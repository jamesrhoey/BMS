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

final class PurchaseRequestService
{
    /**
     * Generate a unique Purchase Request (PR) number on the server.
     * Format is configurable via config/pm.php or .env (default: PR-YYYYMM-XXXX).
     */
    public static function generateUniquePrNumber(): string
    {
        $prefix = (string) Config::get('pm.pr.prefix', 'PR');
        $format = (string) Config::get('pm.pr.format', '{PREFIX}-{YEAR}{MONTH}-{SEQ}');
        $padding = max(3, (int) Config::get('pm.pr.padding', 4));

        $year = date('Y');
        $yearShort = date('y');
        $month = date('m');

        $seriesPrefix = str_replace(
            ['{PREFIX}', '{YEAR}', '{YEAR_SHORT}', '{MONTH}', '{SEQ}'],
            [$prefix, $year, $yearShort, $month, ''],
            $format
        );

        $latest = PurchaseRequest::findLatestPrNumberByPrefix($seriesPrefix . '%');
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
            if (!PurchaseRequest::prNumberExists($candidate)) {
                return $candidate;
            }
        }

        return sprintf('%s-%s%s-%04d-%s', $prefix, $year, $month, $nextSeq + 30, strtoupper(bin2hex(random_bytes(2))));
    }

    /**
     * Create or update a draft/returned Purchase Request with multiple line items.
     * Automatically retrieves and links project documents (SLD, Marketing BOM, Actual BOM).
     * Optionally submits the PR immediately to OPS Finance.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $attachmentFile
     * @param array<string, mixed> $actor
     * @return array{success: bool, pr_id?: int, pr_number?: string, error?: string}
     */
    public static function savePurchaseRequest(
        array $project,
        array $input,
        ?array $attachmentFile,
        array $actor,
        Request $request,
        bool $submitImmediately = false
    ): array {
        $projectId = (int) $project['id'];
        $existingPr = PurchaseRequest::findByProjectId($projectId);

        if (!AuthorizationService::canPreparePurchaseRequest($actor, $project, $existingPr)) {
            return [
                'success' => false,
                'error' => 'You are not authorized to prepare or edit this Purchase Request, or it has already been submitted to OPS Finance.',
            ];
        }

        $prDateRaw = trim((string) ($input['pr_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $prDateRaw)) {
            $prDateRaw = date('Y-m-d');
        }

        $purpose = trim((string) ($input['purpose'] ?? $input['pr_purpose'] ?? ''));
        if (mb_strlen($purpose, 'UTF-8') > 3000) {
            return [
                'success' => false,
                'error' => 'Purchase Request purpose / justification must not exceed 3,000 characters.',
            ];
        }

        $requiredDeliveryDate = trim((string) ($input['required_delivery_date'] ?? ''));
        if ($requiredDeliveryDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requiredDeliveryDate)) {
            $requiredDeliveryDate = null;
        } elseif ($requiredDeliveryDate === '') {
            $requiredDeliveryDate = null;
        }

        $remarks = trim((string) ($input['pr_remarks'] ?? $input['remarks'] ?? ''));
        if (mb_strlen($remarks, 'UTF-8') > 2000) {
            return [
                'success' => false,
                'error' => 'Purchase Request remarks must not exceed 2,000 characters.',
            ];
        }

        $parsedItems = self::parseLineItems($input);
        if ($parsedItems === []) {
            return [
                'success' => false,
                'error' => 'Please enter at least one valid Purchase Request line item with item description, specification, and positive quantity.',
            ];
        }

        $attachmentDocId = null;
        if ($attachmentFile !== null && isset($attachmentFile['error']) && (int) $attachmentFile['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadRes = DocumentStorageService::storeWorkflowDocument(
                $project,
                $attachmentFile,
                PurchaseRequest::DOC_TYPE_ATTACHMENT,
                $actor,
                $request,
                'Purchase Request Attachment'
            );
            if (!$uploadRes['success']) {
                return [
                    'success' => false,
                    'error' => $uploadRes['error'] ?? 'Failed to upload PR attachment.',
                ];
            }
            $attachmentDocId = (int) $uploadRes['document_id'];
        }

        $opsDept = Department::findByCode(Department::CODE_OPERATIONS);
        $opfinDept = Department::findByCode(Department::CODE_OPERATION_FINANCE);
        $opsDeptId = $opsDept !== null ? (int) $opsDept['id'] : null;
        $opfinDeptId = $opfinDept !== null ? (int) $opfinDept['id'] : null;
        $actorId = (int) $actor['id'];
        $jomNumber = (string) ($project['jom_number'] ?? '');

        // Auto-retrieve SLD, Marketing BOM, and Actual BOM references from project
        $sldDocId = !empty($project['ops_sld_document_id']) ? (int) $project['ops_sld_document_id'] : null;
        $marketingBomDocId = !empty($project['ops_bom_document_id']) ? (int) $project['ops_bom_document_id'] : null;

        $existingAb = ActualBom::findByProjectId($projectId);
        $actualBomId = $existingAb !== null ? (int) $existingAb['id'] : null;

        $savedResult = Database::transaction(static function () use (
            $project,
            $projectId,
            $jomNumber,
            $existingPr,
            $opsDeptId,
            $opfinDeptId,
            $actorId,
            $actor,
            $prDateRaw,
            $purpose,
            $requiredDeliveryDate,
            $remarks,
            $attachmentDocId,
            $sldDocId,
            $marketingBomDocId,
            $actualBomId,
            $parsedItems,
            $request
        ): array {
            $fromStatus = (string) ($project['status'] ?? Project::STATUS_FORWARDED_TO_OPERATIONS);

            if ($existingPr === null) {
                $prNumber = self::generateUniquePrNumber();
                $prId = PurchaseRequest::create([
                    'pr_number' => $prNumber,
                    'project_id' => $projectId,
                    'jom_number' => $jomNumber,
                    'requesting_department_id' => $opsDeptId,
                    'receiving_department_id' => $opfinDeptId,
                    'requested_by' => $actorId,
                    'pr_date' => $prDateRaw,
                    'purpose' => $purpose,
                    'required_delivery_date' => $requiredDeliveryDate,
                    'remarks' => $remarks,
                    'attachment_document_id' => $attachmentDocId,
                    'sld_document_id' => $sldDocId,
                    'marketing_bom_document_id' => $marketingBomDocId,
                    'actual_bom_id' => $actualBomId,
                    'status' => PurchaseRequest::STATUS_DRAFT,
                ], $parsedItems);

                Project::updateStatus($projectId, Project::STATUS_PR_BEING_PREPARED);

                ProjectWorkflowHistory::record([
                    'project_id' => $projectId,
                    'from_status' => $fromStatus,
                    'to_status' => Project::STATUS_PR_BEING_PREPARED,
                    'action' => 'created_purchase_request',
                    'from_department_id' => $opsDeptId,
                    'to_department_id' => $opsDeptId,
                    'acted_by' => $actorId,
                    'notes' => sprintf(
                        "Operations (%s) created Purchase Request '%s' (%d line item%s) for JOM '%s'.",
                        $actor['full_name'] ?? $actor['username'],
                        $prNumber,
                        count($parsedItems),
                        count($parsedItems) === 1 ? '' : 's',
                        $jomNumber
                    ),
                ]);

                ActivityLogger::log(
                    'pr.created',
                    'project',
                    $projectId,
                    "Operations user '{$actor['full_name']}' created Purchase Request '{$prNumber}' (" . count($parsedItems) . " line items) for project '{$project['project_code']}' (JOM: '{$jomNumber}').",
                    $request,
                    $actorId,
                    false
                );

                return [
                    'pr_id' => $prId,
                    'pr_number' => $prNumber,
                ];
            }

            $prId = (int) $existingPr['id'];
            $prNumber = (string) $existingPr['pr_number'];

            PurchaseRequest::updateDraft($prId, [
                'pr_date' => $prDateRaw,
                'purpose' => $purpose,
                'required_delivery_date' => $requiredDeliveryDate,
                'remarks' => $remarks,
                'attachment_document_id' => $attachmentDocId,
                'sld_document_id' => $sldDocId,
                'marketing_bom_document_id' => $marketingBomDocId,
                'actual_bom_id' => $actualBomId,
            ], $parsedItems);

            PurchaseRequest::recordHistory(
                $prId,
                (string) $existingPr['status'],
                (string) $existingPr['status'],
                'updated_draft',
                $actorId,
                sprintf("Operations updated Purchase Request with %d line items.", count($parsedItems))
            );

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $fromStatus,
                'to_status' => $fromStatus,
                'action' => 'updated_purchase_request',
                'from_department_id' => $opsDeptId,
                'to_department_id' => $opsDeptId,
                'acted_by' => $actorId,
                'notes' => sprintf(
                    "Operations (%s) updated Purchase Request '%s' (%d line item%s).",
                    $actor['full_name'] ?? $actor['username'],
                    $prNumber,
                    count($parsedItems),
                    count($parsedItems) === 1 ? '' : 's'
                ),
            ]);

            ActivityLogger::log(
                'pr.updated',
                'project',
                $projectId,
                "Operations user '{$actor['full_name']}' updated Purchase Request '{$prNumber}' (" . count($parsedItems) . " line items) for project '{$project['project_code']}'.",
                $request,
                $actorId,
                false
            );

            return [
                'pr_id' => $prId,
                'pr_number' => $prNumber,
            ];
        });

        if ($submitImmediately) {
            $freshPr = PurchaseRequest::findById((int) $savedResult['pr_id']);
            $freshProject = Project::findById($projectId) ?? $project;
            if ($freshPr !== null) {
                $submitRes = self::submitToOpsFinance($freshProject, $freshPr, $actor, $request);
                if (!$submitRes['success']) {
                    return $submitRes;
                }
            }
        }

        return [
            'success' => true,
            'pr_id' => $savedResult['pr_id'],
            'pr_number' => $savedResult['pr_number'],
        ];
    }

    /**
     * Submit and forward a Purchase Request (PR) from Operations to OPS Finance.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $pr
     * @param array<string, mixed> $actor
     * @return array{success: bool, pr_number?: string, error?: string}
     */
    public static function submitToOpsFinance(
        array $project,
        array $pr,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canSubmitPurchaseRequest($actor, $project, $pr)) {
            return [
                'success' => false,
                'error' => 'This Purchase Request cannot be submitted (already submitted, unauthorized, or missing line items).',
            ];
        }

        $opfinDept = Department::findByCode(Department::CODE_OPERATION_FINANCE);
        if ($opfinDept === null) {
            return [
                'success' => false,
                'error' => 'OPS Finance department configuration is missing.',
            ];
        }

        $opsDept = Department::findByCode(Department::CODE_OPERATIONS);
        $opsDeptId = $opsDept !== null ? (int) $opsDept['id'] : null;
        $opfinDeptId = (int) $opfinDept['id'];
        $projectId = (int) $project['id'];
        $prId = (int) $pr['id'];
        $prNumber = (string) $pr['pr_number'];
        $actorId = (int) $actor['id'];

        return Database::transaction(static function () use (
            $project,
            $projectId,
            $prId,
            $prNumber,
            $opsDeptId,
            $opfinDeptId,
            $actorId,
            $actor,
            $request
        ): array {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT * FROM purchase_requests WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $prId]);
            $lockedPr = $stmt->fetch();

            if (!$lockedPr || !PurchaseRequest::isEditableByOperations($lockedPr)) {
                return [
                    'success' => false,
                    'error' => 'This Purchase Request has already been submitted to OPS Finance. Duplicate submission is prevented.',
                ];
            }

            $marked = PurchaseRequest::markSubmittedToOpsFinance($prId, $opfinDeptId, $actorId);
            if (!$marked) {
                return [
                    'success' => false,
                    'error' => 'Unable to submit Purchase Request to OPS Finance.',
                ];
            }

            $fromStatus = (string) ($project['status'] ?? Project::STATUS_PR_BEING_PREPARED);
            $updProj = $pdo->prepare(
                'UPDATE projects
                 SET status = :status,
                     current_department_id = :dept_id,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $updProj->execute([
                'id' => $projectId,
                'status' => Project::STATUS_PR_SUBMITTED_TO_OPS_FINANCE,
                'dept_id' => $opfinDeptId,
            ]);

            $notesText = sprintf(
                "Operations (%s) submitted Purchase Request '%s' (JOM: %s) and forwarded it to OPS Finance.",
                $actor['full_name'] ?? $actor['username'],
                $prNumber,
                $project['jom_number'] ?? ''
            );

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $fromStatus,
                'to_status' => Project::STATUS_PR_SUBMITTED_TO_OPS_FINANCE,
                'action' => 'submitted_pr_to_ops_finance',
                'from_department_id' => $opsDeptId,
                'to_department_id' => $opfinDeptId,
                'acted_by' => $actorId,
                'notes' => $notesText,
            ]);

            Notification::notifyDepartmentUsers(
                $opfinDeptId,
                $projectId,
                'pr.submitted_to_ops_finance',
                "Purchase Request Submitted: {$prNumber} ({$project['project_code']})",
                sprintf(
                    "Operations (%s) submitted Purchase Request %s for project '%s' (JOM: %s) to OPS Finance for review and Actual BOM upload.",
                    $actor['full_name'] ?? $actor['username'],
                    $prNumber,
                    $project['project_name'],
                    $project['jom_number'] ?? ''
                ),
                '/projects/' . $projectId
            );

            ActivityLogger::log(
                'pr.submitted',
                'project',
                $projectId,
                "Operations user '{$actor['full_name']}' submitted Purchase Request '{$prNumber}' for project '{$project['project_code']}' (JOM: '{$project['jom_number']}').",
                $request,
                $actorId,
                false
            );

            ActivityLogger::log(
                'pr.forwarded_to_ops_finance',
                'project',
                $projectId,
                "Operations user '{$actor['full_name']}' forwarded Purchase Request '{$prNumber}' from Operations (OPS) to receiving department OPS Finance (OPFIN) for project '{$project['project_code']}'.",
                $request,
                $actorId,
                false
            );

            return [
                'success' => true,
                'pr_number' => $prNumber,
            ];
        });
    }

    /**
     * Mark a submitted PR as Under OPS Finance Review.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $pr
     * @param array<string, mixed> $actor
     * @return array{success: bool, error?: string}
     */
    public static function startOpsFinanceReview(
        array $project,
        array $pr,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canReviewPurchaseRequest($actor, $project, $pr)) {
            return [
                'success' => false,
                'error' => 'You are not authorized to review this Purchase Request.',
            ];
        }

        $projectId = (int) $project['id'];
        $prId = (int) $pr['id'];
        $prNumber = (string) $pr['pr_number'];
        $actorId = (int) $actor['id'];
        $opfinDept = Department::findByCode(Department::CODE_OPERATION_FINANCE);
        $opfinDeptId = $opfinDept !== null ? (int) $opfinDept['id'] : null;

        return Database::transaction(static function () use ($project, $projectId, $prId, $prNumber, $opfinDeptId, $actorId, $actor, $request): array {
            PurchaseRequest::markUnderOpsFinanceReview($prId, $actorId);
            Project::updateStatus($projectId, Project::STATUS_ACTUAL_BOM_PENDING_UPLOAD);

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => (string) ($project['status'] ?? Project::STATUS_PR_SUBMITTED_TO_OPS_FINANCE),
                'to_status' => Project::STATUS_ACTUAL_BOM_PENDING_UPLOAD,
                'action' => 'ops_finance_reviewed_pr',
                'from_department_id' => $opfinDeptId,
                'to_department_id' => $opfinDeptId,
                'acted_by' => $actorId,
                'notes' => "OPS Finance ({$actor['full_name']}) reviewed Purchase Request '{$prNumber}'. Actual BOM pending upload.",
            ]);

            ActivityLogger::log(
                'pr.reviewed_by_ops_finance',
                'project',
                $projectId,
                "OPS Finance user '{$actor['full_name']}' reviewed Purchase Request '{$prNumber}' for project '{$project['project_code']}'.",
                $request,
                $actorId,
                false
            );

            return ['success' => true];
        });
    }

    /**
     * Controlled return-for-revision / return-for-correction workflow for a Purchase Request.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $pr
     * @param array<string, mixed> $actor
     * @return array{success: bool, error?: string}
     */
    public static function returnPrForCorrections(
        array $project,
        array $pr,
        string $reason,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canReviewPurchaseRequest($actor, $project, $pr)) {
            return [
                'success' => false,
                'error' => 'You are not authorized to return this Purchase Request.',
            ];
        }

        $reason = trim($reason);
        if ($reason === '') {
            return [
                'success' => false,
                'error' => 'Please provide correction notes before returning the Purchase Request to Operations.',
            ];
        }

        $opsDept = Department::findByCode(Department::CODE_OPERATIONS);
        $opfinDept = Department::findByCode(Department::CODE_OPERATION_FINANCE);
        $opsDeptId = $opsDept !== null ? (int) $opsDept['id'] : null;
        $opfinDeptId = $opfinDept !== null ? (int) $opfinDept['id'] : null;
        $projectId = (int) $project['id'];
        $prId = (int) $pr['id'];
        $prNumber = (string) $pr['pr_number'];
        $actorId = (int) $actor['id'];

        return Database::transaction(static function () use (
            $project,
            $projectId,
            $prId,
            $prNumber,
            $reason,
            $opsDeptId,
            $opfinDeptId,
            $actorId,
            $actor,
            $request
        ): array {
            PurchaseRequest::markReturnedForCorrections($prId, $actorId, $reason);

            $pdo = Database::connection();
            $upd = $pdo->prepare(
                'UPDATE projects
                 SET status = :status,
                     current_department_id = COALESCE(:ops_dept, current_department_id),
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $upd->execute([
                'id' => $projectId,
                'status' => Project::STATUS_PR_BEING_PREPARED,
                'ops_dept' => $opsDeptId,
            ]);

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => (string) ($project['status'] ?? Project::STATUS_PR_SUBMITTED_TO_OPS_FINANCE),
                'to_status' => Project::STATUS_PR_BEING_PREPARED,
                'action' => 'returned_pr_for_corrections',
                'from_department_id' => $opfinDeptId,
                'to_department_id' => $opsDeptId,
                'acted_by' => $actorId,
                'notes' => "Purchase Request '{$prNumber}' returned for revision: {$reason}",
            ]);

            if ($opsDeptId !== null) {
                Notification::notifyDepartmentUsers(
                    $opsDeptId,
                    $projectId,
                    'pr.returned_for_corrections',
                    "PR Returned for Revision: {$prNumber} ({$project['project_code']})",
                    "Approver ({$actor['full_name']}) returned Purchase Request {$prNumber} for revision: {$reason}",
                    '/projects/' . $projectId
                );
            }

            ActivityLogger::log(
                'pr.returned_for_corrections',
                'project',
                $projectId,
                "User '{$actor['full_name']}' returned Purchase Request '{$prNumber}' to Operations for revision: {$reason}",
                $request,
                $actorId,
                false
            );

            return ['success' => true];
        });
    }

    /**
     * Formally approve a Purchase Request for Purchase Order (PO) creation eligibility.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $pr
     * @param array<string, mixed> $actor
     * @return array{success: bool, pr_number?: string, error?: string}
     */
    public static function approvePurchaseRequest(
        array $project,
        array $pr,
        ?string $notes,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canApprovePurchaseRequest($actor, $project, $pr)) {
            return [
                'success' => false,
                'error' => 'You are not authorized to approve this Purchase Request, or it is not in an approvable status.',
            ];
        }

        $projectId = (int) $project['id'];
        $prId = (int) $pr['id'];
        $prNumber = (string) $pr['pr_number'];
        $actorId = (int) $actor['id'];
        $actorDeptId = $actor['department_id'] !== null ? (int) $actor['department_id'] : null;

        return Database::transaction(static function () use (
            $project,
            $projectId,
            $prId,
            $prNumber,
            $notes,
            $actorId,
            $actorDeptId,
            $actor,
            $request
        ): array {
            $marked = PurchaseRequest::markApproved($prId, $actorId, $notes);
            if (!$marked) {
                return [
                    'success' => false,
                    'error' => 'Unable to approve Purchase Request.',
                ];
            }

            $fromStatus = (string) ($project['status'] ?? Project::STATUS_PR_SUBMITTED_TO_OPS_FINANCE);

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $fromStatus,
                'to_status' => 'Purchase Request Approved',
                'action' => 'approved_purchase_request',
                'from_department_id' => $actorDeptId,
                'to_department_id' => $actorDeptId,
                'acted_by' => $actorId,
                'notes' => sprintf(
                    "Purchase Request '%s' formally approved by %s. %s",
                    $prNumber,
                    $actor['full_name'] ?? $actor['username'],
                    $notes ? "Notes: {$notes}" : 'Eligible for PO creation.'
                ),
            ]);

            // Notify Operations department
            $opsDept = Department::findByCode(Department::CODE_OPERATIONS);
            if ($opsDept !== null) {
                Notification::notifyDepartmentUsers(
                    (int) $opsDept['id'],
                    $projectId,
                    'pr.approved',
                    "Purchase Request Approved: {$prNumber} ({$project['project_code']})",
                    sprintf(
                        "Purchase Request %s for project '%s' has been approved by %s (%s). Eligible for PO creation.",
                        $prNumber,
                        $project['project_name'],
                        $actor['full_name'],
                        $actor['department_code'] ?? 'Approver'
                    ),
                    '/projects/' . $projectId
                );
            }

            ActivityLogger::log(
                'pr.approved',
                'project',
                $projectId,
                "User '{$actor['full_name']}' approved Purchase Request '{$prNumber}' for project '{$project['project_code']}'.",
                $request,
                $actorId,
                false
            );

            return [
                'success' => true,
                'pr_number' => $prNumber,
            ];
        });
    }

    /**
     * Formally reject a Purchase Request with documented reason.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $pr
     * @param array<string, mixed> $actor
     * @return array{success: bool, pr_number?: string, error?: string}
     */
    public static function rejectPurchaseRequest(
        array $project,
        array $pr,
        string $reason,
        array $actor,
        Request $request
    ): array {
        if (!AuthorizationService::canRejectPurchaseRequest($actor, $project, $pr)) {
            return [
                'success' => false,
                'error' => 'You are not authorized to reject this Purchase Request, or it is not in a rejectable status.',
            ];
        }

        $reason = trim($reason);
        if ($reason === '') {
            return [
                'success' => false,
                'error' => 'Please provide a documented reason for rejecting this Purchase Request.',
            ];
        }

        $projectId = (int) $project['id'];
        $prId = (int) $pr['id'];
        $prNumber = (string) $pr['pr_number'];
        $actorId = (int) $actor['id'];
        $actorDeptId = $actor['department_id'] !== null ? (int) $actor['department_id'] : null;

        return Database::transaction(static function () use (
            $project,
            $projectId,
            $prId,
            $prNumber,
            $reason,
            $actorId,
            $actorDeptId,
            $actor,
            $request
        ): array {
            $marked = PurchaseRequest::markRejected($prId, $actorId, $reason);
            if (!$marked) {
                return [
                    'success' => false,
                    'error' => 'Unable to reject Purchase Request.',
                ];
            }

            $fromStatus = (string) ($project['status'] ?? Project::STATUS_PR_SUBMITTED_TO_OPS_FINANCE);

            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $fromStatus,
                'to_status' => 'Purchase Request Rejected',
                'action' => 'rejected_purchase_request',
                'from_department_id' => $actorDeptId,
                'to_department_id' => $actorDeptId,
                'acted_by' => $actorId,
                'notes' => sprintf(
                    "Purchase Request '%s' rejected by %s. Reason: %s",
                    $prNumber,
                    $actor['full_name'] ?? $actor['username'],
                    $reason
                ),
            ]);

            // Notify Operations department
            $opsDept = Department::findByCode(Department::CODE_OPERATIONS);
            if ($opsDept !== null) {
                Notification::notifyDepartmentUsers(
                    (int) $opsDept['id'],
                    $projectId,
                    'pr.rejected',
                    "Purchase Request Rejected: {$prNumber} ({$project['project_code']})",
                    sprintf(
                        "Purchase Request %s for project '%s' has been rejected by %s. Reason: %s",
                        $prNumber,
                        $project['project_name'],
                        $actor['full_name'],
                        $reason
                    ),
                    '/projects/' . $projectId
                );
            }

            ActivityLogger::log(
                'pr.rejected',
                'project',
                $projectId,
                "User '{$actor['full_name']}' rejected Purchase Request '{$prNumber}' for project '{$project['project_code']}': {$reason}",
                $request,
                $actorId,
                false
            );

            return [
                'success' => true,
                'pr_number' => $prNumber,
            ];
        });
    }

    /**
     * Parse PR line items from POST input.
     * Supports:
     * - `item_description[]`
     * - `model_part_number[]`
     * - `item_specification[]`
     * - `quantity[]`
     * - `unit_of_measure[]`
     * - `purpose[]` / `item_purpose[]`
     * - `item_remarks[]`
     *
     * @param array<string, mixed> $input
     * @return list<array{item_description: string, model_part_number: ?string, item_specification: string, quantity: float, unit_of_measure: string, purpose: ?string, remarks: ?string}>
     */
    private static function parseLineItems(array $input): array
    {
        if (isset($input['items']) && is_array($input['items'])) {
            $parsed = [];
            foreach ($input['items'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $desc = trim((string) ($row['item_description'] ?? ''));
                $model = trim((string) ($row['model_part_number'] ?? $row['part_number'] ?? ''));
                $spec = trim((string) ($row['item_specification'] ?? ''));
                $qty = (float) ($row['quantity'] ?? 0);
                $uom = trim((string) ($row['unit_of_measure'] ?? 'pcs'));
                $purp = trim((string) ($row['purpose'] ?? ''));
                $rem = trim((string) ($row['remarks'] ?? ''));

                if ($desc !== '' && $spec !== '' && $qty > 0) {
                    $parsed[] = [
                        'item_description' => mb_substr($desc, 0, 255, 'UTF-8'),
                        'model_part_number' => $model !== '' ? mb_substr($model, 0, 150, 'UTF-8') : null,
                        'item_specification' => mb_substr($spec, 0, 2000, 'UTF-8'),
                        'quantity' => $qty,
                        'unit_of_measure' => $uom !== '' ? mb_substr($uom, 0, 50, 'UTF-8') : 'pcs',
                        'purpose' => $purp !== '' ? mb_substr($purp, 0, 255, 'UTF-8') : null,
                        'remarks' => $rem !== '' ? mb_substr($rem, 0, 500, 'UTF-8') : null,
                    ];
                }
            }
            return $parsed;
        }

        $descriptions = is_array($input['item_description'] ?? null) ? array_values($input['item_description']) : [];
        $models = is_array($input['model_part_number'] ?? $input['part_number'] ?? null)
            ? array_values($input['model_part_number'] ?? $input['part_number'])
            : [];
        $specifications = is_array($input['item_specification'] ?? null) ? array_values($input['item_specification']) : [];
        $quantities = is_array($input['quantity'] ?? null) ? array_values($input['quantity']) : [];
        $units = is_array($input['unit_of_measure'] ?? null) ? array_values($input['unit_of_measure']) : [];
        $purposes = is_array($input['item_purpose'] ?? $input['purpose'] ?? null)
            ? array_values($input['item_purpose'] ?? $input['purpose'])
            : [];
        $remarksList = is_array($input['item_remarks'] ?? null) ? array_values($input['item_remarks']) : [];

        $count = max(count($descriptions), count($specifications), count($quantities));
        $parsed = [];

        for ($i = 0; $i < $count; $i++) {
            $desc = trim((string) ($descriptions[$i] ?? ''));
            $model = trim((string) ($models[$i] ?? ''));
            $spec = trim((string) ($specifications[$i] ?? ''));
            $qtyRaw = trim((string) ($quantities[$i] ?? ''));
            $qty = is_numeric($qtyRaw) ? (float) $qtyRaw : 0.0;
            $uom = trim((string) ($units[$i] ?? 'pcs'));
            $purp = trim((string) ($purposes[$i] ?? ''));
            $rem = trim((string) ($remarksList[$i] ?? ''));

            if ($desc === '' && $spec === '') {
                continue;
            }

            if ($desc === '' || $spec === '' || $qty <= 0) {
                return [];
            }

            $parsed[] = [
                'item_description' => mb_substr($desc, 0, 255, 'UTF-8'),
                'model_part_number' => $model !== '' ? mb_substr($model, 0, 150, 'UTF-8') : null,
                'item_specification' => mb_substr($spec, 0, 2000, 'UTF-8'),
                'quantity' => $qty,
                'unit_of_measure' => $uom !== '' ? mb_substr($uom, 0, 50, 'UTF-8') : 'pcs',
                'purpose' => $purp !== '' ? mb_substr($purp, 0, 255, 'UTF-8') : null,
                'remarks' => $rem !== '' ? mb_substr($rem, 0, 500, 'UTF-8') : null,
            ];
        }

        return $parsed;
    }
}
