<?php
$currentPage = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 12);
$totalRows = (int) ($pagination['total'] ?? 0);
$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || ($filters['sort'] ?? 'received') !== 'received'
    || ($filters['date_from'] ?? '') !== ''
    || ($filters['date_to'] ?? '') !== ''
    || ($filters['jom_number'] ?? '') !== '';

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter([
        'q' => $filters['q'] ?? '',
        'status' => $filters['status'] ?? '',
        'sort' => ($filters['sort'] ?? 'received') !== 'received' ? $filters['sort'] : '',
        'date_from' => $filters['date_from'] ?? '',
        'date_to' => $filters['date_to'] ?? '',
        'jom_number' => $filters['jom_number'] ?? '',
        'page' => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn($v): bool => $v !== '');

    return url('/operations/projects' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>

<div class="bms-table-panel">
    <form method="GET" action="<?= url('/operations/projects') ?>" class="bms-table-toolbar flex-wrap gap-2">
        <h3 class="h6 fw-bold mb-0 me-auto">Operations Incoming Projects &amp; Purchase Requests Registry</h3>

        <select id="status" name="status" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by PR status">
            <option value="">All Incoming Projects</option>
            <option value="awaiting_pr" <?= ($filters['status'] ?? '') === 'awaiting_pr' ? 'selected' : '' ?>>Awaiting PR Preparation</option>
            <option value="preparing_pr" <?= in_array(($filters['status'] ?? ''), ['preparing_pr', 'draft'], true) ? 'selected' : '' ?>>PRs Being Prepared (Draft)</option>
            <option value="submitted_pr" <?= in_array(($filters['status'] ?? ''), ['submitted_pr', 'pending'], true) ? 'selected' : '' ?>>PRs Submitted to OPS Finance (Pending)</option>
            <option value="returned" <?= ($filters['status'] ?? '') === 'returned' ? 'selected' : '' ?>>Returned for Revision</option>
            <option value="rejected" <?= ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected PRs</option>
            <option value="approved" <?= ($filters['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved PRs</option>
            <option value="converted_po" <?= ($filters['status'] ?? '') === 'converted_po' ? 'selected' : '' ?>>Converted to PO / Ordered</option>
            <option value="awaiting_purchasing" <?= ($filters['status'] ?? '') === 'awaiting_purchasing' ? 'selected' : '' ?>>Awaiting Purchasing</option>
        </select>

        <select id="sort" name="sort" class="form-select form-select-sm bms-toolbar-select" aria-label="Sort by date">
            <option value="received" <?= ($filters['sort'] ?? 'received') === 'received' ? 'selected' : '' ?>>Date Received &darr;</option>
            <option value="pr_date" <?= ($filters['sort'] ?? '') === 'pr_date' ? 'selected' : '' ?>>PR Date &darr;</option>
            <option value="pr_number" <?= ($filters['sort'] ?? '') === 'pr_number' ? 'selected' : '' ?>>PR Number &darr;</option>
            <option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            <option value="client" <?= ($filters['sort'] ?? '') === 'client' ? 'selected' : '' ?>>Client Name</option>
        </select>

        <div class="d-flex align-items-center gap-1">
            <input type="date"
                   id="date_from"
                   name="date_from"
                   value="<?= e($filters['date_from'] ?? '') ?>"
                   class="form-control form-control-sm"
                   style="max-width: 135px;"
                   title="PR Date From"
                   placeholder="From">
            <span class="text-secondary small">&ndash;</span>
            <input type="date"
                   id="date_to"
                   name="date_to"
                   value="<?= e($filters['date_to'] ?? '') ?>"
                   class="form-control form-control-sm"
                   style="max-width: 135px;"
                   title="PR Date To"
                   placeholder="To">
        </div>

        <input type="text"
               id="q"
               name="q"
               value="<?= e($filters['q'] ?? '') ?>"
               class="form-control form-control-sm bms-toolbar-input"
               placeholder="Search project, client, JOM, PR, requester...">

        <button type="submit" class="bms-toolbar-icon-btn" title="Apply Filters" aria-label="Apply Filters">
            <?= bms_icon('search', 16) ?>
        </button>

        <?php if ($hasActiveFilters): ?>
            <a href="<?= url('/operations/projects') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline" title="Clear Filters" aria-label="Clear Filters">
                <?= bms_icon('reset', 16) ?>
            </a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th style="width: 20%;">Project Name</th>
                    <th style="width: 13%;">Client / End User</th>
                    <th style="width: 10%;">JOM Number</th>
                    <th style="width: 11%;">SLD</th>
                    <th style="width: 11%;">Marketing BOM</th>
                    <th style="width: 17%;">Purchase Request</th>
                    <th style="width: 9%;">Date Received</th>
                    <th class="text-end" style="width: 9%;">Available Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4 small">
                            No Operations projects found matching the selected criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                        <?php
                        $pId = (int) $proj['id'];
                        $pStatus = (string) ($proj['status'] ?? '');
                        $sldDocId = !empty($proj['ops_sld_document_id']) ? (int) $proj['ops_sld_document_id'] : 0;
                        $bomDocId = !empty($proj['ops_bom_document_id']) ? (int) $proj['ops_bom_document_id'] : 0;
                        $prNumber = (string) ($proj['pr_number'] ?? '');
                        $prStatus = (string) ($proj['pr_status'] ?? '');
                        $prItemsCount = (int) ($proj['pr_items_count'] ?? 0);
                        $prPurpose = (string) ($proj['pr_purpose'] ?? '');
                        $requesterName = (string) ($proj['requester_name'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <a href="<?= url('/projects/' . $pId . '#operations-pr-panel') ?>" class="project-name text-decoration-none" title="<?= e($proj['project_name']) ?>">
                                    <?= e($proj['project_name']) ?>
                                </a>
                                <div class="cell-subtext font-monospace" title="<?= e($proj['project_code']) ?>"><?= e($proj['project_code']) ?></div>
                            </td>
                            <td>
                                <div class="cell-text" title="<?= e($proj['client_name']) ?>"><?= e($proj['client_name']) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($proj['jom_number'])): ?>
                                    <span class="badge bg-primary font-monospace"><?= e($proj['jom_number']) ?></span>
                                <?php else: ?>
                                    <span class="text-secondary small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($sldDocId > 0): ?>
                                    <div class="cell-text fw-semibold" title="<?= e($proj['sld_filename'] ?? 'SLD') ?>">
                                        <?= e($proj['sld_filename'] ?? 'SLD Document') ?>
                                    </div>
                                    <div class="cell-subtext">
                                        <a href="<?= url('/projects/' . $pId . '/documents/' . $sldDocId . '/view') ?>">View</a>
                                        &bull;
                                        <a href="<?= url('/projects/' . $pId . '/documents/' . $sldDocId . '/download') ?>" class="text-secondary">Download</a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-secondary small">Not linked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($bomDocId > 0): ?>
                                    <div class="cell-text fw-semibold" title="<?= e($proj['bom_filename'] ?? 'Marketing BOM') ?>">
                                        <?= e($proj['bom_filename'] ?? 'Marketing BOM') ?>
                                    </div>
                                    <div class="cell-subtext">
                                        <a href="<?= url('/projects/' . $pId . '/documents/' . $bomDocId . '/view') ?>">View</a>
                                        &bull;
                                        <a href="<?= url('/projects/' . $pId . '/documents/' . $bomDocId . '/download') ?>" class="text-secondary">Download</a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-secondary small">Not linked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($prNumber !== ''): ?>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="font-monospace fw-semibold cell-text" style="max-width: 90px;"><?= e($prNumber) ?></span>
                                        <span class="badge <?= pr_status_badge_class($prStatus) ?>"><?= e($prStatus) ?></span>
                                    </div>
                                    <div class="cell-subtext" title="<?= !empty($prPurpose) ? 'Purpose: ' . e($prPurpose) : '' ?>">
                                        <?= $prItemsCount ?> item<?= $prItemsCount === 1 ? '' : 's' ?><?php if ($requesterName !== ''): ?> &bull; <?= e($requesterName) ?><?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge badge-soft-warning">Awaiting PR Preparation</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <div class="cell-subtext"><?= format_datetime($proj['forwarded_to_ops_at'] ?? null) ?></div>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="<?= url('/projects/' . $pId . '#operations-pr-panel') ?>"
                                   class="btn btn-sm <?= $prNumber === '' || in_array($prStatus, ['Draft', 'Returned for Corrections', 'Returned for Revision'], true) ? 'btn-primary' : 'btn-outline-primary' ?>">
                                    <?= $prNumber === '' ? 'Prepare PR' : (in_array($prStatus, ['Draft', 'Returned for Corrections', 'Returned for Revision'], true) ? 'Edit PR' : 'View') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= render_table_footer($currentPage, $perPage, count($projects), $totalRows, $buildPageUrl) ?>
</div>
