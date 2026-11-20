<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

/**
 * Unified Reporting & Export Engine (Digitized Paper Forms 1, 2, 3 + Operational Reports).
 *
 * Enforces:
 * - Form 1 (Stock In): 1 row per physical serialized unit, zero Cartesian duplication, excludes cancelled receipts.
 * - Form 2 (Stock Out): Exact Paper Form 2 columns, excludes cancelled deliveries.
 * - Form 3 (Ending Balance): Exact Paper Form 3 columns with integer Ending Balance & persistent remarks.
 * - CWE-1236 CSV / Excel Formula Injection protection escaping =, +, -, @, \t, \r.
 */
final class Report
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            'stock-in' => [
                'slug' => 'stock-in',
                'title' => 'Stock In Report (Paper Form 1)',
                'category' => 'Digitized Paper Forms',
                'description' => 'Official Paper Form 1: 1 row per physical unit with PO Number, PO Date, Model, Description, Warranty, Serial, In Qty, and Delivery to PCT Date.',
                'columns' => ['Item', 'PO Number', 'PO Date', 'Model', 'Description', 'Warranty Duration', 'Serial', 'In Qty', 'Inventory Date / Delivery to PCT'],
            ],
            'stock-out' => [
                'slug' => 'stock-out',
                'title' => 'Stock Out Report (Paper Form 2)',
                'category' => 'Digitized Paper Forms',
                'description' => 'Official Paper Form 2: Serial, Out Qty, Out Date, Warranty Duration, Company, Address, PO/PR No., DR No., and Invoice No.',
                'columns' => ['Serial', 'Out Qty', 'Out Date', 'Warranty Duration', 'Company', 'Address', 'PO No. / PR No.', 'DR No.', 'Invoice No.'],
            ],
            'ending-balance' => [
                'slug' => 'ending-balance',
                'title' => 'Ending Balance Report (Paper Form 3)',
                'category' => 'Digitized Paper Forms',
                'description' => 'Official Paper Form 3: Description, Ending Balance (strict integer available stock), and persistent Remarks.',
                'columns' => ['Description', 'Ending Balance', 'Remarks'],
            ],
            'inventory-balance' => [
                'slug' => 'inventory-balance',
                'title' => 'Inventory Balance & Valuation',
                'category' => 'Inventory & Warehousing',
                'description' => 'Stock on hand, available units, under maintenance count, reorder level, and inventory valuation.',
                'columns' => ['SKU / Model', 'Product Name', 'In Stock', 'Under Repair', 'Delivered', 'Reorder Level', 'Unit Cost', 'Valuation'],
            ],
            'po-status-outstanding' => [
                'slug' => 'po-status-outstanding',
                'title' => 'PO Status & Outstanding Quantities',
                'category' => 'Procurement',
                'description' => 'Purchase orders tracking ordered vs. received vs. outstanding quantities.',
                'columns' => ['PO Number', 'PO Date', 'Supplier', 'Item Description', 'Ordered', 'Received', 'Outstanding', 'PO Status'],
            ],
            'invoices-aging' => [
                'slug' => 'invoices-aging',
                'title' => 'Invoices & Due Dates Aging',
                'category' => 'Financial & Billing',
                'description' => 'Receivable and payable invoices with due dates, paid amounts, and balances due.',
                'columns' => ['Invoice No.', 'Type', 'Party', 'Invoice Date', 'Due Date', 'Total Amount', 'Paid Amount', 'Balance Due', 'Status'],
            ],
            'collections-history' => [
                'slug' => 'collections-history',
                'title' => 'Collections & Payment History',
                'category' => 'Financial & Billing',
                'description' => 'Settlement ledger of cleared and voided payment collections and disbursements.',
                'columns' => ['Collection No.', 'Invoice No.', 'Party', 'Date', 'Method', 'Reference No.', 'Amount', 'Status'],
            ],
            'warranty-expiration' => [
                'slug' => 'warranty-expiration',
                'title' => 'Warranty Status & Expiration',
                'category' => 'Warranty & Assets',
                'description' => 'Active, expiring soon, and expired 24-month hardware warranty certificates.',
                'columns' => ['Warranty Code', 'Serial Number', 'Product', 'Client', 'Start Date', 'Duration', 'Expiry Date', 'Status'],
            ],
        ];
    }

    /**
     * CWE-1236 CSV / Spreadsheet Formula Injection sanitizer.
     * Prefixes any string starting with =, +, -, @, \t, or \r with a single quote.
     */
    public static function sanitizeCsvCell(mixed $value): string
    {
        $str = (string) ($value ?? '');
        if ($str === '') {
            return '';
        }
        $firstChar = $str[0];
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }
        return $str;
    }

    /**
     * Execute the requested report query and return normalized rows.
     *
     * @param array<string, mixed> $filters
     * @return array{meta: array<string, mixed>, rows: list<list<string|int>>}
     */
    public static function run(string $slug, array $filters = []): array
    {
        $defs = self::definitions();
        if (!isset($defs[$slug])) {
            throw new InvalidArgumentException("Unknown report '{$slug}'.");
        }

        $rows = match ($slug) {
            'stock-in' => self::queryStockInForm1($filters),
            'stock-out' => self::queryStockOutForm2($filters),
            'ending-balance' => self::queryEndingBalanceForm3($filters),
            'inventory-balance' => self::queryInventoryBalance($filters),
            'po-status-outstanding' => self::queryPoOutstanding($filters),
            'invoices-aging' => self::queryInvoicesAging($filters),
            'collections-history' => self::queryCollectionsHistory($filters),
            'warranty-expiration' => self::queryWarrantyExpiration($filters),
        };

        return [
            'meta' => $defs[$slug],
            'rows' => $rows,
        ];
    }

    /**
     * Report 1 — Stock In (Paper Form 1)
     * 1 row per physical serialized unit, zero Cartesian duplication, excludes cancelled receipts.
     *
     * @param array<string, mixed> $filters
     * @return list<list<string|int>>
     */
    private static function queryStockInForm1(array $filters): array
    {
        $db = Database::connection();
        $where = ["sr.status != 'cancelled'"];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'COALESCE(sr.delivery_to_pct_date, sr.receipt_date) >= :dfrom';
            $params['dfrom'] = trim((string) $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'COALESCE(sr.delivery_to_pct_date, sr.receipt_date) <= :dto';
            $params['dto'] = trim((string) $filters['date_to']);
        }
        if (!empty($filters['keyword'])) {
            $term = '%' . trim((string) $filters['keyword']) . '%';
            $where[] = '(COALESCE(po.po_number, sr.po_number) LIKE :k1 OR iu.serial_number LIKE :k2 OR ii.item_name LIKE :k3 OR ii.model_part_number LIKE :k4)';
            $params['k1'] = $term;
            $params['k2'] = $term;
            $params['k3'] = $term;
            $params['k4'] = $term;
        }

        $whereSql = implode(' AND ', $where);
        // Serialized rows: 1 row per unit via supplier_receipt_serials (unique unit_id -> zero Cartesian duplication)
        $sql = "SELECT
                    COALESCE(po.po_number, sr.po_number, '') AS po_number,
                    COALESCE(po.po_date, sr.po_date, '') AS po_date,
                    COALESCE(ii.model_part_number, ii.item_code) AS model_code,
                    COALESCE(ii.description, ii.item_name) AS item_desc,
                    CONCAT(COALESCE(sri.warranty_duration_months, ii.default_warranty_months, 24), ' Months') AS warranty_dur,
                    iu.serial_number AS serial_no,
                    1 AS in_qty,
                    COALESCE(sr.delivery_to_pct_date, sr.receipt_date) AS delivery_date
                FROM supplier_receipt_serials srs
                INNER JOIN supplier_receipt_items sri ON sri.id = srs.receipt_item_id
                INNER JOIN supplier_receipts sr ON sr.id = sri.receipt_id
                INNER JOIN inventory_units iu ON iu.id = srs.unit_id
                INNER JOIN inventory_items ii ON ii.id = sri.inventory_item_id
                LEFT JOIN purchase_orders po ON po.id = sr.po_id
                WHERE {$whereSql}
                ORDER BY COALESCE(sr.delivery_to_pct_date, sr.receipt_date) ASC, sr.id ASC, iu.id ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rows = [];
        $itemSeq = 0;
        foreach ($data as $r) {
            $itemSeq++;
            $rows[] = [
                $itemSeq,
                (string) $r['po_number'],
                (string) $r['po_date'],
                (string) $r['model_code'],
                (string) $r['item_desc'],
                (string) $r['warranty_dur'],
                (string) $r['serial_no'],
                (int) $r['in_qty'],
                (string) $r['delivery_date'],
            ];
        }
        return $rows;
    }

    /**
     * Report 2 — Stock Out (Paper Form 2)
     * Excludes cancelled deliveries. 1 row per issued serialized unit.
     *
     * @param array<string, mixed> $filters
     * @return list<list<string|int>>
     */
    private static function queryStockOutForm2(array $filters): array
    {
        $db = Database::connection();
        $where = ["cd.status != 'cancelled'"];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'cd.delivery_date >= :dfrom';
            $params['dfrom'] = trim((string) $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'cd.delivery_date <= :dto';
            $params['dto'] = trim((string) $filters['date_to']);
        }
        if (!empty($filters['keyword'])) {
            $term = '%' . trim((string) $filters['keyword']) . '%';
            $where[] = '(iu.serial_number LIKE :k1 OR COALESCE(c.company_name, cd.client_name) LIKE :k2 OR cd.dr_number LIKE :k3 OR cd.invoice_number LIKE :k4 OR cd.customer_po_number LIKE :k5)';
            $params['k1'] = $term;
            $params['k2'] = $term;
            $params['k3'] = $term;
            $params['k4'] = $term;
            $params['k5'] = $term;
        }

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT
                    iu.serial_number,
                    1 AS out_qty,
                    cd.delivery_date AS out_date,
                    CONCAT(COALESCE(cdi.warranty_duration_months, 24), ' Months') AS warranty_dur,
                    COALESCE(c.company_name, cd.client_name, '') AS company_name,
                    cd.shipping_address,
                    COALESCE(NULLIF(cd.customer_po_number, ''), NULLIF(cd.pr_reference, ''), '') AS po_pr_no,
                    COALESCE(cd.dr_number, cd.delivery_number) AS dr_no,
                    COALESCE(cd.invoice_number, '') AS invoice_no
                FROM customer_delivery_serials cds
                INNER JOIN customer_delivery_items cdi ON cdi.id = cds.delivery_item_id
                INNER JOIN customer_deliveries cd ON cd.id = cdi.delivery_id
                INNER JOIN inventory_units iu ON iu.id = cds.unit_id
                LEFT JOIN clients c ON c.id = cd.client_id
                WHERE {$whereSql}
                ORDER BY cd.delivery_date ASC, cd.id ASC, iu.id ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rows = [];
        foreach ($data as $r) {
            $rows[] = [
                (string) $r['serial_number'],
                (int) $r['out_qty'],
                (string) $r['out_date'],
                (string) $r['warranty_dur'],
                (string) $r['company_name'],
                (string) $r['shipping_address'],
                (string) $r['po_pr_no'],
                (string) $r['dr_no'],
                (string) $r['invoice_no'],
            ];
        }
        return $rows;
    }

    /**
     * Report 3 — Ending Balance (Paper Form 3)
     * Exact columns: Description | Ending Balance (strict integer) | Remarks
     *
     * @param array<string, mixed> $filters
     * @return list<list<string|int>>
     */
    private static function queryEndingBalanceForm3(array $filters): array
    {
        $list = InventoryLedger::getEndingBalanceList([
            'search' => $filters['keyword'] ?? '',
            'location_id' => $filters['location_id'] ?? 1,
        ]);

        $rows = [];
        foreach ($list as $r) {
            $desc = trim(($r['model_code'] ? '[' . $r['model_code'] . '] ' : '') . $r['item_name'] . (!empty($r['description']) ? ' — ' . $r['description'] : ''));
            $rows[] = [
                $desc,
                (int) $r['ending_balance'],
                (string) ($r['remarks'] ?? ''),
            ];
        }
        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<list<string|int>>
     */
    private static function queryInventoryBalance(array $filters): array
    {
        $list = InventoryLedger::getEndingBalanceList(['search' => $filters['keyword'] ?? '']);
        $rows = [];
        foreach ($list as $r) {
            $val = (int) $r['ending_balance'] * (float) $r['unit_cost'];
            $rows[] = [
                (string) $r['model_code'],
                (string) $r['item_name'],
                (int) $r['ending_balance'],
                (int) $r['under_repair_count'],
                (int) $r['delivered_count'],
                (int) $r['reorder_level'],
                number_format((float) $r['unit_cost'], 2),
                number_format($val, 2),
            ];
        }
        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<list<string|int>>
     */
    private static function queryPoOutstanding(array $filters): array
    {
        $sql = "SELECT po.po_number, po.po_date, s.supplier_name, poi.item_description,
                       poi.quantity_ordered, poi.quantity_received,
                       (poi.quantity_ordered - poi.quantity_received) AS outstanding,
                       po.status
                FROM purchase_order_items poi
                INNER JOIN purchase_orders po ON po.id = poi.purchase_order_id
                INNER JOIN suppliers s ON s.id = po.supplier_id
                ORDER BY po.po_date DESC, po.id DESC";
        $data = Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $rows = [];
        foreach ($data as $r) {
            $rows[] = [
                (string) $r['po_number'],
                (string) $r['po_date'],
                (string) $r['supplier_name'],
                (string) $r['item_description'],
                (int) round((float) $r['quantity_ordered']),
                (int) round((float) $r['quantity_received']),
                (int) round((float) $r['outstanding']),
                (string) $r['status'],
            ];
        }
        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<list<string|int>>
     */
    private static function queryInvoicesAging(array $filters): array
    {
        $res = Invoice::paginate(['search' => $filters['keyword'] ?? ''], 1, 500);
        $rows = [];
        foreach ($res['data'] as $r) {
            $rows[] = [
                (string) $r['invoice_number'],
                ucfirst((string) $r['invoice_type']),
                (string) ($r['client_name'] ?: ($r['supplier_name'] ?: '—')),
                (string) $r['invoice_date'],
                (string) $r['due_date'],
                number_format((float) $r['total_amount'], 2),
                number_format((float) $r['paid_amount'], 2),
                number_format((float) $r['balance_due'], 2),
                ucfirst(str_replace('_', ' ', (string) $r['status'])),
            ];
        }
        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<list<string|int>>
     */
    private static function queryCollectionsHistory(array $filters): array
    {
        $res = Collection::paginate(['search' => $filters['keyword'] ?? ''], 1, 500);
        $rows = [];
        foreach ($res['data'] as $r) {
            $rows[] = [
                (string) $r['collection_number'],
                (string) $r['invoice_number'],
                (string) ($r['client_name'] ?: ($r['supplier_name'] ?: '—')),
                (string) $r['collection_date'],
                ucfirst(str_replace('_', ' ', (string) $r['payment_method'])),
                (string) ($r['reference_number'] ?? ''),
                number_format((float) $r['amount_received'], 2),
                ucfirst((string) $r['status']),
            ];
        }
        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<list<string|int>>
     */
    private static function queryWarrantyExpiration(array $filters): array
    {
        $res = Warranty::paginate(['search' => $filters['keyword'] ?? ''], 1, 500);
        $rows = [];
        foreach ($res['data'] as $r) {
            $rows[] = [
                (string) $r['warranty_code'],
                (string) $r['serial_number'],
                (string) $r['item_name'],
                (string) ($r['client_name'] ?: '—'),
                (string) ($r['start_date'] ?: '—'),
                (int) $r['duration_months'] . ' Months',
                (string) ($r['end_date'] ?: '—'),
                ucfirst(str_replace('_', ' ', (string) $r['derived_status'])),
            ];
        }
        return $rows;
    }
}
