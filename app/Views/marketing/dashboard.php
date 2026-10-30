<?php
use App\Models\Project;

$reqTotal = count($requiredDocumentTypes ?? []);
$currentPage = (int) ($pagination['page'] ?? 1);
$totalRows = (int) ($pagination['total'] ?? 0);
?>

<!-- 6 Live Database-Driven Marketing Statistics -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Total Projects</div>
            <div class="bms-stat-value"><?= (int) ($stats['total_marketing_projects'] ?? 0) ?></div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Draft Projects</div>
            <div class="bms-stat-value text-secondary"><?= (int) ($stats['draft_projects'] ?? 0) ?></div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Awaiting Doc Prep</div>
            <div class="bms-stat-value text-warning"><?= (int) ($stats['awaiting_doc_prep'] ?? 0) ?></div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Submitted to PM</div>
            <div class="bms-stat-value text-primary"><?= (int) ($stats['submitted_to_pm'] ?? 0) ?></div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Returned</div>
            <div class="bms-stat-value text-danger"><?= (int) ($stats['returned_for_corrections'] ?? 0) ?></div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Missing Documents</div>
            <div class="bms-stat-value text-warning"><?= (int) ($stats['missing_documents'] ?? 0) ?></div>
        </div>
    </div>
</div>

<!-- Paginated Marketing Projects Table -->
<div class="bms-table-panel">
    <div class="bms-table-toolbar bms-table-toolbar--split">
        <div>
            <h3 class="h6 fw-bold mb-0">Recent Marketing Projects</h3>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="<?= url('/marketing/projects') ?>" class="btn btn-light btn-sm border text-secondary">Search &amp; Filter All &rarr;</a>
            <?php if (!empty($canRegisterProject)): ?>
                <a href="<?= url('/marketing/projects/create') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--primary" title="Register Project" aria-label="Register Marketing Project">
                    <?= bms_icon('plus', 16) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Project Code</th>
                    <th>Project Name</th>
                    <th>Client Name</th>
                    <th>Date Created &darr;</th>
                    <th>Document Completion</th>
                    <th>Workflow Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">
                            No Marketing projects have been registered yet.
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
                        $requiredCnt = (int) ($proj['required_total_count'] ?? $reqTotal);
                        $isComplete = !empty($proj['is_documents_complete']);
                        $status = (string) ($proj['status'] ?? 'Draft');
                        $isEditable = Project::isMarketingEditable($proj);
                        $canStartPrep = $status === 'Draft'
                            || ($status === 'Submitted' && (($proj['verification_status'] ?? 'not_submitted') === 'not_submitted'));
                        ?>
                        <tr class="<?= $status === 'Returned for Corrections' ? 'checklist-row-attention' : '' ?>">
                            <td class="font-monospace small fw-semibold"><?= e($proj['project_code']) ?></td>
                            <td>
                                <div class="fw-semibold"><?= e($proj['project_name']) ?></div>
                                <div class="text-secondary small">By <?= e($proj['creator_name']) ?></div>
                            </td>
                            <td><?= e($proj['client_name']) ?></td>
                            <td class="small text-secondary"><?= format_datetime($proj['created_at']) ?></td>
                            <td>
                                <?php if ($isComplete): ?>
                                    <span class="badge badge-soft-success"><?= $uploadedReq ?>/<?= $requiredCnt ?> Uploaded</span>
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

                                    <?php if ($status === 'Returned for Corrections'): ?>
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

    <?= render_table_footer(
        $currentPage,
        (int) ($pagination['per_page'] ?? 10),
        count($projects),
        $totalRows,
        static fn(int $p): string => url('/marketing/dashboard' . ($p > 1 ? '?page=' . $p : ''))
    ) ?>
</div>
