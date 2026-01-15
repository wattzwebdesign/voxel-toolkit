/**
 * Social Proof Notifications
 *
 * Displays toast notifications showing recent Voxel app events.
 * Auto-rotates through recent events and polls for new ones in real-time.
 * Supports Activity Boost for generating additional notifications.
 *
 * @package Voxel_Toolkit
 */

(function($) {
    'use strict';

    const SocialProof = {
        config: {},
        events: [],
        currentIndex: 0,
        lastId: 0,
        rotationTimer: null,
        pollTimer: null,
        isVisible: false,
        isPaused: false,

        /**
         * Initialize
         */
        init: function() {
            this.config = window.voxelSocialProof || {};

            if (!this.config.enabled) {
                return;
            }

            this.container = $('#vt-social-proof-container');
            this.toast = this.container.find('.vt-social-proof-toast');

            if (!this.container.length || !this.toast.length) {
                return;
            }

            // Bind events
            this.bindEvents();

            // Check if boost_only mode - skip server fetch
            if (this.config.boostEnabled && this.config.boostMode === 'boost_only') {
                this.events = this.generateBoostEvents(this.config.maxEvents);
                var self = this;
                setTimeout(function() {
                    self.startRotation();
                }, self.config.delayBetween * 1000);
                return;
            }

            // Initial fetch
            this.fetchEvents(false).then(function() {
                // Apply boost logic after fetching real events
                SocialProof.applyBoostLogic();

                if (SocialProof.events.length > 0) {
                    // Start rotation after delay
                    setTimeout(function() {
                        SocialProof.startRotation();
                    }, SocialProof.config.delayBetween * 1000);
                }
            });

            // Start polling for real-time updates (only if not boost_only)
            this.startPolling();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Close button
            this.toast.on('click', '.vt-sp-close', function(e) {
                e.preventDefault();
                self.hideToast();
            });

            // Pause on hover
            this.toast.on('mouseenter', function() {
                self.isPaused = true;
            });

            this.toast.on('mouseleave', function() {
                self.isPaused = false;
            });

            // Click on toast (if has link)
            this.toast.on('click', function(e) {
                if ($(e.target).closest('.vt-sp-close').length) {
                    return;
                }

                var currentEvent = self.events[self.currentIndex];
                if (currentEvent && currentEvent.post_url) {
                    window.location.href = currentEvent.post_url;
                }
            });
        },

        /**
         * Fetch events from server
         */
        fetchEvents: function(checkNew) {
            var self = this;

            return $.ajax({
                url: this.config.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'vt_social_proof_get',
                    last_id: checkNew ? this.lastId : 0,
                    limit: this.config.maxEvents
                },
                dataType: 'json'
            }).then(function(response) {
                if (response.success) {
                    if (checkNew && response.data.events.length > 0) {
                        // New events arrived - show immediately
                        self.showNewEvents(response.data.events);
                    } else if (!checkNew) {
                        // Initial fetch
                        self.events = response.data.events;
                    }
                    self.lastId = response.data.last_id;
                }
            }).catch(function(error) {
                console.error('Social Proof: Error fetching events', error);
            });
        },

        /**
         * Start rotation through events
         */
        startRotation: function() {
            var self = this;

            if (this.events.length === 0) {
                return;
            }

            // Show first event
            this.showEvent(this.currentIndex);

            // Set up rotation interval
            var totalDuration = (this.config.displayDuration + this.config.delayBetween) * 1000;

            this.rotationTimer = setInterval(function() {
                if (self.isPaused) {
                    return;
                }

                self.currentIndex = (self.currentIndex + 1) % self.events.length;
                self.showEvent(self.currentIndex);
            }, totalDuration);
        },

        /**
         * Stop rotation
         */
        stopRotation: function() {
            if (this.rotationTimer) {
                clearInterval(this.rotationTimer);
                this.rotationTimer = null;
            }
        },

        /**
         * Start polling for new events
         */
        startPolling: function() {
            var self = this;

            if (this.config.pollInterval <= 0) {
                return;
            }

            this.pollTimer = setInterval(function() {
                self.fetchEvents(true);
            }, this.config.pollInterval * 1000);
        },

        /**
         * Show event at index
         */
        showEvent: function(index) {
            var event = this.events[index];

            if (!event) {
                return;
            }

            var self = this;

            // Update avatar
            var avatarImg = this.toast.find('.vt-sp-avatar img');
            if (event.user_avatar) {
                avatarImg.attr('src', event.user_avatar).show();
                this.toast.find('.vt-sp-avatar').show();
            } else {
                this.toast.find('.vt-sp-avatar').hide();
            }

            // Update message
            this.toast.find('.vt-sp-message').text(event.message);

            // Update time
            var timeEl = this.toast.find('.vt-sp-time');
            if (event.time_ago) {
                timeEl.text(event.time_ago).show();
            } else {
                timeEl.hide();
            }

            // Add clickable class if has link
            if (event.post_url) {
                this.toast.addClass('vt-sp-clickable');
            } else {
                this.toast.removeClass('vt-sp-clickable');
            }

            // Animate in
            this.animateIn();

            // Hide after duration
            setTimeout(function() {
                if (!self.isPaused) {
                    self.hideToast();
                }
            }, this.config.displayDuration * 1000);
        },

        /**
         * Show new events (from polling)
         */
        showNewEvents: function(newEvents) {
            // Prepend new events to the list
            this.events = newEvents.concat(this.events).slice(0, this.config.maxEvents);

            // Stop current rotation
            this.stopRotation();

            // Reset to first event (newest)
            this.currentIndex = 0;

            // Show first new event immediately
            this.showEvent(0);

            // Resume rotation after this event
            var self = this;
            var totalDuration = (this.config.displayDuration + this.config.delayBetween) * 1000;

            setTimeout(function() {
                self.startRotation();
            }, totalDuration);
        },

        /**
         * Animate toast in
         */
        animateIn: function() {
            var animation = this.config.animation || 'slide';

            this.toast.removeClass('vt-sp-hidden vt-sp-slide-out vt-sp-fade-out');

            if (animation === 'slide') {
                this.toast.addClass('vt-sp-visible vt-sp-slide-in');
            } else {
                this.toast.addClass('vt-sp-visible vt-sp-fade-in');
            }

            this.isVisible = true;
        },

        /**
         * Hide toast
         */
        hideToast: function() {
            var animation = this.config.animation || 'slide';

            this.toast.removeClass('vt-sp-slide-in vt-sp-fade-in');

            if (animation === 'slide') {
                this.toast.addClass('vt-sp-slide-out');
            } else {
                this.toast.addClass('vt-sp-fade-out');
            }

            var self = this;
            setTimeout(function() {
                self.toast.removeClass('vt-sp-visible').addClass('vt-sp-hidden');
                self.isVisible = false;
            }, 300);
        },

        /**
         * Apply boost logic based on mode
         */
        applyBoostLogic: function() {
            if (!this.config.boostEnabled) {
                return;
            }

            var mode = this.config.boostMode || 'fill_gaps';

            if (mode === 'fill_gaps' && this.events.length === 0) {
                // No real events - fill with boost events
                this.events = this.generateBoostEvents(this.config.maxEvents);
            } else if (mode === 'mixed') {
                // Mix boost events with real events
                this.events = this.mixWithBoostEvents(this.events);
            }
            // boost_only is handled in init() before fetch
        },

        /**
         * Generate multiple boost events
         */
        generateBoostEvents: function(count) {
            var events = [];
            for (var i = 0; i < count; i++) {
                events.push(this.generateBoostEvent());
            }
            return events;
        },

        /**
         * Format time ago string with i18n support
         */
        formatTimeAgo: function(value, unit) {
            var i18n = this.config.i18n || {};
            var ago = i18n.ago || 'ago';
            var unitStr;

            if (unit === 'minute') {
                unitStr = value === 1 ? (i18n.minute || 'minute') : (i18n.minutes || 'minutes');
            } else if (unit === 'hour') {
                unitStr = value === 1 ? (i18n.hour || 'hour') : (i18n.hours || 'hours');
            } else if (unit === 'day') {
                unitStr = value === 1 ? (i18n.day || 'day') : (i18n.days || 'days');
            } else {
                unitStr = unit;
            }

            return value + ' ' + unitStr + ' ' + ago;
        },

        /**
         * Generate a single boost event
         */
        generateBoostEvent: function() {
            var names = this.config.boostNames || ['Someone'];
            var listings = this.config.boostListings || ['a listing'];
            var messages = this.config.boostMessages || { signup: '{name} just joined' };
            var msgKeys = Object.keys(messages);

            var name = names[Math.floor(Math.random() * names.length)];
            var listing = listings[Math.floor(Math.random() * listings.length)];
            var msgKey = msgKeys[Math.floor(Math.random() * msgKeys.length)];
            var template = messages[msgKey] || '{name} just joined';

            var message = template.replace('{name}', name).replace('{listing}', listing);
            var minutesAgo = Math.floor(Math.random() * 25) + 1;

            return {
                id: 'boost-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9),
                message: message,
                user_avatar: this.config.defaultAvatar || '',
                post_url: '',
                time_ago: this.formatTimeAgo(minutesAgo, 'minute'),
                boost: true
            };
        },

        /**
         * Mix boost events with real events
         */
        mixWithBoostEvents: function(realEvents) {
            var boostCount = Math.ceil(this.config.maxEvents / 2);
            var boostEvents = this.generateBoostEvents(boostCount);

            // Interleave boost events with real events
            var mixed = [];
            var realIndex = 0;
            var boostIndex = 0;

            while (mixed.length < this.config.maxEvents && (realIndex < realEvents.length || boostIndex < boostEvents.length)) {
                // Add a real event
                if (realIndex < realEvents.length) {
                    mixed.push(realEvents[realIndex]);
                    realIndex++;
                }

                // Add a boost event
                if (boostIndex < boostEvents.length && mixed.length < this.config.maxEvents) {
                    mixed.push(boostEvents[boostIndex]);
                    boostIndex++;
                }
            }

            return mixed;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SocialProof.init();
    });

})(jQuery);
