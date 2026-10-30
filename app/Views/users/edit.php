<?php
$selectedRole = (string) old_raw('role_id', (string) $targetUser['role_id']);
$selectedDept = (string) old_raw('department_id', (string) ($targetUser['department_id'] ?? ''));
$selectedStatus = (string) old_raw('status', (string) $targetUser['status']);
$isSelf = (int) ($currentUser['id'] ?? 0) === (int) $targetUser['id'];
?>
<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h4 fw-bold mb-0">Edit User: <?= e($targetUser['username']) ?></h2>
                <p class="text-secondary small mb-0">Update profile details, department assignment, role, or password.</p>
            </div>
            <a href="<?= url('/users') ?>"
               class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline"
               title="Back to Users"
               aria-label="Back to Users">
                <?= bms_icon('back', 16) ?>
            </a>
        </div>

        <div class="bms-card p-4">
            <form method="POST" action="<?= url('/users/' . (int) $targetUser['id']) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="full_name" class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
                        <input type="text" id="full_name" name="full_name" value="<?= old('full_name', $targetUser['full_name']) ?>" maxlength="150" class="form-control <?= has_error('full_name') ? 'is-invalid' : '' ?>" required>
                        <?php if (has_error('full_name')): ?>
                            <div class="invalid-feedback"><?= field_error('full_name') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="username" class="form-label fw-medium">Username <span class="text-danger">*</span></label>
                        <input type="text" id="username" name="username" value="<?= old('username', $targetUser['username']) ?>" maxlength="50" class="form-control <?= has_error('username') ? 'is-invalid' : '' ?>" required>
                        <?php if (has_error('username')): ?>
                            <div class="invalid-feedback"><?= field_error('username') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="email" class="form-label fw-medium">Email Address <span class="text-muted fw-normal small">(Optional)</span></label>
                        <input type="email" id="email" name="email" value="<?= old('email', $targetUser['email'] ?? '') ?>" maxlength="190" class="form-control <?= has_error('email') ? 'is-invalid' : '' ?>">
                        <?php if (has_error('email')): ?>
                            <div class="invalid-feedback"><?= field_error('email') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="status" class="form-label fw-medium">Account Status <span class="text-danger">*</span></label>
                        <select id="status" name="status" class="form-select <?= has_error('status') ? 'is-invalid' : '' ?>" required>
                            <option value="active" <?= $selectedStatus === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $selectedStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        <?php if ($isSelf): ?>
                            <div class="form-text">Your own active administrator account cannot be deactivated here.</div>
                        <?php endif; ?>
                        <?php if (has_error('status')): ?>
                            <div class="invalid-feedback"><?= field_error('status') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="role_id" class="form-label fw-medium">Authorized Role <span class="text-danger">*</span></label>
                        <select id="role_id" name="role_id" class="form-select <?= has_error('role_id') ? 'is-invalid' : '' ?>" required>
                            <?php foreach ($roles as $role): ?>
                                <option
                                    value="<?= (int) $role['id'] ?>"
                                    data-role-slug="<?= e($role['role_slug']) ?>"
                                    <?= $selectedRole === (string) $role['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($role['role_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (has_error('role_id')): ?>
                            <div class="invalid-feedback"><?= field_error('role_id') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="department_id" class="form-label fw-medium">
                            Department <span id="departmentRequiredBadge" class="text-danger ms-1">*</span>
                        </label>
                        <select id="department_id" name="department_id" class="form-select <?= has_error('department_id') ? 'is-invalid' : '' ?>">
                            <option value="">No Specific Department (System Admin Only)</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= (int) $dept['id'] ?>" <?= $selectedDept === (string) $dept['id'] ? 'selected' : '' ?>>
                                    <?= e($dept['department_name']) ?> (<?= e($dept['department_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (has_error('department_id')): ?>
                            <div class="invalid-feedback"><?= field_error('department_id') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12"><hr class="my-2"></div>

                    <div class="col-12">
                        <div class="fw-semibold small text-secondary text-uppercase" style="letter-spacing: 0.04em;">Change Password (Optional)</div>
                        <div class="text-secondary small">Leave blank to keep the user's current password unchanged.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="password" class="form-label fw-medium">New Password</label>
                        <input type="password" id="password" name="password" minlength="8" maxlength="255" class="form-control <?= has_error('password') ? 'is-invalid' : '' ?>" autocomplete="new-password">
                        <?php if (has_error('password')): ?>
                            <div class="invalid-feedback"><?= field_error('password') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="password_confirmation" class="form-label fw-medium">Confirm New Password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" maxlength="255" class="form-control" autocomplete="new-password">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <div class="small text-secondary">
                        Created: <?= format_datetime($targetUser['created_at']) ?> &bull; Last Login: <?= format_datetime($targetUser['last_login_at']) ?>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= url('/users') ?>" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary fw-semibold">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

