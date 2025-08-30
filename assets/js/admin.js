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
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Function toggle switches
            $(document).on('change', '.function-toggle-checkbox', this.handleFunctionToggle.bind(this));
            
            // Reset settings button
            $(document).on('click', '#reset-all-settings', this.handleResetSettings.bind(this));
            
            // Search functionality
            $(document).on('input', '#voxel-toolkit-search', this.handleSearch.bind(this));
            $(document).on('click', '#voxel-toolkit-search-reset', this.handleSearchReset.bind(this));
            
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
            const $input = $(e.target);
            const searchTerm = $input.val().toLowerCase().trim();
            
            // Search immediately as user types
            this.performSearch(searchTerm);
        },
        
        /**
         * Perform the actual search
         */
        performSearch: function(searchTerm) {
            const $functions = $('.voxel-toolkit-function-card');
            const $resultsInfo = $('#search-results-info');
            let visibleCount = 0;
            let totalCount = $functions.length;
            
            console.log('Searching for:', searchTerm, 'Total functions:', totalCount); // Debug
            
            if (searchTerm === '') {
                // Show all functions
                $functions.show();
                $resultsInfo.empty();
                console.log('Empty search - showing all functions'); // Debug
                return;
            }
            
            // Search through functions
            $functions.each(function() {
                const $card = $(this);
                const functionName = ($card.data('function-name') || '').toString().toLowerCase();
                const functionDescription = ($card.data('function-description') || '').toString().toLowerCase();
                const functionKey = ($card.data('function-key') || '').toString().toLowerCase();
                
                // Also search in the visible text content
                const cardText = $card.text().toLowerCase();
                
                // Check if search term matches any field
                const matches = functionName.includes(searchTerm) || 
                              functionDescription.includes(searchTerm) ||
                              functionKey.includes(searchTerm) ||
                              cardText.includes(searchTerm);
                
                console.log('Function:', functionKey, 'Matches:', matches); // Debug
                
                if (matches) {
                    $card.show();
                    visibleCount++;
                } else {
                    $card.hide();
                }
            });
            
            console.log('Visible count:', visibleCount); // Debug
            
            // Update results info
            this.updateSearchResults(searchTerm, visibleCount, totalCount);
        },
        
        /**
         * Update search results information
         */
        updateSearchResults: function(searchTerm, visibleCount, totalCount) {
            const $resultsInfo = $('#search-results-info');
            
            if (visibleCount === 0) {
                $resultsInfo.html('<span class="no-results">No functions found for "' + searchTerm + '"</span>');
            } else if (visibleCount === totalCount) {
                $resultsInfo.empty();
            } else {
                const resultsText = visibleCount === 1 ? 'function' : 'functions';
                $resultsInfo.html('<span class="search-results">Showing ' + visibleCount + ' of ' + totalCount + ' ' + resultsText + ' for "' + searchTerm + '"</span>');
            }
        },
        
        /**
         * Handle search reset
         */
        handleSearchReset: function(e) {
            e.preventDefault();
            
            const $searchInput = $('#voxel-toolkit-search');
            const $functions = $('.voxel-toolkit-function-card');
            const $resultsInfo = $('#search-results-info');
            
            console.log('Resetting search'); // Debug
            
            // Clear search input
            $searchInput.val('').focus();
            
            // Show all functions
            $functions.show();
            
            // Clear results info
            $resultsInfo.empty();
        }
    };
    
    // Export for global access
    window.VoxelToolkitAdmin = VoxelToolkitAdmin;
    
})(jQuery);