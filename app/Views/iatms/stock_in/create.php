<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">Record Stock In — Digitized Paper Form 1</h2>
        <div class="text-secondary small">Supports Mode A (Direct Form 1 / Legacy PO) and Mode B (Receive Against System PO) with atomic 1-to-1 serial validation.</div>
    </div>
    <a href="<?= url('/inventory/stock-in') ?>" class="btn btn-outline-secondary btn-sm">&larr; Stock In List</a>
</div>

<form method="POST" action="<?= url('/inventory/stock-in') ?>">
    <?= csrf_field() ?>

    <div class="card mb-3">
        <div class="card-header bg-light py-2 fw-semibold small">1. Receiving Mode &amp; Header Details</div>
        <div class="card-body">
            <div class="mb-3 d-flex gap-4">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="receiving_mode" id="modeDirect" value="direct" checked>
                    <label class="form-check-label fw-semibold small" for="modeDirect">Mode A — Direct Stock In (Paper Form 1 / Legacy PO)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="receiving_mode" id="modePo" value="po">
                    <label class="form-check-label fw-semibold small" for="modePo">Mode B — Receive Against System PO</label>
                </div>
            </div>

            <div id="iatmsPoSelectorWrap" class="mb-3 d-none">
                <label class="form-label small fw-semibold">Select Open System Purchase Order</label>
                <select name="po_id" id="iatmsPoSelect" class="form-select form-select-sm" data-po-items-url="<?= url('/inventory/stock-in/po-items') ?>">
                    <option value="">-- Choose Approved / Issued PO --</option>
                    <?php foreach ($openPos as $po): ?>
                        <option value="<?= (int) $po['id'] ?>">
                            <?= e($po['po_number']) ?> — <?= e($po['supplier_name']) ?> (Outstanding: <?= (int) $po['total_outstanding'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Receipt Reference</label>
                    <input type="text" name="receipt_number" class="form-control form-control-sm font-monospace" value="<?= e($nextReceiptNumber) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">PO Number (Preserves Leading Zeros)</label>
                    <input type="text" name="po_number" id="iatmsPoNumberInput" class="form-control form-control-sm font-monospace" placeholder="e.g. 000398">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">PO Date</label>
                    <input type="date" name="po_date" id="iatmsPoDateInput" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Delivery to PCT / Inventory Date *</label>
                    <input type="date" name="delivery_to_pct_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                    <input type="hidden" name="receipt_date" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Registered Supplier</label>
                    <select name="supplier_id" id="iatmsSupplierSelect" class="form-select form-select-sm">
                        <option value="">-- Or type Ad-Hoc Supplier Name --&gt;</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['supplier_name']) ?> (<?= e($s['supplier_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Ad-Hoc Supplier Name (If not in dropdown)</label>
                    <input type="text" name="supplier_name" class="form-control form-control-sm" placeholder="Type vendor name...">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Destination Warehouse *</label>
                    <select name="destination_location_id" class="form-select form-select-sm" required>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= (int) $loc['id'] ?>"><?= e($loc['name']) ?> (<?= e($loc['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Supplier DR / Waybill No.</label>
                    <input type="text" name="waybill_number" class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Supplier Invoice Reference</label>
                    <input type="text" name="invoice_reference" class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Remarks</label>
                    <input type="text" name="remarks" class="form-control form-control-sm">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
            <span class="fw-semibold small">2. Equipment Lines &amp; Serial Numbers (Exact 1-to-1 Match Required)</span>
            <button type="button" class="btn btn-outline-primary btn-sm py-0" data-add-row-target="iatmsStockInLinesBody" data-add-row-template="iatmsStockInLineTemplate">+ Add Line</button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-top mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th style="width:28%;">Product / Model *</th>
                        <th style="width:9%;">In Qty *</th>
                        <th style="width:12%;">Unit Cost</th>
                        <th style="width:10%;">Warranty (Mos)</th>
                        <th style="width:36%;">Serial Numbers (One per line or comma-separated)</th>
                        <th style="width:5%;"></th>
                    </tr>
                </thead>
                <tbody id="iatmsStockInLinesBody">
                    <tr>
                        <td>
                            <input type="hidden" name="line_po_item_id[]" class="iatms-line-po-item" value="">
                            <select name="line_item_id[]" class="form-select form-select-sm iatms-line-item" required>
                                <option value="">-- Select Product --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>">[<?= e($p['model_code']) ?>] <?= e($p['item_name']) ?> <?= (int) $p['is_serialized'] === 1 ? '(Serialized)' : '(Bulk)' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" name="line_qty[]" min="1" value="1" class="form-control form-control-sm iatms-line-qty" required></td>
                        <td><input type="number" step="0.01" min="0" name="line_unit_cost[]" value="0.00" class="form-control form-control-sm iatms-line-cost"></td>
                        <td><input type="number" min="0" name="line_warranty_months[]" value="24" class="form-control form-control-sm iatms-line-warranty"></td>
                        <td>
                            <textarea name="line_serials[]" rows="2" class="form-control form-control-sm font-monospace iatms-line-serials" placeholder="SN0001&#10;SN0002"></textarea>
                            <span class="badge bg-warning text-dark mt-1 iatms-serial-count-badge">0 / 1 serials</span>
                        </td>
                        <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0" data-remove-row>&times;</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= url('/inventory/stock-in') ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm">Post Atomic Stock In Receipt</button>
        </div>
    </div>
</form>

<template id="iatmsStockInLineTemplate">
    <tr>
        <td>
            <input type="hidden" name="line_po_item_id[]" class="iatms-line-po-item" value="">
            <select name="line_item_id[]" class="form-select form-select-sm iatms-line-item" required>
                <option value="">-- Select Product --</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int) $p['id'] ?>">[<?= e($p['model_code']) ?>] <?= e($p['item_name']) ?> <?= (int) $p['is_serialized'] === 1 ? '(Serialized)' : '(Bulk)' ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" name="line_qty[]" min="1" value="1" class="form-control form-control-sm iatms-line-qty" required></td>
        <td><input type="number" step="0.01" min="0" name="line_unit_cost[]" value="0.00" class="form-control form-control-sm iatms-line-cost"></td>
        <td><input type="number" min="0" name="line_warranty_months[]" value="24" class="form-control form-control-sm iatms-line-warranty"></td>
        <td>
            <textarea name="line_serials[]" rows="2" class="form-control form-control-sm font-monospace iatms-line-serials" placeholder="SN0001&#10;SN0002"></textarea>
            <span class="badge bg-warning text-dark mt-1 iatms-serial-count-badge">0 / 1 serials</span>
        </td>
        <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0" data-remove-row>&times;</button></td>
    </tr>
</template>
