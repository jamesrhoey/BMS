<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use App\Services\AuthorizationService;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $user = $this->requireUser();

        if (AuthorizationService::isAdmin($user)) {
            $this->adminDashboard($request);
            return;
        }

        if (AuthorizationService::isMarketingUser($user)) {
            (new MarketingController())->dashboard($request);
            return;
        }

        if (AuthorizationService::isProjectManagementUser($user)) {
            (new ProjectManagementController())->dashboard($request);
            return;
        }

        $this->departmentDashboard($request);
    }

    public function adminDashboard(Request $request): void
    {
        $user = $this->requireAdmin($request);

        $actPageRaw = (string) $request->query('activity_page', '1');
        $actPage = ctype_digit($actPageRaw) ? max(1, (int) $actPageRaw) : 1;

        $userStats = User::dashboardCounts();
        $projectStats = Project::dashboardCounts();
        $totalDepartments = Department::countAll();
        $departments = Department::allWithStats();
        $activityPagination = ActivityLog::paginate([], $actPage, 4);
        $recentProjects = Project::recentForUser($user, 5);

        $this->render('dashboard/admin', [
            'pageTitle' => 'Administrator Dashboard',
            'activeNav' => 'dashboard',
            'stats' => [
                'total_users' => $userStats['total_users'],
                'active_users' => $userStats['active_users'],
                'inactive_users' => $userStats['inactive_users'],
                'total_departments' => $totalDepartments,
                'total_projects' => $projectStats['total_projects'],
                'draft_projects' => $projectStats['draft_projects'],
                'submitted_projects' => $projectStats['submitted_projects'],
            ],
            'departments' => $departments,
            'recentProjects' => $recentProjects,
            'recentLogs' => $activityPagination['items'],
            'activityPagination' => $activityPagination,
        ]);
    }

    public function departmentDashboard(Request $request): void
    {
        $user = $this->requireUser();

        if (AuthorizationService::isMarketingUser($user)) {
            (new MarketingController())->dashboard($request);
            return;
        }

        $department = null;
        if ($user['department_id'] !== null) {
            $department = Department::findById((int) $user['department_id']);
        }

        $projectCounts = Project::countsForUser($user);
        $recentProjects = Project::recentForUser($user, 6);

        $pmQueue = [];
        if (AuthorizationService::isProjectManagementUser($user) && $department !== null) {
            $pmQueue = Project::pmVerificationQueue((int) $department['id'], 10);
        }

        $this->render('dashboard/department', [
            'pageTitle' => ($department['department_name'] ?? 'Department') . ' Workspace',
            'activeNav' => 'dashboard_dept',
            'department' => $department,
            'projectCounts' => $projectCounts,
            'recentProjects' => $recentProjects,
            'pmVerificationQueue' => $pmQueue,
        ]);
    }
}
