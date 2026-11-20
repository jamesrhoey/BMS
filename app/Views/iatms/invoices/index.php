<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">Invoices (Receivable &amp; Payable)</h2>
        <div class="text-secondary small">Customer sales invoices linked to Delivery Receipts and supplier vendor bills linked to POs.</div>
    </div>
    <?php if (!empty($canManage)): ?>
        <a href="<?= url('/invoices/create') ?>" class="btn btn-primary btn-sm">+ Create Invoice</a>
    <?php endif; ?>
</div>

<form method="GET" action="<?= url('/invoices') ?>" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-1">Search Invoice No. / Client / Supplier / DR</label>
                <input type="text" name="search" class="form-control form-control-sm" value="<?= e($filters['search'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Type</label>
                <select name="invoice_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="receivable" <?= ($filters['invoice_type'] ?? '') === 'receivable' ? 'selected' : '' ?>>Receivable (Customer)</option>
                    <option value="payable" <?= ($filters['invoice_type'] ?? '') === 'payable' ? 'selected' : '' ?>>Payable (Supplier)</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled'] as $st): ?>
                        <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-outline-primary btn-sm w-100">Filter</button>
                <a href="<?= url('/invoices') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Invoice No.</th>
                    <th>Type</th>
                    <th>Party</th>
                    <th>Invoice / Due Date</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Balance Due</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr><td colspan="8" class="text-center text-secondary py-4">No invoices found.</td></tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <?php
                        $ib = match ($inv['status']) {
                            'paid' => 'bg-success',
                            'partially_paid' => 'bg-info text-dark',
                            'overdue' => 'bg-danger',
                            'cancelled' => 'bg-secondary',
                            default => 'bg-warning text-dark',
                        };
                        ?>
                        <tr>
                            <td class="fw-semibold font-monospace"><?= e($inv['invoice_number']) ?></td>
                            <td><?= ucfirst($inv['invoice_type']) ?></td>
                            <td><?= e($inv['client_name'] ?: ($inv['supplier_name'] ?: '—')) ?></td>
                            <td><?= e($inv['invoice_date']) ?> / <span class="text-secondary"><?= e($inv['due_date']) ?></span></td>
                            <td class="text-end"><?= e($inv['currency']) ?> <?= number_format((float) $inv['total_amount'], 2) ?></td>
                            <td class="text-end fw-semibold"><?= e($inv['currency']) ?> <?= number_format((float) $inv['balance_due'], 2) ?></td>
                            <td class="text-center"><span class="badge <?= $ib ?>"><?= ucfirst(str_replace('_', ' ', $inv['status'])) ?></span></td>
                            <td class="text-end"><a href="<?= url('/invoices/' . (int) $inv['id']) ?>" class="btn btn-outline-primary btn-sm py-0">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
