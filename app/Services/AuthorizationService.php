<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Department;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;

final class AuthorizationService
{
    /** @var array<int, array<string, bool>> */
    private static array $rolePermissionsCache = [];

    /**
     * @param array<string, mixed>|null $user
     */
    public static function isAdmin(?array $user): bool
    {
        if ($user === null || ($user['status'] ?? '') !== User::STATUS_ACTIVE) {
            return false;
        }

        return ($user['role_slug'] ?? '') === Role::SLUG_ADMIN;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function isMarketingUser(?array $user): bool
    {
        if ($user === null || ($user['status'] ?? '') !== User::STATUS_ACTIVE) {
            return false;
        }

        return ($user['department_code'] ?? '') === Department::CODE_MARKETING;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function isProjectManagementUser(?array $user): bool
    {
        if ($user === null || ($user['status'] ?? '') !== User::STATUS_ACTIVE) {
            return false;
        }

        return ($user['department_code'] ?? '') === Department::CODE_PROJECT_MANAGEMENT;
    }

    /**
     * Access to dedicated Project Management workspace routes (/pm/*).
     *
     * @param array<string, mixed>|null $user
     */
    public static function canAccessProjectManagementModule(?array $user): bool
    {
        if ($user === null || ($user['status'] ?? '') !== User::STATUS_ACTIVE) {
            return false;
        }

        return self::isAdmin($user) || self::isProjectManagementUser($user);
    }

    /**
     * Access to dedicated Marketing workspace routes (/marketing/*).
     *
     * @param array<string, mixed>|null $user
     */
    public static function canAccessMarketingModule(?array $user): bool
    {
        if ($user === null) {
            return false;
        }

        return self::isAdmin($user) || self::isMarketingUser($user);
    }

    /**
     * Check granular role-based permission from the `role_permissions` table.
     *
     * @param array<string, mixed>|null $user
     */
    public static function hasPermission(?array $user, string $permissionKey): bool
    {
        if ($user === null || ($user['status'] ?? '') !== User::STATUS_ACTIVE) {
            return false;
        }

        if (self::isAdmin($user)) {
            return true;
        }

        $roleId = (int) ($user['role_id'] ?? 0);
        if ($roleId <= 0) {
            return false;
        }

        if (!isset(self::$rolePermissionsCache[$roleId])) {
            $keys = Role::permissionKeysForRole($roleId);
            self::$rolePermissionsCache[$roleId] = array_fill_keys($keys, true);
        }

        return isset(self::$rolePermissionsCache[$roleId][$permissionKey]);
    }

    /**
     * Administrators and Marketing department users may register projects.
     *
     * @param array<string, mixed>|null $user
     */
    public static function canRegisterProject(?array $user): bool
    {
        if ($user === null) {
            return false;
        }

        return self::hasPermission($user, 'projects.create') || self::isMarketingUser($user);
    }

    /**
     * Determine whether a user can view a specific project record.
     *
     * Shared Marketing Team Access Rule:
     * Active Marketing users can view any project originating from Marketing
     * (`origin_department_id` = MKT), currently assigned to Marketing, or created by
     * a Marketing team member — enabling collaborative document preparation and status tracking.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     */
    public static function canViewProject(?array $user, array $project): bool
    {
        if ($user === null) {
            return false;
        }

        if (self::hasPermission($user, 'projects.view_all')) {
            return true;
        }

        $userId = (int) ($user['id'] ?? 0);
        $userDeptId = $user['department_id'] !== null ? (int) $user['department_id'] : null;
        $projectDeptId = $project['current_department_id'] !== null ? (int) $project['current_department_id'] : null;
        $originDeptId = isset($project['origin_department_id']) && $project['origin_department_id'] !== null
            ? (int) $project['origin_department_id']
            : null;
        $creatorDeptId = isset($project['creator_department_id']) && $project['creator_department_id'] !== null
            ? (int) $project['creator_department_id']
            : null;
        $creatorId = (int) ($project['created_by'] ?? 0);

        if ($userId > 0 && $creatorId === $userId) {
            return true;
        }

        if ($userDeptId !== null && $projectDeptId !== null && $userDeptId === $projectDeptId) {
            return true;
        }

        if (self::isMarketingUser($user) && $userDeptId !== null) {
            if ($originDeptId === $userDeptId || $creatorDeptId === $userDeptId) {
                return true;
            }
        }

        if (self::isProjectManagementUser($user)) {
            $verStatus = (string) ($project['verification_status'] ?? Project::VERIFICATION_NOT_SUBMITTED);
            if ($verStatus !== Project::VERIFICATION_NOT_SUBMITTED || !empty($project['submitted_at'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether a user can edit permitted fields of a project.
     * Marketing users may edit projects while in Draft, Preparing Documents, or Returned for Corrections.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     */
    public static function canEditProject(?array $user, array $project): bool
    {
        if ($user === null) {
            return false;
        }

        if (self::isAdmin($user)) {
            return true;
        }

        $isEditableStage = Project::isMarketingEditable($project);

        return $isEditableStage && self::isMarketingUser($user) && self::canViewProject($user, $project);
    }

    /**
     * Determine whether a user can submit or resubmit a Marketing project to Project Management.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     */
    public static function canSubmitProject(?array $user, array $project): bool
    {
        if ($user === null) {
            return false;
        }

        if (!Project::isMarketingEditable($project)) {
            return false;
        }

        if (self::isAdmin($user)) {
            return true;
        }

        return self::isMarketingUser($user) && self::canViewProject($user, $project);
    }

    /**
     * Determine whether a user can upload or replace Marketing documents on a project.
     * Locked while a project is queued in Project Management verification so submitted packages stay immutable during review.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     */
    public static function canUploadDocument(?array $user, array $project): bool
    {
        if ($user === null) {
            return false;
        }

        if (!Project::isMarketingEditable($project)) {
            return false;
        }

        if (self::isAdmin($user)) {
            return true;
        }

        return self::isMarketingUser($user) && self::canViewProject($user, $project);
    }

    /**
     * Determine whether a user can initiate review on a project.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     */
    public static function canStartReview(?array $user, array $project): bool
    {
        if ($user === null) {
            return false;
        }

        $status = (string) ($project['status'] ?? '');
        if (!in_array($status, [Project::STATUS_SUBMITTED_FOR_VERIFICATION, Project::STATUS_RESUBMITTED_FOR_VERIFICATION, Project::STATUS_SUBMITTED], true)) {
            return false;
        }

        if (self::isAdmin($user)) {
            return true;
        }

        return self::isProjectManagementUser($user) && self::canViewProject($user, $project);
    }

    /**
     * Determine whether a user can complete and approve the document verification stage.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     */
    public static function canCompleteVerification(?array $user, array $project): bool
    {
        if ($user === null) {
            return false;
        }

        if (!in_array((string) ($project['status'] ?? ''), Project::pmReviewableStatuses(), true)) {
            return false;
        }

        if (self::isAdmin($user)) {
            return true;
        }

        return self::isProjectManagementUser($user) && self::canViewProject($user, $project);
    }

    /**
     * Determine whether a user can return a submitted project to Marketing for corrections.
     * Strictly restricted to Project Management users (and Administrators) — never Marketing users.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     */
    public static function canReturnProjectToMarketing(?array $user, array $project): bool
    {
        if ($user === null) {
            return false;
        }

        if (!in_array((string) ($project['status'] ?? ''), Project::pmVerificationQueueStatuses(), true)) {
            return false;
        }

        if (self::isAdmin($user)) {
            return true;
        }

        return self::isProjectManagementUser($user) && self::canViewProject($user, $project);
    }

    /**
     * Determine whether a user can download documents attached to a project.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     */
    public static function canDownloadDocument(?array $user, array $project): bool
    {
        return self::canViewProject($user, $project);
    }

    /**
     * Determine whether the user is authorized to issue a JOM (Job Order Memorandum) number.
     * Only authorized Project Management users and administrators can issue JOM numbers,
     * and only once document verification is fully completed and no JOM has been issued yet.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     */
    public static function canIssueJomNumber(?array $user, array $project): bool
    {
        if ($user === null) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isProjectManagementUser($user)) {
            return false;
        }

        if (!self::isAdmin($user) && !self::hasPermission($user, 'pm.projects.jom_issue')) {
            return false;
        }

        // Cannot issue if project already has a JOM number
        if (!empty($project['jom_number'])) {
            return false;
        }

        // Must have completed document verification
        $status = (string) ($project['status'] ?? '');
        $vStatus = (string) ($project['verification_status'] ?? '');

        return $status === Project::STATUS_VERIFICATION_COMPLETED || $vStatus === Project::VERIFICATION_VERIFIED;
    }
}
