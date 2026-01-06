/**
 * Timeline Pin - Allow post authors to pin timeline posts to the top
 *
 * @package Voxel_Toolkit
 */
(function() {
    'use strict';

    // Check if config is available
    if (typeof vtTimelinePin === 'undefined') {
        return;
    }

    var config = window.vtTimelinePinConfig || {};
    var ajaxUrl = vtTimelinePin.ajaxUrl;
    var i18n = vtTimelinePin.i18n;

    // Track processed timelines
    var processedTimelines = new Set();

    // Track the currently active status element (the one whose dropdown is open)
    var activeStatusElement = null;

    /**
     * Get widget ID from element
     */
    function getWidgetId(el) {
        var widgetEl = el.closest('[data-id]');
        return widgetEl ? widgetEl.getAttribute('data-id') : null;
    }

    /**
     * Check if widget has pin enabled
     */
    function isWidgetEnabled(widgetId) {
        return config.widgets && config.widgets[widgetId] && config.widgets[widgetId].enabled;
    }

    /**
     * Pin icon SVG
     */
    var pinIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/></svg>';

    /**
     * Listen for timeline init events
     */
    document.addEventListener('voxel/timeline/init', function(e) {
        var app = e.detail.app;
        var timelineConfig = e.detail.config;
        var el = e.detail.el;

        if (!app || !timelineConfig || !el) {
            return;
        }

        // Get widget ID and check if enabled
        var widgetId = getWidgetId(el);
        if (!widgetId || !isWidgetEnabled(widgetId)) {
            return;
        }

        // Skip if already processed
        if (processedTimelines.has(widgetId)) {
            return;
        }
        processedTimelines.add(widgetId);

        // Override the mount function to intercept after Vue mounts
        var originalMount = app.mount;
        if (originalMount && !app._vtTimelinePinApplied) {
            app._vtTimelinePinApplied = true;

            app.mount = function(mountEl) {
                var result = originalMount.call(this, mountEl);

                // After mount, setup the pin functionality
                setTimeout(function() {
                    setupPinFunctionality(app, timelineConfig, el);
                }, 100);

                return result;
            };
        }
    });

    /**
     * Setup pin functionality
     */
    function setupPinFunctionality(app, timelineConfig, containerEl) {
        // Setup click listener to track which status's more button was clicked
        setupMoreButtonTracker(containerEl);

        // Setup dropdown observer
        setupDropdownObserver(containerEl);

        // Setup list interceptor to reorder pinned posts
        setupListInterceptor(app, timelineConfig);
    }

    /**
     * Track clicks on .vxf-more buttons to know which status triggered the dropdown
     */
    function setupMoreButtonTracker(containerEl) {
        // Use event delegation on the container
        document.addEventListener('mousedown', function(e) {
            var moreBtn = e.target.closest('.vxf-more');
            if (moreBtn) {
                // Find the parent status element
                var statusEl = moreBtn.closest('.vxf-post:not(.vxf-comment)');
                if (statusEl) {
                    activeStatusElement = statusEl;
                }
            }
        }, true); // Use capture phase to get it before Vue handles it
    }

    /**
     * Setup MutationObserver to watch for dropdown menus
     */
    function setupDropdownObserver(containerEl) {
        // Watch for dropdown menus appearing anywhere in the document (they're teleported to body)
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Check if this is a timeline dropdown
                        var dropdown = node.querySelector('.ts-timeline-popup .ts-term-dropdown-list');
                        if (dropdown) {
                            injectPinAction(dropdown);
                        }
                        // Also check if the node itself is the popup
                        if (node.classList && node.classList.contains('ts-timeline-popup')) {
                            var list = node.querySelector('.ts-term-dropdown-list');
                            if (list) {
                                injectPinAction(list);
                            }
                        }
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Inject pin action into dropdown menu
     */
    function injectPinAction(dropdownList) {
        // Check if already injected
        if (dropdownList.querySelector('.vt-pin-action')) {
            return;
        }

        // Only show if user can pin
        if (!config.canPin) {
            return;
        }

        // Find the status ID from the dropdown context
        var popup = dropdownList.closest('.ts-timeline-popup');
        if (!popup) {
            return;
        }

        // Get the status element that triggered this dropdown
        // We need to find the .vxf-post that has the active dropdown
        var statusEl = findActiveStatusElement();
        if (!statusEl) {
            return;
        }

        var statusId = getStatusIdFromElement(statusEl);
        if (!statusId) {
            return;
        }

        // Check if this status is currently pinned
        var isPinned = config.pinnedStatusId === statusId;

        // Create pin action
        var pinLi = document.createElement('li');
        pinLi.className = 'vt-pin-action';

        var pinLink = document.createElement('a');
        pinLink.href = '#';
        pinLink.className = 'flexify';
        pinLink.innerHTML = '<span>' + (isPinned ? i18n.unpin : i18n.pinToTop) + '</span>';

        pinLink.addEventListener('click', function(e) {
            e.preventDefault();
            handlePinToggle(statusId, statusEl, isPinned);

            // Close the dropdown
            var blurEvent = new Event('blur', { bubbles: true });
            popup.dispatchEvent(blurEvent);
        });

        pinLi.appendChild(pinLink);

        // Insert at the beginning of the list
        var firstLi = dropdownList.querySelector('li');
        if (firstLi) {
            dropdownList.insertBefore(pinLi, firstLi);
        } else {
            dropdownList.appendChild(pinLi);
        }
    }

    /**
     * Find the status element that has an active dropdown
     */
    function findActiveStatusElement() {
        // Return the tracked active status element
        return activeStatusElement;
    }

    /**
     * Get status ID from element
     */
    function getStatusIdFromElement(statusEl) {
        // Look for a link with status_id parameter
        var statusLink = statusEl.querySelector('a[href*="status_id="]');
        if (statusLink) {
            var match = statusLink.href.match(/status_id=(\d+)/);
            if (match) {
                return parseInt(match[1], 10);
            }
        }

        // Also try data attribute if available
        var dataId = statusEl.getAttribute('data-status-id');
        if (dataId) {
            return parseInt(dataId, 10);
        }

        return null;
    }

    /**
     * Handle pin toggle AJAX
     */
    function handlePinToggle(statusId, statusEl, wasPin) {
        // Send AJAX request
        var formData = new FormData();
        formData.append('nonce', vtTimelinePin.nonce);
        formData.append('post_id', config.postId);
        formData.append('status_id', statusId);

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(response) {
            if (response.success) {
                // Update local config
                var previousPinned = config.pinnedStatusId;
                config.pinnedStatusId = response.pinned_status_id;

                // Update UI
                updatePinnedUI(statusId, response.is_pinned, previousPinned);

                // Show success notification
                if (typeof Voxel !== 'undefined' && Voxel.notify) {
                    Voxel.notify({
                        type: 'success',
                        message: response.is_pinned ? i18n.pinned : i18n.unpin
                    });
                }
            } else {
                // Show error
                if (typeof Voxel !== 'undefined' && Voxel.notify) {
                    Voxel.notify({
                        type: 'error',
                        message: response.message || i18n.error
                    });
                }
            }
        })
        .catch(function(error) {
            console.error('Pin toggle error:', error);
            if (typeof Voxel !== 'undefined' && Voxel.notify) {
                Voxel.notify({
                    type: 'error',
                    message: i18n.error
                });
            }
        });
    }

    /**
     * Update UI after pin toggle
     */
    function updatePinnedUI(statusId, isPinned, previousPinned) {
        // Remove pinned state from previous pinned post
        if (previousPinned) {
            var prevEl = findStatusElementById(previousPinned);
            if (prevEl) {
                prevEl.classList.remove('vt-pinned');
                var prevBadge = prevEl.querySelector('.vt-pinned-badge');
                if (prevBadge) {
                    prevBadge.remove();
                }
                var prevIcon = prevEl.querySelector('.vt-pin-icon');
                if (prevIcon) {
                    prevIcon.remove();
                }
            }
        }

        // Update new pinned post
        var statusEl = findStatusElementById(statusId);
        if (statusEl) {
            if (isPinned) {
                statusEl.classList.add('vt-pinned');
                addPinnedBadge(statusEl);
                addPinIcon(statusEl);
                // Move to top
                movePostToTop(statusEl);
            } else {
                statusEl.classList.remove('vt-pinned');
                var badge = statusEl.querySelector('.vt-pinned-badge');
                if (badge) {
                    badge.remove();
                }
                var icon = statusEl.querySelector('.vt-pin-icon');
                if (icon) {
                    icon.remove();
                }
            }
        }
    }

    /**
     * Find status element by ID
     */
    function findStatusElementById(statusId) {
        var allStatuses = document.querySelectorAll('.vxf-post:not(.vxf-comment)');
        for (var i = 0; i < allStatuses.length; i++) {
            var el = allStatuses[i];
            var elId = getStatusIdFromElement(el);
            if (elId === statusId) {
                return el;
            }
        }
        return null;
    }

    /**
     * Add pinned badge to status element
     */
    function addPinnedBadge(statusEl) {
        // Check if badge already exists
        if (statusEl.querySelector('.vt-pinned-badge')) {
            return;
        }

        var badge = document.createElement('div');
        badge.className = 'vt-pinned-badge';
        badge.innerHTML = pinIconSvg + '<span>' + i18n.pinned + '</span>';

        // Insert at the beginning of the post
        var firstChild = statusEl.firstChild;
        if (firstChild) {
            statusEl.insertBefore(badge, firstChild);
        } else {
            statusEl.appendChild(badge);
        }
    }

    /**
     * Add pin icon next to actions button
     */
    function addPinIcon(statusEl) {
        // Check if icon already exists
        if (statusEl.querySelector('.vt-pin-icon')) {
            return;
        }

        var moreBtn = statusEl.querySelector('.vxf-more');
        if (moreBtn) {
            var pinIcon = document.createElement('span');
            pinIcon.className = 'vt-pin-icon';
            pinIcon.innerHTML = pinIconSvg;
            moreBtn.parentNode.insertBefore(pinIcon, moreBtn);
        }
    }

    /**
     * Move post to top of the list
     */
    function movePostToTop(statusEl) {
        // Each post is wrapped in its own .vxf-subgrid container
        // We need to move the parent .vxf-subgrid, not the post itself
        var postWrapper = statusEl.closest('.vxf-subgrid');
        if (!postWrapper) {
            console.log('VT Pin: No .vxf-subgrid wrapper found');
            return;
        }

        // The main feed container is the parent of .vxf-subgrid (usually .vxfeed)
        var feedContainer = postWrapper.parentNode;
        if (!feedContainer) {
            console.log('VT Pin: No feed container found');
            return;
        }

        console.log('VT Pin: feedContainer class', feedContainer.className);

        // Find the filters section (.vxf-filters)
        var filtersEl = feedContainer.querySelector(':scope > .vxf-filters');
        console.log('VT Pin: filtersEl', filtersEl);

        if (filtersEl) {
            // Insert right after the filters
            console.log('VT Pin: Moving after filters');
            if (filtersEl.nextElementSibling !== postWrapper) {
                feedContainer.insertBefore(postWrapper, filtersEl.nextElementSibling);
            }
        } else {
            // No filters, insert at the beginning (after any non-subgrid elements)
            var firstSubgrid = feedContainer.querySelector(':scope > .vxf-subgrid');
            console.log('VT Pin: Moving before first subgrid', firstSubgrid);
            if (firstSubgrid && firstSubgrid !== postWrapper) {
                feedContainer.insertBefore(postWrapper, firstSubgrid);
            }
        }
    }

    /**
     * Setup list interceptor to handle pinned posts on load
     */
    function setupListInterceptor(app, timelineConfig) {
        // Wait for Vue component to be ready
        var attempts = 0;
        var maxAttempts = 20;

        function checkAndSetup() {
            attempts++;

            if (app._container && app._container._vnode && app._container._vnode.component) {
                processExistingPosts();
                setupListWatcher(app);
                return;
            }

            if (attempts < maxAttempts) {
                setTimeout(checkAndSetup, 100);
            }
        }

        checkAndSetup();
    }

    /**
     * Process existing posts to mark and reorder pinned
     */
    function processExistingPosts() {
        if (!config.pinnedStatusId) {
            return;
        }

        // Wait a bit for posts to fully render
        setTimeout(function() {
            // Find and mark the pinned post
            var pinnedEl = findStatusElementById(config.pinnedStatusId);
            if (pinnedEl) {
                pinnedEl.classList.add('vt-pinned');
                addPinnedBadge(pinnedEl);
                addPinIcon(pinnedEl);
                movePostToTop(pinnedEl);
            }
        }, 200);
    }

    /**
     * Setup watcher for list changes (pagination, filters)
     */
    function setupListWatcher(app) {
        // Use MutationObserver to detect when new posts are added
        // Look for .vxfeed or .ts-timeline container
        var timelineContainer = document.querySelector('.vxfeed') || document.querySelector('.ts-timeline');
        if (!timelineContainer) {
            return;
        }

        var listObserver = new MutationObserver(function(mutations) {
            // Check if pinned post needs to be reprocessed
            if (config.pinnedStatusId) {
                var pinnedEl = findStatusElementById(config.pinnedStatusId);
                if (pinnedEl && !pinnedEl.classList.contains('vt-pinned')) {
                    pinnedEl.classList.add('vt-pinned');
                    addPinnedBadge(pinnedEl);
                    addPinIcon(pinnedEl);
                    movePostToTop(pinnedEl);
                }
            }
        });

        listObserver.observe(timelineContainer, {
            childList: true,
            subtree: true
        });
    }

})();
