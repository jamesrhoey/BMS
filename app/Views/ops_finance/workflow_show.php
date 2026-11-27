<?php
$special = ($project['project_category'] ?? '') === 'Special Project'
    || ($project['workflow_type'] ?? '') === 'special_project';

$documentTypeAliases = [
    'FINAL_SLD' => 'FINAL_SLD',
    'Final SLD' => 'FINAL_SLD',
    'Final SLD — Single Line Diagram' => 'FINAL_SLD',
    'Final Single Line Diagram' => 'FINAL_SLD',
    'FINAL_BOM' => 'FINAL_BOM',
    'Final BOM' => 'FINAL_BOM',
    'Final BOM — Bill of Materials' => 'FINAL_BOM',
    'Final Bill of Materials' => 'FINAL_BOM',
    'REQUEST_OF_PR' => 'REQUEST_OF_PR',
    'Request of PR' => 'REQUEST_OF_PR',
    'Request of PR — Purchase Requisition Request' => 'REQUEST_OF_PR',
    'Request of Purchase Requisition' => 'REQUEST_OF_PR',
];
$documentGroups = [
    'Marketing Documents' => [
        'SON' => 'SON',
        'BOM' => 'BOM',
        'SLD' => 'SLD',
        'PO' => 'PO',
        'Marketing PO' => 'Marketing PO',
        'Purchase Order' => 'Purchase Order',
    ],
    'Project Management Documents' => [
        'JOM' => 'JOM',
        'JOM — Job Order Memorandum' => 'JOM',
        'Job Order Memorandum' => 'JOM',
    ],
    'OPS Documents' => [
        'FINAL_SLD' => 'Final SLD',
        'FINAL_BOM' => 'Final BOM',
        'REQUEST_OF_PR' => 'Request of PR',
    ],
    'OPS Finance Documents' => [
        'PR' => 'PR',
        'CANVASSING_SHEETS' => 'Canvassing Sheets',
    ],
];
$documentsByType = [];
foreach (($project['documents'] ?? []) as $document) {
    $rawType = (string) ($document['document_type'] ?? '');
    $normalizedType = $documentTypeAliases[$rawType] ?? $rawType;
    $documentsByType[$normalizedType][] = $document;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1"><?= e($project['project_name']) ?></h1>
        <div class="small text-secondary">
            <?= e($project['project_code']) ?> &middot;
            <?= $special ? 'Special Project' : 'MRC' ?> &middot;
            <?= e($project['client_name']) ?>
        </div>
    </div>
    <a href="<?= url('/ops-finance/projects') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="bms-card p-3"><div class="small text-secondary">Current status</div><strong><?= e($project['status']) ?></strong></div></div>
    <div class="col-md-3"><div class="bms-card p-3"><div class="small text-secondary">OPS Finance received</div><strong><?= e($project['opfin_received_at'] ?? '—') ?></strong></div></div>
    <div class="col-md-3"><div class="bms-card p-3"><div class="small text-secondary">OPS submission</div><strong><?= e($project['submitted_to_opfin_at'] ?? '—') ?></strong></div></div>
    <div class="col-md-3"><div class="bms-card p-3"><div class="small text-secondary">Scope</div><strong><?= e($project['project_description'] ?? '—') ?></strong></div></div>
</div>

<?php foreach ($documentGroups as $groupTitle => $types): ?>
    <?php
    $visibleTypes = array_filter(
        $types,
        static fn(string $label, string $canonicalType): bool => !empty($documentsByType[$canonicalType]),
        ARRAY_FILTER_USE_BOTH
    );
    if ($visibleTypes === []) {
        continue;
    }
    ?>
    <section class="bms-card p-3 mb-3">
        <h2 class="h6 fw-bold"><?= e($groupTitle) ?> <span class="badge bg-light text-dark border">Read-only</span></h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>File</th>
                        <th>Uploaded by</th>
                        <th>Uploaded at</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visibleTypes as $canonicalType => $label): ?>
                        <?php foreach ($documentsByType[$canonicalType] as $document): ?>
                            <tr>
                                <td><?= e($label) ?></td>
                                <td>
                                    <?= e($document['original_filename']) ?>
                                    <span class="text-secondary">v<?= (int) $document['version_number'] ?></span>
                                </td>
                                <td><?= e($document['uploader_name'] ?? '—') ?></td>
                                <td><?= e($document['created_at'] ?? '—') ?></td>
                                <td>
                                    <?php if (!empty($document['is_current'])): ?>
                                        <span class="badge badge-soft-success">Current</span>
                                    <?php else: ?>
                                        <span class="text-secondary small">Previous version</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-outline-primary btn-sm"
                                       href="<?= url('/projects/' . (int) $project['id'] . '/documents/' . (int) $document['id'] . '/view?return_to=ops-finance') ?>"
                                       target="_blank" rel="noopener noreferrer">View</a>
                                    <a class="btn btn-outline-secondary btn-sm"
                                       href="<?= url('/projects/' . (int) $project['id'] . '/documents/' . (int) $document['id'] . '/download') ?>">Download</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endforeach; ?>

<?php if (($project['opfin_submitted_to_afin_at'] ?? null) === null): ?>
    <section class="bms-card p-3 mb-3">
        <h2 class="h6 fw-bold">OPS Finance uploads</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <form method="post" enctype="multipart/form-data" action="<?= url('/ops-finance/projects/' . (int) $project['id'] . '/documents') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="document_type" value="PR">
                    <label class="form-label">PR</label>
                    <input required type="file" name="document" class="form-control">
                    <button class="btn btn-primary btn-sm mt-2">Upload PR</button>
                </form>
            </div>
            <div class="col-md-6">
                <form method="post" enctype="multipart/form-data" action="<?= url('/ops-finance/projects/' . (int) $project['id'] . '/documents') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="document_type" value="CANVASSING_SHEETS">
                    <label class="form-label">Canvassing Sheets</label>
                    <input required multiple type="file" name="documents[]" class="form-control">
                    <div class="form-text">Select as many files as needed. Each sheet is saved separately.</div>
                    <button class="btn btn-primary btn-sm mt-2">Upload Sheets</button>
                </form>
            </div>
        </div>
    </section>

    <section class="bms-card p-3 mb-4">
        <h2 class="h6 fw-bold">Submit to Admin Finance</h2>
        <?php if (!empty($project['pr']) && !empty($project['canvassing'])): ?>
            <p class="text-success small">PR and <?= count($project['canvassing']) ?> canvassing sheet(s) are ready.</p>
            <form method="post" action="<?= url('/ops-finance/projects/' . (int) $project['id'] . '/submit-admin-finance') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-success" data-confirm="Submit this project and its documents to Admin Finance?">Submit to Admin Finance</button>
            </form>
        <?php else: ?>
            <p class="text-danger small">Upload both PR and at least one Canvassing Sheet before submission.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>
