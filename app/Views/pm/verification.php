<?php
use App\Models\ProjectDocument;
use App\Services\MarketingChecklistService;

$projectId = (int) $project['id'];
$category = strtolower(trim((string) ($project['project_category'] ?? $project['workflow_type'] ?? 'mrc')));
$isSpecialProject = ($category === 'special_project');
$categoryLabel = $isSpecialProject ? 'Special Project' : 'MRC';

// Document definitions
$docDefinitions = [
    'SON' => ['name' => 'Service On Notice / Service Order', 'desc' => 'Official service notice issued to initiate project operations.'],
    'SLD' => ['name' => 'Single Line Diagram', 'desc' => 'Initial marketing electrical / technical single line diagram.'],
    'BOM' => ['name' => 'Marketing Bill of Materials', 'desc' => 'Initial estimated materials list prepared by Marketing.'],
    'CA' => ['name' => 'Marketing Cost Analysis', 'desc' => 'Financial feasibility and cost projection for the project.'],
    'PROP' => ['name' => 'Commercial Proposal Issued to Client', 'desc' => 'Official business and technical proposal delivered to the client.'],
    'PROP-SUPPLIER' => ['name' => 'Vendor / Supplier Quotation & Proposal', 'desc' => 'Quotations and commercial terms from third-party suppliers.'],
];
if ($isSpecialProject) {
    $docDefinitions['PO'] = ['name' => 'Purchase Order (Client PO)', 'desc' => 'Official client purchase order required for Special Projects.'];
}

// Index current documents by type
$currentDocsByType = [];
foreach ($documents as $doc) {
    $normalizedType = MarketingChecklistService::normalizeDocumentType($doc['document_type']);
    $currentDocsByType[$normalizedType] = $doc;
    $currentDocsByType[$doc['document_type']] = $doc;
}

$allVerified = true;
$hasCorrections = false;
$missingCount = 0;
$verifiedCount = 0;
$totalRequired = count($docDefinitions);

foreach ($docDefinitions as $typeCode => $def) {
    $doc = $currentDocsByType[$typeCode] ?? null;
    if ($doc === null) {
        $allVerified = false;
        $missingCount++;
    } else {
        $rStatus = (string) ($doc['review_status'] ?? '');
        if ($rStatus === 'verified') {
            $verifiedCount++;
        } else {
            $allVerified = false;
        }
        if ($rStatus === 'needs_correction') {
            $hasCorrections = true;
        }
    }
}

$jomDoc = ProjectDocument::findCurrentJom($projectId);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= url('/pm/projects') ?>" class="btn btn-sm btn-outline-secondary" title="Back to PM Registry">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h2 class="h5 fw-bold mb-0">Document Verification &mdash; <?= e($project['project_code']) ?></h2>
            <?php if ($isSpecialProject): ?>
                <span class="badge" style="background-color: #6f42c1; color: #fff;">Special Project</span>
            <?php else: ?>
                <span class="badge bg-info text-dark">MRC</span>
            <?php endif; ?>
            <span class="badge <?= project_status_badge_class($project['status']) ?>"><?= e($project['status']) ?></span>
        </div>
        <div class="text-secondary small mt-1">
            <strong><?= e($project['project_name']) ?></strong> &bull; Client: <?= e($project['client_name']) ?>
            <?php if (!empty($project['project_location'])): ?> &bull; Location: <?= e($project['project_location']) ?><?php endif; ?>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= url('/projects/' . $projectId) ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-folder2-open me-1"></i>Full Project Details
        </a>
    </div>
</div>

<!-- Verification Progress Bar -->
<div class="card mb-3 border-0 shadow-sm">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-semibold">Verification Progress: <?= $verifiedCount ?> of <?= $totalRequired ?> Required Documents Verified</span>
            <span class="small text-muted"><?= round(($verifiedCount / max(1, $totalRequired)) * 100) ?>%</span>
        </div>
        <div class="progress" style="height: 8px;">
            <div class="progress-bar bg-success" role="progressbar" style="width: <?= round(($verifiedCount / max(1, $totalRequired)) * 100) ?>%" aria-valuenow="<?= $verifiedCount ?>" aria-valuemin="0" aria-valuemax="<?= $totalRequired ?>"></div>
        </div>
        <?php if ($missingCount > 0): ?>
            <div class="text-danger small mt-1"><i class="bi bi-exclamation-triangle me-1"></i><?= $missingCount ?> required document(s) missing. Missing documents cannot be verified.</div>
        <?php elseif ($hasCorrections): ?>
            <div class="text-warning small mt-1"><i class="bi bi-exclamation-circle me-1"></i>One or more documents have been marked as needing correction. Return project to Marketing for revisions.</div>
        <?php elseif ($allVerified): ?>
            <div class="text-success small mt-1"><i class="bi bi-check-circle me-1"></i>All <?= $totalRequired ?> required documents are verified and ready for PM approval.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Per-Document Verification Table -->
<div class="bms-table-panel mb-4">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
        <h3 class="h6 fw-bold mb-0">Required Marketing Documents Checklist (<?= $categoryLabel ?>)</h3>
        <span class="badge bg-light text-dark border"><?= $totalRequired ?> Required Documents</span>
    </div>

    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 25%;">Document Type</th>
                    <th style="width: 20%;">File Information</th>
                    <th style="width: 15%;">Upload Details</th>
                    <th style="width: 15%;">Review Status</th>
                    <th style="width: 25%;" class="text-end">Verification Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($docDefinitions as $typeCode => $def): ?>
                    <?php
                    $doc = $currentDocsByType[$typeCode] ?? null;
                    $isUploaded = ($doc !== null);
                    $docReviewStatus = $isUploaded ? (string) ($doc['review_status'] ?? 'pending_review') : 'missing';
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark"><?= e($typeCode) ?> &mdash; <?= e($def['name']) ?></div>
                            <div class="text-muted small"><?= e($def['desc']) ?></div>
                            <?php if ($typeCode === 'PO' && !$isSpecialProject): ?>
                                <span class="badge bg-secondary small">Not required for MRC</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($isUploaded): ?>
                                <div class="fw-medium text-break small">
                                    <i class="bi bi-file-earmark me-1 text-primary"></i><?= e($doc['original_filename']) ?>
                                </div>
                                <div class="text-muted small">
                                    v<?= (int) ($doc['version_number'] ?? 1) ?> &bull; <?= number_format(((int) ($doc['file_size'] ?? 0)) / 1024, 1) ?> KB
                                </div>
                                <?php if (!empty($doc['upload_notes'])): ?>
                                    <div class="text-secondary small fst-italic">Note: <?= e($doc['upload_notes']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-danger text-white"><i class="bi bi-x-circle me-1"></i>Missing</span>
                            <?php endif; ?>
                        </td>

                        <td class="small">
                            <?php if ($isUploaded): ?>
                                <div><i class="bi bi-person me-1"></i><?= e($doc['uploader_name'] ?? 'Marketing') ?></div>
                                <div class="text-muted"><?= format_datetime($doc['created_at']) ?></div>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if (!$isUploaded): ?>
                                <span class="badge bg-danger">Missing</span>
                            <?php elseif ($docReviewStatus === 'verified'): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Verified</span>
                                <?php if (!empty($doc['verifier_name'])): ?>
                                    <div class="text-muted small mt-1">by <?= e($doc['verifier_name']) ?></div>
                                <?php endif; ?>
                            <?php elseif ($docReviewStatus === 'needs_correction'): ?>
                                <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Needs Correction</span>
                                <?php if (!empty($doc['correction_notes'])): ?>
                                    <div class="text-danger small mt-1"><strong>Note:</strong> <?= e($doc['correction_notes']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending Review</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-end">
                            <?php if ($isUploaded): ?>
                                <div class="d-flex flex-column align-items-end gap-1">
                                    <div class="btn-group btn-group-sm mb-1">
                                        <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $doc['id'] . '/view') ?>"
                                           class="btn btn-outline-secondary btn-sm"
                                           target="_blank"
                                           title="Preview Document">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $doc['id'] . '/download') ?>"
                                           class="btn btn-outline-secondary btn-sm"
                                           title="Download Document">
                                            <i class="bi bi-download"></i> Download
                                        </a>
                                    </div>

                                    <?php if ($canVerify): ?>
                                        <button type="button"
                                                class="btn btn-sm <?= $docReviewStatus === 'verified' ? 'btn-outline-success' : 'btn-primary' ?>"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#verify-form-<?= (int) $doc['id'] ?>">
                                            <i class="bi bi-pencil-square me-1"></i><?= $docReviewStatus === 'verified' ? 'Update Status' : 'Verify / Flag' ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small fst-italic">Upload required before verification</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <?php if ($isUploaded && $canVerify): ?>
                        <tr class="collapse bg-light" id="verify-form-<?= (int) $doc['id'] ?>">
                            <td colspan="5" class="p-3">
                                <form method="POST" action="<?= url('/pm/projects/' . $projectId . '/verify-document') ?>" class="row g-2 align-items-end">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">

                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Verification Decision</label>
                                        <select name="review_status" class="form-select form-select-sm" required>
                                            <option value="verified" <?= $docReviewStatus === 'verified' ? 'selected' : '' ?>>Verified (Approve Document)</option>
                                            <option value="needs_correction" <?= $docReviewStatus === 'needs_correction' ? 'selected' : '' ?>>Needs Correction (Flag for Revision)</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Correction Notes / Remarks</label>
                                        <input type="text"
                                               name="correction_notes"
                                               class="form-control form-control-sm"
                                               placeholder="Required if marking Needs Correction; optional remarks if Verified"
                                               value="<?= e($doc['correction_notes'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-3 d-flex gap-1">
                                        <button type="submit" class="btn btn-sm btn-success w-100">
                                            <i class="bi bi-check2 me-1"></i>Save Decision
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#verify-form-<?= (int) $doc['id'] ?>">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Workflow Action Cards: Approval & Return to Marketing -->
<div class="row g-3">
    <!-- PM Approval Action -->
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="mb-0 fw-bold text-success"><i class="bi bi-check-circle me-2"></i>PM Document Verification Approval</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Once all required documents are verified complete with no outstanding correction flags, approve the project to proceed to JOM upload.
                </p>

                <?php if ($canApprove && $allVerified && !$hasCorrections && $missingCount === 0): ?>
                    <form method="POST" action="<?= url('/projects/' . $projectId . '/complete-verification') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Approval Remarks (Optional)</label>
                            <textarea name="verification_notes" class="form-control form-control-sm" rows="2" placeholder="All documents verified and approved."></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-success w-100">
                            <i class="bi bi-check2-circle me-1"></i>Approve & Complete Verification
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-secondary py-2 small mb-0">
                        <i class="bi bi-lock me-1"></i>Approval is blocked until all <?= $totalRequired ?> documents are verified without correction flags.
                        <?php if ($missingCount > 0): ?>
                            <div>&bull; Missing: <?= $missingCount ?> document(s)</div>
                        <?php endif; ?>
                        <?php if ($hasCorrections): ?>
                            <div>&bull; Unresolved corrections must be addressed or returned to Marketing.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Return to Marketing Action -->
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="mb-0 fw-bold text-danger"><i class="bi bi-arrow-return-left me-2"></i>Return to Marketing for Corrections</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">
                    Return the project to Marketing with specific correction notes. Flagged documents will be highlighted for the Marketing team.
                </p>

                <?php if ($canReturn): ?>
                    <form method="POST" action="<?= url('/projects/' . $projectId . '/return-to-marketing') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Flagged Documents for Revision</label>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?php foreach ($docDefinitions as $typeCode => $def): ?>
                                    <?php
                                    $doc = $currentDocsByType[$typeCode] ?? null;
                                    $isFlagged = ($doc !== null && ($doc['review_status'] ?? '') === 'needs_correction');
                                    ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="flagged_documents[]" value="<?= e($typeCode) ?>" id="flag_<?= e($typeCode) ?>" <?= $isFlagged ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="flag_<?= e($typeCode) ?>"><?= e($typeCode) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Return Reason / Detailed Correction Notes <span class="text-danger">*</span></label>
                            <textarea name="return_reason" class="form-control form-control-sm" rows="3" required placeholder="Describe the required corrections in detail..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-sm btn-outline-danger w-100" data-confirm="Return project '<?= e($project['project_code']) ?>' to Marketing for corrections?">
                            <i class="bi bi-arrow-return-left me-1"></i>Return to Marketing
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-secondary py-2 small mb-0">
                        <i class="bi bi-lock me-1"></i>Returning to Marketing is only available when the project is in a reviewable status.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
