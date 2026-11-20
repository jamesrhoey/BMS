<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\ActivityLogger;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Outbound Stock Out & Customer Delivery Tracking Engine (Digitized Paper Form 2).
 *
 * Enforces:
 * - Pessimistic row-locking (SELECT ... FOR UPDATE) on `inventory_units` preventing concurrent double-issuance.
 * - Strict eligibility: only `status = 'in_stock'` units may ever be selected or issued.
 * - Preserves leading zeros on DR No., Invoice No., Customer PO No., and PR No.
 * - Auto-registers ad-hoc client company names into `clients`.
 * - Tracks delivery trajectory in `customer_delivery_updates` and automatically activates
 *   24-month warranties when confirmed `delivered`.
 * - Atomic cancellation/reversal restores units to `in_stock`, increments stock, and posts offsetting ledger entry.
 */
final class StockOut
{
    public static function generateDeliveryNumber(?PDO $pdo = null): string
    {
        $db = $pdo ?? Database::connection();
        $year = date('Y');
        $prefix = "DEL-{$year}-";
        $stmt = $db->prepare('SELECT delivery_number FROM customer_deliveries WHERE delivery_number LIKE :p ORDER BY id DESC LIMIT 1');
        $stmt->execute(['p' => $prefix . '%']);
        $last = $stmt->fetchColumn();

        if (is_string($last) && preg_match('/-(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        return sprintf('%s%04d', $prefix, $next);
    }

    public static function generateStockOutReference(?PDO $pdo = null): string
    {
        $db = $pdo ?? Database::connection();
        $year = date('Y');
        $prefix = "SO-{$year}-";
        $stmt = $db->prepare('SELECT stock_out_reference FROM customer_deliveries WHERE stock_out_reference LIKE :p ORDER BY id DESC LIMIT 1');
        $stmt->execute(['p' => $prefix . '%']);
        $last = $stmt->fetchColumn();

        if (is_string($last) && preg_match('/-(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        return sprintf('%s%04d', $prefix, $next);
    }

    public static function generateDRNumber(?PDO $pdo = null): string
    {
        $db = $pdo ?? Database::connection();
        $year = date('Y');
        $prefix = "DR-{$year}-";
        $stmt = $db->prepare('SELECT dr_number FROM customer_deliveries WHERE dr_number LIKE :p ORDER BY id DESC LIMIT 1');
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
     * Fetch strictly available (`status = 'in_stock'`) serialized units for Stock Out picker.
     *
     * @return list<array<string, mixed>>
     */
    public static function getAvailableSerializedUnits(?int $locationId = null, ?int $itemId = null): array
    {
        $where = ["iu.status = 'in_stock'"];
        $params = [];

        if ($locationId !== null && $locationId > 0) {
            $where[] = '(iu.location_id = :loc_id OR iu.location_id IS NULL)';
            $params['loc_id'] = $locationId;
        }
        if ($itemId !== null && $itemId > 0) {
            $where[] = 'iu.inventory_item_id = :item_id';
            $params['item_id'] = $itemId;
        }

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT iu.id,
                       iu.serial_number,
                       iu.inventory_item_id,
                       ii.item_code,
                       ii.item_name,
                       COALESCE(ii.model_part_number, iu.model) AS model_code,
                       iu.description,
                       iu.po_reference,
                       iu.stock_in_date,
                       iu.unit_cost,
                       ii.unit_price,
                       ii.default_warranty_months,
                       loc.name AS location_name
                FROM inventory_units iu
                INNER JOIN inventory_items ii ON ii.id = iu.inventory_item_id
                LEFT JOIN inventory_locations loc ON loc.id = iu.location_id
                WHERE {$whereSql}
                ORDER BY ii.item_name ASC, iu.stock_in_date ASC, iu.id ASC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute an atomic Stock Out / Customer Delivery (Digitized Paper Form 2).
     *
     * @param array{
     *     dr_number?: string|null,
     *     invoice_number?: string|null,
     *     customer_po_number?: string|null,
     *     pr_reference?: string|null,
     *     project_id?: int|null,
     *     client_id?: int|null,
     *     company_name?: string|null,
     *     shipping_address: string,
     *     contact_person?: string|null,
     *     contact_phone?: string|null,
     *     contact_email?: string|null,
     *     source_location_id?: int,
     *     delivery_date: string,
     *     planned_delivery_date?: string|null,
     *     carrier?: string|null,
     *     tracking_number?: string|null,
     *     remarks?: string|null
     * } $header
     * @param list<array{
     *     inventory_item_id: int,
     *     quantity: int,
     *     unit_price?: float,
     *     warranty_duration_months?: int,
     *     unit_ids?: list<int>,
     *     notes?: string|null
     * }> $lines
     * @return array{delivery_id: int, delivery_number: string, stock_out_reference: string, dr_number: string, units_issued: int}
     */
    public static function recordStockOut(array $header, array $lines, int $userId): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('At least one line item is required for Stock Out.');
        }

        $deliveryDate = trim((string) ($header['delivery_date'] ?? date('Y-m-d')));
        $shippingAddress = trim((string) ($header['shipping_address'] ?? ''));
        if ($deliveryDate === '' || $shippingAddress === '') {
            throw new InvalidArgumentException('Out Date and Shipping / Site Address are required.');
        }

        $locationId = !empty($header['source_location_id']) ? (int) $header['source_location_id'] : 1;

        return Database::transaction(static function (PDO $pdo) use ($header, $lines, $userId, $deliveryDate, $shippingAddress, $locationId): array {
            // 1. Resolve Client (existing client_id OR auto-register ad-hoc company_name)
            $clientId = !empty($header['client_id']) ? (int) $header['client_id'] : null;
            $companyName = trim((string) ($header['company_name'] ?? ''));

            if ($clientId !== null) {
                $cStmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
                $cStmt->execute(['id' => $clientId]);
                $client = $cStmt->fetch(PDO::FETCH_ASSOC);
                if (!$client) {
                    throw new InvalidArgumentException("Client #{$clientId} not found.");
                }
                $companyName = (string) $client['company_name'];
            } elseif ($companyName !== '') {
                $client = Client::findOrCreateByCompanyName(
                    $companyName,
                    $shippingAddress,
                    $header['contact_person'] ?? null,
                    $header['contact_phone'] ?? null,
                    $header['contact_email'] ?? null,
                    $pdo
                );
                $clientId = (int) $client['id'];
            } else {
                throw new InvalidArgumentException('Either an existing Client or a Company Name must be provided.');
            }

            // 2. Preserve leading zeros on DR No., Invoice No., Customer PO/PR
            $drNumber = !empty($header['dr_number'])
                ? trim((string) $header['dr_number'])
                : self::generateDRNumber($pdo);

            $dupDr = $pdo->prepare('SELECT id FROM customer_deliveries WHERE dr_number = :dr LIMIT 1');
            $dupDr->execute(['dr' => $drNumber]);
            if ($dupDr->fetchColumn()) {
                throw new RuntimeException("Delivery Receipt number '{$drNumber}' is already in use.");
            }

            $invoiceNumber = isset($header['invoice_number']) && trim((string) $header['invoice_number']) !== ''
                ? trim((string) $header['invoice_number'])
                : null;
            $customerPo = isset($header['customer_po_number']) && trim((string) $header['customer_po_number']) !== ''
                ? trim((string) $header['customer_po_number'])
                : null;
            $prRef = isset($header['pr_reference']) && trim((string) $header['pr_reference']) !== ''
                ? trim((string) $header['pr_reference'])
                : null;

            $deliveryNumber = self::generateDeliveryNumber($pdo);
            $soRef = self::generateStockOutReference($pdo);
            $defaultWarrantyMonths = Setting::getInt('default_warranty_months', 24);

            // 3. Insert customer_deliveries header
            $pdo->prepare(
                'INSERT INTO customer_deliveries (
                    delivery_number, stock_out_reference, dr_number, invoice_number,
                    customer_po_number, pr_reference, project_id,
                    client_id, client_name, contact_person, contact_phone, contact_email,
                    source_location_id, prepared_by_id, delivery_date, planned_delivery_date,
                    carrier, tracking_number, shipping_address, status, remarks
                 ) VALUES (
                    :dno, :soref, :dr, :inv,
                    :cpo, :pr, :proj_id,
                    :cid, :cname, :cperson, :cphone, :cemail,
                    :loc_id, :uid, :ddate, :pdate,
                    :carrier, :track, :addr, "dispatched", :remarks
                 )'
            )->execute([
                'dno' => $deliveryNumber,
                'soref' => $soRef,
                'dr' => $drNumber,
                'inv' => $invoiceNumber,
                'cpo' => $customerPo,
                'pr' => $prRef,
                'proj_id' => !empty($header['project_id']) ? (int) $header['project_id'] : null,
                'cid' => $clientId,
                'cname' => $companyName,
                'cperson' => ($header['contact_person'] ?? '') !== '' ? trim((string) $header['contact_person']) : null,
                'cphone' => ($header['contact_phone'] ?? '') !== '' ? trim((string) $header['contact_phone']) : null,
                'cemail' => ($header['contact_email'] ?? '') !== '' ? trim((string) $header['contact_email']) : null,
                'loc_id' => $locationId,
                'uid' => $userId,
                'ddate' => $deliveryDate,
                'pdate' => !empty($header['planned_delivery_date']) ? trim((string) $header['planned_delivery_date']) : $deliveryDate,
                'carrier' => ($header['carrier'] ?? '') !== '' ? trim((string) $header['carrier']) : null,
                'track' => ($header['tracking_number'] ?? '') !== '' ? trim((string) $header['tracking_number']) : null,
                'addr' => $shippingAddress,
                'remarks' => ($header['remarks'] ?? '') !== '' ? trim((string) $header['remarks']) : null,
            ]);
            $deliveryId = (int) $pdo->lastInsertId();

            // Log initial delivery trajectory update
            $pdo->prepare(
                'INSERT INTO customer_delivery_updates (delivery_id, previous_status, new_status, remarks, created_by_id)
                 VALUES (:did, NULL, "dispatched", :rem, :uid)'
            )->execute([
                'did' => $deliveryId,
                'rem' => "Initial Stock Out dispatch recorded (DR: {$drNumber}).",
                'uid' => $userId,
            ]);

            // Create stock_transactions header
            $txNumber = InventoryLedger::generateStockTransactionNumber($pdo, 'SOUT');
            $pdo->prepare(
                'INSERT INTO stock_transactions (
                    transaction_number, transaction_type, reference_type, reference_id,
                    source_location_id, performed_by_id, transaction_date, notes
                 ) VALUES (
                    :tx_no, "STOCK_OUT", "CUSTOMER_DELIVERY", :ref_id,
                    :loc_id, :uid, :tx_date, :notes
                 )'
            )->execute([
                'tx_no' => $txNumber,
                'ref_id' => $deliveryId,
                'loc_id' => $locationId,
                'uid' => $userId,
                'tx_date' => $deliveryDate . ' ' . date('H:i:s'),
                'notes' => "Stock Out DR {$drNumber} to {$companyName}",
            ]);
            $stockTxId = (int) $pdo->lastInsertId();

            // 4. Process lines with FOR UPDATE row locking on serial units
            $unitsIssued = 0;
            $seenUnitIds = [];

            foreach ($lines as $idx => $line) {
                $lineNum = $idx + 1;
                $itemId = (int) ($line['inventory_item_id'] ?? 0);
                $qty = (int) ($line['quantity'] ?? 0);

                if ($itemId <= 0 || $qty <= 0) {
                    throw new InvalidArgumentException("Line #{$lineNum}: Valid product and positive quantity are required.");
                }

                $itemStmt = $pdo->prepare('SELECT * FROM inventory_items WHERE id = :id LIMIT 1');
                $itemStmt->execute(['id' => $itemId]);
                $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
                if (!$item) {
                    throw new InvalidArgumentException("Line #{$lineNum}: Product #{$itemId} not found.");
                }

                $isSerialized = (int) $item['is_serialized'] === 1;
                $unitIds = array_values(array_map('intval', $line['unit_ids'] ?? []));
                $warrantyMonths = isset($line['warranty_duration_months']) && $line['warranty_duration_months'] !== ''
                    ? max(0, (int) $line['warranty_duration_months'])
                    : (int) ($item['default_warranty_months'] ?? $defaultWarrantyMonths);
                $unitPrice = round((float) ($line['unit_price'] ?? $item['unit_price'] ?? 0), 2);

                if ($isSerialized && count($unitIds) !== $qty) {
                    throw new InvalidArgumentException(
                        "Line #{$lineNum} ({$item['item_name']}): Out Qty ({$qty}) must match selected serial count (" . count($unitIds) . ').'
                    );
                }

                $pdo->prepare(
                    'INSERT INTO customer_delivery_items (
                        delivery_id, inventory_item_id, quantity_delivered, unit_price,
                        warranty_duration_months, notes
                     ) VALUES (
                        :did, :item_id, :qty, :price, :w_months, :notes
                     )'
                )->execute([
                    'did' => $deliveryId,
                    'item_id' => $itemId,
                    'qty' => $qty,
                    'price' => $unitPrice,
                    'w_months' => $warrantyMonths,
                    'notes' => ($line['notes'] ?? '') !== '' ? trim((string) $line['notes']) : null,
                ]);
                $deliveryItemId = (int) $pdo->lastInsertId();

                $pdo->prepare(
                    'INSERT INTO stock_transaction_items (transaction_id, inventory_item_id, quantity, unit_cost, notes)
                     VALUES (:tx_id, :item_id, :qty, :cost, :notes)'
                )->execute([
                    'tx_id' => $stockTxId,
                    'item_id' => $itemId,
                    'qty' => $qty,
                    'cost' => (float) ($item['unit_cost'] ?? 0),
                    'notes' => "DR {$drNumber}",
                ]);
                $txItemId = (int) $pdo->lastInsertId();

                if ($isSerialized) {
                    $cdsIns = $pdo->prepare('INSERT INTO customer_delivery_serials (delivery_item_id, unit_id, is_active) VALUES (:cdi, :uid, 1)');
                    $stsIns = $pdo->prepare('INSERT INTO stock_transaction_serials (transaction_item_id, unit_id) VALUES (:sti, :uid)');
                    $unitUpd = $pdo->prepare(
                        'UPDATE inventory_units
                         SET status = "delivered",
                             client_id = :cid,
                             customer_delivery_id = :did,
                             dr_reference = :dr,
                             invoice_number = :inv,
                             stock_out_date = :odate,
                             warranty_duration = :w_dur,
                             updated_at = NOW()
                         WHERE id = :uid'
                    );

                    foreach ($unitIds as $unitId) {
                        if (isset($seenUnitIds[$unitId])) {
                            throw new InvalidArgumentException("Duplicate unit #{$unitId} selected within the same Stock Out form.");
                        }
                        $seenUnitIds[$unitId] = true;

                        // Pessimistic row lock to prevent concurrent double-issuance
                        $uStmt = $pdo->prepare('SELECT * FROM inventory_units WHERE id = :id FOR UPDATE');
                        $uStmt->execute(['id' => $unitId]);
                        $u = $uStmt->fetch(PDO::FETCH_ASSOC);

                        if (!$u) {
                            throw new RuntimeException("Serialized unit #{$unitId} not found.");
                        }
                        if ((int) $u['inventory_item_id'] !== $itemId) {
                            throw new RuntimeException("Serial '{$u['serial_number']}' does not belong to {$item['item_name']}.");
                        }
                        if ((string) $u['status'] !== InventoryUnit::STATUS_IN_STOCK) {
                            throw new RuntimeException(
                                "Serial '{$u['serial_number']}' is not available for Stock Out (current status: {$u['status']})."
                            );
                        }

                        $unitUpd->execute([
                            'cid' => $clientId,
                            'did' => $deliveryId,
                            'dr' => $drNumber,
                            'inv' => $invoiceNumber,
                            'odate' => $deliveryDate,
                            'w_dur' => "{$warrantyMonths} Months",
                            'uid' => $unitId,
                        ]);

                        $cdsIns->execute(['cdi' => $deliveryItemId, 'uid' => $unitId]);
                        $stsIns->execute(['sti' => $txItemId, 'uid' => $unitId]);
                        $unitsIssued++;
                    }
                }

                // Post immutable double-entry ledger debit & decrement inventory_stock
                InventoryLedger::postMovement($pdo, [
                    'inventory_item_id' => $itemId,
                    'location_id' => $locationId,
                    'transaction_type' => 'STOCK_OUT',
                    'reference_type' => 'CUSTOMER_DELIVERY',
                    'reference_id' => $deliveryId,
                    'quantity_in' => 0,
                    'quantity_out' => $qty,
                    'unit_cost' => (float) ($item['unit_cost'] ?? 0),
                    'performed_by_id' => $userId,
                    'notes' => "Stock Out DR {$drNumber} to {$companyName}",
                ]);
            }

            ActivityLogger::log(
                'STOCK_OUT',
                'customer_delivery',
                $deliveryId,
                "Recorded Stock Out {$soRef} (DR: {$drNumber}) to {$companyName} — {$unitsIssued} serialized unit(s).",
                null,
                $userId,
                false,
                null,
                [
                    'delivery_number' => $deliveryNumber,
                    'stock_out_reference' => $soRef,
                    'dr_number' => $drNumber,
                    'invoice_number' => $invoiceNumber,
                    'client_id' => $clientId,
                    'company_name' => $companyName,
                    'units_issued' => $unitsIssued,
                ]
            );

            return [
                'delivery_id' => $deliveryId,
                'delivery_number' => $deliveryNumber,
                'stock_out_reference' => $soRef,
                'dr_number' => $drNumber,
                'units_issued' => $unitsIssued,
            ];
        });
    }

    /**
     * Update delivery trajectory status (dispatched -> in_transit -> delivered / delayed / returned).
     * Automatically activates 24-month warranties when confirmed `delivered`.
     *
     * @param array<string, mixed>|null $confirmationData
     * @return array<string, mixed>
     */
    public static function updateDeliveryStatus(
        int $deliveryId,
        string $newStatus,
        ?string $remarks,
        int $userId,
        ?array $confirmationData = null
    ): array {
        $allowed = ['dispatched', 'in_transit', 'delivered', 'delayed', 'returned'];
        if (!in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException("Invalid delivery status '{$newStatus}'.");
        }

        return Database::transaction(static function (PDO $pdo) use ($deliveryId, $newStatus, $remarks, $userId, $confirmationData): array {
            $stmt = $pdo->prepare('SELECT * FROM customer_deliveries WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $deliveryId]);
            $del = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$del) {
                throw new InvalidArgumentException("Delivery #{$deliveryId} not found.");
            }
            if ((string) $del['status'] === 'cancelled') {
                throw new RuntimeException('Cancelled deliveries cannot be updated.');
            }

            $prevStatus = (string) $del['status'];
            $actualDate = $del['actual_delivery_date'];
            $receivedBy = $del['received_by_client_name'];
            $confirmNotes = $del['delivery_confirmation_notes'];

            if ($newStatus === 'delivered') {
                $actualDate = !empty($confirmationData['actual_delivery_date'])
                    ? trim((string) $confirmationData['actual_delivery_date'])
                    : ($actualDate ?: date('Y-m-d'));
                $receivedBy = !empty($confirmationData['received_by_client_name'])
                    ? trim((string) $confirmationData['received_by_client_name'])
                    : $receivedBy;
                $confirmNotes = !empty($confirmationData['delivery_confirmation_notes'])
                    ? trim((string) $confirmationData['delivery_confirmation_notes'])
                    : $confirmNotes;
            }

            $pdo->prepare(
                'UPDATE customer_deliveries
                 SET status = :st,
                     actual_delivery_date = :adate,
                     received_by_client_name = :rec_by,
                     delivery_confirmation_notes = :cnotes,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'st' => $newStatus,
                'adate' => $actualDate,
                'rec_by' => $receivedBy,
                'cnotes' => $confirmNotes,
                'id' => $deliveryId,
            ]);

            $pdo->prepare(
                'INSERT INTO customer_delivery_updates (delivery_id, previous_status, new_status, remarks, created_by_id)
                 VALUES (:did, :prev, :new, :rem, :uid)'
            )->execute([
                'did' => $deliveryId,
                'prev' => $prevStatus,
                'new' => $newStatus,
                'rem' => $remarks !== null && trim($remarks) !== '' ? trim($remarks) : "Status updated to {$newStatus}.",
                'uid' => $userId,
            ]);

            // Activate 24-month warranties strictly upon confirmed customer delivery
            if ($newStatus === 'delivered' && !empty($actualDate)) {
                Warranty::activateForDelivery($pdo, $deliveryId, (string) $actualDate, $userId);
            }

            ActivityLogger::log(
                'DELIVERY_UPDATED',
                'customer_delivery',
                $deliveryId,
                "Delivery {$del['dr_number']} status changed from {$prevStatus} to {$newStatus}.",
                null,
                $userId,
                false,
                ['status' => $prevStatus],
                ['status' => $newStatus, 'actual_delivery_date' => $actualDate, 'received_by' => $receivedBy]
            );

            return (array) self::find($deliveryId);
        });
    }

    /**
     * Cancel / Reverse a Stock Out delivery atomically with mandatory reason.
     * Restores all attached serialized units back to `in_stock`, increments `inventory_stock`,
     * and posts offsetting REVERSAL entries to `inventory_ledger`.
     *
     * @return array<string, mixed>
     */
    public static function cancelDelivery(int $deliveryId, string $reason, int $userId): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A mandatory cancellation reason is required to reverse a Stock Out delivery.');
        }

        return Database::transaction(static function (PDO $pdo) use ($deliveryId, $reason, $userId): array {
            $stmt = $pdo->prepare('SELECT * FROM customer_deliveries WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $deliveryId]);
            $del = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$del) {
                throw new InvalidArgumentException("Delivery #{$deliveryId} not found.");
            }
            if ((string) $del['status'] === 'cancelled') {
                throw new RuntimeException("Delivery {$del['dr_number']} is already cancelled.");
            }

            $prevStatus = (string) $del['status'];
            $locationId = (int) ($del['source_location_id'] ?? 1);

            // Create offsetting stock transaction
            $txNumber = InventoryLedger::generateStockTransactionNumber($pdo, 'REV');
            $pdo->prepare(
                'INSERT INTO stock_transactions (
                    transaction_number, transaction_type, reference_type, reference_id,
                    destination_location_id, performed_by_id, transaction_date, notes
                 ) VALUES (
                    :tx_no, "STOCK_IN", "CLIENT_RETURN", :ref_id,
                    :loc_id, :uid, NOW(), :notes
                 )'
            )->execute([
                'tx_no' => $txNumber,
                'ref_id' => $deliveryId,
                'loc_id' => $locationId,
                'uid' => $userId,
                'notes' => "Reversal of Delivery {$del['dr_number']}: {$reason}",
            ]);
            $revTxId = (int) $pdo->lastInsertId();

            $itemsStmt = $pdo->prepare('SELECT * FROM customer_delivery_items WHERE delivery_id = :did');
            $itemsStmt->execute(['did' => $deliveryId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $restoredUnits = 0;
            foreach ($items as $it) {
                $itemId = (int) $it['inventory_item_id'];
                $qty = (int) $it['quantity_delivered'];

                $pdo->prepare(
                    'INSERT INTO stock_transaction_items (transaction_id, inventory_item_id, quantity, unit_cost, notes)
                     VALUES (:tx_id, :item_id, :qty, :cost, :notes)'
                )->execute([
                    'tx_id' => $revTxId,
                    'item_id' => $itemId,
                    'qty' => $qty,
                    'cost' => (float) $it['unit_price'],
                    'notes' => "Reversal of DR {$del['dr_number']}",
                ]);
                $revTxItemId = (int) $pdo->lastInsertId();

                // Restore attached active serialized units back to in_stock
                $serStmt = $pdo->prepare(
                    'SELECT cds.id AS cds_id, cds.unit_id
                     FROM customer_delivery_serials cds
                     WHERE cds.delivery_item_id = :cdi_id AND cds.is_active = 1'
                );
                $serStmt->execute(['cdi_id' => (int) $it['id']]);
                $serialRows = $serStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($serialRows as $sr) {
                    $unitId = (int) $sr['unit_id'];

                    // Lock unit row
                    $pdo->prepare('SELECT id FROM inventory_units WHERE id = :id FOR UPDATE')->execute(['id' => $unitId]);

                    $pdo->prepare(
                        'UPDATE inventory_units
                         SET status = "in_stock",
                             client_id = NULL,
                             customer_delivery_id = NULL,
                             dr_reference = NULL,
                             invoice_number = NULL,
                             stock_out_date = NULL,
                             warranty_start_date = NULL,
                             warranty_expiry_date = NULL,
                             updated_at = NOW()
                         WHERE id = :uid'
                    )->execute(['uid' => $unitId]);

                    $pdo->prepare('UPDATE customer_delivery_serials SET is_active = 0, returned_at = NOW() WHERE id = :id')
                        ->execute(['id' => (int) $sr['cds_id']]);

                    $pdo->prepare('INSERT INTO stock_transaction_serials (transaction_item_id, unit_id) VALUES (:sti, :uid)')
                        ->execute(['sti' => $revTxItemId, 'uid' => $unitId]);

                    $restoredUnits++;
                }

                // Post offsetting ledger entry restoring inventory_stock
                InventoryLedger::postMovement($pdo, [
                    'inventory_item_id' => $itemId,
                    'location_id' => $locationId,
                    'transaction_type' => 'REVERSAL',
                    'reference_type' => 'REVERSAL',
                    'reference_id' => $deliveryId,
                    'quantity_in' => $qty,
                    'quantity_out' => 0,
                    'unit_cost' => (float) $it['unit_price'],
                    'performed_by_id' => $userId,
                    'notes' => "Cancelled Delivery {$del['dr_number']}: {$reason}",
                ]);
            }

            // Void any warranties linked to this cancelled delivery
            $pdo->prepare('UPDATE warranties SET status = "voided", remarks = CONCAT(COALESCE(remarks, ""), " [Voided: Delivery Cancelled]"), updated_at = NOW() WHERE delivery_id = :did')
                ->execute(['did' => $deliveryId]);

            // Mark delivery cancelled
            $pdo->prepare(
                'UPDATE customer_deliveries
                 SET status = "cancelled",
                     cancellation_reason = :reason,
                     cancelled_at = NOW(),
                     cancelled_by_id = :uid,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'reason' => $reason,
                'uid' => $userId,
                'id' => $deliveryId,
            ]);

            $pdo->prepare(
                'INSERT INTO customer_delivery_updates (delivery_id, previous_status, new_status, remarks, created_by_id)
                 VALUES (:did, :prev, "cancelled", :rem, :uid)'
            )->execute([
                'did' => $deliveryId,
                'prev' => $prevStatus,
                'rem' => "Delivery cancelled and stock reversed ({$restoredUnits} unit(s) restored): {$reason}",
                'uid' => $userId,
            ]);

            ActivityLogger::log(
                'STOCK_OUT_CANCELLED',
                'customer_delivery',
                $deliveryId,
                "Cancelled Delivery {$del['dr_number']} and restored {$restoredUnits} serialized unit(s) to in_stock: {$reason}",
                null,
                $userId,
                false,
                ['status' => $prevStatus],
                ['status' => 'cancelled', 'reason' => $reason, 'restored_units' => $restoredUnits, 'reversal_tx' => $txNumber]
            );

            return [
                'delivery_id' => $deliveryId,
                'restored_units' => $restoredUnits,
                'reversal_transaction' => $txNumber,
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
            $where[] = '(cd.delivery_number LIKE :s1 OR cd.dr_number LIKE :s2 OR cd.invoice_number LIKE :s3 OR cd.client_name LIKE :s4 OR c.company_name LIKE :s5 OR cd.customer_po_number LIKE :s6)';
            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
            $params['s4'] = $term;
            $params['s5'] = $term;
            $params['s6'] = $term;
        }

        if (!empty($filters['status'])) {
            $where[] = 'cd.status = :st';
            $params['st'] = trim((string) $filters['status']);
        }

        if (!empty($filters['client_id'])) {
            $where[] = 'cd.client_id = :cid';
            $params['cid'] = (int) $filters['client_id'];
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $db->prepare(
            "SELECT COUNT(cd.id) FROM customer_deliveries cd LEFT JOIN clients c ON c.id = cd.client_id WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT cd.*,
                       COALESCE(c.company_name, cd.client_name) AS display_client_name,
                       u.full_name AS prepared_by_name,
                       loc.name AS location_name,
                       COUNT(cdi.id) AS lines_count,
                       COALESCE(SUM(cdi.quantity_delivered), 0) AS total_units
                FROM customer_deliveries cd
                LEFT JOIN clients c ON c.id = cd.client_id
                LEFT JOIN users u ON u.id = cd.prepared_by_id
                LEFT JOIN inventory_locations loc ON loc.id = cd.source_location_id
                LEFT JOIN customer_delivery_items cdi ON cdi.delivery_id = cd.id
                WHERE {$whereSql}
                GROUP BY cd.id
                ORDER BY cd.delivery_date DESC, cd.id DESC
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
            'SELECT cd.*,
                    COALESCE(c.company_name, cd.client_name) AS display_client_name,
                    u.full_name AS prepared_by_name,
                    cu.full_name AS cancelled_by_name,
                    loc.name AS location_name
             FROM customer_deliveries cd
             LEFT JOIN clients c ON c.id = cd.client_id
             LEFT JOIN users u ON u.id = cd.prepared_by_id
             LEFT JOIN users cu ON cu.id = cd.cancelled_by_id
             LEFT JOIN inventory_locations loc ON loc.id = cd.source_location_id
             WHERE cd.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getItemsWithSerials(int $deliveryId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT cdi.*, ii.item_code, ii.item_name, ii.model_part_number, ii.is_serialized
             FROM customer_delivery_items cdi
             INNER JOIN inventory_items ii ON ii.id = cdi.inventory_item_id
             WHERE cdi.delivery_id = :did
             ORDER BY cdi.id ASC'
        );
        $stmt->execute(['did' => $deliveryId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $serStmt = $db->prepare(
            'SELECT iu.id, iu.serial_number, iu.status, cds.is_active, cds.returned_at
             FROM customer_delivery_serials cds
             INNER JOIN inventory_units iu ON iu.id = cds.unit_id
             WHERE cds.delivery_item_id = :cdi_id
             ORDER BY iu.id ASC'
        );

        foreach ($items as &$it) {
            $serStmt->execute(['cdi_id' => (int) $it['id']]);
            $it['serials'] = $serStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($it);

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getUpdates(int $deliveryId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT cdu.*, u.full_name AS created_by_name
             FROM customer_delivery_updates cdu
             LEFT JOIN users u ON u.id = cdu.created_by_id
             WHERE cdu.delivery_id = :did
             ORDER BY cdu.created_at DESC, cdu.id DESC'
        );
        $stmt->execute(['did' => $deliveryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
