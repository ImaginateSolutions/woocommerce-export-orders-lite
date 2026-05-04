jQuery(function ($) {

    /* =========================================================
       Initialize Select2 / SelectWoo
    ========================================================= */
    if ($.fn.selectWoo) {
        $('#eowc-status').selectWoo({
            placeholder: 'Select Status',
            allowClear: true,
            width: '100%'
        });
    }

    /* =========================================================
       Modal open / close
    ========================================================= */
    $(document).on('click', '.eowc-export-btn', function () {
        $('#eowc-export-modal').fadeIn(200);
    });

    $(document).on('click', '.eowc-close, .eowc-modal-overlay', function () {
        $('#eowc-export-modal').fadeOut(200);
        resetForm();
    });

    // Sync checked class for radios
    $(document).on('change', '[name="eowc_export_format"]', function () {
        $('.eowc-radio-item').removeClass('is-checked');
        $(this).closest('.eowc-radio-item').addClass('is-checked');
    });

    // Set initially checked radio if present
    $('[name="eowc_export_format"]:checked').closest('.eowc-radio-item').addClass('is-checked');

    /* =========================================================
       Select / Deselect all columns
    ========================================================= */
    $('#eowc-select-all').on('click', function () {
        $('[name="eowc_columns[]"]').prop('checked', true).closest('.eowc-checkbox-item').addClass('is-checked');
    });

    $('#eowc-deselect-all').on('click', function () {
        $('[name="eowc_columns[]"]').prop('checked', false).closest('.eowc-checkbox-item').removeClass('is-checked');
    });

    // Sync checked class on change
    $(document).on('change', '[name="eowc_columns[]"]', function () {
        $(this).closest('.eowc-checkbox-item').toggleClass('is-checked', this.checked);
    });

    // Set initial checked classes
    $('[name="eowc_columns[]"]:checked').closest('.eowc-checkbox-item').addClass('is-checked');

    /* =========================================================
       Draggable export column order
    ========================================================= */
    if ($.fn.sortable) {
        $('.eowc-checkbox-grid').sortable({
            items: '.eowc-checkbox-item',
            connectWith: '.eowc-checkbox-grid',
            handle: '.eowc-drag-handle',
            placeholder: 'eowc-checkbox-placeholder',
            forcePlaceholderSize: true,
            tolerance: 'pointer',
            start: function (event, ui) {
                ui.item.addClass('is-dragging');
            },
            stop: function (event, ui) {
                ui.item.removeClass('is-dragging');
            }
        });
    }

    $(document).on('click', '.eowc-drag-handle', function (e) {
        e.preventDefault();
    });

    /* =========================================================
       Step navigation: Config → Confirm
    ========================================================= */
    $('#eowc-proceed-btn').on('click', function () {
        if (!validateForm()) return;
        buildSummary();
        showStep('confirm');
    });

    $('#eowc-back-btn').on('click', function () {
        showStep('config');
    });

    function showStep(step) {
        if (step === 'confirm') {
            $('#eowc-step-config').addClass('eowc-step--hidden');
            $('#eowc-step-confirm').removeClass('eowc-step--hidden');
        } else {
            $('#eowc-step-confirm').addClass('eowc-step--hidden');
            $('#eowc-step-config').removeClass('eowc-step--hidden');
        }
    }

    /* =========================================================
       Validation
    ========================================================= */
    function validateForm() {
        let valid = true;

        // Clear previous errors
        $('.eowc-field--required').removeClass('eowc-field--error');
        $('#eowc-date-range-error').hide();
        $('#eowc-columns-error').hide();

        const dateFrom = $('[name="eowc_date_from"]').val();
        const dateTo = $('[name="eowc_date_to"]').val();

        if (!dateFrom) {
            $('[name="eowc_date_from"]').closest('.eowc-field').addClass('eowc-field--error');
            valid = false;
        }

        if (!dateTo) {
            $('[name="eowc_date_to"]').closest('.eowc-field').addClass('eowc-field--error');
            valid = false;
        }

        if (dateFrom && dateTo && dateFrom > dateTo) {
            $('[name="eowc_date_from"]').closest('.eowc-field').addClass('eowc-field--error');
            $('[name="eowc_date_to"]').closest('.eowc-field').addClass('eowc-field--error');
            $('#eowc-date-range-error').show();
            valid = false;
        }

        const checkedCols = $('[name="eowc_columns[]"]:checked').length;
        if (checkedCols === 0) {
            $('#eowc-columns-error').show();
            valid = false;
        }

        return valid;
    }

    // Live-clear errors on input
    $(document).on('change', '[name="eowc_date_from"], [name="eowc_date_to"]', function () {
        $(this).closest('.eowc-field').removeClass('eowc-field--error');
        $('#eowc-date-range-error').hide();
    });

    /* =========================================================
       Build confirmation summary
    ========================================================= */
    function buildSummary() {
        const selectedStatuses = $('[name="eowc_status[]"]').val();
        let statusText = 'All Statuses';
        if (selectedStatuses && selectedStatuses.length > 0) {
            statusText = $('[name="eowc_status[]"] option:selected').map(function () {
                return $(this).text();
            }).get().join(', ');
        }
        const dateFrom = $('[name="eowc_date_from"]').val();
        const dateTo = $('[name="eowc_date_to"]').val();
        const columns = $('[name="eowc_columns[]"]:checked').map(function () {
            return $(this).closest('.eowc-checkbox-item').find('.eowc-checkbox-label').text().trim();
        }).get();

        const rows = [
            { icon: tagIcon(), label: 'Status', value: statusText },
            { icon: calIcon(), label: 'From', value: formatDate(dateFrom) },
            { icon: calIcon(), label: 'To', value: formatDate(dateTo) },
            { icon: colIcon(), label: 'Columns', value: columns.length + ' selected' },
        ];

        let html = '<ul class="eowc-summary-list">';
        rows.forEach(function (r) {
            html += `<li class="eowc-summary-item">
                <span class="eowc-summary-icon">${r.icon}</span>
                <span class="eowc-summary-key">${r.label}</span>
                <span class="eowc-summary-val">${r.value}</span>
            </li>`;
        });
        html += '</ul>';

        html += `<div class="eowc-column-tags">`;
        columns.forEach(function (col) {
            html += `<span class="eowc-tag">${col}</span>`;
        });
        html += '</div>';

        $('#eowc-summary').html(html);
    }

    function formatDate(d) {
        if (!d) return '—';
        const parts = d.split('-');
        return parts[2] + ' / ' + parts[1] + ' / ' + parts[0];
    }

    function tagIcon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>';
    }
    function calIcon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
    }
    function colIcon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>';
    }

    /* =========================================================
       Export (batch)
    ========================================================= */
    let offset = 0;
    let totalProcessed = 0;
    let totalOrders = 0;

    $('#eowc-export-form').on('submit', function (e) {
        e.preventDefault();
        totalProcessed = 0;
        offset = 0;

        $('.eowc-overlay').fadeIn(200);
        setProgress(0, 'Starting export…');

        startBatch();
    });

    function startBatch() {
        const formData = $('#eowc-export-form').serializeArray();
        let dataObj = {};
        console.log(formData);
        formData.forEach(function (f) {
            if (f.name === 'eowc_columns[]') {
                if (!dataObj.eowc_columns) dataObj.eowc_columns = [];
                dataObj.eowc_columns.push(f.value);
            } else if (f.name === 'eowc_status[]') {
                if (!dataObj.eowc_status) dataObj.eowc_status = [];
                dataObj.eowc_status.push(f.value);
            }
            else {
                dataObj[f.name] = f.value;
            }
        });
        console.log(dataObj);
        $.ajax({
            url: eowc_export_orders_params.ajax_url,
            type: 'POST',
            data: {
                action: 'eowc_export_orders',
                nonce: eowc_export_orders_params.nonce,
                offset: offset,
                ...dataObj,
            },
            success: function (res) {
                if (!res.success) {
                    if (res.data.message) {
                        setProgress(0, res.data.message);
                        setTimeout(function () {
                            $('.eowc-overlay').fadeOut(300);
                        }, 5000);
                        return;
                    } else {
                        setProgress(0, 'Something went wrong. Please try again.');
                        return;
                    }
                }

                totalOrders = res.data.total;

                if (res.data.done) {
                    finishExport(res.data.download_url);
                    return;
                }

                offset = res.data.next_offset;
                totalProcessed += res.data.processed;

                const pct = totalOrders > 0 ? Math.min((totalProcessed / totalOrders) * 100, 99) : 50;
                setProgress(pct, totalProcessed + ' of ' + totalOrders + ' orders processed…');

                startBatch();
            },
            error: function () {
                setProgress(0, 'Network error. Please try again.');
            }
        });
    }

    function setProgress(pct, text) {
        $('.eowc-progress-bar').css('width', pct + '%');
        $('.eowc-progress-pct').text(Math.round(pct) + '%');
        $('.eowc-progress-text').text(text);
    }

    function finishExport(downloadUrl) {
        setProgress(100, 'Export completed!');

        setTimeout(function () {
            $('.eowc-overlay').fadeOut(300);
            resetForm();

            if (downloadUrl) {
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = ''; // force download
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }, 1000);
    }

    function resetForm() {
        offset = 0;
        totalProcessed = 0;
        totalOrders = 0;
        if ($.fn.selectWoo) {
            $('#eowc-status').val(null).trigger('change');
        } else if ($.fn.select2) {
            $('#eowc-status').val(null).trigger('change');
        }
        showStep('config');
    }

});
