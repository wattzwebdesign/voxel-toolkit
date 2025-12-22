/**
 * Timeline Filters - Handle custom ordering options for Voxel Timeline
 *
 * This script adds the "Unanswered" ordering option to Timeline widgets
 * when enabled in Elementor widget settings.
 *
 * @package Voxel_Toolkit
 */
(function() {
    'use strict';

    // Check if config is available
    if (typeof vtTimelineFilters === 'undefined' || !vtTimelineFilters.filters) {
        return;
    }

    var ajaxUrl = vtTimelineFilters.ajaxUrl;

    /**
     * Get widget ID from element
     */
    function getWidgetId(el) {
        var widgetEl = el.closest('[data-id]');
        return widgetEl ? widgetEl.getAttribute('data-id') : null;
    }

    /**
     * Check if widget has unanswered filter enabled
     */
    function isWidgetEnabled(widgetId) {
        return window.vtTimelineWidgetConfig && window.vtTimelineWidgetConfig[widgetId];
    }

    /**
     * Get widget config
     */
    function getWidgetConfig(widgetId) {
        return window.vtTimelineWidgetConfig ? window.vtTimelineWidgetConfig[widgetId] : null;
    }

    /**
     * Listen for timeline init events to inject filter and override getFeed
     */
    document.addEventListener('voxel/timeline/init', function(e) {
        var app = e.detail.app;
        var config = e.detail.config;
        var el = e.detail.el;

        if (!app || !config || !el) {
            return;
        }

        // Get widget ID and check if enabled
        var widgetId = getWidgetId(el);
        if (!widgetId || !isWidgetEnabled(widgetId)) {
            return;
        }

        var widgetConfig = getWidgetConfig(widgetId);

        // Inject the ordering option into config
        if (config.settings && config.settings.ordering_options) {
            config.settings.ordering_options.push({
                _id: 'vt_unanswered_' + widgetId,
                label: widgetConfig.label,
                order: 'unanswered',
                time: 'all_time',
            });
        }

        // Override the mount function to intercept after Vue mounts
        var originalMount = app.mount;
        if (originalMount && !app._vtTimelineFiltersApplied) {
            app._vtTimelineFiltersApplied = true;

            app.mount = function(mountEl) {
                var result = originalMount.call(this, mountEl);

                // After mount, setup the getFeed override
                setTimeout(function() {
                    setupFeedOverride(app, config);
                }, 50);

                return result;
            };
        }
    });

    /**
     * Setup getFeed override using the app object
     */
    function setupFeedOverride(app, config, retryCount) {
        retryCount = retryCount || 0;

        // Get component via _vnode (Vue 3 pattern)
        if (app._container && app._container._vnode && app._container._vnode.component) {
            findStatusFeedComponents(app._container._vnode.component, config);
            return;
        }

        // Retry if not ready yet
        if (retryCount < 10) {
            setTimeout(function() {
                setupFeedOverride(app, config, retryCount + 1);
            }, 100);
        }
    }

    /**
     * Recursively find status-feed components and override their getFeed
     */
    function findStatusFeedComponents(instance, config) {
        if (!instance) return;

        var proxy = instance.proxy || instance;

        // Check if this is a status-feed component (has getFeed and orderBy)
        if (typeof proxy.getFeed === 'function' &&
            typeof proxy.orderBy !== 'undefined' &&
            !proxy._vtFeedOverridden) {

            proxy._vtFeedOverridden = true;
            var originalGetFeed = proxy.getFeed.bind(proxy);

            proxy.getFeed = function(options) {
                options = options || {};
                var activeOrder = proxy.orderBy ? proxy.orderBy.active : null;

                // Check if "unanswered" order is selected
                if (activeOrder && activeOrder.order === 'unanswered') {
                    return handleUnansweredFeed(proxy, config, options);
                }

                // Use original method for other orders
                return originalGetFeed(options);
            };
        }

        // Check subTree for child components
        if (instance.subTree) {
            if (instance.subTree.component) {
                findStatusFeedComponents(instance.subTree.component, config);
            }
            if (instance.subTree.children && Array.isArray(instance.subTree.children)) {
                instance.subTree.children.forEach(function(child) {
                    if (child && child.component) {
                        findStatusFeedComponents(child.component, config);
                    }
                });
            }
        }
    }

    /**
     * Handle Unanswered feed request
     */
    function handleUnansweredFeed(component, config, options) {
        component.loading = true;

        // Build query parameters
        var params = new URLSearchParams();
        params.append('page', component.page || 1);

        // Get mode from the component's config
        var mode = component.config.timeline ? component.config.timeline.mode : '';
        params.append('mode', mode);

        // Add post_id if available
        if (component.config.current_post && component.config.current_post.id) {
            params.append('post_id', component.config.current_post.id);
        }

        // Add user_id if available
        if (component.config.current_author && component.config.current_author.id) {
            params.append('user_id', component.config.current_author.id);
        }

        // Make AJAX request to our custom endpoint
        var url = ajaxUrl + '&' + params.toString();

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
            if (response.success) {
                if (component.page === 1) {
                    component.list = response.data;
                } else {
                    component.list.push.apply(component.list, response.data);
                }

                component.hasMore = response.has_more;

                if (component.list.length) {
                    component.showFilters = true;
                }
            } else {
                if (typeof Voxel !== 'undefined' && Voxel.alert) {
                    Voxel.alert(response.message || 'An error occurred', 'error');
                }
            }

            component.loading = false;
        })
        .catch(function(error) {
            console.error('Timeline Filters Error:', error);
            component.loading = false;

            if (typeof Voxel !== 'undefined' && Voxel.alert) {
                Voxel.alert('Failed to load posts', 'error');
            }
        });
    }

})();
