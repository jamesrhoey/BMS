<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">Product Models Catalog</h2>
        <div class="text-secondary small">Unified equipment models, SKUs, serialization rules, and warehouse availability.</div>
    </div>
    <?php if (!empty($canManage)): ?>
        <a href="<?= url('/inventory/products/create') ?>" class="btn btn-primary btn-sm">+ Add Product Model</a>
    <?php endif; ?>
</div>

<form method="GET" action="<?= url('/inventory/products') ?>" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-1">Search SKU / Model / Description</label>
                <input type="text" name="search" class="form-control form-control-sm" value="<?= e($filters['search'] ?? '') ?>" placeholder="Search model code, SKU, or name...">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= (string) ($filters['category_id'] ?? '') === (string) $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Tracking</label>
                <select name="is_serialized" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="1" <?= ($filters['is_serialized'] ?? '') === '1' ? 'selected' : '' ?>>Serialized</option>
                    <option value="0" <?= ($filters['is_serialized'] ?? '') === '0' ? 'selected' : '' ?>>Bulk</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-outline-primary btn-sm w-100">Filter</button>
                <a href="<?= url('/inventory/products') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>SKU / Model</th>
                    <th>Product Name &amp; Description</th>
                    <th>Category</th>
                    <th class="text-center">Tracking</th>
                    <th class="text-center">Warranty</th>
                    <th class="text-end">Unit Cost</th>
                    <th class="text-center">Available</th>
                    <?php if (!empty($canManage)): ?>
                        <th class="text-end">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="8" class="text-center text-secondary py-4">No products found.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold font-monospace"><?= e($p['model_code']) ?></div>
                                <?php if (!empty($p['sku']) && $p['sku'] !== $p['model_code']): ?>
                                    <div class="text-secondary small">SKU: <?= e($p['sku']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-medium"><?= e($p['description'] ?: $p['item_name']) ?></div>
                                <?php if (!empty($p['remarks'])): ?>
                                    <div class="text-secondary small text-truncate" style="max-width:320px;"><?= e($p['remarks']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($p['category_name'] ?: $p['category']) ?></td>
                            <td class="text-center">
                                <?php if ((int) $p['is_serialized'] === 1): ?>
                                    <span class="badge bg-primary-subtle text-primary">Serialized</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">Bulk</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= (int) ($p['default_warranty_months'] ?? 24) ?> mos</td>
                            <td class="text-end"><?= number_format((float) ($p['unit_cost'] ?? 0), 2) ?></td>
                            <td class="text-center">
                                <?php $avail = (int) $p['is_serialized'] === 1 ? (int) $p['in_stock_units'] : (int) $p['quantity_available']; ?>
                                <span class="badge <?= $avail <= (int) $p['reorder_level'] ? 'bg-warning text-dark' : 'bg-success' ?>"><?= $avail ?> <?= e($p['unit_of_measure']) ?></span>
                            </td>
                            <?php if (!empty($canManage)): ?>
                                <td class="text-end">
                                    <a href="<?= url('/inventory/products/' . (int) $p['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm py-0">Edit</a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
