<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Models\Department;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectWorkflowHistory;

final class OperationsService
{
    /**
     * Upload an OPS document (Final SLD, Final BOM, Request of PR).
     *
     * @param array<string, mixed> $project
     * @param string $docTypeKey 'FINAL_SLD' | 'FINAL_BOM' | 'REQUEST_OF_PR'
     * @param array<string, mixed>|null $file
     * @param array<string, mixed> $actor
     * @return array{success: bool, error?: string, document_id?: int, version_number?: int, replaced?: bool, doc_type?: string, doc_name?: string}
     */
    public static function uploadOpsDocument(
        array $project,
        string $docTypeKey,
        ?array $file,
        array $actor,
        Request $request,
        string $uploadNotes = '',
        bool $allowLocalTestFile = false
    ): array {
        if (!AuthorizationService::canUploadOpsDocument($actor, $project)) {
            return [
                'success' => false,
                'error' => 'You are not authorized to upload Operations documents for this project, or the project is in a read-only state.',
            ];
        }

        $configDocs = (array) Config::get('operations.required_documents', []);
        $docConfig = $configDocs[$docTypeKey] ?? null;
        if ($docConfig === null) {
            return ['success' => false, 'error' => 'Invalid Operations document type requested.'];
        }

        $canonicalType = (string) $docConfig['type'];
        $columnMap = [
            'FINAL_SLD' => 'ops_final_sld_document_id',
            'FINAL_BOM' => 'ops_final_bom_document_id',
            'REQUEST_OF_PR' => 'ops_request_pr_document_id',
        ];
        $targetColumn = $columnMap[$docTypeKey] ?? null;
        if ($targetColumn === null) {
            return ['success' => false, 'error' => 'Invalid document column mapping.'];
        }

        $storeResult = DocumentStorageService::storeUploadedDocument(
            $project,
            $file,
            $canonicalType,
            $actor,
            $request,
            $uploadNotes,
            $allowLocalTestFile
        );

        if (!$storeResult['success']) {
            return $storeResult;
        }

        $docId = (int) ($storeResult['document_id'] ?? 0);
        $projectId = (int) $project['id'];
        $actorId = (int) $actor['id'];
        $versionNum = (int) ($storeResult['version_number'] ?? 1);

        Project::markOpsDocumentUploaded($projectId, $targetColumn, $docId, $actorId);

        $actionKey = !empty($storeResult['replaced']) ? 'ops_document_replaced' : 'ops_document_uploaded';
        $logAction = !empty($storeResult['replaced']) ? 'project.ops_document_replaced' : 'project.ops_document_uploaded';
        $docDesc = "Uploaded {$docConfig['name']} (v{$versionNum})";

        ProjectWorkflowHistory::record([
            'project_id' => $projectId,
            'from_status' => (string) ($project['status'] ?? ''),
            'to_status' => Project::STATUS_UNDER_OPS_PROCESSING,
            'action' => $actionKey,
            'from_department_id' => $project['current_department_id'],
            'to_department_id' => $project['current_department_id'],
            'acted_by' => $actorId,
            'notes' => $docDesc,
        ]);

        ActivityLogger::log(
            $logAction,
            'project',
            $projectId,
            "OPS user '{$actor['full_name']}' {$docDesc} for project '{$project['project_code']}'.",
            $request,
            $actorId,
            false
        );

        return [
            'success' => true,
            'document_id' => $docId,
            'version_number' => $versionNum,
            'replaced' => !empty($storeResult['replaced']),
            'doc_type' => $docTypeKey,
            'doc_name' => $docConfig['name'],
        ];
    }

    /**
     * Submit completed OPS project to OPS Finance.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @return array{success: bool, error?: string}
     */
    public static function submitToOpsFinance(
        array $project,
        array $actor,
        Request $request,
        array $input = []
    ): array {
        if (!AuthorizationService::canSubmitToOpsFinance($actor, $project)) {
            return [
                'success' => false,
                'error' => 'This project cannot be submitted to OPS Finance. Ensure all 3 Operations documents (Final SLD, Final BOM, Request of PR) have been uploaded.',
            ];
        }

        $opfinDept = Department::findByCode(Department::CODE_OPERATION_FINANCE);
        if ($opfinDept === null) {
            return ['success' => false, 'error' => 'Operations Finance department configuration is missing.'];
        }

        $projectId = (int) $project['id'];
        $actorId = (int) $actor['id'];
        $notes = trim((string) ($input['notes'] ?? ''));

        // Load current documents for handoff summary
        $finalSld = ProjectDocument::findCurrentFinalSld($projectId);
        $finalBom = ProjectDocument::findCurrentFinalBom($projectId);
        $requestPr = ProjectDocument::findCurrentRequestPr($projectId);

        if ($finalSld === null || $finalBom === null || $requestPr === null) {
            return [
                'success' => false,
                'error' => 'Missing one or more required Operations documents. Please upload Final SLD, Final BOM, and Request of PR.',
            ];
        }

        $handoffSummary = [
            'submitted_at' => date('Y-m-d H:i:s'),
            'submitted_by' => $actorId,
            'submitted_by_name' => $actor['full_name'] ?? 'Operations User',
            'notes' => $notes,
            'documents' => [
                'final_sld' => [
                    'id' => (int) $finalSld['id'],
                    'filename' => $finalSld['original_filename'],
                    'version' => (int) $finalSld['version_number'],
                ],
                'final_bom' => [
                    'id' => (int) $finalBom['id'],
                    'filename' => $finalBom['original_filename'],
                    'version' => (int) $finalBom['version_number'],
                ],
                'request_of_pr' => [
                    'id' => (int) $requestPr['id'],
                    'filename' => $requestPr['original_filename'],
                    'version' => (int) $requestPr['version_number'],
                ],
            ],
        ];

        return Database::transaction(static function () use ($projectId, $opfinDept, $actorId, $notes, $handoffSummary, $project, $actor, $request): array {
            $updated = Project::markSubmittedToOpsFinance(
                $projectId,
                (int) $opfinDept['id'],
                $actorId,
                $notes,
                $handoffSummary
            );

            if (!$updated) {
                return ['success' => false, 'error' => 'Failed to record Operations Finance submission. Project may have already been submitted.'];
            }

            $currentStatus = (string) ($project['status'] ?? '');
            ProjectWorkflowHistory::record([
                'project_id' => $projectId,
                'from_status' => $currentStatus,
                'to_status' => Project::STATUS_SUBMITTED_TO_OPS_FINANCE,
                'action' => 'submitted_to_ops_finance',
                'from_department_id' => $project['current_department_id'],
                'to_department_id' => (int) $opfinDept['id'],
                'acted_by' => $actorId,
                'notes' => $notes !== '' ? "Submitted to OPS Finance: {$notes}" : 'Submitted to OPS Finance with Final SLD, Final BOM, and Request of PR',
            ]);

            Notification::notifyDepartmentUsers(
                (int) $opfinDept['id'],
                $projectId,
                'project.submitted_to_opfin',
                "New Project Received: {$project['project_code']}",
                sprintf(
                    "%s (%s): Operations has submitted the project with Final SLD, Final BOM, and Request of PR.",
                    $project['project_name'],
                    $project['project_code']
                ),
                '/projects/' . $projectId
            );

            ActivityLogger::log(
                'project.submitted_to_opfin',
                'project',
                $projectId,
                "OPS user '{$actor['full_name']}' submitted project '{$project['project_code']}' to OPS Finance.",
                $request,
                $actorId,
                false
            );

            return ['success' => true];
        });
    }
}
