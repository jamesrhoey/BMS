<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="bms-card p-4 p-md-5">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 pb-4 mb-4 border-bottom">
                <div>
                    <h2 class="h4 fw-bold mb-1"><?= e($profileUser['full_name']) ?></h2>
                    <div class="text-secondary small">@<?= e($profileUser['username']) ?></div>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge badge-soft-primary px-3 py-2"><?= e($profileUser['role_name']) ?></span>
                    <?php if (($profileUser['status'] ?? '') === 'active'): ?>
                        <span class="badge badge-soft-success px-3 py-2">Active Account</span>
                    <?php else: ?>
                        <span class="badge badge-soft-danger px-3 py-2">Inactive</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <div class="text-secondary small text-uppercase fw-semibold mb-1">Username</div>
                    <div class="fw-medium"><?= e($profileUser['username']) ?></div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="text-secondary small text-uppercase fw-semibold mb-1">Email Address</div>
                    <div class="fw-medium"><?= e($profileUser['email'] ?? 'Not provided') ?></div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="text-secondary small text-uppercase fw-semibold mb-1">Assigned Department</div>
                    <div class="fw-medium">
                        <?php if (!empty($department)): ?>
                            <?= e($department['department_name']) ?>
                            <span class="badge badge-soft-secondary ms-1"><?= e($department['department_code']) ?></span>
                        <?php else: ?>
                            System-Wide (No single department assigned)
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="text-secondary small text-uppercase fw-semibold mb-1">System Role</div>
                    <div class="fw-medium"><?= e($profileUser['role_name']) ?></div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="text-secondary small text-uppercase fw-semibold mb-1">Last Sign-In</div>
                    <div class="fw-medium"><?= format_datetime($profileUser['last_login_at'] ?? null) ?></div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="text-secondary small text-uppercase fw-semibold mb-1">Account Created</div>
                    <div class="fw-medium"><?= format_datetime($profileUser['created_at'] ?? null) ?></div>
                </div>
            </div>

            <div class="alert alert-light border mt-4 mb-0 small text-secondary">
                Role and department assignments are managed centrally by system administrators. Contact an Administrator if your department assignment or account details need to be updated.
            </div>
        </div>
    </div>
</div>

