<?php
$currentPage = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 12);
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

    return url('/ops-finance/projects' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>

<div class="bms-table-panel">
    <form method="GET" action="<?= url('/ops-finance/projects') ?>" class="bms-table-toolbar">
        <h3 class="h6 fw-bold mb-0 me-auto">OPS Finance Purchase Requests &amp; Actual BOM Registry</h3>

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
            <a href="<?= url('/ops-finance/projects') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline" title="Clear Filters" aria-label="Clear Filters">
                <?= bms_icon('reset', 16) ?>
            </a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th style="width: 12%; min-width: 110px;">PR Number</th>
                    <th style="width: 20%; min-width: 180px;">Project Name</th>
                    <th style="width: 12%; min-width: 110px;">Client / End User</th>
                    <th style="width: 10%; min-width: 95px;">JOM Number</th>
                    <th style="width: 14%; min-width: 130px;">PR Status &amp; Items</th>
                    <th style="width: 14%; min-width: 130px;">Actual BOM Status</th>
                    <th style="width: 9%; min-width: 85px;">Submitted to Afin</th>
                    <th class="text-end" style="width: 9%; min-width: 85px;">Available Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($queue === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4 small">
                            No Purchase Requests found matching the selected criteria.
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
                                <div class="small text-secondary text-truncate"><?= format_datetime($row['pr_submitted_at'] ?? null) ?></div>
                            </td>
                            <td>
                                <a href="<?= url('/projects/' . $pId . '#ops-finance-actual-bom-panel') ?>" class="fw-semibold text-dark text-decoration-none text-truncate d-block" title="<?= e($row['project_name']) ?>">
                                    <?= e($row['project_name']) ?>
                                </a>
                                <div class="small text-secondary font-monospace text-truncate"><?= e($row['project_code']) ?></div>
                            </td>
                            <td>
                                <div class="text-truncate" title="<?= e($row['client_name']) ?>"><?= e($row['client_name']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-primary font-monospace"><?= e($row['jom_number']) ?></span>
                            </td>
                            <td>
                                <div class="text-truncate"><span class="badge <?= pr_status_badge_class($prStatus) ?>"><?= e($prStatus) ?></span></div>
                                <div class="small text-secondary text-truncate"><?= $prItemsCount ?> item<?= $prItemsCount === 1 ? '' : 's' ?></div>
                            </td>
                            <td>
                                <?php if ($abNumber !== ''): ?>
                                    <div class="d-flex align-items-center gap-1 text-truncate">
                                        <span class="font-monospace fw-semibold small"><?= e($abNumber) ?></span>
                                        <span class="badge <?= actual_bom_status_badge_class($abStatus) ?>"><?= e($abStatus) ?></span>
                                    </div>
                                    <div class="small text-truncate">
                                        <?php if ($abDocId > 0): ?>
                                            <a href="<?= url('/projects/' . $pId . '/documents/' . $abDocId . '/view') ?>" class="text-primary text-decoration-none">View</a>
                                            <span class="text-secondary">&bull;</span>
                                            <a href="<?= url('/projects/' . $pId . '/documents/' . $abDocId . '/download') ?>" class="text-secondary text-decoration-none">Download</a>
                                        <?php else: ?>
                                            <span class="text-secondary">v<?= $abVersion ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge badge-soft-warning">Actual BOM Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-nowrap">
                                <div><?= !empty($row['actual_bom_submitted_at']) ? format_datetime($row['actual_bom_submitted_at']) : '<span class="text-secondary">Pending</span>' ?></div>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="<?= url('/projects/' . $pId . '#ops-finance-actual-bom-panel') ?>"
                                   class="btn btn-sm <?= ($abNumber === '' || in_array($abStatus, ['Draft', 'Returned for Corrections'], true)) ? 'btn-primary' : 'btn-outline-primary' ?>">
                                    <?= $abNumber === '' ? 'Upload BOM' : (in_array($abStatus, ['Draft', 'Returned for Corrections'], true) ? 'Submit BOM' : 'View BOM') ?>
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
