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
     * Dedicated Marketing Department Dashboard with live database statistics,
     * category breakdown (MRC vs Special Project), and paginated recent projects.
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

        $category = trim((string) $request->query('category', ''));
        $stats = Project::marketingDashboardStats($mktDeptId, $requiredDocs, $category);
        $pagination = Project::paginateMarketingProjects(
            $mktDeptId,
            $requiredDocs,
            ['sort' => 'newest', 'category' => $category],
            $page,
            $perPage
        );

        $this->render('marketing/dashboard', [
            'pageTitle' => 'Marketing Dashboard',
            'activeNav' => 'marketing_dashboard',
            'department' => $mktDept,
            'stats' => $stats,
            'projects' => $pagination['items'],
            'pagination' => $pagination,
            'requiredDocumentTypes' => $requiredDocs,
            'categories' => (array) Config::get('marketing.categories', []),
            'selectedCategory' => $category,
        ]);
    }

    /**
     * Marketing Project List with search, project category filter (MRC / Special Project),
     * workflow status filter, completeness filter, date sorting, and pagination.
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
            'category' => (string) $request->query('category', ''),
            'status' => (string) $request->query('status', ''),
            'completeness' => (string) $request->query('completeness', ''),
            'sort' => (string) $request->query('sort', 'newest'),
            'view' => (string) $request->query('view', ''),
        ];

        $pagination = Project::paginateMarketingProjects($mktDeptId, $requiredDocs, $filters, $page, $perPage);

        $this->render('marketing/projects', [
            'pageTitle' => 'Marketing Projects',
            'activeNav' => 'marketing_projects',
            'projects' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
            'categories' => (array) Config::get('marketing.categories', []),
            'statuses' => Project::marketingStatuses(),
            'requiredCount' => count($requiredDocs),
        ]);
    }

    /**
     * Display project creation form with controlled category selection (MRC vs Special Project)
     * and dynamic document checklist preview.
     *
     * @param array<string, string> $params
     */
    public function createProject(Request $request, array $params = []): void
    {
        $this->requireMarketingAccess($request);

        $this->render('projects/create', [
            'pageTitle' => 'Register Marketing Project',
            'activeNav' => 'projects_create',
            'departments' => Department::allActive(),
            'categories' => (array) Config::get('marketing.categories', []),
            'defaultCategory' => (string) Config::get('marketing.default_workflow', 'mrc'),
            'checklistDefinitions' => MarketingChecklistService::checklistDefinition('mrc'),
            'mrcChecklist' => MarketingChecklistService::checklistDefinition('mrc'),
            'specialChecklist' => MarketingChecklistService::checklistDefinition('special_project'),
            'formAction' => '/marketing/projects',
            'backUrl' => '/marketing/projects',
        ]);
    }

    /**
     * Register a new draft project with a controlled category (MRC or Special Project).
     *
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
        $catLabel = (string) ($result['category_label'] ?? 'MRC');
        $isSpecial = ($result['project_category'] ?? '') === 'special_project' || $catLabel === 'Special Project';
        $docCountNotice = $isSpecial ? 'seven required Marketing documents (including client PO)' : 'six required Marketing documents';

        Session::flash(
            'success',
            "Draft {$catLabel} project '{$result['project_code']}' registered successfully. Upload the {$docCountNotice} below before submitting to Project Management and Admin Finance."
        );
        Response::redirect('/projects/' . $projectId);
    }
}
