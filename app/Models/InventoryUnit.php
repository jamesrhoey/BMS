<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\ActivityLogger;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Serialized Physical Unit Lifecycle, Maintenance & Serial Dossier Model.
 *
 * Enforces strict unit states:
 * - in_stock      (Available for Stock Out issuance)
 * - delivered     (Issued to customer)
 * - under_repair  (Under Maintenance — excluded from available stock)
 * - scrapped      (Retired — excluded from available and on-hand stock)
 */
final class InventoryUnit
{
    public const STATUS_IN_STOCK = 'in_stock';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_UNDER_REPAIR = 'under_repair';
    public const STATUS_SCRAPPED = 'scrapped';

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $db = Database::connection();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(iu.serial_number LIKE :s1 OR iu.model LIKE :s2 OR iu.description LIKE :s3 OR iu.po_reference LIKE :s4 OR iu.dr_reference LIKE :s5 OR ii.item_name LIKE :s6)';
            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
            $params['s4'] = $term;
            $params['s5'] = $term;
            $params['s6'] = $term;
        }

        if (!empty($filters['status'])) {
            $where[] = 'iu.status = :status';
            $params['status'] = trim((string) $filters['status']);
        }

        if (!empty($filters['item_id'])) {
            $where[] = 'iu.inventory_item_id = :item_id';
            $params['item_id'] = (int) $filters['item_id'];
        }

        if (!empty($filters['location_id'])) {
            $where[] = 'iu.location_id = :loc_id';
            $params['loc_id'] = (int) $filters['location_id'];
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $db->prepare(
            "SELECT COUNT(*) FROM inventory_units iu INNER JOIN inventory_items ii ON ii.id = iu.inventory_item_id WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT iu.*,
                       ii.item_code,
                       ii.item_name,
                       COALESCE(ii.model_part_number, iu.model) AS model_code,
                       loc.name AS location_name,
                       c.company_name AS client_name,
                       s.supplier_name
                FROM inventory_units iu
                INNER JOIN inventory_items ii ON ii.id = iu.inventory_item_id
                LEFT JOIN inventory_locations loc ON loc.id = iu.location_id
                LEFT JOIN clients c ON c.id = iu.client_id
                LEFT JOIN suppliers s ON s.id = iu.supplier_id
                WHERE {$whereSql}
                ORDER BY iu.id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
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
            'SELECT iu.*,
                    ii.item_code,
                    ii.item_name,
                    COALESCE(ii.model_part_number, iu.model) AS model_code,
                    ii.default_warranty_months,
                    loc.name AS location_name,
                    c.company_name AS client_name,
                    s.supplier_name,
                    sr.receipt_number,
                    cd.delivery_number,
                    cd.dr_number,
                    cd.status AS delivery_status
             FROM inventory_units iu
             INNER JOIN inventory_items ii ON ii.id = iu.inventory_item_id
             LEFT JOIN inventory_locations loc ON loc.id = iu.location_id
             LEFT JOIN clients c ON c.id = iu.client_id
             LEFT JOIN suppliers s ON s.id = iu.supplier_id
             LEFT JOIN supplier_receipts sr ON sr.id = iu.supplier_receipt_id
             LEFT JOIN customer_deliveries cd ON cd.id = iu.customer_delivery_id
             WHERE iu.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findBySerial(string $serialNumber): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id FROM inventory_units WHERE serial_number = :sn LIMIT 1');
        $stmt->execute(['sn' => trim($serialNumber)]);
        $id = $stmt->fetchColumn();
        return $id ? self::find((int) $id) : null;
    }

    /**
     * Transition a serialized unit between `in_stock`, `under_repair`, and `scrapped` atomically.
     *
     * Rules:
     * - in_stock -> under_repair : decrements quantity_available by 1 (on_hand unchanged), blocks Stock Out.
     * - under_repair -> in_stock : increments quantity_available by 1 (on_hand unchanged), restores Stock Out eligibility.
     * - in_stock -> scrapped     : decrements quantity_on_hand by 1 and quantity_available by 1.
     * - under_repair -> scrapped : decrements quantity_on_hand by 1 (available already excluded).
     *
     * @return array<string, mixed>
     */
    public static function transitionStatus(
        int $unitId,
        string $targetStatus,
        string $reason,
        int $userId
    ): array {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A mandatory reason is required for unit status transitions.');
        }

        $allowedTargets = [self::STATUS_IN_STOCK, self::STATUS_UNDER_REPAIR, self::STATUS_SCRAPPED];
        if (!in_array($targetStatus, $allowedTargets, true)) {
            throw new InvalidArgumentException("Invalid target status '{$targetStatus}'.");
        }

        return Database::transaction(static function (PDO $pdo) use ($unitId, $targetStatus, $reason, $userId): array {
            $stmt = $pdo->prepare('SELECT * FROM inventory_units WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $unitId]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                throw new InvalidArgumentException("Inventory unit #{$unitId} not found.");
            }

            $currentStatus = (string) $unit['status'];
            if ($currentStatus === $targetStatus) {
                throw new InvalidArgumentException("Unit {$unit['serial_number']} is already in status '{$targetStatus}'.");
            }
            if ($currentStatus === self::STATUS_DELIVERED) {
                throw new RuntimeException("Unit {$unit['serial_number']} is currently delivered to a client. Cancel or return the delivery first.");
            }
            if ($currentStatus === self::STATUS_SCRAPPED) {
                throw new RuntimeException("Unit {$unit['serial_number']} is permanently scrapped and cannot be reactivated.");
            }

            $itemId = (int) $unit['inventory_item_id'];
            $locationId = (int) ($unit['location_id'] ?? 1);

            $deltaOnHand = 0;
            $deltaAvailable = 0;
            $qtyIn = 0;
            $qtyOut = 0;
            $ledgerType = 'MAINTENANCE';

            if ($currentStatus === self::STATUS_IN_STOCK && $targetStatus === self::STATUS_UNDER_REPAIR) {
                $deltaOnHand = 0;
                $deltaAvailable = -1;
                $ledgerType = 'MAINTENANCE_OUT';
            } elseif ($currentStatus === self::STATUS_UNDER_REPAIR && $targetStatus === self::STATUS_IN_STOCK) {
                $deltaOnHand = 0;
                $deltaAvailable = 1;
                $ledgerType = 'MAINTENANCE_IN';
            } elseif ($currentStatus === self::STATUS_IN_STOCK && $targetStatus === self::STATUS_SCRAPPED) {
                $deltaOnHand = -1;
                $deltaAvailable = -1;
                $qtyOut = 1;
                $ledgerType = 'SCRAP';
            } elseif ($currentStatus === self::STATUS_UNDER_REPAIR && $targetStatus === self::STATUS_SCRAPPED) {
                $deltaOnHand = -1;
                $deltaAvailable = 0;
                $qtyOut = 1;
                $ledgerType = 'SCRAP';
            } else {
                throw new RuntimeException("Unsupported status transition: {$currentStatus} -> {$targetStatus}.");
            }

            // Record stock_transaction event
            $txNum = InventoryLedger::generateStockTransactionNumber($pdo, 'MNT');
            $pdo->prepare(
                'INSERT INTO stock_transactions (transaction_number, transaction_type, reference_type, reference_id, source_location_id, destination_location_id, performed_by_id, transaction_date, notes)
                 VALUES (:tx_num, "MAINTENANCE", "MAINTENANCE", :unit_id, :loc1, :loc2, :uid, NOW(), :notes)'
            )->execute([
                'tx_num' => $txNum,
                'unit_id' => $unitId,
                'loc1' => $locationId,
                'loc2' => $locationId,
                'uid' => $userId,
                'notes' => "Status {$currentStatus} -> {$targetStatus}: {$reason}",
            ]);
            $txId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO stock_transaction_items (transaction_id, inventory_item_id, quantity, unit_cost, notes)
                 VALUES (:tx_id, :item_id, 1, :cost, :notes)'
            )->execute([
                'tx_id' => $txId,
                'item_id' => $itemId,
                'cost' => (float) ($unit['unit_cost'] ?? 0),
                'notes' => $reason,
            ]);
            $txItemId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO stock_transaction_serials (transaction_item_id, unit_id) VALUES (:tx_item_id, :unit_id)'
            )->execute([
                'tx_item_id' => $txItemId,
                'unit_id' => $unitId,
            ]);

            // Update unit status
            $pdo->prepare(
                'UPDATE inventory_units SET status = :st, notes = CONCAT(COALESCE(notes, ""), IF(notes IS NULL OR notes = "", "", "\n"), :note), updated_at = NOW() WHERE id = :id'
            )->execute([
                'st' => $targetStatus,
                'note' => '[' . date('Y-m-d H:i') . " {$currentStatus}->{$targetStatus}] {$reason}",
                'id' => $unitId,
            ]);

            // Post ledger + stock cache update
            InventoryLedger::postMovement($pdo, [
                'inventory_item_id' => $itemId,
                'location_id' => $locationId,
                'transaction_type' => $ledgerType,
                'reference_type' => 'MAINTENANCE',
                'reference_id' => $txId,
                'quantity_in' => $qtyIn,
                'quantity_out' => $qtyOut,
                'delta_on_hand' => $deltaOnHand,
                'delta_available' => $deltaAvailable,
                'unit_cost' => (float) ($unit['unit_cost'] ?? 0),
                'performed_by_id' => $userId,
                'notes' => "Unit {$unit['serial_number']}: {$currentStatus} -> {$targetStatus} ({$reason})",
            ]);

            ActivityLogger::log(
                'UNIT_STATUS_CHANGED',
                'inventory_unit',
                $unitId,
                "Unit {$unit['serial_number']} changed from {$currentStatus} to {$targetStatus}: {$reason}",
                null,
                $userId,
                false,
                ['status' => $currentStatus],
                ['status' => $targetStatus, 'reason' => $reason, 'transaction_number' => $txNum]
            );

            return (array) self::find($unitId);
        });
    }

    /**
     * Complete chronological Serial Movement History Dossier.
     *
     * @return array<string, mixed>
     */
    public static function getSerialDossier(int $unitId): array
    {
        $unit = self::find($unitId);
        if (!$unit) {
            throw new InvalidArgumentException("Inventory unit #{$unitId} not found.");
        }

        $db = Database::connection();

        // 1. Stock transactions history (Stock In, Maintenance, Stock Out, Reversals)
        $txStmt = $db->prepare(
            'SELECT st.id, st.transaction_number, st.transaction_type, st.reference_type, st.reference_id,
                    st.transaction_date, st.notes, u.full_name AS performed_by_name
             FROM stock_transaction_serials sts
             INNER JOIN stock_transaction_items sti ON sti.id = sts.transaction_item_id
             INNER JOIN stock_transactions st ON st.id = sti.transaction_id
             LEFT JOIN users u ON u.id = st.performed_by_id
             WHERE sts.unit_id = :uid
             ORDER BY st.transaction_date ASC, st.id ASC'
        );
        $txStmt->execute(['uid' => $unitId]);
        $transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Delivery assignments history (including cancelled/returned dispatches)
        $delStmt = $db->prepare(
            'SELECT cd.id, cd.delivery_number, cd.dr_number, cd.invoice_number, cd.delivery_date,
                    cd.actual_delivery_date, cd.status, COALESCE(c.company_name, cd.client_name) AS client_name,
                    cd.shipping_address, cds.is_active, cds.returned_at
             FROM customer_delivery_serials cds
             INNER JOIN customer_delivery_items cdi ON cdi.id = cds.delivery_item_id
             INNER JOIN customer_deliveries cd ON cd.id = cdi.delivery_id
             LEFT JOIN clients c ON c.id = cd.client_id
             WHERE cds.unit_id = :uid
             ORDER BY cd.delivery_date ASC, cd.id ASC'
        );
        $delStmt->execute(['uid' => $unitId]);
        $deliveries = $delStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Warranties & Claims
        $warStmt = $db->prepare(
            'SELECT w.*, COALESCE(c.company_name, cd.client_name) AS client_name
             FROM warranties w
             LEFT JOIN clients c ON c.id = w.client_id
             LEFT JOIN customer_deliveries cd ON cd.id = w.delivery_id
             WHERE w.unit_id = :uid
             ORDER BY w.id DESC'
        );
        $warStmt->execute(['uid' => $unitId]);
        $warranties = $warStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($warranties as &$w) {
            $w['derived_status'] = Warranty::deriveStatus($w['start_date'], $w['end_date'], $w['status']);
        }
        unset($w);

        $claimStmt = $db->prepare(
            'SELECT wc.*, u.full_name AS handled_by_name
             FROM warranty_claims wc
             LEFT JOIN users u ON u.id = wc.handled_by_id
             WHERE wc.unit_id = :uid
             ORDER BY wc.claim_date DESC, wc.id DESC'
        );
        $claimStmt->execute(['uid' => $unitId]);
        $claims = $claimStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'unit' => $unit,
            'transactions' => $transactions,
            'deliveries' => $deliveries,
            'warranties' => $warranties,
            'claims' => $claims,
        ];
    }
}
