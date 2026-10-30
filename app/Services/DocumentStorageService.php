<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectWorkflowHistory;
use finfo;
use RuntimeException;
use Throwable;

final class DocumentStorageService
{
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'exe', 'dll', 'bat', 'cmd', 'com', 'msi', 'ps1', 'vbs', 'wsf',
        'js', 'mjs', 'cjs', 'jar', 'sh', 'bash', 'cgi', 'pl', 'py',
        'asp', 'aspx', 'jsp', 'htaccess', 'htpasswd', 'ini', 'config',
        'svg', 'html', 'htm', 'xhtml', 'scr', 'reg',
    ];

    /**
     * Validate and store an uploaded or replacement project document outside the web root.
     * Preserves all previous versions in `project_documents` and `storage/documents` for auditability.
     *
     * @param array<string, mixed> $project
     * @param array<string, mixed>|null $file
     * @param array<string, mixed> $actor
     * @return array{success: bool, error?: string, document_id?: int, version_number?: int, replaced?: bool}
     */
    public static function storeUploadedDocument(
        array $project,
        ?array $file,
        string $documentType,
        array $actor,
        Request $request,
        string $uploadNotes = '',
        bool $allowLocalTestFile = false
    ): array {
        $allowedCategories = (array) Config::get('app.uploads.document_categories', []);
        $checklistTypes = MarketingChecklistService::allChecklistTypes((string) ($project['workflow_type'] ?? 'standard'));
        $documentType = trim($documentType);

        if (
            $documentType === ''
            || (!array_key_exists($documentType, $allowedCategories) && !in_array($documentType, $checklistTypes, true))
        ) {
            return ['success' => false, 'error' => 'Please select a valid Marketing checklist document type.'];
        }

        $uploadNotes = trim($uploadNotes);
        if (mb_strlen($uploadNotes, 'UTF-8') > 255) {
            return ['success' => false, 'error' => 'Document note must not exceed 255 characters.'];
        }

        if ($file === null || !isset($file['error'])) {
            return ['success' => false, 'error' => 'Please choose a file to upload.'];
        }

        $uploadError = (int) $file['error'];
        if ($uploadError !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => self::uploadErrorMessage($uploadError)];
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return ['success' => false, 'error' => 'Uploaded temporary file could not be read.'];
        }

        if (!$allowLocalTestFile && !is_uploaded_file($tmpPath)) {
            return ['success' => false, 'error' => 'Invalid file upload source rejected.'];
        }

        $actualSize = (int) filesize($tmpPath);
        $maxBytes = (int) Config::get('app.uploads.max_size_bytes', 10 * 1024 * 1024);

        if ($actualSize <= 0) {
            return ['success' => false, 'error' => 'Uploaded file is empty.'];
        }

        if ($actualSize > $maxBytes) {
            $maxMb = round($maxBytes / 1048576, 1);
            return ['success' => false, 'error' => "File exceeds the maximum permitted size of {$maxMb} MB."];
        }

        $rawClientName = (string) ($file['name'] ?? '');
        $sanitizedDisplayName = self::sanitizeDisplayFilename($rawClientName);
        if ($sanitizedDisplayName === '') {
            return ['success' => false, 'error' => 'Invalid filename provided.'];
        }

        // Reject double extensions containing executable segments (e.g. proposal.php.pdf)
        if (self::containsBlockedExtensionSegment($sanitizedDisplayName)) {
            ActivityLogger::log(
                'security.blocked_upload',
                'project',
                (int) $project['id'],
                "Blocked dangerous file upload attempt ('{$sanitizedDisplayName}') on project '{$project['project_code']}'.",
                $request,
                (int) $actor['id'],
                false
            );
            return ['success' => false, 'error' => 'Executable or unsafe file types are strictly prohibited.'];
        }

        $extension = strtolower((string) pathinfo($sanitizedDisplayName, PATHINFO_EXTENSION));
        /** @var array<string, list<string>> $allowedTypes */
        $allowedTypes = (array) Config::get('app.uploads.allowed_types', []);

        if ($extension === '' || !isset($allowedTypes[$extension])) {
            $allowedList = strtoupper(implode(', ', array_keys($allowedTypes)));
            return ['success' => false, 'error' => "File type is not permitted. Allowed formats: {$allowedList}."];
        }

        // Inspect actual server-side file content using finfo (never trust client MIME header)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) ($finfo->file($tmpPath) ?: '');

        if ($detectedMime === '' || !in_array($detectedMime, $allowedTypes[$extension], true)) {
            ActivityLogger::log(
                'security.mime_mismatch_upload',
                'project',
                (int) $project['id'],
                "Rejected upload '{$sanitizedDisplayName}' due to MIME mismatch (detected: '{$detectedMime}').",
                $request,
                (int) $actor['id'],
                false
            );
            return ['success' => false, 'error' => 'File content does not match its declared file extension.'];
        }

        $documentsRoot = rtrim((string) Config::get('app.paths.documents', BASE_PATH . '/storage/documents'), '/\\');
        $subDir = date('Y') . '/' . date('m');
        $targetDir = $documentsRoot . '/' . $subDir;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Unable to initialize document storage directory.');
        }

        $projectId = (int) $project['id'];
        $storedFilename = sprintf(
            'proj_%d_%s_%s.%s',
            $projectId,
            date('YmdHis'),
            bin2hex(random_bytes(16)),
            $extension
        );

        $relativePath = $subDir . '/' . $storedFilename;
        $destinationPath = $targetDir . '/' . $storedFilename;

        $moved = $allowLocalTestFile
            ? copy($tmpPath, $destinationPath)
            : move_uploaded_file($tmpPath, $destinationPath);

        if (!$moved || !is_file($destinationPath)) {
            throw new RuntimeException('Failed to write uploaded document to secure storage.');
        }

        $actorId = (int) $actor['id'];
        $isChecklistDoc = in_array($documentType, $checklistTypes, true);

        try {
            $outcome = Database::transaction(static function () use (
                $projectId,
                $project,
                $documentType,
                $isChecklistDoc,
                $sanitizedDisplayName,
                $storedFilename,
                $relativePath,
                $detectedMime,
                $actualSize,
                $uploadNotes,
                $actorId,
                $request
            ): array {
                $version = ProjectDocument::nextVersionNumber($projectId, $documentType);
                $supersededCount = 0;

                if ($isChecklistDoc && $version > 1) {
                    $supersededCount = ProjectDocument::markPreviousVersionsNotCurrent($projectId, $documentType);
                }

                $id = ProjectDocument::create([
                    'project_id' => $projectId,
                    'document_type' => $documentType,
                    'version_number' => $version,
                    'is_current' => 1,
                    'original_filename' => $sanitizedDisplayName,
                    'stored_filename' => $storedFilename,
                    'file_path' => $relativePath,
                    'mime_type' => $detectedMime,
                    'file_size' => $actualSize,
                    'review_status' => 'uploaded',
                    'upload_notes' => $uploadNotes,
                    'uploaded_by' => $actorId,
                ]);

                // Automatically move a Draft (or unverified legacy Submitted) project into Preparing Documents upon first checklist upload
                $currProjStatus = (string) ($project['status'] ?? '');
                $isLegacyUnverified = $currProjStatus === Project::STATUS_SUBMITTED
                    && ((string) ($project['verification_status'] ?? Project::VERIFICATION_NOT_SUBMITTED)) === Project::VERIFICATION_NOT_SUBMITTED;

                if ($isChecklistDoc && ($currProjStatus === Project::STATUS_DRAFT || $isLegacyUnverified)) {
                    Project::updateStatus($projectId, Project::STATUS_PREPARING_DOCUMENTS);
                    ProjectWorkflowHistory::record([
                        'project_id' => $projectId,
                        'from_status' => $currProjStatus,
                        'to_status' => Project::STATUS_PREPARING_DOCUMENTS,
                        'action' => 'started_document_prep',
                        'from_department_id' => $project['current_department_id'],
                        'to_department_id' => $project['current_department_id'],
                        'acted_by' => $actorId,
                        'notes' => "Document preparation initiated via '{$documentType}' upload.",
                    ]);
                }

                $wasReplaced = $supersededCount > 0 || ($isChecklistDoc && $version > 1);
                if ($wasReplaced) {
                    ActivityLogger::log(
                        'document.replaced',
                        'project',
                        $projectId,
                        "Replaced '{$documentType}' with '{$sanitizedDisplayName}' (v{$version}; prior version preserved) on project '{$project['project_code']}'.",
                        $request,
                        $actorId,
                        false
                    );
                } else {
                    ActivityLogger::log(
                        'document.uploaded',
                        'project',
                        $projectId,
                        "Uploaded '{$sanitizedDisplayName}' for '{$documentType}' (v{$version}) on project '{$project['project_code']}'.",
                        $request,
                        $actorId,
                        false
                    );
                }

                return [
                    'document_id' => $id,
                    'version_number' => $version,
                    'replaced' => $wasReplaced,
                ];
            });
        } catch (Throwable $e) {
            @unlink($destinationPath);
            throw $e;
        }

        return [
            'success' => true,
            'document_id' => $outcome['document_id'],
            'version_number' => $outcome['version_number'],
            'replaced' => $outcome['replaced'],
        ];
    }

    /**
     * Resolve and verify the absolute path of a stored document inside storage/documents.
     *
     * @param array<string, mixed> $document
     */
    public static function resolveVerifiedPath(array $document): ?string
    {
        $documentsRoot = realpath((string) Config::get('app.paths.documents', BASE_PATH . '/storage/documents'));
        if ($documentsRoot === false) {
            return null;
        }

        $relativePath = ltrim(str_replace('\\', '/', (string) ($document['file_path'] ?? '')), '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        $candidate = realpath($documentsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if ($candidate === false || !is_file($candidate)) {
            return null;
        }

        // Ensure resolved path is strictly inside storage/documents
        $normalizedRoot = rtrim(str_replace('\\', '/', $documentsRoot), '/') . '/';
        $normalizedFile = str_replace('\\', '/', $candidate);

        if (!str_starts_with($normalizedFile, $normalizedRoot)) {
            return null;
        }

        return $candidate;
    }

    private static function sanitizeDisplayFilename(string $rawName): string
    {
        $normalized = str_replace('\\', '/', $rawName);
        $base = basename($normalized);
        $base = preg_replace('/[\x00-\x1F\x7F]/u', '', $base) ?? '';
        $base = trim($base, " .\t\n\r\0\x0B");

        if (mb_strlen($base, 'UTF-8') > 240) {
            $ext = pathinfo($base, PATHINFO_EXTENSION);
            $stem = mb_substr(pathinfo($base, PATHINFO_FILENAME), 0, 200, 'UTF-8');
            $base = $ext !== '' ? "{$stem}.{$ext}" : $stem;
        }

        return $base;
    }

    private static function containsBlockedExtensionSegment(string $filename): bool
    {
        $parts = explode('.', strtolower($filename));
        if (count($parts) < 2) {
            return true;
        }

        array_shift($parts);
        foreach ($parts as $segment) {
            if (in_array(trim($segment), self::BLOCKED_EXTENSIONS, true)) {
                return true;
            }
        }

        return false;
    }

    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the maximum allowed upload size.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Please select a file before uploading.',
            default => 'File upload failed due to a server error.',
        };
    }
}
