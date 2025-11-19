(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCheckoutLinks);
    } else {
        initCheckoutLinks();
    }

    function initCheckoutLinks() {
        // Find all vx-pick-plan links
        var links = document.querySelectorAll('.vx-pick-plan');

        links.forEach(function(link) {
            // Skip if already initialized
            if (link.hasAttribute('data-vt-checkout-init')) {
                return;
            }

            // Mark as initialized
            link.setAttribute('data-vt-checkout-init', '1');

            // Add click handler
            link.addEventListener('click', function(e) {
                e.preventDefault();

                var url = link.getAttribute('href');
                if (!url) {
                    return;
                }

                // Add loading state
                var container = link.closest('.ts-plan-container');
                if (container) {
                    container.classList.add('vx-pending');
                }

                // Make AJAX request
                jQuery.get(url)
                    .done(function(response) {
                        if (response.success) {
                            // Handle redirect
                            if (response.redirect_url) {
                                window.location.href = response.redirect_url;
                            } else if (response.redirect_to) {
                                window.location.href = response.redirect_to;
                            } else if (response.checkout_link) {
                                window.location.href = response.checkout_link;
                            }
                        } else {
                            // Show error
                            if (window.Voxel && window.Voxel.alert) {
                                window.Voxel.alert(response.message || 'An error occurred', 'error');
                            } else {
                                alert(response.message || 'An error occurred');
                            }
                        }
                    })
                    .fail(function() {
                        // Show error
                        if (window.Voxel && window.Voxel.alert) {
                            window.Voxel.alert('An error occurred. Please try again.', 'error');
                        } else {
                            alert('An error occurred. Please try again.');
                        }
                    })
                    .always(function() {
                        // Remove loading state
                        if (container) {
                            container.classList.remove('vx-pending');
                        }
                    });
            });
        });
    }

    // Re-initialize after AJAX events (for dynamic content)
    if (window.jQuery) {
        jQuery(document).on('ajaxComplete', initCheckoutLinks);
    }
})();
