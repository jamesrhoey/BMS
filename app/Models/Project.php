<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\AuthorizationService;
use PDO;

final class Project
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PREPARING_DOCUMENTS = 'Preparing Documents';
    public const STATUS_SUBMITTED_TO_PM_AND_AFIN = 'Submitted to Project Management and Admin Finance';
    public const STATUS_RESUBMITTED_TO_PM_AND_AFIN = 'Resubmitted to Project Management and Admin Finance';
    public const STATUS_UNDER_PM_REVIEW = 'Under Project Management Review';
    public const STATUS_SUBMITTED_FOR_VERIFICATION = 'Submitted for Document Verification';
    public const STATUS_SUBMITTED_FOR_VERIFICATION_SHORT = 'Submitted for Verification';
    public const STATUS_UNDER_REVIEW = 'Under Review';
    public const STATUS_RETURNED_FOR_CORRECTIONS = 'Returned for Corrections';
    public const STATUS_RETURNED_TO_MARKETING = 'Returned to Marketing for Corrections';
    public const STATUS_RETURNED_TO_MARKETING_SHORT = 'Returned to Marketing';
    public const STATUS_RESUBMITTED_FOR_VERIFICATION = 'Resubmitted for Verification';
    public const STATUS_APPROVED_BY_PM = 'Approved by Project Management';
    public const STATUS_AWAITING_JOM_UPLOAD = 'Awaiting JOM Upload';
    public const STATUS_JOM_UPLOADED = 'JOM Uploaded';
    public const STATUS_VERIFICATION_COMPLETED = 'Approved by Project Management';
    public const STATUS_LEGACY_VERIFICATION_COMPLETED = 'Document Verification Completed';
    public const STATUS_FORWARDED_TO_ADMIN_FINANCE = 'Forwarded to Admin Finance';
    public const STATUS_UNDER_ADMIN_FINANCE_REVIEW = 'Under Admin Finance Review';
    public const STATUS_JOM_ISSUED = 'JOM Issued';
    public const STATUS_LEGACY_JOM_ISSUED = 'JOM Number Issued';
    public const STATUS_FORWARDED_TO_OPERATIONS = 'Forwarded to Operations';
    public const STATUS_FORWARDED_TO_OPS = 'Forwarded to OPS';
    public const STATUS_RECEIVED_BY_OPS = 'Received by OPS';
    public const STATUS_UNDER_OPS_PROCESSING = 'Under OPS Processing';
    public const STATUS_AWAITING_OPS_DOCUMENTS = 'Awaiting OPS Documents';
    public const STATUS_OPS_DOCUMENTS_COMPLETE = 'OPS Documents Complete';
    public const STATUS_SUBMITTED_TO_OPS_FINANCE = 'Submitted to OPS Finance';
    public const STATUS_PR_BEING_PREPARED = 'PR Being Prepared';
    public const STATUS_PR_SUBMITTED_TO_OPS_FINANCE = 'PR Submitted to OPS Finance';
    public const STATUS_ACTUAL_BOM_PENDING_UPLOAD = 'Actual BOM Pending Upload';
    public const STATUS_ACTUAL_BOM_SUBMITTED_TO_AFIN = 'Actual BOM Submitted to Admin Finance';
    public const STATUS_ACTUAL_BOM_RECEIVED = 'Actual BOM Received by Admin Finance';
    public const STATUS_ACTUAL_BOM_UNDER_REVIEW = 'Actual BOM Under Admin Finance Review';
    public const STATUS_ACTUAL_BOM_RETURNED = 'Actual BOM Returned for Corrections';
    public const STATUS_ACTUAL_BOM_ACCEPTED = 'Actual BOM Accepted by Admin Finance';
    // Retained for backward compatibility with initial foundation records
    public const STATUS_SUBMITTED = 'Submitted';

    public const CATEGORY_MRC = 'mrc';
    public const CATEGORY_SPECIAL_PROJECT = 'special_project';
    public const CATEGORY_LABEL_MRC = 'MRC';
    public const CATEGORY_LABEL_SPECIAL_PROJECT = 'Special Project';

    public const VERIFICATION_NOT_SUBMITTED = 'not_submitted';
    public const VERIFICATION_PENDING_PM = 'pending_pm_review';
    public const VERIFICATION_UNDER_REVIEW = 'under_review';
    public const VERIFICATION_RETURNED = 'returned_for_corrections';
    public const VERIFICATION_VERIFIED = 'verified_complete';
    public const VERIFICATION_APPROVED_PM = 'approved_by_pm';
    public const VERIFICATION_FORWARDED_AFIN = 'forwarded_to_admin_finance';
    public const VERIFICATION_UNDER_AFIN_REVIEW = 'under_admin_finance_review';
    public const VERIFICATION_JOM_ISSUED = 'jom_issued';
    public const VERIFICATION_FORWARDED_OPS = 'forwarded_to_operations';

    private const BASE_SELECT = '
        SELECT
            p.id,
            p.project_code,
            p.jom_number,
            p.jom_issued_by,
            p.jom_issued_at,
            p.project_name,
            p.client_name,
            p.client_contact,
            p.project_location,
            p.project_description,
            p.workflow_type,
            p.project_category,
            p.origin_department_id,
            p.current_department_id,
            p.created_by,
            p.status,
            p.submitted_by,
            p.submitted_at,
            p.submitted_to_afin_at,
            p.submitted_to_afin_by,
            p.verification_status,
            p.returned_by,
            p.returned_at,
            p.return_reason,
            p.attention_documents,
            p.reviewed_by,
            p.reviewed_at,
            p.verified_by,
            p.verified_at,
            p.verification_notes,
            p.forwarded_to_afin_by,
            p.forwarded_to_afin_at,
            p.forwarded_to_afin_notes,
            p.afin_reviewed_by,
            p.afin_reviewed_at,
            p.forwarded_to_ops_by,
            p.forwarded_to_ops_at,
            p.forwarded_to_ops_notes,
            p.ops_sld_document_id,
            p.ops_bom_document_id,
            p.jom_document_id,
            p.jom_uploaded_by,
            p.jom_uploaded_at,
            p.ops_jom_document_id,
            p.ops_son_document_id,
            p.ops_po_document_id,
            p.ops_handoff_documents,
            p.ops_received_at,
            p.ops_received_by,
            p.ops_processed_at,
            p.ops_final_sld_document_id,
            p.ops_final_bom_document_id,
            p.ops_request_pr_document_id,
            p.submitted_to_opfin_at,
            p.submitted_to_opfin_by,
            p.opfin_submission_notes,
            p.opfin_handoff_documents,
            p.created_at,
            p.updated_at,
            d.department_name AS current_department_name,
            d.department_code AS current_department_code,
            od.department_name AS origin_department_name,
            od.department_code AS origin_department_code,
            u.full_name AS creator_name,
            u.username AS creator_username,
            u.department_id AS creator_department_id,
            su.full_name AS submitter_name,
            afinsubu.full_name AS submitted_to_afin_by_name,
            ru.full_name AS returner_name,
            revu.full_name AS reviewer_name,
            vu.full_name AS verifier_name,
            afinu.full_name AS forwarded_to_afin_by_name,
            afinrevu.full_name AS afin_reviewed_by_name,
            opsfwdu.full_name AS forwarded_to_ops_by_name,
            opsrecu.full_name AS ops_receiver_name,
            opfinsubu.full_name AS submitted_to_opfin_by_name,
            jomu.full_name AS jom_issuer_name,
            jomupu.full_name AS jom_uploader_name,
            (SELECT COUNT(*) FROM project_documents pd WHERE pd.project_id = p.id AND pd.is_current = 1) AS document_count
        FROM projects p
        LEFT JOIN departments d ON d.id = p.current_department_id
        LEFT JOIN departments od ON od.id = p.origin_department_id
        INNER JOIN users u ON u.id = p.created_by
        LEFT JOIN users su ON su.id = p.submitted_by
        LEFT JOIN users afinsubu ON afinsubu.id = p.submitted_to_afin_by
        LEFT JOIN users ru ON ru.id = p.returned_by
        LEFT JOIN users revu ON revu.id = p.reviewed_by
        LEFT JOIN users vu ON vu.id = p.verified_by
        LEFT JOIN users afinu ON afinu.id = p.forwarded_to_afin_by
        LEFT JOIN users afinrevu ON afinrevu.id = p.afin_reviewed_by
        LEFT JOIN users opsfwdu ON opsfwdu.id = p.forwarded_to_ops_by
        LEFT JOIN users opsrecu ON opsrecu.id = p.ops_received_by
        LEFT JOIN users opfinsubu ON opfinsubu.id = p.submitted_to_opfin_by
        LEFT JOIN users jomu ON jomu.id = p.jom_issued_by
        LEFT JOIN users jomupu ON jomupu.id = p.jom_uploaded_by
    ';

    /**
     * @return list<string>
     */
    public static function availableStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PREPARING_DOCUMENTS,
            self::STATUS_SUBMITTED_TO_PM_AND_AFIN,
            self::STATUS_RESUBMITTED_TO_PM_AND_AFIN,
            self::STATUS_UNDER_PM_REVIEW,
            self::STATUS_SUBMITTED_FOR_VERIFICATION,
            self::STATUS_SUBMITTED_FOR_VERIFICATION_SHORT,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_RETURNED_FOR_CORRECTIONS,
            self::STATUS_RETURNED_TO_MARKETING,
            self::STATUS_RETURNED_TO_MARKETING_SHORT,
            self::STATUS_RESUBMITTED_FOR_VERIFICATION,
            self::STATUS_APPROVED_BY_PM,
            self::STATUS_AWAITING_JOM_UPLOAD,
            self::STATUS_JOM_UPLOADED,
            self::STATUS_LEGACY_VERIFICATION_COMPLETED,
            self::STATUS_FORWARDED_TO_ADMIN_FINANCE,
            self::STATUS_UNDER_ADMIN_FINANCE_REVIEW,
            self::STATUS_JOM_ISSUED,
            self::STATUS_LEGACY_JOM_ISSUED,
            self::STATUS_FORWARDED_TO_OPERATIONS,
            self::STATUS_FORWARDED_TO_OPS,
            self::STATUS_RECEIVED_BY_OPS,
            self::STATUS_UNDER_OPS_PROCESSING,
            self::STATUS_AWAITING_OPS_DOCUMENTS,
            self::STATUS_OPS_DOCUMENTS_COMPLETE,
            self::STATUS_SUBMITTED_TO_OPS_FINANCE,
            self::STATUS_PR_BEING_PREPARED,
            self::STATUS_PR_SUBMITTED_TO_OPS_FINANCE,
            self::STATUS_ACTUAL_BOM_PENDING_UPLOAD,
            self::STATUS_ACTUAL_BOM_SUBMITTED_TO_AFIN,
            self::STATUS_ACTUAL_BOM_RECEIVED,
            self::STATUS_ACTUAL_BOM_UNDER_REVIEW,
            self::STATUS_ACTUAL_BOM_RETURNED,
            self::STATUS_ACTUAL_BOM_ACCEPTED,
            self::STATUS_SUBMITTED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function marketingStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PREPARING_DOCUMENTS,
            self::STATUS_SUBMITTED_TO_PM_AND_AFIN,
            self::STATUS_RESUBMITTED_TO_PM_AND_AFIN,
            self::STATUS_UNDER_PM_REVIEW,
            self::STATUS_SUBMITTED_FOR_VERIFICATION,
            self::STATUS_RETURNED_FOR_CORRECTIONS,
            self::STATUS_RETURNED_TO_MARKETING,
            self::STATUS_RETURNED_TO_MARKETING_SHORT,
            self::STATUS_RESUBMITTED_FOR_VERIFICATION,
            self::STATUS_APPROVED_BY_PM,
            self::STATUS_FORWARDED_TO_ADMIN_FINANCE,
            self::STATUS_UNDER_ADMIN_FINANCE_REVIEW,
            self::STATUS_JOM_ISSUED,
            self::STATUS_FORWARDED_TO_OPERATIONS,
        ];
    }

    /**
     * Statuses in which Marketing may edit project fields, upload/replace documents, or submit to PM.
     *
     * @return list<string>
     */
    public static function marketingEditableStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PREPARING_DOCUMENTS,
            self::STATUS_RETURNED_FOR_CORRECTIONS,
            self::STATUS_RETURNED_TO_MARKETING,
            self::STATUS_RETURNED_TO_MARKETING_SHORT,
        ];
    }

    /**
     * Determine whether a project is in an editable Marketing stage.
     * Includes pre-Marketing-module legacy 'Submitted' records whose verification_status
     * is still 'not_submitted' (never verified or routed to Project Management).
     *
     * @param array<string, mixed> $project
     */
    public static function isMarketingEditable(array $project): bool
    {
        $status = (string) ($project['status'] ?? '');
        if (in_array($status, self::marketingEditableStatuses(), true)) {
            return true;
        }

        $verificationStatus = (string) ($project['verification_status'] ?? self::VERIFICATION_NOT_SUBMITTED);

        return $status === self::STATUS_SUBMITTED && $verificationStatus === self::VERIFICATION_NOT_SUBMITTED;
    }

    /**
     * Determine whether a project has been approved by Project Management.
     *
     * @param array<string, mixed> $project
     */
    public static function isApprovedByPm(array $project): bool
    {
        $status = (string) ($project['status'] ?? '');
        $vStatus = (string) ($project['verification_status'] ?? '');

        if (in_array($status, [self::STATUS_APPROVED_BY_PM, self::STATUS_LEGACY_VERIFICATION_COMPLETED], true)) {
            return true;
        }

        return !empty($project['verified_at'])
            || in_array($vStatus, [
                self::VERIFICATION_VERIFIED,
                self::VERIFICATION_APPROVED_PM,
                self::VERIFICATION_FORWARDED_AFIN,
                self::VERIFICATION_UNDER_AFIN_REVIEW,
                self::VERIFICATION_JOM_ISSUED,
                self::VERIFICATION_FORWARDED_OPS,
            ], true);
    }

    /**
     * Determine whether a project has already been forwarded to Admin Finance.
     *
     * @param array<string, mixed> $project
     */
    public static function isForwardedToAdminFinance(array $project): bool
    {
        $status = (string) ($project['status'] ?? '');
        $vStatus = (string) ($project['verification_status'] ?? '');

        return in_array($status, [
            self::STATUS_FORWARDED_TO_ADMIN_FINANCE,
            self::STATUS_UNDER_ADMIN_FINANCE_REVIEW,
            self::STATUS_JOM_ISSUED,
            self::STATUS_LEGACY_JOM_ISSUED,
            self::STATUS_FORWARDED_TO_OPERATIONS,
        ], true)
            || in_array($vStatus, [
                self::VERIFICATION_FORWARDED_AFIN,
                self::VERIFICATION_UNDER_AFIN_REVIEW,
                self::VERIFICATION_JOM_ISSUED,
                self::VERIFICATION_FORWARDED_OPS,
            ], true)
            || !empty($project['forwarded_to_afin_at']);
    }

    /**
     * Determine whether a project has a JOM number issued.
     *
     * @param array<string, mixed> $project
     */
    public static function isJomIssued(array $project): bool
    {
        $status = (string) ($project['status'] ?? '');

        return !empty($project['jom_number'])
            || in_array($status, [self::STATUS_JOM_ISSUED, self::STATUS_LEGACY_JOM_ISSUED, self::STATUS_FORWARDED_TO_OPERATIONS], true);
    }

    /**
     * Determine whether a project has already been forwarded to Operations by Admin Finance.
     *
     * @param array<string, mixed> $project
     */
    public static function isForwardedToOperations(array $project): bool
    {
        $status = (string) ($project['status'] ?? '');
        $vStatus = (string) ($project['verification_status'] ?? '');

        return in_array($status, [
            self::STATUS_FORWARDED_TO_OPERATIONS,
            self::STATUS_FORWARDED_TO_OPS,
            self::STATUS_RECEIVED_BY_OPS,
            self::STATUS_UNDER_OPS_PROCESSING,
            self::STATUS_AWAITING_OPS_DOCUMENTS,
            self::STATUS_OPS_DOCUMENTS_COMPLETE,
            self::STATUS_SUBMITTED_TO_OPS_FINANCE,
            self::STATUS_PR_BEING_PREPARED,
            self::STATUS_PR_SUBMITTED_TO_OPS_FINANCE,
            self::STATUS_ACTUAL_BOM_PENDING_UPLOAD,
            self::STATUS_ACTUAL_BOM_SUBMITTED_TO_AFIN,
            self::STATUS_ACTUAL_BOM_RECEIVED,
            self::STATUS_ACTUAL_BOM_UNDER_REVIEW,
            self::STATUS_ACTUAL_BOM_RETURNED,
            self::STATUS_ACTUAL_BOM_ACCEPTED,
        ], true)
            || $vStatus === self::VERIFICATION_FORWARDED_OPS
            || !empty($project['forwarded_to_ops_at']);
    }

    /**
     * Determine whether a project has been submitted to OPS Finance.
     *
     * @param array<string, mixed> $project
     */
    public static function isSubmittedToOpsFinance(array $project): bool
    {
        $status = (string) ($project['status'] ?? '');

        return !empty($project['submitted_to_opfin_at'])
            || in_array($status, [
                self::STATUS_SUBMITTED_TO_OPS_FINANCE,
                self::STATUS_PR_SUBMITTED_TO_OPS_FINANCE,
            ], true);
    }

    /**
     * Statuses representing projects queued with Project Management for document verification.
     *
     * @return list<string>
     */
    public static function pmVerificationQueueStatuses(): array
    {
        return [
            self::STATUS_SUBMITTED_TO_PM_AND_AFIN,
            self::STATUS_RESUBMITTED_TO_PM_AND_AFIN,
            self::STATUS_UNDER_PM_REVIEW,
            self::STATUS_SUBMITTED_FOR_VERIFICATION,
            self::STATUS_SUBMITTED_FOR_VERIFICATION_SHORT,
            self::STATUS_RESUBMITTED_FOR_VERIFICATION,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_SUBMITTED,
        ];
    }

    /**
     * Statuses representing projects that PM may actively inspect and make verification decisions on.
     *
     * @return list<string>
     */
    public static function pmReviewableStatuses(): array
    {
        return [
            self::STATUS_SUBMITTED_TO_PM_AND_AFIN,
            self::STATUS_RESUBMITTED_TO_PM_AND_AFIN,
            self::STATUS_UNDER_PM_REVIEW,
            self::STATUS_SUBMITTED_FOR_VERIFICATION,
            self::STATUS_SUBMITTED_FOR_VERIFICATION_SHORT,
            self::STATUS_RESUBMITTED_FOR_VERIFICATION,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_SUBMITTED,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(self::BASE_SELECT . ' WHERE p.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public static function codeExists(string $projectCode, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = Database::connection()->prepare(
                'SELECT 1 FROM projects WHERE project_code = :code AND id != :exclude_id LIMIT 1'
            );
            $stmt->execute(['code' => $projectCode, 'exclude_id' => $excludeId]);
        } else {
            $stmt = Database::connection()->prepare(
                'SELECT 1 FROM projects WHERE project_code = :code LIMIT 1'
            );
            $stmt->execute(['code' => $projectCode]);
        }

        return $stmt->fetchColumn() !== false;
    }

    public static function jomNumberExists(string $jomNumber, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = Database::connection()->prepare(
                'SELECT 1 FROM projects WHERE jom_number = :jom AND id != :exclude_id LIMIT 1'
            );
            $stmt->execute(['jom' => $jomNumber, 'exclude_id' => $excludeId]);
        } else {
            $stmt = Database::connection()->prepare(
                'SELECT 1 FROM projects WHERE jom_number = :jom LIMIT 1'
            );
            $stmt->execute(['jom' => $jomNumber]);
        }

        return $stmt->fetchColumn() !== false;
    }

    public static function findLatestJomNumberByPrefix(string $prefixPattern): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT jom_number FROM projects WHERE jom_number LIKE :pattern ORDER BY jom_number DESC LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['pattern' => $prefixPattern]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (string) $val : null;
    }

    public static function assignJomNumber(
        int $projectId,
        string $jomNumber,
        int $actorId,
        string $newStatus = self::STATUS_JOM_ISSUED
    ): bool {
        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET jom_number = :jom_number,
                 jom_issued_by = :actor_id,
                 jom_issued_at = NOW(),
                 status = :new_status,
                 verification_status = :v_status,
                 updated_at = NOW()
             WHERE id = :id AND jom_number IS NULL'
        );
        $stmt->execute([
            'id' => $projectId,
            'jom_number' => $jomNumber,
            'actor_id' => $actorId,
            'new_status' => $newStatus,
            'v_status' => self::VERIFICATION_VERIFIED,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Shared project list scoped to the user's authorized access.
     * Marketing users share team-wide visibility on Marketing-originated projects.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function listForUser(array $user, array $filters = []): array
    {
        $conditions = [];
        $params = [];

        self::applyUserAccessFilter($user, $conditions, $params, $filters);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(p.project_code LIKE :q1 OR p.project_name LIKE :q2 OR p.client_name LIKE :q3 OR p.project_location LIKE :q4)';
            $like = '%' . $search . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'pm_queue') {
            $conditions[] = "p.status IN ('Submitted for Document Verification', 'Submitted for Verification', 'Resubmitted for Verification', 'Under Review', 'Submitted')";
        } elseif ($status !== '' && in_array($status, self::availableStatuses(), true)) {
            $conditions[] = 'p.status = :status';
            $params['status'] = $status;
        }

        $sql = self::BASE_SELECT;
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY p.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public static function recentForUser(array $user, int $limit = 6): array
    {
        $conditions = [];
        $params = [];

        self::applyUserAccessFilter($user, $conditions, $params, []);

        $sql = self::BASE_SELECT;
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY p.id DESC LIMIT :limit_rows';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit_rows', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Database-driven statistics for the dedicated Marketing Dashboard.
     *
     * @param list<string> $requiredDocTypes
     * @param string $categoryFilter Optional category filter ('mrc' or 'special_project')
     * @return array{
     *   total_marketing_projects: int,
     *   mrc_projects: int,
     *   special_projects: int,
     *   draft_projects: int,
     *   awaiting_doc_prep: int,
     *   submitted_to_pm: int,
     *   submitted_projects: int,
     *   under_review_projects: int,
     *   returned_for_corrections: int,
     *   missing_documents: int
     * }
     */
    public static function marketingDashboardStats(int $marketingDeptId, array $requiredDocTypes, string $categoryFilter = ''): array
    {
        $reqCount = count($requiredDocTypes);
        $docPlaceholders = [];
        $params = [
            'mkt_origin' => $marketingDeptId,
            'mkt_curr' => $marketingDeptId,
            'mkt_creator' => $marketingDeptId,
            'req_count' => $reqCount,
        ];

        foreach (array_values($requiredDocTypes) as $idx => $docType) {
            $key = 'req_doc_' . $idx;
            $docPlaceholders[] = ':' . $key;
            $params[$key] = $docType;
        }

        $inListSql = $docPlaceholders !== [] ? implode(', ', $docPlaceholders) : "''";
        $categoryCondition = '';
        if ($categoryFilter === 'mrc') {
            $categoryCondition = " AND (p.workflow_type IN ('mrc', 'standard') OR p.project_category = 'MRC')";
        } elseif ($categoryFilter === 'special_project') {
            $categoryCondition = " AND (p.workflow_type = 'special_project' OR p.project_category = 'Special Project')";
        }

        $sql = "
            SELECT
                COUNT(*) AS total_marketing_projects,
                COALESCE(SUM(CASE WHEN sub.workflow_type IN ('mrc', 'standard') OR sub.project_category = 'MRC' THEN 1 ELSE 0 END), 0) AS mrc_projects,
                COALESCE(SUM(CASE WHEN sub.workflow_type = 'special_project' OR sub.project_category = 'Special Project' THEN 1 ELSE 0 END), 0) AS special_projects,
                COALESCE(SUM(CASE WHEN sub.status = 'Draft' THEN 1 ELSE 0 END), 0) AS draft_projects,
                COALESCE(SUM(CASE WHEN sub.status = 'Preparing Documents' THEN 1 ELSE 0 END), 0) AS awaiting_doc_prep,
                COALESCE(SUM(CASE WHEN sub.status IN ('Submitted to Project Management and Admin Finance', 'Resubmitted to Project Management and Admin Finance', 'Submitted for Document Verification', 'Submitted for Verification', 'Resubmitted for Verification', 'Under Review', 'Under Project Management Review', 'Submitted') THEN 1 ELSE 0 END), 0) AS submitted_to_pm,
                COALESCE(SUM(CASE WHEN sub.status IN ('Submitted to Project Management and Admin Finance', 'Resubmitted to Project Management and Admin Finance', 'Submitted for Document Verification', 'Submitted for Verification', 'Resubmitted for Verification', 'Submitted') THEN 1 ELSE 0 END), 0) AS submitted_projects,
                COALESCE(SUM(CASE WHEN sub.status IN ('Under Review', 'Under Project Management Review') THEN 1 ELSE 0 END), 0) AS under_review_projects,
                COALESCE(SUM(CASE WHEN sub.status IN ('Returned for Corrections', 'Returned to Marketing for Corrections', 'Returned to Marketing') THEN 1 ELSE 0 END), 0) AS returned_for_corrections,
                COALESCE(SUM(CASE WHEN sub.uploaded_req_docs < :req_count THEN 1 ELSE 0 END), 0) AS missing_documents
            FROM (
                SELECT
                    p.id,
                    p.status,
                    p.workflow_type,
                    p.project_category,
                    (
                        SELECT COUNT(DISTINCT pd.document_type)
                        FROM project_documents pd
                        WHERE pd.project_id = p.id
                          AND pd.is_current = 1
                          AND pd.document_type IN ({$inListSql})
                    ) AS uploaded_req_docs
                FROM projects p
                INNER JOIN users u ON u.id = p.created_by
                WHERE (p.origin_department_id = :mkt_origin
                   OR p.current_department_id = :mkt_curr
                   OR u.department_id = :mkt_creator)
                   {$categoryCondition}
            ) AS sub
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'total_marketing_projects' => (int) ($row['total_marketing_projects'] ?? 0),
            'mrc_projects' => (int) ($row['mrc_projects'] ?? 0),
            'special_projects' => (int) ($row['special_projects'] ?? 0),
            'draft_projects' => (int) ($row['draft_projects'] ?? 0),
            'awaiting_doc_prep' => (int) ($row['awaiting_doc_prep'] ?? 0),
            'submitted_to_pm' => (int) ($row['submitted_to_pm'] ?? 0),
            'submitted_projects' => (int) ($row['submitted_projects'] ?? 0),
            'under_review_projects' => (int) ($row['under_review_projects'] ?? 0),
            'returned_for_corrections' => (int) ($row['returned_for_corrections'] ?? 0),
            'missing_documents' => (int) ($row['missing_documents'] ?? 0),
        ];
    }

    /**
     * Paginated Marketing project list with search, workflow status filter,
     * category filter, document completeness filter, and creation date sorting.
     *
     * @param list<string> $requiredDocTypes
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function paginateMarketingProjects(
        int $marketingDeptId,
        array $requiredDocTypes,
        array $filters = [],
        int $page = 1,
        int $perPage = 12
    ): array {
        $reqCount = count($requiredDocTypes);
        $makeDocSubquery = static function (string $prefix, array &$targetParams) use ($requiredDocTypes): string {
            $placeholders = [];
            foreach (array_values($requiredDocTypes) as $idx => $docType) {
                $key = $prefix . $idx;
                $placeholders[] = ':' . $key;
                $targetParams[$key] = $docType;
            }
            $inSql = $placeholders !== [] ? implode(', ', $placeholders) : "''";

            return "(SELECT COUNT(DISTINCT pd.document_type)
                     FROM project_documents pd
                     WHERE pd.project_id = p.id
                       AND pd.is_current = 1
                       AND pd.document_type IN ({$inSql}))";
        };

        $whereParams = [
            'mkt_origin' => $marketingDeptId,
            'mkt_curr' => $marketingDeptId,
            'mkt_creator' => $marketingDeptId,
        ];

        $conditions = [
            '(p.origin_department_id = :mkt_origin OR p.current_department_id = :mkt_curr OR u.department_id = :mkt_creator)',
        ];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(p.project_code LIKE :q1 OR p.project_name LIKE :q2 OR p.client_name LIKE :q3)';
            $like = '%' . $search . '%';
            $whereParams['q1'] = $like;
            $whereParams['q2'] = $like;
            $whereParams['q3'] = $like;
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category === 'mrc') {
            $conditions[] = "(p.workflow_type IN ('mrc', 'standard') OR p.project_category = 'MRC')";
        } elseif ($category === 'special_project') {
            $conditions[] = "(p.workflow_type = 'special_project' OR p.project_category = 'Special Project')";
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'pm_queue' || $status === 'submitted' || $status === 'submitted_queue') {
            $conditions[] = "p.status IN ('Submitted to Project Management and Admin Finance', 'Resubmitted to Project Management and Admin Finance', 'Submitted for Document Verification', 'Submitted for Verification', 'Resubmitted for Verification', 'Submitted')";
        } elseif ($status === 'draft') {
            $conditions[] = "p.status = 'Draft'";
        } elseif ($status === 'returned') {
            $conditions[] = "p.status IN ('Returned for Corrections', 'Returned to Marketing for Corrections', 'Returned to Marketing')";
        } elseif ($status === 'under_review') {
            $conditions[] = "p.status IN ('Under Review', 'Under Project Management Review')";
        } elseif ($status !== '' && in_array($status, self::availableStatuses(), true)) {
            $conditions[] = 'p.status = :status';
            $whereParams['status'] = $status;
        }

        $completeness = trim((string) ($filters['completeness'] ?? ''));
        if ($completeness === 'complete') {
            $fltSubquery = $makeDocSubquery('flt_doc_', $whereParams);
            $conditions[] = "{$fltSubquery} >= :comp_req_count";
            $whereParams['comp_req_count'] = $reqCount;
        } elseif ($completeness === 'incomplete') {
            $fltSubquery = $makeDocSubquery('flt_doc_', $whereParams);
            $conditions[] = "{$fltSubquery} < :comp_req_count";
            $whereParams['comp_req_count'] = $reqCount;
        }

        $whereClause = ' WHERE ' . implode(' AND ', $conditions);

        $countSql = 'SELECT COUNT(*)
                     FROM projects p
                     INNER JOIN users u ON u.id = p.created_by' . $whereClause;
        $countStmt = Database::connection()->prepare($countSql);
        $countStmt->execute($whereParams);
        $total = (int) $countStmt->fetchColumn();

        $perPage = max(5, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sort = trim((string) ($filters['sort'] ?? 'newest'));
        $orderBy = $sort === 'oldest'
            ? ' ORDER BY p.created_at ASC, p.id ASC'
            : ' ORDER BY p.created_at DESC, p.id DESC';

        $dataParams = $whereParams;
        $selectSubquery = $makeDocSubquery('sel_doc_', $dataParams);

        $dataSql = "
            SELECT
                p.id,
                p.project_code,
                p.project_name,
                p.client_name,
                p.client_contact,
                p.project_location,
                p.workflow_type,
                p.project_category,
                p.origin_department_id,
                p.current_department_id,
                p.created_by,
                p.status,
                p.submitted_by,
                p.submitted_at,
                p.submitted_to_afin_at,
                p.submitted_to_afin_by,
                p.verification_status,
                p.returned_by,
                p.returned_at,
                p.return_reason,
                p.attention_documents,
                p.created_at,
                p.updated_at,
                d.department_name AS current_department_name,
                d.department_code AS current_department_code,
                u.full_name AS creator_name,
                u.username AS creator_username,
                {$selectSubquery} AS uploaded_required_count
            FROM projects p
            LEFT JOIN departments d ON d.id = p.current_department_id
            INNER JOIN users u ON u.id = p.created_by
            {$whereClause}
            {$orderBy}
            LIMIT :limit_rows OFFSET :offset_rows
        ";

        $stmt = Database::connection()->prepare($dataSql);
        foreach ($dataParams as $key => $val) {
            $stmt->bindValue(':' . $key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit_rows', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset_rows', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll();
        foreach ($items as &$item) {
            $uploaded = (int) ($item['uploaded_required_count'] ?? 0);
            $cat = (string) ($item['project_category'] ?? $item['workflow_type'] ?? 'mrc');
            $isSpecial = strtolower($cat) === 'special project' || strtolower($cat) === 'special_project';
            $itemRequiredCount = $isSpecial ? 7 : 6;
            $item['uploaded_required_count'] = $uploaded;
            $item['required_total_count'] = $itemRequiredCount;
            $item['is_documents_complete'] = $uploaded >= $itemRequiredCount;
            $item['category_label'] = $isSpecial ? 'Special Project' : 'MRC';
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Projects currently in Project Management's document verification review queue.
     *
     * @return list<array<string, mixed>>
     */
    public static function pmVerificationQueue(int $pmDeptId, int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . "
            WHERE p.current_department_id = :pm_dept_id
              AND p.status IN ('Submitted for Document Verification', 'Resubmitted for Verification', 'Submitted')
            ORDER BY p.submitted_at ASC, p.id ASC
            LIMIT :limit_rows"
        );
        $stmt->bindValue(':pm_dept_id', $pmDeptId, PDO::PARAM_INT);
        $stmt->bindValue(':limit_rows', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $workflowType = (string) ($data['workflow_type'] ?? 'mrc');
        $projectCategory = (string) ($data['project_category'] ?? ($workflowType === 'special_project' ? 'Special Project' : 'MRC'));

        $stmt = $pdo->prepare(
            'INSERT INTO projects (
                project_code,
                project_name,
                client_name,
                client_contact,
                project_location,
                project_description,
                workflow_type,
                project_category,
                origin_department_id,
                current_department_id,
                created_by,
                status,
                verification_status,
                submitted_at
            ) VALUES (
                :project_code,
                :project_name,
                :client_name,
                :client_contact,
                :project_location,
                :project_description,
                :workflow_type,
                :project_category,
                :origin_department_id,
                :current_department_id,
                :created_by,
                :status,
                :verification_status,
                :submitted_at
            )'
        );

        $stmt->execute([
            'project_code' => $data['project_code'],
            'project_name' => $data['project_name'],
            'client_name' => $data['client_name'],
            'client_contact' => $data['client_contact'] !== '' ? $data['client_contact'] : null,
            'project_location' => $data['project_location'] !== '' ? $data['project_location'] : null,
            'project_description' => $data['project_description'] !== '' ? $data['project_description'] : null,
            'workflow_type' => $workflowType,
            'project_category' => $projectCategory,
            'origin_department_id' => $data['origin_department_id'] ?? $data['current_department_id'],
            'current_department_id' => $data['current_department_id'],
            'created_by' => $data['created_by'],
            'status' => $data['status'],
            'verification_status' => $data['verification_status'] ?? self::VERIFICATION_NOT_SUBMITTED,
            'submitted_at' => $data['submitted_at'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Mark a project submitted simultaneously to Project Management and Admin Finance.
     */
    public static function markSubmittedSimultaneous(
        int $id,
        string $targetStatus,
        int $pmDepartmentId,
        int $actorId
    ): void {
        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET status = :status,
                 current_department_id = :pm_dept_id,
                 submitted_by = :submitted_by,
                 submitted_at = NOW(),
                 submitted_to_afin_by = :submitted_to_afin_by,
                 submitted_to_afin_at = NOW(),
                 verification_status = :verification_status,
                 attention_documents = NULL,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => $targetStatus,
            'pm_dept_id' => $pmDepartmentId,
            'submitted_by' => $actorId,
            'submitted_to_afin_by' => $actorId,
            'verification_status' => self::VERIFICATION_PENDING_PM,
            'id' => $id,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET project_name = :project_name,
                 client_name = :client_name,
                 client_contact = :client_contact,
                 project_location = :project_location,
                 project_description = :project_description,
                 current_department_id = :current_department_id
             WHERE id = :id'
        );
        $stmt->execute([
            'project_name' => $data['project_name'],
            'client_name' => $data['client_name'],
            'client_contact' => $data['client_contact'] !== '' ? $data['client_contact'] : null,
            'project_location' => $data['project_location'] !== '' ? $data['project_location'] : null,
            'project_description' => $data['project_description'] !== '' ? $data['project_description'] : null,
            'current_department_id' => $data['current_department_id'],
            'id' => $id,
        ]);
    }

    public static function updateStatus(int $id, string $newStatus): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE projects SET status = :status WHERE id = :id'
        );
        $stmt->execute([
            'status' => $newStatus,
            'id' => $id,
        ]);
    }

    public static function markSubmittedToProjectManagement(
        int $id,
        string $targetStatus,
        int $pmDepartmentId,
        int $submittedByUserId
    ): void {
        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET status = :status,
                 current_department_id = :pm_dept_id,
                 submitted_by = :submitted_by,
                 submitted_at = NOW(),
                 verification_status = :verification_status,
                 attention_documents = NULL
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => $targetStatus,
            'pm_dept_id' => $pmDepartmentId,
            'submitted_by' => $submittedByUserId,
            'verification_status' => self::VERIFICATION_PENDING_PM,
            'id' => $id,
        ]);
    }

    /**
     * @param list<string> $flaggedDocuments
     */
    public static function markReturnedToMarketing(
        int $id,
        int $marketingDepartmentId,
        int $returnedByUserId,
        string $returnReason,
        array $flaggedDocuments
    ): void {
        $attentionJson = $flaggedDocuments !== []
            ? json_encode(array_values($flaggedDocuments), JSON_UNESCAPED_UNICODE)
            : null;

        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET status = :status,
                 current_department_id = :mkt_dept_id,
                 verification_status = :verification_status,
                 returned_by = :returned_by,
                 returned_at = NOW(),
                 return_reason = :return_reason,
                 attention_documents = :attention_documents
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => self::STATUS_RETURNED_FOR_CORRECTIONS,
            'mkt_dept_id' => $marketingDepartmentId,
            'verification_status' => self::VERIFICATION_RETURNED,
            'returned_by' => $returnedByUserId,
            'return_reason' => $returnReason,
            'attention_documents' => $attentionJson,
            'id' => $id,
        ]);
    }

    public static function markUnderReview(int $id, int $reviewerUserId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET status = :status,
                 verification_status = :verification_status,
                 reviewed_by = :reviewed_by,
                 reviewed_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => self::STATUS_UNDER_REVIEW,
            'verification_status' => self::VERIFICATION_UNDER_REVIEW,
            'reviewed_by' => $reviewerUserId,
            'id' => $id,
        ]);
    }

    public static function markVerificationCompleted(
        int $id,
        int $verifiedByUserId,
        ?string $notes = null
    ): void {
        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET status = :status,
                 verification_status = :verification_status,
                 verified_by = :verified_by,
                 verified_at = NOW(),
                 verification_notes = :verification_notes
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => self::STATUS_APPROVED_BY_PM,
            'verification_status' => self::VERIFICATION_VERIFIED,
            'verified_by' => $verifiedByUserId,
            'verification_notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'id' => $id,
        ]);
    }

    public static function markForwardedToAdminFinance(
        int $id,
        int $adminFinanceDeptId,
        int $forwardedByUserId,
        ?string $notes = null
    ): bool {
        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET status = :status,
                 current_department_id = :afin_dept_id,
                 verification_status = :verification_status,
                 forwarded_to_afin_by = :forwarded_by,
                 forwarded_to_afin_at = NOW(),
                 forwarded_to_afin_notes = :forwarded_notes,
                 updated_at = NOW()
             WHERE id = :id
               AND forwarded_to_afin_at IS NULL'
        );
        $stmt->execute([
            'status' => self::STATUS_FORWARDED_TO_ADMIN_FINANCE,
            'afin_dept_id' => $adminFinanceDeptId,
            'verification_status' => self::VERIFICATION_FORWARDED_AFIN,
            'forwarded_by' => $forwardedByUserId,
            'forwarded_notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function markUnderAdminFinanceReview(int $id, int $reviewerUserId): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET status = :status,
                 verification_status = :verification_status,
                 afin_reviewed_by = :reviewed_by,
                 afin_reviewed_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND jom_number IS NULL'
        );
        $stmt->execute([
            'status' => self::STATUS_UNDER_ADMIN_FINANCE_REVIEW,
            'verification_status' => self::VERIFICATION_UNDER_AFIN_REVIEW,
            'reviewed_by' => $reviewerUserId,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function markForwardedToOperations(
        int $id,
        int $operationsDeptId,
        int $forwardedByUserId,
        int $sldDocumentId,
        int $bomDocumentId,
        ?string $notes = null
    ): bool {
        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET status = :status,
                 current_department_id = :ops_dept_id,
                 verification_status = :verification_status,
                 forwarded_to_ops_by = :forwarded_by,
                 forwarded_to_ops_at = NOW(),
                 forwarded_to_ops_notes = :forwarded_notes,
                 ops_sld_document_id = :sld_doc_id,
                 ops_bom_document_id = :bom_doc_id,
                 updated_at = NOW()
             WHERE id = :id
               AND jom_number IS NOT NULL
               AND forwarded_to_ops_at IS NULL'
        );
        $stmt->execute([
            'status' => self::STATUS_FORWARDED_TO_OPERATIONS,
            'ops_dept_id' => $operationsDeptId,
            'verification_status' => self::VERIFICATION_FORWARDED_OPS,
            'forwarded_by' => $forwardedByUserId,
            'forwarded_notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'sld_doc_id' => $sldDocumentId,
            'bom_doc_id' => $bomDocumentId,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Projects forwarded by Project Management to Admin Finance incoming queue.
     *
     * @return list<array<string, mixed>>
     */
    public static function adminFinanceIncomingQueue(int $afinDeptId, int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . "
            WHERE p.current_department_id = :afin_dept_id
               OR p.status IN ('Forwarded to Admin Finance', 'Under Admin Finance Review', 'JOM Issued', 'JOM Number Issued', 'Forwarded to Operations')
               OR p.verification_status IN ('forwarded_to_admin_finance', 'under_admin_finance_review', 'jom_issued', 'forwarded_to_operations')
               OR p.forwarded_to_afin_at IS NOT NULL
            ORDER BY COALESCE(p.forwarded_to_afin_at, p.updated_at) DESC, p.id DESC
            LIMIT :limit_rows"
        );
        $stmt->bindValue(':afin_dept_id', $afinDeptId, PDO::PARAM_INT);
        $stmt->bindValue(':limit_rows', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Database-driven metrics for the Admin Finance incoming project & JOM issuance dashboard.
     *
     * @return array{
     *   total_afin_projects: int,
     *   awaiting_jom_issuance: int,
     *   under_afin_review: int,
     *   jom_issued: int,
     *   forwarded_to_operations: int
     * }
     */
    public static function adminFinanceDashboardStats(int $afinDeptId): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total_afin_projects,
                COALESCE(SUM(CASE WHEN p.jom_number IS NULL AND p.status IN ('Forwarded to Admin Finance', 'Under Admin Finance Review') THEN 1 ELSE 0 END), 0) AS awaiting_jom_issuance,
                COALESCE(SUM(CASE WHEN p.status = 'Under Admin Finance Review' THEN 1 ELSE 0 END), 0) AS under_afin_review,
                COALESCE(SUM(CASE WHEN p.jom_number IS NOT NULL OR p.status IN ('JOM Issued', 'JOM Number Issued', 'Forwarded to Operations') THEN 1 ELSE 0 END), 0) AS jom_issued,
                COALESCE(SUM(CASE WHEN p.status = 'Forwarded to Operations' OR p.forwarded_to_ops_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS forwarded_to_operations
            FROM projects p
            WHERE p.current_department_id = :afin_dept_id
               OR p.status IN ('Forwarded to Admin Finance', 'Under Admin Finance Review', 'JOM Issued', 'JOM Number Issued', 'Forwarded to Operations')
               OR p.verification_status IN ('forwarded_to_admin_finance', 'under_admin_finance_review', 'jom_issued', 'forwarded_to_operations')
               OR p.forwarded_to_afin_at IS NOT NULL
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['afin_dept_id' => $afinDeptId]);
        $row = $stmt->fetch() ?: [];

        return [
            'total_afin_projects' => (int) ($row['total_afin_projects'] ?? 0),
            'awaiting_jom_issuance' => (int) ($row['awaiting_jom_issuance'] ?? 0),
            'under_afin_review' => (int) ($row['under_afin_review'] ?? 0),
            'jom_issued' => (int) ($row['jom_issued'] ?? 0),
            'forwarded_to_operations' => (int) ($row['forwarded_to_operations'] ?? 0),
        ];
    }

    /**
     * Paginated Admin Finance project list with search, status filtering, and sorting.
     *
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function paginateAdminFinanceProjects(
        int $afinDeptId,
        array $filters = [],
        int $page = 1,
        int $perPage = 10
    ): array {
        $conditions = [
            "(p.current_department_id = :afin_dept_id
              OR p.status IN ('Forwarded to Admin Finance', 'Under Admin Finance Review', 'JOM Issued', 'JOM Number Issued', 'Forwarded to Operations')
              OR p.verification_status IN ('forwarded_to_admin_finance', 'under_admin_finance_review', 'jom_issued', 'forwarded_to_operations')
              OR p.forwarded_to_afin_at IS NOT NULL)",
        ];
        $params = ['afin_dept_id' => $afinDeptId];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(p.project_code LIKE :q1 OR p.jom_number LIKE :q2 OR p.project_name LIKE :q3 OR p.client_name LIKE :q4)';
            $like = '%' . $search . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'incoming_approved') {
            $conditions[] = "(p.status = 'Forwarded to Admin Finance' AND p.jom_number IS NULL)";
        } elseif ($status === 'awaiting_jom' || $status === 'awaiting') {
            $conditions[] = "(p.jom_number IS NULL AND p.status IN ('Forwarded to Admin Finance', 'Under Admin Finance Review'))";
        } elseif ($status === 'under_review' || $status === 'under_afin_review') {
            $conditions[] = "p.status = 'Under Admin Finance Review'";
        } elseif ($status === 'jom_issued') {
            $conditions[] = "(p.jom_number IS NOT NULL AND p.status IN ('JOM Issued', 'JOM Number Issued'))";
        } elseif ($status === 'ready_for_operations' || $status === 'ready_ops') {
            $conditions[] = "(p.jom_number IS NOT NULL AND p.forwarded_to_ops_at IS NULL AND p.status IN ('JOM Issued', 'JOM Number Issued'))";
        } elseif ($status === 'forwarded_to_operations' || $status === 'forwarded_ops') {
            $conditions[] = "(p.status = 'Forwarded to Operations' OR p.forwarded_to_ops_at IS NOT NULL)";
        } elseif ($status === 'actual_bom_submitted' || $status === 'actual_bom_incoming') {
            $conditions[] = "p.status = 'Actual BOM Submitted to Admin Finance'";
        } elseif ($status === 'actual_bom_under_review') {
            $conditions[] = "p.status = 'Actual BOM Under Admin Finance Review'";
        } elseif ($status === 'actual_bom_returned') {
            $conditions[] = "p.status = 'Actual BOM Returned for Corrections'";
        } elseif ($status === 'actual_bom_accepted') {
            $conditions[] = "p.status = 'Actual BOM Accepted by Admin Finance'";
        } elseif ($status === 'po_records' || $status === 'has_po') {
            $conditions[] = "p.id IN (SELECT po_sub.project_id FROM purchase_orders po_sub)";
        } elseif ($status === 'po_issued') {
            $conditions[] = "p.id IN (SELECT po_sub.project_id FROM purchase_orders po_sub WHERE po_sub.status IN ('Issued to Supplier', 'Partially Received', 'Fully Received'))";
        } elseif ($status !== '' && in_array($status, self::availableStatuses(), true)) {
            $conditions[] = 'p.status = :status';
            $params['status'] = $status;
        }

        $whereClause = ' WHERE ' . implode(' AND ', $conditions);

        $countSql = "SELECT COUNT(*) FROM projects p {$whereClause}";
        $countStmt = Database::connection()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sort = (string) ($filters['sort'] ?? 'newest');
        $orderBy = match ($sort) {
            'oldest' => 'ORDER BY p.id ASC',
            'approved' => 'ORDER BY COALESCE(p.verified_at, p.forwarded_to_afin_at, p.updated_at) DESC, p.id DESC',
            'client' => 'ORDER BY p.client_name ASC, p.id DESC',
            default => 'ORDER BY COALESCE(p.forwarded_to_afin_at, p.updated_at) DESC, p.id DESC',
        };

        $dataSql = self::BASE_SELECT . "
            {$whereClause}
            {$orderBy}
            LIMIT :limit_rows OFFSET :offset_rows
        ";

        $dataStmt = Database::connection()->prepare($dataSql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $dataStmt->bindValue(':limit_rows', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset_rows', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'items' => $dataStmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Projects forwarded by Admin Finance to Operations incoming queue (with JOM, SLD, and Marketing BOM).
     *
     * @return list<array<string, mixed>>
     */
    public static function operationsIncomingQueue(int $opsDeptId, int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . "
            WHERE (p.current_department_id = :ops_dept_id AND p.jom_number IS NOT NULL)
               OR p.status = 'Forwarded to Operations'
               OR p.verification_status = 'forwarded_to_operations'
               OR p.forwarded_to_ops_at IS NOT NULL
            ORDER BY COALESCE(p.forwarded_to_ops_at, p.jom_issued_at, p.updated_at) DESC, p.id DESC
            LIMIT :limit_rows"
        );
        $stmt->bindValue(':ops_dept_id', $opsDeptId, PDO::PARAM_INT);
        $stmt->bindValue(':limit_rows', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $projId = (int) $row['id'];
            $handoff = ProjectDocument::operationsHandoffDocuments($projId);
            $row['handoff_sld'] = $handoff['sld'];
            $row['handoff_marketing_bom'] = $handoff['marketing_bom'];
        }
        unset($row);

        return $rows;
    }

    /**
     * Database-driven metrics for the Project Management dashboard summary cards.
     *
     * @return array{
     *   total_pm_projects: int,
     *   pending_verification: int,
     *   awaiting_verification: int,
     *   under_review: int,
     *   returned_to_marketing: int,
     *   resubmitted: int,
     *   resubmitted_for_verification: int,
     *   approved: int,
     *   verification_completed: int,
     *   forwarded_to_admin_finance: int,
     *   jom_issued: int
     * }
     */
    public static function pmDashboardStats(int $pmDeptId, string $categoryFilter = ''): array
    {
        $params = ['pm_dept_id' => $pmDeptId];
        $categoryCondition = '';
        $normalizedCat = strtolower(trim($categoryFilter));
        if ($normalizedCat === 'mrc') {
            $categoryCondition = " AND (p.workflow_type IN ('mrc', 'standard') OR p.project_category = 'MRC')";
        } elseif ($normalizedCat === 'special_project' || $normalizedCat === 'special project') {
            $categoryCondition = " AND (p.workflow_type = 'special_project' OR p.project_category = 'Special Project')";
        }

        $sql = "
            SELECT
                COUNT(*) AS total_pm_projects,
                COUNT(*) AS total_received,
                COALESCE(SUM(CASE WHEN p.workflow_type IN ('mrc', 'standard') OR p.project_category = 'MRC' THEN 1 ELSE 0 END), 0) AS mrc_projects,
                COALESCE(SUM(CASE WHEN p.workflow_type = 'special_project' OR p.project_category = 'Special Project' THEN 1 ELSE 0 END), 0) AS special_projects,
                COALESCE(SUM(CASE WHEN p.status IN ('Submitted to Project Management and Admin Finance', 'Submitted for Document Verification', 'Submitted for Verification', 'Submitted') THEN 1 ELSE 0 END), 0) AS pending_verification,
                COALESCE(SUM(CASE WHEN p.status IN ('Under Review', 'Under Project Management Review') THEN 1 ELSE 0 END), 0) AS under_review,
                COALESCE(SUM(CASE WHEN p.status IN ('Returned for Corrections', 'Returned to Marketing for Corrections', 'Returned to Marketing') THEN 1 ELSE 0 END), 0) AS returned_to_marketing,
                COALESCE(SUM(CASE WHEN p.status IN ('Resubmitted to Project Management and Admin Finance', 'Resubmitted for Verification') THEN 1 ELSE 0 END), 0) AS resubmitted,
                COALESCE(SUM(CASE WHEN p.status IN ('Approved by Project Management', 'Document Verification Completed', 'Awaiting JOM Upload', 'JOM Uploaded', 'Forwarded to OPS', 'Forwarded to Operations') OR (p.verification_status IN ('verified_complete', 'approved_by_pm')) THEN 1 ELSE 0 END), 0) AS approved,
                COALESCE(SUM(CASE WHEN (p.status IN ('Approved by Project Management', 'Awaiting JOM Upload', 'Document Verification Completed') OR p.verification_status IN ('verified_complete', 'approved_by_pm')) AND (p.jom_document_id IS NULL AND p.jom_number IS NULL) AND p.status NOT IN ('Forwarded to OPS', 'Forwarded to Operations', 'JOM Uploaded') THEN 1 ELSE 0 END), 0) AS awaiting_jom_upload,
                COALESCE(SUM(CASE WHEN p.status IN ('Forwarded to OPS', 'Forwarded to Operations') OR p.forwarded_to_ops_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS forwarded_to_ops,
                COALESCE(SUM(CASE WHEN p.status IN ('Forwarded to Admin Finance', 'Under Admin Finance Review', 'JOM Issued', 'JOM Number Issued', 'Forwarded to Operations') OR p.verification_status IN ('forwarded_to_admin_finance', 'under_admin_finance_review', 'jom_issued', 'forwarded_to_operations') OR p.forwarded_to_afin_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS forwarded_to_admin_finance,
                COALESCE(SUM(CASE WHEN p.jom_number IS NOT NULL OR p.status IN ('JOM Issued', 'JOM Number Issued') THEN 1 ELSE 0 END), 0) AS jom_issued
            FROM projects p
            WHERE (p.current_department_id = :pm_dept_id
               OR p.verification_status != 'not_submitted'
               OR p.submitted_at IS NOT NULL)
               {$categoryCondition}
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        $pending = (int) ($row['pending_verification'] ?? 0);
        $resubmitted = (int) ($row['resubmitted'] ?? 0);
        $approved = (int) ($row['approved'] ?? 0);
        $awaitingJom = (int) ($row['awaiting_jom_upload'] ?? 0);
        $forwardedOps = (int) ($row['forwarded_to_ops'] ?? 0);

        return [
            'total_pm_projects' => (int) ($row['total_pm_projects'] ?? 0),
            'total_received' => (int) ($row['total_received'] ?? 0),
            'mrc_projects' => (int) ($row['mrc_projects'] ?? 0),
            'special_projects' => (int) ($row['special_projects'] ?? 0),
            'pending_verification' => $pending,
            'awaiting_verification' => $pending,
            'under_review' => (int) ($row['under_review'] ?? 0),
            'returned_to_marketing' => (int) ($row['returned_to_marketing'] ?? 0),
            'resubmitted' => $resubmitted,
            'resubmitted_projects' => $resubmitted,
            'resubmitted_for_verification' => $resubmitted,
            'approved' => $approved,
            'approved_projects' => $approved,
            'verification_completed' => $approved,
            'awaiting_jom_upload' => $awaitingJom,
            'forwarded_to_ops' => $forwardedOps,
            'forwarded_to_admin_finance' => (int) ($row['forwarded_to_admin_finance'] ?? 0),
            'jom_issued' => (int) ($row['jom_issued'] ?? 0),
        ];
    }

    /**
     * Paginated Project Management project list with search, status filtering, category filtering,
     * date range filtering, and sorting.
     *
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function paginatePmProjects(
        int $pmDeptId,
        array $filters = [],
        int $page = 1,
        int $perPage = 10
    ): array {
        $conditions = [
            "(p.current_department_id = :pm_dept_id OR p.verification_status != 'not_submitted' OR p.submitted_at IS NOT NULL)",
        ];
        $params = ['pm_dept_id' => $pmDeptId];

        // Search Keyword
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(p.project_code LIKE :q1 OR p.jom_number LIKE :q2 OR p.project_name LIKE :q3 OR p.client_name LIKE :q4)';
            $like = '%' . $search . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        // Category Filter
        $category = strtolower(trim((string) ($filters['category'] ?? '')));
        if ($category === 'mrc') {
            $conditions[] = "(p.workflow_type IN ('mrc', 'standard') OR p.project_category = 'MRC')";
        } elseif ($category === 'special_project' || $category === 'special project') {
            $conditions[] = "(p.workflow_type = 'special_project' OR p.project_category = 'Special Project')";
        }

        // Submission Date Range
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $conditions[] = 'DATE(COALESCE(p.submitted_at, p.created_at)) >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $conditions[] = 'DATE(COALESCE(p.submitted_at, p.created_at)) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        // Status Filter
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'awaiting' || $status === 'pending' || $status === 'incoming') {
            $conditions[] = "p.status IN ('Submitted to Project Management and Admin Finance', 'Submitted for Document Verification', 'Submitted for Verification', 'Submitted')";
        } elseif ($status === 'verification_queue' || $status === 'pm_queue' || $status === 'verification') {
            $conditions[] = "p.status IN ('Submitted to Project Management and Admin Finance', 'Resubmitted to Project Management and Admin Finance', 'Under Project Management Review', 'Submitted for Document Verification', 'Submitted for Verification', 'Submitted', 'Resubmitted for Verification', 'Under Review')";
        } elseif ($status === 'under_review') {
            $conditions[] = "p.status IN ('Under Review', 'Under Project Management Review')";
        } elseif ($status === 'returned') {
            $conditions[] = "p.status IN ('Returned to Marketing', 'Returned for Corrections', 'Returned to Marketing for Corrections')";
        } elseif ($status === 'resubmitted') {
            $conditions[] = "p.status IN ('Resubmitted to Project Management and Admin Finance', 'Resubmitted for Verification')";
        } elseif ($status === 'awaiting_jom' || $status === 'awaiting_jom_upload') {
            $conditions[] = "(p.status IN ('Approved by Project Management', 'Awaiting JOM Upload') AND p.jom_document_id IS NULL AND p.jom_number IS NULL)";
        } elseif ($status === 'jom_uploaded') {
            $conditions[] = "(p.status = 'JOM Uploaded' OR p.jom_document_id IS NOT NULL)";
        } elseif (in_array($status, ['approved', 'completed', 'verified'], true)) {
            $conditions[] = "(p.status IN ('Approved by Project Management', 'Document Verification Completed', 'Awaiting JOM Upload', 'JOM Uploaded') OR (p.verification_status IN ('verified_complete', 'approved_by_pm') AND p.status NOT IN ('Forwarded to OPS', 'Forwarded to Operations')))";
        } elseif (in_array($status, ['forwarded_ops', 'forwarded_to_ops'], true)) {
            $conditions[] = "(p.status IN ('Forwarded to OPS', 'Forwarded to Operations') OR p.forwarded_to_ops_at IS NOT NULL)";
        } elseif ($status === 'history') {
            // All PM projects
        } elseif ($status !== '' && in_array($status, self::availableStatuses(), true)) {
            $conditions[] = 'p.status = :status';
            $params['status'] = $status;
        }

        $whereClause = ' WHERE ' . implode(' AND ', $conditions);

        $countSql = "SELECT COUNT(*) FROM projects p {$whereClause}";
        $countStmt = Database::connection()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sort = (string) ($filters['sort'] ?? 'newest');
        $orderBy = match ($sort) {
            'oldest' => 'ORDER BY p.id ASC',
            'submitted' => 'ORDER BY COALESCE(p.submitted_at, p.created_at) DESC, p.id DESC',
            'client' => 'ORDER BY p.client_name ASC, p.id DESC',
            default => 'ORDER BY p.id DESC',
        };

        $dataSql = self::BASE_SELECT . "
            {$whereClause}
            {$orderBy}
            LIMIT :limit_rows OFFSET :offset_rows
        ";

        $dataStmt = Database::connection()->prepare($dataSql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $dataStmt->bindValue(':limit_rows', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset_rows', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $items = $dataStmt->fetchAll();
        foreach ($items as &$item) {
            $cat = (string) ($item['project_category'] ?? $item['workflow_type'] ?? 'mrc');
            $isSpecial = (strcasecmp($cat, 'Special Project') === 0 || strtolower($cat) === 'special_project');
            $reqCount = $isSpecial ? 7 : 6;
            $item['category_label'] = $isSpecial ? 'Special Project' : 'MRC';
            $item['required_count'] = $reqCount;
            $uploadedCount = (int) ($item['document_count'] ?? 0);
            $item['uploaded_required_count'] = $uploadedCount;
            $item['is_documents_complete'] = ($uploadedCount >= $reqCount);
            $item['has_jom'] = !empty($item['jom_document_id']) || !empty($item['jom_number']);
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Mark project as Approved by Project Management.
     */
    public static function markApprovedByPm(
        int $id,
        int $verifiedByUserId,
        ?string $notes = null
    ): void {
        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET status = :status,
                 verification_status = :verification_status,
                 verified_by = :verified_by,
                 verified_at = NOW(),
                 verification_notes = :notes,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => self::STATUS_APPROVED_BY_PM,
            'verification_status' => self::VERIFICATION_APPROVED_PM,
            'verified_by' => $verifiedByUserId,
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'id' => $id,
        ]);
    }

    /**
     * Mark project status as JOM Uploaded after Project Management uploads the JOM file.
     */
    public static function markJomUploaded(
        int $id,
        int $jomDocId,
        int $actorId
    ): void {
        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET status = :status,
                 jom_document_id = :jom_doc_id,
                 jom_uploaded_by = :actor_id,
                 jom_uploaded_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => self::STATUS_JOM_UPLOADED,
            'jom_doc_id' => $jomDocId,
            'actor_id' => $actorId,
            'id' => $id,
        ]);
    }

    /**
     * Forward project from Project Management to Operations with category-specific documents.
     */
    public static function markForwardedToOpsByPm(
        int $id,
        int $operationsDeptId,
        int $forwardedByUserId,
        int $jomDocId,
        int $sonDocId,
        int $bomDocId,
        int $sldDocId,
        ?int $poDocId = null,
        ?string $notes = null,
        ?array $handoffDocs = null
    ): bool {
        $handoffJson = $handoffDocs !== null ? json_encode($handoffDocs, JSON_UNESCAPED_UNICODE) : null;
        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET status = :status,
                 current_department_id = :ops_dept_id,
                 verification_status = :verification_status,
                 forwarded_to_ops_by = :forwarded_by,
                 forwarded_to_ops_at = NOW(),
                 forwarded_to_ops_notes = :forwarded_notes,
                 ops_jom_document_id = :jom_doc_id,
                 ops_son_document_id = :son_doc_id,
                 ops_bom_document_id = :bom_doc_id,
                 ops_sld_document_id = :sld_doc_id,
                 ops_po_document_id = :po_doc_id,
                 ops_handoff_documents = :handoff_json,
                 updated_at = NOW()
             WHERE id = :id
               AND forwarded_to_ops_at IS NULL'
        );
        $stmt->execute([
            'status' => self::STATUS_FORWARDED_TO_OPS,
            'ops_dept_id' => $operationsDeptId,
            'verification_status' => self::VERIFICATION_FORWARDED_OPS,
            'forwarded_by' => $forwardedByUserId,
            'forwarded_notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'jom_doc_id' => $jomDocId,
            'son_doc_id' => $sonDocId,
            'bom_doc_id' => $bomDocId,
            'sld_doc_id' => $sldDocId,
            'po_doc_id' => $poDocId,
            'handoff_json' => $handoffJson,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Database-driven metrics for the Operations dashboard summary cards.
     *
     * @return array{
     *   total_received: int,
     *   mrc_projects: int,
     *   special_projects: int,
     *   pending_processing: int,
     *   under_processing: int,
     *   missing_documents: int,
     *   documents_complete: int,
     *   submitted_to_opfin: int
     * }
     */
    public static function opsDashboardStats(int $opsDeptId, string $categoryFilter = ''): array
    {
        $params = ['ops_dept_id' => $opsDeptId];
        $categoryCondition = '';
        $normalizedCat = strtolower(trim($categoryFilter));
        if ($normalizedCat === 'mrc') {
            $categoryCondition = " AND (p.workflow_type IN ('mrc', 'standard') OR p.project_category = 'MRC')";
        } elseif ($normalizedCat === 'special_project' || $normalizedCat === 'special project') {
            $categoryCondition = " AND (p.workflow_type = 'special_project' OR p.project_category = 'Special Project')";
        }

        $sql = "
            SELECT
                COUNT(*) AS total_received,
                COALESCE(SUM(CASE WHEN p.workflow_type IN ('mrc', 'standard') OR p.project_category = 'MRC' THEN 1 ELSE 0 END), 0) AS mrc_projects,
                COALESCE(SUM(CASE WHEN p.workflow_type = 'special_project' OR p.project_category = 'Special Project' THEN 1 ELSE 0 END), 0) AS special_projects,
                COALESCE(SUM(CASE WHEN p.status IN ('Forwarded to OPS', 'Forwarded to Operations', 'Received by OPS') AND p.ops_processed_at IS NULL AND p.submitted_to_opfin_at IS NULL THEN 1 ELSE 0 END), 0) AS pending_processing,
                COALESCE(SUM(CASE WHEN (p.status IN ('Under OPS Processing', 'Awaiting OPS Documents') OR (p.ops_processed_at IS NOT NULL AND (p.ops_final_sld_document_id IS NULL OR p.ops_final_bom_document_id IS NULL OR p.ops_request_pr_document_id IS NULL))) AND p.submitted_to_opfin_at IS NULL THEN 1 ELSE 0 END), 0) AS under_processing,
                COALESCE(SUM(CASE WHEN (p.ops_final_sld_document_id IS NULL OR p.ops_final_bom_document_id IS NULL OR p.ops_request_pr_document_id IS NULL) AND p.submitted_to_opfin_at IS NULL THEN 1 ELSE 0 END), 0) AS missing_documents,
                COALESCE(SUM(CASE WHEN (p.ops_final_sld_document_id IS NOT NULL AND p.ops_final_bom_document_id IS NOT NULL AND p.ops_request_pr_document_id IS NOT NULL) AND p.submitted_to_opfin_at IS NULL THEN 1 ELSE 0 END), 0) AS documents_complete,
                COALESCE(SUM(CASE WHEN p.submitted_to_opfin_at IS NOT NULL OR p.status = 'Submitted to OPS Finance' THEN 1 ELSE 0 END), 0) AS submitted_to_opfin
            FROM projects p
            WHERE (p.forwarded_to_ops_at IS NOT NULL
               OR p.current_department_id = :ops_dept_id
               OR p.status IN ('Forwarded to OPS', 'Forwarded to Operations', 'Received by OPS', 'Under OPS Processing', 'Awaiting OPS Documents', 'OPS Documents Complete', 'Submitted to OPS Finance'))
               {$categoryCondition}
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'total_received' => (int) ($row['total_received'] ?? 0),
            'mrc_projects' => (int) ($row['mrc_projects'] ?? 0),
            'special_projects' => (int) ($row['special_projects'] ?? 0),
            'pending_processing' => (int) ($row['pending_processing'] ?? 0),
            'under_processing' => (int) ($row['under_processing'] ?? 0),
            'missing_documents' => (int) ($row['missing_documents'] ?? 0),
            'documents_complete' => (int) ($row['documents_complete'] ?? 0),
            'submitted_to_opfin' => (int) ($row['submitted_to_opfin'] ?? 0),
        ];
    }

    /**
     * Paginated Operations project list with search, status filtering, category filtering,
     * date range filtering, and sorting.
     *
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function paginateOpsProjects(
        int $opsDeptId,
        array $filters = [],
        int $page = 1,
        int $perPage = 12
    ): array {
        $conditions = [
            "(p.forwarded_to_ops_at IS NOT NULL OR p.current_department_id = :ops_dept_id OR p.status IN ('Forwarded to OPS', 'Forwarded to Operations', 'Received by OPS', 'Under OPS Processing', 'Awaiting OPS Documents', 'OPS Documents Complete', 'Submitted to OPS Finance'))",
        ];
        $params = ['ops_dept_id' => $opsDeptId];

        // Search Keyword
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(p.project_code LIKE :q1 OR p.jom_number LIKE :q2 OR p.project_name LIKE :q3 OR p.client_name LIKE :q4)';
            $like = '%' . $search . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        // Category Filter
        $category = strtolower(trim((string) ($filters['category'] ?? '')));
        if ($category === 'mrc') {
            $conditions[] = "(p.workflow_type IN ('mrc', 'standard') OR p.project_category = 'MRC')";
        } elseif ($category === 'special_project' || $category === 'special project') {
            $conditions[] = "(p.workflow_type = 'special_project' OR p.project_category = 'Special Project')";
        }

        // Status Filter
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if (in_array($status, ['incoming', 'received', 'pending'], true)) {
            $conditions[] = "(p.status IN ('Forwarded to OPS', 'Forwarded to Operations', 'Received by OPS') AND p.ops_processed_at IS NULL AND p.submitted_to_opfin_at IS NULL)";
        } elseif (in_array($status, ['processing', 'in_progress'], true)) {
            $conditions[] = "((p.status IN ('Under OPS Processing', 'Awaiting OPS Documents') OR (p.ops_processed_at IS NOT NULL AND (p.ops_final_sld_document_id IS NULL OR p.ops_final_bom_document_id IS NULL OR p.ops_request_pr_document_id IS NULL))) AND p.submitted_to_opfin_at IS NULL)";
        } elseif (in_array($status, ['missing_documents', 'missing_ops_documents'], true)) {
            $conditions[] = "((p.ops_final_sld_document_id IS NULL OR p.ops_final_bom_document_id IS NULL OR p.ops_request_pr_document_id IS NULL) AND p.submitted_to_opfin_at IS NULL)";
        } elseif (in_array($status, ['documents_complete', 'complete'], true)) {
            $conditions[] = "(p.ops_final_sld_document_id IS NOT NULL AND p.ops_final_bom_document_id IS NOT NULL AND p.ops_request_pr_document_id IS NOT NULL AND p.submitted_to_opfin_at IS NULL)";
        } elseif (in_array($status, ['submitted_opfin', 'submitted_to_ops_finance', 'submitted'], true)) {
            $conditions[] = "(p.submitted_to_opfin_at IS NOT NULL OR p.status = 'Submitted to OPS Finance')";
        } elseif ($status === 'history') {
            $conditions[] = "(p.submitted_to_opfin_at IS NOT NULL OR p.forwarded_to_ops_at IS NOT NULL)";
        }

        // Date Range (forwarded_to_ops_at or ops_received_at)
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $conditions[] = 'DATE(COALESCE(p.forwarded_to_ops_at, p.ops_received_at, p.created_at)) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $conditions[] = 'DATE(COALESCE(p.forwarded_to_ops_at, p.ops_received_at, p.created_at)) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $conditions);

        // Count Total
        $countSql = "SELECT COUNT(*) FROM projects p {$whereSql}";
        $countStmt = Database::connection()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Sorting
        $sort = strtolower(trim((string) ($filters['sort'] ?? 'received')));
        $orderSql = match ($sort) {
            'oldest' => 'ORDER BY COALESCE(p.forwarded_to_ops_at, p.ops_received_at, p.created_at) ASC, p.id ASC',
            'name' => 'ORDER BY p.project_name ASC',
            'code' => 'ORDER BY p.project_code ASC',
            'status' => 'ORDER BY p.status ASC, p.id DESC',
            default => 'ORDER BY COALESCE(p.forwarded_to_ops_at, p.ops_received_at, p.created_at) DESC, p.id DESC',
        };

        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $dataSql = self::BASE_SELECT . " {$whereSql} {$orderSql} LIMIT {$perPage} OFFSET {$offset}";
        $dataStmt = Database::connection()->prepare($dataSql);
        foreach ($params as $key => $val) {
            $dataStmt->bindValue($key, $val);
        }
        $dataStmt->execute();

        $items = $dataStmt->fetchAll();
        foreach ($items as &$item) {
            $cat = (string) ($item['project_category'] ?? $item['workflow_type'] ?? 'mrc');
            $isSpecial = (strcasecmp($cat, 'Special Project') === 0 || strtolower($cat) === 'special_project');
            $item['category_label'] = $isSpecial ? 'Special Project' : 'MRC';
            $item['has_final_sld'] = !empty($item['ops_final_sld_document_id']);
            $item['has_final_bom'] = !empty($item['ops_final_bom_document_id']);
            $item['has_request_pr'] = !empty($item['ops_request_pr_document_id']);
            $opsUploadedCount = ($item['has_final_sld'] ? 1 : 0) + ($item['has_final_bom'] ? 1 : 0) + ($item['has_request_pr'] ? 1 : 0);
            $item['ops_uploaded_count'] = $opsUploadedCount;
            $item['is_ops_complete'] = ($opsUploadedCount === 3);
            $item['is_submitted_to_opfin'] = !empty($item['submitted_to_opfin_at']) || ($item['status'] === self::STATUS_SUBMITTED_TO_OPS_FINANCE);
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Mark an OPS document uploaded and update OPS processing state.
     */
    public static function markOpsDocumentUploaded(
        int $id,
        string $column,
        int $docId,
        int $actorId
    ): bool {
        $validColumns = [
            'ops_final_sld_document_id',
            'ops_final_bom_document_id',
            'ops_request_pr_document_id',
        ];
        if (!in_array($column, $validColumns, true)) {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "UPDATE projects
             SET {$column} = :doc_id,
                 ops_received_at = COALESCE(ops_received_at, NOW()),
                 ops_received_by = COALESCE(ops_received_by, :actor_id_rec),
                 ops_processed_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'doc_id' => $docId,
            'actor_id_rec' => $actorId,
            'id' => $id,
        ]);

        $chkStmt = $pdo->prepare(
            "SELECT ops_final_sld_document_id, ops_final_bom_document_id, ops_request_pr_document_id, status, submitted_to_opfin_at
             FROM projects
             WHERE id = :id"
        );
        $chkStmt->execute(['id' => $id]);
        $row = $chkStmt->fetch();

        if ($row && empty($row['submitted_to_opfin_at'])) {
            $hasSld = !empty($row['ops_final_sld_document_id']);
            $hasBom = !empty($row['ops_final_bom_document_id']);
            $hasPr = !empty($row['ops_request_pr_document_id']);

            $newStatus = ($hasSld && $hasBom && $hasPr)
                ? self::STATUS_OPS_DOCUMENTS_COMPLETE
                : self::STATUS_UNDER_OPS_PROCESSING;

            $upStatus = $pdo->prepare("UPDATE projects SET status = :status WHERE id = :id");
            $upStatus->execute(['status' => $newStatus, 'id' => $id]);
        }

        return true;
    }

    /**
     * Mark project submitted to Operations Finance.
     */
    public static function markSubmittedToOpsFinance(
        int $id,
        int $opfinDeptId,
        int $actorId,
        ?string $notes = null,
        ?array $handoffSummary = null
    ): bool {
        $handoffJson = $handoffSummary !== null ? json_encode($handoffSummary, JSON_UNESCAPED_UNICODE) : null;
        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET status = :status,
                 current_department_id = :opfin_dept_id,
                 submitted_to_opfin_at = NOW(),
                 submitted_to_opfin_by = :actor_id,
                 opfin_submission_notes = :notes,
                 opfin_handoff_documents = :handoff_json,
                 updated_at = NOW()
             WHERE id = :id
               AND submitted_to_opfin_at IS NULL'
        );
        $stmt->execute([
            'status' => self::STATUS_SUBMITTED_TO_OPS_FINANCE,
            'opfin_dept_id' => $opfinDeptId,
            'actor_id' => $actorId,
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'handoff_json' => $handoffJson,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array{total_projects: int, draft_projects: int, submitted_projects: int}
     */
    public static function dashboardCounts(): array
    {
        $row = Database::connection()->query(
            "SELECT
                COUNT(*) AS total_projects,
                COALESCE(SUM(CASE WHEN status IN ('Draft', 'Preparing Documents', 'Returned for Corrections', 'Returned to Marketing for Corrections') THEN 1 ELSE 0 END), 0) AS draft_projects,
                COALESCE(SUM(CASE WHEN status IN ('Submitted', 'Submitted for Document Verification', 'Submitted for Verification', 'Resubmitted for Verification', 'Under Review', 'Approved by Project Management', 'Document Verification Completed', 'Forwarded to Admin Finance', 'Under Admin Finance Review', 'JOM Issued', 'JOM Number Issued', 'Forwarded to Operations') THEN 1 ELSE 0 END), 0) AS submitted_projects
             FROM projects"
        )->fetch();

        return [
            'total_projects' => (int) ($row['total_projects'] ?? 0),
            'draft_projects' => (int) ($row['draft_projects'] ?? 0),
            'submitted_projects' => (int) ($row['submitted_projects'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array{total_projects: int, draft_projects: int, submitted_projects: int}
     */
    public static function countsForUser(array $user): array
    {
        $conditions = [];
        $params = [];
        self::applyUserAccessFilter($user, $conditions, $params, []);
        $whereSql = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';

        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*) AS total_projects,
                COALESCE(SUM(CASE WHEN p.status IN ('Draft', 'Preparing Documents', 'Returned for Corrections', 'Returned to Marketing for Corrections') THEN 1 ELSE 0 END), 0) AS draft_projects,
                COALESCE(SUM(CASE WHEN p.status IN ('Submitted', 'Submitted for Document Verification', 'Submitted for Verification', 'Resubmitted for Verification', 'Under Review', 'Approved by Project Management', 'Document Verification Completed', 'Forwarded to Admin Finance', 'Under Admin Finance Review', 'JOM Issued', 'JOM Number Issued', 'Forwarded to Operations') THEN 1 ELSE 0 END), 0) AS submitted_projects
             FROM projects p
             INNER JOIN users u ON u.id = p.created_by" . $whereSql
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'total_projects' => (int) ($row['total_projects'] ?? 0),
            'draft_projects' => (int) ($row['draft_projects'] ?? 0),
            'submitted_projects' => (int) ($row['submitted_projects'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param list<string> $conditions
     * @param array<string, mixed> $params
     * @param array<string, mixed> $filters
     */
    private static function applyUserAccessFilter(array $user, array &$conditions, array &$params, array $filters): void
    {
        if (AuthorizationService::hasPermission($user, 'projects.view_all')) {
            if (!empty($filters['department_id']) && ctype_digit((string) $filters['department_id'])) {
                $conditions[] = 'p.current_department_id = :filter_dept_id';
                $params['filter_dept_id'] = (int) $filters['department_id'];
            }
            return;
        }

        $userId = (int) ($user['id'] ?? 0);
        $deptId = $user['department_id'] !== null ? (int) $user['department_id'] : null;

        if (AuthorizationService::isMarketingUser($user) && $deptId !== null) {
            // Shared Marketing team access across all Marketing-originated or currently-assigned projects
            $conditions[] = '(p.origin_department_id = :auth_mkt_origin OR p.current_department_id = :auth_mkt_curr OR u.department_id = :auth_mkt_creator OR p.created_by = :auth_user_id)';
            $params['auth_mkt_origin'] = $deptId;
            $params['auth_mkt_curr'] = $deptId;
            $params['auth_mkt_creator'] = $deptId;
            $params['auth_user_id'] = $userId;
            return;
        }

        if (AuthorizationService::isProjectManagementUser($user) && $deptId !== null) {
            // Project Management retains visibility into projects submitted for verification and forwarded to Admin Finance
            $conditions[] = "(p.current_department_id = :auth_pm_dept OR p.verification_status != 'not_submitted' OR p.submitted_at IS NOT NULL OR p.created_by = :auth_user_id)";
            $params['auth_pm_dept'] = $deptId;
            $params['auth_user_id'] = $userId;
            return;
        }

        if (AuthorizationService::isAdminFinanceUser($user) && $deptId !== null) {
            // Admin Finance sees all projects forwarded to Admin Finance, under AFIN review, JOM issued, or forwarded to Operations
            $conditions[] = "(p.current_department_id = :auth_afin_dept OR p.status IN ('Forwarded to Admin Finance', 'Under Admin Finance Review', 'JOM Issued', 'JOM Number Issued', 'Forwarded to Operations') OR p.verification_status IN ('forwarded_to_admin_finance', 'under_admin_finance_review', 'jom_issued', 'forwarded_to_operations') OR p.forwarded_to_afin_at IS NOT NULL OR p.created_by = :auth_user_id)";
            $params['auth_afin_dept'] = $deptId;
            $params['auth_user_id'] = $userId;
            return;
        }

        if (AuthorizationService::isOperationsUser($user) && $deptId !== null) {
            // Operations sees projects forwarded to Operations by Admin Finance
            $conditions[] = "((p.current_department_id = :auth_ops_dept AND p.jom_number IS NOT NULL) OR p.status = 'Forwarded to Operations' OR p.verification_status = 'forwarded_to_operations' OR p.forwarded_to_ops_at IS NOT NULL OR p.created_by = :auth_user_id)";
            $params['auth_ops_dept'] = $deptId;
            $params['auth_user_id'] = $userId;
            return;
        }

        if (AuthorizationService::isOpsFinanceUser($user) && $deptId !== null) {
            // OPS Finance sees projects with PRs submitted by Operations or currently assigned to OPFIN
            $conditions[] = "(p.current_department_id = :auth_opfin_dept OR EXISTS (SELECT 1 FROM purchase_requests pr WHERE pr.project_id = p.id AND pr.submitted_at IS NOT NULL) OR p.created_by = :auth_user_id)";
            $params['auth_opfin_dept'] = $deptId;
            $params['auth_user_id'] = $userId;
            return;
        }

        if ($deptId !== null) {
            $conditions[] = '(p.current_department_id = :auth_dept_id OR p.created_by = :auth_user_id)';
            $params['auth_dept_id'] = $deptId;
            $params['auth_user_id'] = $userId;
        } else {
            $conditions[] = 'p.created_by = :auth_user_id';
            $params['auth_user_id'] = $userId;
        }
    }
}
