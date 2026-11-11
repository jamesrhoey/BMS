<?php
$currentPage = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 10);
$totalRows = (int) ($pagination['total'] ?? 0);
$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || ($filters['sort'] ?? 'submitted') !== 'submitted';

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter([
        'q' => $filters['q'] ?? '',
        'status' => $filters['status'] ?? '',
        'sort' => ($filters['sort'] ?? 'submitted') !== 'submitted' ? $filters['sort'] : '',
        'page' => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn($v): bool => $v !== '');

    return url('/ops-finance/dashboard' . ($query !== [] ? '?' . http_build_query($query) : ''));
};

$abFormatPattern = (string) ($actualBomConfig['format'] ?? '{PREFIX}-{YEAR}{MONTH}-{SEQ}');
?>

<!-- 4 Database-Driven OPS Finance Summary Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">PRs Received from Operations</div>
            <div class="bms-stat-value text-primary"><?= (int) ($stats['prs_received_from_operations'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">Submitted Purchase Requests</div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">PRs Awaiting Review</div>
            <div class="bms-stat-value text-warning"><?= (int) ($stats['prs_awaiting_review'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">Incoming PRs pending review</div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Actual BOM Pending Upload</div>
            <div class="bms-stat-value text-info"><?= (int) ($stats['actual_bom_pending_upload'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">Awaiting Actual BOM &amp; specs</div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Actual BOM Submitted to Admin Finance</div>
            <div class="bms-stat-value text-success"><?= (int) ($stats['actual_bom_submitted_to_afin'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">Forwarded to Admin Finance</div>
        </div>
    </div>
</div>

<div class="bms-card p-3 mb-4 border-start border-4 border-primary bg-light">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small">
        <div>
            <span class="badge badge-soft-primary me-1">Separate Actual BOM Record</span>
            <strong class="text-dark">Configured Pattern: <code class="text-dark"><?= e($abFormatPattern) ?></code></strong>
            <span class="text-secondary ms-1">
                — Actual BOM uploads are stored separately with version history and never overwrite the original Marketing BOM.
            </span>
        </div>
        <a href="<?= url('/ops-finance/projects') ?>" class="btn btn-outline-secondary btn-sm py-0 px-2">Open Full OPS Finance Queue &rarr;</a>
    </div>
</div>

<div class="bms-table-panel">
    <form method="GET" action="<?= url('/ops-finance/dashboard') ?>" class="bms-table-toolbar">
        <h3 class="h6 fw-bold mb-0 me-auto">OPS Finance Incoming Purchase Requests &amp; Actual BOM Queue</h3>

        <select id="status" name="status" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by OPS Finance status">
            <option value="">All Received PRs</option>
            <option value="awaiting_review" <?= ($filters['status'] ?? '') === 'awaiting_review' ? 'selected' : '' ?>>Incoming PRs Awaiting Review</option>
            <option value="under_review" <?= ($filters['status'] ?? '') === 'under_review' ? 'selected' : '' ?>>Pending PR Review</option>
            <option value="returned_prs" <?= ($filters['status'] ?? '') === 'returned_prs' ? 'selected' : '' ?>>Returned PRs</option>
            <option value="processed_prs" <?= ($filters['status'] ?? '') === 'processed_prs' ? 'selected' : '' ?>>Processed PRs</option>
            <option value="pending_actual_bom" <?= ($filters['status'] ?? '') === 'pending_actual_bom' ? 'selected' : '' ?>>Actual BOM Preparation</option>
            <option value="draft_bom" <?= ($filters['status'] ?? '') === 'draft_bom' ? 'selected' : '' ?>>Draft Actual BOMs</option>
            <option value="all_boms" <?= ($filters['status'] ?? '') === 'all_boms' ? 'selected' : '' ?>>Actual BOM Records</option>
            <option value="pending_submission" <?= ($filters['status'] ?? '') === 'pending_submission' ? 'selected' : '' ?>>Pending Submission to Admin Finance</option>
            <option value="submitted_to_afin" <?= ($filters['status'] ?? '') === 'submitted_to_afin' ? 'selected' : '' ?>>Actual BOM Submitted to Admin Finance</option>
            <option value="returned_bom" <?= ($filters['status'] ?? '') === 'returned_bom' ? 'selected' : '' ?>>Returned Actual BOMs</option>
        </select>

        <select id="sort" name="sort" class="form-select form-select-sm bms-toolbar-select" aria-label="Sort by date">
            <option value="submitted" <?= ($filters['sort'] ?? 'submitted') === 'submitted' ? 'selected' : '' ?>>PR Submitted Date &darr;</option>
            <option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            <option value="client" <?= ($filters['sort'] ?? '') === 'client' ? 'selected' : '' ?>>Client Name</option>
        </select>

        <input type="text"
               id="q"
               name="q"
               value="<?= e($filters['q'] ?? '') ?>"
               class="form-control form-control-sm bms-toolbar-input"
               placeholder="Search PR, project, JOM, Actual BOM...">

        <button type="submit" class="bms-toolbar-icon-btn" title="Apply Filters" aria-label="Apply Filters">
            <?= bms_icon('search', 16) ?>
        </button>

        <?php if ($hasActiveFilters): ?>
            <a href="<?= url('/ops-finance/dashboard') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline" title="Clear Filters" aria-label="Clear Filters">
                <?= bms_icon('reset', 16) ?>
            </a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>PR Number</th>
                    <th>Project Name</th>
                    <th>Client / End User</th>
                    <th>JOM Number</th>
                    <th>PR Status &amp; Items</th>
                    <th>Actual BOM Status</th>
                    <th>Submitted to Admin Finance</th>
                    <th class="text-end">Available Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($queue === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4 small">
                            No Purchase Requests from Operations currently match the OPS Finance filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($queue as $row): ?>
                        <?php
                        $pId = (int) $row['project_id'];
                        $prNumber = (string) ($row['pr_number'] ?? '');
                        $prStatus = (string) ($row['pr_status'] ?? '');
                        $prItemsCount = (int) ($row['pr_items_count'] ?? 0);
                        $abNumber = (string) ($row['actual_bom_number'] ?? '');
                        $abStatus = (string) ($row['actual_bom_status'] ?? '');
                        $abDocId = !empty($row['actual_bom_document_id']) ? (int) $row['actual_bom_document_id'] : 0;
                        $abVersion = (int) ($row['actual_bom_version'] ?? 1);
                        ?>
                        <tr>
                            <td>
                                <span class="font-monospace fw-semibold"><?= e($prNumber) ?></span>
                                <div class="small text-secondary"><?= format_datetime($row['pr_submitted_at'] ?? null) ?></div>
                                <?php if (!empty($row['pr_requester_name'])): ?>
                                    <div class="text-secondary" style="font-size: 0.72rem;">by <?= e($row['pr_requester_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= url('/projects/' . $pId . '#ops-finance-actual-bom-panel') ?>" class="fw-semibold text-dark text-decoration-none">
                                    <?= e($row['project_name']) ?>
                                </a>
                                <div class="small text-secondary font-monospace"><?= e($row['project_code']) ?></div>
                            </td>
                            <td><?= e($row['client_name']) ?></td>
                            <td>
                                <span class="badge bg-primary font-monospace"><?= e($row['jom_number']) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= pr_status_badge_class($prStatus) ?>"><?= e($prStatus) ?></span>
                                <div class="small text-secondary mt-1"><?= $prItemsCount ?> requested item<?= $prItemsCount === 1 ? '' : 's' ?></div>
                            </td>
                            <td>
                                <?php if ($abNumber !== ''): ?>
                                    <div>
                                        <span class="font-monospace fw-semibold small"><?= e($abNumber) ?></span>
                                        <span class="badge bg-light text-dark border ms-1">v<?= $abVersion ?></span>
                                    </div>
                                    <div class="mt-1">
                                        <span class="badge <?= actual_bom_status_badge_class($abStatus) ?>"><?= e($abStatus) ?></span>
                                    </div>
                                    <?php if ($abDocId > 0): ?>
                                        <div class="small mt-1">
                                            <a href="<?= url('/projects/' . $pId . '/documents/' . $abDocId . '/view') ?>" class="text-primary text-decoration-none me-2">View BOM</a>
                                            <a href="<?= url('/projects/' . $pId . '/documents/' . $abDocId . '/download') ?>" class="text-secondary text-decoration-none">Download</a>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-soft-warning">Actual BOM Pending Upload</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-nowrap">
                                <?= !empty($row['actual_bom_submitted_at']) ? format_datetime($row['actual_bom_submitted_at']) : '<span class="text-secondary">Pending</span>' ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="<?= url('/projects/' . $pId . '#ops-finance-actual-bom-panel') ?>"
                                   class="btn btn-sm <?= ($abNumber === '' || in_array($abStatus, ['Draft', 'Returned for Corrections'], true)) ? 'btn-primary' : 'btn-outline-primary' ?>">
                                    <?= $abNumber === '' ? 'Review PR & Upload Actual BOM' : (in_array($abStatus, ['Draft', 'Returned for Corrections'], true) ? 'Update / Submit Actual BOM' : 'View PR & Actual BOM') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= render_table_footer($currentPage, $perPage, count($queue), $totalRows, $buildPageUrl) ?>
</div>
