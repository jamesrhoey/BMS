<?php
$selectedDept = (string) old_raw('current_department_id', '');
$targetAction = $formAction ?? '/projects';
$returnPath = $backUrl ?? (!empty($isMarketingUser) ? '/marketing/projects' : '/projects');
$checklistItems = $checklistDefinitions ?? [];
?>
<div class="row justify-content-center">
    <div class="col-12 col-xl-9">
        <div class="alert alert-light border small text-secondary mb-3">
            <div class="fw-semibold text-dark mb-1">Marketing Workflow &amp; JOM Policy:</div>
            <div>
                Saving this form registers the project as a <strong>Draft</strong> without assigning a JOM number (JOM issuance belongs strictly to Project Management after document verification).
                <?php if ($checklistItems !== []): ?>
                    After saving, upload the <?= count($checklistItems) ?> standard Marketing documents:
                    <?php foreach ($checklistItems as $def): ?>
                        <span class="badge badge-soft-secondary ms-1"><?= e((string) ($def['code'] ?? $def['type'] ?? '')) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="bms-card p-4">
            <form method="POST" action="<?= url($targetAction) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-12">
                        <label for="project_name" class="form-label fw-medium">Project Name <span class="text-danger">*</span></label>
                        <input type="text" id="project_name" name="project_name" value="<?= old('project_name') ?>" maxlength="200" class="form-control <?= has_error('project_name') ? 'is-invalid' : '' ?>" placeholder="e.g., Corporate PABX & Structured Cabling Upgrade" required>
                        <?php if (has_error('project_name')): ?>
                            <div class="invalid-feedback"><?= field_error('project_name') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="client_name" class="form-label fw-medium">Client Name <span class="text-danger">*</span></label>
                        <input type="text" id="client_name" name="client_name" value="<?= old('client_name') ?>" maxlength="150" class="form-control <?= has_error('client_name') ? 'is-invalid' : '' ?>" placeholder="Registered company or client name" required>
                        <?php if (has_error('client_name')): ?>
                            <div class="invalid-feedback"><?= field_error('client_name') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="client_contact" class="form-label fw-medium">Client Contact Person / Details</label>
                        <input type="text" id="client_contact" name="client_contact" value="<?= old('client_contact') ?>" maxlength="150" class="form-control <?= has_error('client_contact') ? 'is-invalid' : '' ?>" placeholder="Contact person, phone, or email">
                        <?php if (has_error('client_contact')): ?>
                            <div class="invalid-feedback"><?= field_error('client_contact') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 <?= !empty($isAdmin) ? 'col-md-6' : '' ?>">
                        <label for="project_location" class="form-label fw-medium">Project Site / Location</label>
                        <input type="text" id="project_location" name="project_location" value="<?= old('project_location') ?>" maxlength="200" class="form-control <?= has_error('project_location') ? 'is-invalid' : '' ?>" placeholder="City, province, or facility address">
                        <?php if (has_error('project_location')): ?>
                            <div class="invalid-feedback"><?= field_error('project_location') ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($isAdmin)): ?>
                        <div class="col-12 col-md-6">
                            <label for="current_department_id" class="form-label fw-medium">Initial Assigned Department</label>
                            <select id="current_department_id" name="current_department_id" class="form-select <?= has_error('current_department_id') ? 'is-invalid' : '' ?>">
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
                                <div class="invalid-feedback"><?= field_error('current_department_id') ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <label for="project_description" class="form-label fw-medium">Project Scope / Description</label>
                        <textarea id="project_description" name="project_description" rows="4" maxlength="5000" class="form-control <?= has_error('project_description') ? 'is-invalid' : '' ?>" placeholder="Summarize the client inquiry, technical scope of work, or registration remarks..."><?= old('project_description') ?></textarea>
                        <?php if (has_error('project_description')): ?>
                            <div class="invalid-feedback"><?= field_error('project_description') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="<?= url($returnPath) ?>" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary fw-semibold">Save Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>
