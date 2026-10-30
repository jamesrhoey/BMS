<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    private const BASE_SELECT = '
        SELECT
            u.id,
            u.department_id,
            u.role_id,
            u.full_name,
            u.username,
            u.email,
            u.password_hash,
            u.status,
            u.last_login_at,
            u.created_at,
            u.updated_at,
            r.role_name,
            r.role_slug,
            d.department_name,
            d.department_code,
            d.status AS department_status
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        LEFT JOIN departments d ON d.id = u.department_id
    ';

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(self::BASE_SELECT . ' WHERE u.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::connection()->prepare(self::BASE_SELECT . ' WHERE u.username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public static function usernameExists(string $username, ?int $excludeUserId = null): bool
    {
        if ($excludeUserId !== null) {
            $stmt = Database::connection()->prepare(
                'SELECT 1 FROM users WHERE username = :username AND id != :exclude_id LIMIT 1'
            );
            $stmt->execute(['username' => $username, 'exclude_id' => $excludeUserId]);
        } else {
            $stmt = Database::connection()->prepare(
                'SELECT 1 FROM users WHERE username = :username LIMIT 1'
            );
            $stmt->execute(['username' => $username]);
        }

        return $stmt->fetchColumn() !== false;
    }

    public static function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        if ($email === '') {
            return false;
        }

        if ($excludeUserId !== null) {
            $stmt = Database::connection()->prepare(
                'SELECT 1 FROM users WHERE email = :email AND id != :exclude_id LIMIT 1'
            );
            $stmt->execute(['email' => $email, 'exclude_id' => $excludeUserId]);
        } else {
            $stmt = Database::connection()->prepare(
                'SELECT 1 FROM users WHERE email = :email LIMIT 1'
            );
            $stmt->execute(['email' => $email]);
        }

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function allWithRelations(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(u.full_name LIKE :q1 OR u.username LIKE :q2 OR u.email LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        if (!empty($filters['department_id']) && ctype_digit((string) $filters['department_id'])) {
            $conditions[] = 'u.department_id = :department_id';
            $params['department_id'] = (int) $filters['department_id'];
        }

        if (!empty($filters['role_id']) && ctype_digit((string) $filters['role_id'])) {
            $conditions[] = 'u.role_id = :role_id';
            $params['role_id'] = (int) $filters['role_id'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], [self::STATUS_ACTIVE, self::STATUS_INACTIVE], true)) {
            $conditions[] = 'u.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql = self::BASE_SELECT;
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY u.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function byDepartment(int $departmentId): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . ' WHERE u.department_id = :dept_id ORDER BY u.full_name ASC'
        );
        $stmt->execute(['dept_id' => $departmentId]);
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO users (department_id, role_id, full_name, username, email, password_hash, status)
             VALUES (:department_id, :role_id, :full_name, :username, :email, :password_hash, :status)'
        );
        $stmt->execute([
            'department_id' => $data['department_id'],
            'role_id' => $data['role_id'],
            'full_name' => $data['full_name'],
            'username' => $data['username'],
            'email' => $data['email'] !== '' ? $data['email'] : null,
            'password_hash' => $data['password_hash'],
            'status' => $data['status'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET department_id = :department_id,
                 role_id = :role_id,
                 full_name = :full_name,
                 username = :username,
                 email = :email,
                 status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'department_id' => $data['department_id'],
            'role_id' => $data['role_id'],
            'full_name' => $data['full_name'],
            'username' => $data['username'],
            'email' => $data['email'] !== '' ? $data['email'] : null,
            'status' => $data['status'],
            'id' => $id,
        ]);
    }

    public static function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET password_hash = :password_hash WHERE id = :id'
        );
        $stmt->execute([
            'password_hash' => $passwordHash,
            'id' => $id,
        ]);
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET status = :status WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'id' => $id,
        ]);
    }

    public static function recordLastLogin(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public static function countActiveAdmins(?int $excludeUserId = null): int
    {
        $sql = 'SELECT COUNT(*)
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE r.role_slug = :slug AND u.status = :status';
        $params = [
            'slug' => Role::SLUG_ADMIN,
            'status' => self::STATUS_ACTIVE,
        ];

        if ($excludeUserId !== null) {
            $sql .= ' AND u.id != :exclude_id';
            $params['exclude_id'] = $excludeUserId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{total_users: int, active_users: int, inactive_users: int}
     */
    public static function dashboardCounts(): array
    {
        $row = Database::connection()->query(
            "SELECT
                COUNT(*) AS total_users,
                COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) AS active_users,
                COALESCE(SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END), 0) AS inactive_users
             FROM users"
        )->fetch();

        return [
            'total_users' => (int) ($row['total_users'] ?? 0),
            'active_users' => (int) ($row['active_users'] ?? 0),
            'inactive_users' => (int) ($row['inactive_users'] ?? 0),
        ];
    }
}

