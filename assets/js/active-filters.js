/**
 * Active Filters Widget JavaScript
 *
 * Handles click events for filter removal and clear all functionality
 *
 * @package Voxel_Toolkit
 */
(function() {
    'use strict';

    /**
     * Initialize event handlers using event delegation
     */
    function init() {
        document.addEventListener('click', handleClick);
    }

    /**
     * Handle click events on filter elements
     *
     * @param {Event} e Click event
     */
    function handleClick(e) {
        // Handle individual filter removal
        var filterTag = e.target.closest('.vt-active-filter');
        if (filterTag) {
            e.preventDefault();
            var removeUrl = filterTag.getAttribute('href');
            if (removeUrl) {
                window.location.href = removeUrl;
            }
            return;
        }

        // Handle clear all filters
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

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
