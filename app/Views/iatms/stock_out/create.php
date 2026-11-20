<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">Record Stock Out — Digitized Paper Form 2</h2>
        <div class="text-secondary small">Select strictly available <code>in_stock</code> serialized units (locked via <code>FOR UPDATE</code>) and assign Delivery Receipt &amp; Client details.</div>
    </div>
    <a href="<?= url('/inventory/stock-out') ?>" class="btn btn-outline-secondary btn-sm">&larr; Stock Out List</a>
</div>

<form method="POST" action="<?= url('/inventory/stock-out') ?>">
    <?= csrf_field() ?>

    <div class="card mb-3">
        <div class="card-header bg-light py-2 fw-semibold small">1. Delivery Receipt (Form 2) Header &amp; Recipient Details</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">DR No. (Unique, Preserves Leading Zeros) *</label>
                    <input type="text" name="dr_number" class="form-control form-control-sm font-monospace" value="<?= e($nextDrNumber) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Out Date *</label>
                    <input type="date" name="delivery_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Invoice No.</label>
                    <input type="text" name="invoice_number" class="form-control form-control-sm font-monospace" placeholder="e.g. 000812">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Warranty Duration (Months)</label>
                    <input type="number" name="warranty_duration_months" min="0" value="24" class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Existing Client Company</label>
                    <select name="client_id" id="iatmsClientSelect" class="form-select form-select-sm">
                        <option value="">-- Or type New Company Name --&gt;</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" data-address="<?= e($c['shipping_address'] ?? '') ?>">
                                <?= e($c['company_name']) ?> (<?= e($c['client_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">New Company Name (Auto-Registers into Clients)</label>
                    <input type="text" name="company_name" id="iatmsCompanyNameInput" class="form-control form-control-sm" placeholder="Type new client company...">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Source Warehouse *</label>
                    <select name="source_location_id" class="form-select form-select-sm" required>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= (int) $loc['id'] ?>"><?= e($loc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Shipping / Jobsite Address *</label>
                    <input type="text" name="shipping_address" id="iatmsShippingAddress" class="form-control form-control-sm" required placeholder="Full delivery address...">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Customer PO No.</label>
                    <input type="text" name="customer_po_number" class="form-control form-control-sm font-monospace">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Internal PR No.</label>
                    <input type="text" name="pr_reference" class="form-control form-control-sm font-monospace">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-light py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold small">2. Select Available Serialized Units (<code>status = 'in_stock'</code> Only)</span>
            <div class="d-flex align-items-center gap-2">
                <input type="text" id="iatmsSerialPickerSearch" class="form-control form-control-sm" style="width:260px;" placeholder="Filter by serial, model, or PO...">
                <span class="badge bg-primary" id="iatmsSelectedSerialCount">0 selected</span>
            </div>
        </div>
        <div class="table-responsive" style="max-height:360px; overflow-y:auto;">
            <table class="table table-sm table-hover align-middle mb-0 small">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width:40px;" class="text-center">Pick</th>
                        <th>Serial Number</th>
                        <th>Model / Product</th>
                        <th>PO Reference</th>
                        <th>Stock In Date</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($availableUnits)): ?>
                        <tr><td colspan="6" class="text-center text-secondary py-4">No <code>in_stock</code> serialized units available. Record Stock In (Form 1) first.</td></tr>
                    <?php else: ?>
                        <?php foreach ($availableUnits as $u): ?>
                            <?php $searchHay = ($u['serial_number'] ?? '') . ' ' . ($u['model_code'] ?? '') . ' ' . ($u['item_name'] ?? '') . ' ' . ($u['po_reference'] ?? ''); ?>
                            <tr class="iatms-serial-row" data-search="<?= e($searchHay) ?>">
                                <td class="text-center">
                                    <input type="checkbox" name="unit_ids[]" value="<?= (int) $u['id'] ?>" class="form-check-input iatms-serial-checkbox">
                                </td>
                                <td class="fw-semibold font-monospace"><?= e($u['serial_number']) ?></td>
                                <td>
                                    <span class="fw-medium"><?= e($u['item_name']) ?></span>
                                    <span class="text-secondary font-monospace small ms-1">(<?= e($u['model_code']) ?>)</span>
                                </td>
                                <td class="font-monospace"><?= e($u['po_reference'] ?: '—') ?></td>
                                <td><?= e($u['stock_in_date'] ?: '—') ?></td>
                                <td><?= e($u['location_name'] ?: 'PCT Warehouse') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= url('/inventory/stock-out') ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm">Execute Atomic Stock Out</button>
        </div>
    </div>
</form>
