<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class SupplierDelivery
{
    public const STATUS_PENDING_INSPECTION = 'Pending Inspection';
    public const STATUS_INSPECTED = 'Inspected';
    public const STATUS_PARTIALLY_RECEIVED = 'Partially Received';
    public const STATUS_FULLY_RECEIVED = 'Fully Received';
    public const STATUS_DISCREPANCY = 'Discrepancy';

    private const BASE_SELECT = 'SELECT
            sd.id,
            sd.delivery_receipt_no,
            sd.purchase_order_id,
            sd.project_id,
            sd.supplier_id,
            sd.delivery_date,
            sd.carrier_name,
            sd.tracking_or_plate_no,
            sd.dr_document_id,
            sd.received_by,
            sd.receiving_notes,
            sd.status,
            sd.created_at,
            sd.updated_at,
            po.po_number,
            po.po_date,
            po.status AS po_status,
            p.project_code,
            p.project_name,
            s.supplier_name,
            s.supplier_code,
            rec_u.full_name AS receiver_name,
            rec_u.username AS receiver_username,
            dr_doc.original_filename AS dr_document_filename
        FROM supplier_deliveries sd
        INNER JOIN purchase_orders po ON po.id = sd.purchase_order_id
        INNER JOIN projects p ON p.id = sd.project_id
        INNER JOIN suppliers s ON s.id = sd.supplier_id
        LEFT JOIN users rec_u ON rec_u.id = sd.received_by
        LEFT JOIN project_documents dr_doc ON dr_doc.id = sd.dr_document_id';

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(self::BASE_SELECT . ' WHERE sd.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['items'] = self::itemsForDelivery($id);
        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByDrNumber(string $drNumber): ?array
    {
        $stmt = Database::connection()->prepare(self::BASE_SELECT . ' WHERE sd.delivery_receipt_no = :dr LIMIT 1');
        $stmt->execute(['dr' => trim($drNumber)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['items'] = self::itemsForDelivery((int) $row['id']);
        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forPo(int $poId): array
    {
        $stmt = Database::connection()->prepare(self::BASE_SELECT . ' WHERE sd.purchase_order_id = :po_id ORDER BY sd.id DESC');
        $stmt->execute(['po_id' => $poId]);

        /** @var list<array<string, mixed>> */
        $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($deliveries as &$del) {
            $del['items'] = self::itemsForDelivery((int) $del['id']);
        }
        unset($del);

        return $deliveries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare(self::BASE_SELECT . ' WHERE sd.project_id = :project_id ORDER BY sd.id DESC');
        $stmt->execute(['project_id' => $projectId]);

        /** @var list<array<string, mixed>> */
        $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($deliveries as &$del) {
            $del['items'] = self::itemsForDelivery((int) $del['id']);
        }
        unset($del);

        return $deliveries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function itemsForDelivery(int $deliveryId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                sdi.*,
                poi.line_no AS po_line_no,
                poi.item_description,
                poi.model_part_number,
                poi.item_specification,
                poi.quantity_ordered,
                poi.unit_of_measure,
                poi.quantity_received AS po_quantity_received
             FROM supplier_delivery_items sdi
             INNER JOIN purchase_order_items poi ON poi.id = sdi.po_item_id
             WHERE sdi.delivery_id = :delivery_id
             ORDER BY poi.line_no ASC, sdi.id ASC'
        );
        $stmt->execute(['delivery_id' => $deliveryId]);

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create incoming supplier delivery record.
     *
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $items
     */
    public static function create(array $data, array $items): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'INSERT INTO supplier_deliveries (
                delivery_receipt_no,
                purchase_order_id,
                project_id,
                supplier_id,
                delivery_date,
                carrier_name,
                tracking_or_plate_no,
                dr_document_id,
                received_by,
                receiving_notes,
                status
            ) VALUES (
                :delivery_receipt_no,
                :purchase_order_id,
                :project_id,
                :supplier_id,
                :delivery_date,
                :carrier_name,
                :tracking_or_plate_no,
                :dr_document_id,
                :received_by,
                :receiving_notes,
                :status
            )'
        );

        $stmt->execute([
            'delivery_receipt_no' => $data['delivery_receipt_no'],
            'purchase_order_id' => $data['purchase_order_id'],
            'project_id' => $data['project_id'],
            'supplier_id' => $data['supplier_id'],
            'delivery_date' => $data['delivery_date'],
            'carrier_name' => $data['carrier_name'] ?? null,
            'tracking_or_plate_no' => $data['tracking_or_plate_no'] ?? null,
            'dr_document_id' => $data['dr_document_id'] ?? null,
            'received_by' => $data['received_by'],
            'receiving_notes' => $data['receiving_notes'] ?? null,
            'status' => $data['status'] ?? self::STATUS_PENDING_INSPECTION,
        ]);

        $deliveryId = (int) $pdo->lastInsertId();

        $insItem = $pdo->prepare(
            'INSERT INTO supplier_delivery_items (
                delivery_id,
                po_item_id,
                quantity_delivered,
                quantity_accepted,
                quantity_rejected,
                rejection_reason,
                remarks
            ) VALUES (
                :delivery_id,
                :po_item_id,
                :quantity_delivered,
                :quantity_accepted,
                :quantity_rejected,
                :rejection_reason,
                :remarks
            )'
        );

        foreach ($items as $item) {
            $delQty = (float) ($item['quantity_delivered'] ?? 0);
            $accQty = (float) ($item['quantity_accepted'] ?? $delQty);
            $rejQty = (float) ($item['quantity_rejected'] ?? max(0, $delQty - $accQty));

            $insItem->execute([
                'delivery_id' => $deliveryId,
                'po_item_id' => (int) $item['po_item_id'],
                'quantity_delivered' => number_format($delQty, 2, '.', ''),
                'quantity_accepted' => number_format($accQty, 2, '.', ''),
                'quantity_rejected' => number_format($rejQty, 2, '.', ''),
                'rejection_reason' => !empty($item['rejection_reason']) ? (string) $item['rejection_reason'] : null,
                'remarks' => !empty($item['remarks']) ? (string) $item['remarks'] : null,
            ]);
        }

        return $deliveryId;
    }

    /**
     * Compute delivery tracking statistics for a PO.
     *
     * @return array<string, mixed>
     */
    public static function deliveryTrackingSummaryForPo(int $poId): array
    {
        $po = PurchaseOrder::findById($poId);
        if (!$po) {
            return [];
        }

        $items = $po['items'] ?? [];
        $totalOrdered = 0.0;
        $totalReceived = 0.0;

        $itemSummaries = [];
        foreach ($items as $it) {
            $ordered = (float) $it['quantity_ordered'];
            $received = (float) $it['quantity_received'];
            $remaining = max(0.0, $ordered - $received);

            $totalOrdered += $ordered;
            $totalReceived += $received;

            $itemSummaries[] = [
                'po_item_id' => (int) $it['id'],
                'description' => $it['item_description'],
                'model' => $it['model_part_number'],
                'ordered_quantity' => $ordered,
                'previously_received_quantity' => $received,
                'remaining_quantity' => $remaining,
                'unit_of_measure' => $it['unit_of_measure'],
            ];
        }

        $deliveries = self::forPo($poId);

        return [
            'po_number' => $po['po_number'],
            'po_date' => $po['po_date'],
            'supplier_name' => $po['supplier_name'],
            'total_ordered' => $totalOrdered,
            'total_received' => $totalReceived,
            'total_remaining' => max(0.0, $totalOrdered - $totalReceived),
            'items' => $itemSummaries,
            'deliveries' => $deliveries,
        ];
    }
}
