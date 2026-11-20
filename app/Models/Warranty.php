<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\ActivityLogger;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

/**
 * Warranty Lifecycle & Claims Engine.
 *
 * Enforces:
 * - Warranty starts strictly upon confirmed customer delivery date (never fabricates dates for unissued stock).
 * - Safe month-end & leap-year expiration date arithmetic (default 24 months).
 * - Dynamic status derivation: active, expiring_soon, expired, voided, incomplete_unknown.
 * - Authorized manual warranty adjustments with mandatory documented reason and JSON audit trail.
 */
final class Warranty
{
    /**
     * Safely add months to a YYYY-MM-DD date preserving month-end & leap-year boundaries.
     * Example: 2024-02-29 + 24 months => 2026-02-28; 2026-01-31 + 1 month => 2026-02-28.
     */
    public static function calculateEndDate(string $startDate, int $durationMonths): string
    {
        $dt = new DateTimeImmutable($startDate);
        if ($durationMonths <= 0) {
            return $dt->format('Y-m-d');
        }

        $year = (int) $dt->format('Y');
        $month = (int) $dt->format('n');
        $day = (int) $dt->format('j');

        $totalMonths = ($year * 12) + ($month - 1) + $durationMonths;
        $targetYear = intdiv($totalMonths, 12);
        $targetMonth = ($totalMonths % 12) + 1;

        $daysInTargetMonth = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $targetYear, $targetMonth)))->format('t');
        $targetDay = min($day, $daysInTargetMonth);

        return sprintf('%04d-%02d-%02d', $targetYear, $targetMonth, $targetDay);
    }

    /**
     * Dynamically derive warranty status from dates and system threshold setting.
     */
    public static function deriveStatus(?string $startDate, ?string $endDate, ?string $storedStatus = null): string
    {
        if ($storedStatus === 'voided') {
            return 'voided';
        }
        if (empty($startDate) || empty($endDate)) {
            return 'incomplete_unknown';
        }

        $today = new DateTimeImmutable('today');
        $end = new DateTimeImmutable($endDate);

        if ($end < $today) {
            return 'expired';
        }

        $thresholdDays = Setting::getInt('warranty_expiring_soon_days', 30);
        $soonCutoff = $today->modify("+{$thresholdDays} days");

        if ($end <= $soonCutoff) {
            return 'expiring_soon';
        }

        return 'active';
    }

    public static function generateWarrantyCode(PDO $pdo): string
    {
        $year = date('Y');
        $prefix = "WAR-{$year}-";
        $stmt = $pdo->prepare('SELECT warranty_code FROM warranties WHERE warranty_code LIKE :p ORDER BY id DESC LIMIT 1');
        $stmt->execute(['p' => $prefix . '%']);
        $last = $stmt->fetchColumn();

        if (is_string($last) && preg_match('/-(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        return sprintf('%s%04d', $prefix, $next);
    }

    public static function generateClaimNumber(PDO $pdo): string
    {
        $year = date('Y');
        $prefix = "CLM-{$year}-";
        $stmt = $pdo->prepare('SELECT claim_number FROM warranty_claims WHERE claim_number LIKE :p ORDER BY id DESC LIMIT 1');
        $stmt->execute(['p' => $prefix . '%']);
        $last = $stmt->fetchColumn();

        if (is_string($last) && preg_match('/-(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        return sprintf('%s%04d', $prefix, $next);
    }

    /**
     * Activate or create warranty certificates for all serialized units on a confirmed customer delivery.
     * Must be called within an active PDO transaction.
     *
     * @return list<int> Created/updated warranty IDs
     */
    public static function activateForDelivery(PDO $pdo, int $deliveryId, string $deliveryDate, int $userId): array
    {
        $delStmt = $pdo->prepare('SELECT * FROM customer_deliveries WHERE id = :id LIMIT 1');
        $delStmt->execute(['id' => $deliveryId]);
        $delivery = $delStmt->fetch(PDO::FETCH_ASSOC);
        if (!$delivery) {
            return [];
        }

        $policy = (string) Setting::get('warranty_start_policy', 'actual_delivery_date');
        $defaultMonths = Setting::getInt('default_warranty_months', 24);

        $sql = "SELECT cds.unit_id,
                       cdi.inventory_item_id,
                       cdi.warranty_duration_months,
                       ii.default_warranty_months
                FROM customer_delivery_serials cds
                INNER JOIN customer_delivery_items cdi ON cdi.id = cds.delivery_item_id
                INNER JOIN inventory_items ii ON ii.id = cdi.inventory_item_id
                WHERE cdi.delivery_id = :del_id
                  AND cds.is_active = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['del_id' => $deliveryId]);
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $warrantyIds = [];
        foreach ($units as $u) {
            $unitId = (int) $u['unit_id'];
            $itemId = (int) $u['inventory_item_id'];
            $months = (int) ($u['warranty_duration_months'] ?? $u['default_warranty_months'] ?? $defaultMonths);
            if ($months <= 0) {
                $months = $defaultMonths;
            }

            $endDate = self::calculateEndDate($deliveryDate, $months);
            $status = self::deriveStatus($deliveryDate, $endDate);

            // Check if warranty already exists for this unit + delivery
            $chk = $pdo->prepare('SELECT id FROM warranties WHERE unit_id = :uid AND delivery_id = :did LIMIT 1');
            $chk->execute(['uid' => $unitId, 'did' => $deliveryId]);
            $existingId = $chk->fetchColumn();

            if ($existingId) {
                $pdo->prepare(
                    'UPDATE warranties
                     SET start_date = :sd, duration_months = :dur, end_date = :ed, status = :st, policy_applied = :pol, updated_at = NOW()
                     WHERE id = :id'
                )->execute([
                    'sd' => $deliveryDate,
                    'dur' => $months,
                    'ed' => $endDate,
                    'st' => $status,
                    'pol' => $policy,
                    'id' => (int) $existingId,
                ]);
                $warrantyId = (int) $existingId;
            } else {
                $code = self::generateWarrantyCode($pdo);
                $pdo->prepare(
                    'INSERT INTO warranties (
                        warranty_code, unit_id, inventory_item_id, client_id, delivery_id,
                        start_date, duration_months, end_date, status, policy_applied, terms
                     ) VALUES (
                        :code, :uid, :item_id, :cid, :did,
                        :sd, :dur, :ed, :st, :pol, :terms
                     )'
                )->execute([
                    'code' => $code,
                    'uid' => $unitId,
                    'item_id' => $itemId,
                    'cid' => !empty($delivery['client_id']) ? (int) $delivery['client_id'] : null,
                    'did' => $deliveryId,
                    'sd' => $deliveryDate,
                    'dur' => $months,
                    'ed' => $endDate,
                    'st' => $status,
                    'pol' => $policy,
                    'terms' => "Standard {$months}-month hardware warranty from confirmed delivery date.",
                ]);
                $warrantyId = (int) $pdo->lastInsertId();
            }

            // Sync unit warranty dates
            $pdo->prepare(
                'UPDATE inventory_units
                 SET warranty_start_date = :sd, warranty_expiry_date = :ed, warranty_duration = :dur_str, updated_at = NOW()
                 WHERE id = :uid'
            )->execute([
                'sd' => $deliveryDate,
                'ed' => $endDate,
                'dur_str' => "{$months} Months",
                'uid' => $unitId,
            ]);

            $warrantyIds[] = $warrantyId;
        }

        if ($warrantyIds !== []) {
            ActivityLogger::log(
                'WARRANTY_ACTIVATED',
                'customer_delivery',
                $deliveryId,
                'Activated ' . count($warrantyIds) . " warranty certificate(s) starting {$deliveryDate}.",
                null,
                $userId,
                false,
                null,
                ['warranty_ids' => $warrantyIds, 'start_date' => $deliveryDate]
            );
        }

        return $warrantyIds;
    }

    /**
     * Authorized manual warranty adjustment with mandatory reason and immutable audit log.
     *
     * @return array<string, mixed>
     */
    public static function adjustWarranty(
        int $warrantyId,
        ?string $newStartDate,
        int $newDurationMonths,
        string $reason,
        int $userId,
        ?string $remarks = null
    ): array {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A documented adjustment reason is mandatory.');
        }
        if ($newDurationMonths < 0) {
            throw new InvalidArgumentException('Warranty duration months cannot be negative.');
        }

        return Database::transaction(static function (PDO $pdo) use ($warrantyId, $newStartDate, $newDurationMonths, $reason, $userId, $remarks): array {
            $stmt = $pdo->prepare('SELECT * FROM warranties WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $warrantyId]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) {
                throw new InvalidArgumentException("Warranty #{$warrantyId} not found.");
            }

            $startDate = ($newStartDate !== null && trim($newStartDate) !== '') ? trim($newStartDate) : null;
            $endDate = $startDate !== null ? self::calculateEndDate($startDate, $newDurationMonths) : null;
            $newStatus = self::deriveStatus($startDate, $endDate, $old['status'] === 'voided' ? 'voided' : null);

            $pdo->prepare(
                'UPDATE warranties
                 SET start_date = :sd,
                     duration_months = :dur,
                     end_date = :ed,
                     status = :st,
                     remarks = COALESCE(:rem, remarks),
                     policy_applied = "manual_adjustment",
                     last_adjusted_by_id = :uid,
                     last_adjustment_reason = :reason,
                     last_adjusted_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'sd' => $startDate,
                'dur' => $newDurationMonths,
                'ed' => $endDate,
                'st' => $newStatus,
                'rem' => $remarks !== null && trim($remarks) !== '' ? trim($remarks) : null,
                'uid' => $userId,
                'reason' => $reason,
                'id' => $warrantyId,
            ]);

            // Sync inventory_units
            $pdo->prepare(
                'UPDATE inventory_units
                 SET warranty_start_date = :sd, warranty_expiry_date = :ed, warranty_duration = :dur_str, updated_at = NOW()
                 WHERE id = :unit_id'
            )->execute([
                'sd' => $startDate,
                'ed' => $endDate,
                'dur_str' => "{$newDurationMonths} Months",
                'unit_id' => (int) $old['unit_id'],
            ]);

            ActivityLogger::log(
                'WARRANTY_ADJUSTED',
                'warranty',
                $warrantyId,
                "Warranty {$old['warranty_code']} manually adjusted: {$reason}",
                null,
                $userId,
                false,
                [
                    'start_date' => $old['start_date'],
                    'duration_months' => $old['duration_months'],
                    'end_date' => $old['end_date'],
                    'status' => $old['status'],
                ],
                [
                    'start_date' => $startDate,
                    'duration_months' => $newDurationMonths,
                    'end_date' => $endDate,
                    'status' => $newStatus,
                    'reason' => $reason,
                ]
            );

            return (array) self::find($warrantyId);
        });
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $db = Database::connection();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(w.warranty_code LIKE :s1 OR iu.serial_number LIKE :s2 OR ii.item_name LIKE :s3 OR c.company_name LIKE :s4 OR cd.dr_number LIKE :s5)';
            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
            $params['s4'] = $term;
            $params['s5'] = $term;
        }

        if (!empty($filters['client_id'])) {
            $where[] = 'w.client_id = :cid';
            $params['cid'] = (int) $filters['client_id'];
        }

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT w.*,
                       iu.serial_number,
                       iu.status AS unit_status,
                       ii.item_code,
                       ii.item_name,
                       ii.model_part_number,
                       COALESCE(c.company_name, cd.client_name) AS client_name,
                       cd.dr_number,
                       cd.delivery_number,
                       u.full_name AS adjusted_by_name
                FROM warranties w
                INNER JOIN inventory_units iu ON iu.id = w.unit_id
                INNER JOIN inventory_items ii ON ii.id = w.inventory_item_id
                LEFT JOIN clients c ON c.id = w.client_id
                LEFT JOIN customer_deliveries cd ON cd.id = w.delivery_id
                LEFT JOIN users u ON u.id = w.last_adjusted_by_id
                WHERE {$whereSql}
                ORDER BY w.end_date ASC, w.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all as &$row) {
            $row['derived_status'] = self::deriveStatus($row['start_date'], $row['end_date'], $row['status']);
        }
        unset($row);

        if (!empty($filters['status'])) {
            $st = trim((string) $filters['status']);
            $all = array_values(array_filter($all, static fn(array $r): bool => $r['derived_status'] === $st));
        }

        $total = count($all);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        return [
            'data' => array_slice($all, $offset, $perPage),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.*,
                    iu.serial_number,
                    iu.status AS unit_status,
                    ii.item_code,
                    ii.item_name,
                    ii.model_part_number,
                    COALESCE(c.company_name, cd.client_name) AS client_name,
                    cd.dr_number,
                    cd.delivery_number,
                    cd.actual_delivery_date,
                    u.full_name AS adjusted_by_name
             FROM warranties w
             INNER JOIN inventory_units iu ON iu.id = w.unit_id
             INNER JOIN inventory_items ii ON ii.id = w.inventory_item_id
             LEFT JOIN clients c ON c.id = w.client_id
             LEFT JOIN customer_deliveries cd ON cd.id = w.delivery_id
             LEFT JOIN users u ON u.id = w.last_adjusted_by_id
             WHERE w.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['derived_status'] = self::deriveStatus($row['start_date'], $row['end_date'], $row['status']);
        return $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function createClaim(int $warrantyId, array $data, int $userId): int
    {
        $issue = trim((string) ($data['issue_description'] ?? ''));
        if ($issue === '') {
            throw new InvalidArgumentException('Issue description is required for warranty claim.');
        }

        return Database::transaction(static function (PDO $pdo) use ($warrantyId, $data, $issue, $userId): int {
            $w = self::find($warrantyId);
            if (!$w) {
                throw new InvalidArgumentException('Warranty certificate not found.');
            }

            $claimNo = self::generateClaimNumber($pdo);
            $pdo->prepare(
                'INSERT INTO warranty_claims (
                    claim_number, warranty_id, unit_id, client_id, handled_by_id,
                    claim_date, issue_description, resolution_type, status
                 ) VALUES (
                    :no, :wid, :uid, :cid, :handler,
                    :cdate, :issue, "pending", "received"
                 )'
            )->execute([
                'no' => $claimNo,
                'wid' => $warrantyId,
                'uid' => (int) $w['unit_id'],
                'cid' => !empty($w['client_id']) ? (int) $w['client_id'] : null,
                'handler' => $userId,
                'cdate' => !empty($data['claim_date']) ? (string) $data['claim_date'] : date('Y-m-d'),
                'issue' => $issue,
            ]);

            $claimId = (int) $pdo->lastInsertId();
            ActivityLogger::log(
                'WARRANTY_CLAIM_CREATED',
                'warranty_claim',
                $claimId,
                "Filed warranty claim {$claimNo} for warranty {$w['warranty_code']} (Serial: {$w['serial_number']}).",
                null,
                $userId,
                false,
                null,
                ['claim_number' => $claimNo, 'issue' => $issue]
            );

            return $claimId;
        });
    }
}
