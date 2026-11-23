<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

/**
 * Product Catalog model backed by BMS's extended `inventory_items` table,
 * linked with `categories`, `equipment_models`, and `inventory_stock`.
 */
final class ProductCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function allActive(): array
    {
        $sql = "SELECT ii.*,
                       COALESCE(ii.sku, ii.item_code) AS sku,
                       COALESCE(ii.model_part_number, ii.item_code) AS model_code,
                       c.name AS category_name,
                       COALESCE(ist.quantity_on_hand, 0) AS quantity_on_hand,
                       COALESCE(ist.quantity_reserved, 0) AS quantity_reserved,
                       COALESCE(ist.quantity_available, 0) AS quantity_available,
                       ist.remarks AS ending_balance_remarks
                FROM inventory_items ii
                LEFT JOIN categories c ON c.id = ii.category_id
                LEFT JOIN inventory_stock ist ON ist.inventory_item_id = ii.id AND ist.location_id = 1
                WHERE ii.status = 'active'
                ORDER BY ii.item_name ASC";
        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $db = Database::connection();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(ii.item_code LIKE :s1 OR ii.sku LIKE :s2 OR ii.item_name LIKE :s3 OR ii.model_part_number LIKE :s4 OR ii.description LIKE :s5)';
            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
            $params['s4'] = $term;
            $params['s5'] = $term;
        }

        if (!empty($filters['category_id'])) {
            $where[] = 'ii.category_id = :cat_id';
            $params['cat_id'] = (int) $filters['category_id'];
        }

        if (isset($filters['is_serialized']) && $filters['is_serialized'] !== '') {
            $where[] = 'ii.is_serialized = :is_ser';
            $params['is_ser'] = (int) $filters['is_serialized'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'ii.status = :status';
            $params['status'] = trim((string) $filters['status']);
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM inventory_items ii WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT ii.*,
                       COALESCE(ii.sku, ii.item_code) AS sku,
                       COALESCE(ii.model_part_number, ii.item_code) AS model_code,
                       c.name AS category_name,
                       COALESCE(ist.quantity_on_hand, 0) AS quantity_on_hand,
                       COALESCE(ist.quantity_available, 0) AS quantity_available,
                       (SELECT COUNT(*) FROM inventory_units iu WHERE iu.inventory_item_id = ii.id AND iu.status = 'in_stock') AS in_stock_units
                FROM inventory_items ii
                LEFT JOIN categories c ON c.id = ii.category_id
                LEFT JOIN inventory_stock ist ON ist.inventory_item_id = ii.id AND ist.location_id = 1
                WHERE {$whereSql}
                ORDER BY ii.item_name ASC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ii.*,
                    COALESCE(ii.sku, ii.item_code) AS sku,
                    COALESCE(ii.model_part_number, ii.item_code) AS model_code,
                    c.name AS category_name,
                    COALESCE(ist.quantity_on_hand, 0) AS quantity_on_hand,
                    COALESCE(ist.quantity_reserved, 0) AS quantity_reserved,
                    COALESCE(ist.quantity_available, 0) AS quantity_available,
                    ist.remarks AS ending_balance_remarks
             FROM inventory_items ii
             LEFT JOIN categories c ON c.id = ii.category_id
             LEFT JOIN inventory_stock ist ON ist.inventory_item_id = ii.id AND ist.location_id = 1
             WHERE ii.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $modelCode = trim((string) ($data['model_code'] ?? $data['model_part_number'] ?? $data['sku'] ?? $data['item_code'] ?? ''));
        $description = trim((string) ($data['description'] ?? $data['item_name'] ?? $data['name'] ?? ''));
        $sku = trim((string) ($data['sku'] ?? $data['item_code'] ?? $modelCode));
        $name = trim((string) ($data['item_name'] ?? $data['name'] ?? $description));

        if ($sku === '' || $name === '') {
            throw new InvalidArgumentException('Model / Code and Description are required.');
        }

        return Database::transaction(static function (PDO $pdo) use ($data, $sku, $name, $modelCode, $description): int {
            $dup = $pdo->prepare('SELECT id FROM inventory_items WHERE item_code = :code OR sku = :sku LIMIT 1');
            $dup->execute(['code' => $sku, 'sku' => $sku]);
            if ($dup->fetchColumn()) {
                throw new InvalidArgumentException("Product Model / Code '{$sku}' already exists.");
            }

            $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
            $categoryLabel = 'Serialized Equipment';
            if ($categoryId !== null) {
                $catStmt = $pdo->prepare('SELECT name FROM categories WHERE id = :id LIMIT 1');
                $catStmt->execute(['id' => $categoryId]);
                $catName = $catStmt->fetchColumn();
                if (is_string($catName) && $catName !== '') {
                    $categoryLabel = $catName;
                }
            }

            $isSerialized = isset($data['is_serialized']) ? ((int) $data['is_serialized'] === 1 ? 1 : 0) : 1;
            $warrantyMonths = isset($data['default_warranty_months']) && $data['default_warranty_months'] !== ''
                ? max(0, (int) $data['default_warranty_months'])
                : Setting::getInt('default_warranty_months', 12);

            $status = 'active';
            if (isset($data['is_active'])) {
                $status = !empty($data['is_active']) ? 'active' : 'inactive';
            } elseif (isset($data['status'])) {
                $status = ($data['status'] === 'inactive') ? 'inactive' : 'active';
            }

            $stmt = $pdo->prepare(
                'INSERT INTO inventory_items (
                    item_code, sku, item_name, model_part_number, category, category_id,
                    unit_of_measure, is_serialized, default_warranty_months,
                    reorder_level, reorder_quantity, unit_cost, unit_price,
                    current_balance, description, remarks, status
                 ) VALUES (
                    :item_code, :sku, :item_name, :model, :category, :category_id,
                    :uom, :is_serialized, :warranty_months,
                    :reorder_level, :reorder_qty, :unit_cost, :unit_price,
                    0.00, :description, :remarks, :status
                 )'
            );
            $stmt->execute([
                'item_code' => $sku,
                'sku' => $sku,
                'item_name' => $name,
                'model' => $modelCode !== '' ? $modelCode : null,
                'category' => $categoryLabel,
                'category_id' => $categoryId,
                'uom' => trim((string) ($data['unit_of_measure'] ?? 'pcs')) ?: 'pcs',
                'is_serialized' => $isSerialized,
                'warranty_months' => $warrantyMonths,
                'reorder_level' => max(0, (int) ($data['reorder_level'] ?? 5)),
                'reorder_qty' => max(0, (int) ($data['reorder_quantity'] ?? 10)),
                'unit_cost' => round((float) ($data['unit_cost'] ?? 0), 2),
                'unit_price' => round((float) ($data['unit_price'] ?? 0), 2),
                'description' => $description !== '' ? $description : null,
                'remarks' => ($data['remarks'] ?? '') !== '' ? trim((string) $data['remarks']) : null,
                'status' => $status,
            ]);

            $itemId = (int) $pdo->lastInsertId();

            // Initialize inventory_stock row for default location (PCT Warehouse = 1)
            $pdo->prepare(
                'INSERT IGNORE INTO inventory_stock (inventory_item_id, location_id, quantity_on_hand, quantity_reserved, quantity_available, last_reconciled_at)
                 VALUES (:item_id, 1, 0, 0, 0, NOW())'
            )->execute(['item_id' => $itemId]);

            return $itemId;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $existing = self::find($id);
        if (!$existing) {
            throw new InvalidArgumentException('Product not found.');
        }

        $modelCode = trim((string) ($data['model_code'] ?? $data['model_part_number'] ?? $data['sku'] ?? $data['item_code'] ?? $existing['model_part_number'] ?? $existing['item_code']));
        $description = trim((string) ($data['description'] ?? $data['item_name'] ?? $data['name'] ?? $existing['description'] ?? $existing['item_name']));
        $sku = trim((string) ($data['sku'] ?? $data['item_code'] ?? $modelCode));
        $name = trim((string) ($data['item_name'] ?? $data['name'] ?? $description));

        if ($sku === '' || $name === '') {
            throw new InvalidArgumentException('Model / Code and Description are required.');
        }

        $db = Database::connection();
        $dup = $db->prepare('SELECT id FROM inventory_items WHERE (item_code = :code OR sku = :sku) AND id != :id LIMIT 1');
        $dup->execute(['code' => $sku, 'sku' => $sku, 'id' => $id]);
        if ($dup->fetchColumn()) {
            throw new InvalidArgumentException("Product Model / Code '{$sku}' is already in use.");
        }

        $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
        $categoryLabel = (string) $existing['category'];
        if ($categoryId !== null) {
            $catStmt = $db->prepare('SELECT name FROM categories WHERE id = :id LIMIT 1');
            $catStmt->execute(['id' => $categoryId]);
            $catName = $catStmt->fetchColumn();
            if (is_string($catName) && $catName !== '') {
                $categoryLabel = $catName;
            }
        }

        $status = (string) ($existing['status'] ?? 'active');
        if (isset($data['is_active'])) {
            $status = !empty($data['is_active']) ? 'active' : 'inactive';
        } elseif (isset($data['status'])) {
            $status = ($data['status'] === 'inactive') ? 'inactive' : 'active';
        }

        $stmt = $db->prepare(
            'UPDATE inventory_items
             SET item_code = :item_code,
                 sku = :sku,
                 item_name = :item_name,
                 model_part_number = :model,
                 category = :category,
                 category_id = :category_id,
                 unit_of_measure = :uom,
                 is_serialized = :is_serialized,
                 default_warranty_months = :warranty_months,
                 reorder_level = :reorder_level,
                 reorder_quantity = :reorder_qty,
                 unit_cost = :unit_cost,
                 unit_price = :unit_price,
                 description = :description,
                 remarks = :remarks,
                 status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'item_code' => $sku,
            'sku' => $sku,
            'item_name' => $name,
            'model' => $modelCode !== '' ? $modelCode : null,
            'category' => $categoryLabel,
            'category_id' => $categoryId,
            'uom' => trim((string) ($data['unit_of_measure'] ?? $existing['unit_of_measure'])) ?: 'pcs',
            'is_serialized' => isset($data['is_serialized']) ? ((int) $data['is_serialized'] === 1 ? 1 : 0) : (int) $existing['is_serialized'],
            'warranty_months' => isset($data['default_warranty_months']) ? max(0, (int) $data['default_warranty_months']) : (int) $existing['default_warranty_months'],
            'reorder_level' => isset($data['reorder_level']) ? max(0, (int) $data['reorder_level']) : (int) $existing['reorder_level'],
            'reorder_qty' => isset($data['reorder_quantity']) ? max(0, (int) $data['reorder_quantity']) : (int) $existing['reorder_quantity'],
            'unit_cost' => round((float) ($data['unit_cost'] ?? $existing['unit_cost']), 2),
            'unit_price' => round((float) ($data['unit_price'] ?? $existing['unit_price']), 2),
            'description' => $description !== '' ? $description : null,
            'remarks' => ($data['remarks'] ?? '') !== '' ? trim((string) $data['remarks']) : null,
            'status' => $status,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function categories(): array
    {
        return Database::connection()
            ->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function locations(): array
    {
        return Database::connection()
            ->query('SELECT * FROM inventory_locations WHERE is_active = 1 ORDER BY id ASC')
            ->fetchAll(PDO::FETCH_ASSOC);
    }
}
