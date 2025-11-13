/**
 * Voxel Toolkit - Duplicate Title Checker
 * Real-time duplicate title detection for Voxel post creation forms
 */

(function($) {
    'use strict';

    // Configuration
    const config = {
        checkDelay: 500, // Delay in ms before checking (debounce)
        minTitleLength: 3, // Minimum title length before checking
        selectors: {
            // Common title field selectors for Voxel forms
            // Target the input inside the title field container
            titleInputs: [
                '.field-key-title input',
                '.ts-form-group.field-key-title input.ts-filter',
                'input[name="title"]',
                'input[name="post_title"]',
                'input.voxel-post-title',
                'input[data-field="title"]',
                'input[data-key="title"]',
                'input[data-field-key="title"]',
                '.elementor-field-type-text input[name*="title"]',
                'input.elementor-field[type="text"][name*="title"]'
            ].join(', '),

            // Selectors to EXCLUDE (non-title filter/search fields)
            // These are excluded UNLESS they're inside .field-key-title
            excludeInputs: [
                '.ts-filter:not(.field-key-title .ts-filter)',
                '[class*="search"]:not(.field-key-title *)',
                '[placeholder*="Search"]',
                '[placeholder*="Filter"]'
            ].join(', '),

            // Container for duplicate warning message
            warningContainer: 'voxel-toolkit-duplicate-warning'
        }
    };

    class DuplicateTitleChecker {
        constructor() {
            this.checkTimeout = null;
            this.currentRequest = null;
            this.lastCheckedTitle = '';
            this.debug = window.voxelToolkitDuplicateChecker?.debug || false;
            this.init();
        }

        log(...args) {
            if (this.debug) {
                console.log('[Voxel Toolkit - Duplicate Checker]', ...args);
            }
        }

        init() {
            this.log('Initializing Duplicate Title Checker');

            // Wait for DOM to be ready
            $(document).ready(() => {
                this.log('DOM ready, attaching event handlers');
                this.attachEventHandlers();

                // Also listen for dynamically added forms (Voxel loads some via AJAX)
                this.observeDOMChanges();
            });
        }

        /**
         * Attach event handlers to title input fields
         */
        attachEventHandlers() {
            this.log('Looking for title inputs with selectors:', config.selectors.titleInputs);
            let $titleInputs = $(config.selectors.titleInputs);

            this.log('Found potential title inputs (before filtering):', $titleInputs.length);

            // Filter out excluded inputs (search/filter fields)
            $titleInputs = $titleInputs.filter((index, input) => {
                const $input = $(input);
                const isExcluded = $input.is(config.selectors.excludeInputs);
                if (isExcluded) {
                    this.log('Excluding input (matches exclude selector):', input);
                }
                return !isExcluded;
            });

            this.log('Found title inputs after filtering:', $titleInputs.length);

            if ($titleInputs.length === 0) {
                this.log('No title inputs found, will retry in 1 second');
                // Try again after a short delay (for dynamically loaded forms)
                setTimeout(() => this.attachEventHandlers(), 1000);
                return;
            }

            $titleInputs.each((index, input) => {
                const $input = $(input);

                // Skip if already initialized
                if ($input.data('duplicate-checker-initialized')) {
                    this.log('Input already initialized, skipping:', input);
                    return;
                }

                this.log('Initializing duplicate checker on input:', input);
                $input.data('duplicate-checker-initialized', true);

                // Create warning container
                this.createWarningContainer($input);

                // Attach input event with debounce
                $input.on('input', (e) => {
                    this.log('Input event triggered');
                    this.handleTitleInput($(e.target));
                });

                // Also check on blur
                $input.on('blur', (e) => {
                    this.log('Blur event triggered');
                    this.handleTitleInput($(e.target), true);
                });

                this.log('Duplicate Title Checker initialized on input #' + index);
            });
        }

        /**
         * Create warning container near the title input
         */
        createWarningContainer($input) {
            this.log('Creating warning container for input:', $input);

            // Check if container already exists
            let $container = $input.siblings('.' + config.selectors.warningContainer);

            if ($container.length === 0) {
                this.log('Container does not exist, creating new one');
                $container = $('<div>', {
                    class: config.selectors.warningContainer,
                    style: 'display: none; margin-top: 8px;'
                });

                // Insert after the input or its parent container
                const $parent = $input.closest('.ts-form-group, .form-group, .voxel-input-container');
                this.log('Parent container:', $parent);

                if ($parent.length > 0) {
                    this.log('Inserting after parent container');
                    $parent.after($container);
                } else {
                    this.log('Inserting after input directly');
                    $input.after($container);
                }
            } else {
                this.log('Container already exists');
            }

            return $container;
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

            // Don't check if title is too short or unchanged
            if (title.length < config.minTitleLength || title === this.lastCheckedTitle) {
                this.clearWarning($input);
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
            this.log('Checking for duplicates of title:', title);

            // Cancel any pending request
            if (this.currentRequest) {
                this.log('Aborting previous request');
                this.currentRequest.abort();
            }

            // Show loading indicator
            this.showLoading($input);

            // Get post type from form data or URL
            const postType = this.getPostType($input);
            this.log('Post type detected:', postType);

            // Get current post ID (if editing)
            const postId = this.getCurrentPostId();
            this.log('Current post ID:', postId);

            // Store the title being checked
            this.lastCheckedTitle = title;

            const ajaxData = {
                action: 'voxel_toolkit_check_duplicate_title',
                nonce: voxelToolkitDuplicateChecker.nonce,
                title: title,
                post_type: postType,
                post_id: postId
            };

            this.log('Sending AJAX request with data:', ajaxData);

            // Make AJAX request
            this.currentRequest = $.ajax({
                url: voxelToolkitDuplicateChecker.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    this.log('AJAX response received:', response);
                    this.handleResponse($input, response);
                },
                error: (xhr, status, error) => {
                    if (status !== 'abort') {
                        this.log('AJAX error:', status, error, xhr);
                        console.error('Duplicate title check failed:', error);
                        this.clearWarning($input);
                    }
                },
                complete: () => {
                    this.log('AJAX request complete');
                    this.currentRequest = null;
                }
            });
        }

        /**
         * Handle AJAX response
         */
        handleResponse($input, response) {
            this.log('Handling response:', response);

            if (!response.success) {
                this.log('Response not successful, clearing warning');
                this.clearWarning($input);
                this.enableSubmitButton();
                return;
            }

            const data = response.data;
            this.log('Response data:', data);

            if (data.has_duplicate) {
                this.log('Duplicate found! Showing warning');
                this.showWarning($input, data);

                // Block submission if setting is enabled
                if (window.voxelToolkitDuplicateChecker?.block_duplicate) {
                    this.disableSubmitButton();
                }
            } else {
                this.log('No duplicate found, clearing warning');
                this.clearWarning($input);
                this.enableSubmitButton();
            }
        }

        /**
         * Show loading indicator
         */
        showLoading($input) {
            const $container = this.getWarningContainer($input);
            $container.html('<div class="voxel-toolkit-checking">Checking for duplicates...</div>');
            $container.show();
        }

        /**
         * Show duplicate warning
         */
        showWarning($input, data) {
            this.log('Showing warning with data:', data);
            const $container = this.getWarningContainer($input);
            this.log('Warning container:', $container);

            // Modern, Voxel-styled warning box
            let html = '<div class="voxel-toolkit-duplicate-alert" style="';
            html += 'background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%);';
            html += 'border-left: 4px solid #f59e0b;';
            html += 'border-radius: 8px;';
            html += 'padding: 20px 24px;';
            html += 'margin-top: 16px;';
            html += 'box-shadow: 0 2px 8px rgba(245, 158, 11, 0.15);';
            html += 'font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;';
            html += 'line-height: 1.6;';
            html += '">';

            // Header with icon and title
            html += '<div style="display: flex; align-items: flex-start; gap: 16px;">';

            // Warning icon
            html += '<div style="flex-shrink: 0; margin-top: 3px;">';
            html += '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" style="color: #f59e0b;">';
            html += '<path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
            html += '</svg>';
            html += '</div>';

            // Content
            html += '<div style="flex: 1; min-width: 0;">';

            // Title
            html += '<div style="';
            html += 'font-size: 16px;';
            html += 'font-weight: 600;';
            html += 'color: #92400e;';
            html += 'margin-bottom: 14px;';
            html += 'line-height: 1.5;';
            html += '">';
            html += data.message;
            html += '</div>';

            // Duplicate posts list
            if (data.duplicates && data.duplicates.length > 0) {
                html += '<div style="';
                html += 'background: rgba(255, 255, 255, 0.7);';
                html += 'border-radius: 8px;';
                html += 'padding: 16px 18px;';
                html += 'margin-top: 4px;';
                html += '">';

                html += '<div style="';
                html += 'font-size: 14px;';
                html += 'font-weight: 600;';
                html += 'color: #78350f;';
                html += 'margin-bottom: 12px;';
                html += '">Similar posts found:</div>';

                html += '<div style="display: flex; flex-direction: column; gap: 10px;">';

                data.duplicates.forEach((duplicate) => {
                    html += '<div style="';
                    html += 'display: flex;';
                    html += 'align-items: center;';
                    html += 'gap: 12px;';
                    html += 'padding: 12px 14px;';
                    html += 'background: white;';
                    html += 'border-radius: 6px;';
                    html += 'border: 1px solid #fde68a;';
                    html += 'transition: all 0.2s;';
                    html += '">';

                    // Post icon
                    html += '<svg width="18" height="18" viewBox="0 0 16 16" fill="none" style="flex-shrink: 0; color: #f59e0b;">';
                    html += '<path d="M2 4a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V4z" stroke="currentColor" stroke-width="1.5"/>';
                    html += '<path d="M5 7h6M5 10h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>';
                    html += '</svg>';

                    // Post title and status
                    html += '<div style="flex: 1; min-width: 0;">';
                    html += `<a href="${duplicate.url}" target="_blank" style="`;
                    html += 'color: #92400e;';
                    html += 'text-decoration: none;';
                    html += 'font-size: 14px;';
                    html += 'font-weight: 500;';
                    html += 'display: block;';
                    html += 'line-height: 1.4;';
                    html += '">';
                    html += this.escapeHtml(duplicate.title);
                    html += '</a>';
                    html += '</div>';

                    // Status badge
                    if (duplicate.status !== 'publish') {
                        html += '<span style="';
                        html += 'font-size: 12px;';
                        html += 'padding: 4px 10px;';
                        html += 'background: #fef3c7;';
                        html += 'color: #78350f;';
                        html += 'border-radius: 12px;';
                        html += 'font-weight: 500;';
                        html += 'text-transform: capitalize;';
                        html += 'flex-shrink: 0;';
                        html += '">';
                        html += duplicate.status;
                        html += '</span>';
                    }

                    html += '</div>';
                });

                html += '</div>';
                html += '</div>';
            }

            html += '</div>'; // Close content div
            html += '</div>'; // Close flex container
            html += '</div>'; // Close alert div

            this.log('Setting HTML and showing container');
            $container.html(html);
            $container.show();
            this.log('Warning displayed');
        }

        /**
         * Clear warning message
         */
        clearWarning($input) {
            const $container = this.getWarningContainer($input);
            $container.hide().empty();
        }

        /**
         * Get warning container for input
         */
        getWarningContainer($input) {
            // First try to find as sibling of input
            let $container = $input.siblings('.' + config.selectors.warningContainer);

            // If not found, look for it after the parent container
            if ($container.length === 0) {
                const $parent = $input.closest('.ts-form-group, .form-group, .voxel-input-container');
                if ($parent.length > 0) {
                    $container = $parent.next('.' + config.selectors.warningContainer);
                }
            }

            this.log('getWarningContainer found:', $container.length, 'containers');
            return $container;
        }

        /**
         * Get post type from form or URL
         */
        getPostType($input) {
            // Try to find post_type input in the form
            const $form = $input.closest('form');
            let postType = 'post';

            if ($form.length > 0) {
                const $postTypeInput = $form.find('input[name="post_type"]');
                if ($postTypeInput.length > 0) {
                    postType = $postTypeInput.val();
                }
            }

            // If not found, try to extract from URL
            if (postType === 'post') {
                const urlMatch = window.location.pathname.match(/create-([^\/]+)/);
                if (urlMatch) {
                    postType = urlMatch[1];
                }
            }

            return postType;
        }

        /**
         * Get current post ID (for edit pages)
         */
        getCurrentPostId() {
            // Try to find post_id in form
            const $postIdInput = $('input[name="post_id"], input[name="post_ID"]');
            if ($postIdInput.length > 0) {
                return $postIdInput.val();
            }

            // Try to extract from URL
            const urlMatch = window.location.search.match(/[?&]post=(\d+)/);
            if (urlMatch) {
                return urlMatch[1];
            }

            return 0;
        }

        /**
         * Observe DOM changes for dynamically loaded forms
         */
        observeDOMChanges() {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.addedNodes.length > 0) {
                        // Re-attach handlers after a short delay
                        setTimeout(() => this.attachEventHandlers(), 100);
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

        /**
         * Disable submit/publish button when duplicates found
         */
        disableSubmitButton() {
            this.log('Disabling submit button due to duplicate title');

            // Find common submit button selectors in Voxel forms
            const $submitButtons = $(
                'button[type="submit"], ' +
                'input[type="submit"], ' +
                '.ts-btn.create-btn, ' +
                'button.create-btn, ' +
                'button:contains("Publish"), ' +
                'button:contains("Submit")'
            );

            $submitButtons.each((index, button) => {
                const $button = $(button);
                if (!$button.data('original-disabled-state')) {
                    $button.data('original-disabled-state', $button.prop('disabled'));
                }
                $button.prop('disabled', true);
                $button.addClass('voxel-toolkit-blocked-duplicate');
                $button.attr('title', 'Cannot submit: duplicate title detected');
            });

            this.log('Disabled', $submitButtons.length, 'submit buttons');
        }

        /**
         * Enable submit/publish button
         */
        enableSubmitButton() {
            this.log('Enabling submit button');

            const $blockedButtons = $('.voxel-toolkit-blocked-duplicate');

            $blockedButtons.each((index, button) => {
                const $button = $(button);
                const originalState = $button.data('original-disabled-state');

                if (originalState !== undefined) {
                    $button.prop('disabled', originalState);
                    $button.removeData('original-disabled-state');
                } else {
                    $button.prop('disabled', false);
                }

                $button.removeClass('voxel-toolkit-blocked-duplicate');
                $button.removeAttr('title');
            });

            this.log('Enabled', $blockedButtons.length, 'submit buttons');
        }
    }

    // Initialize the duplicate title checker
    new DuplicateTitleChecker();

})(jQuery);
