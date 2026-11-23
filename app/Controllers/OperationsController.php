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
use App\Models\ProjectDocument;
use App\Models\ProjectWorkflowHistory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SupplierDelivery;
use App\Services\AuthorizationService;
use App\Services\MarketingChecklistService;
use App\Services\OperationsService;
use App\Services\PurchaseRequestService;
use App\Services\ReceivingService;

final class OperationsController extends Controller
{
    /**
     * Dedicated Operations Department Dashboard.
     * Displays live metrics for projects received from PM, processing status, and OPS Finance submissions.
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

        $category = (string) $request->query('category', '');
        $stats = Project::opsDashboardStats((int) $opsDept['id'], $category);

        $pageRaw = (string) $request->query('page', '1');
        $page = ctype_digit($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $filters = [
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'category' => $category,
            'sort' => (string) $request->query('sort', 'received'),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];

        $pagination = Project::paginateOpsProjects((int) $opsDept['id'], $filters, $page, 10);

        // Also load legacy PR stats for tabbed/sub-views if needed
        $prStats = PurchaseRequest::operationsDashboardStats();

        $this->render('operations/dashboard', [
            'pageTitle' => 'Operations Department Dashboard',
            'activeNav' => 'ops_dashboard',
            'department' => $opsDept,
            'stats' => $stats,
            'prStats' => $prStats,
            'projects' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
        ]);
    }

    /**
     * Full Operations incoming project registry.
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
            'category' => (string) $request->query('category', ''),
            'sort' => (string) $request->query('sort', 'received'),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];

        $pagination = Project::paginateOpsProjects((int) $opsDept['id'], $filters, $page, 12);

        $this->render('operations/projects', [
            'pageTitle' => 'Operations Projects Registry',
            'activeNav' => 'ops_projects',
            'department' => $opsDept,
            'projects' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
        ]);
    }

    /**
     * Dedicated Operations Project Details & Document Handoff workspace.
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params = []): void
    {
        $user = $this->requireOperationsAccess($request);
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];

        if (!Project::isForwardedToOperations($project)) {
            Session::flash('error', "Project '{$project['project_code']}' has not been forwarded to Operations yet.");
            Response::redirect('/operations/projects');
            return;
        }

        // Determine category & handoff documents from PM
        $category = MarketingChecklistService::normalizeCategory(
            (string) ($project['project_category'] ?? $project['workflow_type'] ?? 'mrc')
        );

        $handoffDocs = [
            'JOM' => ProjectDocument::findCurrentJom($projectId),
            'SON' => ProjectDocument::findCurrentByType($projectId, 'SON'),
            'BOM' => ProjectDocument::findCurrentByType($projectId, 'BOM'),
            'SLD' => ProjectDocument::findCurrentByType($projectId, 'SLD'),
        ];
        if ($category === 'special_project') {
            $handoffDocs['PO'] = ProjectDocument::findCurrentByType($projectId, 'PO')
                ?? ProjectDocument::findCurrentByType($projectId, 'Marketing PO')
                ?? ProjectDocument::findCurrentByType($projectId, 'PO — Purchase Order')
                ?? ProjectDocument::findCurrentByType($projectId, 'Purchase Order');
        }

        // OPS-uploaded documents (Final SLD, Final BOM, Request of PR)
        $opsDocs = ProjectDocument::opsUploadedDocuments($projectId);
        $opsConfig = (array) Config::get('operations.required_documents', []);

        $canUpload = AuthorizationService::canUploadOpsDocument($user, $project);
        $canSubmit = AuthorizationService::canSubmitToOpsFinance($user, $project);
        $isSubmitted = Project::isSubmittedToOpsFinance($project);

        $workflowHistory = ProjectWorkflowHistory::forProject($projectId);

        $this->render('operations/show', [
            'pageTitle' => "OPS Project Details — {$project['project_code']}",
            'activeNav' => 'ops_projects',
            'project' => $project,
            'category' => $category,
            'handoffDocs' => $handoffDocs,
            'opsDocs' => $opsDocs,
            'opsConfig' => $opsConfig,
            'canUpload' => $canUpload,
            'canSubmit' => $canSubmit,
            'isSubmitted' => $isSubmitted,
            'workflowHistory' => $workflowHistory,
        ]);
    }

    /**
     * Upload an OPS document (Final SLD, Final BOM, Request of PR).
     *
     * @param array<string, string> $params
     */
    public function uploadDocument(Request $request, array $params = []): void
    {
        $user = $this->requireOperationsAccess($request);
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];

        $docType = trim((string) $request->post('doc_type', ''));
        $file = $request->file('ops_document');
        $notes = trim((string) $request->post('upload_notes', ''));

        $result = OperationsService::uploadOpsDocument(
            $project,
            $docType,
            $file,
            $user,
            $request,
            $notes
        );

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Failed to upload document.');
        } else {
            $docName = $result['doc_name'] ?? $docType;
            $vNum = $result['version_number'] ?? 1;
            Session::flash('success', "{$docName} (v{$vNum}) uploaded successfully.");
        }

        Response::redirect('/operations/projects/' . $projectId);
    }

    /**
     * Show confirmation page for submitting project to OPS Finance.
     *
     * @param array<string, string> $params
     */
    public function showSubmitOpfin(Request $request, array $params = []): void
    {
        $user = $this->requireOperationsAccess($request);
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];

        if (!AuthorizationService::canSubmitToOpsFinance($user, $project)) {
            Session::flash('error', 'This project cannot be submitted to OPS Finance. Please make sure all 3 required Operations documents are uploaded.');
            Response::redirect('/operations/projects/' . $projectId);
            return;
        }

        $opsDocs = ProjectDocument::opsUploadedDocuments($projectId);
        $opsConfig = (array) Config::get('operations.required_documents', []);

        $this->render('operations/submit_opfin', [
            'pageTitle' => "Submit Project to OPS Finance — {$project['project_code']}",
            'activeNav' => 'ops_projects',
            'project' => $project,
            'opsDocs' => $opsDocs,
            'opsConfig' => $opsConfig,
        ]);
    }

    /**
     * Process submission of project to OPS Finance.
     *
     * @param array<string, string> $params
     */
    public function submitToOpfin(Request $request, array $params = []): void
    {
        $user = $this->requireOperationsAccess($request);
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];

        $result = OperationsService::submitToOpsFinance(
            $project,
            $user,
            $request,
            $request->allPost()
        );

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Failed to submit project to OPS Finance.');
            Response::redirect('/operations/projects/' . $projectId . '/submit-opfin');
            return;
        }

        Session::flash('success', "Project '{$project['project_code']}' has been officially submitted to OPS Finance with Final SLD, Final BOM, and Request of PR.");
        Response::redirect('/operations/projects/' . $projectId);
    }

    /**
     * View history of projects processed by Operations.
     *
     * @param array<string, string> $params
     */
    public function history(Request $request, array $params = []): void
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
            'status' => 'history',
            'category' => (string) $request->query('category', ''),
            'sort' => (string) $request->query('sort', 'received'),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];

        $pagination = Project::paginateOpsProjects((int) $opsDept['id'], $filters, $page, 15);

        $this->render('operations/history', [
            'pageTitle' => 'Operations Project History',
            'activeNav' => 'ops_history',
            'department' => $opsDept,
            'projects' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
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
