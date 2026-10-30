<?php
$actCurrPage = (int) ($activityPagination['page'] ?? 1);
$actPerPage = (int) ($activityPagination['per_page'] ?? 4);
$actTotal = (int) ($activityPagination['total'] ?? count($recentLogs));

$buildActivityPageUrl = static function (int $targetActPage): string {
    return url('/dashboard' . ($targetActPage > 1 ? '?activity_page=' . $targetActPage : ''));
};
?>
<!-- 6 Live Database Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Total Users</div>
            <div class="bms-stat-value"><?= (int) ($stats['total_users'] ?? 0) ?></div>
            <div class="small text-secondary mt-1"><?= (int) ($stats['inactive_users'] ?? 0) ?> inactive</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Active Users</div>
            <div class="bms-stat-value text-success"><?= (int) ($stats['active_users'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">Authorized accounts</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Departments</div>
            <div class="bms-stat-value text-primary"><?= (int) ($stats['total_departments'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">Integrated units</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Total Projects</div>
            <div class="bms-stat-value"><?= (int) ($stats['total_projects'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">Shared registry</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Draft Projects</div>
            <div class="bms-stat-value text-warning"><?= (int) ($stats['draft_projects'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">Pending submission</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Submitted</div>
            <div class="bms-stat-value text-info"><?= (int) ($stats['submitted_projects'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">Registered projects</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Six Integrated Departments Summary -->
    <div class="col-12 col-xl-7">
        <div class="bms-table-panel h-100">
            <div class="bms-table-toolbar bms-table-toolbar--split">
                <h3 class="h6 fw-bold mb-0">Company Departments (<?= count($departments) ?>)</h3>
                <a href="<?= url('/departments') ?>" class="btn btn-light btn-sm border text-secondary">View All &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Department</th>
                            <th class="text-center">Active Users</th>
                            <th class="text-center">Projects</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td><span class="badge badge-soft-primary"><?= e($dept['department_code']) ?></span></td>
                                <td>
                                    <a href="<?= url('/departments/' . (int) $dept['id']) ?>" class="fw-semibold text-decoration-none text-dark">
                                        <?= e($dept['department_name']) ?>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <?= (int) $dept['active_users'] ?>
                                    <span class="text-secondary small">/ <?= (int) $dept['total_users'] ?></span>
                                </td>
                                <td class="text-center"><?= (int) $dept['assigned_projects'] ?></td>
                                <td>
                                    <?php if ($dept['status'] === 'active'): ?>
                                        <span class="badge badge-soft-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-soft-secondary"><?= e(ucfirst((string) $dept['status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="bms-action-group">
                                        <a href="<?= url('/departments/' . (int) $dept['id']) ?>"
                                           class="bms-action-icon bms-action-icon--primary"
                                           title="View Department"
                                           aria-label="View Department">
                                            <?= bms_icon('eye') ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Activity Audit Stream (4 rows per page + pagination) -->
    <div class="col-12 col-xl-5">
        <div class="bms-table-panel h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="bms-table-toolbar bms-table-toolbar--split">
                    <h3 class="h6 fw-bold mb-0">Recent System Activity</h3>
                    <a href="<?= url('/activity-logs') ?>" class="btn btn-light btn-sm border text-secondary">Full Audit Log &rarr;</a>
                </div>
                <div class="list-group list-group-flush">
                    <?php if ($recentLogs === []): ?>
                        <div class="p-4 text-center text-secondary small">No activity recorded yet.</div>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <div class="list-group-item px-2 py-2 border-bottom-0 mb-1 rounded" style="background-color: #f6f7f9;">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <span class="badge badge-soft-secondary"><?= e($log['action']) ?></span>
                                    <span class="text-secondary" style="font-size: 0.75rem;"><?= format_datetime($log['created_at']) ?></span>
                                </div>
                                <div class="small mt-1 text-dark text-break"><?= e($log['description']) ?></div>
                                <div class="text-secondary mt-1" style="font-size: 0.74rem;">
                                    Actor: <strong><?= e($log['username'] ?? 'System') ?></strong>
                                    <?php if (!empty($log['ip_address'])): ?>
                                        &bull; IP: <?= e($log['ip_address']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?= render_table_footer($actCurrPage, $actPerPage, count($recentLogs), $actTotal, $buildActivityPageUrl) ?>
        </div>
    </div>
</div>

<!-- Recent Registered Projects -->
<div class="bms-table-panel">
    <div class="bms-table-toolbar bms-table-toolbar--split">
        <h3 class="h6 fw-bold mb-0">Recent Projects</h3>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= url('/projects') ?>" class="btn btn-light btn-sm border text-secondary">All Projects &rarr;</a>
            <a href="<?= url('/projects/create') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--primary" title="Register Project" aria-label="Register Project">
                <?= bms_icon('plus', 16) ?>
            </a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Project Code</th>
                    <th>Project Name</th>
                    <th>Client</th>
                    <th>Current Department</th>
                    <th>Status</th>
                    <th>Created &darr;</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentProjects === []): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">No projects have been registered yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentProjects as $proj): ?>
                        <tr>
                            <td class="fw-semibold font-monospace small"><?= e($proj['project_code']) ?></td>
                            <td class="fw-medium"><?= e($proj['project_name']) ?></td>
                            <td><?= e($proj['client_name']) ?></td>
                            <td><?= e($proj['current_department_name'] ?? 'Unassigned') ?></td>
                            <td>
                                <span class="badge <?= project_status_badge_class((string) $proj['status']) ?>"><?= e($proj['status']) ?></span>
                            </td>
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
    <?= render_table_footer(1, max(1, count($recentProjects)), count($recentProjects), count($recentProjects)) ?>
</div>

