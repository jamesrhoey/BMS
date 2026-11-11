<?php
$currentPage = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 10);
$totalRows = (int) ($pagination['total'] ?? 0);
$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || ($filters['sort'] ?? 'forwarded') !== 'forwarded';

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter([
        'q' => $filters['q'] ?? '',
        'status' => $filters['status'] ?? '',
        'sort' => ($filters['sort'] ?? 'forwarded') !== 'forwarded' ? $filters['sort'] : '',
        'page' => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn($v): bool => $v !== '');

    return url('/admin-finance/dashboard' . ($query !== [] ? '?' . http_build_query($query) : ''));
};

$jomFormatPattern = (string) ($jomConfig['format_pattern'] ?? 'JOM-{YYYY}-{SEQ}');
$jomRequiresConfirmation = (bool) ($jomConfig['requires_business_confirmation'] ?? true);
?>

<!-- 4 Database-Driven Admin Finance Summary Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Projects Awaiting JOM Issuance</div>
            <div class="bms-stat-value text-primary"><?= (int) ($stats['awaiting_jom_issuance'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">Approved &amp; forwarded by PM</div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Projects Under Admin Finance Review</div>
            <div class="bms-stat-value text-warning"><?= (int) ($stats['under_admin_finance_review'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">Active Admin Finance review</div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Projects With JOM Issued</div>
            <div class="bms-stat-value text-success"><?= (int) ($stats['jom_issued'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">Awaiting handoff: <?= (int) ($stats['jom_issued_pending_ops'] ?? 0) ?></div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Projects Forwarded to Operations</div>
            <div class="bms-stat-value text-info"><?= (int) ($stats['forwarded_to_operations'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">JOM + SLD + Marketing BOM sent</div>
        </div>
    </div>
</div>

<?php if ($jomRequiresConfirmation): ?>
    <div class="bms-card p-3 mb-4 border-start border-4 border-warning bg-light">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small">
            <div>
                <span class="badge badge-soft-warning me-1">Configurable JOM Format</span>
                <strong class="text-dark">Configured Pattern: <code class="text-dark"><?= e($jomFormatPattern) ?></code></strong>
                <span class="text-secondary ms-1">
                    — Configurable in <code>config/app.php</code> (<code>BMS_JOM_FORMAT_PATTERN</code>). Requires official business confirmation before production cutover.
                </span>
            </div>
            <a href="<?= url('/admin-finance/projects') ?>" class="btn btn-outline-secondary btn-sm py-0 px-2">Open Full Queue &rarr;</a>
        </div>
    </div>
<?php endif; ?>

<!-- Unified Nuclias-Style Filter Toolbar & Admin Finance Incoming Project Queue Table -->
<div class="bms-table-panel">
    <form method="GET" action="<?= url('/admin-finance/dashboard') ?>" class="bms-table-toolbar">
        <h3 class="h6 fw-bold mb-0 me-auto">Admin Finance Incoming Project Queue &amp; JOM Issuance</h3>

        <select id="status" name="status" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by Admin Finance status">
            <option value="">All Incoming Projects</option>
            <option value="incoming_approved" <?= ($filters['status'] ?? '') === 'incoming_approved' ? 'selected' : '' ?>>Incoming Approved Projects</option>
            <option value="awaiting_jom" <?= in_array($filters['status'] ?? '', ['awaiting_jom', 'awaiting'], true) ? 'selected' : '' ?>>Awaiting JOM Issuance</option>
            <option value="under_review" <?= in_array($filters['status'] ?? '', ['under_review', 'under_afin_review'], true) ? 'selected' : '' ?>>Under Admin Finance Review</option>
            <option value="jom_issued" <?= ($filters['status'] ?? '') === 'jom_issued' ? 'selected' : '' ?>>JOM Issued</option>
            <option value="ready_for_operations" <?= in_array($filters['status'] ?? '', ['ready_for_operations', 'ready_ops'], true) ? 'selected' : '' ?>>Ready for Operations</option>
            <option value="forwarded_to_operations" <?= in_array($filters['status'] ?? '', ['forwarded_to_operations', 'forwarded_ops'], true) ? 'selected' : '' ?>>Forwarded to Operations</option>
            <option value="actual_bom_submitted" <?= in_array($filters['status'] ?? '', ['actual_bom_submitted', 'actual_bom_incoming'], true) ? 'selected' : '' ?>>Actual BOM Submitted</option>
            <option value="actual_bom_under_review" <?= ($filters['status'] ?? '') === 'actual_bom_under_review' ? 'selected' : '' ?>>Actual BOM Under Review</option>
            <option value="actual_bom_returned" <?= ($filters['status'] ?? '') === 'actual_bom_returned' ? 'selected' : '' ?>>Actual BOM Returned</option>
            <option value="actual_bom_accepted" <?= ($filters['status'] ?? '') === 'actual_bom_accepted' ? 'selected' : '' ?>>Actual BOM Accepted</option>
            <option value="po_records" <?= in_array($filters['status'] ?? '', ['po_records', 'has_po'], true) ? 'selected' : '' ?>>Purchase Order Records</option>
            <option value="po_issued" <?= ($filters['status'] ?? '') === 'po_issued' ? 'selected' : '' ?>>POs Issued to Supplier</option>
        </select>

        <select id="sort" name="sort" class="form-select form-select-sm bms-toolbar-select" aria-label="Sort by date">
            <option value="forwarded" <?= ($filters['sort'] ?? 'forwarded') === 'forwarded' ? 'selected' : '' ?>>PM Forwarded Date &darr;</option>
            <option value="approved" <?= ($filters['sort'] ?? '') === 'approved' ? 'selected' : '' ?>>PM Approval Date &darr;</option>
            <option value="newest" <?= ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest First</option>
            <option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            <option value="client" <?= ($filters['sort'] ?? '') === 'client' ? 'selected' : '' ?>>Client Name</option>
        </select>

        <input type="text"
               id="q"
               name="q"
               value="<?= e($filters['q'] ?? '') ?>"
               class="form-control form-control-sm bms-toolbar-input"
               placeholder="Search project, client, JOM...">

        <button type="submit" class="bms-toolbar-icon-btn" title="Apply Filters" aria-label="Apply Filters">
            <?= bms_icon('search', 16) ?>
        </button>

        <?php if ($hasActiveFilters): ?>
            <a href="<?= url('/admin-finance/dashboard') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline" title="Clear Filters" aria-label="Clear Filters">
                <?= bms_icon('reset', 16) ?>
            </a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Project Name</th>
                    <th>Client / End User</th>
                    <th>Project Identifier</th>
                    <th>PM Approval Date</th>
                    <th>JOM Status</th>
                    <th>Assigned JOM Number</th>
                    <th>Submitted By</th>
                    <th class="text-end">Available Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4 small">
                            No approved and forwarded projects currently match the Admin Finance filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                        <?php
                        $pId = (int) $proj['id'];
                        $pStatus = (string) ($proj['status'] ?? '');
                        $hasJom = !empty($proj['jom_number']);
                        $isForwardedOps = !empty($proj['forwarded_to_ops_at']) || $pStatus === 'Forwarded to Operations';
                        $canStartAfinReview = !$hasJom && !$isForwardedOps && $pStatus === 'Forwarded to Admin Finance';
                        $canIssueJom = !$hasJom && !$isForwardedOps;
                        $canForwardOps = $hasJom && !$isForwardedOps;

                        if ($isForwardedOps) {
                            $jomStatusLabel = 'Forwarded to Operations';
                            $jomStatusBadge = 'badge-soft-info';
                        } elseif ($hasJom) {
                            $jomStatusLabel = 'JOM Issued';
                            $jomStatusBadge = 'badge-soft-success';
                        } elseif ($pStatus === 'Under Admin Finance Review') {
                            $jomStatusLabel = 'Under Admin Finance Review';
                            $jomStatusBadge = 'badge-soft-warning';
                        } else {
                            $jomStatusLabel = 'Awaiting JOM Issuance';
                            $jomStatusBadge = 'badge-soft-primary';
                        }
                        ?>
                        <tr>
                            <td>
                                <a href="<?= url('/projects/' . $pId) ?>" class="fw-semibold text-dark text-decoration-none">
                                    <?= e($proj['project_name']) ?>
                                </a>
                                <div class="mt-1">
                                    <span class="badge <?= project_status_badge_class($pStatus) ?>"><?= e($pStatus) ?></span>
                                </div>
                            </td>
                            <td><?= e($proj['client_name']) ?></td>
                            <td>
                                <span class="font-monospace small fw-semibold"><?= e($proj['project_code']) ?></span>
                            </td>
                            <td class="small text-nowrap">
                                <?= format_datetime($proj['verified_at'] ?? $proj['forwarded_to_afin_at'] ?? null) ?>
                                <?php if (!empty($proj['verifier_name'])): ?>
                                    <div class="text-secondary" style="font-size: 0.75rem;">by <?= e($proj['verifier_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $jomStatusBadge ?>"><?= e($jomStatusLabel) ?></span>
                            </td>
                            <td>
                                <?php if ($hasJom): ?>
                                    <span class="badge bg-primary font-monospace"><?= e($proj['jom_number']) ?></span>
                                    <?php if (!empty($proj['jom_issued_at'])): ?>
                                        <div class="text-secondary" style="font-size: 0.72rem;"><?= format_datetime($proj['jom_issued_at']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-secondary small">Pending Issuance</span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if (!empty($proj['submitter_name'])): ?>
                                    <?= e($proj['submitter_name']) ?>
                                <?php else: ?>
                                    <span class="text-secondary"><?= e($proj['creator_name'] ?? '—') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="bms-action-group">
                                    <a href="<?= url('/projects/' . $pId) ?>"
                                       class="bms-action-icon bms-action-icon--primary"
                                       title="Open Project & Admin Finance Panel"
                                       aria-label="Open Project & Admin Finance Panel">
                                        <?= bms_icon('eye') ?>
                                    </a>

                                    <?php if ($canStartAfinReview): ?>
                                        <form method="POST" action="<?= url('/projects/' . $pId . '/admin-finance-review') ?>" class="m-0 d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="bms-action-icon bms-action-icon--primary"
                                                    title="Mark Under Admin Finance Review"
                                                    aria-label="Mark Under Admin Finance Review">
                                                <?= bms_icon('prepare') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($canIssueJom): ?>
                                        <form method="POST"
                                              action="<?= url('/projects/' . $pId . '/issue-jom') ?>"
                                              class="m-0 d-inline"
                                              data-confirm="Confirm official JOM Number issuance for approved project '<?= e($proj['project_code']) ?>' (<?= e($proj['project_name']) ?>)? This action is permanent and assigns a unique JOM number.">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="bms-action-icon bms-action-icon--success"
                                                    title="Issue JOM Number"
                                                    aria-label="Issue JOM Number">
                                                <?= bms_icon('check') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($canForwardOps): ?>
                                        <form method="POST"
                                              action="<?= url('/projects/' . $pId . '/forward-to-operations') ?>"
                                              class="m-0 d-inline"
                                              data-confirm="Forward JOM Number '<?= e($proj['jom_number']) ?>', SLD, and Marketing BOM for project '<?= e($proj['project_code']) ?>' to Operations?">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="bms-action-icon bms-action-icon--primary"
                                                    title="Forward JOM, SLD & Marketing BOM to Operations"
                                                    aria-label="Forward JOM, SLD & Marketing BOM to Operations">
                                                <?= bms_icon('send') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= render_table_footer($currentPage, $perPage, count($projects), $totalRows, $buildPageUrl) ?>
</div>

<!-- Incoming Actual BOM Queue (Submitted by OPS Finance to Admin Finance) -->
<?php $incomingActualBoms = $incomingActualBoms ?? []; ?>
<div class="bms-table-panel mt-4" id="admin-finance-actual-bom-queue">
    <div class="bms-table-toolbar">
        <div>
            <h3 class="h6 fw-bold mb-0">Incoming Actual BOM Queue (From OPS Finance)</h3>
            <div class="small text-secondary">Actual BOMs with actual equipment/material specifications submitted by OPS Finance for Admin Finance review.</div>
        </div>
        <span class="badge badge-soft-primary ms-auto"><?= count($incomingActualBoms) ?> Submitted Actual BOM<?= count($incomingActualBoms) === 1 ? '' : 's' ?></span>
    </div>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Actual BOM Ref</th>
                    <th>Project Name</th>
                    <th>JOM Number</th>
                    <th>PR Reference</th>
                    <th>Actual BOM File</th>
                    <th>Date Submitted</th>
                    <th>Submitted By</th>
                    <th>Current Status</th>
                    <th class="text-end">Available Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($incomingActualBoms === []): ?>
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-4 small">
                            No Actual BOMs have been submitted by OPS Finance to Admin Finance yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($incomingActualBoms as $ab): ?>
                        <?php
                        $abProjId = (int) $ab['project_id'];
                        $abDocId = !empty($ab['document_id']) ? (int) $ab['document_id'] : 0;
                        $abStatus = (string) ($ab['status'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <span class="font-monospace fw-semibold small"><?= e($ab['actual_bom_number']) ?></span>
                                <span class="badge bg-light text-dark border ms-1">v<?= (int) ($ab['version_number'] ?? 1) ?></span>
                            </td>
                            <td>
                                <a href="<?= url('/projects/' . $abProjId . '#ops-finance-actual-bom-panel') ?>" class="fw-semibold text-dark text-decoration-none">
                                    <?= e($ab['project_name']) ?>
                                </a>
                                <div class="small text-secondary font-monospace"><?= e($ab['project_code']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-primary font-monospace"><?= e($ab['jom_number']) ?></span>
                            </td>
                            <td>
                                <span class="font-monospace small fw-semibold"><?= e($ab['pr_number']) ?></span>
                            </td>
                            <td class="small">
                                <?php if ($abDocId > 0): ?>
                                    <div class="fw-semibold text-truncate" style="max-width: 170px;" title="<?= e($ab['document_filename'] ?? 'Actual BOM') ?>">
                                        <?= e($ab['document_filename'] ?? 'Actual BOM File') ?>
                                    </div>
                                    <div class="d-flex gap-2 mt-1">
                                        <a href="<?= url('/projects/' . $abProjId . '/documents/' . $abDocId . '/view') ?>" class="text-primary text-decoration-none">View</a>
                                        <a href="<?= url('/projects/' . $abProjId . '/documents/' . $abDocId . '/download') ?>" class="text-secondary text-decoration-none">Download</a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-secondary">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-nowrap">
                                <?= format_datetime($ab['submitted_at'] ?? null) ?>
                            </td>
                            <td class="small">
                                <?= e($ab['submitter_name'] ?? $ab['uploader_name'] ?? '—') ?>
                            </td>
                            <td>
                                <span class="badge <?= actual_bom_status_badge_class($abStatus) ?>"><?= e($abStatus) ?></span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="<?= url('/projects/' . $abProjId . '#ops-finance-actual-bom-panel') ?>" class="btn btn-sm btn-outline-primary">
                                    Review Actual BOM &amp; Specs
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
