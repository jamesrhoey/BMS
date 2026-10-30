<?php
$selectedDept = (string) old_raw('current_department_id', (string) ($project['current_department_id'] ?? ''));
?>
<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h4 fw-bold mb-0">Edit Project: <span class="font-monospace"><?= e($project['project_code']) ?></span></h2>
                <p class="text-secondary small mb-0">Update permitted project registration details.</p>
            </div>
            <a href="<?= url('/projects/' . (int) $project['id']) ?>"
               class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline"
               title="Back to Project"
               aria-label="Back to Project">
                <?= bms_icon('back', 16) ?>
            </a>
        </div>

        <div class="bms-card p-4">
            <form method="POST" action="<?= url('/projects/' . (int) $project['id']) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-medium text-secondary">Internal Project Code</label>
                        <input type="text" class="form-control bg-light font-monospace" value="<?= e($project['project_code']) ?>" readonly disabled>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-medium text-secondary">Current Status</label>
                        <input type="text" class="form-control bg-light" value="<?= e($project['status']) ?>" readonly disabled>
                    </div>

                    <div class="col-12">
                        <label for="project_name" class="form-label fw-medium">Project Name <span class="text-danger">*</span></label>
                        <input type="text" id="project_name" name="project_name" value="<?= old('project_name', $project['project_name']) ?>" maxlength="200" class="form-control <?= has_error('project_name') ? 'is-invalid' : '' ?>" required>
                        <?php if (has_error('project_name')): ?>
                            <div class="invalid-feedback"><?= field_error('project_name') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="client_name" class="form-label fw-medium">Client Name <span class="text-danger">*</span></label>
                        <input type="text" id="client_name" name="client_name" value="<?= old('client_name', $project['client_name']) ?>" maxlength="150" class="form-control <?= has_error('client_name') ? 'is-invalid' : '' ?>" required>
                        <?php if (has_error('client_name')): ?>
                            <div class="invalid-feedback"><?= field_error('client_name') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="client_contact" class="form-label fw-medium">Client Contact Person / Details</label>
                        <input type="text" id="client_contact" name="client_contact" value="<?= old('client_contact', $project['client_contact'] ?? '') ?>" maxlength="150" class="form-control <?= has_error('client_contact') ? 'is-invalid' : '' ?>">
                        <?php if (has_error('client_contact')): ?>
                            <div class="invalid-feedback"><?= field_error('client_contact') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 <?= !empty($isAdmin) ? 'col-md-6' : '' ?>">
                        <label for="project_location" class="form-label fw-medium">Project Site / Location</label>
                        <input type="text" id="project_location" name="project_location" value="<?= old('project_location', $project['project_location'] ?? '') ?>" maxlength="200" class="form-control <?= has_error('project_location') ? 'is-invalid' : '' ?>">
                        <?php if (has_error('project_location')): ?>
                            <div class="invalid-feedback"><?= field_error('project_location') ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($isAdmin)): ?>
                        <div class="col-12 col-md-6">
                            <label for="current_department_id" class="form-label fw-medium">Current Department Assignment</label>
                            <select id="current_department_id" name="current_department_id" class="form-select <?= has_error('current_department_id') ? 'is-invalid' : '' ?>">
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= (int) $dept['id'] ?>" <?= $selectedDept === (string) $dept['id'] ? 'selected' : '' ?>>
                                        <?= e($dept['department_name']) ?> (<?= e($dept['department_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (has_error('current_department_id')): ?>
                                <div class="invalid-feedback"><?= field_error('current_department_id') ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <label for="project_description" class="form-label fw-medium">Project Scope / Description</label>
                        <textarea id="project_description" name="project_description" rows="4" maxlength="5000" class="form-control <?= has_error('project_description') ? 'is-invalid' : '' ?>"><?= old('project_description', $project['project_description'] ?? '') ?></textarea>
                        <?php if (has_error('project_description')): ?>
                            <div class="invalid-feedback"><?= field_error('project_description') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="<?= url('/projects/' . (int) $project['id']) ?>" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary fw-semibold">Update Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

