<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\ActivityLogger;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Immutable Double-Entry Inventory Ledger & Ending Balance (Form 3) Engine.
 *
 * Enforces:
 * - Row-level pessimistic locking (SELECT ... FOR UPDATE) on `inventory_stock`.
 * - Atomic lockstep updates across `inventory_ledger`, `inventory_stock`, and `inventory_items.current_balance`.
 * - Strict non-negative available balance prevention.
 * - Ending Balance (Paper Form 3) calculation & persistent inline-editable remarks.
 */
final class InventoryLedger
{
    /**
     * Generate unique sequential stock transaction number (e.g. STX-2026-0001).
     */
    public static function generateStockTransactionNumber(PDO $pdo, string $prefixType = 'STX'): string
    {
        $year = date('Y');
        $prefix = "{$prefixType}-{$year}-";
        $stmt = $pdo->prepare(
            'SELECT transaction_number FROM stock_transactions WHERE transaction_number LIKE :prefix ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();

        if (is_string($last) && preg_match('/-(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        return sprintf('%s%04d', $prefix, $next);
    }

    /**
     * Ensure an `inventory_stock` row exists and lock it via FOR UPDATE.
     *
     * @return array<string, mixed>
     */
    public static function lockStockRow(PDO $pdo, int $itemId, int $locationId = 1): array
    {
        $pdo->prepare(
            'INSERT IGNORE INTO inventory_stock (inventory_item_id, location_id, quantity_on_hand, quantity_reserved, quantity_available, last_reconciled_at)
             VALUES (:item_id, :loc_id, 0, 0, 0, NOW())'
        )->execute([
            'item_id' => $itemId,
            'loc_id' => $locationId,
        ]);

        $stmt = $pdo->prepare(
            'SELECT * FROM inventory_stock WHERE inventory_item_id = :item_id AND location_id = :loc_id FOR UPDATE'
        );
        $stmt->execute([
            'item_id' => $itemId,
            'loc_id' => $locationId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException("Unable to lock inventory_stock row for item #{$itemId} at location #{$locationId}.");
        }

        return $row;
    }

    /**
     * Post an immutable double-entry ledger movement and update cached stock balances in lockstep.
     * Must be called inside an active PDO transaction.
     *
     * @param array{
     *     inventory_item_id: int,
     *     location_id?: int,
     *     transaction_type: string,
     *     reference_type: string,
     *     reference_id?: int|null,
     *     quantity_in?: int,
     *     quantity_out?: int,
     *     delta_on_hand?: int|null,
     *     delta_available?: int|null,
     *     unit_cost?: float,
     *     performed_by_id: int,
     *     notes?: string|null
     * } $params
     * @return array{ledger_id: int, balance_before: int, balance_after: int, quantity_on_hand: int, quantity_available: int}
     */
    public static function postMovement(PDO $pdo, array $params): array
    {
        $itemId = (int) $params['inventory_item_id'];
        $locationId = (int) ($params['location_id'] ?? 1);
        $qtyIn = max(0, (int) ($params['quantity_in'] ?? 0));
        $qtyOut = max(0, (int) ($params['quantity_out'] ?? 0));
        $txType = strtoupper(trim((string) $params['transaction_type']));
        $refType = strtoupper(trim((string) $params['reference_type']));
        $refId = isset($params['reference_id']) ? (int) $params['reference_id'] : null;
        $unitCost = round((float) ($params['unit_cost'] ?? 0.00), 2);
        $userId = (int) $params['performed_by_id'];
        $notes = isset($params['notes']) && trim((string) $params['notes']) !== '' ? trim((string) $params['notes']) : null;

        if ($qtyIn === 0 && $qtyOut === 0 && !isset($params['delta_available']) && !isset($params['delta_on_hand'])) {
            throw new InvalidArgumentException('Ledger movement must have a non-zero quantity.');
        }

        // 1. Pessimistic lock on inventory_stock row
        $stockRow = self::lockStockRow($pdo, $itemId, $locationId);
        $onHandBefore = (int) $stockRow['quantity_on_hand'];
        $availBefore = (int) $stockRow['quantity_available'];

        $deltaOnHand = $params['delta_on_hand'] ?? ($qtyIn - $qtyOut);
        $deltaAvail = $params['delta_available'] ?? ($qtyIn - $qtyOut);

        $newOnHand = $onHandBefore + (int) $deltaOnHand;
        $newAvail = $availBefore + (int) $deltaAvail;

        if ($newOnHand < 0 || $newAvail < 0) {
            throw new RuntimeException(
                "Insufficient stock for item #{$itemId}: on_hand={$onHandBefore}, available={$availBefore}, attempted delta_on_hand={$deltaOnHand}, delta_available={$deltaAvail}."
            );
        }

        // 2. Insert immutable inventory_ledger entry
        $ins = $pdo->prepare(
            'INSERT INTO inventory_ledger (
                inventory_item_id, location_id, transaction_type, reference_type, reference_id,
                quantity_in, quantity_out, balance_after, unit_cost, performed_by_id, notes
             ) VALUES (
                :item_id, :loc_id, :tx_type, :ref_type, :ref_id,
                :qty_in, :qty_out, :balance_after, :unit_cost, :user_id, :notes
             )'
        );
        $ins->execute([
            'item_id' => $itemId,
            'loc_id' => $locationId,
            'tx_type' => $txType,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'balance_after' => $newOnHand,
            'unit_cost' => $unitCost,
            'user_id' => $userId,
            'notes' => $notes,
        ]);
        $ledgerId = (int) $pdo->lastInsertId();

        // 3. Update cached inventory_stock in lockstep
        $updStock = $pdo->prepare(
            'UPDATE inventory_stock
             SET quantity_on_hand = :on_hand,
                 quantity_available = :avail,
                 last_reconciled_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );
        $updStock->execute([
            'on_hand' => $newOnHand,
            'avail' => $newAvail,
            'id' => (int) $stockRow['id'],
        ]);

        // 4. Sync aggregate inventory_items.current_balance across all locations
        $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(quantity_available), 0) FROM inventory_stock WHERE inventory_item_id = :item_id');
        $sumStmt->execute(['item_id' => $itemId]);
        $totalAvailable = (float) $sumStmt->fetchColumn();

        $pdo->prepare('UPDATE inventory_items SET current_balance = :bal, updated_at = NOW() WHERE id = :id')
            ->execute(['bal' => $totalAvailable, 'id' => $itemId]);

        return [
            'ledger_id' => $ledgerId,
            'balance_before' => $onHandBefore,
            'balance_after' => $newOnHand,
            'quantity_on_hand' => $newOnHand,
            'quantity_available' => $newAvail,
        ];
    }

    /**
     * Ending Balance (Digitized Paper Form 3) query.
     *
     * Computes:
     *   Ending Balance = Beginning Balance + Stock In - Stock Out
     * Strictly matches available `in_stock` physical units for serialized models
     * (excluding `delivered`, `under_repair`, and `scrapped` units) and includes
     * persistent inline-editable remarks from `inventory_stock.remarks`.
     *
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function getEndingBalanceList(array $filters = []): array
    {
        $db = Database::connection();
        $locationId = !empty($filters['location_id']) ? (int) $filters['location_id'] : 1;
        $where = ["ii.status = 'active'"];
        $params = [
            'loc_stock' => $locationId,
            'loc_units' => $locationId,
            'loc_ledger' => $locationId,
        ];

        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(ii.item_code LIKE :s1 OR ii.model_part_number LIKE :s2 OR ii.item_name LIKE :s3 OR ii.description LIKE :s4 OR ist.remarks LIKE :s5)';
            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
            $params['s4'] = $term;
            $params['s5'] = $term;
        }

        if (!empty($filters['item_id'])) {
            $where[] = 'ii.id = :item_id';
            $params['item_id'] = (int) $filters['item_id'];
        }

        if (!empty($filters['category_id'])) {
            $where[] = 'ii.category_id = :cat_id';
            $params['cat_id'] = (int) $filters['category_id'];
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT
                    ii.id AS inventory_item_id,
                    ii.item_code,
                    COALESCE(ii.sku, ii.item_code) AS sku,
                    COALESCE(ii.model_part_number, ii.item_code) AS model_code,
                    ii.item_name,
                    ii.description,
                    ii.is_serialized,
                    ii.unit_of_measure,
                    ii.reorder_level,
                    ii.unit_cost,
                    c.name AS category_name,
                    COALESCE(ist.location_id, :loc_stock) AS location_id,
                    COALESCE(ist.quantity_on_hand, 0) AS quantity_on_hand,
                    COALESCE(ist.quantity_reserved, 0) AS quantity_reserved,
                    COALESCE(ist.quantity_available, 0) AS quantity_available,
                    ist.remarks AS remarks,
                    ist.last_reconciled_at,
                    (SELECT COALESCE(SUM(il.quantity_in), 0)
                     FROM inventory_ledger il
                     WHERE il.inventory_item_id = ii.id AND il.location_id = :loc_ledger) AS total_in,
                    (SELECT COALESCE(SUM(il.quantity_out), 0)
                     FROM inventory_ledger il
                     WHERE il.inventory_item_id = ii.id AND il.location_id = :loc_ledger_out) AS total_out,
                    (SELECT COUNT(*)
                     FROM inventory_units iu
                     WHERE iu.inventory_item_id = ii.id
                       AND (iu.location_id = :loc_units OR iu.location_id IS NULL)
                       AND iu.status = 'in_stock') AS physical_in_stock_count,
                    (SELECT COUNT(*)
                     FROM inventory_units iu
                     WHERE iu.inventory_item_id = ii.id
                       AND iu.status = 'under_repair') AS under_repair_count,
                    (SELECT COUNT(*)
                     FROM inventory_units iu
                     WHERE iu.inventory_item_id = ii.id
                       AND iu.status = 'delivered') AS delivered_count
                FROM inventory_items ii
                LEFT JOIN categories c ON c.id = ii.category_id
                LEFT JOIN inventory_stock ist ON ist.inventory_item_id = ii.id AND ist.location_id = :loc_join
                WHERE {$whereSql}
                ORDER BY ii.item_name ASC";

        $params['loc_ledger_out'] = $locationId;
        $params['loc_join'] = $locationId;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $isSer = (int) $row['is_serialized'] === 1;
            $endingBal = $isSer
                ? (int) $row['physical_in_stock_count']
                : (int) $row['quantity_available'];

            $row['ending_balance'] = $endingBal;
            $row['total_in'] = (int) $row['total_in'];
            $row['total_out'] = (int) $row['total_out'];
            $row['is_low_stock'] = $endingBal <= (int) $row['reorder_level'];
        }
        unset($row);

        if (!empty($filters['stock_status'])) {
            $st = (string) $filters['stock_status'];
            $rows = array_values(array_filter($rows, static function (array $r) use ($st): bool {
                if ($st === 'in_stock') {
                    return $r['ending_balance'] > 0;
                }
                if ($st === 'low_stock') {
                    return $r['ending_balance'] > 0 && $r['is_low_stock'];
                }
                if ($st === 'out_of_stock') {
                    return $r['ending_balance'] === 0;
                }
                return true;
            }));
        }

        return $rows;
    }

    /**
     * Save persistent inline-editable Form 3 Ending Balance remarks.
     */
    public static function updateEndingBalanceRemarks(
        int $itemId,
        int $locationId,
        ?string $remarks,
        int $userId
    ): void {
        Database::transaction(static function (PDO $pdo) use ($itemId, $locationId, $remarks, $userId): void {
            $stockRow = self::lockStockRow($pdo, $itemId, $locationId);
            $oldRemarks = $stockRow['remarks'];
            $cleanRemarks = $remarks !== null && trim($remarks) !== '' ? trim($remarks) : null;

            $pdo->prepare('UPDATE inventory_stock SET remarks = :remarks, updated_at = NOW() WHERE id = :id')
                ->execute([
                    'remarks' => $cleanRemarks,
                    'id' => (int) $stockRow['id'],
                ]);

            ActivityLogger::log(
                'ENDING_BALANCE_REMARKS_UPDATED',
                'inventory_stock',
                (int) $stockRow['id'],
                "Updated Ending Balance (Form 3) remarks for item #{$itemId}.",
                null,
                $userId,
                false,
                ['remarks' => $oldRemarks],
                ['remarks' => $cleanRemarks]
            );
        });
    }

    /**
     * Authorized manual stock adjustment with mandatory reason logging.
     *
     * @return array<string, mixed>
     */
    public static function adjustStock(
        int $itemId,
        int $locationId,
        int $delta,
        string $reason,
        int $userId
    ): array {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A documented justification reason is mandatory for manual stock adjustments.');
        }
        if ($delta === 0) {
            throw new InvalidArgumentException('Adjustment quantity delta must be non-zero.');
        }

        return Database::transaction(static function (PDO $pdo) use ($itemId, $locationId, $delta, $reason, $userId): array {
            $item = ProductCatalog::find($itemId);
            if (!$item) {
                throw new InvalidArgumentException("Inventory item #{$itemId} not found.");
            }

            $txNum = self::generateStockTransactionNumber($pdo, 'ADJ');
            $txType = $delta > 0 ? 'STOCK_IN' : 'STOCK_OUT';

            $pdo->prepare(
                'INSERT INTO stock_transactions (transaction_number, transaction_type, reference_type, reference_id, source_location_id, destination_location_id, performed_by_id, transaction_date, notes)
                 VALUES (:tx_num, :tx_type, "ADJUSTMENT", :ref_id, :src_loc, :dst_loc, :user_id, NOW(), :notes)'
            )->execute([
                'tx_num' => $txNum,
                'tx_type' => $txType,
                'ref_id' => $itemId,
                'src_loc' => $delta < 0 ? $locationId : null,
                'dst_loc' => $delta > 0 ? $locationId : null,
                'user_id' => $userId,
                'notes' => $reason,
            ]);
            $txId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO stock_transaction_items (transaction_id, inventory_item_id, quantity, unit_cost, notes)
                 VALUES (:tx_id, :item_id, :qty, :cost, :notes)'
            )->execute([
                'tx_id' => $txId,
                'item_id' => $itemId,
                'qty' => abs($delta),
                'cost' => (float) ($item['unit_cost'] ?? 0),
                'notes' => $reason,
            ]);

            $movement = self::postMovement($pdo, [
                'inventory_item_id' => $itemId,
                'location_id' => $locationId,
                'transaction_type' => 'ADJUSTMENT',
                'reference_type' => 'ADJUSTMENT',
                'reference_id' => $txId,
                'quantity_in' => $delta > 0 ? $delta : 0,
                'quantity_out' => $delta < 0 ? abs($delta) : 0,
                'unit_cost' => (float) ($item['unit_cost'] ?? 0),
                'performed_by_id' => $userId,
                'notes' => "Manual Adjustment ({$txNum}): {$reason}",
            ]);

            ActivityLogger::log(
                'STOCK_ADJUSTED',
                'inventory_stock',
                $itemId,
                "Manual stock adjustment ({$txNum}) of {$delta} for {$item['item_name']}: {$reason}",
                null,
                $userId,
                false,
                ['balance_before' => $movement['balance_before']],
                ['balance_after' => $movement['balance_after'], 'delta' => $delta, 'reason' => $reason]
            );

            return [
                'transaction_id' => $txId,
                'transaction_number' => $txNum,
                'movement' => $movement,
            ];
        });
    }
}
