(function($) {
    'use strict';

    var VT_AI_Bot = {
        container: null,
        panel: null,
        messagesContainer: null,
        input: null,
        config: {},
        state: {
            isOpen: false,
            isLoading: false,
            messages: [],
            lastQuery: '', // Store last query for quick filters
            activeFilters: [], // Track active quick filters
        },

        init: function() {
            var self = this;
            $(document).ready(function() {
                self.container = $('.vt-ai-bot-container');
                if (self.container.length === 0) return;

                self.panel = self.container.find('.vt-ai-bot-panel');
                self.messagesContainer = self.container.find('.vt-ai-bot-messages');
                self.input = self.container.find('.vt-ai-bot-input');
                self.config = window.vtAiBot || {};

                self.bindEvents();
                self.showWelcomeMessage();
            });
        },

        bindEvents: function() {
            var self = this;

            // Toggle panel on ANY trigger click (document-level for Action VX widget)
            $(document).on('click', '.vt-ai-bot-trigger', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.openPanel();
            });

            // Close panel
            this.container.on('click', '.vt-ai-bot-close', function(e) {
                e.preventDefault();
                self.closePanel();
            });

            // Send message on form submit
            this.container.on('submit', '.vt-ai-bot-form', function(e) {
                e.preventDefault();
                self.sendMessage();
            });

            // Handle Enter key in input
            this.input.on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });

            // Close on Escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.state.isOpen) {
                    self.closePanel();
                }
            });

            // Close when clicking outside panel (but not on triggers)
            $(document).on('click', function(e) {
                if (self.state.isOpen &&
                    !$(e.target).closest('.vt-ai-bot-container').length &&
                    !$(e.target).closest('.vt-ai-bot-trigger').length) {
                    self.closePanel();
                }
            });
        },

        togglePanel: function() {
            if (this.state.isOpen) {
                this.closePanel();
            } else {
                this.openPanel();
            }
        },

        openPanel: function() {
            this.state.isOpen = true;
            this.container.addClass('is-open');
            $('body').addClass('vt-ai-bot-panel-open');

            // Push body content based on panel position
            var position = this.config.settings?.panelPosition || this.container.data('position') || 'right';
            var panelWidth = this.panel.outerWidth();

            if (position === 'right') {
                $('body').css('margin-right', panelWidth + 'px');
            } else {
                $('body').css('margin-left', panelWidth + 'px');
            }

            // Focus input
            setTimeout(function() {
                this.input.focus();
            }.bind(this), 300);
        },

        closePanel: function() {
            this.state.isOpen = false;
            this.container.removeClass('is-open');
            $('body').removeClass('vt-ai-bot-panel-open');
            $('body').css({'margin-left': '', 'margin-right': ''});
        },

        showWelcomeMessage: function() {
            var welcomeMsg = this.config.settings?.welcomeMessage ||
                             this.container.data('welcome') ||
                             'Hi! How can I help you find what you\'re looking for?';

            this.addMessage('ai', welcomeMsg);
            this.showSuggestedQueries();
        },

        showSuggestedQueries: function() {
            var self = this;
            var queries = this.config.settings?.suggestedQueries || [];
            if (!queries.length) return;

            var html = '<div class="vt-ai-bot-suggestions">';
            queries.forEach(function(query) {
                html += '<button type="button" class="vt-ai-bot-suggestion-chip">' + self.escapeHtml(query) + '</button>';
            });
            html += '</div>';

            var $suggestions = $(html);
            $suggestions.find('.vt-ai-bot-suggestion-chip').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var query = $(this).text();
                self.input.val(query);
                // Remove suggestions after clicking one
                $('.vt-ai-bot-suggestions').remove();
                self.sendMessage();
            });

            // Place suggestions above the input form
            this.container.find('.vt-ai-bot-input-area').prepend($suggestions);
        },

        sendMessage: function() {
            var message = this.input.val().trim();

            if (!message || this.state.isLoading) return;

            // Check login if required
            if (this.config.settings?.accessControl === 'logged_in' && !this.config.isLoggedIn) {
                this.addMessage('error', this.config.i18n?.loginRequired || 'Please log in to use the AI assistant.');
                return;
            }

            // Add user message to UI
            this.addMessage('user', message);
            this.input.val('');

            // Store for quick filters (only if not already a filter refinement)
            if (!message.startsWith('Refine:')) {
                this.state.lastQuery = message;
                this.state.activeFilters = [];
            }

            // Show loading
            this.state.isLoading = true;
            this.showLoading();

            // Build history for context
            var history = [];
            if (this.config.settings?.conversationMemory) {
                var maxHistory = this.config.settings?.maxMemoryMessages || 10;
                history = this.state.messages.slice(-maxHistory);
            }

            // Send to server
            var self = this;
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_ai_bot_query',
                    nonce: this.config.nonce,
                    message: message,
                    history: JSON.stringify(history),
                },
                success: function(response) {
                    self.hideLoading();
                    self.state.isLoading = false;

                    if (response.success) {
                        self.handleResponse(response.data);
                    } else {
                        var errorMsg = response.data?.message || self.config.i18n?.error || 'Something went wrong.';
                        if (response.data?.rate_limited) {
                            errorMsg = self.config.i18n?.rateLimit || 'Please wait a moment before asking again.';
                        }
                        self.addMessage('error', errorMsg);
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.state.isLoading = false;
                    self.addMessage('error', self.config.i18n?.error || 'Something went wrong. Please try again.');
                }
            });
        },

        handleResponse: function(data) {
            // Add AI explanation
            if (data.explanation) {
                this.addMessage('ai', data.explanation);
            }

            // Add results
            if (data.results && data.results.length > 0) {
                var hasResults = false;
                for (var i = 0; i < data.results.length; i++) {
                    if (data.results[i].count > 0) {
                        hasResults = true;
                        break;
                    }
                }

                if (hasResults) {
                    this.addResults(data.results);
                } else {
                    this.addMessage('ai', this.config.i18n?.noResults || 'I couldn\'t find any matching results. Try rephrasing your question.');
                }
            } else if (!data.explanation) {
                this.addMessage('ai', this.config.i18n?.noResults || 'I couldn\'t find any matching results. Try rephrasing your question.');
            }
        },

        addMessage: function(type, content) {
            var escapedContent = this.escapeHtml(content);
            var html = '<div class="vt-ai-bot-message vt-ai-bot-message-' + type + '">';
            html += '<div class="vt-ai-bot-message-content">' + escapedContent + '</div>';
            html += '</div>';

            this.messagesContainer.append(html);
            this.scrollToBottom();

            // Store in history (only user and ai messages)
            if (type === 'user' || type === 'ai') {
                this.state.messages.push({type: type, content: content});
            }
        },

        addResults: function(results) {
            var self = this;
            var html = '<div class="vt-ai-bot-results">';

            // Add quick filter chips if we have a stored query
            if (this.state.lastQuery) {
                html += this.renderQuickFilters();
            }

            results.forEach(function(result) {
                if (result.count === 0) return;

                html += '<div class="vt-ai-bot-result-group">';
                if (results.length > 1) {
                    html += '<div class="vt-ai-bot-result-header">' + self.escapeHtml(result.post_type_label) + '</div>';
                }
                html += '<div class="vt-ai-bot-result-cards">' + result.html + '</div>';
                if (result.has_more) {
                    if (result.archive_url) {
                        html += '<a href="' + self.escapeHtml(result.archive_url) + '" class="vt-ai-bot-result-more vt-ai-bot-result-more-link">';
                        html += 'View all ' + self.escapeHtml(result.post_type_label) + ' &rarr;';
                        html += '</a>';
                    } else {
                        html += '<div class="vt-ai-bot-result-more">' + self.escapeHtml(result.post_type_label) + ' has more results...</div>';
                    }
                }
                html += '</div>';
            });

            html += '</div>';

            var $results = $(html);
            this.bindQuickFilterEvents($results);
            this.messagesContainer.append($results);
            this.scrollToBottom();
        },

        renderQuickFilters: function() {
            var self = this;
            var filters = [
                { key: 'rating', label: '4+ Stars', query: 'with 4 stars or higher rating' },
                { key: 'reviews', label: 'Has Reviews', query: 'that have at least 1 review' }
            ];

            var html = '<div class="vt-ai-bot-quick-filters">';
            filters.forEach(function(filter) {
                var isActive = self.state.activeFilters.indexOf(filter.key) !== -1;
                html += '<button type="button" class="vt-ai-bot-filter-chip' + (isActive ? ' active' : '') + '" ';
                html += 'data-filter="' + filter.key + '" data-query="' + self.escapeHtml(filter.query) + '">';
                html += self.escapeHtml(filter.label);
                html += '</button>';
            });
            html += '</div>';

            return html;
        },

        bindQuickFilterEvents: function($container) {
            var self = this;
            $container.find('.vt-ai-bot-filter-chip').on('click', function() {
                var $chip = $(this);
                var filterKey = $chip.data('filter');
                var filterQuery = $chip.data('query');

                if (self.state.isLoading) return;

                // Toggle active state
                var isActive = self.state.activeFilters.indexOf(filterKey) !== -1;
                if (isActive) {
                    // Remove filter
                    self.state.activeFilters = self.state.activeFilters.filter(function(f) {
                        return f !== filterKey;
                    });
                } else {
                    // Add filter
                    self.state.activeFilters.push(filterKey);
                }

                // Build refined query
                var refinedQuery = self.state.lastQuery;
                if (self.state.activeFilters.length > 0) {
                    var filterParts = [];
                    $container.find('.vt-ai-bot-filter-chip').each(function() {
                        var key = $(this).data('filter');
                        var query = $(this).data('query');
                        if (self.state.activeFilters.indexOf(key) !== -1) {
                            filterParts.push(query);
                        }
                    });
                    refinedQuery = 'Refine: ' + self.state.lastQuery + ' ' + filterParts.join(' and ');
                }

                // Remove old results
                self.messagesContainer.find('.vt-ai-bot-results').last().remove();

                // Send refined query
                self.input.val(refinedQuery);
                self.sendMessage();
            });
        },

        showLoading: function() {
            var html = '<div class="vt-ai-bot-message vt-ai-bot-message-ai vt-ai-bot-loading-message">';
            html += '<div class="vt-ai-bot-loading">';
            html += '<span class="vt-ai-bot-loading-text">AI is thinking</span>';
            html += '<span class="vt-ai-bot-loading-dots">';
            html += '<span></span><span></span><span></span>';
            html += '</span>';
            html += '</div>';
            html += '</div>';

            this.messagesContainer.append(html);
            this.scrollToBottom();
        },

        hideLoading: function() {
            this.messagesContainer.find('.vt-ai-bot-loading-message').remove();
        },

        scrollToBottom: function() {
            var container = this.messagesContainer[0];
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        escapeHtml: function(text) {
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
    };

    VT_AI_Bot.init();

})(jQuery);
