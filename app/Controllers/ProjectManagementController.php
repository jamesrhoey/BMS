<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Department;
use App\Models\Project;
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
        $stats = Project::pmDashboardStats($pmDeptId);

        $pageRaw = (string) $request->query('page', '1');
        $page = ctype_digit($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $filters = [
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'sort' => (string) $request->query('sort', 'submitted'),
        ];

        $pagination = Project::paginatePmProjects($pmDeptId, $filters, $page, 10);

        $this->render('pm/dashboard', [
            'pageTitle' => 'Project Management Workspace',
            'activeNav' => 'pm_dashboard',
            'department' => $pmDept,
            'stats' => $stats,
            'projects' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
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
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'sort' => (string) $request->query('sort', 'newest'),
        ];

        $pagination = Project::paginatePmProjects($pmDeptId, $filters, $page, 12);

        $this->render('pm/projects', [
            'pageTitle' => 'PM Project Verification List',
            'activeNav' => 'pm_projects',
            'department' => $pmDept,
            'projects' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
            'statuses' => Project::availableStatuses(),
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
            "Document verification completed for project '{$project['project_code']}'. All required documents have been verified. The project is now eligible for JOM number issuance."
        );
        Response::redirect('/projects/' . (int) $project['id']);
    }

    /**
     * Authorize and issue official JOM (Job Order Memorandum) number for a verified project.
     * Available only to authorized Project Management users and Administrators.
     *
     * @param array<string, string> $params
     */
    public function issueJomNumber(Request $request, array $params = []): void
    {
        $user = $this->requirePmAccess($request);
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canIssueJomNumber($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to issue JOM number on project '{$project['project_code']}' without authorization or prior verification."
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
            "Job Order Memorandum (JOM) Number '{$jom}' has been successfully issued by Project Management. The project is now authorized for downstream departmental execution."
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

