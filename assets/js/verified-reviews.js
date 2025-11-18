(function($) {
    'use strict';

    /**
     * Verified Reviews Frontend Handler
     */
    class VerifiedReviews {
        constructor() {
            this.config = window.voxelConfig?.verifiedReviews || {};
            this.init();
        }

        init() {
            // Wait for DOM to be ready
            $(document).ready(() => {
                this.handleNonPurchaserUI();
                this.watchForDynamicForms();
            });
        }

        /**
         * Handle UI for non-purchasers
         */
        handleNonPurchaserUI() {
            const action = this.config.nonPurchaserAction || 'show_message';
            const message = this.config.nonPurchaserMessage || 'Only customers who have purchased can leave reviews.';

            // Find review forms
            const $reviewForms = $('.ts-form[data-feed="post_reviews"]');

            if ($reviewForms.length === 0) {
                return;
            }

            $reviewForms.each((index, form) => {
                const $form = $(form);

                // Check if user can review (Voxel will handle this, but we can enhance UI)
                if (action === 'hide_form') {
                    // Hide the form completely for non-purchasers
                    $form.addClass('voxel-toolkit-hidden-for-non-purchaser');
                } else if (action === 'show_message') {
                    // Show informational message above form
                    if ($form.find('.voxel-toolkit-non-purchaser-notice').length === 0) {
                        $form.prepend(
                            `<div class="voxel-toolkit-non-purchaser-notice">
                                <i class="las la-info-circle"></i>
                                <span>${this.escapeHtml(message)}</span>
                            </div>`
                        );
                    }
                }
            });
        }

        /**
         * Watch for dynamically loaded forms
         */
        watchForDynamicForms() {
            // Use MutationObserver to watch for dynamically added forms
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.addedNodes.length) {
                        this.handleNonPurchaserUI();
                    }
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    /**
     * Initialize when DOM is ready
     */
    const verifiedReviews = new VerifiedReviews();

})(jQuery);
