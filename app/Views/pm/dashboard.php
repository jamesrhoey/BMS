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

    return url('/pm/dashboard' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>

<!-- 7 Database-Driven Project Management Summary Metric Cards -->
<div class="row g-2 mb-4">
    <div class="col-6 col-md-4 col-xl">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Total in Queue</div>
            <div class="bms-stat-value"><?= (int) ($stats['total_pm_projects'] ?? 0) ?></div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Awaiting Verification</div>
            <div class="bms-stat-value text-primary"><?= (int) ($stats['awaiting_verification'] ?? 0) ?></div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Under Review</div>
            <div class="bms-stat-value text-warning"><?= (int) ($stats['under_review'] ?? 0) ?></div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Returned</div>
            <div class="bms-stat-value text-danger"><?= (int) ($stats['returned_to_marketing'] ?? 0) ?></div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Resubmitted</div>
            <div class="bms-stat-value text-info"><?= (int) ($stats['resubmitted_for_verification'] ?? 0) ?></div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Verified Completed</div>
            <div class="bms-stat-value text-success"><?= (int) ($stats['verification_completed'] ?? 0) ?></div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl">
        <div class="bms-stat-card">
            <div class="bms-stat-label">JOM Issued</div>
            <div class="bms-stat-value text-primary"><?= (int) ($stats['jom_issued'] ?? 0) ?></div>
        </div>
    </div>
</div>

<!-- Unified Nuclias-Style Filter Toolbar & Marketing Document Verification Queue Table -->
<div class="bms-table-panel">
    <form method="GET" action="<?= url('/pm/dashboard') ?>" class="bms-table-toolbar">
        <h3 class="h6 fw-bold mb-0 me-auto">Marketing Document Verification Queue</h3>

        <select id="status" name="status" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by verification status">
            <option value="">All Verification Statuses</option>
            <option value="awaiting" <?= ($filters['status'] ?? '') === 'awaiting' ? 'selected' : '' ?>>Awaiting Verification</option>
            <option value="under_review" <?= ($filters['status'] ?? '') === 'under_review' ? 'selected' : '' ?>>Under Review</option>
            <option value="resubmitted" <?= ($filters['status'] ?? '') === 'resubmitted' ? 'selected' : '' ?>>Resubmitted for Verification</option>
            <option value="returned" <?= ($filters['status'] ?? '') === 'returned' ? 'selected' : '' ?>>Returned for Corrections</option>
            <option value="completed" <?= in_array($filters['status'] ?? '', ['completed', 'verified'], true) ? 'selected' : '' ?>>Verification Completed</option>
            <option value="jom_issued" <?= ($filters['status'] ?? '') === 'jom_issued' ? 'selected' : '' ?>>JOM Number Issued</option>
        </select>

        <select id="sort" name="sort" class="form-select form-select-sm bms-toolbar-select" aria-label="Sort by date">
            <option value="submitted" <?= ($filters['sort'] ?? 'submitted') === 'submitted' ? 'selected' : '' ?>>Submission Date &darr;</option>
            <option value="newest" <?= ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest First</option>
            <option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
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
            <a href="<?= url('/pm/dashboard') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline" title="Clear Filters" aria-label="Clear Filters">
                <?= bms_icon('reset', 16) ?>
            </a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Project Name</th>
                    <th>Client</th>
                    <th>Code / JOM</th>
                    <th>Submission Date</th>
                    <th>Submitted By</th>
                    <th>Verification Status</th>
                    <th>Last Updated</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4 small">
                            No projects currently match the verification queue criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                        <?php
                        $pId = (int) $proj['id'];
                        $pStatus = (string) ($proj['status'] ?? '');
                        $canStartReview = in_array($pStatus, ['Submitted for Document Verification', 'Resubmitted for Verification', 'Submitted'], true);
                        $isUnderReview = $pStatus === 'Under Review';
                        $isVerified = $pStatus === 'Document Verification Completed';
                        ?>
                        <tr>
                            <td>
                                <a href="<?= url('/projects/' . $pId) ?>" class="fw-semibold text-dark text-decoration-none">
                                    <?= e($proj['project_name']) ?>
                                </a>
                                <?php if (!empty($proj['project_location'])): ?>
                                    <div class="text-secondary small"><?= e($proj['project_location']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($proj['client_name']) ?></td>
                            <td>
                                <span class="font-monospace small fw-medium"><?= e($proj['project_code']) ?></span>
                                <?php if (!empty($proj['jom_number'])): ?>
                                    <div><span class="badge bg-primary font-monospace" style="font-size: 0.72rem;"><?= e($proj['jom_number']) ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-nowrap"><?= format_datetime($proj['submitted_at'] ?? null) ?></td>
                            <td class="small">
                                <?php if (!empty($proj['submitter_name'])): ?>
                                    <?= e($proj['submitter_name']) ?>
                                <?php else: ?>
                                    <span class="text-secondary"><?= e($proj['creator_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= project_status_badge_class($pStatus) ?>"><?= e($pStatus) ?></span>
                            </td>
                            <td class="small text-secondary text-nowrap"><?= format_datetime($proj['updated_at'] ?? $proj['created_at']) ?></td>
                            <td class="text-end text-nowrap">
                                <div class="bms-action-group">
                                    <a href="<?= url('/projects/' . $pId) ?>"
                                       class="bms-action-icon bms-action-icon--primary"
                                       title="Inspect Documents & Verify"
                                       aria-label="Inspect Documents & Verify">
                                        <?= bms_icon('eye') ?>
                                    </a>

                                    <?php if ($canStartReview): ?>
                                        <form method="POST" action="<?= url('/projects/' . $pId . '/start-review') ?>" class="m-0 d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="bms-action-icon bms-action-icon--primary"
                                                    title="Start Review"
                                                    aria-label="Start Review">
                                                <?= bms_icon('prepare') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($pStatus !== 'Returned for Corrections' && !$isVerified): ?>
                                        <a href="<?= url('/projects/' . $pId . '#pm-review-panel') ?>"
                                           class="bms-action-icon bms-action-icon--danger"
                                           title="Return for Corrections"
                                           aria-label="Return for Corrections">
                                            <?= bms_icon('alert') ?>
                                        </a>
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

