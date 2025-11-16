(function($) {
    'use strict';

    /**
     * Voxel Onboarding Tour Class
     */
    class VoxelOnboardingTour {
        constructor($widget) {
            this.$widget = $widget;
            this.widgetId = $widget.data('widget-id');
            this.pageId = $widget.data('page-id');
            this.tourVersion = $widget.data('tour-version') || 1;
            this.tourSteps = $widget.data('tour-steps');
            this.autoStart = $widget.data('auto-start') === 'yes';
            this.autoStartDelay = parseInt($widget.data('auto-start-delay')) || 500;
            this.showProgress = $widget.data('show-progress') === 'yes';
            this.showBullets = $widget.data('show-bullets') === 'yes';
            this.exitOnOverlay = $widget.data('exit-on-overlay') === 'yes';
            this.showStepNumbers = $widget.data('show-step-numbers') === 'yes';
            this.tour = null;

            this.init();
        }

        /**
         * Initialize the tour
         */
        init() {
            // Check if intro.js is loaded
            if (typeof introJs === 'undefined') {
                console.error('Voxel Onboarding: intro.js library is not loaded');
                return;
            }

            // Validate tour steps
            if (!this.tourSteps || this.tourSteps.length === 0) {
                console.warn('Voxel Onboarding: No tour steps defined');
                return;
            }

            // Check if we're in Elementor editor
            const isElementorEditor = typeof elementorFrontend !== 'undefined' && elementorFrontend.isEditMode();

            // Setup the tour
            this.setupTour();

            // Auto-start if enabled, not in editor, and not completed
            if (this.autoStart && !isElementorEditor && !this.isTourCompleted()) {
                console.log('Voxel Onboarding: Auto-starting tour');
                setTimeout(() => this.start(), this.autoStartDelay);
            } else {
                console.log('Voxel Onboarding: Auto-start blocked', {
                    autoStart: this.autoStart,
                    isElementorEditor: isElementorEditor,
                    isTourCompleted: this.isTourCompleted()
                });
            }

            // Bind start button - always allow manual restart
            this.$widget.find('.voxel-tour-start-btn').on('click', (e) => {
                e.preventDefault();
                this.clearCompletion();
                this.start();
            });
        }

        /**
         * Setup intro.js tour
         */
        setupTour() {
            // Prepare steps for intro.js
            const processedSteps = this.prepareSteps();

            // Initialize intro.js
            this.tour = introJs();

            // Configure tour options
            this.tour.setOptions({
                steps: processedSteps,
                showProgress: this.showProgress,
                showBullets: this.showBullets,
                showStepNumbers: this.showStepNumbers,
                exitOnOverlayClick: this.exitOnOverlay,
                scrollToElement: true,
                scrollTo: 'tooltip',
                disableInteraction: false,
                overlayOpacity: 0.8,
                nextLabel: 'Next →',
                prevLabel: '← Back',
                doneLabel: 'Done',
                skipLabel: 'Skip',
            });

            // Event handlers
            this.tour.oncomplete(() => this.onComplete());
            this.tour.onexit(() => this.onExit());
        }

        /**
         * Prepare tour steps for intro.js
         */
        prepareSteps() {
            const steps = [];

            this.tourSteps.forEach((step, index) => {
                const processedStep = {
                    title: step.title || `Step ${index + 1}`,
                    intro: step.intro || '',
                    position: step.position || 'bottom',
                };

                // Add element selector if provided
                if (step.element) {
                    console.log(`Voxel Onboarding: Looking for element with selector: "${step.element}"`);
                    const element = document.querySelector(step.element);

                    if (!element) {
                        console.warn(`Voxel Onboarding: Element not found for selector "${step.element}". This step will be skipped.`);
                        // Skip this step entirely if element not found
                        return;
                    } else {
                        console.log(`Voxel Onboarding: Element found!`, element);
                        processedStep.element = element;
                    }
                } else {
                    // No element selector - show as floating tooltip in center
                    console.log(`Voxel Onboarding: Step ${index + 1} has no target element - will show as centered overlay`);
                }

                steps.push(processedStep);
            });

            return steps;
        }

        /**
         * Start the tour
         */
        start() {
            if (this.tour) {
                this.tour.start();
            }
        }

        /**
         * Handle tour completion
         */
        onComplete() {
            console.log('Voxel Onboarding: Tour completed');
            this.markAsCompleted();
        }

        /**
         * Handle tour exit
         */
        onExit() {
            console.log('Voxel Onboarding: Tour exited');
            // Optionally mark as completed even if exited early
            // this.markAsCompleted();
        }

        /**
         * Check if tour has been completed this session
         */
        isTourCompleted() {
            const storageKey = this.getStorageKey();
            return sessionStorage.getItem(storageKey) === '1';
        }

        /**
         * Mark tour as completed
         */
        markAsCompleted() {
            const storageKey = this.getStorageKey();
            sessionStorage.setItem(storageKey, '1');
        }

        /**
         * Get session storage key
         */
        getStorageKey() {
            return `voxel_tour_${this.pageId}_v${this.tourVersion}_completed`;
        }

        /**
         * Clear tour completion status
         */
        clearCompletion() {
            const storageKey = this.getStorageKey();
            sessionStorage.removeItem(storageKey);
            console.log('Voxel Onboarding: Completion status cleared');
        }
    }

    /**
     * Initialize all onboarding tour widgets on the page
     */
    function initOnboardingTours() {
        $('.voxel-onboarding-tour-widget').each(function() {
            const $widget = $(this);
            const tourInstance = new VoxelOnboardingTour($widget);
            // Store the instance on the widget element for later access
            $widget.data('voxelTourInstance', tourInstance);
        });
    }

    /**
     * Wait for all elements to be loaded before initializing tours
     */
    function initWhenReady() {
        // Try to initialize immediately first
        let attempts = 0;
        const maxAttempts = 20; // 10 seconds max

        function tryInit() {
            attempts++;

            // Check if all tour widgets have loaded
            const widgets = $('.voxel-onboarding-tour-widget');
            if (widgets.length === 0 && attempts < maxAttempts) {
                // No widgets found yet, wait and try again
                setTimeout(tryInit, 500);
                return;
            }

            // Check if all target elements exist
            let allElementsExist = true;
            widgets.each(function() {
                const tourSteps = $(this).data('tour-steps');
                if (tourSteps && tourSteps.length > 0) {
                    tourSteps.forEach(function(step) {
                        if (step.element && !document.querySelector(step.element)) {
                            console.log(`Voxel Onboarding: Waiting for element "${step.element}" to load...`);
                            allElementsExist = false;
                        }
                    });
                }
            });

            // If not all elements exist and we haven't exceeded max attempts, wait and try again
            if (!allElementsExist && attempts < maxAttempts) {
                setTimeout(tryInit, 500);
                return;
            }

            // Initialize tours
            console.log(`Voxel Onboarding: Initializing tours (attempt ${attempts})`);
            initOnboardingTours();
        }

        // Start trying
        tryInit();
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initWhenReady();
    });

    /**
     * Re-initialize after Elementor preview loads
     */
    $(window).on('elementor/frontend/init', function() {
        initWhenReady();
    });

})(jQuery);
