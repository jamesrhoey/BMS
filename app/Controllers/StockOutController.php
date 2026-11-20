<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Client;
use App\Models\ProductCatalog;
use App\Models\StockOut;
use App\Services\AuthorizationService;
use Throwable;

final class StockOutController extends Controller
{
    public function index(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized access to Stock Out & Deliveries.');
        }

        $page = max(1, (int) $request->query('page', '1'));
        $filters = [
            'search' => (string) $request->query('search', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $result = StockOut::paginate($filters, $page, 20);

        $this->render('iatms/stock_out/index', [
            'pageTitle' => 'Stock Out & Customer Deliveries (Form 2)',
            'activeNav' => 'iatms_stock_out',
            'deliveries' => $result['data'],
            'pagination' => $result,
            'filters' => $filters,
            'canCreate' => AuthorizationService::canRecordIatmsStockOut($user),
        ]);
    }

    public function create(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canRecordIatmsStockOut($user)) {
            $this->denyAccess($request, 'Unauthorized access to record Stock Out.');
        }

        $this->render('iatms/stock_out/create', [
            'pageTitle' => 'Record Stock Out — Digitized Paper Form 2',
            'activeNav' => 'iatms_stock_out_create',
            'nextDrNumber' => StockOut::generateDRNumber(),
            'clients' => Client::allActive(),
            'locations' => ProductCatalog::locations(),
            'availableUnits' => StockOut::getAvailableSerializedUnits(),
            'bulkProducts' => array_values(array_filter(ProductCatalog::allActive(), static fn(array $p): bool => (int) $p['is_serialized'] === 0 && (int) $p['quantity_available'] > 0)),
        ]);
    }

    public function store(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canRecordIatmsStockOut($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to record Stock Out.');
        }

        $post = $request->allPost();
        $header = [
            'dr_number' => $post['dr_number'] ?? null,
            'invoice_number' => $post['invoice_number'] ?? null,
            'customer_po_number' => $post['customer_po_number'] ?? null,
            'pr_reference' => $post['pr_reference'] ?? null,
            'client_id' => !empty($post['client_id']) ? (int) $post['client_id'] : null,
            'company_name' => $post['company_name'] ?? null,
            'shipping_address' => (string) ($post['shipping_address'] ?? ''),
            'contact_person' => $post['contact_person'] ?? null,
            'contact_phone' => $post['contact_phone'] ?? null,
            'source_location_id' => !empty($post['source_location_id']) ? (int) $post['source_location_id'] : 1,
            'delivery_date' => (string) ($post['delivery_date'] ?? date('Y-m-d')),
            'planned_delivery_date' => $post['planned_delivery_date'] ?? null,
            'carrier' => $post['carrier'] ?? null,
            'tracking_number' => $post['tracking_number'] ?? null,
            'remarks' => $post['remarks'] ?? null,
        ];

        $warrantyMonths = isset($post['warranty_duration_months']) && $post['warranty_duration_months'] !== ''
            ? max(0, (int) $post['warranty_duration_months'])
            : 24;

        // Group selected serialized unit IDs by inventory_item_id
        $selectedUnitIds = array_values(array_filter(array_map('intval', (array) ($post['unit_ids'] ?? []))));
        $lines = [];

        if ($selectedUnitIds !== []) {
            $allUnits = StockOut::getAvailableSerializedUnits();
            $unitMap = [];
            foreach ($allUnits as $u) {
                $unitMap[(int) $u['id']] = $u;
            }

            $grouped = [];
            foreach ($selectedUnitIds as $uid) {
                if (!isset($unitMap[$uid])) {
                    continue;
                }
                $iid = (int) $unitMap[$uid]['inventory_item_id'];
                if (!isset($grouped[$iid])) {
                    $grouped[$iid] = [
                        'inventory_item_id' => $iid,
                        'quantity' => 0,
                        'unit_price' => (float) ($unitMap[$uid]['unit_price'] ?? 0),
                        'warranty_duration_months' => $warrantyMonths,
                        'unit_ids' => [],
                    ];
                }
                $grouped[$iid]['unit_ids'][] = $uid;
                $grouped[$iid]['quantity']++;
            }
            $lines = array_values($grouped);
        }

        // Optional bulk items
        $bulkIds = $post['bulk_item_id'] ?? [];
        $bulkQtys = $post['bulk_qty'] ?? [];
        if (is_array($bulkIds)) {
            foreach ($bulkIds as $i => $bid) {
                $iid = (int) $bid;
                $qty = (int) ($bulkQtys[$i] ?? 0);
                if ($iid > 0 && $qty > 0) {
                    $lines[] = [
                        'inventory_item_id' => $iid,
                        'quantity' => $qty,
                        'warranty_duration_months' => $warrantyMonths,
                        'unit_ids' => [],
                    ];
                }
            }
        }

        try {
            $res = StockOut::recordStockOut($header, $lines, (int) $user['id']);
            Session::flash('success', "Stock Out {$res['stock_out_reference']} (DR: {$res['dr_number']}) recorded successfully ({$res['units_issued']} serialized unit(s) issued).");
            Response::redirect('/inventory/stock-out/' . $res['delivery_id']);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/inventory/stock-out/create');
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized access to Stock Out delivery.');
        }

        $id = (int) ($params['id'] ?? 0);
        $delivery = StockOut::find($id);
        if (!$delivery) {
            $this->notFound('Customer delivery / Stock Out record not found.');
        }

        $this->render('iatms/stock_out/show', [
            'pageTitle' => 'Delivery Receipt — ' . ($delivery['dr_number'] ?: $delivery['delivery_number']),
            'activeNav' => 'iatms_stock_out',
            'delivery' => $delivery,
            'items' => StockOut::getItemsWithSerials($id),
            'updates' => StockOut::getUpdates($id),
            'canManage' => AuthorizationService::canManageIatmsDeliveries($user),
        ]);
    }

    public function updateStatus(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsDeliveries($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to update delivery status.');
        }

        $id = (int) ($params['id'] ?? 0);
        $post = $request->allPost();
        try {
            StockOut::updateDeliveryStatus(
                $id,
                (string) ($post['new_status'] ?? ''),
                $post['remarks'] ?? null,
                (int) $user['id'],
                [
                    'actual_delivery_date' => $post['actual_delivery_date'] ?? null,
                    'received_by_client_name' => $post['received_by_client_name'] ?? null,
                    'delivery_confirmation_notes' => $post['delivery_confirmation_notes'] ?? null,
                ]
            );
            Session::flash('success', 'Delivery status updated successfully.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::redirect("/inventory/stock-out/{$id}");
    }

    public function cancel(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsDeliveries($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to cancel delivery.');
        }

        $id = (int) ($params['id'] ?? 0);
        $reason = (string) ($request->post('cancellation_reason', ''));
        try {
            $res = StockOut::cancelDelivery($id, $reason, (int) $user['id']);
            Session::flash('success', "Delivery cancelled and {$res['restored_units']} unit(s) restored to In Stock.");
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::redirect("/inventory/stock-out/{$id}");
    }
}
