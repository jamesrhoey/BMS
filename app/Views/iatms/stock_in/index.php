<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">Stock In Receipts (Paper Form 1)</h2>
        <div class="text-secondary small">Dual-mode inbound equipment receiving log with serialized intake and ledger posting.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/reports/stock-in') ?>" class="btn btn-outline-secondary btn-sm">Form 1 Report</a>
        <?php if (!empty($canCreate)): ?>
            <a href="<?= url('/inventory/stock-in/create') ?>" class="btn btn-primary btn-sm">+ Record Stock In (Form 1)</a>
        <?php endif; ?>
    </div>
</div>

<form method="GET" action="<?= url('/inventory/stock-in') ?>" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-1">Search Receipt / PO / Supplier / Waybill</label>
                <input type="text" name="search" class="form-control form-control-sm" value="<?= e($filters['search'] ?? '') ?>" placeholder="SR-2026-..., 000398, supplier...">
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
                <button type="submit" class="btn btn-outline-primary btn-sm w-100">Filter</button>
                <a href="<?= url('/inventory/stock-in') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Receipt No.</th>
                    <th>Mode / PO No.</th>
                    <th>Supplier</th>
                    <th>Delivery to PCT</th>
                    <th>Warehouse</th>
                    <th class="text-center">Total Qty</th>
                    <th>Received By</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($receipts)): ?>
                    <tr><td colspan="8" class="text-center text-secondary py-4">No Stock In receipts recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($receipts as $r): ?>
                        <tr>
                            <td class="fw-semibold font-monospace"><?= e($r['receipt_number']) ?></td>
                            <td>
                                <span class="font-monospace"><?= e($r['display_po_number'] ?: 'Direct') ?></span>
                                <?php if (empty($r['po_id'])): ?>
                                    <span class="badge bg-info-subtle text-info ms-1">Direct</span>
                                <?php else: ?>
                                    <span class="badge bg-primary-subtle text-primary ms-1">System PO</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($r['display_supplier_name'] ?: '—') ?></td>
                            <td><?= e($r['delivery_to_pct_date'] ?: $r['receipt_date']) ?></td>
                            <td><?= e($r['location_name'] ?: 'PCT Warehouse') ?></td>
                            <td class="text-center"><span class="badge bg-success"><?= (int) $r['total_units'] ?></span></td>
                            <td><?= e($r['received_by_name'] ?: '—') ?></td>
                            <td class="text-end">
                                <a href="<?= url('/inventory/stock-in/' . (int) $r['id']) ?>" class="btn btn-outline-primary btn-sm py-0">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
