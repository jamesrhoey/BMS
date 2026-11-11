<?php
$currentPage = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 12);
$totalRows = (int) ($pagination['total'] ?? 0);
$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || ($filters['sort'] ?? 'newest') !== 'newest';

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter([
        'q' => $filters['q'] ?? '',
        'status' => $filters['status'] ?? '',
        'sort' => ($filters['sort'] ?? 'newest') !== 'newest' ? $filters['sort'] : '',
        'page' => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn($v): bool => $v !== '');

    return url('/pm/projects' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>

<!-- Unified Nuclias-Style Filter Toolbar & PM Project Verification List -->
<div class="bms-table-panel">
    <form method="GET" action="<?= url('/pm/projects') ?>" class="bms-table-toolbar">
        <h3 class="h6 fw-bold mb-0 me-auto">PM Project Verification Registry</h3>

        <select id="status" name="status" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by verification status">
            <option value="">All Verification Statuses</option>
            <option value="pending" <?= in_array($filters['status'] ?? '', ['pending', 'awaiting'], true) ? 'selected' : '' ?>>Incoming / Pending Verification</option>
            <option value="verification_queue" <?= in_array($filters['status'] ?? '', ['verification_queue', 'pm_queue'], true) ? 'selected' : '' ?>>Document Verification Queue</option>
            <option value="under_review" <?= ($filters['status'] ?? '') === 'under_review' ? 'selected' : '' ?>>Under Review</option>
            <option value="returned" <?= ($filters['status'] ?? '') === 'returned' ? 'selected' : '' ?>>Returned to Marketing</option>
            <option value="resubmitted" <?= ($filters['status'] ?? '') === 'resubmitted' ? 'selected' : '' ?>>Resubmitted for Verification</option>
            <option value="approved" <?= in_array($filters['status'] ?? '', ['approved', 'completed', 'verified'], true) ? 'selected' : '' ?>>Approved by Project Management</option>
            <option value="ready_for_admin_finance" <?= in_array($filters['status'] ?? '', ['ready_for_admin_finance', 'ready_forward'], true) ? 'selected' : '' ?>>Ready for Admin Finance</option>
            <option value="forwarded_to_admin_finance" <?= in_array($filters['status'] ?? '', ['forwarded_to_admin_finance', 'forwarded'], true) ? 'selected' : '' ?>>Forwarded to Admin Finance</option>
        </select>

        <select id="sort" name="sort" class="form-select form-select-sm bms-toolbar-select" aria-label="Sort by date">
            <option value="newest" <?= ($filters['sort'] ?? 'newest') === 'newest' ? 'selected' : '' ?>>Newest First</option>
            <option value="submitted" <?= ($filters['sort'] ?? '') === 'submitted' ? 'selected' : '' ?>>Submission Date &darr;</option>
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
            <a href="<?= url('/pm/projects') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline" title="Clear Filters" aria-label="Clear Filters">
                <?= bms_icon('reset', 16) ?>
            </a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th style="width: 22%; min-width: 180px;">Project Name</th>
                    <th style="width: 13%; min-width: 110px;">Client</th>
                    <th style="width: 11%; min-width: 100px;">Code / JOM</th>
                    <th style="width: 11%; min-width: 100px;">Submission Date</th>
                    <th style="width: 11%; min-width: 100px;">Submitted By</th>
                    <th style="width: 14%; min-width: 130px;">Verification Status</th>
                    <th style="width: 10%; min-width: 95px;">Last Updated</th>
                    <th class="text-end" style="width: 8%; min-width: 80px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4 small">
                            No projects found matching the selected criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                        <?php
                        $pId = (int) $proj['id'];
                        $pStatus = (string) ($proj['status'] ?? '');
                        $canStartReview = in_array($pStatus, ['Submitted for Document Verification', 'Submitted for Verification', 'Resubmitted for Verification', 'Submitted'], true);
                        $isApproved = in_array($pStatus, ['Approved by Project Management', 'Document Verification Completed'], true);
                        $isForwarded = $pStatus === 'Forwarded to Admin Finance' || !empty($proj['forwarded_to_afin_at']);
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
                                <div class="text-truncate" title="<?= e($proj['client_name']) ?>"><?= e($proj['client_name']) ?></div>
                            </td>
                            <td class="font-monospace small">
                                <div class="text-truncate"><?= e($proj['project_code']) ?></div>
                                <?php if (!empty($proj['jom_number'])): ?>
                                    <div><span class="badge bg-primary font-monospace"><?= e($proj['jom_number']) ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <div class="text-nowrap"><?= format_datetime($proj['submitted_at'] ?? null) ?></div>
                            </td>
                            <td class="small">
                                <div class="text-truncate"><?= e(!empty($proj['submitter_name']) ? $proj['submitter_name'] : ($proj['creator_name'] ?? '—')) ?></div>
                            </td>
                            <td>
                                <span class="badge <?= project_status_badge_class($pStatus) ?>"><?= e($pStatus) ?></span>
                            </td>
                            <td class="small text-secondary">
                                <div class="text-nowrap"><?= format_datetime($proj['updated_at'] ?? $proj['created_at']) ?></div>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="bms-action-group">
                                    <a href="<?= url('/projects/' . $pId) ?>"
                                       class="bms-action-icon bms-action-icon--primary"
                                       title="Review Project Documents"
                                       aria-label="Review Project Documents">
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

                                    <?php if ($isApproved && !$isForwarded): ?>
                                        <form method="POST" action="<?= url('/projects/' . $pId . '/forward-to-admin-finance') ?>" class="m-0 d-inline" data-confirm="Forward approved project '<?= e($proj['project_code']) ?>' to Admin Finance?">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="bms-action-icon bms-action-icon--success"
                                                    title="Forward to Admin Finance"
                                                    aria-label="Forward to Admin Finance">
                                                <?= bms_icon('send') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (!in_array($pStatus, ['Returned for Corrections', 'Returned to Marketing for Corrections'], true) && !$isApproved && !$isForwarded): ?>
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

