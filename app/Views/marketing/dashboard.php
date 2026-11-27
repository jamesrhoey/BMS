<?php
use App\Models\Project;

$currentPage = (int) ($pagination['page'] ?? 1);
$totalRows = (int) ($pagination['total'] ?? 0);
$selectedCat = (string) ($selectedCategory ?? '');
?>

<!-- Category Filter Pills & Dashboard Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h5 fw-bold mb-0 text-dark">Marketing Department Workspace</h1>
        <div class="text-secondary small">Official project registration, category management (MRC &amp; Special Project), and document compliance</div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Category Filters">
            <a href="<?= url('/marketing/dashboard') ?>"
               class="btn <?= $selectedCat === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                All Categories
            </a>
            <a href="<?= url('/marketing/dashboard?category=mrc') ?>"
               class="btn <?= $selectedCat === 'mrc' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                MRC
            </a>
            <a href="<?= url('/marketing/dashboard?category=special_project') ?>"
               class="btn <?= $selectedCat === 'special_project' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                Special Project
            </a>
        </div>
        <?php if (!empty($canRegisterProject)): ?>
            <a href="<?= url('/marketing/projects/create') ?>" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
                <?= bms_icon('plus', 15) ?>
                <span>Register Project</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- 8 Live Database-Driven Marketing Statistics -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Total Projects</div>
            <div class="bms-stat-value text-dark"><?= (int) ($stats['total_marketing_projects'] ?? 0) ?></div>
            <div class="text-secondary small" style="font-size: 0.74rem;">Created Marketing records</div>
        </div>
    </div>

    <div class="col-6 col-md-3 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">MRC Projects</div>
            <div class="bms-stat-value text-info"><?= (int) ($stats['mrc_projects'] ?? 0) ?></div>
            <div class="text-secondary small" style="font-size: 0.74rem;">6 docs &bull; No PO required</div>
        </div>
    </div>

    <div class="col-6 col-md-3 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Special Projects</div>
            <div class="bms-stat-value" style="color: #6f42c1;"><?= (int) ($stats['special_projects'] ?? 0) ?></div>
            <div class="text-secondary small" style="font-size: 0.74rem;">7 docs &bull; Client PO required</div>
        </div>
    </div>

    <div class="col-6 col-md-3 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Draft Projects</div>
            <div class="bms-stat-value text-secondary"><?= (int) ($stats['draft_projects'] ?? 0) ?></div>
            <div class="text-secondary small" style="font-size: 0.74rem;">Awaiting document preparation</div>
        </div>
    </div>

    <div class="col-6 col-md-3 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Returned for Correction</div>
            <div class="bms-stat-value text-danger"><?= (int) ($stats['returned_for_corrections'] ?? 0) ?></div>
            <div class="text-secondary small" style="font-size: 0.74rem;">Requires document replacement</div>
        </div>
    </div>

    <div class="col-6 col-md-3 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Submitted Projects</div>
            <div class="bms-stat-value text-success"><?= (int) ($stats['submitted_projects'] ?? $stats['submitted_to_pm'] ?? 0) ?></div>
            <div class="text-secondary small" style="font-size: 0.74rem;">To PM &amp; Admin Finance</div>
        </div>
    </div>

    <div class="col-6 col-md-3 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Under Review</div>
            <div class="bms-stat-value text-warning"><?= (int) ($stats['under_review_projects'] ?? 0) ?></div>
            <div class="text-secondary small" style="font-size: 0.74rem;">PM verification in progress</div>
        </div>
    </div>

    <div class="col-6 col-md-3 col-xl-3">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Missing Documents</div>
            <div class="bms-stat-value text-warning"><?= (int) ($stats['missing_documents'] ?? 0) ?></div>
            <div class="text-secondary small" style="font-size: 0.74rem;">Incomplete checklist files</div>
        </div>
    </div>
</div>

<!-- Paginated Marketing Projects Table -->
<div class="bms-table-panel">
    <div class="bms-table-toolbar bms-table-toolbar--split">
        <div class="d-flex align-items-center gap-2">
            <h3 class="h6 fw-bold mb-0">Recent Marketing Projects</h3>
            <?php if ($selectedCat !== ''): ?>
                <span class="badge bg-primary"><?= $selectedCat === 'special_project' ? 'Special Project Filter' : 'MRC Filter' ?></span>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="<?= url('/marketing/projects' . ($selectedCat !== '' ? '?category=' . urlencode($selectedCat) : '')) ?>" class="btn btn-light btn-sm border text-secondary">
                Search &amp; Filter All &rarr;
            </a>
            <?php if (!empty($canRegisterProject)): ?>
                <a href="<?= url('/marketing/projects/create') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--primary" title="Register Project" aria-label="Register Marketing Project">
                    <?= bms_icon('plus', 16) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width: 14%;">Project Code</th>
                    <th style="width: 12%;">Category</th>
                    <th style="width: 26%;">Project &amp; Client</th>
                    <th style="width: 14%;">Date Created &darr;</th>
                    <th style="width: 16%;">Document Completion</th>
                    <th style="width: 18%;">Workflow Status</th>
                    <th class="text-end" style="width: 8%;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">
                            No Marketing projects found matching your selection.
                            <?php if (!empty($canRegisterProject)): ?>
                                <a href="<?= url('/marketing/projects/create') ?>" class="ms-1">Register your first draft project &rarr;</a>
                            <?php endif; ?>
                        </td>
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
                            </td>
                            <td>
                                <?php if ($isSpecial): ?>
                                    Special Project
                                <?php else: ?>
                                    MRC
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold text-truncate" title="<?= e($proj['project_name']) ?>"><?= e($proj['project_name']) ?></div>
                                <div class="text-secondary small text-truncate">Client: <strong><?= e($proj['client_name']) ?></strong></div>
                            </td>
                            <td class="small text-secondary">
                                <?= format_datetime($proj['created_at']) ?>
                            </td>
                            <td>
                                <?php if ($isComplete): ?>
                                    <span class="badge badge-soft-success"><?= $uploadedReq ?>/<?= $requiredCnt ?> Uploaded (Complete)</span>
                                <?php else: ?>
                                    <span class="badge badge-soft-warning"><?= $uploadedReq ?>/<?= $requiredCnt ?> Uploaded</span>
                                    <span class="text-danger small ms-1">(<?= max(0, $requiredCnt - $uploadedReq) ?> missing)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= project_status_badge_class($status) ?>"><?= e($status) ?></span>
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

                                    <?php if (in_array($status, ['Returned for Corrections', 'Returned to Marketing for Corrections', 'Returned to Marketing'], true)): ?>
                                        <a href="<?= url('/projects/' . $projId . '?upload=auto#return-banner') ?>"
                                           class="bms-action-icon bms-action-icon--danger"
                                           title="Fix Returned Documents"
                                           aria-label="Fix Returned Documents">
                                            <?= bms_icon('alert') ?>
                                        </a>
                                    <?php elseif ($canStartPrep && !$isComplete): ?>
                                        <form method="POST" action="<?= url('/projects/' . $projId . '/prepare-documents') ?>" class="d-inline m-0">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="bms-action-icon bms-action-icon--warning"
                                                    title="Prepare Documents"
                                                    aria-label="Prepare Documents">
                                                <?= bms_icon('prepare') ?>
                                            </button>
                                        </form>
                                    <?php elseif ($isEditable && !$isComplete): ?>
                                        <a href="<?= url('/projects/' . $projId . '?upload=auto#checklist') ?>"
                                           class="bms-action-icon bms-action-icon--warning"
                                           title="Upload Checklist Documents"
                                           aria-label="Upload Checklist Documents">
                                            <?= bms_icon('upload') ?>
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

    <?= render_table_footer(
        $currentPage,
        (int) ($pagination['per_page'] ?? 10),
        count($projects),
        $totalRows,
        static fn(int $p): string => url('/marketing/dashboard' . ($p > 1 ? '?page=' . $p : '') . ($selectedCat !== '' ? ($p > 1 ? '&' : '?') . 'category=' . urlencode($selectedCat) : ''))
    ) ?>
</div>
