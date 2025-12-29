/**
 * Timeline Reply Summary - Display AI-generated summaries of timeline replies
 * Shows summary only when replies are expanded
 *
 * @package Voxel_Toolkit
 */
(function() {
    'use strict';

    // Check if config is available
    if (typeof vtReplySummary === 'undefined') {
        return;
    }

    var ajaxUrl = vtReplySummary.ajaxUrl;
    var threshold = vtReplySummary.threshold || 3;
    var label = vtReplySummary.label || 'TL;DR';
    var loadingText = vtReplySummary.loadingText || 'Generating summary...';
    var errorText = vtReplySummary.errorText || 'Summary unavailable';

    // Track processed statuses to avoid duplicates
    var processedStatuses = {};

    /**
     * Create summary container HTML
     */
    function createSummaryContainer(statusId) {
        var container = document.createElement('div');
        container.className = 'vt-reply-summary';
        container.setAttribute('data-status-id', statusId);
        container.innerHTML = [
            '<div class="vt-reply-summary__header">',
            '  <span class="vt-reply-summary__icon">',
            '    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">',
            '      <path d="M9.5 2l1.5 3.5L14.5 7l-3.5 1.5L9.5 12 8 8.5 4.5 7 8 5.5 9.5 2zm5 10l1 2.5 2.5 1-2.5 1-1 2.5-1-2.5-2.5-1 2.5-1 1-2.5zM5 14l1.5 3L10 18.5 6.5 20 5 23l-1.5-3L0 18.5 3.5 17 5 14z"/>',
            '    </svg>',
            '  </span>',
            '  <span class="vt-reply-summary__label">' + escapeHtml(label) + '</span>',
            '  <span class="vt-reply-summary__badge">AI</span>',
            '  <span class="vt-reply-summary__toggle">',
            '    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">',
            '      <path d="M7 10l5 5 5-5H7z" fill="currentColor"/>',
            '    </svg>',
            '  </span>',
            '</div>',
            '<div class="vt-reply-summary__content" style="display: none;">',
            '  <div class="vt-reply-summary__loading">',
            '    <span class="vt-reply-summary__spinner"></span>',
            '    <span>' + escapeHtml(loadingText) + '</span>',
            '  </div>',
            '</div>'
        ].join('');

        return container;
    }

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Fetch summary from server
     */
    function fetchSummary(statusId, contentEl) {
        var url = ajaxUrl + '&status_id=' + statusId;

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(response) {
            if (response.success && response.has_summary) {
                contentEl.innerHTML = '<div class="vt-reply-summary__text">' + escapeHtml(response.summary) + '</div>';
            } else {
                contentEl.innerHTML = '<div class="vt-reply-summary__error">' + escapeHtml(errorText) + '</div>';
            }
        })
        .catch(function(error) {
            console.error('VT Reply Summary Error:', error);
            contentEl.innerHTML = '<div class="vt-reply-summary__error">' + escapeHtml(errorText) + '</div>';
        });
    }

    /**
     * Toggle summary visibility
     */
    function toggleSummary(container) {
        var contentEl = container.querySelector('.vt-reply-summary__content');
        var isExpanded = container.classList.contains('vt-reply-summary--expanded');

        if (isExpanded) {
            container.classList.remove('vt-reply-summary--expanded');
            contentEl.style.display = 'none';
        } else {
            container.classList.add('vt-reply-summary--expanded');
            contentEl.style.display = 'block';

            // Fetch summary if not already loaded
            if (!container.getAttribute('data-loaded')) {
                var statusId = container.getAttribute('data-status-id');
                fetchSummary(statusId, contentEl);
                container.setAttribute('data-loaded', 'true');
            }
        }
    }

    /**
     * Check if comment level is visible (replies expanded)
     */
    function isRepliesExpanded(subgridEl) {
        var commentLevel = subgridEl.querySelector('.vxf-comment-level');
        if (!commentLevel) {
            return false;
        }
        // Check if visible (has content and is displayed)
        var style = window.getComputedStyle(commentLevel);
        return style.display !== 'none' && commentLevel.children.length > 0;
    }

    /**
     * Show/hide summary based on replies visibility
     */
    function updateSummaryVisibility(subgridEl) {
        var summaryContainer = subgridEl.querySelector('.vt-reply-summary');
        if (!summaryContainer) {
            return;
        }

        if (isRepliesExpanded(subgridEl)) {
            summaryContainer.classList.add('vt-reply-summary--visible');
        } else {
            summaryContainer.classList.remove('vt-reply-summary--visible');
        }
    }

    /**
     * Process a post element by extracting status ID and checking DB for eligibility
     */
    function processPostElement(postEl) {
        // Find the parent subgrid
        var subgridEl = postEl.closest('.vxf-subgrid');
        if (!subgridEl) {
            return;
        }

        // Skip if already has summary
        if (subgridEl.querySelector('.vt-reply-summary')) {
            // Update visibility in case replies toggled
            updateSummaryVisibility(subgridEl);
            return;
        }

        // Find the status link to extract ID
        var statusLink = postEl.querySelector('a[href*="status_id="]');
        var statusId = null;

        if (statusLink) {
            var match = statusLink.href.match(/status_id=(\d+)/);
            if (match) {
                statusId = match[1];
            }
        }

        if (!statusId) {
            return;
        }

        // Skip if already processing this status
        if (processedStatuses[statusId]) {
            return;
        }

        // Mark as processing
        processedStatuses[statusId] = 'checking';

        // Check with server if this status qualifies (DB has reply count)
        checkStatusEligibility(statusId, function(eligible, data) {
            if (!eligible) {
                processedStatuses[statusId] = 'ineligible';
                return;
            }

            processedStatuses[statusId] = 'eligible';

            // Create and insert summary container
            var summaryContainer = createSummaryContainer(statusId);

            // Find the comment level (replies section) to insert before it
            var commentLevel = subgridEl.querySelector('.vxf-comment-level');
            if (commentLevel) {
                commentLevel.insertAdjacentElement('beforebegin', summaryContainer);
            } else {
                // Fallback: insert after the post
                postEl.insertAdjacentElement('afterend', summaryContainer);
            }

            // If we already have a summary, pre-load it
            if (data && data.summary) {
                var contentEl = summaryContainer.querySelector('.vt-reply-summary__content');
                contentEl.innerHTML = '<div class="vt-reply-summary__text">' + escapeHtml(data.summary) + '</div>';
                summaryContainer.setAttribute('data-loaded', 'true');
            }

            // Check if replies are already visible and show summary
            updateSummaryVisibility(subgridEl);

            // Add click handler for toggle
            var headerEl = summaryContainer.querySelector('.vt-reply-summary__header');
            headerEl.addEventListener('click', function(e) {
                e.preventDefault();
                toggleSummary(summaryContainer);
            });
        });
    }

    /**
     * Check if a status is eligible for summary (via AJAX to check DB)
     */
    function checkStatusEligibility(statusId, callback) {
        var url = ajaxUrl + '&status_id=' + statusId + '&check_only=1';

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(response) {
            if (response.success && response.eligible) {
                callback(true, response);
            } else {
                callback(false, response);
            }
        })
        .catch(function(error) {
            console.error('[VT Reply Summary] Error checking status', statusId, ':', error);
            callback(false, { error: error.message });
        });
    }

    /**
     * Scan the page for timeline posts and add summaries
     */
    function scanForPosts() {
        // Find all main posts (not comments)
        var posts = document.querySelectorAll('.vxf-subgrid > .vxf-post:not(.vxf-comment)');

        posts.forEach(function(postEl) {
            processPostElement(postEl);
        });

        // Also update visibility for all existing summaries
        document.querySelectorAll('.vxf-subgrid').forEach(function(subgridEl) {
            updateSummaryVisibility(subgridEl);
        });
    }

    /**
     * Listen for timeline init events
     */
    document.addEventListener('voxel/timeline/init', function(e) {
        // Delay to let Vue render
        setTimeout(scanForPosts, 500);
        setTimeout(scanForPosts, 1500);
    });

    /**
     * Setup MutationObserver to watch for new posts and reply visibility changes
     */
    function setupObserver() {
        var observer = new MutationObserver(function(mutations) {
            var shouldScan = false;
            var subgridsToUpdate = new Set();

            mutations.forEach(function(mutation) {
                // Check for new nodes
                if (mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            if (node.classList && (node.classList.contains('vxf-subgrid') || node.classList.contains('vxf-post'))) {
                                shouldScan = true;
                            } else if (node.querySelector && node.querySelector('.vxf-post')) {
                                shouldScan = true;
                            }
                            // Check if reply comments are being added
                            if (node.classList && node.classList.contains('vxf-comment')) {
                                var subgrid = node.closest('.vxf-subgrid');
                                if (subgrid) {
                                    subgridsToUpdate.add(subgrid);
                                }
                            }
                        }
                    });
                }

                // Check for removed nodes (replies being hidden)
                if (mutation.removedNodes.length > 0) {
                    mutation.removedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            if (node.classList && (node.classList.contains('vxf-comment') || node.classList.contains('vxf-comment-level'))) {
                                // Find parent subgrid from mutation target
                                var subgrid = mutation.target.closest('.vxf-subgrid');
                                if (subgrid) {
                                    subgridsToUpdate.add(subgrid);
                                }
                            }
                        }
                    });
                }

                // Check for attribute/class changes on comment-level (visibility toggle)
                if (mutation.type === 'attributes' || mutation.type === 'childList') {
                    var target = mutation.target;
                    if (target.classList && target.classList.contains('vxf-comment-level')) {
                        var subgrid = target.closest('.vxf-subgrid');
                        if (subgrid) {
                            subgridsToUpdate.add(subgrid);
                        }
                    }
                    // Also check parent subgrid for any childList changes
                    if (mutation.type === 'childList') {
                        var subgrid = target.closest('.vxf-subgrid');
                        if (subgrid && subgrid.querySelector('.vt-reply-summary')) {
                            subgridsToUpdate.add(subgrid);
                        }
                    }
                }
            });

            if (shouldScan) {
                setTimeout(scanForPosts, 100);
            }

            // Update visibility for affected subgrids
            subgridsToUpdate.forEach(function(subgrid) {
                setTimeout(function() {
                    updateSummaryVisibility(subgrid);
                }, 50);
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class']
        });
    }

    /**
     * Setup click handlers on reply count links to detect toggle
     */
    function setupReplyCountListeners() {
        // Use event delegation for reply count clicks
        document.addEventListener('click', function(e) {
            // Check if clicked element is or is within a reply count link
            var target = e.target.closest('.vxf-details a, .vxf-actions a');
            if (target) {
                // Delay check to let Vue update the DOM
                setTimeout(function() {
                    document.querySelectorAll('.vxf-subgrid').forEach(function(subgridEl) {
                        updateSummaryVisibility(subgridEl);
                    });
                }, 100);
                setTimeout(function() {
                    document.querySelectorAll('.vxf-subgrid').forEach(function(subgridEl) {
                        updateSummaryVisibility(subgridEl);
                    });
                }, 300);
            }
        }, true);
    }

    /**
     * Initialize on DOM ready
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Initial scan after a delay
        setTimeout(scanForPosts, 1000);
        setTimeout(scanForPosts, 2000);

        // Setup observer for dynamically loaded content
        setupObserver();

        // Setup click listeners for reply toggle detection
        setupReplyCountListeners();
    });

    // Also run if DOM is already loaded
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(scanForPosts, 500);
        setupObserver();
        setupReplyCountListeners();
    }

})();
