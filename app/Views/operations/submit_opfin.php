<?php
$finalSld = $opsDocs['final_sld'] ?? null;
$finalBom = $opsDocs['final_bom'] ?? null;
$requestPr = $opsDocs['request_of_pr'] ?? null;
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h1 class="h5 fw-bold mb-0 text-dark">Submit Project to Operations Finance</h1>
                    <div class="text-secondary small mt-1">Review handoff checklist and package project for OPS Finance review.</div>
                </div>
                <a href="<?= url('/operations/projects/' . $project['id']) ?>" class="btn btn-sm btn-outline-secondary">
                    &larr; Back
                </a>
            </div>

            <div class="card-body">
                <!-- Project Details Summary -->
                <div class="bg-light p-3 rounded mb-4 border">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <span class="text-muted small">Project:</span>
                            <span class="fw-semibold text-dark"><?= e($project['project_name']) ?></span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Code:</span>
                            <span class="font-monospace fw-semibold text-dark"><?= e($project['project_code']) ?></span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Client:</span>
                            <span class="text-dark"><?= e($project['client_name']) ?></span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Category:</span>
                            <span class="badge bg-secondary"><?= e($project['project_category'] ?? $project['workflow_type'] ?? 'MRC') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Verified Documents Checklist -->
                <h6 class="fw-bold text-dark mb-3">Required Operations Documents for Handoff:</h6>
                <div class="list-group mb-4">
                    <div class="list-group-item d-flex align-items-center justify-content-between py-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="text-success"><?= bms_icon('check-circle', 20) ?></span>
                            <div>
                                <div class="fw-semibold text-dark">Final SLD — Single Line Diagram</div>
                                <div class="small text-muted font-monospace">
                                    <?= e($finalSld['original_filename'] ?? 'N/A') ?>
                                    (v<?= (int) ($finalSld['version_number'] ?? 1) ?>)
                                </div>
                            </div>
                        </div>
                        <span class="badge bg-success">Verified</span>
                    </div>

                    <div class="list-group-item d-flex align-items-center justify-content-between py-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="text-success"><?= bms_icon('check-circle', 20) ?></span>
                            <div>
                                <div class="fw-semibold text-dark">Final BOM — Bill of Materials</div>
                                <div class="small text-muted font-monospace">
                                    <?= e($finalBom['original_filename'] ?? 'N/A') ?>
                                    (v<?= (int) ($finalBom['version_number'] ?? 1) ?>)
                                </div>
                            </div>
                        </div>
                        <span class="badge bg-success">Verified</span>
                    </div>

                    <div class="list-group-item d-flex align-items-center justify-content-between py-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="text-success"><?= bms_icon('check-circle', 20) ?></span>
                            <div>
                                <div class="fw-semibold text-dark">Request of PR — Purchase Requisition Request</div>
                                <div class="small text-muted font-monospace">
                                    <?= e($requestPr['original_filename'] ?? 'N/A') ?>
                                    (v<?= (int) ($requestPr['version_number'] ?? 1) ?>)
                                </div>
                            </div>
                        </div>
                        <span class="badge bg-success">Verified</span>
                    </div>
                </div>

                <div class="alert alert-warning small mb-4">
                    <strong>Important:</strong> Upon submission to Operations Finance, this project will transition to <em>Submitted to OPS Finance</em> and all document uploads will be locked in read-only mode for Operations.
                </div>

                <!-- Submission Form -->
                <form method="POST" action="<?= url('/operations/projects/' . $project['id'] . '/submit-opfin') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-4">
                        <label for="notes" class="form-label fw-semibold text-dark">
                            Submission Notes / Instructions for OPS Finance (Optional)
                        </label>
                        <textarea id="notes"
                                  name="notes"
                                  class="form-control"
                                  rows="3"
                                  placeholder="Enter any technical notes, special procurement instructions, or remarks for OPS Finance..."></textarea>
                    </div>

                    <div class="d-flex align-items-center justify-content-end gap-2">
                        <a href="<?= url('/operations/projects/' . $project['id']) ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-success fw-bold px-4">
                            <?= bms_icon('send', 16) ?> Confirm &amp; Submit to OPS Finance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
