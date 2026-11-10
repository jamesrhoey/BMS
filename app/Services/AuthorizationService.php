<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActualBom;
use App\Models\Department;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\SupplierDelivery;
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
     * @param array<string, mixed>|null $user
     */
    public static function isAdminFinanceUser(?array $user): bool
    {
        if ($user === null || ($user['status'] ?? '') !== User::STATUS_ACTIVE) {
            return false;
        }

        return ($user['department_code'] ?? '') === Department::CODE_ADMIN_FINANCE;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function isOperationsUser(?array $user): bool
    {
        if ($user === null || ($user['status'] ?? '') !== User::STATUS_ACTIVE) {
            return false;
        }

        return ($user['department_code'] ?? '') === Department::CODE_OPERATIONS;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function isOpsFinanceUser(?array $user): bool
    {
        if ($user === null || ($user['status'] ?? '') !== User::STATUS_ACTIVE) {
            return false;
        }

        return ($user['department_code'] ?? '') === Department::CODE_OPERATION_FINANCE;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function isLogisticsUser(?array $user): bool
    {
        if ($user === null || ($user['status'] ?? '') !== User::STATUS_ACTIVE) {
            return false;
        }

        return ($user['department_code'] ?? '') === Department::CODE_LOGISTICS;
    }

    /**
     * Access to dedicated Operations workspace routes (/operations/*).
     *
     * @param array<string, mixed>|null $user
     */
    public static function canAccessOperationsModule(?array $user): bool
    {
        if ($user === null || ($user['status'] ?? '') !== User::STATUS_ACTIVE) {
            return false;
        }

        return self::isAdmin($user) || self::isOperationsUser($user);
    }

    /**
     * Access to dedicated OPS Finance workspace routes (/ops-finance/*).
     *
     * @param array<string, mixed>|null $user
     */
    public static function canAccessOpsFinanceModule(?array $user): bool
    {
        if ($user === null || ($user['status'] ?? '') !== User::STATUS_ACTIVE) {
            return false;
        }

        return self::isAdmin($user) || self::isOpsFinanceUser($user);
    }

    /**
     * Access to dedicated Admin Finance workspace routes (/admin-finance/*).
     *
     * @param array<string, mixed>|null $user
     */
    public static function canAccessAdminFinanceModule(?array $user): bool
    {
        if ($user === null || ($user['status'] ?? '') !== User::STATUS_ACTIVE) {
            return false;
        }

        return self::isAdmin($user) || self::isAdminFinanceUser($user);
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

        if (self::isAdminFinanceUser($user)) {
            if (Project::isForwardedToAdminFinance($project) || Project::isJomIssued($project)) {
                return true;
            }
        }

        if (self::isOperationsUser($user)) {
            if (Project::isForwardedToOperations($project) || ($userDeptId !== null && $projectDeptId === $userDeptId && !empty($project['jom_number']))) {
                return true;
            }
        }

        if (self::isOpsFinanceUser($user)) {
            $pr = PurchaseRequest::findByProjectId((int) ($project['id'] ?? 0));
            if ($pr !== null && !empty($pr['submitted_at'])) {
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
        if (!in_array($status, [
            Project::STATUS_SUBMITTED_FOR_VERIFICATION,
            Project::STATUS_SUBMITTED_FOR_VERIFICATION_SHORT,
            Project::STATUS_RESUBMITTED_FOR_VERIFICATION,
            Project::STATUS_SUBMITTED,
        ], true)) {
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
     * Determine whether a user can forward a PM-approved project to Admin Finance.
     * Restricted to Project Management users and Administrators.
     * Prevents duplicate forwarding if the project has already been forwarded.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     */
    public static function canForwardToAdminFinance(?array $user, array $project): bool
    {
        if ($user === null) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isProjectManagementUser($user)) {
            return false;
        }

        if (!self::isAdmin($user) && !self::hasPermission($user, 'pm.projects.forward_afin')) {
            return false;
        }

        if (Project::isForwardedToAdminFinance($project)) {
            return false;
        }

        if (!Project::isApprovedByPm($project)) {
            return false;
        }

        if (self::isAdmin($user)) {
            return true;
        }

        return self::canViewProject($user, $project);
    }

    /**
     * Determine whether a user can download or view documents attached to a project.
     * Operations users may only access the forwarded SLD and Marketing BOM ('BOM') documents
     * on projects forwarded to Operations by Admin Finance.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $document
     */
    public static function canDownloadDocument(?array $user, array $project, ?array $document = null): bool
    {
        if (!self::canViewProject($user, $project)) {
            return false;
        }

        if (self::isOperationsUser($user) && !self::isAdmin($user)) {
            if (!Project::isForwardedToOperations($project) && empty($project['jom_number'])) {
                return false;
            }

            if ($document !== null) {
                $docType = (string) ($document['document_type'] ?? '');
                if (!in_array($docType, ['SLD', 'BOM', PurchaseRequest::DOC_TYPE_ATTACHMENT], true)) {
                    return false;
                }
            }
        }

        if (self::isOpsFinanceUser($user) && !self::isAdmin($user)) {
            $pr = PurchaseRequest::findByProjectId((int) ($project['id'] ?? 0));
            if ($pr === null || empty($pr['submitted_at'])) {
                return false;
            }

            if ($document !== null) {
                $docType = (string) ($document['document_type'] ?? '');
                if (!in_array($docType, [
                    'SLD',
                    'BOM',
                    PurchaseRequest::DOC_TYPE_ATTACHMENT,
                    ActualBom::DOC_TYPE_ACTUAL_BOM,
                    ActualBom::DOC_TYPE_SUPPORTING,
                ], true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Determine whether a user can mark an incoming project as Under Admin Finance Review.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     */
    public static function canStartAdminFinanceReview(?array $user, array $project): bool
    {
        if ($user === null) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isAdminFinanceUser($user)) {
            return false;
        }

        if (!empty($project['jom_number']) || Project::isForwardedToOperations($project)) {
            return false;
        }

        $status = (string) ($project['status'] ?? '');

        return $status === Project::STATUS_FORWARDED_TO_ADMIN_FINANCE
            || (!empty($project['forwarded_to_afin_at']) && $status !== Project::STATUS_UNDER_ADMIN_FINANCE_REVIEW);
    }

    /**
     * Determine whether the user is authorized to issue a JOM (Job Order Memorandum) number.
     * Under the new official company workflow (MARKETING -> PM -> ADMIN FINANCE -> OPERATIONS -> OPS FINANCE -> ADMIN FINANCE),
     * Admin Finance is the ONLY department responsible for issuing JOM numbers (plus Administrator full system access).
     * Do not allow JOM issuance for projects that have not been approved and forwarded by Project Management.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     */
    public static function canIssueJomNumber(?array $user, array $project): bool
    {
        if ($user === null) {
            return false;
        }

        // Explicitly block Project Management, Marketing, Operations, Logistics, and Operation Finance users
        if (!self::isAdmin($user) && !self::isAdminFinanceUser($user)) {
            return false;
        }

        if (!self::isAdmin($user) && !self::hasPermission($user, 'afin.projects.jom_issue')) {
            return false;
        }

        // Cannot issue if project already has a JOM number
        if (!empty($project['jom_number'])) {
            return false;
        }

        // Project must have been approved and forwarded by Project Management to Admin Finance
        return Project::isApprovedByPm($project) && Project::isForwardedToAdminFinance($project);
    }

    /**
     * Determine whether a user can forward the JOM number, SLD, and Marketing BOM to Operations.
     * Restricted to Admin Finance users and Administrators after JOM issuance.
     * Prevents duplicate forwarding if already forwarded to Operations.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     */
    public static function canForwardToOperations(?array $user, array $project): bool
    {
        if ($user === null) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isAdminFinanceUser($user)) {
            return false;
        }

        if (!self::isAdmin($user) && !self::hasPermission($user, 'afin.projects.forward_ops')) {
            return false;
        }

        if (Project::isForwardedToOperations($project)) {
            return false;
        }

        return !empty($project['jom_number']);
    }

    /**
     * Determine whether a user can create or edit a Draft/Returned Purchase Request (PR).
     * Restricted strictly to Operations users (and Administrators) on projects forwarded to Operations.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $existingPr
     */
    public static function canPreparePurchaseRequest(?array $user, array $project, ?array $existingPr = null): bool
    {
        if ($user === null) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isOperationsUser($user)) {
            return false;
        }

        if (!self::isAdmin($user) && !self::hasPermission($user, 'ops.pr.manage')) {
            return false;
        }

        if (!Project::isForwardedToOperations($project) || empty($project['jom_number'])) {
            return false;
        }

        if ($existingPr !== null && !PurchaseRequest::isEditableByOperations($existingPr)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether a user can submit a Purchase Request to OPS Finance.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $pr
     */
    public static function canSubmitPurchaseRequest(?array $user, array $project, ?array $pr): bool
    {
        if ($pr === null) {
            return false;
        }

        return self::canPreparePurchaseRequest($user, $project, $pr)
            && count($pr['items'] ?? []) > 0;
    }

    /**
     * Determine whether a user (OPS Finance, Admin Finance, or Admin) can review or return a submitted Purchase Request.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $pr
     */
    public static function canReviewPurchaseRequest(?array $user, array $project, ?array $pr): bool
    {
        if ($user === null || $pr === null) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isOpsFinanceUser($user) && !self::isAdminFinanceUser($user)) {
            return false;
        }

        if (!self::isAdmin($user) && !self::hasPermission($user, 'opfin.pr.review') && !self::hasPermission($user, 'afin.pr.approve')) {
            return false;
        }

        return in_array((string) ($pr['status'] ?? ''), [
            PurchaseRequest::STATUS_SUBMITTED_TO_OPS_FINANCE,
            PurchaseRequest::STATUS_SUBMITTED,
            PurchaseRequest::STATUS_UNDER_OPS_FINANCE_REVIEW,
            PurchaseRequest::STATUS_UNDER_REVIEW,
        ], true);
    }

    /**
     * Determine whether an authorized user (OPS Finance, Admin Finance, or Admin) can formally approve a Purchase Request.
     * Operations, Marketing, and Project Management users are strictly forbidden from approving PRs.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $pr
     */
    public static function canApprovePurchaseRequest(?array $user, array $project, ?array $pr): bool
    {
        if ($user === null || $pr === null) {
            return false;
        }

        if ((self::isOperationsUser($user) || self::isMarketingUser($user) || self::isProjectManagementUser($user)) && !self::isAdmin($user)) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isOpsFinanceUser($user) && !self::isAdminFinanceUser($user)) {
            return false;
        }

        if (!self::isAdmin($user)
            && !self::hasPermission($user, 'opfin.pr.approve')
            && !self::hasPermission($user, 'afin.pr.approve')) {
            return false;
        }

        return in_array((string) ($pr['status'] ?? ''), [
            PurchaseRequest::STATUS_SUBMITTED_TO_OPS_FINANCE,
            PurchaseRequest::STATUS_SUBMITTED,
            PurchaseRequest::STATUS_UNDER_OPS_FINANCE_REVIEW,
            PurchaseRequest::STATUS_UNDER_REVIEW,
            PurchaseRequest::STATUS_ACTUAL_BOM_SUBMITTED,
        ], true);
    }

    /**
     * Determine whether an authorized user can formally reject a Purchase Request with reason.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $pr
     */
    public static function canRejectPurchaseRequest(?array $user, array $project, ?array $pr): bool
    {
        if ($user === null || $pr === null) {
            return false;
        }

        if ((self::isOperationsUser($user) || self::isMarketingUser($user) || self::isProjectManagementUser($user)) && !self::isAdmin($user)) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isOpsFinanceUser($user) && !self::isAdminFinanceUser($user)) {
            return false;
        }

        if (!self::isAdmin($user)
            && !self::hasPermission($user, 'opfin.pr.reject')
            && !self::hasPermission($user, 'afin.pr.reject')) {
            return false;
        }

        return in_array((string) ($pr['status'] ?? ''), [
            PurchaseRequest::STATUS_SUBMITTED_TO_OPS_FINANCE,
            PurchaseRequest::STATUS_SUBMITTED,
            PurchaseRequest::STATUS_UNDER_OPS_FINANCE_REVIEW,
            PurchaseRequest::STATUS_UNDER_REVIEW,
            PurchaseRequest::STATUS_ACTUAL_BOM_SUBMITTED,
        ], true);
    }

    /**
     * Determine whether a user can upload or update the Actual BOM with specifications.
     * Strictly restricted to OPS Finance users (and Administrators).
     * Operations, Marketing, PM, and Admin Finance cannot upload or modify OPS Finance Actual BOM records.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $pr
     * @param array<string, mixed>|null $existingActualBom
     */
    public static function canUploadActualBom(
        ?array $user,
        array $project,
        ?array $pr,
        ?array $existingActualBom = null
    ): bool {
        if ($user === null || $pr === null) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isOpsFinanceUser($user)) {
            return false;
        }

        if (!self::isAdmin($user) && !self::hasPermission($user, 'opfin.actual_bom.manage')) {
            return false;
        }

        if (!PurchaseRequest::isSubmittedToOpsFinance($pr)) {
            return false;
        }

        if ($existingActualBom !== null && !ActualBom::isEditableByOpsFinance($existingActualBom)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether a user can submit the Actual BOM to Admin Finance.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $pr
     * @param array<string, mixed>|null $actualBom
     */
    public static function canSubmitActualBom(
        ?array $user,
        array $project,
        ?array $pr,
        ?array $actualBom
    ): bool {
        if ($actualBom === null || empty($actualBom['document_id']) || count($actualBom['items'] ?? []) === 0) {
            return false;
        }

        return self::canUploadActualBom($user, $project, $pr, $actualBom);
    }

    /**
     * Determine whether a user (Admin Finance or Admin) can receive, review, return, or accept a submitted Actual BOM.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $actualBom
     */
    public static function canReviewActualBom(?array $user, array $project, ?array $actualBom): bool
    {
        if ($user === null || $actualBom === null) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isAdminFinanceUser($user)) {
            return false;
        }

        if (!self::isAdmin($user) && !self::hasPermission($user, 'afin.actual_bom.review')) {
            return false;
        }

        return in_array((string) ($actualBom['status'] ?? ''), [
            ActualBom::STATUS_SUBMITTED_TO_ADMIN_FINANCE,
            ActualBom::STATUS_RECEIVED,
            ActualBom::STATUS_UNDER_REVIEW,
        ], true);
    }

    /**
     * Determine whether an authorized user (Admin Finance or Admin) can create/edit a PO from an approved PR.
     * Operations, Marketing, and PM are strictly forbidden from creating POs.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $pr
     */
    public static function canCreatePurchaseOrder(?array $user, array $project, ?array $pr): bool
    {
        if ($user === null || $pr === null) {
            return false;
        }

        if ((self::isOperationsUser($user) || self::isMarketingUser($user) || self::isProjectManagementUser($user) || self::isOpsFinanceUser($user)) && !self::isAdmin($user)) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isAdminFinanceUser($user)) {
            return false;
        }

        if (!self::isAdmin($user) && !self::hasPermission($user, 'afin.po.manage')) {
            return false;
        }

        return in_array((string) ($pr['status'] ?? ''), [
            PurchaseRequest::STATUS_APPROVED,
            PurchaseRequest::STATUS_PARTIALLY_ORDERED,
            PurchaseRequest::STATUS_CONVERTED_TO_PO,
        ], true);
    }

    /**
     * Determine whether an authorized user (Admin Finance or Admin) can financially authorize a PO.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $po
     */
    public static function canAuthorizePurchaseOrder(?array $user, array $project, ?array $po): bool
    {
        if ($user === null || $po === null) {
            return false;
        }

        if ((self::isOperationsUser($user) || self::isMarketingUser($user) || self::isProjectManagementUser($user) || self::isOpsFinanceUser($user)) && !self::isAdmin($user)) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isAdminFinanceUser($user)) {
            return false;
        }

        if (!self::isAdmin($user) && !self::hasPermission($user, 'afin.po.authorize')) {
            return false;
        }

        return in_array((string) ($po['status'] ?? ''), [
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_PENDING_APPROVAL,
        ], true);
    }

    /**
     * Determine whether an authorized user (Admin Finance or Admin) can issue an authorized PO to a supplier.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $po
     */
    public static function canIssuePurchaseOrder(?array $user, array $project, ?array $po): bool
    {
        if ($user === null || $po === null) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isAdminFinanceUser($user)) {
            return false;
        }

        return (string) ($po['status'] ?? '') === PurchaseOrder::STATUS_APPROVED;
    }

    /**
     * Determine whether an authorized user can log or inspect supplier delivery shipments.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $po
     */
    public static function canReceiveSupplierDelivery(?array $user, array $project, ?array $po): bool
    {
        if ($user === null || $po === null) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isLogisticsUser($user) && !self::isOperationsUser($user)) {
            return false;
        }

        return in_array((string) ($po['status'] ?? ''), [
            PurchaseOrder::STATUS_ISSUED_TO_SUPPLIER,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        ], true);
    }

    /**
     * Determine whether an authorized user (Logistics, Operations, or Admin) can execute Stock In.
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $delivery
     */
    public static function canExecuteStockIn(?array $user, array $project, ?array $delivery): bool
    {
        if ($user === null || $delivery === null) {
            return false;
        }

        if (!self::isAdmin($user) && !self::isLogisticsUser($user) && !self::isOperationsUser($user)) {
            return false;
        }

        return in_array((string) ($delivery['status'] ?? ''), [
            SupplierDelivery::STATUS_PENDING_INSPECTION,
            SupplierDelivery::STATUS_INSPECTED,
            SupplierDelivery::STATUS_PARTIALLY_RECEIVED,
            SupplierDelivery::STATUS_DISCREPANCY,
        ], true);
    }
}
