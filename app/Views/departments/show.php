<?php
$membersCount = count($members);
$deptProjectsCount = count($projects);
?>
<div class="row g-4">
    <div class="col-12 col-xl-6">
        <div class="bms-table-panel h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="bms-table-toolbar bms-table-toolbar--split">
                    <h3 class="h6 fw-bold mb-0">Assigned Users (<?= $membersCount ?>)</h3>
                    <a href="<?= url('/users/create?department_id=' . (int) $department['id']) ?>"
                       class="bms-toolbar-icon-btn bms-toolbar-icon-btn--primary"
                       title="Add User to <?= e($department['department_code']) ?>"
                       aria-label="Add User to <?= e($department['department_code']) ?>">
                        <?= bms_icon('plus', 16) ?>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($members === []): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-4">No users are assigned to this department yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($members as $m): ?>
                                    <tr>
                                        <td class="fw-medium"><?= e($m['full_name']) ?></td>
                                        <td class="font-monospace small"><?= e($m['username']) ?></td>
                                        <td><span class="badge badge-soft-secondary"><?= e($m['role_name']) ?></span></td>
                                        <td>
                                            <?php if ($m['status'] === 'active'): ?>
                                                <span class="badge badge-soft-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-soft-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="bms-action-group">
                                                <a href="<?= url('/users/' . (int) $m['id'] . '/edit') ?>"
                                                   class="bms-action-icon bms-action-icon--primary"
                                                   title="Edit User"
                                                   aria-label="Edit User">
                                                    <?= bms_icon('edit') ?>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?= render_table_footer(1, max(1, $membersCount), $membersCount, $membersCount) ?>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="bms-table-panel h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="bms-table-toolbar bms-table-toolbar--split">
                    <h3 class="h6 fw-bold mb-0">Projects Currently in <?= e($department['department_name']) ?> (<?= $deptProjectsCount ?>)</h3>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Project</th>
                                <th>Client</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($projects === []): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-4">No projects are currently assigned to this department.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($projects as $p): ?>
                                    <tr>
                                        <td class="font-monospace small fw-semibold"><?= e($p['project_code']) ?></td>
                                        <td class="fw-medium"><?= e($p['project_name']) ?></td>
                                        <td><?= e($p['client_name']) ?></td>
                                        <td>
                                            <span class="badge <?= project_status_badge_class((string) $p['status']) ?>"><?= e($p['status']) ?></span>
                                        </td>
                                        <td class="text-end">
                                            <div class="bms-action-group">
                                                <a href="<?= url('/projects/' . (int) $p['id']) ?>"
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
            </div>
            <?= render_table_footer(1, max(1, $deptProjectsCount), $deptProjectsCount, $deptProjectsCount) ?>
        </div>
    </div>
</div>
