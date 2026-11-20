<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\InventoryLedger;
use App\Models\ProductCatalog;
use App\Services\AuthorizationService;
use Throwable;

final class EndingBalanceController extends Controller
{
    public function index(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized access to Ending Balance.');
        }

        $filters = [
            'search' => (string) $request->query('search', ''),
            'location_id' => (string) $request->query('location_id', '1'),
            'stock_status' => (string) $request->query('stock_status', ''),
        ];

        $rows = InventoryLedger::getEndingBalanceList($filters);

        $this->render('iatms/ending_balance/index', [
            'pageTitle' => 'Ending Balance — Digitized Paper Form 3',
            'activeNav' => 'iatms_ending_balance',
            'rows' => $rows,
            'filters' => $filters,
            'locations' => ProductCatalog::locations(),
            'canManage' => AuthorizationService::canManageIatmsEndingBalance($user),
        ]);
    }

    public function updateRemarks(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsEndingBalance($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to update Ending Balance remarks.');
        }

        $itemId = (int) $request->post('inventory_item_id', '0');
        $locationId = max(1, (int) $request->post('location_id', '1'));
        $remarks = (string) $request->post('remarks', '');

        try {
            InventoryLedger::updateEndingBalanceRemarks($itemId, $locationId, $remarks, (int) $user['id']);
            Session::flash('success', 'Ending Balance (Form 3) remarks saved.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::redirect('/inventory/ending-balance?location_id=' . $locationId);
    }

    public function adjust(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsEndingBalance($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to adjust inventory balance.');
        }

        $itemId = (int) $request->post('inventory_item_id', '0');
        $locationId = max(1, (int) $request->post('location_id', '1'));
        $delta = (int) $request->post('delta', '0');
        $reason = (string) $request->post('reason', '');

        try {
            $res = InventoryLedger::adjustStock($itemId, $locationId, $delta, $reason, (int) $user['id']);
            Session::flash('success', "Stock adjusted ({$res['transaction_number']}). New balance: {$res['movement']['balance_after']}.");
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::redirect('/inventory/ending-balance?location_id=' . $locationId);
    }
}
