<?php
$hasFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || ($filters['department_id'] ?? '') !== '';
$totalProjectsCount = count($projects);
?>

<div class="bms-table-panel">
    <form method="GET" action="<?= url('/projects') ?>" class="bms-table-toolbar">
        <select id="status" name="status" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by status">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $st): ?>
                <option value="<?= e($st) ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= e($st) ?></option>
            <?php endforeach; ?>
        </select>

        <?php if (!empty($isAdmin)): ?>
            <select id="department_id" name="department_id" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by department">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= (int) $dept['id'] ?>" <?= ($filters['department_id'] ?? '') === (string) $dept['id'] ? 'selected' : '' ?>>
                        <?= e($dept['department_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <input type="text"
               id="q"
               name="q"
               value="<?= e($filters['q'] ?? '') ?>"
               class="form-control form-control-sm bms-toolbar-input"
               placeholder="Search 'Keyword'">

        <button type="submit" class="bms-toolbar-icon-btn" title="Filter Projects" aria-label="Filter Projects">
            <?= bms_icon('search', 16) ?>
        </button>

        <?php if ($hasFilters): ?>
            <a href="<?= url('/projects') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline" title="Clear Filters" aria-label="Clear Filters">
                <?= bms_icon('reset', 16) ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($canRegisterProject)): ?>
            <a href="<?= url('/projects/create') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--primary" title="Register Draft Project" aria-label="Register Draft Project">
                <?= bms_icon('plus', 16) ?>
            </a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th style="width: 12%; min-width: 110px;">Internal Code</th>
                    <th style="width: 25%; min-width: 200px;">Project Name</th>
                    <th style="width: 13%; min-width: 110px;">Client</th>
                    <th style="width: 15%; min-width: 130px;">Current Department</th>
                    <th style="width: 13%; min-width: 110px;">Status</th>
                    <th style="width: 10%; min-width: 90px;">Created By</th>
                    <th style="width: 7%; min-width: 85px;">Created At &darr;</th>
                    <th class="text-end" style="width: 5%; min-width: 60px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4 small">No projects match the selected criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                        <tr>
                            <td class="font-monospace small fw-semibold">
                                <div class="text-truncate"><?= e($proj['project_code']) ?></div>
                            </td>
                            <td>
                                <a href="<?= url('/projects/' . (int) $proj['id']) ?>" class="fw-semibold text-dark text-decoration-none text-truncate d-block" title="<?= e($proj['project_name']) ?>">
                                    <?= e($proj['project_name']) ?>
                                </a>
                                <?php if (!empty($proj['project_location'])): ?>
                                    <div class="text-secondary small text-truncate"><?= e($proj['project_location']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-truncate" title="<?= e($proj['client_name']) ?>"><?= e($proj['client_name']) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($proj['current_department_name'])): ?>
                                    <div class="text-truncate"><?= e($proj['current_department_name']) ?></div>
                                    <div class="text-secondary small text-truncate">(<?= e($proj['current_department_code']) ?>)</div>
                                <?php else: ?>
                                    <span class="text-secondary small">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= project_status_badge_class((string) $proj['status']) ?>"><?= e($proj['status']) ?></span>
                            </td>
                            <td class="small">
                                <div class="text-truncate"><?= e($proj['creator_name']) ?></div>
                            </td>
                            <td class="small text-secondary">
                                <div class="text-nowrap"><?= format_datetime($proj['created_at']) ?></div>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="bms-action-group">
                                    <a href="<?= url('/projects/' . (int) $proj['id']) ?>"
                                       class="bms-action-icon bms-action-icon--primary"
                                       title="Open Project"
                                       aria-label="Open Project">
                                        <?= bms_icon('eye') ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= render_table_footer(1, max(1, $totalProjectsCount), $totalProjectsCount, $totalProjectsCount) ?>
</div>
