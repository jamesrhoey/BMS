<?php $special = ($project['project_category'] ?? '') === 'Special Project' || ($project['workflow_type'] ?? '') === 'special_project'; $groups = ['Marketing Documents'=>['SON','BOM','SLD','PO'],'Project Management Documents'=>['JOM'],'OPS Documents'=>['FINAL_SLD','FINAL_BOM','REQUEST_OF_PR'],'OPS Finance Documents'=>['PR','CANVASSING_SHEETS']]; $byType=[]; foreach (($project['documents']??[]) as $d) $byType[$d['document_type']][]=$d; ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h4 fw-bold mb-1"><?= e($project['project_name']) ?></h1><div class="small text-secondary"><?= e($project['project_code']) ?> · <?= $special?'Special Project':'MRC' ?> · <?= e($project['client_name']) ?></div></div><a href="<?= url('/ops-finance/projects') ?>" class="btn btn-outline-secondary btn-sm">Back</a></div>
<div class="row g-3 mb-4"><div class="col-md-3"><div class="bms-card p-3"><div class="small text-secondary">Current status</div><strong><?= e($project['status']) ?></strong></div></div><div class="col-md-3"><div class="bms-card p-3"><div class="small text-secondary">OPS Finance received</div><strong><?= e($project['opfin_received_at'] ?? '—') ?></strong></div></div><div class="col-md-3"><div class="bms-card p-3"><div class="small text-secondary">OPS submission</div><strong><?= e($project['submitted_to_opfin_at'] ?? '—') ?></strong></div></div><div class="col-md-3"><div class="bms-card p-3"><div class="small text-secondary">Scope</div><strong><?= e($project['project_description'] ?? '—') ?></strong></div></div></div>
<?php foreach ($groups as $title=>$types): ?>
    <?php $visibleTypes = array_values(array_filter($types, static fn(string $type): bool => !empty($byType[$type]))); ?>
    <?php if ($visibleTypes === []) continue; ?>
    <div class="bms-card p-3 mb-3">
        <h2 class="h6 fw-bold"><?= e($title) ?> <span class="badge bg-light text-dark border">Read-only</span></h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <tbody>
                    <?php foreach ($visibleTypes as $type): ?>
                        <?php foreach ($byType[$type] as $d): ?>
                            <tr>
                                <td><?= e($type) ?></td>
                                <td><?= e($d['original_filename']) ?> <span class="text-secondary">v<?= (int) $d['version_number'] ?></span></td>
                                <td><?= e($d['uploader_name']) ?></td>
                                <td><?= e($d['created_at']) ?></td>
                                <td><a href="<?= url('/projects/' . (int) $project['id'] . '/documents/' . (int) $d['id'] . '/download') ?>">Download</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>
<?php if (($project['opfin_submitted_to_afin_at'] ?? null) === null): ?>
    <div class="bms-card p-3 mb-3">
        <h2 class="h6 fw-bold">OPS Finance uploads</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <form method="post" enctype="multipart/form-data" action="<?= url('/ops-finance/projects/'.(int)$project['id'].'/documents') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="document_type" value="PR">
                    <label class="form-label">PR</label>
                    <input required type="file" name="document" class="form-control">
                    <button class="btn btn-primary btn-sm mt-2">Upload PR</button>
                </form>
            </div>
            <div class="col-md-6">
                <form method="post" enctype="multipart/form-data" action="<?= url('/ops-finance/projects/'.(int)$project['id'].'/documents') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="document_type" value="CANVASSING_SHEETS">
                    <label class="form-label">Canvassing Sheets</label>
                    <input required multiple type="file" name="documents[]" class="form-control">
                    <div class="form-text">Select as many files as needed. Each sheet is saved separately.</div>
                    <button class="btn btn-primary btn-sm mt-2">Upload Sheets</button>
                </form>
            </div>
        </div>
    </div>
    <div class="bms-card p-3 mb-4">
        <h2 class="h6 fw-bold">Submit to Admin Finance</h2>
        <?php if (!empty($project['pr']) && !empty($project['canvassing'])): ?>
            <p class="text-success small">PR and <?= count($project['canvassing']) ?> canvassing sheet(s) are ready.</p>
            <form method="post" action="<?= url('/ops-finance/projects/'.(int)$project['id'].'/submit-admin-finance') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-success" data-confirm="Submit this project and its documents to Admin Finance?">Submit to Admin Finance</button>
            </form>
        <?php else: ?>
            <p class="text-danger small">Upload both PR and at least one Canvassing Sheet before submission.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>
