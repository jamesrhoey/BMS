<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;

final class DepartmentController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): void
    {
        $this->requireAdmin($request);

        $departments = Department::allWithStats();

        $this->render('departments/index', [
            'pageTitle' => 'Company Departments',
            'activeNav' => 'departments',
            'departments' => $departments,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params = []): void
    {
        $admin = $this->requireAdmin($request);

        $id = isset($params['id']) && ctype_digit($params['id']) ? (int) $params['id'] : 0;
        $department = $id > 0 ? Department::findById($id) : null;

        if ($department === null) {
            $this->notFound('The requested department could not be found.');
        }

        $members = User::byDepartment($id);
        $projects = Project::listForUser($admin, ['department_id' => (string) $id]);

        $this->render('departments/show', [
            'pageTitle' => $department['department_name'] . ' Department',
            'activeNav' => 'departments',
            'department' => $department,
            'members' => $members,
            'projects' => $projects,
        ]);
    }
}

