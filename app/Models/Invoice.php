<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\ActivityLogger;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Invoices Model (Accounts Receivable & Accounts Payable).
 *
 * Supports:
 * - Receivable (Customer Sales Invoices linked to Customer Deliveries / DRs)
 * - Payable (Supplier Vendor Bills linked to POs / Supplier Receipts)
 * - Preserves leading zeros on invoice_number (VARCHAR)
 * - Automatic subtotal, VAT/tax, total_amount, paid_amount, balance_due, and status progression.
 */
final class Invoice
{
    public const TYPE_RECEIVABLE = 'receivable';
    public const TYPE_PAYABLE = 'payable';

    public static function generateInvoiceNumber(string $type = self::TYPE_RECEIVABLE, ?PDO $pdo = null): string
    {
        $db = $pdo ?? Database::connection();
        $year = date('Y');
        $prefix = ($type === self::TYPE_PAYABLE ? 'BILL' : 'INV') . "-{$year}-";

        $stmt = $db->prepare('SELECT invoice_number FROM invoices WHERE invoice_number LIKE :p ORDER BY id DESC LIMIT 1');
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
     * Create a receivable or payable invoice atomically.
     *
     * @param array<string, mixed> $header
     * @param list<array<string, mixed>> $items
     * @return array{invoice_id: int, invoice_number: string, total_amount: float}
     */
    public static function create(array $header, array $items, int $userId): array
    {
        if ($items === []) {
            throw new InvalidArgumentException('At least one invoice line item is required.');
        }

        $type = in_array((string) ($header['invoice_type'] ?? 'receivable'), [self::TYPE_RECEIVABLE, self::TYPE_PAYABLE], true)
            ? (string) $header['invoice_type']
            : self::TYPE_RECEIVABLE;

        $invoiceDate = trim((string) ($header['invoice_date'] ?? date('Y-m-d')));
        if ($invoiceDate === '') {
            throw new InvalidArgumentException('Invoice date is required.');
        }

        return Database::transaction(static function (PDO $pdo) use ($header, $items, $userId, $type, $invoiceDate): array {
            $invoiceNumber = isset($header['invoice_number']) && trim((string) $header['invoice_number']) !== ''
                ? trim((string) $header['invoice_number'])
                : self::generateInvoiceNumber($type, $pdo);

            $dup = $pdo->prepare('SELECT id FROM invoices WHERE invoice_number = :no LIMIT 1');
            $dup->execute(['no' => $invoiceNumber]);
            if ($dup->fetchColumn()) {
                throw new RuntimeException("Invoice number '{$invoiceNumber}' already exists.");
            }

            $clientId = !empty($header['client_id']) ? (int) $header['client_id'] : null;
            $supplierId = !empty($header['supplier_id']) ? (int) $header['supplier_id'] : null;
            $deliveryId = !empty($header['delivery_id']) ? (int) $header['delivery_id'] : null;
            $poId = !empty($header['po_id']) ? (int) $header['po_id'] : null;
            $receiptId = !empty($header['receipt_id']) ? (int) $header['receipt_id'] : null;
            $paymentTermId = !empty($header['payment_term_id']) ? (int) $header['payment_term_id'] : null;

            if ($type === self::TYPE_RECEIVABLE && $clientId === null && $deliveryId !== null) {
                $dStmt = $pdo->prepare('SELECT client_id FROM customer_deliveries WHERE id = :id LIMIT 1');
                $dStmt->execute(['id' => $deliveryId]);
                $dCid = $dStmt->fetchColumn();
                if ($dCid) {
                    $clientId = (int) $dCid;
                }
            }

            if ($type === self::TYPE_PAYABLE && $supplierId === null && $poId !== null) {
                $pStmt = $pdo->prepare('SELECT supplier_id FROM purchase_orders WHERE id = :id LIMIT 1');
                $pStmt->execute(['id' => $poId]);
                $pSid = $pStmt->fetchColumn();
                if ($pSid) {
                    $supplierId = (int) $pSid;
                }
            }

            // Compute due date
            $dueDate = trim((string) ($header['due_date'] ?? ''));
            if ($dueDate === '') {
                $creditDays = 0;
                if ($paymentTermId !== null) {
                    $pt = PaymentTerm::find($paymentTermId);
                    if ($pt) {
                        $creditDays = (int) $pt['days'];
                    }
                }
                $dueDate = PaymentTerm::calculateDueDate($invoiceDate, $creditDays);
            }

            // Compute line totals
            $subtotal = 0.00;
            $cleanItems = [];
            foreach ($items as $idx => $it) {
                $desc = trim((string) ($it['description'] ?? ''));
                $qty = (int) ($it['quantity'] ?? 0);
                $price = round((float) ($it['unit_price'] ?? 0), 2);
                if ($desc === '' || $qty <= 0 || $price < 0) {
                    throw new InvalidArgumentException('Invoice line #' . ($idx + 1) . ' requires description, positive quantity, and non-negative unit price.');
                }
                $lineTotal = round($qty * $price, 2);
                $subtotal += $lineTotal;
                $cleanItems[] = [
                    'delivery_item_id' => !empty($it['delivery_item_id']) ? (int) $it['delivery_item_id'] : null,
                    'po_item_id' => !empty($it['po_item_id']) ? (int) $it['po_item_id'] : null,
                    'receipt_item_id' => !empty($it['receipt_item_id']) ? (int) $it['receipt_item_id'] : null,
                    'inventory_item_id' => !empty($it['inventory_item_id']) ? (int) $it['inventory_item_id'] : null,
                    'description' => $desc,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'total_price' => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $defaultTax = (float) Setting::get('default_tax_rate', '12.00');
            $taxRate = isset($header['tax_rate']) && $header['tax_rate'] !== ''
                ? max(0.0, round((float) $header['tax_rate'], 2))
                : $defaultTax;
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $totalAmount = round($subtotal + $taxAmount, 2);
            $currency = strtoupper(trim((string) ($header['currency'] ?? 'PHP'))) ?: 'PHP';

            $status = (new DateTimeImmutable($dueDate) < new DateTimeImmutable('today')) ? 'overdue' : 'unpaid';

            $pdo->prepare(
                'INSERT INTO invoices (
                    invoice_number, invoice_type, reference_number, client_id, supplier_id,
                    delivery_id, po_id, receipt_id, payment_term_id, created_by_id,
                    invoice_date, due_date, subtotal, currency, tax_rate, tax_amount,
                    total_amount, paid_amount, balance_due, status, notes
                 ) VALUES (
                    :no, :type, :ref, :cid, :sid,
                    :did, :poid, :rid, :ptid, :uid,
                    :idate, :ddate, :sub, :curr, :trate, :tamt,
                    :tot, 0.00, :bal, :st, :notes
                 )'
            )->execute([
                'no' => $invoiceNumber,
                'type' => $type,
                'ref' => ($header['reference_number'] ?? '') !== '' ? trim((string) $header['reference_number']) : null,
                'cid' => $clientId,
                'sid' => $supplierId,
                'did' => $deliveryId,
                'poid' => $poId,
                'rid' => $receiptId,
                'ptid' => $paymentTermId,
                'uid' => $userId,
                'idate' => $invoiceDate,
                'ddate' => $dueDate,
                'sub' => $subtotal,
                'curr' => $currency,
                'trate' => $taxRate,
                'tamt' => $taxAmount,
                'tot' => $totalAmount,
                'bal' => $totalAmount,
                'st' => $status,
                'notes' => ($header['notes'] ?? '') !== '' ? trim((string) $header['notes']) : null,
            ]);
            $invoiceId = (int) $pdo->lastInsertId();

            $insItem = $pdo->prepare(
                'INSERT INTO invoice_items (
                    invoice_id, delivery_item_id, po_item_id, receipt_item_id,
                    inventory_item_id, description, quantity, unit_price, total_price
                 ) VALUES (
                    :inv_id, :di, :pi, :ri, :ii, :desc, :qty, :price, :tot
                 )'
            );
            foreach ($cleanItems as $ci) {
                $insItem->execute([
                    'inv_id' => $invoiceId,
                    'di' => $ci['delivery_item_id'],
                    'pi' => $ci['po_item_id'],
                    'ri' => $ci['receipt_item_id'],
                    'ii' => $ci['inventory_item_id'],
                    'desc' => $ci['description'],
                    'qty' => $ci['quantity'],
                    'price' => $ci['unit_price'],
                    'tot' => $ci['total_price'],
                ]);
            }

            // If linked to a delivery without an invoice_number, stamp it back on the delivery
            if ($deliveryId !== null) {
                $pdo->prepare('UPDATE customer_deliveries SET invoice_number = COALESCE(invoice_number, :ino) WHERE id = :did')
                    ->execute(['ino' => $invoiceNumber, 'did' => $deliveryId]);
            }

            ActivityLogger::log(
                'INVOICE_CREATED',
                'invoice',
                $invoiceId,
                "Created {$type} invoice {$invoiceNumber} totaling {$currency} " . number_format($totalAmount, 2) . '.',
                null,
                $userId,
                false,
                null,
                [
                    'invoice_number' => $invoiceNumber,
                    'invoice_type' => $type,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'due_date' => $dueDate,
                ]
            );

            return [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $totalAmount,
            ];
        });
    }

    /**
     * Recompute paid_amount, balance_due, and status from cleared collections inside an active transaction.
     *
     * @return array<string, mixed>
     */
    public static function recalculateTotalsAndStatus(PDO $pdo, int $invoiceId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $invoiceId]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$inv) {
            throw new InvalidArgumentException("Invoice #{$invoiceId} not found.");
        }

        if ((string) $inv['status'] === 'cancelled') {
            return $inv;
        }

        $paidStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(amount_received), 0) FROM collections WHERE invoice_id = :id AND status = 'cleared'"
        );
        $paidStmt->execute(['id' => $invoiceId]);
        $paidAmount = round((float) $paidStmt->fetchColumn(), 2);

        $totalAmount = round((float) $inv['total_amount'], 2);
        $balanceDue = max(0.00, round($totalAmount - $paidAmount, 2));

        if ($balanceDue <= 0.005) {
            $balanceDue = 0.00;
            $newStatus = 'paid';
        } elseif ($paidAmount > 0.00) {
            $newStatus = 'partially_paid';
        } elseif (new DateTimeImmutable((string) $inv['due_date']) < new DateTimeImmutable('today')) {
            $newStatus = 'overdue';
        } else {
            $newStatus = 'unpaid';
        }

        $pdo->prepare(
            'UPDATE invoices SET paid_amount = :paid, balance_due = :bal, status = :st, updated_at = NOW() WHERE id = :id'
        )->execute([
            'paid' => $paidAmount,
            'bal' => $balanceDue,
            'st' => $newStatus,
            'id' => $invoiceId,
        ]);

        $inv['paid_amount'] = $paidAmount;
        $inv['balance_due'] = $balanceDue;
        $inv['status'] = $newStatus;
        return $inv;
    }

    /**
     * Cancel an invoice if it has zero cleared collections.
     *
     * @return array<string, mixed>
     */
    public static function cancel(int $invoiceId, string $reason, int $userId): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A cancellation reason is required.');
        }

        return Database::transaction(static function (PDO $pdo) use ($invoiceId, $reason, $userId): array {
            $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $invoiceId]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$inv) {
                throw new InvalidArgumentException("Invoice #{$invoiceId} not found.");
            }
            if ((string) $inv['status'] === 'cancelled') {
                throw new RuntimeException("Invoice {$inv['invoice_number']} is already cancelled.");
            }

            $clearedCnt = (int) $pdo->query("SELECT COUNT(*) FROM collections WHERE invoice_id = {$invoiceId} AND status = 'cleared'")->fetchColumn();
            if ($clearedCnt > 0) {
                throw new RuntimeException('Cannot cancel an invoice with cleared payment collections. Void all collections first.');
            }

            $oldStatus = (string) $inv['status'];
            $pdo->prepare(
                'UPDATE invoices
                 SET status = "cancelled",
                     balance_due = 0.00,
                     notes = CONCAT(COALESCE(notes, ""), IF(notes IS NULL OR notes = "", "", "\n"), :note),
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'note' => '[Cancelled ' . date('Y-m-d H:i') . "] {$reason}",
                'id' => $invoiceId,
            ]);

            ActivityLogger::log(
                'INVOICE_CANCELLED',
                'invoice',
                $invoiceId,
                "Cancelled invoice {$inv['invoice_number']}: {$reason}",
                null,
                $userId,
                false,
                ['status' => $oldStatus],
                ['status' => 'cancelled', 'reason' => $reason]
            );

            return (array) self::find($invoiceId);
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
            $where[] = '(i.invoice_number LIKE :s1 OR i.reference_number LIKE :s2 OR c.company_name LIKE :s3 OR s.supplier_name LIKE :s4 OR cd.dr_number LIKE :s5)';
            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
            $params['s4'] = $term;
            $params['s5'] = $term;
        }

        if (!empty($filters['invoice_type'])) {
            $where[] = 'i.invoice_type = :type';
            $params['type'] = trim((string) $filters['invoice_type']);
        }

        if (!empty($filters['status'])) {
            $where[] = 'i.status = :st';
            $params['st'] = trim((string) $filters['status']);
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $db->prepare(
            "SELECT COUNT(i.id) FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             LEFT JOIN suppliers s ON s.id = i.supplier_id
             LEFT JOIN customer_deliveries cd ON cd.id = i.delivery_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT i.*,
                       c.company_name AS client_name,
                       s.supplier_name,
                       cd.dr_number,
                       po.po_number,
                       pt.name AS payment_term_name,
                       u.full_name AS created_by_name
                FROM invoices i
                LEFT JOIN clients c ON c.id = i.client_id
                LEFT JOIN suppliers s ON s.id = i.supplier_id
                LEFT JOIN customer_deliveries cd ON cd.id = i.delivery_id
                LEFT JOIN purchase_orders po ON po.id = i.po_id
                LEFT JOIN payment_terms pt ON pt.id = i.payment_term_id
                LEFT JOIN users u ON u.id = i.created_by_id
                WHERE {$whereSql}
                ORDER BY i.invoice_date DESC, i.id DESC
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
            'SELECT i.*,
                    c.company_name AS client_name,
                    s.supplier_name,
                    cd.dr_number,
                    cd.delivery_number,
                    po.po_number,
                    sr.receipt_number,
                    pt.name AS payment_term_name,
                    u.full_name AS created_by_name
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             LEFT JOIN suppliers s ON s.id = i.supplier_id
             LEFT JOIN customer_deliveries cd ON cd.id = i.delivery_id
             LEFT JOIN purchase_orders po ON po.id = i.po_id
             LEFT JOIN supplier_receipts sr ON sr.id = i.receipt_id
             LEFT JOIN payment_terms pt ON pt.id = i.payment_term_id
             LEFT JOIN users u ON u.id = i.created_by_id
             WHERE i.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getItems(int $invoiceId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ii.*, inv.item_code, inv.model_part_number
             FROM invoice_items ii
             LEFT JOIN inventory_items inv ON inv.id = ii.inventory_item_id
             WHERE ii.invoice_id = :id
             ORDER BY ii.id ASC'
        );
        $stmt->execute(['id' => $invoiceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getOpenInvoices(): array
    {
        return Database::connection()->query(
            "SELECT i.*, COALESCE(c.company_name, s.supplier_name) AS party_name
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             LEFT JOIN suppliers s ON s.id = i.supplier_id
             WHERE i.status IN ('unpaid', 'partially_paid', 'overdue')
               AND i.balance_due > 0
             ORDER BY i.due_date ASC, i.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
