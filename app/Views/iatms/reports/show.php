<?php
$meta = $report['meta'];
$rows = $report['rows'];
$qs = http_build_query(array_filter($filters, static fn($v): bool => $v !== ''));
$qsSuffix = $qs !== '' ? '?' . $qs : '';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold"><?= e($meta['title']) ?></h2>
        <div class="text-secondary small"><?= e($meta['description']) ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= url('/reports') ?>" class="btn btn-outline-secondary btn-sm">&larr; All Reports</a>
        <a href="<?= url('/reports/' . $meta['slug'] . '/export-csv' . $qsSuffix) ?>" class="btn btn-outline-success btn-sm">Export CSV</a>
        <a href="<?= url('/reports/' . $meta['slug'] . '/export-excel' . $qsSuffix) ?>" class="btn btn-outline-success btn-sm">Export Excel</a>
        <a href="<?= url('/reports/' . $meta['slug'] . '/print' . $qsSuffix) ?>" target="_blank" class="btn btn-primary btn-sm">Landscape Print Preview</a>
    </div>
</div>

<form method="GET" action="<?= url('/reports/' . $meta['slug']) ?>" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-1">Keyword / Serial / PO / DR / Company</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword'] ?? '') ?>" placeholder="Filter rows...">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">From Date</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">To Date</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-outline-primary btn-sm w-100">Apply Filters</button>
                <a href="<?= url('/reports/' . $meta['slug']) ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center small">
        <span class="fw-semibold">Total Records: <?= count($rows) ?></span>
        <span class="text-secondary">Generated <?= date('Y-m-d H:i') ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <?php foreach ($meta['columns'] as $col): ?>
                        <th><?= e($col) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="<?= count($meta['columns']) ?>" class="text-center text-secondary py-4">No matching records for the selected filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?= e((string) $cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
