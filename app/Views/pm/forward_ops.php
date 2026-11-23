<?php
$projectId = (int) $project['id'];
$isSpecialProject = ($category === 'special_project');
$categoryLabel = $isSpecialProject ? 'Special Project' : 'MRC';
$hasMissing = ($missingDocs !== []);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= url('/projects/' . $projectId) ?>" class="btn btn-sm btn-outline-secondary" title="Back to Project">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h2 class="h5 fw-bold mb-0">Forward to Operations &mdash; <?= e($project['project_code']) ?></h2>
            <?php if ($isSpecialProject): ?>
                <span class="badge" style="background-color: #6f42c1; color: #fff;">Special Project</span>
            <?php else: ?>
                <span class="badge bg-info text-dark">MRC</span>
            <?php endif; ?>
            <span class="badge <?= project_status_badge_class($project['status']) ?>"><?= e($project['status']) ?></span>
        </div>
        <div class="text-secondary small mt-1">
            <strong><?= e($project['project_name']) ?></strong> &bull; Client: <?= e($project['client_name']) ?>
        </div>
    </div>
    <div>
        <a href="<?= url('/pm/projects/' . $projectId . '/verification') ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-clipboard-check me-1"></i>Document Verification Checklist
        </a>
    </div>
</div>

<!-- Category-Specific Forwarding Notice -->
<div class="alert alert-info border-0 shadow-sm py-2 px-3 small mb-3">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-info-circle-fill fs-5 text-info"></i>
        <div>
            <strong>Operations Handoff Package (<?= $categoryLabel ?>):</strong>
            <?php if ($isSpecialProject): ?>
                Forwarding requires exactly <strong>5 documents</strong>: JOM, Client Purchase Order (PO), SON, Marketing BOM, and Marketing SLD.
            <?php else: ?>
                Forwarding requires exactly <strong>4 documents</strong>: JOM, SON, Marketing BOM, and Marketing SLD (MRC does not require Client PO).
            <?php endif; ?>
            <span class="text-muted fst-italic">Commercial Cost Analysis (CA), Client Proposals (PROP), and Supplier Proposals (PROP-SUPPLIER) are strictly excluded from Operations handoff.</span>
        </div>
    </div>
</div>

<?php if ($hasMissing): ?>
    <div class="alert alert-danger border-0 shadow-sm py-2 px-3 small mb-3">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        <strong>Forwarding Blocked:</strong> Missing required document(s): <strong><?= implode(', ', $missingDocs) ?></strong>.
        Please ensure all category-specific documents including the JOM file are uploaded before forwarding.
    </div>
<?php endif; ?>

<!-- Handoff Documents Table -->
<div class="bms-table-panel mb-4">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
        <h3 class="h6 fw-bold mb-0">Documents Included in Operations Handoff Package</h3>
        <span class="badge bg-light text-dark border"><?= count($requiredTypes) ?> Package Documents</span>
    </div>

    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 20%;">Document Type</th>
                    <th style="width: 25%;">Description</th>
                    <th style="width: 25%;">File Name</th>
                    <th style="width: 15%;">Uploaded By / Date</th>
                    <th style="width: 15%;" class="text-end">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requiredTypes as $typeCode): ?>
                    <?php
                    $doc = $handoffDocs[$typeCode] ?? null;
                    $label = $handoffLabels[$typeCode] ?? $typeCode;
                    $isPresent = ($doc !== null);
                    ?>
                    <tr>
                        <td class="fw-semibold">
                            <?= e($typeCode) ?>
                            <?php if ($typeCode === 'JOM'): ?>
                                <span class="badge bg-primary ms-1" style="font-size: 0.68rem;">PM Uploaded</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= e($label) ?></td>
                        <td>
                            <?php if ($isPresent): ?>
                                <div class="fw-medium text-break small">
                                    <i class="bi bi-file-earmark-check text-success me-1"></i><?= e($doc['original_filename']) ?>
                                </div>
                                <div class="text-muted small">
                                    v<?= (int) ($doc['version_number'] ?? 1) ?> &bull; <?= number_format(((int) ($doc['file_size'] ?? 0)) / 1024, 1) ?> KB
                                </div>
                            <?php else: ?>
                                <span class="badge bg-danger">Missing</span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if ($isPresent): ?>
                                <div><?= e($doc['uploader_name'] ?? 'PM') ?></div>
                                <div class="text-muted"><?= format_datetime($doc['created_at']) ?></div>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($isPresent): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Ready for Handoff</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Required</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Forward Confirmation Form -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-send-check me-2 text-primary"></i>Confirm Forward to Operations</h6>
    </div>
    <div class="card-body">
        <?php if (!$hasMissing): ?>
            <form method="POST" action="<?= url('/pm/projects/' . $projectId . '/forward-ops') ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Forwarding Notes / Handoff Remarks (Optional)</label>
                    <textarea name="forward_notes" class="form-control form-control-sm" rows="3" placeholder="Enter instructions or notes for the Operations team..."></textarea>
                    <div class="form-text small">These notes will be recorded in the workflow history and visible to Operations.</div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?= url('/projects/' . $projectId) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x me-1"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-sm btn-success px-4" data-confirm="Forward project '<?= e($project['project_code']) ?>' to Operations? This action will transfer active project custody to the Operations Department.">
                        <i class="bi bi-send-check me-1"></i>Confirm & Forward to Operations
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="text-secondary small mb-3">
                Forwarding cannot proceed while documents are missing from the handoff package.
            </div>
            <a href="<?= url('/projects/' . $projectId) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Return to Project
            </a>
        <?php endif; ?>
    </div>
</div>
