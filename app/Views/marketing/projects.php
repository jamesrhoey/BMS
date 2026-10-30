<?php
use App\Models\Project;

$currentPage = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 15);
$totalRows = (int) ($pagination['total'] ?? 0);
$reqTotal = (int) ($requiredCount ?? 6);
$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || ($filters['completeness'] ?? '') !== ''
    || ($filters['sort'] ?? 'newest') !== 'newest';

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter([
        'q' => $filters['q'] ?? '',
        'status' => $filters['status'] ?? '',
        'completeness' => $filters['completeness'] ?? '',
        'sort' => ($filters['sort'] ?? 'newest') !== 'newest' ? $filters['sort'] : '',
        'page' => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn($v): bool => $v !== '');

    return url('/marketing/projects' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>

<!-- Unified Nuclias-Style Filter Toolbar, Striped Table & Pagination Panel -->
<div class="bms-table-panel">
    <form method="GET" action="<?= url('/marketing/projects') ?>" class="bms-table-toolbar">
        <h3 class="h6 fw-bold mb-0 me-auto">Project List</h3>
        <select id="status" name="status" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by workflow status">
            <option value="">All Marketing Statuses</option>
            <?php foreach (($statuses ?? []) as $st): ?>
                <option value="<?= e((string) $st) ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>>
                    <?= e((string) $st) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="completeness" name="completeness" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by document checklist completeness">
            <option value="">All Checklists</option>
            <option value="complete" <?= ($filters['completeness'] ?? '') === 'complete' ? 'selected' : '' ?>>Complete (<?= $reqTotal ?>/<?= $reqTotal ?>)</option>
            <option value="incomplete" <?= ($filters['completeness'] ?? '') === 'incomplete' ? 'selected' : '' ?>>Incomplete (&lt; <?= $reqTotal ?>/<?= $reqTotal ?>)</option>
        </select>

        <select id="sort" name="sort" class="form-select form-select-sm bms-toolbar-select" aria-label="Sort by created date">
            <option value="newest" <?= ($filters['sort'] ?? 'newest') === 'newest' ? 'selected' : '' ?>>Newest First</option>
            <option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
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
            <a href="<?= url('/marketing/projects') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline" title="Clear Filters" aria-label="Clear Filters">
                <?= bms_icon('reset', 16) ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($canRegisterProject)): ?>
            <a href="<?= url('/marketing/projects/create') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--primary" title="Register Marketing Project" aria-label="Register Marketing Project">
                <?= bms_icon('plus', 16) ?>
            </a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Project Code</th>
                    <th>Project &amp; Client</th>
                    <th>Current Dept</th>
                    <th>Document Checklist</th>
                    <th>Workflow Status</th>
                    <th>Created <?= ($filters['sort'] ?? 'newest') === 'oldest' ? '&uarr;' : '&darr;' ?></th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">No Marketing projects match your current search or filter criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                        <?php
                        $projId = (int) $proj['id'];
                        $uploadedReq = (int) ($proj['uploaded_required_count'] ?? 0);
                        $requiredCnt = (int) ($proj['required_total_count'] ?? $reqTotal);
                        $isComplete = !empty($proj['is_documents_complete']);
                        $status = (string) ($proj['status'] ?? 'Draft');
                        $isEditable = Project::isMarketingEditable($proj);
                        $canStartPrep = $status === 'Draft'
                            || ($status === 'Submitted' && (($proj['verification_status'] ?? 'not_submitted') === 'not_submitted'));
                        ?>
                        <tr class="<?= $status === 'Returned for Corrections' ? 'checklist-row-attention' : '' ?>">
                            <td class="font-monospace small fw-semibold">
                                <?= e($proj['project_code']) ?>
                                <?php if (!empty($proj['jom_number'])): ?>
                                    <div><span class="badge bg-primary font-monospace" style="font-size: 0.72rem;"><?= e($proj['jom_number']) ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($proj['project_name']) ?></div>
                                <div class="small text-secondary">
                                    Client: <strong><?= e($proj['client_name']) ?></strong>
                                    <?php if (!empty($proj['project_location'])): ?>
                                        &bull; <?= e($proj['project_location']) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($status === 'Returned for Corrections' && !empty($proj['return_reason'])): ?>
                                    <div class="mt-1 small text-danger">
                                        <strong>PM Return Note:</strong> <?= e($proj['return_reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($proj['current_department_code'])): ?>
                                    <span class="badge badge-soft-secondary"><?= e($proj['current_department_code']) ?></span>
                                    <div class="small text-secondary mt-1"><?= e($proj['current_department_name']) ?></div>
                                <?php else: ?>
                                    <span class="text-secondary small">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isComplete): ?>
                                    <span class="badge badge-soft-success"><?= $uploadedReq ?>/<?= $requiredCnt ?> Complete</span>
                                    <div class="text-secondary" style="font-size: 0.74rem;">Ready / Uploaded</div>
                                <?php else: ?>
                                    <span class="badge badge-soft-warning"><?= $uploadedReq ?>/<?= $requiredCnt ?> Uploaded</span>
                                    <div class="text-danger" style="font-size: 0.74rem;"><?= max(0, $requiredCnt - $uploadedReq) ?> required missing</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= project_status_badge_class($status) ?>"><?= e($status) ?></span>
                            </td>
                            <td class="small text-secondary">
                                <div><?= format_datetime($proj['created_at']) ?></div>
                                <div style="font-size: 0.74rem;">By <?= e($proj['creator_name']) ?></div>
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

                                    <?php if ($status === 'Returned for Corrections'): ?>
                                        <a href="<?= url('/projects/' . $projId . '?upload=auto#return-banner') ?>"
                                           class="bms-action-icon bms-action-icon--danger"
                                           title="View Return Notes &amp; Fix"
                                           aria-label="View Return Notes &amp; Fix">
                                            <?= bms_icon('alert') ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($isEditable && $isComplete): ?>
                                        <?php $submitTitle = $status === 'Returned for Corrections' ? 'Resubmit to Project Management' : 'Submit to Project Management'; ?>
                                        <form method="POST" action="<?= url('/projects/' . $projId . '/submit') ?>" class="d-inline m-0" data-confirm="Submit '<?= e($proj['project_code']) ?>' to Project Management for document verification?">
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
