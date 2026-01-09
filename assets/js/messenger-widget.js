/**
 * Voxel Toolkit - Messenger Widget
 * Facebook-style floating messenger with multi-chat support
 */

(function($) {
    'use strict';

    var VT_Messenger = {
        container: null,
        button: null,
        popup: null,
        chatWindows: null,
        badge: null,
        config: {},
        originalTitle: document.title,
        titleFlashInterval: null,
        tooltipHideTimeout: null,
        state: {
            isOpen: false,
            chats: [],
            openChats: [],
            activeChat: null,
            unreadCount: 0,
            polling: null,
            isPolling: false, // Lock to prevent concurrent polling requests
            searchTerm: '',
            unreadChats: {}, // Track unread status and count by chat key: { 'chat-key': 3 }
            seenChatKeys: {}, // Track all chat keys we've ever seen to prevent auto-opening existing chats
            persistentAdminChat: null, // Stores persistent admin chat config
            persistentChatKey: null, // The chat key for persistent admin
        },

        init: function() {
            var self = this;

            // Prevent double initialization
            if (self.initialized) {
                return;
            }
            self.initialized = true;

            // Wait for DOM ready
            $(document).ready(function() {
                self.container = $('.vt-messenger-container');

                if (self.container.length === 0) {
                    return;
                }

                self.button = self.container.find('.vt-messenger-button');
                self.popup = self.container.find('.vt-messenger-popup');
                self.chatWindows = self.container.find('.vt-messenger-chat-windows');
                self.badge = self.container.find('.vt-messenger-badge');

                // Get configuration
                self.config = window.vtMessenger || {};

                // Get widget-specific settings from data attributes
                // Use .attr() instead of .data() to get raw HTML values (avoids jQuery caching/parsing issues)
                self.widgetConfig = {
                    placeholder: self.container.attr('data-placeholder') || self.config.i18n.typeMessage,
                    replyAs: self.container.attr('data-reply-as') || self.config.i18n.replyAs,
                    sendIcon: self.container.attr('data-send-icon') || '',
                    uploadIcon: self.container.attr('data-upload-icon') || '',
                    widgetAvatar: self.container.attr('data-widget-avatar') || ''
                };

                // Load unread state from localStorage
                self.loadUnreadState();

                // Update badge with loaded state
                self.updateBadge();

                // Create tooltip element
                self.createTooltip();

                // Create lightbox element
                self.createLightbox();

                // Bind events
                self.bindEvents();

                // Load initial chat list
                self.loadChats();

                // Initialize persistent admin chat if configured
                self.initPersistentAdminChat();

                // Initialize AI Bot chat if configured
                self.initAIBotChat();

                // Start polling for new messages
                if (self.config.polling && self.config.polling.enabled) {
                    self.startPolling();
                }
            });
        },

        flashTitle: function(message) {
            var self = this;

            // Stop any existing flash
            self.stopTitleFlash();

            var isOriginal = true;
            self.titleFlashInterval = setInterval(function() {
                document.title = isOriginal ? message : self.originalTitle;
                isOriginal = !isOriginal;
            }, 1000); // Flash every 1 second
        },

        stopTitleFlash: function() {
            if (this.titleFlashInterval) {
                clearInterval(this.titleFlashInterval);
                this.titleFlashInterval = null;
                document.title = this.originalTitle;
            }
        },

        loadUnreadState: function() {
            try {
                var userId = this.config.userId || 'guest';
                var storageKey = 'vt_messenger_unread_' + userId;
                var saved = localStorage.getItem(storageKey);
                if (saved) {
                    this.state.unreadChats = JSON.parse(saved);
                    this.state.unreadCount = Object.keys(this.state.unreadChats).length;
                }
            } catch (e) {
                console.error('VT Messenger: Error loading unread state', e);
            }
        },

        saveUnreadState: function() {
            try {
                var userId = this.config.userId || 'guest';
                var storageKey = 'vt_messenger_unread_' + userId;
                localStorage.setItem(storageKey, JSON.stringify(this.state.unreadChats));
            } catch (e) {
                console.error('VT Messenger: Error saving unread state', e);
            }
        },

        createTooltip: function() {
            var self = this;

            // Create a single floating tooltip element
            var tooltipHtml = '<div class="vt-chat-tooltip-floating" style="display: none;">';
            tooltipHtml += '  <div class="vt-tooltip-name"></div>';
            tooltipHtml += '  <div class="vt-tooltip-message"></div>';
            tooltipHtml += '</div>';

            $('body').append(tooltipHtml);
            self.tooltip = $('.vt-chat-tooltip-floating');
        },

        createLightbox: function() {
            var lightboxHtml = '<div class="vt-lightbox-overlay" style="display: none;">';
            lightboxHtml += '  <button class="vt-lightbox-close">&times;</button>';
            lightboxHtml += '  <div class="vt-lightbox-content">';
            lightboxHtml += '    <img src="" alt="">';
            lightboxHtml += '  </div>';
            lightboxHtml += '</div>';

            $('body').append(lightboxHtml);
            this.lightbox = $('.vt-lightbox-overlay');
        },

        openLightbox: function(imageSrc) {
            this.lightbox.find('img').attr('src', imageSrc);
            this.lightbox.fadeIn(200);
            $('body').css('overflow', 'hidden');
        },

        closeLightbox: function() {
            this.lightbox.fadeOut(200);
            $('body').css('overflow', '');
        },

        /**
         * Initialize persistent admin chat from data attribute
         */
        initPersistentAdminChat: function() {
            var self = this;

            // Get persistent admin config from container data attribute
            var persistentAdminData = self.container.attr('data-persistent-admin');
            if (!persistentAdminData || persistentAdminData === '{}') {
                return;
            }

            try {
                var config = JSON.parse(persistentAdminData);
                if (!config.enabled || !config.userId) {
                    return;
                }

                self.state.persistentAdminChat = {
                    userId: config.userId,
                    userName: config.userName,
                    userAvatar: config.userAvatar,
                };

                // Generate a unique chat key for persistent admin
                var currentUserId = self.config.userId;
                self.state.persistentChatKey = 'persistent-admin-' + config.userId;

                // Create the persistent chat circle after a short delay to ensure chat list is loaded
                setTimeout(function() {
                    self.createPersistentAdminCircle();
                }, 500);

            } catch (e) {
                console.error('VT Messenger: Error parsing persistent admin config', e);
            }
        },

        /**
         * Create the persistent admin chat circle in the chat list
         */
        createPersistentAdminCircle: function() {
            var self = this;
            var admin = self.state.persistentAdminChat;

            if (!admin) return;

            // Prevent creating multiple times
            if (self.state.persistentAdminCreated) {
                return;
            }

            var $list = self.popup.find('.vt-messenger-chat-list');

            // Check if already exists in DOM
            if ($list.find('.vt-persistent-admin-chat').length > 0) {
                self.state.persistentAdminCreated = true;
                return;
            }

            // Mark as created
            self.state.persistentAdminCreated = true;

            // Create avatar HTML
            var avatarHtml = '';
            if (admin.userAvatar) {
                avatarHtml = '<img src="' + self.escapeHtml(admin.userAvatar) + '" alt="' + self.escapeHtml(admin.userName) + '">';
            } else {
                // Use widget avatar or global default avatar
                var fallbackAvatar = self.widgetConfig.widgetAvatar || self.config.defaultAvatar;
                if (fallbackAvatar) {
                    avatarHtml = '<img src="' + self.escapeHtml(fallbackAvatar) + '" alt="' + self.escapeHtml(admin.userName) + '">';
                }
            }

            var circleHtml = '<div class="vt-messenger-chat-item vt-persistent-admin-chat" ';
            circleHtml += 'data-chat-key="' + self.state.persistentChatKey + '" ';
            circleHtml += 'data-name="' + self.escapeHtml(admin.userName) + '" ';
            circleHtml += 'data-excerpt="' + self.escapeHtml('Chat with ' + admin.userName) + '" ';
            circleHtml += 'data-persistent="true">';
            circleHtml += '  <div class="vt-chat-avatar">' + avatarHtml + '</div>';
            circleHtml += '</div>';

            // Insert at the top of the chat list
            $list.prepend(circleHtml);
        },

        /**
         * Open the persistent admin chat window
         */
        openPersistentAdminChat: function() {
            var self = this;
            var admin = self.state.persistentAdminChat;

            if (!admin) return;

            // Check if already open
            var existing = null;
            for (var i = 0; i < self.state.openChats.length; i++) {
                if (self.state.openChats[i].isPersistentAdmin) {
                    existing = self.state.openChats[i];
                    break;
                }
            }

            if (existing) {
                // Already open, expand if minimized
                if (existing.minimized) {
                    self.expandChat(existing.key);
                }
                return;
            }

            // Create avatar HTML
            var avatarHtml = '';
            if (admin.userAvatar) {
                avatarHtml = '<img src="' + self.escapeHtml(admin.userAvatar) + '" alt="' + self.escapeHtml(admin.userName) + '">';
            } else {
                var fallbackAvatar = self.widgetConfig.widgetAvatar || self.config.defaultAvatar;
                if (fallbackAvatar) {
                    avatarHtml = '<img src="' + self.escapeHtml(fallbackAvatar) + '" alt="' + self.escapeHtml(admin.userName) + '">';
                }
            }

            // Create a chat object for the admin
            var adminChat = {
                key: self.state.persistentChatKey,
                isPersistentAdmin: true,
                author: {
                    type: 'user',
                    id: self.config.userId,
                    name: 'You',
                },
                target: {
                    type: 'user',
                    id: admin.userId,
                    name: admin.userName,
                    avatar: avatarHtml,
                },
                messages: [],
                loading: true,
                minimized: false,
            };

            // Add to open chats
            self.state.openChats.push(adminChat);

            // Render with special persistent flag
            self.renderPersistentAdminChatWindow(adminChat);

            // Load actual messages via Voxel's inbox API
            self.loadPersistentAdminMessages(adminChat);
        },

        /**
         * Render the persistent admin chat window (without close button)
         */
        renderPersistentAdminChatWindow: function(chat) {
            var self = this;
            var targetName = chat.target.name;
            var targetAvatar = chat.target.avatar;

            // Get icon HTML from widget config
            var sendIconHtml = self.widgetConfig.sendIcon || '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>';
            var uploadIconHtml = self.widgetConfig.uploadIcon || '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/></svg>';
            var placeholderText = self.widgetConfig.placeholder || self.config.i18n.typeMessage;

            var html = '<div class="vt-messenger-chat-window vt-persistent-admin-window" data-chat-key="' + chat.key + '" data-persistent="true">';

            // Header - NO close button for persistent chat
            html += '  <div class="vt-messenger-chat-header">';
            html += '    <div class="vt-chat-header-info">';
            html += '      <div class="vt-chat-header-avatar">' + targetAvatar + '</div>';
            html += '      <div class="vt-chat-header-name">' + self.escapeHtml(targetName) + '</div>';
            html += '    </div>';
            html += '    <div class="vt-chat-header-actions">';
            html += '      <button class="vt-messenger-chat-minimize" title="' + (self.config.i18n.minimize || 'Minimize') + '">';
            html += '        <span>−</span>';
            html += '      </button>';
            // NOTE: NO close button here for persistent admin chat
            html += '    </div>';
            html += '  </div>';

            // Body
            html += '  <div class="vt-messenger-chat-body">';
            html += '    <div class="vt-messenger-messages">';
            html += '      <div class="vt-messenger-loading"><i class="eicon-loading eicon-animation-spin"></i></div>';
            html += '    </div>';
            html += '  </div>';

            // Footer
            html += '  <div class="vt-messenger-chat-footer">';
            html += '    <textarea class="vt-messenger-input" placeholder="' + self.escapeHtml(placeholderText) + '" rows="1"></textarea>';
            html += '    <div class="vt-messenger-upload-buttons">';
            html += '      <button class="vt-messenger-upload-btn vt-upload-device" title="Upload from device">';
            html += uploadIconHtml;
            html += '      </button>';
            html += '    </div>';
            html += '    <button class="vt-messenger-send-btn">';
            html += sendIconHtml;
            html += '    </button>';
            html += '    <input type="file" class="vt-messenger-file-input" style="display: none;" accept="image/*,video/*,application/pdf" multiple>';
            html += '  </div>';

            html += '</div>';

            self.chatWindows.append(html);
            self.repositionChatWindows();
        },

        /**
         * Load messages for persistent admin chat via Voxel's inbox API
         */
        loadPersistentAdminMessages: function(chat) {
            var self = this;
            var admin = self.state.persistentAdminChat;

            // Build the AJAX URL for Voxel's inbox
            var ajaxUrl = (typeof Voxel_Config !== 'undefined' && Voxel_Config.ajax_url)
                ? Voxel_Config.ajax_url + '&action=inbox.load_chat'
                : self.config.ajaxUrl + '?action=inbox.load_chat';

            $.ajax({
                url: ajaxUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    author_type: 'user',
                    author_id: self.config.userId,
                    target_type: 'user',
                    target_id: admin.userId,
                    _wpnonce: self.config.nonce,
                },
                success: function(response) {
                    if (response.success) {
                        chat.messages = response.list || [];
                        chat.loading = false;

                        // Update author info if available
                        if (response.author) {
                            chat.author = response.author;
                        }

                        self.renderMessages(chat.key);
                    } else {
                        // Chat may not exist yet - that's okay
                        chat.messages = [];
                        chat.loading = false;
                        self.renderMessages(chat.key);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('VT Messenger: Error loading persistent admin messages', error);
                    chat.messages = [];
                    chat.loading = false;
                    self.renderMessages(chat.key);
                }
            });
        },

        /**
         * Initialize AI Bot chat from config
         */
        initAIBotChat: function() {
            var self = this;

            // Check if AI Bot is enabled for messenger
            if (!self.config.aiBot || !self.config.aiBot.enabled) {
                return;
            }

            // Create the AI Bot circle after a short delay
            setTimeout(function() {
                self.createAIBotCircle();
            }, 600);
        },

        /**
         * Create the AI Bot chat circle in the chat list
         */
        createAIBotCircle: function() {
            var self = this;

            if (!self.config.aiBot || !self.config.aiBot.enabled) return;

            // Prevent creating multiple times
            if (self.state.aiBotCircleCreated) {
                return;
            }

            var $list = self.popup.find('.vt-messenger-chat-list');

            // Check if already exists in DOM
            if ($list.find('.vt-ai-bot-chat-circle').length > 0) {
                self.state.aiBotCircleCreated = true;
                return;
            }

            // Mark as created
            self.state.aiBotCircleCreated = true;

            // Get AI Bot avatar
            var avatarUrl = self.config.aiBot.avatar || self.config.defaultAvatar || '';
            var avatarHtml = avatarUrl
                ? '<img src="' + self.escapeHtml(avatarUrl) + '" alt="AI Assistant">'
                : '<span class="vt-ai-bot-icon">AI</span>';

            var circleHtml = '<div class="vt-messenger-chat-item vt-ai-bot-chat-circle" ';
            circleHtml += 'data-chat-key="ai-bot" ';
            circleHtml += 'data-name="AI Assistant" ';
            circleHtml += 'data-ai-bot="true">';
            circleHtml += '  <div class="vt-chat-avatar vt-ai-bot-avatar">' + avatarHtml + '</div>';
            circleHtml += '  <span class="vt-ai-bot-badge"></span>';
            circleHtml += '</div>';

            // Insert after persistent admin if exists, otherwise at top
            var $persistentAdmin = $list.find('.vt-persistent-admin-chat');
            if ($persistentAdmin.length > 0) {
                $persistentAdmin.after(circleHtml);
            } else {
                $list.prepend(circleHtml);
            }
        },

        /**
         * Open AI Bot - either in sidebar panel or chat window based on displayMode
         */
        openAIBotPanel: function() {
            var displayMode = this.config.aiBot?.displayMode || 'sidebar';

            if (displayMode === 'chat_window') {
                this.openAIBotChatWindow();
            } else {
                // Sidebar mode - trigger the main AI Bot panel
                var $trigger = $('.vt-ai-bot-trigger').first();
                if ($trigger.length > 0) {
                    $trigger.trigger('click');
                } else {
                    // Fallback: directly open the AI Bot container
                    var $container = $('.vt-ai-bot-container');
                    if ($container.length > 0) {
                        $container.addClass('is-open');
                        $('body').addClass('vt-ai-bot-panel-open');
                    }
                }
                // Close messenger popup for sidebar mode
                this.closePopup();
            }
        },

        /**
         * Open AI Bot in a messenger-style chat window
         */
        openAIBotChatWindow: function() {
            var self = this;
            var chatKey = 'ai-bot-chat';

            // Check if AI Bot chat is already open
            var existingWindow = this.chatWindows.find('[data-chat-key="' + chatKey + '"]');
            if (existingWindow.length > 0) {
                // If minimized, expand it
                if (existingWindow.hasClass('minimized')) {
                    this.expandChat(chatKey);
                }
                return;
            }

            // Check max chat limit
            var maxChats = parseInt(this.container.data('max-chats')) || 3;
            if (this.state.openChats.length >= maxChats) {
                // Close the oldest chat
                var oldestChat = this.state.openChats[0];
                this.closeChat(oldestChat.key, true);
            }

            // Create AI Bot chat object
            var aiChatConfig = this.config.aiBot || {};
            var aiBotChat = {
                key: chatKey,
                isAIBot: true,
                messages: [],
                loading: false,
                minimized: false,
                state: {
                    isLoading: false,
                    messages: [],
                }
            };

            // Add to open chats
            this.state.openChats.push(aiBotChat);

            // Render the AI Bot chat window
            this.renderAIBotChatWindow(aiBotChat);
        },

        /**
         * Render AI Bot chat window
         */
        renderAIBotChatWindow: function(chat) {
            var self = this;
            var aiConfig = this.config.aiBot || {};
            var headerName = aiConfig.panelTitle || 'AI Assistant';
            var avatarUrl = aiConfig.avatar || '';
            var welcomeMessage = aiConfig.welcomeMessage || 'Hi! How can I help you find what you are looking for?';
            var placeholderText = aiConfig.placeholderText || 'Ask me anything...';

            // Avatar HTML
            var avatarHtml = avatarUrl
                ? '<img src="' + this.escapeHtml(avatarUrl) + '" alt="' + this.escapeHtml(headerName) + '">'
                : '<span class="vt-ai-bot-icon">AI</span>';

            var html = '<div class="vt-messenger-chat-window vt-ai-bot-chat-window" data-chat-key="' + chat.key + '">';

            // Header
            html += '  <div class="vt-messenger-chat-header">';
            html += '    <div class="vt-chat-header-info">';
            html += '      <div class="vt-chat-header-avatar vt-ai-bot-avatar">' + avatarHtml + '</div>';
            html += '      <div class="vt-chat-header-name">' + this.escapeHtml(headerName) + '</div>';
            html += '    </div>';
            html += '    <div class="vt-chat-header-actions">';
            html += '      <button class="vt-messenger-chat-minimize" title="' + this.config.i18n.minimize + '">';
            html += '        <span>−</span>';
            html += '      </button>';
            html += '      <button class="vt-messenger-chat-close" title="' + this.config.i18n.close + '">';
            html += '        <span>×</span>';
            html += '      </button>';
            html += '    </div>';
            html += '  </div>';

            // Body
            html += '  <div class="vt-messenger-chat-body">';
            html += '    <div class="vt-messenger-messages vt-ai-bot-messages">';
            // Welcome message
            html += '      <div class="vt-ai-bot-message vt-ai-bot-message-ai">';
            html += '        <div class="vt-ai-bot-message-content">' + this.escapeHtml(welcomeMessage) + '</div>';
            html += '      </div>';
            html += '    </div>';
            html += '  </div>';

            // Footer with input
            html += '  <div class="vt-messenger-chat-footer">';
            html += '    <form class="vt-ai-bot-chat-form">';
            html += '      <div class="vt-messenger-input-container">';
            html += '        <input type="text" class="vt-ai-bot-chat-input" placeholder="' + this.escapeHtml(placeholderText) + '">';
            html += '        <button type="submit" class="vt-messenger-send-btn">';
            html += '          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>';
            html += '        </button>';
            html += '      </div>';
            html += '    </form>';
            html += '  </div>';

            html += '</div>';

            // Append to chat windows
            this.chatWindows.append(html);

            var $window = this.chatWindows.find('[data-chat-key="' + chat.key + '"]');

            // Bind AI Bot chat events
            this.bindAIBotChatEvents($window, chat);

            // Focus input
            setTimeout(function() {
                $window.find('.vt-ai-bot-chat-input').focus();
            }, 100);
        },

        /**
         * Bind events for AI Bot chat window
         */
        bindAIBotChatEvents: function($window, chat) {
            var self = this;

            // Form submit
            $window.find('.vt-ai-bot-chat-form').on('submit', function(e) {
                e.preventDefault();
                self.sendAIBotMessage($window, chat);
            });

            // Enter key in input
            $window.find('.vt-ai-bot-chat-input').on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    self.sendAIBotMessage($window, chat);
                }
            });
        },

        /**
         * Send message to AI Bot
         */
        sendAIBotMessage: function($window, chat) {
            var self = this;
            var $input = $window.find('.vt-ai-bot-chat-input');
            var $messages = $window.find('.vt-ai-bot-messages');
            var message = $input.val().trim();

            if (!message || chat.state.isLoading) return;

            var aiConfig = this.config.aiBot || {};

            // Check login if required
            if (aiConfig.accessControl === 'logged_in' && !this.config.userId) {
                this.addAIBotMessage($messages, 'error', 'Please log in to use the AI assistant.');
                return;
            }

            // Add user message to UI
            this.addAIBotMessage($messages, 'user', message);
            $input.val('');

            // Show loading
            chat.state.isLoading = true;
            var thinkingText = aiConfig.thinkingText || 'AI is thinking';
            var $loading = $('<div class="vt-ai-bot-message vt-ai-bot-message-loading"><div class="vt-ai-bot-message-content"><i class="eicon-loading eicon-animation-spin"></i> ' + this.escapeHtml(thinkingText) + '...</div></div>');
            $messages.append($loading);
            this.scrollAIBotToBottom($messages);

            // Build history for context
            var history = chat.state.messages.slice(-10);

            // Send AJAX request
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_ai_bot_query',
                    nonce: aiConfig.nonce,
                    message: message,
                    history: JSON.stringify(history),
                },
                success: function(response) {
                    $loading.remove();
                    chat.state.isLoading = false;

                    if (response.success) {
                        self.handleAIBotResponse($messages, chat, response.data);
                    } else {
                        var errorMsg = response.data?.message || 'Something went wrong.';
                        self.addAIBotMessage($messages, 'error', errorMsg);
                    }
                },
                error: function() {
                    $loading.remove();
                    chat.state.isLoading = false;
                    self.addAIBotMessage($messages, 'error', 'Something went wrong. Please try again.');
                }
            });
        },

        /**
         * Handle AI Bot response
         */
        handleAIBotResponse: function($messages, chat, data) {
            // Add AI explanation
            if (data.explanation) {
                this.addAIBotMessage($messages, 'ai', data.explanation);
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
                    this.addAIBotResults($messages, chat, data.results);
                } else if (!data.explanation) {
                    this.addAIBotMessage($messages, 'ai', 'I couldn\'t find any matching results. Try rephrasing your question.');
                }
            } else if (!data.explanation) {
                this.addAIBotMessage($messages, 'ai', 'I couldn\'t find any matching results. Try rephrasing your question.');
            }
        },

        /**
         * Add message to AI Bot chat
         */
        addAIBotMessage: function($messages, type, content) {
            var escapedContent = this.escapeHtml(content);
            var html = '<div class="vt-ai-bot-message vt-ai-bot-message-' + type + '">';
            html += '<div class="vt-ai-bot-message-content">' + escapedContent + '</div>';
            html += '</div>';

            $messages.append(html);
            this.scrollAIBotToBottom($messages);

            // Store in chat history (only user and ai messages)
            if (type === 'user' || type === 'ai') {
                // Find the chat in openChats and update its state
                var chat = this.state.openChats.find(function(c) { return c.key === 'ai-bot-chat'; });
                if (chat) {
                    chat.state.messages.push({type: type, content: content});
                }
            }
        },

        /**
         * Add AI Bot search results
         */
        addAIBotResults: function($messages, chat, results) {
            var self = this;

            var html = '<div class="vt-ai-bot-results">';

            results.forEach(function(result) {
                if (result.count === 0) return;

                html += '<div class="vt-ai-bot-result-group">';
                if (results.length > 1) {
                    html += '<div class="vt-ai-bot-result-header">' + self.escapeHtml(result.post_type_label) + '</div>';
                }

                // Use pre-rendered HTML from the AI Bot response
                html += '<div class="vt-ai-bot-result-cards">' + result.html + '</div>';

                // View all link
                if (result.has_more && result.archive_url) {
                    html += '<a href="' + self.escapeHtml(result.archive_url) + '" class="vt-ai-bot-view-all" target="_blank">';
                    html += 'View all ' + self.escapeHtml(result.post_type_label) + ' →';
                    html += '</a>';
                }

                html += '</div>';
            });

            html += '</div>';

            $messages.append(html);
            this.scrollAIBotToBottom($messages);
        },

        /**
         * Scroll AI Bot messages to bottom
         */
        scrollAIBotToBottom: function($messages) {
            $messages.scrollTop($messages[0].scrollHeight);
        },

        /**
         * Intercept clicks on message action links to open chat windows instead of navigating
         */
        interceptMessageActions: function() {
            var self = this;

            // Intercept clicks on links with ?chat=p{id} or ?chat=u{id} in their href
            $(document).on('click', 'a[href*="?chat=p"], a[href*="?chat=u"], a[href*="&chat=p"], a[href*="&chat=u"]', function(e) {
                var href = $(this).attr('href');
                if (!href) return;

                // Extract the chat parameter
                var chatMatch = href.match(/[?&]chat=([pu])(\d+)/);
                if (!chatMatch) return;

                var type = chatMatch[1]; // 'p' for post, 'u' for user
                var id = parseInt(chatMatch[2], 10);

                if (!id) return;

                // Prevent default navigation
                e.preventDefault();
                e.stopPropagation();

                // Open chat window
                var targetType = type === 'p' ? 'post' : 'user';
                self.openChatByTarget(targetType, id);
            });
        },

        /**
         * Open a chat window with a specific target (post or user)
         * This fetches target info and opens a chat window
         */
        openChatByTarget: function(targetType, targetId) {
            var self = this;

            // Check if user is logged in
            if (!self.config.userId) {
                // Redirect to login or show message
                window.location.href = window.location.href;
                return;
            }

            // Check if we already have this chat open or in the list
            var existingChat = self.findExistingChatByTarget(targetType, targetId);
            if (existingChat) {
                self.openChat(existingChat);
                return;
            }

            // Determine author info (current user)
            var authorType = 'user';
            var authorId = self.config.userId;

            // Build a chat key based on target
            var chatKey = targetType + '-' + targetId + '-user-' + authorId;

            // Check if chat window already exists
            var existingWindow = self.chatWindows.find('[data-chat-key="' + chatKey + '"]');
            if (existingWindow.length > 0) {
                if (existingWindow.hasClass('minimized')) {
                    self.expandChat(chatKey);
                }
                return;
            }

            // Check max chat limit
            var maxChats = parseInt(self.container.data('max-chats')) || 3;
            if (self.state.openChats.length >= maxChats) {
                var oldestChat = self.state.openChats[0];
                self.closeChat(oldestChat.key, true);
            }

            // Create a new chat object with minimal info - we'll load the full data
            var newChat = {
                key: chatKey,
                author: {
                    type: authorType,
                    id: authorId,
                    name: 'You',
                },
                target: {
                    type: targetType,
                    id: targetId,
                    name: 'Loading...',
                    avatar: '',
                },
                messages: [],
                loading: true,
                minimized: false,
                isNewConversation: true,
            };

            // Add to open chats
            self.state.openChats.push(newChat);

            // Render the chat window
            self.renderChatWindow(newChat);

            // Open the popup if not already open
            if (!self.state.isOpen) {
                self.openPopup();
            }

            // Load chat details and messages
            self.loadChatByTarget(newChat, targetType, targetId);
        },

        /**
         * Find an existing chat by target
         */
        findExistingChatByTarget: function(targetType, targetId) {
            var self = this;

            // Check in open chats
            var found = self.state.openChats.find(function(chat) {
                return chat.target &&
                       chat.target.type === targetType &&
                       chat.target.id === targetId;
            });

            if (found) return found;

            // Check in chat list
            found = self.state.chats.find(function(chat) {
                return chat.target &&
                       chat.target.type === targetType &&
                       chat.target.id === targetId;
            });

            return found;
        },

        /**
         * Load chat details for a target
         */
        loadChatByTarget: function(chat, targetType, targetId) {
            var self = this;

            console.log('VT Messenger: loadChatByTarget called', {targetType: targetType, targetId: targetId});

            var ajaxUrl = (typeof Voxel_Config !== 'undefined' && Voxel_Config.ajax_url)
                ? Voxel_Config.ajax_url + '&action=inbox.load_chat'
                : self.config.ajaxUrl + '?action=inbox.load_chat';

            $.ajax({
                url: ajaxUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    author_type: 'user',
                    author_id: self.config.userId,
                    target_type: targetType,
                    target_id: targetId,
                    _wpnonce: self.config.nonce,
                },
                success: function(response) {
                    console.log('VT Messenger: loadChatByTarget response', response);
                    if (response.success) {
                        // Update chat with real data
                        chat.messages = response.list || [];
                        chat.loading = false;

                        // Update target info if available
                        if (response.target) {
                            console.log('VT Messenger: Got target from response', response.target);
                            chat.target = response.target;
                            self.updateChatWindowHeader(chat);
                        } else {
                            // Target info not in response - fetch it separately
                            console.log('VT Messenger: No target in response, fetching separately');
                            self.fetchTargetInfo(chat, targetType, targetId);
                        }

                        // Update author info if available
                        if (response.author) {
                            chat.author = response.author;
                        }

                        // Render messages
                        self.renderMessages(chat.key);
                    } else {
                        // Chat may not exist yet - fetch target info and show empty state
                        console.log('VT Messenger: Response not successful, fetching target info');
                        chat.messages = [];
                        chat.loading = false;
                        self.fetchTargetInfo(chat, targetType, targetId);
                        self.renderMessages(chat.key);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('VT Messenger: Error loading chat by target', error);
                    chat.messages = [];
                    chat.loading = false;
                    self.fetchTargetInfo(chat, targetType, targetId);
                    self.renderMessages(chat.key);
                }
            });
        },

        /**
         * Fetch target info (post or user) using our custom AJAX endpoint
         */
        fetchTargetInfo: function(chat, targetType, targetId) {
            var self = this;

            console.log('VT Messenger: fetchTargetInfo called', {targetType: targetType, targetId: targetId});

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    action: 'vt_get_chat_target_info',
                    target_type: targetType,
                    target_id: targetId,
                },
                success: function(response) {
                    console.log('VT Messenger: fetchTargetInfo response', response);
                    if (response.success && response.data) {
                        chat.target.name = response.data.name || (targetType === 'post' ? 'Post' : 'User') + ' #' + targetId;
                        if (response.data.avatar) {
                            chat.target.avatar = response.data.avatar;
                        }
                        console.log('VT Messenger: Updated chat target', chat.target);
                    } else {
                        console.log('VT Messenger: Response not successful or no data');
                        chat.target.name = (targetType === 'post' ? 'Post' : 'User') + ' #' + targetId;
                    }
                    self.updateChatWindowHeader(chat);
                },
                error: function(xhr, status, error) {
                    console.error('VT Messenger: fetchTargetInfo error', {xhr: xhr, status: status, error: error});
                    chat.target.name = (targetType === 'post' ? 'Post' : 'User') + ' #' + targetId;
                    self.updateChatWindowHeader(chat);
                }
            });
        },

        /**
         * Update the chat window header with real target info
         */
        updateChatWindowHeader: function(chat) {
            var self = this;
            var $window = self.chatWindows.find('[data-chat-key="' + chat.key + '"]');
            if (!$window.length) return;

            var targetName = chat.target ? chat.target.name : 'Unknown';
            var targetAvatar = chat.target ? chat.target.avatar : '';

            // Use widget avatar first, then global default avatar
            if (!targetAvatar) {
                var fallbackAvatar = self.widgetConfig.widgetAvatar || self.config.defaultAvatar;
                if (fallbackAvatar) {
                    targetAvatar = '<img src="' + self.escapeHtml(fallbackAvatar) + '" alt="' + self.escapeHtml(targetName) + '">';
                }
            }

            // Update header
            $window.find('.vt-chat-header-avatar').html(targetAvatar);
            $window.find('.vt-chat-header-name').text(targetName);

            // Update placeholder if replying as a post
            if (chat.author && chat.author.type === 'post' && chat.author.name) {
                var replyAsTemplate = self.widgetConfig.replyAs || self.config.i18n.replyAs;
                var placeholderText = replyAsTemplate.replace('%s', chat.author.name);
                $window.find('.vt-messenger-input').attr('placeholder', placeholderText);
            }
        },

        bindEvents: function() {
            var self = this;

            // Intercept message action clicks if enabled
            if (self.config.openChatsInWindow) {
                self.interceptMessageActions();
            }

            // Stop title flash when user focuses on window
            $(window).on('focus', function() {
                self.stopTitleFlash();
            });

            // Stop title flash when user interacts with page
            $(document).on('click keydown', function() {
                self.stopTitleFlash();
            });

            // Toggle popup
            self.button.on('click', function(e) {
                e.preventDefault();
                self.stopTitleFlash(); // Stop flashing when opening messenger
                self.togglePopup();
            });

            // Close popup
            self.popup.find('.vt-messenger-close').on('click', function(e) {
                e.preventDefault();
                self.closePopup();
            });

            // Search chats
            self.popup.find('.vt-messenger-search-input').on('input', function() {
                self.state.searchTerm = $(this).val();
                self.filterChats();
            });

            // Open chat from list
            $(document).on('click', '.vt-messenger-chat-item', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Hide tooltip immediately on click
                self.tooltip.css({
                    'display': 'none',
                    'opacity': '0'
                });

                var $item = $(this);
                var chatKey = $item.data('chat-key');

                // Check if this is an AI Bot circle
                if ($item.hasClass('vt-ai-bot-chat-circle') || $item.data('ai-bot') === true) {
                    self.openAIBotPanel();
                    return;
                }

                // Check if this is a persistent admin chat
                if ($item.hasClass('vt-persistent-admin-chat') || $item.data('persistent') === true) {
                    self.openPersistentAdminChat();
                    return;
                }

                var chat = self.findChatByKey(chatKey);
                if (chat) {
                    self.openChat(chat);
                } else {
                    console.error('VT Messenger: Chat not found for key', chatKey);
                }
            });

            // Show tooltip on hover
            $(document).on('mouseenter', '.vt-messenger-chat-item', function(e) {
                var $item = $(this);
                var name = $item.data('name');
                var excerpt = $item.data('excerpt');

                // Cancel any pending hide
                if (self.tooltipHideTimeout) {
                    clearTimeout(self.tooltipHideTimeout);
                    self.tooltipHideTimeout = null;
                }

                // Update tooltip content
                self.tooltip.find('.vt-tooltip-name').text(name);
                self.tooltip.find('.vt-tooltip-message').text(excerpt);

                // Position tooltip
                var offset = $item.offset();
                var itemWidth = $item.outerWidth();
                var itemHeight = $item.outerHeight();
                var tooltipWidth = 250; // Fixed width

                // Check if container is on left or right
                var isLeft = self.container.hasClass('vt-messenger-position-bottom-left');

                var tooltipLeft, tooltipTop;

                if (isLeft) {
                    // Position to the right of the circle
                    tooltipLeft = offset.left + itemWidth + 14;
                } else {
                    // Position to the left of the circle
                    tooltipLeft = offset.left - tooltipWidth - 14;
                }

                tooltipTop = offset.top + (itemHeight / 2);

                // Show tooltip - use visibility for smooth transitions
                self.tooltip.css({
                    'left': tooltipLeft + 'px',
                    'top': tooltipTop + 'px',
                    'display': 'block'
                });
                // Trigger reflow before adding opacity for smooth transition
                self.tooltip[0].offsetHeight;
                self.tooltip.css('opacity', '1');
            });

            // Hide tooltip on mouse leave
            $(document).on('mouseleave', '.vt-messenger-chat-item', function(e) {
                // Fade out first, then hide
                self.tooltip.css('opacity', '0');
                self.tooltipHideTimeout = setTimeout(function() {
                    self.tooltip.css('display', 'none');
                }, 150); // Match CSS transition duration
            });

            // Minimize chat window
            $(document).on('click', '.vt-messenger-chat-minimize', function(e) {
                e.preventDefault();
                var chatKey = $(this).closest('.vt-messenger-chat-window').data('chat-key');
                self.minimizeChat(chatKey);
            });

            // Close chat window
            $(document).on('click', '.vt-messenger-chat-close', function(e) {
                e.preventDefault();
                var $window = $(this).closest('.vt-messenger-chat-window');

                // Block closing persistent admin chat
                if ($window.data('persistent') === true || $window.hasClass('vt-persistent-admin-window')) {
                    return;
                }

                var chatKey = $window.data('chat-key');
                self.closeChat(chatKey);
            });

            // Expand minimized chat
            $(document).on('click', '.vt-messenger-minimized-chat', function(e) {
                e.preventDefault();
                var chatKey = $(this).data('chat-key');
                self.expandChat(chatKey);
            });

            // Send message
            $(document).on('click', '.vt-messenger-send-btn', function(e) {
                e.preventDefault();
                var chatKey = $(this).closest('.vt-messenger-chat-window').data('chat-key');
                self.sendMessage(chatKey);
            });

            // Send on Enter
            $(document).on('keydown', '.vt-messenger-input', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    var chatKey = $(this).closest('.vt-messenger-chat-window').data('chat-key');
                    self.sendMessage(chatKey);
                }
            });

            // Upload from device button
            $(document).on('click', '.vt-upload-device', function(e) {
                e.preventDefault();
                var $fileInput = $(this).closest('.vt-messenger-chat-footer').find('.vt-messenger-file-input');
                $fileInput.click();
            });

            // Handle file selection
            $(document).on('change', '.vt-messenger-file-input', function(e) {
                var files = e.target.files;
                if (files.length > 0) {
                    var chatKey = $(this).closest('.vt-messenger-chat-window').data('chat-key');
                    self.uploadFiles(chatKey, files);
                }
                // Reset input so same file can be selected again
                $(this).val('');
            });

            // Image lightbox
            $(document).on('click', '.vt-message-image img', function(e) {
                e.preventDefault();
                self.openLightbox($(this).attr('src'));
            });

            // Close lightbox
            $(document).on('click', '.vt-lightbox-overlay, .vt-lightbox-close', function(e) {
                if (e.target === this) {
                    self.closeLightbox();
                }
            });

            // Close lightbox on ESC key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    self.closeLightbox();
                }
            });

            // Mark chat as seen when user clicks on the chat window
            $(document).on('click', '.vt-messenger-chat-window', function(e) {
                var chatKey = $(this).data('chat-key');

                // Remove from unread tracking
                if (self.state.unreadChats[chatKey]) {
                    delete self.state.unreadChats[chatKey];
                    self.state.unreadCount = self.calculateUnreadCount();
                    self.updateBadge();
                    self.saveUnreadState(); // Persist to localStorage
                }
            });

            // Close popup when clicking outside (only if no chat windows are open)
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.vt-messenger-container').length &&
                    !$(e.target).closest('.vt-messenger-chat-window').length) {
                    if (self.state.openChats.length === 0) {
                        self.closePopup();
                    }
                }
            });
        },

        togglePopup: function() {
            // Check actual visibility, not just state (they can get out of sync)
            var isActuallyVisible = this.popup.is(':visible');

            if (isActuallyVisible) {
                // Only close if no chats are open
                if (this.state.openChats.length === 0) {
                    this.closePopup();
                }
            } else {
                this.openPopup();
            }
        },

        openPopup: function() {
            this.popup.fadeIn(200);
            this.state.isOpen = true;
            this.button.addClass('active');
        },

        closePopup: function() {
            this.popup.fadeOut(200);
            this.state.isOpen = false;
            this.button.removeClass('active');
        },

        loadChats: function() {
            var self = this;

            // Use Voxel's AJAX URL if available
            var ajaxUrl = (typeof Voxel_Config !== 'undefined' && Voxel_Config.ajax_url)
                ? Voxel_Config.ajax_url + '&action=inbox.list_chats'
                : self.config.ajaxUrl + '?action=inbox.list_chats';

            $.ajax({
                url: ajaxUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    pg: 1,
                    _wpnonce: self.config.nonce,
                },
                success: function(response) {
                    if (response.success) {
                        self.state.chats = response.list || [];
                        // Debug: Check what fields are available in chat objects
                        if (self.state.chats.length > 0) {
                        }

                        // Track all loaded chat keys and initialize unread tracking
                        var hasNewUnread = false;
                        self.state.chats.forEach(function(chat) {
                            // Mark this chat key as seen (ever)
                            self.state.seenChatKeys[chat.key] = true;

                            if (!chat.seen && chat.is_new && !self.state.unreadChats[chat.key]) {
                                // Mark as unread with count of 1 (we'll update it when we get the full data)
                                self.state.unreadChats[chat.key] = 1;
                                hasNewUnread = true;

                                // Load messages for this chat to get accurate unread count
                                self.getUnreadCountForChat(chat);
                            }
                        });

                        // Save if we added new unread chats
                        if (hasNewUnread) {
                            self.saveUnreadState();
                        }

                        self.state.unreadCount = self.calculateUnreadCount();
                        self.renderChatList();
                        self.updateBadge();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('VT Messenger: Error loading chats', error);
                },
                complete: function() {
                    self.popup.find('.vt-messenger-loading').hide();
                }
            });
        },

        renderChatList: function() {
            var self = this;
            var $list = self.popup.find('.vt-messenger-chat-list');
            var chats = self.state.searchTerm ? self.filterChatsByTerm(self.state.chats, self.state.searchTerm) : self.state.chats;

            // Preserve special circles before updating (persistent admin and AI Bot)
            var $persistentCircle = $list.find('.vt-persistent-admin-chat').detach();
            var $aiBotCircle = $list.find('.vt-ai-bot-chat-circle').detach();

            if (chats.length === 0) {
                if ($list.children().length > 0) {
                    $list.empty();
                }
                // Re-add special circles
                if ($aiBotCircle.length > 0) {
                    $list.prepend($aiBotCircle);
                }
                if ($persistentCircle.length > 0) {
                    $list.prepend($persistentCircle);
                }
                return;
            }

            // Generate new HTML - circles only, no tooltip in HTML
            var html = '';
            chats.forEach(function(chat) {
                var unreadClass = (!chat.seen && chat.is_new) ? 'unread' : '';
                var targetName = chat.target ? chat.target.name : 'Unknown';
                var targetAvatar = chat.target ? chat.target.avatar : '';
                var excerpt = chat.excerpt || '';

                // Use widget avatar first, then global default avatar
                if (!targetAvatar) {
                    var fallbackAvatar = self.widgetConfig.widgetAvatar || self.config.defaultAvatar;
                    if (fallbackAvatar) {
                        targetAvatar = '<img src="' + self.escapeHtml(fallbackAvatar) + '" alt="' + self.escapeHtml(targetName) + '">';
                    }
                }

                // Get sender name - check if message was sent by author or target
                var senderName = '';
                if (chat.author && chat.target) {
                    // Determine who sent the last message based on excerpt_sent_by
                    // If sent_by is 'author', use author's name, otherwise use target's name
                    if (chat.excerpt_sent_by === 'author') {
                        senderName = chat.author.name || 'You';
                    } else {
                        senderName = chat.target.name || targetName;
                    }
                }

                // Format message as "Sender: Message"
                var formattedMessage = senderName ? senderName + ': ' + excerpt : excerpt;

                // Truncate to ~50 characters after adding sender
                var truncatedMessage = formattedMessage.length > 60 ? formattedMessage.substring(0, 60) + '...' : formattedMessage;

                html += '<div class="vt-messenger-chat-item ' + unreadClass + '" data-chat-key="' + self.escapeHtml(chat.key) + '" data-name="' + self.escapeHtml(targetName) + '" data-excerpt="' + self.escapeHtml(truncatedMessage) + '">';
                html += '  <div class="vt-chat-avatar">' + targetAvatar;

                // Add badge with unread count if locally tracked as unread
                if (self.state.unreadChats[chat.key]) {
                    var unreadCount = self.state.unreadChats[chat.key];
                    if (unreadCount > 1) {
                        // Show number if more than 1 unread
                        html += '    <span class="vt-chat-avatar-badge">' + unreadCount + '</span>';
                    } else {
                        // Show just a dot if 1 unread or count not yet loaded
                        html += '    <span class="vt-chat-avatar-badge"></span>';
                    }
                }

                html += '  </div>';
                html += '</div>';
            });

            // Only update if content has changed
            var currentHtml = $list.html();
            if (currentHtml !== html) {
                $list.html(html);
            }

            // Re-add special circles at top (AI Bot after persistent admin)
            if ($aiBotCircle.length > 0) {
                $list.prepend($aiBotCircle);
            }
            if ($persistentCircle.length > 0) {
                $list.prepend($persistentCircle);
            }
        },

        autoOpenNewChat: function(chat) {
            var self = this;

            // Check if auto-open is enabled
            if (!self.config.autoOpen) {
                return;
            }

            // Check if chat is already open
            var existingIndex = self.state.openChats.findIndex(function(c) {
                return c.key === chat.key;
            });

            if (existingIndex !== -1) {
                // Already open, don't open again
                return;
            }

            // Check max chat limit
            var maxChats = parseInt(self.container.data('max-chats')) || 3;
            if (self.state.openChats.length >= maxChats) {
                // Don't auto-open if we're at max capacity
                // User will see the badge and can open manually
                return;
            }

            // Open the popup to show circles if not already open
            if (!self.state.isOpen) {
                self.openPopup();
            }

            // Automatically open the chat window after a brief delay
            setTimeout(function() {
                self.openChat(chat);
            }, 500);
        },

        openChat: function(chat) {
            var self = this;

            // Stop title flash when opening a chat
            self.stopTitleFlash();

            // Check if chat is already open
            var existingIndex = self.state.openChats.findIndex(function(c) {
                return c.key === chat.key;
            });

            if (existingIndex !== -1) {
                // Chat already open, just expand if minimized
                var $window = self.chatWindows.find('[data-chat-key="' + chat.key + '"]');
                if ($window.hasClass('minimized')) {
                    self.expandChat(chat.key);
                }
                // Don't close popup - keep circles visible
                return;
            }

            // Check max chat limit
            var maxChats = parseInt(self.container.data('max-chats')) || 3;
            if (self.state.openChats.length >= maxChats) {
                // Close the oldest chat
                var oldestChat = self.state.openChats[0];
                self.closeChat(oldestChat.key, true);
            }

            // Remove from main chats list (so circle disappears)
            var chatIndex = self.state.chats.findIndex(function(c) {
                return c.key === chat.key;
            });
            if (chatIndex !== -1) {
                self.state.chats.splice(chatIndex, 1);
                self.renderChatList(); // Re-render to remove the circle
            }

            // Add to open chats
            chat.messages = [];
            chat.loading = true;
            chat.minimized = false;
            self.state.openChats.push(chat);

            // Don't mark as read yet - badge will be cleared when user clicks chat window

            // Render chat window
            self.renderChatWindow(chat);

            // Load messages
            self.loadMessages(chat.key);

            // Don't close popup - keep circles visible
        },

        renderChatWindow: function(chat) {
            var self = this;
            var targetName = chat.target ? chat.target.name : 'Unknown';
            var targetAvatar = chat.target ? chat.target.avatar : '';

            // Use widget avatar first, then global default avatar
            if (!targetAvatar) {
                var fallbackAvatar = self.widgetConfig.widgetAvatar || self.config.defaultAvatar;
                if (fallbackAvatar) {
                    targetAvatar = '<img src="' + self.escapeHtml(fallbackAvatar) + '" alt="' + self.escapeHtml(targetName) + '">';
                }
            }

            var html = '<div class="vt-messenger-chat-window" data-chat-key="' + chat.key + '">';

            // Header
            html += '  <div class="vt-messenger-chat-header">';
            html += '    <div class="vt-chat-header-info">';
            html += '      <div class="vt-chat-header-avatar">' + targetAvatar + '</div>';
            html += '      <div class="vt-chat-header-name">' + self.escapeHtml(targetName) + '</div>';
            html += '    </div>';
            html += '    <div class="vt-chat-header-actions">';
            html += '      <button class="vt-messenger-chat-minimize" title="' + self.config.i18n.minimize + '">';
            html += '        <span>−</span>';
            html += '      </button>';
            html += '      <button class="vt-messenger-chat-close" title="' + self.config.i18n.close + '">';
            html += '        <span>×</span>';
            html += '      </button>';
            html += '    </div>';
            html += '  </div>';

            // Body
            html += '  <div class="vt-messenger-chat-body">';
            html += '    <div class="vt-messenger-messages">';
            if (chat.loading) {
                html += '      <div class="vt-messenger-loading"><i class="eicon-loading eicon-animation-spin"></i></div>';
            }
            html += '    </div>';
            html += '  </div>';

            // Footer - use widget-specific settings
            var placeholderText = self.widgetConfig.placeholder || self.config.i18n.typeMessage;

            // Show "Reply as [listing name]" when author is a post (listing conversation)
            if (chat.author && chat.author.type === 'post' && chat.author.name) {
                var replyAsTemplate = self.widgetConfig.replyAs || self.config.i18n.replyAs;
                placeholderText = replyAsTemplate.replace('%s', chat.author.name);
            }

            var sendIconHtml = self.widgetConfig.sendIcon || '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 10L17.5 2.5L10 17.5L8.75 11.25L2.5 10Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            var uploadIconHtml = self.widgetConfig.uploadIcon || '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L12 16M12 4L8 8M12 4L16 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 17V19C4 20.1046 4.89543 21 6 21H18C19.1046 21 20 20.1046 20 19V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

            html += '  <div class="vt-messenger-chat-footer">';
            html += '    <textarea class="vt-messenger-input" placeholder="' + self.escapeHtml(placeholderText) + '" rows="1"></textarea>';
            html += '    <div class="vt-messenger-upload-buttons">';
            html += '      <button class="vt-messenger-upload-btn vt-upload-device" title="Upload from device">';
            html += uploadIconHtml;
            html += '      </button>';
            html += '      <button class="vt-messenger-upload-toggle" style="display: none;" title="Attach file">';
            html += '        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
            html += '      </button>';
            html += '    </div>';
            html += '    <button class="vt-messenger-send-btn">';
            html += sendIconHtml;
            html += '    </button>';
            html += '    <input type="file" class="vt-messenger-file-input" style="display: none;" accept="image/*,video/*,application/pdf" multiple>';
            html += '  </div>';

            html += '</div>';

            self.chatWindows.append(html);
            self.repositionChatWindows();
        },

        getUnreadCountForChat: function(chat) {
            var self = this;

            var ajaxUrl = (typeof Voxel_Config !== 'undefined' && Voxel_Config.ajax_url)
                ? Voxel_Config.ajax_url + '&action=inbox.load_chat'
                : self.config.ajaxUrl + '?action=inbox.load_chat';

            $.ajax({
                url: ajaxUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    author_type: chat.author.type,
                    author_id: chat.author.id,
                    target_type: chat.target.type,
                    target_id: chat.target.id,
                    _wpnonce: self.config.nonce,
                },
                success: function(response) {
                    if (response.success && response.list) {
                        // Count messages that are not sent by author (received messages)
                        var unreadCount = 0;
                        response.list.forEach(function(message) {
                            if (message.sent_by !== 'author' && !message.seen) {
                                unreadCount++;
                            }
                        });

                        // Track previous count
                        var previousCount = self.state.unreadChats[chat.key] || 0;

                        // Only update if count actually changed
                        if (unreadCount !== previousCount) {
                            if (unreadCount > 0) {
                                self.state.unreadChats[chat.key] = unreadCount;
                                self.saveUnreadState();

                                // Sound and title flash moved to checkForUpdates()
                                // to play once per polling cycle instead of per chat
                            } else {
                                // No more unread messages - remove from tracking
                                delete self.state.unreadChats[chat.key];
                                self.saveUnreadState();
                            }

                            // Update UI
                            self.renderChatList();
                            self.state.unreadCount = self.calculateUnreadCount();
                            self.updateBadge();
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('VT Messenger: Error getting unread count', error);
                }
            });
        },

        loadMessages: function(chatKey) {
            var self = this;
            var chat = self.findChatByKey(chatKey);
            if (!chat) return;

            // Skip AI Bot chats - they don't load messages via AJAX
            if (chat.isAIBot) return;

            var ajaxUrl = (typeof Voxel_Config !== 'undefined' && Voxel_Config.ajax_url)
                ? Voxel_Config.ajax_url + '&action=inbox.load_chat'
                : self.config.ajaxUrl + '?action=inbox.load_chat';

            $.ajax({
                url: ajaxUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    author_type: chat.author.type,
                    author_id: chat.author.id,
                    target_type: chat.target.type,
                    target_id: chat.target.id,
                    _wpnonce: self.config.nonce,
                },
                success: function(response) {
                    if (response.success) {
                        chat.messages = response.list || [];
                        chat.loading = false;
                        self.renderMessages(chatKey);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('VT Messenger: Error loading messages', error);
                }
            });
        },

        renderMessages: function(chatKey) {
            var self = this;
            var chat = self.findChatByKey(chatKey);
            if (!chat) return;

            var $window = self.chatWindows.find('[data-chat-key="' + chatKey + '"]');
            var $messagesContainer = $window.find('.vt-messenger-messages');

            if (chat.messages.length === 0) {
                $messagesContainer.html('<div class="vt-messenger-no-messages">No messages yet</div>');
                return;
            }

            // Reverse messages so oldest is first (top) and newest is last (bottom)
            var messages = chat.messages.slice().reverse();

            var html = '';
            messages.forEach(function(message) {
                var sentByMe = message.sent_by === 'author';
                var messageClass = sentByMe ? 'sent' : 'received';

                html += '<div class="vt-messenger-message ' + messageClass + '">';
                html += '  <div class="vt-message-bubble">';
                if (message.has_content) {
                    html += '    <div class="vt-message-content">' + message.content + '</div>';
                }
                if (message.files && message.files.length > 0) {
                    message.files.forEach(function(file) {
                        if (file.is_image) {
                            html += '<div class="vt-message-image"><img src="' + file.preview + '" alt=""></div>';
                        }
                    });
                }
                html += '  </div>';
                html += '  <div class="vt-message-time">' + self.escapeHtml(message.time) + '</div>';
                // Add "Seen" badge for sent messages that have been seen
                if (sentByMe && message.seen) {
                    html += '  <div class="vt-message-seen-badge">Seen</div>';
                }
                html += '</div>';
            });

            $messagesContainer.html(html);
            self.scrollToBottom($messagesContainer);
        },

        sendMessage: function(chatKey) {
            var self = this;
            var chat = self.findChatByKey(chatKey);
            if (!chat) return;

            var $window = self.chatWindows.find('[data-chat-key="' + chatKey + '"]');
            var $input = $window.find('.vt-messenger-input');
            var content = $input.val().trim();

            if (!content) {
                return;
            }

            // Optimistic UI update
            var tempMessage = {
                id: 'temp-' + Date.now(),
                sent_by: 'author',
                has_content: true,
                content: '<p>' + self.escapeHtml(content) + '</p>',
                time: 'Just now',
                sending: true
            };
            // Add to beginning since messages array is in reverse chronological order
            chat.messages.unshift(tempMessage);
            self.renderMessages(chatKey);

            // Clear input
            $input.val('');

            // Send via AJAX
            var ajaxUrl = (typeof Voxel_Config !== 'undefined' && Voxel_Config.ajax_url)
                ? Voxel_Config.ajax_url + '&action=inbox.send_message'
                : self.config.ajaxUrl + '?action=inbox.send_message';

            var params = $.param({
                sender_type: chat.author.type,
                sender_id: chat.author.id,
                receiver_type: chat.target.type,
                receiver_id: chat.target.id,
                _wpnonce: self.config.nonce
            });

            var fields = { content: content };
            var formData = new FormData();
            formData.append('fields', JSON.stringify(fields));

            $.ajax({
                url: ajaxUrl + '&' + params,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response.success && response.data) {
                        // Remove temp message and add real message
                        chat.messages = chat.messages.filter(function(m) {
                            return m.id !== tempMessage.id;
                        });
                        if (response.data.message) {
                            // Add to beginning since messages array is in reverse chronological order
                            chat.messages.unshift(response.data.message);
                        }
                        self.renderMessages(chatKey);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('VT Messenger: Error sending message', error);
                    // Remove temp message on error
                    chat.messages = chat.messages.filter(function(m) {
                        return m.id !== tempMessage.id;
                    });
                    self.renderMessages(chatKey);
                }
            });
        },

        uploadFiles: function(chatKey, files) {
            var self = this;
            var chat = self.findChatByKey(chatKey);
            if (!chat) return;

            // Use Voxel's AJAX URL for file upload
            var ajaxUrl = (typeof Voxel_Config !== 'undefined' && Voxel_Config.ajax_url)
                ? Voxel_Config.ajax_url + '&action=inbox.send_message'
                : self.config.ajaxUrl + '?action=inbox.send_message';

            var params = $.param({
                sender_type: chat.author.type,
                sender_id: chat.author.id,
                receiver_type: chat.target.type,
                receiver_id: chat.target.id,
                _wpnonce: self.config.nonce
            });

            var formData = new FormData();

            // Build files array in Voxel format and append to FormData
            var filesArray = [];
            for (var i = 0; i < files.length; i++) {
                // Add to FormData with Voxel's expected pattern
                formData.append('files[files][]', files[i]);
                // Track as uploaded_file marker in fields array
                filesArray.push('uploaded_file');
            }

            // Include files array in fields
            var fields = {
                content: '',
                files: filesArray
            };
            formData.append('fields', JSON.stringify(fields));

            // Show optimistic UI update
            var tempMessage = {
                id: 'temp-' + Date.now(),
                sent_by: 'author',
                has_content: false,
                content: '',
                time: 'Uploading...',
                files: [],
                sending: true
            };

            // Create preview for images
            for (var i = 0; i < files.length; i++) {
                if (files[i].type.startsWith('image/')) {
                    tempMessage.files.push({
                        is_image: true,
                        preview: URL.createObjectURL(files[i])
                    });
                }
            }

            chat.messages.unshift(tempMessage);
            self.renderMessages(chatKey);

            $.ajax({
                url: ajaxUrl + '&' + params,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response.success && response.data) {
                        // Remove temp message and add real message
                        chat.messages = chat.messages.filter(function(m) {
                            return m.id !== tempMessage.id;
                        });
                        if (response.data.message) {
                            chat.messages.unshift(response.data.message);
                        }
                        self.renderMessages(chatKey);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('VT Messenger: Error uploading files', error);
                    // Remove temp message on error
                    chat.messages = chat.messages.filter(function(m) {
                        return m.id !== tempMessage.id;
                    });
                    self.renderMessages(chatKey);
                }
            });
        },

        minimizeChat: function(chatKey) {
            var self = this;
            var chat = self.findChatByKey(chatKey);
            if (!chat) return;

            var $window = self.chatWindows.find('[data-chat-key="' + chatKey + '"]');

            // Handle persistent admin chat differently - hide window but keep in state
            if (chat.isPersistentAdmin) {
                chat.minimized = true;
                $window.hide();
                self.repositionChatWindows();
                return;
            }

            // Remove from open chats
            var openIndex = self.state.openChats.findIndex(function(c) {
                return c.key === chatKey;
            });
            if (openIndex !== -1) {
                self.state.openChats.splice(openIndex, 1);
            }

            // Close the chat window
            $window.fadeOut(200, function() {
                $(this).remove();
                self.repositionChatWindows();
            });

            // Add back to circles list at the beginning
            // First check if we need to remove the oldest circle
            var maxCircles = 10; // Maximum circles to show
            if (self.state.chats.length >= maxCircles) {
                self.state.chats.pop(); // Remove the oldest (last) one
            }

            // Add to the beginning of the list
            self.state.chats.unshift(chat);
            self.renderChatList();
        },

        expandChat: function(chatKey) {
            var chat = this.findChatByKey(chatKey);
            if (!chat) return;

            chat.minimized = false;
            var $window = this.chatWindows.find('[data-chat-key="' + chatKey + '"]');
            $window.removeClass('minimized');
            $window.show();

            this.repositionChatWindows();

            // Scroll to bottom
            var $messagesContainer = $window.find('.vt-messenger-messages');
            this.scrollToBottom($messagesContainer);
        },

        closeChat: function(chatKey, silent) {
            var self = this;

            // Check if this is a persistent admin chat - cannot be closed
            var chat = self.state.openChats.find(function(c) {
                return c.key === chatKey;
            });
            if (chat && chat.isPersistentAdmin) {
                return; // Cannot close persistent admin chat
            }

            var chatIndex = self.state.openChats.findIndex(function(c) {
                return c.key === chatKey;
            });

            if (chatIndex !== -1) {
                self.state.openChats.splice(chatIndex, 1);
            }

            var $window = self.chatWindows.find('[data-chat-key="' + chatKey + '"]');
            $window.fadeOut(200, function() {
                $(this).remove();
                self.repositionChatWindows();
            });
        },

        repositionChatWindows: function() {
            var self = this;
            var position = self.container.hasClass('vt-messenger-position-bottom-left') ? 'left' : 'right';
            var offset = 0;
            var spacing = 10;

            self.chatWindows.find('.vt-messenger-chat-window').each(function(index) {
                var $window = $(this);
                var isMinimized = $window.hasClass('minimized');
                var width = isMinimized ? 250 : parseInt(self.container.css('--chat-width') || 320);

                if (position === 'right') {
                    $window.css('right', offset + 'px');
                } else {
                    $window.css('left', offset + 'px');
                }

                offset += width + spacing;
            });
        },

        startPolling: function() {
            var self = this;
            var frequency = self.config.polling.frequency || 5000; // Increased to 5 seconds

            self.state.polling = setInterval(function() {
                self.checkForUpdates();
            }, frequency);
        },

        checkForUpdates: function() {
            var self = this;

            // Prevent concurrent polling requests - critical for performance
            if (self.state.isPolling) {
                return; // Skip this poll if previous one is still running
            }

            self.state.isPolling = true;

            var ajaxUrl = (typeof Voxel_Config !== 'undefined' && Voxel_Config.ajax_url)
                ? Voxel_Config.ajax_url + '&action=inbox.list_chats'
                : self.config.ajaxUrl + '?action=inbox.list_chats';

            // Reload chat list to get new messages
            $.ajax({
                url: ajaxUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    pg: 1,
                    _wpnonce: self.config.nonce,
                },
                success: function(response) {
                    if (response.success) {
                        // Track total unread count BEFORE processing updates
                        var oldTotalUnreadCount = self.calculateUnreadCount();
                        var oldUnreadCount = self.state.unreadCount;
                        var newChats = response.list || [];

                        // Filter out chats that are currently open
                        var openChatKeys = self.state.openChats.map(function(c) {
                            return c.key;
                        });

                        self.state.chats = newChats.filter(function(chat) {
                            return openChatKeys.indexOf(chat.key) === -1;
                        });

                        // Track new unread chats from server
                        var hasNewUnread = false;
                        var newIncomingChats = [];
                        var previousUnreadCounts = JSON.parse(JSON.stringify(self.state.unreadChats)); // Clone

                        newChats.forEach(function(chat) {
                            // Mark this chat as seen (we now know about it)
                            var wasNeverSeenBefore = !self.state.seenChatKeys[chat.key];
                            self.state.seenChatKeys[chat.key] = true;

                            // Only auto-open if it's truly a NEW chat conversation
                            // (not just a new message in an existing chat that's in the popup)
                            var isBrandNewChat = wasNeverSeenBefore && !self.state.unreadChats[chat.key];

                            if (!chat.seen && chat.is_new) {
                                var previousCount = self.state.unreadChats[chat.key] || 0;

                                if (isBrandNewChat) {
                                    // Brand new chat - auto-open it and get accurate count
                                    self.state.unreadChats[chat.key] = 1;
                                    hasNewUnread = true;
                                    newIncomingChats.push(chat);
                                    self.getUnreadCountForChat(chat);
                                } else if (previousCount === 0) {
                                    // First time marking as unread - get accurate count
                                    self.state.unreadChats[chat.key] = 1;
                                    hasNewUnread = true;
                                    self.getUnreadCountForChat(chat);
                                }
                                // Don't constantly recheck already-tracked chats - save resources
                            }
                        });

                        // Auto-open new incoming chats
                        if (newIncomingChats.length > 0) {
                            newIncomingChats.forEach(function(chat) {
                                self.autoOpenNewChat(chat);
                            });
                        }

                        // Save if we added new unread chats
                        if (hasNewUnread) {
                            self.saveUnreadState();
                        }

                        self.state.unreadCount = self.calculateUnreadCount();

                        // Update badge
                        self.updateBadge();

                        // If popup is open, refresh list
                        if (self.state.isOpen) {
                            self.renderChatList();
                        }

                        // Check for new messages in open chats
                        self.state.openChats.forEach(function(openChat) {
                            self.loadMessages(openChat.key);
                        });

                        // Flash title if total unread count increased
                        var newTotalUnreadCount = self.calculateUnreadCount();
                        if (newTotalUnreadCount > oldTotalUnreadCount) {
                            // Flash title with total message count
                            var messageText = newTotalUnreadCount === 1
                                ? 'New message!'
                                : '(' + newTotalUnreadCount + ') New messages!';
                            self.flashTitle(messageText);
                        }
                    }

                    // Release polling lock when complete
                    self.state.isPolling = false;
                },
                error: function() {
                    // Release polling lock on error
                    self.state.isPolling = false;
                }
            });
        },

        calculateUnreadCount: function() {
            // Count total unread messages across all chats
            var total = 0;
            for (var key in this.state.unreadChats) {
                if (this.state.unreadChats.hasOwnProperty(key)) {
                    total += this.state.unreadChats[key];
                }
            }
            return total;
        },

        updateBadge: function() {
            if (this.badge.length === 0) return;

            if (this.state.unreadCount > 0) {
                this.badge.text(this.state.unreadCount > 99 ? '99+' : this.state.unreadCount);
                this.badge.show();
            } else {
                this.badge.hide();
            }
        },

        filterChats: function() {
            this.renderChatList();
        },

        filterChatsByTerm: function(chats, term) {
            if (!term) return chats;

            term = term.toLowerCase();
            return chats.filter(function(chat) {
                var targetName = chat.target ? chat.target.name.toLowerCase() : '';
                var excerpt = chat.excerpt ? chat.excerpt.toLowerCase() : '';
                return targetName.includes(term) || excerpt.includes(term);
            });
        },

        findChatByKey: function(chatKey) {
            var found = this.state.openChats.find(function(c) {
                return c.key === chatKey;
            });
            if (!found) {
                // Also check in all chats
                found = this.state.chats.find(function(c) {
                    return c.key === chatKey;
                });
            }
            return found;
        },

        scrollToBottom: function($container) {
            if ($container.length) {
                $container.scrollTop($container[0].scrollHeight);
            }
        },

        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

    // Initialize
    VT_Messenger.init();

})(jQuery);
