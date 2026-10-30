<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Department;
use App\Models\Project;
use App\Services\MarketingChecklistService;
use App\Services\ProjectService;

final class MarketingController extends Controller
{
    /**
     * Dedicated Marketing Department Dashboard with live database statistics and paginated projects.
     *
     * @param array<string, string> $params
     */
    public function dashboard(Request $request, array $params = []): void
    {
        $this->requireMarketingAccess($request);

        $mktDept = Department::findByCode(Department::CODE_MARKETING);
        if ($mktDept === null) {
            $this->notFound('Marketing department configuration is missing.');
        }

        $mktDeptId = (int) $mktDept['id'];
        $requiredDocs = MarketingChecklistService::requiredDocumentTypes();

        $pageRaw = (string) $request->query('page', '1');
        $page = ctype_digit($pageRaw) ? max(1, (int) $pageRaw) : 1;
        $perPage = (int) Config::get('marketing.pagination.dashboard_per_page', 10);

        $stats = Project::marketingDashboardStats($mktDeptId, $requiredDocs);
        $pagination = Project::paginateMarketingProjects($mktDeptId, $requiredDocs, ['sort' => 'newest'], $page, $perPage);

        $this->render('marketing/dashboard', [
            'pageTitle' => 'Marketing Dashboard',
            'activeNav' => 'marketing_dashboard',
            'department' => $mktDept,
            'stats' => $stats,
            'projects' => $pagination['items'],
            'pagination' => $pagination,
            'requiredDocumentTypes' => $requiredDocs,
        ]);
    }

    /**
     * Marketing Project List with search, workflow status filter, completeness filter, date sorting, and pagination.
     *
     * @param array<string, string> $params
     */
    public function projects(Request $request, array $params = []): void
    {
        $this->requireMarketingAccess($request);

        $mktDept = Department::findByCode(Department::CODE_MARKETING);
        if ($mktDept === null) {
            $this->notFound('Marketing department configuration is missing.');
        }

        $mktDeptId = (int) $mktDept['id'];
        $requiredDocs = MarketingChecklistService::requiredDocumentTypes();

        $pageRaw = (string) $request->query('page', '1');
        $page = ctype_digit($pageRaw) ? max(1, (int) $pageRaw) : 1;
        $perPage = (int) Config::get('marketing.pagination.projects_per_page', 12);

        $filters = [
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'completeness' => (string) $request->query('completeness', ''),
            'sort' => (string) $request->query('sort', 'newest'),
        ];

        $pagination = Project::paginateMarketingProjects($mktDeptId, $requiredDocs, $filters, $page, $perPage);

        $this->render('marketing/projects', [
            'pageTitle' => 'Marketing Projects',
            'activeNav' => 'marketing_projects',
            'projects' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
            'statuses' => Project::marketingStatuses(),
            'requiredCount' => count($requiredDocs),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function createProject(Request $request, array $params = []): void
    {
        $this->requireMarketingAccess($request);

        $this->render('projects/create', [
            'pageTitle' => 'Register Marketing Project',
            'activeNav' => 'projects_create',
            'departments' => Department::allActive(),
            'checklistDefinitions' => MarketingChecklistService::checklistDefinition(),
            'formAction' => '/marketing/projects',
            'backUrl' => '/marketing/projects',
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function storeProject(Request $request, array $params = []): void
    {
        $user = $this->requireMarketingAccess($request);
        $input = $request->allPost();

        $result = ProjectService::createDraft($input, $user, $request);

        if (!$result['success']) {
            Session::flashErrors($result['errors'] ?? []);
            Session::flashOldInput($input);
            Response::redirect('/marketing/projects/create');
        }

        $projectId = (int) ($result['project_id'] ?? 0);
        Session::flash(
            'success',
            "Draft project '{$result['project_code']}' created. Upload the six required Marketing documents below before submitting to Project Management."
        );
        Response::redirect('/projects/' . $projectId);
    }
}

