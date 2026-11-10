<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Services\ActivityLogger;
use App\Models\Inventory;
use App\Models\Notification;
use App\Models\ProjectWorkflowHistory;
use App\Models\PurchaseOrder;
use App\Models\SupplierDelivery;

class ReceivingService
{
    /**
     * Record physical supplier delivery shipment.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $po
     * @param array<string, mixed> $data
     * @param array<string, mixed> $actor
     * @return array{success: bool, delivery_id?: int, error?: string}
     */
    public static function recordSupplierDelivery(
        array $project,
        array $po,
        array $data,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canReceiveSupplierDelivery($actor, $project, $po)) {
            return [
                'success' => false,
                'error' => 'You are not authorized to log supplier deliveries for this Purchase Order.',
            ];
        }

        $drNo = trim((string) ($data['delivery_receipt_no'] ?? ''));
        if ($drNo === '') {
            return [
                'success' => false,
                'error' => 'Delivery Receipt Number (DR #) is required.',
            ];
        }

        $deliveryDate = trim((string) ($data['delivery_date'] ?? date('Y-m-d')));
        if ($deliveryDate === '') {
            $deliveryDate = date('Y-m-d');
        }

        $poItemIds = (array) ($data['po_item_id'] ?? []);
        $delQuantities = (array) ($data['quantity_delivered'] ?? []);
        $accQuantities = (array) ($data['quantity_accepted'] ?? []);
        $rejQuantities = (array) ($data['quantity_rejected'] ?? []);
        $rejReasons = (array) ($data['rejection_reason'] ?? []);
        $remarks = (array) ($data['item_remarks'] ?? []);

        $parsedItems = [];
        $hasDiscrepancy = false;
        for ($i = 0; $i < count($poItemIds); $i++) {
            $poItemId = (int) $poItemIds[$i];
            $delQty = (float) ($delQuantities[$i] ?? 0.0);
            if ($delQty <= 0) {
                continue;
            }

            $accQty = isset($accQuantities[$i]) ? (float) $accQuantities[$i] : $delQty;
            $rejQty = isset($rejQuantities[$i]) ? (float) $rejQuantities[$i] : max(0.0, $delQty - $accQty);
            $reason = trim((string) ($rejReasons[$i] ?? ''));

            if ($rejQty > 0) {
                $hasDiscrepancy = true;
            }

            $parsedItems[] = [
                'po_item_id' => $poItemId,
                'quantity_delivered' => $delQty,
                'quantity_accepted' => $accQty,
                'quantity_rejected' => $rejQty,
                'rejection_reason' => $reason,
                'remarks' => trim((string) ($remarks[$i] ?? '')),
            ];
        }

        if ($parsedItems === []) {
            return [
                'success' => false,
                'error' => 'Please enter delivered quantities for at least one item.',
            ];
        }

        $initialStatus = $hasDiscrepancy ? SupplierDelivery::STATUS_DISCREPANCY : SupplierDelivery::STATUS_INSPECTED;

        $deliveryId = SupplierDelivery::create([
            'delivery_receipt_no' => $drNo,
            'purchase_order_id' => (int) $po['id'],
            'project_id' => (int) $project['id'],
            'supplier_id' => (int) $po['supplier_id'],
            'delivery_date' => $deliveryDate,
            'carrier_name' => trim((string) ($data['carrier_name'] ?? '')),
            'tracking_or_plate_no' => trim((string) ($data['tracking_or_plate_no'] ?? '')),
            'dr_document_id' => !empty($data['dr_document_id']) ? (int) $data['dr_document_id'] : null,
            'received_by' => (int) $actor['id'],
            'receiving_notes' => trim((string) ($data['receiving_notes'] ?? '')),
            'status' => $initialStatus,
        ], $parsedItems);

        ActivityLogger::log(
            'supplier_delivery.logged',
            'project',
            (int) $project['id'],
            "Logistics user '{$actor['full_name']}' logged supplier delivery (DR #{$drNo}) for PO '{$po['po_number']}'.",
            $request,
            (int) $actor['id'],
            false
        );

        return [
            'success' => true,
            'delivery_id' => $deliveryId,
        ];
    }

    /**
     * Execute Stock In transaction into inventory, creating serialized units.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $po
     * @param array<string, mixed> $delivery
     * @param array<string, mixed> $data
     * @param array<string, mixed> $actor
     * @return array{success: bool, transaction_number?: string, units_count?: int, error?: string}
     */
    public static function executeStockIn(
        array $project,
        array $po,
        array $delivery,
        array $data,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canExecuteStockIn($actor, $project, $delivery)) {
            return [
                'success' => false,
                'error' => 'You are not authorized to execute Stock In for this delivery.',
            ];
        }

        $deliveryItems = $delivery['items'] ?? [];
        if ($deliveryItems === []) {
            return [
                'success' => false,
                'error' => 'No delivery items available for Stock In.',
            ];
        }

        $inventoryDate = trim((string) ($data['inventory_date'] ?? date('Y-m-d')));
        if ($inventoryDate === '') {
            $inventoryDate = date('Y-m-d');
        }

        $warehouseLocation = trim((string) ($data['warehouse_location'] ?? 'PCT Warehouse')) ?: 'PCT Warehouse';

        // Prepare stock in items
        $stockInItems = [];
        $rawSerialsByPoItem = (array) ($data['serial_numbers'] ?? []);
        $rawWarranties = (array) ($data['warranty_duration'] ?? []);

        $lineNum = 1;
        foreach ($deliveryItems as $delItem) {
            $acceptedQty = (float) ($delItem['quantity_accepted'] ?? 0.0);
            if ($acceptedQty <= 0) {
                continue;
            }

            $poItemId = (int) $delItem['po_item_id'];
            $serials = [];
            if (isset($rawSerialsByPoItem[$poItemId])) {
                $rawVal = $rawSerialsByPoItem[$poItemId];
                if (is_array($rawVal)) {
                    $serials = array_values(array_filter(array_map('trim', $rawVal)));
                } elseif (is_string($rawVal)) {
                    $serials = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $rawVal))));
                }
            }

            $warranty = trim((string) ($rawWarranties[$poItemId] ?? '1 Year')) ?: '1 Year';

            $stockInItems[] = [
                'po_item_id' => $poItemId,
                'item_number' => $lineNum++,
                'item_name' => (string) ($delItem['item_description'] ?? 'Equipment Item'),
                'model' => (string) ($delItem['model_part_number'] ?? ''),
                'description' => (string) ($delItem['item_description'] ?? ''),
                'warranty_duration' => $warranty,
                'unit_of_measure' => (string) ($delItem['unit_of_measure'] ?? 'pcs'),
                'accepted_quantity' => $acceptedQty,
                'serial_numbers' => $serials,
            ];
        }

        if ($stockInItems === []) {
            return [
                'success' => false,
                'error' => 'No accepted items to stock in. Rejected items cannot be placed into available inventory.',
            ];
        }

        $stockInPayload = [
            'delivery_id' => (int) $delivery['id'],
            'purchase_order_id' => (int) $po['id'],
            'project_id' => (int) $project['id'],
            'jom_number' => (string) ($project['jom_number'] ?? $po['jom_number'] ?? ''),
            'po_number' => (string) $po['po_number'],
            'po_date' => (string) $po['po_date'],
            'inventory_date' => $inventoryDate,
            'performed_by' => (int) $actor['id'],
            'warehouse_location' => $warehouseLocation,
            'remarks' => trim((string) ($data['remarks'] ?? "Stock In for PO {$po['po_number']} DR {$delivery['delivery_receipt_no']}")),
            'items' => $stockInItems,
        ];

        $result = Inventory::recordStockIn($stockInPayload);
        if (!$result['success']) {
            return $result;
        }

        ProjectWorkflowHistory::record([
            'project_id' => (int) $project['id'],
            'from_status' => (string) ($project['status'] ?? ''),
            'to_status' => (string) ($project['status'] ?? ''),
            'action' => 'stock_in_completed',
            'from_department_id' => !empty($actor['department_id']) ? (int) $actor['department_id'] : null,
            'to_department_id' => !empty($actor['department_id']) ? (int) $actor['department_id'] : null,
            'acted_by' => (int) $actor['id'],
            'notes' => sprintf(
                "Logistics (%s) completed Stock In (%s) for PO '%s' (%d unit(s) registered).",
                $actor['full_name'] ?? $actor['username'],
                $result['transaction_number'] ?? '',
                $po['po_number'],
                $result['units_count'] ?? 0
            ),
        ]);

        ActivityLogger::log(
            'inventory.stock_in',
            'project',
            (int) $project['id'],
            "Logistics user '{$actor['full_name']}' completed Stock In (Txn #{$result['transaction_number']}) for PO '{$po['po_number']}' with {$result['units_count']} unit(s) added to inventory.",
            $request,
            (int) $actor['id'],
            false
        );

        return $result;
    }
}
