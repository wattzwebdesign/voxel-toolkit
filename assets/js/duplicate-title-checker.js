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
            if (this.debug || true) { // Always log for now during testing
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
                return;
            }

            const data = response.data;
            this.log('Response data:', data);

            if (data.has_duplicate) {
                this.log('Duplicate found! Showing warning');
                this.showWarning($input, data);
            } else {
                this.log('No duplicate found, clearing warning');
                this.clearWarning($input);
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

            let html = '<div class="voxel-toolkit-duplicate-alert" style="padding: 12px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404;">';
            html += '<div style="display: flex; align-items: center; margin-bottom: 8px;">';
            html += '<svg width="20" height="20" style="margin-right: 8px; flex-shrink: 0;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>';
            html += '<strong>' + data.message + '</strong>';
            html += '</div>';

            // Add links to duplicate posts
            if (data.duplicates && data.duplicates.length > 0) {
                html += '<div style="margin-top: 8px; font-size: 13px;">';
                html += '<div style="margin-bottom: 4px;">Similar posts:</div>';
                html += '<ul style="margin: 4px 0 0 20px; padding: 0;">';

                data.duplicates.forEach((duplicate) => {
                    const statusBadge = duplicate.status !== 'publish' ? ` <span style="font-size: 11px; opacity: 0.7;">(${duplicate.status})</span>` : '';
                    html += `<li style="margin-bottom: 4px;"><a href="${duplicate.url}" target="_blank" style="color: #856404; text-decoration: underline;">${this.escapeHtml(duplicate.title)}</a>${statusBadge}</li>`;
                });

                html += '</ul>';
                html += '</div>';
            }

            html += '</div>';

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
            return $input.siblings('.' + config.selectors.warningContainer);
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
    }

    // Initialize the duplicate title checker
    new DuplicateTitleChecker();

})(jQuery);
