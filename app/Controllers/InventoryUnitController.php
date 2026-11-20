<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\InventoryUnit;
use App\Models\ProductCatalog;
use App\Services\AuthorizationService;
use Throwable;

final class InventoryUnitController extends Controller
{
    public function index(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized access to Serialized Units.');
        }

        $page = max(1, (int) $request->query('page', '1'));
        $filters = [
            'search' => (string) $request->query('search', ''),
            'status' => (string) $request->query('status', ''),
            'item_id' => (string) $request->query('item_id', ''),
        ];

        $result = InventoryUnit::paginate($filters, $page, 25);

        $this->render('iatms/units/index', [
            'pageTitle' => 'Serialized Hardware Units',
            'activeNav' => 'iatms_units',
            'units' => $result['data'],
            'pagination' => $result,
            'filters' => $filters,
            'products' => ProductCatalog::allActive(),
        ]);
    }

    public function show(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized access to Serial Unit Dossier.');
        }

        $id = (int) ($params['id'] ?? 0);
        try {
            $dossier = InventoryUnit::getSerialDossier($id);
        } catch (Throwable $e) {
            $this->notFound('Serialized unit not found.');
        }

        $this->render('iatms/units/show', [
            'pageTitle' => 'Serial Dossier — ' . $dossier['unit']['serial_number'],
            'activeNav' => 'iatms_units',
            'dossier' => $dossier,
            'canManageMaintenance' => AuthorizationService::canManageIatmsUnitMaintenance($user),
        ]);
    }

    public function transitionMaintenance(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsUnitMaintenance($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to change unit maintenance status.');
        }

        $id = (int) ($params['id'] ?? 0);
        $targetStatus = (string) $request->post('target_status', '');
        $reason = (string) $request->post('reason', '');

        try {
            InventoryUnit::transitionStatus($id, $targetStatus, $reason, (int) $user['id']);
            Session::flash('success', "Unit status updated to '{$targetStatus}' successfully.");
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::redirect("/inventory/units/{$id}");
    }
}
