<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\ActivityLogger;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Payment Collections & Vendor Disbursements Model backed by `collections`.
 *
 * Enforces:
 * - Atomic lockstep updates with `invoices` (`paid_amount`, `balance_due`, `status`) via FOR UPDATE.
 * - Strict overpayment prevention (`amount_received <= invoice.balance_due`).
 * - Auditable voiding workflow with mandatory reason that atomically restores invoice balance & status.
 */
final class Collection
{
    public static function generateCollectionNumber(string $paymentType = 'collection', ?PDO $pdo = null): string
    {
        $db = $pdo ?? Database::connection();
        $year = date('Y');
        $prefix = ($paymentType === 'disbursement' ? 'PAY' : 'COL') . "-{$year}-";

        $stmt = $db->prepare('SELECT collection_number FROM collections WHERE collection_number LIKE :p ORDER BY id DESC LIMIT 1');
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
     * Record a partial or full payment collection atomically.
     *
     * @param array<string, mixed> $data
     * @return array{collection_id: int, collection_number: string, invoice_status: string, balance_due: float}
     */
    public static function recordCollection(array $data, int $userId): array
    {
        $invoiceId = (int) ($data['invoice_id'] ?? 0);
        $amount = round((float) ($data['amount_received'] ?? 0), 2);
        $collectionDate = trim((string) ($data['collection_date'] ?? date('Y-m-d')));

        if ($invoiceId <= 0) {
            throw new InvalidArgumentException('Valid invoice is required.');
        }
        if ($amount <= 0.00) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $allowedMethods = ['cash', 'check', 'bank_transfer', 'credit_card', 'other'];
        $method = (string) ($data['payment_method'] ?? 'bank_transfer');
        if (!in_array($method, $allowedMethods, true)) {
            $method = 'other';
        }

        return Database::transaction(static function (PDO $pdo) use ($data, $userId, $invoiceId, $amount, $collectionDate, $method): array {
            // Lock target invoice
            $invStmt = $pdo->prepare('SELECT * FROM invoices WHERE id = :id FOR UPDATE');
            $invStmt->execute(['id' => $invoiceId]);
            $inv = $invStmt->fetch(PDO::FETCH_ASSOC);

            if (!$inv) {
                throw new InvalidArgumentException("Invoice #{$invoiceId} not found.");
            }
            if ((string) $inv['status'] === 'cancelled') {
                throw new RuntimeException("Cannot record payment against cancelled invoice {$inv['invoice_number']}.");
            }
            if ((string) $inv['status'] === 'paid' || (float) $inv['balance_due'] <= 0.005) {
                throw new RuntimeException("Invoice {$inv['invoice_number']} is already fully paid.");
            }
            if ($amount > (float) $inv['balance_due'] + 0.005) {
                throw new RuntimeException(
                    "Payment amount (" . number_format($amount, 2) . ") exceeds remaining balance due (" . number_format((float) $inv['balance_due'], 2) . ")."
                );
            }

            $paymentType = ((string) $inv['invoice_type'] === Invoice::TYPE_PAYABLE) ? 'disbursement' : 'collection';
            $colNo = isset($data['collection_number']) && trim((string) $data['collection_number']) !== ''
                ? trim((string) $data['collection_number'])
                : self::generateCollectionNumber($paymentType, $pdo);

            $pdo->prepare(
                'INSERT INTO collections (
                    collection_number, payment_type, invoice_id, client_id, supplier_id,
                    recorded_by_id, collection_date, payment_method, reference_number,
                    amount_received, currency, deposited_to_account, status, notes
                 ) VALUES (
                    :cno, :ptype, :inv_id, :cid, :sid,
                    :uid, :cdate, :method, :ref,
                    :amt, :curr, :acct, "cleared", :notes
                 )'
            )->execute([
                'cno' => $colNo,
                'ptype' => $paymentType,
                'inv_id' => $invoiceId,
                'cid' => $inv['client_id'],
                'sid' => $inv['supplier_id'],
                'uid' => $userId,
                'cdate' => $collectionDate,
                'method' => $method,
                'ref' => ($data['reference_number'] ?? '') !== '' ? trim((string) $data['reference_number']) : null,
                'amt' => $amount,
                'curr' => (string) $inv['currency'],
                'acct' => ($data['deposited_to_account'] ?? '') !== '' ? trim((string) $data['deposited_to_account']) : null,
                'notes' => ($data['notes'] ?? '') !== '' ? trim((string) $data['notes']) : null,
            ]);
            $collectionId = (int) $pdo->lastInsertId();

            $updatedInv = Invoice::recalculateTotalsAndStatus($pdo, $invoiceId);

            ActivityLogger::log(
                'COLLECTION_RECORDED',
                'collection',
                $collectionId,
                "Recorded {$paymentType} {$colNo} of {$inv['currency']} " . number_format($amount, 2) . " for invoice {$inv['invoice_number']}.",
                null,
                $userId,
                false,
                ['invoice_balance_before' => (float) $inv['balance_due'], 'invoice_status_before' => $inv['status']],
                [
                    'collection_number' => $colNo,
                    'amount_received' => $amount,
                    'payment_method' => $method,
                    'invoice_balance_after' => (float) $updatedInv['balance_due'],
                    'invoice_status_after' => $updatedInv['status'],
                ]
            );

            return [
                'collection_id' => $collectionId,
                'collection_number' => $colNo,
                'invoice_status' => (string) $updatedInv['status'],
                'balance_due' => (float) $updatedInv['balance_due'],
            ];
        });
    }

    /**
     * Void a collection with a mandatory reason and atomically restore invoice balance_due & status.
     *
     * @return array<string, mixed>
     */
    public static function voidCollection(int $collectionId, string $reason, int $userId): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A mandatory void reason is required.');
        }

        return Database::transaction(static function (PDO $pdo) use ($collectionId, $reason, $userId): array {
            $stmt = $pdo->prepare('SELECT * FROM collections WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $collectionId]);
            $col = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$col) {
                throw new InvalidArgumentException("Collection #{$collectionId} not found.");
            }
            if ((string) $col['status'] === 'voided') {
                throw new RuntimeException("Collection {$col['collection_number']} is already voided.");
            }

            $oldStatus = (string) $col['status'];
            $pdo->prepare(
                'UPDATE collections
                 SET status = "voided",
                     void_reason = :reason,
                     voided_by_id = :uid,
                     voided_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'reason' => $reason,
                'uid' => $userId,
                'id' => $collectionId,
            ]);

            $updatedInv = Invoice::recalculateTotalsAndStatus($pdo, (int) $col['invoice_id']);

            ActivityLogger::log(
                'COLLECTION_VOIDED',
                'collection',
                $collectionId,
                "Voided collection {$col['collection_number']} ({$col['currency']} " . number_format((float) $col['amount_received'], 2) . "): {$reason}",
                null,
                $userId,
                false,
                ['status' => $oldStatus],
                [
                    'status' => 'voided',
                    'void_reason' => $reason,
                    'invoice_balance_after' => (float) $updatedInv['balance_due'],
                    'invoice_status_after' => $updatedInv['status'],
                ]
            );

            return [
                'collection_id' => $collectionId,
                'invoice_id' => (int) $col['invoice_id'],
                'invoice_status' => (string) $updatedInv['status'],
                'balance_due' => (float) $updatedInv['balance_due'],
            ];
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
            $where[] = '(col.collection_number LIKE :s1 OR col.reference_number LIKE :s2 OR i.invoice_number LIKE :s3 OR c.company_name LIKE :s4 OR s.supplier_name LIKE :s5)';
            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
            $params['s4'] = $term;
            $params['s5'] = $term;
        }

        if (!empty($filters['status'])) {
            $where[] = 'col.status = :st';
            $params['st'] = trim((string) $filters['status']);
        }

        if (!empty($filters['invoice_id'])) {
            $where[] = 'col.invoice_id = :inv_id';
            $params['inv_id'] = (int) $filters['invoice_id'];
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $db->prepare(
            "SELECT COUNT(col.id) FROM collections col
             INNER JOIN invoices i ON i.id = col.invoice_id
             LEFT JOIN clients c ON c.id = col.client_id
             LEFT JOIN suppliers s ON s.id = col.supplier_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT col.*,
                       i.invoice_number,
                       i.invoice_type,
                       c.company_name AS client_name,
                       s.supplier_name,
                       u.full_name AS recorded_by_name,
                       vu.full_name AS voided_by_name
                FROM collections col
                INNER JOIN invoices i ON i.id = col.invoice_id
                LEFT JOIN clients c ON c.id = col.client_id
                LEFT JOIN suppliers s ON s.id = col.supplier_id
                LEFT JOIN users u ON u.id = col.recorded_by_id
                LEFT JOIN users vu ON vu.id = col.voided_by_id
                WHERE {$whereSql}
                ORDER BY col.collection_date DESC, col.id DESC
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
     * @return list<array<string, mixed>>
     */
    public static function forInvoice(int $invoiceId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT col.*, u.full_name AS recorded_by_name, vu.full_name AS voided_by_name
             FROM collections col
             LEFT JOIN users u ON u.id = col.recorded_by_id
             LEFT JOIN users vu ON vu.id = col.voided_by_id
             WHERE col.invoice_id = :id
             ORDER BY col.collection_date DESC, col.id DESC'
        );
        $stmt->execute(['id' => $invoiceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
