/**
 * Voxel Toolkit Guest View JavaScript - Version 2
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Check if button exists
        setTimeout(function() {
            const buttonCount = $('.voxel-toolkit-guest-view-btn').length;
            if (buttonCount > 0) {
            }
        }, 1000);
        
        // Handle guest view button click
        $(document).on('click', '.voxel-toolkit-guest-view-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            
            if (!voxelToolkitGuestView.isLoggedIn) {
                alert('You must be logged in to use guest view.');
                return false;
            }
            
            const $button = $(this);
            const $buttonText = $button.find('.button-text');
            const originalText = $buttonText.length ? $buttonText.text() : $button.text();
            
            
            // Show confirmation only if setting is enabled
            if (voxelToolkitGuestView.settings.showConfirmation && !confirm('View the site as a guest? You can switch back anytime using the floating button.')) {
                return false;
            }
            
            // Disable button and show loading
            $button.prop('disabled', true).addClass('loading');
            if ($buttonText.length) {
                $buttonText.text('Switching to Guest View...');
            } else {
                $button.text('Switching to Guest View...');
            }
            
            
            // Make AJAX request
            $.ajax({
                url: voxelToolkitGuestView.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'voxel_toolkit_toggle_guest_view',
                    enable: 'true',
                    current_url: voxelToolkitGuestView.currentUrl,
                    nonce: voxelToolkitGuestView.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Simple reload - no redirect needed
                        window.location.reload();
                    } else {
                        console.error('AJAX returned error:', response.data);
                        alert(response.data || 'An error occurred');
                        $button.prop('disabled', false).removeClass('loading');
                        if ($buttonText.length) {
                            $buttonText.text(originalText);
                        } else {
                            $button.text(originalText);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                    console.error('Response text:', xhr.responseText);
                    alert('An error occurred. Please try again. Check console for details.');
                    $button.prop('disabled', false).removeClass('loading');
                    if ($buttonText.length) {
                        $buttonText.text(originalText);
                    } else {
                        $button.text(originalText);
                    }
                }
            });
            
            return false;
        });
        
        // Handle switch back button click
        $(document).on('click', '#voxel-toolkit-switch-back-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $button = $(this);
            
            // Show confirmation only if setting is enabled
            if (voxelToolkitGuestView.settings.showConfirmation && !confirm('Return to your logged-in view?')) {
                return false;
            }
            
            // Disable button
            $button.prop('disabled', true).text('Switching Back...');
            
            
            // Make AJAX request
            $.ajax({
                url: voxelToolkitGuestView.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'voxel_toolkit_switch_back'
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        console.error('Switch back AJAX returned error:', response.data);
                        alert(response.data || 'An error occurred');
                        $button.prop('disabled', false).text('Switch Back');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Switch back AJAX Error:', {xhr: xhr, status: status, error: error});
                    alert('An error occurred. Please try again.');
                    $button.prop('disabled', false).text('Switch Back');
                }
            });
            
            return false;
        });
        
        // Add visual feedback when in guest view
        if (voxelToolkitGuestView.isGuestView) {
        }
        
    });
    
})(jQuery);