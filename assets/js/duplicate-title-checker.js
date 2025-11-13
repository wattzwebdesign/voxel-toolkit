(function($) {
    'use strict';

    /**
     * Configuration
     */
    const config = {
        checkDelay: 500,
        minTitleLength: 3,
        selectors: {
            titleInputs: [
                '.field-key-title input',
                '.ts-form-group.field-key-title input.ts-filter',
                'input[name="title"]',
                '.post-title-input',
                '#title'
            ],
            excludeInputs: [
                '.ts-filter:not(.field-key-title .ts-filter)',
                '[class*="search"]:not(.field-key-title *)',
                '.search-input',
                '#search',
                '[type="search"]'
            ]
        }
    };

    /**
     * Main Duplicate Title Checker Class
     */
    class DuplicateTitleChecker {
        constructor() {
            this.checkTimeout = null;
            this.currentRequest = null;
            this.lastCheckedTitle = '';
            this.validationState = new WeakMap(); // Store state per input
        }

        /**
         * Initialize
         */
        init() {
            // Wait for DOM to be ready
            $(document).ready(() => {
                this.attachEventHandlers();
            });
        }

        /**
         * Find and attach to title inputs
         */
        attachEventHandlers() {
            // Build selector for title inputs
            const titleSelector = config.selectors.titleInputs.join(', ');
            const excludeSelector = config.selectors.excludeInputs.join(', ');

            // Find all matching inputs
            const $inputs = $(titleSelector).not(excludeSelector);

            $inputs.each((index, input) => {
                const $input = $(input);

                // Skip if already initialized
                if ($input.data('duplicate-checker-initialized')) {
                    return;
                }

                $input.data('duplicate-checker-initialized', true);

                // Setup validation UI
                this.setupValidationUI($input);

                // Attach input event with debounce
                $input.on('input.duplicateChecker', (e) => {
                    this.handleTitleInput($(e.target));
                });

                // Also check on blur
                $input.on('blur.duplicateChecker', (e) => {
                    this.handleTitleInput($(e.target), true);
                });
            });
        }

        /**
         * Setup validation UI - icon and message
         */
        setupValidationUI($input) {
            // Make parent relative for absolute positioning
            const $parent = $input.parent();
            if ($parent.css('position') === 'static') {
                $parent.css('position', 'relative');
            }

            // Add padding to input for icon
            const currentPadding = parseInt($input.css('padding-right')) || 10;
            $input.css('padding-right', Math.max(currentPadding, 50) + 'px');

            // Store reference to input for later
            this.validationState.set($input[0], {
                hasError: false,
                hasSuccess: false,
                message: ''
            });
        }

        /**
         * Handle title input with debouncing
         */
        handleTitleInput($input, immediate = false) {
            const title = $input.val().trim();

            // Clear existing timeout
            if (this.checkTimeout) {
                clearTimeout(this.checkTimeout);
            }

            // Clear validation if title too short
            if (title.length < config.minTitleLength) {
                this.clearValidation($input);
                return;
            }

            // Don't recheck if unchanged
            if (title === this.lastCheckedTitle) {
                return;
            }

            const delay = immediate ? 0 : config.checkDelay;

            this.checkTimeout = setTimeout(() => {
                this.checkDuplicateTitle($input, title);
            }, delay);
        }

        /**
         * Check for duplicate titles via AJAX
         */
        checkDuplicateTitle($input, title) {
            // Cancel any pending request
            if (this.currentRequest) {
                this.currentRequest.abort();
            }

            this.lastCheckedTitle = title;

            // Show loading state
            this.showLoading($input);

            // Get post type and ID
            const postType = this.getPostType($input);
            const postId = this.getCurrentPostId();

            // AJAX request
            this.currentRequest = $.ajax({
                url: window.voxelToolkitDuplicateChecker.ajax_url,
                type: 'POST',
                data: {
                    action: 'voxel_toolkit_check_duplicate_title',
                    nonce: window.voxelToolkitDuplicateChecker.nonce,
                    title: title,
                    post_type: postType,
                    post_id: postId
                },
                success: (response) => {
                    this.handleResponse($input, response);
                },
                error: (xhr, status, error) => {
                    if (status !== 'abort') {
                        this.clearValidation($input);
                    }
                },
                complete: () => {
                    this.currentRequest = null;
                }
            });
        }

        /**
         * Handle AJAX response
         */
        handleResponse($input, response) {
            if (!response.success) {
                this.clearValidation($input);
                this.enableSubmitButton();
                return;
            }

            const data = response.data;

            if (data.has_duplicate) {
                this.showError($input);

                // Block submission if enabled
                if (window.voxelToolkitDuplicateChecker?.block_duplicate) {
                    this.disableSubmitButton();
                }
            } else {
                this.showSuccess($input);

                // Always enable button when title is unique
                this.enableSubmitButton();
            }
        }

        /**
         * Show loading state
         */
        showLoading($input) {
            this.renderValidation($input, 'loading');
        }

        /**
         * Show error state
         */
        showError($input) {
            this.renderValidation($input, 'error');
        }

        /**
         * Show success state
         */
        showSuccess($input) {
            this.renderValidation($input, 'success');
        }

        /**
         * Clear validation
         */
        clearValidation($input) {
            this.renderValidation($input, 'none');
        }

        /**
         * Render validation UI - this runs every time to ensure persistence
         */
        renderValidation($input, state) {
            const $parent = $input.parent();

            // Remove any existing validation elements
            $parent.find('.voxel-validation-icon').remove();
            $input.siblings('.voxel-validation-message').remove();

            if (state === 'none') {
                return;
            }

            // Create icon
            let iconHTML = '';
            let messageHTML = '';

            switch (state) {
                case 'loading':
                    iconHTML = `
                        <div class="voxel-validation-icon" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); z-index: 10; line-height: 0;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" stroke="#d1d5db" stroke-width="2" fill="none"/>
                                <path d="M12 2 A10 10 0 0 1 22 12" stroke="#6b7280" stroke-width="2" fill="none" stroke-linecap="round">
                                    <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                                </path>
                            </svg>
                        </div>
                    `;
                    break;

                case 'error':
                    iconHTML = `
                        <div class="voxel-validation-icon" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); z-index: 10; line-height: 0; pointer-events: none;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" fill="#ef4444" stroke="#dc2626" stroke-width="2"/>
                                <path d="M8 8l8 8M16 8l-8 8" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                    `;
                    const errorMsg = window.voxelToolkitDuplicateChecker?.error_message || 'Title is taken. Please choose another.';
                    messageHTML = `
                        <div class="voxel-validation-message" style="margin-top: 8px; text-align: center; font-size: 14px; line-height: 1.5; color: #dc2626; font-weight: 500;">
                            ${this.escapeHtml(errorMsg)}
                        </div>
                    `;
                    break;

                case 'success':
                    iconHTML = `
                        <div class="voxel-validation-icon" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); z-index: 10; line-height: 0; pointer-events: none;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" fill="#10b981" stroke="#059669" stroke-width="2"/>
                                <path d="M8 12l3 3l5-5" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    `;
                    const successMsg = window.voxelToolkitDuplicateChecker?.success_message || 'Title is available.';
                    messageHTML = `
                        <div class="voxel-validation-message" style="margin-top: 8px; text-align: center; font-size: 14px; line-height: 1.5; color: #059669; font-weight: 500;">
                            ${this.escapeHtml(successMsg)}
                        </div>
                    `;
                    break;
            }

            // Insert icon into parent
            if (iconHTML) {
                $parent.append(iconHTML);
            }

            // Insert message after input
            if (messageHTML) {
                $input.after(messageHTML);
            }

            // Store state
            const stateObj = this.validationState.get($input[0]);
            if (stateObj) {
                stateObj.currentState = state;
            }

            // Use setInterval to maintain validation state
            this.maintainValidation($input, state);
        }

        /**
         * Maintain validation state - rerender if elements disappear
         */
        maintainValidation($input, state) {
            // Clear any existing interval for this input
            const existingInterval = $input.data('validation-interval');
            if (existingInterval) {
                clearInterval(existingInterval);
            }

            // Only maintain non-loading, non-none states
            if (state === 'loading' || state === 'none') {
                return;
            }

            // Check every 500ms if validation elements still exist, recreate if missing
            const intervalId = setInterval(() => {
                const $parent = $input.parent();
                const hasIcon = $parent.find('.voxel-validation-icon').length > 0;
                const hasMessage = $input.siblings('.voxel-validation-message').length > 0;

                if (!hasIcon || !hasMessage) {
                    this.renderValidation($input, state);
                }
            }, 500);

            $input.data('validation-interval', intervalId);
        }

        /**
         * Disable submit button
         */
        disableSubmitButton() {
            const $buttons = $(
                'button[type="submit"], input[type="submit"], .ts-btn, ' +
                'button:contains("Publish"), button:contains("Submit")'
            );

            $buttons.each(function() {
                const $btn = $(this);
                $btn.prop('disabled', true);
                $btn.css({
                    'opacity': '0.5',
                    'cursor': 'not-allowed',
                    'pointer-events': 'none'
                });
                $btn.data('disabled-by-duplicate-checker', true);
            });
        }

        /**
         * Enable submit button
         */
        enableSubmitButton() {
            // Find ALL submit buttons, not just ones we marked
            const $buttons = $(
                'button[type="submit"], input[type="submit"], .ts-btn, ' +
                'button:contains("Publish"), button:contains("Submit")'
            );

            $buttons.each(function() {
                const $btn = $(this);
                $btn.prop('disabled', false);
                $btn.css({
                    'opacity': '',
                    'cursor': '',
                    'pointer-events': ''
                });
                $btn.removeData('disabled-by-duplicate-checker');
            });
        }

        /**
         * Get post type from form or URL
         */
        getPostType($input) {
            const $form = $input.closest('form');
            let postType = 'post';

            if ($form.length > 0) {
                const $postTypeInput = $form.find('input[name="post_type"]');
                if ($postTypeInput.length > 0) {
                    postType = $postTypeInput.val();
                }
            }

            if (postType === 'post') {
                const urlMatch = window.location.pathname.match(/create-([^\/]+)/);
                if (urlMatch) {
                    postType = urlMatch[1];
                }
            }

            return postType;
        }

        /**
         * Get current post ID
         */
        getCurrentPostId() {
            const $postIdInput = $('input[name="post_id"], input[name="post_ID"]');
            if ($postIdInput.length > 0) {
                return $postIdInput.val();
            }

            const urlMatch = window.location.search.match(/[?&]post=(\d+)/);
            if (urlMatch) {
                return urlMatch[1];
            }

            return 0;
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
    const checker = new DuplicateTitleChecker();
    checker.init();

})(jQuery);
