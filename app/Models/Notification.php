<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Notification
{
    public static function notifyDepartmentUsers(
        int $departmentId,
        ?int $projectId,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null
    ): int {
        $pdo = Database::connection();

        $userStmt = $pdo->prepare(
            'SELECT id FROM users WHERE department_id = :dept_id AND status = :status'
        );
        $userStmt->execute([
            'dept_id' => $departmentId,
            'status' => User::STATUS_ACTIVE,
        ]);
        /** @var list<int> $recipientIds */
        $recipientIds = $userStmt->fetchAll(PDO::FETCH_COLUMN);

        if ($recipientIds === []) {
            return 0;
        }

        $insertStmt = $pdo->prepare(
            'INSERT INTO notifications (
                recipient_user_id,
                project_id,
                type,
                title,
                message,
                action_url,
                is_read
            ) VALUES (
                :recipient_user_id,
                :project_id,
                :type,
                :title,
                :message,
                :action_url,
                0
            )'
        );

        $count = 0;
        foreach ($recipientIds as $recipientId) {
            $insertStmt->execute([
                'recipient_user_id' => (int) $recipientId,
                'project_id' => $projectId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_url' => $actionUrl,
            ]);
            $count++;
        }

        return $count;
    }

    public static function unreadCountForUser(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE recipient_user_id = :user_id AND is_read = 0'
        );
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(int $userId, int $limit = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                n.id,
                n.recipient_user_id,
                n.project_id,
                n.type,
                n.title,
                n.message,
                n.action_url,
                n.is_read,
                n.read_at,
                n.created_at,
                p.project_code,
                p.project_name
             FROM notifications n
             LEFT JOIN projects p ON p.id = n.project_id
             WHERE n.recipient_user_id = :user_id
             ORDER BY n.id DESC
             LIMIT :limit_rows'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit_rows', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function markAsRead(int $notificationId, int $userId): ?array
    {
        $pdo = Database::connection();
        $findStmt = $pdo->prepare(
            'SELECT id, recipient_user_id, project_id, action_url, is_read
             FROM notifications
             WHERE id = :id AND recipient_user_id = :user_id
             LIMIT 1'
        );
        $findStmt->execute([
            'id' => $notificationId,
            'user_id' => $userId,
        ]);
        $row = $findStmt->fetch();
        if ($row === false) {
            return null;
        }

        if ((int) $row['is_read'] === 0) {
            $upd = $pdo->prepare(
                'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id AND recipient_user_id = :user_id'
            );
            $upd->execute([
                'id' => $notificationId,
                'user_id' => $userId,
            ]);
        }

        return $row;
    }

    public static function markAllAsRead(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE recipient_user_id = :user_id AND is_read = 0'
        );
        $stmt->execute(['user_id' => $userId]);
    }
}

