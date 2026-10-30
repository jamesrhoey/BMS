<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ProjectWorkflowHistory
{
    /**
     * @param array<string, mixed> $data
     */
    public static function record(array $data): int
    {
        $pdo = Database::connection();
        $flaggedJson = null;
        if (!empty($data['flagged_documents']) && is_array($data['flagged_documents'])) {
            $flaggedJson = json_encode(array_values($data['flagged_documents']), JSON_UNESCAPED_UNICODE);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO project_workflow_histories (
                project_id,
                from_status,
                to_status,
                action,
                from_department_id,
                to_department_id,
                acted_by,
                notes,
                flagged_documents
            ) VALUES (
                :project_id,
                :from_status,
                :to_status,
                :action,
                :from_department_id,
                :to_department_id,
                :acted_by,
                :notes,
                :flagged_documents
            )'
        );

        $stmt->execute([
            'project_id' => $data['project_id'],
            'from_status' => $data['from_status'] ?? null,
            'to_status' => $data['to_status'],
            'action' => $data['action'],
            'from_department_id' => $data['from_department_id'] ?? null,
            'to_department_id' => $data['to_department_id'] ?? null,
            'acted_by' => $data['acted_by'],
            'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
            'flagged_documents' => $flaggedJson,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                pwh.id,
                pwh.project_id,
                pwh.from_status,
                pwh.to_status,
                pwh.action,
                pwh.from_department_id,
                pwh.to_department_id,
                pwh.acted_by,
                pwh.notes,
                pwh.flagged_documents,
                pwh.created_at,
                u.full_name AS actor_name,
                u.username AS actor_username,
                df.department_name AS from_department_name,
                df.department_code AS from_department_code,
                dt.department_name AS to_department_name,
                dt.department_code AS to_department_code
             FROM project_workflow_histories pwh
             INNER JOIN users u ON u.id = pwh.acted_by
             LEFT JOIN departments df ON df.id = pwh.from_department_id
             LEFT JOIN departments dt ON dt.id = pwh.to_department_id
             WHERE pwh.project_id = :project_id
             ORDER BY pwh.id DESC'
        );
        $stmt->execute(['project_id' => $projectId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $decoded = is_string($row['flagged_documents']) && $row['flagged_documents'] !== ''
                ? json_decode($row['flagged_documents'], true)
                : [];
            $row['flagged_documents_list'] = is_array($decoded) ? $decoded : [];
        }
        unset($row);

        return $rows;
    }
}

