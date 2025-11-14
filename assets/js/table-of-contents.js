(function($) {
    'use strict';

    /**
     * Table of Contents Active State Handler
     */
    class TableOfContentsHandler {
        constructor() {
            this.init();
        }

        init() {
            // Update active state on page load
            this.updateActiveStates();

            // Listen for URL changes (history API)
            this.watchUrlChanges();

            // Listen for popstate (browser back/forward)
            window.addEventListener('popstate', () => {
                this.updateActiveStates();
            });
        }

        /**
         * Watch for URL changes using MutationObserver and setInterval
         */
        watchUrlChanges() {
            let lastUrl = location.href;

            // Check URL every 100ms
            setInterval(() => {
                const currentUrl = location.href;
                if (currentUrl !== lastUrl) {
                    lastUrl = currentUrl;
                    this.updateActiveStates();
                }
            }, 100);

            // Also watch for pushState/replaceState
            const originalPushState = history.pushState;
            const originalReplaceState = history.replaceState;

            history.pushState = (...args) => {
                originalPushState.apply(history, args);
                this.updateActiveStates();
            };

            history.replaceState = (...args) => {
                originalReplaceState.apply(history, args);
                this.updateActiveStates();
            };
        }

        /**
         * Update active states on all TOC widgets
         */
        updateActiveStates() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentStep = urlParams.get('step');

            // Find all TOC widgets
            $('.voxel-table-of-contents').each((index, container) => {
                const $container = $(container);
                const $items = $container.find('.voxel-toc-item');

                // If no step in URL, activate first item
                if (!currentStep || currentStep === '') {
                    $items.removeClass('active');
                    $items.first().addClass('active');
                    $container.attr('data-current-step', $items.first().attr('data-step-key') || '');
                } else {
                    // Remove active from all items
                    $items.removeClass('active');

                    // Add active to matching item
                    $items.each((i, item) => {
                        const $item = $(item);
                        const stepKey = $item.attr('data-step-key');

                        if (stepKey === currentStep) {
                            $item.addClass('active');
                            $container.attr('data-current-step', currentStep);
                        }
                    });
                }
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new TableOfContentsHandler();
    });

    // Also initialize after Elementor preview loads
    $(window).on('elementor/frontend/init', () => {
        new TableOfContentsHandler();
    });

})(jQuery);
