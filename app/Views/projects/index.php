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
                    <th>Internal Code</th>
                    <th>Project Name</th>
                    <th>Client</th>
                    <th>Current Department</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Created At &darr;</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4">No projects match the selected criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                        <tr>
                            <td class="font-monospace small fw-semibold"><?= e($proj['project_code']) ?></td>
                            <td>
                                <div class="fw-semibold"><?= e($proj['project_name']) ?></div>
                                <?php if (!empty($proj['project_location'])): ?>
                                    <div class="text-secondary small"><?= e($proj['project_location']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($proj['client_name']) ?></td>
                            <td>
                                <?php if (!empty($proj['current_department_name'])): ?>
                                    <?= e($proj['current_department_name']) ?>
                                    <span class="text-secondary small">(<?= e($proj['current_department_code']) ?>)</span>
                                <?php else: ?>
                                    <span class="text-secondary small">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= project_status_badge_class((string) $proj['status']) ?>"><?= e($proj['status']) ?></span>
                            </td>
                            <td class="small"><?= e($proj['creator_name']) ?></td>
                            <td class="small text-secondary"><?= format_datetime($proj['created_at']) ?></td>
                            <td class="text-end">
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
