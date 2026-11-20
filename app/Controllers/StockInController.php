<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\ProductCatalog;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierReceipt;
use App\Services\AuthorizationService;
use Throwable;

final class StockInController extends Controller
{
    public function index(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized access to Stock In.');
        }

        $page = max(1, (int) $request->query('page', '1'));
        $filters = [
            'search' => (string) $request->query('search', ''),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];

        $result = SupplierReceipt::paginate($filters, $page, 20);

        $this->render('iatms/stock_in/index', [
            'pageTitle' => 'Stock In Receipts (Form 1)',
            'activeNav' => 'iatms_stock_in',
            'receipts' => $result['data'],
            'pagination' => $result,
            'filters' => $filters,
            'canCreate' => AuthorizationService::canRecordIatmsStockIn($user),
        ]);
    }

    public function create(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canRecordIatmsStockIn($user)) {
            $this->denyAccess($request, 'Unauthorized access to record Stock In.');
        }

        $this->render('iatms/stock_in/create', [
            'pageTitle' => 'Record Stock In — Digitized Paper Form 1',
            'activeNav' => 'iatms_stock_in_create',
            'nextReceiptNumber' => SupplierReceipt::generateReceiptNumber(),
            'products' => ProductCatalog::allActive(),
            'locations' => ProductCatalog::locations(),
            'suppliers' => Supplier::allActive(),
            'openPos' => PurchaseOrder::getOpenOrdersForReceiving(),
        ]);
    }

    public function store(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canRecordIatmsStockIn($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to record Stock In.');
        }

        $post = $request->allPost();
        $mode = (string) ($post['receiving_mode'] ?? 'direct');

        $header = [
            'receipt_number' => $post['receipt_number'] ?? null,
            'po_id' => $mode === 'po' && !empty($post['po_id']) ? (int) $post['po_id'] : null,
            'po_number' => $post['po_number'] ?? null,
            'po_date' => $post['po_date'] ?? null,
            'supplier_id' => !empty($post['supplier_id']) ? (int) $post['supplier_id'] : null,
            'supplier_name' => $post['supplier_name'] ?? null,
            'destination_location_id' => !empty($post['destination_location_id']) ? (int) $post['destination_location_id'] : 1,
            'receipt_date' => (string) ($post['receipt_date'] ?? date('Y-m-d')),
            'delivery_to_pct_date' => (string) ($post['delivery_to_pct_date'] ?? $post['receipt_date'] ?? date('Y-m-d')),
            'carrier' => $post['carrier'] ?? null,
            'waybill_number' => $post['waybill_number'] ?? null,
            'invoice_reference' => $post['invoice_reference'] ?? null,
            'remarks' => $post['remarks'] ?? null,
        ];

        $itemIds = $post['line_item_id'] ?? [];
        $poItemIds = $post['line_po_item_id'] ?? [];
        $qtys = $post['line_qty'] ?? [];
        $costs = $post['line_unit_cost'] ?? [];
        $warranties = $post['line_warranty_months'] ?? [];
        $serialTexts = $post['line_serials'] ?? [];

        $lines = [];
        if (is_array($itemIds)) {
            foreach ($itemIds as $i => $iid) {
                $itemId = (int) $iid;
                $qty = (int) ($qtys[$i] ?? 0);
                if ($itemId <= 0 || $qty <= 0) {
                    continue;
                }
                $lines[] = [
                    'inventory_item_id' => $itemId,
                    'po_item_id' => !empty($poItemIds[$i]) ? (int) $poItemIds[$i] : null,
                    'quantity_received' => $qty,
                    'unit_cost' => (float) ($costs[$i] ?? 0),
                    'warranty_duration_months' => isset($warranties[$i]) && $warranties[$i] !== '' ? (int) $warranties[$i] : 24,
                    'serial_numbers' => (string) ($serialTexts[$i] ?? ''),
                ];
            }
        }

        try {
            $res = SupplierReceipt::recordReceipt($header, $lines, (int) $user['id']);
            Session::flash('success', "Stock In {$res['receipt_number']} recorded successfully ({$res['units_created']} serialized unit(s) added).");
            Response::redirect('/inventory/stock-in/' . $res['receipt_id']);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/inventory/stock-in/create');
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized access to Stock In receipt.');
        }

        $id = (int) ($params['id'] ?? 0);
        $receipt = SupplierReceipt::find($id);
        if (!$receipt) {
            $this->notFound('Stock In receipt not found.');
        }

        $this->render('iatms/stock_in/show', [
            'pageTitle' => 'Stock In Receipt — ' . $receipt['receipt_number'],
            'activeNav' => 'iatms_stock_in',
            'receipt' => $receipt,
            'items' => SupplierReceipt::getItemsWithSerials($id),
        ]);
    }

    public function getPoItems(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canRecordIatmsStockIn($user)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $poId = (int) ($params['id'] ?? 0);
        $po = PurchaseOrder::findById($poId);
        $items = $poId > 0 ? PurchaseOrder::getReceivableItems($poId) : [];

        header('Content-Type: application/json');
        echo json_encode([
            'po' => $po,
            'items' => $items,
        ]);
        exit;
    }
}
