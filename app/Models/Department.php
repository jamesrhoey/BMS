<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Department
{
    public const CODE_MARKETING = 'MKT';
    public const CODE_PROJECT_MANAGEMENT = 'PM';
    public const CODE_OPERATIONS = 'OPS';
    public const CODE_LOGISTICS = 'LOG';
    public const CODE_OPERATION_FINANCE = 'OPFIN';
    public const CODE_ADMIN_FINANCE = 'ADMFIN';

    /**
     * @return list<array<string, mixed>>
     */
    public static function allWithStats(): array
    {
        $sql = 'SELECT
                    d.id,
                    d.department_name,
                    d.department_code,
                    d.description,
                    d.status,
                    d.created_at,
                    d.updated_at,
                    COUNT(DISTINCT u.id) AS total_users,
                    COUNT(DISTINCT CASE WHEN u.status = \'active\' THEN u.id END) AS active_users,
                    COUNT(DISTINCT p.id) AS assigned_projects
                FROM departments d
                LEFT JOIN users u ON u.department_id = d.id
                LEFT JOIN projects p ON p.current_department_id = d.id
                GROUP BY
                    d.id,
                    d.department_name,
                    d.department_code,
                    d.description,
                    d.status,
                    d.created_at,
                    d.updated_at
                ORDER BY d.id ASC';

        return Database::connection()->query($sql)->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function allActive(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, department_name, department_code, description, status
             FROM departments
             WHERE status = :status
             ORDER BY id ASC'
        );
        $stmt->execute(['status' => 'active']);
        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return Database::connection()->query(
            'SELECT id, department_name, department_code, description, status, created_at, updated_at
             FROM departments
             ORDER BY id ASC'
        )->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, department_name, department_code, description, status, created_at, updated_at
             FROM departments
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByCode(string $code): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, department_name, department_code, description, status, created_at, updated_at
             FROM departments
             WHERE department_code = :code
             LIMIT 1'
        );
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function paginateWithStats(int $page = 1, int $perPage = 6): array
    {
        $total = self::countAll();
        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT
                    d.id,
                    d.department_name,
                    d.department_code,
                    d.description,
                    d.status,
                    d.created_at,
                    d.updated_at,
                    COUNT(DISTINCT u.id) AS total_users,
                    COUNT(DISTINCT CASE WHEN u.status = \'active\' THEN u.id END) AS active_users,
                    COUNT(DISTINCT p.id) AS assigned_projects
                FROM departments d
                LEFT JOIN users u ON u.department_id = d.id
                LEFT JOIN projects p ON p.current_department_id = d.id
                GROUP BY
                    d.id,
                    d.department_name,
                    d.department_code,
                    d.description,
                    d.status,
                    d.created_at,
                    d.updated_at
                ORDER BY d.id ASC
                LIMIT :limit_rows OFFSET :offset_rows';

        $stmt = Database::connection()->prepare($sql);
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

    public static function countAll(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM departments')->fetchColumn();
    }
}

