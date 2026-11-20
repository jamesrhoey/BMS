<?php
$u = $dossier['unit'];
$uBadge = match ($u['status']) {
    'in_stock' => 'bg-success',
    'delivered' => 'bg-primary',
    'under_repair' => 'bg-warning text-dark',
    'scrapped' => 'bg-danger',
    default => 'bg-secondary',
};
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">
            Serial Dossier: <span class="font-monospace"><?= e($u['serial_number']) ?></span>
            <span class="badge <?= $uBadge ?> ms-2"><?= ucfirst(str_replace('_', ' ', $u['status'])) ?></span>
        </h2>
        <div class="text-secondary small"><?= e($u['item_name']) ?> (<?= e($u['model_code']) ?>)</div>
    </div>
    <a href="<?= url('/inventory/units') ?>" class="btn btn-outline-secondary btn-sm">&larr; All Units</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-light py-2 fw-semibold small">Hardware Profile</div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-md-4"><span class="text-secondary d-block">Stock In Date</span><strong><?= e($u['stock_in_date'] ?: $u['inventory_date'] ?: '—') ?></strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">PO Reference</span><strong class="font-monospace"><?= e($u['po_reference'] ?: '—') ?></strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Supplier</span><strong><?= e($u['supplier_name'] ?: '—') ?></strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Stock Out Date</span><strong><?= e($u['stock_out_date'] ?: 'In Warehouse') ?></strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">DR Reference</span><strong class="font-monospace"><?= e($u['dr_reference'] ?: '—') ?></strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Assigned Client</span><strong><?= e($u['client_name'] ?: '—') ?></strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Warranty Start</span><strong><?= e($u['warranty_start_date'] ?: 'Starts on Delivery') ?></strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Warranty Expiry</span><strong><?= e($u['warranty_expiry_date'] ?: '—') ?></strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Location</span><strong><?= e($u['location_name'] ?: $u['current_location']) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-light py-2 fw-semibold small">Chronological Movement &amp; Maintenance Timeline</div>
            <ul class="list-group list-group-flush small">
                <?php if (empty($dossier['transactions'])): ?>
                    <li class="list-group-item text-secondary">No stock transaction events recorded yet.</li>
                <?php else: ?>
                    <?php foreach ($dossier['transactions'] as $tx): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><strong class="font-monospace"><?= e($tx['transaction_number']) ?></strong> &mdash; <?= e($tx['transaction_type']) ?> (<?= e($tx['reference_type']) ?>)</span>
                                <span class="text-secondary"><?= e($tx['transaction_date']) ?> &bull; <?= e($tx['performed_by_name'] ?? 'System') ?></span>
                            </div>
                            <div class="text-secondary"><?= e($tx['notes'] ?? '') ?></div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="card">
            <div class="card-header bg-light py-2 fw-semibold small">Warranty Certificates</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Client</th>
                            <th>Start</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th class="text-end">View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dossier['warranties'])): ?>
                            <tr><td colspan="6" class="text-center text-secondary py-3">No warranty certificate active yet (activates upon confirmed customer delivery).</td></tr>
                        <?php else: ?>
                            <?php foreach ($dossier['warranties'] as $w): ?>
                                <tr>
                                    <td class="font-monospace fw-semibold"><?= e($w['warranty_code']) ?></td>
                                    <td><?= e($w['client_name'] ?: '—') ?></td>
                                    <td><?= e($w['start_date'] ?: '—') ?></td>
                                    <td><?= e($w['end_date'] ?: '—') ?></td>
                                    <td><span class="badge bg-info-subtle text-info"><?= e($w['derived_status']) ?></span></td>
                                    <td class="text-end"><a href="<?= url('/warranties/' . (int) $w['id']) ?>" class="btn btn-outline-primary btn-sm py-0">Open</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if (!empty($canManageMaintenance) && in_array($u['status'], ['in_stock', 'under_repair'], true)): ?>
            <div class="card">
                <div class="card-header bg-light py-2 fw-semibold small">Maintenance / Status Transition</div>
                <form method="POST" action="<?= url('/inventory/units/' . (int) $u['id'] . '/maintenance') ?>" class="card-body small">
                    <?= csrf_field() ?>
                    <p class="text-secondary mb-2">Placing a unit in <code>under_repair</code> decrements available stock by 1 and blocks Stock Out until returned to <code>in_stock</code>.</p>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Target Status</label>
                        <select name="target_status" class="form-select form-select-sm" required>
                            <?php if ($u['status'] === 'in_stock'): ?>
                                <option value="under_repair">Place Under Maintenance (under_repair)</option>
                                <option value="scrapped">Retire / Scrap Unit (scrapped)</option>
                            <?php elseif ($u['status'] === 'under_repair'): ?>
                                <option value="in_stock">Return to Available Stock (in_stock)</option>
                                <option value="scrapped">Retire / Scrap Unit (scrapped)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Mandatory Reason *</label>
                        <textarea name="reason" rows="2" class="form-control form-control-sm" required placeholder="Document maintenance/bench reason..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm w-100">Execute Status Transition</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
