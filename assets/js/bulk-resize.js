/**
 * Voxel Toolkit - Bulk Resize
 *
 * Admin JavaScript for bulk image resizing.
 */
(function($) {
    'use strict';

    const Config = VT_BulkResize;
    let isProcessing = false;
    let shouldStop = false;
    let totalImages = 0;
    let processedImages = 0;
    let totalSaved = 0;
    let totalOriginal = 0;
    let totalResized = 0;
    let totalSkipped = 0;

    /**
     * Initialize
     */
    function init() {
        bindEvents();
        refreshCount();
    }

    /**
     * Bind events
     */
    function bindEvents() {
        $('input[name="vt_filter"]').on('change', refreshCount);
        $('#vt-refresh-count').on('click', refreshCount);
        $('#vt-start-btn').on('click', startProcessing);
        $('#vt-stop-btn').on('click', stopProcessing);
        $('#vt-reset-btn').on('click', resetProcessed);
    }

    /**
     * Get current filter value
     */
    function getFilter() {
        return $('input[name="vt_filter"]:checked').val() || 'not_processed';
    }

    /**
     * Refresh image count
     */
    function refreshCount() {
        $('#vt-image-count').text('...');

        $.ajax({
            url: Config.ajax_url,
            type: 'POST',
            data: {
                action: 'vt_bulk_resize_get_count',
                nonce: Config.nonce,
                filter: getFilter()
            },
            success: function(response) {
                if (response.success) {
                    totalImages = response.data.count;
                    $('#vt-image-count').text(formatNumber(totalImages));
                } else {
                    $('#vt-image-count').text('Error');
                }
            },
            error: function() {
                $('#vt-image-count').text('Error');
            }
        });
    }

    /**
     * Start processing
     */
    function startProcessing() {
        if (isProcessing) return;
        if (totalImages === 0) {
            addLog('info', Config.strings.no_images);
            return;
        }

        isProcessing = true;
        shouldStop = false;
        processedImages = 0;
        totalSaved = 0;
        totalOriginal = 0;
        totalResized = 0;
        totalSkipped = 0;

        // Update UI
        $('#vt-start-btn').hide();
        $('#vt-stop-btn').show();
        $('#vt-progress-card').show();
        $('#vt-summary-card').hide();
        $('#vt-total-count').text(formatNumber(totalImages));
        clearLog();

        addLog('info', 'Starting bulk resize...');
        addLog('info', 'Settings: Max ' + Config.max_width + 'x' + Config.max_height + 'px, Quality ' + Config.quality + '%');

        processBatch(0);
    }

    /**
     * Stop processing
     */
    function stopProcessing() {
        shouldStop = true;
        addLog('warning', Config.strings.stopped);
        finishProcessing();
    }

    /**
     * Process a batch of images
     */
    function processBatch(offset) {
        if (shouldStop) {
            finishProcessing();
            return;
        }

        const filter = getFilter();
        // For "not_processed" filter, always use offset 0 since processed items are removed from results
        const useOffset = filter === 'not_processed' ? 0 : offset;

        $.ajax({
            url: Config.ajax_url,
            type: 'POST',
            data: {
                action: 'vt_bulk_resize_process',
                nonce: Config.nonce,
                filter: filter,
                offset: useOffset
            },
            success: function(response) {
                if (!response.success) {
                    addLog('error', response.data || Config.strings.error);
                    finishProcessing();
                    return;
                }

                const data = response.data;
                processedImages += data.processed;
                totalSaved += data.saved_bytes;
                totalOriginal += data.original_bytes || 0;
                totalResized += data.resized;
                totalSkipped += data.skipped;

                // Log each result
                data.results.forEach(function(result) {
                    let type;
                    let tooltip = '';

                    if (result.status === 'error') {
                        type = 'error';
                    } else if (result.status === 'skipped') {
                        type = 'info';
                    } else if (result.saved < 0) {
                        // File got larger after conversion
                        type = 'warning';
                        tooltip = 'File size increased after conversion. This can happen with already-optimized images.';
                    } else {
                        type = 'success';
                    }

                    addLog(type, result.filename + ' - ' + result.message, tooltip);
                });

                updateProgress();

                if (data.has_more && !shouldStop) {
                    // For "not_processed", keep offset at 0; for others, increment
                    const nextOffset = filter === 'not_processed' ? 0 : data.next_offset;
                    processBatch(nextOffset);
                } else {
                    addLog('success', Config.strings.complete);
                    finishProcessing();
                }
            },
            error: function(xhr, status, error) {
                addLog('error', 'AJAX Error: ' + error);
                finishProcessing();
            }
        });
    }

    /**
     * Update progress display
     */
    function updateProgress() {
        const percent = totalImages > 0 ? Math.round((processedImages / totalImages) * 100) : 0;

        $('#vt-progress-fill').css('width', percent + '%');
        $('#vt-progress-percent').text(percent + '%');
        $('#vt-processed-count').text(formatNumber(processedImages));
        $('#vt-saved-size').text(formatBytes(totalSaved));
    }

    /**
     * Finish processing
     */
    function finishProcessing() {
        isProcessing = false;

        $('#vt-start-btn').show();
        $('#vt-stop-btn').hide();

        // Set progress to 100% on completion (unless stopped)
        if (!shouldStop && processedImages > 0) {
            $('#vt-progress-fill').css('width', '100%');
            $('#vt-progress-percent').text('100%');
            $('#vt-total-count').text(formatNumber(processedImages));
        }

        // Calculate average percentage saved
        const avgPercent = totalOriginal > 0 ? Math.round((totalSaved / totalOriginal) * 100) : 0;

        // Show summary
        $('#vt-summary-processed').text(formatNumber(processedImages));
        $('#vt-summary-resized').text(formatNumber(totalResized));
        $('#vt-summary-skipped').text(formatNumber(totalSkipped));
        $('#vt-summary-saved').text(formatBytes(totalSaved));
        $('#vt-summary-percent').text(avgPercent + '%');
        $('#vt-summary-card').show();

        // Refresh count
        refreshCount();
    }

    /**
     * Reset processed status
     */
    function resetProcessed() {
        if (!confirm('This will reset the "processed" status for all images, allowing them to be processed again. Continue?')) {
            return;
        }

        $.ajax({
            url: Config.ajax_url,
            type: 'POST',
            data: {
                action: 'vt_bulk_resize_reset',
                nonce: Config.nonce
            },
            success: function(response) {
                if (response.success) {
                    addLog('info', 'Reset complete. ' + response.data.deleted + ' records cleared.');
                    refreshCount();
                } else {
                    addLog('error', 'Reset failed.');
                }
            },
            error: function() {
                addLog('error', 'Reset failed (AJAX error).');
            }
        });
    }

    /**
     * Add log entry
     */
    function addLog(type, message, tooltip) {
        const $log = $('#vt-log');
        $log.find('.vt-bulk-resize-log-empty').remove();

        const icon = type === 'success' ? 'yes' :
                     type === 'error' ? 'no' :
                     type === 'warning' ? 'warning' : 'arrow-right-alt2';

        const tooltipAttr = tooltip ? ' title="' + escapeHtml(tooltip) + '"' : '';

        const $entry = $('<div class="vt-bulk-resize-log-entry vt-bulk-resize-log-' + type + '"' + tooltipAttr + '>' +
            '<span class="dashicons dashicons-' + icon + '"></span>' +
            '<span class="vt-bulk-resize-log-text">' + escapeHtml(message) + '</span>' +
        '</div>');

        $log.append($entry);
        $log.scrollTop($log[0].scrollHeight);

        // Update current image display
        if (type === 'success' || type === 'info' || type === 'warning') {
            const filename = message.split(' - ')[0];
            $('#vt-current-image').text(filename);
        }
    }

    /**
     * Clear log
     */
    function clearLog() {
        $('#vt-log').html('<div class="vt-bulk-resize-log-empty">Processing log will appear here...</div>');
    }

    /**
     * Format number with commas
     */
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    /**
     * Format bytes to human readable
     */
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';

        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize when ready
    $(document).ready(init);

})(jQuery);
