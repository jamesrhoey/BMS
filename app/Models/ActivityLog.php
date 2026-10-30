<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Immutable audit log model.
 * Intentionally exposes only insert and read operations; entries cannot be edited or deleted.
 */
final class ActivityLog
{
    public static function record(
        ?int $userId,
        string $action,
        string $entityType,
        ?int $entityId,
        string $description,
        ?string $ipAddress
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address)
             VALUES (:user_id, :action, :entity_type, :entity_id, :description, :ip_address)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                al.id,
                al.user_id,
                al.action,
                al.entity_type,
                al.entity_id,
                al.description,
                al.ip_address,
                al.created_at,
                u.username,
                u.full_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.id DESC
             LIMIT :limit_rows'
        );
        $stmt->bindValue(':limit_rows', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forEntity(string $entityType, int $entityId, int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                al.id,
                al.user_id,
                al.action,
                al.entity_type,
                al.entity_id,
                al.description,
                al.ip_address,
                al.created_at,
                u.username,
                u.full_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.entity_type = :entity_type AND al.entity_id = :entity_id
             ORDER BY al.id DESC
             LIMIT :limit_rows'
        );
        $stmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $stmt->bindValue(':limit_rows', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $conditions = [];
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(al.description LIKE :q1 OR al.action LIKE :q2 OR u.username LIKE :q3 OR u.full_name LIKE :q4)';
            $like = '%' . $search . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $entityType = trim((string) ($filters['entity_type'] ?? ''));
        if ($entityType !== '') {
            $conditions[] = 'al.entity_type = :entity_type';
            $params['entity_type'] = $entityType;
        }

        $whereClause = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';

        $countSql = 'SELECT COUNT(*)
                     FROM activity_logs al
                     LEFT JOIN users u ON u.id = al.user_id' . $whereClause;
        $countStmt = Database::connection()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $dataSql = 'SELECT
                        al.id,
                        al.user_id,
                        al.action,
                        al.entity_type,
                        al.entity_id,
                        al.description,
                        al.ip_address,
                        al.created_at,
                        u.username,
                        u.full_name
                    FROM activity_logs al
                    LEFT JOIN users u ON u.id = al.user_id'
                    . $whereClause
                    . ' ORDER BY al.id DESC LIMIT :limit_rows OFFSET :offset_rows';

        $stmt = Database::connection()->prepare($dataSql);
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

    public static function countRecentFailedLogins(?string $ipAddress, int $windowMinutes = 10): int
    {
        if ($ipAddress === null || $ipAddress === '') {
            return 0;
        }

        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM activity_logs
             WHERE action IN ('auth.login_failed', 'auth.login_blocked_inactive')
               AND ip_address = :ip
               AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)"
        );
        $stmt->bindValue(':ip', $ipAddress, PDO::PARAM_STR);
        $stmt->bindValue(':minutes', $windowMinutes, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}

