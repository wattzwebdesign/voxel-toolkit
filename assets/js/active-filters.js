/**
 * Active Filters Widget JavaScript
 *
 * Handles URL monitoring and dynamic filter updates for Voxel AJAX filtering
 *
 * @package Voxel_Toolkit
 */
(function() {
    'use strict';

    var widgets = [];
    var lastUrl = window.location.href;

    /**
     * Initialize all widgets and URL monitoring
     */
    function init() {
        // Find all widget instances
        document.querySelectorAll('.vt-active-filters-widget').forEach(function(widget) {
            initWidget(widget);
        });

        // Monitor URL changes
        monitorUrlChanges();

        // Handle click events
        document.addEventListener('click', handleClick);
    }

    /**
     * Initialize a single widget instance
     */
    function initWidget(widget) {
        // Parse filter labels from JSON
        var filterLabels = {};
        try {
            if (widget.dataset.filterLabels) {
                filterLabels = JSON.parse(widget.dataset.filterLabels);
            }
        } catch (e) {
            console.warn('VT Active Filters: Could not parse filter labels', e);
        }

        var config = {
            element: widget,
            hideType: widget.dataset.hideType === 'yes',
            hideSort: widget.dataset.hideSort === 'yes',
            excludeParams: (widget.dataset.excludeParams || '').split(',').map(function(p) { return p.trim(); }).filter(Boolean),
            showFilterName: widget.dataset.showFilterName === 'yes',
            keywordsLabel: widget.dataset.keywordsLabel || 'Search',
            sortLabel: widget.dataset.sortLabel || 'Sort',
            rangeSeparator: widget.dataset.rangeSeparator || ' - ',
            removeIcon: widget.dataset.removeIcon || '&times;',
            showClearAll: widget.dataset.showClearAll === 'yes',
            clearAllText: widget.dataset.clearAllText || 'Clear All',
            clearAllPosition: widget.dataset.clearAllPosition || 'after',
            headingText: widget.dataset.headingText || '',
            hideWhenEmpty: widget.dataset.hideWhenEmpty === 'yes',
            isPreview: widget.dataset.isPreview === 'yes',
            filterLabels: filterLabels
        };
        widgets.push(config);
    }

    /**
     * Monitor URL changes (handles both popstate and pushState/replaceState)
     */
    function monitorUrlChanges() {
        // Listen for back/forward navigation
        window.addEventListener('popstate', function() {
            onUrlChange();
        });

        // Override pushState and replaceState to detect AJAX navigation
        var originalPushState = history.pushState;
        var originalReplaceState = history.replaceState;

        history.pushState = function() {
            originalPushState.apply(this, arguments);
            onUrlChange();
        };

        history.replaceState = function() {
            originalReplaceState.apply(this, arguments);
            onUrlChange();
        };

        // Also poll for URL changes as a fallback (some frameworks modify URL differently)
        setInterval(function() {
            if (window.location.href !== lastUrl) {
                lastUrl = window.location.href;
                onUrlChange();
            }
        }, 500);
    }

    /**
     * Handle URL change - update all widgets
     */
    function onUrlChange() {
        lastUrl = window.location.href;
        widgets.forEach(function(config) {
            updateWidget(config);
        });
    }

    /**
     * Update a widget with current URL filters
     */
    function updateWidget(config) {
        // Skip if preview mode
        if (config.isPreview) {
            return;
        }

        var filters = parseUrlFilters(config);
        renderFilters(config, filters);
    }

    /**
     * Parse URL parameters into filter objects
     */
    function parseUrlFilters(config) {
        var params = new URLSearchParams(window.location.search);
        var filters = [];
        var hiddenParams = ['pg', 'per_page', '_wpnonce', 'action'];

        if (config.hideType) {
            hiddenParams.push('type');
        }
        if (config.hideSort) {
            hiddenParams.push('sort');
        }
        hiddenParams = hiddenParams.concat(config.excludeParams);

        params.forEach(function(value, key) {
            if (hiddenParams.indexOf(key) !== -1 || !value) {
                return;
            }

            filters.push({
                key: key,
                value: value,
                label: formatFilterLabel(config, key, value),
                removeUrl: buildRemoveUrl(key)
            });
        });

        return filters;
    }

    /**
     * Format filter label for display
     */
    function formatFilterLabel(config, key, value) {
        var decodedValue = decodeURIComponent(value);
        var formattedValue = decodedValue;

        // Range format: "0..300" → "0 - 300"
        if (decodedValue.indexOf('..') !== -1) {
            var parts = decodedValue.split('..');
            formattedValue = parts[0] + config.rangeSeparator + parts[1];
        }
        // Terms format: "slug1,slug2" → "Slug1, Slug2"
        else if (decodedValue.indexOf(',') !== -1) {
            var terms = decodedValue.split(',').map(function(term) {
                return capitalize(term.trim().replace(/-/g, ' '));
            });
            formattedValue = terms.join(', ');
        }
        // Boolean
        else if (decodedValue === '1') {
            formattedValue = 'Yes';
        }
        else if (decodedValue === '0') {
            formattedValue = 'No';
        }
        else {
            formattedValue = capitalize(decodedValue.replace(/-/g, ' '));
        }

        if (config.showFilterName) {
            var prefix = getFilterLabelPrefix(config, key);
            return prefix + ': ' + formattedValue;
        }

        return formattedValue;
    }

    /**
     * Get label prefix for a filter key
     */
    function getFilterLabelPrefix(config, key) {
        // First check Voxel filter labels from data attribute
        if (config.filterLabels && config.filterLabels[key]) {
            return config.filterLabels[key];
        }

        // Try to get label from Voxel's Vue search form at runtime
        var voxelLabel = getVoxelFilterLabel(key);
        if (voxelLabel) {
            // Cache it for future use
            if (config.filterLabels) {
                config.filterLabels[key] = voxelLabel;
            }
            return voxelLabel;
        }

        // Fallback to custom widget labels
        if (key === 'keywords') {
            return config.keywordsLabel;
        }
        if (key === 'sort') {
            return config.sortLabel;
        }

        // Default: capitalize and replace dashes/underscores
        return capitalize(key.replace(/[-_]/g, ' '));
    }

    /**
     * Get filter label from Voxel's Vue search form
     */
    function getVoxelFilterLabel(key) {
        try {
            // Try multiple selectors for Voxel search form (be specific to avoid matching notifications widget)
            var searchForm = document.querySelector('.ts-form.ts-search-form')
                || document.querySelector('form.ts-search-form')
                || document.querySelector('.elementor-widget-ts-search-form .ts-form')
                || document.querySelector('.ts-search-widget .ts-form');

            if (!searchForm || !searchForm.__vue_app__) {
                return null;
            }

            var vueApp = searchForm.__vue_app__;
            var component = vueApp._container._vnode.component;
            if (!component || !component.proxy) {
                return null;
            }

            var proxy = component.proxy;

            // Method 1: Check $refs for filter with matching key (format: posttype:filterkey)
            if (proxy.$refs) {
                var refKeys = Object.keys(proxy.$refs);
                for (var r = 0; r < refKeys.length; r++) {
                    var refKey = refKeys[r];
                    // Check if this ref ends with :key (e.g., "places:terms" for key "terms")
                    if (refKey.endsWith(':' + key)) {
                        var filterRef = proxy.$refs[refKey];
                        if (filterRef && filterRef.filter && filterRef.filter.label) {
                            return filterRef.filter.label;
                        }
                    }
                }
            }

            return null;
        } catch (e) {
            return null;
        }
    }

    /**
     * Build URL without a specific parameter
     */
    function buildRemoveUrl(keyToRemove) {
        var params = new URLSearchParams(window.location.search);
        params.delete(keyToRemove);
        var baseUrl = window.location.pathname;
        var newSearch = params.toString();
        return newSearch ? baseUrl + '?' + newSearch : baseUrl;
    }

    /**
     * Get clear all URL
     */
    function getClearAllUrl() {
        return window.location.pathname;
    }

    /**
     * Capitalize first letter
     */
    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    /**
     * Render filters to the widget
     */
    function renderFilters(config, filters) {
        var widget = config.element;

        // Find Elementor widget wrapper (parent container that may have padding/margin)
        var elementorWrapper = widget.closest('.elementor-widget');

        // Check if widget was initially rendered empty (minimal placeholder)
        var wasHiddenEmpty = widget.classList.contains('vt-hidden-empty');

        // Handle empty state
        if (filters.length === 0) {
            if (config.hideWhenEmpty) {
                widget.style.display = 'none';
                widget.classList.add('vt-hidden-empty');
                // Also hide the Elementor wrapper to remove white space
                if (elementorWrapper) {
                    elementorWrapper.style.display = 'none';
                }
            } else {
                widget.style.display = '';
                widget.classList.remove('vt-hidden-empty');
                if (elementorWrapper) {
                    elementorWrapper.style.display = '';
                }
            }
            // Clear the inner content
            var inner = widget.querySelector('.vt-active-filters-inner');
            if (inner) {
                var list = inner.querySelector('.vt-active-filters-list');
                if (list) {
                    list.innerHTML = '';
                }
            }
            return;
        }

        // Show widget and Elementor wrapper
        widget.style.display = '';
        widget.classList.remove('vt-hidden-empty');
        if (elementorWrapper) {
            elementorWrapper.style.display = '';
        }

        // If widget was initially empty, we need to build the full structure
        var inner = widget.querySelector('.vt-active-filters-inner');
        if (!inner && wasHiddenEmpty) {
            // Build the full widget structure
            inner = buildWidgetStructure(config, widget);
        }

        if (!inner) {
            return;
        }

        // Build filters HTML
        var filtersHtml = '';
        filters.forEach(function(filter) {
            filtersHtml += '<a href="' + escapeHtml(filter.removeUrl) + '" class="vt-active-filter" data-filter-key="' + escapeHtml(filter.key) + '">';
            filtersHtml += '<span class="vt-filter-label">' + escapeHtml(filter.label) + '</span>';
            if (config.removeIcon && config.removeIcon !== 'none') {
                filtersHtml += '<span class="vt-filter-remove">' + config.removeIcon + '</span>';
            }
            filtersHtml += '</a>';
        });

        // Update filter list
        var list = inner.querySelector('.vt-active-filters-list');
        if (list) {
            list.innerHTML = filtersHtml;
        }

        // Update clear all button visibility
        var clearAllBtns = inner.querySelectorAll('.vt-clear-all-filters');
        clearAllBtns.forEach(function(btn) {
            btn.href = getClearAllUrl();
            btn.style.display = filters.length > 0 ? '' : 'none';
        });
    }

    /**
     * Build full widget structure for initially empty widgets
     */
    function buildWidgetStructure(config, widget) {
        var html = '';

        // Add heading if configured
        if (config.headingText) {
            html += '<div class="vt-active-filters-heading">' + escapeHtml(config.headingText) + '</div>';
        }

        // Build inner container
        html += '<div class="vt-active-filters-inner">';

        // Clear all before
        if (config.showClearAll && config.clearAllPosition === 'before') {
            html += '<a href="' + escapeHtml(getClearAllUrl()) + '" class="vt-clear-all-filters">' + escapeHtml(config.clearAllText) + '</a>';
        }

        // Filter list
        html += '<div class="vt-active-filters-list"></div>';

        // Clear all after
        if (config.showClearAll && config.clearAllPosition === 'after') {
            html += '<a href="' + escapeHtml(getClearAllUrl()) + '" class="vt-clear-all-filters">' + escapeHtml(config.clearAllText) + '</a>';
        }

        html += '</div>';

        widget.innerHTML = html;
        return widget.querySelector('.vt-active-filters-inner');
    }

    /**
     * Escape HTML entities
     */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Handle click events on filter elements
     */
    function handleClick(e) {
        // Handle individual filter removal
        var filterTag = e.target.closest('.vt-active-filter');
        if (filterTag) {
            e.preventDefault();
            var removeUrl = filterTag.getAttribute('href');
            if (removeUrl && removeUrl !== '#') {
                // Update URL without page reload
                history.pushState({}, '', removeUrl);
                onUrlChange();

                // Trigger Voxel to refresh results
                triggerVoxelRefresh();
            }
            return;
        }

        // Handle clear all filters - full page refresh
        var clearAll = e.target.closest('.vt-clear-all-filters');
        if (clearAll) {
            e.preventDefault();
            var clearUrl = clearAll.getAttribute('href');
            if (clearUrl) {
                window.location.href = clearUrl;
            }
            return;
        }
    }

    /**
     * Trigger Voxel to refresh search results
     */
    function triggerVoxelRefresh() {
        var refreshed = false;

        // Method 1: Try to access Voxel's search form Vue app and trigger URL sync
        var searchForm = document.querySelector('.ts-form.ts-search-form');
        if (searchForm && searchForm.__vue_app__) {
            try {
                var vueApp = searchForm.__vue_app__;
                var component = vueApp._container._vnode.component;
                if (component && component.proxy) {
                    // Call syncFromUrl if available
                    if (typeof component.proxy.syncFromUrl === 'function') {
                        component.proxy.syncFromUrl();
                        refreshed = true;
                    }
                    // Or trigger submit
                    else if (typeof component.proxy.submit === 'function') {
                        component.proxy.submit();
                        refreshed = true;
                    }
                }
            } catch (e) {
                console.warn('VT Active Filters: Could not access Voxel Vue app', e);
            }
        }

        // Method 2: Find and click Voxel's submit button
        if (!refreshed) {
            var submitBtn = document.querySelector('.ts-search-form .ts-form-submit, .ts-search-form [type="submit"], .ts-search-form .ts-submit-btn');
            if (submitBtn) {
                submitBtn.click();
                refreshed = true;
            }
        }

        // Method 3: Dispatch popstate event which some Voxel setups listen to
        if (!refreshed) {
            window.dispatchEvent(new PopStateEvent('popstate', { state: {} }));
        }

        // Method 4: Try triggering Voxel's custom events if available
        if (window.Voxel && window.Voxel.events) {
            window.Voxel.events.emit('search:refresh');
        }

        // Dispatch a custom event that can be listened to
        window.dispatchEvent(new CustomEvent('vt-filters-changed'));

        // Method 5: If still no refresh detected, fall back to page reload after short delay
        // This ensures the filter is removed even if other methods fail
        if (!refreshed) {
            setTimeout(function() {
                // Check if results actually updated by looking for loading state
                var hasLoading = document.querySelector('.ts-search-form.loading, .ts-form.loading');
                if (!hasLoading) {
                    // Results didn't refresh, do a page reload
                    window.location.reload();
                }
            }, 300);
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
