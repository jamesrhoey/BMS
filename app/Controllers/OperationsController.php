<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SupplierDelivery;
use App\Services\AuthorizationService;
use App\Services\PurchaseRequestService;
use App\Services\ReceivingService;

final class OperationsController extends Controller
{
    /**
     * Dedicated Operations Incoming Projects & Purchase Request (PR) Dashboard.
     *
     * @param array<string, string> $params
     */
    public function dashboard(Request $request, array $params = []): void
    {
        $this->requireOperationsAccess($request);

        $opsDept = Department::findByCode(Department::CODE_OPERATIONS);
        if ($opsDept === null) {
            $this->notFound('Operations department configuration is missing.');
        }

        $stats = PurchaseRequest::operationsDashboardStats();

        $pageRaw = (string) $request->query('page', '1');
        $page = ctype_digit($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $filters = [
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'sort' => (string) $request->query('sort', 'received'),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
            'jom_number' => (string) $request->query('jom_number', ''),
            'project_id' => (string) $request->query('project_id', ''),
            'requested_by' => (string) $request->query('requested_by', ''),
        ];

        $pagination = PurchaseRequest::paginateOperationsProjects($filters, $page, 10);

        $this->render('operations/dashboard', [
            'pageTitle' => 'Operations Purchase Request Workspace',
            'activeNav' => 'ops_dashboard',
            'department' => $opsDept,
            'stats' => $stats,
            'projects' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
            'prConfig' => (array) Config::get('pm.pr', []),
        ]);
    }

    /**
     * Full Operations incoming project and Purchase Request registry.
     *
     * @param array<string, string> $params
     */
    public function projects(Request $request, array $params = []): void
    {
        $this->requireOperationsAccess($request);

        $opsDept = Department::findByCode(Department::CODE_OPERATIONS);
        if ($opsDept === null) {
            $this->notFound('Operations department configuration is missing.');
        }

        $pageRaw = (string) $request->query('page', '1');
        $page = ctype_digit($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $filters = [
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'sort' => (string) $request->query('sort', 'received'),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
            'jom_number' => (string) $request->query('jom_number', ''),
            'project_id' => (string) $request->query('project_id', ''),
            'requested_by' => (string) $request->query('requested_by', ''),
        ];

        $pagination = PurchaseRequest::paginateOperationsProjects($filters, $page, 12);

        $this->render('operations/projects', [
            'pageTitle' => 'Operations Projects & Purchase Requests',
            'activeNav' => 'ops_projects',
            'department' => $opsDept,
            'projects' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
            'prConfig' => (array) Config::get('pm.pr', []),
        ]);
    }

    /**
     * Save a Draft Purchase Request (or save & submit immediately to OPS Finance).
     *
     * @param array<string, string> $params
     */
    public function savePurchaseRequest(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];
        $existingPr = PurchaseRequest::findByProjectId($projectId);

        if (!AuthorizationService::canPreparePurchaseRequest($user, $project, $existingPr)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to create or update Purchase Request on project '{$project['project_code']}' without Operations authorization or after submission."
            );
        }

        $input = $request->allPost();
        $attachmentFile = $request->file('pr_attachment');
        $submitImmediately = ((string) ($input['submit_action'] ?? '')) === 'submit';

        $result = PurchaseRequestService::savePurchaseRequest(
            $project,
            $input,
            $attachmentFile,
            $user,
            $request,
            $submitImmediately
        );

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to save Purchase Request.');
            Session::flashOldInput($input);
            Response::redirect('/projects/' . $projectId . '#operations-pr-panel');
        }

        $prNumber = $result['pr_number'] ?? '';
        if ($submitImmediately) {
            Session::flash(
                'success',
                "Purchase Request '{$prNumber}' has been saved and officially submitted to OPS Finance for review and Actual BOM preparation."
            );
        } else {
            Session::flash(
                'success',
                "Draft Purchase Request '{$prNumber}' saved successfully. You may continue editing line items or submit it to OPS Finance when ready."
            );
        }

        Response::redirect('/projects/' . $projectId . '#operations-pr-panel');
    }

    /**
     * Submit an existing Draft/Returned Purchase Request to OPS Finance.
     *
     * @param array<string, string> $params
     */
    public function submitPurchaseRequest(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];
        $pr = PurchaseRequest::findByProjectId($projectId);

        if (!AuthorizationService::canSubmitPurchaseRequest($user, $project, $pr)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to submit Purchase Request on project '{$project['project_code']}' without authorization or when already submitted."
            );
        }

        $result = PurchaseRequestService::submitToOpsFinance($project, $pr, $user, $request);
        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to submit Purchase Request to OPS Finance.');
            Response::redirect('/projects/' . $projectId . '#operations-pr-panel');
        }

        $prNumber = $result['pr_number'] ?? ($pr['pr_number'] ?? '');
        Session::flash(
            'success',
            "Purchase Request '{$prNumber}' has been officially submitted and forwarded to OPS Finance."
        );
        Response::redirect('/projects/' . $projectId . '#operations-pr-panel');
    }

    /**
     * Record supplier delivery shipment against a PO.
     *
     * @param array<string, string> $params
     */
    public function recordDelivery(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];

        $poId = (int) ($params['poId'] ?? 0);
        $po = PurchaseOrder::findById($poId);
        if (!$po || (int) $po['project_id'] !== $projectId) {
            $this->notFound('Purchase Order not found for this project.');
        }

        if (!AuthorizationService::canReceiveSupplierDelivery($user, $project, $po)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to record supplier delivery for PO '{$po['po_number']}' without authorization."
            );
        }

        $result = ReceivingService::recordSupplierDelivery($project, $po, $request->allPost(), $user, $request);
        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to record supplier delivery.');
            Response::redirect('/projects/' . $projectId . '#supplier-deliveries-panel');
        }

        Session::flash(
            'success',
            "Supplier delivery (DR #{$request->post('delivery_receipt_no')}) has been recorded for PO '{$po['po_number']}'."
        );
        Response::redirect('/projects/' . $projectId . '#supplier-deliveries-panel');
    }

    /**
     * Execute Stock In transaction into inventory with serial numbers.
     *
     * @param array<string, string> $params
     */
    public function executeStockIn(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];

        $deliveryId = (int) ($params['deliveryId'] ?? 0);
        $delivery = SupplierDelivery::findById($deliveryId);
        if (!$delivery || (int) $delivery['project_id'] !== $projectId) {
            $this->notFound('Supplier delivery not found for this project.');
        }

        $po = PurchaseOrder::findById((int) $delivery['purchase_order_id']);
        if (!$po) {
            $this->notFound('Linked Purchase Order not found.');
        }

        if (!AuthorizationService::canExecuteStockIn($user, $project, $delivery)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to execute Stock In for delivery DR #{$delivery['delivery_receipt_no']} without authorization."
            );
        }

        $result = ReceivingService::executeStockIn($project, $po, $delivery, $request->allPost(), $user, $request);
        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to complete Stock In transaction.');
            Response::redirect('/projects/' . $projectId . '#inventory-stockin-panel');
        }

        Session::flash(
            'success',
            "Stock In COMPLETED (Txn #{$result['transaction_number']}). {$result['units_count']} unit(s) added to inventory."
        );
        Response::redirect('/projects/' . $projectId . '#inventory-stockin-panel');
    }

    /**
     * @return array<string, mixed>
     */
    private function requireOperationsAccess(Request $request): array
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessOperationsModule($user)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to access Operations route '{$request->path()}' without permission."
            );
        }

        return $user;
    }

    /**
     * @param array<string, string> $params
     * @return array<string, mixed>
     */
    private function loadProjectOrFail(array $params): array
    {
        $idRaw = (string) ($params['id'] ?? '');
        if (!ctype_digit($idRaw)) {
            $this->notFound('Invalid project identifier.');
        }

        $project = Project::findById((int) $idRaw);
        if ($project === null) {
            $this->notFound('Project not found.');
        }

        return $project;
    }
}
