/**
 * Voxel Toolkit Compare Posts
 *
 * Handles localStorage state management, floating bar, and comparison table
 *
 * @package Voxel_Toolkit
 */
(function($) {
    'use strict';

    // Storage key
    var STORAGE_KEY = 'voxel_compare_posts';

    /**
     * Get comparison data from localStorage
     *
     * @returns {Object} Comparison data with postType and posts array
     */
    function getComparisonData() {
        try {
            var data = localStorage.getItem(STORAGE_KEY);
            if (data) {
                var parsed = JSON.parse(data);
                // Validate structure
                if (parsed && typeof parsed === 'object' && Array.isArray(parsed.posts)) {
                    return parsed;
                }
            }
        } catch (e) {
            console.error('VT Compare: Error reading comparison data', e);
        }
        return { postType: null, posts: [] };
    }

    /**
     * Save comparison data to localStorage
     *
     * @param {Object} data Comparison data
     */
    function saveComparisonData(data) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
            // Dispatch custom event for cross-tab sync
            window.dispatchEvent(new CustomEvent('voxelCompareUpdated', { detail: data }));
        } catch (e) {
            console.error('VT Compare: Error saving comparison data', e);
        }
    }

    /**
     * Add post to comparison
     *
     * @param {number} postId Post ID
     * @param {string} postType Post type
     * @param {string} postTitle Post title
     * @param {string} postThumbnail Post thumbnail URL
     * @returns {boolean} Success status
     */
    function addPost(postId, postType, postTitle, postThumbnail) {
        var data = getComparisonData();
        var maxPosts = voxelCompare.maxPosts || 4;

        // Check if different post type
        if (data.postType && data.postType !== postType) {
            showNotification(voxelCompare.i18n.differentPostType, 'warning');
            return false;
        }

        // Check max limit
        if (data.posts.length >= maxPosts) {
            showNotification(voxelCompare.i18n.maxReached, 'warning');
            return false;
        }

        // Check if already added
        if (data.posts.find(function(p) { return p.id == postId; })) {
            return false;
        }

        // Add post
        data.postType = postType;
        data.posts.push({
            id: postId,
            title: postTitle,
            thumbnail: postThumbnail
        });

        saveComparisonData(data);
        updateUI();
        return true;
    }

    /**
     * Remove post from comparison
     *
     * @param {number} postId Post ID
     */
    function removePost(postId) {
        var data = getComparisonData();
        data.posts = data.posts.filter(function(p) { return p.id != postId; });

        // Clear post type if no posts left
        if (data.posts.length === 0) {
            data.postType = null;
        }

        saveComparisonData(data);
        updateUI();
    }

    /**
     * Clear all posts from comparison
     */
    function clearAll() {
        saveComparisonData({ postType: null, posts: [] });
        updateUI();
    }

    /**
     * Check if post is in comparison
     *
     * @param {number} postId Post ID
     * @returns {boolean}
     */
    function isPostInComparison(postId) {
        var data = getComparisonData();
        return data.posts.some(function(p) { return p.id == postId; });
    }

    /**
     * Update all UI elements
     */
    function updateUI() {
        updateButtons();
        updateFloatingBar();
        updateComparisonTable();
    }

    /**
     * Update compare button states
     */
    function updateButtons() {
        $('.vt-compare-button').each(function() {
            var $btn = $(this);
            var postId = $btn.data('post-id');
            var isAdded = isPostInComparison(postId);

            $btn.toggleClass('is-added', isAdded);
            $btn.find('.vt-compare-button-text').text(
                isAdded ? $btn.data('text-added') : $btn.data('text-normal')
            );

            // Toggle icons
            $btn.find('.vt-compare-icon-normal').toggle(!isAdded);
            $btn.find('.vt-compare-icon-added').toggle(isAdded);
        });
    }

    /**
     * Update floating bar
     */
    function updateFloatingBar() {
        var $bar = $('#vt-compare-floating-bar');
        var data = getComparisonData();

        // Hide floating bar on comparison page
        if ($('.vt-compare-table-wrapper').length > 0) {
            $bar.hide();
            return;
        }

        if (data.posts.length === 0) {
            $bar.hide();
            return;
        }

        var isBottom = $bar.hasClass('vt-compare-bar-bottom');
        var html = '<div class="vt-compare-bar-content">';

        // Posts thumbnails
        html += '<div class="vt-compare-bar-posts">';
        data.posts.forEach(function(post) {
            html += '<div class="vt-compare-bar-post" data-post-id="' + post.id + '">';
            if (post.thumbnail) {
                html += '<img src="' + post.thumbnail + '" alt="' + escapeHtml(post.title) + '">';
            } else {
                html += '<span class="vt-compare-no-thumb"><span class="dashicons dashicons-format-image"></span></span>';
            }
            html += '<span class="vt-compare-post-title">' + escapeHtml(post.title) + '</span>';
            html += '<button class="vt-compare-remove-post" data-post-id="' + post.id + '" title="' + voxelCompare.i18n.remove + '">';
            html += '<span class="dashicons dashicons-no-alt"></span>';
            html += '</button>';
            html += '</div>';
        });
        html += '</div>';

        // Actions
        html += '<div class="vt-compare-bar-actions">';
        html += '<span class="vt-compare-count">' + data.posts.length + ' ' + voxelCompare.i18n.postsSelected + '</span>';

        var canCompare = data.posts.length >= 2;
        html += '<button class="vt-compare-view-btn" ' + (!canCompare ? 'disabled' : '') + '>';
        html += voxelCompare.i18n.viewComparison;
        html += '</button>';
        html += '<button class="vt-compare-clear-btn">' + voxelCompare.i18n.clearAll + '</button>';
        html += '</div>';

        html += '</div>';

        $bar.html(html).show();
    }

    /**
     * Update comparison table (on comparison page)
     */
    function updateComparisonTable() {
        var $wrapper = $('.vt-compare-table-wrapper');
        if ($wrapper.length === 0) return;

        var data = getComparisonData();
        var widgetPostType = $wrapper.data('post-type');
        var selectedFields = $wrapper.data('fields') || [];
        var fieldLabels = $wrapper.data('field-labels') || {};
        var removeText = $wrapper.data('remove-text');
        var featureLabel = $wrapper.data('feature-label');
        var minPostsMsg = $wrapper.data('min-posts-msg');

        // Check if posts match widget post type
        var matchingPosts = (data.postType === widgetPostType) ? data.posts : [];

        // Show empty state if no posts
        if (matchingPosts.length === 0) {
            $wrapper.find('.vt-compare-empty').show();
            $wrapper.find('.vt-compare-min-posts').hide();
            $wrapper.find('.vt-compare-table-container').hide();
            return;
        }

        // Show min posts message if only 1 post
        if (matchingPosts.length === 1) {
            $wrapper.find('.vt-compare-empty').hide();
            $wrapper.find('.vt-compare-min-posts').show();
            $wrapper.find('.vt-compare-table-container').hide();
            return;
        }

        // Hide empty/min states
        $wrapper.find('.vt-compare-empty').hide();
        $wrapper.find('.vt-compare-min-posts').hide();

        // Fetch post data via AJAX and build table
        fetchPostsData(matchingPosts.map(function(p) { return p.id; }), selectedFields, function(postsData) {
            buildComparisonTable($wrapper, postsData, selectedFields, fieldLabels, removeText, featureLabel);
        });
    }

    /**
     * Fetch posts data via AJAX
     *
     * @param {Array} postIds Post IDs
     * @param {Array} fields Field keys
     * @param {Function} callback Callback function
     */
    function fetchPostsData(postIds, fields, callback) {
        $.ajax({
            url: voxelCompare.ajaxUrl,
            type: 'POST',
            data: {
                action: 'vt_get_comparison_posts',
                post_ids: postIds,
                fields: fields,
                nonce: voxelCompare.nonce
            },
            success: function(response) {
                if (response.success) {
                    callback(response.data);
                } else {
                    console.error('VT Compare: Error fetching posts', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('VT Compare: AJAX error', error);
            }
        });
    }

    /**
     * Build comparison table HTML
     *
     * @param {jQuery} $wrapper Table wrapper element
     * @param {Array} postsData Posts data from AJAX
     * @param {Array} fields Field keys
     * @param {Object} fieldLabels Field labels mapping
     * @param {string} removeText Remove button text
     * @param {string} featureLabel Feature column label
     */
    function buildComparisonTable($wrapper, postsData, fields, fieldLabels, removeText, featureLabel) {
        var $container = $wrapper.find('.vt-compare-table-container');
        var $table = $wrapper.find('.vt-compare-table');
        var $thead = $table.find('thead tr');
        var $tbody = $table.find('tbody');

        // Clear existing content
        $thead.find('th:not(.vt-compare-field-header)').remove();
        $tbody.empty();

        // Update feature column label
        $thead.find('.vt-compare-field-header').text(featureLabel);

        // Add post headers
        postsData.forEach(function(post) {
            var headerHtml = '<th class="vt-compare-post-header">';
            headerHtml += '<div class="vt-compare-header-content">';
            if (post.thumbnail) {
                headerHtml += '<a href="' + post.permalink + '"><img src="' + post.thumbnail + '" alt="' + escapeHtml(post.title) + '"></a>';
            }
            headerHtml += '<a href="' + post.permalink + '" class="vt-compare-header-title">' + escapeHtml(post.title) + '</a>';
            headerHtml += '<button class="vt-compare-remove-btn" data-post-id="' + post.id + '">' + removeText + '</button>';
            headerHtml += '</div>';
            headerHtml += '</th>';
            $thead.append(headerHtml);
        });

        // Add field rows
        fields.forEach(function(fieldKey) {
            var row = '<tr><th class="vt-compare-field-label">' + escapeHtml(fieldLabels[fieldKey] || fieldKey) + '</th>';
            postsData.forEach(function(post) {
                var value = post.fields && post.fields[fieldKey] ? post.fields[fieldKey] : '<span class="vt-compare-empty-value">—</span>';
                row += '<td class="vt-compare-field-value">' + value + '</td>';
            });
            row += '</tr>';
            $tbody.append(row);
        });

        $container.show();
    }

    /**
     * Show notification
     *
     * @param {string} message Message text
     * @param {string} type Notification type (info, warning, error)
     */
    function showNotification(message, type) {
        // Use Voxel's native notification system if available
        if (typeof Voxel !== 'undefined' && Voxel.notify) {
            Voxel.notify(message, type);
            return;
        }

        // Fallback: simple toast notification
        var $toast = $('<div class="vt-compare-toast vt-compare-toast-' + type + '">' + escapeHtml(message) + '</div>');
        $('body').append($toast);

        setTimeout(function() {
            $toast.addClass('vt-compare-toast-visible');
        }, 10);

        setTimeout(function() {
            $toast.removeClass('vt-compare-toast-visible');
            setTimeout(function() {
                $toast.remove();
            }, 300);
        }, 3000);
    }

    /**
     * Escape HTML special characters
     *
     * @param {string} text Text to escape
     * @returns {string} Escaped text
     */
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Initialize
     */
    $(document).ready(function() {
        // Check if voxelCompare config is available
        if (typeof voxelCompare === 'undefined') {
            return;
        }

        // Initial UI update
        updateUI();

        // Handle compare button clicks
        $(document).on('click', '.vt-compare-button', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $btn = $(this);
            var postId = $btn.data('post-id');
            var postType = $btn.data('post-type');
            var postTitle = $btn.data('post-title');
            var postThumbnail = $btn.data('post-thumbnail');

            if (isPostInComparison(postId)) {
                removePost(postId);
            } else {
                addPost(postId, postType, postTitle, postThumbnail);
            }
        });

        // Handle remove from floating bar
        $(document).on('click', '.vt-compare-remove-post', function(e) {
            e.preventDefault();
            e.stopPropagation();
            removePost($(this).data('post-id'));
        });

        // Handle view comparison button
        $(document).on('click', '.vt-compare-view-btn:not([disabled])', function(e) {
            e.preventDefault();
            var data = getComparisonData();
            var postType = data.postType;

            if (postType && voxelCompare.comparisonPageUrls && voxelCompare.comparisonPageUrls[postType]) {
                window.location.href = voxelCompare.comparisonPageUrls[postType];
            } else {
                showNotification(voxelCompare.i18n.noComparisonPage || 'Comparison page not configured', 'warning');
            }
        });

        // Handle clear all button
        $(document).on('click', '.vt-compare-clear-btn', function(e) {
            e.preventDefault();
            clearAll();
        });

        // Handle remove from table
        $(document).on('click', '.vt-compare-remove-btn', function(e) {
            e.preventDefault();
            removePost($(this).data('post-id'));
        });

        // Listen for storage changes (cross-tab sync)
        window.addEventListener('storage', function(e) {
            if (e.key === STORAGE_KEY) {
                updateUI();
            }
        });

        // Listen for custom events (same-tab updates)
        window.addEventListener('voxelCompareUpdated', function() {
            // Already handled by the function that triggered it
        });
    });

})(jQuery);
