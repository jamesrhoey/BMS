<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class ActualBom
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_SUBMITTED_TO_ADMIN_FINANCE = 'Submitted to Admin Finance';
    public const STATUS_RECEIVED = 'Received';
    public const STATUS_UNDER_REVIEW = 'Under Review';
    public const STATUS_RETURNED_FOR_CORRECTIONS = 'Returned for Corrections';
    public const STATUS_ACCEPTED = 'Accepted';

    public const DOC_TYPE_ACTUAL_BOM = 'Actual BOM';
    public const DOC_TYPE_SUPPORTING = 'Actual BOM Supporting Document';

    private const BASE_SELECT = 'SELECT
            ab.id,
            ab.actual_bom_number,
            ab.project_id,
            ab.jom_number,
            ab.purchase_request_id,
            pr.pr_number,
            pr.status AS pr_status,
            ab.version_number,
            ab.remarks,
            ab.document_id,
            doc.original_filename AS document_filename,
            doc.version_number AS document_version,
            doc.file_size AS document_file_size,
            doc.created_at AS document_uploaded_at,
            ab.supporting_document_id,
            sdoc.original_filename AS supporting_filename,
            sdoc.version_number AS supporting_version,
            sdoc.file_size AS supporting_file_size,
            ab.status,
            ab.uploaded_by,
            up_u.full_name AS uploader_name,
            up_u.username AS uploader_username,
            ab.uploaded_at,
            ab.submitted_by,
            sub_u.full_name AS submitter_name,
            ab.submitted_at,
            ab.received_by,
            rec_u.full_name AS receiver_name,
            ab.received_at,
            ab.reviewed_by,
            rev_u.full_name AS reviewer_name,
            ab.reviewed_at,
            ab.returned_by,
            ret_u.full_name AS returner_name,
            ab.returned_at,
            ab.return_reason,
            ab.accepted_by,
            acc_u.full_name AS accepter_name,
            ab.accepted_at,
            ab.acceptance_notes,
            ab.created_at,
            ab.updated_at,
            p.project_code,
            p.project_name,
            p.client_name,
            p.status AS project_status
        FROM actual_boms ab
        INNER JOIN projects p ON p.id = ab.project_id
        INNER JOIN purchase_requests pr ON pr.id = ab.purchase_request_id
        LEFT JOIN project_documents doc ON doc.id = ab.document_id
        LEFT JOIN project_documents sdoc ON sdoc.id = ab.supporting_document_id
        LEFT JOIN users up_u ON up_u.id = ab.uploaded_by
        LEFT JOIN users sub_u ON sub_u.id = ab.submitted_by
        LEFT JOIN users rec_u ON rec_u.id = ab.received_by
        LEFT JOIN users rev_u ON rev_u.id = ab.reviewed_by
        LEFT JOIN users ret_u ON ret_u.id = ab.returned_by
        LEFT JOIN users acc_u ON acc_u.id = ab.accepted_by';

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(self::BASE_SELECT . ' WHERE ab.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['items'] = self::itemsForActualBom((int) $row['id']);
        $row['document_history'] = self::documentHistoryForProject((int) $row['project_id']);
        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByProjectId(int $projectId): ?array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . ' WHERE ab.project_id = :project_id ORDER BY ab.id DESC LIMIT 1'
        );
        $stmt->execute(['project_id' => $projectId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['items'] = self::itemsForActualBom((int) $row['id']);
        $row['document_history'] = self::documentHistoryForProject((int) $row['project_id']);
        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByPrId(int $prId): ?array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . ' WHERE ab.purchase_request_id = :pr_id ORDER BY ab.id DESC LIMIT 1'
        );
        $stmt->execute(['pr_id' => $prId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['items'] = self::itemsForActualBom((int) $row['id']);
        $row['document_history'] = self::documentHistoryForProject((int) $row['project_id']);
        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function itemsForActualBom(int $actualBomId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                id,
                actual_bom_id,
                pr_item_id,
                line_no,
                item_description,
                actual_specification,
                quantity,
                unit_of_measure,
                remarks,
                created_at
             FROM actual_bom_items
             WHERE actual_bom_id = :ab_id
             ORDER BY line_no ASC, id ASC'
        );
        $stmt->execute(['ab_id' => $actualBomId]);
        return $stmt->fetchAll();
    }

    /**
     * All preserved versions of Actual BOM documents for this project (never touches Marketing BOM).
     *
     * @return list<array<string, mixed>>
     */
    public static function documentHistoryForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                pd.id,
                pd.project_id,
                pd.document_type,
                pd.version_number,
                pd.is_current,
                pd.original_filename,
                pd.file_size,
                pd.upload_notes,
                pd.uploaded_by,
                u.full_name AS uploader_name,
                pd.created_at
             FROM project_documents pd
             INNER JOIN users u ON u.id = pd.uploaded_by
             WHERE pd.project_id = :project_id
               AND pd.document_type = :doc_type
             ORDER BY pd.version_number DESC, pd.id DESC'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'doc_type' => self::DOC_TYPE_ACTUAL_BOM,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * @param list<array{pr_item_id?: ?int, item_description: string, actual_specification: string, quantity: float|string, unit_of_measure: string, remarks?: ?string}> $items
     */
    public static function replaceItems(int $actualBomId, array $items): void
    {
        $pdo = Database::connection();
        $del = $pdo->prepare('DELETE FROM actual_bom_items WHERE actual_bom_id = :ab_id');
        $del->execute(['ab_id' => $actualBomId]);

        $ins = $pdo->prepare(
            'INSERT INTO actual_bom_items (
                actual_bom_id,
                pr_item_id,
                line_no,
                item_description,
                actual_specification,
                quantity,
                unit_of_measure,
                remarks
            ) VALUES (
                :actual_bom_id,
                :pr_item_id,
                :line_no,
                :item_description,
                :actual_specification,
                :quantity,
                :unit_of_measure,
                :remarks
            )'
        );

        $lineNo = 1;
        foreach ($items as $item) {
            $ins->execute([
                'actual_bom_id' => $actualBomId,
                'pr_item_id' => !empty($item['pr_item_id']) ? (int) $item['pr_item_id'] : null,
                'line_no' => $lineNo++,
                'item_description' => $item['item_description'],
                'actual_specification' => $item['actual_specification'],
                'quantity' => number_format((float) $item['quantity'], 2, '.', ''),
                'unit_of_measure' => $item['unit_of_measure'] !== '' ? $item['unit_of_measure'] : 'pcs',
                'remarks' => ($item['remarks'] ?? '') !== '' ? $item['remarks'] : null,
            ]);
        }
    }

    public static function actualBomNumberExists(string $number): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM actual_boms WHERE actual_bom_number = :num');
        $stmt->execute(['num' => $number]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public static function findLatestNumberByPrefix(string $prefixPattern): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT actual_bom_number FROM actual_boms
             WHERE actual_bom_number LIKE :pattern
             ORDER BY actual_bom_number DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute(['pattern' => $prefixPattern]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (string) $val : null;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array{pr_item_id?: ?int, item_description: string, actual_specification: string, quantity: float|string, unit_of_measure: string, remarks?: ?string}> $items
     */
    public static function create(array $data, array $items): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO actual_boms (
                actual_bom_number,
                project_id,
                jom_number,
                purchase_request_id,
                version_number,
                remarks,
                document_id,
                supporting_document_id,
                status,
                uploaded_by,
                uploaded_at
            ) VALUES (
                :actual_bom_number,
                :project_id,
                :jom_number,
                :purchase_request_id,
                :version_number,
                :remarks,
                :document_id,
                :supporting_document_id,
                :status,
                :uploaded_by,
                NOW()
            )'
        );
        $stmt->execute([
            'actual_bom_number' => $data['actual_bom_number'],
            'project_id' => $data['project_id'],
            'jom_number' => $data['jom_number'],
            'purchase_request_id' => $data['purchase_request_id'],
            'version_number' => $data['version_number'] ?? 1,
            'remarks' => ($data['remarks'] ?? '') !== '' ? $data['remarks'] : null,
            'document_id' => $data['document_id'] ?? null,
            'supporting_document_id' => $data['supporting_document_id'] ?? null,
            'status' => $data['status'] ?? self::STATUS_DRAFT,
            'uploaded_by' => $data['uploaded_by'],
        ]);

        $abId = (int) $pdo->lastInsertId();
        self::replaceItems($abId, $items);

        return $abId;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array{pr_item_id?: ?int, item_description: string, actual_specification: string, quantity: float|string, unit_of_measure: string, remarks?: ?string}> $items
     */
    public static function updateRecord(int $actualBomId, array $data, array $items): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE actual_boms
             SET version_number = :version_number,
                 remarks = :remarks,
                 document_id = COALESCE(:document_id, document_id),
                 supporting_document_id = COALESCE(:supporting_document_id, supporting_document_id),
                 uploaded_by = :uploaded_by,
                 uploaded_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND status IN (:s_draft, :s_returned)'
        );
        $stmt->execute([
            'id' => $actualBomId,
            'version_number' => $data['version_number'] ?? 1,
            'remarks' => ($data['remarks'] ?? '') !== '' ? $data['remarks'] : null,
            'document_id' => $data['document_id'] ?? null,
            'supporting_document_id' => $data['supporting_document_id'] ?? null,
            'uploaded_by' => $data['uploaded_by'],
            's_draft' => self::STATUS_DRAFT,
            's_returned' => self::STATUS_RETURNED_FOR_CORRECTIONS,
        ]);

        self::replaceItems($actualBomId, $items);

        return true;
    }

    public static function markSubmittedToAdminFinance(int $actualBomId, int $submittedBy): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE actual_boms
             SET status = :status,
                 submitted_by = :submitted_by,
                 submitted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND document_id IS NOT NULL
               AND status IN (:s_draft, :s_returned)'
        );
        $stmt->execute([
            'id' => $actualBomId,
            'status' => self::STATUS_SUBMITTED_TO_ADMIN_FINANCE,
            'submitted_by' => $submittedBy,
            's_draft' => self::STATUS_DRAFT,
            's_returned' => self::STATUS_RETURNED_FOR_CORRECTIONS,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function markReceivedByAdminFinance(int $actualBomId, int $receivedBy): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE actual_boms
             SET status = :status,
                 received_by = :received_by,
                 received_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND status = :from_status'
        );
        $stmt->execute([
            'id' => $actualBomId,
            'status' => self::STATUS_RECEIVED,
            'received_by' => $receivedBy,
            'from_status' => self::STATUS_SUBMITTED_TO_ADMIN_FINANCE,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function markUnderReviewByAdminFinance(int $actualBomId, int $reviewedBy): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE actual_boms
             SET status = :status,
                 received_by = COALESCE(received_by, :rec_by),
                 received_at = COALESCE(received_at, NOW()),
                 reviewed_by = :reviewed_by,
                 reviewed_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND status IN (:s_sub, :s_rec)'
        );
        $stmt->execute([
            'id' => $actualBomId,
            'status' => self::STATUS_UNDER_REVIEW,
            'rec_by' => $reviewedBy,
            'reviewed_by' => $reviewedBy,
            's_sub' => self::STATUS_SUBMITTED_TO_ADMIN_FINANCE,
            's_rec' => self::STATUS_RECEIVED,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function markReturnedForCorrections(int $actualBomId, int $returnedBy, string $reason): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE actual_boms
             SET status = :status,
                 returned_by = :returned_by,
                 returned_at = NOW(),
                 return_reason = :reason,
                 updated_at = NOW()
             WHERE id = :id
               AND status IN (:s_sub, :s_rec, :s_rev)'
        );
        $stmt->execute([
            'id' => $actualBomId,
            'status' => self::STATUS_RETURNED_FOR_CORRECTIONS,
            'returned_by' => $returnedBy,
            'reason' => $reason,
            's_sub' => self::STATUS_SUBMITTED_TO_ADMIN_FINANCE,
            's_rec' => self::STATUS_RECEIVED,
            's_rev' => self::STATUS_UNDER_REVIEW,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function markAcceptedByAdminFinance(int $actualBomId, int $acceptedBy, ?string $notes = null): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE actual_boms
             SET status = :status,
                 received_by = COALESCE(received_by, :rec_by),
                 received_at = COALESCE(received_at, NOW()),
                 reviewed_by = COALESCE(reviewed_by, :rev_by),
                 reviewed_at = COALESCE(reviewed_at, NOW()),
                 accepted_by = :accepted_by,
                 accepted_at = NOW(),
                 acceptance_notes = :notes,
                 updated_at = NOW()
             WHERE id = :id
               AND status IN (:s_sub, :s_rec, :s_rev)'
        );
        $stmt->execute([
            'id' => $actualBomId,
            'status' => self::STATUS_ACCEPTED,
            'rec_by' => $acceptedBy,
            'rev_by' => $acceptedBy,
            'accepted_by' => $acceptedBy,
            'notes' => ($notes ?? '') !== '' ? $notes : null,
            's_sub' => self::STATUS_SUBMITTED_TO_ADMIN_FINANCE,
            's_rec' => self::STATUS_RECEIVED,
            's_rev' => self::STATUS_UNDER_REVIEW,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function isEditableByOpsFinance(array $actualBom): bool
    {
        return in_array((string) ($actualBom['status'] ?? ''), [
            self::STATUS_DRAFT,
            self::STATUS_RETURNED_FOR_CORRECTIONS,
        ], true);
    }

    /**
     * Live database counts for the OPS Finance Dashboard:
     * - PRs received from Operations
     * - PRs awaiting review
     * - Actual BOM pending upload
     * - Actual BOM submitted to Admin Finance
     *
     * @return array{
     *   prs_received_from_operations: int,
     *   prs_awaiting_review: int,
     *   actual_bom_pending_upload: int,
     *   actual_bom_submitted_to_afin: int
     * }
     */
    public static function opsFinanceDashboardStats(): array
    {
        $sql = 'SELECT
                    COUNT(DISTINCT pr.id) AS prs_received_from_operations,
                    COUNT(DISTINCT CASE
                        WHEN pr.status = \'Submitted to OPS Finance\' THEN pr.id
                    END) AS prs_awaiting_review,
                    COUNT(DISTINCT CASE
                        WHEN ab.id IS NULL OR ab.status IN (\'Draft\', \'Returned for Corrections\') THEN pr.id
                    END) AS actual_bom_pending_upload,
                    COUNT(DISTINCT CASE
                        WHEN ab.id IS NOT NULL AND ab.status IN (\'Submitted to Admin Finance\', \'Received\', \'Under Review\', \'Accepted\') THEN ab.id
                    END) AS actual_bom_submitted_to_afin
                FROM purchase_requests pr
                LEFT JOIN actual_boms ab ON ab.purchase_request_id = pr.id
                WHERE pr.submitted_at IS NOT NULL
                  AND pr.status IN (\'Submitted to OPS Finance\', \'Under OPS Finance Review\', \'Actual BOM Submitted\')';

        $row = Database::connection()->query($sql)->fetch() ?: [];

        return [
            'prs_received_from_operations' => (int) ($row['prs_received_from_operations'] ?? 0),
            'prs_awaiting_review' => (int) ($row['prs_awaiting_review'] ?? 0),
            'actual_bom_pending_upload' => (int) ($row['actual_bom_pending_upload'] ?? 0),
            'actual_bom_submitted_to_afin' => (int) ($row['actual_bom_submitted_to_afin'] ?? 0),
        ];
    }

    /**
     * Paginated OPS Finance Incoming Purchase Requests & Actual BOM queue.
     *
     * @param array{q?: string, status?: string, sort?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function paginateOpsFinanceQueue(
        array $filters = [],
        int $page = 1,
        int $perPage = 10
    ): array {
        $where = ['pr.submitted_at IS NOT NULL'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.project_code LIKE :q1 OR p.project_name LIKE :q2 OR p.client_name LIKE :q3 OR p.jom_number LIKE :q4 OR pr.pr_number LIKE :q5 OR ab.actual_bom_number LIKE :q6)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
            $params['q5'] = $like;
            $params['q6'] = $like;
        }

        $statusFilter = trim((string) ($filters['status'] ?? ''));
        if ($statusFilter === 'awaiting_review') {
            $where[] = 'pr.status = \'Submitted to OPS Finance\'';
        } elseif ($statusFilter === 'pending_actual_bom') {
            $where[] = '(ab.id IS NULL OR ab.status IN (\'Draft\', \'Returned for Corrections\'))';
        } elseif ($statusFilter === 'submitted_to_afin') {
            $where[] = 'ab.status IN (\'Submitted to Admin Finance\', \'Received\', \'Under Review\', \'Accepted\')';
        }

        $whereClause = ' WHERE ' . implode(' AND ', $where);

        $countSql = 'SELECT COUNT(DISTINCT pr.id)
                     FROM purchase_requests pr
                     INNER JOIN projects p ON p.id = pr.project_id
                     LEFT JOIN actual_boms ab ON ab.purchase_request_id = pr.id' . $whereClause;
        $countStmt = Database::connection()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sort = (string) ($filters['sort'] ?? 'submitted');
        $orderBy = match ($sort) {
            'oldest' => ' ORDER BY COALESCE(pr.submitted_at, pr.created_at) ASC, pr.id ASC',
            'client' => ' ORDER BY p.client_name ASC, pr.id DESC',
            default => ' ORDER BY COALESCE(pr.submitted_at, pr.created_at) DESC, pr.id DESC',
        };

        $sql = 'SELECT
                    pr.id AS pr_id,
                    pr.pr_number,
                    pr.project_id,
                    pr.jom_number,
                    pr.pr_date,
                    pr.status AS pr_status,
                    pr.submitted_at AS pr_submitted_at,
                    req_u.full_name AS pr_requester_name,
                    p.project_code,
                    p.project_name,
                    p.client_name,
                    p.status AS project_status,
                    (SELECT COUNT(*) FROM purchase_request_items pri WHERE pri.purchase_request_id = pr.id) AS pr_items_count,
                    ab.id AS actual_bom_id,
                    ab.actual_bom_number,
                    ab.status AS actual_bom_status,
                    ab.version_number AS actual_bom_version,
                    ab.document_id AS actual_bom_document_id,
                    doc.original_filename AS actual_bom_filename,
                    ab.submitted_at AS actual_bom_submitted_at
                FROM purchase_requests pr
                INNER JOIN projects p ON p.id = pr.project_id
                LEFT JOIN users req_u ON req_u.id = pr.requested_by
                LEFT JOIN actual_boms ab ON ab.purchase_request_id = pr.id
                LEFT JOIN project_documents doc ON doc.id = ab.document_id'
            . $whereClause
            . $orderBy
            . ' LIMIT :limit_rows OFFSET :offset_rows';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }
        $stmt->bindValue(':limit_rows', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset_rows', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Incoming Actual BOM queue for Admin Finance review.
     *
     * @return list<array<string, mixed>>
     */
    public static function adminFinanceIncomingActualBoms(int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . '
             WHERE ab.submitted_at IS NOT NULL
             ORDER BY COALESCE(ab.submitted_at, ab.updated_at) DESC, ab.id DESC
             LIMIT :limit_rows'
        );
        $stmt->bindValue(':limit_rows', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
