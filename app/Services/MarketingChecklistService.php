<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Project;
use App\Models\ProjectDocument;

final class MarketingChecklistService
{
    public const DOC_STATUS_MISSING = 'Missing';
    public const DOC_STATUS_UPLOADED = 'Uploaded';
    public const DOC_STATUS_REPLACED = 'Replaced';

    /**
     * Load configured checklist item definitions for a project workflow.
     *
     * @return array<string, array{type: string, code: string, label: string, subtitle: string, required: bool, order: int}>
     */
    public static function checklistDefinition(string $workflowType = 'standard'): array
    {
        $defaultWorkflow = (string) Config::get('marketing.default_workflow', 'standard');
        $workflows = (array) Config::get('marketing.workflows', []);

        $selected = $workflows[$workflowType] ?? $workflows[$defaultWorkflow] ?? [];
        $documents = is_array($selected['documents'] ?? null) ? $selected['documents'] : [];

        uasort($documents, static fn(array $a, array $b): int => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0)));

        return $documents;
    }

    /**
     * @return list<string>
     */
    public static function requiredDocumentTypes(string $workflowType = 'standard'): array
    {
        $defs = self::checklistDefinition($workflowType);
        $required = [];

        foreach ($defs as $type => $meta) {
            if (!empty($meta['required'])) {
                $required[] = (string) $type;
            }
        }

        return $required;
    }

    /**
     * @return list<string>
     */
    public static function allChecklistTypes(string $workflowType = 'standard'): array
    {
        return array_keys(self::checklistDefinition($workflowType));
    }

    /**
     * Evaluate a project's Marketing document checklist, version history,
     * completeness status, and PM verification state.
     *
     * Note: Upload completeness (`is_complete`) indicates all required Marketing
     * documents have an uploaded file; it does NOT mean Project Management has
     * verified or approved the documents.
     *
     * @param array<string, mixed> $project
     * @return array{
     *   workflow_type: string,
     *   workflow_name: string,
     *   items: array<string, array<string, mixed>>,
     *   supplementary_documents: list<array<string, mixed>>,
     *   required_types: list<string>,
     *   uploaded_required_types: list<string>,
     *   missing_required_types: list<string>,
     *   attention_types: list<string>,
     *   required_count: int,
     *   uploaded_required_count: int,
     *   completion_percent: int,
     *   is_complete: bool,
     *   verification_status: string,
     *   verification_label: string
     * }
     */
    public static function evaluateProject(array $project): array
    {
        $projectId = (int) $project['id'];
        $workflowType = (string) ($project['workflow_type'] ?? Config::get('marketing.default_workflow', 'standard'));
        $workflows = (array) Config::get('marketing.workflows', []);
        $workflowName = (string) ($workflows[$workflowType]['name'] ?? 'Standard Project Workflow');

        $definitions = self::checklistDefinition($workflowType);
        $allDocs = ProjectDocument::forProject($projectId);

        $attentionRaw = $project['attention_documents'] ?? null;
        $attentionDecoded = is_string($attentionRaw) && $attentionRaw !== ''
            ? json_decode($attentionRaw, true)
            : [];
        /** @var list<string> $attentionTypes */
        $attentionTypes = is_array($attentionDecoded) ? array_values(array_map('strval', $attentionDecoded)) : [];

        $returnedTimestamp = !empty($project['returned_at']) ? strtotime((string) $project['returned_at']) : false;

        $groupedCurrent = [];
        $groupedHistory = [];
        $supplementary = [];

        foreach ($allDocs as $doc) {
            $type = (string) $doc['document_type'];
            if (!isset($definitions[$type])) {
                if ((int) ($doc['is_current'] ?? 1) === 1) {
                    $supplementary[] = $doc;
                }
                continue;
            }

            if ((int) ($doc['is_current'] ?? 1) === 1 && !isset($groupedCurrent[$type])) {
                $groupedCurrent[$type] = $doc;
            } else {
                $groupedHistory[$type][] = $doc;
            }
        }

        $items = [];
        $requiredTypes = [];
        $uploadedRequiredTypes = [];
        $missingRequiredTypes = [];

        foreach ($definitions as $type => $meta) {
            $isRequired = !empty($meta['required']);
            $currentDoc = $groupedCurrent[$type] ?? null;
            $historyDocs = $groupedHistory[$type] ?? [];

            if ($currentDoc === null) {
                $docStatus = self::DOC_STATUS_MISSING;
            } elseif ((int) $currentDoc['version_number'] > 1) {
                $docStatus = self::DOC_STATUS_REPLACED;
            } else {
                $docStatus = self::DOC_STATUS_UPLOADED;
            }

            if ($isRequired) {
                $requiredTypes[] = (string) $type;
                if ($currentDoc !== null) {
                    $uploadedRequiredTypes[] = (string) $type;
                } else {
                    $missingRequiredTypes[] = (string) $type;
                }
            }

            $flaggedForAttention = in_array((string) $type, $attentionTypes, true);
            $updatedSinceReturn = false;
            if ($flaggedForAttention && $returnedTimestamp !== false && $currentDoc !== null) {
                $uploadedTs = strtotime((string) $currentDoc['created_at']);
                if ($uploadedTs !== false && $uploadedTs >= $returnedTimestamp) {
                    $updatedSinceReturn = true;
                }
            }

            $items[$type] = [
                'type' => (string) $type,
                'code' => (string) ($meta['code'] ?? $type),
                'label' => (string) ($meta['label'] ?? $type),
                'subtitle' => (string) ($meta['subtitle'] ?? ''),
                'required' => $isRequired,
                'status' => $docStatus,
                'current' => $currentDoc,
                'version_number' => $currentDoc !== null ? (int) $currentDoc['version_number'] : 0,
                'history' => $historyDocs,
                'history_count' => count($historyDocs),
                'flagged_for_attention' => $flaggedForAttention,
                'updated_since_return' => $updatedSinceReturn,
            ];
        }

        $requiredCount = count($requiredTypes);
        $uploadedCount = count($uploadedRequiredTypes);
        $percent = $requiredCount > 0 ? (int) round(($uploadedCount / $requiredCount) * 100) : 100;

        $verificationStatus = (string) ($project['verification_status'] ?? Project::VERIFICATION_NOT_SUBMITTED);
        $verificationLabel = match ($verificationStatus) {
            Project::VERIFICATION_PENDING_PM => 'Awaiting Project Management Document Verification',
            Project::VERIFICATION_RETURNED => 'Returned by Project Management for Corrections',
            Project::VERIFICATION_VERIFIED => 'Verified Complete by Project Management',
            default => 'Not Yet Submitted for PM Verification',
        };

        return [
            'workflow_type' => $workflowType,
            'workflow_name' => $workflowName,
            'items' => $items,
            'supplementary_documents' => $supplementary,
            'required_types' => $requiredTypes,
            'uploaded_required_types' => $uploadedRequiredTypes,
            'missing_required_types' => $missingRequiredTypes,
            'attention_types' => $attentionTypes,
            'required_count' => $requiredCount,
            'uploaded_required_count' => $uploadedCount,
            'completion_percent' => $percent,
            'is_complete' => $missingRequiredTypes === [],
            'verification_status' => $verificationStatus,
            'verification_label' => $verificationLabel,
        ];
    }
}

