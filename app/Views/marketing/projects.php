<?php
use App\Models\Project;

$currentPage = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 12);
$totalRows = (int) ($pagination['total'] ?? 0);
$reqTotal = (int) ($requiredCount ?? 6);
$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['category'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || ($filters['completeness'] ?? '') !== ''
    || ($filters['sort'] ?? 'newest') !== 'newest'
    || ($filters['view'] ?? '') !== '';

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter([
        'q' => $filters['q'] ?? '',
        'category' => $filters['category'] ?? '',
        'status' => $filters['status'] ?? '',
        'completeness' => $filters['completeness'] ?? '',
        'sort' => ($filters['sort'] ?? 'newest') !== 'newest' ? $filters['sort'] : '',
        'view' => $filters['view'] ?? '',
        'page' => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn($v): bool => $v !== '');

    return url('/marketing/projects' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>

<div class="bms-table-panel">
    <form method="GET" action="<?= url('/marketing/projects') ?>" class="bms-table-toolbar">
        <h3 class="h6 fw-bold mb-0 me-auto">Marketing Projects Registry</h3>

        <!-- Category Filter (MRC / Special Project) -->
        <select id="category" name="category" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by project category">
            <option value="">All Categories</option>
            <option value="mrc" <?= ($filters['category'] ?? '') === 'mrc' ? 'selected' : '' ?>>MRC (6 Docs)</option>
            <option value="special_project" <?= ($filters['category'] ?? '') === 'special_project' ? 'selected' : '' ?>>Special Project (7 Docs + PO)</option>
        </select>

        <!-- Status Filter -->
        <select id="status" name="status" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by workflow status">
            <option value="">All Marketing Statuses</option>
            <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft Projects</option>
            <option value="returned" <?= ($filters['status'] ?? '') === 'returned' ? 'selected' : '' ?>>Returned for Corrections</option>
            <option value="submitted_queue" <?= in_array($filters['status'] ?? '', ['submitted', 'submitted_queue', 'pm_queue'], true) ? 'selected' : '' ?>>Submitted to PM &amp; Admin Finance</option>
            <option value="under_review" <?= ($filters['status'] ?? '') === 'under_review' ? 'selected' : '' ?>>Under Review</option>
            <?php foreach (($statuses ?? []) as $st): ?>
                <option value="<?= e((string) $st) ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>>
                    <?= e((string) $st) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Completeness Filter -->
        <select id="completeness" name="completeness" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by document checklist completeness">
            <option value="">All Document Statuses</option>
            <option value="complete" <?= ($filters['completeness'] ?? '') === 'complete' ? 'selected' : '' ?>>Complete Checklist (100%)</option>
            <option value="incomplete" <?= ($filters['completeness'] ?? '') === 'incomplete' ? 'selected' : '' ?>>Incomplete Checklist</option>
        </select>

        <!-- Sort Filter -->
        <select id="sort" name="sort" class="form-select form-select-sm bms-toolbar-select" aria-label="Sort by created date">
            <option value="newest" <?= ($filters['sort'] ?? 'newest') === 'newest' ? 'selected' : '' ?>>Newest First</option>
            <option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
        </select>

        <input type="text"
               id="q"
               name="q"
               value="<?= e($filters['q'] ?? '') ?>"
               class="form-control form-control-sm bms-toolbar-input"
               placeholder="Search Code / Name / Client">

        <button type="submit" class="bms-toolbar-icon-btn" title="Apply Filters" aria-label="Apply Filters">
            <?= bms_icon('search', 16) ?>
        </button>

        <?php if ($hasActiveFilters): ?>
            <a href="<?= url('/marketing/projects') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline" title="Clear Filters" aria-label="Clear Filters">
                <?= bms_icon('reset', 16) ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($canRegisterProject)): ?>
            <a href="<?= url('/marketing/projects/create') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--primary" title="Add Project" aria-label="Add Project">
                <?= bms_icon('plus', 16) ?>
            </a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width: 12%; min-width: 110px;">Project Code</th>
                    <th style="width: 11%; min-width: 100px;">Category</th>
                    <th style="width: 25%; min-width: 200px;">Project &amp; Client</th>
                    <th style="width: 13%; min-width: 120px;">Current Dept</th>
                    <th style="width: 15%; min-width: 140px;">Document Checklist</th>
                    <th style="width: 14%; min-width: 130px;">Workflow Status</th>
                    <th style="width: 10%; min-width: 95px;">Created <?= ($filters['sort'] ?? 'newest') === 'oldest' ? '&uarr;' : '&darr;' ?></th>
                    <th class="text-end" style="width: 8%; min-width: 80px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4 small">No Marketing projects match your current search or filter criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                        <?php
                        $projId = (int) $proj['id'];
                        $uploadedReq = (int) ($proj['uploaded_required_count'] ?? 0);
                        $requiredCnt = (int) ($proj['required_total_count'] ?? 6);
                        $isComplete = !empty($proj['is_documents_complete']);
                        $status = (string) ($proj['status'] ?? 'Draft');
                        $isEditable = Project::isMarketingEditable($proj);
                        $canStartPrep = $status === 'Draft'
                            || ($status === 'Submitted' && (($proj['verification_status'] ?? 'not_submitted') === 'not_submitted'));
                        $catLabel = (string) ($proj['category_label'] ?? (($proj['workflow_type'] ?? '') === 'special_project' ? 'Special Project' : 'MRC'));
                        $isSpecial = ($catLabel === 'Special Project');
                        ?>
                        <tr class="<?= in_array($status, ['Returned for Corrections', 'Returned to Marketing for Corrections', 'Returned to Marketing'], true) ? 'checklist-row-attention' : '' ?>">
                            <td class="font-monospace small fw-semibold">
                                <a href="<?= url('/projects/' . $projId) ?>" class="text-decoration-none">
                                    <?= e($proj['project_code']) ?>
                                </a>
                                <?php if (!empty($proj['jom_number'])): ?>
                                    <div><span class="badge bg-primary font-monospace"><?= e($proj['jom_number']) ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isSpecial): ?>
                                    <span class="badge" style="background-color: #6f42c1; color: #fff;">Special Project</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark">MRC</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold text-truncate" title="<?= e($proj['project_name']) ?>"><?= e($proj['project_name']) ?></div>
                                <div class="small text-secondary text-truncate" title="Client: <?= e($proj['client_name']) ?><?= !empty($proj['project_location']) ? ' • ' . e($proj['project_location']) : '' ?><?= (!empty($proj['return_reason'])) ? ' • Note: ' . e($proj['return_reason']) : '' ?>">
                                    Client: <strong><?= e($proj['client_name']) ?></strong>
                                    <?php if (!empty($proj['project_location'])): ?>
                                        &bull; <?= e($proj['project_location']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($proj['current_department_code'])): ?>
                                    <div class="d-flex align-items-center gap-1 text-truncate">
                                        <span class="badge badge-soft-secondary"><?= e($proj['current_department_code']) ?></span>
                                        <span class="small text-secondary text-truncate"><?= e($proj['current_department_name']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-secondary small">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isComplete): ?>
                                    <div class="text-truncate"><span class="badge badge-soft-success"><?= $uploadedReq ?>/<?= $requiredCnt ?> Complete</span></div>
                                    <div class="text-secondary small text-truncate">Ready for submission</div>
                                <?php else: ?>
                                    <div class="text-truncate"><span class="badge badge-soft-warning"><?= $uploadedReq ?>/<?= $requiredCnt ?> Uploaded</span></div>
                                    <div class="text-danger small text-truncate"><?= max(0, $requiredCnt - $uploadedReq) ?> missing</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-truncate"><span class="badge <?= project_status_badge_class($status) ?>"><?= e($status) ?></span></div>
                                <?php if (!empty($proj['return_reason']) && in_array($status, ['Returned for Corrections', 'Returned to Marketing for Corrections', 'Returned to Marketing'], true)): ?>
                                    <div class="text-danger small text-truncate" title="Correction Note: <?= e($proj['return_reason']) ?>">Note: <?= e($proj['return_reason']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary">
                                <div><?= format_datetime($proj['created_at']) ?></div>
                                <div class="text-truncate" style="font-size: 0.74rem;">By <?= e($proj['creator_name']) ?></div>
                            </td>
                            <td class="text-end">
                                <div class="bms-action-group">
                                    <a href="<?= url('/projects/' . $projId) ?>"
                                       class="bms-action-icon bms-action-icon--primary"
                                       title="View Project Details"
                                       aria-label="View Project Details">
                                        <?= bms_icon('eye') ?>
                                    </a>

                                    <?php if ($isEditable): ?>
                                        <a href="<?= url('/projects/' . $projId . '/edit') ?>"
                                           class="bms-action-icon"
                                           title="Edit Project"
                                           aria-label="Edit Project">
                                            <?= bms_icon('edit') ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($canStartPrep && !$isComplete): ?>
                                        <form method="POST" action="<?= url('/projects/' . $projId . '/prepare-documents') ?>" class="d-inline m-0">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="bms-action-icon bms-action-icon--warning"
                                                    title="Prepare Documents"
                                                    aria-label="Prepare Documents">
                                                <?= bms_icon('prepare') ?>
                                            </button>
                                        </form>
                                    <?php elseif ($isEditable): ?>
                                        <a href="<?= url('/projects/' . $projId . '?upload=auto#checklist') ?>"
                                           class="bms-action-icon bms-action-icon--warning"
                                           title="Upload / Manage Documents"
                                           aria-label="Upload / Manage Documents">
                                            <?= bms_icon('upload') ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (in_array($status, ['Returned for Corrections', 'Returned to Marketing for Corrections', 'Returned to Marketing'], true)): ?>
                                        <a href="<?= url('/projects/' . $projId . '?upload=auto#return-banner') ?>"
                                           class="bms-action-icon bms-action-icon--danger"
                                           title="View Return Notes &amp; Fix"
                                           aria-label="View Return Notes &amp; Fix">
                                            <?= bms_icon('alert') ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($isEditable && $isComplete): ?>
                                        <?php $submitTitle = in_array($status, ['Returned for Corrections', 'Returned to Marketing for Corrections', 'Returned to Marketing'], true) ? 'Resubmit to PM & Admin Finance' : 'Submit to PM & Admin Finance'; ?>
                                        <form method="POST" action="<?= url('/projects/' . $projId . '/submit') ?>" class="d-inline m-0" data-confirm="Submit '<?= e($proj['project_code']) ?>' simultaneously to Project Management and Admin Finance?">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="bms-action-icon bms-action-icon--success"
                                                    title="<?= e($submitTitle) ?>"
                                                    aria-label="<?= e($submitTitle) ?>">
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
