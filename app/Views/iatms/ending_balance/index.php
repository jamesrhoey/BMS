<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">Ending Balance &amp; Reconciliation (Paper Form 3)</h2>
        <div class="text-secondary small">Live physical available <code>in_stock</code> balance (excluding delivered, under_repair, and scrapped units) with persistent Form 3 remarks.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/reports/ending-balance') ?>" class="btn btn-outline-secondary btn-sm">Form 3 Print / Export</a>
    </div>
</div>

<form method="GET" action="<?= url('/inventory/ending-balance') ?>" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-1">Search Description / Model / Remarks</label>
                <input type="text" name="search" class="form-control form-control-sm" value="<?= e($filters['search'] ?? '') ?>" placeholder="Search equipment or remarks...">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Warehouse Location</label>
                <select name="location_id" class="form-select form-select-sm">
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= (int) $loc['id'] ?>" <?= (string) ($filters['location_id'] ?? '1') === (string) $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Stock Status</label>
                <select name="stock_status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="in_stock" <?= ($filters['stock_status'] ?? '') === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                    <option value="low_stock" <?= ($filters['stock_status'] ?? '') === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                    <option value="out_of_stock" <?= ($filters['stock_status'] ?? '') === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-outline-primary btn-sm w-100">Filter</button>
                <a href="<?= url('/inventory/ending-balance') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th style="width:34%;">Description (Model &amp; Product)</th>
                    <th class="text-center">In</th>
                    <th class="text-center">Out</th>
                    <th class="text-center">Maint.</th>
                    <th class="text-center">Ending Balance</th>
                    <th style="width:36%;">Remarks (Persistent Paper Form 3 Notes)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="text-center text-secondary py-4">No inventory items found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($r['item_name']) ?></div>
                                <div class="text-secondary small">
                                    <span class="font-monospace"><?= e($r['model_code']) ?></span>
                                    <?php if (!empty($r['description'])): ?>
                                        &mdash; <?= e($r['description']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center text-success"><?= (int) $r['total_in'] ?></td>
                            <td class="text-center text-danger"><?= (int) $r['total_out'] ?></td>
                            <td class="text-center text-warning"><?= (int) $r['under_repair_count'] ?></td>
                            <td class="text-center">
                                <span class="badge fs-6 <?= $r['ending_balance'] === 0 ? 'bg-danger' : ($r['is_low_stock'] ? 'bg-warning text-dark' : 'bg-success') ?>">
                                    <?= (int) $r['ending_balance'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($canManage)): ?>
                                    <form method="POST" action="<?= url('/inventory/ending-balance/remarks') ?>" class="d-flex gap-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="inventory_item_id" value="<?= (int) $r['inventory_item_id'] ?>">
                                        <input type="hidden" name="location_id" value="<?= (int) $r['location_id'] ?>">
                                        <input type="text" name="remarks" class="form-control form-control-sm" value="<?= e($r['remarks'] ?? '') ?>" placeholder="e.g. FOR MAINTENANCE REPLACEMENT">
                                        <button type="submit" class="btn btn-outline-primary btn-sm">Save</button>
                                    </form>
                                <?php else: ?>
                                    <span><?= e($r['remarks'] ?: '—') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
