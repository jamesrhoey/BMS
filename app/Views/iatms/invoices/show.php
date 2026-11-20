<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">Invoice: <span class="font-monospace"><?= e($invoice['invoice_number']) ?></span></h2>
        <div class="text-secondary small"><?= ucfirst($invoice['invoice_type']) ?> &bull; Status: <strong><?= ucfirst(str_replace('_', ' ', $invoice['status'])) ?></strong></div>
    </div>
    <a href="<?= url('/invoices') ?>" class="btn btn-outline-secondary btn-sm">&larr; All Invoices</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body small">
                <div class="row g-2 mb-3">
                    <div class="col-md-4"><span class="text-secondary d-block">Party</span><strong><?= e($invoice['client_name'] ?: ($invoice['supplier_name'] ?: '—')) ?></strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Invoice Date / Due Date</span><strong><?= e($invoice['invoice_date']) ?> / <?= e($invoice['due_date']) ?></strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Linked DR / PO</span><strong class="font-monospace"><?= e($invoice['dr_number'] ?: ($invoice['po_number'] ?: '—')) ?></strong></div>
                </div>
                <table class="table table-bordered table-sm mb-2">
                    <thead class="table-light">
                        <tr><th>Description</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td><?= e($it['description']) ?></td>
                                <td class="text-center"><?= (int) $it['quantity'] ?></td>
                                <td class="text-end"><?= number_format((float) $it['unit_price'], 2) ?></td>
                                <td class="text-end"><?= number_format((float) $it['total_price'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="3" class="text-end">Subtotal</th><th class="text-end"><?= number_format((float) $invoice['subtotal'], 2) ?></th></tr>
                        <tr><th colspan="3" class="text-end">VAT / Tax (<?= number_format((float) $invoice['tax_rate'], 2) ?>%)</th><th class="text-end"><?= number_format((float) $invoice['tax_amount'], 2) ?></th></tr>
                        <tr><th colspan="3" class="text-end">Total Amount</th><th class="text-end"><?= e($invoice['currency']) ?> <?= number_format((float) $invoice['total_amount'], 2) ?></th></tr>
                        <tr><th colspan="3" class="text-end text-success">Paid Amount</th><th class="text-end text-success"><?= e($invoice['currency']) ?> <?= number_format((float) $invoice['paid_amount'], 2) ?></th></tr>
                        <tr><th colspan="3" class="text-end text-danger">Balance Due</th><th class="text-end text-danger"><?= e($invoice['currency']) ?> <?= number_format((float) $invoice['balance_due'], 2) ?></th></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-light py-2 fw-semibold small">Payment Collections &amp; Settlement History</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light">
                        <tr><th>Collection No.</th><th>Date</th><th>Method / Ref</th><th class="text-end">Amount</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($collections)): ?>
                            <tr><td colspan="6" class="text-center text-secondary py-3">No payments recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($collections as $c): ?>
                                <tr>
                                    <td class="font-monospace fw-semibold"><?= e($c['collection_number']) ?></td>
                                    <td><?= e($c['collection_date']) ?></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $c['payment_method'])) ?> <?= !empty($c['reference_number']) ? '(' . e($c['reference_number']) . ')' : '' ?></td>
                                    <td class="text-end fw-semibold"><?= number_format((float) $c['amount_received'], 2) ?></td>
                                    <td><span class="badge <?= $c['status'] === 'cleared' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($c['status']) ?></span></td>
                                    <td>
                                        <?php if (!empty($canManage) && $c['status'] !== 'voided'): ?>
                                            <form method="POST" action="<?= url('/collections/' . (int) $c['id'] . '/void') ?>" class="d-flex gap-1">
                                                <?= csrf_field() ?>
                                                <input type="text" name="void_reason" class="form-control form-control-sm" placeholder="Void reason" required style="width:120px;">
                                                <button type="submit" class="btn btn-outline-danger btn-sm py-0">Void</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if (!empty($canManage) && !in_array($invoice['status'], ['paid', 'cancelled'], true)): ?>
            <div class="card mb-3">
                <div class="card-header bg-light py-2 fw-semibold small">Record Payment Collection</div>
                <form method="POST" action="<?= url('/collections') ?>" class="card-body small">
                    <?= csrf_field() ?>
                    <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">
                    <input type="hidden" name="redirect_invoice" value="1">
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Amount Received (Max <?= number_format((float) $invoice['balance_due'], 2) ?>) *</label>
                        <input type="number" step="0.01" min="0.01" max="<?= number_format((float) $invoice['balance_due'], 2, '.', '') ?>" name="amount_received" class="form-control form-control-sm" value="<?= number_format((float) $invoice['balance_due'], 2, '.', '') ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Payment Date *</label>
                        <input type="date" name="collection_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Method</label>
                        <select name="payment_method" class="form-select form-select-sm">
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="check">Check</option>
                            <option value="cash">Cash</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Check / Reference No.</label>
                        <input type="text" name="reference_number" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Deposited To Account</label>
                        <input type="text" name="deposited_to_account" class="form-control form-control-sm">
                    </div>
                    <button type="submit" class="btn btn-success btn-sm w-100">Record Payment</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
