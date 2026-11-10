<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class PurchaseRequest
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_SUBMITTED = 'Submitted';
    public const STATUS_SUBMITTED_TO_OPS_FINANCE = 'Submitted to OPS Finance';
    public const STATUS_UNDER_REVIEW = 'Under Review';
    public const STATUS_UNDER_OPS_FINANCE_REVIEW = 'Under OPS Finance Review';
    public const STATUS_RETURNED_FOR_REVISION = 'Returned for Revision';
    public const STATUS_RETURNED_FOR_CORRECTIONS = 'Returned for Corrections';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_REJECTED = 'Rejected';
    public const STATUS_CONVERTED_TO_PO = 'Converted to PO';
    public const STATUS_PARTIALLY_ORDERED = 'Partially Ordered';
    public const STATUS_FULLY_ORDERED = 'Fully Ordered';
    public const STATUS_CLOSED = 'Closed';
    public const STATUS_ACTUAL_BOM_SUBMITTED = 'Actual BOM Submitted';

    public const DOC_TYPE_ATTACHMENT = 'PR Attachment';

    private const BASE_SELECT = 'SELECT
            pr.id,
            pr.pr_number,
            pr.project_id,
            pr.jom_number,
            pr.requesting_department_id,
            req_d.department_name AS requesting_department_name,
            req_d.department_code AS requesting_department_code,
            pr.receiving_department_id,
            rec_d.department_name AS receiving_department_name,
            rec_d.department_code AS receiving_department_code,
            pr.requested_by,
            req_u.full_name AS requester_name,
            req_u.username AS requester_username,
            pr.pr_date,
            pr.purpose,
            pr.required_delivery_date,
            pr.remarks,
            pr.attachment_document_id,
            att.original_filename AS attachment_filename,
            att.version_number AS attachment_version,
            att.file_size AS attachment_file_size,
            pr.sld_document_id,
            sld_doc.original_filename AS sld_document_filename,
            pr.marketing_bom_document_id,
            bom_doc.original_filename AS marketing_bom_filename,
            COALESCE(pr.actual_bom_id, ab_proj.id) AS actual_bom_id,
            ab_rec.actual_bom_number,
            ab_rec.status AS actual_bom_status,
            pr.status,
            pr.submitted_by,
            sub_u.full_name AS submitter_name,
            pr.submitted_at,
            pr.reviewed_by,
            rev_u.full_name AS reviewer_name,
            pr.reviewed_at,
            pr.returned_by,
            ret_u.full_name AS returner_name,
            pr.returned_at,
            pr.return_reason,
            pr.approved_by,
            appr_u.full_name AS approver_name,
            pr.approved_at,
            pr.approval_notes,
            pr.rejected_by,
            rej_u.full_name AS rejecter_name,
            pr.rejected_at,
            pr.rejection_reason,
            pr.ordered_quantity_total,
            pr.ordered_status,
            pr.created_at,
            pr.updated_at,
            p.project_code,
            p.project_name,
            p.client_name,
            p.status AS project_status,
            p.ops_sld_document_id,
            p.ops_bom_document_id
        FROM purchase_requests pr
        INNER JOIN projects p ON p.id = pr.project_id
        LEFT JOIN departments req_d ON req_d.id = pr.requesting_department_id
        LEFT JOIN departments rec_d ON rec_d.id = pr.receiving_department_id
        LEFT JOIN users req_u ON req_u.id = pr.requested_by
        LEFT JOIN users sub_u ON sub_u.id = pr.submitted_by
        LEFT JOIN users rev_u ON rev_u.id = pr.reviewed_by
        LEFT JOIN users ret_u ON ret_u.id = pr.returned_by
        LEFT JOIN users appr_u ON appr_u.id = pr.approved_by
        LEFT JOIN users rej_u ON rej_u.id = pr.rejected_by
        LEFT JOIN project_documents att ON att.id = pr.attachment_document_id
        LEFT JOIN project_documents sld_doc ON sld_doc.id = COALESCE(pr.sld_document_id, p.ops_sld_document_id)
        LEFT JOIN project_documents bom_doc ON bom_doc.id = COALESCE(pr.marketing_bom_document_id, p.ops_bom_document_id)
        LEFT JOIN actual_boms ab_proj ON ab_proj.id = (SELECT ab_sub.id FROM actual_boms ab_sub WHERE ab_sub.project_id = pr.project_id ORDER BY ab_sub.id DESC LIMIT 1)
        LEFT JOIN actual_boms ab_rec ON ab_rec.id = COALESCE(pr.actual_bom_id, ab_proj.id)';

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(self::BASE_SELECT . ' WHERE pr.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['items'] = self::itemsForPr((int) $row['id']);
        $row['histories'] = self::historiesForPr((int) $row['id']);
        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByProjectId(int $projectId): ?array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . ' WHERE pr.project_id = :project_id ORDER BY pr.id DESC LIMIT 1'
        );
        $stmt->execute(['project_id' => $projectId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['items'] = self::itemsForPr((int) $row['id']);
        $row['histories'] = self::historiesForPr((int) $row['id']);
        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function itemsForPr(int $prId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                id,
                purchase_request_id,
                line_no,
                item_description,
                model_part_number,
                item_specification,
                quantity,
                unit_of_measure,
                purpose,
                approved_quantity,
                ordered_quantity,
                remarks,
                created_at
             FROM purchase_request_items
             WHERE purchase_request_id = :pr_id
             ORDER BY line_no ASC, id ASC'
        );
        $stmt->execute(['pr_id' => $prId]);
        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function historiesForPr(int $prId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                h.id,
                h.purchase_request_id,
                h.from_status,
                h.to_status,
                h.action,
                h.user_id,
                u.full_name AS user_name,
                u.username,
                d.department_code,
                h.remarks,
                h.created_at
             FROM purchase_request_histories h
             LEFT JOIN users u ON u.id = h.user_id
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE h.purchase_request_id = :pr_id
             ORDER BY h.id DESC, h.created_at DESC'
        );
        $stmt->execute(['pr_id' => $prId]);
        return $stmt->fetchAll();
    }

    public static function recordHistory(
        int $prId,
        ?string $fromStatus,
        string $toStatus,
        string $action,
        int $userId,
        ?string $remarks = null
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO purchase_request_histories (
                purchase_request_id,
                from_status,
                to_status,
                action,
                user_id,
                remarks
            ) VALUES (
                :purchase_request_id,
                :from_status,
                :to_status,
                :action,
                :user_id,
                :remarks
            )'
        );
        $stmt->execute([
            'purchase_request_id' => $prId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action' => $action,
            'user_id' => $userId,
            'remarks' => $remarks !== null && trim($remarks) !== '' ? trim($remarks) : null,
        ]);
    }

    /**
     * @param list<array{item_description: string, model_part_number?: ?string, item_specification: string, quantity: float|string, unit_of_measure: string, purpose?: ?string, approved_quantity?: float|string|null, ordered_quantity?: float|string|null, remarks?: ?string}> $items
     */
    public static function replaceItems(int $prId, array $items): void
    {
        $pdo = Database::connection();
        $del = $pdo->prepare('DELETE FROM purchase_request_items WHERE purchase_request_id = :pr_id');
        $del->execute(['pr_id' => $prId]);

        $ins = $pdo->prepare(
            'INSERT INTO purchase_request_items (
                purchase_request_id,
                line_no,
                item_description,
                model_part_number,
                item_specification,
                quantity,
                unit_of_measure,
                purpose,
                approved_quantity,
                ordered_quantity,
                remarks
            ) VALUES (
                :purchase_request_id,
                :line_no,
                :item_description,
                :model_part_number,
                :item_specification,
                :quantity,
                :unit_of_measure,
                :purpose,
                :approved_quantity,
                :ordered_quantity,
                :remarks
            )'
        );

        $lineNo = 1;
        foreach ($items as $item) {
            $qty = number_format((float) $item['quantity'], 2, '.', '');
            $apprQty = isset($item['approved_quantity']) && $item['approved_quantity'] !== null && $item['approved_quantity'] !== ''
                ? number_format((float) $item['approved_quantity'], 2, '.', '')
                : null;
            $ordQty = isset($item['ordered_quantity']) && $item['ordered_quantity'] !== null
                ? number_format((float) $item['ordered_quantity'], 2, '.', '')
                : '0.00';

            $ins->execute([
                'purchase_request_id' => $prId,
                'line_no' => $lineNo++,
                'item_description' => $item['item_description'],
                'model_part_number' => ($item['model_part_number'] ?? null) !== '' ? ($item['model_part_number'] ?? null) : null,
                'item_specification' => $item['item_specification'],
                'quantity' => $qty,
                'unit_of_measure' => ($item['unit_of_measure'] ?? '') !== '' ? $item['unit_of_measure'] : 'pcs',
                'purpose' => ($item['purpose'] ?? null) !== '' ? ($item['purpose'] ?? null) : null,
                'approved_quantity' => $apprQty,
                'ordered_quantity' => $ordQty,
                'remarks' => ($item['remarks'] ?? '') !== '' ? $item['remarks'] : null,
            ]);
        }
    }

    public static function prNumberExists(string $prNumber): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM purchase_requests WHERE pr_number = :num');
        $stmt->execute(['num' => $prNumber]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public static function findLatestPrNumberByPrefix(string $prefixPattern): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT pr_number FROM purchase_requests
             WHERE pr_number LIKE :pattern
             ORDER BY pr_number DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute(['pattern' => $prefixPattern]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (string) $val : null;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array{item_description: string, model_part_number?: ?string, item_specification: string, quantity: float|string, unit_of_measure: string, purpose?: ?string, remarks?: ?string}> $items
     */
    public static function create(array $data, array $items): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO purchase_requests (
                pr_number,
                project_id,
                jom_number,
                requesting_department_id,
                receiving_department_id,
                requested_by,
                pr_date,
                purpose,
                required_delivery_date,
                remarks,
                attachment_document_id,
                sld_document_id,
                marketing_bom_document_id,
                actual_bom_id,
                status
            ) VALUES (
                :pr_number,
                :project_id,
                :jom_number,
                :requesting_department_id,
                :receiving_department_id,
                :requested_by,
                :pr_date,
                :purpose,
                :required_delivery_date,
                :remarks,
                :attachment_document_id,
                :sld_document_id,
                :marketing_bom_document_id,
                :actual_bom_id,
                :status
            )'
        );
        $stmt->execute([
            'pr_number' => $data['pr_number'],
            'project_id' => $data['project_id'],
            'jom_number' => $data['jom_number'],
            'requesting_department_id' => $data['requesting_department_id'] ?? null,
            'receiving_department_id' => $data['receiving_department_id'] ?? null,
            'requested_by' => $data['requested_by'],
            'pr_date' => $data['pr_date'],
            'purpose' => ($data['purpose'] ?? '') !== '' ? $data['purpose'] : null,
            'required_delivery_date' => ($data['required_delivery_date'] ?? '') !== '' ? $data['required_delivery_date'] : null,
            'remarks' => ($data['remarks'] ?? '') !== '' ? $data['remarks'] : null,
            'attachment_document_id' => $data['attachment_document_id'] ?? null,
            'sld_document_id' => $data['sld_document_id'] ?? null,
            'marketing_bom_document_id' => $data['marketing_bom_document_id'] ?? null,
            'actual_bom_id' => $data['actual_bom_id'] ?? null,
            'status' => $data['status'] ?? self::STATUS_DRAFT,
        ]);

        $prId = (int) $pdo->lastInsertId();
        self::replaceItems($prId, $items);
        self::recordHistory($prId, null, $data['status'] ?? self::STATUS_DRAFT, 'created_draft', (int) $data['requested_by'], 'Purchase Request draft created.');

        return $prId;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array{item_description: string, model_part_number?: ?string, item_specification: string, quantity: float|string, unit_of_measure: string, purpose?: ?string, remarks?: ?string}> $items
     */
    public static function updateDraft(int $prId, array $data, array $items): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE purchase_requests
             SET pr_date = :pr_date,
                 purpose = :purpose,
                 required_delivery_date = :required_delivery_date,
                 remarks = :remarks,
                 attachment_document_id = COALESCE(:attachment_document_id, attachment_document_id),
                 sld_document_id = COALESCE(:sld_document_id, sld_document_id),
                 marketing_bom_document_id = COALESCE(:marketing_bom_document_id, marketing_bom_document_id),
                 actual_bom_id = COALESCE(:actual_bom_id, actual_bom_id),
                 updated_at = NOW()
             WHERE id = :id
               AND status IN (:s_draft, :s_returned, :s_rev)'
        );
        $stmt->execute([
            'id' => $prId,
            'pr_date' => $data['pr_date'],
            'purpose' => ($data['purpose'] ?? '') !== '' ? $data['purpose'] : null,
            'required_delivery_date' => ($data['required_delivery_date'] ?? '') !== '' ? $data['required_delivery_date'] : null,
            'remarks' => ($data['remarks'] ?? '') !== '' ? $data['remarks'] : null,
            'attachment_document_id' => $data['attachment_document_id'] ?? null,
            'sld_document_id' => $data['sld_document_id'] ?? null,
            'marketing_bom_document_id' => $data['marketing_bom_document_id'] ?? null,
            'actual_bom_id' => $data['actual_bom_id'] ?? null,
            's_draft' => self::STATUS_DRAFT,
            's_returned' => self::STATUS_RETURNED_FOR_CORRECTIONS,
            's_rev' => self::STATUS_RETURNED_FOR_REVISION,
        ]);

        self::replaceItems($prId, $items);

        return true;
    }

    public static function markSubmittedToOpsFinance(int $prId, int $receivingDeptId, int $submittedBy): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE purchase_requests
             SET status = :status,
                 receiving_department_id = :receiving_dept_id,
                 submitted_by = :submitted_by,
                 submitted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND status IN (:s_draft, :s_returned, :s_rev)'
        );
        $stmt->execute([
            'id' => $prId,
            'status' => self::STATUS_SUBMITTED_TO_OPS_FINANCE,
            'receiving_dept_id' => $receivingDeptId,
            'submitted_by' => $submittedBy,
            's_draft' => self::STATUS_DRAFT,
            's_returned' => self::STATUS_RETURNED_FOR_CORRECTIONS,
            's_rev' => self::STATUS_RETURNED_FOR_REVISION,
        ]);

        $affected = $stmt->rowCount() > 0;
        if ($affected) {
            self::recordHistory($prId, null, self::STATUS_SUBMITTED_TO_OPS_FINANCE, 'submitted', $submittedBy, 'Purchase Request submitted to OPS Finance.');
        }

        return $affected;
    }

    public static function markUnderOpsFinanceReview(int $prId, int $reviewedBy): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE purchase_requests
             SET status = :status,
                 reviewed_by = :reviewed_by,
                 reviewed_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND status IN (:s_sub1, :s_sub2)'
        );
        $stmt->execute([
            'id' => $prId,
            'status' => self::STATUS_UNDER_OPS_FINANCE_REVIEW,
            'reviewed_by' => $reviewedBy,
            's_sub1' => self::STATUS_SUBMITTED_TO_OPS_FINANCE,
            's_sub2' => self::STATUS_SUBMITTED,
        ]);

        $affected = $stmt->rowCount() > 0;
        if ($affected) {
            self::recordHistory($prId, self::STATUS_SUBMITTED_TO_OPS_FINANCE, self::STATUS_UNDER_OPS_FINANCE_REVIEW, 'under_review', $reviewedBy, 'Purchase Request placed under review.');
        }

        return $affected;
    }

    public static function markReturnedForCorrections(int $prId, int $returnedBy, string $reason): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE purchase_requests
             SET status = :status,
                 returned_by = :returned_by,
                 returned_at = NOW(),
                 return_reason = :reason,
                 updated_at = NOW()
             WHERE id = :id
               AND status IN (:s_sub1, :s_sub2, :s_rev1, :s_rev2)'
        );
        $stmt->execute([
            'id' => $prId,
            'status' => self::STATUS_RETURNED_FOR_CORRECTIONS,
            'returned_by' => $returnedBy,
            'reason' => $reason,
            's_sub1' => self::STATUS_SUBMITTED_TO_OPS_FINANCE,
            's_sub2' => self::STATUS_SUBMITTED,
            's_rev1' => self::STATUS_UNDER_OPS_FINANCE_REVIEW,
            's_rev2' => self::STATUS_UNDER_REVIEW,
        ]);

        $affected = $stmt->rowCount() > 0;
        if ($affected) {
            self::recordHistory($prId, null, self::STATUS_RETURNED_FOR_CORRECTIONS, 'returned_for_revision', $returnedBy, $reason);
        }

        return $affected;
    }

    public static function markApproved(int $prId, int $approvedBy, ?string $notes = null): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE purchase_requests
             SET status = :status,
                 approved_by = :approved_by,
                 approved_at = NOW(),
                 approval_notes = :notes,
                 updated_at = NOW()
             WHERE id = :id
               AND status IN (:s_sub1, :s_sub2, :s_rev1, :s_rev2, :s_abom)'
        );
        $stmt->execute([
            'id' => $prId,
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            's_sub1' => self::STATUS_SUBMITTED_TO_OPS_FINANCE,
            's_sub2' => self::STATUS_SUBMITTED,
            's_rev1' => self::STATUS_UNDER_OPS_FINANCE_REVIEW,
            's_rev2' => self::STATUS_UNDER_REVIEW,
            's_abom' => self::STATUS_ACTUAL_BOM_SUBMITTED,
        ]);

        $affected = $stmt->rowCount() > 0;
        if ($affected) {
            // Set approved_quantity = quantity for line items
            $updItems = $pdo->prepare(
                'UPDATE purchase_request_items
                 SET approved_quantity = quantity
                 WHERE purchase_request_id = :pr_id
                   AND approved_quantity IS NULL'
            );
            $updItems->execute(['pr_id' => $prId]);

            self::recordHistory($prId, null, self::STATUS_APPROVED, 'approved', $approvedBy, $notes ?: 'Purchase Request approved.');
        }

        return $affected;
    }

    public static function markRejected(int $prId, int $rejectedBy, string $reason): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE purchase_requests
             SET status = :status,
                 rejected_by = :rejected_by,
                 rejected_at = NOW(),
                 rejection_reason = :reason,
                 updated_at = NOW()
             WHERE id = :id
               AND status IN (:s_sub1, :s_sub2, :s_rev1, :s_rev2, :s_abom)'
        );
        $stmt->execute([
            'id' => $prId,
            'status' => self::STATUS_REJECTED,
            'rejected_by' => $rejectedBy,
            'reason' => $reason,
            's_sub1' => self::STATUS_SUBMITTED_TO_OPS_FINANCE,
            's_sub2' => self::STATUS_SUBMITTED,
            's_rev1' => self::STATUS_UNDER_OPS_FINANCE_REVIEW,
            's_rev2' => self::STATUS_UNDER_REVIEW,
            's_abom' => self::STATUS_ACTUAL_BOM_SUBMITTED,
        ]);

        $affected = $stmt->rowCount() > 0;
        if ($affected) {
            self::recordHistory($prId, null, self::STATUS_REJECTED, 'rejected', $rejectedBy, $reason);
        }

        return $affected;
    }

    public static function markActualBomSubmitted(int $prId): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE purchase_requests
             SET status = :status,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $prId,
            'status' => self::STATUS_ACTUAL_BOM_SUBMITTED,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function isEditableByOperations(array $pr): bool
    {
        return in_array((string) ($pr['status'] ?? ''), [
            self::STATUS_DRAFT,
            self::STATUS_RETURNED_FOR_CORRECTIONS,
            self::STATUS_RETURNED_FOR_REVISION,
        ], true);
    }

    public static function isSubmittedToOpsFinance(array $pr): bool
    {
        return !empty($pr['submitted_at']) && in_array((string) ($pr['status'] ?? ''), [
            self::STATUS_SUBMITTED_TO_OPS_FINANCE,
            self::STATUS_SUBMITTED,
            self::STATUS_UNDER_OPS_FINANCE_REVIEW,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_CONVERTED_TO_PO,
            self::STATUS_PARTIALLY_ORDERED,
            self::STATUS_FULLY_ORDERED,
            self::STATUS_ACTUAL_BOM_SUBMITTED,
        ], true);
    }

    public static function isEligibleForPo(array $pr): bool
    {
        return in_array((string) ($pr['status'] ?? ''), [
            self::STATUS_APPROVED,
            self::STATUS_PARTIALLY_ORDERED,
        ], true);
    }

    /**
     * Record quantity ordered against an approved PR line item.
     * Prevents ordering quantities from exceeding approved quantities.
     *
     * @return array{success: bool, error?: string, remaining_quantity?: float}
     */
    public static function recordOrderQuantity(int $prId, int $prItemId, float $qtyToOrder, int $userId): array
    {
        if ($qtyToOrder <= 0) {
            return [
                'success' => false,
                'error' => 'Order quantity must be greater than zero.',
            ];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT pri.*, pr.status AS pr_status
             FROM purchase_request_items pri
             INNER JOIN purchase_requests pr ON pr.id = pri.purchase_request_id
             WHERE pri.id = :item_id AND pri.purchase_request_id = :pr_id
             FOR UPDATE'
        );
        $stmt->execute(['item_id' => $prItemId, 'pr_id' => $prId]);
        $item = $stmt->fetch();

        if (!$item) {
            return [
                'success' => false,
                'error' => 'Purchase Request item not found.',
            ];
        }

        if (!in_array((string) $item['pr_status'], [self::STATUS_APPROVED, self::STATUS_PARTIALLY_ORDERED], true)) {
            return [
                'success' => false,
                'error' => 'Only approved Purchase Requests are eligible for Purchase Order creation.',
            ];
        }

        $approvedQty = (float) ($item['approved_quantity'] ?? $item['quantity']);
        $currentOrderedQty = (float) ($item['ordered_quantity'] ?? 0);
        $newOrderedQty = $currentOrderedQty + $qtyToOrder;

        if ($newOrderedQty > $approvedQty) {
            return [
                'success' => false,
                'error' => sprintf(
                    "Ordering quantity (%.2f) exceeds remaining approved quantity (%.2f). Approved: %.2f, Already Ordered: %.2f.",
                    $qtyToOrder,
                    max(0, $approvedQty - $currentOrderedQty),
                    $approvedQty,
                    $currentOrderedQty
                ),
            ];
        }

        // Update item ordered quantity
        $updItem = $pdo->prepare(
            'UPDATE purchase_request_items
             SET ordered_quantity = :ordered_quantity
             WHERE id = :id'
        );
        $updItem->execute([
            'id' => $prItemId,
            'ordered_quantity' => number_format($newOrderedQty, 2, '.', ''),
        ]);

        // Evaluate overall PR ordering status
        $totals = $pdo->prepare(
            'SELECT
                SUM(COALESCE(approved_quantity, quantity)) AS total_approved,
                SUM(ordered_quantity) AS total_ordered
             FROM purchase_request_items
             WHERE purchase_request_id = :pr_id'
        );
        $totals->execute(['pr_id' => $prId]);
        $rowTotals = $totals->fetch() ?: [];

        $totAppr = (float) ($rowTotals['total_approved'] ?? 0);
        $totOrd = (float) ($rowTotals['total_ordered'] ?? 0);

        $newPrStatus = ($totOrd >= $totAppr && $totAppr > 0)
            ? self::STATUS_FULLY_ORDERED
            : self::STATUS_PARTIALLY_ORDERED;
        $orderedStatus = ($totOrd >= $totAppr && $totAppr > 0) ? 'fully_ordered' : 'partially_ordered';

        $updPr = $pdo->prepare(
            'UPDATE purchase_requests
             SET status = :status,
                 ordered_quantity_total = :tot_ord,
                 ordered_status = :ord_status,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $updPr->execute([
            'id' => $prId,
            'status' => $newPrStatus,
            'tot_ord' => number_format($totOrd, 2, '.', ''),
            'ord_status' => $orderedStatus,
        ]);

        self::recordHistory(
            $prId,
            (string) $item['pr_status'],
            $newPrStatus,
            'converted_to_po_item',
            $userId,
            sprintf('Ordered %.2f %s of item #%d (%s). Total ordered: %.2f/%.2f.', $qtyToOrder, $item['unit_of_measure'], $item['line_no'], $item['item_description'], $totOrd, $totAppr)
        );

        return [
            'success' => true,
            'remaining_quantity' => max(0, $approvedQty - $newOrderedQty),
        ];
    }

    /**
     * Live database counts for the Operations Dashboard:
     * - Total PRs
     * - Draft PRs
     * - Pending PRs
     * - Returned PRs
     * - Approved PRs
     * - PRs converted to PO
     * - PRs awaiting purchasing
     * - Recent project requests
     *
     * @return array{
     *   total_prs: int,
     *   draft_prs: int,
     *   pending_prs: int,
     *   returned_prs: int,
     *   approved_prs: int,
     *   converted_po_prs: int,
     *   awaiting_purchasing_prs: int,
     *   recent_project_requests: int,
     *   received_from_admin_finance: int,
     *   awaiting_pr_preparation: int,
     *   prs_being_prepared: int,
     *   prs_submitted_to_ops_finance: int
     * }
     */
    public static function operationsDashboardStats(): array
    {
        $sql = 'SELECT
                    COUNT(DISTINCT p.id) AS received_from_admin_finance,
                    COUNT(DISTINCT CASE
                        WHEN pr.id IS NULL THEN p.id
                    END) AS awaiting_pr_preparation,
                    COUNT(DISTINCT CASE
                        WHEN pr.id IS NOT NULL AND pr.status IN (\'Draft\', \'Returned for Corrections\', \'Returned for Revision\') THEN pr.id
                    END) AS prs_being_prepared,
                    COUNT(DISTINCT CASE
                        WHEN pr.id IS NOT NULL AND pr.status IN (\'Submitted to OPS Finance\', \'Submitted\', \'Under OPS Finance Review\', \'Under Review\', \'Actual BOM Submitted\') THEN pr.id
                    END) AS prs_submitted_to_ops_finance,
                    COUNT(DISTINCT pr.id) AS total_prs,
                    COUNT(DISTINCT CASE
                        WHEN pr.status = \'Draft\' THEN pr.id
                    END) AS draft_prs,
                    COUNT(DISTINCT CASE
                        WHEN pr.status IN (\'Submitted to OPS Finance\', \'Submitted\', \'Under OPS Finance Review\', \'Under Review\') THEN pr.id
                    END) AS pending_prs,
                    COUNT(DISTINCT CASE
                        WHEN pr.status IN (\'Returned for Corrections\', \'Returned for Revision\') THEN pr.id
                    END) AS returned_prs,
                    COUNT(DISTINCT CASE
                        WHEN pr.status = \'Approved\' THEN pr.id
                    END) AS approved_prs,
                    COUNT(DISTINCT CASE
                        WHEN pr.status IN (\'Converted to PO\', \'Partially Ordered\', \'Fully Ordered\') THEN pr.id
                    END) AS converted_po_prs,
                    COUNT(DISTINCT CASE
                        WHEN pr.status IN (\'Approved\', \'Partially Ordered\') THEN pr.id
                    END) AS awaiting_purchasing_prs
                FROM projects p
                LEFT JOIN purchase_requests pr ON pr.project_id = p.id
                WHERE (p.forwarded_to_ops_at IS NOT NULL OR p.status = \'Forwarded to Operations\')';

        $row = Database::connection()->query($sql)->fetch() ?: [];

        return [
            'total_prs' => (int) ($row['total_prs'] ?? 0),
            'draft_prs' => (int) ($row['draft_prs'] ?? 0),
            'pending_prs' => (int) ($row['pending_prs'] ?? 0),
            'returned_prs' => (int) ($row['returned_prs'] ?? 0),
            'approved_prs' => (int) ($row['approved_prs'] ?? 0),
            'converted_po_prs' => (int) ($row['converted_po_prs'] ?? 0),
            'awaiting_purchasing_prs' => (int) ($row['awaiting_purchasing_prs'] ?? 0),
            'recent_project_requests' => (int) ($row['received_from_admin_finance'] ?? 0),
            'received_from_admin_finance' => (int) ($row['received_from_admin_finance'] ?? 0),
            'awaiting_pr_preparation' => (int) ($row['awaiting_pr_preparation'] ?? 0),
            'prs_being_prepared' => (int) ($row['prs_being_prepared'] ?? 0),
            'prs_submitted_to_ops_finance' => (int) ($row['prs_submitted_to_ops_finance'] ?? 0),
        ];
    }

    /**
     * Paginated Operations Incoming Projects & Purchase Requests queue with date, project, JOM, status, and requester filters.
     *
     * @param array{q?: string, status?: string, sort?: string, date_from?: string, date_to?: string, project_id?: int|string, jom_number?: string, requested_by?: int|string} $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function paginateOperationsProjects(
        array $filters = [],
        int $page = 1,
        int $perPage = 10
    ): array {
        $where = ['(p.forwarded_to_ops_at IS NOT NULL OR p.status = \'Forwarded to Operations\')'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.project_code LIKE :q1 OR p.project_name LIKE :q2 OR p.client_name LIKE :q3 OR p.jom_number LIKE :q4 OR pr.pr_number LIKE :q5 OR req_u.full_name LIKE :q6)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
            $params['q5'] = $like;
            $params['q6'] = $like;
        }

        $statusFilter = trim((string) ($filters['status'] ?? ''));
        if ($statusFilter === 'awaiting_pr') {
            $where[] = 'pr.id IS NULL';
        } elseif ($statusFilter === 'preparing_pr' || $statusFilter === 'draft') {
            $where[] = 'pr.status IN (\'Draft\', \'Returned for Corrections\', \'Returned for Revision\')';
        } elseif ($statusFilter === 'submitted_pr' || $statusFilter === 'pending') {
            $where[] = 'pr.status IN (\'Submitted to OPS Finance\', \'Submitted\', \'Under OPS Finance Review\', \'Under Review\', \'Actual BOM Submitted\')';
        } elseif ($statusFilter === 'returned') {
            $where[] = 'pr.status IN (\'Returned for Corrections\', \'Returned for Revision\')';
        } elseif ($statusFilter === 'approved') {
            $where[] = 'pr.status = \'Approved\'';
        } elseif ($statusFilter === 'rejected') {
            $where[] = 'pr.status = \'Rejected\'';
        } elseif ($statusFilter === 'converted_po') {
            $where[] = 'pr.status IN (\'Converted to PO\', \'Partially Ordered\', \'Fully Ordered\')';
        } elseif ($statusFilter === 'awaiting_purchasing') {
            $where[] = 'pr.status IN (\'Approved\', \'Partially Ordered\')';
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 'pr.pr_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 'pr.pr_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $jom = trim((string) ($filters['jom_number'] ?? ''));
        if ($jom !== '') {
            $where[] = 'p.jom_number = :filter_jom';
            $params['filter_jom'] = $jom;
        }

        $projId = (int) ($filters['project_id'] ?? 0);
        if ($projId > 0) {
            $where[] = 'p.id = :filter_proj_id';
            $params['filter_proj_id'] = $projId;
        }

        $reqBy = (int) ($filters['requested_by'] ?? 0);
        if ($reqBy > 0) {
            $where[] = 'pr.requested_by = :filter_req_by';
            $params['filter_req_by'] = $reqBy;
        }

        $whereClause = ' WHERE ' . implode(' AND ', $where);

        $countSql = 'SELECT COUNT(DISTINCT p.id)
                     FROM projects p
                     LEFT JOIN purchase_requests pr ON pr.project_id = p.id
                     LEFT JOIN users req_u ON req_u.id = pr.requested_by' . $whereClause;
        $countStmt = Database::connection()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sort = (string) ($filters['sort'] ?? 'received');
        $orderBy = match ($sort) {
            'oldest' => ' ORDER BY COALESCE(p.forwarded_to_ops_at, p.updated_at) ASC, p.id ASC',
            'client' => ' ORDER BY p.client_name ASC, p.id DESC',
            'pr_date' => ' ORDER BY COALESCE(pr.pr_date, p.forwarded_to_ops_at) DESC, p.id DESC',
            'pr_number' => ' ORDER BY pr.pr_number DESC, p.id DESC',
            default => ' ORDER BY COALESCE(p.forwarded_to_ops_at, p.updated_at) DESC, p.id DESC',
        };

        $sql = 'SELECT
                    p.id,
                    p.project_code,
                    p.jom_number,
                    p.project_name,
                    p.client_name,
                    p.status,
                    p.forwarded_to_ops_at,
                    p.ops_sld_document_id,
                    sld.original_filename AS sld_filename,
                    sld.version_number AS sld_version,
                    p.ops_bom_document_id,
                    bom.original_filename AS bom_filename,
                    bom.version_number AS bom_version,
                    pr.id AS pr_id,
                    pr.pr_number,
                    pr.status AS pr_status,
                    pr.pr_date,
                    pr.purpose AS pr_purpose,
                    pr.required_delivery_date,
                    pr.submitted_at AS pr_submitted_at,
                    pr.approved_at AS pr_approved_at,
                    req_u.full_name AS requester_name,
                    appr_u.full_name AS approver_name,
                    (SELECT COUNT(*) FROM purchase_request_items pri WHERE pri.purchase_request_id = pr.id) AS pr_items_count
                FROM projects p
                LEFT JOIN purchase_requests pr ON pr.project_id = p.id
                LEFT JOIN users req_u ON req_u.id = pr.requested_by
                LEFT JOIN users appr_u ON appr_u.id = pr.approved_by
                LEFT JOIN project_documents sld ON sld.id = COALESCE(pr.sld_document_id, p.ops_sld_document_id)
                LEFT JOIN project_documents bom ON bom.id = COALESCE(pr.marketing_bom_document_id, p.ops_bom_document_id)'
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
}
