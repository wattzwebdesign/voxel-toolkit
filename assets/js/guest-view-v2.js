/**
 * Voxel Toolkit Guest View JavaScript - Version 2
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log('=== VOXEL TOOLKIT GUEST VIEW V2 DEBUG ===');
        console.log('Guest View JS V2 loaded');
        console.log('voxelToolkitGuestView object:', voxelToolkitGuestView);
        console.log('Is Guest View Active:', voxelToolkitGuestView.isGuestView);
        console.log('Is Logged In:', voxelToolkitGuestView.isLoggedIn);
        console.log('Current URL:', voxelToolkitGuestView.currentUrl);
        
        // Check if button exists
        setTimeout(function() {
            const buttonCount = $('.voxel-toolkit-guest-view-btn').length;
            console.log('Guest view buttons found:', buttonCount);
            if (buttonCount > 0) {
                console.log('Button elements:', $('.voxel-toolkit-guest-view-btn'));
            }
        }, 1000);
        
        // Handle guest view button click
        $(document).on('click', '.voxel-toolkit-guest-view-btn', function(e) {
            console.log('=== BUTTON CLICKED V2 ===');
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Button clicked, logged in:', voxelToolkitGuestView.isLoggedIn);
            
            if (!voxelToolkitGuestView.isLoggedIn) {
                alert('You must be logged in to use guest view.');
                return false;
            }
            
            const $button = $(this);
            const $buttonText = $button.find('.button-text');
            const originalText = $buttonText.length ? $buttonText.text() : $button.text();
            
            console.log('Original button text:', originalText);
            
            // Show confirmation
            if (!confirm('View the site as a guest? You can switch back anytime using the floating button.')) {
                return false;
            }
            
            // Disable button and show loading
            $button.prop('disabled', true).addClass('loading');
            if ($buttonText.length) {
                $buttonText.text('Switching to Guest View...');
            } else {
                $button.text('Switching to Guest View...');
            }
            
            console.log('Making AJAX request...');
            
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
                    console.log('AJAX Success response:', response);
                    if (response.success) {
                        console.log('Success! Redirecting to:', response.data.redirect_url);
                        // Redirect to the switch URL instead of reloading
                        window.location.href = response.data.redirect_url;
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
        
        // Add visual feedback when in guest view
        if (voxelToolkitGuestView.isGuestView) {
            console.log('Guest View V2 is ACTIVE - user is actually logged out');
            console.log('Current User ID should be 0, and floating button should show');
        }
        
        console.log('=== END GUEST VIEW V2 DEBUG ===');
    });
    
})(jQuery);