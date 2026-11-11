<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\ActualBom;
use App\Models\Department;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Services\ActivityLogger;
use App\Services\ActualBomService;
use App\Services\AuthorizationService;
use App\Services\ProjectService;
use App\Services\PurchaseOrderService;

final class AdminFinanceController extends Controller
{
    /**
     * Dedicated Admin Finance Incoming Project & JOM Issuance Dashboard.
     *
     * @param array<string, string> $params
     */
    public function dashboard(Request $request, array $params = []): void
    {
        $user = $this->requireAdminFinanceAccess($request);

        $afinDept = Department::findByCode(Department::CODE_ADMIN_FINANCE);
        if ($afinDept === null) {
            $this->notFound('Admin Finance department configuration is missing.');
        }

        $afinDeptId = (int) $afinDept['id'];
        $stats = Project::adminFinanceDashboardStats($afinDeptId);

        $pageRaw = (string) $request->query('page', '1');
        $page = ctype_digit($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $filters = [
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'sort' => (string) $request->query('sort', 'forwarded'),
        ];

        $pagination = Project::paginateAdminFinanceProjects($afinDeptId, $filters, $page, 10);
        $incomingActualBoms = ActualBom::adminFinanceIncomingActualBoms(10);

        $this->render('admin_finance/dashboard', [
            'pageTitle' => 'Admin Finance JOM Issuance & Actual BOM Review Workspace',
            'activeNav' => 'afin_dashboard',
            'department' => $afinDept,
            'stats' => $stats,
            'projects' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
            'incomingActualBoms' => $incomingActualBoms,
            'jomConfig' => (array) Config::get('app.jom', []),
        ]);
    }

    /**
     * Full Admin Finance incoming project registry with search, status filters, and sorting.
     *
     * @param array<string, string> $params
     */
    public function projects(Request $request, array $params = []): void
    {
        $user = $this->requireAdminFinanceAccess($request);

        $afinDept = Department::findByCode(Department::CODE_ADMIN_FINANCE);
        if ($afinDept === null) {
            $this->notFound('Admin Finance department configuration is missing.');
        }

        $afinDeptId = (int) $afinDept['id'];
        $pageRaw = (string) $request->query('page', '1');
        $page = ctype_digit($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $filters = [
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'sort' => (string) $request->query('sort', 'forwarded'),
        ];

        $pagination = Project::paginateAdminFinanceProjects($afinDeptId, $filters, $page, 12);

        $this->render('admin_finance/projects', [
            'pageTitle' => 'Admin Finance Projects & JOM Issuance',
            'activeNav' => 'afin_projects',
            'department' => $afinDept,
            'projects' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
            'statuses' => [
                Project::STATUS_FORWARDED_TO_ADMIN_FINANCE,
                Project::STATUS_UNDER_ADMIN_FINANCE_REVIEW,
                Project::STATUS_JOM_ISSUED,
                Project::STATUS_FORWARDED_TO_OPERATIONS,
            ],
            'jomConfig' => (array) Config::get('app.jom', []),
        ]);
    }

    /**
     * Mark a PM-approved & forwarded project as Under Admin Finance Review.
     *
     * @param array<string, string> $params
     */
    public function startReview(Request $request, array $params = []): void
    {
        $user = $this->requireAdminFinanceAccess($request);
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canStartAdminFinanceReview($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to start Admin Finance review on project '{$project['project_code']}' without authorization."
            );
        }

        $result = ProjectService::startAdminFinanceReview($project, $user, $request);
        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to mark project as Under Admin Finance Review.');
            Response::redirect('/projects/' . (int) $project['id']);
        }

        Session::flash(
            'success',
            "Project '{$project['project_code']}' is now Under Admin Finance Review. You may proceed to issue the official JOM number."
        );
        Response::redirect('/projects/' . (int) $project['id'] . '#admin-finance-jom-panel');
    }

    /**
     * Authorize and issue official JOM (Job Order Memorandum) number for a PM-approved and forwarded project.
     * Admin Finance is the ONLY department responsible for issuing JOM numbers (along with Administrator full access).
     *
     * @param array<string, string> $params
     */
    public function issueJomNumber(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canIssueJomNumber($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to issue JOM number on project '{$project['project_code']}' without Admin Finance authorization or on an uneligible project."
            );
        }

        $result = ProjectService::issueJomNumber($project, $user, $request);

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to issue JOM number.');
            Response::redirect('/projects/' . (int) $project['id'] . '#admin-finance-jom-panel');
        }

        $jom = $result['jom_number'] ?? '';
        Session::flash(
            'success',
            "Job Order Memorandum (JOM) Number '{$jom}' has been officially issued by Admin Finance. You may now forward the JOM Number, SLD, and Marketing BOM to Operations."
        );
        Response::redirect('/projects/' . (int) $project['id'] . '#admin-finance-jom-panel');
    }

    /**
     * Forward JOM Number, SLD, and Marketing BOM to Operations after JOM issuance.
     *
     * @param array<string, string> $params
     */
    public function forwardToOperations(Request $request, array $params = []): void
    {
        $user = $this->requireAdminFinanceAccess($request);
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canForwardToOperations($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to forward project '{$project['project_code']}' to Operations without authorization or when already forwarded."
            );
        }

        $input = $request->allPost();
        $result = ProjectService::forwardToOperations($project, $input, $user, $request);

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to forward project and documents to Operations.');
            Response::redirect('/projects/' . (int) $project['id'] . '#admin-finance-jom-panel');
        }

        Session::flash(
            'success',
            "Project '{$project['project_code']}' (JOM: {$project['jom_number']}), SLD, and Marketing BOM have been officially forwarded to Operations."
        );
        Response::redirect('/projects/' . (int) $project['id'] . '#admin-finance-jom-panel');
    }

    /**
     * Receive, review, return for corrections, or accept an Actual BOM submitted by OPS Finance.
     *
     * @param array<string, string> $params
     */
    public function reviewActualBom(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];
        $pr = PurchaseRequest::findByProjectId($projectId);
        $actualBom = ActualBom::findByProjectId($projectId);

        if ($pr === null || $actualBom === null || !AuthorizationService::canReviewActualBom($user, $project, $actualBom)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to review or update Actual BOM status on project '{$project['project_code']}' without Admin Finance authorization."
            );
        }

        $input = $request->allPost();
        $decision = (string) ($input['decision'] ?? '');

        $result = ActualBomService::reviewActualBomByAdminFinance(
            $project,
            $pr,
            $actualBom,
            $decision,
            $input,
            $user,
            $request
        );

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to update Actual BOM status.');
            Response::redirect('/projects/' . $projectId . '#ops-finance-actual-bom-panel');
        }

        $newStatus = $result['new_status'] ?? '';
        $abNum = $actualBom['actual_bom_number'] ?? '';
        Session::flash(
            'success',
            "Actual BOM '{$abNum}' status updated to '{$newStatus}' by Admin Finance."
        );
        Response::redirect('/projects/' . $projectId . '#ops-finance-actual-bom-panel');
    }

    /**
     * Create a Purchase Order from an approved Purchase Request.
     *
     * @param array<string, string> $params
     */
    public function createPurchaseOrder(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];
        $pr = PurchaseRequest::findByProjectId($projectId);

        if (!AuthorizationService::canCreatePurchaseOrder($user, $project, $pr)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to create Purchase Order on project '{$project['project_code']}' without authorization."
            );
        }

        $result = PurchaseOrderService::createPurchaseOrder($project, $pr, $request->allPost(), $user, $request);
        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to create Purchase Order.');
            Response::redirect('/projects/' . $projectId . '#purchase-order-panel');
        }

        Session::flash(
            'success',
            "Purchase Order '{$result['po_number']}' has been successfully created and linked to PR '{$pr['pr_number']}'."
        );
        Response::redirect('/projects/' . $projectId . '#purchase-order-panel');
    }

    /**
     * Financially authorize and approve a Purchase Order.
     *
     * @param array<string, string> $params
     */
    public function authorizePurchaseOrder(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];

        $poId = (int) ($params['poId'] ?? 0);
        $po = PurchaseOrder::findById($poId);
        if (!$po || (int) $po['project_id'] !== $projectId) {
            $this->notFound('Purchase Order not found for this project.');
        }

        if (!AuthorizationService::canAuthorizePurchaseOrder($user, $project, $po)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to authorize Purchase Order '{$po['po_number']}' without authorization."
            );
        }

        $notes = (string) $request->post('authorization_notes', '');
        $result = PurchaseOrderService::authorizePurchaseOrder($project, $po, $notes, $user, $request);
        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to authorize Purchase Order.');
            Response::redirect('/projects/' . $projectId . '#purchase-order-panel');
        }

        Session::flash(
            'success',
            "Purchase Order '{$po['po_number']}' has been officially APPROVED and financially authorized."
        );
        Response::redirect('/projects/' . $projectId . '#purchase-order-panel');
    }

    /**
     * Officially issue approved PO to external supplier.
     *
     * @param array<string, string> $params
     */
    public function issuePurchaseOrder(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];

        $poId = (int) ($params['poId'] ?? 0);
        $po = PurchaseOrder::findById($poId);
        if (!$po || (int) $po['project_id'] !== $projectId) {
            $this->notFound('Purchase Order not found for this project.');
        }

        if (!AuthorizationService::canIssuePurchaseOrder($user, $project, $po)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to issue Purchase Order '{$po['po_number']}' to supplier without authorization."
            );
        }

        $result = PurchaseOrderService::issuePurchaseOrderToSupplier($project, $po, $user, $request);
        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to issue Purchase Order.');
            Response::redirect('/projects/' . $projectId . '#purchase-order-panel');
        }

        Session::flash(
            'success',
            "Purchase Order '{$po['po_number']}' has been officially ISSUED to supplier '{$po['supplier_name']}'."
        );
        Response::redirect('/projects/' . $projectId . '#purchase-order-panel');
    }

    /**
     * Add milestone Terms of Payment to Purchase Order.
     *
     * @param array<string, string> $params
     */
    public function addPaymentTerm(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];

        $poId = (int) ($params['poId'] ?? 0);
        $po = PurchaseOrder::findById($poId);
        if (!$po || (int) $po['project_id'] !== $projectId) {
            $this->notFound('Purchase Order not found for this project.');
        }

        if (!AuthorizationService::isAdmin($user) && !AuthorizationService::isAdminFinanceUser($user)) {
            $this->denyAccess($request, 'Unauthorized to configure payment terms.');
        }

        $termName = trim((string) ($request->post('payment_term') ?? $request->post('term_name') ?? ''));
        $amount = (float) ($request->post('term_amount') ?? $request->post('amount') ?? 0.0);
        $percent = (float) ($request->post('term_percentage') ?? $request->post('percentage') ?? 0.0);
        $dueDate = trim((string) ($request->post('term_due_date') ?? $request->post('due_date') ?? ''));
        $remarks = trim((string) ($request->post('term_remarks') ?? $request->post('remarks') ?? ''));

        if ($termName === '' || ($amount <= 0 && $percent <= 0)) {
            Session::flash('error', 'Please provide a valid payment term name and amount or percentage.');
            Response::redirect('/projects/' . $projectId . '#purchase-order-panel');
        }

        if ($amount <= 0 && $percent > 0) {
            $amount = round(((float) $po['total_amount']) * ($percent / 100), 2);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO payment_terms_schedules (
                purchase_order_id,
                payment_term,
                percentage,
                amount,
                due_date,
                status,
                remarks
            ) VALUES (
                :po_id,
                :payment_term,
                :percentage,
                :amount,
                :due_date,
                "Scheduled",
                :remarks
            )'
        );
        $stmt->execute([
            'po_id' => $poId,
            'payment_term' => $termName,
            'percentage' => $percent,
            'amount' => $amount,
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'remarks' => $remarks !== '' ? $remarks : null,
        ]);

        ActivityLogger::log(
            'po.payment_term_added',
            'project',
            $projectId,
            "Admin Finance user '{$user['full_name']}' added milestone payment term '{$termName}' to PO '{$po['po_number']}'.",
            $request,
            (int) $user['id'],
            false
        );

        Session::flash('success', "Payment term '{$termName}' added to Purchase Order '{$po['po_number']}'.");
        Response::redirect('/projects/' . $projectId . '#purchase-order-panel');
    }

    /**
     * @return array<string, mixed>
     */
    private function requireAdminFinanceAccess(Request $request): array
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessAdminFinanceModule($user)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to access Admin Finance route '{$request->path()}' without permission."
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
