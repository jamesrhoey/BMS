<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">Serialized Hardware Units</h2>
        <div class="text-secondary small">Global unique serial registry tracking real-time lifecycle state, maintenance, and full chronological movement history.</div>
    </div>
</div>

<form method="GET" action="<?= url('/inventory/units') ?>" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-1">Search Serial Number / Model / PO / DR</label>
                <input type="text" name="search" class="form-control form-control-sm font-monospace" value="<?= e($filters['search'] ?? '') ?>" placeholder="Scan or type serial number...">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="in_stock" <?= ($filters['status'] ?? '') === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                    <option value="delivered" <?= ($filters['status'] ?? '') === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="under_repair" <?= ($filters['status'] ?? '') === 'under_repair' ? 'selected' : '' ?>>Under Maintenance / Repair</option>
                    <option value="scrapped" <?= ($filters['status'] ?? '') === 'scrapped' ? 'selected' : '' ?>>Scrapped</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Product</label>
                <select name="item_id" class="form-select form-select-sm">
                    <option value="">All Products</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (string) ($filters['item_id'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['item_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-outline-primary btn-sm w-100">Filter</button>
                <a href="<?= url('/inventory/units') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Serial Number</th>
                    <th>Model / Product</th>
                    <th>PO Ref</th>
                    <th>DR Ref / Client</th>
                    <th class="text-center">Status</th>
                    <th>Warranty Expiry</th>
                    <th class="text-end">Dossier</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($units)): ?>
                    <tr><td colspan="7" class="text-center text-secondary py-4">No serialized units found.</td></tr>
                <?php else: ?>
                    <?php foreach ($units as $u): ?>
                        <?php
                        $uBadge = match ($u['status']) {
                            'in_stock' => 'bg-success',
                            'delivered' => 'bg-primary',
                            'under_repair' => 'bg-warning text-dark',
                            'scrapped' => 'bg-danger',
                            default => 'bg-secondary',
                        };
                        ?>
                        <tr>
                            <td class="fw-semibold font-monospace"><?= e($u['serial_number']) ?></td>
                            <td>
                                <div class="fw-medium"><?= e($u['item_name']) ?></div>
                                <div class="text-secondary font-monospace small"><?= e($u['model_code']) ?></div>
                            </td>
                            <td class="font-monospace"><?= e($u['po_reference'] ?: '—') ?></td>
                            <td>
                                <?php if (!empty($u['dr_reference'])): ?>
                                    <div class="font-monospace"><?= e($u['dr_reference']) ?></div>
                                    <div class="text-secondary small"><?= e($u['client_name'] ?? '') ?></div>
                                <?php else: ?>
                                    <span class="text-secondary">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><span class="badge <?= $uBadge ?>"><?= ucfirst(str_replace('_', ' ', $u['status'])) ?></span></td>
                            <td><?= e($u['warranty_expiry_date'] ?: 'Not Started') ?></td>
                            <td class="text-end">
                                <a href="<?= url('/inventory/units/' . (int) $u['id']) ?>" class="btn btn-outline-primary btn-sm py-0">History</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
