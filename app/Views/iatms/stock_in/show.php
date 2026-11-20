<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">Stock In Receipt: <span class="font-monospace"><?= e($receipt['receipt_number']) ?></span></h2>
        <div class="text-secondary small">Received on <?= e($receipt['delivery_to_pct_date'] ?: $receipt['receipt_date']) ?> by <?= e($receipt['received_by_name'] ?: '—') ?></div>
    </div>
    <a href="<?= url('/inventory/stock-in') ?>" class="btn btn-outline-secondary btn-sm">&larr; Back to Stock In List</a>
</div>

<div class="card mb-3">
    <div class="card-body small">
        <div class="row g-3">
            <div class="col-md-3"><span class="text-secondary d-block">PO Number</span><strong class="font-monospace"><?= e($receipt['display_po_number'] ?: 'Direct Stock In') ?></strong></div>
            <div class="col-md-3"><span class="text-secondary d-block">PO Date</span><strong><?= e($receipt['display_po_date'] ?: '—') ?></strong></div>
            <div class="col-md-3"><span class="text-secondary d-block">Supplier</span><strong><?= e($receipt['display_supplier_name'] ?: '—') ?></strong></div>
            <div class="col-md-3"><span class="text-secondary d-block">Destination Warehouse</span><strong><?= e($receipt['location_name'] ?: 'PCT Warehouse') ?></strong></div>
            <div class="col-md-3"><span class="text-secondary d-block">Waybill / DR</span><strong><?= e($receipt['waybill_number'] ?: '—') ?></strong></div>
            <div class="col-md-3"><span class="text-secondary d-block">Invoice Ref</span><strong><?= e($receipt['invoice_reference'] ?: '—') ?></strong></div>
            <div class="col-md-6"><span class="text-secondary d-block">Remarks</span><strong><?= e($receipt['remarks'] ?: '—') ?></strong></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light py-2 fw-semibold small">Received Equipment Lines &amp; Serial Numbers</div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Model / Product</th>
                    <th class="text-center">In Qty</th>
                    <th class="text-center">Warranty</th>
                    <th class="text-end">Unit Cost</th>
                    <th>Captured Serial Numbers</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($it['item_name']) ?></div>
                            <div class="text-secondary font-monospace small"><?= e($it['model_part_number'] ?: $it['item_code']) ?></div>
                        </td>
                        <td class="text-center fw-semibold"><?= (int) $it['quantity_received'] ?></td>
                        <td class="text-center"><?= (int) ($it['warranty_duration_months'] ?? 24) ?> mos</td>
                        <td class="text-end"><?= number_format((float) $it['unit_cost'], 2) ?></td>
                        <td>
                            <?php if (empty($it['serials'])): ?>
                                <span class="text-secondary">Non-serialized (Bulk)</span>
                            <?php else: ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($it['serials'] as $s): ?>
                                        <a href="<?= url('/inventory/units/' . (int) $s['id']) ?>" class="badge bg-light text-dark border text-decoration-none font-monospace">
                                            <?= e($s['serial_number']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
