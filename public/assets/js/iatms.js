/**
 * PAGECOM IATMS — CSP-Compliant Client-Side Interactivity
 * Zero inline event handlers; binds strictly via DOMContentLoaded & data-* attributes.
 */
document.addEventListener('DOMContentLoaded', function () {
    // 1. STOCK IN (FORM 1): Dual-Mode Toggle (Mode A Direct vs Mode B System PO)
    const modeRadios = document.querySelectorAll('input[name="receiving_mode"]');
    const poSelectorWrap = document.getElementById('iatmsPoSelectorWrap');
    const poSelect = document.getElementById('iatmsPoSelect');
    const poNumInput = document.getElementById('iatmsPoNumberInput');
    const poDateInput = document.getElementById('iatmsPoDateInput');
    const supplierSelect = document.getElementById('iatmsSupplierSelect');

    function syncStockInMode() {
        const active = document.querySelector('input[name="receiving_mode"]:checked');
        if (!active || !poSelectorWrap) return;
        const isPo = active.value === 'po';
        poSelectorWrap.classList.toggle('d-none', !isPo);
        if (!isPo && poSelect) {
            poSelect.value = '';
        }
    }

    modeRadios.forEach(function (r) {
        r.addEventListener('change', syncStockInMode);
    });
    syncStockInMode();

    // Load System PO items when PO is selected in Mode B
    if (poSelect) {
        poSelect.addEventListener('change', function () {
            const poId = poSelect.value;
            const endpointBase = poSelect.getAttribute('data-po-items-url') || '';
            if (!poId || !endpointBase) return;

            fetch(endpointBase + '/' + encodeURIComponent(poId), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.po) {
                        if (poNumInput) poNumInput.value = data.po.po_number || '';
                        if (poDateInput) poDateInput.value = data.po.po_date || '';
                        if (supplierSelect && data.po.supplier_id) supplierSelect.value = String(data.po.supplier_id);
                    }
                    const tbody = document.getElementById('iatmsStockInLinesBody');
                    const template = document.getElementById('iatmsStockInLineTemplate');
                    if (tbody && template && Array.isArray(data.items) && data.items.length > 0) {
                        tbody.innerHTML = '';
                        data.items.forEach(function (item) {
                            const clone = template.content.cloneNode(true);
                            const tr = clone.querySelector('tr');
                            const itemSel = tr.querySelector('.iatms-line-item');
                            const poItemInp = tr.querySelector('.iatms-line-po-item');
                            const qtyInp = tr.querySelector('.iatms-line-qty');
                            const costInp = tr.querySelector('.iatms-line-cost');
                            const warInp = tr.querySelector('.iatms-line-warranty');

                            if (itemSel && item.matched_item_id) itemSel.value = String(item.matched_item_id);
                            if (poItemInp) poItemInp.value = String(item.id || '');
                            if (qtyInp) {
                                const outQty = Math.max(1, Math.floor(parseFloat(item.outstanding_quantity || '1')));
                                qtyInp.value = String(outQty);
                                qtyInp.max = String(outQty);
                            }
                            if (costInp) costInp.value = String(item.unit_price || '0.00');
                            if (warInp && item.default_warranty_months) warInp.value = String(item.default_warranty_months);

                            tbody.appendChild(clone);
                        });
                    }
                })
                .catch(function (err) {
                    console.error('Failed to load PO items:', err);
                });
        });
    }

    // Dynamic Add/Remove Row for Stock In & Invoices
    document.body.addEventListener('click', function (e) {
        const addBtn = e.target.closest('[data-add-row-target]');
        if (addBtn) {
            const tbodyId = addBtn.getAttribute('data-add-row-target');
            const tplId = addBtn.getAttribute('data-add-row-template');
            const tbody = document.getElementById(tbodyId);
            const tpl = document.getElementById(tplId);
            if (tbody && tpl) {
                tbody.appendChild(tpl.content.cloneNode(true));
            }
            return;
        }

        const removeBtn = e.target.closest('[data-remove-row]');
        if (removeBtn) {
            const tr = removeBtn.closest('tr');
            if (tr && tr.parentElement && tr.parentElement.children.length > 1) {
                tr.remove();
            }
        }
    });

    // Live Serial Count Indicator on Stock In Form 1
    document.body.addEventListener('input', function (e) {
        if (e.target.classList.contains('iatms-line-serials') || e.target.classList.contains('iatms-line-qty')) {
            const tr = e.target.closest('tr');
            if (!tr) return;
            const qtyEl = tr.querySelector('.iatms-line-qty');
            const serEl = tr.querySelector('.iatms-line-serials');
            const badge = tr.querySelector('.iatms-serial-count-badge');
            if (!qtyEl || !serEl || !badge) return;

            const qty = parseInt(qtyEl.value || '0', 10);
            const serials = serEl.value.split(/[\r\n,]+/).map(function (s) { return s.trim(); }).filter(Boolean);
            badge.textContent = serials.length + ' / ' + qty + ' serials';
            badge.classList.toggle('bg-success', serials.length === qty && qty > 0);
            badge.classList.toggle('bg-warning', serials.length !== qty);
            badge.classList.toggle('text-dark', serials.length !== qty);
        }
    });

    // 2. STOCK OUT (FORM 2): Live Search Filter for Available Serials & Client Shipping Address Auto-Fill
    const serialFilter = document.getElementById('iatmsSerialPickerSearch');
    const selectedCountBadge = document.getElementById('iatmsSelectedSerialCount');
    if (serialFilter) {
        serialFilter.addEventListener('input', function () {
            const term = serialFilter.value.trim().toLowerCase();
            document.querySelectorAll('.iatms-serial-row').forEach(function (row) {
                const hay = (row.getAttribute('data-search') || '').toLowerCase();
                row.classList.toggle('d-none', term !== '' && !hay.includes(term));
            });
        });
    }

    document.body.addEventListener('change', function (e) {
        if (e.target.classList.contains('iatms-serial-checkbox') && selectedCountBadge) {
            const count = document.querySelectorAll('.iatms-serial-checkbox:checked').length;
            selectedCountBadge.textContent = count + ' selected';
        }
    });

    const clientSelect = document.getElementById('iatmsClientSelect');
    const shippingAddr = document.getElementById('iatmsShippingAddress');
    const companyInput = document.getElementById('iatmsCompanyNameInput');
    if (clientSelect) {
        clientSelect.addEventListener('change', function () {
            const opt = clientSelect.options[clientSelect.selectedIndex];
            if (!opt) return;
            const addr = opt.getAttribute('data-address') || '';
            if (shippingAddr && addr && shippingAddr.value.trim() === '') {
                shippingAddr.value = addr;
            }
            if (companyInput) {
                companyInput.disabled = clientSelect.value !== '';
                if (clientSelect.value !== '') companyInput.value = '';
            }
        });
    }

    // 3. DELIVERY STATUS: Toggle Delivery Confirmation Fields when status = 'delivered'
    const delStatusSelect = document.getElementById('iatmsDeliveryStatusSelect');
    const delConfirmFields = document.getElementById('iatmsDeliveryConfirmFields');
    if (delStatusSelect && delConfirmFields) {
        const syncConfirm = function () {
            delConfirmFields.classList.toggle('d-none', delStatusSelect.value !== 'delivered');
        };
        delStatusSelect.addEventListener('change', syncConfirm);
        syncConfirm();
    }

    // 4. PRINT PREVIEW BUTTON
    const printBtn = document.getElementById('iatmsPrintBtn');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.print();
        });
    }
});
