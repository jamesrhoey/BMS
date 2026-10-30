<div class="container-fluid px-0 py-2" id="bms-document-viewer-page"
     data-document-viewer="1"
     data-category="<?= e($fileCategory) ?>"
     data-raw-url="<?= e($rawUrl) ?>"
     data-download-url="<?= e($downloadUrl) ?>"
     data-filename="<?= e($document['original_filename']) ?>">

    <!-- Top Document Viewer Toolbar -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
                        <span>&larr;</span>
                        <span>Back to Project</span>
                    </a>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="mb-0 fw-bold text-dark text-break"><?= e($document['original_filename']) ?></h5>
                            <span class="badge bg-primary"><?= e($document['document_type']) ?></span>
                            <span class="badge badge-soft-secondary">v<?= (int) $document['version_number'] ?></span>
                            <?php if (!empty($document['is_current'])): ?>
                                <span class="badge bg-success">Current Version</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Superseded / History</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-secondary small mt-1">
                            <span>Project: <strong><?= e($project['project_code']) ?></strong> (<?= e($project['project_name']) ?>)</span>
                            <span class="mx-1">&bull;</span>
                            <span>Size: <strong><?= format_bytes((int) $document['file_size']) ?></strong></span>
                            <span class="mx-1">&bull;</span>
                            <span>Uploaded: <?= format_datetime($document['created_at']) ?> by <?= e($document['uploader_name'] ?? 'Authorized User') ?></span>
                            <?php if (!empty($project['jom_number'])): ?>
                                <span class="mx-1">&bull;</span>
                                <span class="badge bg-primary">JOM: <?= e($project['jom_number']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <a href="<?= e($downloadUrl) ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" title="Download copy to your device">
                        <?= bms_icon('download') ?>
                        <span>Download</span>
                    </a>
                    <a href="<?= e($rawUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" title="Open raw file directly in a new browser window">
                        <span>Open Raw &nearr;</span>
                    </a>
                </div>
            </div>

            <?php if (!empty($document['upload_notes'])): ?>
                <div class="mt-2 pt-2 border-top small text-secondary">
                    <strong class="text-dark">Upload Note:</strong> <?= e($document['upload_notes']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Viewer Canvas Container -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-2 p-md-3">

            <?php if ($fileCategory === 'pdf'): ?>
                <!-- Native Browser PDF Viewer (inline stream via iframe / object) -->
                <div class="bms-pdf-viewer-wrap" style="height: calc(100vh - 200px); min-height: 650px;">
                    <iframe src="<?= e($rawUrl) ?>#toolbar=1&navpanes=1"
                            class="w-100 h-100 border-0 rounded"
                            title="PDF Viewer — <?= e($document['original_filename']) ?>">
                    </iframe>
                </div>

            <?php elseif ($fileCategory === 'excel'): ?>
                <!-- Interactive SheetJS Excel Spreadsheet Viewer -->
                <div id="excel-viewer-container">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-2 bg-light rounded border mb-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="small fw-bold text-secondary text-uppercase tracking-wider">Sheets:</span>
                            <div id="excel-sheet-tabs" class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Worksheets"></div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input type="text"
                                   id="excel-search-input"
                                   class="form-control form-control-sm"
                                   placeholder="Search sheet data..."
                                   style="max-width: 260px;"
                                   aria-label="Filter sheet table rows">
                            <span id="excel-row-count" class="badge badge-soft-secondary small"></span>
                        </div>
                    </div>

                    <div id="excel-loading-spinner" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading spreadsheet...</span>
                        </div>
                        <div class="mt-2 text-secondary fw-medium">Loading and rendering spreadsheet data...</div>
                    </div>

                    <div id="excel-error-message" class="alert alert-warning d-none" role="alert">
                        <strong>Preview Note:</strong> Unable to render spreadsheet preview.
                        <a href="<?= e($downloadUrl) ?>" class="alert-link">Click here to download and view in Microsoft Excel or your spreadsheet app</a>.
                    </div>

                    <div id="excel-table-scroll" class="table-responsive bg-white border rounded shadow-sm d-none"
                         style="max-height: calc(100vh - 250px); min-height: 480px; overflow: auto;">
                        <table id="excel-render-table" class="table table-sm table-bordered table-hover table-striped align-middle mb-0"></table>
                    </div>
                </div>

            <?php elseif ($fileCategory === 'image'): ?>
                <!-- High-Resolution Image Viewer -->
                <div class="text-center p-3 p-md-4 bg-light rounded border"
                     style="min-height: 450px; max-height: calc(100vh - 200px); overflow: auto;">
                    <img src="<?= e($rawUrl) ?>"
                         alt="<?= e($document['original_filename']) ?>"
                         class="img-fluid rounded shadow-sm"
                         style="max-height: calc(100vh - 250px); object-fit: contain; cursor: zoom-in;"
                         id="bms-preview-image">
                </div>

            <?php elseif ($fileCategory === 'word'): ?>
                <!-- Word Document (.docx) Viewer -->
                <div id="word-viewer-container">
                    <div id="word-loading-spinner" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading Word document...</span>
                        </div>
                        <div class="mt-2 text-secondary fw-medium">Parsing and formatting Word document...</div>
                    </div>

                    <div id="word-error-message" class="alert alert-warning d-none" role="alert">
                        <strong>Preview Note:</strong> Unable to render Word document preview.
                        <a href="<?= e($downloadUrl) ?>" class="alert-link">Click here to download and view in Microsoft Word</a>.
                    </div>

                    <div id="word-content-area"
                         class="bg-white p-4 p-md-5 border rounded shadow-sm d-none"
                         style="max-height: calc(100vh - 230px); min-height: 500px; overflow-y: auto; line-height: 1.7;">
                    </div>
                </div>

            <?php elseif ($fileCategory === 'text'): ?>
                <!-- Plain Text / CSV / Log Viewer -->
                <div style="height: calc(100vh - 200px); min-height: 500px;">
                    <iframe src="<?= e($rawUrl) ?>"
                            class="w-100 h-100 border rounded bg-white"
                            title="Text File Viewer">
                    </iframe>
                </div>

            <?php else: ?>
                <!-- Fallback Info Card for Unsupported Binary Formats -->
                <div class="text-center py-5">
                    <div class="mb-3 text-secondary">
                        <?= bms_icon('document', 'w-16 h-16') ?>
                    </div>
                    <h5 class="fw-bold text-dark">File Preview Not Available for this Format</h5>
                    <p class="text-secondary small mb-3">
                        This file format (<code>.<?= e($fileExtension) ?></code>) cannot be rendered directly in the browser canvas.
                    </p>
                    <a href="<?= e($downloadUrl) ?>" class="btn btn-primary d-inline-flex align-items-center gap-1">
                        <?= bms_icon('download') ?>
                        <span>Download <?= e($document['original_filename']) ?></span>
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php if ($fileCategory === 'excel'): ?>
    <script src="<?= asset('js/vendor/xlsx.full.min.js') ?>"></script>
<?php elseif ($fileCategory === 'word'): ?>
    <script src="<?= asset('js/vendor/mammoth.browser.min.js') ?>"></script>
<?php endif; ?>
<script src="<?= asset('js/document-viewer.js') ?>"></script>

