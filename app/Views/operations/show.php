<?php
$isSpecial = ($category ?? '') === 'special_project';
$categoryLabel = $isSpecial ? 'Special Project' : 'MRC';
$receivedDate = !empty($project['forwarded_to_ops_at']) ? date('M d, Y H:i', strtotime((string) $project['forwarded_to_ops_at'])) : '—';
$finalSld = $opsDocs['final_sld'] ?? null;
$finalBom = $opsDocs['final_bom'] ?? null;
$requestPr = $opsDocs['request_of_pr'] ?? null;
$missingCount = count($opsDocs['missing'] ?? []);
$uploadedCount = 3 - $missingCount;
$isComplete = ($missingCount === 0);
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2">
            <h1 class="h4 fw-bold mb-0 text-dark"><?= e($project['project_name']) ?></h1>
            <span class="badge <?= $isSpecial ? 'bg-purple text-white' : 'bg-info text-dark' ?>" style="<?= $isSpecial ? 'background-color: #6f42c1;' : '' ?>">
                <?= e($categoryLabel) ?>
            </span>
            <?php if (!empty($isSubmitted)): ?>
                <span class="badge bg-dark">Submitted to OPS Finance</span>
            <?php elseif ($isComplete): ?>
                <span class="badge bg-success">OPS Documents Complete</span>
            <?php elseif ($uploadedCount > 0): ?>
                <span class="badge bg-warning text-dark">Under OPS Processing</span>
            <?php else: ?>
                <span class="badge bg-secondary">Pending Processing</span>
            <?php endif; ?>
        </div>
        <div class="text-secondary small mt-1 font-monospace">
            Code: <strong><?= e($project['project_code']) ?></strong>
            <?php if (!empty($project['jom_number'])): ?>
                &bull; JOM: <strong><?= e($project['jom_number']) ?></strong>
            <?php endif; ?>
            &bull; Client: <strong><?= e($project['client_name']) ?></strong>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <a href="<?= url('/operations/projects') ?>" class="btn btn-sm btn-outline-secondary">
            &larr; Back to Registry
        </a>
        <?php if (!empty($canSubmit)): ?>
            <a href="<?= url('/operations/projects/' . $project['id'] . '/submit-opfin') ?>" class="btn btn-sm btn-success fw-bold">
                <?= bms_icon('send', 14) ?> Submit to OPS Finance
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Project Overview Card -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small">Client Name</div>
                <div class="fw-semibold text-dark"><?= e($project['client_name']) ?></div>
                <?php if (!empty($project['client_contact'])): ?>
                    <div class="small text-secondary"><?= e($project['client_contact']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Location</div>
                <div class="fw-semibold text-dark"><?= e($project['project_location'] ?? 'Not specified') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Marketing Submission</div>
                <div class="fw-semibold text-dark">
                    <?= !empty($project['submitted_at']) ? date('M d, Y', strtotime((string) $project['submitted_at'])) : '—' ?>
                </div>
                <?php if (!empty($project['submitter_name'])): ?>
                    <div class="small text-secondary">by <?= e($project['submitter_name']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Received by Operations</div>
                <div class="fw-semibold text-dark"><?= e($receivedDate) ?></div>
                <?php if (!empty($project['forwarded_to_ops_by_name'])): ?>
                    <div class="small text-secondary">from PM (<?= e($project['forwarded_to_ops_by_name']) ?>)</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($project['project_description'])): ?>
            <div class="border-top pt-2 mt-3">
                <div class="text-muted small">Project Scope &amp; Description</div>
                <div class="small text-dark mt-1"><?= nl2br(e($project['project_description'])) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($project['forwarded_to_ops_notes'])): ?>
            <div class="alert alert-info py-2 px-3 mb-0 mt-3 small">
                <strong>Project Management Handoff Notes:</strong> <?= e($project['forwarded_to_ops_notes']) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Section 1: Documents Received from Marketing / Project Management (READ-ONLY) -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <div>
            <h5 class="h6 fw-bold mb-0 text-dark">1. Documents Received from Marketing / Project Management</h5>
            <div class="text-secondary small mt-1">Official category-based handoff package. Strictly read-only for Operations.</div>
        </div>
        <span class="badge bg-light text-dark border">Read-Only</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="width: 120px;">Doc Code</th>
                    <th scope="col">Document Type</th>
                    <th scope="col">Filename</th>
                    <th scope="col" style="width: 100px;">Size</th>
                    <th scope="col">Uploaded By</th>
                    <th scope="col" style="width: 140px;">Upload Date</th>
                    <th scope="col" class="text-end" style="width: 120px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $expectedHandoff = [
                    'JOM' => 'Job Order Memorandum (PM Uploaded)',
                    'SON' => 'Service On Notice / Service Order',
                    'BOM' => 'Marketing Bill of Materials',
                    'SLD' => 'Marketing Single Line Diagram',
                ];
                if ($isSpecial) {
                    $expectedHandoff = [
                        'JOM' => 'Job Order Memorandum (PM Uploaded)',
                        'PO' => 'Purchase Order (Client PO)',
                        'SON' => 'Service On Notice / Service Order',
                        'BOM' => 'Marketing Bill of Materials',
                        'SLD' => 'Marketing Single Line Diagram',
                    ];
                }
                ?>
                <?php foreach ($expectedHandoff as $code => $title): ?>
                    <?php $doc = $handoffDocs[$code] ?? null; ?>
                    <tr>
                        <td>
                            <span class="badge bg-secondary"><?= e($code) ?></span>
                        </td>
                        <td>
                            <div class="fw-medium text-dark"><?= e($title) ?></div>
                            <?php if ($code === 'BOM' || $code === 'SLD'): ?>
                                <div class="text-muted small fst-italic">Original Marketing version (not Final OPS version)</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($doc !== null): ?>
                                <span class="font-monospace small text-dark"><?= e($doc['original_filename']) ?></span>
                                <span class="badge bg-light text-secondary border ms-1">v<?= (int) ($doc['version_number'] ?? 1) ?></span>
                            <?php else: ?>
                                <span class="text-muted small fst-italic">Not available in handoff package</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-secondary">
                            <?= ($doc !== null && !empty($doc['file_size'])) ? number_format((int) $doc['file_size'] / 1024, 1) . ' KB' : '—' ?>
                        </td>
                        <td class="small text-secondary">
                            <?= ($doc !== null && !empty($doc['uploader_name'])) ? e($doc['uploader_name']) : '—' ?>
                        </td>
                        <td class="small text-secondary">
                            <?= ($doc !== null && !empty($doc['created_at'])) ? date('M d, Y', strtotime((string) $doc['created_at'])) : '—' ?>
                        </td>
                        <td class="text-end">
                            <?php if ($doc !== null): ?>
                                <a href="<?= url('/projects/' . $project['id'] . '/documents/' . $doc['id'] . '/view') ?>"
                                   target="_blank"
                                   class="btn btn-sm btn-outline-primary"
                                   title="View Document">
                                    <?= bms_icon('eye', 14) ?> View
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Section 2: Documents Uploaded by Operations -->
<div class="card shadow-sm border-0 mb-4" id="ops-documents">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <div>
            <h5 class="h6 fw-bold mb-0 text-dark">2. Documents Uploaded by Operations</h5>
            <div class="text-secondary small mt-1">
                Operations must upload the 3 required documents below before forwarding to OPS Finance.
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge <?= $isComplete ? 'bg-success' : 'bg-warning text-dark' ?>">
                <?= $uploadedCount ?> / 3 Complete
            </span>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="progress rounded-0" style="height: 6px;">
        <div class="progress-bar <?= $isComplete ? 'bg-success' : 'bg-primary' ?>"
             role="progressbar"
             style="width: <?= (int) (($uploadedCount / 3) * 100) ?>%;"
             aria-valuenow="<?= $uploadedCount ?>"
             aria-valuemin="0"
             aria-valuemax="3">
        </div>
    </div>

    <div class="card-body">
        <?php if (!empty($isSubmitted)): ?>
            <div class="alert alert-dark d-flex align-items-center gap-2 mb-4 py-2">
                <?= bms_icon('lock', 16) ?>
                <div>
                    <strong>Project submitted to OPS Finance:</strong> Document uploads and replacements are now locked in read-only mode.
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <?php
            $opsItems = [
                'FINAL_SLD' => [
                    'title' => 'FINAL SLD',
                    'subtitle' => 'Final Single Line Diagram',
                    'doc' => $finalSld,
                    'desc' => 'Final technical single-line diagram prepared by Operations (distinct from Marketing SLD).',
                ],
                'FINAL_BOM' => [
                    'title' => 'FINAL BOM',
                    'subtitle' => 'Final Bill of Materials',
                    'doc' => $finalBom,
                    'desc' => 'Final bill of materials prepared by Operations with verified quantities and engineering specifications.',
                ],
                'REQUEST_OF_PR' => [
                    'title' => 'REQUEST OF PR',
                    'subtitle' => 'Purchase Requisition Request',
                    'doc' => $requestPr,
                    'desc' => 'Official Request of PR prepared for OPS Finance actual costing and procurement.',
                ],
            ];
            ?>

            <?php foreach ($opsItems as $key => $item): ?>
                <?php
                $doc = $item['doc'];
                $isUploaded = ($doc !== null);
                ?>
                <div class="col-12 col-lg-4">
                    <div class="card h-100 border <?= $isUploaded ? 'border-success border-opacity-50' : 'border-danger border-opacity-50' ?>">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge <?= $isUploaded ? 'bg-success' : 'bg-danger' ?>">
                                            <?= e($item['title']) ?>
                                        </span>
                                        <?php if ($isUploaded): ?>
                                            <span class="badge bg-light text-dark border">v<?= (int) ($doc['version_number'] ?? 1) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="fw-bold text-dark mt-1 mb-0"><?= e($item['subtitle']) ?></h6>
                                </div>
                                <div>
                                    <?php if ($isUploaded): ?>
                                        <span class="text-success small fw-semibold"><?= bms_icon('check', 16) ?> Ready</span>
                                    <?php else: ?>
                                        <span class="text-danger small fw-semibold">Required</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <p class="small text-secondary mb-3 flex-grow-1">
                                <?= e($item['desc']) ?>
                            </p>

                            <?php if ($isUploaded): ?>
                                <div class="bg-light p-2 rounded small mb-3 border">
                                    <div class="text-truncate fw-medium text-dark font-monospace" title="<?= e($doc['original_filename']) ?>">
                                        <?= e($doc['original_filename']) ?>
                                    </div>
                                    <div class="text-secondary small mt-1">
                                        <?= number_format((int) $doc['file_size'] / 1024, 1) ?> KB &bull;
                                        <?= date('M d, Y', strtotime((string) $doc['created_at'])) ?>
                                    </div>
                                    <?php if (!empty($doc['uploader_name'])): ?>
                                        <div class="text-muted small">Uploaded by: <?= e($doc['uploader_name']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="<?= url('/projects/' . $project['id'] . '/documents/' . $doc['id'] . '/view') ?>"
                                       target="_blank"
                                       class="btn btn-sm btn-outline-primary flex-fill">
                                        <?= bms_icon('eye', 14) ?> View
                                    </a>
                                    <a href="<?= url('/projects/' . $project['id'] . '/documents/' . $doc['id'] . '/download') ?>"
                                       class="btn btn-sm btn-outline-secondary"
                                       title="Download File">
                                        <?= bms_icon('download', 14) ?>
                                    </a>
                                </div>

                                <?php if (!empty($canUpload)): ?>
                                    <!-- Replace Button / Modal Trigger -->
                                    <button type="button"
                                            class="btn btn-sm btn-link text-decoration-none text-muted text-center mt-2 p-0"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#collapse-replace-<?= e($key) ?>"
                                            aria-expanded="false">
                                        Replace file with new version &darr;
                                    </button>

                                    <div class="collapse mt-2" id="collapse-replace-<?= e($key) ?>">
                                        <form method="POST"
                                              action="<?= url('/operations/projects/' . $project['id'] . '/upload-document') ?>"
                                              enctype="multipart/form-data"
                                              class="border rounded p-2 bg-white">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="doc_type" value="<?= e($key) ?>">
                                            <div class="mb-2">
                                                <label class="form-label small fw-semibold mb-1">New Version File</label>
                                                <input type="file" name="ops_document" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="mb-2">
                                                <input type="text" name="upload_notes" class="form-control form-control-sm" placeholder="Version notes (optional)">
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-warning w-100 fw-semibold">
                                                Upload Version <?= (int) ($doc['version_number'] ?? 1) + 1 ?>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <!-- Upload Form for Missing Document -->
                                <?php if (!empty($canUpload)): ?>
                                    <form method="POST"
                                          action="<?= url('/operations/projects/' . $project['id'] . '/upload-document') ?>"
                                          enctype="multipart/form-data"
                                          class="border rounded p-3 bg-light">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="doc_type" value="<?= e($key) ?>">
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold mb-1">Select File</label>
                                            <input type="file" name="ops_document" class="form-control form-control-sm" required>
                                            <div class="form-text small">PDF, DOC, DOCX, XLS, XLSX, DWG, images (max 20MB)</div>
                                        </div>
                                        <div class="mb-2">
                                            <input type="text" name="upload_notes" class="form-control form-control-sm" placeholder="Upload notes (optional)">
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                                            <?= bms_icon('upload', 14) ?> Upload <?= e($item['title']) ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-secondary mb-0 small text-center py-2">
                                        Document not uploaded yet.
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Section 3: Submit to OPS Finance Panel -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h5 class="h6 fw-bold mb-1 text-dark">3. Submit to Operations Finance</h5>
                <div class="text-secondary small">
                    <?php if (!empty($isSubmitted)): ?>
                        Project was submitted to OPS Finance on <?= !empty($project['submitted_to_opfin_at']) ? date('M d, Y H:i', strtotime((string) $project['submitted_to_opfin_at'])) : '—' ?>
                        <?= !empty($project['submitted_to_opfin_by_name']) ? ' by ' . e($project['submitted_to_opfin_by_name']) : '' ?>.
                    <?php elseif ($isComplete): ?>
                        All 3 required Operations documents are available. Ready to package and forward to OPS Finance.
                    <?php else: ?>
                        Missing <?= $missingCount ?> required document<?= $missingCount === 1 ? '' : 's' ?>:
                        <span class="text-danger fw-semibold"><?= implode(', ', $opsDocs['missing'] ?? []) ?></span>.
                    <?php endif; ?>
                </div>
                <?php if (!empty($project['opfin_submission_notes'])): ?>
                    <div class="small text-muted mt-1 fst-italic">Notes: <?= e($project['opfin_submission_notes']) ?></div>
                <?php endif; ?>
            </div>

            <div>
                <?php if (!empty($isSubmitted)): ?>
                    <button class="btn btn-secondary btn-sm" disabled>
                        <?= bms_icon('check-circle', 14) ?> Submitted to OPS Finance
                    </button>
                <?php elseif (!empty($canSubmit)): ?>
                    <a href="<?= url('/operations/projects/' . $project['id'] . '/submit-opfin') ?>" class="btn btn-success fw-bold">
                        <?= bms_icon('send', 16) ?> Submit to OPS Finance &rarr;
                    </a>
                <?php else: ?>
                    <button class="btn btn-outline-secondary btn-sm" disabled title="Upload all 3 OPS documents to enable submission">
                        <?= bms_icon('lock', 14) ?> Submit to OPS Finance (Incomplete)
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Section 4: Audit & Workflow Timeline -->
<?php if (false): ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h5 class="h6 fw-bold mb-0 text-dark">4. Project Workflow &amp; Audit History</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($workflowHistory)): ?>
            <div class="text-center text-secondary py-4 small">No history entries recorded yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 160px;">Timestamp</th>
                            <th scope="col">Action</th>
                            <th scope="col">Status Transition</th>
                            <th scope="col">Actor</th>
                            <th scope="col">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workflowHistory as $hist): ?>
                            <tr>
                                <td class="text-secondary">
                                    <?= date('M d, Y H:i', strtotime((string) $hist['created_at'])) ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace">
                                        <?= e($hist['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted"><?= e($hist['from_status'] ?? '—') ?></span>
                                    &rarr;
                                    <span class="fw-semibold text-dark"><?= e($hist['to_status'] ?? '—') ?></span>
                                </td>
                                <td><?= e($hist['acted_by_name'] ?? 'System') ?></td>
                                <td class="text-muted"><?= e($hist['notes'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
