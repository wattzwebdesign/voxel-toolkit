/**
 * Voxel Toolkit Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Initialize admin functionality
        VoxelToolkitAdmin.init();
    });
    
    const VoxelToolkitAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initializeFilters();
        },
        
        /**
         * Initialize filters on page load
         */
        initializeFilters: function() {
            // Apply initial filters to show current state
            this.applyFilters();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Function toggle switches
            $(document).on('change', '.function-toggle-checkbox', this.handleFunctionToggle.bind(this));
            
            // Reset settings button
            $(document).on('click', '#reset-all-settings', this.handleResetSettings.bind(this));
            
            // Search and filter functionality
            $(document).on('input', '#voxel-toolkit-search', this.handleSearch.bind(this));
            $(document).on('change', 'input[name="function-filter"]', this.handleFilterChange.bind(this));
            $(document).on('click', '#voxel-toolkit-controls-reset', this.handleControlsReset.bind(this));
            
            // Handle AJAX errors globally
            $(document).ajaxError(this.handleAjaxError.bind(this));
        },
        
        /**
         * Handle function toggle
         */
        handleFunctionToggle: function(e) {
            const $checkbox = $(e.target);
            const $card = $checkbox.closest('.voxel-toolkit-function-card');
            const functionKey = $checkbox.data('function');
            const enabled = $checkbox.is(':checked');
            
            // Add loading state
            $card.addClass('loading');
            $checkbox.prop('disabled', true);
            
            // Make AJAX request
            $.ajax({
                url: voxelToolkit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'voxel_toolkit_toggle_function',
                    function: functionKey,
                    enabled: enabled ? 1 : 0,
                    nonce: voxelToolkit.nonce
                },
                success: function(response) {
                    console.log('Toggle response for', functionKey, ':', response); // Debug log
                    if (response.success) {
                        // Update card state
                        $card.toggleClass('enabled', enabled);
                        $card.toggleClass('disabled', !enabled);
                        
                        // Update status indicator
                        const $statusIndicator = $card.find('.status-indicator');
                        if (enabled) {
                            $statusIndicator.removeClass('inactive').addClass('active').text(voxelToolkit.strings.functionEnabled || 'Active');
                        } else {
                            $statusIndicator.removeClass('active').addClass('inactive').text(voxelToolkit.strings.functionDisabled || 'Inactive');
                        }
                        
                        // Show/hide configure button
                        const $actions = $card.find('.function-actions');
                        if (enabled && $actions.length === 0) {
                            const settingsUrl = window.location.origin + window.location.pathname + '?page=voxel-toolkit-settings#section-' + functionKey;
                            $card.append('<div class="function-actions"><a href="' + settingsUrl + '" class="button button-secondary">Configure</a></div>');
                        } else if (!enabled) {
                            $actions.remove();
                        }
                        
                        // Show success message
                        VoxelToolkitAdmin.showNotice(response.data.message, 'success');
                        
                        // Re-apply filters in case filter is active
                        VoxelToolkitAdmin.applyFilters();
                    } else {
                        // Regular error - revert checkbox state
                        $checkbox.prop('checked', !enabled);
                        VoxelToolkitAdmin.showNotice(response.data || voxelToolkit.strings.error, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    // Revert checkbox state
                    $checkbox.prop('checked', !enabled);
                    VoxelToolkitAdmin.showNotice(voxelToolkit.strings.error, 'error');
                    console.error('AJAX Error:', error);
                },
                complete: function() {
                    // Remove loading state
                    $card.removeClass('loading');
                    $checkbox.prop('disabled', false);
                }
            });
        },
        
        /**
         * Handle reset settings
         */
        handleResetSettings: function(e) {
            e.preventDefault();
            
            if (!confirm(voxelToolkit.strings.confirmReset)) {
                return;
            }
            
            const $button = $(e.target);
            const originalText = $button.text();
            
            // Add loading state
            $button.prop('disabled', true).text('Resetting...');
            
            // Make AJAX request
            $.ajax({
                url: voxelToolkit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'voxel_toolkit_reset_settings',
                    nonce: voxelToolkit.nonce
                },
                success: function(response) {
                    if (response.success) {
                        VoxelToolkitAdmin.showNotice(voxelToolkit.strings.settingsReset, 'success');
                        
                        // Refresh page after a short delay
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        VoxelToolkitAdmin.showNotice(response.data || voxelToolkit.strings.error, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    VoxelToolkitAdmin.showNotice(voxelToolkit.strings.error, 'error');
                    console.error('AJAX Error:', error);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Handle AJAX errors globally
         */
        handleAjaxError: function(event, xhr, settings, error) {
            if (settings.url && settings.url.indexOf(voxelToolkit.ajaxUrl) !== -1) {
                console.error('Global AJAX Error:', {
                    url: settings.url,
                    status: xhr.status,
                    error: error,
                    response: xhr.responseText
                });
            }
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(message, type = 'info') {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible voxel-toolkit-notice"><p>' + message + '</p></div>');
            
            // Insert after h1
            $('.wrap h1').first().after($notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Make notice dismissible
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        },
        
        /**
         * Validate form before submission
         */
        validateForm: function($form) {
            let isValid = true;
            
            // Add custom validation rules here
            $form.find('input[required], select[required]').each(function() {
                const $field = $(this);
                if (!$field.val().trim()) {
                    $field.addClass('error');
                    isValid = false;
                } else {
                    $field.removeClass('error');
                }
            });
            
            return isValid;
        },
        
        /**
         * Handle settings form submission
         */
        handleSettingsSubmit: function(e) {
            const $form = $(e.target);
            
            if (!this.validateForm($form)) {
                e.preventDefault();
                this.showNotice('Please fill in all required fields.', 'error');
                return false;
            }
            
            // Show loading indicator
            $form.find('input[type="submit"]').prop('disabled', true).val('Saving...');
            
            return true;
        },
        
        /**
         * Handle search input - search as user types
         */
        handleSearch: function(e) {
            // Apply filters when search changes
            this.applyFilters();
        },
        
        /**
         * Handle filter change
         */
        handleFilterChange: function(e) {
            // Apply filters when radio button changes
            this.applyFilters();
        },
        
        /**
         * Apply both search and filter
         */
        applyFilters: function() {
            const searchTerm = $('#voxel-toolkit-search').val().toLowerCase().trim();
            const filterValue = $('input[name="function-filter"]:checked').val();
            
            const $functions = $('.voxel-toolkit-function-card');
            const $resultsInfo = $('#controls-results-info');
            let visibleCount = 0;
            let totalCount = $functions.length;
            
            console.log('Applying filters - Search:', searchTerm, 'Filter:', filterValue); // Debug
            
            // Filter through functions
            $functions.each(function() {
                const $card = $(this);
                const functionName = ($card.data('function-name') || '').toString().toLowerCase();
                const functionDescription = ($card.data('function-description') || '').toString().toLowerCase();
                const functionKey = ($card.data('function-key') || '').toString().toLowerCase();
                const isEnabled = $card.hasClass('enabled');
                const isAlwaysEnabled = $card.hasClass('always-enabled');
                
                // Also search in the visible text content
                const cardText = $card.text().toLowerCase();
                
                // Check search criteria
                let matchesSearch = true;
                if (searchTerm !== '') {
                    matchesSearch = functionName.includes(searchTerm) || 
                                  functionDescription.includes(searchTerm) ||
                                  functionKey.includes(searchTerm) ||
                                  cardText.includes(searchTerm);
                }
                
                // Check filter criteria
                let matchesFilter = true;
                if (filterValue === 'enabled') {
                    matchesFilter = isEnabled || isAlwaysEnabled;
                } else if (filterValue === 'disabled') {
                    matchesFilter = !isEnabled && !isAlwaysEnabled;
                }
                // 'all' matches everything, so no additional check needed
                
                const shouldShow = matchesSearch && matchesFilter;
                
                console.log('Function:', functionKey, 'Enabled:', isEnabled, 'Always:', isAlwaysEnabled, 'Matches search:', matchesSearch, 'Matches filter:', matchesFilter, 'Show:', shouldShow); // Debug
                
                if (shouldShow) {
                    $card.show();
                    visibleCount++;
                } else {
                    $card.hide();
                }
            });
            
            console.log('Visible count:', visibleCount); // Debug
            
            // Update results info
            this.updateResultsInfo(searchTerm, filterValue, visibleCount, totalCount);
        },
        
        /**
         * Update results information
         */
        updateResultsInfo: function(searchTerm, filterValue, visibleCount, totalCount) {
            const $resultsInfo = $('#controls-results-info');
            
            if (visibleCount === 0) {
                let message = 'No functions found';
                if (searchTerm && filterValue !== 'all') {
                    const filterText = filterValue === 'enabled' ? 'enabled' : 'disabled';
                    message += ' for "' + searchTerm + '" in ' + filterText + ' functions';
                } else if (searchTerm) {
                    message += ' for "' + searchTerm + '"';
                } else if (filterValue === 'enabled') {
                    message = 'No enabled functions found';
                } else if (filterValue === 'disabled') {
                    message = 'No disabled functions found';
                }
                $resultsInfo.html('<div class="results-message no-results"><span class="dashicons dashicons-warning"></span>' + message + '</div>');
            } else if (visibleCount === totalCount && !searchTerm && filterValue === 'all') {
                $resultsInfo.html('<div class="results-message all-showing"><span class="dashicons dashicons-yes"></span>Showing all ' + totalCount + ' functions</div>');
            } else {
                const resultsText = visibleCount === 1 ? 'function' : 'functions';
                let message = 'Showing ' + visibleCount + ' of ' + totalCount + ' ' + resultsText;
                
                const conditions = [];
                if (searchTerm) conditions.push('matching "' + searchTerm + '"');
                if (filterValue === 'enabled') conditions.push('enabled only');
                if (filterValue === 'disabled') conditions.push('disabled only');
                
                if (conditions.length > 0) {
                    message += ' (' + conditions.join(', ') + ')';
                }
                
                $resultsInfo.html('<div class="results-message filtered-results"><span class="dashicons dashicons-filter"></span>' + message + '</div>');
            }
        },
        
        /**
         * Handle controls reset
         */
        handleControlsReset: function(e) {
            e.preventDefault();
            
            const $searchInput = $('#voxel-toolkit-search');
            const $allRadio = $('input[name="function-filter"][value="all"]');
            
            console.log('Resetting all controls'); // Debug
            
            // Clear search input and reset filter
            $searchInput.val('');
            $allRadio.prop('checked', true);
            
            // Re-apply filters (which will show all functions)
            this.applyFilters();
            
            // Focus on search input
            $searchInput.focus();
        }
    };
    
    // Export for global access
    window.VoxelToolkitAdmin = VoxelToolkitAdmin;
    
})(jQuery);
// ===================================
// Widgets Page - Enhanced Functionality
// ===================================

jQuery(document).ready(function($) {
    'use strict';

    // Check if we're on the widgets page
    if (!$('.voxel-toolkit-widgets-page').length) {
        return;
    }

    // Search functionality
    const $searchInput = $('#voxel-widgets-search');
    const $widgetCards = $('.voxel-toolkit-widget-card');
    const $noResults = $('.voxel-toolkit-no-results');
    const $widgetsGrid = $('.voxel-toolkit-widgets-grid');

    $searchInput.on('input', function() {
        const searchTerm = $(this).val().toLowerCase().trim();

        if (searchTerm === '') {
            // Show all widgets
            $widgetCards.removeClass('hidden').show();
            $noResults.hide();
        } else {
            let visibleCount = 0;

            $widgetCards.each(function() {
                const $card = $(this);
                const widgetName = $card.data('widget-name') || '';
                const widgetDescription = $card.data('widget-description') || '';

                if (widgetName.includes(searchTerm) || widgetDescription.includes(searchTerm)) {
                    $card.removeClass('hidden').show();
                    visibleCount++;
                } else {
                    $card.addClass('hidden').hide();
                }
            });

            // Show/hide no results message
            if (visibleCount === 0) {
                $noResults.show();
            } else {
                $noResults.hide();
            }
        }
    });

    // Individual widget toggle
    $('.voxel-toolkit-widget-toggle input').on('change', function() {
        const $checkbox = $(this);
        const widgetKey = $checkbox.data('widget');
        const enabled = $checkbox.is(':checked');
        const $card = $checkbox.closest('.voxel-toolkit-widget-card');
        const $badge = $card.find('.voxel-toolkit-widget-badge');

        // Disable checkbox during request
        $checkbox.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'voxel_toolkit_toggle_widget',
                widget_key: widgetKey,
                enabled: enabled ? 1 : 0,
                nonce: voxelToolkitAdmin.widgetNonce
            },
            success: function(response) {
                if (response.success) {
                    // Update badge
                    if (enabled) {
                        $badge.removeClass('voxel-toolkit-badge-disabled')
                              .addClass('voxel-toolkit-badge-enabled')
                              .text(voxelToolkitAdmin.i18n.enabled || 'Enabled');
                    } else {
                        $badge.removeClass('voxel-toolkit-badge-enabled')
                              .addClass('voxel-toolkit-badge-disabled')
                              .text(voxelToolkitAdmin.i18n.disabled || 'Disabled');
                    }

                    // Show success message
                    showNotice(response.data.message, 'success');
                } else {
                    // Revert toggle
                    $checkbox.prop('checked', !enabled);
                    showNotice(response.data.message || 'Error updating widget status', 'error');
                }
            },
            error: function() {
                // Revert toggle
                $checkbox.prop('checked', !enabled);
                showNotice('Error updating widget status', 'error');
            },
            complete: function() {
                // Re-enable checkbox
                $checkbox.prop('disabled', false);
            }
        });
    });

    // Bulk enable all widgets
    $('#voxel-widgets-enable-all').on('click', function() {
        const $button = $(this);

        if ($button.prop('disabled')) {
            return;
        }

        if (!confirm(voxelToolkitAdmin.i18n.confirmEnableAll || 'Enable all widgets?')) {
            return;
        }

        bulkToggleWidgets('enable', $button);
    });

    // Bulk disable all widgets
    $('#voxel-widgets-disable-all').on('click', function() {
        const $button = $(this);

        if ($button.prop('disabled')) {
            return;
        }

        if (!confirm(voxelToolkitAdmin.i18n.confirmDisableAll || 'Disable all widgets?')) {
            return;
        }

        bulkToggleWidgets('disable', $button);
    });

    // Bulk toggle function
    function bulkToggleWidgets(action, $button) {
        const originalText = $button.text();
        const $allButtons = $('#voxel-widgets-enable-all, #voxel-widgets-disable-all');

        // Disable buttons
        $allButtons.prop('disabled', true);
        $button.text(action === 'enable' ? 'Enabling...' : 'Disabling...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'voxel_toolkit_bulk_toggle_widgets',
                bulk_action: action,
                nonce: voxelToolkitAdmin.widgetNonce
            },
            success: function(response) {
                if (response.success) {
                    // Update all widget toggles and badges
                    const isEnabled = action === 'enable';

                    $('.voxel-toolkit-widget-toggle input').each(function() {
                        $(this).prop('checked', isEnabled);
                    });

                    $('.voxel-toolkit-widget-badge').each(function() {
                        const $badge = $(this);
                        if (isEnabled) {
                            $badge.removeClass('voxel-toolkit-badge-disabled')
                                  .addClass('voxel-toolkit-badge-enabled')
                                  .text(voxelToolkitAdmin.i18n.enabled || 'Enabled');
                        } else {
                            $badge.removeClass('voxel-toolkit-badge-enabled')
                                  .addClass('voxel-toolkit-badge-disabled')
                                  .text(voxelToolkitAdmin.i18n.disabled || 'Disabled');
                        }
                    });

                    showNotice(response.data.message, 'success');

                    // Reload page after a short delay to reflect changes
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice(response.data.message || 'Error updating widgets', 'error');
                }
            },
            error: function() {
                showNotice('Error updating widgets', 'error');
            },
            complete: function() {
                // Re-enable buttons
                $allButtons.prop('disabled', false);
                $button.text(originalText);
            }
        });
    }

    // Show admin notice
    function showNotice(message, type) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');

        $('.voxel-toolkit-widgets-page h1').after($notice);

        // Auto dismiss after 3 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);

        // Handle manual dismiss
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        });
    }

    // Usage badge click handler (optional - can expand later to show actual pages)
    $('.voxel-toolkit-usage-badge').on('click', function() {
        const $badge = $(this);
        if ($badge.hasClass('voxel-toolkit-usage-none')) {
            return;
        }

        const widgetKey = $badge.data('widget');
        // For now, just show a simple message
        // In the future, this could open a modal showing which pages use this widget
        alert('This widget is being used. Page list feature coming soon!');
    });
});
