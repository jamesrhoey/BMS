<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;

final class UserService
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $actor
     * @return array{success: bool, errors?: array<string, string>, user_id?: int}
     */
    public static function createUser(array $input, array $actor, Request $request): array
    {
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $username = trim((string) ($input['username'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $roleIdRaw = (string) ($input['role_id'] ?? '');
        $deptIdRaw = (string) ($input['department_id'] ?? '');
        $status = trim((string) ($input['status'] ?? User::STATUS_ACTIVE));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirmation = (string) ($input['password_confirmation'] ?? '');

        $validator = new Validator([
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'role_id' => $roleIdRaw,
            'department_id' => $deptIdRaw,
            'status' => $status,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ]);

        $validator
            ->required('full_name', 'Full name')
            ->minLength('full_name', 2, 'Full name')
            ->maxLength('full_name', 150, 'Full name')
            ->required('username', 'Username')
            ->username('username', 'Username')
            ->email('email', 'Email address')
            ->maxLength('email', 190, 'Email address')
            ->required('role_id', 'Role')
            ->integer('role_id', 'Role')
            ->required('status', 'Account status')
            ->inList('status', [User::STATUS_ACTIVE, User::STATUS_INACTIVE], 'Account status')
            ->required('password', 'Password')
            ->minLength('password', 8, 'Password')
            ->maxLength('password', 255, 'Password')
            ->matches('password', 'password_confirmation', 'Password');

        $role = ctype_digit($roleIdRaw) ? Role::findById((int) $roleIdRaw) : null;
        if ($role === null) {
            $validator->addError('role_id', 'Please select a valid system role.');
        }

        $department = null;
        if ($deptIdRaw !== '') {
            if (!ctype_digit($deptIdRaw) || ($department = Department::findById((int) $deptIdRaw)) === null) {
                $validator->addError('department_id', 'Please select a valid department.');
            }
        } elseif ($role !== null && ($role['role_slug'] ?? '') !== Role::SLUG_ADMIN) {
            $validator->addError('department_id', 'Department assignment is required for Department Users.');
        }

        if ($username !== '' && User::usernameExists($username)) {
            $validator->addError('username', 'This username is already taken.');
        }

        if ($email !== '' && User::emailExists($email)) {
            $validator->addError('email', 'This email address is already registered to another user.');
        }

        if ($validator->fails()) {
            return ['success' => false, 'errors' => $validator->errors()];
        }

        $departmentId = $department !== null ? (int) $department['id'] : null;
        $roleId = (int) $role['id'];
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $newUserId = Database::transaction(static function () use (
            $departmentId,
            $department,
            $roleId,
            $role,
            $fullName,
            $username,
            $email,
            $passwordHash,
            $status,
            $actor,
            $request
        ): int {
            $id = User::create([
                'department_id' => $departmentId,
                'role_id' => $roleId,
                'full_name' => $fullName,
                'username' => $username,
                'email' => $email,
                'password_hash' => $passwordHash,
                'status' => $status,
            ]);

            $deptLabel = $department !== null ? $department['department_name'] : 'None (System-Wide)';
            ActivityLogger::log(
                'user.created',
                'user',
                $id,
                "Created user '{$username}' ({$fullName}) with role '{$role['role_name']}', department '{$deptLabel}', status '{$status}'.",
                $request,
                (int) $actor['id'],
                false
            );

            return $id;
        });

        return [
            'success' => true,
            'user_id' => $newUserId,
        ];
    }

    /**
     * @param array<string, mixed> $existingUser
     * @param array<string, mixed> $input
     * @param array<string, mixed> $actor
     * @return array{success: bool, errors?: array<string, string>, error_message?: string}
     */
    public static function updateUser(array $existingUser, array $input, array $actor, Request $request): array
    {
        $targetId = (int) $existingUser['id'];
        $actorId = (int) $actor['id'];

        $fullName = trim((string) ($input['full_name'] ?? ''));
        $username = trim((string) ($input['username'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $roleIdRaw = (string) ($input['role_id'] ?? '');
        $deptIdRaw = (string) ($input['department_id'] ?? '');
        $status = trim((string) ($input['status'] ?? User::STATUS_ACTIVE));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirmation = (string) ($input['password_confirmation'] ?? '');

        $validator = new Validator([
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'role_id' => $roleIdRaw,
            'department_id' => $deptIdRaw,
            'status' => $status,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ]);

        $validator
            ->required('full_name', 'Full name')
            ->minLength('full_name', 2, 'Full name')
            ->maxLength('full_name', 150, 'Full name')
            ->required('username', 'Username')
            ->username('username', 'Username')
            ->email('email', 'Email address')
            ->maxLength('email', 190, 'Email address')
            ->required('role_id', 'Role')
            ->integer('role_id', 'Role')
            ->required('status', 'Account status')
            ->inList('status', [User::STATUS_ACTIVE, User::STATUS_INACTIVE], 'Account status');

        if ($password !== '' || $passwordConfirmation !== '') {
            $validator
                ->minLength('password', 8, 'New password')
                ->maxLength('password', 255, 'New password')
                ->matches('password', 'password_confirmation', 'New password');
        }

        $role = ctype_digit($roleIdRaw) ? Role::findById((int) $roleIdRaw) : null;
        if ($role === null) {
            $validator->addError('role_id', 'Please select a valid system role.');
        }

        $department = null;
        if ($deptIdRaw !== '') {
            if (!ctype_digit($deptIdRaw) || ($department = Department::findById((int) $deptIdRaw)) === null) {
                $validator->addError('department_id', 'Please select a valid department.');
            }
        } elseif ($role !== null && ($role['role_slug'] ?? '') !== Role::SLUG_ADMIN) {
            $validator->addError('department_id', 'Department assignment is required for Department Users.');
        }

        if ($username !== '' && User::usernameExists($username, $targetId)) {
            $validator->addError('username', 'This username is already taken.');
        }

        if ($email !== '' && User::emailExists($email, $targetId)) {
            $validator->addError('email', 'This email address is already registered to another user.');
        }

        // Prevent self-demotion or self-deactivation
        if ($targetId === $actorId) {
            if ($status !== User::STATUS_ACTIVE) {
                $validator->addError('status', 'You cannot deactivate your own active administrator session.');
            }
            if ($role !== null && ($role['role_slug'] ?? '') !== Role::SLUG_ADMIN) {
                $validator->addError('role_id', 'You cannot remove the Administrator role from your own account.');
            }
        }

        // Prevent deactivating or demoting the last active Administrator
        $wasActiveAdmin = ($existingUser['role_slug'] ?? '') === Role::SLUG_ADMIN
            && ($existingUser['status'] ?? '') === User::STATUS_ACTIVE;
        $willRemainActiveAdmin = $role !== null
            && ($role['role_slug'] ?? '') === Role::SLUG_ADMIN
            && $status === User::STATUS_ACTIVE;

        if ($wasActiveAdmin && !$willRemainActiveAdmin && User::countActiveAdmins($targetId) === 0) {
            $validator->addError('status', 'Cannot deactivate or demote the last active Administrator account.');
        }

        if ($validator->fails()) {
            return ['success' => false, 'errors' => $validator->errors()];
        }

        $newDepartmentId = $department !== null ? (int) $department['id'] : null;
        $newRoleId = (int) $role['id'];

        Database::transaction(static function () use (
            $existingUser,
            $targetId,
            $newDepartmentId,
            $department,
            $newRoleId,
            $role,
            $fullName,
            $username,
            $email,
            $status,
            $password,
            $actorId,
            $request
        ): void {
            User::update($targetId, [
                'department_id' => $newDepartmentId,
                'role_id' => $newRoleId,
                'full_name' => $fullName,
                'username' => $username,
                'email' => $email,
                'status' => $status,
            ]);

            ActivityLogger::log(
                'user.updated',
                'user',
                $targetId,
                "Updated user details for '{$username}' ({$fullName}).",
                $request,
                $actorId,
                false
            );

            $oldDeptId = $existingUser['department_id'] !== null ? (int) $existingUser['department_id'] : null;
            if ($oldDeptId !== $newDepartmentId) {
                $oldDeptName = $existingUser['department_name'] ?? 'Unassigned';
                $newDeptName = $department['department_name'] ?? 'Unassigned';
                ActivityLogger::log(
                    'user.department_changed',
                    'user',
                    $targetId,
                    "Changed department assignment for '{$username}' from '{$oldDeptName}' to '{$newDeptName}'.",
                    $request,
                    $actorId,
                    false
                );
            }

            if ((int) $existingUser['role_id'] !== $newRoleId) {
                ActivityLogger::log(
                    'user.role_changed',
                    'user',
                    $targetId,
                    "Changed role for '{$username}' from '{$existingUser['role_name']}' to '{$role['role_name']}'.",
                    $request,
                    $actorId,
                    false
                );
            }

            if (($existingUser['status'] ?? '') !== $status) {
                ActivityLogger::log(
                    'user.status_changed',
                    'user',
                    $targetId,
                    "Changed account status for '{$username}' from '{$existingUser['status']}' to '{$status}'.",
                    $request,
                    $actorId,
                    false
                );
            }

            if ($password !== '') {
                User::updatePassword($targetId, password_hash($password, PASSWORD_DEFAULT));
                ActivityLogger::log(
                    'user.password_reset',
                    'user',
                    $targetId,
                    "Reset password for user '{$username}'.",
                    $request,
                    $actorId,
                    false
                );
            }
        });

        return ['success' => true];
    }

    /**
     * @param array<string, mixed> $targetUser
     * @param array<string, mixed> $actor
     * @return array{success: bool, error?: string}
     */
    public static function setUserStatus(array $targetUser, string $newStatus, array $actor, Request $request): array
    {
        if (!in_array($newStatus, [User::STATUS_ACTIVE, User::STATUS_INACTIVE], true)) {
            return ['success' => false, 'error' => 'Invalid status requested.'];
        }

        $targetId = (int) $targetUser['id'];
        $actorId = (int) $actor['id'];

        if ($targetId === $actorId && $newStatus !== User::STATUS_ACTIVE) {
            return ['success' => false, 'error' => 'You cannot deactivate your own active administrator account.'];
        }

        $isAdminTarget = ($targetUser['role_slug'] ?? '') === Role::SLUG_ADMIN;
        if ($isAdminTarget && $newStatus === User::STATUS_INACTIVE && User::countActiveAdmins($targetId) === 0) {
            return ['success' => false, 'error' => 'Cannot deactivate the last active Administrator account.'];
        }

        if (($targetUser['status'] ?? '') === $newStatus) {
            return ['success' => true];
        }

        Database::transaction(static function () use ($targetUser, $targetId, $newStatus, $actorId, $request): void {
            User::updateStatus($targetId, $newStatus);

            ActivityLogger::log(
                'user.status_changed',
                'user',
                $targetId,
                "Changed account status for '{$targetUser['username']}' from '{$targetUser['status']}' to '{$newStatus}'.",
                $request,
                $actorId,
                false
            );
        });

        return ['success' => true];
    }
}

