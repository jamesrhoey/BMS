<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">Stock Out &amp; Customer Deliveries (Paper Form 2)</h2>
        <div class="text-secondary small">Outbound equipment issuances, Delivery Receipts (DR), and customer delivery trajectory.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/reports/stock-out') ?>" class="btn btn-outline-secondary btn-sm">Form 2 Report</a>
        <?php if (!empty($canCreate)): ?>
            <a href="<?= url('/inventory/stock-out/create') ?>" class="btn btn-primary btn-sm">+ Record Stock Out (Form 2)</a>
        <?php endif; ?>
    </div>
</div>

<form method="GET" action="<?= url('/inventory/stock-out') ?>" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small mb-1">Search DR No. / Invoice / Client / PO</label>
                <input type="text" name="search" class="form-control form-control-sm" value="<?= e($filters['search'] ?? '') ?>" placeholder="DR-2026-..., company name, invoice...">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Delivery Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach (['dispatched', 'in_transit', 'delivered', 'delayed', 'returned', 'cancelled'] as $st): ?>
                        <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-outline-primary btn-sm w-100">Filter</button>
                <a href="<?= url('/inventory/stock-out') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>DR No. / Ref</th>
                    <th>Out Date</th>
                    <th>Company / Client</th>
                    <th>PO / PR Ref</th>
                    <th>Invoice No.</th>
                    <th class="text-center">Qty</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deliveries)): ?>
                    <tr><td colspan="8" class="text-center text-secondary py-4">No Stock Out deliveries recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($deliveries as $d): ?>
                        <?php
                        $badge = match ($d['status']) {
                            'delivered' => 'bg-success',
                            'in_transit', 'dispatched' => 'bg-primary',
                            'delayed' => 'bg-warning text-dark',
                            'cancelled' => 'bg-danger',
                            default => 'bg-secondary',
                        };
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold font-monospace"><?= e($d['dr_number'] ?: $d['delivery_number']) ?></div>
                                <div class="text-secondary small font-monospace"><?= e($d['stock_out_reference'] ?? '') ?></div>
                            </td>
                            <td><?= e($d['delivery_date']) ?></td>
                            <td><?= e($d['display_client_name'] ?: '—') ?></td>
                            <td class="font-monospace"><?= e($d['customer_po_number'] ?: ($d['pr_reference'] ?: '—')) ?></td>
                            <td class="font-monospace"><?= e($d['invoice_number'] ?: '—') ?></td>
                            <td class="text-center fw-semibold"><?= (int) $d['total_units'] ?></td>
                            <td class="text-center"><span class="badge <?= $badge ?>"><?= ucfirst(str_replace('_', ' ', $d['status'])) ?></span></td>
                            <td class="text-end">
                                <a href="<?= url('/inventory/stock-out/' . (int) $d['id']) ?>" class="btn btn-outline-primary btn-sm py-0">Manage</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
