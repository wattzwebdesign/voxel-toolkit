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
                            const settingsUrl = window.location.origin + window.location.pathname + '?page=voxel-toolkit-settings#' + functionKey;
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
            // Only run if search element exists (Functions page)
            if (!$('#voxel-toolkit-search').length) {
                return;
            }

            const searchTerm = $('#voxel-toolkit-search').val().toLowerCase().trim();
            const filterValue = $('input[name="function-filter"]:checked').val();
            
            const $functions = $('.voxel-toolkit-function-card');
            const $resultsInfo = $('#controls-results-info');
            let visibleCount = 0;
            let totalCount = $functions.length;

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

                if (shouldShow) {
                    $card.show();
                    visibleCount++;
                } else {
                    $card.hide();
                }
            });

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

    // Usage badge click handler - show page list modal
    $('.voxel-toolkit-usage-badge').on('click', function(e) {
        e.preventDefault();
        const $badge = $(this);

        if ($badge.hasClass('voxel-toolkit-usage-none')) {
            return;
        }

        const widgetKey = $badge.data('widget');
        const widgetName = $badge.closest('.voxel-toolkit-widget-card').find('.voxel-toolkit-widget-title').text();

        showWidgetUsageModal(widgetKey, widgetName);
    });

    // Show widget usage modal
    function showWidgetUsageModal(widgetKey, widgetName) {
        // Show loading modal first
        const $modal = createUsageModal(widgetName, true);
        $('body').append($modal);
        $modal.fadeIn(200);

        // Fetch usage data
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'voxel_toolkit_get_widget_usage',
                widget_key: widgetKey,
                nonce: voxelToolkitAdmin.widgetNonce
            },
            success: function(response) {
                if (response.success) {
                    updateModalContent($modal, response.data.pages, widgetName);
                } else {
                    updateModalContent($modal, [], widgetName, response.data.message);
                }
            },
            error: function() {
                updateModalContent($modal, [], widgetName, 'Error loading widget usage data.');
            }
        });
    }

    // Create usage modal HTML
    function createUsageModal(widgetName, loading) {
        const $modal = $('<div class="voxel-toolkit-modal-overlay"></div>');
        const $content = $('<div class="voxel-toolkit-modal"></div>');

        $content.html(`
            <div class="voxel-toolkit-modal-header">
                <h2>${widgetName} - Widget Usage</h2>
                <button class="voxel-toolkit-modal-close" aria-label="Close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="voxel-toolkit-modal-body">
                ${loading ? '<div class="voxel-toolkit-modal-loading"><span class="spinner is-active"></span> Loading...</div>' : ''}
            </div>
        `);

        $modal.append($content);

        // Close modal on overlay click
        $modal.on('click', function(e) {
            if ($(e.target).hasClass('voxel-toolkit-modal-overlay')) {
                $modal.fadeOut(200, function() {
                    $(this).remove();
                    $(document).off('keydown.voxelModal');
                });
            }
        });

        // Close modal on X button click
        $modal.on('click', '.voxel-toolkit-modal-close', function(e) {
            e.preventDefault();
            $modal.fadeOut(200, function() {
                $(this).remove();
                $(document).off('keydown.voxelModal');
            });
        });

        // Close on escape key
        $(document).on('keydown.voxelModal', function(e) {
            if (e.key === 'Escape') {
                $modal.find('.voxel-toolkit-modal-close').click();
                $(document).off('keydown.voxelModal');
            }
        });

        return $modal;
    }

    // Update modal content with page list
    function updateModalContent($modal, pages, widgetName, errorMessage) {
        const $body = $modal.find('.voxel-toolkit-modal-body');

        if (errorMessage) {
            $body.html(`<div class="voxel-toolkit-modal-empty"><p>${errorMessage}</p></div>`);
            return;
        }

        if (pages.length === 0) {
            $body.html(`
                <div class="voxel-toolkit-modal-empty">
                    <span class="dashicons dashicons-admin-page"></span>
                    <p>This widget is not currently used on any pages.</p>
                </div>
            `);
            return;
        }

        let html = '<div class="voxel-toolkit-usage-list">';
        html += `<p class="voxel-toolkit-usage-count">Found in <strong>${pages.length}</strong> ${pages.length === 1 ? 'page' : 'pages'}:</p>`;
        html += '<ul class="voxel-toolkit-page-list">';

        pages.forEach(function(page) {
            const statusClass = page.status === 'publish' ? 'status-publish' : 'status-draft';
            const statusLabel = page.status === 'publish' ? 'Published' : page.status.charAt(0).toUpperCase() + page.status.slice(1);

            html += `
                <li class="voxel-toolkit-page-item">
                    <div class="voxel-toolkit-page-info">
                        <h4 class="voxel-toolkit-page-title">${page.title}</h4>
                        <div class="voxel-toolkit-page-meta">
                            <span class="voxel-toolkit-page-type">${page.type}</span>
                            <span class="voxel-toolkit-page-status ${statusClass}">${statusLabel}</span>
                            <span class="voxel-toolkit-page-id">ID: ${page.id}</span>
                        </div>
                    </div>
                    <div class="voxel-toolkit-page-actions">
                        <a href="${page.elementor_edit_link}" class="button button-primary button-small" target="_blank">
                            <span class="dashicons dashicons-edit"></span> Edit with Elementor
                        </a>
                        <a href="${page.view_link}" class="button button-secondary button-small" target="_blank">
                            <span class="dashicons dashicons-visibility"></span> View
                        </a>
                    </div>
                </li>
            `;
        });

        html += '</ul></div>';
        $body.html(html);
    }
});

// ===================================
// Settings Page - Tab Navigation & AJAX Save
// ===================================

jQuery(document).ready(function($) {
    'use strict';

    // Check if we're on the settings page
    if (!$('.vt-settings-container').length) {
        return;
    }

    const VTSettings = {
        init: function() {
            this.bindEvents();
            this.handleURLHash();
        },

        bindEvents: function() {
            // Tab clicking
            $(document).on('click', '.vt-settings-tab', this.handleTabClick.bind(this));

            // Search filtering
            $(document).on('input', '#vt-settings-search', this.handleSearch.bind(this));

            // Save button click
            $(document).on('click', '.vt-settings-save-btn', this.handleSave.bind(this));

            // Form submit prevention (we use AJAX instead)
            $(document).on('submit', '.vt-settings-form', function(e) {
                e.preventDefault();
            });
        },

        handleTabClick: function(e) {
            const $tab = $(e.currentTarget);
            const tabKey = $tab.data('tab');

            // Update active tab
            $('.vt-settings-tab').removeClass('active');
            $tab.addClass('active');

            // Update active panel
            $('.vt-settings-panel').removeClass('active');
            $(`.vt-settings-panel[data-panel="${tabKey}"]`).addClass('active');

            // Update URL hash
            window.history.replaceState(null, null, '#' + tabKey);
        },

        handleSearch: function(e) {
            const searchTerm = $(e.target).val().toLowerCase().trim();
            const $tabs = $('.vt-settings-tab');
            const $noResults = $('.vt-settings-no-results');
            let visibleCount = 0;
            let firstVisibleTab = null;

            $tabs.each(function() {
                const $tab = $(this);
                const tabText = $tab.text().toLowerCase();
                const tabKey = $tab.data('tab');

                if (searchTerm === '' || tabText.includes(searchTerm)) {
                    $tab.removeClass('hidden');
                    visibleCount++;
                    if (!firstVisibleTab) {
                        firstVisibleTab = $tab;
                    }
                } else {
                    $tab.removeClass('active').addClass('hidden');
                }
            });

            // Show/hide no results message
            if (visibleCount === 0) {
                $noResults.show();
                $('.vt-settings-panel').removeClass('active');
            } else {
                $noResults.hide();

                // If current active tab is hidden, switch to first visible
                const $activeTab = $('.vt-settings-tab.active:not(.hidden)');
                if ($activeTab.length === 0 && firstVisibleTab) {
                    firstVisibleTab.click();
                }
            }
        },

        handleSave: function(e) {
            const $button = $(e.currentTarget);
            const functionKey = $button.data('function');
            const $panel = $(`.vt-settings-panel[data-panel="${functionKey}"]`);
            const $form = $panel.find('.vt-settings-form');
            const $status = $panel.find('.vt-settings-save-status');
            const $saveText = $button.find('.vt-save-text');
            const $icon = $button.find('.dashicons');

            // Prevent double-click
            if ($button.prop('disabled')) {
                return;
            }

            // Build settings object from form
            const settings = {};
            settings[functionKey] = {
                _form_submitted: '1' // Marker to ensure data is sent even when all arrays are empty
            };

            // First, find all checkboxes and initialize them
            $form.find('input[type="checkbox"]').each(function() {
                const $cb = $(this);
                const name = $cb.attr('name');
                if (!name) return;

                // Handle array checkboxes (e.g., post_types[])
                const matchArray = name.match(/^voxel_toolkit_options\[([^\]]+)\]\[([^\]]+)\]\[\]$/);
                if (matchArray) {
                    const fKey = matchArray[1];
                    const settingKey = matchArray[2];

                    if (!settings[fKey]) {
                        settings[fKey] = {};
                    }
                    // Initialize as empty array
                    if (!settings[fKey][settingKey]) {
                        settings[fKey][settingKey] = [];
                    }
                    // Add value if checked
                    if ($cb.is(':checked')) {
                        settings[fKey][settingKey].push($cb.val());
                    }
                    return;
                }

                // Handle 5-level checkbox: voxel_toolkit_options[func][n1][n2][n3][key]
                const match5LevelCb = name.match(/^voxel_toolkit_options\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]$/);
                if (match5LevelCb) {
                    const fKey = match5LevelCb[1];
                    const n1 = match5LevelCb[2];
                    const n2 = match5LevelCb[3];
                    const n3 = match5LevelCb[4];
                    const subKey = match5LevelCb[5];

                    if (!settings[fKey]) settings[fKey] = {};
                    if (!settings[fKey][n1]) settings[fKey][n1] = {};
                    if (!settings[fKey][n1][n2]) settings[fKey][n1][n2] = {};
                    if (!settings[fKey][n1][n2][n3]) settings[fKey][n1][n2][n3] = {};
                    // Use actual value if checked, empty string if not (for 'yes' type checkboxes)
                    settings[fKey][n1][n2][n3][subKey] = $cb.is(':checked') ? $cb.val() : '';
                    return;
                }

                // Handle 4-level checkbox: voxel_toolkit_options[func][n1][n2][key]
                const match4LevelCb = name.match(/^voxel_toolkit_options\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]$/);
                if (match4LevelCb) {
                    const fKey = match4LevelCb[1];
                    const n1 = match4LevelCb[2];
                    const n2 = match4LevelCb[3];
                    const subKey = match4LevelCb[4];

                    if (!settings[fKey]) settings[fKey] = {};
                    if (!settings[fKey][n1]) settings[fKey][n1] = {};
                    if (!settings[fKey][n1][n2]) settings[fKey][n1][n2] = {};
                    // Use '1' if checked, '0' if not (standard checkbox pattern)
                    settings[fKey][n1][n2][subKey] = $cb.is(':checked') ? '1' : '0';
                    return;
                }

                // Handle single checkboxes (e.g., disable_plugin_updates with value="1")
                const matchSingle = name.match(/^voxel_toolkit_options\[([^\]]+)\]\[([^\]]+)\]$/);
                if (matchSingle) {
                    const fKey = matchSingle[1];
                    const settingKey = matchSingle[2];

                    if (!settings[fKey]) {
                        settings[fKey] = {};
                    }
                    // Send "1" if checked, "0" if not
                    settings[fKey][settingKey] = $cb.is(':checked') ? '1' : '0';
                }
            });

            // Now collect all other form values (text inputs, selects, etc.)
            const formData = new FormData($form[0]);

            formData.forEach((value, key) => {
                // Skip nonce, function_key, and checkbox fields (already handled above)
                if (key === 'vt_settings_nonce' || key === '_wp_http_referer' || key === 'function_key') {
                    return;
                }

                // Skip checkbox fields - already handled
                const isCheckbox = $form.find(`input[type="checkbox"][name="${key}"]`).length > 0;
                if (isCheckbox) {
                    return;
                }

                // Handle array text inputs: voxel_toolkit_options[func][setting][]
                const matchArray = key.match(/^voxel_toolkit_options\[([^\]]+)\]\[([^\]]+)\]\[\]$/);
                if (matchArray) {
                    const fKey = matchArray[1];
                    const settingKey = matchArray[2];

                    if (!settings[fKey]) {
                        settings[fKey] = {};
                    }
                    if (!settings[fKey][settingKey]) {
                        settings[fKey][settingKey] = [];
                    }
                    // Only add non-empty values
                    if (value.trim() !== '') {
                        settings[fKey][settingKey].push(value);
                    }
                    return;
                }

                // Handle 5-level nesting: voxel_toolkit_options[func][nested1][nested2][nested3][key]
                const match5Level = key.match(/^voxel_toolkit_options\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]$/);
                if (match5Level) {
                    const fKey = match5Level[1];
                    const nestedKey1 = match5Level[2];
                    const nestedKey2 = match5Level[3];
                    const nestedKey3 = match5Level[4];
                    const subKey = match5Level[5];

                    if (!settings[fKey]) settings[fKey] = {};
                    if (!settings[fKey][nestedKey1]) settings[fKey][nestedKey1] = {};
                    if (!settings[fKey][nestedKey1][nestedKey2]) settings[fKey][nestedKey1][nestedKey2] = {};
                    if (!settings[fKey][nestedKey1][nestedKey2][nestedKey3]) settings[fKey][nestedKey1][nestedKey2][nestedKey3] = {};
                    settings[fKey][nestedKey1][nestedKey2][nestedKey3][subKey] = value;
                    return;
                }

                // Handle 4-level nesting: voxel_toolkit_options[func][nested1][nested2][key]
                const match4Level = key.match(/^voxel_toolkit_options\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]$/);
                if (match4Level) {
                    const fKey = match4Level[1];
                    const nestedKey1 = match4Level[2];
                    const nestedKey2 = match4Level[3];
                    const subKey = match4Level[4];

                    if (!settings[fKey]) settings[fKey] = {};
                    if (!settings[fKey][nestedKey1]) settings[fKey][nestedKey1] = {};
                    if (!settings[fKey][nestedKey1][nestedKey2]) settings[fKey][nestedKey1][nestedKey2] = {};
                    settings[fKey][nestedKey1][nestedKey2][subKey] = value;
                    return;
                }

                // Handle 3-level nesting: voxel_toolkit_options[func][nested][key]
                const match3Level = key.match(/^voxel_toolkit_options\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]$/);
                if (match3Level) {
                    const fKey = match3Level[1];
                    const nestedKey = match3Level[2];
                    const subKey = match3Level[3];

                    if (!settings[fKey]) settings[fKey] = {};
                    if (!settings[fKey][nestedKey]) settings[fKey][nestedKey] = {};
                    settings[fKey][nestedKey][subKey] = value;
                    return;
                }

                // Handle 2-level nesting: voxel_toolkit_options[func][setting]
                const matchSingle = key.match(/^voxel_toolkit_options\[([^\]]+)\]\[([^\]]+)\]$/);
                if (matchSingle) {
                    const fKey = matchSingle[1];
                    const settingKey = matchSingle[2];

                    if (!settings[fKey]) {
                        settings[fKey] = {};
                    }
                    settings[fKey][settingKey] = value;
                }
            });

            // Set loading state
            $button.prop('disabled', true).addClass('saving');
            $saveText.text('Saving...');
            $icon.removeClass('dashicons-saved dashicons-yes dashicons-no').addClass('dashicons-update');
            $status.removeClass('success error').empty();

            // Make AJAX request
            $.ajax({
                url: voxelToolkit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_save_function_settings',
                    nonce: $form.find('input[name="vt_settings_nonce"]').val(),
                    function_key: functionKey,
                    settings: settings
                },
                success: function(response) {
                    if (response.success) {
                        // Success state
                        $icon.removeClass('dashicons-update').addClass('dashicons-yes');
                        $saveText.text('Saved!');
                        $status.addClass('success').html('<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message);

                        // Reset after delay
                        setTimeout(function() {
                            $saveText.text('Save Settings');
                            $icon.removeClass('dashicons-yes').addClass('dashicons-saved');
                            $status.fadeOut(300, function() {
                                $(this).empty().show();
                            });
                        }, 2000);
                    } else {
                        // Error state
                        $icon.removeClass('dashicons-update').addClass('dashicons-no');
                        $saveText.text('Error');
                        $status.addClass('error').html('<span class="dashicons dashicons-warning"></span> ' + (response.data.message || 'Save failed'));

                        // Reset after delay
                        setTimeout(function() {
                            $saveText.text('Save Settings');
                            $icon.removeClass('dashicons-no').addClass('dashicons-saved');
                        }, 3000);
                    }
                },
                error: function(xhr, status, error) {
                    // Error state
                    $icon.removeClass('dashicons-update').addClass('dashicons-no');
                    $saveText.text('Error');
                    $status.addClass('error').html('<span class="dashicons dashicons-warning"></span> Connection error');

                    console.error('Settings save error:', error);

                    // Reset after delay
                    setTimeout(function() {
                        $saveText.text('Save Settings');
                        $icon.removeClass('dashicons-no').addClass('dashicons-saved');
                    }, 3000);
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('saving');
                }
            });
        },

        handleURLHash: function() {
            // Check if there's a hash in the URL
            const hash = window.location.hash.replace('#', '');

            if (hash) {
                // Small delay to ensure tabs are fully rendered
                setTimeout(function() {
                    const $tab = $(`.vt-settings-tab[data-tab="${hash}"]`);
                    if ($tab.length) {
                        // Update active tab
                        $('.vt-settings-tab').removeClass('active');
                        $tab.addClass('active');

                        // Update active panel
                        $('.vt-settings-panel').removeClass('active');
                        $(`.vt-settings-panel[data-panel="${hash}"]`).addClass('active');

                        // Scroll tab into view if needed
                        $tab[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }, 150);
            }
        }
    };

    // Initialize
    VTSettings.init();
});

/**
 * Tools Page - Duplicate Post Fields functionality
 */
(function($) {
    'use strict';

    var VTTools = {
        init: function() {
            // Only initialize on Tools page
            if ($('#vt-duplicate-fields-form').length === 0) {
                return;
            }

            this.bindEvents();
            this.updateButtonState();
        },

        bindEvents: function() {
            var self = this;

            // Source post type change - load fields
            $('#vt-source-post-type').on('change', function() {
                self.loadFields($(this).val());
            });

            // Destination post type change - update button state
            $('#vt-dest-post-type').on('change', function() {
                self.updateButtonState();
            });

            // Key suffix change - update button state
            $('#vt-key-suffix').on('input', function() {
                self.updateButtonState();
            });

            // Field checkbox change - update button state
            $(document).on('change', 'input[name="vt_fields[]"]', function() {
                self.updateButtonState();
            });

            // Select all fields
            $('#vt-select-all-fields').on('click', function() {
                $('#vt-fields-list input[type="checkbox"]').prop('checked', true);
                self.updateButtonState();
            });

            // Deselect all fields
            $('#vt-deselect-all-fields').on('click', function() {
                $('#vt-fields-list input[type="checkbox"]').prop('checked', false);
                self.updateButtonState();
            });

            // Form submit
            $('#vt-duplicate-fields-form').on('submit', function(e) {
                e.preventDefault();
                self.duplicateFields();
            });

            // Tab switching (reuse existing settings page logic)
            $('.vt-settings-tab').on('click', function() {
                var tabKey = $(this).data('tab');

                // Update active states
                $('.vt-settings-tab').removeClass('active');
                $(this).addClass('active');

                $('.vt-settings-panel').removeClass('active');
                $('.vt-settings-panel[data-panel="' + tabKey + '"]').addClass('active');

                // Update URL hash
                if (history.replaceState) {
                    history.replaceState(null, null, '#' + tabKey);
                }
            });
        },

        loadFields: function(postType) {
            var self = this;
            var $fieldsRow = $('#vt-fields-row');
            var $fieldsList = $('#vt-fields-list');
            var $result = $('#vt-duplicate-result');

            // Hide result
            $result.hide().removeClass('success warning error').empty();

            if (!postType) {
                $fieldsRow.hide();
                $fieldsList.empty();
                self.updateButtonState();
                return;
            }

            // Show loading state
            $fieldsList.html('<p class="description" style="padding: 15px;">Loading fields...</p>');
            $fieldsRow.show();

            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: {
                    action: 'vt_get_post_type_fields',
                    post_type: postType,
                    nonce: $('#vt_duplicate_fields_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        $fieldsList.html(response.data.html);
                        self.updateButtonState();
                    } else {
                        $fieldsList.html('<p class="description" style="padding: 15px; color: #dc2626;">' + (response.data.message || 'Error loading fields.') + '</p>');
                    }
                },
                error: function() {
                    $fieldsList.html('<p class="description" style="padding: 15px; color: #dc2626;">Failed to load fields. Please try again.</p>');
                }
            });
        },

        updateButtonState: function() {
            var source = $('#vt-source-post-type').val();
            var dest = $('#vt-dest-post-type').val();
            var suffix = $('#vt-key-suffix').val().trim();
            var selectedFields = $('input[name="vt_fields[]"]:checked').length;

            var isValid = source && dest && source !== dest && suffix && selectedFields > 0;

            $('#vt-duplicate-btn').prop('disabled', !isValid);
        },

        duplicateFields: function() {
            var self = this;
            var $form = $('#vt-duplicate-fields-form');
            var $button = $('#vt-duplicate-btn');
            var $spinner = $('#vt-duplicate-spinner');
            var $result = $('#vt-duplicate-result');

            var source = $('#vt-source-post-type').val();
            var dest = $('#vt-dest-post-type').val();
            var suffix = $('#vt-key-suffix').val().trim();
            var fields = [];

            $('input[name="vt_fields[]"]:checked').each(function() {
                fields.push($(this).val());
            });

            // Validation
            if (!source || !dest) {
                self.showResult('error', 'Please select both source and destination post types.');
                return;
            }

            if (source === dest) {
                self.showResult('error', 'Source and destination post types cannot be the same.');
                return;
            }

            if (!suffix) {
                self.showResult('error', 'Key suffix is required.');
                return;
            }

            if (fields.length === 0) {
                self.showResult('error', 'Please select at least one field to copy.');
                return;
            }

            // Check for singleton fields
            var singletonFields = [];
            $('input[name="vt_fields[]"]:checked').each(function() {
                if ($(this).data('singleton') === 1) {
                    var label = $(this).closest('label').find('.vt-field-label').text();
                    var type = $(this).data('type');
                    singletonFields.push(label + ' (' + type + ')');
                }
            });

            // Confirmation
            var sourceLabel = $('#vt-source-post-type option:selected').text().trim();
            var destLabel = $('#vt-dest-post-type option:selected').text().trim();

            var confirmMsg = 'Copy ' + fields.length + ' field(s) from ' + sourceLabel + ' to ' + destLabel + '?\n\nKey suffix: ' + suffix;

            if (singletonFields.length > 0) {
                confirmMsg += '\n\nWarning: You selected ' + singletonFields.length + ' singleton field(s):\n• ' + singletonFields.join('\n• ') + '\n\nThese will be skipped if the destination already has this field type.';
            }

            if (!confirm(confirmMsg)) {
                return;
            }

            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.hide();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'vt_duplicate_post_fields',
                    nonce: $('#vt_duplicate_fields_nonce').val(),
                    source: source,
                    destination: dest,
                    suffix: suffix,
                    fields: fields
                },
                success: function(response) {
                    if (response.success) {
                        var status = response.data.status || 'success';
                        self.showResult(status, response.data.message);

                        // If successful, clear field selection
                        if (status === 'success') {
                            $('input[name="vt_fields[]"]').prop('checked', false);
                            self.updateButtonState();
                        }
                    } else {
                        self.showResult('error', response.data.message || 'An error occurred.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Duplicate fields error:', error);
                    self.showResult('error', 'Connection error. Please try again.');
                },
                complete: function() {
                    $spinner.removeClass('is-active');
                    self.updateButtonState();
                }
            });
        },

        showResult: function(status, message) {
            var $result = $('#vt-duplicate-result');
            $result
                .removeClass('success warning error')
                .addClass(status)
                .html(message)
                .show();

            // Scroll to result
            $('html, body').animate({
                scrollTop: $result.offset().top - 100
            }, 300);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        VTTools.init();
    });
})(jQuery);

/**
 * Media Uploader for Settings Page
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle upload button clicks
        $(document).on('click', '.vt-upload-button', function(e) {
            e.preventDefault();

            var $button = $(this);
            var targetId = $button.data('target');
            var $input = $('#' + targetId);

            // Create media frame
            var frame = wp.media({
                title: 'Select or Upload Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });

            // When an image is selected
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.url).trigger('change');

                // Update preview if it exists
                var $preview = $input.siblings('div').find('img');
                if ($preview.length) {
                    $preview.attr('src', attachment.url);
                } else {
                    // Create preview if it doesn't exist
                    $input.after('<div style="margin-top: 10px;"><img src="' + attachment.url + '" style="max-width: 60px; max-height: 60px; border-radius: 50%;"></div>');
                }
            });

            frame.open();
        });

        // Handle URL input changes (for manual URL entry)
        $(document).on('change', '#ai_bot_avatar', function() {
            var $input = $(this);
            var url = $input.val().trim();
            var $previewContainer = $input.siblings('div').first();

            if (url) {
                if ($previewContainer.find('img').length) {
                    $previewContainer.find('img').attr('src', url);
                } else {
                    $input.after('<div style="margin-top: 10px;"><img src="' + url + '" style="max-width: 60px; max-height: 60px; border-radius: 50%;"></div>');
                }
            } else {
                $previewContainer.remove();
            }
        });
    });
})(jQuery);
