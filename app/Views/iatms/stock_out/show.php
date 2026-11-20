<?php
$badge = match ($delivery['status']) {
    'delivered' => 'bg-success',
    'in_transit', 'dispatched' => 'bg-primary',
    'delayed' => 'bg-warning text-dark',
    'cancelled' => 'bg-danger',
    default => 'bg-secondary',
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">
            Delivery Receipt: <span class="font-monospace"><?= e($delivery['dr_number'] ?: $delivery['delivery_number']) ?></span>
            <span class="badge <?= $badge ?> ms-2"><?= ucfirst(str_replace('_', ' ', $delivery['status'])) ?></span>
        </h2>
        <div class="text-secondary small">Stock Out Ref: <span class="font-monospace"><?= e($delivery['stock_out_reference'] ?? '—') ?></span> &bull; Prepared by <?= e($delivery['prepared_by_name'] ?? '—') ?></div>
    </div>
    <a href="<?= url('/inventory/stock-out') ?>" class="btn btn-outline-secondary btn-sm">&larr; Back to Stock Out List</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-light py-2 fw-semibold small">Delivery Summary</div>
            <div class="card-body small">
                <div class="row g-3">
                    <div class="col-md-4"><span class="text-secondary d-block">Company / Client</span><strong><?= e($delivery['display_client_name'] ?: '—') ?></strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Out Date</span><strong><?= e($delivery['delivery_date']) ?></strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Actual Delivery Date</span><strong><?= e($delivery['actual_delivery_date'] ?: 'Pending Confirmation') ?></strong></div>
                    <div class="col-md-6"><span class="text-secondary d-block">Shipping / Site Address</span><strong><?= e($delivery['shipping_address']) ?></strong></div>
                    <div class="col-md-3"><span class="text-secondary d-block">Customer PO / PR</span><strong class="font-monospace"><?= e($delivery['customer_po_number'] ?: ($delivery['pr_reference'] ?: '—')) ?></strong></div>
                    <div class="col-md-3"><span class="text-secondary d-block">Invoice No.</span><strong class="font-monospace"><?= e($delivery['invoice_number'] ?: '—') ?></strong></div>
                    <?php if (!empty($delivery['received_by_client_name'])): ?>
                        <div class="col-md-6"><span class="text-secondary d-block">Received By (Client)</span><strong><?= e($delivery['received_by_client_name']) ?></strong></div>
                    <?php endif; ?>
                    <?php if ($delivery['status'] === 'cancelled'): ?>
                        <div class="col-12">
                            <div class="alert alert-danger py-2 mb-0 small">
                                <strong>Cancelled:</strong> <?= e($delivery['cancellation_reason']) ?> (by <?= e($delivery['cancelled_by_name'] ?? '—') ?> on <?= e($delivery['cancelled_at'] ?? '') ?>)
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-light py-2 fw-semibold small">Issued Equipment &amp; Serialized Units</div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Product / Model</th>
                            <th class="text-center">Out Qty</th>
                            <th class="text-center">Warranty</th>
                            <th>Assigned Serial Numbers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($it['item_name']) ?></div>
                                    <div class="text-secondary font-monospace small"><?= e($it['model_part_number'] ?: $it['item_code']) ?></div>
                                </td>
                                <td class="text-center fw-semibold"><?= (int) $it['quantity_delivered'] ?></td>
                                <td class="text-center"><?= (int) ($it['warranty_duration_months'] ?? 24) ?> mos</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($it['serials'] as $s): ?>
                                            <a href="<?= url('/inventory/units/' . (int) $s['id']) ?>" class="badge <?= (int) $s['is_active'] === 1 ? 'bg-light text-dark border' : 'bg-secondary-subtle text-secondary text-decoration-line-through' ?> text-decoration-none font-monospace">
                                                <?= e($s['serial_number']) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-light py-2 fw-semibold small">Delivery Trajectory &amp; Daily Update Log</div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($updates as $upd): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong><?= ucfirst(str_replace('_', ' ', $upd['new_status'])) ?></strong>
                            <span class="text-secondary"><?= e($upd['created_at']) ?> &bull; <?= e($upd['created_by_name'] ?? 'System') ?></span>
                        </div>
                        <div class="text-secondary"><?= e($upd['remarks'] ?? '') ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if (!empty($canManage) && $delivery['status'] !== 'cancelled'): ?>
            <div class="card mb-3">
                <div class="card-header bg-light py-2 fw-semibold small">Update Delivery Status</div>
                <form method="POST" action="<?= url('/inventory/stock-out/' . (int) $delivery['id'] . '/status') ?>" class="card-body small">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">New Status</label>
                        <select name="new_status" id="iatmsDeliveryStatusSelect" class="form-select form-select-sm" required>
                            <?php foreach (['dispatched', 'in_transit', 'delivered', 'delayed', 'returned'] as $st): ?>
                                <option value="<?= $st ?>" <?= $delivery['status'] === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="iatmsDeliveryConfirmFields" class="d-none border rounded p-2 bg-light mb-2">
                        <div class="text-success fw-semibold mb-1">Confirming <code>delivered</code> activates 24-month warranties!</div>
                        <div class="mb-2">
                            <label class="form-label mb-1">Actual Delivery Date</label>
                            <input type="date" name="actual_delivery_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label mb-1">Received By (Client Name)</label>
                            <input type="text" name="received_by_client_name" class="form-control form-control-sm" placeholder="Signatory full name">
                        </div>
                        <div>
                            <label class="form-label mb-1">Reception Notes</label>
                            <input type="text" name="delivery_confirmation_notes" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Update Remarks</label>
                        <textarea name="remarks" rows="2" class="form-control form-control-sm" placeholder="Daily transit or handover remarks..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Save Status Update</button>
                </form>
            </div>

            <div class="card border-danger">
                <div class="card-header bg-danger-subtle text-danger py-2 fw-semibold small">Cancel / Reverse Stock Out</div>
                <form method="POST" action="<?= url('/inventory/stock-out/' . (int) $delivery['id'] . '/cancel') ?>" class="card-body small">
                    <?= csrf_field() ?>
                    <p class="text-secondary mb-2">Atomically restores all attached serialized units back to <code>in_stock</code> and posts an offsetting reversal to the inventory ledger.</p>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Mandatory Cancellation Reason *</label>
                        <textarea name="cancellation_reason" rows="2" class="form-control form-control-sm" required placeholder="Document reason for reversal..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">Reverse &amp; Cancel Delivery</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
