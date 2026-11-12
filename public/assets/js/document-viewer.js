/**
 * SYB BMS — In-App Interactive Document Viewer
 * Supports interactive Excel & CSV spreadsheets (SheetJS), Word (.docx via Mammoth),
 * PDF embedding, and high-res image zoom.
 * Provides both In-Page Modal Viewer and Full-Page Canvas Viewer.
 * Strict CSP Compliant: No inline event handlers or inline scripts.
 */
(() => {
    'use strict';

    // 1. Initialize Standalone Page Viewer if present
    const standaloneEl = document.querySelector('[data-document-viewer]');
    if (standaloneEl) {
        initStandaloneViewer(standaloneEl);
    }

    // 2. Initialize In-System Modal Viewer Triggers across the page
    initModalViewerTriggers();

    /**
     * Standalone Page Viewer Initialization
     */
    function initStandaloneViewer(el) {
        const category = el.getAttribute('data-category') || '';
        const rawUrl = el.getAttribute('data-raw-url') || '';
        if (!rawUrl) return;

        if (category === 'excel') {
            renderExcelWorkbook(
                rawUrl,
                document.getElementById('excel-loading-spinner'),
                document.getElementById('excel-error-message'),
                document.getElementById('excel-table-scroll'),
                document.getElementById('excel-render-table'),
                document.getElementById('excel-sheet-tabs'),
                document.getElementById('excel-search-input'),
                document.getElementById('excel-row-count'),
                document.getElementById('excel-cell-name'),
                document.getElementById('excel-formula-val'),
                document.getElementById('excel-sheet-active-badge'),
                el.getAttribute('data-download-url')
            );
        } else if (category === 'word') {
            renderWordDocument(
                rawUrl,
                document.getElementById('word-loading-spinner'),
                document.getElementById('word-error-message'),
                document.getElementById('word-content-area'),
                el.getAttribute('data-download-url')
            );
        } else if (category === 'image') {
            initZoomImage(document.getElementById('bms-preview-image'));
        }
    }

    /**
     * In-System Modal Viewer Initialization & Click Handling
     */
    function initModalViewerTriggers() {
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('.bms-doc-view-trigger');
            if (!trigger) return;

            // Allow Ctrl/Cmd/Shift+Click to open in new tab natively if requested
            if (e.metaKey || e.ctrlKey || e.shiftKey) {
                return;
            }

            const rawUrl = trigger.getAttribute('data-raw-url');
            const viewUrl = trigger.getAttribute('data-view-url');
            const downloadUrl = trigger.getAttribute('data-download-url');
            const filename = trigger.getAttribute('data-filename') || 'Document';
            const docType = trigger.getAttribute('data-doc-type') || 'Document';
            const version = trigger.getAttribute('data-version') || 'v1';
            const size = trigger.getAttribute('data-size') || '';

            if (!rawUrl) return;

            e.preventDefault();

            const modalEl = document.getElementById('bmsDocPreviewModal');
            if (!modalEl) {
                // If modal is not in current DOM, navigate to viewUrl
                window.location.href = viewUrl;
                return;
            }

            // Update modal header information
            const titleEl = document.getElementById('bmsDocPreviewModalTitle');
            const typeBadge = document.getElementById('bmsDocModalType');
            const verBadge = document.getElementById('bmsDocModalVersion');
            const sizeBadge = document.getElementById('bmsDocModalSize');
            const dlBtn = document.getElementById('bmsDocModalDownloadBtn');
            const fullBtn = document.getElementById('bmsDocModalFullPageBtn');
            const modalBody = document.getElementById('bmsDocModalBody');

            if (titleEl) titleEl.textContent = filename;
            if (typeBadge) typeBadge.textContent = docType;
            if (verBadge) verBadge.textContent = version;
            if (sizeBadge) sizeBadge.textContent = size ? '(' + size + ')' : '';
            if (dlBtn) dlBtn.href = downloadUrl || rawUrl;
            if (fullBtn) fullBtn.href = viewUrl || rawUrl;

            // Detect category from filename extension
            const ext = getExtension(filename);
            const category = getCategory(ext);

            // Clear modal body and render appropriate viewer
            modalBody.innerHTML = '';

            if (category === 'excel') {
                renderModalExcel(modalBody, rawUrl, downloadUrl, filename);
            } else if (category === 'pdf') {
                renderModalPdf(modalBody, rawUrl, filename);
            } else if (category === 'image') {
                renderModalImage(modalBody, rawUrl, filename);
            } else if (category === 'word') {
                renderModalWord(modalBody, rawUrl, downloadUrl);
            } else if (category === 'text') {
                renderModalText(modalBody, rawUrl);
            } else {
                renderModalFallback(modalBody, filename, downloadUrl, ext);
            }

            if (window.bootstrap && window.bootstrap.Modal) {
                const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }
        });
    }

    /**
     * Modal Renderers
     */
    function renderModalPdf(container, url, filename) {
        const frame = document.createElement('iframe');
        frame.src = url + '#toolbar=1&navpanes=1';
        frame.className = 'w-100 flex-grow-1 border-0 rounded';
        frame.style.minHeight = '75vh';
        frame.title = 'PDF Viewer — ' + filename;
        container.appendChild(frame);
    }

    function renderModalImage(container, url, filename) {
        const wrap = document.createElement('div');
        wrap.className = 'text-center p-3 bg-light rounded border flex-grow-1 d-flex align-items-center justify-content-center';
        wrap.style.minHeight = '70vh';
        wrap.style.overflow = 'auto';

        const img = document.createElement('img');
        img.src = url;
        img.alt = filename;
        img.className = 'img-fluid rounded shadow-sm';
        img.style.maxHeight = '72vh';
        img.style.objectFit = 'contain';
        img.style.cursor = 'zoom-in';

        initZoomImage(img);
        wrap.appendChild(img);
        container.appendChild(wrap);
    }

    function renderModalText(container, url) {
        const frame = document.createElement('iframe');
        frame.src = url;
        frame.className = 'w-100 flex-grow-1 border rounded bg-white';
        frame.style.minHeight = '72vh';
        container.appendChild(frame);
    }

    function renderModalFallback(container, filename, downloadUrl, ext) {
        container.innerHTML =
            '<div class="text-center py-5 my-auto">' +
                '<h5 class="fw-bold text-dark mb-2">Preview Not Supported for .' + escapeHtml(ext) + '</h5>' +
                '<p class="text-secondary small mb-3">This file cannot be rendered directly in the browser canvas.</p>' +
                '<a href="' + escapeHtml(downloadUrl) + '" class="btn btn-primary btn-sm">' +
                    'Download ' + escapeHtml(filename) +
                '</a>' +
            '</div>';
    }

    function renderModalExcel(container, rawUrl, downloadUrl, filename) {
        container.innerHTML =
            '<div class="bms-excel-frame w-100 flex-grow-1">' +
                '<div class="bms-excel-titlebar">' +
                    '<div class="bms-excel-brand">' +
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="17"></line><line x1="16" y1="13" x2="8" y2="17"></line></svg>' +
                        '<span>Microsoft Excel / CSV Workbook Viewer</span>' +
                    '</div>' +
                    '<span class="bms-excel-badge" id="modal-excel-sheet-badge">Sheet1</span>' +
                '</div>' +
                '<div class="bms-excel-toolbar">' +
                    '<div class="bms-excel-formulabar-wrap">' +
                        '<span class="bms-excel-name-box" id="modal-excel-cell-name">A1</span>' +
                        '<span class="bms-excel-fx-label">fx</span>' +
                        '<input type="text" id="modal-excel-formula-val" class="bms-excel-formula-input" readonly placeholder="Select a cell to view contents...">' +
                    '</div>' +
                    '<div class="d-flex align-items-center gap-2">' +
                        '<input type="text" id="modal-excel-search" class="form-control form-control-sm" placeholder="Filter cells..." style="max-width: 200px;">' +
                        '<span id="modal-excel-count" class="badge badge-soft-secondary small"></span>' +
                    '</div>' +
                '</div>' +
                '<div id="modal-excel-loading" class="text-center py-5 my-auto">' +
                    '<div class="spinner-border text-success" role="status"><span class="visually-hidden">Loading...</span></div>' +
                    '<div class="mt-2 text-secondary fw-medium">Loading and rendering spreadsheet data...</div>' +
                '</div>' +
                '<div id="modal-excel-error" class="alert alert-warning m-3 d-none" role="alert">' +
                    '<strong>Preview Notice:</strong> Unable to parse spreadsheet. ' +
                    '<a href="' + escapeHtml(downloadUrl) + '" class="alert-link">Download file directly</a>.' +
                '</div>' +
                '<div id="modal-excel-scroll" class="bms-excel-grid-scroll d-none">' +
                    '<table id="modal-excel-table" class="bms-excel-grid-table"></table>' +
                '</div>' +
                '<div class="bms-excel-tabs-bar" id="modal-excel-tabs"></div>' +
            '</div>';

        renderExcelWorkbook(
            rawUrl,
            document.getElementById('modal-excel-loading'),
            document.getElementById('modal-excel-error'),
            document.getElementById('modal-excel-scroll'),
            document.getElementById('modal-excel-table'),
            document.getElementById('modal-excel-tabs'),
            document.getElementById('modal-excel-search'),
            document.getElementById('modal-excel-count'),
            document.getElementById('modal-excel-cell-name'),
            document.getElementById('modal-excel-formula-val'),
            document.getElementById('modal-excel-sheet-badge'),
            downloadUrl
        );
    }

    function renderModalWord(container, rawUrl, downloadUrl) {
        container.innerHTML =
            '<div id="modal-word-loading" class="text-center py-5">' +
                '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>' +
                '<div class="mt-2 text-secondary fw-medium">Parsing and formatting Word document...</div>' +
            '</div>' +
            '<div id="modal-word-error" class="alert alert-warning d-none" role="alert">' +
                '<strong>Preview Notice:</strong> Unable to render Word document. ' +
                '<a href="' + escapeHtml(downloadUrl) + '" class="alert-link">Download file directly</a>.' +
            '</div>' +
            '<div id="modal-word-content" class="bg-white p-4 border rounded shadow-sm d-none flex-grow-1" style="max-height: 72vh; overflow-y: auto; line-height: 1.7;"></div>';

        renderWordDocument(
            rawUrl,
            document.getElementById('modal-word-loading'),
            document.getElementById('modal-word-error'),
            document.getElementById('modal-word-content'),
            downloadUrl
        );
    }

    /**
     * Core SheetJS Excel & CSV Rendering Logic
     * Renders an authentic Microsoft Excel grid with A, B, C... column headers,
     * 1, 2, 3... row numbers, cell selection, and formula bar display.
     */
    function renderExcelWorkbook(
        url,
        loadingEl,
        errorEl,
        scrollEl,
        tableEl,
        tabsEl,
        searchInput,
        countBadge,
        cellNameEl,
        formulaValEl,
        sheetBadgeEl,
        downloadUrl
    ) {
        let workbook = null;
        let activeSheetName = '';

        fetch(url, { credentials: 'same-origin' })
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.arrayBuffer();
            })
            .then(buffer => {
                ensureXlsxLoaded(() => {
                    try {
                        const u8 = new Uint8Array(buffer);
                        // Try reading array buffer first (xlsx / xls / binary)
                        try {
                            workbook = window.XLSX.read(u8, {
                                type: 'array',
                                cellDates: true
                            });
                        } catch (readArrErr) {
                            // If binary array fails (e.g. plain CSV text with UTF-8 BOM/chars), decode to string
                            const text = new TextDecoder('utf-8').decode(u8);
                            workbook = window.XLSX.read(text, {
                                type: 'string',
                                cellDates: true
                            });
                        }

                        if (!workbook || !workbook.SheetNames || workbook.SheetNames.length === 0) {
                            throw new Error('Workbook contains no worksheets or data.');
                        }

                        renderTabs(workbook.SheetNames);
                        loadSheet(workbook.SheetNames[0]);
                    } catch (err) {
                        showError(err.message || 'Error processing spreadsheet file');
                    }
                });
            })
            .catch(err => {
                showError(err.message || 'Error fetching spreadsheet file');
            });

        function ensureXlsxLoaded(cb) {
            if (typeof window.XLSX !== 'undefined') {
                cb();
                return;
            }
            let attempts = 0;
            const timer = setInterval(() => {
                attempts++;
                if (typeof window.XLSX !== 'undefined') {
                    clearInterval(timer);
                    cb();
                } else if (attempts > 30) {
                    clearInterval(timer);
                    showError('Spreadsheet rendering library could not be loaded.');
                }
            }, 100);
        }

        function renderTabs(names) {
            if (!tabsEl) return;
            tabsEl.innerHTML = '';
            names.forEach((name, idx) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'bms-excel-tab-btn ' + (idx === 0 ? 'is-active' : '');
                btn.textContent = name;
                btn.addEventListener('click', () => {
                    if (activeSheetName !== name) {
                        tabsEl.querySelectorAll('.bms-excel-tab-btn').forEach(b => {
                            b.classList.remove('is-active');
                        });
                        btn.classList.add('is-active');
                        loadSheet(name);
                    }
                });
                tabsEl.appendChild(btn);
            });
        }

        function loadSheet(name) {
            activeSheetName = name;
            if (sheetBadgeEl) sheetBadgeEl.textContent = name;

            const sheet = workbook.Sheets[name];
            if (!sheet) return;

            // Convert worksheet to raw array-of-arrays
            const rows = window.XLSX.utils.sheet_to_json(sheet, {
                header: 1,
                defval: '',
                blankrows: false
            });

            if (!rows || rows.length === 0) {
                if (tableEl) {
                    tableEl.innerHTML = '<tbody><tr><td class="text-center py-5 text-secondary fst-italic">This worksheet contains no data.</td></tr></tbody>';
                }
                if (countBadge) countBadge.textContent = '0 rows';
                if (loadingEl) loadingEl.classList.add('d-none');
                if (scrollEl) scrollEl.classList.remove('d-none');
                return;
            }

            // Determine maximum column count across all rows
            let maxCols = 0;
            for (let i = 0; i < rows.length; i++) {
                if (rows[i] && rows[i].length > maxCols) {
                    maxCols = rows[i].length;
                }
            }
            if (sheet['!ref']) {
                try {
                    const decodedRange = window.XLSX.utils.decode_range(sheet['!ref']);
                    if (decodedRange.e.c + 1 > maxCols) {
                        maxCols = decodedRange.e.c + 1;
                    }
                } catch (e) {
                    // Ignore range decode errors
                }
            }
            maxCols = Math.max(1, maxCols);

            // Build authentic Excel Grid
            let html = '';

            // Top Header: Corner cell (#) + Column Letters (A, B, C...)
            html += '<thead><tr>';
            html += '<th class="bms-excel-corner" title="Select All">&#9699;</th>';
            for (let c = 0; c < maxCols; c++) {
                const colLetter = getColumnLetter(c);
                html += '<th class="bms-excel-col-header" data-col="' + c + '" id="excel-col-hdr-' + c + '">' + colLetter + '</th>';
            }
            html += '</tr></thead><tbody>';

            // Data Rows: 1, 2, 3...
            for (let r = 0; r < rows.length; r++) {
                const rowNum = r + 1;
                const rowData = rows[r] || [];
                html += '<tr class="bms-excel-grid-row" data-row-idx="' + r + '">';
                html += '<th class="bms-excel-row-header" data-row-num="' + rowNum + '" id="excel-row-hdr-' + r + '">' + rowNum + '</th>';

                for (let c = 0; c < maxCols; c++) {
                    const rawVal = rowData[c] !== undefined && rowData[c] !== null ? String(rowData[c]) : '';
                    const coord = getColumnLetter(c) + rowNum;
                    const isDefaultA1 = (r === 0 && c === 0);

                    html += '<td class="bms-excel-cell' + (isDefaultA1 ? ' is-selected' : '') + '"' +
                        ' data-coord="' + coord + '"' +
                        ' data-r="' + r + '"' +
                        ' data-c="' + c + '"' +
                        ' data-val="' + escapeHtml(rawVal) + '"' +
                        ' title="' + coord + '">' +
                        escapeHtml(rawVal) +
                        '</td>';
                }
                html += '</tr>';
            }
            html += '</tbody>';

            if (tableEl) {
                tableEl.innerHTML = html;
            }

            if (countBadge) {
                countBadge.textContent = rows.length + ' rows \u00D7 ' + maxCols + ' cols';
            }

            // Set default formula bar to A1 cell content
            const firstVal = (rows[0] && rows[0][0] !== undefined && rows[0][0] !== null) ? String(rows[0][0]) : '';
            if (cellNameEl) cellNameEl.textContent = 'A1';
            if (formulaValEl) formulaValEl.value = firstVal;
            highlightHeaders(0, 0);

            if (loadingEl) loadingEl.classList.add('d-none');
            if (errorEl) errorEl.classList.add('d-none');
            if (scrollEl) scrollEl.classList.remove('d-none');

            if (searchInput) searchInput.value = '';

            // Attach Cell Click Handler for Selection & Formula Bar inspection
            attachCellSelectionHandlers();
        }

        function attachCellSelectionHandlers() {
            if (!tableEl) return;
            const cells = tableEl.querySelectorAll('td.bms-excel-cell');
            cells.forEach(cell => {
                cell.addEventListener('click', () => {
                    const coord = cell.getAttribute('data-coord') || 'A1';
                    const val = cell.getAttribute('data-val') || '';
                    const r = parseInt(cell.getAttribute('data-r') || '0', 10);
                    const c = parseInt(cell.getAttribute('data-c') || '0', 10);

                    tableEl.querySelectorAll('td.bms-excel-cell.is-selected').forEach(el => {
                        el.classList.remove('is-selected');
                    });
                    cell.classList.add('is-selected');

                    if (cellNameEl) cellNameEl.textContent = coord;
                    if (formulaValEl) formulaValEl.value = val;

                    highlightHeaders(r, c);
                });
            });
        }

        function highlightHeaders(r, c) {
            if (!tableEl) return;
            tableEl.querySelectorAll('.bms-excel-col-header.is-highlighted').forEach(el => el.classList.remove('is-highlighted'));
            tableEl.querySelectorAll('.bms-excel-row-header.is-highlighted').forEach(el => el.classList.remove('is-highlighted'));

            const colHdr = tableEl.querySelector('#excel-col-hdr-' + c);
            if (colHdr) colHdr.classList.add('is-highlighted');

            const rowHdr = tableEl.querySelector('#excel-row-hdr-' + r);
            if (rowHdr) rowHdr.classList.add('is-highlighted');
        }

        function getColumnLetter(colIndex) {
            if (window.XLSX && window.XLSX.utils && window.XLSX.utils.encode_col) {
                return window.XLSX.utils.encode_col(colIndex);
            }
            let letter = '';
            let temp = colIndex;
            while (temp >= 0) {
                letter = String.fromCharCode((temp % 26) + 65) + letter;
                temp = Math.floor(temp / 26) - 1;
            }
            return letter;
        }

        // Live Spreadsheet Search / Filter
        if (searchInput && tableEl) {
            searchInput.addEventListener('input', () => {
                const filter = searchInput.value.trim().toLowerCase();
                const trList = tableEl.querySelectorAll('tbody tr.bms-excel-grid-row');
                let count = 0;

                trList.forEach(tr => {
                    if (!filter) {
                        tr.style.display = '';
                        count++;
                    } else {
                        const txt = tr.textContent.toLowerCase();
                        if (txt.includes(filter)) {
                            tr.style.display = '';
                            count++;
                        } else {
                            tr.style.display = 'none';
                        }
                    }
                });

                if (countBadge) {
                    countBadge.textContent = filter ? (count + ' matching rows') : (trList.length + ' rows');
                }
            });
        }

        function showError(msg) {
            if (loadingEl) loadingEl.classList.add('d-none');
            if (scrollEl) scrollEl.classList.add('d-none');
            if (errorEl) {
                errorEl.classList.remove('d-none');
                errorEl.innerHTML = '<strong>Preview Notice:</strong> ' + escapeHtml(msg) +
                    (downloadUrl ? '. <a href="' + escapeHtml(downloadUrl) + '" class="alert-link">Download file directly</a>.' : '');
            }
        }
    }

    /**
     * Core Mammoth Word Rendering Logic
     */
    function renderWordDocument(url, loadingEl, errorEl, contentEl, downloadUrl) {
        fetch(url, { credentials: 'same-origin' })
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.arrayBuffer();
            })
            .then(buffer => {
                ensureMammothLoaded(() => {
                    window.mammoth.convertToHtml({ arrayBuffer: buffer })
                        .then(result => {
                            if (loadingEl) loadingEl.classList.add('d-none');
                            if (contentEl) {
                                contentEl.innerHTML = result.value || '<p class="text-secondary fst-italic">This document contains no text.</p>';
                                contentEl.classList.remove('d-none');
                            }
                        })
                        .catch(err => {
                            showWordError(err.message);
                        });
                });
            })
            .catch(err => {
                showWordError(err.message);
            });

        function ensureMammothLoaded(cb) {
            if (typeof window.mammoth !== 'undefined') {
                cb();
                return;
            }
            let attempts = 0;
            const timer = setInterval(() => {
                attempts++;
                if (typeof window.mammoth !== 'undefined') {
                    clearInterval(timer);
                    cb();
                } else if (attempts > 30) {
                    clearInterval(timer);
                    showWordError('Mammoth library could not be loaded.');
                }
            }, 100);
        }

        function showWordError(msg) {
            if (loadingEl) loadingEl.classList.add('d-none');
            if (errorEl) {
                errorEl.classList.remove('d-none');
                errorEl.innerHTML = '<strong>Preview Notice:</strong> Unable to render Word document. ' +
                    (downloadUrl ? '<a href="' + escapeHtml(downloadUrl) + '" class="alert-link">Download file directly</a>.' : '');
            }
        }
    }

    /**
     * Image Zoom Toggle
     */
    function initZoomImage(img) {
        if (!img) return;
        let isZoomed = false;
        img.addEventListener('click', () => {
            isZoomed = !isZoomed;
            if (isZoomed) {
                img.style.maxHeight = 'none';
                img.style.cursor = 'zoom-out';
            } else {
                img.style.maxHeight = '72vh';
                img.style.cursor = 'zoom-in';
            }
        });
    }

    function getExtension(filename) {
        if (!filename) return '';
        const cleanName = filename.split('?')[0].split('#')[0];
        const parts = cleanName.split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }

    function getCategory(ext) {
        if (['xlsx', 'xls', 'csv'].includes(ext)) return 'excel';
        if (ext === 'pdf') return 'pdf';
        if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext)) return 'image';
        if (['docx', 'doc'].includes(ext)) return 'word';
        if (['txt', 'json', 'log'].includes(ext)) return 'text';
        return 'other';
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
