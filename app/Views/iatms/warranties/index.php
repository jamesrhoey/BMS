<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">Warranty Certificates &amp; Expirations</h2>
        <div class="text-secondary small">24-month hardware warranty tracking activated upon confirmed customer delivery.</div>
    </div>
</div>

<form method="GET" action="<?= url('/warranties') ?>" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small mb-1">Search Warranty Code / Serial / Client / DR</label>
                <input type="text" name="search" class="form-control form-control-sm" value="<?= e($filters['search'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="expiring_soon" <?= ($filters['status'] ?? '') === 'expiring_soon' ? 'selected' : '' ?>>Expiring Soon</option>
                    <option value="expired" <?= ($filters['status'] ?? '') === 'expired' ? 'selected' : '' ?>>Expired</option>
                    <option value="voided" <?= ($filters['status'] ?? '') === 'voided' ? 'selected' : '' ?>>Voided</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-outline-primary btn-sm w-100">Filter</button>
                <a href="<?= url('/warranties') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Warranty Code</th>
                    <th>Serial Number</th>
                    <th>Product</th>
                    <th>Client</th>
                    <th>Coverage Period</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($warranties)): ?>
                    <tr><td colspan="7" class="text-center text-secondary py-4">No warranty certificates yet. Warranties activate automatically upon confirming a customer delivery.</td></tr>
                <?php else: ?>
                    <?php foreach ($warranties as $w): ?>
                        <?php
                        $wb = match ($w['derived_status']) {
                            'active' => 'bg-success',
                            'expiring_soon' => 'bg-warning text-dark',
                            'expired' => 'bg-danger',
                            default => 'bg-secondary',
                        };
                        ?>
                        <tr>
                            <td class="fw-semibold font-monospace"><?= e($w['warranty_code']) ?></td>
                            <td class="font-monospace"><a href="<?= url('/inventory/units/' . (int) $w['unit_id']) ?>"><?= e($w['serial_number']) ?></a></td>
                            <td><?= e($w['item_name']) ?></td>
                            <td><?= e($w['client_name'] ?: '—') ?></td>
                            <td><?= e($w['start_date'] ?: '—') ?> &rarr; <?= e($w['end_date'] ?: '—') ?> (<?= (int) $w['duration_months'] ?>m)</td>
                            <td class="text-center"><span class="badge <?= $wb ?>"><?= ucfirst(str_replace('_', ' ', $w['derived_status'])) ?></span></td>
                            <td class="text-end"><a href="<?= url('/warranties/' . (int) $w['id']) ?>" class="btn btn-outline-primary btn-sm py-0">Manage</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
