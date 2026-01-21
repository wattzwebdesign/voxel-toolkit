/**
 * Voxel Toolkit - Online Status
 *
 * Handles heartbeat and injects online status indicators into the UI.
 */

(function($) {
    'use strict';

    var VT_OnlineStatus = {
        config: window.vtOnlineStatus || {},
        heartbeatTimer: null,
        observer: null,
        processedElements: new WeakSet(),

        /**
         * Initialize
         */
        init: function() {
            if (!this.config.enabled) {
                return;
            }

            // Start heartbeat
            this.startHeartbeat();

            // Send initial heartbeat
            this.sendHeartbeat();

            // Inject indicator for current user in dashboard menu
            if (this.config.locations && this.config.locations.dashboard) {
                this.injectCurrentUserIndicator();
            }

            // Set up MutationObserver for Voxel's dynamic content
            if (this.config.locations && this.config.locations.inbox) {
                this.setupObserver();
                // Initial scan for inbox elements
                this.scanForInboxElements();
            }
        },

        /**
         * Start the heartbeat interval
         */
        startHeartbeat: function() {
            var self = this;
            var interval = this.config.heartbeatInterval || 60000;

            this.heartbeatTimer = setInterval(function() {
                self.sendHeartbeat();
            }, interval);
        },

        /**
         * Send heartbeat to server
         */
        sendHeartbeat: function() {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_online_heartbeat',
                    nonce: this.config.nonce
                }
            });
        },

        /**
         * Inject online indicator for current logged-in user in dashboard menu
         */
        injectCurrentUserIndicator: function() {
            var self = this;

            // Find ONLY the user avatar in the header (not notifications/messages icons)
            // The user avatar li has class 'ts-user-area-avatar' and contains an img with class 'avatar'
            var $userAvatars = $('.ts-user-area-avatar .ts-comp-icon').filter(function() {
                // Only target elements that contain an actual avatar image
                return $(this).find('img.avatar, img.ts-status-avatar').length > 0;
            });

            $userAvatars.each(function() {
                var $el = $(this);

                // Skip if already has indicator
                if ($el.find('.vt-online-indicator').length > 0) {
                    return;
                }

                // Current user is always "online" since they're viewing the page
                var $indicator = $('<span class="vt-online-indicator vt-online-indicator--small vt-online" title="Online"></span>');
                $el.append($indicator);
            });
        },

        /**
         * Set up MutationObserver to watch for Voxel inbox content
         */
        setupObserver: function() {
            var self = this;

            // Watch for changes in the document body
            this.observer = new MutationObserver(function(mutations) {
                var shouldScan = false;

                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length > 0) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) { // Element node
                                // Check if it's an inbox-related element
                                var $node = $(node);
                                if ($node.hasClass('ts-convo-list') ||
                                    $node.hasClass('ts-message-notifications') ||
                                    $node.hasClass('ts-notification-list') ||
                                    $node.find('.ts-convo-list, .ts-message-notifications, .convo-avatar, .notification-image').length > 0) {
                                    shouldScan = true;
                                }
                            }
                        });
                    }
                });

                if (shouldScan) {
                    // Debounce the scan
                    clearTimeout(self.scanTimeout);
                    self.scanTimeout = setTimeout(function() {
                        self.scanForInboxElements();
                    }, 300);
                }
            });

            this.observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        /**
         * Scan for Voxel inbox elements and inject indicators
         */
        scanForInboxElements: function() {
            var self = this;
            var nameTargets = [];

            // Find Voxel inbox chat list items - extract names from DOM
            $('.ts-convo-list li').each(function() {
                var $li = $(this);
                var $avatar = $li.find('.convo-avatar, .convo-avatars').first();

                if ($avatar.length && !$avatar.find('.vt-online-indicator').length) {
                    // Get the name from the chat item
                    var name = $li.find('.message-details b, .convo-name b').first().text().trim();

                    if (name) {
                        nameTargets.push({
                            name: name,
                            element: $avatar
                        });
                    }
                }
            });

            // Find Voxel message notifications (header popup)
            $('.ts-message-notifications li, .ts-notification-list li').each(function() {
                var $li = $(this);
                var $avatar = $li.find('.notification-image').first();

                // Only add indicator if there's an actual user photo (img tag), not just an icon
                if ($avatar.length && $avatar.find('img').length && !$avatar.find('.vt-online-indicator').length) {
                    // Get the name from notification
                    var name = $li.find('.notification-details b').first().text().trim();

                    if (name) {
                        nameTargets.push({
                            name: name,
                            element: $avatar
                        });
                    }
                }
            });

            // Also check the full inbox widget (messages page)
            $('.ts-inbox .inbox-left li, .ts-inbox .ts-convo-list li').each(function() {
                var $li = $(this);
                var $avatar = $li.find('.convo-avatar, .convo-avatars').first();

                if ($avatar.length && !$avatar.find('.vt-online-indicator').length) {
                    // Get the name - Voxel uses <b> tag for the name
                    var name = $li.find('b').first().text().trim();

                    if (name) {
                        nameTargets.push({
                            name: name,
                            element: $avatar
                        });
                    }
                }
            });

            if (nameTargets.length === 0) {
                return;
            }

            // Fetch online status by names
            this.fetchStatusByNames(nameTargets);
        },

        /**
         * Fetch online status by display names and apply indicators
         */
        fetchStatusByNames: function(nameTargets) {
            var self = this;

            // Get unique names
            var names = [];
            nameTargets.forEach(function(t) {
                if (names.indexOf(t.name) === -1) {
                    names.push(t.name);
                }
            });

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_get_online_status_by_names',
                    nonce: this.config.nonce,
                    names: names
                },
                success: function(response) {
                    if (response.success && response.data.statuses) {
                        nameTargets.forEach(function(target) {
                            var status = response.data.statuses[target.name];

                            if (target.element && target.element.length) {
                                var $el = target.element;

                                // Skip if indicator already exists
                                if ($el.find('.vt-online-indicator').length) {
                                    return;
                                }

                                var isOnline = status && status.is_online;
                                var statusClass = isOnline ? 'vt-online' : 'vt-offline';
                                var title = isOnline ? 'Online' : 'Offline';
                                var $indicator = $('<span class="vt-online-indicator vt-online-indicator--small ' + statusClass + '" title="' + title + '"></span>');

                                $el.append($indicator);
                            }
                        });
                    }
                }
            });
        },

        /**
         * Fetch online status and apply indicators
         */
        fetchAndApplyStatus: function(targets) {
            var self = this;

            // Build target keys
            var targetKeys = targets.map(function(t) {
                return t.type + ':' + t.id;
            });

            // Remove duplicates
            targetKeys = targetKeys.filter(function(key, index) {
                return targetKeys.indexOf(key) === index;
            });

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_get_targets_online_status',
                    nonce: this.config.nonce,
                    targets: targetKeys
                },
                success: function(response) {
                    if (response.success && response.data.statuses) {
                        self.applyIndicators(targets, response.data.statuses);
                    }
                }
            });
        },

        /**
         * Apply indicators to elements
         */
        applyIndicators: function(targets, statuses) {
            targets.forEach(function(target) {
                var key = target.type + ':' + target.id;
                var status = statuses[key];

                if (target.element && target.element.length) {
                    var $el = target.element;

                    // Skip if indicator already exists
                    if ($el.find('.vt-online-indicator').length) {
                        return;
                    }

                    var isOnline = status && status.is_online;
                    var statusClass = isOnline ? 'vt-online' : 'vt-offline';
                    var title = isOnline ? 'Online' : 'Offline';
                    var $indicator = $('<span class="vt-online-indicator vt-online-indicator--small ' + statusClass + '" title="' + title + '"></span>');

                    $el.append($indicator);
                }
            });
        },

        /**
         * Create indicator HTML
         */
        createIndicator: function(isOnline, size) {
            size = size || 'small';
            var statusClass = isOnline ? 'vt-online' : 'vt-offline';
            var title = isOnline ? 'Online' : 'Offline';
            return '<span class="vt-online-indicator vt-online-indicator--' + size + ' ' + statusClass + '" title="' + title + '"></span>';
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        VT_OnlineStatus.init();
    });

    // Expose to global scope
    window.VT_OnlineStatus = VT_OnlineStatus;

})(jQuery);
