<?php
$currentPage = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 12);
$totalRows = (int) ($pagination['total'] ?? 0);
$selectedCat = (string) ($filters['category'] ?? '');
$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || ($filters['category'] ?? '') !== ''
    || ($filters['date_from'] ?? '') !== ''
    || ($filters['date_to'] ?? '') !== ''
    || ($filters['sort'] ?? 'newest') !== 'newest';

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter([
        'q' => $filters['q'] ?? '',
        'status' => $filters['status'] ?? '',
        'category' => $filters['category'] ?? '',
        'date_from' => $filters['date_from'] ?? '',
        'date_to' => $filters['date_to'] ?? '',
        'sort' => ($filters['sort'] ?? 'newest') !== 'newest' ? $filters['sort'] : '',
        'page' => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn($v): bool => $v !== '');

    return url('/pm/projects' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>

<!-- Category Filter Pills & PM Project Registry Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 fw-bold mb-0">PM Project Registry</h2>
        <div class="text-secondary small">Comprehensive registry of incoming, verified, JOM-uploaded, and forwarded projects</div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Category Filters">
            <a href="<?= url('/pm/projects' . ($selectedCat === '' ? '' : '?status=' . urlencode($filters['status'] ?? ''))) ?>"
               class="btn <?= $selectedCat === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                All Categories
            </a>
            <a href="<?= url('/pm/projects?category=mrc' . (!empty($filters['status']) ? '&status=' . urlencode($filters['status']) : '')) ?>"
               class="btn <?= $selectedCat === 'mrc' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                MRC
            </a>
            <a href="<?= url('/pm/projects?category=special_project' . (!empty($filters['status']) ? '&status=' . urlencode($filters['status']) : '')) ?>"
               class="btn <?= $selectedCat === 'special_project' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                Special Project
            </a>
        </div>
        <a href="<?= url('/pm/dashboard') ?>" class="btn btn-sm btn-outline-primary">
            PM Dashboard
        </a>
        <a href="<?= url('/pm/verification-history') ?>" class="btn btn-sm btn-outline-secondary">
            Verification History
        </a>
    </div>
</div>

<!-- Unified Filter Toolbar & PM Project Verification List -->
<div class="bms-table-panel">
    <form method="GET" action="<?= url('/pm/projects') ?>" class="bms-table-toolbar flex-wrap">
        <h3 class="h6 fw-bold mb-0 me-auto">Project Verification Registry</h3>

        <select id="category" name="category" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by category">
            <option value="" <?= ($filters['category'] ?? '') === '' ? 'selected' : '' ?>>All Categories</option>
            <option value="mrc" <?= ($filters['category'] ?? '') === 'mrc' ? 'selected' : '' ?>>MRC</option>
            <option value="special_project" <?= ($filters['category'] ?? '') === 'special_project' ? 'selected' : '' ?>>Special Project</option>
        </select>

        <select id="status" name="status" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by verification status">
            <option value="">All PM Statuses</option>
            <option value="incoming" <?= ($filters['status'] ?? '') === 'incoming' ? 'selected' : '' ?>>Incoming Queue</option>
            <option value="verification" <?= ($filters['status'] ?? '') === 'verification' ? 'selected' : '' ?>>Under Verification</option>
            <option value="returned" <?= ($filters['status'] ?? '') === 'returned' ? 'selected' : '' ?>>Returned to Marketing</option>
            <option value="resubmitted" <?= ($filters['status'] ?? '') === 'resubmitted' ? 'selected' : '' ?>>Resubmitted for Verification</option>
            <option value="approved" <?= ($filters['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved by PM</option>
            <option value="awaiting_jom" <?= ($filters['status'] ?? '') === 'awaiting_jom' ? 'selected' : '' ?>>Awaiting JOM Upload</option>
            <option value="jom_uploaded" <?= ($filters['status'] ?? '') === 'jom_uploaded' ? 'selected' : '' ?>>JOM Uploaded</option>
            <option value="forwarded_to_ops" <?= ($filters['status'] ?? '') === 'forwarded_to_ops' ? 'selected' : '' ?>>Forwarded to OPS</option>
        </select>

        <input type="date"
               name="date_from"
               value="<?= e($filters['date_from'] ?? '') ?>"
               class="form-control form-control-sm bms-toolbar-input"
               title="Submission Date From"
               placeholder="From date">

        <input type="date"
               name="date_to"
               value="<?= e($filters['date_to'] ?? '') ?>"
               class="form-control form-control-sm bms-toolbar-input"
               title="Submission Date To"
               placeholder="To date">

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
                    <th style="width: 20%; min-width: 170px;">Project Name</th>
                    <th style="width: 11%; min-width: 90px;">Category</th>
                    <th style="width: 12%; min-width: 110px;">Client</th>
                    <th style="width: 11%; min-width: 100px;">Code</th>
                    <th style="width: 11%; min-width: 100px;">Submission Date</th>
                    <th style="width: 10%; min-width: 90px;">Submitted By</th>
                    <th style="width: 13%; min-width: 120px;">Status</th>
                    <th style="width: 6%; min-width: 70px;">JOM</th>
                    <th class="text-end" style="width: 6%; min-width: 90px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-4 small">
                            No projects found matching the selected criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                        <?php
                        $pId = (int) $proj['id'];
                        $pStatus = (string) ($proj['status'] ?? '');
                        $pCat = strtolower(trim((string) ($proj['project_category'] ?? $proj['workflow_type'] ?? 'mrc')));
                        $canStartReview = in_array($pStatus, [
                            'Submitted to Project Management and Admin Finance',
                            'Resubmitted to Project Management and Admin Finance',
                            'Submitted for Document Verification',
                            'Submitted for Verification',
                            'Resubmitted for Verification',
                            'Submitted'
                        ], true);
                        $isApproved = in_array($pStatus, ['Approved by Project Management', 'Document Verification Completed'], true);
                        $hasJom = !empty($proj['jom_document_id']);
                        $isForwardedOps = in_array($pStatus, ['Forwarded to OPS', 'Forwarded to Operations'], true) || !empty($proj['forwarded_to_ops_at']);
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
                            <td class="small text-nowrap"><?= format_datetime($proj['submitted_at'] ?? $proj['submitted_to_pm_at'] ?? null) ?></td>
                            <td class="small">
                                <?php if (!empty($proj['submitter_name'])): ?>
                                    <?= e($proj['submitter_name']) ?>
                                <?php else: ?>
                                    <span class="text-secondary"><?= e($proj['creator_name'] ?? 'Marketing') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= project_status_badge_class($pStatus) ?>"><?= e($pStatus) ?></span>
                            </td>
                            <td>
                                <?php if ($hasJom): ?>
                                    <span class="badge badge-soft-success small text-nowrap"><i class="bi bi-file-earmark-check me-1"></i>Uploaded</span>
                                <?php elseif ($isApproved): ?>
                                    <span class="badge bg-warning text-dark small">Pending JOM</span>
                                <?php else: ?>
                                    <span class="text-secondary small">&mdash;</span>
                                <?php endif; ?>
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
                                       title="Review Documents"
                                       aria-label="Review Documents">
                                        <?= bms_icon('prepare') ?>
                                    </a>

                                    <?php if ($canStartReview): ?>
                                        <form method="POST" action="<?= url('/projects/' . $pId . '/start-review') ?>" class="m-0 d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="bms-action-icon bms-action-icon--primary"
                                                    title="Start Review"
                                                    aria-label="Start Review">
                                                <i class="bi bi-play-circle"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($hasJom && !$isForwardedOps): ?>
                                        <a href="<?= url('/pm/projects/' . $pId . '/forward-ops') ?>"
                                           class="bms-action-icon bms-action-icon--success"
                                           title="Forward to Operations"
                                           aria-label="Forward to Operations">
                                            <?= bms_icon('send') ?>
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
