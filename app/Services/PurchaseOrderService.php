<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Services\ActivityLogger;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectWorkflowHistory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use InvalidArgumentException;

class PurchaseOrderService
{
    /**
     * Create a Purchase Order from an approved Purchase Request.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $pr
     * @param array<string, mixed> $data
     * @param array<string, mixed> $actor
     * @return array{success: bool, po_id?: int, po_number?: string, error?: string}
     */
    public static function createPurchaseOrder(
        array $project,
        array $pr,
        array $data,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canCreatePurchaseOrder($actor, $project, $pr)) {
            return [
                'success' => false,
                'error' => 'You are not authorized to create Purchase Orders, or the Purchase Request is not approved for PO creation.',
            ];
        }

        $supplierId = (int) ($data['supplier_id'] ?? 0);
        $supplier = Supplier::findById($supplierId);
        if (!$supplier) {
            return [
                'success' => false,
                'error' => 'Please select a valid approved supplier.',
            ];
        }

        $poDate = trim((string) ($data['po_date'] ?? date('Y-m-d')));
        if ($poDate === '') {
            $poDate = date('Y-m-d');
        }

        $poNumber = trim((string) ($data['po_number'] ?? ''));
        if ($poNumber === '') {
            $poNumber = PurchaseOrder::generatePoNumber($poDate);
        }

        $projectId = (int) $project['id'];
        $prId = (int) $pr['id'];
        $jomNumber = (string) ($project['jom_number'] ?? $pr['jom_number'] ?? '');
        $actorId = (int) $actor['id'];

        // Parse items
        $rawDescriptions = (array) ($data['item_description'] ?? []);
        $rawModelPartNumbers = (array) ($data['model_part_number'] ?? []);
        $rawSpecs = (array) ($data['item_specification'] ?? []);
        $rawQuantities = (array) ($data['quantity_ordered'] ?? $data['quantity'] ?? []);
        $rawUoms = (array) ($data['unit_of_measure'] ?? []);
        $rawUnitPrices = (array) ($data['unit_price'] ?? []);
        $rawPrItemIds = (array) ($data['pr_item_id'] ?? []);
        $rawRemarks = (array) ($data['item_remarks'] ?? []);

        $parsedItems = [];
        $totalItems = count($rawDescriptions);
        for ($i = 0; $i < $totalItems; $i++) {
            $desc = trim((string) ($rawDescriptions[$i] ?? ''));
            if ($desc === '') {
                continue;
            }

            $qty = (float) ($rawQuantities[$i] ?? 1.0);
            if ($qty <= 0) {
                return [
                    'success' => false,
                    'error' => "Quantity for item #{$i} must be greater than zero.",
                ];
            }

            $price = (float) ($rawUnitPrices[$i] ?? 0.0);
            $prItemId = !empty($rawPrItemIds[$i]) ? (int) $rawPrItemIds[$i] : null;

            $parsedItems[] = [
                'pr_item_id' => $prItemId,
                'item_description' => $desc,
                'model_part_number' => trim((string) ($rawModelPartNumbers[$i] ?? '')),
                'item_specification' => trim((string) ($rawSpecs[$i] ?? '')),
                'quantity_ordered' => $qty,
                'unit_of_measure' => trim((string) ($rawUoms[$i] ?? 'pcs')) ?: 'pcs',
                'unit_price' => $price,
                'remarks' => trim((string) ($rawRemarks[$i] ?? '')),
            ];
        }

        if ($parsedItems === []) {
            return [
                'success' => false,
                'error' => 'A Purchase Order must contain at least one valid item.',
            ];
        }

        // Parse payment terms
        $rawTerms = (array) ($data['payment_term'] ?? []);
        $rawPercents = (array) ($data['term_percentage'] ?? []);
        $rawAmounts = (array) ($data['term_amount'] ?? []);
        $rawDueDates = (array) ($data['term_due_date'] ?? []);
        $rawTermRemarks = (array) ($data['term_remarks'] ?? []);

        $parsedPaymentTerms = [];
        for ($k = 0; $k < count($rawTerms); $k++) {
            $termName = trim((string) ($rawTerms[$k] ?? ''));
            if ($termName === '') {
                continue;
            }
            $parsedPaymentTerms[] = [
                'payment_term' => $termName,
                'percentage' => (float) ($rawPercents[$k] ?? 0),
                'amount' => (float) ($rawAmounts[$k] ?? 0),
                'due_date' => !empty($rawDueDates[$k]) ? trim((string) $rawDueDates[$k]) : null,
                'status' => 'Scheduled',
                'remarks' => trim((string) ($rawTermRemarks[$k] ?? '')),
            ];
        }

        return Database::transaction(static function () use (
            $project,
            $pr,
            $prId,
            $projectId,
            $jomNumber,
            $supplierId,
            $poNumber,
            $poDate,
            $parsedItems,
            $parsedPaymentTerms,
            $data,
            $actorId,
            $actor,
            $request
        ): array {
            // Track and validate ordered quantities against approved PR items
            foreach ($parsedItems as $item) {
                if (!empty($item['pr_item_id'])) {
                    $orderResult = PurchaseRequest::recordOrderQuantity(
                        $prId,
                        (int) $item['pr_item_id'],
                        (float) $item['quantity_ordered'],
                        $actorId
                    );
                    if (!$orderResult['success']) {
                        return [
                            'success' => false,
                            'error' => $orderResult['error'] ?? 'Order quantity exceeds approved PR quantity.',
                        ];
                    }
                }
            }

            $poId = PurchaseOrder::create([
                'po_number' => $poNumber,
                'purchase_request_id' => $prId,
                'project_id' => $projectId,
                'jom_number' => $jomNumber,
                'supplier_id' => $supplierId,
                'po_date' => $poDate,
                'delivery_terms' => trim((string) ($data['delivery_terms'] ?? '')),
                'expected_delivery_date' => !empty($data['expected_delivery_date']) ? trim((string) $data['expected_delivery_date']) : null,
                'payment_terms_summary' => trim((string) ($data['payment_terms_summary'] ?? '')),
                'currency' => trim((string) ($data['currency'] ?? 'PHP')),
                'remarks' => trim((string) ($data['po_remarks'] ?? '')),
                'supporting_document_id' => !empty($data['supporting_document_id']) ? (int) $data['supporting_document_id'] : null,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'created_by' => $actorId,
            ], $parsedItems, $parsedPaymentTerms);

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => (string) ($project['status'] ?? ''),
                'to_status' => (string) ($project['status'] ?? ''),
                'action' => 'created_purchase_order',
                'from_department_id' => !empty($actor['department_id']) ? (int) $actor['department_id'] : null,
                'to_department_id' => !empty($actor['department_id']) ? (int) $actor['department_id'] : null,
                'acted_by' => $actorId,
                'notes' => sprintf(
                    "Admin Finance (%s) created Purchase Order '%s' for PR '%s' (JOM: '%s').",
                    $actor['full_name'] ?? $actor['username'],
                    $poNumber,
                    $pr['pr_number'] ?? '',
                    $jomNumber
                ),
            ]);

            ActivityLogger::log(
                'po.created',
                'project',
                $projectId,
                "Admin Finance user '{$actor['full_name']}' created Purchase Order '{$poNumber}' (" . count($parsedItems) . " items) for PR '{$pr['pr_number']}'.",
                $request,
                $actorId,
                false
            );

            return [
                'success' => true,
                'po_id' => $poId,
                'po_number' => $poNumber,
            ];
        });
    }

    /**
     * Financially authorize and approve a Purchase Order.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $po
     * @param array<string, mixed> $actor
     * @return array{success: bool, error?: string}
     */
    public static function authorizePurchaseOrder(
        array $project,
        array $po,
        ?string $notes,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canAuthorizePurchaseOrder($actor, $project, $po)) {
            return [
                'success' => false,
                'error' => 'You are not authorized to approve or authorize Purchase Orders.',
            ];
        }

        $poId = (int) $po['id'];
        $actorId = (int) $actor['id'];
        $projectId = (int) $project['id'];
        $poNumber = (string) $po['po_number'];

        $authorized = PurchaseOrder::markAuthorized($poId, $actorId, $notes);
        if (!$authorized) {
            return [
                'success' => false,
                'error' => 'Unable to authorize Purchase Order.',
            ];
        }

        ActivityLogger::log(
            'po.authorized',
            'project',
            $projectId,
            "Admin Finance user '{$actor['full_name']}' financially authorized Purchase Order '{$poNumber}'.",
            $request,
            $actorId,
            false
        );

        return ['success' => true];
    }

    /**
     * Officially issue authorized PO to supplier.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $po
     * @param array<string, mixed> $actor
     * @return array{success: bool, error?: string}
     */
    public static function issuePurchaseOrderToSupplier(
        array $project,
        array $po,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canIssuePurchaseOrder($actor, $project, $po)) {
            return [
                'success' => false,
                'error' => 'You are not authorized to issue this Purchase Order, or it is not yet approved.',
            ];
        }

        $poId = (int) $po['id'];
        $actorId = (int) $actor['id'];
        $projectId = (int) $project['id'];
        $poNumber = (string) $po['po_number'];

        $issued = PurchaseOrder::markIssuedToSupplier($poId, $actorId);
        if (!$issued) {
            return [
                'success' => false,
                'error' => 'Unable to issue Purchase Order to supplier.',
            ];
        }

        ActivityLogger::log(
            'po.issued_to_supplier',
            'project',
            $projectId,
            "Admin Finance user '{$actor['full_name']}' issued Purchase Order '{$poNumber}' to supplier '{$po['supplier_name']}'.",
            $request,
            $actorId,
            false
        );

        return ['success' => true];
    }
}
