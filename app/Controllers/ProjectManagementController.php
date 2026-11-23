<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Services\AuthorizationService;
use App\Services\ProjectService;

final class ProjectManagementController extends Controller
{
    /**
     * Dedicated Project Management Dashboard with live database metrics and verification queue.
     *
     * @param array<string, string> $params
     */
    public function dashboard(Request $request, array $params = []): void
    {
        $user = $this->requirePmAccess($request);

        $pmDept = Department::findByCode(Department::CODE_PROJECT_MANAGEMENT);
        if ($pmDept === null) {
            $this->notFound('Project Management department configuration is missing.');
        }

        $pmDeptId = (int) $pmDept['id'];
        $category = (string) $request->query('category', '');

        // pmDashboardStats expects a string; an omitted category is represented
        // by the empty string so the unfiltered dashboard remains valid.
        $stats = Project::pmDashboardStats($pmDeptId, $category);

        $pageRaw = (string) $request->query('page', '1');
        $page = ctype_digit($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $filters = [
            'q'         => (string) $request->query('q', ''),
            'status'    => (string) $request->query('status', 'incoming'),
            'category'  => $category,
            'date_from' => (string) $request->query('date_from', ''),
            'date_to'   => (string) $request->query('date_to', ''),
            'sort'      => (string) $request->query('sort', 'submitted'),
        ];

        $pagination = Project::paginatePmProjects($pmDeptId, $filters, $page, 10);

        $this->render('pm/dashboard', [
            'pageTitle'  => 'Project Management Workspace',
            'activeNav'  => 'pm_dashboard',
            'department' => $pmDept,
            'stats'      => $stats,
            'projects'   => $pagination['items'],
            'pagination' => $pagination,
            'filters'    => $filters,
        ]);
    }

    /**
     * Full Project Management verification registry with search, status filters, and sorting.
     *
     * @param array<string, string> $params
     */
    public function projects(Request $request, array $params = []): void
    {
        $user = $this->requirePmAccess($request);

        $pmDept = Department::findByCode(Department::CODE_PROJECT_MANAGEMENT);
        if ($pmDept === null) {
            $this->notFound('Project Management department configuration is missing.');
        }

        $pmDeptId = (int) $pmDept['id'];
        $pageRaw = (string) $request->query('page', '1');
        $page = ctype_digit($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $filters = [
            'q'         => (string) $request->query('q', ''),
            'status'    => (string) $request->query('status', ''),
            'category'  => (string) $request->query('category', ''),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to'   => (string) $request->query('date_to', ''),
            'sort'      => (string) $request->query('sort', 'newest'),
        ];

        $pagination = Project::paginatePmProjects($pmDeptId, $filters, $page, 12);

        $this->render('pm/projects', [
            'pageTitle'  => 'PM Project Verification List',
            'activeNav'  => 'pm_projects',
            'department' => $pmDept,
            'projects'   => $pagination['items'],
            'pagination' => $pagination,
            'filters'    => $filters,
            'statuses'   => Project::availableStatuses(),
        ]);
    }

    /**
     * Per-document verification page for a single project.
     *
     * @param array<string, string> $params
     */
    public function verification(Request $request, array $params = []): void
    {
        $user    = $this->requirePmAccess($request);
        $project = $this->loadProjectOrFail($params);

        $projectId     = (int) $project['id'];
        $documents     = ProjectDocument::currentForProject($projectId);
        $allVersions   = ProjectDocument::forProject($projectId);
        $canVerify     = AuthorizationService::canVerifyDocument($user, $project);
        $canApprove    = AuthorizationService::canCompleteVerification($user, $project);
        $canReturn     = AuthorizationService::canReturnProjectToMarketing($user, $project);

        $this->render('pm/verification', [
            'pageTitle'   => 'Document Verification — ' . $project['project_code'],
            'activeNav'   => 'pm_projects',
            'project'     => $project,
            'documents'   => $documents,
            'allVersions' => $allVersions,
            'canVerify'   => $canVerify,
            'canApprove'  => $canApprove,
            'canReturn'   => $canReturn,
            'user'        => $user,
        ]);
    }

    /**
     * Mark a single document as verified or needs_correction.
     *
     * @param array<string, string> $params
     */
    public function verifyDocument(Request $request, array $params = []): void
    {
        $user    = $this->requirePmAccess($request);
        $project = $this->loadProjectOrFail($params);

        $docIdRaw = (string) $request->post('document_id', '');
        if (!ctype_digit($docIdRaw)) {
            Session::flash('error', 'Invalid document identifier.');
            Response::redirect('/pm/projects/' . (int) $project['id'] . '/verification');
        }

        $status = (string) $request->post('review_status', '');
        $notes  = (string) $request->post('correction_notes', '');

        $result = ProjectService::verifyDocument($project, (int) $docIdRaw, $status, $notes, $user, $request);

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Document verification failed.');
        } else {
            $label = $status === 'verified' ? 'marked as Verified' : 'flagged as Needs Correction';
            Session::flash('success', "Document has been {$label}.");
        }

        Response::redirect('/pm/projects/' . (int) $project['id'] . '/verification');
    }

    /**
     * Upload the JOM file for an approved project.
     *
     * @param array<string, string> $params
     */
    public function uploadJom(Request $request, array $params = []): void
    {
        $user    = $this->requirePmAccess($request);
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canUploadJom($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to upload JOM on project '{$project['project_code']}' without authorization."
            );
        }

        $result = ProjectService::uploadJomFile($project, $user, $request);

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'JOM file upload failed.');
            Response::redirect('/projects/' . (int) $project['id'] . '#pm-review-panel');
        }

        $replaced = !empty($result['replaced']);
        Session::flash(
            'success',
            $replaced
                ? "JOM file has been replaced successfully. The project status has been updated to 'JOM Uploaded'."
                : "JOM file has been uploaded successfully. The project status is now 'JOM Uploaded'. You may now forward the project to Operations."
        );
        Response::redirect('/projects/' . (int) $project['id'] . '#pm-review-panel');
    }

    /**
     * Confirmation page before forwarding a project to Operations.
     *
     * @param array<string, string> $params
     */
    public function showForwardOps(Request $request, array $params = []): void
    {
        $user    = $this->requirePmAccess($request);
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canPmForwardToOperations($user, $project)) {
            Session::flash('error', 'This project cannot be forwarded to Operations yet. Ensure the JOM file is uploaded and all required documents are present.');
            Response::redirect('/projects/' . (int) $project['id']);
        }

        $projectId  = (int) $project['id'];
        $category   = strtolower(trim((string) ($project['project_category'] ?? $project['workflow_type'] ?? 'mrc')));
        $pmConfig   = Config::get('pm');
        $handoffCfg = $category === 'special_project'
            ? ($pmConfig['ops_handoff']['special_project'] ?? [])
            : ($pmConfig['ops_handoff']['mrc'] ?? []);

        $requiredTypes = $handoffCfg['required_documents'] ?? ['JOM', 'SON', 'BOM', 'SLD'];
        $handoffDocs   = [];
        $missingDocs   = [];

        foreach ($requiredTypes as $type) {
            if ($type === 'JOM') {
                $doc = ProjectDocument::findCurrentJom($projectId);
            } else {
                $doc = ProjectDocument::findCurrentByType($projectId, $type);
            }
            if ($doc !== null) {
                $handoffDocs[$type] = $doc;
            } else {
                $missingDocs[] = $type;
            }
        }

        $this->render('pm/forward_ops', [
            'pageTitle'     => 'Forward to Operations — ' . $project['project_code'],
            'activeNav'     => 'pm_projects',
            'project'       => $project,
            'handoffDocs'   => $handoffDocs,
            'missingDocs'   => $missingDocs,
            'requiredTypes' => $requiredTypes,
            'handoffLabels' => $handoffCfg['labels'] ?? [],
            'category'      => $category,
        ]);
    }

    /**
     * Execute the PM → Operations forwarding.
     *
     * @param array<string, string> $params
     */
    public function forwardToOps(Request $request, array $params = []): void
    {
        $user    = $this->requirePmAccess($request);
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canPmForwardToOperations($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to forward project '{$project['project_code']}' to Operations without authorization."
            );
        }

        $input  = $request->allPost();
        $result = ProjectService::pmForwardToOperations($project, $input, $user, $request);

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to forward project to Operations.');
            Response::redirect('/pm/projects/' . (int) $project['id'] . '/forward-ops');
        }

        Session::flash(
            'success',
            "Project '{$project['project_code']}' has been successfully forwarded to Operations with the category-specific document package. It is now visible in the Operations incoming queue."
        );
        Response::redirect('/projects/' . (int) $project['id']);
    }

    /**
     * Verification history — all projects PM has reviewed.
     *
     * @param array<string, string> $params
     */
    public function verificationHistory(Request $request, array $params = []): void
    {
        $user = $this->requirePmAccess($request);

        $pmDept = Department::findByCode(Department::CODE_PROJECT_MANAGEMENT);
        if ($pmDept === null) {
            $this->notFound('Project Management department configuration is missing.');
        }

        $pmDeptId = (int) $pmDept['id'];
        $pageRaw  = (string) $request->query('page', '1');
        $page     = ctype_digit($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $filters = [
            'q'         => (string) $request->query('q', ''),
            'status'    => 'history',
            'category'  => (string) $request->query('category', ''),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to'   => (string) $request->query('date_to', ''),
            'sort'      => (string) $request->query('sort', 'newest'),
        ];

        $pagination = Project::paginatePmProjects($pmDeptId, $filters, $page, 15);

        $this->render('pm/verification_history', [
            'pageTitle'  => 'PM Verification History',
            'activeNav'  => 'pm_verification_history',
            'department' => $pmDept,
            'projects'   => $pagination['items'],
            'pagination' => $pagination,
            'filters'    => $filters,
        ]);
    }

    /**
     * Mark a submitted project as Under Review by Project Management.
     *
     * @param array<string, string> $params
     */
    public function startReview(Request $request, array $params = []): void
    {
        $user = $this->requirePmAccess($request);
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canStartReview($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to start review on project '{$project['project_code']}' without authorization."
            );
        }

        $result = ProjectService::startReview($project, $user, $request);
        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to mark project as Under Review.');
            Response::redirect('/projects/' . (int) $project['id']);
        }

        Session::flash('success', "Project '{$project['project_code']}' is now Under Review. Inspect the submitted document checklist below.");
        Response::redirect('/projects/' . (int) $project['id'] . '#pm-review-panel');
    }

    /**
     * Complete and approve document verification stage for a project.
     * Enforces complete presence of all required documents (6/6).
     *
     * @param array<string, string> $params
     */
    public function completeVerification(Request $request, array $params = []): void
    {
        $user = $this->requirePmAccess($request);
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canCompleteVerification($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to complete document verification on '{$project['project_code']}' without authorization."
            );
        }

        $input = $request->allPost();
        $result = ProjectService::completeVerification($project, $input, $user, $request);

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Document verification could not be completed.');
            Response::redirect('/projects/' . (int) $project['id'] . '#pm-review-panel');
        }

        Session::flash(
            'success',
            "Project '{$project['project_code']}' has been Approved by Project Management. All required documents have been verified. You may now upload the JOM file and forward the category-specific package to Operations."
        );
        Response::redirect('/projects/' . (int) $project['id']);
    }

    /**
     * Forward an approved Project Management project to Admin Finance.
     * Routes the project into the Admin Finance incoming queue and prevents duplicate forwarding.
     *
     * @param array<string, string> $params
     */
    public function forwardToAdminFinance(Request $request, array $params = []): void
    {
        $user = $this->requirePmAccess($request);
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canForwardToAdminFinance($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to forward project '{$project['project_code']}' to Admin Finance without authorization or when already forwarded."
            );
        }

        $input = $request->allPost();
        $result = ProjectService::forwardToAdminFinance($project, $input, $user, $request);

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to forward project to Admin Finance.');
            Response::redirect('/projects/' . (int) $project['id'] . '#pm-review-panel');
        }

        Session::flash(
            'success',
            "Project '{$project['project_code']}' has been forwarded to Admin Finance. It is now visible in the Admin Finance incoming project queue for JOM number issuance."
        );
        Response::redirect('/projects/' . (int) $project['id']);
    }

    /**
     * Authorize and issue official JOM (Job Order Memorandum) number for a forwarded project.
     * Under the new company workflow, Admin Finance (and Administrators) issue JOM numbers —
     * Project Management users are strictly blocked from issuing JOM numbers.
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
                "User '{$user['username']}' attempted to issue JOM number on project '{$project['project_code']}' without Admin Finance authorization."
            );
        }

        $result = ProjectService::issueJomNumber($project, $user, $request);

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to issue JOM number.');
            Response::redirect('/projects/' . (int) $project['id']);
        }

        $jom = $result['jom_number'] ?? '';
        Session::flash(
            'success',
            "Job Order Memorandum (JOM) Number '{$jom}' has been successfully issued by Admin Finance."
        );
        Response::redirect('/projects/' . (int) $project['id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function requirePmAccess(Request $request): array
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessProjectManagementModule($user)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to access Project Management route '{$request->path()}' without permission."
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

