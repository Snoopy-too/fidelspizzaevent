/**
 * order-pdf-export.js
 * High-integrity PDF exporter for Fidel's Pizza Event admin order tables.
 * Exports visible table data matching the exact sort order, filters, and active language on the screen.
 */
(function () {
    'use strict';

    function padZero(num) {
        return String(num).padStart(2, '0');
    }

    function formatTimestamp(d) {
        return `${d.getFullYear()}/${padZero(d.getMonth() + 1)}/${padZero(d.getDate())} ${padZero(d.getHours())}:${padZero(d.getMinutes())}`;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Inspect a table cell and extract clean, printable HTML representation.
     */
    function extractCellContent(cell) {
        // If cell contains a status <select>, extract the selected option text and value
        const select = cell.querySelector('select[name="status"], select.status-select');
        if (select) {
            const selectedOpt = select.options[select.selectedIndex];
            const text = selectedOpt ? selectedOpt.text.trim() : select.value;
            const val = select.value.toLowerCase();
            return `<span class="pdf-status-badge pdf-status-${escapeHtml(val)}">${escapeHtml(text)}</span>`;
        }

        // If cell already has a status badge
        const badge = cell.querySelector('.status-badge');
        if (badge) {
            // Find status class
            const classes = Array.from(badge.classList);
            const statusClass = classes.find(c => c.startsWith('status-') && c !== 'status-badge') || 'status-pending';
            const statusName = statusClass.replace('status-', '');
            return `<span class="pdf-status-badge pdf-status-${escapeHtml(statusName)}">${escapeHtml(badge.textContent.trim())}</span>`;
        }

        // If cell contains customer name + email (<br><small>email</small>)
        const small = cell.querySelector('small');
        if (small) {
            // Clone and separate
            const clone = cell.cloneNode(true);
            const cloneSmall = clone.querySelector('small');
            const email = cloneSmall ? cloneSmall.textContent.trim() : '';
            if (cloneSmall) cloneSmall.remove();
            const name = clone.textContent.trim();
            return `<div style="font-weight: 600; color: #2c3e50;">${escapeHtml(name)}</div><div style="font-size: 8.5px; color: #7f8c8d;">${escapeHtml(email)}</div>`;
        }

        // Otherwise get inner text with preserved line breaks
        const text = cell.innerText.trim();
        return escapeHtml(text).replace(/\n/g, '<br>');
    }

    async function exportTableToPdf(btn) {
        if (typeof window.html2pdf === 'undefined') {
            alert('PDF generation library is not loaded. Please refresh the page and try again.');
            return;
        }

        const tableId = btn.getAttribute('data-table-id');
        const targetTable = document.getElementById(tableId);
        if (!targetTable) {
            console.error('Target table not found: ' + tableId);
            return;
        }

        // Configuration from data attributes
        const siteTitle = btn.getAttribute('data-site-title') || "Fidel's Pizza Event";
        const reportTitle = btn.getAttribute('data-report-title') || 'Orders List';
        const lang = btn.getAttribute('data-lang') || document.documentElement.lang || 'ja';
        const labelGenerating = btn.getAttribute('data-label-generating') || 'Generating PDF...';
        const labelGeneratedAt = btn.getAttribute('data-label-generated-at') || 'Generated on';
        const labelTotalRecords = btn.getAttribute('data-label-total-records') || 'Total records';
        const labelFilters = btn.getAttribute('data-label-filters') || 'Filters';
        const filterInfo = btn.getAttribute('data-filter-info') || '';
        const orientation = btn.getAttribute('data-orientation') || 'portrait';
        const isLandscape = orientation === 'landscape';

        const now = new Date();
        const dateStr = `${now.getFullYear()}-${padZero(now.getMonth() + 1)}-${padZero(now.getDate())}`;
        const defaultFilename = `${tableId.replace(/-/g, '_')}_${dateStr}.pdf`;
        const filename = btn.getAttribute('data-filename') || defaultFilename;

        // Save original button state and show progress
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<span style="display:inline-block; animation: spin 1s infinite linear;">⏳</span> ${escapeHtml(labelGenerating)}`;

        // Determine columns to include (skip checkbox & action columns)
        const headerCells = Array.from(targetTable.querySelectorAll('thead tr th'));
        const includedCols = [];

        headerCells.forEach((th, idx) => {
            const text = th.textContent.trim().toLowerCase();
            const hasCheckbox = th.querySelector('input[type="checkbox"]');
            
            // Skip checkbox column and action column
            if (hasCheckbox) return;
            if (text === 'action' || text === 'actions' || text === '操作') return;

            includedCols.push({
                index: idx,
                title: th.textContent.trim()
            });
        });

        // Collect rows in their current DOM order
        const rowNodes = Array.from(targetTable.querySelectorAll('tbody tr'));
        const rowsData = [];

        rowNodes.forEach(tr => {
            // Check if this is an empty-state message row
            const firstCell = tr.querySelector('td');
            if (firstCell && firstCell.getAttribute('colspan')) {
                return; // skip placeholder/empty row
            }

            const cells = tr.querySelectorAll('td');
            if (cells.length === 0) return;

            const rowCells = [];
            includedCols.forEach(col => {
                const cell = cells[col.index];
                if (cell) {
                    rowCells.push({
                        content: extractCellContent(cell),
                        isAmount: /amount|金額|total/i.test(col.title) || cell.textContent.includes('¥'),
                        isOrderNum: /number|番号/i.test(col.title),
                        isStatus: /status|ステータス/i.test(col.title)
                    });
                } else {
                    rowCells.push({ content: '', isAmount: false, isOrderNum: false, isStatus: false });
                }
            });

            rowsData.push(rowCells);
        });

        // Build temporary export container
        const exportContainer = document.createElement('div');
        exportContainer.className = 'pdf-export-wrapper';
        exportContainer.style.width = isLandscape ? '1060px' : '750px';
        exportContainer.style.background = '#ffffff';
        exportContainer.style.color = '#2c3e50';
        exportContainer.style.padding = isLandscape ? '20px 24px' : '16px 18px';
        exportContainer.style.boxSizing = 'border-box';
        exportContainer.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Hiragino Sans", "Hiragino Kaku Gothic ProN", "Yu Gothic", Meiryo, sans-serif';

        const timestampFormatted = formatTimestamp(now);
        const recordCountText = lang === 'ja' ? `${rowsData.length} 件` : `${rowsData.length} orders`;

        let filterHtml = '';
        if (filterInfo.trim() !== '') {
            filterHtml = `<div><strong>${escapeHtml(labelFilters)}:</strong> ${escapeHtml(filterInfo)}</div>`;
        }

        const thPadding = isLandscape ? '7px 6px' : '6px 4px';
        const thFontSize = isLandscape ? '10px' : '9px';
        const tdPadding = isLandscape ? '6px 6px' : '5px 4px';
        const tdFontSize = isLandscape ? '9.5px' : '8.5px';

        let tableHeaderHtml = '<tr>';
        includedCols.forEach(col => {
            const isAmount = /amount|金額|total/i.test(col.title);
            const isStatus = /status|ステータス/i.test(col.title);
            const align = isAmount ? 'right' : (isStatus ? 'center' : 'left');
            tableHeaderHtml += `<th style="text-align: ${align}; padding: ${thPadding}; font-size: ${thFontSize}; background-color: #2c3e50; color: #ffffff; border: 1px solid #1a252f; font-weight: bold; white-space: nowrap;">${escapeHtml(col.title)}</th>`;
        });
        tableHeaderHtml += '</tr>';

        let tableBodyHtml = '';
        rowsData.forEach((row, rowIdx) => {
            const bg = rowIdx % 2 === 0 ? '#ffffff' : '#f8f9fa';
            tableBodyHtml += `<tr style="background-color: ${bg}; page-break-inside: avoid;">`;
            row.forEach(cell => {
                const align = cell.isAmount ? 'right' : (cell.isStatus ? 'center' : 'left');
                const weight = cell.isOrderNum ? 'font-weight: 600;' : '';
                tableBodyHtml += `<td style="text-align: ${align}; ${weight} padding: ${tdPadding}; font-size: ${tdFontSize}; border: 1px solid #dcdcdc; vertical-align: top; word-break: break-word; line-height: 1.35;">${cell.content}</td>`;
            });
            tableBodyHtml += '</tr>';
        });

        exportContainer.innerHTML = `
            <style>
                .pdf-status-badge {
                    display: inline-block;
                    padding: 2px ${isLandscape ? '6px' : '4px'};
                    border-radius: 4px;
                    font-size: ${isLandscape ? '8.5px' : '8px'};
                    font-weight: bold;
                    text-transform: uppercase;
                    text-align: center;
                    white-space: nowrap;
                }
                .pdf-status-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
                .pdf-status-confirmed { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
                .pdf-status-preparing { background-color: #cce5ff; color: #004085; border: 1px solid #b8daff; }
                .pdf-status-ready { background-color: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
                .pdf-status-completed { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
                .pdf-status-cancelled { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
                .pdf-status-archived { background-color: #ececec; color: #444444; border: 1px solid #dddddd; }
            </style>
            <div style="border-bottom: 2px solid #2c3e50; padding-bottom: 10px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <h1 style="margin: 0 0 4px 0; font-size: 18px; color: #2c3e50; font-weight: bold;">🍕 ${escapeHtml(siteTitle)}</h1>
                    <div style="font-size: 14px; font-weight: bold; color: #34495e;">${escapeHtml(reportTitle)}</div>
                </div>
                <div style="text-align: right; font-size: 10px; color: #555;">
                    <div><strong>${escapeHtml(labelGeneratedAt)}:</strong> ${escapeHtml(timestampFormatted)}</div>
                    <div><strong>${escapeHtml(labelTotalRecords)}:</strong> ${escapeHtml(recordCountText)}</div>
                </div>
            </div>
            ${filterHtml ? `<div style="font-size: 10px; color: #555; margin-bottom: 10px; padding: 6px 10px; background: #edf2f7; border-radius: 4px;">${filterHtml}</div>` : ''}
            <table style="width: 100%; border-collapse: collapse; margin-top: 6px;">
                <thead>
                    ${tableHeaderHtml}
                </thead>
                <tbody>
                    ${tableBodyHtml}
                </tbody>
            </table>
            <div style="margin-top: 14px; padding-top: 8px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #888; display: flex; justify-content: space-between;">
                <span>${escapeHtml(siteTitle)} &bull; ${escapeHtml(reportTitle)}</span>
                <span>${escapeHtml(timestampFormatted)}</span>
            </div>
        `;

        const pdfOptions = {
            margin: [8, 8, 8, 8],
            filename: filename,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: {
                scale: 2,
                useCORS: true,
                logging: false,
                scrollX: 0,
                scrollY: 0
            },
            jsPDF: {
                unit: 'mm',
                format: 'a4',
                orientation: orientation
            },
            pagebreak: {
                mode: ['avoid-all', 'css', 'legacy']
            }
        };

        try {
            await window.html2pdf().set(pdfOptions).from(exportContainer).save();
        } catch (err) {
            console.error('PDF export failed:', err);
            alert('Failed to generate PDF. Please try again.');
        } finally {
            if (exportContainer.parentNode) {
                exportContainer.parentNode.removeChild(exportContainer);
            }
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    // Attach listener to all PDF export buttons on DOMContentLoaded or immediately
    function initPdfExportButtons() {
        const buttons = document.querySelectorAll('.btn-export-pdf, #exportDashboardPdfBtn, #exportOrdersPdfBtn');
        buttons.forEach(btn => {
            if (!btn.dataset.pdfBound) {
                btn.dataset.pdfBound = 'true';
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    exportTableToPdf(this);
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPdfExportButtons);
    } else {
        initPdfExportButtons();
    }
})();
