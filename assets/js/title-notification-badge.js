/**
 * Title Notification Badge
 *
 * Updates browser tab title with unread notification/message count
 * and polls for real-time updates.
 */
(function($) {
    'use strict';

    if (typeof vtTitleBadge === 'undefined') {
        return;
    }

    var TitleBadge = {
        config: vtTitleBadge,
        originalTitle: document.title,
        currentCount: 0,
        isFlashing: false,
        flashInterval: null,
        pollTimer: null,
        isPolling: false,

        init: function() {
            var self = this;

            // Store original title
            self.originalTitle = document.title;

            // Get initial counts
            self.fetchCounts();

            // Start polling
            self.startPolling();

            // Stop flashing when window gains focus
            $(window).on('focus', function() {
                self.stopFlashing();
            });
        },

        startPolling: function() {
            var self = this;

            if (self.config.pollInterval < 5000) {
                self.config.pollInterval = 5000; // Minimum 5 seconds
            }

            self.pollTimer = setInterval(function() {
                self.fetchCounts();
            }, self.config.pollInterval);
        },

        stopPolling: function() {
            var self = this;
            if (self.pollTimer) {
                clearInterval(self.pollTimer);
                self.pollTimer = null;
            }
        },

        fetchCounts: function() {
            var self = this;

            // Prevent concurrent requests
            if (self.isPolling) {
                return;
            }

            self.isPolling = true;

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'vt_get_unread_counts',
                    nonce: self.config.nonce,
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var newCount = response.data.total;

                        // Check if count increased (new notification)
                        if (newCount > self.currentCount && self.currentCount > 0) {
                            // New notification arrived
                            if (self.config.flashOnNew && !document.hasFocus()) {
                                self.startFlashing();
                            }
                        }

                        // Update count and title
                        self.currentCount = newCount;
                        self.updateTitle(newCount);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('VT Title Badge: Error fetching counts', error);
                },
                complete: function() {
                    self.isPolling = false;
                }
            });
        },

        updateTitle: function(count) {
            var self = this;

            // Don't update if we're flashing
            if (self.isFlashing) {
                return;
            }

            if (count > 0) {
                // Add count to title
                var countText = count > 99 ? '99+' : count;
                document.title = '(' + countText + ') ' + self.originalTitle;
            } else {
                // Restore original title
                document.title = self.originalTitle;
            }
        },

        startFlashing: function() {
            var self = this;

            if (self.isFlashing) {
                return;
            }

            self.isFlashing = true;
            var showOriginal = false;

            self.flashInterval = setInterval(function() {
                if (showOriginal) {
                    var countText = self.currentCount > 99 ? '99+' : self.currentCount;
                    document.title = '(' + countText + ') ' + self.originalTitle;
                } else {
                    document.title = self.config.flashText;
                }
                showOriginal = !showOriginal;
            }, 1000);
        },

        stopFlashing: function() {
            var self = this;

            if (!self.isFlashing) {
                return;
            }

            self.isFlashing = false;

            if (self.flashInterval) {
                clearInterval(self.flashInterval);
                self.flashInterval = null;
            }

            // Update title with current count
            self.updateTitle(self.currentCount);
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        TitleBadge.init();
    });

})(jQuery);
