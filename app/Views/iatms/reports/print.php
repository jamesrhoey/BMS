<?php
$meta = $report['meta'];
$rows = $report['rows'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; background: #fff; }
        .print-table th { background: #1A202C !important; color: #fff !important; font-size: 11px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .print-table td { font-size: 11px; }
        .signoff-box { border-top: 1px solid #333; padding-top: 6px; margin-top: 38px; text-align: center; font-size: 11px; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="p-3">
    <div class="no-print d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
        <span class="small text-secondary">A4 / Letter Landscape Print Preview</span>
        <div class="d-flex gap-2">
            <a href="<?= url('/reports/' . $meta['slug']) ?>" class="btn btn-outline-secondary btn-sm">&larr; Back to Report</a>
            <button type="button" id="iatmsPrintBtn" class="btn btn-primary btn-sm">Print Document</button>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
        <div>
            <h4 class="mb-0 fw-bold"><?= e($companyName) ?></h4>
            <div class="small text-secondary">Inventory &amp; Asset Tracking Management System (BMS Integrated)</div>
        </div>
        <div class="text-end">
            <h5 class="mb-0 fw-semibold"><?= e($meta['title']) ?></h5>
            <div class="small text-secondary">Printed: <?= date('Y-m-d H:i') ?> &bull; Records: <?= count($rows) ?></div>
        </div>
    </div>

    <table class="table table-bordered table-sm print-table mb-4">
        <thead>
            <tr>
                <?php foreach ($meta['columns'] as $col): ?>
                    <th><?= e($col) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="<?= count($meta['columns']) ?>" class="text-center py-3">No records found.</td></tr>
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

    <div class="row mt-4">
        <div class="col-4">
            <div class="signoff-box">
                <strong><?= e($preparedBy) ?></strong><br>
                Prepared By (Signature &amp; Date)
            </div>
        </div>
        <div class="col-4">
            <div class="signoff-box">
                <strong>Logistics / Warehouse Supervisor</strong><br>
                Verified By (Signature &amp; Date)
            </div>
        </div>
        <div class="col-4">
            <div class="signoff-box">
                <strong>Operations / Finance Manager</strong><br>
                Approved By (Signature &amp; Date)
            </div>
        </div>
    </div>

    <script src="<?= asset('js/iatms.js') ?>"></script>
</body>
</html>
