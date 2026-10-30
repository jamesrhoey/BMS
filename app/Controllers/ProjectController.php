<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectWorkflowHistory;
use App\Services\AuthorizationService;
use App\Services\MarketingChecklistService;
use App\Services\ProjectService;

final class ProjectController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): void
    {
        $user = $this->requireUser();

        $filters = [
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'department_id' => (string) $request->query('department_id', ''),
        ];

        $projects = Project::listForUser($user, $filters);

        $this->render('projects/index', [
            'pageTitle' => 'Projects Registry',
            'activeNav' => 'projects',
            'projects' => $projects,
            'departments' => Department::all(),
            'statuses' => Project::availableStatuses(),
            'filters' => $filters,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params = []): void
    {
        $user = $this->requireUser();

        if (!AuthorizationService::canRegisterProject($user)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to access project registration form without permission."
            );
        }

        $this->render('projects/create', [
            'pageTitle' => 'Register New Project',
            'activeNav' => 'projects_create',
            'departments' => Department::allActive(),
            'checklistDefinitions' => MarketingChecklistService::checklistDefinition(),
            'formAction' => '/projects',
            'backUrl' => AuthorizationService::isMarketingUser($user) ? '/marketing/projects' : '/projects',
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): void
    {
        $user = $this->requireUser();

        if (!AuthorizationService::canRegisterProject($user)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to create a project without permission."
            );
        }

        $input = $request->allPost();
        $result = ProjectService::createDraft($input, $user, $request);

        if (!$result['success']) {
            Session::flashErrors($result['errors'] ?? []);
            Session::flashOldInput($input);
            Response::redirect('/projects/create');
        }

        $projectId = (int) ($result['project_id'] ?? 0);
        Session::flash(
            'success',
            "Draft project '{$result['project_code']}' registered successfully. Prepare and upload the required Marketing documents before submitting to Project Management."
        );
        Response::redirect('/projects/' . $projectId);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canViewProject($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to view unauthorized project '{$project['project_code']}'."
            );
        }

        $checklist = MarketingChecklistService::evaluateProject($project);
        $workflowHistories = ProjectWorkflowHistory::forProject((int) $project['id']);
        $projectLogs = ActivityLog::forEntity('project', (int) $project['id'], 20);

        $this->render('projects/show', [
            'pageTitle' => $project['project_code'] . ' — ' . $project['project_name'],
            'activeNav' => AuthorizationService::isMarketingUser($user) ? 'marketing_projects' : 'projects',
            'project' => $project,
            'checklist' => $checklist,
            'workflowHistories' => $workflowHistories,
            'projectLogs' => $projectLogs,
            'canEdit' => AuthorizationService::canEditProject($user, $project),
            'canSubmit' => AuthorizationService::canSubmitProject($user, $project),
            'canUploadDocument' => AuthorizationService::canUploadDocument($user, $project),
            'canDownloadDocument' => AuthorizationService::canDownloadDocument($user, $project),
            'canReturnToMarketing' => AuthorizationService::canReturnProjectToMarketing($user, $project),
            'canStartReview' => AuthorizationService::canStartReview($user, $project),
            'canCompleteVerification' => AuthorizationService::canCompleteVerification($user, $project),
            'canIssueJomNumber' => AuthorizationService::canIssueJomNumber($user, $project),
            'documentCategories' => (array) Config::get('app.uploads.document_categories', []),
            'maxUploadMb' => round((int) Config::get('app.uploads.max_size_bytes', 10485760) / 1048576, 1),
            'openUploadType' => (string) $request->query('upload', ''),
            'openHistoryType' => (string) $request->query('history', ''),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canEditProject($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to edit project '{$project['project_code']}' without permission."
            );
        }

        $this->render('projects/edit', [
            'pageTitle' => 'Edit Project: ' . $project['project_code'],
            'activeNav' => AuthorizationService::isMarketingUser($user) ? 'marketing_projects' : 'projects',
            'project' => $project,
            'departments' => Department::allActive(),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canEditProject($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to update project '{$project['project_code']}' without permission."
            );
        }

        $projectId = (int) $project['id'];
        $input = $request->allPost();
        $result = ProjectService::updateProject($project, $input, $user, $request);

        if (!$result['success']) {
            Session::flashErrors($result['errors'] ?? []);
            Session::flashOldInput($input);
            Response::redirect('/projects/' . $projectId . '/edit');
        }

        Session::flash('success', "Project '{$project['project_code']}' updated successfully.");
        Response::redirect('/projects/' . $projectId);
    }

    /**
     * Move a Draft project explicitly into Preparing Documents.
     *
     * @param array<string, string> $params
     */
    public function startPreparation(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canEditProject($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to start document preparation on '{$project['project_code']}' without permission."
            );
        }

        $projectId = (int) $project['id'];
        $result = ProjectService::startDocumentPreparation($project, $user, $request);

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to update workflow stage.');
            Response::redirect('/projects/' . $projectId);
        }

        Session::flash('success', "Project '{$project['project_code']}' is now in 'Preparing Documents'. Select and upload the required Marketing checklist files below.");
        Response::redirect('/projects/' . $projectId . '?upload=auto#checklist');
    }

    /**
     * Submit or resubmit an eligible Marketing project to Project Management for document verification.
     *
     * @param array<string, string> $params
     */
    public function submit(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canSubmitProject($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to submit project '{$project['project_code']}' without permission."
            );
        }

        $projectId = (int) $project['id'];
        $notes = (string) $request->post('submission_notes', '');
        $result = ProjectService::submitProject($project, $user, $request, $notes);

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to submit project to Project Management.');
            Response::redirect('/projects/' . $projectId . '#submission-panel');
        }

        $newStatus = (string) ($result['new_status'] ?? Project::STATUS_SUBMITTED_FOR_VERIFICATION);
        Session::flash(
            'success',
            "Project '{$project['project_code']}' has been forwarded to Project Management ({$newStatus}). JOM issuance awaits PM verification."
        );
        Response::redirect('/projects/' . $projectId);
    }

    /**
     * Minimal shared Project Management handoff action to return a submitted project to Marketing for corrections.
     *
     * @param array<string, string> $params
     */
    public function returnToMarketing(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canReturnProjectToMarketing($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to return project '{$project['project_code']}' for corrections without Project Management authorization."
            );
        }

        $projectId = (int) $project['id'];
        $input = $request->allPost();
        $result = ProjectService::returnProjectToMarketing($project, $input, $user, $request);

        if (!$result['success']) {
            if (!empty($result['errors'])) {
                Session::flashErrors($result['errors']);
            } else {
                Session::flash('error', $result['error'] ?? 'Unable to return project to Marketing.');
            }
            Session::flashOldInput($input);
            Response::redirect('/projects/' . $projectId . '#pm-review-panel');
        }

        Session::flash(
            'success',
            "Project '{$project['project_code']}' has been returned to Marketing for corrections."
        );
        Response::redirect('/projects/' . $projectId);
    }

    /**
     * @param array<string, string> $params
     * @return array<string, mixed>
     */
    private function loadProjectOrFail(array $params): array
    {
        $id = isset($params['id']) && ctype_digit($params['id']) ? (int) $params['id'] : 0;
        $project = $id > 0 ? Project::findById($id) : null;

        if ($project === null) {
            $this->notFound('The requested project record could not be found.');
        }

        return $project;
    }
}
