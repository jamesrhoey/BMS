<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

class Inventory
{
    /**
     * Generate sequential, zero-padded transaction number (e.g. TXN-202611-0001).
     */
    public static function generateTransactionNumber(?string $date = null): string
    {
        $dateObj = $date !== null ? new \DateTimeImmutable($date) : new \DateTimeImmutable('now');
        $prefix = 'TXN-' . $dateObj->format('Ym') . '-';

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT transaction_number
             FROM inventory_transactions
             WHERE transaction_number LIKE :prefix
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(['prefix' => $prefix . '%']);
        $latest = $stmt->fetchColumn();

        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $m)) {
            $seq = (int) $m[1] + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Find or create inventory item catalog entry.
     *
     * @return array<string, mixed>
     */
    public static function findOrCreateCatalogItem(
        string $itemCode,
        string $itemName,
        ?string $model = null,
        string $uom = 'pcs',
        bool $isSerialized = true
    ): array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM inventory_items WHERE item_code = :code LIMIT 1');
        $stmt->execute(['code' => trim($itemCode)]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            return $item;
        }

        $ins = $pdo->prepare(
            'INSERT INTO inventory_items (
                item_code,
                item_name,
                model_part_number,
                category,
                unit_of_measure,
                is_serialized,
                current_balance,
                status
            ) VALUES (
                :item_code,
                :item_name,
                :model,
                :category,
                :uom,
                :is_serialized,
                0.00,
                "active"
            )'
        );

        $ins->execute([
            'item_code' => trim($itemCode),
            'item_name' => trim($itemName),
            'model' => $model !== null && trim($model) !== '' ? trim($model) : null,
            'category' => $isSerialized ? 'Serialized Equipment' : 'Bulk Material',
            'uom' => $uom ?: 'pcs',
            'is_serialized' => $isSerialized ? 1 : 0,
        ]);

        $newId = (int) $pdo->lastInsertId();
        return (array) self::findItemById($newId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findItemById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM inventory_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Record a Stock In transaction linked to PO and delivery.
     *
     * @param array{
     *     delivery_id: int,
     *     purchase_order_id: int,
     *     project_id: int,
     *     jom_number: string,
     *     po_number: string,
     *     po_date: string,
     *     inventory_date: string,
     *     performed_by: int,
     *     warehouse_location?: string,
     *     remarks?: string,
     *     items: list<array{
     *         po_item_id: int,
     *         item_number?: int,
     *         item_name: string,
     *         model?: string,
     *         description: string,
     *         warranty_duration?: string,
     *         unit_of_measure?: string,
     *         accepted_quantity: float,
     *         serial_numbers?: list<string>
     *     }>
     * } $data
     * @return array{success: bool, error?: string, transaction_number?: string, units_count?: int}
     */
    public static function recordStockIn(array $data): array
    {
        $pdo = Database::connection();

        // 1. Validate serial numbers for duplicates upfront before modifying any ledger
        $allSerials = [];
        foreach ($data['items'] as $item) {
            $serials = $item['serial_numbers'] ?? [];
            foreach ($serials as $sn) {
                $trimmed = trim((string) $sn);
                if ($trimmed === '') {
                    continue;
                }
                if (isset($allSerials[$trimmed])) {
                    return [
                        'success' => false,
                        'error' => "Duplicate serial number '{$trimmed}' in current Stock In payload.",
                    ];
                }
                $allSerials[$trimmed] = true;
            }
        }

        if ($allSerials !== []) {
            $inList = implode(',', array_fill(0, count($allSerials), '?'));
            $chkStmt = $pdo->prepare("SELECT serial_number FROM inventory_units WHERE serial_number IN ({$inList}) LIMIT 1");
            $chkStmt->execute(array_keys($allSerials));
            $existingDuplicate = $chkStmt->fetchColumn();
            if ($existingDuplicate) {
                return [
                    'success' => false,
                    'error' => "Duplicate serial number '{$existingDuplicate}' already exists in inventory units.",
                ];
            }
        }

        // 2. Execute atomic Stock In transaction
        return Database::transaction(static function () use ($pdo, $data): array {
            $poId = (int) $data['purchase_order_id'];
            $deliveryId = (int) $data['delivery_id'];
            $projectId = (int) $data['project_id'];
            $jomNumber = (string) $data['jom_number'];
            $poNumber = (string) $data['po_number'];
            $poDate = (string) $data['po_date'];
            $inventoryDate = (string) ($data['inventory_date'] ?: date('Y-m-d'));
            $performedBy = (int) $data['performed_by'];
            $warehouseLocation = (string) ($data['warehouse_location'] ?? 'PCT Warehouse');
            $generalRemarks = (string) ($data['remarks'] ?? 'Stock In from Supplier Delivery');

            $totalUnitsCreated = 0;
            $firstTxnNumber = null;
            $lineSequence = 1;

            foreach ($data['items'] as $item) {
                $poItemId = (int) $item['po_item_id'];
                $acceptedQty = (float) $item['accepted_quantity'];
                if ($acceptedQty <= 0) {
                    continue;
                }

                $model = trim((string) ($item['model'] ?? ''));
                $description = trim((string) ($item['description'] ?? $item['item_name']));
                $warrantyDuration = trim((string) ($item['warranty_duration'] ?? '1 Year')) ?: '1 Year';
                $uom = (string) ($item['unit_of_measure'] ?? 'pcs');
                $serials = array_values(array_filter(array_map('trim', $item['serial_numbers'] ?? [])));
                $isSerialized = count($serials) > 0 || (isset($item['is_serialized']) && $item['is_serialized']);

                // Find or create catalog item
                $itemCode = $model !== '' ? 'ITEM-' . preg_replace('/[^A-Za-z0-9]/', '', $model) : 'ITEM-' . substr(md5($description), 0, 8);
                $catalogItem = self::findOrCreateCatalogItem(
                    $itemCode,
                    $description,
                    $model !== '' ? $model : null,
                    $uom,
                    $isSerialized
                );
                $catalogItemId = (int) $catalogItem['id'];

                // Lock catalog item row to calculate perpetual balance
                $lockStmt = $pdo->prepare('SELECT current_balance FROM inventory_items WHERE id = :id FOR UPDATE');
                $lockStmt->execute(['id' => $catalogItemId]);
                $balanceBefore = (float) ($lockStmt->fetchColumn() ?: 0.0);
                $balanceAfter = $balanceBefore + $acceptedQty;

                // Create transaction ledger record
                $txnNumber = self::generateTransactionNumber($inventoryDate);
                if ($firstTxnNumber === null) {
                    $firstTxnNumber = $txnNumber;
                }

                $insTxn = $pdo->prepare(
                    'INSERT INTO inventory_transactions (
                        transaction_number,
                        transaction_type,
                        inventory_item_id,
                        quantity,
                        unit_of_measure,
                        warehouse_location,
                        project_id,
                        jom_number,
                        reference_type,
                        reference_id,
                        balance_before,
                        balance_after,
                        performed_by,
                        remarks,
                        transaction_date
                    ) VALUES (
                        :txn_number,
                        "stock_in",
                        :catalog_item_id,
                        :quantity,
                        :uom,
                        :warehouse_location,
                        :project_id,
                        :jom_number,
                        "supplier_delivery",
                        :reference_id,
                        :balance_before,
                        :balance_after,
                        :performed_by,
                        :remarks,
                        :txn_date
                    )'
                );
                $insTxn->execute([
                    'txn_number' => $txnNumber,
                    'catalog_item_id' => $catalogItemId,
                    'quantity' => number_format($acceptedQty, 2, '.', ''),
                    'uom' => $uom,
                    'warehouse_location' => $warehouseLocation,
                    'project_id' => $projectId,
                    'jom_number' => $jomNumber,
                    'reference_id' => $deliveryId,
                    'balance_before' => number_format($balanceBefore, 2, '.', ''),
                    'balance_after' => number_format($balanceAfter, 2, '.', ''),
                    'performed_by' => $performedBy,
                    'remarks' => $generalRemarks,
                    'txn_date' => $inventoryDate . ' ' . date('H:i:s'),
                ]);
                $stockInTxnId = (int) $pdo->lastInsertId();

                // Increment running balance on inventory_items
                $pdo->prepare('UPDATE inventory_items SET current_balance = :balance, updated_at = NOW() WHERE id = :id')
                    ->execute([
                        'balance' => number_format($balanceAfter, 2, '.', ''),
                        'id' => $catalogItemId,
                    ]);

                // Insert individual physical serialized units
                $insUnit = $pdo->prepare(
                    'INSERT INTO inventory_units (
                        item_number,
                        po_number,
                        po_date,
                        model,
                        description,
                        warranty_duration,
                        serial_number,
                        in_qty,
                        inventory_date,
                        inventory_item_id,
                        purchase_order_id,
                        delivery_id,
                        stock_in_transaction_id,
                        project_id,
                        jom_number,
                        status,
                        current_location,
                        created_by
                    ) VALUES (
                        :item_number,
                        :po_number,
                        :po_date,
                        :model,
                        :description,
                        :warranty_duration,
                        :serial_number,
                        :in_qty,
                        :inventory_date,
                        :inventory_item_id,
                        :purchase_order_id,
                        :delivery_id,
                        :stock_in_transaction_id,
                        :project_id,
                        :jom_number,
                        "In Stock",
                        :current_location,
                        :created_by
                    )'
                );

                if (count($serials) > 0) {
                    foreach ($serials as $sn) {
                        $insUnit->execute([
                            'item_number' => $lineSequence,
                            'po_number' => $poNumber,
                            'po_date' => $poDate,
                            'model' => $model ?: 'Standard Unit',
                            'description' => $description,
                            'warranty_duration' => $warrantyDuration,
                            'serial_number' => $sn,
                            'in_qty' => number_format(1.0, 2, '.', ''),
                            'inventory_date' => $inventoryDate,
                            'inventory_item_id' => $catalogItemId,
                            'purchase_order_id' => $poId,
                            'delivery_id' => $deliveryId,
                            'stock_in_transaction_id' => $stockInTxnId,
                            'project_id' => $projectId,
                            'jom_number' => $jomNumber,
                            'current_location' => $warehouseLocation,
                            'created_by' => $performedBy,
                        ]);
                        $totalUnitsCreated++;
                    }
                } else {
                    // For non-serialized bulk items, create record with generated lot/unit identifier
                    $genSerial = 'LOT-' . $poNumber . '-' . $lineSequence;
                    $insUnit->execute([
                        'item_number' => $lineSequence,
                        'po_number' => $poNumber,
                        'po_date' => $poDate,
                        'model' => $model ?: 'Standard Bulk',
                        'description' => $description,
                        'warranty_duration' => $warrantyDuration,
                        'serial_number' => $genSerial,
                        'in_qty' => number_format($acceptedQty, 2, '.', ''),
                        'inventory_date' => $inventoryDate,
                        'inventory_item_id' => $catalogItemId,
                        'purchase_order_id' => $poId,
                        'delivery_id' => $deliveryId,
                        'stock_in_transaction_id' => $stockInTxnId,
                        'project_id' => $projectId,
                        'jom_number' => $jomNumber,
                        'current_location' => $warehouseLocation,
                        'created_by' => $performedBy,
                    ]);
                    $totalUnitsCreated++;
                }

                // Update PO item quantity received
                $pdo->prepare(
                    'UPDATE purchase_order_items
                     SET quantity_received = quantity_received + :accepted_qty,
                         updated_at = NOW()
                     WHERE id = :id'
                )->execute([
                    'accepted_qty' => number_format($acceptedQty, 2, '.', ''),
                    'id' => $poItemId,
                ]);

                $lineSequence++;
            }

            // Update overall PO status based on received quantities
            $newPoStatus = PurchaseOrder::updateReceivingStatus($poId);

            // Update delivery record status
            $pdo->prepare("UPDATE supplier_deliveries SET status = 'Stocked In', updated_at = NOW() WHERE id = :id")
                ->execute(['id' => $deliveryId]);

            return [
                'success' => true,
                'transaction_number' => $firstTxnNumber ?? self::generateTransactionNumber($inventoryDate),
                'units_count' => $totalUnitsCreated,
                'po_status' => $newPoStatus,
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function unitsForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT iu.*, ii.item_code, ii.item_name
             FROM inventory_units iu
             INNER JOIN inventory_items ii ON ii.id = iu.inventory_item_id
             WHERE iu.project_id = :project_id
             ORDER BY iu.id ASC'
        );
        $stmt->execute(['project_id' => $projectId]);

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function unitsForPo(int $poId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT iu.*, ii.item_code, ii.item_name
             FROM inventory_units iu
             INNER JOIN inventory_items ii ON ii.id = iu.inventory_item_id
             WHERE iu.purchase_order_id = :po_id
             ORDER BY iu.id ASC'
        );
        $stmt->execute(['po_id' => $poId]);

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function transactionsForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT it.*, ii.item_code, ii.item_name, u.full_name AS performed_by_name
             FROM inventory_transactions it
             INNER JOIN inventory_items ii ON ii.id = it.inventory_item_id
             LEFT JOIN users u ON u.id = it.performed_by
             WHERE it.project_id = :project_id
             ORDER BY it.id DESC'
        );
        $stmt->execute(['project_id' => $projectId]);

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
