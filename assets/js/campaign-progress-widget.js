/**
 * Campaign Progress Widget JavaScript
 *
 * Handles interactive features for the campaign progress widget
 */

(function($) {
    'use strict';

    /**
     * Initialize Campaign Progress Widget
     */
    function initCampaignProgress() {
        // Add animation class when widget comes into view
        $('.vt-campaign-progress-widget').each(function() {
            const $widget = $(this);
            const $progressFill = $widget.find('.vt-campaign-progress-fill');

            // Intersection Observer for animation on scroll
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            $progressFill.addClass('animated');
                            observer.unobserve(entry.target);
                        }
                    });
                }, {
                    threshold: 0.1
                });

                observer.observe($widget[0]);
            }
        });
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initCampaignProgress();
    });

    /**
     * Re-initialize when Elementor preview reloads
     */
    $(window).on('elementor/frontend/init', function() {
        elementorFrontend.hooks.addAction('frontend/element_ready/voxel-toolkit-campaign-progress.default', function($scope) {
            initCampaignProgress();
        });
    });

})(jQuery);
