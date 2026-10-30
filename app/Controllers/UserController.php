<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;

final class UserController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): void
    {
        $this->requireAdmin($request);

        $filters = [
            'q' => (string) $request->query('q', ''),
            'department_id' => (string) $request->query('department_id', ''),
            'role_id' => (string) $request->query('role_id', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $users = User::allWithRelations($filters);
        $departments = Department::all();
        $roles = Role::all();

        $this->render('users/index', [
            'pageTitle' => 'User Management',
            'activeNav' => 'users',
            'users' => $users,
            'departments' => $departments,
            'roles' => $roles,
            'filters' => $filters,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params = []): void
    {
        $this->requireAdmin($request);

        $preselectedDept = (string) $request->query('department_id', '');

        $this->render('users/create', [
            'pageTitle' => 'Create User Account',
            'activeNav' => 'users',
            'departments' => Department::allActive(),
            'roles' => Role::all(),
            'preselectedDept' => $preselectedDept,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): void
    {
        $admin = $this->requireAdmin($request);
        $input = $request->allPost();

        $result = UserService::createUser($input, $admin, $request);

        if (!$result['success']) {
            Session::flashErrors($result['errors'] ?? []);
            Session::flashOldInput($input);
            Response::redirect('/users/create');
        }

        Session::flash('success', 'User account created successfully.');
        Response::redirect('/users');
    }

    /**
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params = []): void
    {
        $this->requireAdmin($request);

        $id = isset($params['id']) && ctype_digit($params['id']) ? (int) $params['id'] : 0;
        $targetUser = $id > 0 ? User::findById($id) : null;

        if ($targetUser === null) {
            $this->notFound('The requested user account could not be found.');
        }

        $this->render('users/edit', [
            'pageTitle' => 'Edit User: ' . $targetUser['username'],
            'activeNav' => 'users',
            'targetUser' => $targetUser,
            'departments' => Department::all(),
            'roles' => Role::all(),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): void
    {
        $admin = $this->requireAdmin($request);

        $id = isset($params['id']) && ctype_digit($params['id']) ? (int) $params['id'] : 0;
        $targetUser = $id > 0 ? User::findById($id) : null;

        if ($targetUser === null) {
            $this->notFound('The requested user account could not be found.');
        }

        $input = $request->allPost();
        $result = UserService::updateUser($targetUser, $input, $admin, $request);

        if (!$result['success']) {
            Session::flashErrors($result['errors'] ?? []);
            Session::flashOldInput($input);
            Response::redirect('/users/' . $id . '/edit');
        }

        Session::flash('success', "User account '{$input['username']}' updated successfully.");
        Response::redirect('/users');
    }

    /**
     * @param array<string, string> $params
     */
    public function toggleStatus(Request $request, array $params = []): void
    {
        $admin = $this->requireAdmin($request);

        $id = isset($params['id']) && ctype_digit($params['id']) ? (int) $params['id'] : 0;
        $targetUser = $id > 0 ? User::findById($id) : null;

        if ($targetUser === null) {
            $this->notFound('The requested user account could not be found.');
        }

        $desiredStatus = (string) $request->post('status', '');
        $result = UserService::setUserStatus($targetUser, $desiredStatus, $admin, $request);

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Unable to update user status.');
            Response::redirect('/users');
        }

        $verb = $desiredStatus === User::STATUS_ACTIVE ? 'activated' : 'deactivated';
        Session::flash('success', "Account '{$targetUser['username']}' has been {$verb}.");
        Response::redirect('/users');
    }
}

