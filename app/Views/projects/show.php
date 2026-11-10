<?php
$projectId = (int) $project['id'];
$status = (string) ($project['status'] ?? 'Draft');
if (!empty($isMarketingUser)) {
    $backListUrl = '/marketing/projects';
    $backListLabel = 'Marketing Projects';
} elseif (!empty($isProjectManagementUser)) {
    $backListUrl = '/pm/projects';
    $backListLabel = 'PM Projects';
} elseif (!empty($isAdminFinanceUser)) {
    $backListUrl = '/admin-finance/projects';
    $backListLabel = 'Admin Finance Projects';
} else {
    $backListUrl = '/projects';
    $backListLabel = 'Projects';
}

$checklistItems = $checklist['items'] ?? [];
$supplementaryDocs = $checklist['supplementary_documents'] ?? [];
$missingRequiredTypes = $checklist['missing_required_types'] ?? [];
$attentionTypes = $checklist['attention_types'] ?? [];
$requiredCount = (int) ($checklist['required_count'] ?? 6);
$uploadedRequiredCount = (int) ($checklist['uploaded_required_count'] ?? 0);
$completionPercent = (int) ($checklist['completion_percent'] ?? 0);
$isChecklistComplete = !empty($checklist['is_complete']);
$verificationLabel = (string) ($checklist['verification_label'] ?? 'Not Yet Submitted for PM Verification');
$verificationStatus = (string) ($checklist['verification_status'] ?? 'not_submitted');

$canStartPrep = $status === 'Draft' || ($status === 'Submitted' && $verificationStatus === 'not_submitted');

$requestedUploadType = trim((string) ($openUploadType ?? ''));
$requestedHistoryType = trim((string) ($openHistoryType ?? ''));
$firstActionableType = $attentionTypes[0] ?? $missingRequiredTypes[0] ?? (string) array_key_first($checklistItems);
$resolvedOpenUploadType = $requestedUploadType === 'auto' ? $firstActionableType : $requestedUploadType;
$quickSelectedDocType = ($resolvedOpenUploadType !== '' && isset($documentCategories[$resolvedOpenUploadType]))
    ? $resolvedOpenUploadType
    : $firstActionableType;

$oldFlagged = old_raw('flagged_documents', []);
$selectedFlagged = is_array($oldFlagged) ? array_map('strval', $oldFlagged) : [];
?>

<!-- Top Header Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <span class="font-monospace badge badge-soft-secondary fs-6"><?= e($project['project_code']) ?></span>
            <span class="badge <?= project_status_badge_class($status) ?> px-3 py-2"><?= e($status) ?></span>
            <?php if ($isChecklistComplete): ?>
                <span class="badge badge-soft-success px-2 py-2"><?= $uploadedRequiredCount ?>/<?= $requiredCount ?> Required Docs Uploaded</span>
            <?php else: ?>
                <span class="badge badge-soft-warning px-2 py-2"><?= $uploadedRequiredCount ?>/<?= $requiredCount ?> Required Docs Uploaded</span>
            <?php endif; ?>
        </div>
        <h2 class="h4 fw-bold mb-1"><?= e($project['project_name']) ?></h2>
        <div class="text-secondary small">
            Client: <strong><?= e($project['client_name']) ?></strong>
            &bull; Registered by <strong><?= e($project['creator_name']) ?></strong> (@<?= e($project['creator_username']) ?>)
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="<?= url($backListUrl) ?>"
           class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline"
           title="Back to Projects"
           aria-label="Back to Projects">
            <?= bms_icon('back', 16) ?>
        </a>

        <?php if (!empty($canEdit)): ?>
            <a href="<?= url('/projects/' . $projectId . '/edit') ?>" class="btn btn-outline-primary btn-sm">Edit Details</a>
        <?php endif; ?>

        <?php if (!empty($canEdit) && $canStartPrep): ?>
            <form method="POST" action="<?= url('/projects/' . $projectId . '/prepare-documents') ?>" class="m-0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-warning btn-sm fw-semibold">Start Document Preparation</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Returned for Corrections Banner -->
<?php if (in_array($status, ['Returned for Corrections', 'Returned to Marketing for Corrections'], true) || (!empty($project['return_reason']) && $verificationStatus === 'returned_for_corrections')): ?>
    <div id="return-banner" class="alert alert-danger border-danger border-opacity-50 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <div class="fw-bold">
                &#9888; Returned by Project Management for Document Corrections
            </div>
            <div class="small">
                <?php if (!empty($project['returner_name'])): ?>
                    Returned by <strong><?= e($project['returner_name']) ?></strong>
                <?php endif; ?>
                <?php if (!empty($project['returned_at'])): ?>
                    on <?= format_datetime((string) $project['returned_at']) ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded border border-danger border-opacity-25 p-3 small text-dark mb-2" style="white-space: pre-line;"><?= e($project['return_reason'] ?: 'Please review and update the Marketing document package.') ?></div>

        <?php if ($attentionTypes !== []): ?>
            <div class="small">
                <strong>Documents Flagged for Attention:</strong>
                <?php foreach ($attentionTypes as $flaggedType): ?>
                    <span class="badge bg-danger ms-1"><?= e($flaggedType) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="small mt-2">
            Upload replacement versions in the checklist below (prior versions remain preserved in history) and click <strong>Resubmit for Verification</strong> when ready.
        </div>
    </div>
<?php endif; ?>

<!-- JOM Number / Forwarding / PM Verification Status Banners -->
<?php if (!empty($project['jom_number'])): ?>
    <div class="alert alert-primary border-primary border-opacity-50 mb-4 shadow-sm">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <span class="badge bg-primary text-uppercase me-2">Official JOM Issued</span>
                <span class="font-monospace fw-bold fs-5 text-primary"><?= e($project['jom_number']) ?></span>
                <div class="small text-secondary mt-1">
                    Job Order Memorandum officially issued by <strong><?= e($project['jom_issuer_name'] ?: 'Admin Finance') ?></strong>
                    <?php if (!empty($project['jom_issued_at'])): ?>
                        on <?= format_datetime((string) $project['jom_issued_at']) ?>
                    <?php endif; ?>
                    &bull; Authorized for downstream departmental execution (Operations, OPS Finance, Admin Finance).
                </div>
            </div>
            <div>
                <span class="badge badge-soft-success fs-6"><i class="me-1">&#10003;</i> JOM Assigned</span>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$isForwardedToOps = $status === 'Forwarded to Operations' || !empty($project['forwarded_to_ops_at']) || $verificationStatus === 'forwarded_to_operations';
?>
<?php if ($isForwardedToOps): ?>
    <div class="alert alert-success border-success border-opacity-50 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <div class="fw-bold text-success">
                &rarr; Forwarded to Operations (JOM Number, SLD &amp; Marketing BOM Handoff Complete)
            </div>
            <div class="small text-secondary">
                <?php if (!empty($project['forwarded_to_ops_by_name'])): ?>
                    Forwarded by <strong><?= e($project['forwarded_to_ops_by_name']) ?></strong> (Admin Finance)
                <?php endif; ?>
                <?php if (!empty($project['forwarded_to_ops_at'])): ?>
                    on <?= format_datetime((string) $project['forwarded_to_ops_at']) ?>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($project['forwarded_to_ops_notes'])): ?>
            <div class="bg-white rounded border border-success border-opacity-25 p-3 small text-dark mb-2" style="white-space: pre-line;"><?= e($project['forwarded_to_ops_notes']) ?></div>
        <?php endif; ?>
        <div class="small text-secondary">
            Admin Finance has issued JOM Number <strong><?= e($project['jom_number'] ?: 'N/A') ?></strong> and forwarded the <strong>1. JOM Number</strong>, <strong>2. Single Line Diagram (SLD)</strong>, and <strong>3. Marketing BOM</strong> to the <strong>Operations</strong> incoming queue.
        </div>
    </div>
<?php elseif ($status === 'Forwarded to Admin Finance' || !empty($project['forwarded_to_afin_at']) || $verificationStatus === 'forwarded_to_admin_finance'): ?>
    <div class="alert alert-info border-info border-opacity-50 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <div class="fw-bold text-dark">
                &rarr; Forwarded to Admin Finance
            </div>
            <div class="small text-secondary">
                <?php if (!empty($project['forwarded_to_afin_by_name'])): ?>
                    Forwarded by <strong><?= e($project['forwarded_to_afin_by_name']) ?></strong>
                <?php elseif (!empty($project['verifier_name'])): ?>
                    Verified by <strong><?= e($project['verifier_name']) ?></strong>
                <?php endif; ?>
                <?php if (!empty($project['forwarded_to_afin_at'])): ?>
                    on <?= format_datetime((string) $project['forwarded_to_afin_at']) ?>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($project['forwarded_to_afin_notes'])): ?>
            <div class="bg-white rounded border border-info border-opacity-25 p-3 small text-dark mb-2" style="white-space: pre-line;"><?= e($project['forwarded_to_afin_notes']) ?></div>
        <?php endif; ?>
        <div class="small text-secondary">
            Project documents have been verified and approved by Project Management and routed to the <strong>Admin Finance</strong> incoming queue. Admin Finance is responsible for issuing the official JOM number and forwarding required documents before the project proceeds to Operations.
        </div>
    </div>
<?php elseif (in_array($status, ['Approved by Project Management', 'Document Verification Completed'], true) || in_array($verificationStatus, ['approved_by_pm', 'verified_complete'], true)): ?>
    <div class="alert alert-success border-success border-opacity-50 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <div class="fw-bold text-success">
                &#10003; Approved by Project Management (Document Verification Complete)
            </div>
            <div class="small text-secondary">
                <?php if (!empty($project['verifier_name'])): ?>
                    Approved by <strong><?= e($project['verifier_name']) ?></strong>
                <?php endif; ?>
                <?php if (!empty($project['verified_at'])): ?>
                    on <?= format_datetime((string) $project['verified_at']) ?>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($project['verification_notes'])): ?>
            <div class="bg-white rounded border border-success border-opacity-25 p-3 small text-dark mb-2" style="white-space: pre-line;"><?= e($project['verification_notes']) ?></div>
        <?php endif; ?>
        <div class="small text-secondary">
            All required Marketing checklist documents are verified complete and readable by Project Management. Project Management does not issue JOM numbers; the approved project must be forwarded to <strong>Admin Finance</strong> for JOM number assignment.
            <?php if (!empty($canForwardToAdminFinance)): ?>
                <div class="mt-2 fw-semibold text-primary">
                    Ready for Next Stage: Use the <strong>Forward to Admin Finance</strong> action below to route this project to Admin Finance.
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Project Record, Workflow Actions & PM Review Panels -->
    <div class="col-12 col-xl-5">
        <!-- Project Registration & Verification Summary -->
        <div class="bms-card p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h3 class="h6 fw-bold text-uppercase text-secondary mb-0" style="letter-spacing: 0.04em;">Project Registration &amp; Workflow Summary</h3>
                <span class="badge badge-soft-secondary"><?= e($checklist['workflow_name'] ?? 'Standard Project Workflow') ?></span>
            </div>

            <div class="row g-3 small">
                <div class="col-12 col-md-6">
                    <div class="text-secondary fw-semibold mb-1">Internal Project Code</div>
                    <div class="font-monospace fw-semibold"><?= e($project['project_code']) ?></div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="text-secondary fw-semibold mb-1">Job Order Memorandum (JOM) Number</div>
                    <?php if (!empty($project['jom_number'])): ?>
                        <div class="font-monospace fw-bold text-primary fs-5"><?= e($project['jom_number']) ?></div>
                        <div class="text-secondary" style="font-size: 0.75rem;">
                            Issued by <?= e($project['jom_issuer_name'] ?: 'Admin Finance') ?> on <?= format_datetime((string) $project['jom_issued_at']) ?>
                        </div>
                    <?php else: ?>
                        <div class="text-secondary fst-italic">Not Yet Assigned</div>
                        <div class="text-secondary" style="font-size: 0.75rem;">
                            Issued by Admin Finance after Project Management approval and handoff.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-12 col-md-6">
                    <div class="text-secondary fw-semibold mb-1">Current Department Custody</div>
                    <div>
                        <?php if (!empty($project['current_department_name'])): ?>
                            <?= e($project['current_department_name']) ?>
                            <span class="badge badge-soft-secondary ms-1"><?= e($project['current_department_code']) ?></span>
                        <?php else: ?>
                            Unassigned
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($project['origin_department_name'])): ?>
                        <div class="text-secondary" style="font-size: 0.75rem;">Origin Department: <?= e($project['origin_department_name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 col-md-6">
                    <div class="text-secondary fw-semibold mb-1">Client Name</div>
                    <div class="fw-medium"><?= e($project['client_name']) ?></div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="text-secondary fw-semibold mb-1">Client Contact</div>
                    <div><?= e($project['client_contact'] ?: 'Not specified') ?></div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="text-secondary fw-semibold mb-1">Project Location</div>
                    <div><?= e($project['project_location'] ?: 'Not specified') ?></div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="text-secondary fw-semibold mb-1">PM Document Verification Status</div>
                    <div class="fw-medium"><?= e($verificationLabel) ?></div>
                    <div class="text-secondary" style="font-size: 0.74rem;">Uploading a file marks it Uploaded/Replaced, not PM-approved.</div>
                </div>

                <?php if ($purchaseRequest !== null): ?>
                    <div class="col-12 col-md-6">
                        <div class="text-secondary fw-semibold mb-1">Operations Purchase Request (PR)</div>
                        <div class="font-monospace fw-semibold"><?= e($purchaseRequest['pr_number']) ?></div>
                        <div>
                            <span class="badge <?= pr_status_badge_class((string) $purchaseRequest['status']) ?>"><?= e($purchaseRequest['status']) ?></span>
                            <span class="text-secondary small">(<?= count($purchaseRequest['items'] ?? []) ?> items)</span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($actualBom !== null): ?>
                    <div class="col-12 col-md-6">
                        <div class="text-secondary fw-semibold mb-1">OPS Finance Actual BOM</div>
                        <div class="font-monospace fw-semibold"><?= e($actualBom['actual_bom_number']) ?> (v<?= (int) ($actualBom['version_number'] ?? 1) ?>)</div>
                        <div>
                            <span class="badge <?= actual_bom_status_badge_class((string) $actualBom['status']) ?>"><?= e($actualBom['status']) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="col-12">
                    <div class="text-secondary fw-semibold mb-1">Project Scope / Description</div>
                    <div class="bg-light rounded p-3 text-dark" style="white-space: pre-line;"><?= e($project['project_description'] ?: 'No description provided.') ?></div>
                </div>
            </div>

            <div class="row g-2 mt-3 pt-3 border-top text-secondary" style="font-size: 0.8rem;">
                <div class="col-12 col-md-4">
                    <strong>Created:</strong> <?= format_datetime($project['created_at']) ?>
                </div>
                <div class="col-12 col-md-4">
                    <strong>Last Updated:</strong> <?= format_datetime($project['updated_at']) ?>
                </div>
                <div class="col-12 col-md-4">
                    <strong>Submitted to PM:</strong> <?= format_datetime($project['submitted_at'] ?? null) ?>
                    <?php if (!empty($project['submitter_name'])): ?>
                        (<?= e($project['submitter_name']) ?>)
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Document Completeness Validation & Submit to Project Management Panel -->
        <?php if (!empty($canSubmit)): ?>
            <div id="submission-panel" class="bms-card p-4 mb-4 border-start border-4 <?= $isChecklistComplete ? 'border-success' : 'border-warning' ?>">
                <h3 class="h6 fw-bold mb-2">
                    <?= in_array($status, ['Returned for Corrections', 'Returned to Marketing for Corrections'], true) ? 'Resubmit Project to Project Management' : 'Submit to Project Management for Document Verification' ?>
                </h3>

                <?php if (!$isChecklistComplete): ?>
                    <p class="small text-secondary mb-2">
                        <strong><?= count($missingRequiredTypes) ?> of <?= $requiredCount ?> required Marketing document<?= count($missingRequiredTypes) === 1 ? ' is' : 's are' ?> still missing</strong> (all <?= $requiredCount ?> must be uploaded before Project Management verification):
                    </p>
                    <div class="mb-3">
                        <?php foreach ($missingRequiredTypes as $missingType): ?>
                            <span class="badge badge-soft-danger me-1 mb-1"><?= e($missingType) ?> — Missing</span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="small text-secondary mb-3">
                        All <strong><?= $requiredCount ?> required Marketing documents</strong> have been uploaded. Submitting forwards this project to the <strong>Project Management</strong> review queue for document completeness verification.
                    </p>
                <?php endif; ?>

                <form
                    method="POST"
                    action="<?= url('/projects/' . $projectId . '/submit') ?>"
                    <?= $isChecklistComplete ? 'data-confirm="Confirm forwarding project \'' . e($project['project_code']) . '\' to Project Management for document verification?"' : '' ?>
                >
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="submission_notes" class="form-label small fw-medium mb-1">Handoff Remarks for Project Management (Optional)</label>
                        <textarea id="submission_notes" name="submission_notes" rows="2" maxlength="1000" class="form-control form-control-sm" placeholder="Summarize document package highlights or revision notes for PM..."></textarea>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if (!$isChecklistComplete): ?>
                            <a href="<?= url('/projects/' . $projectId . '?upload=auto#checklist') ?>" class="btn btn-primary btn-sm fw-semibold">
                                Upload
                            </a>
                        <?php endif; ?>
                        <button type="submit" class="btn <?= $isChecklistComplete ? 'btn-success' : 'btn-outline-secondary btn-sm' ?> fw-semibold">
                            <?= in_array($status, ['Returned for Corrections', 'Returned to Marketing for Corrections'], true) ? 'Resubmit' : 'Submit' ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php elseif (in_array($status, ['Submitted for Document Verification', 'Submitted for Verification', 'Resubmitted for Verification', 'Submitted'], true)): ?>
            <div id="submission-panel" class="bms-card p-4 mb-4 border-start border-4 border-primary">
                <h3 class="h6 fw-bold mb-1">Queued with Project Management for Document Verification</h3>
                <p class="small text-secondary mb-0">
                    Marketing edits and document replacements are locked while Project Management reviews the submitted package. If Project Management flags incomplete or discrepant files, the project will be returned to Marketing for corrections.
                </p>
            </div>
        <?php endif; ?>

        <!-- Project Management Document Verification & Handoff Panel -->
        <?php
        $isForwardedToAfin = $status === 'Forwarded to Admin Finance' || !empty($project['forwarded_to_afin_at']) || $verificationStatus === 'forwarded_to_admin_finance';
        $isPmApprovedStage = in_array($status, ['Approved by Project Management', 'Document Verification Completed'], true) || in_array($verificationStatus, ['approved_by_pm', 'verified_complete'], true);
        ?>
        <?php if (!empty($canCompleteVerification) || !empty($canReturnToMarketing) || !empty($canStartReview) || !empty($canForwardToAdminFinance) || $isForwardedToAfin || $isPmApprovedStage): ?>
            <div id="pm-review-panel" class="bms-card p-4 mb-4 border-start border-4 border-primary">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h3 class="h6 fw-bold mb-0">Project Management Document Verification &amp; Handoff</h3>
                        <div class="text-secondary small">
                            Inspect submitted Marketing documents, record verification decisions, return incomplete packages, or forward approved projects to Admin Finance.
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-1">
                        <span class="badge badge-soft-primary">PM Verification</span>
                        <?php if (!empty($project['jom_number'])): ?>
                            <span class="badge bg-success font-monospace">JOM: <?= e($project['jom_number']) ?></span>
                        <?php endif; ?>
                        <?php if ($isForwardedToAfin): ?>
                            <span class="badge badge-soft-info">Forwarded to Admin Finance</span>
                        <?php elseif ($isPmApprovedStage): ?>
                            <span class="badge badge-soft-success">Approved by PM</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="p-3 bg-light rounded border mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div class="fw-semibold small text-dark">Document Completeness Evaluation:</div>
                        <?php if ($isChecklistComplete): ?>
                            <span class="badge bg-success">All <?= $requiredCount ?>/<?= $requiredCount ?> Required Documents Present</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><?= $uploadedRequiredCount ?>/<?= $requiredCount ?> Documents Uploaded (Incomplete)</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$isChecklistComplete): ?>
                        <div class="small text-danger mb-1">
                            <strong>Missing Required Documents:</strong> <?= e(implode(', ', $missingRequiredTypes)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="small text-secondary">
                        Verification completeness checks all 6 standard Marketing documents (SON, SLD, BOM, CA, Proposal to Client, Proposal from Supplier).
                    </div>
                </div>

                <!-- Action: Forward Approved Project to Admin Finance -->
                <?php if (!empty($canForwardToAdminFinance)): ?>
                    <div class="p-4 rounded border mb-4 bg-white border-primary border-opacity-75 shadow-sm">
                        <div class="fw-bold text-primary fs-6 mb-1">
                            &rarr; Forward Approved Project to Admin Finance
                        </div>
                        <div class="text-secondary small mb-3">
                            Project Management verification is complete. Forward this approved project to <strong>Admin Finance</strong> so it appears in the Admin Finance incoming queue for JOM number assignment.
                        </div>
                        <form
                            method="POST"
                            action="<?= url('/projects/' . $projectId . '/forward-to-admin-finance') ?>"
                            data-confirm="Forward approved project '<?= e($project['project_code']) ?>' to Admin Finance?"
                            class="m-0"
                        >
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label for="forward_notes" class="form-label small fw-semibold mb-1">Handoff Notes for Admin Finance (Optional)</label>
                                <textarea
                                    id="forward_notes"
                                    name="forward_notes"
                                    rows="2"
                                    maxlength="2000"
                                    class="form-control form-control-sm"
                                    placeholder="All 6 required Marketing documents verified complete and approved for Admin Finance processing..."
                                ></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary fw-semibold w-100">
                                Forward to Admin Finance
                            </button>
                        </form>
                    </div>
                <?php elseif ($isForwardedToAfin): ?>
                    <div class="p-3 rounded border mb-4 bg-light border-info">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <div class="fw-bold text-dark small mb-1">
                                    &#10003; Forwarded to Admin Finance
                                </div>
                                <div class="text-secondary" style="font-size: 0.78rem;">
                                    <?php if (!empty($project['forwarded_to_afin_by_name'])): ?>
                                        Forwarded by <strong><?= e($project['forwarded_to_afin_by_name']) ?></strong>
                                    <?php endif; ?>
                                    <?php if (!empty($project['forwarded_to_afin_at'])): ?>
                                        on <?= format_datetime((string) $project['forwarded_to_afin_at']) ?>
                                    <?php endif; ?>
                                    &bull; Project is in the Admin Finance incoming queue awaiting JOM issuance before Operations.
                                </div>
                            </div>
                            <div>
                                <button type="button" class="btn btn-secondary btn-sm" disabled>Forwarded to Admin Finance</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Action 1: Mark Under Review (if awaiting review) -->
                <?php if (!empty($canStartReview)): ?>
                    <div class="p-3 rounded border mb-4 bg-white">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <div class="fw-semibold small text-dark">Initiate Formal Review</div>
                                <div class="text-secondary small">Mark this submission as Under Review by Project Management.</div>
                            </div>
                            <form method="POST" action="<?= url('/projects/' . $projectId . '/start-review') ?>" class="m-0">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-warning btn-sm fw-semibold">
                                    Mark as Under Review
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <!-- Action 2: Approve & Complete Document Verification -->
                    <?php if (!empty($canCompleteVerification)): ?>
                        <div class="col-12">
                            <div class="p-3 rounded border h-100 bg-white d-flex flex-column justify-content-between">
                                <div>
                                    <div class="fw-semibold small text-success mb-1">&#10003; Approve Document Verification</div>
                                    <p class="small text-secondary mb-3">
                                        Confirm that all required documents meet configured completeness and readability requirements.
                                    </p>

                                    <?php if (!$isChecklistComplete): ?>
                                        <div class="alert alert-warning py-2 small mb-3">
                                            <strong>Verification Locked:</strong> Cannot approve while required documents are missing (<?= count($missingRequiredTypes) ?> missing).
                                        </div>
                                    <?php endif; ?>

                                    <form
                                        method="POST"
                                        action="<?= url('/projects/' . $projectId . '/complete-verification') ?>"
                                        data-confirm="Approve project '<?= e($project['project_code']) ?>' by Project Management?"
                                    >
                                        <?= csrf_field() ?>
                                        <div class="mb-3">
                                            <label for="verification_notes" class="form-label small fw-semibold mb-1">Verification Notes / Approval Remarks (Optional)</label>
                                            <textarea
                                                id="verification_notes"
                                                name="verification_notes"
                                                rows="3"
                                                maxlength="2000"
                                                class="form-control form-control-sm"
                                                placeholder="All required Marketing documents verified complete and readable..."
                                                <?= !$isChecklistComplete ? 'disabled' : '' ?>
                                            ></textarea>
                                        </div>

                                        <button
                                            type="submit"
                                            class="btn btn-success btn-sm fw-semibold w-100"
                                            <?= !$isChecklistComplete ? 'disabled' : '' ?>
                                        >
                                            Approve &amp; Complete Verification
                                        </button>
                                    </form>
                                </div>
                                <div class="small text-secondary mt-3 pt-2 border-top" style="font-size: 0.72rem;">
                                    Approves document verification only. Does not issue a JOM number (Admin Finance issues the JOM number after PM forwards the project).
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Action 3: Return to Marketing for Corrections -->
                    <?php if (!empty($canReturnToMarketing)): ?>
                        <div class="col-12">
                            <div class="p-3 rounded border h-100 bg-white">
                                <div class="fw-semibold small text-danger mb-1">&#9888; Return for Corrections</div>
                                <p class="small text-secondary mb-3">
                                    Send project back to Marketing with specific correction notes and flagged documents.
                                </p>

                                <form
                                    method="POST"
                                    action="<?= url('/projects/' . $projectId . '/return-to-marketing') ?>"
                                    data-confirm="Return project '<?= e($project['project_code']) ?>' to Marketing for document corrections?"
                                    novalidate
                                >
                                    <?= csrf_field() ?>

                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold mb-1">Flag Specific Documents (Optional)</label>
                                        <div class="row g-1">
                                            <?php $flagIdx = 0; ?>
                                            <?php foreach ($checklistItems as $docType => $item): ?>
                                                <?php $flagIdx++; ?>
                                                <div class="col-6">
                                                    <div class="form-check small" style="font-size: 0.76rem;">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            name="flagged_documents[]"
                                                            value="<?= e((string) $docType) ?>"
                                                            id="flag_doc_<?= $flagIdx ?>"
                                                            <?= in_array((string) $docType, $selectedFlagged, true) ? 'checked' : '' ?>
                                                        >
                                                        <label class="form-check-label text-truncate" for="flag_doc_<?= $flagIdx ?>">
                                                            <?= e((string) $item['code']) ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="return_reason" class="form-label small fw-semibold mb-1">Correction Notes <span class="text-danger">*</span></label>
                                        <textarea
                                            id="return_reason"
                                            name="return_reason"
                                            rows="3"
                                            maxlength="2000"
                                            class="form-control form-control-sm <?= has_error('return_reason') ? 'is-invalid' : '' ?>"
                                            placeholder="Explain clearly which documents require correction..."
                                            required
                                        ><?= old('return_reason') ?></textarea>
                                        <?php if (has_error('return_reason')): ?>
                                            <div class="invalid-feedback"><?= field_error('return_reason') ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <button type="submit" class="btn btn-outline-danger btn-sm fw-semibold w-100">
                                        Return to Marketing for Corrections
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Admin Finance: JOM Number Issuance & Operations Handoff Panel -->
        <?php
        $showAdminFinancePanel = !empty($canStartAdminFinanceReview)
            || !empty($canIssueJomNumber)
            || !empty($canForwardToOperations)
            || (!empty($isAdminFinanceUser) && ($isForwardedToAfin || $isForwardedToOps))
            || (!empty($isAdmin) && ($isForwardedToAfin || $isForwardedToOps));
        $handoffSld = $operationsHandoffDocs['sld'] ?? null;
        $handoffBom = $operationsHandoffDocs['marketing_bom'] ?? null;
        $hasAssignedJom = !empty($project['jom_number']);
        $allOpsHandoffItemsReady = $hasAssignedJom && $handoffSld !== null && $handoffBom !== null;
        $jomFormatPattern = (string) ($jomConfig['format_pattern'] ?? 'JOM-{YYYY}-{SEQ}');
        $jomRequiresConfirmation = (bool) ($jomConfig['requires_business_confirmation'] ?? true);
        ?>
        <?php if ($showAdminFinancePanel): ?>
            <div id="admin-finance-jom-panel" class="bms-card p-4 mb-4 border-start border-4 border-info">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge badge-soft-info">Admin Finance Module</span>
                            <h3 class="h6 fw-bold text-dark mb-0">JOM Issuance &amp; Operations Document Handoff</h3>
                        </div>
                        <div class="text-secondary small">
                            Admin Finance is the sole department authorized to issue the official JOM number and forward the JOM Number, SLD, and Marketing BOM to Operations.
                        </div>
                    </div>
                    <div>
                        <?php if ($isForwardedToOps): ?>
                            <span class="badge badge-soft-info">Forwarded to Operations</span>
                        <?php elseif ($hasAssignedJom): ?>
                            <span class="badge badge-soft-success">JOM Issued</span>
                        <?php elseif ($status === 'Under Admin Finance Review'): ?>
                            <span class="badge badge-soft-warning">Under Admin Finance Review</span>
                        <?php else: ?>
                            <span class="badge badge-soft-primary">Awaiting JOM Issuance</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Step 1: Admin Finance Review -->
                <?php if (!empty($canStartAdminFinanceReview)): ?>
                    <div class="p-3 rounded border bg-white mb-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <div class="fw-semibold small text-dark">Step 1: Start Admin Finance Review</div>
                                <div class="text-secondary small">Mark this approved project as Under Admin Finance Review before issuing the JOM number.</div>
                            </div>
                            <form method="POST" action="<?= url('/projects/' . $projectId . '/admin-finance-review') ?>" class="m-0">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-warning btn-sm fw-semibold">
                                    Mark Under Admin Finance Review
                                </button>
                            </form>
                        </div>
                    </div>
                <?php elseif (!empty($project['afin_reviewed_at'])): ?>
                    <div class="p-2 px-3 rounded border bg-light mb-3 small text-secondary">
                        <strong>Admin Finance Review:</strong> Reviewed by <strong><?= e($project['afin_reviewed_by_name'] ?: 'Admin Finance') ?></strong>
                        on <?= format_datetime((string) $project['afin_reviewed_at']) ?>
                    </div>
                <?php endif; ?>

                <!-- Step 2: Issue JOM Number -->
                <div class="p-3 rounded border mb-3 <?= $hasAssignedJom ? 'bg-light border-success border-opacity-50' : 'bg-white border-primary border-opacity-75 shadow-sm' ?>">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <div class="fw-bold text-dark small mb-1">
                                Step 2: Issue Job Order Memorandum (JOM) Number
                            </div>
                            <?php if ($hasAssignedJom): ?>
                                <div class="small text-secondary">
                                    Assigned JOM Number: <strong class="font-monospace text-primary"><?= e($project['jom_number']) ?></strong>
                                    &bull; Issued by <strong><?= e($project['jom_issuer_name'] ?: 'Admin Finance') ?></strong>
                                    <?php if (!empty($project['jom_issued_at'])): ?>
                                        on <?= format_datetime((string) $project['jom_issued_at']) ?>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-secondary small mb-1">
                                    Generate and assign a unique, server-controlled JOM number for this PM-approved project.
                                </div>
                                <?php if ($jomRequiresConfirmation): ?>
                                    <div class="small text-warning-emphasis" style="font-size: 0.74rem;">
                                        <strong>Format Notice:</strong> Configured pattern <code><?= e($jomFormatPattern) ?></code> (<code>config/app.php</code>) — requires business confirmation before production use.
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if (!empty($canIssueJomNumber)): ?>
                                <form
                                    method="POST"
                                    action="<?= url('/projects/' . $projectId . '/issue-jom') ?>"
                                    data-confirm="Confirm official JOM Number issuance for approved project '<?= e($project['project_code']) ?>' (<?= e($project['project_name']) ?>)? This action is permanent and assigns a unique JOM number."
                                    class="m-0"
                                >
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-primary btn-sm fw-semibold px-3 py-2">
                                        Issue JOM Number
                                    </button>
                                </form>
                            <?php elseif ($hasAssignedJom): ?>
                                <span class="badge bg-success font-monospace"><?= e($project['jom_number']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Forward JOM Number, SLD & Marketing BOM to Operations -->
                <div class="p-3 rounded border <?= $isForwardedToOps ? 'bg-light border-info' : 'bg-white' ?>">
                    <div class="fw-bold text-dark small mb-1">
                        Step 3: Forward Required Package to Operations (JOM Number, SLD &amp; Marketing BOM)
                    </div>
                    <div class="text-secondary small mb-3">
                        After JOM issuance, Admin Finance must verify and forward these exact 3 items to <strong>Operations (OPS)</strong>. Note: <strong>Marketing BOM</strong> is the initial Bill of Materials verified from Marketing (distinct from Actual BOM).
                    </div>

                    <div class="list-group mb-3 small">
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div>
                                <strong>1. JOM Number</strong>
                                <div class="text-secondary" style="font-size: 0.75rem;">Official Job Order Memorandum Number</div>
                            </div>
                            <?php if ($hasAssignedJom): ?>
                                <span class="badge bg-primary font-monospace"><?= e($project['jom_number']) ?> &#10003;</span>
                            <?php else: ?>
                                <span class="badge badge-soft-warning">Awaiting JOM Issuance</span>
                            <?php endif; ?>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div>
                                <strong>2. SLD (Single Line Diagram)</strong>
                                <?php if ($handoffSld !== null): ?>
                                    <div class="text-secondary" style="font-size: 0.75rem;">
                                        <?= e($handoffSld['original_filename']) ?> (v<?= (int) $handoffSld['version_number'] ?>, <?= format_bytes((int) $handoffSld['file_size']) ?>)
                                    </div>
                                <?php else: ?>
                                    <div class="text-danger" style="font-size: 0.75rem;">Missing current SLD document</div>
                                <?php endif; ?>
                            </div>
                            <?php if ($handoffSld !== null): ?>
                                <span class="badge badge-soft-success">Available &#10003;</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Missing</span>
                            <?php endif; ?>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div>
                                <strong>3. Marketing BOM (Initial Bill of Materials)</strong>
                                <?php if ($handoffBom !== null): ?>
                                    <div class="text-secondary" style="font-size: 0.75rem;">
                                        <?= e($handoffBom['original_filename']) ?> (v<?= (int) $handoffBom['version_number'] ?>, <?= format_bytes((int) $handoffBom['file_size']) ?>)
                                    </div>
                                <?php else: ?>
                                    <div class="text-danger" style="font-size: 0.75rem;">Missing current Marketing BOM document</div>
                                <?php endif; ?>
                            </div>
                            <?php if ($handoffBom !== null): ?>
                                <span class="badge badge-soft-success">Available &#10003;</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Missing</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($canForwardToOperations)): ?>
                        <form
                            method="POST"
                            action="<?= url('/projects/' . $projectId . '/forward-to-operations') ?>"
                            data-confirm="Forward JOM Number '<?= e($project['jom_number'] ?? '') ?>', SLD, and Marketing BOM for project '<?= e($project['project_code']) ?>' to Operations?"
                            class="m-0"
                        >
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label for="operations_forward_notes" class="form-label small fw-semibold mb-1">Handoff Notes for Operations (Optional)</label>
                                <textarea
                                    id="operations_forward_notes"
                                    name="operations_forward_notes"
                                    rows="2"
                                    maxlength="2000"
                                    class="form-control form-control-sm"
                                    placeholder="JOM Number issued; forwarding JOM Number, SLD, and Marketing BOM to Operations..."
                                    <?= !$allOpsHandoffItemsReady ? 'disabled' : '' ?>
                                ></textarea>
                            </div>
                            <button
                                type="submit"
                                class="btn btn-success btn-sm fw-semibold w-100"
                                <?= !$allOpsHandoffItemsReady ? 'disabled' : '' ?>
                            >
                                Forward JOM, SLD &amp; Marketing BOM to Operations
                            </button>
                        </form>
                    <?php elseif ($isForwardedToOps): ?>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="small text-secondary">
                                &#10003; Forwarded to <strong>Operations (OPS)</strong>
                                <?php if (!empty($project['forwarded_to_ops_by_name'])): ?>
                                    by <strong><?= e($project['forwarded_to_ops_by_name']) ?></strong>
                                <?php endif; ?>
                                <?php if (!empty($project['forwarded_to_ops_at'])): ?>
                                    on <?= format_datetime((string) $project['forwarded_to_ops_at']) ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" disabled>Forwarded to Operations</button>
                        </div>
                    <?php else: ?>
                        <div class="small text-secondary">
                            Issue the JOM number above first to unlock forwarding to Operations.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Operations Handoff Package & Marketing Document Checklist -->
    <div class="col-12 col-xl-7">
        <?php if ($isForwardedToOps || !empty($isOperationsUser)): ?>
            <!-- Operations Handoff Package (JOM Number, SLD & Marketing BOM) -->
            <div id="operations-handoff-package" class="bms-card p-4 mb-4 border-start border-4 border-info">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge badge-soft-info">Operations Handoff Package</span>
                            <h3 class="h6 fw-bold mb-0">Authorized Operations Deliverables (JOM, SLD &amp; Marketing BOM)</h3>
                        </div>
                        <div class="text-secondary small">
                            Official package forwarded by Admin Finance to Operations. (Marketing BOM is the initial Marketing Bill of Materials, distinct from Actual BOM.)
                        </div>
                    </div>
                    <?php if (!empty($project['jom_number'])): ?>
                        <span class="badge bg-primary font-monospace fs-6"><?= e($project['jom_number']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Handoff Item</th>
                                <th>Document / Identifier</th>
                                <th>Version &amp; Size</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-semibold small">1. JOM Number</td>
                                <td>
                                    <span class="badge bg-primary font-monospace"><?= e($project['jom_number'] ?: 'N/A') ?></span>
                                    <span class="text-secondary small ms-1">Issued by <?= e($project['jom_issuer_name'] ?: 'Admin Finance') ?></span>
                                </td>
                                <td class="small text-secondary"><?= format_datetime($project['jom_issued_at'] ?? null) ?></td>
                                <td class="text-end"><span class="badge badge-soft-success">Assigned</span></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold small">2. SLD (Single Line Diagram)</td>
                                <td class="small">
                                    <?php if ($handoffSld !== null): ?>
                                        <span class="fw-medium"><?= e($handoffSld['original_filename']) ?></span>
                                    <?php else: ?>
                                        <span class="text-danger">Not Available</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-secondary">
                                    <?php if ($handoffSld !== null): ?>
                                        v<?= (int) $handoffSld['version_number'] ?> &bull; <?= format_bytes((int) $handoffSld['file_size']) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($handoffSld !== null && !empty($canDownloadDocument)): ?>
                                        <div class="bms-action-group">
                                            <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $handoffSld['id'] . '/view') ?>"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="bms-action-icon bms-action-icon--primary"
                                               title="View SLD"
                                               aria-label="View SLD">
                                                <?= bms_icon('eye') ?>
                                            </a>
                                            <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $handoffSld['id'] . '/download') ?>"
                                               class="bms-action-icon bms-action-icon--secondary"
                                               title="Download SLD"
                                               aria-label="Download SLD">
                                                <?= bms_icon('download') ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-semibold small">3. Marketing BOM</td>
                                <td class="small">
                                    <?php if ($handoffBom !== null): ?>
                                        <span class="fw-medium"><?= e($handoffBom['original_filename']) ?></span>
                                        <div class="text-secondary" style="font-size: 0.72rem;">Initial Marketing Bill of Materials (not Actual BOM)</div>
                                    <?php else: ?>
                                        <span class="text-danger">Not Available</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-secondary">
                                    <?php if ($handoffBom !== null): ?>
                                        v<?= (int) $handoffBom['version_number'] ?> &bull; <?= format_bytes((int) $handoffBom['file_size']) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($handoffBom !== null && !empty($canDownloadDocument)): ?>
                                        <div class="bms-action-group">
                                            <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $handoffBom['id'] . '/view') ?>"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="bms-action-icon bms-action-icon--primary"
                                               title="View Marketing BOM"
                                               aria-label="View Marketing BOM">
                                                <?= bms_icon('eye') ?>
                                            </a>
                                            <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $handoffBom['id'] . '/download') ?>"
                                               class="bms-action-icon bms-action-icon--secondary"
                                               title="Download Marketing BOM"
                                               aria-label="Download Marketing BOM">
                                                <?= bms_icon('download') ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- End-to-End Linked Document Workflow Bar (Section 6) -->
        <div id="linked-document-workflow-bar" class="bms-card p-3 mb-4 bg-light border">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-bold text-dark text-uppercase tracking-wider">
                    &#128279; End-to-End Traceability &amp; Linked Documents
                </span>
                <span class="small text-secondary">
                    Project &rarr; JOM &rarr; PR &rarr; PO &rarr; Terms &rarr; Delivery &rarr; Stock In &rarr; Inventory
                </span>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center small">
                <!-- 1. Project -->
                <div class="p-2 rounded bg-white border flex-fill">
                    <div class="text-secondary" style="font-size: 0.7rem;">1. Project</div>
                    <strong class="text-dark font-monospace"><?= e($project['project_code']) ?></strong>
                    <div><span class="badge <?= project_status_badge_class((string) $project['status']) ?>" style="font-size: 0.68rem;"><?= e($project['status']) ?></span></div>
                </div>

                <div class="text-secondary fw-bold">&rarr;</div>

                <!-- 2. JOM -->
                <div class="p-2 rounded bg-white border flex-fill">
                    <div class="text-secondary" style="font-size: 0.7rem;">2. JOM Number</div>
                    <?php if (!empty($project['jom_number'])): ?>
                        <strong class="text-primary font-monospace"><?= e($project['jom_number']) ?></strong>
                        <div><span class="badge badge-soft-success" style="font-size: 0.68rem;">Issued</span></div>
                    <?php else: ?>
                        <span class="text-muted font-monospace">Pending JOM</span>
                    <?php endif; ?>
                </div>

                <div class="text-secondary fw-bold">&rarr;</div>

                <!-- 3. PR -->
                <div class="p-2 rounded bg-white border flex-fill">
                    <div class="text-secondary" style="font-size: 0.7rem;">3. Purchase Request</div>
                    <?php if ($purchaseRequest !== null): ?>
                        <a href="#operations-pr-panel" class="fw-bold font-monospace text-primary text-decoration-none"><?= e($purchaseRequest['pr_number']) ?></a>
                        <div><span class="badge <?= pr_status_badge_class((string) $purchaseRequest['status']) ?>" style="font-size: 0.68rem;"><?= e($purchaseRequest['status']) ?></span></div>
                    <?php else: ?>
                        <span class="text-muted font-monospace">Pending PR</span>
                    <?php endif; ?>
                </div>

                <div class="text-secondary fw-bold">&rarr;</div>

                <!-- 4. PO -->
                <div class="p-2 rounded bg-white border flex-fill">
                    <div class="text-secondary" style="font-size: 0.7rem;">4. Purchase Order</div>
                    <?php if (!empty($purchaseOrders)): ?>
                        <a href="#purchase-order-panel" class="fw-bold font-monospace text-primary text-decoration-none">
                            <?= e(implode(', ', array_column($purchaseOrders, 'po_number'))) ?>
                        </a>
                        <div><span class="badge <?= po_status_badge_class((string) $purchaseOrders[0]['status']) ?>" style="font-size: 0.68rem;"><?= e($purchaseOrders[0]['status']) ?></span></div>
                    <?php else: ?>
                        <span class="text-muted font-monospace">Pending PO</span>
                    <?php endif; ?>
                </div>

                <div class="text-secondary fw-bold">&rarr;</div>

                <!-- 5. Terms of Payment -->
                <div class="p-2 rounded bg-white border flex-fill">
                    <div class="text-secondary" style="font-size: 0.7rem;">5. Payment Terms</div>
                    <?php
                    $totalTermsCount = 0;
                    foreach ($purchaseOrders as $poItemRow) {
                        $totalTermsCount += count($poItemRow['payment_terms'] ?? []);
                    }
                    ?>
                    <?php if ($totalTermsCount > 0): ?>
                        <a href="#purchase-order-panel" class="fw-bold text-dark text-decoration-none"><?= $totalTermsCount ?> Milestone(s)</a>
                        <div><span class="badge badge-soft-info" style="font-size: 0.68rem;">Configured</span></div>
                    <?php else: ?>
                        <span class="text-muted font-monospace">On PO Creation</span>
                    <?php endif; ?>
                </div>

                <div class="text-secondary fw-bold">&rarr;</div>

                <!-- 6. Supplier Delivery -->
                <div class="p-2 rounded bg-white border flex-fill">
                    <div class="text-secondary" style="font-size: 0.7rem;">6. Supplier Delivery</div>
                    <?php if (!empty($supplierDeliveries)): ?>
                        <a href="#supplier-deliveries-panel" class="fw-bold font-monospace text-dark text-decoration-none">
                            <?= count($supplierDeliveries) ?> DR(s)
                        </a>
                        <div><span class="badge <?= delivery_status_badge_class((string) $supplierDeliveries[0]['status']) ?>" style="font-size: 0.68rem;"><?= e($supplierDeliveries[0]['status']) ?></span></div>
                    <?php else: ?>
                        <span class="text-muted font-monospace">Pending DR</span>
                    <?php endif; ?>
                </div>

                <div class="text-secondary fw-bold">&rarr;</div>

                <!-- 7. Stock In & Inventory Units -->
                <div class="p-2 rounded bg-white border flex-fill">
                    <div class="text-secondary" style="font-size: 0.7rem;">7. Stock In &amp; Units</div>
                    <?php if (!empty($inventoryUnits)): ?>
                        <a href="#inventory-stockin-panel" class="fw-bold text-success text-decoration-none">
                            <?= count($inventoryUnits) ?> Unit(s) In Stock
                        </a>
                        <div><span class="badge badge-soft-success" style="font-size: 0.68rem;">Inventory Active</span></div>
                    <?php else: ?>
                        <span class="text-muted font-monospace">0 Units In Stock</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
        $showOperationsPrPanel = $isForwardedToOps
            || $purchaseRequest !== null
            || !empty($canPreparePr)
            || !empty($canSubmitPr)
            || !empty($canReviewPr)
            || !empty($isOperationsUser)
            || !empty($isOpsFinanceUser);
        ?>
        <?php if ($showOperationsPrPanel): ?>
            <!-- Operations Purchase Request (PR) Panel -->
            <div id="operations-pr-panel" class="bms-card p-4 mb-4 border-start border-4 border-primary">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge badge-soft-primary">Operations Module</span>
                            <h3 class="h6 fw-bold mb-0">Operations Purchase Request (PR)</h3>
                        </div>
                        <div class="text-secondary small">
                            Operations prepares requested equipment and material items with specifications based on the verified SLD and Marketing BOM, then forwards the PR to OPS Finance.
                        </div>
                    </div>
                    <div>
                        <?php if ($purchaseRequest !== null): ?>
                            <span class="badge <?= pr_status_badge_class((string) $purchaseRequest['status']) ?> fs-6">
                                <?= e($purchaseRequest['status']) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge badge-soft-warning fs-6">Awaiting PR Preparation</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Returned for Revision / Corrections Banner for PR -->
                <?php if ($purchaseRequest !== null && in_array(($purchaseRequest['status'] ?? ''), ['Returned for Corrections', 'Returned for Revision'], true)): ?>
                    <div class="alert alert-danger border-danger border-opacity-50 mb-3 small">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong>&#9888; Purchase Request Returned for Revision</strong>
                            <span class="text-secondary">
                                <?= format_datetime($purchaseRequest['returned_at'] ?? null) ?>
                                <?php if (!empty($purchaseRequest['returner_name'])): ?>
                                    by <?= e($purchaseRequest['returner_name']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="bg-white p-2 rounded border border-danger border-opacity-25 text-dark" style="white-space: pre-line;"><?= e($purchaseRequest['return_reason'] ?: 'Please revise the requested items or specifications per notes.') ?></div>
                        <div class="mt-2 text-dark">Revise the line items or remarks in the form below and click <strong>Save &amp; Submit PR to OPS Finance</strong> to resubmit.</div>
                    </div>
                <?php endif; ?>

                <!-- Approved Banner for PR -->
                <?php if ($purchaseRequest !== null && in_array(($purchaseRequest['status'] ?? ''), ['Approved', 'Converted to PO', 'Partially Ordered', 'Fully Ordered'], true)): ?>
                    <div class="alert alert-success border-success border-opacity-50 mb-3 small">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                            <div class="d-flex align-items-center gap-2">
                                <strong class="fs-6">&#10003; Purchase Request Formally Approved</strong>
                                <span class="badge bg-success">Eligible for PO Creation</span>
                            </div>
                            <span class="text-secondary">
                                <?= format_datetime($purchaseRequest['approved_at'] ?? null) ?>
                                <?php if (!empty($purchaseRequest['approver_name'])): ?>
                                    by <?= e($purchaseRequest['approver_name']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if (!empty($purchaseRequest['approval_notes'])): ?>
                            <div class="bg-white p-2 rounded border border-success border-opacity-25 text-dark mb-1" style="white-space: pre-line;"><?= e($purchaseRequest['approval_notes']) ?></div>
                        <?php endif; ?>
                        <div class="text-muted" style="font-size: 0.76rem;">Approved quantities are locked and authorized for Purchase Order generation in Admin Finance.</div>
                    </div>
                <?php endif; ?>

                <!-- Rejected Banner for PR -->
                <?php if ($purchaseRequest !== null && ($purchaseRequest['status'] ?? '') === 'Rejected'): ?>
                    <div class="alert alert-danger border-danger border-opacity-50 mb-3 small">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong>&times; Purchase Request Rejected</strong>
                            <span class="text-secondary">
                                <?= format_datetime($purchaseRequest['rejected_at'] ?? null) ?>
                                <?php if (!empty($purchaseRequest['rejecter_name'])): ?>
                                    by <?= e($purchaseRequest['rejecter_name']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="bg-white p-2 rounded border border-danger border-opacity-25 text-dark" style="white-space: pre-line;"><?= e($purchaseRequest['rejection_reason'] ?: 'Purchase Request has been rejected.') ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($purchaseRequest !== null): ?>
                    <!-- PR Metadata Summary -->
                    <div class="row g-3 small bg-light p-3 rounded border mb-3">
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">PR Number</div>
                            <div class="font-monospace fw-bold text-primary fs-6"><?= e($purchaseRequest['pr_number']) ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">PR Date</div>
                            <div><?= format_datetime($purchaseRequest['pr_date'] ?? null) ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Requesting Department</div>
                            <div>Operations (<?= e($purchaseRequest['requesting_department_code'] ?? 'OPS') ?>)</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Receiving Department</div>
                            <div>OPS Finance (<?= e($purchaseRequest['receiving_department_code'] ?? 'OPFIN') ?>)</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Requested By</div>
                            <div><?= e($purchaseRequest['requester_name'] ?? '—') ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Required Delivery Date</div>
                            <div><?= !empty($purchaseRequest['required_delivery_date']) ? format_datetime($purchaseRequest['required_delivery_date']) : '<span class="text-secondary">Not specified</span>' ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Linked SLD Document</div>
                            <?php $sldId = (int) ($purchaseRequest['sld_document_id'] ?? $project['ops_sld_document_id'] ?? 0); ?>
                            <?php if ($sldId > 0): ?>
                                <div>
                                    <a href="<?= url('/projects/' . $projectId . '/documents/' . $sldId . '/view') ?>" target="_blank" class="fw-semibold text-primary text-decoration-none">
                                        <?= e($purchaseRequest['sld_document_filename'] ?? 'SLD Document') ?> &nearr;
                                    </a>
                                </div>
                            <?php else: ?>
                                <span class="text-secondary">None</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Linked Marketing BOM</div>
                            <?php $bomId = (int) ($purchaseRequest['marketing_bom_document_id'] ?? $project['ops_bom_document_id'] ?? 0); ?>
                            <?php if ($bomId > 0): ?>
                                <div>
                                    <a href="<?= url('/projects/' . $projectId . '/documents/' . $bomId . '/view') ?>" target="_blank" class="fw-semibold text-primary text-decoration-none">
                                        <?= e($purchaseRequest['marketing_bom_filename'] ?? 'Marketing BOM') ?> &nearr;
                                    </a>
                                </div>
                            <?php else: ?>
                                <span class="text-secondary">None</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Linked Actual BOM</div>
                            <?php if (!empty($purchaseRequest['actual_bom_id']) || $actualBom !== null): ?>
                                <div>
                                    <a href="#ops-finance-actual-bom-panel" class="fw-semibold text-success text-decoration-none">
                                        <?= e($purchaseRequest['actual_bom_number'] ?? ($actualBom['actual_bom_number'] ?? 'Actual BOM')) ?> &darr;
                                    </a>
                                </div>
                            <?php else: ?>
                                <span class="text-secondary">Pending Actual BOM</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">PR Attachment Document</div>
                            <?php if (!empty($purchaseRequest['attachment_document_id'])): ?>
                                <div>
                                    <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $purchaseRequest['attachment_document_id'] . '/view') ?>"
                                       target="_blank" class="fw-semibold text-primary text-decoration-none">
                                        <?= e($purchaseRequest['attachment_filename'] ?? 'PR Attachment') ?> &nearr;
                                    </a>
                                    <span class="text-secondary ms-1">(<?= format_bytes((int) ($purchaseRequest['attachment_file_size'] ?? 0)) ?>)</span>
                                </div>
                            <?php else: ?>
                                <span class="text-secondary">None</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Submitted To Approver</div>
                            <div>
                                <?= format_datetime($purchaseRequest['submitted_at'] ?? null) ?>
                                <?php if (!empty($purchaseRequest['submitter_name'])): ?>
                                    <span class="text-secondary">(<?= e($purchaseRequest['submitter_name']) ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Reviewed / Approved By</div>
                            <div>
                                <?php if (!empty($purchaseRequest['approver_name'])): ?>
                                    <span class="text-success fw-semibold">&#10003; <?= e($purchaseRequest['approver_name']) ?></span>
                                    <span class="text-secondary">(<?= format_datetime($purchaseRequest['approved_at']) ?>)</span>
                                <?php elseif (!empty($purchaseRequest['reviewer_name'])): ?>
                                    <span><?= e($purchaseRequest['reviewer_name']) ?></span>
                                    <span class="text-secondary">(<?= format_datetime($purchaseRequest['reviewed_at']) ?>)</span>
                                <?php else: ?>
                                    <span class="text-secondary">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($purchaseRequest['purpose'])): ?>
                            <div class="col-12">
                                <div class="text-secondary fw-semibold">Purpose / Justification</div>
                                <div class="text-dark bg-white p-2 rounded border" style="white-space: pre-line;"><?= e($purchaseRequest['purpose']) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($purchaseRequest['remarks'])): ?>
                            <div class="col-12">
                                <div class="text-secondary fw-semibold">General Remarks</div>
                                <div class="text-dark bg-white p-2 rounded border" style="white-space: pre-line;"><?= e($purchaseRequest['remarks']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Line Items Table (Read-Only Overview) -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h4 class="h6 fw-bold mb-0">Requested Equipment &amp; Material Items (<?= count($purchaseRequest['items'] ?? []) ?>)</h4>
                            <?php if (!empty($canPreparePr)): ?>
                                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" data-bs-toggle="collapse" data-bs-target="#editPrFormCollapse" aria-expanded="false">
                                    Edit PR Line Items &darr;
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th>Item Description</th>
                                        <th>Model / Part #</th>
                                        <th>Technical Specification</th>
                                        <th style="width: 80px;" class="text-end">Qty</th>
                                        <th style="width: 60px;">UOM</th>
                                        <th>Purpose</th>
                                        <th style="width: 80px;" class="text-end">Appr. Qty</th>
                                        <th style="width: 80px;" class="text-end">Ord. Qty</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($purchaseRequest['items'])): ?>
                                        <tr>
                                            <td colspan="10" class="text-center text-secondary py-3">No line items added yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($purchaseRequest['items'] as $pItem): ?>
                                            <tr>
                                                <td class="text-center font-monospace"><?= (int) $pItem['line_no'] ?></td>
                                                <td class="fw-semibold"><?= e($pItem['item_description']) ?></td>
                                                <td><?= !empty($pItem['model_part_number']) ? '<span class="font-monospace small">' . e($pItem['model_part_number']) . '</span>' : '<span class="text-secondary">—</span>' ?></td>
                                                <td style="white-space: pre-line;"><?= e($pItem['item_specification']) ?></td>
                                                <td class="text-end font-monospace"><?= number_format((float) $pItem['quantity'], 2) ?></td>
                                                <td><span class="badge bg-light text-dark border"><?= e($pItem['unit_of_measure']) ?></span></td>
                                                <td><?= !empty($pItem['purpose']) ? e($pItem['purpose']) : '<span class="text-secondary">—</span>' ?></td>
                                                <td class="text-end font-monospace"><?= $pItem['approved_quantity'] !== null ? number_format((float) $pItem['approved_quantity'], 2) : '<span class="text-secondary">—</span>' ?></td>
                                                <td class="text-end font-monospace">
                                                    <?php $ordQ = (float) ($pItem['ordered_quantity'] ?? 0); ?>
                                                    <?php if ($ordQ > 0): ?>
                                                        <span class="badge badge-soft-info"><?= number_format($ordQ, 2) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-secondary">0.00</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-secondary"><?= e($pItem['remarks'] ?? '—') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PR Revision & Lifecycle Audit Trail -->
                    <?php if (!empty($purchaseRequest['histories'])): ?>
                        <div class="mb-3">
                            <button class="btn btn-outline-secondary btn-sm py-0 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#prHistoriesCollapse" aria-expanded="false">
                                View PR Revision &amp; Approval History (<?= count($purchaseRequest['histories']) ?>) &darr;
                            </button>
                            <div class="collapse mt-2" id="prHistoriesCollapse">
                                <div class="table-responsive bg-light p-2 rounded border">
                                    <table class="table table-sm table-bordered bg-white align-middle mb-0 small">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date &amp; Time</th>
                                                <th>Action</th>
                                                <th>User</th>
                                                <th>Department</th>
                                                <th>To Status</th>
                                                <th>Remarks / Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($purchaseRequest['histories'] as $h): ?>
                                                <tr>
                                                    <td><?= format_datetime($h['created_at']) ?></td>
                                                    <td><span class="badge badge-soft-secondary font-monospace"><?= e($h['action']) ?></span></td>
                                                    <td class="fw-semibold"><?= e($h['user_name'] ?? $h['username']) ?></td>
                                                    <td><?= e($h['department_code'] ?? '—') ?></td>
                                                    <td><span class="badge <?= pr_status_badge_class((string) $h['to_status']) ?>"><?= e($h['to_status']) ?></span></td>
                                                    <td style="white-space: pre-line;"><?= e($h['remarks'] ?? '—') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Approver Review / Approval / Return / Rejection Actions on Submitted PR -->
                <?php if ((!empty($canReviewPr) || !empty($canApprovePr) || !empty($canRejectPr)) && $purchaseRequest !== null && in_array(($purchaseRequest['status'] ?? ''), ['Submitted to OPS Finance', 'Submitted', 'Under OPS Finance Review', 'Under Review', 'Actual BOM Submitted'], true)): ?>
                    <div class="p-3 rounded border bg-light mb-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div>
                                <span class="badge badge-soft-primary me-1">Approver Action</span>
                                <strong class="small text-dark">Review &amp; Authorize Purchase Request</strong>
                                <span class="text-secondary small ms-1">— OPS Finance &amp; Admin Finance authorization workflow.</span>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <?php if (in_array(($purchaseRequest['status'] ?? ''), ['Submitted to OPS Finance', 'Submitted'], true)): ?>
                                <form method="POST" action="<?= url('/projects/' . $projectId . '/purchase-request/review') ?>" class="m-0">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-warning btn-sm fw-semibold">
                                        Mark Under Review
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (!empty($canApprovePr)): ?>
                                <button type="button" class="btn btn-success btn-sm fw-semibold" data-bs-toggle="collapse" data-bs-target="#approvePrCollapse" aria-expanded="false">
                                    &#10003; Approve PR &darr;
                                </button>
                            <?php endif; ?>

                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="collapse" data-bs-target="#returnPrCollapse" aria-expanded="false">
                                Return PR for Revision &darr;
                            </button>

                            <?php if (!empty($canRejectPr)): ?>
                                <button type="button" class="btn btn-danger btn-sm fw-semibold" data-bs-toggle="collapse" data-bs-target="#rejectPrCollapse" aria-expanded="false">
                                    &times; Reject PR &darr;
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Return Collapse Form -->
                        <div class="collapse mt-3" id="returnPrCollapse">
                            <form method="POST" action="<?= url('/projects/' . $projectId . '/purchase-request/return') ?>" class="border p-3 rounded bg-white" data-confirm="Return this Purchase Request to Operations for revision?">
                                <?= csrf_field() ?>
                                <label for="return_pr_reason" class="form-label small fw-semibold text-danger">Reason for Return / Revision Notes <span class="text-danger">*</span></label>
                                <textarea id="return_pr_reason" name="return_reason" rows="2" class="form-control form-control-sm mb-2" placeholder="Explain what items, specifications, or quantities need revision..." required></textarea>
                                <button type="submit" class="btn btn-danger btn-sm fw-semibold">Submit Return for Revision</button>
                            </form>
                        </div>

                        <!-- Approve Collapse Form -->
                        <?php if (!empty($canApprovePr)): ?>
                            <div class="collapse mt-3" id="approvePrCollapse">
                                <form method="POST" action="<?= url('/projects/' . $projectId . '/purchase-request/approve') ?>" class="border p-3 rounded bg-white" data-confirm="Formally approve this Purchase Request? Approved items will become eligible for Purchase Order (PO) creation.">
                                    <?= csrf_field() ?>
                                    <div class="mb-2">
                                        <strong class="text-success small">&#10003; Purchase Request Approval</strong>
                                        <div class="text-secondary small">Approving this PR locks approved quantities and authorizes Admin Finance to proceed with Purchase Order issuance.</div>
                                    </div>
                                    <label for="approval_notes" class="form-label small fw-semibold mb-1">Approval Notes / Remarks (Optional)</label>
                                    <textarea id="approval_notes" name="approval_notes" rows="2" class="form-control form-control-sm mb-2" placeholder="e.g. Approved per project budget and verified technical specs."></textarea>
                                    <button type="submit" class="btn btn-success btn-sm fw-semibold">&#10003; Confirm Approval of PR</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- Reject Collapse Form -->
                        <?php if (!empty($canRejectPr)): ?>
                            <div class="collapse mt-3" id="rejectPrCollapse">
                                <form method="POST" action="<?= url('/projects/' . $projectId . '/purchase-request/reject') ?>" class="border p-3 rounded bg-white" data-confirm="Reject this Purchase Request? This will terminate this PR.">
                                    <?= csrf_field() ?>
                                    <label for="reject_pr_reason" class="form-label small fw-semibold text-danger">Rejection Reason <span class="text-danger">*</span></label>
                                    <textarea id="reject_pr_reason" name="rejection_reason" rows="2" class="form-control form-control-sm mb-2" placeholder="Document the reason for rejecting this Purchase Request..." required></textarea>
                                    <button type="submit" class="btn btn-danger btn-sm fw-semibold">&times; Confirm Rejection of PR</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Operations PR Preparation / Edit Form -->
                <?php if (!empty($canPreparePr)): ?>
                    <?php
                    $isPrCollapsed = $purchaseRequest !== null && in_array(($purchaseRequest['status'] ?? ''), ['Draft', 'Returned for Corrections', 'Returned for Revision'], true) && count($purchaseRequest['items'] ?? []) > 0;
                    $prItemsToEdit = $purchaseRequest['items'] ?? [];
                    if ($prItemsToEdit === []) {
                        $prItemsToEdit = [
                            ['item_description' => '', 'model_part_number' => '', 'item_specification' => '', 'quantity' => 1, 'unit_of_measure' => 'pcs', 'purpose' => '', 'remarks' => ''],
                        ];
                    }
                    ?>
                    <div id="editPrFormCollapse" class="<?= $isPrCollapsed ? 'collapse' : '' ?>">
                        <form method="POST" action="<?= url('/projects/' . $projectId . '/purchase-request') ?>" enctype="multipart/form-data" class="border p-3 rounded bg-white">
                            <?= csrf_field() ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="h6 fw-bold mb-0 text-primary">
                                    <?= $purchaseRequest === null ? 'Prepare Purchase Request (PR)' : 'Edit Purchase Request Items &amp; Details' ?>
                                </h4>
                                <span class="badge badge-soft-primary">Auto-retrieved from JOM <?= e($project['jom_number']) ?></span>
                            </div>

                            <!-- Automatic Project Context Banner -->
                            <div class="bg-light p-2 rounded border mb-3 small">
                                <div class="row g-2">
                                    <div class="col-md-3"><strong>Project:</strong> <?= e($project['project_name']) ?> (<?= e($project['project_code']) ?>)</div>
                                    <div class="col-md-3"><strong>Client:</strong> <?= e($project['client_name']) ?></div>
                                    <div class="col-md-3"><strong>JOM Number:</strong> <span class="badge bg-primary font-monospace"><?= e($project['jom_number']) ?></span></div>
                                    <div class="col-md-3">
                                        <strong>Linked Specs:</strong>
                                        <?= !empty($project['ops_sld_document_id']) ? '<span class="badge badge-soft-success">SLD Linked</span>' : '' ?>
                                        <?= !empty($project['ops_bom_document_id']) ? '<span class="badge badge-soft-success">BOM Linked</span>' : '' ?>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-12 col-md-3">
                                    <label for="pr_date" class="form-label small fw-semibold mb-1">Request Date <span class="text-danger">*</span></label>
                                    <input type="date" id="pr_date" name="pr_date" class="form-control form-control-sm" value="<?= e($purchaseRequest['pr_date'] ?? date('Y-m-d')) ?>" required>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label for="required_delivery_date" class="form-label small fw-semibold mb-1">Required Delivery Date (Opt.)</label>
                                    <input type="date" id="required_delivery_date" name="required_delivery_date" class="form-control form-control-sm" value="<?= e($purchaseRequest['required_delivery_date'] ?? '') ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="purpose" class="form-label small fw-semibold mb-1">Purpose / Justification (Opt.)</label>
                                    <input type="text" id="purpose" name="purpose" maxlength="500" class="form-control form-control-sm" value="<?= e($purchaseRequest['purpose'] ?? '') ?>" placeholder="e.g. Procurement of main inverters and balance of system components for client project">
                                </div>
                                <div class="col-12">
                                    <label for="pr_attachment" class="form-label small fw-semibold mb-1">PR Supporting Document (Optional)</label>
                                    <input type="file" id="pr_attachment" name="pr_attachment" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                    <div class="text-secondary" style="font-size: 0.72rem;">Optional signed PR voucher or technical schedule. Max <?= e((string) $maxUploadMb) ?> MB.</div>
                                </div>
                            </div>

                            <!-- Dynamic Items Table -->
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered align-middle mb-0" id="bmsPrItemsTable">
                                    <thead class="table-light small">
                                        <tr>
                                            <th style="width: 35px;">#</th>
                                            <th style="min-width: 170px;">Item Description <span class="text-danger">*</span></th>
                                            <th style="min-width: 130px;">Model / Part #</th>
                                            <th style="min-width: 200px;">Technical Specification <span class="text-danger">*</span></th>
                                            <th style="width: 85px;">Qty <span class="text-danger">*</span></th>
                                            <th style="width: 75px;">UOM</th>
                                            <th style="min-width: 120px;">Item Purpose</th>
                                            <th style="min-width: 100px;">Remarks</th>
                                            <th style="width: 40px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="bmsPrItemsTableBody">
                                        <?php $itemIdx = 0; ?>
                                        <?php foreach ($prItemsToEdit as $row): ?>
                                            <?php $itemIdx++; ?>
                                            <tr>
                                                <td class="text-center font-monospace small"><?= $itemIdx ?></td>
                                                <td>
                                                    <input type="text" name="item_description[]" class="form-control form-control-sm" value="<?= e($row['item_description'] ?? '') ?>" placeholder="e.g. Solar Inverter 50kW" required>
                                                </td>
                                                <td>
                                                    <input type="text" name="model_part_number[]" class="form-control form-control-sm font-monospace" value="<?= e($row['model_part_number'] ?? '') ?>" placeholder="e.g. SUN2000-50KTL">
                                                </td>
                                                <td>
                                                    <textarea name="item_specification[]" rows="2" class="form-control form-control-sm" placeholder="Detailed technical specs..." required><?= e($row['item_specification'] ?? '') ?></textarea>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0.01" name="quantity[]" class="form-control form-control-sm" value="<?= e((string) ($row['quantity'] ?? 1)) ?>" required>
                                                </td>
                                                <td>
                                                    <input type="text" name="unit_of_measure[]" class="form-control form-control-sm" value="<?= e($row['unit_of_measure'] ?? 'pcs') ?>" required>
                                                </td>
                                                <td>
                                                    <input type="text" name="item_purpose[]" class="form-control form-control-sm" value="<?= e($row['purpose'] ?? '') ?>" placeholder="Item purpose">
                                                </td>
                                                <td>
                                                    <input type="text" name="item_remarks[]" class="form-control form-control-sm" value="<?= e($row['remarks'] ?? '') ?>" placeholder="Optional note">
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-outline-danger btn-sm p-1 bms-remove-row" title="Remove line item">&times;</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="bmsAddPrRowBtn">
                                + Add Requested Item
                            </button>

                            <div class="mb-3">
                                <label for="pr_remarks" class="form-label small fw-semibold mb-1">Purchase Request General Remarks (Optional)</label>
                                <textarea id="pr_remarks" name="pr_remarks" rows="2" maxlength="2000" class="form-control form-control-sm" placeholder="Additional notes for OPS Finance / Approver..."><?= e($purchaseRequest['remarks'] ?? '') ?></textarea>
                            </div>

                            <div class="d-flex flex-wrap justify-content-end gap-2 pt-2 border-top">
                                <button type="submit" name="submit_action" value="draft" class="btn btn-outline-primary btn-sm fw-semibold">
                                    Save Draft PR
                                </button>
                                <button type="submit" name="submit_action" value="submit" class="btn btn-primary btn-sm fw-semibold" data-confirm="Submit this Purchase Request to OPS Finance? It will be forwarded to OPS Finance for review.">
                                    Save &amp; Submit PR to OPS Finance
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php
        $showOpsFinancePanel = $actualBom !== null
            || ($purchaseRequest !== null && !empty($purchaseRequest['submitted_at']))
            || !empty($canUploadActualBom)
            || !empty($canSubmitActualBom)
            || !empty($canReviewActualBom)
            || !empty($isOpsFinanceUser)
            || (!empty($isAdminFinanceUser) && $actualBom !== null);
        ?>
        <?php if ($showOpsFinancePanel): ?>
            <!-- OPS Finance Actual BOM & Admin Finance Review Panel -->
            <div id="ops-finance-actual-bom-panel" class="bms-card p-4 mb-4 border-start border-4 border-success">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge badge-soft-success">OPS Finance Module</span>
                            <h3 class="h6 fw-bold mb-0">Actual BOM with Equipment &amp; Material Specifications</h3>
                        </div>
                        <div class="text-secondary small">
                            OPS Finance uploads the Actual BOM with actual equipment/material specifications for Admin Finance review.
                            <strong class="text-dark">Separate from Marketing BOM</strong> — preserves all versions and never overwrites Marketing BOM.
                        </div>
                    </div>
                    <div>
                        <?php if ($actualBom !== null): ?>
                            <span class="badge <?= actual_bom_status_badge_class((string) $actualBom['status']) ?> fs-6">
                                <?= e($actualBom['status']) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge badge-soft-warning fs-6">Actual BOM Pending Upload</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Admin Finance Returned for Corrections Banner -->
                <?php if ($actualBom !== null && ($actualBom['status'] ?? '') === 'Returned for Corrections'): ?>
                    <div class="alert alert-danger border-danger border-opacity-50 mb-3 small">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong>&#9888; Actual BOM Returned by Admin Finance for Corrections</strong>
                            <span class="text-secondary">
                                <?= format_datetime($actualBom['returned_at'] ?? null) ?>
                                <?php if (!empty($actualBom['returner_name'])): ?>
                                    by <?= e($actualBom['returner_name']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="bg-white p-2 rounded border border-danger border-opacity-25 text-dark" style="white-space: pre-line;"><?= e($actualBom['return_reason'] ?: 'Please update the Actual BOM document and specifications.') ?></div>
                        <div class="mt-2 text-dark">Upload an updated Actual BOM document below (prior version v<?= (int) ($actualBom['version_number'] ?? 1) ?> remains preserved) and click <strong>Submit Actual BOM to Admin Finance</strong>.</div>
                    </div>
                <?php endif; ?>

                <!-- Admin Finance Accepted Banner -->
                <?php if ($actualBom !== null && ($actualBom['status'] ?? '') === 'Accepted'): ?>
                    <div class="alert alert-success border-success border-opacity-50 mb-3 small">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong>&#10003; Actual BOM with Specifications Reviewed &amp; Accepted by Admin Finance</strong>
                            <span class="text-secondary">
                                <?= format_datetime($actualBom['accepted_at'] ?? null) ?>
                                <?php if (!empty($actualBom['accepter_name'])): ?>
                                    by <?= e($actualBom['accepter_name']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if (!empty($actualBom['acceptance_notes'])): ?>
                            <div class="bg-white p-2 rounded border border-success border-opacity-25 text-dark" style="white-space: pre-line;"><?= e($actualBom['acceptance_notes']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($actualBom !== null): ?>
                    <!-- Actual BOM Summary Card -->
                    <div class="row g-3 small bg-light p-3 rounded border mb-3">
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Actual BOM Ref</div>
                            <div class="font-monospace fw-bold text-success fs-6"><?= e($actualBom['actual_bom_number']) ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Current Version</div>
                            <div>
                                <span class="badge bg-secondary font-monospace">v<?= (int) ($actualBom['version_number'] ?? 1) ?></span>
                                <span class="text-secondary small ms-1">(Marketing BOM preserved)</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Linked PR</div>
                            <div class="font-monospace fw-semibold"><?= e($actualBom['pr_number'] ?? '—') ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Uploaded By</div>
                            <div><?= e($actualBom['uploader_name'] ?? '—') ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Submitted To Admin Finance</div>
                            <div><?= format_datetime($actualBom['submitted_at'] ?? null) ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Admin Finance Received</div>
                            <div><?= format_datetime($actualBom['received_at'] ?? null) ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Admin Finance Review</div>
                            <div><?= format_datetime($actualBom['reviewed_at'] ?? null) ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary fw-semibold">Admin Finance Decision</div>
                            <div><span class="badge <?= actual_bom_status_badge_class((string) $actualBom['status']) ?>"><?= e($actualBom['status']) ?></span></div>
                        </div>

                        <!-- Documents Section -->
                        <div class="col-12 col-md-6">
                            <div class="text-secondary fw-semibold mb-1">Actual BOM Document File</div>
                            <?php if (!empty($actualBom['document_id'])): ?>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-semibold"><?= e($actualBom['document_filename'] ?? 'Actual BOM File') ?></span>
                                    <span class="text-secondary small">(v<?= (int) ($actualBom['document_version'] ?? 1) ?>, <?= format_bytes((int) ($actualBom['document_file_size'] ?? 0)) ?>)</span>
                                </div>
                                <div class="mt-1">
                                    <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $actualBom['document_id'] . '/view') ?>" target="_blank" class="text-primary text-decoration-none me-3">View Document &nearr;</a>
                                    <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $actualBom['document_id'] . '/download') ?>" class="text-secondary text-decoration-none">Download</a>
                                </div>
                            <?php else: ?>
                                <span class="text-secondary">No file attached</span>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-secondary fw-semibold mb-1">Supporting Document</div>
                            <?php if (!empty($actualBom['supporting_document_id'])): ?>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-semibold"><?= e($actualBom['supporting_filename'] ?? 'Supporting Document') ?></span>
                                    <span class="text-secondary small">(v<?= (int) ($actualBom['supporting_version'] ?? 1) ?>, <?= format_bytes((int) ($actualBom['supporting_file_size'] ?? 0)) ?>)</span>
                                </div>
                                <div class="mt-1">
                                    <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $actualBom['supporting_document_id'] . '/view') ?>" target="_blank" class="text-primary text-decoration-none me-3">View Supporting &nearr;</a>
                                    <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $actualBom['supporting_document_id'] . '/download') ?>" class="text-secondary text-decoration-none">Download</a>
                                </div>
                            <?php else: ?>
                                <span class="text-secondary">None</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($actualBom['remarks'])): ?>
                            <div class="col-12">
                                <div class="text-secondary fw-semibold mb-1">Remarks</div>
                                <div class="text-dark bg-white p-2 rounded border" style="white-space: pre-line;"><?= e($actualBom['remarks']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Preserved Actual BOM Version History -->
                    <?php $abHistory = $actualBom['document_history'] ?? []; ?>
                    <?php if (count($abHistory) > 1): ?>
                        <div class="mb-3 p-3 rounded border bg-light small">
                            <div class="fw-semibold text-secondary mb-2">Preserved Actual BOM Versions (All prior versions preserved):</div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered bg-white mb-0">
                                    <thead>
                                        <tr>
                                            <th>Version</th>
                                            <th>File Name</th>
                                            <th>Size</th>
                                            <th>Uploaded By</th>
                                            <th>Uploaded At</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($abHistory as $hist): ?>
                                            <tr>
                                                <td><span class="badge <?= !empty($hist['is_current']) ? 'bg-success' : 'badge-soft-secondary' ?>">v<?= (int) $hist['version_number'] ?><?= !empty($hist['is_current']) ? ' (Current)' : '' ?></span></td>
                                                <td><?= e($hist['original_filename']) ?></td>
                                                <td><?= format_bytes((int) $hist['file_size']) ?></td>
                                                <td><?= e($hist['uploader_name']) ?></td>
                                                <td><?= format_datetime($hist['created_at']) ?></td>
                                                <td class="text-end">
                                                    <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $hist['id'] . '/view') ?>" target="_blank" class="text-primary text-decoration-none me-2">View</a>
                                                    <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $hist['id'] . '/download') ?>" class="text-secondary text-decoration-none">Download</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Actual BOM Line Items Table -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h4 class="h6 fw-bold mb-0">Actual Equipment &amp; Material Specifications (<?= count($actualBom['items'] ?? []) ?>)</h4>
                            <?php if (!empty($canUploadActualBom)): ?>
                                <button type="button" class="btn btn-outline-success btn-sm py-0 px-2" data-bs-toggle="collapse" data-bs-target="#editActualBomCollapse" aria-expanded="false">
                                    Update Actual BOM &amp; Specs &darr;
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 45px;">#</th>
                                        <th>Item Description</th>
                                        <th>Actual Equipment / Material Specification</th>
                                        <th style="width: 90px;" class="text-end">Quantity</th>
                                        <th style="width: 70px;">UOM</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($actualBom['items'])): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-secondary py-3">No actual specification line items recorded yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($actualBom['items'] as $abItem): ?>
                                            <tr>
                                                <td class="text-center font-monospace"><?= (int) $abItem['line_no'] ?></td>
                                                <td class="fw-semibold"><?= e($abItem['item_description']) ?></td>
                                                <td style="white-space: pre-line;"><?= e($abItem['actual_specification']) ?></td>
                                                <td class="text-end font-monospace"><?= number_format((float) $abItem['quantity'], 2) ?></td>
                                                <td><span class="badge bg-light text-dark border"><?= e($abItem['unit_of_measure']) ?></span></td>
                                                <td class="text-secondary"><?= e($abItem['remarks'] ?? '—') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Admin Finance Review Section for Actual BOM -->
                <?php if (!empty($canReviewActualBom) && $actualBom !== null): ?>
                    <div class="p-3 rounded border bg-light mb-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div>
                                <span class="badge badge-soft-info me-1">Admin Finance Action</span>
                                <strong class="small text-dark">Review Actual BOM with Specifications</strong>
                                <span class="text-secondary small ms-1">— Review the uploaded Actual BOM and technical specifications submitted by OPS Finance.</span>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <?php if (($actualBom['status'] ?? '') === 'Submitted to Admin Finance'): ?>
                                <form method="POST" action="<?= url('/projects/' . $projectId . '/actual-bom/review') ?>" class="m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="decision" value="received">
                                    <button type="submit" class="btn btn-info btn-sm text-white fw-semibold">
                                        Mark as Received
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array(($actualBom['status'] ?? ''), ['Submitted to Admin Finance', 'Received'], true)): ?>
                                <form method="POST" action="<?= url('/projects/' . $projectId . '/actual-bom/review') ?>" class="m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="decision" value="under_review">
                                    <button type="submit" class="btn btn-warning btn-sm fw-semibold">
                                        Mark Under Review
                                    </button>
                                </form>
                            <?php endif; ?>

                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="collapse" data-bs-target="#returnActualBomCollapse" aria-expanded="false">
                                Return for Corrections &darr;
                            </button>

                            <button type="button" class="btn btn-success btn-sm fw-semibold" data-bs-toggle="collapse" data-bs-target="#acceptActualBomCollapse" aria-expanded="false">
                                &#10003; Accept Actual BOM &darr;
                            </button>
                        </div>

                        <!-- Return Collapse Form -->
                        <div class="collapse mt-3" id="returnActualBomCollapse">
                            <form method="POST" action="<?= url('/projects/' . $projectId . '/actual-bom/review') ?>" class="border p-3 rounded bg-white" data-confirm="Return this Actual BOM to OPS Finance for corrections?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="decision" value="return">
                                <label for="return_ab_reason" class="form-label small fw-semibold text-danger">Correction Notes / Reason for Return <span class="text-danger">*</span></label>
                                <textarea id="return_ab_reason" name="return_reason" rows="2" class="form-control form-control-sm mb-2" placeholder="Specify why the Actual BOM is being returned for corrections..." required></textarea>
                                <button type="submit" class="btn btn-danger btn-sm fw-semibold">Return Actual BOM to OPS Finance</button>
                            </form>
                        </div>

                        <!-- Accept Collapse Form -->
                        <div class="collapse mt-3" id="acceptActualBomCollapse">
                            <form method="POST" action="<?= url('/projects/' . $projectId . '/actual-bom/review') ?>" class="border p-3 rounded bg-white" data-confirm="Accept this Actual BOM with specifications?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="decision" value="accept">
                                <label for="accept_ab_notes" class="form-label small fw-semibold text-success">Acceptance Notes (Optional)</label>
                                <textarea id="accept_ab_notes" name="acceptance_notes" rows="2" class="form-control form-control-sm mb-2" placeholder="Actual BOM and equipment/material specifications verified and accepted..."></textarea>
                                <button type="submit" class="btn btn-success btn-sm fw-semibold">&#10003; Confirm Acceptance of Actual BOM</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- OPS Finance Actual BOM Upload / Preparation Form -->
                <?php if (!empty($canUploadActualBom)): ?>
                    <?php
                    $isAbCollapsed = $actualBom !== null && in_array(($actualBom['status'] ?? ''), ['Draft', 'Returned for Corrections'], true) && count($actualBom['items'] ?? []) > 0;
                    $abItemsToEdit = $actualBom['items'] ?? [];
                    if ($abItemsToEdit === [] && !empty($purchaseRequest['items'])) {
                        // Pre-populate from PR line items
                        foreach ($purchaseRequest['items'] as $pri) {
                            $abItemsToEdit[] = [
                                'pr_item_id' => $pri['id'],
                                'item_description' => $pri['item_description'],
                                'actual_specification' => $pri['item_specification'],
                                'quantity' => $pri['quantity'],
                                'unit_of_measure' => $pri['unit_of_measure'],
                                'remarks' => '',
                            ];
                        }
                    }
                    if ($abItemsToEdit === []) {
                        $abItemsToEdit = [
                            ['pr_item_id' => '', 'item_description' => '', 'actual_specification' => '', 'quantity' => 1, 'unit_of_measure' => 'pcs', 'remarks' => ''],
                        ];
                    }
                    ?>
                    <div id="editActualBomCollapse" class="<?= $isAbCollapsed ? 'collapse' : '' ?>">
                        <form method="POST" action="<?= url('/projects/' . $projectId . '/actual-bom') ?>" enctype="multipart/form-data" class="border p-3 rounded bg-white">
                            <?= csrf_field() ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="h6 fw-bold mb-0 text-success">
                                    <?= $actualBom === null ? 'Upload Actual BOM & Enter Specifications' : 'Update Actual BOM & Specifications' ?>
                                </h4>
                                <span class="badge badge-soft-success">Separate Actual BOM Record</span>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-12 col-md-6">
                                    <label for="actual_bom_file" class="form-label small fw-semibold mb-1">
                                        Actual BOM Document File <?= ($actualBom === null || empty($actualBom['document_id']) || ($actualBom['status'] ?? '') === 'Returned for Corrections') ? '<span class="text-danger">*</span>' : '(Optional replacement)' ?>
                                    </label>
                                    <input type="file" id="actual_bom_file" name="actual_bom_file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" <?= ($actualBom === null || empty($actualBom['document_id']) || ($actualBom['status'] ?? '') === 'Returned for Corrections') ? 'required' : '' ?>>
                                    <div class="text-secondary" style="font-size: 0.72rem;">Excel, PDF, or Word. Preserves all versions in project documents.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="supporting_file" class="form-label small fw-semibold mb-1">Supporting Technical Document (Optional)</label>
                                    <input type="file" id="supporting_file" name="supporting_file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                    <div class="text-secondary" style="font-size: 0.72rem;">Optional spec sheet, cut sheet, or technical schedule.</div>
                                </div>
                            </div>

                            <!-- Dynamic Actual BOM Items Table -->
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered align-middle mb-0" id="bmsAbItemsTable">
                                    <thead class="table-light small">
                                        <tr>
                                            <th style="width: 40px;">#</th>
                                            <th style="min-width: 180px;">Item Description <span class="text-danger">*</span></th>
                                            <th style="min-width: 240px;">Actual Equipment / Material Specification <span class="text-danger">*</span></th>
                                            <th style="width: 100px;">Qty <span class="text-danger">*</span></th>
                                            <th style="width: 90px;">UOM</th>
                                            <th style="min-width: 120px;">Remarks</th>
                                            <th style="width: 45px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="bmsAbItemsTableBody">
                                        <?php $abIdx = 0; ?>
                                        <?php foreach ($abItemsToEdit as $row): ?>
                                            <?php $abIdx++; ?>
                                            <tr>
                                                <td class="text-center font-monospace small"><?= $abIdx ?></td>
                                                <td>
                                                    <input type="text" name="ab_item_description[]" class="form-control form-control-sm" value="<?= e($row['item_description'] ?? '') ?>" placeholder="Item description" required>
                                                    <input type="hidden" name="ab_pr_item_id[]" value="<?= e((string) ($row['pr_item_id'] ?? '')) ?>">
                                                </td>
                                                <td>
                                                    <textarea name="ab_actual_specification[]" rows="2" class="form-control form-control-sm" placeholder="Actual equipment/material specifications..." required><?= e($row['actual_specification'] ?? '') ?></textarea>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0.01" name="ab_quantity[]" class="form-control form-control-sm" value="<?= e((string) ($row['quantity'] ?? 1)) ?>" required>
                                                </td>
                                                <td>
                                                    <input type="text" name="ab_unit_of_measure[]" class="form-control form-control-sm" value="<?= e($row['unit_of_measure'] ?? 'pcs') ?>" required>
                                                </td>
                                                <td>
                                                    <input type="text" name="ab_item_remarks[]" class="form-control form-control-sm" value="<?= e($row['remarks'] ?? '') ?>" placeholder="Optional note">
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-outline-danger btn-sm p-1 bms-remove-row" title="Remove line item">&times;</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="bmsAddAbRowBtn">
                                + Add Specification Item
                            </button>

                            <div class="mb-3">
                                <label for="actual_bom_remarks" class="form-label small fw-semibold mb-1">Actual BOM Remarks (Optional)</label>
                                <textarea id="actual_bom_remarks" name="actual_bom_remarks" rows="2" maxlength="2000" class="form-control form-control-sm" placeholder="Technical notes or adjustments for Admin Finance..."><?= e($actualBom['remarks'] ?? '') ?></textarea>
                            </div>

                            <div class="d-flex flex-wrap justify-content-end gap-2 pt-2 border-top">
                                <button type="submit" name="submit_action" value="draft" class="btn btn-outline-primary btn-sm fw-semibold">
                                    Save Draft Actual BOM
                                </button>
                                <button type="submit" name="submit_action" value="submit" class="btn btn-success btn-sm fw-semibold" data-confirm="Submit Actual BOM with specifications to Admin Finance?">
                                    Upload &amp; Submit Actual BOM to Admin Finance
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Phase 3: Purchase Order (PO) & Terms of Payment Panel -->
        <div id="purchase-order-panel" class="bms-card p-4 mb-4 border-start border-4 border-info">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge badge-soft-info">Admin Finance Purchasing</span>
                        <h3 class="h6 fw-bold mb-0">Purchase Orders (PO) &amp; Terms of Payment</h3>
                    </div>
                    <div class="text-secondary small">
                        Admin Finance creates official Purchase Orders referencing approved Purchase Requests with milestone Terms of Payment and supplier specifications.
                    </div>
                </div>
                <div>
                    <?php if (!empty($purchaseOrders)): ?>
                        <span class="badge <?= po_status_badge_class((string) $purchaseOrders[0]['status']) ?> fs-6">
                            <?= count($purchaseOrders) ?> PO(s) &bull; <?= e($purchaseOrders[0]['status']) ?>
                        </span>
                    <?php elseif (!empty($canCreatePo)): ?>
                        <span class="badge badge-soft-success fs-6">PR Approved &bull; Ready for PO Creation</span>
                    <?php else: ?>
                        <span class="badge badge-soft-warning fs-6">Awaiting PR Approval</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Existing Purchase Orders -->
            <?php if (!empty($purchaseOrders)): ?>
                <div class="mb-4">
                    <div class="small fw-bold text-dark text-uppercase tracking-wider mb-2">
                        Official Purchase Orders for this Project (<?= count($purchaseOrders) ?>)
                    </div>
                    <?php foreach ($purchaseOrders as $poIndex => $poItem): ?>
                        <div class="border rounded p-3 mb-3 bg-white">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pb-2 border-bottom mb-3">
                                <div>
                                    <span class="font-monospace fw-bold fs-6 text-primary me-2"><?= e($poItem['po_number']) ?></span>
                                    <span class="badge <?= po_status_badge_class((string) $poItem['status']) ?>"><?= e($poItem['status']) ?></span>
                                </div>
                                <div class="small text-secondary">
                                    PO Date: <strong><?= e($poItem['po_date']) ?></strong> &bull;
                                    Supplier: <strong class="text-dark"><?= e($poItem['supplier_name']) ?></strong> (<?= e($poItem['supplier_code']) ?>)
                                    <?php if (!empty($poItem['authorizer_name'])): ?>
                                        &bull; Authorized by: <strong><?= e($poItem['authorizer_name']) ?></strong> (<?= format_datetime($poItem['authorized_at'] ?? null) ?>)
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row g-2 mb-3 small">
                                <div class="col-6 col-md-3">
                                    <div class="text-secondary fw-semibold">PR Reference</div>
                                    <div class="font-monospace fw-bold text-dark"><?= e($poItem['pr_number']) ?></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-secondary fw-semibold">JOM Number</div>
                                    <div class="font-monospace fw-bold text-dark"><?= e($poItem['jom_number']) ?></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-secondary fw-semibold">Delivery Terms / Expected</div>
                                    <div class="text-dark"><?= e($poItem['delivery_terms'] ?: '—') ?> &bull; <?= e($poItem['expected_delivery_date'] ?: 'TBD') ?></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-secondary fw-semibold">Total Amount</div>
                                    <div class="fs-6 fw-bold text-success font-monospace"><?= e($poItem['currency'] ?? 'PHP') ?> <?= number_format((float) $poItem['total_amount'], 2) ?></div>
                                </div>
                            </div>

                            <!-- PO Line Items Table -->
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered align-middle mb-0 font-sans" style="font-size: 0.82rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;" class="text-center">#</th>
                                            <th style="min-width: 160px;">Description</th>
                                            <th style="width: 140px;">Model / Part #</th>
                                            <th style="min-width: 200px;">Technical Specification</th>
                                            <th style="width: 80px;" class="text-end">Qty Ordered</th>
                                            <th style="width: 60px;">UOM</th>
                                            <th style="width: 95px;" class="text-end">Unit Price</th>
                                            <th style="width: 105px;" class="text-end">Total Amount</th>
                                            <th style="width: 85px;" class="text-end">Qty Received</th>
                                            <th style="width: 85px;" class="text-end">Remaining</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($poItem['items'] as $poi): ?>
                                            <tr>
                                                <td class="text-center font-monospace text-secondary"><?= (int) $poi['line_no'] ?></td>
                                                <td class="fw-medium text-dark"><?= e($poi['item_description']) ?></td>
                                                <td class="font-monospace text-secondary small"><?= e($poi['model_part_number'] ?: '—') ?></td>
                                                <td class="text-secondary small"><?= nl2br(e($poi['item_specification'])) ?></td>
                                                <td class="text-end fw-bold font-monospace"><?= number_format((float) $poi['quantity_ordered'], 2) ?></td>
                                                <td class="text-secondary"><?= e($poi['unit_of_measure']) ?></td>
                                                <td class="text-end font-monospace"><?= number_format((float) $poi['unit_price'], 2) ?></td>
                                                <td class="text-end font-monospace fw-semibold"><?= number_format((float) $poi['total_amount'], 2) ?></td>
                                                <td class="text-end font-monospace <?= (float) $poi['quantity_received'] > 0 ? 'text-success fw-bold' : 'text-secondary' ?>">
                                                    <?= number_format((float) $poi['quantity_received'], 2) ?>
                                                </td>
                                                <td class="text-end font-monospace <?= (float) $poi['remaining_to_receive'] > 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                                                    <?= number_format((float) $poi['remaining_to_receive'], 2) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Terms of Payment Table for this PO -->
                            <div class="p-3 bg-light rounded border mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="small text-dark text-uppercase">&#128179; Terms of Payment Milestones</strong>
                                    <span class="small text-secondary">Milestone-based financial schedule attached to PO</span>
                                </div>
                                <?php if (!empty($poItem['payment_terms'])): ?>
                                    <div class="table-responsive mb-2">
                                        <table class="table table-sm table-white bg-white border mb-0 small">
                                            <thead>
                                                <tr>
                                                    <th>Payment Term / Arrangement</th>
                                                    <th class="text-end" style="width: 100px;">Percentage</th>
                                                    <th class="text-end" style="width: 120px;">Amount</th>
                                                    <th style="width: 110px;">Due Date</th>
                                                    <th style="width: 90px;">Status</th>
                                                    <th>Payment Remarks</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($poItem['payment_terms'] as $pt): ?>
                                                    <tr>
                                                        <td class="fw-semibold text-dark"><?= e($pt['payment_term']) ?></td>
                                                        <td class="text-end font-monospace"><?= (float) $pt['percentage'] > 0 ? number_format((float) $pt['percentage'], 1) . '%' : '—' ?></td>
                                                        <td class="text-end font-monospace fw-semibold"><?= number_format((float) $pt['amount'], 2) ?></td>
                                                        <td class="text-secondary"><?= e($pt['due_date'] ?: 'Upon Notice') ?></td>
                                                        <td><span class="badge badge-soft-info"><?= e($pt['status']) ?></span></td>
                                                        <td class="text-secondary"><?= e($pt['remarks'] ?: '—') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-secondary small fst-italic mb-2">No milestone payment terms configured. Default vendor terms apply: <?= e($poItem['payment_terms_summary'] ?: 'Net 30') ?>.</div>
                                <?php endif; ?>

                                <!-- Admin Finance Add Payment Term Form -->
                                <?php if (!empty($canAuthorizePo)): ?>
                                    <form method="POST" action="<?= url('/projects/' . $projectId . '/purchase-orders/' . (int) $poItem['id'] . '/payment-terms') ?>" class="row g-2 align-items-end pt-2 border-top">
                                        <?= csrf_field() ?>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label small fw-semibold mb-1">Milestone Arrangement</label>
                                            <input type="text" name="payment_term" class="form-control form-control-sm" placeholder="e.g. 30% Down Payment" required>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small fw-semibold mb-1">Percentage (%)</label>
                                            <input type="number" step="0.1" min="0" max="100" name="percentage" class="form-control form-control-sm" placeholder="30.0">
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small fw-semibold mb-1">Amount</label>
                                            <input type="number" step="0.01" min="0" name="amount" class="form-control form-control-sm" placeholder="0.00">
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small fw-semibold mb-1">Due Date</label>
                                            <input type="date" name="due_date" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-6 col-md-3 d-flex gap-1">
                                            <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Remarks">
                                            <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">+ Add Term</button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- Financial Authorization & Issuance Action Buttons -->
                            <div class="d-flex flex-wrap justify-content-end gap-2 pt-2 border-top">
                                <?php if (!empty($canAuthorizePo) && in_array($poItem['status'], ['Draft', 'Pending Approval'], true)): ?>
                                    <form method="POST" action="<?= url('/projects/' . $projectId . '/purchase-orders/' . (int) $poItem['id'] . '/authorize') ?>" class="d-inline" data-confirm="Authorize and approve Purchase Order <?= e($poItem['po_number']) ?>?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-success btn-sm fw-semibold">&#10003; Financially Authorize &amp; Approve PO</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!empty($canAuthorizePo) && $poItem['status'] === 'Approved'): ?>
                                    <form method="POST" action="<?= url('/projects/' . $projectId . '/purchase-orders/' . (int) $poItem['id'] . '/issue') ?>" class="d-inline" data-confirm="Officially issue Purchase Order <?= e($poItem['po_number']) ?> to supplier <?= e($poItem['supplier_name']) ?>?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-primary btn-sm fw-semibold">&rarr; Issue PO to Supplier</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- PO Creation Form for Admin Finance (Section 1) -->
            <?php if (!empty($canCreatePo) && $purchaseRequest !== null): ?>
                <div class="p-3 bg-light rounded border mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="h6 fw-bold mb-0 text-dark">&#43; Create Purchase Order from Approved PR</h4>
                            <div class="text-secondary small">Automatically populates approved items, models, and specifications from PR <?= e($purchaseRequest['pr_number']) ?>.</div>
                        </div>
                        <span class="badge badge-soft-primary">Admin Finance Purchasing</span>
                    </div>

                    <form method="POST" action="<?= url('/projects/' . $projectId . '/purchase-orders') ?>" data-confirm="Generate official Purchase Order from this approved PR?">
                        <?= csrf_field() ?>
                        <div class="row g-2 mb-3">
                            <div class="col-12 col-md-4">
                                <label for="po_supplier_id" class="form-label small fw-semibold mb-1">Approved Supplier <span class="text-danger">*</span></label>
                                <select id="po_supplier_id" name="supplier_id" class="form-select form-select-sm" required>
                                    <option value="">— Select Supplier —</option>
                                    <?php foreach ($suppliers as $sup): ?>
                                        <option value="<?= (int) $sup['id'] ?>"><?= e($sup['supplier_name']) ?> (<?= e($sup['supplier_code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label for="po_date" class="form-label small fw-semibold mb-1">PO Date <span class="text-danger">*</span></label>
                                <input type="date" id="po_date" name="po_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-6 col-md-2">
                                <label for="po_expected_delivery" class="form-label small fw-semibold mb-1">Expected Delivery</label>
                                <input type="date" id="po_expected_delivery" name="expected_delivery_date" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+14 days')) ?>">
                            </div>
                            <div class="col-6 col-md-2">
                                <label for="po_delivery_terms" class="form-label small fw-semibold mb-1">Delivery Terms</label>
                                <input type="text" id="po_delivery_terms" name="delivery_terms" class="form-control form-control-sm" value="FOB PCT Warehouse" placeholder="e.g. FOB Warehouse">
                            </div>
                            <div class="col-6 col-md-2">
                                <label for="po_payment_terms_summary" class="form-label small fw-semibold mb-1">Payment Arrangement</label>
                                <input type="text" id="po_payment_terms_summary" name="payment_terms_summary" class="form-control form-control-sm" value="30% DP, 70% Delivery" placeholder="e.g. Net 30">
                            </div>
                        </div>

                        <!-- Approved PR Items to Order -->
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered align-middle mb-0 bg-white small">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 160px;">Item Description</th>
                                        <th style="width: 130px;">Model / Part #</th>
                                        <th style="min-width: 180px;">Specification</th>
                                        <th style="width: 90px;" class="text-end">Approved Qty</th>
                                        <th style="width: 100px;">Qty to Order <span class="text-danger">*</span></th>
                                        <th style="width: 60px;">UOM</th>
                                        <th style="width: 110px;">Unit Price (PHP) <span class="text-danger">*</span></th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($purchaseRequest['items'] ?? []) as $pri): ?>
                                        <?php
                                        $apprQ = (float) ($pri['approved_quantity'] ?? $pri['quantity']);
                                        $ordQ = (float) ($pri['ordered_quantity'] ?? 0);
                                        $remQ = max(0.0, $apprQ - $ordQ);
                                        ?>
                                        <tr>
                                            <td>
                                                <input type="text" name="item_description[]" class="form-control form-control-sm" value="<?= e($pri['item_description']) ?>" required>
                                                <input type="hidden" name="pr_item_id[]" value="<?= (int) $pri['id'] ?>">
                                            </td>
                                            <td>
                                                <input type="text" name="model_part_number[]" class="form-control form-control-sm font-monospace" value="<?= e($pri['model_part_number'] ?? '') ?>" placeholder="Model">
                                            </td>
                                            <td>
                                                <textarea name="item_specification[]" rows="1" class="form-control form-control-sm"><?= e($pri['item_specification'] ?? '') ?></textarea>
                                            </td>
                                            <td class="text-end font-monospace text-secondary"><?= number_format($apprQ, 2) ?></td>
                                            <td>
                                                <input type="number" step="0.01" min="0.01" max="<?= $remQ > 0 ? $remQ : $apprQ ?>" name="quantity[]" class="form-control form-control-sm text-end font-monospace" value="<?= $remQ > 0 ? $remQ : $apprQ ?>" required>
                                            </td>
                                            <td>
                                                <input type="text" name="unit_of_measure[]" class="form-control form-control-sm" value="<?= e($pri['unit_of_measure'] ?? 'pcs') ?>" required>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" name="unit_price[]" class="form-control form-control-sm text-end font-monospace" value="0.00" required>
                                            </td>
                                            <td>
                                                <input type="text" name="item_remarks[]" class="form-control form-control-sm" placeholder="Notes">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Milestone Payment Terms (Section 3) -->
                        <div class="row g-2 mb-3">
                            <div class="col-12 col-md-6">
                                <label for="po_remarks" class="form-label small fw-semibold mb-1">General Purchase Order Remarks</label>
                                <textarea id="po_remarks" name="po_remarks" rows="2" class="form-control form-control-sm" placeholder="Terms, warranty conditions, payment arrangement..."></textarea>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold mb-1">Initial Milestone Terms of Payment</label>
                                <div class="row g-1">
                                    <div class="col-6">
                                        <input type="text" name="payment_term[]" class="form-control form-control-sm" value="Down Payment">
                                    </div>
                                    <div class="col-3">
                                        <input type="number" step="0.1" name="term_percentage[]" class="form-control form-control-sm" value="30.0" placeholder="%">
                                    </div>
                                    <div class="col-3">
                                        <input type="date" name="term_due_date[]" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-6 mt-1">
                                        <input type="text" name="payment_term[]" class="form-control form-control-sm" value="Full Delivery">
                                    </div>
                                    <div class="col-3 mt-1">
                                        <input type="number" step="0.1" name="term_percentage[]" class="form-control form-control-sm" value="70.0" placeholder="%">
                                    </div>
                                    <div class="col-3 mt-1">
                                        <input type="date" name="term_due_date[]" class="form-control form-control-sm">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                                &#128221; Create &amp; Link Purchase Order
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Phase 3: Supplier Delivery Tracking Panel -->
        <div id="supplier-deliveries-panel" class="bms-card p-4 mb-4 border-start border-4 border-warning">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge badge-soft-warning">Logistics &amp; Operations</span>
                        <h3 class="h6 fw-bold mb-0">Supplier Delivery Tracking</h3>
                    </div>
                    <div class="text-secondary small">
                        Track incoming physical shipments against Purchase Orders, verify quantities delivered vs accepted, and monitor remaining balances.
                    </div>
                </div>
                <div>
                    <span class="badge badge-soft-warning fs-6">
                        <?= count($supplierDeliveries) ?> Shipment(s) Logged
                    </span>
                </div>
            </div>

            <!-- Cumulative PO Delivery Tracking Summary (Section 4) -->
            <?php if (!empty($purchaseOrders)): ?>
                <div class="p-3 bg-light rounded border mb-3">
                    <div class="small fw-bold text-dark text-uppercase tracking-wider mb-2">
                        Cumulative PO Delivery Status
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered bg-white align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>PO Number</th>
                                    <th>Supplier</th>
                                    <th class="text-end">Total Ordered</th>
                                    <th class="text-end">Previously Received</th>
                                    <th class="text-end">Remaining to Receive</th>
                                    <th>Receiving Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchaseOrders as $poTrack): ?>
                                    <?php
                                    $totOrd = 0.0;
                                    $totRec = 0.0;
                                    foreach ($poTrack['items'] as $poi) {
                                        $totOrd += (float) $poi['quantity_ordered'];
                                        $totRec += (float) $poi['quantity_received'];
                                    }
                                    $totRem = max(0.0, $totOrd - $totRec);
                                    ?>
                                    <tr>
                                        <td class="font-monospace fw-bold text-primary"><?= e($poTrack['po_number']) ?></td>
                                        <td><?= e($poTrack['supplier_name']) ?></td>
                                        <td class="text-end font-monospace fw-bold"><?= number_format($totOrd, 2) ?></td>
                                        <td class="text-end font-monospace text-success fw-bold"><?= number_format($totRec, 2) ?></td>
                                        <td class="text-end font-monospace <?= $totRem > 0 ? 'text-danger fw-bold' : 'text-success' ?>"><?= number_format($totRem, 2) ?></td>
                                        <td><span class="badge <?= po_status_badge_class((string) $poTrack['status']) ?>"><?= e($poTrack['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Logged Supplier Deliveries List -->
            <?php if (!empty($supplierDeliveries)): ?>
                <div class="mb-4">
                    <div class="small fw-bold text-dark text-uppercase tracking-wider mb-2">
                        Recorded Supplier Deliveries &amp; Inspections (<?= count($supplierDeliveries) ?>)
                    </div>
                    <?php foreach ($supplierDeliveries as $delRow): ?>
                        <div class="border rounded p-3 mb-2 bg-white">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2 pb-2 border-bottom">
                                <div>
                                    <strong class="text-dark">Delivery Receipt (DR #): <span class="font-monospace text-primary"><?= e($delRow['delivery_receipt_no']) ?></span></strong>
                                    <span class="badge <?= delivery_status_badge_class((string) $delRow['status']) ?> ms-2"><?= e($delRow['status']) ?></span>
                                </div>
                                <div class="small text-secondary">
                                    Date: <strong><?= e($delRow['delivery_date']) ?></strong> &bull;
                                    PO: <span class="font-monospace fw-bold text-dark"><?= e($delRow['po_number']) ?></span> &bull;
                                    Received by: <strong><?= e($delRow['receiver_name']) ?></strong>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0 small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item Description</th>
                                            <th>Model</th>
                                            <th class="text-end" style="width: 90px;">Delivered</th>
                                            <th class="text-end" style="width: 90px;">Accepted</th>
                                            <th class="text-end" style="width: 90px;">Rejected</th>
                                            <th>Discrepancy / Rejection Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($delRow['items'] as $delItm): ?>
                                            <tr>
                                                <td class="fw-medium text-dark"><?= e($delItm['item_description']) ?></td>
                                                <td class="font-monospace text-secondary small"><?= e($delItm['model_part_number'] ?: '—') ?></td>
                                                <td class="text-end font-monospace"><?= number_format((float) $delItm['quantity_delivered'], 2) ?></td>
                                                <td class="text-end font-monospace text-success fw-bold"><?= number_format((float) $delItm['quantity_accepted'], 2) ?></td>
                                                <td class="text-end font-monospace <?= (float) $delItm['quantity_rejected'] > 0 ? 'text-danger fw-bold' : 'text-secondary' ?>"><?= number_format((float) $delItm['quantity_rejected'], 2) ?></td>
                                                <td class="text-secondary"><?= e($delItm['rejection_reason'] ?: '—') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Log New Supplier Delivery Form (Section 4) -->
            <?php
            $receivablePos = array_filter($purchaseOrders, static function ($p) {
                return in_array($p['status'], ['Issued to Supplier', 'Partially Received', 'Approved'], true);
            });
            ?>
            <?php if (!empty($canReceiveDelivery) && !empty($receivablePos)): ?>
                <div class="p-3 bg-light rounded border">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <strong class="h6 fw-bold mb-0 text-dark">&#128666; Record Supplier Delivery Receipt (DR)</strong>
                        <span class="badge badge-soft-warning">Logistics Intake</span>
                    </div>

                    <?php $primaryReceivablePo = reset($receivablePos); ?>
                    <form method="POST" action="<?= url('/projects/' . $projectId . '/purchase-orders/' . (int) $primaryReceivablePo['id'] . '/deliveries') ?>" data-confirm="Log physical supplier delivery?">
                        <?= csrf_field() ?>
                        <div class="row g-2 mb-3">
                            <div class="col-12 col-md-3">
                                <label for="del_dr_no" class="form-label small fw-semibold mb-1">Delivery Receipt (DR) # <span class="text-danger">*</span></label>
                                <input type="text" id="del_dr_no" name="delivery_receipt_no" class="form-control form-control-sm font-monospace" placeholder="e.g. DR-2026-9901" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label for="del_date" class="form-label small fw-semibold mb-1">Delivery Date <span class="text-danger">*</span></label>
                                <input type="date" id="del_date" name="delivery_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label for="del_carrier" class="form-label small fw-semibold mb-1">Carrier / Truck Plate</label>
                                <input type="text" id="del_carrier" name="carrier_name" class="form-control form-control-sm" placeholder="e.g. Lalamove / NBD-1234">
                            </div>
                            <div class="col-12 col-md-3">
                                <label for="del_notes" class="form-label small fw-semibold mb-1">Receiving Remarks</label>
                                <input type="text" id="del_notes" name="receiving_notes" class="form-control form-control-sm" placeholder="Condition upon arrival">
                            </div>
                        </div>

                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered bg-white align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item Description</th>
                                        <th style="width: 140px;">Model</th>
                                        <th style="width: 90px;" class="text-end">Ordered</th>
                                        <th style="width: 90px;" class="text-end">Prev. Received</th>
                                        <th style="width: 100px;">Delivered Qty <span class="text-danger">*</span></th>
                                        <th style="width: 100px;">Accepted Qty <span class="text-danger">*</span></th>
                                        <th style="width: 90px;">Rejected Qty</th>
                                        <th style="min-width: 140px;">Rejection / Discrepancy Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($primaryReceivablePo['items'] as $delPoItem): ?>
                                        <?php
                                        $ordQ = (float) $delPoItem['quantity_ordered'];
                                        $prevRec = (float) $delPoItem['quantity_received'];
                                        $remQ = max(0.0, $ordQ - $prevRec);
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="fw-semibold text-dark"><?= e($delPoItem['item_description']) ?></span>
                                                <input type="hidden" name="po_item_id[]" value="<?= (int) $delPoItem['id'] ?>">
                                            </td>
                                            <td class="font-monospace text-secondary small"><?= e($delPoItem['model_part_number'] ?: '—') ?></td>
                                            <td class="text-end font-monospace text-secondary"><?= number_format($ordQ, 2) ?></td>
                                            <td class="text-end font-monospace text-success"><?= number_format($prevRec, 2) ?></td>
                                            <td>
                                                <input type="number" step="0.01" min="0" max="<?= $remQ ?>" name="quantity_delivered[]" class="form-control form-control-sm text-end font-monospace" value="<?= $remQ ?>" required>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" max="<?= $remQ ?>" name="quantity_accepted[]" class="form-control form-control-sm text-end font-monospace" value="<?= $remQ ?>" required>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" name="quantity_rejected[]" class="form-control form-control-sm text-end font-monospace" value="0.00">
                                            </td>
                                            <td>
                                                <input type="text" name="rejection_reason[]" class="form-control form-control-sm" placeholder="If any items rejected">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-warning btn-sm fw-semibold">
                                &#128230; Record Delivery &amp; Inspection
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Phase 3: Stock In & Serialized Inventory Units Panel (Section 5) -->
        <div id="inventory-stockin-panel" class="bms-card p-4 mb-4 border-start border-4 border-success">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge badge-soft-success">Warehouse &amp; Inventory</span>
                        <h3 class="h6 fw-bold mb-0">Stock In &amp; Physical Inventory Tracking</h3>
                    </div>
                    <div class="text-secondary small">
                        Execute official Stock In transactions to increment available inventory and register physical serialized units for warranty and site tracking.
                    </div>
                </div>
                <div>
                    <span class="badge badge-soft-success fs-6">
                        <?= count($inventoryUnits) ?> Serialized Units In Stock
                    </span>
                </div>
            </div>

            <!-- Execute Stock In Form (Matching Company Actual Form Fields) -->
            <?php
            $readyDeliveries = array_filter($supplierDeliveries, static function ($d) {
                return in_array($d['status'], ['Pending Inspection', 'Inspected', 'Discrepancy'], true);
            });
            ?>
            <?php if (!empty($canExecuteStockIn) && !empty($readyDeliveries)): ?>
                <?php $primaryDeliveryToStock = reset($readyDeliveries); ?>
                <div class="p-3 bg-light rounded border mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <strong class="h6 fw-bold mb-0 text-success">&#128229; Execute Stock In Transaction (Delivery to PCT)</strong>
                        <span class="badge badge-soft-success">DR #<?= e($primaryDeliveryToStock['delivery_receipt_no']) ?></span>
                    </div>

                    <form method="POST" action="<?= url('/projects/' . $projectId . '/deliveries/' . (int) $primaryDeliveryToStock['id'] . '/stock-in') ?>" data-confirm="Complete Stock In and register serial numbers into inventory?">
                        <?= csrf_field() ?>
                        <div class="row g-2 mb-3">
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-semibold mb-1">PO Number</label>
                                <input type="text" class="form-control form-control-sm font-monospace bg-white" value="<?= e($primaryDeliveryToStock['po_number']) ?>" readonly>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-semibold mb-1">PO Date</label>
                                <input type="text" class="form-control form-control-sm bg-white" value="<?= e($primaryDeliveryToStock['po_date']) ?>" readonly>
                            </div>
                            <div class="col-6 col-md-3">
                                <label for="inv_date" class="form-label small fw-semibold mb-1">Inventory Date / Delivery to PCT <span class="text-danger">*</span></label>
                                <input type="date" id="inv_date" name="inventory_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label for="wh_loc" class="form-label small fw-semibold mb-1">Warehouse Location</label>
                                <input type="text" id="wh_loc" name="warehouse_location" class="form-control form-control-sm" value="PCT Main Warehouse">
                            </div>
                        </div>

                        <!-- Company Actual Stock In Line Item Fields -->
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered bg-white align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">#</th>
                                        <th style="width: 140px;">Model</th>
                                        <th style="min-width: 160px;">Description</th>
                                        <th style="width: 110px;">Warranty Duration</th>
                                        <th style="width: 80px;" class="text-end">In Qty</th>
                                        <th style="min-width: 220px;">Physical Serial Numbers (1 per line) <span class="text-danger">*</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $sInIdx = 1; ?>
                                    <?php foreach ($primaryDeliveryToStock['items'] as $sInItm): ?>
                                        <?php if ((float) $sInItm['quantity_accepted'] > 0): ?>
                                            <tr>
                                                <td class="text-center font-monospace text-secondary"><?= $sInIdx++ ?></td>
                                                <td class="font-monospace fw-semibold text-dark"><?= e($sInItm['model_part_number'] ?: 'Standard') ?></td>
                                                <td class="text-dark"><?= e($sInItm['item_description']) ?></td>
                                                <td>
                                                    <select name="warranty_duration[<?= (int) $sInItm['po_item_id'] ?>]" class="form-select form-select-sm">
                                                        <option value="1 Year" selected>1 Year</option>
                                                        <option value="2 Years">2 Years</option>
                                                        <option value="5 Years">5 Years</option>
                                                        <option value="10 Years">10 Years</option>
                                                        <option value="25 Years">25 Years</option>
                                                    </select>
                                                </td>
                                                <td class="text-end font-monospace fw-bold text-success">
                                                    <?= number_format((float) $sInItm['quantity_accepted'], 2) ?>
                                                </td>
                                                <td>
                                                    <textarea name="serial_numbers[<?= (int) $sInItm['po_item_id'] ?>]" rows="2" class="form-control form-control-sm font-monospace" placeholder="Enter serial number(s) separated by newline or comma..."></textarea>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-success btn-sm fw-semibold">
                                &#10003; Confirm Stock In &amp; Increment Inventory
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Registered Serialized Physical Units Table (Section 5) -->
            <div class="mb-4">
                <div class="small fw-bold text-dark text-uppercase tracking-wider mb-2">
                    Registered Serialized Equipment in Inventory (<?= count($inventoryUnits) ?>)
                </div>
                <?php if (!empty($inventoryUnits)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered bg-white align-middle mb-0 font-sans small">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;" class="text-center">#</th>
                                    <th>PO Number</th>
                                    <th>PO Date</th>
                                    <th>Model</th>
                                    <th>Description</th>
                                    <th>Warranty Duration</th>
                                    <th>Serial Number</th>
                                    <th class="text-end">In Qty</th>
                                    <th>Delivery to PCT</th>
                                    <th>Warehouse Location</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventoryUnits as $uIdx => $unit): ?>
                                    <tr>
                                        <td class="text-center font-monospace text-secondary"><?= (int) ($unit['item_number'] ?? ($uIdx + 1)) ?></td>
                                        <td class="font-monospace fw-bold text-primary"><?= e($unit['po_number']) ?></td>
                                        <td class="text-secondary"><?= e($unit['po_date']) ?></td>
                                        <td class="font-monospace fw-semibold"><?= e($unit['model']) ?></td>
                                        <td><?= e($unit['description']) ?></td>
                                        <td class="text-secondary"><?= e($unit['warranty_duration']) ?></td>
                                        <td><span class="badge badge-soft-dark font-monospace"><?= e($unit['serial_number']) ?></span></td>
                                        <td class="text-end font-monospace fw-bold"><?= number_format((float) $unit['in_qty'], 2) ?></td>
                                        <td class="text-secondary"><?= e($unit['inventory_date']) ?></td>
                                        <td class="text-secondary"><?= e($unit['current_location']) ?></td>
                                        <td><span class="badge badge-soft-success"><?= e($unit['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-light rounded text-center text-secondary small fst-italic">
                        No serialized equipment units registered yet. Complete Stock In from verified supplier deliveries to populate inventory.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Double-Entry Perpetual Ledger Transactions Table -->
            <?php if (!empty($inventoryTransactions)): ?>
                <div>
                    <div class="small fw-bold text-dark text-uppercase tracking-wider mb-2">
                        Perpetual Double-Entry Inventory Ledger (<?= count($inventoryTransactions) ?>)
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered bg-white align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Txn Number</th>
                                    <th>Type</th>
                                    <th>Catalog Item</th>
                                    <th class="text-end">Quantity</th>
                                    <th class="text-end">Balance Before</th>
                                    <th class="text-end">Balance After</th>
                                    <th>Warehouse Location</th>
                                    <th>Performed By</th>
                                    <th>Transaction Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventoryTransactions as $txn): ?>
                                    <tr>
                                        <td class="font-monospace fw-bold text-primary"><?= e($txn['transaction_number']) ?></td>
                                        <td><span class="badge badge-soft-success"><?= e($txn['transaction_type']) ?></span></td>
                                        <td class="fw-semibold text-dark"><?= e($txn['item_name']) ?> (<?= e($txn['item_code']) ?>)</td>
                                        <td class="text-end font-monospace fw-bold text-success">+<?= number_format((float) $txn['quantity'], 2) ?></td>
                                        <td class="text-end font-monospace text-secondary"><?= number_format((float) $txn['balance_before'], 2) ?></td>
                                        <td class="text-end font-monospace text-success fw-bold"><?= number_format((float) $txn['balance_after'], 2) ?></td>
                                        <td class="text-secondary"><?= e($txn['warehouse_location']) ?></td>
                                        <td><?= e($txn['performed_by_name'] ?? 'System') ?></td>
                                        <td class="text-secondary"><?= format_datetime($txn['transaction_date']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <!-- Marketing Document Checklist -->
        <div id="checklist" class="bms-card mb-4">
            <div class="p-3 px-4 border-bottom">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div>
                        <h3 class="h6 fw-bold mb-0">Marketing Document Checklist (<?= $uploadedRequiredCount ?>/<?= $requiredCount ?> Required Uploaded)</h3>
                        <div class="text-secondary" style="font-size: 0.78rem;">
                            Standard SYB Flowchart Marketing Deliverables &bull; Allowed formats: PDF, JPG, PNG, DOC/DOCX, XLS/XLSX (Max <?= e((string) $maxUploadMb) ?> MB)
                        </div>
                    </div>
                    <span class="badge <?= $isChecklistComplete ? 'badge-soft-success' : 'badge-soft-warning' ?>">
                        <?= $completionPercent ?>% Uploaded
                    </span>
                </div>
                <div class="progress" style="height: 7px;">
                    <div
                        class="progress-bar <?= $isChecklistComplete ? 'bg-success' : 'bg-primary' ?>"
                        role="progressbar"
                        style="width: <?= $completionPercent ?>%;"
                        aria-valuenow="<?= $completionPercent ?>"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    ></div>
                </div>
            </div>

            <!-- Always-Ready Quick Upload / Replace Bar -->
            <?php if (!empty($canUploadDocument)): ?>
                <div class="p-3 px-4 bg-light border-bottom">
                    <form method="POST" action="<?= url('/projects/' . $projectId . '/documents') ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <div class="col-12 col-md-4">
                            <label for="quick_document_type" class="form-label small fw-semibold mb-1">Checklist Document Type</label>
                            <select id="quick_document_type" name="document_type" class="form-select form-select-sm" required>
                                <?php foreach ($documentCategories as $catKey => $catLabel): ?>
                                    <option value="<?= e((string) $catKey) ?>" <?= (string) $catKey === $quickSelectedDocType ? 'selected' : '' ?>>
                                        <?= e((string) $catLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="quick_document_file" class="form-label small fw-semibold mb-1">Choose File</label>
                            <input type="file" id="quick_document_file" name="document" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
                        </div>

                        <div class="col-12 col-md-2">
                            <label for="quick_upload_notes" class="form-label small text-secondary mb-1">Notes (Opt.)</label>
                            <input type="text" id="quick_upload_notes" name="upload_notes" maxlength="255" class="form-control form-control-sm" placeholder="Version note">
                        </div>

                        <div class="col-12 col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary btn-sm fw-semibold">Upload File</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <div class="table-responsive px-3 pb-3 pt-2">
                <table class="table bms-checklist-table mb-0">
                    <thead>
                        <tr>
                            <th>Checklist Document</th>
                            <th>Status</th>
                            <th>Current File</th>
                            <th>Version</th>
                            <th>Uploaded By / Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rowIdx = 0; ?>
                        <?php foreach ($checklistItems as $docType => $item): ?>
                            <?php
                            $rowIdx++;
                            $currentDoc = $item['current'] ?? null;
                            $historyDocs = $item['history'] ?? [];
                            $historyCount = (int) ($item['history_count'] ?? 0);
                            $docStatus = (string) ($item['status'] ?? 'Missing');
                            $isFlagged = !empty($item['flagged_for_attention']);
                            $updatedSinceReturn = !empty($item['updated_since_return']);
                            $uploadPanelId = 'checklist-upload-panel-' . $rowIdx;
                            $historyPanelId = 'checklist-history-panel-' . $rowIdx;
                            $isRowUploadOpen = $resolvedOpenUploadType === (string) $docType;
                            $isRowHistoryOpen = $requestedHistoryType === (string) $docType;
                            $zebraClass = ($rowIdx % 2 === 1) ? 'bms-row-odd' : 'bms-row-even';
                            $rowClass = ($isFlagged && !$updatedSinceReturn)
                                ? 'checklist-row-attention'
                                : $zebraClass;
                            $canDownloadRowDoc = !empty($canDownloadDocument)
                                && (empty($isOperationsUser) || in_array((string) $docType, ['SLD', 'BOM'], true));
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td>
                                    <div class="d-flex flex-wrap align-items-center gap-1">
                                        <span class="fw-semibold"><?= e((string) $item['code']) ?></span>
                                        <?php if (!empty($item['required'])): ?>
                                            <span class="badge badge-soft-secondary" style="font-size: 0.68rem;">Required</span>
                                        <?php endif; ?>
                                        <?php if ($isFlagged && !$updatedSinceReturn): ?>
                                            <span class="badge bg-danger" style="font-size: 0.68rem;">Needs Attention</span>
                                        <?php elseif ($isFlagged && $updatedSinceReturn): ?>
                                            <span class="badge badge-soft-success" style="font-size: 0.68rem;">Updated Since Return</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($item['subtitle']) && $item['subtitle'] !== $item['code']): ?>
                                        <div class="text-secondary" style="font-size: 0.76rem;"><?= e((string) $item['subtitle']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="badge <?= checklist_status_badge_class($docStatus) ?>"><?= e($docStatus) ?></span>
                                </td>

                                <td class="small">
                                    <?php if ($currentDoc !== null): ?>
                                        <?php if ($canDownloadRowDoc): ?>
                                            <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $currentDoc['id'] . '/view') ?>"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="bms-doc-view-trigger fw-medium text-break text-decoration-none text-dark hover-primary d-inline-flex align-items-center gap-1"
                                               title="Click to view/preview document in system"
                                               data-doc-id="<?= (int) $currentDoc['id'] ?>"
                                               data-doc-type="<?= e($currentDoc['document_type']) ?>"
                                               data-filename="<?= e($currentDoc['original_filename']) ?>"
                                               data-version="v<?= (int) $currentDoc['version_number'] ?>"
                                               data-size="<?= format_bytes((int) $currentDoc['file_size']) ?>"
                                               data-raw-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $currentDoc['id'] . '/raw') ?>"
                                               data-view-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $currentDoc['id'] . '/view') ?>"
                                               data-download-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $currentDoc['id'] . '/download') ?>">
                                                <span><?= e($currentDoc['original_filename']) ?></span>
                                                <span class="text-primary small" style="font-size: 0.72rem;">&nearr;</span>
                                            </a>
                                        <?php else: ?>
                                            <div class="fw-medium text-break"><?= e($currentDoc['original_filename']) ?></div>
                                        <?php endif; ?>
                                        <div class="text-secondary" style="font-size: 0.74rem;"><?= format_bytes((int) $currentDoc['file_size']) ?></div>
                                        <?php if (!empty($currentDoc['upload_notes'])): ?>
                                            <div class="text-secondary fst-italic mt-1" style="font-size: 0.75rem;">
                                                Note: <?= e($currentDoc['upload_notes']) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-secondary fst-italic">No file uploaded</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($currentDoc !== null): ?>
                                        <span class="badge badge-soft-secondary">v<?= (int) $currentDoc['version_number'] ?></span>
                                        <?php if ($historyCount > 0): ?>
                                            <div class="text-secondary mt-1" style="font-size: 0.72rem;">+<?= $historyCount ?> prior</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-secondary small">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="small">
                                    <?php if ($currentDoc !== null): ?>
                                        <div><?= e($currentDoc['uploader_name']) ?></div>
                                        <div class="text-secondary" style="font-size: 0.74rem;"><?= format_datetime($currentDoc['created_at']) ?></div>
                                    <?php else: ?>
                                        <span class="text-secondary">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end">
                                    <div class="bms-action-group">
                                        <?php if ($currentDoc !== null && $canDownloadRowDoc): ?>
                                            <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $currentDoc['id'] . '/view') ?>"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="bms-action-icon bms-action-icon--primary bms-doc-view-trigger"
                                               title="View / Inspect Document"
                                               aria-label="View / Inspect Document"
                                               data-doc-id="<?= (int) $currentDoc['id'] ?>"
                                               data-doc-type="<?= e($currentDoc['document_type']) ?>"
                                               data-filename="<?= e($currentDoc['original_filename']) ?>"
                                               data-version="v<?= (int) $currentDoc['version_number'] ?>"
                                               data-size="<?= format_bytes((int) $currentDoc['file_size']) ?>"
                                               data-raw-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $currentDoc['id'] . '/raw') ?>"
                                               data-view-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $currentDoc['id'] . '/view') ?>"
                                               data-download-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $currentDoc['id'] . '/download') ?>">
                                                <?= bms_icon('eye') ?>
                                            </a>
                                            <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $currentDoc['id'] . '/download') ?>"
                                               class="bms-action-icon bms-action-icon--secondary"
                                               title="Download Current File"
                                               aria-label="Download Current File">
                                                <?= bms_icon('download') ?>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($historyCount > 0): ?>
                                            <a href="<?= url('/projects/' . $projectId . '?history=' . rawurlencode((string) $docType) . '#checklist') ?>"
                                               class="bms-action-icon"
                                               title="Version History (<?= $historyCount ?>)"
                                               aria-label="Version History (<?= $historyCount ?>)"
                                               data-toggle-panel="<?= e($historyPanelId) ?>"
                                               aria-expanded="<?= $isRowHistoryOpen ? 'true' : 'false' ?>">
                                                <?= bms_icon('history') ?>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($canUploadDocument)): ?>
                                            <?php $uploadLabel = $currentDoc === null ? 'Upload ' . $item['code'] : 'Replace ' . $item['code']; ?>
                                            <a href="<?= url('/projects/' . $projectId . '?upload=' . rawurlencode((string) $docType) . '#checklist') ?>"
                                               class="bms-action-icon <?= $currentDoc === null ? 'bms-action-icon--warning' : 'bms-action-icon--primary' ?>"
                                               title="<?= e($uploadLabel) ?>"
                                               aria-label="<?= e($uploadLabel) ?>"
                                               data-toggle-panel="<?= e($uploadPanelId) ?>"
                                               aria-expanded="<?= $isRowUploadOpen ? 'true' : 'false' ?>">
                                                <?= bms_icon($currentDoc === null ? 'upload' : 'replace') ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <?php if (!empty($canUploadDocument)): ?>
                                <tr id="<?= e($uploadPanelId) ?>" class="checklist-subrow <?= $isRowUploadOpen ? '' : 'd-none' ?>">
                                    <td colspan="6" class="p-3">
                                        <form method="POST" action="<?= url('/projects/' . $projectId . '/documents') ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="document_type" value="<?= e((string) $docType) ?>">

                                            <div class="col-12 col-md-5">
                                                <label class="form-label small fw-semibold mb-1">
                                                    <?= $currentDoc === null ? 'Upload' : 'Upload Replacement for' ?> <?= e((string) $item['code']) ?>
                                                    <?php if ($currentDoc !== null): ?>
                                                        <span class="text-secondary fw-normal">(creates v<?= (int) $currentDoc['version_number'] + 1 ?>, keeps v<?= (int) $currentDoc['version_number'] ?>)</span>
                                                    <?php endif; ?>
                                                </label>
                                                <input type="file" name="document" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
                                            </div>

                                            <div class="col-12 col-md-5">
                                                <label class="form-label small text-secondary mb-1">Version / Revision Notes (Optional)</label>
                                                <input type="text" name="upload_notes" maxlength="255" class="form-control form-control-sm" placeholder="e.g., Updated quantities or signed client copy">
                                            </div>

                                            <div class="col-12 col-md-2 d-grid">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <?= $currentDoc === null ? 'Save File' : 'Replace File' ?>
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php if ($historyCount > 0): ?>
                                <tr id="<?= e($historyPanelId) ?>" class="checklist-subrow <?= $isRowHistoryOpen ? '' : 'd-none' ?>">
                                    <td colspan="6" class="px-4 py-3">
                                        <div class="small fw-semibold text-secondary mb-2">
                                            Preserved Previous Versions for <?= e((string) $item['code']) ?> (<?= $historyCount ?>)
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm bg-white mb-0 small">
                                                <thead>
                                                    <tr>
                                                        <th>Ver.</th>
                                                        <th>Original Filename</th>
                                                        <th>Size</th>
                                                        <th>Uploaded By</th>
                                                        <th>Uploaded At</th>
                                                        <th>Notes</th>
                                                        <th class="text-end">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($historyDocs as $hist): ?>
                                                        <tr>
                                                            <td><span class="badge badge-soft-secondary">v<?= (int) $hist['version_number'] ?></span></td>
                                                            <td>
                                                                <?php if ($canDownloadRowDoc): ?>
                                                                    <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $hist['id'] . '/view') ?>"
                                                                       target="_blank"
                                                                       rel="noopener noreferrer"
                                                                       class="bms-doc-view-trigger text-dark text-decoration-none fw-medium hover-primary d-inline-flex align-items-center gap-1"
                                                                       title="View v<?= (int) $hist['version_number'] ?>"
                                                                       data-doc-id="<?= (int) $hist['id'] ?>"
                                                                       data-doc-type="<?= e($hist['document_type']) ?>"
                                                                       data-filename="<?= e($hist['original_filename']) ?>"
                                                                       data-version="v<?= (int) $hist['version_number'] ?>"
                                                                       data-size="<?= format_bytes((int) $hist['file_size']) ?>"
                                                                       data-raw-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $hist['id'] . '/raw') ?>"
                                                                       data-view-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $hist['id'] . '/view') ?>"
                                                                       data-download-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $hist['id'] . '/download') ?>">
                                                                        <span><?= e($hist['original_filename']) ?></span>
                                                                        <span class="text-primary small" style="font-size: 0.7rem;">&nearr;</span>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <?= e($hist['original_filename']) ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-secondary"><?= format_bytes((int) $hist['file_size']) ?></td>
                                                            <td><?= e($hist['uploader_name']) ?></td>
                                                            <td class="text-secondary"><?= format_datetime($hist['created_at']) ?></td>
                                                            <td class="text-secondary"><?= e($hist['upload_notes'] ?: '—') ?></td>
                                                            <td class="text-end text-nowrap">
                                                                <?php if ($canDownloadRowDoc): ?>
                                                                    <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $hist['id'] . '/view') ?>"
                                                                       target="_blank"
                                                                       rel="noopener noreferrer"
                                                                       class="bms-action-icon bms-action-icon--primary bms-doc-view-trigger"
                                                                       title="View v<?= (int) $hist['version_number'] ?>"
                                                                       aria-label="View v<?= (int) $hist['version_number'] ?>"
                                                                       data-doc-id="<?= (int) $hist['id'] ?>"
                                                                       data-doc-type="<?= e($hist['document_type']) ?>"
                                                                       data-filename="<?= e($hist['original_filename']) ?>"
                                                                       data-version="v<?= (int) $hist['version_number'] ?>"
                                                                       data-size="<?= format_bytes((int) $hist['file_size']) ?>"
                                                                       data-raw-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $hist['id'] . '/raw') ?>"
                                                                       data-view-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $hist['id'] . '/view') ?>"
                                                                       data-download-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $hist['id'] . '/download') ?>">
                                                                        <?= bms_icon('eye') ?>
                                                                    </a>
                                                                    <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $hist['id'] . '/download') ?>"
                                                                       class="bms-action-icon bms-action-icon--secondary"
                                                                       title="Download v<?= (int) $hist['version_number'] ?>"
                                                                       aria-label="Download v<?= (int) $hist['version_number'] ?>">
                                                                        <?= bms_icon('download') ?>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Optional Supplementary / General Attachments -->
            <?php if ($supplementaryDocs !== [] || !empty($canUploadDocument)): ?>
                <div class="p-3 px-4 border-top bg-light">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="small">
                            <strong>Supplementary Attachments (Optional):</strong>
                            <span class="text-secondary">Additional supporting files outside the 6 mandatory checklist items.</span>
                        </div>
                        <?php if (!empty($canUploadDocument)): ?>
                            <a
                                href="<?= url('/projects/' . $projectId . '?upload=' . rawurlencode('General Attachment') . '#checklist') ?>"
                                class="btn btn-light btn-sm border"
                                data-toggle-panel="supplementary-upload-panel"
                                aria-expanded="<?= $resolvedOpenUploadType === 'General Attachment' ? 'true' : 'false' ?>"
                            >
                                + Add Supplementary File
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($canUploadDocument)): ?>
                        <div id="supplementary-upload-panel" class="mt-3 pt-3 border-top <?= $resolvedOpenUploadType === 'General Attachment' ? '' : 'd-none' ?>">
                            <form method="POST" action="<?= url('/projects/' . $projectId . '/documents') ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
                                <?= csrf_field() ?>
                                <input type="hidden" name="document_type" value="General Attachment">
                                <div class="col-12 col-md-5">
                                    <label class="form-label small fw-medium mb-1">Supplementary File</label>
                                    <input type="file" name="document" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
                                </div>
                                <div class="col-12 col-md-5">
                                    <label class="form-label small text-secondary mb-1">Description / Notes</label>
                                    <input type="text" name="upload_notes" maxlength="255" class="form-control form-control-sm" placeholder="Optional attachment note">
                                </div>
                                <div class="col-12 col-md-2 d-grid">
                                    <button type="submit" class="btn btn-secondary btn-sm">Attach</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if ($supplementaryDocs !== []): ?>
                        <ul class="list-group list-group-flush mt-2 rounded border">
                            <?php foreach ($supplementaryDocs as $sDoc): ?>
                                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2 small py-2">
                                    <div>
                                        <span class="badge badge-soft-secondary me-1">v<?= (int) $sDoc['version_number'] ?></span>
                                        <?php if (!empty($canDownloadDocument)): ?>
                                            <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $sDoc['id'] . '/view') ?>"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="bms-doc-view-trigger text-dark text-decoration-none fw-bold hover-primary"
                                               title="View attachment"
                                               data-doc-id="<?= (int) $sDoc['id'] ?>"
                                               data-doc-type="<?= e($sDoc['document_type']) ?>"
                                               data-filename="<?= e($sDoc['original_filename']) ?>"
                                               data-version="v<?= (int) $sDoc['version_number'] ?>"
                                               data-size="<?= format_bytes((int) $sDoc['file_size']) ?>"
                                               data-raw-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $sDoc['id'] . '/raw') ?>"
                                               data-view-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $sDoc['id'] . '/view') ?>"
                                               data-download-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $sDoc['id'] . '/download') ?>">
                                                <?= e($sDoc['original_filename']) ?> &nearr;
                                            </a>
                                        <?php else: ?>
                                            <strong><?= e($sDoc['original_filename']) ?></strong>
                                        <?php endif; ?>
                                        <span class="text-secondary ms-1">(<?= format_bytes((int) $sDoc['file_size']) ?> &bull; <?= e($sDoc['uploader_name']) ?>)</span>
                                    </div>
                                    <?php if (!empty($canDownloadDocument)): ?>
                                        <div class="bms-action-group">
                                            <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $sDoc['id'] . '/view') ?>"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="bms-action-icon bms-action-icon--primary bms-doc-view-trigger"
                                               title="View Attachment"
                                               aria-label="View Attachment"
                                               data-doc-id="<?= (int) $sDoc['id'] ?>"
                                               data-doc-type="<?= e($sDoc['document_type']) ?>"
                                               data-filename="<?= e($sDoc['original_filename']) ?>"
                                               data-version="v<?= (int) $sDoc['version_number'] ?>"
                                               data-size="<?= format_bytes((int) $sDoc['file_size']) ?>"
                                               data-raw-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $sDoc['id'] . '/raw') ?>"
                                               data-view-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $sDoc['id'] . '/view') ?>"
                                               data-download-url="<?= url('/projects/' . $projectId . '/documents/' . (int) $sDoc['id'] . '/download') ?>">
                                                <?= bms_icon('eye') ?>
                                            </a>
                                            <a href="<?= url('/projects/' . $projectId . '/documents/' . (int) $sDoc['id'] . '/download') ?>"
                                               class="bms-action-icon bms-action-icon--secondary"
                                               title="Download Attachment"
                                               aria-label="Download Attachment">
                                                <?= bms_icon('download') ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- In-System Interactive Document Viewer Modal -->
<div class="modal fade" id="bmsDocPreviewModal" tabindex="-1" aria-labelledby="bmsDocPreviewModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 95vw; height: 92vh;">
        <div class="modal-content h-100 border-0 shadow">
            <div class="modal-header py-2 px-3 bg-light border-bottom">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-primary" id="bmsDocModalType">DOC</span>
                    <h6 class="modal-title fw-bold text-dark text-break mb-0" id="bmsDocPreviewModalTitle">Document Preview</h6>
                    <span class="badge badge-soft-secondary" id="bmsDocModalVersion">v1</span>
                    <span class="text-secondary small" id="bmsDocModalSize"></span>
                </div>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <a href="#" id="bmsDocModalDownloadBtn" class="btn btn-outline-primary btn-sm py-1 px-2 d-inline-flex align-items-center gap-1" title="Download copy">
                        <?= bms_icon('download') ?>
                        <span>Download</span>
                    </a>
                    <a href="#" id="bmsDocModalFullPageBtn" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm py-1 px-2" title="Open full viewer in new tab">
                        <span>Full Page &nearr;</span>
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-2 p-md-3 bg-white d-flex flex-column" id="bmsDocModalBody" style="min-height: 480px;">
                <!-- Dynamically rendered -->
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('js/vendor/xlsx.full.min.js') ?>"></script>
<script src="<?= asset('js/vendor/mammoth.browser.min.js') ?>"></script>
<script src="<?= asset('js/document-viewer.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Dynamic PR row addition
    var addPrRowBtn = document.getElementById('bmsAddPrRowBtn');
    if (addPrRowBtn) {
        addPrRowBtn.addEventListener('click', function () {
            var tbody = document.getElementById('bmsPrItemsTableBody');
            if (!tbody) return;
            var rowCount = tbody.children.length + 1;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td class="text-center font-monospace small">' + rowCount + '</td>'
                + '<td><input type="text" name="item_description[]" class="form-control form-control-sm" placeholder="e.g. Solar Inverter 50kW" required></td>'
                + '<td><input type="text" name="model_part_number[]" class="form-control form-control-sm font-monospace" placeholder="e.g. SUN2000-50KTL"></td>'
                + '<td><textarea name="item_specification[]" rows="2" class="form-control form-control-sm" placeholder="Detailed technical specs..." required></textarea></td>'
                + '<td><input type="number" step="0.01" min="0.01" name="quantity[]" class="form-control form-control-sm" value="1.00" required></td>'
                + '<td><input type="text" name="unit_of_measure[]" class="form-control form-control-sm" value="pcs" required></td>'
                + '<td><input type="text" name="item_purpose[]" class="form-control form-control-sm" placeholder="Item purpose"></td>'
                + '<td><input type="text" name="item_remarks[]" class="form-control form-control-sm" placeholder="Optional note"></td>'
                + '<td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm p-1 bms-remove-row" title="Remove line item">&times;</button></td>';
            tbody.appendChild(tr);
        });
    }

    // Dynamic Actual BOM row addition
    var addAbRowBtn = document.getElementById('bmsAddAbRowBtn');
    if (addAbRowBtn) {
        addAbRowBtn.addEventListener('click', function () {
            var tbody = document.getElementById('bmsAbItemsTableBody');
            if (!tbody) return;
            var rowCount = tbody.children.length + 1;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td class="text-center font-monospace small">' + rowCount + '</td>'
                + '<td><input type="text" name="ab_item_description[]" class="form-control form-control-sm" placeholder="Item description" required><input type="hidden" name="ab_pr_item_id[]" value=""></td>'
                + '<td><textarea name="ab_actual_specification[]" rows="2" class="form-control form-control-sm" placeholder="Actual equipment/material specifications..." required></textarea></td>'
                + '<td><input type="number" step="0.01" min="0.01" name="ab_quantity[]" class="form-control form-control-sm" value="1.00" required></td>'
                + '<td><input type="text" name="ab_unit_of_measure[]" class="form-control form-control-sm" value="pcs" required></td>'
                + '<td><input type="text" name="ab_item_remarks[]" class="form-control form-control-sm" placeholder="Optional note"></td>'
                + '<td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm p-1 bms-remove-row" title="Remove line item">&times;</button></td>';
            tbody.appendChild(tr);
        });
    }

    // Remove row delegation for both PR and Actual BOM tables
    document.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('bms-remove-row')) {
            var tr = e.target.closest('tr');
            var tbody = tr ? tr.parentNode : null;
            if (tbody) {
                if (tbody.children.length > 1) {
                    tr.remove();
                    Array.from(tbody.children).forEach(function (row, idx) {
                        var firstCol = row.querySelector('td:first-child');
                        if (firstCol) firstCol.textContent = idx + 1;
                    });
                } else {
                    alert('At least one item is required.');
                }
            }
        }
    });
});
</script>
