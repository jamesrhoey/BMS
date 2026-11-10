<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class PurchaseOrder
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PENDING_APPROVAL = 'Pending Approval';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_ISSUED_TO_SUPPLIER = 'Issued to Supplier';
    public const STATUS_PARTIALLY_RECEIVED = 'Partially Received';
    public const STATUS_FULLY_RECEIVED = 'Fully Received';
    public const STATUS_CLOSED = 'Closed';
    public const STATUS_CANCELLED = 'Cancelled';

    public const ALL_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_ISSUED_TO_SUPPLIER,
        self::STATUS_PARTIALLY_RECEIVED,
        self::STATUS_FULLY_RECEIVED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    private const BASE_SELECT = 'SELECT
            po.id,
            po.po_number,
            po.purchase_request_id,
            po.project_id,
            po.jom_number,
            po.supplier_id,
            po.po_date,
            po.delivery_terms,
            po.expected_delivery_date,
            po.payment_terms_summary,
            po.currency,
            po.subtotal_amount,
            po.tax_amount,
            po.total_amount,
            po.remarks,
            po.supporting_document_id,
            po.status,
            po.created_by,
            creator.full_name AS creator_name,
            creator.username AS creator_username,
            po.reviewed_by,
            reviewer.full_name AS reviewer_name,
            po.reviewed_at,
            po.review_notes,
            po.authorized_by,
            authorizer.full_name AS authorizer_name,
            authorizer.username AS authorizer_username,
            po.authorized_at,
            po.authorization_notes,
            po.sent_to_supplier_at,
            po.created_at,
            po.updated_at,
            p.project_code,
            p.project_name,
            p.client_name,
            pr.pr_number,
            s.supplier_name,
            s.supplier_code,
            s.contact_person AS supplier_contact_person,
            s.email AS supplier_email,
            s.phone AS supplier_phone,
            supp_doc.original_filename AS supporting_document_filename
        FROM purchase_orders po
        INNER JOIN projects p ON p.id = po.project_id
        INNER JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        INNER JOIN suppliers s ON s.id = po.supplier_id
        LEFT JOIN users creator ON creator.id = po.created_by
        LEFT JOIN users reviewer ON reviewer.id = po.reviewed_by
        LEFT JOIN users authorizer ON authorizer.id = po.authorized_by
        LEFT JOIN project_documents supp_doc ON supp_doc.id = po.supporting_document_id';

    /**
     * Generate sequential, zero-padded PO number (e.g. PO-202611-0001).
     */
    public static function generatePoNumber(?string $date = null): string
    {
        $dateObj = $date !== null ? new \DateTimeImmutable($date) : new \DateTimeImmutable('now');
        $prefix = 'PO-' . $dateObj->format('Ym') . '-';

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT po_number
             FROM purchase_orders
             WHERE po_number LIKE :prefix
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(['prefix' => $prefix . '%']);
        $latest = $stmt->fetchColumn();

        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $m)) {
            $seq = (int) $m[1] + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(self::BASE_SELECT . ' WHERE po.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['items'] = self::itemsForPo($id);
        $row['payment_terms'] = self::paymentTermsForPo($id);
        $row['histories'] = self::historiesForPo($id);

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByPoNumber(string $poNumber): ?array
    {
        $stmt = Database::connection()->prepare(self::BASE_SELECT . ' WHERE po.po_number = :po_number LIMIT 1');
        $stmt->execute(['po_number' => trim($poNumber)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $id = (int) $row['id'];
        $row['items'] = self::itemsForPo($id);
        $row['payment_terms'] = self::paymentTermsForPo($id);
        $row['histories'] = self::historiesForPo($id);

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . ' WHERE po.project_id = :project_id ORDER BY po.id DESC'
        );
        $stmt->execute(['project_id' => $projectId]);

        /** @var list<array<string, mixed>> */
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($orders as &$po) {
            $poId = (int) $po['id'];
            $po['items'] = self::itemsForPo($poId);
            $po['payment_terms'] = self::paymentTermsForPo($poId);
        }
        unset($po);

        return $orders;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forPurchaseRequest(int $prId): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . ' WHERE po.purchase_request_id = :pr_id ORDER BY po.id DESC'
        );
        $stmt->execute(['pr_id' => $prId]);

        /** @var list<array<string, mixed>> */
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($orders as &$po) {
            $poId = (int) $po['id'];
            $po['items'] = self::itemsForPo($poId);
            $po['payment_terms'] = self::paymentTermsForPo($poId);
        }
        unset($po);

        return $orders;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function itemsForPo(int $poId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                poi.*,
                pri.approved_quantity AS pr_approved_quantity,
                pri.ordered_quantity AS pr_ordered_quantity,
                pri.model_part_number AS pr_model_part_number,
                (poi.quantity_ordered - poi.quantity_received) AS remaining_to_receive
             FROM purchase_order_items poi
             LEFT JOIN purchase_request_items pri ON pri.id = poi.pr_item_id
             WHERE poi.purchase_order_id = :po_id
             ORDER BY poi.line_no ASC, poi.id ASC'
        );
        $stmt->execute(['po_id' => $poId]);

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function paymentTermsForPo(int $poId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                pts.*,
                u.full_name AS paid_by_name
             FROM payment_terms_schedules pts
             LEFT JOIN users u ON u.id = pts.paid_by
             WHERE pts.purchase_order_id = :po_id
             ORDER BY pts.id ASC'
        );
        $stmt->execute(['po_id' => $poId]);

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function historiesForPo(int $poId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                poh.*,
                u.full_name AS actor_name,
                u.username AS actor_username
             FROM purchase_order_histories poh
             LEFT JOIN users u ON u.id = poh.actor_id
             WHERE poh.purchase_order_id = :po_id
             ORDER BY poh.id DESC'
        );
        $stmt->execute(['po_id' => $poId]);

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new Purchase Order with items and optional payment terms.
     *
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $paymentTerms
     */
    public static function create(array $data, array $items, array $paymentTerms = []): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'INSERT INTO purchase_orders (
                po_number,
                purchase_request_id,
                project_id,
                jom_number,
                supplier_id,
                po_date,
                delivery_terms,
                expected_delivery_date,
                payment_terms_summary,
                currency,
                subtotal_amount,
                tax_amount,
                total_amount,
                remarks,
                supporting_document_id,
                status,
                created_by
            ) VALUES (
                :po_number,
                :purchase_request_id,
                :project_id,
                :jom_number,
                :supplier_id,
                :po_date,
                :delivery_terms,
                :expected_delivery_date,
                :payment_terms_summary,
                :currency,
                :subtotal_amount,
                :tax_amount,
                :total_amount,
                :remarks,
                :supporting_document_id,
                :status,
                :created_by
            )'
        );

        $stmt->execute([
            'po_number' => $data['po_number'],
            'purchase_request_id' => $data['purchase_request_id'],
            'project_id' => $data['project_id'],
            'jom_number' => $data['jom_number'],
            'supplier_id' => $data['supplier_id'],
            'po_date' => $data['po_date'],
            'delivery_terms' => $data['delivery_terms'] ?? null,
            'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
            'payment_terms_summary' => $data['payment_terms_summary'] ?? null,
            'currency' => $data['currency'] ?? 'PHP',
            'subtotal_amount' => $data['subtotal_amount'] ?? 0.00,
            'tax_amount' => $data['tax_amount'] ?? 0.00,
            'total_amount' => $data['total_amount'] ?? 0.00,
            'remarks' => $data['remarks'] ?? null,
            'supporting_document_id' => $data['supporting_document_id'] ?? null,
            'status' => $data['status'] ?? self::STATUS_DRAFT,
            'created_by' => $data['created_by'],
        ]);

        $poId = (int) $pdo->lastInsertId();

        self::replaceItems($poId, $items);
        self::replacePaymentTerms($poId, $paymentTerms);

        self::recordHistory(
            $poId,
            'None',
            $data['status'] ?? self::STATUS_DRAFT,
            'created_purchase_order',
            (int) $data['created_by'],
            sprintf("Created PO '%s' with %d item(s).", $data['po_number'], count($items))
        );

        return $poId;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    public static function replaceItems(int $poId, array $items): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM purchase_order_items WHERE purchase_order_id = :po_id')->execute(['po_id' => $poId]);

        $ins = $pdo->prepare(
            'INSERT INTO purchase_order_items (
                purchase_order_id,
                pr_item_id,
                line_no,
                item_description,
                model_part_number,
                item_specification,
                quantity_ordered,
                unit_of_measure,
                unit_price,
                total_amount,
                quantity_received,
                remarks
            ) VALUES (
                :po_id,
                :pr_item_id,
                :line_no,
                :item_description,
                :model_part_number,
                :item_specification,
                :quantity_ordered,
                :unit_of_measure,
                :unit_price,
                :total_amount,
                0.00,
                :remarks
            )'
        );

        $lineNo = 1;
        $subtotal = 0.0;
        foreach ($items as $item) {
            $qty = (float) ($item['quantity_ordered'] ?? $item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $total = round($qty * $price, 2);
            $subtotal += $total;

            $ins->execute([
                'po_id' => $poId,
                'pr_item_id' => !empty($item['pr_item_id']) ? (int) $item['pr_item_id'] : null,
                'line_no' => $lineNo++,
                'item_description' => (string) ($item['item_description'] ?? ''),
                'model_part_number' => !empty($item['model_part_number']) ? (string) $item['model_part_number'] : null,
                'item_specification' => (string) ($item['item_specification'] ?? ''),
                'quantity_ordered' => number_format($qty, 2, '.', ''),
                'unit_of_measure' => (string) ($item['unit_of_measure'] ?? 'pcs'),
                'unit_price' => number_format($price, 2, '.', ''),
                'total_amount' => number_format($total, 2, '.', ''),
                'remarks' => !empty($item['remarks']) ? (string) $item['remarks'] : null,
            ]);
        }

        // Update PO totals
        $pdo->prepare(
            'UPDATE purchase_orders
             SET subtotal_amount = :subtotal,
                 total_amount = :total,
                 updated_at = NOW()
             WHERE id = :id'
        )->execute([
            'id' => $poId,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'total' => number_format($subtotal, 2, '.', ''),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $paymentTerms
     */
    public static function replacePaymentTerms(int $poId, array $paymentTerms): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM payment_terms_schedules WHERE purchase_order_id = :po_id AND status != :paid')
            ->execute(['po_id' => $poId, 'paid' => 'Paid']);

        if ($paymentTerms === []) {
            return;
        }

        $ins = $pdo->prepare(
            'INSERT INTO payment_terms_schedules (
                purchase_order_id,
                payment_term,
                percentage,
                amount,
                due_date,
                status,
                remarks
            ) VALUES (
                :po_id,
                :payment_term,
                :percentage,
                :amount,
                :due_date,
                :status,
                :remarks
            )'
        );

        foreach ($paymentTerms as $term) {
            $ins->execute([
                'po_id' => $poId,
                'payment_term' => (string) ($term['payment_term'] ?? 'Milestone'),
                'percentage' => (float) ($term['percentage'] ?? 0),
                'amount' => (float) ($term['amount'] ?? 0),
                'due_date' => !empty($term['due_date']) ? (string) $term['due_date'] : null,
                'status' => (string) ($term['status'] ?? 'Scheduled'),
                'remarks' => !empty($term['remarks']) ? (string) $term['remarks'] : null,
            ]);
        }
    }

    /**
     * Financial authorization of a Purchase Order by Admin Finance.
     */
    public static function markAuthorized(int $id, int $userId, ?string $notes = null): bool
    {
        $po = self::findById($id);
        if (!$po) {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE purchase_orders
             SET status = :status,
                 authorized_by = :user_id,
                 authorized_at = NOW(),
                 authorization_notes = :notes,
                 updated_at = NOW()
             WHERE id = :id
               AND status IN (:s_draft, :s_pending)'
        );

        $stmt->execute([
            'id' => $id,
            'status' => self::STATUS_APPROVED,
            'user_id' => $userId,
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            's_draft' => self::STATUS_DRAFT,
            's_pending' => self::STATUS_PENDING_APPROVAL,
        ]);

        if ($stmt->rowCount() > 0) {
            self::recordHistory(
                $id,
                (string) $po['status'],
                self::STATUS_APPROVED,
                'authorized_purchase_order',
                $userId,
                $notes ?: 'Purchase Order financially authorized and approved.'
            );
            return true;
        }

        return false;
    }

    /**
     * Officially issue approved PO to external supplier.
     */
    public static function markIssuedToSupplier(int $id, int $userId, ?string $notes = null): bool
    {
        $po = self::findById($id);
        if (!$po) {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE purchase_orders
             SET status = :status,
                 sent_to_supplier_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND status = :s_appr'
        );

        $stmt->execute([
            'id' => $id,
            'status' => self::STATUS_ISSUED_TO_SUPPLIER,
            's_appr' => self::STATUS_APPROVED,
        ]);

        if ($stmt->rowCount() > 0) {
            self::recordHistory(
                $id,
                (string) $po['status'],
                self::STATUS_ISSUED_TO_SUPPLIER,
                'issued_to_supplier',
                $userId,
                $notes ?: "Transmitted Purchase Order '{$po['po_number']}' to supplier."
            );
            return true;
        }

        return false;
    }

    /**
     * Recompute receiving status for PO based on received quantities.
     */
    public static function updateReceivingStatus(int $poId): string
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT
                SUM(quantity_ordered) AS total_ordered,
                SUM(quantity_received) AS total_received
             FROM purchase_order_items
             WHERE purchase_order_id = :po_id'
        );
        $stmt->execute(['po_id' => $poId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $ordered = (float) ($row['total_ordered'] ?? 0);
        $received = (float) ($row['total_received'] ?? 0);

        if ($received <= 0) {
            return self::STATUS_ISSUED_TO_SUPPLIER;
        }

        $newStatus = ($received >= $ordered && $ordered > 0)
            ? self::STATUS_FULLY_RECEIVED
            : self::STATUS_PARTIALLY_RECEIVED;

        $pdo->prepare(
            'UPDATE purchase_orders
             SET status = :status,
                 updated_at = NOW()
             WHERE id = :id
               AND status NOT IN (:s_closed, :s_cancelled)'
        )->execute([
            'id' => $poId,
            'status' => $newStatus,
            's_closed' => self::STATUS_CLOSED,
            's_cancelled' => self::STATUS_CANCELLED,
        ]);

        return $newStatus;
    }

    /**
     * Record immutable PO audit history.
     */
    public static function recordHistory(
        int $poId,
        string $prevStatus,
        string $newStatus,
        string $action,
        int $actorId,
        ?string $notes = null
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO purchase_order_histories (
                purchase_order_id,
                previous_status,
                new_status,
                action,
                actor_id,
                notes
            ) VALUES (
                :po_id,
                :previous_status,
                :new_status,
                :action,
                :actor_id,
                :notes
            )'
        );

        $stmt->execute([
            'po_id' => $poId,
            'previous_status' => $prevStatus,
            'new_status' => $newStatus,
            'action' => $action,
            'actor_id' => $actorId,
            'notes' => $notes,
        ]);
    }
}
