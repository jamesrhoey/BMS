<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\ActivityLogger;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Dual-Mode Stock In / Equipment Receiving Engine (Digitized Paper Form 1).
 *
 * Supports:
 * - Mode A: Direct Stock In (po_id = NULL, preserves leading zeros on po_number e.g. '000398',
 *           supports dropdown supplier OR ad-hoc supplier_name).
 * - Mode B: Receive Against System PO (po_id populated, caps quantity_received <= outstanding_quantity,
 *           increments purchase_order_items.quantity_received, refreshes PO status).
 * - Atomic transaction posting across supplier_receipts, supplier_receipt_items,
 *   supplier_receipt_serials, inventory_units, stock_transactions, inventory_ledger,
 *   inventory_stock, and activity_logs.
 */
final class SupplierReceipt
{
    public static function generateReceiptNumber(?PDO $pdo = null): string
    {
        $db = $pdo ?? Database::connection();
        $year = date('Y');
        $prefix = "SR-{$year}-";

        $stmt = $db->prepare(
            'SELECT receipt_number FROM supplier_receipts WHERE receipt_number LIKE :p ORDER BY id DESC LIMIT 1'
        );
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
     * Record a Dual-Mode Stock In receipt atomically.
     *
     * @param array{
     *     po_id?: int|null,
     *     po_number?: string|null,
     *     po_date?: string|null,
     *     supplier_id?: int|null,
     *     supplier_name?: string|null,
     *     destination_location_id?: int,
     *     receipt_date: string,
     *     delivery_to_pct_date?: string|null,
     *     carrier?: string|null,
     *     waybill_number?: string|null,
     *     invoice_reference?: string|null,
     *     remarks?: string|null,
     *     receipt_number?: string|null
     * } $header
     * @param list<array{
     *     inventory_item_id: int,
     *     po_item_id?: int|null,
     *     quantity_received: int,
     *     unit_cost?: float,
     *     warranty_duration_months?: int,
     *     item_condition?: string,
     *     serial_numbers?: list<string>,
     *     notes?: string|null
     * }> $lines
     * @return array{receipt_id: int, receipt_number: string, transaction_number: string, units_created: int}
     */
    public static function recordReceipt(array $header, array $lines, int $userId): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('At least one equipment line item is required for Stock In.');
        }

        $receiptDate = trim((string) ($header['receipt_date'] ?? date('Y-m-d')));
        if ($receiptDate === '') {
            throw new InvalidArgumentException('Inventory / Receipt date is required.');
        }

        $deliveryToPctDate = !empty($header['delivery_to_pct_date'])
            ? trim((string) $header['delivery_to_pct_date'])
            : $receiptDate;

        $poId = !empty($header['po_id']) ? (int) $header['po_id'] : null;
        $locationId = !empty($header['destination_location_id']) ? (int) $header['destination_location_id'] : 1;

        return Database::transaction(static function (PDO $pdo) use ($header, $lines, $userId, $receiptDate, $deliveryToPctDate, $poId, $locationId): array {
            // Preserve leading zeros by handling po_number strictly as string
            $poNumber = isset($header['po_number']) && trim((string) $header['po_number']) !== ''
                ? trim((string) $header['po_number'])
                : null;
            $poDate = !empty($header['po_date']) ? trim((string) $header['po_date']) : null;
            $supplierId = !empty($header['supplier_id']) ? (int) $header['supplier_id'] : null;
            $supplierName = isset($header['supplier_name']) && trim((string) $header['supplier_name']) !== ''
                ? trim((string) $header['supplier_name'])
                : null;

            // Mode B: Validate System PO if linked
            if ($poId !== null) {
                $poStmt = $pdo->prepare('SELECT * FROM purchase_orders WHERE id = :id FOR UPDATE');
                $poStmt->execute(['id' => $poId]);
                $po = $poStmt->fetch(PDO::FETCH_ASSOC);
                if (!$po) {
                    throw new InvalidArgumentException("Purchase Order #{$poId} not found.");
                }
                $poStatusLower = strtolower((string) $po['status']);
                if (in_array($poStatusLower, ['cancelled', 'rejected', 'draft'], true)) {
                    throw new RuntimeException("Purchase Order {$po['po_number']} cannot be received in status '{$po['status']}'.");
                }
                $poNumber = (string) $po['po_number'];
                $poDate = (string) $po['po_date'];
                $supplierId = (int) $po['supplier_id'];
            }

            // Resolve location name
            $locStmt = $pdo->prepare('SELECT name FROM inventory_locations WHERE id = :id LIMIT 1');
            $locStmt->execute(['id' => $locationId]);
            $locationName = (string) ($locStmt->fetchColumn() ?: 'PCT Warehouse');

            // 1. Pre-validate all lines & serial numbers (intra-form + DB uniqueness)
            $seenSerialsInForm = [];
            $validatedLines = [];
            $defaultWarrantyMonths = Setting::getInt('default_warranty_months', 24);

            foreach ($lines as $idx => $line) {
                $lineNum = $idx + 1;
                $itemId = (int) ($line['inventory_item_id'] ?? 0);
                $qty = (int) ($line['quantity_received'] ?? 0);
                if ($itemId <= 0 || $qty <= 0) {
                    throw new InvalidArgumentException("Line #{$lineNum}: Valid product and positive quantity are required.");
                }

                $itemStmt = $pdo->prepare('SELECT * FROM inventory_items WHERE id = :id LIMIT 1');
                $itemStmt->execute(['id' => $itemId]);
                $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
                if (!$item) {
                    throw new InvalidArgumentException("Line #{$lineNum}: Product #{$itemId} not found.");
                }

                $poItemId = !empty($line['po_item_id']) ? (int) $line['po_item_id'] : null;
                if ($poId !== null && $poItemId !== null) {
                    $poiStmt = $pdo->prepare('SELECT * FROM purchase_order_items WHERE id = :id AND purchase_order_id = :po_id FOR UPDATE');
                    $poiStmt->execute(['id' => $poItemId, 'po_id' => $poId]);
                    $poi = $poiStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$poi) {
                        throw new InvalidArgumentException("Line #{$lineNum}: PO item #{$poItemId} does not belong to PO #{$poId}.");
                    }
                    $outstanding = (float) $poi['quantity_ordered'] - (float) $poi['quantity_received'];
                    if ($qty > $outstanding + 0.0001) {
                        throw new RuntimeException(
                            "Line #{$lineNum}: Received quantity ({$qty}) exceeds PO outstanding quantity ({$outstanding})."
                        );
                    }
                }

                $isSerialized = (int) $item['is_serialized'] === 1;
                $rawSerials = $line['serial_numbers'] ?? [];
                if (is_string($rawSerials)) {
                    $rawSerials = preg_split('/[\r\n,]+/', $rawSerials) ?: [];
                }
                $serials = [];
                foreach ($rawSerials as $sn) {
                    $trimmed = trim((string) $sn);
                    if ($trimmed !== '') {
                        $serials[] = $trimmed;
                    }
                }

                if ($isSerialized) {
                    if (count($serials) !== $qty) {
                        throw new InvalidArgumentException(
                            "Line #{$lineNum} ({$item['item_name']}): In Qty ({$qty}) must exactly match the number of serial numbers provided (" . count($serials) . ').'
                        );
                    }
                    foreach ($serials as $sn) {
                        $snKey = strtolower($sn);
                        if (isset($seenSerialsInForm[$snKey])) {
                            throw new InvalidArgumentException("Duplicate serial number '{$sn}' detected within the same Stock In form.");
                        }
                        $seenSerialsInForm[$snKey] = true;

                        $dupDb = $pdo->prepare('SELECT id FROM inventory_units WHERE serial_number = :sn LIMIT 1');
                        $dupDb->execute(['sn' => $sn]);
                        if ($dupDb->fetchColumn()) {
                            throw new RuntimeException("Serial number '{$sn}' already exists in inventory_units.");
                        }
                    }
                }

                $warrantyMonths = isset($line['warranty_duration_months']) && $line['warranty_duration_months'] !== ''
                    ? max(0, (int) $line['warranty_duration_months'])
                    : (int) ($item['default_warranty_months'] ?? $defaultWarrantyMonths);

                $validatedLines[] = [
                    'item' => $item,
                    'po_item_id' => $poItemId,
                    'qty' => $qty,
                    'unit_cost' => round((float) ($line['unit_cost'] ?? $item['unit_cost'] ?? 0), 2),
                    'warranty_months' => $warrantyMonths,
                    'condition' => trim((string) ($line['item_condition'] ?? 'Brand New')) ?: 'Brand New',
                    'serials' => $serials,
                    'notes' => isset($line['notes']) && trim((string) $line['notes']) !== '' ? trim((string) $line['notes']) : null,
                ];
            }

            // 2. Insert supplier_receipts header
            $receiptNumber = !empty($header['receipt_number'])
                ? trim((string) $header['receipt_number'])
                : self::generateReceiptNumber($pdo);

            $pdo->prepare(
                'INSERT INTO supplier_receipts (
                    receipt_number, po_id, po_number, po_date, supplier_id, supplier_name,
                    received_by_id, destination_location_id, receipt_date, delivery_to_pct_date,
                    carrier, waybill_number, invoice_reference, remarks, status
                 ) VALUES (
                    :rno, :po_id, :po_num, :po_date, :sup_id, :sup_name,
                    :uid, :loc_id, :rdate, :pct_date,
                    :carrier, :waybill, :inv_ref, :remarks, "received"
                 )'
            )->execute([
                'rno' => $receiptNumber,
                'po_id' => $poId,
                'po_num' => $poNumber,
                'po_date' => $poDate,
                'sup_id' => $supplierId,
                'sup_name' => $supplierName,
                'uid' => $userId,
                'loc_id' => $locationId,
                'rdate' => $receiptDate,
                'pct_date' => $deliveryToPctDate,
                'carrier' => ($header['carrier'] ?? '') !== '' ? trim((string) $header['carrier']) : null,
                'waybill' => ($header['waybill_number'] ?? '') !== '' ? trim((string) $header['waybill_number']) : null,
                'inv_ref' => ($header['invoice_reference'] ?? '') !== '' ? trim((string) $header['invoice_reference']) : null,
                'remarks' => ($header['remarks'] ?? '') !== '' ? trim((string) $header['remarks']) : null,
            ]);
            $receiptId = (int) $pdo->lastInsertId();

            // 3. Create stock_transactions header
            $txNumber = InventoryLedger::generateStockTransactionNumber($pdo, 'SIN');
            $pdo->prepare(
                'INSERT INTO stock_transactions (
                    transaction_number, transaction_type, reference_type, reference_id,
                    destination_location_id, performed_by_id, transaction_date, notes
                 ) VALUES (
                    :tx_no, "STOCK_IN", "SUPPLIER_RECEIPT", :ref_id,
                    :loc_id, :uid, :tx_date, :notes
                 )'
            )->execute([
                'tx_no' => $txNumber,
                'ref_id' => $receiptId,
                'loc_id' => $locationId,
                'uid' => $userId,
                'tx_date' => $receiptDate . ' ' . date('H:i:s'),
                'notes' => "Stock In Receipt {$receiptNumber}" . ($poNumber ? " (PO: {$poNumber})" : ' (Direct Stock In)'),
            ]);
            $stockTxId = (int) $pdo->lastInsertId();

            // 4. Process each line: receipt item, physical units, serials, ledger, PO progress
            $unitsCreated = 0;
            $itemCounter = 0;
            foreach ($validatedLines as $vl) {
                $item = $vl['item'];
                $itemId = (int) $item['id'];
                $qty = (int) $vl['qty'];
                $unitCost = (float) $vl['unit_cost'];
                $warrantyMonths = (int) $vl['warranty_months'];

                $pdo->prepare(
                    'INSERT INTO supplier_receipt_items (
                        receipt_id, po_item_id, inventory_item_id, quantity_received, unit_cost,
                        item_condition, warranty_duration_months, notes
                     ) VALUES (
                        :rid, :poi_id, :item_id, :qty, :cost,
                        :cond, :w_months, :notes
                     )'
                )->execute([
                    'rid' => $receiptId,
                    'poi_id' => $vl['po_item_id'],
                    'item_id' => $itemId,
                    'qty' => $qty,
                    'cost' => $unitCost,
                    'cond' => $vl['condition'],
                    'w_months' => $warrantyMonths,
                    'notes' => $vl['notes'],
                ]);
                $receiptItemId = (int) $pdo->lastInsertId();

                $pdo->prepare(
                    'INSERT INTO stock_transaction_items (transaction_id, inventory_item_id, quantity, unit_cost, notes)
                     VALUES (:tx_id, :item_id, :qty, :cost, :notes)'
                )->execute([
                    'tx_id' => $stockTxId,
                    'item_id' => $itemId,
                    'qty' => $qty,
                    'cost' => $unitCost,
                    'notes' => $vl['notes'],
                ]);
                $txItemId = (int) $pdo->lastInsertId();

                // Create serialized physical units (never fabricate warranty start date for unissued warehouse stock)
                if ((int) $item['is_serialized'] === 1) {
                    $unitIns = $pdo->prepare(
                        'INSERT INTO inventory_units (
                            item_number, po_number, po_reference, po_date, model, description,
                            warranty_duration, serial_number, in_qty, unit_cost,
                            inventory_date, stock_in_date, inventory_item_id,
                            purchase_order_id, supplier_receipt_id, supplier_id,
                            status, current_location, location_id, created_by
                         ) VALUES (
                            :item_no, :po_num, :po_ref, :po_date, :model, :desc,
                            :w_dur, :sn, 1.00, :cost,
                            :inv_date, :in_date, :item_id,
                            :po_id, :sr_id, :sup_id,
                            "in_stock", :loc_name, :loc_id, :uid
                         )'
                    );
                    $srsIns = $pdo->prepare('INSERT INTO supplier_receipt_serials (receipt_item_id, unit_id) VALUES (:sri, :uid)');
                    $stsIns = $pdo->prepare('INSERT INTO stock_transaction_serials (transaction_item_id, unit_id) VALUES (:sti, :uid)');

                    foreach ($vl['serials'] as $sn) {
                        $itemCounter++;
                        $unitIns->execute([
                            'item_no' => $itemCounter,
                            'po_num' => $poNumber,
                            'po_ref' => $poNumber,
                            'po_date' => $poDate,
                            'model' => (string) ($item['model_part_number'] ?: $item['item_code']),
                            'desc' => (string) ($item['description'] ?: $item['item_name']),
                            'w_dur' => "{$warrantyMonths} Months",
                            'sn' => $sn,
                            'cost' => $unitCost,
                            'inv_date' => $deliveryToPctDate,
                            'in_date' => $deliveryToPctDate,
                            'item_id' => $itemId,
                            'po_id' => $poId,
                            'sr_id' => $receiptId,
                            'sup_id' => $supplierId,
                            'loc_name' => $locationName,
                            'loc_id' => $locationId,
                            'uid' => $userId,
                        ]);
                        $unitId = (int) $pdo->lastInsertId();
                        $srsIns->execute(['sri' => $receiptItemId, 'uid' => $unitId]);
                        $stsIns->execute(['sti' => $txItemId, 'uid' => $unitId]);
                        $unitsCreated++;
                    }
                }

                // Post immutable double-entry ledger & update inventory_stock cache
                InventoryLedger::postMovement($pdo, [
                    'inventory_item_id' => $itemId,
                    'location_id' => $locationId,
                    'transaction_type' => 'STOCK_IN',
                    'reference_type' => 'SUPPLIER_RECEIPT',
                    'reference_id' => $receiptId,
                    'quantity_in' => $qty,
                    'quantity_out' => 0,
                    'unit_cost' => $unitCost,
                    'performed_by_id' => $userId,
                    'notes' => "Receipt {$receiptNumber}" . ($poNumber ? " / PO {$poNumber}" : ''),
                ]);

                // Mode B: Increment purchase_order_items.quantity_received
                if ($poId !== null && $vl['po_item_id'] !== null) {
                    $pdo->prepare(
                        'UPDATE purchase_order_items SET quantity_received = quantity_received + :qty, updated_at = NOW() WHERE id = :id'
                    )->execute([
                        'qty' => $qty,
                        'id' => $vl['po_item_id'],
                    ]);
                }
            }

            // Mode B: Refresh PO fulfillment status
            if ($poId !== null) {
                $totStmt = $pdo->prepare(
                    'SELECT SUM(quantity_ordered) AS total_ord, SUM(quantity_received) AS total_rec
                     FROM purchase_order_items WHERE purchase_order_id = :po_id'
                );
                $totStmt->execute(['po_id' => $poId]);
                $tots = $totStmt->fetch(PDO::FETCH_ASSOC);
                $newPoStatus = ((float) ($tots['total_rec'] ?? 0) >= (float) ($tots['total_ord'] ?? 0) - 0.0001)
                    ? 'Fully Received'
                    : 'Partially Received';

                $pdo->prepare('UPDATE purchase_orders SET status = :st, updated_at = NOW() WHERE id = :id')
                    ->execute(['st' => $newPoStatus, 'id' => $poId]);
            }

            ActivityLogger::log(
                'STOCK_IN',
                'supplier_receipt',
                $receiptId,
                "Recorded Stock In {$receiptNumber}" . ($poNumber ? " (PO: {$poNumber})" : ' (Direct Form 1)') . " — {$unitsCreated} serialized unit(s).",
                null,
                $userId,
                false,
                null,
                [
                    'receipt_number' => $receiptNumber,
                    'po_id' => $poId,
                    'po_number' => $poNumber,
                    'lines_count' => count($validatedLines),
                    'units_created' => $unitsCreated,
                ]
            );

            return [
                'receipt_id' => $receiptId,
                'receipt_number' => $receiptNumber,
                'transaction_number' => $txNumber,
                'units_created' => $unitsCreated,
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
            $where[] = '(sr.receipt_number LIKE :s1 OR sr.po_number LIKE :s2 OR po.po_number LIKE :s3 OR s.supplier_name LIKE :s4 OR sr.supplier_name LIKE :s5 OR sr.waybill_number LIKE :s6)';
            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
            $params['s4'] = $term;
            $params['s5'] = $term;
            $params['s6'] = $term;
        }

        if (!empty($filters['status'])) {
            $where[] = 'sr.status = :st';
            $params['st'] = trim((string) $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'sr.receipt_date >= :dfrom';
            $params['dfrom'] = trim((string) $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'sr.receipt_date <= :dto';
            $params['dto'] = trim((string) $filters['date_to']);
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $db->prepare(
            "SELECT COUNT(sr.id) FROM supplier_receipts sr
             LEFT JOIN purchase_orders po ON po.id = sr.po_id
             LEFT JOIN suppliers s ON s.id = sr.supplier_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT sr.*,
                       COALESCE(po.po_number, sr.po_number) AS display_po_number,
                       COALESCE(po.po_date, sr.po_date) AS display_po_date,
                       COALESCE(s.supplier_name, sr.supplier_name) AS display_supplier_name,
                       u.full_name AS received_by_name,
                       loc.name AS location_name,
                       COUNT(sri.id) AS lines_count,
                       COALESCE(SUM(sri.quantity_received), 0) AS total_units
                FROM supplier_receipts sr
                LEFT JOIN purchase_orders po ON po.id = sr.po_id
                LEFT JOIN suppliers s ON s.id = sr.supplier_id
                LEFT JOIN users u ON u.id = sr.received_by_id
                LEFT JOIN inventory_locations loc ON loc.id = sr.destination_location_id
                LEFT JOIN supplier_receipt_items sri ON sri.receipt_id = sr.id
                WHERE {$whereSql}
                GROUP BY sr.id
                ORDER BY sr.receipt_date DESC, sr.id DESC
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
            'SELECT sr.*,
                    COALESCE(po.po_number, sr.po_number) AS display_po_number,
                    COALESCE(po.po_date, sr.po_date) AS display_po_date,
                    po.status AS po_status,
                    COALESCE(s.supplier_name, sr.supplier_name) AS display_supplier_name,
                    u.full_name AS received_by_name,
                    loc.name AS location_name
             FROM supplier_receipts sr
             LEFT JOIN purchase_orders po ON po.id = sr.po_id
             LEFT JOIN suppliers s ON s.id = sr.supplier_id
             LEFT JOIN users u ON u.id = sr.received_by_id
             LEFT JOIN inventory_locations loc ON loc.id = sr.destination_location_id
             WHERE sr.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getItemsWithSerials(int $receiptId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT sri.*, ii.item_code, ii.item_name, ii.model_part_number, ii.is_serialized
             FROM supplier_receipt_items sri
             INNER JOIN inventory_items ii ON ii.id = sri.inventory_item_id
             WHERE sri.receipt_id = :rid
             ORDER BY sri.id ASC'
        );
        $stmt->execute(['rid' => $receiptId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $serStmt = $db->prepare(
            'SELECT iu.id, iu.serial_number, iu.status
             FROM supplier_receipt_serials srs
             INNER JOIN inventory_units iu ON iu.id = srs.unit_id
             WHERE srs.receipt_item_id = :sri_id
             ORDER BY iu.id ASC'
        );

        foreach ($items as &$it) {
            $serStmt->execute(['sri_id' => (int) $it['id']]);
            $it['serials'] = $serStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($it);

        return $items;
    }
}
