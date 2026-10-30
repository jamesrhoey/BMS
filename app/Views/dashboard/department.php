<?php
$deptName = $department['department_name'] ?? 'Unassigned Department';
$deptCode = $department['department_code'] ?? 'N/A';
$deptDesc = $department['description'] ?? 'Your account is not currently linked to a specific department.';

$plannedCapabilities = match ($deptCode) {
    'MKT' => 'Client document completeness checklist, quotation/proposal tracking, and handoff to Project Management.',
    'PM' => 'JOM number issuance, project execution planning, milestone tracking, and cross-department handoffs.',
    'OPS' => 'Field service work orders, technical execution updates, and operational deliverables.',
    'LOG' => 'Material requisition fulfillment, supplier purchasing coordination, warehouse inventory, and dispatch.',
    'OPFIN' => 'Operational budget control, project expense requests, liquidations, and operational disbursements.',
    'ADMFIN' => 'Progress billing, client invoicing, collections monitoring, and project financial close-out.',
    default => 'Department-specific operational workflows scheduled for upcoming modules.',
};
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge badge-soft-primary"><?= e($deptCode) ?></span>
            <h2 class="h4 fw-bold mb-0"><?= e($deptName) ?></h2>
        </div>
        <p class="text-secondary mb-0 small"><?= e($deptDesc) ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($canRegisterProject)): ?>
            <a href="<?= url('/projects/create') ?>" class="btn btn-primary btn-sm fw-medium">+ Register Draft Project</a>
        <?php endif; ?>
        <a href="<?= url('/projects') ?>" class="btn btn-outline-secondary btn-sm fw-medium">View Authorized Projects</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Accessible Projects</div>
            <div class="bms-stat-value"><?= (int) ($projectCounts['total_projects'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">Assigned to <?= e($deptCode) ?> or created by you</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Draft Registrations</div>
            <div class="bms-stat-value text-warning"><?= (int) ($projectCounts['draft_projects'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">In preparation</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="bms-stat-card">
            <div class="bms-stat-label">Submitted Projects</div>
            <div class="bms-stat-value text-primary"><?= (int) ($projectCounts['submitted_projects'] ?? 0) ?></div>
            <div class="small text-secondary mt-1">Registered in shared database</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- User Account & Department Summary -->
    <div class="col-12 col-lg-5">
        <div class="bms-card p-4 h-100">
            <h3 class="h6 fw-bold text-uppercase text-secondary mb-3" style="letter-spacing: 0.04em;">Your Workspace Profile</h3>
            <dl class="row mb-0 small">
                <dt class="col-sm-4 text-secondary py-1">Full Name</dt>
                <dd class="col-sm-8 fw-semibold py-1"><?= e($currentUser['full_name'] ?? '') ?></dd>

                <dt class="col-sm-4 text-secondary py-1">Username</dt>
                <dd class="col-sm-8 py-1"><?= e($currentUser['username'] ?? '') ?></dd>

                <dt class="col-sm-4 text-secondary py-1">Email</dt>
                <dd class="col-sm-8 py-1"><?= e($currentUser['email'] ?? 'Not specified') ?></dd>

                <dt class="col-sm-4 text-secondary py-1">Role</dt>
                <dd class="col-sm-8 py-1"><span class="badge badge-soft-primary"><?= e($currentUser['role_name'] ?? '') ?></span></dd>

                <dt class="col-sm-4 text-secondary py-1">Department</dt>
                <dd class="col-sm-8 py-1"><?= e($deptName) ?> (<?= e($deptCode) ?>)</dd>

                <dt class="col-sm-4 text-secondary py-1">Last Login</dt>
                <dd class="col-sm-8 py-1"><?= format_datetime($currentUser['last_login_at'] ?? null) ?></dd>
            </dl>
            <div class="mt-3 pt-3 border-top">
                <a href="<?= url('/profile') ?>" class="btn btn-outline-secondary btn-sm">Open Full Profile</a>
            </div>
        </div>
    </div>

    <!-- Future Department Module Placeholder -->
    <div class="col-12 col-lg-7">
        <div class="bms-card p-4 h-100 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <h3 class="h6 fw-bold mb-0"><?= e($deptName) ?> Workflow Module</h3>
                <span class="badge badge-soft-secondary">Pending Future Module</span>
            </div>
            <p class="text-secondary small mb-3">
                This system is running the <strong>System Foundation</strong> release. Shared authentication, role-based access control, department assignment, and foundational project registration are active.
            </p>
            <div class="bg-light rounded p-3 small mb-3">
                <div class="fw-semibold text-dark mb-1">Planned Capabilities for <?= e($deptName) ?>:</div>
                <div class="text-secondary"><?= e($plannedCapabilities) ?></div>
            </div>
            <div class="small text-secondary">
                <strong>Note:</strong> Departmental approvals, JOM assignment, procurement, inventory, and financial workflows are intentionally deferred to upcoming modules in accordance with the SYB flowchart rollout plan.
            </div>
        </div>
    </div>
</div>

<?php if (!empty($isProjectManagementUser)): ?>
    <?php $pmQueueCount = count($pmVerificationQueue ?? []); ?>
    <!-- Incoming Marketing Document Verification Queue (Minimal Shared PM Handoff Integration) -->
    <div class="bms-table-panel mb-4 border-start border-4 border-primary">
        <div class="bms-table-toolbar bms-table-toolbar--split">
            <div>
                <h3 class="h6 fw-bold mb-0">Marketing Document Verification Queue (<?= $pmQueueCount ?>)</h3>
                <div class="text-secondary small">
                    Projects submitted by Marketing awaiting document completeness verification before JOM number issuance.
                </div>
            </div>
            <a href="<?= url('/projects?status=pm_queue') ?>" class="btn btn-light btn-sm border text-secondary">Filter Queue &rarr;</a>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Project Code</th>
                        <th>Project Name</th>
                        <th>Client</th>
                        <th>Submitted By</th>
                        <th>Submitted At &darr;</th>
                        <th>Workflow Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($pmVerificationQueue ?? []) === []): ?>
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-4 small">
                                No Marketing projects are currently awaiting document verification.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pmVerificationQueue as $qProj): ?>
                            <tr>
                                <td class="font-monospace small fw-semibold"><?= e($qProj['project_code']) ?></td>
                                <td class="fw-medium"><?= e($qProj['project_name']) ?></td>
                                <td><?= e($qProj['client_name']) ?></td>
                                <td class="small"><?= e($qProj['submitter_name'] ?? $qProj['creator_name']) ?></td>
                                <td class="small text-secondary"><?= format_datetime($qProj['submitted_at'] ?? $qProj['updated_at']) ?></td>
                                <td>
                                    <span class="badge <?= project_status_badge_class((string) $qProj['status']) ?>"><?= e($qProj['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="bms-action-group">
                                        <a href="<?= url('/projects/' . (int) $qProj['id']) ?>"
                                           class="bms-action-icon bms-action-icon--primary"
                                           title="Review Checklist"
                                           aria-label="Review Checklist">
                                            <?= bms_icon('review') ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= render_table_footer(1, max(1, $pmQueueCount), $pmQueueCount, $pmQueueCount) ?>
    </div>
<?php endif; ?>

<!-- Accessible Projects Table -->
<?php $deptRecentCount = count($recentProjects); ?>
<div class="bms-table-panel">
    <div class="bms-table-toolbar bms-table-toolbar--split">
        <h3 class="h6 fw-bold mb-0">Authorized Department Projects</h3>
        <a href="<?= url('/projects') ?>" class="btn btn-light btn-sm border text-secondary">View All &rarr;</a>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Project Code</th>
                    <th>Project Name</th>
                    <th>Client</th>
                    <th>Status</th>
                    <th>Created &darr;</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentProjects === []): ?>
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-4">No projects are currently assigned to your workspace.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentProjects as $proj): ?>
                        <tr>
                            <td class="fw-semibold font-monospace small"><?= e($proj['project_code']) ?></td>
                            <td class="fw-medium"><?= e($proj['project_name']) ?></td>
                            <td><?= e($proj['client_name']) ?></td>
                            <td>
                                <span class="badge <?= project_status_badge_class((string) $proj['status']) ?>"><?= e($proj['status']) ?></span>
                            </td>
                            <td class="small text-secondary"><?= format_datetime($proj['created_at']) ?></td>
                            <td class="text-end">
                                <div class="bms-action-group">
                                    <a href="<?= url('/projects/' . (int) $proj['id']) ?>"
                                       class="bms-action-icon bms-action-icon--primary"
                                       title="View Details"
                                       aria-label="View Details">
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
    <?= render_table_footer(1, max(1, $deptRecentCount), $deptRecentCount, $deptRecentCount) ?>
</div>

