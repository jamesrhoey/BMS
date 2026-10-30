<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Role
{
    public const SLUG_ADMIN = 'administrator';
    public const SLUG_DEPARTMENT_USER = 'department_user';

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, role_name, role_slug, description, created_at, updated_at
             FROM roles
             ORDER BY id ASC'
        );
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, role_name, role_slug, description, created_at, updated_at
             FROM roles
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
    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, role_name, role_slug, description, created_at, updated_at
             FROM roles
             WHERE role_slug = :slug
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * @return list<string>
     */
    public static function permissionKeysForRole(int $roleId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.permission_key
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :role_id'
        );
        $stmt->execute(['role_id' => $roleId]);
        /** @var list<string> $keys */
        $keys = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return $keys;
    }
}

