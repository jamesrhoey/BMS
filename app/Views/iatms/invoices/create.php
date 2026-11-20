<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0 fw-semibold">Create Invoice</h2>
    <a href="<?= url('/invoices') ?>" class="btn btn-outline-secondary btn-sm">&larr; Back to Invoices</a>
</div>

<form method="POST" action="<?= url('/invoices') ?>">
    <?= csrf_field() ?>
    <div class="card mb-3">
        <div class="card-body small">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Invoice Number *</label>
                    <input type="text" name="invoice_number" class="form-control form-control-sm font-monospace" value="<?= e($nextInvoiceNumber) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Invoice Type *</label>
                    <select name="invoice_type" class="form-select form-select-sm" required>
                        <option value="receivable">Receivable (Customer Invoice)</option>
                        <option value="payable">Payable (Supplier Bill)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Invoice Date *</label>
                    <input type="date" name="invoice_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Due Date (Auto if blank)</label>
                    <input type="date" name="due_date" class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Client (For Receivable)</label>
                    <select name="client_id" class="form-select form-select-sm">
                        <option value="">-- Select Client --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= e($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Linked Delivery Receipt (DR)</label>
                    <select name="delivery_id" class="form-select form-select-sm">
                        <option value="">-- Optional DR Link --</option>
                        <?php foreach ($deliveries as $d): ?>
                            <option value="<?= (int) $d['id'] ?>"><?= e($d['dr_number'] ?: $d['delivery_number']) ?> — <?= e($d['display_client_name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Supplier (For Payable)</label>
                    <select name="supplier_id" class="form-select form-select-sm">
                        <option value="">-- Select Supplier --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['supplier_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Payment Term</label>
                    <select name="payment_term_id" class="form-select form-select-sm">
                        <option value="">-- Select Term --</option>
                        <?php foreach ($paymentTerms as $pt): ?>
                            <option value="<?= (int) $pt['id'] ?>"><?= e($pt['name']) ?> (<?= (int) $pt['days'] ?>d)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">VAT / Tax %</label>
                    <input type="number" step="0.01" min="0" name="tax_rate" class="form-control form-control-sm" value="12.00">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Notes</label>
                    <input type="text" name="notes" class="form-control form-control-sm">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
            <span class="fw-semibold small">Invoice Line Items</span>
            <button type="button" class="btn btn-outline-primary btn-sm py-0" data-add-row-target="iatmsInvoiceLinesBody" data-add-row-template="iatmsInvoiceLineTemplate">+ Add Line</button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th style="width:55%;">Description *</th>
                        <th style="width:15%;">Qty *</th>
                        <th style="width:22%;">Unit Price *</th>
                        <th style="width:8%;"></th>
                    </tr>
                </thead>
                <tbody id="iatmsInvoiceLinesBody">
                    <tr>
                        <td><input type="text" name="item_desc[]" class="form-control form-control-sm" required placeholder="Equipment or service description..."></td>
                        <td><input type="number" name="item_qty[]" min="1" value="1" class="form-control form-control-sm" required></td>
                        <td><input type="number" step="0.01" min="0" name="item_price[]" value="0.00" class="form-control form-control-sm" required></td>
                        <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0" data-remove-row>&times;</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= url('/invoices') ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm">Issue Invoice</button>
        </div>
    </div>
</form>

<template id="iatmsInvoiceLineTemplate">
    <tr>
        <td><input type="text" name="item_desc[]" class="form-control form-control-sm" required placeholder="Equipment or service description..."></td>
        <td><input type="number" name="item_qty[]" min="1" value="1" class="form-control form-control-sm" required></td>
        <td><input type="number" step="0.01" min="0" name="item_price[]" value="0.00" class="form-control form-control-sm" required></td>
        <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0" data-remove-row>&times;</button></td>
    </tr>
</template>
