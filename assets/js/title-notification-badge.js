/**
 * Title Notification Badge
 *
 * Updates browser tab title with unread notification/message count,
 * adds favicon badge, and polls for real-time updates.
 */
(function($) {
    'use strict';

    if (typeof vtTitleBadge === 'undefined') {
        return;
    }

    var TitleBadge = {
        config: vtTitleBadge,
        originalTitle: document.title,
        originalFavicon: null,
        faviconCanvas: null,
        faviconCtx: null,
        faviconImage: null,
        faviconReady: false,
        currentCount: 0,
        isFlashing: false,
        flashInterval: null,
        pollTimer: null,
        isPolling: false,

        init: function() {
            var self = this;

            // Store original title
            self.originalTitle = document.title;

            // Initialize favicon badge
            self.initFavicon();

            // Get initial counts
            self.fetchCounts();

            // Start polling
            self.startPolling();

            // Stop flashing when window gains focus
            $(window).on('focus', function() {
                self.stopFlashing();
            });
        },

        initFavicon: function() {
            var self = this;

            // Find existing favicon
            var favicon = document.querySelector('link[rel="icon"]') ||
                          document.querySelector('link[rel="shortcut icon"]');

            if (favicon) {
                self.originalFavicon = favicon.href;
            } else {
                // Try default favicon location
                self.originalFavicon = '/favicon.ico';
            }

            // Create canvas for drawing badge
            self.faviconCanvas = document.createElement('canvas');
            self.faviconCanvas.width = 32;
            self.faviconCanvas.height = 32;
            self.faviconCtx = self.faviconCanvas.getContext('2d');

            // Load favicon image
            self.faviconImage = new Image();
            self.faviconImage.crossOrigin = 'anonymous';
            self.faviconImage.onload = function() {
                self.faviconReady = true;
                // Update favicon with current count
                self.updateFavicon(self.currentCount);
            };
            self.faviconImage.onerror = function() {
                // Favicon couldn't load, create a simple one
                self.faviconReady = true;
                self.updateFavicon(self.currentCount);
            };
            self.faviconImage.src = self.originalFavicon;
        },

        updateFavicon: function(count) {
            var self = this;

            if (!self.faviconCtx) return;

            var canvas = self.faviconCanvas;
            var ctx = self.faviconCtx;
            var size = 32;

            // Clear canvas
            ctx.clearRect(0, 0, size, size);

            // Draw original favicon if loaded
            if (self.faviconImage && self.faviconImage.complete && self.faviconImage.naturalWidth > 0) {
                ctx.drawImage(self.faviconImage, 0, 0, size, size);
            } else {
                // Draw a simple placeholder favicon
                ctx.fillStyle = '#4f46e5';
                ctx.fillRect(0, 0, size, size);
            }

            // Draw badge if count > 0
            if (count > 0) {
                var badgeSize = 16;
                var badgeX = size - badgeSize;
                var badgeY = 0;

                // Draw red circle
                ctx.beginPath();
                ctx.arc(badgeX + badgeSize/2, badgeY + badgeSize/2, badgeSize/2, 0, 2 * Math.PI);
                ctx.fillStyle = '#ef4444';
                ctx.fill();

                // Draw count text
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 11px Arial';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';

                var text = count > 9 ? '9+' : count.toString();
                ctx.fillText(text, badgeX + badgeSize/2, badgeY + badgeSize/2 + 1);
            }

            // Update favicon
            self.setFavicon(canvas.toDataURL('image/png'));
        },

        setFavicon: function(url) {
            var link = document.querySelector('link[rel="icon"]');

            if (!link) {
                link = document.createElement('link');
                link.rel = 'icon';
                document.head.appendChild(link);
            }

            link.href = url;
        },

        resetFavicon: function() {
            var self = this;
            if (self.originalFavicon) {
                self.setFavicon(self.originalFavicon);
            }
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

                        // Update count, title, and favicon
                        self.currentCount = newCount;
                        self.updateTitle(newCount);
                        self.updateFavicon(newCount);
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
