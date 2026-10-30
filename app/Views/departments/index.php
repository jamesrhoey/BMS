<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1">Company Departments</h2>
        <p class="text-secondary mb-0 small">All six integrated SYB operational and financial departments sharing one unified system.</p>
    </div>
    <a href="<?= url('/users/create') ?>" class="btn btn-primary btn-sm fw-medium">+ Assign New User</a>
</div>

<div class="row g-4">
    <?php foreach ($departments as $dept): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="bms-card p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge badge-soft-primary px-2 py-1"><?= e($dept['department_code']) ?></span>
                        <?php if ($dept['status'] === 'active'): ?>
                            <span class="badge badge-soft-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-soft-secondary"><?= e(ucfirst((string) $dept['status'])) ?></span>
                        <?php endif; ?>
                    </div>

                    <h3 class="h5 fw-bold mb-2"><?= e($dept['department_name']) ?></h3>
                    <p class="text-secondary small mb-3"><?= e($dept['description'] ?? '') ?></p>
                </div>

                <div>
                    <div class="row g-2 bg-light rounded p-2 mb-3 text-center small">
                        <div class="col-6 border-end">
                            <div class="fw-bold text-dark fs-6"><?= (int) $dept['active_users'] ?> <span class="text-secondary fw-normal small">/ <?= (int) $dept['total_users'] ?></span></div>
                            <div class="text-secondary" style="font-size: 0.75rem;">Active Users</div>
                        </div>
                        <div class="col-6">
                            <div class="fw-bold text-dark fs-6"><?= (int) $dept['assigned_projects'] ?></div>
                            <div class="text-secondary" style="font-size: 0.75rem;">Assigned Projects</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= url('/departments/' . (int) $dept['id']) ?>" class="btn btn-outline-primary btn-sm">View Department</a>
                        <a href="<?= url('/users/create?department_id=' . (int) $dept['id']) ?>" class="btn btn-link btn-sm text-decoration-none">+ Add User</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

