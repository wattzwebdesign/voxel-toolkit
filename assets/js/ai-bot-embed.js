(function($) {
    'use strict';

    /**
     * AI Bot Embed Class
     * Handles embedded AI chatbot instances
     */
    function VTAiBotEmbed(container) {
        this.container = $(container);
        this.settings = this.container.data('settings') || {};
        this.messagesContainer = this.container.find('.vt-ai-bot-embed-messages');
        this.input = this.container.find('.vt-ai-bot-embed-input');
        this.form = this.container.find('.vt-ai-bot-embed-form');

        this.state = {
            isLoading: false,
            messages: [],
            userLocation: null
        };

        this.init();
    }

    VTAiBotEmbed.prototype = {

        /**
         * Initialize the embedded bot
         */
        init: function() {
            this.bindEvents();
            this.showWelcomeMessage();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Form submission
            this.form.on('submit', function(e) {
                e.preventDefault();
                self.sendMessage();
            });

            // Suggested query clicks
            this.container.on('click', '.vt-ai-bot-embed-suggested-item', function(e) {
                e.preventDefault();
                var query = $(this).text();
                self.input.val(query);
                self.sendMessage();
            });

            // Enter key in input
            this.input.on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });
        },

        /**
         * Show welcome message
         */
        showWelcomeMessage: function() {
            if (this.settings.welcomeMessage) {
                this.addMessage('ai', this.settings.welcomeMessage);
            }

            // Show suggested queries
            if (this.settings.suggestedQueries && this.settings.suggestedQueries.length > 0) {
                this.showSuggestedQueries();
            }
        },

        /**
         * Show suggested queries
         */
        showSuggestedQueries: function() {
            var $suggested = $('<div class="vt-ai-bot-embed-suggested"></div>');

            for (var i = 0; i < this.settings.suggestedQueries.length; i++) {
                var query = this.settings.suggestedQueries[i];
                $suggested.append('<button type="button" class="vt-ai-bot-embed-suggested-item">' + this.escapeHtml(query) + '</button>');
            }

            this.messagesContainer.append($suggested);
            this.scrollToBottom();
        },

        /**
         * Send a message
         */
        sendMessage: function() {
            var self = this;
            var message = this.input.val().trim();

            if (!message) {
                return;
            }

            if (this.state.isLoading) {
                return;
            }

            // Check login requirement
            if (!this.settings.isLoggedIn && this.settings.accessControl === 'logged_in') {
                this.addMessage('error', this.settings.i18n.loginRequired);
                return;
            }

            // Clear input
            this.input.val('');

            // Remove suggested queries on first message
            this.container.find('.vt-ai-bot-embed-suggested').remove();

            // Add user message
            this.addMessage('user', message);

            // Show loading
            this.showLoading();

            // Get user location then send request
            this.getUserLocation(function(location) {
                self.sendRequest(message, location);
            });
        },

        /**
         * Send AJAX request
         */
        sendRequest: function(message, location) {
            var self = this;

            // Build conversation history
            var history = [];
            for (var i = 0; i < this.state.messages.length; i++) {
                var msg = this.state.messages[i];
                if (msg.type === 'user' || msg.type === 'ai') {
                    history.push({
                        type: msg.type,
                        content: msg.content
                    });
                }
            }

            $.ajax({
                url: this.settings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_ai_bot_query',
                    nonce: this.settings.nonce,
                    message: message,
                    history: history,
                    user_location: location
                },
                success: function(response) {
                    self.hideLoading();

                    if (response.success && response.data) {
                        // Add AI explanation
                        if (response.data.explanation) {
                            self.addMessage('ai', response.data.explanation);
                        }

                        // Add results
                        if (response.data.results && response.data.results.length > 0) {
                            self.addResults(response.data.results);
                        }
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : self.settings.i18n.error;
                        self.addMessage('error', errorMsg);
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.addMessage('error', self.settings.i18n.error);
                }
            });
        },

        /**
         * Add a message to the chat
         */
        addMessage: function(type, content) {
            var $message = $('<div class="vt-ai-bot-embed-message vt-ai-bot-embed-message-' + type + '">' +
                '<div class="vt-ai-bot-embed-message-content">' + this.escapeHtml(content) + '</div>' +
                '</div>');

            this.messagesContainer.append($message);
            this.scrollToBottom();

            // Store in state
            this.state.messages.push({
                type: type,
                content: content
            });
        },

        /**
         * Add search results
         */
        addResults: function(results) {
            var self = this;
            var $resultsContainer = $('<div class="vt-ai-bot-embed-results"></div>');

            for (var i = 0; i < results.length; i++) {
                var result = results[i];

                var $group = $('<div class="vt-ai-bot-embed-result-group"></div>');

                // Group header
                if (result.post_type_label) {
                    $group.append('<div class="vt-ai-bot-embed-result-header">' + this.escapeHtml(result.post_type_label) + '</div>');
                }

                // Result cards (raw HTML from Voxel)
                if (result.html) {
                    $group.append('<div class="vt-ai-bot-embed-result-cards">' + result.html + '</div>');
                }

                // View more link
                if (result.has_more && result.archive_url) {
                    $group.append('<a href="' + result.archive_url + '" class="vt-ai-bot-embed-view-more">' +
                        this.escapeHtml(result.post_type_label ? 'View all ' + result.post_type_label : 'View more') +
                        '</a>');
                }

                $resultsContainer.append($group);
            }

            this.messagesContainer.append($resultsContainer);
            this.scrollToBottom();
        },

        /**
         * Show loading indicator
         */
        showLoading: function() {
            this.state.isLoading = true;

            var thinkingText = this.settings.thinkingText || 'AI is thinking';

            var $loading = $('<div class="vt-ai-bot-embed-loading">' +
                '<span>' + this.escapeHtml(thinkingText) + '</span>' +
                '<span class="vt-ai-bot-embed-loading-dots"><span></span><span></span><span></span></span>' +
                '</div>');

            this.messagesContainer.append($loading);
            this.scrollToBottom();
        },

        /**
         * Hide loading indicator
         */
        hideLoading: function() {
            this.state.isLoading = false;
            this.container.find('.vt-ai-bot-embed-loading').remove();
        },

        /**
         * Scroll messages to bottom
         */
        scrollToBottom: function() {
            var container = this.messagesContainer[0];
            container.scrollTop = container.scrollHeight;
        },

        /**
         * Get user location
         */
        getUserLocation: function(callback) {
            var self = this;

            // Already have location cached
            if (this.state.userLocation) {
                callback(this.state.userLocation);
                return;
            }

            // Check cookie
            var locationCookie = this.getCookie('vt_visitor_location');
            if (locationCookie) {
                try {
                    var parsed = JSON.parse(decodeURIComponent(locationCookie));
                    if (parsed && parsed.lat && parsed.lng) {
                        self.state.userLocation = {
                            lat: parsed.lat,
                            lng: parsed.lng,
                            city: parsed.city || '',
                            state: parsed.state || '',
                            source: 'cookie'
                        };
                        callback(self.state.userLocation);
                        return;
                    }
                } catch (e) {
                    // Invalid cookie
                }
            }

            // Try browser geolocation
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(pos) {
                        self.state.userLocation = {
                            lat: pos.coords.latitude,
                            lng: pos.coords.longitude,
                            city: '',
                            state: '',
                            source: 'browser'
                        };
                        callback(self.state.userLocation);
                    },
                    function() {
                        // Fallback to IP
                        self.getLocationByIP(callback);
                    },
                    { timeout: 5000, maximumAge: 300000 }
                );
            } else {
                this.getLocationByIP(callback);
            }
        },

        /**
         * Get location by IP
         */
        getLocationByIP: function(callback) {
            var self = this;

            $.ajax({
                url: 'https://ipapi.co/json/',
                type: 'GET',
                timeout: 5000,
                success: function(data) {
                    if (data && data.latitude && data.longitude) {
                        self.state.userLocation = {
                            lat: data.latitude,
                            lng: data.longitude,
                            city: data.city || '',
                            state: data.region || '',
                            source: 'ip'
                        };
                        callback(self.state.userLocation);
                    } else {
                        callback(null);
                    }
                },
                error: function() {
                    callback(null);
                }
            });
        },

        /**
         * Get cookie value
         */
        getCookie: function(name) {
            var value = '; ' + document.cookie;
            var parts = value.split('; ' + name + '=');
            if (parts.length === 2) {
                return parts.pop().split(';').shift();
            }
            return null;
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    /**
     * Initialize all embedded bots on page
     */
    function initEmbeddedBots() {
        $('.vt-ai-bot-embed').each(function() {
            // Skip if already initialized
            if ($(this).data('vt-ai-bot-init')) {
                return;
            }

            $(this).data('vt-ai-bot-init', true);
            new VTAiBotEmbed(this);
        });
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        initEmbeddedBots();
    });

    // Re-initialize for Elementor editor
    $(window).on('elementor/frontend/init', function() {
        if (typeof elementorFrontend !== 'undefined') {
            elementorFrontend.hooks.addAction('frontend/element_ready/voxel-ai-bot-embed.default', function($element) {
                initEmbeddedBots();
            });
        }
    });

})(jQuery);
