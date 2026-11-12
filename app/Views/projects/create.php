<?php
$selectedDept = (string) old_raw('current_department_id', '');
$targetAction = $formAction ?? '/projects';
$returnPath = $backUrl ?? (!empty($isMarketingUser) ? '/marketing/projects' : '/projects');
$checklistItems = $checklistDefinitions ?? [];
?>
<div class="row justify-content-center">
    <div class="col-12 col-md-9 col-lg-7 col-xl-6" style="max-width: 640px;">
        <div class="bms-card p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
                <div>
                    <h2 class="h6 fw-bold mb-0 text-dark">Register New Project</h2>
                    <div class="text-secondary small" style="font-size: 0.76rem;">Enter project details to initialize a draft record</div>
                </div>
                
            </div>

            <form method="POST" action="<?= url($targetAction) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="row g-2">
                    <div class="col-12">
                        <label for="project_name" class="form-label small fw-semibold mb-1">Project Name <span class="text-danger">*</span></label>
                        <input type="text" id="project_name" name="project_name" value="<?= old('project_name') ?>" maxlength="200" class="form-control form-control-sm <?= has_error('project_name') ? 'is-invalid' : '' ?>" required>
                        <?php if (has_error('project_name')): ?>
                            <div class="invalid-feedback small"><?= field_error('project_name') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="client_name" class="form-label small fw-semibold mb-1">Client Name <span class="text-danger">*</span></label>
                        <input type="text" id="client_name" name="client_name" value="<?= old('client_name') ?>" maxlength="150" class="form-control form-control-sm <?= has_error('client_name') ? 'is-invalid' : '' ?>" required>
                        <?php if (has_error('client_name')): ?>
                            <div class="invalid-feedback small"><?= field_error('client_name') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="client_contact" class="form-label small fw-semibold mb-1">Client Contact Person / Details</label>
                        <input type="text" id="client_contact" name="client_contact" value="<?= old('client_contact') ?>" maxlength="150" class="form-control form-control-sm <?= has_error('client_contact') ? 'is-invalid' : '' ?>">
                        <?php if (has_error('client_contact')): ?>
                            <div class="invalid-feedback small"><?= field_error('client_contact') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 <?= !empty($isAdmin) ? 'col-md-6' : '' ?>">
                        <label for="project_location" class="form-label small fw-semibold mb-1">Project Site / Location</label>
                        <input type="text" id="project_location" name="project_location" value="<?= old('project_location') ?>" maxlength="200" class="form-control form-control-sm <?= has_error('project_location') ? 'is-invalid' : '' ?>">
                        <?php if (has_error('project_location')): ?>
                            <div class="invalid-feedback small"><?= field_error('project_location') ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($isAdmin)): ?>
                        <div class="col-12 col-md-6">
                            <label for="current_department_id" class="form-label small fw-semibold mb-1">Initial Assigned Department</label>
                            <select id="current_department_id" name="current_department_id" class="form-select form-select-sm <?= has_error('current_department_id') ? 'is-invalid' : '' ?>">
                                <?php foreach ($departments as $dept): ?>
                                    <option
                                        value="<?= (int) $dept['id'] ?>"
                                        <?= ($selectedDept !== '' ? $selectedDept === (string) $dept['id'] : $dept['department_code'] === 'MKT') ? 'selected' : '' ?>
                                    >
                                        <?= e($dept['department_name']) ?> (<?= e($dept['department_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (has_error('current_department_id')): ?>
                                <div class="invalid-feedback small"><?= field_error('current_department_id') ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <label for="project_description" class="form-label small fw-semibold mb-1">Project Scope / Description</label>
                        <textarea id="project_description" name="project_description" rows="3" maxlength="5000" class="form-control form-control-sm <?= has_error('project_description') ? 'is-invalid' : '' ?>"><?= old('project_description') ?></textarea>
                        <?php if (has_error('project_description')): ?>
                            <div class="invalid-feedback small"><?= field_error('project_description') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                    <a href="<?= url($returnPath) ?>" class="btn btn-sm btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-sm btn-primary fw-semibold">Save Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>
