<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Department;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectWorkflowHistory;
use App\Services\DocumentStorageService;
use PDOException;

final class ProjectService
{
    /**
     * Generate a unique internal system project identifier (e.g. PRJ-20261029-A1B2C3).
     * Note: This is an internal registry code, NOT a company JOM number.
     */
    public static function generateUniqueProjectCode(): string
    {
        $datePart = date('Ymd');

        for ($attempt = 0; $attempt < 15; $attempt++) {
            $suffix = strtoupper(bin2hex(random_bytes(3)));
            $candidate = "PRJ-{$datePart}-{$suffix}";
            if (!Project::codeExists($candidate)) {
                return $candidate;
            }
        }

        return 'PRJ-' . $datePart . '-' . strtoupper(bin2hex(random_bytes(5)));
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $actor
     * @return array{success: bool, errors?: array<string, string>, project_id?: int, project_code?: string}
     */
    public static function createDraft(array $input, array $actor, Request $request): array
    {
        $projectName = trim((string) ($input['project_name'] ?? ''));
        $clientName = trim((string) ($input['client_name'] ?? ''));
        $clientContact = trim((string) ($input['client_contact'] ?? ''));
        $projectLocation = trim((string) ($input['project_location'] ?? ''));
        $projectDescription = trim((string) ($input['project_description'] ?? ''));
        $deptIdRaw = trim((string) ($input['current_department_id'] ?? ''));

        $validator = new Validator([
            'project_name' => $projectName,
            'client_name' => $clientName,
            'client_contact' => $clientContact,
            'project_location' => $projectLocation,
            'project_description' => $projectDescription,
            'current_department_id' => $deptIdRaw,
        ]);

        $validator
            ->required('project_name', 'Project name')
            ->minLength('project_name', 3, 'Project name')
            ->maxLength('project_name', 200, 'Project name')
            ->required('client_name', 'Client name')
            ->minLength('client_name', 2, 'Client name')
            ->maxLength('client_name', 150, 'Client name')
            ->maxLength('client_contact', 150, 'Client contact')
            ->maxLength('project_location', 200, 'Project location')
            ->maxLength('project_description', 5000, 'Project description');

        $currentDepartmentId = self::resolvePermittedDepartmentId($actor, $deptIdRaw, $validator);

        if ($validator->fails()) {
            return ['success' => false, 'errors' => $validator->errors()];
        }

        // Controlled category validation: 'mrc' or 'special_project'
        $rawCategory = trim((string) ($input['project_category'] ?? 'mrc'));
        $projectCategoryKey = MarketingChecklistService::normalizeCategory($rawCategory);
        $projectCategoryLabel = MarketingChecklistService::categoryLabel($projectCategoryKey);
        $workflowType = $projectCategoryKey;

        $actorId = (int) $actor['id'];

        // Prevent rapid accidental duplicate submission on double-click / browser refresh
        $pdo = Database::connection();
        $recentStmt = $pdo->prepare(
            'SELECT id, project_code FROM projects
             WHERE project_name = :pname AND client_name = :cname AND created_by = :created_by
               AND created_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
             ORDER BY id DESC LIMIT 1'
        );
        $recentStmt->execute([
            'pname' => $projectName,
            'cname' => $clientName,
            'created_by' => $actorId,
        ]);
        $existingRecent = $recentStmt->fetch();
        if ($existingRecent !== false) {
            return [
                'success' => true,
                'project_id' => (int) $existingRecent['id'],
                'project_code' => (string) $existingRecent['project_code'],
                'project_category' => $projectCategoryKey,
                'category_label' => $projectCategoryLabel,
            ];
        }

        $mktDept = Department::findByCode(Department::CODE_MARKETING);
        $mktDeptId = $mktDept !== null ? (int) $mktDept['id'] : null;
        $originDepartmentId = AuthorizationService::isMarketingUser($actor)
            ? ($mktDeptId ?? $currentDepartmentId)
            : ($currentDepartmentId ?? $mktDeptId);

        $created = Database::transaction(static function () use (
            $projectName,
            $clientName,
            $clientContact,
            $projectLocation,
            $projectDescription,
            $workflowType,
            $projectCategoryLabel,
            $originDepartmentId,
            $currentDepartmentId,
            $actorId,
            $request
        ): array {
            $projectCode = '';
            $projectId = 0;

            for ($retry = 0; $retry < 5; $retry++) {
                $projectCode = self::generateUniqueProjectCode();
                try {
                    $projectId = Project::create([
                        'project_code' => $projectCode,
                        'project_name' => $projectName,
                        'client_name' => $clientName,
                        'client_contact' => $clientContact,
                        'project_location' => $projectLocation,
                        'project_description' => $projectDescription,
                        'workflow_type' => $workflowType,
                        'project_category' => $projectCategoryLabel,
                        'origin_department_id' => $originDepartmentId,
                        'current_department_id' => $currentDepartmentId,
                        'created_by' => $actorId,
                        'status' => Project::STATUS_DRAFT,
                        'verification_status' => Project::VERIFICATION_NOT_SUBMITTED,
                        'submitted_at' => null,
                    ]);
                    break;
                } catch (PDOException $e) {
                    if ($retry === 4 || (string) $e->getCode() !== '23000') {
                        throw $e;
                    }
                }
            }

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => null,
                'to_status' => Project::STATUS_DRAFT,
                'action' => 'created_draft',
                'from_department_id' => null,
                'to_department_id' => $currentDepartmentId,
                'acted_by' => $actorId,
                'notes' => "Draft project registered under category '{$projectCategoryLabel}'.",
            ]);

            ActivityLogger::log(
                'project.created',
                'project',
                $projectId,
                "Registered draft project '{$projectCode}' ({$projectName}) [Category: {$projectCategoryLabel}] for client '{$clientName}'.",
                $request,
                $actorId,
                false
            );

            ActivityLogger::log(
                'project.category_selected',
                'project',
                $projectId,
                "Assigned project category '{$projectCategoryLabel}' to '{$projectCode}'.",
                $request,
                $actorId,
                false
            );

            return [
                'project_id' => $projectId,
                'project_code' => $projectCode,
            ];
        });

        return [
            'success' => true,
            'project_id' => $created['project_id'],
            'project_code' => $created['project_code'],
            'project_category' => $projectCategoryKey,
            'category_label' => $projectCategoryLabel,
        ];
    }

    /**
     * Transition a Draft project explicitly into Preparing Documents when Marketing begins checklist preparation.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $actor
     * @return array{success: bool, error?: string}
     */
    public static function startDocumentPreparation(array $project, array $actor, Request $request): array
    {
        $fromStatus = (string) ($project['status'] ?? '');
        $isLegacyUnverifiedSubmitted = $fromStatus === Project::STATUS_SUBMITTED
            && ((string) ($project['verification_status'] ?? Project::VERIFICATION_NOT_SUBMITTED)) === Project::VERIFICATION_NOT_SUBMITTED;

        if ($fromStatus !== Project::STATUS_DRAFT && !$isLegacyUnverifiedSubmitted) {
            return ['success' => false, 'error' => 'Only Draft projects can be moved into Preparing Documents.'];
        }

        $projectId = (int) $project['id'];
        $actorId = (int) $actor['id'];

        Database::transaction(static function () use ($project, $projectId, $fromStatus, $actorId, $request): void {
            Project::updateStatus($projectId, Project::STATUS_PREPARING_DOCUMENTS);

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $fromStatus,
                'to_status' => Project::STATUS_PREPARING_DOCUMENTS,
                'action' => 'started_document_prep',
                'from_department_id' => $project['current_department_id'],
                'to_department_id' => $project['current_department_id'],
                'acted_by' => $actorId,
                'notes' => "Moved project from '{$fromStatus}' to 'Preparing Documents'.",
            ]);

            ActivityLogger::log(
                'project.started_document_prep',
                'project',
                $projectId,
                "Moved project '{$project['project_code']}' to 'Preparing Documents'.",
                $request,
                $actorId,
                false
            );
        });

        return ['success' => true];
    }

    /**
     * @param array<string, mixed> $existingProject
     * @param array<string, mixed> $input
     * @param array<string, mixed> $actor
     * @return array{success: bool, errors?: array<string, string>}
     */
    public static function updateProject(array $existingProject, array $input, array $actor, Request $request): array
    {
        $projectId = (int) $existingProject['id'];
        $projectName = trim((string) ($input['project_name'] ?? ''));
        $clientName = trim((string) ($input['client_name'] ?? ''));
        $clientContact = trim((string) ($input['client_contact'] ?? ''));
        $projectLocation = trim((string) ($input['project_location'] ?? ''));
        $projectDescription = trim((string) ($input['project_description'] ?? ''));
        $deptIdRaw = trim((string) ($input['current_department_id'] ?? ''));

        $validator = new Validator([
            'project_name' => $projectName,
            'client_name' => $clientName,
            'client_contact' => $clientContact,
            'project_location' => $projectLocation,
            'project_description' => $projectDescription,
            'current_department_id' => $deptIdRaw,
        ]);

        $validator
            ->required('project_name', 'Project name')
            ->minLength('project_name', 3, 'Project name')
            ->maxLength('project_name', 200, 'Project name')
            ->required('client_name', 'Client name')
            ->minLength('client_name', 2, 'Client name')
            ->maxLength('client_name', 150, 'Client name')
            ->maxLength('client_contact', 150, 'Client contact')
            ->maxLength('project_location', 200, 'Project location')
            ->maxLength('project_description', 5000, 'Project description');

        $currentDepartmentId = AuthorizationService::isAdmin($actor)
            ? self::resolvePermittedDepartmentId($actor, $deptIdRaw, $validator)
            : ($existingProject['current_department_id'] !== null ? (int) $existingProject['current_department_id'] : null);

        if ($validator->fails()) {
            return ['success' => false, 'errors' => $validator->errors()];
        }

        $actorId = (int) $actor['id'];

        Database::transaction(static function () use (
            $existingProject,
            $projectId,
            $projectName,
            $clientName,
            $clientContact,
            $projectLocation,
            $projectDescription,
            $currentDepartmentId,
            $actorId,
            $request
        ): void {
            Project::update($projectId, [
                'project_name' => $projectName,
                'client_name' => $clientName,
                'client_contact' => $clientContact,
                'project_location' => $projectLocation,
                'project_description' => $projectDescription,
                'current_department_id' => $currentDepartmentId,
            ]);

            ActivityLogger::log(
                'project.updated',
                'project',
                $projectId,
                "Updated project '{$existingProject['project_code']}' ({$projectName}).",
                $request,
                $actorId,
                false
            );

            $oldDeptId = $existingProject['current_department_id'] !== null ? (int) $existingProject['current_department_id'] : null;
            if ($oldDeptId !== $currentDepartmentId) {
                $newDept = $currentDepartmentId !== null ? Department::findById($currentDepartmentId) : null;
                $oldLabel = $existingProject['current_department_name'] ?? 'Unassigned';
                $newLabel = $newDept['department_name'] ?? 'Unassigned';

                ActivityLogger::log(
                    'project.department_changed',
                    'project',
                    $projectId,
                    "Changed current department of project '{$existingProject['project_code']}' from '{$oldLabel}' to '{$newLabel}'.",
                    $request,
                    $actorId,
                    false
                );
            }
        });

        return ['success' => true];
    }

    /**
     * Validate all required project fields and the Marketing document checklist on the server,
     * then submit (or resubmit) the project to Project Management for document verification.
     *
     * Note: Does NOT approve documents and does NOT issue a JOM number.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $actor
     * @return array{success: bool, error?: string, missing_documents?: list<string>, new_status?: string}
     */
    public static function submitProject(
        array $project,
        array $actor,
        Request $request,
        string $submissionNotes = ''
    ): array {
        $currentStatus = (string) ($project['status'] ?? '');
        if (!Project::isMarketingEditable($project)) {
            return [
                'success' => false,
                'error' => 'This project cannot be submitted in its current workflow status.',
            ];
        }

        if (trim((string) ($project['project_name'] ?? '')) === '' || trim((string) ($project['client_name'] ?? '')) === '') {
            return [
                'success' => false,
                'error' => 'Project name and client name must be completed before submission.',
            ];
        }

        $submissionNotes = trim($submissionNotes);
        if (mb_strlen($submissionNotes, 'UTF-8') > 1000) {
            return [
                'success' => false,
                'error' => 'Submission notes must not exceed 1,000 characters.',
            ];
        }

        // Server-side Marketing Document Completeness Check
        $checklist = MarketingChecklistService::evaluateProject($project);
        if (!$checklist['is_complete']) {
            $missingList = $checklist['missing_required_types'];
            ActivityLogger::log(
                'project.submission_blocked_incomplete',
                'project',
                (int) $project['id'],
                "Blocked submission of project '{$project['project_code']}' ({$checklist['category_label']}) due to missing required Marketing documents: " . implode(', ', $missingList) . '.',
                $request,
                (int) $actor['id'],
                false
            );

            $poNotice = ($checklist['is_po_required'] && in_array('Marketing PO', $missingList, true))
                ? ' Note: Special Projects require an uploaded Marketing Purchase Order (PO).'
                : '';

            return [
                'success' => false,
                'missing_documents' => $missingList,
                'error' => 'Cannot submit project. Required documents missing: ' . implode(', ', $missingList) . '.' . $poNotice,
            ];
        }

        $pmDept = Department::findByCode(Department::CODE_PROJECT_MANAGEMENT);
        if ($pmDept === null) {
            return [
                'success' => false,
                'error' => 'Project Management department record is missing from system configuration.',
            ];
        }

        $afinDept = Department::findByCode(Department::CODE_ADMIN_FINANCE);
        if ($afinDept === null) {
            return [
                'success' => false,
                'error' => 'Admin Finance department record is missing from system configuration.',
            ];
        }

        $pmDeptId = (int) $pmDept['id'];
        $afinDeptId = (int) $afinDept['id'];
        $projectId = (int) $project['id'];
        $actorId = (int) $actor['id'];

        $isResubmission = in_array($currentStatus, [
            Project::STATUS_RETURNED_FOR_CORRECTIONS,
            Project::STATUS_RETURNED_TO_MARKETING,
            Project::STATUS_RETURNED_TO_MARKETING_SHORT,
        ], true) || !empty($project['returned_at']);

        // Official Workflow Status: Submitted to Project Management and Admin Finance
        $targetStatus = $isResubmission
            ? Project::STATUS_RESUBMITTED_TO_PM_AND_AFIN
            : Project::STATUS_SUBMITTED_TO_PM_AND_AFIN;

        $historyActionPm = $isResubmission
            ? 'resubmitted_for_verification'
            : 'submitted_for_verification';

        $historyActionAfin = $isResubmission
            ? 'resubmitted_to_admin_finance'
            : 'submitted_to_admin_finance';

        $logAction = $isResubmission
            ? 'project.resubmitted'
            : 'project.submitted';

        $notifTypePm = $isResubmission
            ? 'project.resubmitted_to_pm'
            : 'project.submitted_to_pm';

        $notifTitlePm = $isResubmission
            ? "Project Resubmitted for Verification: {$project['project_code']}"
            : "New Project Submitted for Verification: {$project['project_code']}";

        $notifMessagePm = sprintf(
            "%s (%s) [%s] was %s by %s with all %d required Marketing documents uploaded and awaits Project Management completeness verification.",
            $project['project_name'],
            $project['project_code'],
            $checklist['category_label'],
            $isResubmission ? 'resubmitted after corrections' : 'submitted',
            $actor['full_name'] ?? $actor['username'],
            $checklist['required_count']
        );

        $notifTypeAfin = $isResubmission
            ? 'project.resubmitted_to_afin'
            : 'project.submitted_to_afin';

        $notifTitleAfin = $isResubmission
            ? "Project Resubmitted (Admin Finance Copy): {$project['project_code']}"
            : "New Project Submitted (Admin Finance Copy): {$project['project_code']}";

        $notifMessageAfin = sprintf(
            "%s (%s) [%s] was simultaneously submitted to Admin Finance by %s with all %d required Marketing documents.",
            $project['project_name'],
            $project['project_code'],
            $checklist['category_label'],
            $actor['full_name'] ?? $actor['username'],
            $checklist['required_count']
        );

        Database::transaction(static function () use (
            $project,
            $projectId,
            $currentStatus,
            $targetStatus,
            $isResubmission,
            $historyActionPm,
            $historyActionAfin,
            $logAction,
            $pmDeptId,
            $afinDeptId,
            $actorId,
            $submissionNotes,
            $notifTypePm,
            $notifTitlePm,
            $notifMessagePm,
            $notifTypeAfin,
            $notifTitleAfin,
            $notifMessageAfin,
            $checklist,
            $request
        ): void {
            // Update project record with simultaneous submission timestamps and status
            Project::markSubmittedSimultaneous($projectId, $targetStatus, $pmDeptId, $actorId);

            // If resubmitting, reset previously flagged documents back to pending review
            if ($isResubmission) {
                ProjectDocument::resetCorrectionToPending($projectId);
            }

            // Record assignment 1: Project Management Incoming Queue
            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $currentStatus,
                'to_status' => $targetStatus,
                'action' => $historyActionPm,
                'from_department_id' => $project['current_department_id'],
                'to_department_id' => $pmDeptId,
                'acted_by' => $actorId,
                'notes' => $submissionNotes !== ''
                    ? $submissionNotes
                    : "All {$checklist['required_count']} required Marketing checklist documents uploaded and forwarded to Project Management for verification.",
            ]);

            // Record assignment 2: Admin Finance Incoming Queue (Simultaneous)
            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $currentStatus,
                'to_status' => $targetStatus,
                'action' => $historyActionAfin,
                'from_department_id' => $project['current_department_id'],
                'to_department_id' => $afinDeptId,
                'acted_by' => $actorId,
                'notes' => "Project simultaneously routed to Admin Finance incoming queue per official workflow.",
            ]);

            // Send notification 1: Project Management
            Notification::notifyDepartmentUsers(
                $pmDeptId,
                $projectId,
                $notifTypePm,
                $notifTitlePm,
                $notifMessagePm,
                '/projects/' . $projectId
            );

            // Send notification 2: Admin Finance (Simultaneous)
            Notification::notifyDepartmentUsers(
                $afinDeptId,
                $projectId,
                $notifTypeAfin,
                $notifTitleAfin,
                $notifMessageAfin,
                '/projects/' . $projectId
            );

            ActivityLogger::log(
                $logAction,
                'project',
                $projectId,
                "Submitted project '{$project['project_code']}' ({$checklist['category_label']}) simultaneously to Project Management and Admin Finance with status '{$targetStatus}'.",
                $request,
                $actorId,
                false
            );
        });

        return [
            'success' => true,
            'new_status' => $targetStatus,
        ];
    }

    /**
     * Minimal shared Project Management handoff action to return a submitted project
     * back to Marketing for corrections when documents are incomplete or need revision.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $input
     * @param array<string, mixed> $actor
     * @return array{success: bool, errors?: array<string, string>, error?: string}
     */
    public static function returnProjectToMarketing(array $project, array $input, array $actor, Request $request): array
    {
        $currentStatus = (string) ($project['status'] ?? '');
        if (!in_array($currentStatus, Project::pmVerificationQueueStatuses(), true)) {
            return [
                'success' => false,
                'error' => 'Only projects awaiting Project Management verification can be returned for corrections.',
            ];
        }

        $returnReason = trim((string) ($input['return_reason'] ?? ''));
        $rawFlagged = $input['flagged_documents'] ?? [];

        $validator = new Validator(['return_reason' => $returnReason]);
        $validator
            ->required('return_reason', 'Return reason / correction notes')
            ->minLength('return_reason', 5, 'Return reason / correction notes')
            ->maxLength('return_reason', 2000, 'Return reason / correction notes');

        if ($validator->fails()) {
            return [
                'success' => false,
                'errors' => $validator->errors(),
            ];
        }

        $validTypes = MarketingChecklistService::allChecklistTypes((string) ($project['workflow_type'] ?? 'standard'));
        $flaggedDocuments = [];
        if (is_array($rawFlagged)) {
            foreach ($rawFlagged as $docType) {
                $docStr = trim((string) $docType);
                if (in_array($docStr, $validTypes, true) && !in_array($docStr, $flaggedDocuments, true)) {
                    $flaggedDocuments[] = $docStr;
                }
            }
        }

        $mktDept = Department::findByCode(Department::CODE_MARKETING);
        if ($mktDept === null) {
            return [
                'success' => false,
                'error' => 'Marketing department record is missing from system configuration.',
            ];
        }

        $mktDeptId = (int) $mktDept['id'];
        $projectId = (int) $project['id'];
        $actorId = (int) $actor['id'];

        $flaggedSuffix = $flaggedDocuments !== []
            ? ' Flagged documents: ' . implode(', ', $flaggedDocuments) . '.'
            : '';

        Database::transaction(static function () use (
            $project,
            $projectId,
            $currentStatus,
            $mktDeptId,
            $actorId,
            $actor,
            $returnReason,
            $flaggedDocuments,
            $flaggedSuffix,
            $request
        ): void {
            Project::markReturnedToMarketing(
                $projectId,
                $mktDeptId,
                $actorId,
                $returnReason,
                $flaggedDocuments
            );

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $currentStatus,
                'to_status' => Project::STATUS_RETURNED_FOR_CORRECTIONS,
                'action' => 'returned_for_corrections',
                'from_department_id' => $project['current_department_id'],
                'to_department_id' => $mktDeptId,
                'acted_by' => $actorId,
                'notes' => $returnReason,
                'flagged_documents' => $flaggedDocuments,
            ]);

            Notification::notifyDepartmentUsers(
                $mktDeptId,
                $projectId,
                'project.returned_to_marketing',
                "Project Returned for Corrections: {$project['project_code']}",
                sprintf(
                    "%s (%s) was returned to Marketing by %s. Reason: %s%s",
                    $project['project_name'],
                    $project['project_code'],
                    $actor['full_name'] ?? $actor['username'],
                    $returnReason,
                    $flaggedSuffix
                ),
                '/projects/' . $projectId
            );

            ActivityLogger::log(
                'project.returned_for_corrections',
                'project',
                $projectId,
                "Returned project '{$project['project_code']}' to Marketing for corrections. Reason: {$returnReason}{$flaggedSuffix}",
                $request,
                $actorId,
                false
            );
        });

        return ['success' => true];
    }

    /**
     * Mark a submitted project as 'Under Review' by Project Management.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $actor
     * @return array{success: bool, error?: string}
     */
    public static function startReview(array $project, array $actor, Request $request): array
    {
        $currentStatus = (string) ($project['status'] ?? '');
        if (!in_array($currentStatus, [
            Project::STATUS_SUBMITTED_FOR_VERIFICATION,
            Project::STATUS_SUBMITTED_FOR_VERIFICATION_SHORT,
            Project::STATUS_RESUBMITTED_FOR_VERIFICATION,
            Project::STATUS_SUBMITTED,
        ], true)) {
            return [
                'success' => false,
                'error' => 'Only submitted projects awaiting verification can be marked as Under Review.',
            ];
        }

        $projectId = (int) $project['id'];
        $actorId = (int) $actor['id'];
        $pmDeptId = $project['current_department_id'];

        Database::transaction(static function () use (
            $project,
            $projectId,
            $currentStatus,
            $pmDeptId,
            $actorId,
            $actor,
            $request
        ): void {
            Project::markUnderReview($projectId, $actorId);

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $currentStatus,
                'to_status' => Project::STATUS_UNDER_REVIEW,
                'action' => 'started_review',
                'from_department_id' => $pmDeptId,
                'to_department_id' => $pmDeptId,
                'acted_by' => $actorId,
                'notes' => 'Project Management commenced document verification review.',
            ]);

            ActivityLogger::log(
                'project.review_started',
                'project',
                $projectId,
                "Project Management reviewer '{$actor['full_name']}' started document verification review on '{$project['project_code']}'.",
                $request,
                $actorId,
                false
            );
        });

        return ['success' => true];
    }

    /**
     * Verify or flag a single document within a project's PM review.
     * Status: 'verified' or 'needs_correction'.
     */
    public static function verifyDocument(
        array $project,
        int $docId,
        string $status,
        string $notes,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canVerifyDocument($actor, $project)) {
            return ['success' => false, 'error' => 'You are not authorized to verify documents for this project.'];
        }

        $validStatuses = ['verified', 'needs_correction'];
        if (!in_array($status, $validStatuses, true)) {
            return ['success' => false, 'error' => 'Invalid review status. Use "verified" or "needs_correction".'];
        }

        $notes = trim($notes);
        if ($status === 'needs_correction' && $notes === '') {
            return ['success' => false, 'error' => 'Correction notes are required when flagging a document as Needs Correction.'];
        }
        if (mb_strlen($notes, 'UTF-8') > 2000) {
            return ['success' => false, 'error' => 'Notes must not exceed 2,000 characters.'];
        }

        // Load the document and verify it belongs to this project
        $document = ProjectDocument::findById($docId);
        if ($document === null || (int) $document['project_id'] !== (int) $project['id']) {
            return ['success' => false, 'error' => 'Document not found or does not belong to this project.'];
        }

        if (!$document['is_current']) {
            return ['success' => false, 'error' => 'Only the current version of a document can be verified.'];
        }

        // Cannot verify a document that has not been uploaded
        if (in_array((string) $document['review_status'], ['', 'pending'], true)) {
            return ['success' => false, 'error' => 'Cannot verify a document that has not been uploaded.'];
        }

        $actorId = (int) $actor['id'];
        $projectId = (int) $project['id'];

        ProjectDocument::updateReviewStatus($docId, $status, $notes !== '' ? $notes : null, $actorId);

        $actionKey = $status === 'verified' ? 'project.document_verified' : 'project.document_needs_correction';
        $desc = $status === 'verified'
            ? "PM reviewer '{$actor['full_name']}' marked document '{$document['document_type']}' as Verified on project '{$project['project_code']}'."
            : "PM reviewer '{$actor['full_name']}' flagged document '{$document['document_type']}' as Needs Correction on project '{$project['project_code']}'. Notes: {$notes}";

        ActivityLogger::log($actionKey, 'project', $projectId, $desc, $request, $actorId, false);

        return ['success' => true];
    }

    /**
     * Upload or replace the JOM (Job Order Memorandum) file for a PM-approved project.
     * Only Project Management users and Administrators may upload JOM files.
     */
    public static function uploadJomFile(
        array $project,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canUploadJom($actor, $project)) {
            return ['success' => false, 'error' => 'You are not authorized to upload the JOM file, or the project is not yet approved by Project Management.'];
        }

        $file = $request->file('jom_document');
        $uploadNotes = trim((string) $request->post('upload_notes', ''));

        // Use DocumentStorageService but bypass the WORKFLOW_DOCUMENT_TYPES whitelist
        // by calling storeUploadedDocument directly with 'JOM — Job Order Memorandum' type
        $result = DocumentStorageService::storeUploadedDocument(
            $project,
            $file,
            'JOM — Job Order Memorandum',
            $actor,
            $request,
            $uploadNotes
        );

        if (!$result['success']) {
            return $result;
        }

        $newDocId = (int) ($result['document_id'] ?? 0);
        $projectId = (int) $project['id'];
        $actorId = (int) $actor['id'];

        Database::transaction(static function () use ($project, $projectId, $actorId, $newDocId, $actor, $request): void {
            Project::markJomUploaded($projectId, $newDocId, $actorId);

            $currentStatus = (string) ($project['status'] ?? '');
            ProjectWorkflowHistory::record([
                'project_id'          => $projectId,
                'from_status'         => $currentStatus,
                'to_status'           => Project::STATUS_JOM_UPLOADED,
                'action'              => 'jom_uploaded',
                'from_department_id'  => $project['current_department_id'],
                'to_department_id'    => $project['current_department_id'],
                'acted_by'            => $actorId,
                'notes'               => 'JOM file uploaded by Project Management.',
            ]);

            // Notify Marketing of JOM upload
            $mktDept = Department::findByCode(Department::CODE_MARKETING);
            if ($mktDept !== null) {
                Notification::notifyDepartmentUsers(
                    (int) $mktDept['id'],
                    $projectId,
                    'project.jom_uploaded',
                    "JOM File Uploaded: {$project['project_code']}",
                    sprintf(
                        "%s (%s): Project Management has uploaded the JOM file. The project is ready to be forwarded to Operations.",
                        $project['project_name'],
                        $project['project_code']
                    ),
                    '/projects/' . $projectId
                );
            }

            ActivityLogger::log(
                'project.jom_uploaded',
                'project',
                $projectId,
                "PM user '{$actor['full_name']}' uploaded the JOM file for project '{$project['project_code']}'.",
                $request,
                $actorId,
                false
            );
        });

        return ['success' => true, 'document_id' => $newDocId, 'replaced' => !empty($result['replaced'])];
    }

    /**
     * Forward a JOM-uploaded project from Project Management to Operations.
     * Category-specific document set: MRC (JOM, SON, BOM, SLD) or Special Project (JOM, PO, SON, BOM, SLD).
     * CA, PROP, PROP-SUPPLIER are NEVER forwarded to Operations.
     */
    public static function pmForwardToOperations(
        array $project,
        array $input,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canPmForwardToOperations($actor, $project)) {
            return [
                'success' => false,
                'error'   => 'You are not authorized to forward this project to Operations, or the project has not met forwarding requirements.',
            ];
        }

        $projectId = (int) $project['id'];
        $actorId   = (int) $actor['id'];

        // JOM must exist
        $jomDoc = ProjectDocument::findCurrentJom($projectId);
        if ($jomDoc === null) {
            return ['success' => false, 'error' => 'Cannot forward to Operations: JOM file has not been uploaded yet.'];
        }

        // Determine category
        $rawCat = (string) ($project['project_category'] ?? $project['workflow_type'] ?? 'mrc');
        $category = MarketingChecklistService::normalizeCategory($rawCat);
        $pmConfig  = Config::get('pm');
        $handoffConfig = $category === 'special_project'
            ? ($pmConfig['ops_handoff']['special_project'] ?? [])
            : ($pmConfig['ops_handoff']['mrc'] ?? []);
        $requiredTypes = $handoffConfig['required_documents'] ?? ['JOM', 'SON', 'BOM', 'SLD'];

        // Collect required documents (excluding JOM already found)
        $handoffDocs = ['JOM' => $jomDoc];
        $missing     = [];

        foreach ($requiredTypes as $type) {
            if ($type === 'JOM') {
                continue;
            }
            $doc = ProjectDocument::findCurrentByType($projectId, $type);
            if ($doc === null && $type === 'PO') {
                $doc = ProjectDocument::findCurrentByType($projectId, 'Marketing PO')
                    ?? ProjectDocument::findCurrentByType($projectId, 'PO — Purchase Order')
                    ?? ProjectDocument::findCurrentByType($projectId, 'Purchase Order');
            }
            if ($doc === null) {
                $missing[] = $type;
            } else {
                $handoffDocs[$type] = $doc;
            }
        }

        if ($missing !== []) {
            return [
                'success' => false,
                'error'   => 'Cannot forward: missing required documents: ' . implode(', ', $missing) . '.',
            ];
        }

        $opsDept = Department::findByCode(Department::CODE_OPERATIONS);
        if ($opsDept === null) {
            return ['success' => false, 'error' => 'Operations department configuration is missing.'];
        }

        $opsDeptId  = (int) $opsDept['id'];
        $pmDeptId   = (int) ($project['current_department_id'] ?? 0);
        $forwardNotes = trim((string) ($input['forward_notes'] ?? ''));
        if ($forwardNotes === '') {
            $forwardNotes = "Project forwarded to Operations by Project Management with category-specific document package ({$category}).";
        }
        if (mb_strlen($forwardNotes, 'UTF-8') > 2000) {
            return ['success' => false, 'error' => 'Forwarding notes must not exceed 2,000 characters.'];
        }

        $sonDoc = $handoffDocs['SON'] ?? null;
        $bomDoc = $handoffDocs['BOM'] ?? null;
        $sldDoc = $handoffDocs['SLD'] ?? null;
        $poDoc  = $handoffDocs['PO'] ?? null;

        $handoffSummary = [];
        foreach ($handoffDocs as $type => $doc) {
            $handoffSummary[] = [
                'type'              => $type,
                'document_id'       => (int) $doc['id'],
                'original_filename' => $doc['original_filename'],
            ];
        }

        $success = Database::transaction(static function () use (
            $project,
            $projectId,
            $opsDeptId,
            $pmDeptId,
            $actorId,
            $actor,
            $jomDoc,
            $sonDoc,
            $bomDoc,
            $sldDoc,
            $poDoc,
            $forwardNotes,
            $handoffSummary,
            $category,
            $opsDept,
            $request
        ): bool {
            $forwarded = Project::markForwardedToOpsByPm(
                $projectId,
                $opsDeptId,
                $actorId,
                (int) $jomDoc['id'],
                (int) ($sonDoc['id'] ?? 0),
                (int) ($bomDoc['id'] ?? 0),
                (int) ($sldDoc['id'] ?? 0),
                $poDoc !== null ? (int) $poDoc['id'] : null,
                $forwardNotes,
                $handoffSummary
            );

            if (!$forwarded) {
                return false;
            }

            ProjectWorkflowHistory::record([
                'project_id'         => $projectId,
                'from_status'        => Project::STATUS_JOM_UPLOADED,
                'to_status'          => Project::STATUS_FORWARDED_TO_OPS,
                'action'             => 'forwarded_to_operations',
                'from_department_id' => $pmDeptId,
                'to_department_id'   => $opsDeptId,
                'acted_by'           => $actorId,
                'notes'              => $forwardNotes,
            ]);

            Notification::notifyDepartmentUsers(
                $opsDeptId,
                $projectId,
                'project.forwarded_to_operations',
                "New Project Received from PM: {$project['project_code']}",
                sprintf(
                    "%s (%s) has been forwarded to Operations by Project Management. Category: %s. Document package ready for review.",
                    $project['project_name'],
                    $project['project_code'],
                    strtoupper($category)
                ),
                '/projects/' . $projectId
            );

            ActivityLogger::log(
                'project.pm_forwarded_to_operations',
                'project',
                $projectId,
                "PM user '{$actor['full_name']}' forwarded project '{$project['project_code']}' to Operations. Category: {$category}. Documents forwarded: " . implode(', ', array_keys($handoffSummary[0] ?? [])),
                $request,
                $actorId,
                false
            );

            return true;
        });

        if (!$success) {
            return ['success' => false, 'error' => 'This project has already been forwarded to Operations. Duplicate forwarding prevented.'];
        }

        return ['success' => true];
    }

    /**
     * Approve and complete the document verification stage for a project.
     * Enforces complete presence of all required documents (6 of 6).
     * Does NOT issue a JOM number (Admin Finance is responsible for JOM issuance).
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $input
     * @param array<string, mixed> $actor
     * @return array{success: bool, error?: string}
     */
    public static function completeVerification(
        array $project,
        array $input,
        array $actor,
        Request $request
    ): array {
        $currentStatus = (string) ($project['status'] ?? '');
        if (!in_array($currentStatus, Project::pmReviewableStatuses(), true)) {
            return [
                'success' => false,
                'error' => 'This project cannot undergo verification completion in its current status.',
            ];
        }

        // Server-Side Strict Completeness Enforcement
        $checklist = MarketingChecklistService::evaluateProject($project);
        if (!$checklist['is_complete']) {
            $missingList = $checklist['missing_required_types'];
            ActivityLogger::log(
                'project.verification_blocked_incomplete',
                'project',
                (int) $project['id'],
                "Blocked verification completion of '{$project['project_code']}' due to missing documents: " . implode(', ', $missingList) . '.',
                $request,
                (int) $actor['id'],
                false
            );

            return [
                'success' => false,
                'error' => 'Cannot complete verification. All required documents must be present. Missing: ' . implode(', ', $missingList) . '.',
            ];
        }

        // Enforce that no documents have unresolved correction flags
        $currentDocs = ProjectDocument::currentForProject((int) $project['id']);
        foreach ($currentDocs as $cdoc) {
            if (($cdoc['review_status'] ?? '') === 'needs_correction') {
                return [
                    'success' => false,
                    'error' => 'Cannot complete verification while documents have unresolved correction flags. Return to Marketing or mark as Verified.',
                ];
            }
        }

        $verificationNotes = trim((string) ($input['verification_notes'] ?? ''));
        if (mb_strlen($verificationNotes, 'UTF-8') > 2000) {
            return [
                'success' => false,
                'error' => 'Verification notes must not exceed 2,000 characters.',
            ];
        }

        $projectId = (int) $project['id'];
        $actorId = (int) $actor['id'];
        $pmDeptId = $project['current_department_id'];
        $originDeptId = $project['origin_department_id'] ? (int) $project['origin_department_id'] : null;

        $targetStatus = Project::STATUS_APPROVED_BY_PM;
        $notesText = $verificationNotes !== ''
            ? $verificationNotes
            : 'All required Marketing documents verified complete and approved by Project Management.';

        Database::transaction(static function () use (
            $project,
            $projectId,
            $currentStatus,
            $targetStatus,
            $pmDeptId,
            $originDeptId,
            $actorId,
            $actor,
            $notesText,
            $checklist,
            $request
        ): void {
            Project::markVerificationCompleted($projectId, $actorId, $notesText);
            ProjectDocument::markAllCurrentVerified($projectId, $actorId);

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $currentStatus,
                'to_status' => $targetStatus,
                'action' => 'completed_document_verification',
                'from_department_id' => $pmDeptId,
                'to_department_id' => $pmDeptId,
                'acted_by' => $actorId,
                'notes' => $notesText,
            ]);

            // Notify origin/Marketing department users of PM approval
            if ($originDeptId !== null) {
                Notification::notifyDepartmentUsers(
                    $originDeptId,
                    $projectId,
                    'project.verification_completed',
                    "Approved by Project Management: {$project['project_code']}",
                    sprintf(
                        "%s (%s) document verification was approved by %s. All %d required documents verified. Ready to forward to Admin Finance.",
                        $project['project_name'],
                        $project['project_code'],
                        $actor['full_name'] ?? $actor['username'],
                        $checklist['required_count']
                    ),
                    '/projects/' . $projectId
                );
            }

            ActivityLogger::log(
                'project.document_verification_completed',
                'project',
                $projectId,
                "Project Management reviewer '{$actor['full_name']}' approved document verification for '{$project['project_code']}'. Ready for forwarding to Admin Finance.",
                $request,
                $actorId,
                false
            );
        });

        return ['success' => true];
    }

    /**
     * Forward an approved Project Management project to Admin Finance.
     * Routes the project to Admin Finance incoming queue, records forwarding user & timestamp,
     * prevents duplicate forwarding, and preserves full verification history.
     * Does NOT automatically proceed to Operations or issue a JOM number.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $input
     * @param array<string, mixed> $actor
     * @return array{success: bool, error?: string}
     */
    public static function forwardToAdminFinance(
        array $project,
        array $input,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canForwardToAdminFinance($actor, $project)) {
            return [
                'success' => false,
                'error' => 'This project cannot be forwarded to Admin Finance in its current status, or has already been forwarded.',
            ];
        }

        // Verify required documents remain complete
        $checklist = MarketingChecklistService::evaluateProject($project);
        if (!$checklist['is_complete']) {
            return [
                'success' => false,
                'error' => 'Cannot forward project to Admin Finance while required documents are incomplete.',
            ];
        }

        $forwardNotes = trim((string) ($input['operations_forward_notes'] ?? $input['forward_notes'] ?? ''));
        if (mb_strlen($forwardNotes, 'UTF-8') > 2000) {
            return [
                'success' => false,
                'error' => 'Forwarding notes must not exceed 2,000 characters.',
            ];
        }

        $afinDept = Department::findByCode(Department::CODE_ADMIN_FINANCE);
        if ($afinDept === null) {
            return [
                'success' => false,
                'error' => 'Admin Finance department configuration is missing.',
            ];
        }

        $afinDeptId = (int) $afinDept['id'];
        $projectId = (int) $project['id'];
        $actorId = (int) $actor['id'];

        $notesText = $forwardNotes !== ''
            ? $forwardNotes
            : 'Project Management approved all required documents and forwarded the project to Admin Finance for JOM issuance and financial processing.';

        return Database::transaction(static function () use (
            $project,
            $projectId,
            $afinDeptId,
            $actorId,
            $actor,
            $notesText,
            $request
        ): array {
            $stmt = Database::connection()->prepare('SELECT * FROM projects WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $projectId]);
            $locked = $stmt->fetch();

            if (!$locked) {
                return ['success' => false, 'error' => 'Project record not found.'];
            }

            if (Project::isForwardedToAdminFinance($locked)) {
                return [
                    'success' => false,
                    'error' => 'This project has already been forwarded to Admin Finance. Duplicate forwarding is prevented.',
                ];
            }

            if (!Project::isApprovedByPm($locked)) {
                return [
                    'success' => false,
                    'error' => 'Only projects approved by Project Management can be forwarded to Admin Finance.',
                ];
            }

            $fromStatus = (string) $locked['status'];
            $fromDeptId = $locked['current_department_id'] ? (int) $locked['current_department_id'] : null;
            $originDeptId = $locked['origin_department_id'] ? (int) $locked['origin_department_id'] : null;

            $forwarded = Project::markForwardedToAdminFinance($projectId, $afinDeptId, $actorId, $notesText);
            if (!$forwarded) {
                return [
                    'success' => false,
                    'error' => 'This project has already been forwarded to Admin Finance.',
                ];
            }

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $fromStatus,
                'to_status' => Project::STATUS_FORWARDED_TO_ADMIN_FINANCE,
                'action' => 'forwarded_to_admin_finance',
                'from_department_id' => $fromDeptId,
                'to_department_id' => $afinDeptId,
                'acted_by' => $actorId,
                'notes' => $notesText,
            ]);

            // Notify Admin Finance incoming queue users
            Notification::notifyDepartmentUsers(
                $afinDeptId,
                $projectId,
                'project.forwarded_to_admin_finance',
                "Project Forwarded to Admin Finance: {$locked['project_code']}",
                sprintf(
                    "Project '%s' (%s) was verified and approved by Project Management (%s) and forwarded to Admin Finance for JOM issuance.",
                    $locked['project_name'],
                    $locked['project_code'],
                    $actor['full_name'] ?? $actor['username']
                ),
                '/projects/' . $projectId
            );

            // Notify origin Marketing team
            if ($originDeptId !== null && $originDeptId !== $afinDeptId) {
                Notification::notifyDepartmentUsers(
                    $originDeptId,
                    $projectId,
                    'project.forwarded_to_admin_finance',
                    "Project Forwarded to Admin Finance: {$locked['project_code']}",
                    sprintf(
                        "Project '%s' (%s) was approved by Project Management and forwarded to Admin Finance.",
                        $locked['project_name'],
                        $locked['project_code']
                    ),
                    '/projects/' . $projectId
                );
            }

            ActivityLogger::log(
                'project.forwarded_to_admin_finance',
                'project',
                $projectId,
                "Project Management reviewer '{$actor['full_name']}' forwarded approved project '{$locked['project_code']}' ({$locked['project_name']}) to Admin Finance.",
                $request,
                $actorId,
                false
            );

            return ['success' => true];
        });
    }

    /**
     * Generate a unique Job Order Memorandum (JOM) number on the server.
     * Uses configurable prefix and series format (e.g. JOM-YYYYMM-XXXX).
     * Enforces database-level uniqueness and sequential increment.
     * Safely preserved for Admin Finance JOM issuance stage.
     */
    public static function generateUniqueJomNumber(): string
    {
        $prefix = (string) Config::get('pm.jom.prefix', 'JOM');
        $format = (string) Config::get('pm.jom.format', '{PREFIX}-{YEAR}{MONTH}-{SEQ}');
        $padding = max(3, (int) Config::get('pm.jom.padding', 4));

        $year = date('Y');
        $yearShort = date('y');
        $month = date('m');

        // Series pattern to find highest existing sequence for current monthly/yearly batch
        $seriesPrefix = str_replace(
            ['{PREFIX}', '{YEAR}', '{YEAR_SHORT}', '{MONTH}', '{SEQ}'],
            [$prefix, $year, $yearShort, $month, ''],
            $format
        );

        $latestJom = Project::findLatestJomNumberByPrefix($seriesPrefix . '%');
        $nextSeq = 1;

        if ($latestJom !== null) {
            if (preg_match('/(\d+)$/', $latestJom, $m)) {
                $nextSeq = ((int) $m[1]) + 1;
            }
        }

        // Loop up to 30 attempts to avoid collision under concurrent transactions
        for ($i = 0; $i < 30; $i++) {
            $seqFormatted = str_pad((string) ($nextSeq + $i), $padding, '0', STR_PAD_LEFT);
            $candidate = str_replace(
                ['{PREFIX}', '{YEAR}', '{YEAR_SHORT}', '{MONTH}', '{SEQ}'],
                [$prefix, $year, $yearShort, $month, $seqFormatted],
                $format
            );

            if (!Project::jomNumberExists($candidate)) {
                return $candidate;
            }
        }

        return sprintf('%s-%s%s-%04d-%s', $prefix, $year, $month, $nextSeq + 30, strtoupper(bin2hex(random_bytes(2))));
    }

    /**
     * Mark an approved incoming project as Under Admin Finance Review.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $actor
     * @return array{success: bool, error?: string}
     */
    public static function startAdminFinanceReview(
        array $project,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canStartAdminFinanceReview($actor, $project)) {
            return [
                'success' => false,
                'error' => 'This project cannot be marked as Under Admin Finance Review in its current status.',
            ];
        }

        $projectId = (int) $project['id'];
        $actorId = (int) $actor['id'];
        $fromStatus = (string) ($project['status'] ?? Project::STATUS_FORWARDED_TO_ADMIN_FINANCE);
        $afinDept = Department::findByCode(Department::CODE_ADMIN_FINANCE);
        $afinDeptId = $afinDept !== null ? (int) $afinDept['id'] : ($project['current_department_id'] ? (int) $project['current_department_id'] : null);

        return Database::transaction(static function () use ($project, $projectId, $actorId, $actor, $fromStatus, $afinDeptId, $request): array {
            $updated = Project::markUnderAdminFinanceReview($projectId, $actorId);
            if (!$updated) {
                return [
                    'success' => false,
                    'error' => 'Unable to mark project as Under Admin Finance Review.',
                ];
            }

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $fromStatus,
                'to_status' => Project::STATUS_UNDER_ADMIN_FINANCE_REVIEW,
                'action' => 'started_admin_finance_review',
                'from_department_id' => $afinDeptId,
                'to_department_id' => $afinDeptId,
                'acted_by' => $actorId,
                'notes' => 'Admin Finance received and started reviewing the Project Management approved project for JOM issuance.',
            ]);

            ActivityLogger::log(
                'project.received_by_admin_finance',
                'project',
                $projectId,
                "Admin Finance user '{$actor['full_name']}' received and started reviewing project '{$project['project_code']}' ({$project['project_name']}) for JOM number issuance.",
                $request,
                $actorId,
                false
            );

            return ['success' => true];
        });
    }

    /**
     * Authorize and issue official JOM (Job Order Memorandum) number by Admin Finance (or Administrator).
     * Admin Finance is the ONLY department responsible for issuing JOM numbers.
     * Enforces uniqueness, transaction safety, and idempotency.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $actor
     * @return array{success: bool, jom_number?: string, error?: string}
     */
    public static function issueJomNumber(
        array $project,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::isAdmin($actor) && !AuthorizationService::isAdminFinanceUser($actor)) {
            return [
                'success' => false,
                'error' => 'Only Admin Finance is authorized to issue JOM numbers.',
            ];
        }

        if (!AuthorizationService::canIssueJomNumber($actor, $project)) {
            return [
                'success' => false,
                'error' => 'You do not have permission to issue a JOM number for this project, or the project has not been approved and forwarded by Project Management.',
            ];
        }

        // Check completeness of required Marketing documents
        $checklist = MarketingChecklistService::evaluateProject($project);
        if (!$checklist['is_complete']) {
            return [
                'success' => false,
                'error' => 'Cannot issue JOM number: required project documents are incomplete.',
            ];
        }

        $projectId = (int) $project['id'];
        $actorId = (int) $actor['id'];

        return Database::transaction(static function () use ($project, $projectId, $actorId, $actor, $request): array {
            $stmt = Database::connection()->prepare('SELECT * FROM projects WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $projectId]);
            $locked = $stmt->fetch();

            if (!$locked) {
                return ['success' => false, 'error' => 'Project record not found.'];
            }

            if (!empty($locked['jom_number'])) {
                return [
                    'success' => false,
                    'error' => "This project already has an issued JOM number ({$locked['jom_number']}). Duplicate issuance is prohibited.",
                ];
            }

            $currentStatus = (string) $locked['status'];

            if (!Project::isApprovedByPm($locked) || !Project::isForwardedToAdminFinance($locked)) {
                return [
                    'success' => false,
                    'error' => 'Cannot issue JOM number: project must be approved and forwarded by Project Management to Admin Finance first.',
                ];
            }

            $jomNumber = self::generateUniqueJomNumber();
            $assigned = Project::assignJomNumber($projectId, $jomNumber, $actorId, Project::STATUS_JOM_ISSUED);

            if (!$assigned) {
                return [
                    'success' => false,
                    'error' => 'Failed to issue JOM number: concurrent update detected.',
                ];
            }

            // Record workflow history
            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $currentStatus,
                'to_status' => Project::STATUS_JOM_ISSUED,
                'action' => 'issued_jom',
                'from_department_id' => $locked['current_department_id'],
                'to_department_id' => $locked['current_department_id'],
                'acted_by' => $actorId,
                'notes' => "Official Job Order Memorandum Number '{$jomNumber}' issued by Admin Finance ({$actor['full_name']}). Ready to forward JOM, SLD, and Marketing BOM to Operations.",
            ]);

            // Activity Log
            ActivityLogger::log(
                'project.jom_issued',
                'project',
                $projectId,
                "Admin Finance user '{$actor['full_name']}' (ID #{$actorId}) issued JOM number '{$jomNumber}' for project '{$locked['project_code']}' ({$locked['project_name']}, ID #{$projectId}).",
                $request,
                $actorId,
                false
            );

            // Notify Marketing department users
            if (!empty($locked['origin_department_id'])) {
                Notification::notifyDepartmentUsers(
                    (int) $locked['origin_department_id'],
                    $projectId,
                    'project.jom_issued',
                    "JOM Number Issued: {$jomNumber} ({$locked['project_code']})",
                    sprintf(
                        "Project '%s' (%s) was officially issued JOM Number %s by Admin Finance (%s).",
                        $locked['project_name'],
                        $locked['project_code'],
                        $jomNumber,
                        $actor['full_name'] ?? $actor['username']
                    ),
                    '/projects/' . $projectId
                );
            }

            // Notify Project Management users
            $pmDept = Department::findByCode(Department::CODE_PROJECT_MANAGEMENT);
            if ($pmDept !== null) {
                Notification::notifyDepartmentUsers(
                    (int) $pmDept['id'],
                    $projectId,
                    'project.jom_issued',
                    "JOM Number Issued: {$jomNumber} ({$locked['project_code']})",
                    sprintf(
                        "Admin Finance (%s) issued JOM Number %s for approved project '%s' (%s).",
                        $actor['full_name'] ?? $actor['username'],
                        $jomNumber,
                        $locked['project_name'],
                        $locked['project_code']
                    ),
                    '/projects/' . $projectId
                );
            }

            return [
                'success' => true,
                'jom_number' => $jomNumber,
            ];
        });
    }

    /**
     * Forward JOM Number, SLD, and Marketing BOM from Admin Finance to Operations.
     * Confirms all 3 items are present and readable, associates SLD and Marketing BOM document IDs
     * with the project and JOM, records sender and receiving department (OPS) + timestamp,
     * prevents duplicate forwarding, and notifies Operations.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $input
     * @param array<string, mixed> $actor
     * @return array{success: bool, error?: string}
     */
    public static function forwardToOperations(
        array $project,
        array $input,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canForwardToOperations($actor, $project)) {
            return [
                'success' => false,
                'error' => 'You do not have permission to forward this project to Operations, or the project has not been issued a JOM number or was already forwarded.',
            ];
        }

        $projectId = (int) $project['id'];
        $actorId = (int) $actor['id'];

        $forwardNotes = trim((string) ($input['operations_forward_notes'] ?? $input['forward_notes'] ?? ''));
        if (mb_strlen($forwardNotes, 'UTF-8') > 2000) {
            return [
                'success' => false,
                'error' => 'Forwarding notes must not exceed 2,000 characters.',
            ];
        }

        // Verify all 3 required handoff items: 1. JOM Number, 2. SLD, 3. Marketing BOM
        $jomNumber = trim((string) ($project['jom_number'] ?? ''));
        if ($jomNumber === '') {
            return [
                'success' => false,
                'error' => 'Cannot forward to Operations: JOM number must be issued by Admin Finance first.',
            ];
        }

        $handoffDocs = ProjectDocument::operationsHandoffDocuments($projectId);
        $sldDoc = $handoffDocs['sld'];
        $bomDoc = $handoffDocs['marketing_bom'];

        if (!$handoffDocs['is_ready'] || $sldDoc === null || $bomDoc === null) {
            return [
                'success' => false,
                'error' => 'Cannot forward to Operations: Missing required handoff document(s): ' . implode(', ', $handoffDocs['missing']) . '.',
            ];
        }

        if (
            DocumentStorageService::resolveVerifiedPath($sldDoc) === null
            || DocumentStorageService::resolveVerifiedPath($bomDoc) === null
        ) {
            return [
                'success' => false,
                'error' => 'Cannot forward to Operations: One or more required handoff document files (SLD or Marketing BOM) could not be verified on disk.',
            ];
        }

        $opsDept = Department::findByCode(Department::CODE_OPERATIONS);
        if ($opsDept === null) {
            return [
                'success' => false,
                'error' => 'Operations department configuration is missing.',
            ];
        }

        $afinDept = Department::findByCode(Department::CODE_ADMIN_FINANCE);
        $opsDeptId = (int) $opsDept['id'];
        $afinDeptId = $afinDept !== null ? (int) $afinDept['id'] : ($project['current_department_id'] ? (int) $project['current_department_id'] : null);

        $sldSummary = sprintf('SLD (%s, v%d)', $sldDoc['original_filename'], (int) $sldDoc['version_number']);
        $bomSummary = sprintf('Marketing BOM (%s, v%d)', $bomDoc['original_filename'], (int) $bomDoc['version_number']);
        $notesText = $forwardNotes !== ''
            ? $forwardNotes
            : "Admin Finance forwarded JOM Number '{$jomNumber}', {$sldSummary}, and {$bomSummary} to Operations.";

        return Database::transaction(static function () use (
            $projectId,
            $opsDeptId,
            $afinDeptId,
            $actorId,
            $actor,
            $sldDoc,
            $bomDoc,
            $sldSummary,
            $bomSummary,
            $notesText,
            $request
        ): array {
            $stmt = Database::connection()->prepare('SELECT * FROM projects WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $projectId]);
            $locked = $stmt->fetch();

            if (!$locked) {
                return ['success' => false, 'error' => 'Project record not found.'];
            }

            if (Project::isForwardedToOperations($locked)) {
                return [
                    'success' => false,
                    'error' => 'This project has already been forwarded to Operations. Duplicate forwarding is prevented.',
                ];
            }

            if (empty($locked['jom_number'])) {
                return [
                    'success' => false,
                    'error' => 'Cannot forward to Operations before JOM number issuance.',
                ];
            }

            $fromStatus = (string) $locked['status'];
            $jomNumber = (string) $locked['jom_number'];

            $forwarded = Project::markForwardedToOperations(
                $projectId,
                $opsDeptId,
                $actorId,
                (int) $sldDoc['id'],
                (int) $bomDoc['id'],
                $notesText
            );

            if (!$forwarded) {
                return [
                    'success' => false,
                    'error' => 'This project has already been forwarded to Operations.',
                ];
            }

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $fromStatus,
                'to_status' => Project::STATUS_FORWARDED_TO_OPERATIONS,
                'action' => 'forwarded_to_operations',
                'from_department_id' => $afinDeptId,
                'to_department_id' => $opsDeptId,
                'acted_by' => $actorId,
                'notes' => $notesText,
                'flagged_documents' => json_encode(['SLD', 'BOM']),
            ]);

            // Notify Operations incoming queue users
            Notification::notifyDepartmentUsers(
                $opsDeptId,
                $projectId,
                'project.forwarded_to_operations',
                "Forwarded to Operations: {$jomNumber} ({$locked['project_code']})",
                sprintf(
                    "Admin Finance (%s) forwarded project '%s' (%s) with JOM Number %s, %s, and %s to Operations.",
                    $actor['full_name'] ?? $actor['username'],
                    $locked['project_name'],
                    $locked['project_code'],
                    $jomNumber,
                    $sldSummary,
                    $bomSummary
                ),
                '/projects/' . $projectId
            );

            // Notify origin Marketing team
            if (!empty($locked['origin_department_id'])) {
                Notification::notifyDepartmentUsers(
                    (int) $locked['origin_department_id'],
                    $projectId,
                    'project.forwarded_to_operations',
                    "Forwarded to Operations: {$jomNumber} ({$locked['project_code']})",
                    sprintf(
                        "Project '%s' (%s, JOM: %s) and its SLD & Marketing BOM were forwarded by Admin Finance to Operations.",
                        $locked['project_name'],
                        $locked['project_code'],
                        $jomNumber
                    ),
                    '/projects/' . $projectId
                );
            }

            ActivityLogger::log(
                'project.forwarded_to_operations',
                'project',
                $projectId,
                "Admin Finance user '{$actor['full_name']}' forwarded project '{$locked['project_code']}' (JOM: '{$jomNumber}') along with {$sldSummary} [doc #{$sldDoc['id']}] and {$bomSummary} [doc #{$bomDoc['id']}] from Admin Finance (ADMFIN) to receiving department Operations (OPS).",
                $request,
                $actorId,
                false
            );

            return ['success' => true];
        });
    }

    /**
     * @param array<string, mixed> $actor
     */
    private static function resolvePermittedDepartmentId(array $actor, string $deptIdRaw, Validator $validator): ?int
    {
        if (AuthorizationService::isAdmin($actor)) {
            if ($deptIdRaw === '') {
                $mkt = Department::findByCode(Department::CODE_MARKETING);
                return $mkt !== null ? (int) $mkt['id'] : null;
            }

            if (!ctype_digit($deptIdRaw)) {
                $validator->addError('current_department_id', 'Selected department is invalid.');
                return null;
            }

            $dept = Department::findById((int) $deptIdRaw);
            if ($dept === null) {
                $validator->addError('current_department_id', 'Selected department does not exist.');
                return null;
            }

            return (int) $dept['id'];
        }

        if ($actor['department_id'] !== null) {
            return (int) $actor['department_id'];
        }

        $mkt = Department::findByCode(Department::CODE_MARKETING);
        return $mkt !== null ? (int) $mkt['id'] : null;
    }
}
