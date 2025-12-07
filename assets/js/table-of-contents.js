(function($) {
    'use strict';

    /**
     * Table of Contents Active State Handler
     */
    class TableOfContentsHandler {
        constructor() {
            this.syncDebounceTimer = null;
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

            // Sync with Voxel form after it initializes
            this.initVoxelSync();
        }

        /**
         * Initialize sync with Voxel form
         */
        initVoxelSync() {
            // Wait for Voxel to initialize, then sync
            const trySync = (attempts = 0) => {
                if (attempts > 10) return; // Give up after 5 seconds

                const hasVoxelForm = $('.ts-create-post, .ts-form').length > 0;

                if (hasVoxelForm) {
                    this.syncWithVoxelForm();
                    this.observeVoxelForm();
                } else {
                    // Retry in 500ms
                    setTimeout(() => trySync(attempts + 1), 500);
                }
            };

            // Start trying after DOM is ready
            setTimeout(() => trySync(), 500);
        }

        /**
         * Sync TOC visibility with Voxel form's visible steps
         */
        syncWithVoxelForm() {
            const $toc = $('.voxel-table-of-contents');
            if (!$toc.length) return;

            // Find Voxel's step progress bar - only contains visible steps
            const $voxelProgressSteps = $('.ts-form-progres .step-percentage li');

            // If no Voxel form or no steps, don't filter
            if (!$voxelProgressSteps.length) return;

            const $tocItems = $toc.find('.voxel-toc-item');
            const visibleStepCount = $voxelProgressSteps.length;
            const tocItemCount = $tocItems.length;

            // If counts match, all steps are visible - no filtering needed
            if (visibleStepCount === tocItemCount) {
                $tocItems.removeClass('vt-toc-hidden');
                return;
            }

            // Get the labels of visible steps from Voxel's progress bar
            // We need to check which TOC items correspond to visible steps
            $tocItems.each(function() {
                const $item = $(this);
                const stepKey = $item.data('step-key');
                const stepLabel = $item.text().trim();

                // Check if this step's fields exist in the form
                // Voxel adds field-key-{stepkey} class to step containers
                const $stepFields = $(`.ts-form [data-field-key="${stepKey}"]`);

                // Also check by looking for fields that belong to this step
                // The step key is used as the field.step value in Voxel
                let hasVisibleStep = false;

                // Method 1: Check if any form field has this step key
                if ($stepFields.length > 0) {
                    hasVisibleStep = true;
                }

                // Method 2: Check if the step label appears in the active step display
                // This catches the current step
                const activeStepText = $('.ts-active-step .active-step-details p').text().trim();
                if (stepLabel && activeStepText === stepLabel) {
                    hasVisibleStep = true;
                }

                // Method 3: Check the create-form-step container for fields with this step
                // Voxel sets field.step on each field which determines which step it belongs to
                const $formFields = $('.create-form-step [class*="field-"]').filter(function() {
                    // Check if this field's step matches our step key
                    const fieldClasses = $(this).attr('class') || '';
                    return fieldClasses.includes('field-key-' + stepKey);
                });

                if ($formFields.length > 0) {
                    hasVisibleStep = true;
                }

                // Apply visibility
                if (!hasVisibleStep) {
                    $item.addClass('vt-toc-hidden');
                } else {
                    $item.removeClass('vt-toc-hidden');
                }
            });

            // Update active state to first visible item if current active is hidden
            this.ensureActiveVisible();
        }

        /**
         * Ensure the active item is visible, otherwise activate first visible
         */
        ensureActiveVisible() {
            $('.voxel-table-of-contents').each((index, container) => {
                const $container = $(container);
                const $activeItem = $container.find('.voxel-toc-item.active');
                const $visibleItems = $container.find('.voxel-toc-item:not(.vt-toc-hidden)');

                // If active item is hidden, activate first visible item
                if ($activeItem.hasClass('vt-toc-hidden') && $visibleItems.length > 0) {
                    $container.find('.voxel-toc-item').removeClass('active');
                    $visibleItems.first().addClass('active');
                }
            });
        }

        /**
         * Observe Voxel form for changes and re-sync
         */
        observeVoxelForm() {
            const formContainer = document.querySelector('.ts-create-post, .ts-form');
            if (!formContainer) return;

            const observer = new MutationObserver(() => {
                // Debounce the sync to prevent excessive updates
                if (this.syncDebounceTimer) {
                    clearTimeout(this.syncDebounceTimer);
                }
                this.syncDebounceTimer = setTimeout(() => {
                    this.syncWithVoxelForm();
                }, 100);
            });

            observer.observe(formContainer, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class']
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
                    this.syncWithVoxelForm();
                }
            }, 100);

            // Also watch for pushState/replaceState
            const originalPushState = history.pushState;
            const originalReplaceState = history.replaceState;

            history.pushState = (...args) => {
                originalPushState.apply(history, args);
                this.updateActiveStates();
                this.syncWithVoxelForm();
            };

            history.replaceState = (...args) => {
                originalReplaceState.apply(history, args);
                this.updateActiveStates();
                this.syncWithVoxelForm();
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
                const $items = $container.find('.voxel-toc-item:not(.vt-toc-hidden)');

                // If no step in URL, activate first visible item
                if (!currentStep || currentStep === '') {
                    $container.find('.voxel-toc-item').removeClass('active');
                    $items.first().addClass('active');
                    $container.attr('data-current-step', $items.first().attr('data-step-key') || '');
                } else {
                    // Remove active from all items
                    $container.find('.voxel-toc-item').removeClass('active');

                    // Add active to matching item
                    $container.find('.voxel-toc-item').each((i, item) => {
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
