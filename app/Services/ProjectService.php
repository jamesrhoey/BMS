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

        $actorId = (int) $actor['id'];
        $mktDept = Department::findByCode(Department::CODE_MARKETING);
        $mktDeptId = $mktDept !== null ? (int) $mktDept['id'] : null;
        $originDepartmentId = AuthorizationService::isMarketingUser($actor)
            ? ($mktDeptId ?? $currentDepartmentId)
            : ($currentDepartmentId ?? $mktDeptId);
        $workflowType = (string) Config::get('marketing.default_workflow', 'standard');

        $created = Database::transaction(static function () use (
            $projectName,
            $clientName,
            $clientContact,
            $projectLocation,
            $projectDescription,
            $workflowType,
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
                'notes' => 'Draft project registered.',
            ]);

            ActivityLogger::log(
                'project.created',
                'project',
                $projectId,
                "Registered draft project '{$projectCode}' ({$projectName}) for client '{$clientName}'.",
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
                "Blocked submission of project '{$project['project_code']}' due to missing required Marketing documents: " . implode(', ', $missingList) . '.',
                $request,
                (int) $actor['id'],
                false
            );

            return [
                'success' => false,
                'missing_documents' => $missingList,
                'error' => 'Cannot submit to Project Management. Required documents missing: ' . implode(', ', $missingList) . '.',
            ];
        }

        $pmDept = Department::findByCode(Department::CODE_PROJECT_MANAGEMENT);
        if ($pmDept === null) {
            return [
                'success' => false,
                'error' => 'Project Management department record is missing from system configuration.',
            ];
        }

        $pmDeptId = (int) $pmDept['id'];
        $projectId = (int) $project['id'];
        $actorId = (int) $actor['id'];

        $isResubmission = $currentStatus === Project::STATUS_RETURNED_FOR_CORRECTIONS
            || !empty($project['returned_at']);

        $targetStatus = $isResubmission
            ? Project::STATUS_RESUBMITTED_FOR_VERIFICATION
            : Project::STATUS_SUBMITTED_FOR_VERIFICATION;

        $historyAction = $isResubmission
            ? 'resubmitted_for_verification'
            : 'submitted_for_verification';

        $logAction = $isResubmission
            ? 'project.resubmitted_to_pm'
            : 'project.submitted_to_pm';

        $notifType = $isResubmission
            ? 'project.resubmitted_to_pm'
            : 'project.submitted_to_pm';

        $notifTitle = $isResubmission
            ? "Project Resubmitted for Document Verification: {$project['project_code']}"
            : "New Project Submitted for Document Verification: {$project['project_code']}";

        $notifMessage = sprintf(
            "%s (%s) was %s by %s with all %d required Marketing documents uploaded and awaits Project Management completeness verification.",
            $project['project_name'],
            $project['project_code'],
            $isResubmission ? 'resubmitted after corrections' : 'submitted',
            $actor['full_name'] ?? $actor['username'],
            $checklist['required_count']
        );

        Database::transaction(static function () use (
            $project,
            $projectId,
            $currentStatus,
            $targetStatus,
            $historyAction,
            $logAction,
            $pmDeptId,
            $actorId,
            $submissionNotes,
            $notifType,
            $notifTitle,
            $notifMessage,
            $request
        ): void {
            Project::markSubmittedToProjectManagement($projectId, $targetStatus, $pmDeptId, $actorId);

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $currentStatus,
                'to_status' => $targetStatus,
                'action' => $historyAction,
                'from_department_id' => $project['current_department_id'],
                'to_department_id' => $pmDeptId,
                'acted_by' => $actorId,
                'notes' => $submissionNotes !== ''
                    ? $submissionNotes
                    : 'All required Marketing checklist documents uploaded and forwarded to Project Management for verification.',
            ]);

            Notification::notifyDepartmentUsers(
                $pmDeptId,
                $projectId,
                $notifType,
                $notifTitle,
                $notifMessage,
                '/projects/' . $projectId
            );

            ActivityLogger::log(
                $logAction,
                'project',
                $projectId,
                "Forwarded project '{$project['project_code']}' ({$project['project_name']}) to Project Management with status '{$targetStatus}'.",
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
        if (!in_array($currentStatus, [Project::STATUS_SUBMITTED_FOR_VERIFICATION, Project::STATUS_RESUBMITTED_FOR_VERIFICATION, Project::STATUS_SUBMITTED], true)) {
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
     * Approve and complete the document verification stage for a project.
     * Enforces complete presence of all required documents (6 of 6).
     * Does NOT issue a JOM number (pending business-rule confirmation).
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

        $targetStatus = Project::STATUS_VERIFICATION_COMPLETED;
        $notesText = $verificationNotes !== ''
            ? $verificationNotes
            : 'All required Marketing documents verified complete and readable by Project Management.';

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

            // Notify origin/Marketing department users of completion
            if ($originDeptId !== null) {
                Notification::notifyDepartmentUsers(
                    $originDeptId,
                    $projectId,
                    'project.verification_completed',
                    "Document Verification Completed: {$project['project_code']}",
                    sprintf(
                        "%s (%s) document verification was completed by %s. All %d required documents verified.",
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
                "Project Management reviewer '{$actor['full_name']}' completed document verification for '{$project['project_code']}'. Ready for JOM issuance.",
                $request,
                $actorId,
                false
            );
        });

        return ['success' => true];
    }

    /**
     * Generate a unique Job Order Memorandum (JOM) number on the server.
     * Uses configurable prefix and series format (e.g. JOM-YYYYMM-XXXX).
     * Enforces database-level uniqueness and sequential increment.
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
     * Authorize and issue official JOM (Job Order Memorandum) number by Project Management.
     * Requires document verification completion and verified status.
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
        if (!AuthorizationService::canIssueJomNumber($actor, $project)) {
            return [
                'success' => false,
                'error' => 'You do not have permission to issue a JOM number for this project, or the project is not in verified status.',
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
            $vStatus = (string) $locked['verification_status'];

            if ($currentStatus !== Project::STATUS_VERIFICATION_COMPLETED && $vStatus !== Project::VERIFICATION_VERIFIED) {
                return [
                    'success' => false,
                    'error' => 'Cannot issue JOM number: document verification must be completed first.',
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
                'notes' => "Official Job Order Memorandum Number '{$jomNumber}' issued by Project Management. Authorized for downstream execution.",
            ]);

            // Activity Log
            ActivityLogger::log(
                'project.jom_issued',
                'project',
                $projectId,
                "Project Management reviewer '{$actor['full_name']}' issued JOM number '{$jomNumber}' for project '{$locked['project_code']}' ({$locked['project_name']}).",
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
                        "Project '%s' (%s) was officially issued JOM Number %s by Project Management (%s).",
                        $locked['project_name'],
                        $locked['project_code'],
                        $jomNumber,
                        $actor['full_name'] ?? $actor['username']
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
