<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ProjectDocument
{
    private const BASE_SELECT = '
        SELECT
            pd.id,
            pd.project_id,
            pd.document_type,
            pd.version_number,
            pd.is_current,
            pd.original_filename,
            pd.stored_filename,
            pd.file_path,
            pd.mime_type,
            pd.file_size,
            pd.review_status,
            pd.upload_notes,
            pd.uploaded_by,
            pd.created_at,
            u.full_name AS uploader_name,
            u.username AS uploader_username
        FROM project_documents pd
        INNER JOIN users u ON u.id = pd.uploaded_by
    ';

    /**
     * All versions (current and historical) for a project, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function forProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . ' WHERE pd.project_id = :project_id ORDER BY pd.version_number DESC, pd.id DESC'
        );
        $stmt->execute(['project_id' => $projectId]);
        return $stmt->fetchAll();
    }

    /**
     * Only active current versions for a project.
     *
     * @return list<array<string, mixed>>
     */
    public static function currentForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . ' WHERE pd.project_id = :project_id AND pd.is_current = 1 ORDER BY pd.id DESC'
        );
        $stmt->execute(['project_id' => $projectId]);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . ' WHERE pd.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public static function nextVersionNumber(int $projectId, string $documentType): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(MAX(version_number), 0) + 1
             FROM project_documents
             WHERE project_id = :project_id AND document_type = :doc_type'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'doc_type' => $documentType,
        ]);

        return max(1, (int) $stmt->fetchColumn());
    }

    /**
     * Mark previous versions of the same document type as superseded while preserving their rows & files.
     */
    public static function markPreviousVersionsNotCurrent(int $projectId, string $documentType): int
    {
        $stmt = Database::connection()->prepare(
            "UPDATE project_documents
             SET is_current = 0,
                 review_status = 'superseded'
             WHERE project_id = :project_id
               AND document_type = :doc_type
               AND is_current = 1"
        );
        $stmt->execute([
            'project_id' => $projectId,
            'doc_type' => $documentType,
        ]);

        return $stmt->rowCount();
    }

    public static function markAllCurrentVerified(int $projectId, int $reviewerUserId): int
    {
        $stmt = Database::connection()->prepare(
            "UPDATE project_documents
             SET review_status = 'verified',
                 reviewed_by = :reviewer_id,
                 reviewed_at = NOW()
             WHERE project_id = :project_id
               AND is_current = 1"
        );
        $stmt->execute([
            'reviewer_id' => $reviewerUserId,
            'project_id' => $projectId,
        ]);

        return $stmt->rowCount();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO project_documents (
                project_id,
                document_type,
                version_number,
                is_current,
                original_filename,
                stored_filename,
                file_path,
                mime_type,
                file_size,
                review_status,
                upload_notes,
                uploaded_by
            ) VALUES (
                :project_id,
                :document_type,
                :version_number,
                :is_current,
                :original_filename,
                :stored_filename,
                :file_path,
                :mime_type,
                :file_size,
                :review_status,
                :upload_notes,
                :uploaded_by
            )'
        );

        $notes = isset($data['upload_notes']) && trim((string) $data['upload_notes']) !== ''
            ? trim((string) $data['upload_notes'])
            : null;

        $stmt->execute([
            'project_id' => $data['project_id'],
            'document_type' => $data['document_type'],
            'version_number' => $data['version_number'],
            'is_current' => isset($data['is_current']) ? (int) $data['is_current'] : 1,
            'original_filename' => $data['original_filename'],
            'stored_filename' => $data['stored_filename'],
            'file_path' => $data['file_path'],
            'mime_type' => $data['mime_type'],
            'file_size' => $data['file_size'],
            'review_status' => $data['review_status'] ?? 'uploaded',
            'upload_notes' => $notes,
            'uploaded_by' => $data['uploaded_by'],
        ]);

        return (int) $pdo->lastInsertId();
    }
}
