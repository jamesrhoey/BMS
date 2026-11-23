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
            pd.correction_notes,
            pd.verified_by,
            pd.verified_at,
            pd.reviewed_by,
            pd.reviewed_at,
            pd.uploaded_by,
            pd.created_at,
            u.full_name AS uploader_name,
            u.username AS uploader_username,
            v.full_name AS verifier_name,
            v.username AS verifier_username
        FROM project_documents pd
        INNER JOIN users u ON u.id = pd.uploaded_by
        LEFT JOIN users v ON v.id = pd.verified_by
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

    /**
     * Find the active current document of a specific type for a project (e.g. 'SLD', 'BOM').
     *
     * @return array<string, mixed>|null
     */
    public static function findCurrentByType(int $projectId, string $documentType): ?array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . ' WHERE pd.project_id = :project_id AND pd.document_type = :doc_type AND pd.is_current = 1 ORDER BY pd.version_number DESC, pd.id DESC LIMIT 1'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'doc_type' => $documentType,
        ]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Retrieve the required Operations handoff documents (SLD and Marketing BOM) for a project.
     * Note: 'BOM' in `project_documents` is the Marketing BOM (distinct from Actual BOM).
     *
     * @return array{sld: array<string, mixed>|null, marketing_bom: array<string, mixed>|null, is_ready: bool, missing: list<string>}
     */
    public static function operationsHandoffDocuments(int $projectId): array
    {
        $sld = self::findCurrentByType($projectId, 'SLD');
        $marketingBom = self::findCurrentByType($projectId, 'BOM');

        $missing = [];
        if ($sld === null) {
            $missing[] = 'SLD (Single Line Diagram)';
        }
        if ($marketingBom === null) {
            $missing[] = 'Marketing BOM (Bill of Materials)';
        }

        return [
            'sld' => $sld,
            'marketing_bom' => $marketingBom,
            'is_ready' => $sld !== null && $marketingBom !== null,
            'missing' => $missing,
        ];
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

    /**
     * Find the active current JOM file for a project.
     *
     * @return array<string, mixed>|null
     */
    public static function findCurrentJom(int $projectId): ?array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . " WHERE pd.project_id = :project_id AND pd.document_type IN ('JOM', 'JOM — Job Order Memorandum') AND pd.is_current = 1 ORDER BY pd.version_number DESC, pd.id DESC LIMIT 1"
        );
        $stmt->execute(['project_id' => $projectId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Find the active current Final SLD file for a project (separate from Marketing SLD).
     *
     * @return array<string, mixed>|null
     */
    public static function findCurrentFinalSld(int $projectId): ?array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . " WHERE pd.project_id = :project_id AND pd.document_type IN ('FINAL_SLD', 'Final SLD', 'Final SLD — Single Line Diagram', 'Final Single Line Diagram') AND pd.is_current = 1 ORDER BY pd.version_number DESC, pd.id DESC LIMIT 1"
        );
        $stmt->execute(['project_id' => $projectId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Find the active current Final BOM file for a project (separate from Marketing BOM).
     *
     * @return array<string, mixed>|null
     */
    public static function findCurrentFinalBom(int $projectId): ?array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . " WHERE pd.project_id = :project_id AND pd.document_type IN ('FINAL_BOM', 'Final BOM', 'Final BOM — Bill of Materials', 'Final Bill of Materials') AND pd.is_current = 1 ORDER BY pd.version_number DESC, pd.id DESC LIMIT 1"
        );
        $stmt->execute(['project_id' => $projectId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Find the active current Request of PR file for a project.
     *
     * @return array<string, mixed>|null
     */
    public static function findCurrentRequestPr(int $projectId): ?array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . " WHERE pd.project_id = :project_id AND pd.document_type IN ('REQUEST_OF_PR', 'Request of PR', 'Request of PR — Purchase Requisition Request', 'Request of Purchase Requisition') AND pd.is_current = 1 ORDER BY pd.version_number DESC, pd.id DESC LIMIT 1"
        );
        $stmt->execute(['project_id' => $projectId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Evaluate OPS-uploaded documents status.
     *
     * @return array{
     *   final_sld: array<string, mixed>|null,
     *   final_bom: array<string, mixed>|null,
     *   request_of_pr: array<string, mixed>|null,
     *   is_complete: bool,
     *   missing: list<string>
     * }
     */
    public static function opsUploadedDocuments(int $projectId): array
    {
        $finalSld = self::findCurrentFinalSld($projectId);
        $finalBom = self::findCurrentFinalBom($projectId);
        $requestPr = self::findCurrentRequestPr($projectId);

        $missing = [];
        if ($finalSld === null) {
            $missing[] = 'FINAL SLD';
        }
        if ($finalBom === null) {
            $missing[] = 'FINAL BOM';
        }
        if ($requestPr === null) {
            $missing[] = 'REQUEST OF PR';
        }

        return [
            'final_sld' => $finalSld,
            'final_bom' => $finalBom,
            'request_of_pr' => $requestPr,
            'is_complete' => $missing === [],
            'missing' => $missing,
        ];
    }

    /**
     * Update review status and correction notes for a specific document.
     */
    public static function updateReviewStatus(
        int $docId,
        string $status,
        ?string $correctionNotes = null,
        ?int $verifiedByUserId = null
    ): bool {
        $stmt = Database::connection()->prepare(
            'UPDATE project_documents
             SET review_status = :status,
                 correction_notes = :notes,
                 verified_by = :verified_by,
                 verified_at = :verified_at,
                 reviewed_by = :reviewed_by,
                 reviewed_at = NOW()
             WHERE id = :id'
        );

        $isVerified = ($status === 'verified');
        return $stmt->execute([
            'status' => $status,
            'notes' => $correctionNotes !== null && trim($correctionNotes) !== '' ? trim($correctionNotes) : null,
            'verified_by' => $isVerified ? $verifiedByUserId : null,
            'verified_at' => $isVerified ? date('Y-m-d H:i:s') : null,
            'reviewed_by' => $verifiedByUserId,
            'id' => $docId,
        ]);
    }

    /**
     * Reset any documents marked as needs_correction back to pending_review upon resubmission.
     */
    public static function resetCorrectionToPending(int $projectId): int
    {
        $stmt = Database::connection()->prepare(
            "UPDATE project_documents
             SET review_status = 'pending_review'
             WHERE project_id = :project_id
               AND is_current = 1
               AND review_status = 'needs_correction'"
        );
        $stmt->execute(['project_id' => $projectId]);
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
