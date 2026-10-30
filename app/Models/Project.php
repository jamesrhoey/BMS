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
    public const STATUS_SUBMITTED_FOR_VERIFICATION = 'Submitted for Document Verification';
    public const STATUS_UNDER_REVIEW = 'Under Review';
    public const STATUS_RETURNED_FOR_CORRECTIONS = 'Returned for Corrections';
    public const STATUS_RESUBMITTED_FOR_VERIFICATION = 'Resubmitted for Verification';
    public const STATUS_VERIFICATION_COMPLETED = 'Document Verification Completed';
    public const STATUS_JOM_ISSUED = 'JOM Number Issued';
    // Retained for backward compatibility with initial foundation records
    public const STATUS_SUBMITTED = 'Submitted';

    public const VERIFICATION_NOT_SUBMITTED = 'not_submitted';
    public const VERIFICATION_PENDING_PM = 'pending_pm_review';
    public const VERIFICATION_UNDER_REVIEW = 'under_review';
    public const VERIFICATION_RETURNED = 'returned_for_corrections';
    public const VERIFICATION_VERIFIED = 'verified_complete';
    public const VERIFICATION_JOM_ISSUED = 'jom_issued';

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
            p.origin_department_id,
            p.current_department_id,
            p.created_by,
            p.status,
            p.submitted_by,
            p.submitted_at,
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
            ru.full_name AS returner_name,
            revu.full_name AS reviewer_name,
            vu.full_name AS verifier_name,
            jomu.full_name AS jom_issuer_name,
            (SELECT COUNT(*) FROM project_documents pd WHERE pd.project_id = p.id AND pd.is_current = 1) AS document_count
        FROM projects p
        LEFT JOIN departments d ON d.id = p.current_department_id
        LEFT JOIN departments od ON od.id = p.origin_department_id
        INNER JOIN users u ON u.id = p.created_by
        LEFT JOIN users su ON su.id = p.submitted_by
        LEFT JOIN users ru ON ru.id = p.returned_by
        LEFT JOIN users revu ON revu.id = p.reviewed_by
        LEFT JOIN users vu ON vu.id = p.verified_by
        LEFT JOIN users jomu ON jomu.id = p.jom_issued_by
    ';

    /**
     * @return list<string>
     */
    public static function availableStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PREPARING_DOCUMENTS,
            self::STATUS_SUBMITTED_FOR_VERIFICATION,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_RETURNED_FOR_CORRECTIONS,
            self::STATUS_RESUBMITTED_FOR_VERIFICATION,
            self::STATUS_VERIFICATION_COMPLETED,
            self::STATUS_JOM_ISSUED,
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
            self::STATUS_SUBMITTED_FOR_VERIFICATION,
            self::STATUS_RETURNED_FOR_CORRECTIONS,
            self::STATUS_RESUBMITTED_FOR_VERIFICATION,
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
     * Statuses representing projects queued with Project Management for document verification.
     *
     * @return list<string>
     */
    public static function pmVerificationQueueStatuses(): array
    {
        return [
            self::STATUS_SUBMITTED_FOR_VERIFICATION,
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
            self::STATUS_SUBMITTED_FOR_VERIFICATION,
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
            $conditions[] = "p.status IN ('Submitted for Document Verification', 'Resubmitted for Verification', 'Submitted')";
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
     * @return array{
     *   total_marketing_projects: int,
     *   draft_projects: int,
     *   awaiting_doc_prep: int,
     *   submitted_to_pm: int,
     *   returned_for_corrections: int,
     *   missing_documents: int
     * }
     */
    public static function marketingDashboardStats(int $marketingDeptId, array $requiredDocTypes): array
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

        $sql = "
            SELECT
                COUNT(*) AS total_marketing_projects,
                COALESCE(SUM(CASE WHEN sub.status = 'Draft' THEN 1 ELSE 0 END), 0) AS draft_projects,
                COALESCE(SUM(CASE WHEN sub.status = 'Preparing Documents' THEN 1 ELSE 0 END), 0) AS awaiting_doc_prep,
                COALESCE(SUM(CASE WHEN sub.status IN ('Submitted for Document Verification', 'Resubmitted for Verification', 'Submitted') THEN 1 ELSE 0 END), 0) AS submitted_to_pm,
                COALESCE(SUM(CASE WHEN sub.status = 'Returned for Corrections' THEN 1 ELSE 0 END), 0) AS returned_for_corrections,
                COALESCE(SUM(CASE WHEN sub.uploaded_req_docs < :req_count THEN 1 ELSE 0 END), 0) AS missing_documents
            FROM (
                SELECT
                    p.id,
                    p.status,
                    (
                        SELECT COUNT(DISTINCT pd.document_type)
                        FROM project_documents pd
                        WHERE pd.project_id = p.id
                          AND pd.is_current = 1
                          AND pd.document_type IN ({$inListSql})
                    ) AS uploaded_req_docs
                FROM projects p
                INNER JOIN users u ON u.id = p.created_by
                WHERE p.origin_department_id = :mkt_origin
                   OR p.current_department_id = :mkt_curr
                   OR u.department_id = :mkt_creator
            ) AS sub
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'total_marketing_projects' => (int) ($row['total_marketing_projects'] ?? 0),
            'draft_projects' => (int) ($row['draft_projects'] ?? 0),
            'awaiting_doc_prep' => (int) ($row['awaiting_doc_prep'] ?? 0),
            'submitted_to_pm' => (int) ($row['submitted_to_pm'] ?? 0),
            'returned_for_corrections' => (int) ($row['returned_for_corrections'] ?? 0),
            'missing_documents' => (int) ($row['missing_documents'] ?? 0),
        ];
    }

    /**
     * Paginated Marketing project list with search, workflow status filter,
     * document completeness filter, and creation date sorting.
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

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'pm_queue') {
            $conditions[] = "p.status IN ('Submitted for Document Verification', 'Resubmitted for Verification', 'Submitted')";
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
                p.origin_department_id,
                p.current_department_id,
                p.created_by,
                p.status,
                p.submitted_by,
                p.submitted_at,
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
            $item['uploaded_required_count'] = $uploaded;
            $item['required_total_count'] = $reqCount;
            $item['is_documents_complete'] = $reqCount > 0 ? ($uploaded >= $reqCount) : true;
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
        $stmt = $pdo->prepare(
            'INSERT INTO projects (
                project_code,
                project_name,
                client_name,
                client_contact,
                project_location,
                project_description,
                workflow_type,
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
            'workflow_type' => $data['workflow_type'] ?? 'standard',
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
            'status' => self::STATUS_VERIFICATION_COMPLETED,
            'verification_status' => self::VERIFICATION_VERIFIED,
            'verified_by' => $verifiedByUserId,
            'verification_notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'id' => $id,
        ]);
    }

    /**
     * Database-driven metrics for the Project Management dashboard summary cards.
     *
     * @return array{
     *   total_pm_projects: int,
     *   awaiting_verification: int,
     *   under_review: int,
     *   returned_to_marketing: int,
     *   resubmitted_for_verification: int,
     *   verification_completed: int,
     *   jom_issued: int
     * }
     */
    public static function pmDashboardStats(int $pmDeptId): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total_pm_projects,
                COALESCE(SUM(CASE WHEN p.status IN ('Submitted for Document Verification', 'Submitted') THEN 1 ELSE 0 END), 0) AS awaiting_verification,
                COALESCE(SUM(CASE WHEN p.status = 'Under Review' THEN 1 ELSE 0 END), 0) AS under_review,
                COALESCE(SUM(CASE WHEN p.status = 'Returned for Corrections' THEN 1 ELSE 0 END), 0) AS returned_to_marketing,
                COALESCE(SUM(CASE WHEN p.status = 'Resubmitted for Verification' THEN 1 ELSE 0 END), 0) AS resubmitted_for_verification,
                COALESCE(SUM(CASE WHEN p.status = 'Document Verification Completed' OR p.verification_status = 'verified_complete' THEN 1 ELSE 0 END), 0) AS verification_completed,
                COALESCE(SUM(CASE WHEN p.jom_number IS NOT NULL OR p.status = 'JOM Number Issued' THEN 1 ELSE 0 END), 0) AS jom_issued
            FROM projects p
            WHERE p.current_department_id = :pm_dept_id
               OR p.verification_status != 'not_submitted'
               OR p.submitted_at IS NOT NULL
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['pm_dept_id' => $pmDeptId]);
        $row = $stmt->fetch() ?: [];

        return [
            'total_pm_projects' => (int) ($row['total_pm_projects'] ?? 0),
            'awaiting_verification' => (int) ($row['awaiting_verification'] ?? 0),
            'under_review' => (int) ($row['under_review'] ?? 0),
            'returned_to_marketing' => (int) ($row['returned_to_marketing'] ?? 0),
            'resubmitted_for_verification' => (int) ($row['resubmitted_for_verification'] ?? 0),
            'verification_completed' => (int) ($row['verification_completed'] ?? 0),
            'jom_issued' => (int) ($row['jom_issued'] ?? 0),
        ];
    }

    /**
     * Paginated Project Management project list with search, status filtering, and sorting.
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
        if ($status === 'awaiting') {
            $conditions[] = "p.status IN ('Submitted for Document Verification', 'Submitted')";
        } elseif ($status === 'under_review') {
            $conditions[] = "p.status = 'Under Review'";
        } elseif ($status === 'returned') {
            $conditions[] = "p.status = 'Returned for Corrections'";
        } elseif ($status === 'resubmitted') {
            $conditions[] = "p.status = 'Resubmitted for Verification'";
        } elseif ($status === 'completed' || $status === 'verified') {
            $conditions[] = "(p.status = 'Document Verification Completed' OR p.verification_status = 'verified_complete')";
        } elseif ($status === 'jom_issued') {
            $conditions[] = "(p.jom_number IS NOT NULL OR p.status = 'JOM Number Issued')";
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
            'submitted' => 'ORDER BY p.submitted_at DESC, p.id DESC',
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

        return [
            'items' => $dataStmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * @return array{total_projects: int, draft_projects: int, submitted_projects: int}
     */
    public static function dashboardCounts(): array
    {
        $row = Database::connection()->query(
            "SELECT
                COUNT(*) AS total_projects,
                COALESCE(SUM(CASE WHEN status IN ('Draft', 'Preparing Documents', 'Returned for Corrections') THEN 1 ELSE 0 END), 0) AS draft_projects,
                COALESCE(SUM(CASE WHEN status IN ('Submitted', 'Submitted for Document Verification', 'Resubmitted for Verification') THEN 1 ELSE 0 END), 0) AS submitted_projects
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
                COALESCE(SUM(CASE WHEN p.status IN ('Draft', 'Preparing Documents', 'Returned for Corrections') THEN 1 ELSE 0 END), 0) AS draft_projects,
                COALESCE(SUM(CASE WHEN p.status IN ('Submitted', 'Submitted for Document Verification', 'Resubmitted for Verification') THEN 1 ELSE 0 END), 0) AS submitted_projects
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
