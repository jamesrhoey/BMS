<?php $isEdit = !empty($product); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0 fw-semibold"><?= $isEdit ? 'Edit Product Model' : 'Add Product Model' ?></h2>
    <a href="<?= url('/inventory/products') ?>" class="btn btn-outline-secondary btn-sm">&larr; Back to Catalog</a>
</div>

<form method="POST" action="<?= $isEdit ? url('/inventory/products/' . (int) $product['id']) : url('/inventory/products') ?>" class="card">
    <?= csrf_field() ?>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">SKU / Item Code *</label>
                <input type="text" name="sku" class="form-control form-control-sm" required value="<?= e($product['sku'] ?? $product['item_code'] ?? '') ?>" placeholder="e.g. INV-5KW-HYB">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Model Code</label>
                <input type="text" name="model_part_number" class="form-control form-control-sm" value="<?= e($product['model_part_number'] ?? '') ?>" placeholder="e.g. SUN-5K-SG04LP1">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($product['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label small fw-semibold">Product Name *</label>
                <input type="text" name="item_name" class="form-control form-control-sm" required value="<?= e($product['item_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Unit of Measure</label>
                <input type="text" name="unit_of_measure" class="form-control form-control-sm" value="<?= e($product['unit_of_measure'] ?? 'pcs') ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label small fw-semibold">Description / Technical Specs</label>
                <textarea name="description" class="form-control form-control-sm" rows="2"><?= e($product['description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Tracking Mode</label>
                <select name="is_serialized" class="form-select form-select-sm">
                    <option value="1" <?= (int) ($product['is_serialized'] ?? 1) === 1 ? 'selected' : '' ?>>Serialized (1-to-1 Serial)</option>
                    <option value="0" <?= isset($product['is_serialized']) && (int) $product['is_serialized'] === 0 ? 'selected' : '' ?>>Bulk (Quantity Only)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Default Warranty (Months)</label>
                <input type="number" name="default_warranty_months" min="0" class="form-control form-control-sm" value="<?= (int) ($product['default_warranty_months'] ?? 24) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Reorder Level</label>
                <input type="number" name="reorder_level" min="0" class="form-control form-control-sm" value="<?= (int) ($product['reorder_level'] ?? 5) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Unit Cost (PHP)</label>
                <input type="number" step="0.01" min="0" name="unit_cost" class="form-control form-control-sm" value="<?= number_format((float) ($product['unit_cost'] ?? 0), 2, '.', '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Unit Price (PHP)</label>
                <input type="number" step="0.01" min="0" name="unit_price" class="form-control form-control-sm" value="<?= number_format((float) ($product['unit_price'] ?? 0), 2, '.', '') ?>">
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="<?= url('/inventory/products') ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
        <button type="submit" class="btn btn-primary btn-sm"><?= $isEdit ? 'Save Changes' : 'Create Product Model' ?></button>
    </div>
</form>
