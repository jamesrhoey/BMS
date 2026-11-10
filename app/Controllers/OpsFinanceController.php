<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\ActualBom;
use App\Models\Department;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Services\ActualBomService;
use App\Services\AuthorizationService;
use App\Services\PurchaseRequestService;

final class OpsFinanceController extends Controller
{
    /**
     * Dedicated OPS Finance Purchase Request Review & Actual BOM Upload Dashboard.
     *
     * @param array<string, string> $params
     */
    public function dashboard(Request $request, array $params = []): void
    {
        $this->requireOpsFinanceAccess($request);

        $opfinDept = Department::findByCode(Department::CODE_OPERATION_FINANCE);
        if ($opfinDept === null) {
            $this->notFound('OPS Finance department configuration is missing.');
        }

        $stats = ActualBom::opsFinanceDashboardStats();

        $pageRaw = (string) $request->query('page', '1');
        $page = ctype_digit($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $filters = [
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'sort' => (string) $request->query('sort', 'submitted'),
        ];

        $pagination = ActualBom::paginateOpsFinanceQueue($filters, $page, 10);

        $this->render('ops_finance/dashboard', [
            'pageTitle' => 'OPS Finance PR Review & Actual BOM Workspace',
            'activeNav' => 'opfin_dashboard',
            'department' => $opfinDept,
            'stats' => $stats,
            'queue' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
            'actualBomConfig' => (array) Config::get('pm.actual_bom', []),
        ]);
    }

    /**
     * Full OPS Finance Incoming Purchase Requests & Actual BOM queue.
     *
     * @param array<string, string> $params
     */
    public function projects(Request $request, array $params = []): void
    {
        $this->requireOpsFinanceAccess($request);

        $opfinDept = Department::findByCode(Department::CODE_OPERATION_FINANCE);
        if ($opfinDept === null) {
            $this->notFound('OPS Finance department configuration is missing.');
        }

        $pageRaw = (string) $request->query('page', '1');
        $page = ctype_digit($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $filters = [
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'sort' => (string) $request->query('sort', 'submitted'),
        ];

        $pagination = ActualBom::paginateOpsFinanceQueue($filters, $page, 12);

        $this->render('ops_finance/projects', [
            'pageTitle' => 'OPS Finance Purchase Requests & Actual BOM Registry',
            'activeNav' => 'opfin_projects',
            'department' => $opfinDept,
            'queue' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
            'actualBomConfig' => (array) Config::get('pm.actual_bom', []),
        ]);
    }

    /**
     * Mark an incoming Purchase Request as Under OPS Finance Review.
     *
     * @param array<string, string> $params
     */
    public function reviewPurchaseRequest(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];
        $pr = PurchaseRequest::findByProjectId($projectId);

        if (!AuthorizationService::canReviewPurchaseRequest($user, $project, $pr)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to review Purchase Request on project '{$project['project_code']}' without OPS Finance authorization."
            );
        }

        $result = PurchaseRequestService::startOpsFinanceReview($project, $pr, $user, $request);
        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to mark Purchase Request as Under Review.');
            Response::redirect('/projects/' . $projectId . '#operations-pr-panel');
        }

        Session::flash(
            'success',
            "Purchase Request '{$pr['pr_number']}' is now Under OPS Finance Review. Proceed to prepare and upload the Actual BOM with specifications."
        );
        Response::redirect('/projects/' . $projectId . '#ops-finance-actual-bom-panel');
    }

    /**
     * Return a submitted Purchase Request to Operations for corrections.
     *
     * @param array<string, string> $params
     */
    public function returnPurchaseRequest(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];
        $pr = PurchaseRequest::findByProjectId($projectId);

        if (!AuthorizationService::canReviewPurchaseRequest($user, $project, $pr)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to return Purchase Request on project '{$project['project_code']}' without OPS Finance authorization."
            );
        }

        $reason = (string) $request->post('return_reason', '');
        $result = PurchaseRequestService::returnPrForCorrections($project, $pr, $reason, $user, $request);
        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to return Purchase Request to Operations.');
            Response::redirect('/projects/' . $projectId . '#operations-pr-panel');
        }

        Session::flash(
            'success',
            "Purchase Request '{$pr['pr_number']}' has been returned to Operations for corrections."
        );
        Response::redirect('/projects/' . $projectId . '#operations-pr-panel');
    }

    /**
     * Formally approve a submitted Purchase Request.
     *
     * @param array<string, string> $params
     */
    public function approvePurchaseRequest(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];
        $pr = PurchaseRequest::findByProjectId($projectId);

        if (!AuthorizationService::canApprovePurchaseRequest($user, $project, $pr)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to approve Purchase Request on project '{$project['project_code']}' without authorization."
            );
        }

        $notes = (string) $request->post('approval_notes', '');
        $result = PurchaseRequestService::approvePurchaseRequest($project, $pr, $notes, $user, $request);
        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to approve Purchase Request.');
            Response::redirect('/projects/' . $projectId . '#operations-pr-panel');
        }

        Session::flash(
            'success',
            "Purchase Request '{$pr['pr_number']}' has been officially APPROVED. It is now eligible for Purchase Order (PO) creation."
        );
        Response::redirect('/projects/' . $projectId . '#operations-pr-panel');
    }

    /**
     * Formally reject a submitted Purchase Request with documented reason.
     *
     * @param array<string, string> $params
     */
    public function rejectPurchaseRequest(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];
        $pr = PurchaseRequest::findByProjectId($projectId);

        if (!AuthorizationService::canRejectPurchaseRequest($user, $project, $pr)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to reject Purchase Request on project '{$project['project_code']}' without authorization."
            );
        }

        $reason = (string) $request->post('rejection_reason', '');
        $result = PurchaseRequestService::rejectPurchaseRequest($project, $pr, $reason, $user, $request);
        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to reject Purchase Request.');
            Response::redirect('/projects/' . $projectId . '#operations-pr-panel');
        }

        Session::flash(
            'success',
            "Purchase Request '{$pr['pr_number']}' has been REJECTED."
        );
        Response::redirect('/projects/' . $projectId . '#operations-pr-panel');
    }

    /**
     * Upload/save the Actual BOM with specifications (or save & submit immediately to Admin Finance).
     * Never overwrites or modifies the original Marketing BOM.
     *
     * @param array<string, string> $params
     */
    public function saveActualBom(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];
        $pr = PurchaseRequest::findByProjectId($projectId);
        $existingActualBom = $pr !== null ? ActualBom::findByPrId((int) $pr['id']) : null;

        if (!AuthorizationService::canUploadActualBom($user, $project, $pr, $existingActualBom)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to upload or modify Actual BOM on project '{$project['project_code']}' without OPS Finance authorization."
            );
        }

        $input = $request->allPost();
        $actualBomFile = $request->file('actual_bom_file');
        $supportingFile = $request->file('supporting_file');
        $submitImmediately = ((string) ($input['submit_action'] ?? '')) === 'submit';

        $result = ActualBomService::saveActualBom(
            $project,
            $pr,
            $input,
            $actualBomFile,
            $supportingFile,
            $user,
            $request,
            $submitImmediately
        );

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to upload or save Actual BOM.');
            Session::flashOldInput($input);
            Response::redirect('/projects/' . $projectId . '#ops-finance-actual-bom-panel');
        }

        $abNumber = $result['actual_bom_number'] ?? '';
        if ($submitImmediately) {
            Session::flash(
                'success',
                "Actual BOM '{$abNumber}' with actual equipment/material specifications has been uploaded and officially submitted to Admin Finance for review."
            );
        } else {
            Session::flash(
                'success',
                "Actual BOM '{$abNumber}' saved with actual specifications. Submit it to Admin Finance when ready."
            );
        }

        Response::redirect('/projects/' . $projectId . '#ops-finance-actual-bom-panel');
    }

    /**
     * Submit an existing Draft/Returned Actual BOM to Admin Finance.
     *
     * @param array<string, string> $params
     */
    public function submitActualBom(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);
        $projectId = (int) $project['id'];
        $pr = PurchaseRequest::findByProjectId($projectId);
        $actualBom = $pr !== null ? ActualBom::findByPrId((int) $pr['id']) : null;

        if (!AuthorizationService::canSubmitActualBom($user, $project, $pr, $actualBom)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to submit Actual BOM on project '{$project['project_code']}' without OPS Finance authorization."
            );
        }

        $result = ActualBomService::submitToAdminFinance($project, $pr, $actualBom, $user, $request);
        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to submit Actual BOM to Admin Finance.');
            Response::redirect('/projects/' . $projectId . '#ops-finance-actual-bom-panel');
        }

        $abNumber = $result['actual_bom_number'] ?? ($actualBom['actual_bom_number'] ?? '');
        Session::flash(
            'success',
            "Actual BOM '{$abNumber}' with actual specifications has been officially submitted and forwarded to Admin Finance."
        );
        Response::redirect('/projects/' . $projectId . '#ops-finance-actual-bom-panel');
    }

    /**
     * @return array<string, mixed>
     */
    private function requireOpsFinanceAccess(Request $request): array
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessOpsFinanceModule($user)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to access OPS Finance route '{$request->path()}' without permission."
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
