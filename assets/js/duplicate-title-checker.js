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
            titleInputs: [
                'input[name="title"]',
                'input[name="post_title"]',
                'input.voxel-post-title',
                'input[data-field="title"]',
                '.create-post-form input[type="text"]:first',
                '.ts-form input[name*="title"]',
                '.ts-form .ts-filter input[type="text"]'
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
            this.init();
        }

        init() {
            // Wait for DOM to be ready
            $(document).ready(() => {
                this.attachEventHandlers();

                // Also listen for dynamically added forms (Voxel loads some via AJAX)
                this.observeDOMChanges();
            });
        }

        /**
         * Attach event handlers to title input fields
         */
        attachEventHandlers() {
            const $titleInputs = $(config.selectors.titleInputs);

            if ($titleInputs.length === 0) {
                // Try again after a short delay (for dynamically loaded forms)
                setTimeout(() => this.attachEventHandlers(), 1000);
                return;
            }

            $titleInputs.each((index, input) => {
                const $input = $(input);

                // Skip if already initialized
                if ($input.data('duplicate-checker-initialized')) {
                    return;
                }

                $input.data('duplicate-checker-initialized', true);

                // Create warning container
                this.createWarningContainer($input);

                // Attach input event with debounce
                $input.on('input', (e) => {
                    this.handleTitleInput($(e.target));
                });

                // Also check on blur
                $input.on('blur', (e) => {
                    this.handleTitleInput($(e.target), true);
                });

                console.log('Voxel Toolkit: Duplicate Title Checker initialized');
            });
        }

        /**
         * Create warning container near the title input
         */
        createWarningContainer($input) {
            // Check if container already exists
            let $container = $input.siblings('.' + config.selectors.warningContainer);

            if ($container.length === 0) {
                $container = $('<div>', {
                    class: config.selectors.warningContainer,
                    style: 'display: none; margin-top: 8px;'
                });

                // Insert after the input or its parent container
                const $parent = $input.closest('.ts-form-group, .form-group, .voxel-input-container');
                if ($parent.length > 0) {
                    $parent.after($container);
                } else {
                    $input.after($container);
                }
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
            // Cancel any pending request
            if (this.currentRequest) {
                this.currentRequest.abort();
            }

            // Show loading indicator
            this.showLoading($input);

            // Get post type from form data or URL
            const postType = this.getPostType($input);

            // Get current post ID (if editing)
            const postId = this.getCurrentPostId();

            // Store the title being checked
            this.lastCheckedTitle = title;

            // Make AJAX request
            this.currentRequest = $.ajax({
                url: voxelToolkitDuplicateChecker.ajax_url,
                type: 'POST',
                data: {
                    action: 'voxel_toolkit_check_duplicate_title',
                    nonce: voxelToolkitDuplicateChecker.nonce,
                    title: title,
                    post_type: postType,
                    post_id: postId
                },
                success: (response) => {
                    this.handleResponse($input, response);
                },
                error: (xhr, status, error) => {
                    if (status !== 'abort') {
                        console.error('Duplicate title check failed:', error);
                        this.clearWarning($input);
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
                this.clearWarning($input);
                return;
            }

            const data = response.data;

            if (data.has_duplicate) {
                this.showWarning($input, data);
            } else {
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
            const $container = this.getWarningContainer($input);

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

            $container.html(html);
            $container.show();
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
