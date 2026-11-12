// Dynamic line-item rows for the Purchase Request and Actual BOM forms on the project detail page.
// Kept as an external file so it complies with the Content Security Policy (no inline scripts).
document.addEventListener('DOMContentLoaded', function () {
    // Dynamic PR row addition
    var addPrRowBtn = document.getElementById('bmsAddPrRowBtn');
    if (addPrRowBtn) {
        addPrRowBtn.addEventListener('click', function () {
            var tbody = document.getElementById('bmsPrItemsTableBody');
            if (!tbody) return;
            var rowCount = tbody.children.length + 1;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td class="text-center font-monospace small">' + rowCount + '</td>'
                + '<td><input type="text" name="item_description[]" class="form-control form-control-sm" placeholder="e.g. Solar Inverter 50kW" required></td>'
                + '<td><input type="text" name="model_part_number[]" class="form-control form-control-sm font-monospace" placeholder="e.g. SUN2000-50KTL"></td>'
                + '<td><textarea name="item_specification[]" rows="2" class="form-control form-control-sm" placeholder="Detailed technical specs..." required></textarea></td>'
                + '<td><input type="number" step="0.01" min="0.01" name="quantity[]" class="form-control form-control-sm" value="1.00" required></td>'
                + '<td><input type="text" name="unit_of_measure[]" class="form-control form-control-sm" value="pcs" required></td>'
                + '<td><input type="text" name="item_purpose[]" class="form-control form-control-sm" placeholder="Item purpose"></td>'
                + '<td><input type="text" name="item_remarks[]" class="form-control form-control-sm" placeholder="Optional note"></td>'
                + '<td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm p-1 bms-remove-row" title="Remove line item">&times;</button></td>';
            tbody.appendChild(tr);
        });
    }

    // Dynamic Actual BOM row addition
    var addAbRowBtn = document.getElementById('bmsAddAbRowBtn');
    if (addAbRowBtn) {
        addAbRowBtn.addEventListener('click', function () {
            var tbody = document.getElementById('bmsAbItemsTableBody');
            if (!tbody) return;
            var rowCount = tbody.children.length + 1;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td class="text-center font-monospace small">' + rowCount + '</td>'
                + '<td><input type="text" name="ab_item_description[]" class="form-control form-control-sm" placeholder="Item description" required><input type="hidden" name="ab_pr_item_id[]" value=""></td>'
                + '<td><textarea name="ab_actual_specification[]" rows="2" class="form-control form-control-sm" placeholder="Actual equipment/material specifications..." required></textarea></td>'
                + '<td><input type="number" step="0.01" min="0.01" name="ab_quantity[]" class="form-control form-control-sm" value="1.00" required></td>'
                + '<td><input type="text" name="ab_unit_of_measure[]" class="form-control form-control-sm" value="pcs" required></td>'
                + '<td><input type="text" name="ab_item_remarks[]" class="form-control form-control-sm" placeholder="Optional note"></td>'
                + '<td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm p-1 bms-remove-row" title="Remove line item">&times;</button></td>';
            tbody.appendChild(tr);
        });
    }

    // Remove row delegation for both PR and Actual BOM tables
    document.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('bms-remove-row')) {
            var tr = e.target.closest('tr');
            var tbody = tr ? tr.parentNode : null;
            if (tbody) {
                if (tbody.children.length > 1) {
                    tr.remove();
                    Array.from(tbody.children).forEach(function (row, idx) {
                        var firstCol = row.querySelector('td:first-child');
                        if (firstCol) firstCol.textContent = idx + 1;
                    });
                } else {
                    alert('At least one item is required.');
                }
            }
        }
    });
});
