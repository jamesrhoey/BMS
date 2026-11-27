<?php
$currentPage = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 15);
$totalRows = (int) ($pagination['total'] ?? 0);
$selectedCat = (string) ($filters['category'] ?? '');
$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['category'] ?? '') !== ''
    || ($filters['date_from'] ?? '') !== ''
    || ($filters['date_to'] ?? '') !== ''
    || ($filters['sort'] ?? 'newest') !== 'newest';

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter([
        'q' => $filters['q'] ?? '',
        'category' => $filters['category'] ?? '',
        'date_from' => $filters['date_from'] ?? '',
        'date_to' => $filters['date_to'] ?? '',
        'sort' => ($filters['sort'] ?? 'newest') !== 'newest' ? $filters['sort'] : '',
        'page' => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn($v): bool => $v !== '');

    return url('/pm/verification-history' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>

<!-- PM Verification History Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 fw-bold mb-0">PM Verification History</h2>
        <div class="text-secondary small">Historical record of all projects reviewed, approved, JOM-uploaded, and forwarded by Project Management</div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Category Filters">
            <a href="<?= url('/pm/verification-history' . ($selectedCat === '' ? '' : '')) ?>"
               class="btn <?= $selectedCat === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                All Categories
            </a>
            <a href="<?= url('/pm/verification-history?category=mrc') ?>"
               class="btn <?= $selectedCat === 'mrc' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                MRC
            </a>
            <a href="<?= url('/pm/verification-history?category=special_project') ?>"
               class="btn <?= $selectedCat === 'special_project' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                Special Project
            </a>
        </div>
        <a href="<?= url('/pm/dashboard') ?>" class="btn btn-sm btn-outline-primary">
            PM Dashboard
        </a>
        <a href="<?= url('/pm/projects') ?>" class="btn btn-sm btn-outline-secondary">
            All PM Projects
        </a>
    </div>
</div>

<!-- Unified Filter Toolbar & Verification History Table -->
<div class="bms-table-panel">
    <form method="GET" action="<?= url('/pm/verification-history') ?>" class="bms-table-toolbar flex-wrap">
        <h3 class="h6 fw-bold mb-0 me-auto">Historical Verification Registry</h3>

        <select id="category" name="category" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by category">
            <option value="" <?= ($filters['category'] ?? '') === '' ? 'selected' : '' ?>>All Categories</option>
            <option value="mrc" <?= ($filters['category'] ?? '') === 'mrc' ? 'selected' : '' ?>>MRC</option>
            <option value="special_project" <?= ($filters['category'] ?? '') === 'special_project' ? 'selected' : '' ?>>Special Project</option>
        </select>

        <input type="date"
               name="date_from"
               value="<?= e($filters['date_from'] ?? '') ?>"
               class="form-control form-control-sm bms-toolbar-input"
               title="Date From"
               placeholder="From date">

        <input type="date"
               name="date_to"
               value="<?= e($filters['date_to'] ?? '') ?>"
               class="form-control form-control-sm bms-toolbar-input"
               title="Date To"
               placeholder="To date">

        <select id="sort" name="sort" class="form-select form-select-sm bms-toolbar-select" aria-label="Sort by date">
            <option value="newest" <?= ($filters['sort'] ?? 'newest') === 'newest' ? 'selected' : '' ?>>Newest First</option>
            <option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            <option value="submitted" <?= ($filters['sort'] ?? '') === 'submitted' ? 'selected' : '' ?>>Submission Date &darr;</option>
            <option value="client" <?= ($filters['sort'] ?? '') === 'client' ? 'selected' : '' ?>>Client Name</option>
        </select>

        <input type="text"
               id="q"
               name="q"
               value="<?= e($filters['q'] ?? '') ?>"
               class="form-control form-control-sm bms-toolbar-input"
               placeholder="Search 'Keyword'">

        <button type="submit" class="bms-toolbar-icon-btn" title="Apply Filters" aria-label="Apply Filters">
            <?= bms_icon('search', 16) ?>
        </button>

        <?php if ($hasActiveFilters): ?>
            <a href="<?= url('/pm/verification-history') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline" title="Clear Filters" aria-label="Clear Filters">
                <?= bms_icon('reset', 16) ?>
            </a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th style="width: 22%; min-width: 170px;">Project Name</th>
                    <th style="width: 11%; min-width: 90px;">Category</th>
                    <th style="width: 13%; min-width: 110px;">Client</th>
                    <th style="width: 11%; min-width: 100px;">Code</th>
                    <th style="width: 11%; min-width: 100px;">Verified At</th>
                    <th style="width: 11%; min-width: 100px;">Verified By</th>
                    <th style="width: 13%; min-width: 120px;">Current Status</th>
                    <th class="text-end" style="width: 8%; min-width: 80px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4 small">
                            No verification history records found matching the selected criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                        <?php
                        $pId = (int) $proj['id'];
                        $pStatus = (string) ($proj['status'] ?? '');
                        $pCat = strtolower(trim((string) ($proj['project_category'] ?? $proj['workflow_type'] ?? 'mrc')));
                        ?>
                        <tr>
                            <td>
                                <a href="<?= url('/projects/' . $pId) ?>" class="fw-semibold text-dark text-decoration-none text-truncate d-block" title="<?= e($proj['project_name']) ?>">
                                    <?= e($proj['project_name']) ?>
                                </a>
                                <?php if (!empty($proj['project_location'])): ?>
                                    <div class="text-secondary small text-truncate"><?= e($proj['project_location']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($pCat === 'special_project'): ?>
                                    Special Project
                                <?php else: ?>
                                    MRC
                                <?php endif; ?>
                            </td>
                            <td><?= e($proj['client_name']) ?></td>
                            <td>
                                <span class="font-monospace small fw-medium"><?= e($proj['project_code']) ?></span>
                            </td>
                            <td class="small text-nowrap"><?= format_datetime($proj['verified_at'] ?? $proj['updated_at'] ?? null) ?></td>
                            <td class="small">
                                <?php if (!empty($proj['verifier_name'])): ?>
                                    <?= e($proj['verifier_name']) ?>
                                <?php else: ?>
                                    <span class="text-secondary">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= project_status_badge_class($pStatus) ?>"><?= e($pStatus) ?></span>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="bms-action-group">
                                    <a href="<?= url('/projects/' . $pId) ?>"
                                       class="bms-action-icon bms-action-icon--primary"
                                       title="View Project"
                                       aria-label="View Project">
                                        <?= bms_icon('eye') ?>
                                    </a>

                                    <a href="<?= url('/pm/projects/' . $pId . '/verification') ?>"
                                       class="bms-action-icon bms-action-icon--primary"
                                       title="View Verification Details"
                                       aria-label="View Verification Details">
                                        <?= bms_icon('prepare') ?>
                                    </a>
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
