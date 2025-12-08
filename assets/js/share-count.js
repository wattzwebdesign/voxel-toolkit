/**
 * Share Count Tracking
 *
 * Tracks when users click on share menu items and sends the data to the server
 *
 * @package Voxel_Toolkit
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initShareTracking();
    });

    function initShareTracking() {
        // Use event delegation to capture all clicks
        // Voxel's share popup uses Vue with @click handlers on <li> elements
        document.addEventListener('click', function(e) {
            // Find if we clicked within a share list item
            var listItem = e.target.closest('li');
            if (!listItem) {
                return;
            }

            // Check if this is a share list item by class pattern
            // Voxel uses classes like: ts-share-copy-link, ts-share-facebook, etc.
            var isShareItem = listItem.className && listItem.className.includes('ts-share-');

            if (!isShareItem) {
                return;
            }

            // Get the network type from the list item class
            var network = getNetworkFromListItem(listItem);

            // Get post ID - use fallback methods since popup is outside the share button
            var postId = getPostId();

            if (postId && network) {
                trackShare(network, postId);
            }
        }, true); // Use capture phase to ensure we catch the click before Vue handles it
    }

    /**
     * Get network name from share list item
     */
    function getNetworkFromListItem(listItem) {
        // First try to extract from class name (e.g., ts-share-facebook, ts-share-copy-link)
        var classes = listItem.className || '';
        var match = classes.match(/ts-share-([a-z0-9-]+)/i);
        if (match && match[1]) {
            return match[1];
        }

        // Fallback: Get text content of the list item
        var text = listItem.textContent.trim().toLowerCase();

        // Map text labels to network keys
        var networkMap = {
            'facebook': 'facebook',
            'x': 'twitter',
            'twitter': 'twitter',
            'linkedin': 'linkedin',
            'reddit': 'reddit',
            'tumblr': 'tumblr',
            'whatsapp': 'whatsapp',
            'telegram': 'telegram',
            'pinterest': 'pinterest',
            'threads': 'threads',
            'bluesky': 'bluesky',
            'sms': 'sms',
            'line': 'line',
            'viber': 'viber',
            'snapchat': 'snapchat',
            'kakaotalk': 'kakaotalk',
            'kakao': 'kakaotalk',
            'email': 'email',
            'copy link': 'copy-link',
            'share via': 'native-share',
            'share via...': 'native-share'
        };

        for (var label in networkMap) {
            if (text.includes(label)) {
                return networkMap[label];
            }
        }

        return 'unknown';
    }

    /**
     * Get post ID from share container's data-config
     */
    function getPostIdFromShareContainer(container) {
        // Try to get from data-config attribute
        var configAttr = container.getAttribute('data-config');
        if (configAttr) {
            try {
                var config = JSON.parse(configAttr);
                // The config contains 'link' which has the post URL
                // We can extract post ID from the link or look elsewhere
                if (config.link) {
                    // Try to get post ID from URL parameters or permalink structure
                    var url = new URL(config.link);
                    var pParam = url.searchParams.get('p');
                    if (pParam) {
                        return pParam;
                    }
                }
            } catch (e) {
                // JSON parse failed
            }
        }

        // Fall back to other methods
        return getPostId();
    }

    /**
     * Track share via AJAX
     */
    function trackShare(network, postId) {
        // Check if vtShareCount is available
        if (typeof vtShareCount === 'undefined' || !postId) {
            return;
        }

        // Send AJAX request
        var formData = new FormData();
        formData.append('action', 'vt_track_share');
        formData.append('post_id', postId);
        formData.append('network', network);
        formData.append('nonce', vtShareCount.nonce);

        fetch(vtShareCount.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).catch(function(error) {
            // Silently fail - don't disrupt the user's share action
        });
    }

    /**
     * Get the current post ID (fallback method)
     */
    function getPostId() {
        // Try Voxel's config first
        if (typeof Voxel !== 'undefined' && Voxel.config && Voxel.config.post && Voxel.config.post.id) {
            return Voxel.config.post.id;
        }

        // Try Voxel_Config
        if (typeof Voxel_Config !== 'undefined' && Voxel_Config.post && Voxel_Config.post.id) {
            return Voxel_Config.post.id;
        }

        // Try body class
        var body = document.body;
        if (body.classList) {
            var classes = body.className.split(' ');
            for (var i = 0; i < classes.length; i++) {
                if (classes[i].startsWith('postid-')) {
                    return classes[i].replace('postid-', '');
                }
            }
        }

        // Try data attribute on share button container
        var shareBtn = document.querySelector('[data-post-id]');
        if (shareBtn) {
            return shareBtn.dataset.postId;
        }

        // Try meta tag
        var metaPostId = document.querySelector('meta[name="post-id"]');
        if (metaPostId) {
            return metaPostId.content;
        }

        return null;
    }
})();
