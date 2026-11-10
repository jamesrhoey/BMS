<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Models\ActualBom;
use App\Models\Department;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectWorkflowHistory;
use App\Models\PurchaseRequest;

final class ActualBomService
{
    /**
     * Generate a unique Actual BOM reference number on the server.
     * Format is configurable via config/pm.php or .env (default: ABOM-YYYYMM-XXXX).
     */
    public static function generateUniqueActualBomNumber(): string
    {
        $prefix = (string) Config::get('pm.actual_bom.prefix', 'ABOM');
        $format = (string) Config::get('pm.actual_bom.format', '{PREFIX}-{YEAR}{MONTH}-{SEQ}');
        $padding = max(3, (int) Config::get('pm.actual_bom.padding', 4));

        $year = date('Y');
        $yearShort = date('y');
        $month = date('m');

        $seriesPrefix = str_replace(
            ['{PREFIX}', '{YEAR}', '{YEAR_SHORT}', '{MONTH}', '{SEQ}'],
            [$prefix, $year, $yearShort, $month, ''],
            $format
        );

        $latest = ActualBom::findLatestNumberByPrefix($seriesPrefix . '%');
        $nextSeq = 1;
        if ($latest !== null && preg_match('/(\d+)$/', $latest, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        for ($i = 0; $i < 30; $i++) {
            $seqFormatted = str_pad((string) ($nextSeq + $i), $padding, '0', STR_PAD_LEFT);
            $candidate = str_replace(
                ['{PREFIX}', '{YEAR}', '{YEAR_SHORT}', '{MONTH}', '{SEQ}'],
                [$prefix, $year, $yearShort, $month, $seqFormatted],
                $format
            );
            if (!ActualBom::actualBomNumberExists($candidate)) {
                return $candidate;
            }
        }

        return sprintf('%s-%s%s-%04d-%s', $prefix, $year, $month, $nextSeq + 30, strtoupper(bin2hex(random_bytes(2))));
    }

    /**
     * Upload/save a separate Actual BOM with actual equipment/material specifications.
     * Preserves all previous versions of Actual BOM documents in project_documents and never overwrites Marketing BOM.
     * Optionally submits the Actual BOM immediately to Admin Finance.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $pr
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $actualBomFile
     * @param array<string, mixed>|null $supportingFile
     * @param array<string, mixed> $actor
     * @return array{success: bool, actual_bom_id?: int, actual_bom_number?: string, error?: string}
     */
    public static function saveActualBom(
        array $project,
        array $pr,
        array $input,
        ?array $actualBomFile,
        ?array $supportingFile,
        array $actor,
        Request $request,
        bool $submitImmediately = false
    ): array {
        $projectId = (int) $project['id'];
        $prId = (int) $pr['id'];
        $existingActualBom = ActualBom::findByPrId($prId);

        if (!AuthorizationService::canUploadActualBom($actor, $project, $pr, $existingActualBom)) {
            return [
                'success' => false,
                'error' => 'You are not authorized to upload or modify the Actual BOM for this project.',
            ];
        }

        $remarks = trim((string) ($input['actual_bom_remarks'] ?? $input['remarks'] ?? ''));
        if (mb_strlen($remarks, 'UTF-8') > 2000) {
            return [
                'success' => false,
                'error' => 'Actual BOM remarks must not exceed 2,000 characters.',
            ];
        }

        $parsedItems = self::parseActualBomItems($input);
        if ($parsedItems === []) {
            return [
                'success' => false,
                'error' => 'Please enter at least one valid Actual BOM line item with item description, actual specification, and positive quantity.',
            ];
        }

        $hasNewBomFile = $actualBomFile !== null && isset($actualBomFile['error']) && (int) $actualBomFile['error'] !== UPLOAD_ERR_NO_FILE;
        if (!$hasNewBomFile && ($existingActualBom === null || empty($existingActualBom['document_id']))) {
            return [
                'success' => false,
                'error' => 'Please upload the Actual BOM document file.',
            ];
        }

        // If returning from a correction request, require a new Actual BOM file upload so previous versions remain preserved
        if ($existingActualBom !== null && ($existingActualBom['status'] ?? '') === ActualBom::STATUS_RETURNED_FOR_CORRECTIONS && !$hasNewBomFile) {
            return [
                'success' => false,
                'error' => 'Please upload a corrected Actual BOM document file when resubmitting after return for corrections.',
            ];
        }

        $newDocumentId = null;
        $newVersionNumber = $existingActualBom !== null ? (int) ($existingActualBom['version_number'] ?? 1) : 1;

        if ($hasNewBomFile) {
            $uploadNotes = $remarks !== '' ? mb_substr($remarks, 0, 255, 'UTF-8') : 'OPS Finance Actual BOM Upload';
            $uploadRes = DocumentStorageService::storeWorkflowDocument(
                $project,
                $actualBomFile,
                ActualBom::DOC_TYPE_ACTUAL_BOM,
                $actor,
                $request,
                $uploadNotes
            );
            if (!$uploadRes['success']) {
                return [
                    'success' => false,
                    'error' => $uploadRes['error'] ?? 'Failed to upload Actual BOM document.',
                ];
            }
            $newDocumentId = (int) $uploadRes['document_id'];
            $newVersionNumber = (int) $uploadRes['version_number'];
        }

        $newSupportingDocId = null;
        if ($supportingFile !== null && isset($supportingFile['error']) && (int) $supportingFile['error'] !== UPLOAD_ERR_NO_FILE) {
            $suppRes = DocumentStorageService::storeWorkflowDocument(
                $project,
                $supportingFile,
                ActualBom::DOC_TYPE_SUPPORTING,
                $actor,
                $request,
                'Actual BOM Supporting Document'
            );
            if (!$suppRes['success']) {
                return [
                    'success' => false,
                    'error' => $suppRes['error'] ?? 'Failed to upload Actual BOM supporting document.',
                ];
            }
            $newSupportingDocId = (int) $suppRes['document_id'];
        }

        $opfinDept = Department::findByCode(Department::CODE_OPERATION_FINANCE);
        $opfinDeptId = $opfinDept !== null ? (int) $opfinDept['id'] : null;
        $actorId = (int) $actor['id'];
        $jomNumber = (string) ($project['jom_number'] ?? $pr['jom_number'] ?? '');

        $savedResult = Database::transaction(static function () use (
            $project,
            $projectId,
            $pr,
            $prId,
            $jomNumber,
            $existingActualBom,
            $newDocumentId,
            $newSupportingDocId,
            $newVersionNumber,
            $remarks,
            $parsedItems,
            $opfinDeptId,
            $actorId,
            $actor,
            $request
        ): array {
            $fromStatus = (string) ($project['status'] ?? Project::STATUS_PR_SUBMITTED_TO_OPS_FINANCE);

            // Ensure PR is marked as reviewed by OPS Finance if still in Submitted to OPS Finance
            if (($pr['status'] ?? '') === PurchaseRequest::STATUS_SUBMITTED_TO_OPS_FINANCE) {
                PurchaseRequest::markUnderOpsFinanceReview($prId, $actorId);
            }

            if ($existingActualBom === null) {
                $abNumber = self::generateUniqueActualBomNumber();
                $abId = ActualBom::create([
                    'actual_bom_number' => $abNumber,
                    'project_id' => $projectId,
                    'jom_number' => $jomNumber,
                    'purchase_request_id' => $prId,
                    'version_number' => $newVersionNumber,
                    'remarks' => $remarks,
                    'document_id' => $newDocumentId,
                    'supporting_document_id' => $newSupportingDocId,
                    'status' => ActualBom::STATUS_DRAFT,
                    'uploaded_by' => $actorId,
                ], $parsedItems);

                Project::updateStatus($projectId, Project::STATUS_ACTUAL_BOM_PENDING_UPLOAD);

                ProjectWorkflowHistory::record([
                    'project_id' => $projectId,
                    'from_status' => $fromStatus,
                    'to_status' => Project::STATUS_ACTUAL_BOM_PENDING_UPLOAD,
                    'action' => 'uploaded_actual_bom',
                    'from_department_id' => $opfinDeptId,
                    'to_department_id' => $opfinDeptId,
                    'acted_by' => $actorId,
                    'notes' => sprintf(
                        "OPS Finance (%s) uploaded Actual BOM '%s' (v%d, %d line item%s) for PR '%s' (JOM: '%s').",
                        $actor['full_name'] ?? $actor['username'],
                        $abNumber,
                        $newVersionNumber,
                        count($parsedItems),
                        count($parsedItems) === 1 ? '' : 's',
                        $pr['pr_number'],
                        $jomNumber
                    ),
                ]);

                ActivityLogger::log(
                    'actual_bom.uploaded',
                    'project',
                    $projectId,
                    "OPS Finance user '{$actor['full_name']}' uploaded Actual BOM '{$abNumber}' (v{$newVersionNumber}, " . count($parsedItems) . " line items) for PR '{$pr['pr_number']}' on project '{$project['project_code']}' (JOM: '{$jomNumber}').",
                    $request,
                    $actorId,
                    false
                );

                return [
                    'actual_bom_id' => $abId,
                    'actual_bom_number' => $abNumber,
                ];
            }

            $abId = (int) $existingActualBom['id'];
            $abNumber = (string) $existingActualBom['actual_bom_number'];

            ActualBom::updateRecord($abId, [
                'version_number' => $newVersionNumber,
                'remarks' => $remarks,
                'document_id' => $newDocumentId,
                'supporting_document_id' => $newSupportingDocId,
                'uploaded_by' => $actorId,
            ], $parsedItems);

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $fromStatus,
                'to_status' => $fromStatus,
                'action' => 'uploaded_actual_bom',
                'from_department_id' => $opfinDeptId,
                'to_department_id' => $opfinDeptId,
                'acted_by' => $actorId,
                'notes' => sprintf(
                    "OPS Finance (%s) updated Actual BOM '%s' (v%d, %d line item%s) for PR '%s'.",
                    $actor['full_name'] ?? $actor['username'],
                    $abNumber,
                    $newVersionNumber,
                    count($parsedItems),
                    count($parsedItems) === 1 ? '' : 's',
                    $pr['pr_number']
                ),
            ]);

            ActivityLogger::log(
                'actual_bom.uploaded',
                'project',
                $projectId,
                "OPS Finance user '{$actor['full_name']}' uploaded updated Actual BOM '{$abNumber}' (v{$newVersionNumber}, prior versions preserved) for PR '{$pr['pr_number']}' on project '{$project['project_code']}'.",
                $request,
                $actorId,
                false
            );

            return [
                'actual_bom_id' => $abId,
                'actual_bom_number' => $abNumber,
            ];
        });

        if ($submitImmediately) {
            $freshAb = ActualBom::findById((int) $savedResult['actual_bom_id']);
            $freshProject = Project::findById($projectId) ?? $project;
            $freshPr = PurchaseRequest::findById($prId) ?? $pr;
            if ($freshAb !== null) {
                $submitRes = self::submitToAdminFinance($freshProject, $freshPr, $freshAb, $actor, $request);
                if (!$submitRes['success']) {
                    return $submitRes;
                }
            }
        }

        return [
            'success' => true,
            'actual_bom_id' => $savedResult['actual_bom_id'],
            'actual_bom_number' => $savedResult['actual_bom_number'],
        ];
    }

    /**
     * Submit and forward the Actual BOM with specifications from OPS Finance to Admin Finance.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $pr
     * @param array<string, mixed> $actualBom
     * @param array<string, mixed> $actor
     * @return array{success: bool, actual_bom_number?: string, error?: string}
     */
    public static function submitToAdminFinance(
        array $project,
        array $pr,
        array $actualBom,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canSubmitActualBom($actor, $project, $pr, $actualBom)) {
            return [
                'success' => false,
                'error' => 'This Actual BOM cannot be submitted to Admin Finance (already submitted, unauthorized, or missing uploaded document/specifications).',
            ];
        }

        $afinDept = Department::findByCode(Department::CODE_ADMIN_FINANCE);
        if ($afinDept === null) {
            return [
                'success' => false,
                'error' => 'Admin Finance department configuration is missing.',
            ];
        }

        $opfinDept = Department::findByCode(Department::CODE_OPERATION_FINANCE);
        $opfinDeptId = $opfinDept !== null ? (int) $opfinDept['id'] : null;
        $afinDeptId = (int) $afinDept['id'];
        $projectId = (int) $project['id'];
        $prId = (int) $pr['id'];
        $abId = (int) $actualBom['id'];
        $abNumber = (string) $actualBom['actual_bom_number'];
        $actorId = (int) $actor['id'];

        return Database::transaction(static function () use (
            $project,
            $projectId,
            $pr,
            $prId,
            $actualBom,
            $abId,
            $abNumber,
            $opfinDeptId,
            $afinDeptId,
            $actorId,
            $actor,
            $request
        ): array {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT * FROM actual_boms WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $abId]);
            $lockedAb = $stmt->fetch();

            if (!$lockedAb || !ActualBom::isEditableByOpsFinance($lockedAb)) {
                return [
                    'success' => false,
                    'error' => 'This Actual BOM has already been submitted to Admin Finance. Duplicate submission is prevented.',
                ];
            }

            $marked = ActualBom::markSubmittedToAdminFinance($abId, $actorId);
            if (!$marked) {
                return [
                    'success' => false,
                    'error' => 'Unable to submit Actual BOM to Admin Finance.',
                ];
            }

            PurchaseRequest::markActualBomSubmitted($prId);

            $fromStatus = (string) ($project['status'] ?? Project::STATUS_ACTUAL_BOM_PENDING_UPLOAD);
            $updProj = $pdo->prepare(
                'UPDATE projects
                 SET status = :status,
                     current_department_id = :dept_id,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $updProj->execute([
                'id' => $projectId,
                'status' => Project::STATUS_ACTUAL_BOM_SUBMITTED_TO_AFIN,
                'dept_id' => $afinDeptId,
            ]);

            $version = (int) ($lockedAb['version_number'] ?? 1);
            $notesText = sprintf(
                "OPS Finance (%s) submitted Actual BOM '%s' (v%d) for PR '%s' (JOM: %s) to Admin Finance.",
                $actor['full_name'] ?? $actor['username'],
                $abNumber,
                $version,
                $pr['pr_number'],
                $project['jom_number'] ?? ''
            );

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $fromStatus,
                'to_status' => Project::STATUS_ACTUAL_BOM_SUBMITTED_TO_AFIN,
                'action' => 'submitted_actual_bom_to_admin_finance',
                'from_department_id' => $opfinDeptId,
                'to_department_id' => $afinDeptId,
                'acted_by' => $actorId,
                'notes' => $notesText,
            ]);

            Notification::notifyDepartmentUsers(
                $afinDeptId,
                $projectId,
                'actual_bom.submitted_to_admin_finance',
                "Actual BOM Submitted: {$abNumber} ({$project['project_code']})",
                sprintf(
                    "OPS Finance (%s) submitted Actual BOM %s (v%d) for PR %s (JOM: %s) to Admin Finance for review.",
                    $actor['full_name'] ?? $actor['username'],
                    $abNumber,
                    $version,
                    $pr['pr_number'],
                    $project['jom_number'] ?? ''
                ),
                '/projects/' . $projectId
            );

            ActivityLogger::log(
                'actual_bom.submitted',
                'project',
                $projectId,
                "OPS Finance user '{$actor['full_name']}' submitted Actual BOM '{$abNumber}' (v{$version}) for PR '{$pr['pr_number']}' (JOM: '{$project['jom_number']}') on project '{$project['project_code']}' to Admin Finance.",
                $request,
                $actorId,
                false
            );

            return [
                'success' => true,
                'actual_bom_number' => $abNumber,
            ];
        });
    }

    /**
     * Admin Finance marks the submitted Actual BOM as Received, Under Review, Returned for Corrections, or Accepted.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $pr
     * @param array<string, mixed> $actualBom
     * @param array<string, mixed> $input
     * @param array<string, mixed> $actor
     * @return array{success: bool, new_status?: string, error?: string}
     */
    public static function reviewActualBomByAdminFinance(
        array $project,
        array $pr,
        array $actualBom,
        string $decision,
        array $input,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canReviewActualBom($actor, $project, $actualBom)) {
            return [
                'success' => false,
                'error' => 'You are not authorized to review or update the status of this Actual BOM.',
            ];
        }

        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['received', 'under_review', 'return', 'accept'], true)) {
            return [
                'success' => false,
                'error' => 'Invalid Actual BOM review decision.',
            ];
        }

        $notes = trim((string) ($input['review_notes'] ?? $input['return_reason'] ?? $input['acceptance_notes'] ?? ''));
        if ($decision === 'return' && $notes === '') {
            return [
                'success' => false,
                'error' => 'Please provide correction notes explaining why the Actual BOM is being returned to OPS Finance.',
            ];
        }

        $afinDept = Department::findByCode(Department::CODE_ADMIN_FINANCE);
        $opfinDept = Department::findByCode(Department::CODE_OPERATION_FINANCE);
        $afinDeptId = $afinDept !== null ? (int) $afinDept['id'] : null;
        $opfinDeptId = $opfinDept !== null ? (int) $opfinDept['id'] : null;
        $projectId = (int) $project['id'];
        $abId = (int) $actualBom['id'];
        $abNumber = (string) $actualBom['actual_bom_number'];
        $actorId = (int) $actor['id'];

        return Database::transaction(static function () use (
            $project,
            $projectId,
            $pr,
            $actualBom,
            $abId,
            $abNumber,
            $decision,
            $notes,
            $afinDeptId,
            $opfinDeptId,
            $actorId,
            $actor,
            $request
        ): array {
            $fromProjStatus = (string) ($project['status'] ?? Project::STATUS_ACTUAL_BOM_SUBMITTED_TO_AFIN);
            $pdo = Database::connection();

            if ($decision === 'received') {
                ActualBom::markReceivedByAdminFinance($abId, $actorId);
                Project::updateStatus($projectId, Project::STATUS_ACTUAL_BOM_RECEIVED);

                ProjectWorkflowHistory::record([
                    'project_id' => $projectId,
                    'from_status' => $fromProjStatus,
                    'to_status' => Project::STATUS_ACTUAL_BOM_RECEIVED,
                    'action' => 'admin_finance_received_actual_bom',
                    'from_department_id' => $afinDeptId,
                    'to_department_id' => $afinDeptId,
                    'acted_by' => $actorId,
                    'notes' => $notes !== '' ? $notes : "Admin Finance ({$actor['full_name']}) marked Actual BOM '{$abNumber}' as Received.",
                ]);

                ActivityLogger::log(
                    'actual_bom.received',
                    'project',
                    $projectId,
                    "Admin Finance user '{$actor['full_name']}' marked Actual BOM '{$abNumber}' as Received for project '{$project['project_code']}'.",
                    $request,
                    $actorId,
                    false
                );

                return ['success' => true, 'new_status' => ActualBom::STATUS_RECEIVED];
            }

            if ($decision === 'under_review') {
                ActualBom::markUnderReviewByAdminFinance($abId, $actorId);
                Project::updateStatus($projectId, Project::STATUS_ACTUAL_BOM_UNDER_REVIEW);

                ProjectWorkflowHistory::record([
                    'project_id' => $projectId,
                    'from_status' => $fromProjStatus,
                    'to_status' => Project::STATUS_ACTUAL_BOM_UNDER_REVIEW,
                    'action' => 'admin_finance_reviewing_actual_bom',
                    'from_department_id' => $afinDeptId,
                    'to_department_id' => $afinDeptId,
                    'acted_by' => $actorId,
                    'notes' => $notes !== '' ? $notes : "Admin Finance ({$actor['full_name']}) marked Actual BOM '{$abNumber}' as Under Review.",
                ]);

                ActivityLogger::log(
                    'actual_bom.reviewed',
                    'project',
                    $projectId,
                    "Admin Finance user '{$actor['full_name']}' marked Actual BOM '{$abNumber}' as Under Review for project '{$project['project_code']}'.",
                    $request,
                    $actorId,
                    false
                );

                return ['success' => true, 'new_status' => ActualBom::STATUS_UNDER_REVIEW];
            }

            if ($decision === 'return') {
                ActualBom::markReturnedForCorrections($abId, $actorId, $notes);

                $upd = $pdo->prepare(
                    'UPDATE projects
                     SET status = :status,
                         current_department_id = COALESCE(:opfin_dept, current_department_id),
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $upd->execute([
                    'id' => $projectId,
                    'status' => Project::STATUS_ACTUAL_BOM_RETURNED,
                    'opfin_dept' => $opfinDeptId,
                ]);

                ProjectWorkflowHistory::record([
                    'project_id' => $projectId,
                    'from_status' => $fromProjStatus,
                    'to_status' => Project::STATUS_ACTUAL_BOM_RETURNED,
                    'action' => 'returned_actual_bom_for_corrections',
                    'from_department_id' => $afinDeptId,
                    'to_department_id' => $opfinDeptId,
                    'acted_by' => $actorId,
                    'notes' => "Admin Finance ({$actor['full_name']}) returned Actual BOM '{$abNumber}' to OPS Finance for corrections: {$notes}",
                ]);

                if ($opfinDeptId !== null) {
                    Notification::notifyDepartmentUsers(
                        $opfinDeptId,
                        $projectId,
                        'actual_bom.returned_for_corrections',
                        "Actual BOM Returned: {$abNumber} ({$project['project_code']})",
                        "Admin Finance ({$actor['full_name']}) returned Actual BOM {$abNumber} for corrections: {$notes}",
                        '/projects/' . $projectId
                    );
                }

                ActivityLogger::log(
                    'actual_bom.returned_for_corrections',
                    'project',
                    $projectId,
                    "Admin Finance user '{$actor['full_name']}' returned Actual BOM '{$abNumber}' to OPS Finance for corrections on project '{$project['project_code']}': {$notes}",
                    $request,
                    $actorId,
                    false
                );

                return ['success' => true, 'new_status' => ActualBom::STATUS_RETURNED_FOR_CORRECTIONS];
            }

            // decision === 'accept'
            $acceptNotes = $notes !== '' ? $notes : "Admin Finance ({$actor['full_name']}) reviewed and accepted Actual BOM '{$abNumber}' with specifications.";
            ActualBom::markAcceptedByAdminFinance($abId, $actorId, $acceptNotes);
            Project::updateStatus($projectId, Project::STATUS_ACTUAL_BOM_ACCEPTED);

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $fromProjStatus,
                'to_status' => Project::STATUS_ACTUAL_BOM_ACCEPTED,
                'action' => 'accepted_actual_bom',
                'from_department_id' => $afinDeptId,
                'to_department_id' => $afinDeptId,
                'acted_by' => $actorId,
                'notes' => $acceptNotes,
            ]);

            if ($opfinDeptId !== null) {
                Notification::notifyDepartmentUsers(
                    $opfinDeptId,
                    $projectId,
                    'actual_bom.accepted',
                    "Actual BOM Accepted: {$abNumber} ({$project['project_code']})",
                    "Admin Finance ({$actor['full_name']}) accepted Actual BOM {$abNumber} for project '{$project['project_name']}' (JOM: {$project['jom_number']}).",
                    '/projects/' . $projectId
                );
            }

            ActivityLogger::log(
                'actual_bom.accepted',
                'project',
                $projectId,
                "Admin Finance user '{$actor['full_name']}' accepted Actual BOM '{$abNumber}' for PR '{$pr['pr_number']}' (JOM: '{$project['jom_number']}') on project '{$project['project_code']}'.",
                $request,
                $actorId,
                false
            );

            return ['success' => true, 'new_status' => ActualBom::STATUS_ACCEPTED];
        });
    }

    /**
     * Parse Actual BOM line items from POST input.
     *
     * @param array<string, mixed> $input
     * @return list<array{pr_item_id: ?int, item_description: string, actual_specification: string, quantity: float, unit_of_measure: string, remarks: ?string}>
     */
    private static function parseActualBomItems(array $input): array
    {
        if (isset($input['items']) && is_array($input['items'])) {
            $parsed = [];
            foreach ($input['items'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $desc = trim((string) ($row['item_description'] ?? ''));
                $spec = trim((string) ($row['actual_specification'] ?? $row['item_specification'] ?? ''));
                $qty = (float) ($row['quantity'] ?? 0);
                $uom = trim((string) ($row['unit_of_measure'] ?? 'pcs'));
                $rem = trim((string) ($row['remarks'] ?? ''));
                $prItemId = !empty($row['pr_item_id']) && ctype_digit((string) $row['pr_item_id']) ? (int) $row['pr_item_id'] : null;

                if ($desc !== '' && $spec !== '' && $qty > 0) {
                    $parsed[] = [
                        'pr_item_id' => $prItemId,
                        'item_description' => mb_substr($desc, 0, 255, 'UTF-8'),
                        'actual_specification' => mb_substr($spec, 0, 2000, 'UTF-8'),
                        'quantity' => $qty,
                        'unit_of_measure' => $uom !== '' ? mb_substr($uom, 0, 50, 'UTF-8') : 'pcs',
                        'remarks' => $rem !== '' ? mb_substr($rem, 0, 500, 'UTF-8') : null,
                    ];
                }
            }
            return $parsed;
        }

        $descriptions = is_array($input['ab_item_description'] ?? $input['item_description'] ?? null)
            ? array_values($input['ab_item_description'] ?? $input['item_description'])
            : [];
        $specifications = is_array($input['ab_actual_specification'] ?? $input['actual_specification'] ?? null)
            ? array_values($input['ab_actual_specification'] ?? $input['actual_specification'])
            : [];
        $quantities = is_array($input['ab_quantity'] ?? $input['quantity'] ?? null)
            ? array_values($input['ab_quantity'] ?? $input['quantity'])
            : [];
        $units = is_array($input['ab_unit_of_measure'] ?? $input['unit_of_measure'] ?? null)
            ? array_values($input['ab_unit_of_measure'] ?? $input['unit_of_measure'])
            : [];
        $remarksList = is_array($input['ab_item_remarks'] ?? $input['item_remarks'] ?? null)
            ? array_values($input['ab_item_remarks'] ?? $input['item_remarks'])
            : [];
        $prItemIds = is_array($input['ab_pr_item_id'] ?? $input['pr_item_id'] ?? null)
            ? array_values($input['ab_pr_item_id'] ?? $input['pr_item_id'])
            : [];

        $count = max(count($descriptions), count($specifications), count($quantities));
        $parsed = [];

        for ($i = 0; $i < $count; $i++) {
            $desc = trim((string) ($descriptions[$i] ?? ''));
            $spec = trim((string) ($specifications[$i] ?? ''));
            $qtyRaw = trim((string) ($quantities[$i] ?? ''));
            $qty = is_numeric($qtyRaw) ? (float) $qtyRaw : 0.0;
            $uom = trim((string) ($units[$i] ?? 'pcs'));
            $rem = trim((string) ($remarksList[$i] ?? ''));
            $prItemRaw = trim((string) ($prItemIds[$i] ?? ''));
            $prItemId = $prItemRaw !== '' && ctype_digit($prItemRaw) ? (int) $prItemRaw : null;

            if ($desc === '' && $spec === '') {
                continue;
            }

            if ($desc === '' || $spec === '' || $qty <= 0) {
                return [];
            }

            $parsed[] = [
                'pr_item_id' => $prItemId,
                'item_description' => mb_substr($desc, 0, 255, 'UTF-8'),
                'actual_specification' => mb_substr($spec, 0, 2000, 'UTF-8'),
                'quantity' => $qty,
                'unit_of_measure' => $uom !== '' ? mb_substr($uom, 0, 50, 'UTF-8') : 'pcs',
                'remarks' => $rem !== '' ? mb_substr($rem, 0, 500, 'UTF-8') : null,
            ];
        }

        return $parsed;
    }
}
