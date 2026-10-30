<?php
$selectedRole = (string) old_raw('role_id', '');
$selectedDept = (string) old_raw('department_id', $preselectedDept ?? '');
$selectedStatus = (string) old_raw('status', 'active');
?>
<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h4 fw-bold mb-0">Create User Account</h2>
                <p class="text-secondary small mb-0">Provision an account and assign its authorized role and department.</p>
            </div>
            <a href="<?= url('/users') ?>"
               class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline"
               title="Back to Users"
               aria-label="Back to Users">
                <?= bms_icon('back', 16) ?>
            </a>
        </div>

        <div class="bms-card p-4">
            <form method="POST" action="<?= url('/users') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="full_name" class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
                        <input type="text" id="full_name" name="full_name" value="<?= old('full_name') ?>" maxlength="150" class="form-control <?= has_error('full_name') ? 'is-invalid' : '' ?>" required>
                        <?php if (has_error('full_name')): ?>
                            <div class="invalid-feedback"><?= field_error('full_name') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="username" class="form-label fw-medium">Username <span class="text-danger">*</span></label>
                        <input type="text" id="username" name="username" value="<?= old('username') ?>" maxlength="50" class="form-control <?= has_error('username') ? 'is-invalid' : '' ?>" autocomplete="off" required>
                        <div class="form-text">Letters, numbers, dots, underscores, or hyphens (3–50 chars).</div>
                        <?php if (has_error('username')): ?>
                            <div class="invalid-feedback"><?= field_error('username') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="email" class="form-label fw-medium">Email Address <span class="text-muted fw-normal small">(Optional)</span></label>
                        <input type="email" id="email" name="email" value="<?= old('email') ?>" maxlength="190" class="form-control <?= has_error('email') ? 'is-invalid' : '' ?>">
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
                        <?php if (has_error('status')): ?>
                            <div class="invalid-feedback"><?= field_error('status') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="role_id" class="form-label fw-medium">Authorized Role <span class="text-danger">*</span></label>
                        <select id="role_id" name="role_id" class="form-select <?= has_error('role_id') ? 'is-invalid' : '' ?>" required>
                            <option value="">Select Role...</option>
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

                    <div class="col-12 col-md-6">
                        <label for="password" class="form-label fw-medium">Password <span class="text-danger">*</span></label>
                        <input type="password" id="password" name="password" minlength="8" maxlength="255" class="form-control <?= has_error('password') ? 'is-invalid' : '' ?>" autocomplete="new-password" required>
                        <div class="form-text">Minimum 8 characters. Stored securely using password hashing.</div>
                        <?php if (has_error('password')): ?>
                            <div class="invalid-feedback"><?= field_error('password') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="password_confirmation" class="form-label fw-medium">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" maxlength="255" class="form-control" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="<?= url('/users') ?>" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary fw-semibold">Create User Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

