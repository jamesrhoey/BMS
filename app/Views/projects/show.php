<?php
$projectId = (int) $project['id'];
$status = (string) ($project['status'] ?? 'Draft');
$backListUrl = !empty($isMarketingUser) ? '/marketing/projects' : '/projects';
$backListLabel = !empty($isMarketingUser) ? 'Marketing Projects' : 'Projects';

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
<?php if ($status === 'Returned for Corrections' || (!empty($project['return_reason']) && $verificationStatus === 'returned_for_corrections')): ?>
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

<!-- JOM Number Issued Banner -->
<?php if (!empty($project['jom_number'])): ?>
    <div class="alert alert-primary border-primary border-opacity-50 mb-4 shadow-sm">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <span class="badge bg-primary text-uppercase me-2">Official JOM Issued</span>
                <span class="font-monospace fw-bold fs-5 text-primary"><?= e($project['jom_number']) ?></span>
                <div class="small text-secondary mt-1">
                    Job Order Memorandum officially issued by <strong><?= e($project['jom_issuer_name'] ?: 'Project Management') ?></strong>
                    <?php if (!empty($project['jom_issued_at'])): ?>
                        on <?= format_datetime((string) $project['jom_issued_at']) ?>
                    <?php endif; ?>
                    &bull; Authorized for downstream departmental execution (Operations, Logistics, Finance).
                </div>
            </div>
            <div>
                <span class="badge badge-soft-success fs-6"><i class="me-1">&#10003;</i> JOM Verified &amp; Active</span>
            </div>
        </div>
    </div>
<?php elseif ($status === 'Document Verification Completed' || $verificationStatus === 'verified_complete'): ?>
    <div class="alert alert-success border-success border-opacity-50 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <div class="fw-bold text-success">
                &#10003; Document Verification Completed by Project Management
            </div>
            <div class="small text-secondary">
                <?php if (!empty($project['verifier_name'])): ?>
                    Verified by <strong><?= e($project['verifier_name']) ?></strong>
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
            All required Marketing checklist documents are verified complete and readable. <em>Note: Document verification confirms file completeness only and does not constitute budget approval, procurement authorization, or automatic JOM issuance. JOM number assignment is deferred pending business-rule confirmation.</em>
            <?php if (!empty($canIssueJomNumber)): ?>
                <div class="mt-2 fw-semibold text-primary">
                    Project Management is now authorized to issue the official JOM number using the action below.
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
                            Issued by <?= e($project['jom_issuer_name'] ?: 'PM') ?> on <?= format_datetime((string) $project['jom_issued_at']) ?>
                        </div>
                    <?php elseif ($status === 'Document Verification Completed' || $verificationStatus === 'verified_complete'): ?>
                        <div class="text-warning fw-semibold">Ready for JOM Issuance</div>
                        <div class="text-secondary" style="font-size: 0.75rem;">
                            Documents verified. Project Management authorized to issue JOM.
                        </div>
                    <?php else: ?>
                        <div class="text-secondary fst-italic">Pending Document Verification</div>
                        <div class="text-secondary" style="font-size: 0.75rem;">
                            JOM number will be issued by Project Management after document verification.
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
                    <?= $status === 'Returned for Corrections' ? 'Resubmit Project to Project Management' : 'Submit to Project Management for Document Verification' ?>
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
                        All <strong><?= $requiredCount ?> required Marketing documents</strong> have been uploaded. Submitting forwards this project to the <strong>Project Management</strong> review queue for document completeness verification (JOM number issuance will occur only after PM verification).
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
                            <?= $status === 'Returned for Corrections' ? 'Resubmit' : 'Submit' ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php elseif (in_array($status, ['Submitted for Document Verification', 'Resubmitted for Verification', 'Submitted'], true)): ?>
            <div id="submission-panel" class="bms-card p-4 mb-4 border-start border-4 border-primary">
                <h3 class="h6 fw-bold mb-1">Queued with Project Management for Document Verification</h3>
                <p class="small text-secondary mb-0">
                    Marketing edits and document replacements are locked while Project Management reviews the submitted package. If Project Management flags incomplete or discrepant files, the project will be returned to Marketing for corrections.
                </p>
            </div>
        <?php endif; ?>

        <!-- Project Management Document Verification & Decision Panel -->
        <?php if (!empty($canCompleteVerification) || !empty($canReturnToMarketing) || !empty($canStartReview) || !empty($canIssueJomNumber) || !empty($project['jom_number'])): ?>
            <div id="pm-review-panel" class="bms-card p-4 mb-4 border-start border-4 border-primary">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h3 class="h6 fw-bold mb-0">Project Management Document Verification &amp; JOM Issuance</h3>
                        <div class="text-secondary small">
                            Inspect submitted Marketing documents, record review decisions, or return incomplete packages for corrections.
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-1">
                        <span class="badge badge-soft-primary">PM Workspace</span>
                        <?php if (!empty($project['jom_number'])): ?>
                            <span class="badge bg-success font-monospace">JOM: <?= e($project['jom_number']) ?></span>
                        <?php elseif (!empty($canIssueJomNumber)): ?>
                            <span class="badge badge-soft-warning">JOM: Ready to Issue</span>
                        <?php else: ?>
                            <span class="badge badge-soft-secondary">JOM: Pending Verification</span>
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

                <!-- Action: Issue Official JOM Number -->
                <?php if (!empty($canIssueJomNumber)): ?>
                    <div class="p-4 rounded border mb-4 bg-white border-primary border-opacity-75 shadow-sm">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div>
                                <div class="fw-bold text-primary fs-6 mb-1">
                                    &#128221; Ready to Issue Job Order Memorandum (JOM) Number
                                </div>
                                <div class="text-secondary small mb-1">
                                    Document verification is complete. Project Management is the authorized department to issue the official JOM number for this project.
                                </div>
                                <div class="text-secondary" style="font-size: 0.74rem;">
                                    Series: <code>JOM-YYYYMM-XXXX</code> (Configurable via <code>config/pm.php</code>). Server-side uniqueness and transactional locking are enforced.
                                </div>
                            </div>
                            <div>
                                <form
                                    method="POST"
                                    action="<?= url('/projects/' . $projectId . '/issue-jom') ?>"
                                    data-confirm="Issue official JOM Number for project '<?= e($project['project_code']) ?>'? This action is permanent and generates the unique Job Order Memorandum number."
                                    class="m-0"
                                >
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-primary fw-semibold px-4 py-2">
                                        Issue JOM Number
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php elseif (!empty($project['jom_number'])): ?>
                    <div class="p-3 rounded border mb-4 bg-light border-success">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <div class="fw-bold text-success small mb-1">
                                    &#10003; JOM Number Issued: <span class="font-monospace fs-6"><?= e($project['jom_number']) ?></span>
                                </div>
                                <div class="text-secondary" style="font-size: 0.78rem;">
                                    Issued by <strong><?= e($project['jom_issuer_name'] ?: 'Project Management') ?></strong>
                                    <?php if (!empty($project['jom_issued_at'])): ?>
                                        on <?= format_datetime((string) $project['jom_issued_at']) ?>
                                    <?php endif; ?>
                                    &bull; Issuance is finalized. Repeated requests cannot duplicate JOM numbers.
                                </div>
                            </div>
                            <div>
                                <span class="badge bg-success">JOM Active</span>
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
                                        data-confirm="Approve and complete document verification for project '<?= e($project['project_code']) ?>'?"
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
                                    Does not approve budget, inventory, procurement, or issue a JOM number.
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
    </div>

    <!-- Right Column: Marketing Document Checklist -->
    <div class="col-12 col-xl-7">
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
                                        <?php if (!empty($canDownloadDocument)): ?>
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
                                        <?php if ($currentDoc !== null && !empty($canDownloadDocument)): ?>
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
                                                                <?php if (!empty($canDownloadDocument)): ?>
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
                                                                <?php if (!empty($canDownloadDocument)): ?>
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
