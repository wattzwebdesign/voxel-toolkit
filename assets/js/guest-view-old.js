/**
 * Voxel Toolkit Guest View JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Initialize guest view functionality
        VoxelToolkitGuestView.init();
    });
    
    const VoxelToolkitGuestView = {
        
        /**
         * Initialize guest view functionality
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Handle guest view button click
            $(document).on('click', '.voxel-toolkit-guest-view-btn', this.handleToggleClick.bind(this));
            
            // Handle exit guest view button
            $(document).on('click', '#voxel-toolkit-exit-guest-view', this.handleExitClick.bind(this));
        },
        
        /**
         * Handle toggle guest view click
         */
        handleToggleClick: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            
            // Don't do anything if already in guest view
            if ($button.hasClass('guest-view-active')) {
                return;
            }
            
            // Confirm action
            if (!confirm(voxelToolkitGuestView.strings.confirmEnable)) {
                return;
            }
            
            // Disable button and show loading state
            $button.addClass('loading').prop('disabled', true);
            const originalText = $button.find('.button-text').text();
            $button.find('.button-text').text(voxelToolkitGuestView.strings.enabling);
            
            // Make AJAX request
            $.ajax({
                url: voxelToolkitGuestView.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'voxel_toolkit_toggle_guest_view',
                    nonce: voxelToolkitGuestView.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the page to apply guest view
                        if (response.data.reload) {
                            window.location.reload();
                        }
                    } else {
                        // Show error message
                        alert(response.data || 'An error occurred');
                        
                        // Reset button
                        $button.removeClass('loading').prop('disabled', false);
                        $button.find('.button-text').text(originalText);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    
                    // Reset button
                    $button.removeClass('loading').prop('disabled', false);
                    $button.find('.button-text').text(originalText);
                }
            });
        },
        
        /**
         * Handle exit guest view click
         */
        handleExitClick: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            
            // Confirm action
            if (!confirm(voxelToolkitGuestView.strings.confirmDisable)) {
                return;
            }
            
            // Disable button and show loading state
            $button.prop('disabled', true).text(voxelToolkitGuestView.strings.disabling);
            
            // Make AJAX request
            $.ajax({
                url: voxelToolkitGuestView.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'voxel_toolkit_exit_guest_view',
                    nonce: voxelToolkitGuestView.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the page to restore logged-in view
                        if (response.data.reload) {
                            window.location.reload();
                        }
                    } else {
                        // Show error message
                        alert(response.data || 'An error occurred');
                        
                        // Reset button
                        $button.prop('disabled', false).text('Switch Back');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    
                    // Reset button
                    $button.prop('disabled', false).text('Switch Back');
                }
            });
        },
        
        /**
         * Add visual indicator when in guest view
         */
        addGuestViewIndicator: function() {
            if (voxelToolkitGuestView.isGuestView) {
                $('body').addClass('voxel-toolkit-guest-view-active');
            }
        }
    };
    
    // Export for global access
    window.VoxelToolkitGuestView = VoxelToolkitGuestView;
    
    // Add guest view indicator on load
    $(window).on('load', function() {
        VoxelToolkitGuestView.addGuestViewIndicator();
    });
    
})(jQuery);