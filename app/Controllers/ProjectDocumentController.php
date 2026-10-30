<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Services\ActivityLogger;
use App\Services\AuthorizationService;
use App\Services\DocumentStorageService;

final class ProjectDocumentController extends Controller
{
    /**
     * Upload or replace a project document with server-side content validation and version preservation.
     *
     * @param array<string, string> $params
     */
    public function upload(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canUploadDocument($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to upload or replace a document on project '{$project['project_code']}' without authorization or during locked workflow status '{$project['status']}'."
            );
        }

        $projectId = (int) $project['id'];
        $documentType = (string) $request->post('document_type', '');
        $uploadNotes = (string) $request->post('upload_notes', '');
        $file = $request->file('document');

        $result = DocumentStorageService::storeUploadedDocument(
            $project,
            $file,
            $documentType,
            $user,
            $request,
            $uploadNotes
        );

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Document upload failed.');
            Response::redirect('/projects/' . $projectId . '#checklist');
        }

        $ver = (int) ($result['version_number'] ?? 1);
        if (!empty($result['replaced'])) {
            Session::flash(
                'success',
                "Replaced '{$documentType}' with version v{$ver} (previous version preserved in document history)."
            );
        } else {
            Session::flash('success', "Uploaded '{$documentType}' (v{$ver}) to project checklist.");
        }

        Response::redirect('/projects/' . $projectId . '#checklist');
    }

    /**
     * Stream an authorized download of a current or historical project document.
     *
     * @param array<string, string> $params
     */
    public function download(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canDownloadDocument($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to download a document from unauthorized project '{$project['project_code']}'."
            );
        }

        $docId = isset($params['docId']) && ctype_digit($params['docId']) ? (int) $params['docId'] : 0;
        $document = $docId > 0 ? ProjectDocument::findById($docId) : null;

        if ($document === null || (int) $document['project_id'] !== (int) $project['id']) {
            $this->notFound('The requested project document could not be found.');
        }

        $verifiedPath = DocumentStorageService::resolveVerifiedPath($document);
        if ($verifiedPath === null) {
            $this->notFound('The stored document file is unavailable on disk.');
        }

        ActivityLogger::log(
            'document.downloaded',
            'project',
            (int) $project['id'],
            "Downloaded '{$document['original_filename']}' ({$document['document_type']}, v{$document['version_number']}) from project '{$project['project_code']}'.",
            $request
        );

        Response::download(
            $verifiedPath,
            (string) $document['original_filename'],
            (string) $document['mime_type']
        );
    }

    /**
     * Stream the raw binary file with Content-Disposition: inline.
     * Used by embedded viewers (iframe, fetch, embed).
     *
     * @param array<string, string> $params
     */
    public function raw(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canDownloadDocument($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to access document from unauthorized project '{$project['project_code']}'."
            );
        }

        $docId = isset($params['docId']) && ctype_digit($params['docId']) ? (int) $params['docId'] : 0;
        $document = $docId > 0 ? ProjectDocument::findById($docId) : null;

        if ($document === null || (int) $document['project_id'] !== (int) $project['id']) {
            $this->notFound('The requested project document could not be found.');
        }

        $verifiedPath = DocumentStorageService::resolveVerifiedPath($document);
        if ($verifiedPath === null) {
            $this->notFound('The stored document file is unavailable on disk.');
        }

        Response::streamInline(
            $verifiedPath,
            (string) $document['original_filename'],
            (string) $document['mime_type']
        );
    }

    /**
     * View document in interactive In-App Viewer or stream inline.
     * When requested by a web browser (Accept: text/html), renders an in-app viewer
     * capable of displaying Excel spreadsheets (.xlsx, .xls), PDFs, images, and text.
     * When requested as raw/binary (Accept: other or ?raw=1), streams file inline.
     *
     * @param array<string, string> $params
     */
    public function view(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $project = $this->loadProjectOrFail($params);

        if (!AuthorizationService::canDownloadDocument($user, $project)) {
            $this->denyAccess(
                $request,
                "User '{$user['username']}' attempted to view a document from unauthorized project '{$project['project_code']}'."
            );
        }

        $docId = isset($params['docId']) && ctype_digit($params['docId']) ? (int) $params['docId'] : 0;
        $document = $docId > 0 ? ProjectDocument::findById($docId) : null;

        if ($document === null || (int) $document['project_id'] !== (int) $project['id']) {
            $this->notFound('The requested project document could not be found.');
        }

        $verifiedPath = DocumentStorageService::resolveVerifiedPath($document);
        if ($verifiedPath === null) {
            $this->notFound('The stored document file is unavailable on disk.');
        }

        ActivityLogger::log(
            'document.viewed',
            'project',
            (int) $project['id'],
            "Viewed '{$document['original_filename']}' ({$document['document_type']}, v{$document['version_number']}) from project '{$project['project_code']}'.",
            $request
        );

        $forceRaw = $request->query('raw') === '1';

        // If client explicitly requests raw binary stream via ?raw=1, stream directly
        if ($forceRaw) {
            Response::streamInline(
                $verifiedPath,
                (string) $document['original_filename'],
                (string) $document['mime_type']
            );
        }

        $ext = strtolower(pathinfo((string) $document['original_filename'], PATHINFO_EXTENSION));
        $mime = (string) $document['mime_type'];

        $fileCategory = match (true) {
            in_array($ext, ['xlsx', 'xls', 'csv'], true) || str_contains($mime, 'spreadsheet') || str_contains($mime, 'excel') => 'excel',
            $ext === 'pdf' || $mime === 'application/pdf' => 'pdf',
            in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true) || str_starts_with($mime, 'image/') => 'image',
            in_array($ext, ['docx', 'doc'], true) || str_contains($mime, 'word') => 'word',
            in_array($ext, ['txt', 'json', 'log'], true) || str_starts_with($mime, 'text/') => 'text',
            default => 'other',
        };

        $this->render('projects/document_viewer', [
            'pageTitle' => 'Viewing: ' . $document['original_filename'] . ' — ' . $project['project_code'],
            'project' => $project,
            'document' => $document,
            'fileCategory' => $fileCategory,
            'fileExtension' => $ext,
            'rawUrl' => url('/projects/' . $project['id'] . '/documents/' . $docId . '/raw'),
            'downloadUrl' => url('/projects/' . $project['id'] . '/documents/' . $docId . '/download'),
            'backUrl' => url('/projects/' . $project['id'] . '#checklist'),
        ]);
    }

    /**
     * @param array<string, string> $params
     * @return array<string, mixed>
     */
    private function loadProjectOrFail(array $params): array
    {
        $id = isset($params['id']) && ctype_digit($params['id']) ? (int) $params['id'] : 0;
        $project = $id > 0 ? Project::findById($id) : null;

        if ($project === null) {
            $this->notFound('The requested project record could not be found.');
        }

        return $project;
    }
}

