/**
 * Checklist Display Widget JavaScript
 *
 * Handles checklist item toggle interactions via AJAX.
 *
 * @package Voxel_Toolkit
 */

(function($) {
    'use strict';

    var VT_Checklist = {
        /**
         * Initialize checklist functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Handle click on checklist items
            $(document).on('click', '.vt-checklist-item.can-check', function(e) {
                e.preventDefault();
                self.toggleItem($(this));
            });
        },

        /**
         * Toggle a checklist item
         *
         * @param {jQuery} $item The checklist item element
         */
        toggleItem: function($item) {
            var self = this;

            // Prevent double-clicks
            if ($item.hasClass('is-loading')) {
                return;
            }

            var $container = $item.closest('.vt-checklist-display');
            var postId = $container.data('post-id');
            var fieldKey = $container.data('field-key');
            var nonce = $container.data('nonce');
            var itemIndex = $item.data('index');
            var isCurrentlyChecked = $item.hasClass('is-checked');
            var newCheckedState = !isCurrentlyChecked;

            // Add loading state
            $item.addClass('is-loading');

            // Send AJAX request
            $.ajax({
                url: vtChecklist.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_checklist_toggle',
                    nonce: nonce,
                    post_id: postId,
                    field_key: fieldKey,
                    item_index: itemIndex,
                    checked: newCheckedState ? 1 : 0
                },
                success: function(response) {
                    $item.removeClass('is-loading');

                    if (response.success) {
                        // Update item state
                        self.updateItemState($item, newCheckedState, response.data.timestamp);

                        // Update progress bar
                        self.updateProgress($container, response.data.progress);
                    } else {
                        // Show error message
                        self.showError(response.data.message || vtChecklist.i18n.error);
                    }
                },
                error: function() {
                    $item.removeClass('is-loading');
                    self.showError(vtChecklist.i18n.error);
                }
            });
        },

        /**
         * Update item state after toggle
         *
         * @param {jQuery} $item The checklist item element
         * @param {boolean} isChecked Whether the item is now checked
         * @param {string|null} timestamp The timestamp when checked
         */
        updateItemState: function($item, isChecked, timestamp) {
            var $checkbox = $item.find('.vt-checklist-checkbox');

            if (isChecked) {
                $item.addClass('is-checked');
                $checkbox.addClass('is-checked');

                // Add timestamp if provided
                if (timestamp) {
                    var $timestamp = $item.find('.vt-checklist-timestamp');
                    if ($timestamp.length) {
                        $timestamp.text(timestamp);
                    }
                }
            } else {
                $item.removeClass('is-checked');
                $checkbox.removeClass('is-checked');
            }
        },

        /**
         * Update progress bar and text
         *
         * @param {jQuery} $container The checklist container
         * @param {Object} progress Progress data with total, checked, percentage
         */
        updateProgress: function($container, progress) {
            if (!progress) {
                return;
            }

            // Update progress bar fill
            var $fill = $container.find('.vt-checklist-progress-fill');
            if ($fill.length) {
                $fill.css('width', progress.percentage + '%');
            }

            // Update progress text
            var $text = $container.find('.vt-checklist-progress-text');
            if ($text.length) {
                var text = $text.text();
                // Update numbers in the text
                text = text.replace(/\d+(?= of)/, progress.checked);
                text = text.replace(/(?<=of )\d+/, progress.total);
                text = text.replace(/\d+(?=%)/, progress.percentage);
                $text.text(text);
            }
        },

        /**
         * Show error message
         *
         * @param {string} message The error message to display
         */
        showError: function(message) {
            // Check if Voxel's notification system is available
            if (typeof Voxel !== 'undefined' && Voxel.notify) {
                Voxel.notify({
                    type: 'error',
                    message: message
                });
            } else {
                // Fallback to alert
                alert(message);
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        VT_Checklist.init();
    });

})(jQuery);
