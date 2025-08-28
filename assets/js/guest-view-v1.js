/**
 * Voxel Toolkit Guest View JavaScript - Simplified
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log('=== VOXEL TOOLKIT GUEST VIEW DEBUG ===');
        console.log('Guest View JS loaded');
        console.log('voxelToolkitGuestView object:', voxelToolkitGuestView);
        console.log('Is Guest View Active:', voxelToolkitGuestView.isGuestView);
        console.log('Is Logged In:', voxelToolkitGuestView.isLoggedIn);
        console.log('AJAX URL:', voxelToolkitGuestView.ajaxUrl);
        console.log('Nonce:', voxelToolkitGuestView.nonce);
        
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
            console.log('=== BUTTON CLICKED ===');
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
                $buttonText.text('Enabling Guest View...');
            } else {
                $button.text('Enabling Guest View...');
            }
            
            console.log('Making AJAX request...');
            
            // Make AJAX request
            $.ajax({
                url: voxelToolkitGuestView.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'voxel_toolkit_toggle_guest_view',
                    enable: 'true',
                    nonce: voxelToolkitGuestView.nonce
                },
                success: function(response) {
                    console.log('AJAX Success response:', response);
                    if (response.success) {
                        console.log('Success! Reloading page...');
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
        
        // Handle exit guest view button
        $(document).on('click', '#voxel-toolkit-exit-guest-view', function(e) {
            console.log('=== EXIT BUTTON CLICKED ===');
            e.preventDefault();
            e.stopPropagation();
            
            const $button = $(this);
            
            // Show confirmation
            if (!confirm('Return to your logged-in view?')) {
                return false;
            }
            
            // Disable button
            $button.prop('disabled', true).text('Switching Back...');
            
            console.log('Making exit AJAX request...');
            
            // Make AJAX request
            $.ajax({
                url: voxelToolkitGuestView.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'voxel_toolkit_toggle_guest_view',
                    enable: 'false',
                    nonce: voxelToolkitGuestView.nonce
                },
                success: function(response) {
                    console.log('Exit AJAX Success response:', response);
                    if (response.success) {
                        console.log('Success! Reloading page...');
                        window.location.reload();
                    } else {
                        console.error('Exit AJAX returned error:', response.data);
                        alert(response.data || 'An error occurred');
                        $button.prop('disabled', false).text('Switch Back');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Exit AJAX Error:', {xhr: xhr, status: status, error: error});
                    alert('An error occurred. Please try again.');
                    $button.prop('disabled', false).text('Switch Back');
                }
            });
            
            return false;
        });
        
        // Add visual feedback when in guest view
        if (voxelToolkitGuestView.isGuestView) {
            console.log('Guest View is ACTIVE - adding visual indicators');
            
            // Add a subtle border to indicate guest view
            $('body').css({
                'border': '3px solid #667eea',
                'box-sizing': 'border-box'
            });
            
            // Log what elements are being hidden
            console.log('Hidden elements count:', $('.vx-user-menu, .user-menu, .ts-user-menu').length);
        }
        
        console.log('=== END GUEST VIEW DEBUG ===');
    });
    
})(jQuery);