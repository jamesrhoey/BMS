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
     * Normalize category/workflow key to canonical value ('mrc' or 'special_project').
     */
    public static function normalizeCategory(?string $category): string
    {
        $cat = strtolower(trim((string) $category));
        if ($cat === 'special project' || $cat === 'special_project' || $cat === 'special') {
            return 'special_project';
        }

        return 'mrc';
    }

    /**
     * Normalize legacy and descriptive document names to their checklist codes.
     *
     * @param string|null $documentType Raw document type stored in the database.
     */
    public static function normalizeDocumentType(?string $documentType): string
    {
        $raw = trim((string) $documentType);
        $key = strtolower((string) preg_replace('/\s+/', ' ', $raw));

        return match ($key) {
            'son', 'service on notice', 'service on notice (son)', 'service order', 'service on notice / service order' => 'SON',
            'sld', 'single line diagram', 'single line diagram (sld)' => 'SLD',
            'bom', 'bill of materials', 'bill of materials (bom)', 'marketing bill of materials' => 'BOM',
            'ca', 'cost analysis', 'cost analysis (ca)', 'marketing cost analysis' => 'CA',
            'prop', 'proposal to client', 'commercial proposal issued to client' => 'PROP',
            'prop-supplier', 'proposal from supplier', 'vendor / supplier quotation & proposal' => 'PROP-SUPPLIER',
            'po', 'marketing po', 'purchase order', 'purchase order (client po)' => 'PO',
            default => $raw,
        };
    }

    /**
     * Get display label for category key ('MRC' or 'Special Project').
     */
    public static function categoryLabel(?string $category): string
    {
        $normalized = self::normalizeCategory($category);
        $categories = (array) Config::get('marketing.categories', []);

        return (string) ($categories[$normalized]['label'] ?? ($normalized === 'special_project' ? 'Special Project' : 'MRC'));
    }

    /**
     * Check if a given category string is a valid controlled category.
     */
    public static function isValidCategory(string $category): bool
    {
        $normalized = strtolower(trim($category));
        return in_array($normalized, ['mrc', 'special project', 'special_project'], true);
    }

    /**
     * Determine if Marketing PO is required for a category.
     */
    public static function isPoRequired(string $category): bool
    {
        return self::normalizeCategory($category) === 'special_project';
    }

    /**
     * Load configured checklist item definitions for a project workflow category.
     *
     * @return array<string, array{type: string, code: string, label: string, subtitle: string, required: bool, order: int}>
     */
    public static function checklistDefinition(string $workflowType = 'mrc'): array
    {
        $normalized = self::normalizeCategory($workflowType);
        $workflows = (array) Config::get('marketing.workflows', []);

        $selected = $workflows[$normalized] ?? $workflows['mrc'] ?? $workflows['standard'] ?? [];
        $documents = is_array($selected['documents'] ?? null) ? $selected['documents'] : [];

        uasort($documents, static fn(array $a, array $b): int => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0)));

        return $documents;
    }

    /**
     * @return list<string>
     */
    public static function requiredDocumentTypes(string $workflowType = 'mrc'): array
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
    public static function allChecklistTypes(string $workflowType = 'mrc'): array
    {
        $keys = array_keys(self::checklistDefinition($workflowType));
        // Include common aliases like 'PO' for 'Marketing PO'
        if (in_array('Marketing PO', $keys, true) && !in_array('PO', $keys, true)) {
            $keys[] = 'PO';
        }
        return $keys;
    }

    /**
     * Evaluate a project's Marketing document checklist, version history,
     * completeness status, and PM/Admin Finance submission state.
     *
     * Note:
     * - MRC: requires 6 documents (SON, SLD, BOM, CA, PROP, PROP-SUPPLIER). PO is NOT required.
     * - Special Project: requires 7 documents (the 6 MRC documents + Marketing PO). PO is strictly required.
     *
     * @param array<string, mixed> $project
     * @return array{
     *   workflow_type: string,
     *   category_key: string,
     *   category_label: string,
     *   is_po_required: bool,
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
        $rawCategory = (string) ($project['project_category'] ?? $project['workflow_type'] ?? 'mrc');
        $categoryKey = self::normalizeCategory($rawCategory);
        $categoryLabel = self::categoryLabel($categoryKey);
        $isPoRequired = ($categoryKey === 'special_project');

        $workflows = (array) Config::get('marketing.workflows', []);
        $workflowName = (string) ($workflows[$categoryKey]['name'] ?? ($categoryKey === 'special_project' ? 'Special Project' : 'MRC Project'));

        $definitions = self::checklistDefinition($categoryKey);
        $allDocs = ProjectDocument::forProject($projectId);

        $attentionRaw = $project['attention_documents'] ?? null;
        $attentionDecoded = is_string($attentionRaw) && $attentionRaw !== ''
            ? json_decode($attentionRaw, true)
            : [];
        /** @var list<string> $attentionTypes */
        $attentionTypes = is_array($attentionDecoded) ? array_values(array_map('strval', $attentionDecoded)) : [];

        $returnedTimestamp = !empty($project['returned_at']) ? strtotime((string) $project['returned_at']) : false;

        $typeAliasMap = [
            'PO' => 'Marketing PO',
            'Marketing PO' => 'Marketing PO',
            'Purchase Order' => 'Marketing PO',
            'PROP' => 'Proposal to Client',
            'Proposal to Client' => 'Proposal to Client',
            'Commercial Proposal Issued to Client' => 'Proposal to Client',
            'PROP-SUPPLIER' => 'Proposal from Supplier',
            'Proposal from Supplier' => 'Proposal from Supplier',
            'Vendor / Supplier Quotation & Proposal' => 'Proposal from Supplier',
            'SON' => 'SON',
            'Service On Notice (SON)' => 'SON',
            'SLD' => 'SLD',
            'Single Line Diagram (SLD)' => 'SLD',
            'BOM' => 'BOM',
            'Bill of Materials (BOM)' => 'BOM',
            'CA' => 'CA',
            'Cost Analysis (CA)' => 'CA',
            'JOM' => 'JOM',
            'JOM — Job Order Memorandum' => 'JOM',
            'Job Order Memorandum' => 'JOM',
        ];

        $groupedCurrent = [];
        $groupedHistory = [];
        $supplementary = [];

        foreach ($allDocs as $doc) {
            $rawType = (string) $doc['document_type'];
            if (in_array($rawType, [
                'PR Attachment',
                'Actual BOM',
                'Actual BOM Supporting Document',
                'Final SLD',
                'FINAL_SLD',
                'Final SLD — Single Line Diagram',
                'Final BOM',
                'FINAL_BOM',
                'Final BOM — Bill of Materials',
                'Request of PR',
                'REQUEST_OF_PR',
                'Request of PR — Purchase Requisition Request',
            ], true)) {
                if ((int) ($doc['is_current'] ?? 1) === 1) {
                    $supplementary[] = $doc;
                }
                continue;
            }

            // Resolve alias if applicable
            $canonicalType = $typeAliasMap[$rawType] ?? $rawType;

            if (!isset($definitions[$canonicalType])) {
                if ((int) ($doc['is_current'] ?? 1) === 1) {
                    $supplementary[] = $doc;
                }
                continue;
            }

            if ((int) ($doc['is_current'] ?? 1) === 1 && !isset($groupedCurrent[$canonicalType])) {
                $groupedCurrent[$canonicalType] = $doc;
            } else {
                $groupedHistory[$canonicalType][] = $doc;
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

            $flaggedForAttention = in_array((string) $type, $attentionTypes, true)
                || in_array((string) ($meta['code'] ?? ''), $attentionTypes, true);
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

        $projectStatus = (string) ($project['status'] ?? '');
        $verificationStatus = (string) ($project['verification_status'] ?? Project::VERIFICATION_NOT_SUBMITTED);
        
        $verificationLabel = match (true) {
            $projectStatus === Project::STATUS_SUBMITTED_TO_PM_AND_AFIN => 'Submitted to Project Management and Admin Finance',
            $projectStatus === Project::STATUS_RESUBMITTED_TO_PM_AND_AFIN => 'Resubmitted to Project Management and Admin Finance',
            $projectStatus === Project::STATUS_UNDER_PM_REVIEW || $verificationStatus === Project::VERIFICATION_UNDER_REVIEW => 'Under Project Management Review',
            $projectStatus === Project::STATUS_RETURNED_FOR_CORRECTIONS || $projectStatus === Project::STATUS_RETURNED_TO_MARKETING || $verificationStatus === Project::VERIFICATION_RETURNED => 'Returned by Project Management for Corrections',
            $verificationStatus === Project::VERIFICATION_PENDING_PM => 'Awaiting Project Management Document Verification',
            $verificationStatus === Project::VERIFICATION_VERIFIED, $verificationStatus === Project::VERIFICATION_APPROVED_PM => 'Approved by Project Management',
            $verificationStatus === Project::VERIFICATION_FORWARDED_AFIN => 'Approved by PM & Forwarded to Admin Finance',
            $verificationStatus === Project::VERIFICATION_UNDER_AFIN_REVIEW => 'Under Admin Finance Review',
            $verificationStatus === Project::VERIFICATION_JOM_ISSUED => 'Verified & Official JOM Number Issued',
            $verificationStatus === Project::VERIFICATION_FORWARDED_OPS => 'Forwarded to Operations',
            default => 'Not Yet Submitted for Verification',
        };

        return [
            'workflow_type' => $categoryKey,
            'category_key' => $categoryKey,
            'category_label' => $categoryLabel,
            'is_po_required' => $isPoRequired,
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
