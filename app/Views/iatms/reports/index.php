<div class="mb-3">
    <h2 class="h5 mb-0 fw-semibold">Reports &amp; Digitized Paper Forms</h2>
    <div class="text-secondary small">Unified reporting suite with live filtering, CWE-1236 protected CSV/Excel export, and A4 Landscape print previews.</div>
</div>

<?php
$grouped = [];
foreach ($definitions as $def) {
    $grouped[$def['category']][] = $def;
}
?>

<?php foreach ($grouped as $cat => $items): ?>
    <h6 class="text-uppercase text-secondary small fw-bold mt-3 mb-2"><?= e($cat) ?></h6>
    <div class="row g-3 mb-2">
        <?php foreach ($items as $def): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h6 class="fw-semibold mb-1"><?= e($def['title']) ?></h6>
                        <p class="text-secondary small flex-grow-1 mb-3"><?= e($def['description']) ?></p>
                        <div class="d-flex gap-2">
                            <a href="<?= url('/reports/' . $def['slug']) ?>" class="btn btn-primary btn-sm">Open Report</a>
                            <a href="<?= url('/reports/' . $def['slug'] . '/print') ?>" target="_blank" class="btn btn-outline-secondary btn-sm">Print</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
