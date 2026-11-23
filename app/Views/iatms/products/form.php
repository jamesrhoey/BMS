<?php $isEdit = !empty($product); ?>

<!-- Tab Strip -->
<div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
    <a href="<?= url('/inventory/products') ?>" class="btn btn-sm btn-outline-secondary">
        &larr; Product Catalog
    </a>
    <span class="text-secondary">/</span>
    <span class="fw-semibold small text-primary"><?= $isEdit ? 'Edit Product Model' : 'New Product Model' ?></span>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <h6 class="fw-semibold text-dark m-0 small">
                    <i class="bi bi-box-seam me-1 text-primary"></i>
                    Product Model Specification
                </h6>
            </div>
            <div class="card-body p-4">
                <!-- Informational business notice -->
                <div class="alert alert-light border small text-muted mb-4 py-2 px-3">
                    <i class="bi bi-info-circle me-1 text-primary"></i>
                    <strong>Business Rule:</strong> A product represents a catalog model. Creating a product model establishes its specification but <strong>does not increase inventory stock</strong>. Physical units enter inventory only through a verified stock-in transaction.
                </div>

                <form method="POST" action="<?= $isEdit ? url('/inventory/products/' . (int) $product['id']) : url('/inventory/products') ?>">
                    <?= csrf_field() ?>

                    <div class="text-uppercase fw-bold text-muted mb-3" style="font-size: 11px; letter-spacing: 0.5px;">
                        Model Identification
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-dark fw-medium small" for="model_code">
                                Model / Code <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="model_code" name="model_code"
                                   class="form-control form-control-sm"
                                   value="<?= e($product['model_code'] ?? $product['model_part_number'] ?? $product['sku'] ?? $product['item_code'] ?? '') ?>"
                                   placeholder="e.g. WS-C2960L-24TS-LL" required autofocus>
                            <small class="text-muted d-block mt-1" style="font-size: 11px;">Unique identifier or manufacturer part number.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark fw-medium small" for="category_id">
                                Category <span class="text-muted">(Optional)</span>
                            </label>
                            <select id="category_id" name="category_id" class="form-select form-select-sm">
                                <option value="">-- No Category (Uncategorized) --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int) $cat['id'] ?>" <?= (int) ($product['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted d-block mt-1" style="font-size: 11px;">Optional organizational classification.</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-dark fw-medium small" for="description">
                            Description <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="description" name="description"
                               class="form-control form-control-sm"
                               value="<?= e($product['description'] ?? $product['item_name'] ?? '') ?>"
                               placeholder="e.g. Cisco Catalyst 24-Port Managed Gigabit Ethernet Switch" required>
                    </div>

                    <div class="text-uppercase fw-bold text-muted mb-3 mt-4" style="font-size: 11px; letter-spacing: 0.5px;">
                        Warranty &amp; Additional Information
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-dark fw-medium small" for="default_warranty_months">
                                Default Warranty Duration (Months)
                            </label>
                            <input type="number" id="default_warranty_months" name="default_warranty_months" min="0" max="120"
                                   class="form-control form-control-sm"
                                   value="<?= (int) ($product['default_warranty_months'] ?? 12) ?>">
                            <small class="text-muted d-block mt-1" style="font-size: 11px;">Coverage period applied to serialized units upon receipt.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark fw-medium small d-block">Initial Status</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                       <?= ($product['status'] ?? 'active') !== 'inactive' ? 'checked' : '' ?>>
                                <label class="form-check-label text-dark fw-medium small" for="is_active">
                                    Active Model (Available for procurement &amp; catalog)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-dark fw-medium small" for="remarks">
                            Remarks <span class="text-muted">(Optional)</span>
                        </label>
                        <textarea id="remarks" name="remarks" class="form-control form-control-sm" rows="3"
                                  placeholder="Internal notes, technical references, or handling specifications..."><?= e($product['remarks'] ?? '') ?></textarea>
                    </div>

                    <!-- Collapsible Advanced Inventory Settings -->
                    <div class="my-3">
                        <a class="text-decoration-none small text-secondary" data-bs-toggle="collapse" href="#advancedSettings" role="button" aria-expanded="false" aria-controls="advancedSettings">
                            <i class="bi bi-gear me-1"></i> Optional Advanced System Settings (Tracking Mode, UOM, Cost/Price) &darr;
                        </a>
                        <div class="collapse mt-2" id="advancedSettings">
                            <div class="card card-body bg-light border p-3">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1 fw-semibold">Tracking Mode</label>
                                        <select name="is_serialized" class="form-select form-select-sm">
                                            <option value="1" <?= (int) ($product['is_serialized'] ?? 1) === 1 ? 'selected' : '' ?>>Serialized (1-to-1)</option>
                                            <option value="0" <?= isset($product['is_serialized']) && (int) $product['is_serialized'] === 0 ? 'selected' : '' ?>>Bulk (Quantity)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1 fw-semibold">Unit of Measure</label>
                                        <input type="text" name="unit_of_measure" class="form-control form-control-sm" value="<?= e($product['unit_of_measure'] ?? 'pcs') ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1 fw-semibold">Reorder Level</label>
                                        <input type="number" min="0" name="reorder_level" class="form-control form-control-sm" value="<?= (int) ($product['reorder_level'] ?? 5) ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1 fw-semibold">Unit Cost (PHP)</label>
                                        <input type="number" step="0.01" min="0" name="unit_cost" class="form-control form-control-sm" value="<?= number_format((float) ($product['unit_cost'] ?? 0), 2, '.', '') ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1 fw-semibold">Unit Price (PHP)</label>
                                        <input type="number" step="0.01" min="0" name="unit_price" class="form-control form-control-sm" value="<?= number_format((float) ($product['unit_price'] ?? 0), 2, '.', '') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?= url('/inventory/products') ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-sm px-4">
                            <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Save Changes' : 'Save Product Model' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
