<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">Warranty Certificate: <span class="font-monospace"><?= e($warranty['warranty_code']) ?></span></h2>
        <div class="text-secondary small">Serial: <span class="font-monospace"><?= e($warranty['serial_number']) ?></span> &bull; Status: <strong><?= ucfirst(str_replace('_', ' ', $warranty['derived_status'])) ?></strong></div>
    </div>
    <a href="<?= url('/warranties') ?>" class="btn btn-outline-secondary btn-sm">&larr; All Warranties</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header bg-light py-2 fw-semibold small">Certificate Details</div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-md-6"><span class="text-secondary d-block">Product</span><strong><?= e($warranty['item_name']) ?> (<?= e($warranty['model_part_number'] ?: $warranty['item_code']) ?>)</strong></div>
                    <div class="col-md-6"><span class="text-secondary d-block">Client</span><strong><?= e($warranty['client_name'] ?: '—') ?></strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Start Date</span><strong><?= e($warranty['start_date'] ?: '—') ?></strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Duration</span><strong><?= (int) $warranty['duration_months'] ?> Months</strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Expiry Date</span><strong><?= e($warranty['end_date'] ?: '—') ?></strong></div>
                    <div class="col-md-6"><span class="text-secondary d-block">Delivery Receipt</span><strong class="font-monospace"><?= e($warranty['dr_number'] ?: '—') ?></strong></div>
                    <div class="col-md-6"><span class="text-secondary d-block">Policy Applied</span><strong><?= e($warranty['policy_applied'] ?: 'actual_delivery_date') ?></strong></div>
                    <?php if (!empty($warranty['last_adjustment_reason'])): ?>
                        <div class="col-12 mt-2">
                            <div class="alert alert-info py-2 mb-0 small">
                                <strong>Last Manual Adjustment:</strong> <?= e($warranty['last_adjustment_reason']) ?> (by <?= e($warranty['adjusted_by_name'] ?? '—') ?> on <?= e($warranty['last_adjusted_at'] ?? '') ?>)
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($canManage)): ?>
            <div class="card">
                <div class="card-header bg-light py-2 fw-semibold small">Log Warranty / RMA Claim</div>
                <form method="POST" action="<?= url('/warranties/' . (int) $warranty['id'] . '/claims') ?>" class="card-body small">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Claim Date</label>
                        <input type="date" name="claim_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Reported Issue Description *</label>
                        <textarea name="issue_description" rows="2" class="form-control form-control-sm" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm">File Warranty Claim</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <?php if (!empty($canManage)): ?>
            <div class="card">
                <div class="card-header bg-light py-2 fw-semibold small">Authorized Manual Warranty Adjustment</div>
                <form method="POST" action="<?= url('/warranties/' . (int) $warranty['id'] . '/adjust') ?>" class="card-body small">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Start Date</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($warranty['start_date'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Duration (Months)</label>
                        <input type="number" name="duration_months" min="0" class="form-control form-control-sm" value="<?= (int) $warranty['duration_months'] ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Mandatory Adjustment Reason *</label>
                        <textarea name="reason" rows="2" class="form-control form-control-sm" required placeholder="Required for WARRANTY_ADJUSTED audit log..."></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Remarks</label>
                        <input type="text" name="remarks" class="form-control form-control-sm" value="<?= e($warranty['remarks'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm w-100">Apply Adjustment</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
