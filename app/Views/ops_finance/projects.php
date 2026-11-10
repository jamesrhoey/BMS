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
            <option value="awaiting_review" <?= ($filters['status'] ?? '') === 'awaiting_review' ? 'selected' : '' ?>>PRs Awaiting Review</option>
            <option value="pending_actual_bom" <?= ($filters['status'] ?? '') === 'pending_actual_bom' ? 'selected' : '' ?>>Actual BOM Pending Upload</option>
            <option value="submitted_to_afin" <?= ($filters['status'] ?? '') === 'submitted_to_afin' ? 'selected' : '' ?>>Actual BOM Submitted to Admin Finance</option>
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
