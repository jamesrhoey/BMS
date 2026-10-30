<?php
$hasUserFilters = ($filters['q'] ?? '') !== ''
    || ($filters['department_id'] ?? '') !== ''
    || ($filters['role_id'] ?? '') !== ''
    || ($filters['status'] ?? '') !== '';
$totalUsersCount = count($users);
?>

<div class="bms-table-panel">
    <form method="GET" action="<?= url('/users') ?>" class="bms-table-toolbar">
        <select id="department_id" name="department_id" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by department">
            <option value="">All Departments</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?= (int) $dept['id'] ?>" <?= ($filters['department_id'] ?? '') === (string) $dept['id'] ? 'selected' : '' ?>>
                    <?= e($dept['department_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="role_id" name="role_id" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by role">
            <option value="">All Roles</option>
            <?php foreach ($roles as $role): ?>
                <option value="<?= (int) $role['id'] ?>" <?= ($filters['role_id'] ?? '') === (string) $role['id'] ? 'selected' : '' ?>>
                    <?= e($role['role_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="status" name="status" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by status">
            <option value="">All Statuses</option>
            <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>

        <input type="text"
               id="q"
               name="q"
               value="<?= e($filters['q'] ?? '') ?>"
               class="form-control form-control-sm bms-toolbar-input"
               placeholder="Search 'Keyword'">

        <button type="submit" class="bms-toolbar-icon-btn" title="Filter Users" aria-label="Filter Users">
            <?= bms_icon('search', 16) ?>
        </button>

        <?php if ($hasUserFilters): ?>
            <a href="<?= url('/users') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline" title="Clear Filters" aria-label="Clear Filters">
                <?= bms_icon('reset', 16) ?>
            </a>
        <?php endif; ?>

        <a href="<?= url('/users/create') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--primary" title="Create User" aria-label="Create User">
            <?= bms_icon('plus', 16) ?>
        </a>
    </form>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users === []): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">No matching user accounts found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <?php $isSelf = (int) ($currentUser['id'] ?? 0) === (int) $u['id']; ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($u['full_name']) ?></div>
                                <div class="text-secondary small"><?= e($u['email'] ?? 'No email') ?></div>
                            </td>
                            <td class="font-monospace small">
                                <?= e($u['username']) ?>
                                <?php if ($isSelf): ?>
                                    <span class="badge bg-light text-secondary border ms-1">You</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($u['role_slug'] ?? '') === 'administrator'): ?>
                                    <span class="badge badge-soft-primary"><?= e($u['role_name']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-soft-secondary"><?= e($u['role_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($u['department_name'])): ?>
                                    <?= e($u['department_name']) ?>
                                    <span class="text-secondary small">(<?= e($u['department_code']) ?>)</span>
                                <?php else: ?>
                                    <span class="text-secondary small">System-Wide</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge badge-soft-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-soft-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary"><?= format_datetime($u['last_login_at']) ?></td>
                            <td class="text-end">
                                <div class="bms-action-group">
                                    <a href="<?= url('/users/' . (int) $u['id'] . '/edit') ?>"
                                       class="bms-action-icon bms-action-icon--primary"
                                       title="Edit User"
                                       aria-label="Edit User">
                                        <?= bms_icon('edit') ?>
                                    </a>

                                    <?php if (!$isSelf): ?>
                                        <?php if ($u['status'] === 'active'): ?>
                                            <form method="POST" action="<?= url('/users/' . (int) $u['id'] . '/status') ?>" class="d-inline m-0" data-confirm="Deactivate user '<?= e($u['username']) ?>'? They will immediately lose sign-in access.">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="status" value="inactive">
                                                <button type="submit"
                                                        class="bms-action-icon bms-action-icon--danger"
                                                        title="Deactivate User"
                                                        aria-label="Deactivate User">
                                                    <?= bms_icon('deactivate') ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="<?= url('/users/' . (int) $u['id'] . '/status') ?>" class="d-inline m-0" data-confirm="Activate user '<?= e($u['username']) ?>'?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="status" value="active">
                                                <button type="submit"
                                                        class="bms-action-icon bms-action-icon--success"
                                                        title="Activate User"
                                                        aria-label="Activate User">
                                                    <?= bms_icon('activate') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= render_table_footer(1, max(1, $totalUsersCount), $totalUsersCount, $totalUsersCount) ?>
</div>
