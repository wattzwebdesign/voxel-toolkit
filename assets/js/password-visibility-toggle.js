/**
 * Voxel Toolkit Password Visibility Toggle JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log('Password Visibility Toggle loaded');
        
        // Initialize password toggles
        initPasswordToggles();
        
        // Re-initialize when new content is loaded (for AJAX forms)
        $(document).on('DOMNodeInserted', function(e) {
            if ($(e.target).find('input[type="password"]').length > 0) {
                setTimeout(initPasswordToggles, 100);
            }
        });
        
        // Also listen for common AJAX events
        $(document).on('voxel/forms/loaded elementor/popup/show', function() {
            setTimeout(initPasswordToggles, 100);
        });
    });
    
    function initPasswordToggles() {
        // Find all password fields that don't already have toggles
        $('input[type="password"]').each(function() {
            var $passwordField = $(this);
            
            // Skip if already has toggle
            if ($passwordField.closest('.voxel-password-field-wrapper').length > 0) {
                return;
            }
            
            // Skip if field is hidden or has display:none
            if (!$passwordField.is(':visible') || $passwordField.css('display') === 'none') {
                return;
            }
            
            // Skip certain fields that shouldn't have toggles
            var fieldName = $passwordField.attr('name') || '';
            var fieldId = $passwordField.attr('id') || '';
            
            if (fieldName.includes('confirm') || fieldId.includes('confirm') ||
                $passwordField.hasClass('no-toggle') || 
                $passwordField.closest('.no-password-toggle').length > 0) {
                return;
            }
            
            addPasswordToggle($passwordField);
        });
    }
    
    function addPasswordToggle($passwordField) {
        // Wrap the password field
        $passwordField.wrap('<div class="voxel-password-field-wrapper"></div>');
        
        var $wrapper = $passwordField.parent('.voxel-password-field-wrapper');
        
        // Create toggle element (no button styling)
        var $toggleBtn = $('<span class="voxel-password-toggle-btn" tabindex="0" role="button" aria-label="Toggle password visibility"></span>');
        
        // Set initial state (hidden)
        $toggleBtn.html(voxelToolkitPasswordToggle.icons.show);
        $toggleBtn.attr('data-state', 'hidden');
        
        // Add button to wrapper
        $wrapper.append($toggleBtn);
        
        // Handle toggle click
        $toggleBtn.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(this);
            var currentState = $btn.attr('data-state');
            
            if (currentState === 'hidden') {
                // Show password
                $passwordField.attr('type', 'text');
                $btn.html(voxelToolkitPasswordToggle.icons.hide);
                $btn.attr('data-state', 'visible');
                $btn.attr('aria-label', 'Hide password');
            } else {
                // Hide password
                $passwordField.attr('type', 'password');
                $btn.html(voxelToolkitPasswordToggle.icons.show);
                $btn.attr('data-state', 'hidden');
                $btn.attr('aria-label', 'Show password');
            }
        });
        
        // Handle keyboard accessibility
        $toggleBtn.on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).click();
            }
        });
        
        console.log('Password toggle added to field:', $passwordField.attr('name') || $passwordField.attr('id') || 'unnamed');
    }
    
})(jQuery);