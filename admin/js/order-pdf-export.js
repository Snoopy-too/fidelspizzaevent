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
    function extractCellContent(cell, isLandscape) {
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
        if (small && (cell.querySelector('br') || small.textContent.includes('@'))) {
            const clone = cell.cloneNode(true);
            const cloneSmall = clone.querySelector('small');
            const email = cloneSmall ? cloneSmall.textContent.trim() : '';
            if (cloneSmall) cloneSmall.remove();
            const name = clone.textContent.trim();
            const nameSize = isLandscape ? '8.5px' : '7.5px';
            const emailSize = isLandscape ? '7.5px' : '6.5px';
            return `<div style="font-weight: 600; color: #2c3e50; font-size: ${nameSize}; word-break: break-word;">${escapeHtml(name)}</div><div style="font-size: ${emailSize}; color: #7f8c8d; word-break: break-all;">${escapeHtml(email)}</div>`;
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
                title: th.getAttribute('data-pdf-title') || th.textContent.replace(/[▲▼]/g, '').trim()
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
                        content: extractCellContent(cell, isLandscape),
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

        function getColumnWidth(colTitle, totalCols) {
            if (/number|番号/i.test(colTitle)) return totalCols > 5 ? '12%' : '18%';
            if (/customer|顧客/i.test(colTitle)) return totalCols > 5 ? '19%' : '26%';
            if (/note|メモ/i.test(colTitle)) return '8%';
            if (/item|商品/i.test(colTitle)) return '25%';
            if (/amount|金額|total/i.test(colTitle)) return totalCols > 5 ? '10%' : '16%';
            if (/pickup|受取/i.test(colTitle)) return '16%';
            if (/status|ステータス/i.test(colTitle)) return totalCols > 5 ? '10%' : '15%';
            if (/date|日付|日時/i.test(colTitle)) return '25%';
            return `${(100 / totalCols).toFixed(1)}%`;
        }

        // Build temporary export container with strict width safely under A4 printable dimensions (733px portrait, 1061px landscape)
        const targetWidthPx = isLandscape ? 1000 : 690;
        const exportContainer = document.createElement('div');
        exportContainer.className = 'pdf-export-wrapper';
        exportContainer.style.width = targetWidthPx + 'px';
        exportContainer.style.maxWidth = targetWidthPx + 'px';
        exportContainer.style.minWidth = targetWidthPx + 'px';
        exportContainer.style.background = '#ffffff';
        exportContainer.style.color = '#2c3e50';
        exportContainer.style.padding = '0';
        exportContainer.style.margin = '0 auto';
        exportContainer.style.boxSizing = 'border-box';
        exportContainer.style.overflow = 'hidden';
        exportContainer.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Hiragino Sans", "Hiragino Kaku Gothic ProN", "Yu Gothic", Meiryo, sans-serif';

        const timestampFormatted = formatTimestamp(now);
        const recordCountText = lang === 'ja' ? `${rowsData.length} 件` : `${rowsData.length} orders`;

        let filterHtml = '';
        if (filterInfo.trim() !== '') {
            filterHtml = `<div><strong>${escapeHtml(labelFilters)}:</strong> ${escapeHtml(filterInfo)}</div>`;
        }

        const thPadding = isLandscape ? '5px 5px' : '4px 3px';
        const thFontSize = isLandscape ? '9px' : '7.5px';
        const tdPadding = isLandscape ? '5px 5px' : '4px 3px';
        const tdFontSize = isLandscape ? '8.5px' : '7.5px';

        let colgroupHtml = '<colgroup>';
        let tableHeaderHtml = '<tr>';
        includedCols.forEach(col => {
            const isAmount = /amount|金額|total/i.test(col.title);
            const isStatus = /status|ステータス/i.test(col.title);
            const align = isAmount ? 'right' : (isStatus ? 'center' : 'left');
            const colWidth = getColumnWidth(col.title, includedCols.length);
            colgroupHtml += `<col style="width: ${colWidth};">`;
            tableHeaderHtml += `<th style="width: ${colWidth}; text-align: ${align}; padding: ${thPadding}; font-size: ${thFontSize}; background-color: #2c3e50; color: #ffffff; border: 1px solid #1a252f; font-weight: bold; box-sizing: border-box; word-break: break-word; line-height: 1.15;">${escapeHtml(col.title)}</th>`;
        });
        colgroupHtml += '</colgroup>';
        tableHeaderHtml += '</tr>';

        let tableBodyHtml = '';
        rowsData.forEach((row, rowIdx) => {
            const bg = rowIdx % 2 === 0 ? '#ffffff' : '#f8f9fa';
            tableBodyHtml += `<tr style="background-color: ${bg}; page-break-inside: avoid;">`;
            row.forEach((cell, cellIdx) => {
                const colTitle = includedCols[cellIdx] ? includedCols[cellIdx].title : '';
                const colWidth = getColumnWidth(colTitle, includedCols.length);
                const align = cell.isAmount ? 'right' : (cell.isStatus ? 'center' : 'left');
                const weight = cell.isOrderNum ? 'font-weight: 600;' : '';
                tableBodyHtml += `<td style="width: ${colWidth}; text-align: ${align}; ${weight} padding: ${tdPadding}; font-size: ${tdFontSize}; border: 1px solid #dcdcdc; vertical-align: top; word-break: break-word; overflow-wrap: break-word; line-height: 1.25; box-sizing: border-box;">${cell.content}</td>`;
            });
            tableBodyHtml += '</tr>';
        });

        // Optional summary table (e.g. pickup schedule summary with pizza quantities)
        const summaryTableId = btn.getAttribute('data-summary-table-id');
        const summaryTable = summaryTableId ? document.getElementById(summaryTableId) : null;
        let summarySectionHtml = '';

        if (summaryTable) {
            const summaryTitle = btn.getAttribute('data-summary-title') || 'Pickup Schedule Summary';
            const sumHeaderThs = Array.from(summaryTable.querySelectorAll('thead tr th'));
            const sumCols = sumHeaderThs.map((th, idx) => ({
                index: idx,
                title: th.textContent.trim(),
                isFirst: idx === 0,
                isTotal: idx === sumHeaderThs.length - 1 || /total|合計/i.test(th.textContent)
            }));

            const sumRowNodes = Array.from(summaryTable.querySelectorAll('tbody tr'));
            const sumRowsData = [];
            sumRowNodes.forEach(tr => {
                const firstCell = tr.querySelector('td');
                if (firstCell && firstCell.getAttribute('colspan')) {
                    sumRowsData.push([{ content: firstCell.textContent.trim(), isColspan: true, colspan: sumCols.length }]);
                    return;
                }
                const cells = tr.querySelectorAll('td');
                if (cells.length === 0) return;
                const rowCells = [];
                sumCols.forEach(col => {
                    const c = cells[col.index];
                    let text = c ? c.textContent.trim() : '';
                    if (col.isFirst) {
                        text = text.replace(/^[✓\s]+/, '').replace(/[\s🔍]+$/, '').trim();
                    }
                    rowCells.push({
                        content: escapeHtml(text),
                        isNumeric: !col.isFirst,
                        isTotal: col.isTotal,
                        isFirst: col.isFirst
                    });
                });
                sumRowsData.push(rowCells);
            });

            const sumFootRows = Array.from(summaryTable.querySelectorAll('tfoot tr'));
            const sumFootData = [];
            sumFootRows.forEach(tr => {
                const cells = tr.querySelectorAll('td, th');
                if (cells.length === 0) return;
                const footCells = [];
                sumCols.forEach(col => {
                    const c = cells[col.index];
                    const text = c ? c.textContent.trim() : '';
                    footCells.push({
                        content: escapeHtml(text),
                        isNumeric: !col.isFirst,
                        isTotal: col.isTotal,
                        isFirst: col.isFirst
                    });
                });
                sumFootData.push(footCells);
            });

            const timeColWidth = isLandscape ? 30 : 34;
            const remainingCols = Math.max(1, sumCols.length - 1);
            const otherColWidth = ((100 - timeColWidth) / remainingCols).toFixed(1);

            let sumColgroupHtml = '<colgroup>';
            let sumTheadHtml = '<tr>';
            sumCols.forEach((col, idx) => {
                const w = idx === 0 ? `${timeColWidth}%` : `${otherColWidth}%`;
                const align = idx === 0 ? 'left' : 'right';
                const bg = col.isTotal ? '#1a252f' : '#2c3e50';
                sumColgroupHtml += `<col style="width: ${w};">`;
                sumTheadHtml += `<th style="width: ${w}; text-align: ${align}; padding: ${thPadding}; font-size: ${thFontSize}; background-color: ${bg}; color: #ffffff; border: 1px solid #1a252f; font-weight: bold; box-sizing: border-box; line-height: 1.15;">${escapeHtml(col.title)}</th>`;
            });
            sumColgroupHtml += '</colgroup>';
            sumTheadHtml += '</tr>';

            let sumTbodyHtml = '';
            sumRowsData.forEach((r, rIdx) => {
                if (r[0] && r[0].isColspan) {
                    sumTbodyHtml += `<tr><td colspan="${r[0].colspan}" style="text-align: center; padding: 6px; font-size: ${tdFontSize}; color: #888; border: 1px solid #dcdcdc;">${r[0].content}</td></tr>`;
                    return;
                }
                const bg = rIdx % 2 === 0 ? '#ffffff' : '#f8f9fa';
                sumTbodyHtml += `<tr style="background-color: ${bg}; page-break-inside: avoid;">`;
                r.forEach(cell => {
                    const align = cell.isNumeric ? 'right' : 'left';
                    const weight = (cell.isFirst || cell.isTotal) ? 'font-weight: bold;' : '';
                    const cellBg = cell.isTotal ? 'background-color: #f0f7fb;' : '';
                    sumTbodyHtml += `<td style="text-align: ${align}; ${weight} ${cellBg} padding: ${tdPadding}; font-size: ${tdFontSize}; border: 1px solid #dcdcdc; word-break: break-word; line-height: 1.25; box-sizing: border-box;">${cell.content}</td>`;
                });
                sumTbodyHtml += '</tr>';
            });

            let sumTfootHtml = '';
            if (sumFootData.length > 0) {
                sumFootData.forEach(fr => {
                    sumTfootHtml += `<tr style="background-color: #eaeded; font-weight: bold; border-top: 2px solid #2c3e50; page-break-inside: avoid;">`;
                    fr.forEach(cell => {
                        const align = cell.isNumeric ? 'right' : 'left';
                        const cellBg = cell.isTotal ? 'background-color: #d5dbdb; color: #1a252f;' : '';
                        sumTfootHtml += `<td style="text-align: ${align}; font-weight: bold; ${cellBg} padding: ${tdPadding}; font-size: ${tdFontSize}; border: 1px solid #bdc3c7; line-height: 1.25; box-sizing: border-box;">${cell.content}</td>`;
                    });
                    sumTfootHtml += '</tr>';
                });
            }

            summarySectionHtml = `
                <div style="margin-bottom: 12px; page-break-inside: avoid;">
                    <div style="font-size: 10px; font-weight: bold; color: #2c3e50; margin-bottom: 4px; padding-bottom: 3px; border-bottom: 1.5px solid #2c3e50; display: flex; justify-content: space-between; align-items: center;">
                        <span>📦 ${escapeHtml(summaryTitle)}</span>
                    </div>
                    <table style="width: 100%; table-layout: fixed; border-collapse: collapse; box-sizing: border-box;">
                        ${sumColgroupHtml}
                        <thead>${sumTheadHtml}</thead>
                        <tbody>${sumTbodyHtml}</tbody>
                        ${sumTfootHtml ? `<tfoot>${sumTfootHtml}</tfoot>` : ''}
                    </table>
                </div>
            `;
        }

        const ordersListTitle = btn.getAttribute('data-orders-title') || reportTitle;
        const ordersListHeader = summaryTable
            ? `<div style="font-size: 10px; font-weight: bold; color: #2c3e50; margin-top: 8px; margin-bottom: 4px; padding-bottom: 3px; border-bottom: 1.5px solid #2c3e50;">📋 ${escapeHtml(ordersListTitle)}</div>`
            : '';

        exportContainer.innerHTML = `
            <style>
                .pdf-status-badge {
                    display: inline-block;
                    padding: 1px ${isLandscape ? '4px' : '2px'};
                    border-radius: 2px;
                    font-size: ${isLandscape ? '7.5px' : '6.5px'};
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
            <div style="border-bottom: 2px solid #2c3e50; padding-bottom: 6px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: flex-end; width: 100%; box-sizing: border-box;">
                <div>
                    <h1 style="margin: 0 0 2px 0; font-size: 15px; color: #2c3e50; font-weight: bold;">🍕 ${escapeHtml(siteTitle)}</h1>
                    <div style="font-size: 12px; font-weight: bold; color: #34495e;">${escapeHtml(reportTitle)}</div>
                </div>
                <div style="text-align: right; font-size: 8px; color: #555; white-space: nowrap; line-height: 1.35; padding-right: 2px;">
                    <div><strong>${escapeHtml(labelGeneratedAt)}:</strong> ${escapeHtml(timestampFormatted)}</div>
                    <div><strong>${escapeHtml(labelTotalRecords)}:</strong> ${escapeHtml(recordCountText)}</div>
                </div>
            </div>
            ${filterHtml ? `<div style="font-size: 8px; color: #555; margin-bottom: 6px; padding: 4px 6px; background: #edf2f7; border-radius: 3px; width: 100%; box-sizing: border-box; word-break: break-word;">${filterHtml}</div>` : ''}
            ${summarySectionHtml}
            ${ordersListHeader}
            <table style="width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 3px; box-sizing: border-box;">
                ${colgroupHtml}
                <thead>
                    ${tableHeaderHtml}
                </thead>
                <tbody>
                    ${tableBodyHtml}
                </tbody>
            </table>
            <div style="margin-top: 8px; padding-top: 5px; border-top: 1px solid #e2e8f0; font-size: 7.5px; color: #888; display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
                <span>${escapeHtml(siteTitle)} &bull; ${escapeHtml(reportTitle)}</span>
                <span style="white-space: nowrap; padding-right: 2px;">${escapeHtml(timestampFormatted)}</span>
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
