/**
 * SYB BMS — In-App Interactive Document Viewer
 * Supports interactive Excel spreadsheets (SheetJS), Word (.docx via Mammoth),
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

            // Only intercept primary clicks without modifiers (allow Ctrl/Cmd+Click to open in new tab if user wishes)
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
                // If modal not on page, navigate directly to viewUrl
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

            // Detect category
            const ext = getExtension(filename);
            const category = getCategory(ext);

            // Clear modal body and render appropriate viewer
            modalBody.innerHTML = '';

            if (category === 'excel') {
                renderModalExcel(modalBody, rawUrl, downloadUrl);
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

    function renderModalExcel(container, rawUrl, downloadUrl) {
        container.innerHTML =
            '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-2 bg-light rounded border mb-2">' +
                '<div class="d-flex align-items-center gap-2 flex-wrap">' +
                    '<span class="small fw-bold text-secondary text-uppercase tracking-wider">Sheets:</span>' +
                    '<div id="modal-excel-tabs" class="btn-group btn-group-sm flex-wrap" role="group"></div>' +
                '</div>' +
                '<div class="d-flex align-items-center gap-2">' +
                    '<input type="text" id="modal-excel-search" class="form-control form-control-sm" placeholder="Filter sheet data..." style="max-width: 250px;">' +
                    '<span id="modal-excel-count" class="badge badge-soft-secondary small"></span>' +
                '</div>' +
            '</div>' +
            '<div id="modal-excel-loading" class="text-center py-5">' +
                '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>' +
                '<div class="mt-2 text-secondary fw-medium">Loading and rendering spreadsheet data...</div>' +
            '</div>' +
            '<div id="modal-excel-error" class="alert alert-warning d-none" role="alert">' +
                '<strong>Preview Notice:</strong> Unable to parse spreadsheet. ' +
                '<a href="' + escapeHtml(downloadUrl) + '" class="alert-link">Download file directly</a>.' +
            '</div>' +
            '<div id="modal-excel-scroll" class="table-responsive bg-white border rounded shadow-sm d-none flex-grow-1" style="max-height: 70vh; overflow: auto;">' +
                '<table id="modal-excel-table" class="table table-sm table-bordered table-hover table-striped align-middle mb-0"></table>' +
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
     * Core SheetJS Excel Rendering Logic
     */
    function renderExcelWorkbook(url, loadingEl, errorEl, scrollEl, tableEl, tabsEl, searchInput, countBadge, downloadUrl) {
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
                        workbook = window.XLSX.read(new Uint8Array(buffer), {
                            type: 'array',
                            cellDates: true,
                            dense: true
                        });

                        if (!workbook.SheetNames || workbook.SheetNames.length === 0) {
                            throw new Error('Workbook contains no worksheets.');
                        }

                        renderTabs(workbook.SheetNames);
                        loadSheet(workbook.SheetNames[0]);
                    } catch (err) {
                        showError(err.message || 'Error processing spreadsheet');
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
                btn.className = 'btn ' + (idx === 0 ? 'btn-primary' : 'btn-outline-secondary') + ' fw-semibold';
                btn.textContent = name;
                btn.addEventListener('click', () => {
                    if (activeSheetName !== name) {
                        tabsEl.querySelectorAll('button').forEach(b => {
                            b.className = 'btn btn-outline-secondary fw-semibold';
                        });
                        btn.className = 'btn btn-primary fw-semibold';
                        loadSheet(name);
                    }
                });
                tabsEl.appendChild(btn);
            });
        }

        function loadSheet(name) {
            activeSheetName = name;
            const sheet = workbook.Sheets[name];
            if (!sheet) return;

            const rows = window.XLSX.utils.sheet_to_json(sheet, {
                header: 1,
                defval: '',
                blankrows: false
            });

            if (!rows || rows.length === 0) {
                if (tableEl) tableEl.innerHTML = '<tbody><tr><td class="text-center py-4 text-secondary fst-italic">This worksheet is empty.</td></tr></tbody>';
                if (countBadge) countBadge.textContent = '0 rows';
                if (loadingEl) loadingEl.classList.add('d-none');
                if (scrollEl) scrollEl.classList.remove('d-none');
                return;
            }

            let html = '';
            const headerRow = rows[0] || [];
            html += '<thead class="table-light sticky-top"><tr>';
            html += '<th class="text-center text-secondary small" style="width: 48px;">#</th>';
            headerRow.forEach((col, idx) => {
                const safe = escapeHtml(String(col !== null && col !== undefined ? col : ''));
                html += '<th class="fw-semibold text-dark text-nowrap">' + (safe || ('Col ' + (idx + 1))) + '</th>';
            });
            html += '</tr></thead><tbody>';

            for (let i = 1; i < rows.length; i++) {
                const r = rows[i];
                html += '<tr class="excel-data-row">';
                html += '<td class="text-center text-secondary small bg-light" style="width: 48px;">' + i + '</td>';
                for (let c = 0; c < headerRow.length; c++) {
                    const cell = r[c] !== null && r[c] !== undefined ? String(r[c]) : '';
                    html += '<td class="text-nowrap">' + escapeHtml(cell) + '</td>';
                }
                html += '</tr>';
            }
            html += '</tbody>';

            if (tableEl) tableEl.innerHTML = html;
            if (countBadge) countBadge.textContent = (rows.length - 1) + ' data rows';

            if (loadingEl) loadingEl.classList.add('d-none');
            if (errorEl) errorEl.classList.add('d-none');
            if (scrollEl) scrollEl.classList.remove('d-none');

            if (searchInput) searchInput.value = '';
        }

        if (searchInput && tableEl) {
            searchInput.addEventListener('input', () => {
                const filter = searchInput.value.trim().toLowerCase();
                const trList = tableEl.querySelectorAll('tbody tr.excel-data-row');
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
                    countBadge.textContent = filter ? (count + ' matching') : (trList.length + ' data rows');
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
        const parts = filename.split('.');
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
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
