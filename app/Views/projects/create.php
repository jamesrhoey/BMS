<?php
$selectedDept = (string) old_raw('current_department_id', '');
$targetAction = $formAction ?? '/projects';
$returnPath = $backUrl ?? (!empty($isMarketingUser) ? '/marketing/projects' : '/projects');
$selectedCategory = (string) old_raw('project_category', $defaultCategory ?? 'mrc');
$mrcDocs = $mrcChecklist ?? \App\Services\MarketingChecklistService::checklistDefinition('mrc');
$specialDocs = $specialChecklist ?? \App\Services\MarketingChecklistService::checklistDefinition('special_project');
?>
<div class="row justify-content-center">
    <div class="col-12 col-md-10 col-lg-8 col-xl-7">
        <div class="bms-card p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
                <div>
                    <h2 class="h6 fw-bold mb-0 text-dark">Register New Project</h2>
                    <div class="text-secondary small" style="font-size: 0.76rem;">Select official project category and enter client specifications to initialize a draft record</div>
                </div>
                <div>
                    <span class="badge bg-light text-secondary border font-monospace">Auto-generated PRJ Code</span>
                </div>
            </div>

            <form method="POST" action="<?= url($targetAction) ?>" id="project-create-form" novalidate>
                <?= csrf_field() ?>

                <div class="row g-3">
                    <!-- Project Category (Controlled Selection) -->
                    <div class="col-12">
                        <label for="project_category" class="form-label small fw-bold mb-1 text-dark">
                            Project Category <span class="text-danger">*</span>
                        </label>
                        <select id="project_category"
                                name="project_category"
                                class="form-select form-select-sm <?= has_error('project_category') ? 'is-invalid' : '' ?>"
                                required
                                onchange="onCategoryChanged(this.value)">
                            <option value="mrc" <?= $selectedCategory === 'mrc' ? 'selected' : '' ?>>
                                MRC — Standard Maintenance, Repair &amp; Construction (6 Documents Required)
                            </option>
                            <option value="special_project" <?= $selectedCategory === 'special_project' ? 'selected' : '' ?>>
                                Special Project — Client Order with Purchase Order (7 Documents Required)
                            </option>
                        </select>
                        <?php if (has_error('project_category')): ?>
                            <div class="invalid-feedback small"><?= field_error('project_category') ?></div>
                        <?php endif; ?>

                        <!-- Dynamic Document Upload Checklist Preview -->
                        <div class="mt-2 p-3 bg-light border rounded-2" id="checklist-preview-box">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-bold text-dark" id="checklist-title">
                                    Required Marketing Document Checklist
                                </span>
                                <span class="badge" id="checklist-badge">6 Documents</span>
                            </div>

                            <!-- MRC Checklist Preview -->
                            <div id="mrc-checklist-preview" class="<?= $selectedCategory === 'special_project' ? 'd-none' : '' ?>">
                                <div class="text-secondary small mb-2">
                                    <i class="text-primary fw-semibold">MRC Workflow:</i> The following 6 documents will be required before submission to Project Management and Admin Finance:
                                </div>
                                <div class="row g-1">
                                    <?php foreach ($mrcDocs as $doc): ?>
                                        <div class="col-12 col-sm-6">
                                            <div class="d-flex align-items-center gap-2 p-1 bg-white border rounded small">
                                                <span class="badge bg-secondary font-monospace"><?= e($doc['code']) ?></span>
                                                <span class="text-truncate text-secondary" title="<?= e($doc['subtitle']) ?>"><?= e($doc['subtitle']) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="small text-muted mt-2 fst-italic">
                                    &bull; Note: MRC projects do <strong>NOT</strong> require a Marketing Purchase Order (PO).
                                </div>
                            </div>

                            <!-- Special Project Checklist Preview -->
                            <div id="special-checklist-preview" class="<?= $selectedCategory === 'special_project' ? '' : 'd-none' ?>">
                                <div class="text-secondary small mb-2">
                                    <i class="text-purple fw-semibold">Special Project Workflow:</i> All 7 documents below are strictly required (including Marketing PO):
                                </div>
                                <div class="row g-1">
                                    <?php foreach ($specialDocs as $doc): ?>
                                        <div class="col-12 col-sm-6">
                                            <div class="d-flex align-items-center gap-2 p-1 bg-white border rounded small <?= $doc['code'] === 'PO' ? 'border-primary bg-primary-subtle' : '' ?>">
                                                <span class="badge <?= $doc['code'] === 'PO' ? 'bg-primary' : 'bg-secondary' ?> font-monospace"><?= e($doc['code']) ?></span>
                                                <span class="text-truncate <?= $doc['code'] === 'PO' ? 'fw-bold text-dark' : 'text-secondary' ?>" title="<?= e($doc['subtitle']) ?>">
                                                    <?= e($doc['subtitle']) ?>
                                                    <?= $doc['code'] === 'PO' ? ' (Mandatory)' : '' ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="small text-primary mt-2 fw-semibold">
                                    &bull; Important: Special Projects will not allow final submission until the client Purchase Order (PO) is uploaded.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Project Name -->
                    <div class="col-12">
                        <label for="project_name" class="form-label small fw-semibold mb-1">Project Name / Title <span class="text-danger">*</span></label>
                        <input type="text"
                               id="project_name"
                               name="project_name"
                               value="<?= old('project_name') ?>"
                               maxlength="200"
                               class="form-control form-control-sm <?= has_error('project_name') ? 'is-invalid' : '' ?>"
                               placeholder="e.g., Substation Expansion & Transformer Installation"
                               required>
                        <?php if (has_error('project_name')): ?>
                            <div class="invalid-feedback small"><?= field_error('project_name') ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Client Name -->
                    <div class="col-12 col-md-6">
                        <label for="client_name" class="form-label small fw-semibold mb-1">Client / Customer Name <span class="text-danger">*</span></label>
                        <input type="text"
                               id="client_name"
                               name="client_name"
                               value="<?= old('client_name') ?>"
                               maxlength="150"
                               class="form-control form-control-sm <?= has_error('client_name') ? 'is-invalid' : '' ?>"
                               placeholder="e.g., Meralco Industrial Power"
                               required>
                        <?php if (has_error('client_name')): ?>
                            <div class="invalid-feedback small"><?= field_error('client_name') ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Client Contact -->
                    <div class="col-12 col-md-6">
                        <label for="client_contact" class="form-label small fw-semibold mb-1">Client Contact Person / Details</label>
                        <input type="text"
                               id="client_contact"
                               name="client_contact"
                               value="<?= old('client_contact') ?>"
                               maxlength="150"
                               class="form-control form-control-sm <?= has_error('client_contact') ? 'is-invalid' : '' ?>"
                               placeholder="e.g., Engr. Juan Dela Cruz / (02) 8123-4567">
                        <?php if (has_error('client_contact')): ?>
                            <div class="invalid-feedback small"><?= field_error('client_contact') ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Project Site Location -->
                    <div class="col-12 <?= !empty($isAdmin) ? 'col-md-6' : '' ?>">
                        <label for="project_location" class="form-label small fw-semibold mb-1">Project Site / Location</label>
                        <input type="text"
                               id="project_location"
                               name="project_location"
                               value="<?= old('project_location') ?>"
                               maxlength="200"
                               class="form-control form-control-sm <?= has_error('project_location') ? 'is-invalid' : '' ?>"
                               placeholder="e.g., Calamba City, Laguna">
                        <?php if (has_error('project_location')): ?>
                            <div class="invalid-feedback small"><?= field_error('project_location') ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Initial Assigned Department (Admin Only) -->
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

                    <!-- Project Scope / Description -->
                    <div class="col-12">
                        <label for="project_description" class="form-label small fw-semibold mb-1">Project Scope / Description</label>
                        <textarea id="project_description"
                                  name="project_description"
                                  rows="3"
                                  maxlength="5000"
                                  class="form-control form-control-sm <?= has_error('project_description') ? 'is-invalid' : '' ?>"
                                  placeholder="Provide project overview, engineering specifications, or scope of supply..."><?= old('project_description') ?></textarea>
                        <?php if (has_error('project_description')): ?>
                            <div class="invalid-feedback small"><?= field_error('project_description') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                    <span class="small text-secondary">
                        After saving, you can upload and manage the required Marketing documents before submission.
                    </span>
                    <div class="d-flex gap-2">
                        <a href="<?= url($returnPath) ?>" class="btn btn-sm btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-sm btn-primary fw-semibold" id="submit-btn">
                            Save Draft Project
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function onCategoryChanged(val) {
    var isSpecial = (val === 'special_project');
    var mrcDiv = document.getElementById('mrc-checklist-preview');
    var specialDiv = document.getElementById('special-checklist-preview');
    var badge = document.getElementById('checklist-badge');
    var title = document.getElementById('checklist-title');

    if (isSpecial) {
        if (mrcDiv) mrcDiv.classList.add('d-none');
        if (specialDiv) specialDiv.classList.remove('d-none');
        if (badge) {
            badge.textContent = '7 Documents Required';
            badge.className = 'badge bg-purple text-white';
        }
        if (title) {
            title.textContent = 'Special Project Required Document Checklist';
        }
    } else {
        if (mrcDiv) mrcDiv.classList.remove('d-none');
        if (specialDiv) specialDiv.classList.add('d-none');
        if (badge) {
            badge.textContent = '6 Documents Required';
            badge.className = 'badge bg-primary text-white';
        }
        if (title) {
            title.textContent = 'MRC Project Required Document Checklist';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('project_category');
    if (sel) {
        onCategoryChanged(sel.value);
    }
    // Prevent duplicate submission on double click
    var form = document.getElementById('project-create-form');
    var btn = document.getElementById('submit-btn');
    if (form && btn) {
        form.addEventListener('submit', function() {
            if (form.checkValidity()) {
                btn.disabled = true;
                btn.textContent = 'Saving Draft...';
            }
        });
    }
});
</script>
