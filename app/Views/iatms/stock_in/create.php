<?php
// View variables: $pageTitle, $nextReceiptNumber, $products, $locations, $suppliers, $openPos, $currentUser
?>
<!-- ─── Page Header ─────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= url('/inventory/stock-in') ?>" class="btn btn-sm btn-outline-secondary" title="Back to Supplier Receipts">
            &larr;
        </a>
        <div>
            <h5 class="mb-0 fw-bold text-dark">Stock In / Equipment Receiving (Form 1)</h5>
            <small class="text-secondary">Digitized paper form entry for inbound equipment receipts and serial number tracking.</small>
        </div>
    </div>
    <div class="text-muted small">
        <i class="bi bi-person-check me-1"></i>Receiving Officer: <strong class="text-dark"><?= e($currentUser['name'] ?? $currentUser['username'] ?? 'Staff') ?></strong>
    </div>
</div>

<!-- ─── Mode Switcher Tabs ─────────────────────────────────────────────── -->
<div class="d-flex gap-2 mb-3 border-bottom pb-2">
    <button type="button" class="btn btn-sm btn-primary" id="tabDirectBtn" onclick="switchMode('direct')">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Direct Stock In (Paper Form 1)
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="tabPoBtn" onclick="switchMode('po')">
        <i class="bi bi-cart-check me-1"></i>Receive Against System PO
        <?php if (!empty($openPos)): ?>
            <span class="badge bg-secondary ms-1"><?= count($openPos) ?></span>
        <?php endif; ?>
    </button>
</div>

<form method="POST" action="<?= url('/inventory/stock-in') ?>" id="stockInForm">
    <?= csrf_field() ?>
    <input type="hidden" name="receiving_mode" id="receivingModeInput" value="direct">

    <!-- ─── Section 1: Header ───────────────────────────────────────────── -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-text text-primary"></i>
                <h6 class="mb-0 fw-bold text-dark" id="headerTitle">Direct Stock In (Paper Form 1 Entry)</h6>
            </div>
            <span class="badge bg-light text-secondary border font-monospace small">Form 1: Stock In</span>
        </div>
        <div class="card-body p-3">

            <!-- PO Mode: System PO Selector -->
            <div id="systemPoSection" style="display:none;" class="mb-3 p-3 bg-light rounded border">
                <div class="row g-2 align-items-center">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary mb-1">
                            Select Approved System Purchase Order <span class="text-danger">*</span>
                        </label>
                        <select name="po_id" id="poSelect" class="form-select form-select-sm" onchange="loadPoDetails(this.value)">
                            <option value="">-- Choose Approved / Issued PO --</option>
                            <?php foreach ($openPos as $po): ?>
                                <option value="<?= (int) $po['id'] ?>">
                                    <?= e($po['po_number']) ?> &mdash; <?= e($po['supplier_name'] ?? '') ?> (Outstanding: <?= (int) $po['total_outstanding'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mt-3">
                            <i class="bi bi-info-circle me-1"></i>Selecting a PO auto-fills PO Number, PO Date, and Supplier.
                        </small>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <!-- PO Number -->
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold text-dark">
                        PO Number <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="po_number" id="poNumberInput"
                           class="form-control form-control-sm font-monospace"
                           placeholder="e.g. 000398" required autocomplete="off">
                    <div class="form-text small text-muted">Preserves leading zeros (e.g. 000398).</div>
                </div>

                <!-- PO Date -->
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold text-dark">
                        PO Date <span class="text-danger">*</span>
                    </label>
                    <input type="date" name="po_date" id="poDateInput"
                           class="form-control form-control-sm"
                           value="<?= date('Y-m-d') ?>" required>
                    <div class="form-text small text-muted">Date purchase order was issued.</div>
                </div>

                <!-- Delivery to PCT / Inventory Date -->
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold text-dark">
                        Delivery to PCT / Inventory Date <span class="text-danger">*</span>
                    </label>
                    <input type="date" name="delivery_to_pct_date"
                           class="form-control form-control-sm"
                           value="<?= date('Y-m-d') ?>" required>
                    <div class="form-text small text-muted">Physical delivery date at PCT.</div>
                </div>

                <!-- Receipt Reference -->
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold text-dark">
                        Receipt Reference <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="receipt_number"
                           class="form-control form-control-sm font-monospace"
                           value="<?= e($nextReceiptNumber) ?>" required>
                    <div class="form-text small text-muted">Internal system receipt identifier.</div>
                </div>

                <!-- Supplier / Vendor -->
                <div class="col-md-4 col-sm-6">
                    <label class="form-label small fw-semibold text-dark">Supplier / Vendor</label>
                    <select name="supplier_id" id="supplierSelect" class="form-select form-select-sm" onchange="syncSupplierName(this)">
                        <option value="">-- Choose Existing Supplier or Type Below --</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= (int) $sup['id'] ?>" data-name="<?= e($sup['supplier_name'] ?? $sup['company_name'] ?? '') ?>">
                                <?= e($sup['supplier_name'] ?? $sup['company_name'] ?? '') ?><?php if (!empty($sup['supplier_code'])): ?> (<?= e($sup['supplier_code']) ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="supplier_name" id="supplierNameInput"
                           class="form-control form-control-sm mt-1"
                           placeholder="Or type vendor/supplier name directly...">
                </div>

                <!-- Destination Warehouse -->
                <div class="col-md-4 col-sm-6">
                    <label class="form-label small fw-semibold text-dark">
                        Destination Warehouse Location <span class="text-danger">*</span>
                    </label>
                    <select name="destination_location_id" class="form-select form-select-sm" required>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= (int) $loc['id'] ?>" <?= (int) $loc['id'] === 1 ? 'selected' : '' ?>>
                                <?= e($loc['name']) ?> (<?= e($loc['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Supplier DR / Invoice / Waybill -->
                <div class="col-md-4 col-sm-12">
                    <label class="form-label small fw-semibold text-dark">
                        Supplier DR / Invoice / Waybill Reference
                    </label>
                    <input type="text" name="invoice_reference" class="form-control form-control-sm"
                           placeholder="e.g. DR-88912 / INV-4402">
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Section 2: Equipment Lines ────────────────────────────────────── -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-boxes text-primary"></i>
                <h6 class="mb-0 fw-bold text-dark">Equipment Units (Model, Description, Warranty, Serial, In Qty)</h6>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1" onclick="addEquipmentRow()">
                <i class="bi bi-plus-circle"></i> Add Equipment Line
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-top mb-0 small" id="stockInItemsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px;" class="text-center">#</th>
                            <th style="min-width:200px;">Model <span class="text-danger">*</span></th>
                            <th style="min-width:220px;">Description</th>
                            <th style="width:130px;">Warranty Duration</th>
                            <th style="width:80px;" class="text-center">In Qty <span class="text-danger">*</span></th>
                            <th style="min-width:260px;">Serial Number(s) <span class="text-danger">*</span></th>
                            <th style="width:100px;" class="text-end">Unit Cost</th>
                            <th style="width:40px;" class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="equipmentItemsBody">
                        <!-- JS-injected rows -->
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end fw-bold small">Total Physical Units:</th>
                            <th class="text-center fw-bold text-success" id="totalQtySummary">0</th>
                            <th colspan="3" class="text-muted small" id="serializedSummary">0 serialized units</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-top p-3 d-flex align-items-center justify-content-between">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addEquipmentRow()">
                <i class="bi bi-plus-lg me-1"></i>Add Another Line
            </button>
            <div class="d-flex gap-2">
                <a href="<?= url('/inventory/stock-in') ?>" class="btn btn-outline-secondary btn-sm px-3">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold" id="submitStockInBtn">
                    <i class="bi bi-check2-circle me-1"></i>Save &amp; Post Stock In
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Product Catalog JS Cache -->
<script id="catalogData" type="application/json"><?= json_encode(array_map(function($p) {
    return [
        'id'                      => (int) $p['id'],
        'model_code'              => (string) ($p['model_code'] ?? $p['model_part_number'] ?? $p['item_code'] ?? ''),
        'name'                    => (string) ($p['description'] ?? $p['item_name'] ?? ''),
        'description'             => (string) ($p['description'] ?? ''),
        'default_warranty_months' => (int) ($p['default_warranty_months'] ?? 12),
        'is_serialized'           => (int) ($p['is_serialized'] ?? 1),
        'unit_cost'               => (float) ($p['unit_cost'] ?? 0),
    ];
}, $products), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>

<script>
(function () {
    'use strict';

    const CATALOG = JSON.parse(document.getElementById('catalogData').textContent);
    let rowCounter = 0;

    /* ── Mode Switcher ───────────────────────────────────── */
    window.switchMode = function (mode) {
        const isPoMode = mode === 'po';
        document.getElementById('receivingModeInput').value = mode;
        document.getElementById('systemPoSection').style.display = isPoMode ? 'block' : 'none';
        document.getElementById('headerTitle').textContent = isPoMode
            ? 'Receive Against System Purchase Order'
            : 'Direct Stock In (Paper Form 1 Entry)';
        document.getElementById('tabDirectBtn').className = isPoMode
            ? 'btn btn-sm btn-outline-secondary'
            : 'btn btn-sm btn-primary';
        document.getElementById('tabPoBtn').className = isPoMode
            ? 'btn btn-sm btn-primary'
            : 'btn btn-sm btn-outline-secondary';
    };

    /* ── Supplier name sync ──────────────────────────────── */
    window.syncSupplierName = function (sel) {
        const opt = sel.options[sel.selectedIndex];
        const name = opt.getAttribute('data-name');
        const inp = document.getElementById('supplierNameInput');
        if (name && inp) inp.value = name;
    };

    /* ── PO Detail Load ──────────────────────────────────── */
    window.loadPoDetails = function (poId) {
        if (!poId) return;
        window.location.href = '<?= url('/inventory/stock-in/po-items/') ?>' + poId;
    };

    /* ── Add Row ─────────────────────────────────────────── */
    window.addEquipmentRow = function (data) {
        rowCounter++;
        const idx = rowCounter;
        const tbody = document.getElementById('equipmentItemsBody');

        const preProductId = data ? (parseInt(data.product_id) || 0) : 0;
        const prePoItemId  = data ? (data.po_item_id || '') : '';
        const preDesc      = data ? escHtml(data.description || '') : '';
        const preWarranty  = data ? (parseInt(data.warranty_duration_months) || 12) : 12;
        const preQty       = data ? (parseInt(data.quantity) || 1) : 1;
        const preCost      = data ? (parseFloat(data.unit_cost) || '') : '';

        let opts = '<option value="">-- Select Product Model --</option>';
        CATALOG.forEach(p => {
            const sel = (preProductId && p.id === preProductId) ? 'selected' : '';
            opts += `<option value="${p.id}" ${sel}
                data-desc="${escHtml(p.description || p.name)}"
                data-warranty="${p.default_warranty_months}"
                data-serialized="${p.is_serialized}"
                data-cost="${p.unit_cost}">${escHtml(p.model_code)} &mdash; ${escHtml(p.name)}</option>`;
        });

        const tr = document.createElement('tr');
        tr.id = `row_${idx}`;
        tr.className = 'equipment-line-row';
        tr.innerHTML = `
            <td class="text-center fw-semibold text-muted row-num"></td>
            <td>
                <input type="hidden" name="items[${idx}][po_item_id]" value="${escHtml(prePoItemId)}">
                <select name="items[${idx}][product_id]" class="form-select form-select-sm model-select" required
                        onchange="onModelChange(${idx})">${opts}</select>
            </td>
            <td>
                <input type="text" name="items[${idx}][description]" id="desc_${idx}"
                       class="form-control form-control-sm"
                       placeholder="Product Description" value="${preDesc}">
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" name="items[${idx}][warranty_duration_months]" id="warranty_${idx}"
                           class="form-control form-control-sm text-center" min="0" max="120"
                           value="${preWarranty}" required>
                    <span class="input-group-text small">mos</span>
                </div>
            </td>
            <td>
                <input type="number" name="items[${idx}][quantity]" id="qty_${idx}"
                       class="form-control form-control-sm text-center fw-bold qty-input" min="1"
                       value="${preQty}" required oninput="onQtyChange(${idx})">
            </td>
            <td>
                <div id="serialWrap_${idx}">
                    <textarea name="items[${idx}][serials]" id="serials_${idx}"
                              class="form-control form-control-sm font-monospace serial-textarea" rows="2"
                              placeholder="Enter 1 serial per line, or comma-separated..."
                              oninput="validateSerials(${idx})"></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small class="serial-status text-muted" id="serialBadge_${idx}" style="font-size:11px;">
                            Needs <span class="req-count">${preQty}</span> more serial(s) (0/${preQty})
                        </small>
                        <small class="text-secondary" id="serialCount_${idx}" style="font-size:11px;">Entered: 0</small>
                    </div>
                </div>
            </td>
            <td>
                <input type="number" step="0.01" name="items[${idx}][unit_cost]" id="cost_${idx}"
                       class="form-control form-control-sm text-end font-monospace"
                       placeholder="0.00" value="${escHtml(String(preCost))}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-link text-danger p-0" onclick="removeRow('row_${idx}')" title="Remove">
                    <i class="bi bi-trash3"></i>
                </button>
            </td>`;

        tbody.appendChild(tr);
        onModelChange(idx, false);
        renumber();
        updateTotals();
    };

    /* ── Model Change ────────────────────────────────────── */
    window.onModelChange = function (idx, resetValues = true) {
        const row = document.getElementById(`row_${idx}`);
        if (!row) return;
        const sel     = row.querySelector('.model-select');
        const opt     = sel.options[sel.selectedIndex];
        const descInp = document.getElementById(`desc_${idx}`);
        const warInp  = document.getElementById(`warranty_${idx}`);
        const costInp = document.getElementById(`cost_${idx}`);
        const serWrap = document.getElementById(`serialWrap_${idx}`);
        const serTa   = document.getElementById(`serials_${idx}`);

        if (opt && opt.value) {
            const isSerial = parseInt(opt.getAttribute('data-serialized')) === 1;
            if (resetValues) {
                const desc = opt.getAttribute('data-desc');
                const war  = opt.getAttribute('data-warranty');
                const cost = opt.getAttribute('data-cost');
                if (desc && descInp) descInp.value = desc;
                if (war  && warInp)  warInp.value  = war;
                if (cost && costInp && !costInp.value) costInp.value = cost;
            }
            if (isSerial) {
                serTa.required = true;
                serTa.disabled = false;
            } else {
                serTa.required = false;
                serTa.disabled = true;
                serTa.value    = '';
                document.getElementById(`serialBadge_${idx}`).innerHTML = '<span class="text-muted fst-italic">Bulk / Non-serialized item</span>';
                document.getElementById(`serialCount_${idx}`).textContent = '';
            }
        }
        validateSerials(idx);
    };

    /* ── Qty Change ──────────────────────────────────────── */
    window.onQtyChange = function (idx) {
        const qty = parseInt(document.getElementById(`qty_${idx}`).value) || 0;
        const reqSpan = document.querySelector(`#serialBadge_${idx} .req-count`);
        if (reqSpan) reqSpan.textContent = qty;
        validateSerials(idx);
        updateTotals();
    };

    /* ── Serial Validation ───────────────────────────────── */
    window.validateSerials = function (idx) {
        const qtyEl  = document.getElementById(`qty_${idx}`);
        const ta     = document.getElementById(`serials_${idx}`);
        const badge  = document.getElementById(`serialBadge_${idx}`);
        const countEl= document.getElementById(`serialCount_${idx}`);
        if (!ta || ta.disabled) { updateTotals(); return; }

        const qty      = parseInt(qtyEl ? qtyEl.value : 1) || 1;
        const raw      = ta.value.trim();
        const serials  = raw ? raw.split(/[\r\n,]+/).map(s => s.trim()).filter(s => s.length > 0) : [];
        const entered  = serials.length;
        const unique   = new Set(serials);

        if (countEl) countEl.textContent = `Entered: ${entered}`;

        if (unique.size !== serials.length) {
            badge.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>Duplicate serials on this line!</span>';
            ta.classList.add('is-invalid');
        } else if (entered === qty) {
            badge.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Exact match (${entered}/${qty})</span>`;
            ta.classList.remove('is-invalid');
        } else if (entered < qty) {
            badge.innerHTML = `<span class="text-warning"><i class="bi bi-exclamation-circle me-1"></i>Needs ${qty - entered} more serial(s) (${entered}/${qty})</span>`;
            ta.classList.remove('is-invalid');
        } else {
            badge.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Exceeds required by ${entered - qty}</span>`;
            ta.classList.add('is-invalid');
        }
        updateTotals();
    };

    /* ── Remove Row ──────────────────────────────────────── */
    window.removeRow = function (rowId) {
        const r = document.getElementById(rowId);
        if (r) { r.remove(); renumber(); updateTotals(); }
    };

    /* ── Renumber ────────────────────────────────────────── */
    function renumber() {
        document.querySelectorAll('#equipmentItemsBody tr').forEach((r, i) => {
            const c = r.querySelector('.row-num');
            if (c) c.textContent = i + 1;
        });
    }

    /* ── Totals ──────────────────────────────────────────── */
    function updateTotals() {
        let total = 0, serCount = 0;
        document.querySelectorAll('.qty-input').forEach(el => { total += parseInt(el.value) || 0; });
        document.querySelectorAll('.serial-textarea').forEach(ta => {
            if (!ta.disabled) {
                const raw = ta.value.trim();
                serCount += raw ? raw.split(/[\r\n,]+/).map(s => s.trim()).filter(s => s.length > 0).length : 0;
            }
        });
        const te = document.getElementById('totalQtySummary');
        if (te) te.textContent = total;
        const se = document.getElementById('serializedSummary');
        if (se) se.textContent = `${serCount} serialized unit(s) specified`;
    }

    /* ── Escape HTML ─────────────────────────────────────── */
    function escHtml(t) {
        return String(t || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    window.escapeHtml = escHtml;

    /* ── Form Submit Validation ──────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        addEquipmentRow();

        const form = document.getElementById('stockInForm');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            const rows = document.querySelectorAll('#equipmentItemsBody tr');
            if (!rows.length) {
                e.preventDefault();
                alert('Please add at least one equipment item line to record stock in.');
                return;
            }

            let mismatch = false;
            const allSerials = [];
            let dupe = null;

            document.querySelectorAll('.serial-textarea').forEach(ta => {
                if (ta.disabled) return;
                const row  = ta.closest('tr');
                const qty  = parseInt(row ? (row.querySelector('.qty-input') || {value:'1'}).value : 1) || 1;
                const raw  = ta.value.trim();
                const srs  = raw ? raw.split(/[\r\n,]+/).map(s => s.trim()).filter(s => s.length > 0) : [];

                if (srs.length !== qty) { mismatch = true; ta.classList.add('is-invalid'); }
                srs.forEach(s => {
                    const low = s.toLowerCase();
                    if (allSerials.includes(low)) dupe = s;
                    allSerials.push(low);
                });
            });

            if (mismatch) {
                e.preventDefault();
                alert('Serial number count must exactly match the quantity for all serialized items.');
                return;
            }
            if (dupe) {
                e.preventDefault();
                alert(`Duplicate serial number "${dupe}" found across lines. All serial numbers must be unique.`);
            }
        });
    });
}());
</script>
