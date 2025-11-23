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
        notificationSound: null,
        soundEnabled: false,
        originalTitle: document.title,
        titleFlashInterval: null,
        state: {
            isOpen: false,
            chats: [],
            openChats: [],
            activeChat: null,
            unreadCount: 0,
            polling: null,
            searchTerm: '',
            unreadChats: {}, // Track unread status and count by chat key: { 'chat-key': 3 }
            seenChatKeys: {}, // Track all chat keys we've ever seen to prevent auto-opening existing chats
        },

        init: function() {
            var self = this;

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

                // Initialize notification sound
                self.initNotificationSound();

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

                // Start polling for new messages
                if (self.config.polling && self.config.polling.enabled) {
                    self.startPolling();
                }
            });
        },

        initNotificationSound: function() {
            var self = this;
            // Create Audio element for notification sound
            var soundUrl = (this.config.pluginUrl || '') + 'assets/sounds/new-message-sound.mp3';
            this.notificationSound = new Audio(soundUrl);
            this.notificationSound.volume = 0.5; // Set volume to 50%
            this.notificationSound.muted = true; // Start muted to allow preload

            // Preload the audio
            this.notificationSound.load();

            // Test if audio can be loaded
            this.notificationSound.addEventListener('canplaythrough', function() {
                // Try to play muted to prime the audio
                self.notificationSound.play().then(function() {
                    self.notificationSound.pause();
                    self.notificationSound.currentTime = 0;
                    self.notificationSound.muted = false; // Unmute for actual playback
                }).catch(function(e) {
                    // Could not prime audio, will need user interaction
                });
            });
            this.notificationSound.addEventListener('error', function(e) {
                console.error('VT Messenger: Error loading notification sound', e);
            });
        },

        enableSound: function() {
            var self = this;
            if (!self.soundEnabled && self.notificationSound) {
                // Ensure unmuted
                self.notificationSound.muted = false;
                // Try to play and immediately pause to unlock audio context
                var playPromise = self.notificationSound.play();
                if (playPromise !== undefined) {
                    playPromise.then(function() {
                        self.notificationSound.pause();
                        self.notificationSound.currentTime = 0;
                        self.soundEnabled = true;
                    }).catch(function(err) {
                        // Could not enable sound
                    });
                }
            }
        },

        playNotificationSound: function() {
            var self = this;

            if (!self.soundEnabled) {
                return;
            }

            if (self.notificationSound) {
                try {
                    // Reset to beginning if already playing
                    self.notificationSound.currentTime = 0;

                    var playPromise = self.notificationSound.play();

                    if (playPromise !== undefined) {
                        playPromise.then(function() {
                            // Sound played successfully
                        }).catch(function(error) {
                            // Try to re-enable on next interaction
                            self.soundEnabled = false;
                        });
                    }
                } catch (e) {
                    console.error('VT Messenger: Error playing notification sound', e);
                }
            } else {
                console.error('VT Messenger: notificationSound not initialized');
            }
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

        bindEvents: function() {
            var self = this;

            // Enable sound on ANY user interaction with the page
            var enableSoundOnInteraction = function() {
                self.enableSound();
            };

            // Listen for various user interactions
            $(document).one('click mousedown keydown touchstart', function() {
                enableSoundOnInteraction();
            });

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
                self.enableSound(); // Also try to enable on button click
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

                var chatKey = $(this).data('chat-key');
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

                self.tooltip.css({
                    'left': tooltipLeft + 'px',
                    'top': tooltipTop + 'px',
                    'display': 'block',
                    'opacity': '1'
                });
            });

            // Hide tooltip on mouse leave
            $(document).on('mouseleave', '.vt-messenger-chat-item', function(e) {
                self.tooltip.css({
                    'display': 'none',
                    'opacity': '0'
                });
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
                var chatKey = $(this).closest('.vt-messenger-chat-window').data('chat-key');
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
            if (this.state.isOpen) {
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

            if (chats.length === 0) {
                if ($list.children().length > 0) {
                    $list.empty();
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

                // Use default avatar if no avatar is set
                if (!targetAvatar && self.config.defaultAvatar) {
                    targetAvatar = '<img src="' + self.escapeHtml(self.config.defaultAvatar) + '" alt="' + self.escapeHtml(targetName) + '">';
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

            // Use default avatar if no avatar is set
            if (!targetAvatar && self.config.defaultAvatar) {
                targetAvatar = '<img src="' + self.escapeHtml(self.config.defaultAvatar) + '" alt="' + self.escapeHtml(targetName) + '">';
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

            // Footer
            html += '  <div class="vt-messenger-chat-footer">';
            html += '    <textarea class="vt-messenger-input" placeholder="' + self.config.i18n.typeMessage + '" rows="1"></textarea>';
            html += '    <div class="vt-messenger-upload-buttons">';
            html += '      <button class="vt-messenger-upload-btn vt-upload-device" title="Upload from device">';
            html += '        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L12 16M12 4L8 8M12 4L16 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 17V19C4 20.1046 4.89543 21 6 21H18C19.1046 21 20 20.1046 20 19V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
            html += '      </button>';
            html += '      <button class="vt-messenger-upload-toggle" style="display: none;" title="Attach file">';
            html += '        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
            html += '      </button>';
            html += '    </div>';
            html += '    <button class="vt-messenger-send-btn">';
            html += '      <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 10L17.5 2.5L10 17.5L8.75 11.25L2.5 10Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
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

                                // Play sound and flash title if count increased
                                if (unreadCount > previousCount) {
                                    self.playNotificationSound();

                                    // Flash title with message count
                                    var messageText = unreadCount === 1 ? 'New message!' : '(' + unreadCount + ') New messages!';
                                    self.flashTitle(messageText);
                                }
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

            // Remove from open chats
            var openIndex = self.state.openChats.findIndex(function(c) {
                return c.key === chatKey;
            });
            if (openIndex !== -1) {
                self.state.openChats.splice(openIndex, 1);
            }

            // Close the chat window
            var $window = self.chatWindows.find('[data-chat-key="' + chatKey + '"]');
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
            this.repositionChatWindows();

            // Scroll to bottom
            var $messagesContainer = $window.find('.vt-messenger-messages');
            this.scrollToBottom($messagesContainer);
        },

        closeChat: function(chatKey, silent) {
            var self = this;
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
                                    // Brand new chat - auto-open it
                                    self.state.unreadChats[chat.key] = 1;
                                    hasNewUnread = true;
                                    newIncomingChats.push(chat);
                                    // Get accurate count for brand new chats
                                    self.getUnreadCountForChat(chat);
                                } else if (previousCount === 0) {
                                    // First time marking as unread - get accurate count
                                    self.state.unreadChats[chat.key] = 1;
                                    hasNewUnread = true;
                                    self.getUnreadCountForChat(chat);
                                } else {
                                    // Chat already tracked - only check if we suspect new messages
                                    self.getUnreadCountForChat(chat);
                                }
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
                    }
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
