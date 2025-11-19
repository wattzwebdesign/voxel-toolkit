(function() {
    'use strict';

    console.log('Voxel Toolkit: Membership checkout script loaded');

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCheckoutLinks);
    } else {
        initCheckoutLinks();
    }

    function initCheckoutLinks() {
        // Find all vx-pick-plan links
        var links = document.querySelectorAll('.vx-pick-plan');
        console.log('Voxel Toolkit: Found ' + links.length + ' .vx-pick-plan elements');

        links.forEach(function(link) {
            // Skip if already initialized
            if (link.hasAttribute('data-vt-checkout-init')) {
                return;
            }

            // Mark as initialized
            link.setAttribute('data-vt-checkout-init', '1');

            console.log('Voxel Toolkit: Initialized click handler for link', link);

            // Add click handler
            link.addEventListener('click', function(e) {
                console.log('Voxel Toolkit: Click intercepted!', e);
                e.preventDefault();

                // For Elementor buttons, the URL might be on an <a> tag inside the widget
                var url = link.getAttribute('href');

                // If no href on the element itself, look for an <a> tag inside
                if (!url) {
                    var anchorTag = link.querySelector('a');
                    if (anchorTag) {
                        url = anchorTag.getAttribute('href');
                        console.log('Voxel Toolkit: Found URL in nested anchor tag');
                    }
                }

                console.log('Voxel Toolkit: URL =', url);
                if (!url) {
                    console.error('Voxel Toolkit: No URL found');
                    return;
                }

                // Check if jQuery is available
                if (typeof jQuery === 'undefined') {
                    console.error('Voxel Toolkit: jQuery is not available!');
                    alert('jQuery is not loaded. Cannot process checkout.');
                    return;
                }

                console.log('Voxel Toolkit: Making AJAX request to', url);

                // Add loading state
                var container = link.closest('.ts-plan-container');
                if (container) {
                    container.classList.add('vx-pending');
                }

                // Make AJAX request
                jQuery.get(url)
                    .done(function(response) {
                        console.log('Voxel Toolkit: AJAX response received', response);
                        if (response.success) {
                            // Handle redirect
                            if (response.redirect_url) {
                                console.log('Voxel Toolkit: Redirecting to', response.redirect_url);
                                window.location.href = response.redirect_url;
                            } else if (response.redirect_to) {
                                console.log('Voxel Toolkit: Redirecting to', response.redirect_to);
                                window.location.href = response.redirect_to;
                            } else if (response.checkout_link) {
                                console.log('Voxel Toolkit: Redirecting to', response.checkout_link);
                                window.location.href = response.checkout_link;
                            } else {
                                console.error('Voxel Toolkit: No redirect URL found in response');
                            }
                        } else {
                            console.error('Voxel Toolkit: Request failed', response.message);
                            // Show error
                            if (window.Voxel && window.Voxel.alert) {
                                window.Voxel.alert(response.message || 'An error occurred', 'error');
                            } else {
                                alert(response.message || 'An error occurred');
                            }
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Voxel Toolkit: AJAX request failed', status, error);
                        // Show error
                        if (window.Voxel && window.Voxel.alert) {
                            window.Voxel.alert('An error occurred. Please try again.', 'error');
                        } else {
                            alert('An error occurred. Please try again.');
                        }
                    })
                    .always(function() {
                        console.log('Voxel Toolkit: Request complete');
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
