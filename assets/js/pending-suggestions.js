/**
 * Pending Suggestions Widget JavaScript
 */
(function($) {
    'use strict';

    var PendingSuggestionsWidget = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Accept suggestion
            $(document).on('click', '.vt-accept-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var suggestionId = $btn.data('suggestion-id');

                self.updateSuggestionStatus(suggestionId, 'accept', $btn);
            });

            // Reject suggestion
            $(document).on('click', '.vt-reject-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var suggestionId = $btn.data('suggestion-id');

                if (!confirm('Are you sure you want to reject this suggestion?')) {
                    return;
                }

                self.updateSuggestionStatus(suggestionId, 'reject', $btn);
            });

            // Save all accepted suggestions
            $(document).on('click', '.vt-save-all-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $wrapper = $btn.closest('.vt-pending-suggestions-wrapper');
                var postId = $wrapper.data('post-id');

                if (!confirm(vtPendingSuggestions.i18n.confirmSave)) {
                    return;
                }

                self.saveAcceptedSuggestions(postId, $btn);
            });

            // Status filter
            $(document).on('change', '.vt-filter-select', function() {
                var status = $(this).val();
                var $items = $('.vt-suggestion-item');

                if (status === 'all') {
                    $items.show();
                } else {
                    $items.hide();
                    $items.filter('[data-status="' + status + '"]').show();
                }
            });
        },

        updateSuggestionStatus: function(suggestionId, action, $btn) {
            var $item = $btn.closest('.vt-suggestion-item');
            var $messages = $item.closest('.vt-pending-suggestions-wrapper').find('.vt-form-messages');

            // Disable button
            $btn.prop('disabled', true);

            $.ajax({
                url: vtPendingSuggestions.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_' + action + '_suggestion',
                    nonce: vtPendingSuggestions.nonce,
                    suggestion_id: suggestionId
                },
                success: function(response) {
                    if (response.success) {
                        if (action === 'accept') {
                            // Update status to queued
                            $item.attr('data-status', 'queued');
                            $item.removeClass('vt-status-pending').addClass('vt-status-queued');
                            $item.find('.vt-suggestion-actions').html(
                                '<span class="vt-status-badge vt-status-queued">Queued for Save</span>'
                            );

                            // Show save button if not visible
                            if ($('.vt-save-actions').length === 0) {
                                var $saveBtn = $('<div class="vt-save-actions">' +
                                    '<button type="button" class="vt-save-all-btn">' +
                                    '<i class="eicon-save"></i> Save Changes <span class="vt-queued-count">(1)</span>' +
                                    '</button>' +
                                    '</div>');
                                $('.vt-suggestions-list').after($saveBtn);
                            } else {
                                // Update count
                                var count = $('.vt-status-queued').length;
                                $('.vt-queued-count').text('(' + count + ')');
                            }

                            this.showMessage($messages, vtPendingSuggestions.i18n.acceptSuccess, 'success');
                        } else {
                            // Remove item
                            $item.fadeOut(function() {
                                $(this).remove();

                                // Check if no more suggestions
                                if ($('.vt-suggestion-item').length === 0) {
                                    $('.vt-suggestions-list').html(
                                        '<div class="vt-no-suggestions"><p>No pending suggestions</p></div>'
                                    );
                                }
                            });

                            this.showMessage($messages, vtPendingSuggestions.i18n.rejectSuccess, 'success');
                        }
                    } else {
                        this.showMessage($messages, response.data || vtPendingSuggestions.i18n.error, 'error');
                        $btn.prop('disabled', false);
                    }
                }.bind(this),
                error: function() {
                    this.showMessage($messages, vtPendingSuggestions.i18n.error, 'error');
                    $btn.prop('disabled', false);
                }.bind(this)
            });
        },

        saveAcceptedSuggestions: function(postId, $btn) {
            var $wrapper = $btn.closest('.vt-pending-suggestions-wrapper');
            var $messages = $wrapper.find('.vt-form-messages');

            // Disable button
            $btn.prop('disabled', true).addClass('loading');

            $.ajax({
                url: vtPendingSuggestions.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_save_accepted_suggestions',
                    nonce: vtPendingSuggestions.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        this.showMessage($messages, response.data.message || vtPendingSuggestions.i18n.saveSuccess, 'success');

                        // Remove queued items
                        $('.vt-suggestion-item[data-status="queued"]').fadeOut(function() {
                            $(this).remove();

                            // Check if no more suggestions
                            if ($('.vt-suggestion-item').length === 0) {
                                $('.vt-suggestions-list').html(
                                    '<div class="vt-no-suggestions"><p>No pending suggestions</p></div>'
                                );
                                $('.vt-save-actions').remove();
                            }
                        });

                        // Remove save button
                        setTimeout(function() {
                            $('.vt-save-actions').fadeOut(function() {
                                $(this).remove();
                            });
                        }, 1000);

                        // Reload page after 2 seconds to show updated values
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        this.showMessage($messages, response.data || vtPendingSuggestions.i18n.error, 'error');
                        $btn.prop('disabled', false).removeClass('loading');
                    }
                }.bind(this),
                error: function() {
                    this.showMessage($messages, vtPendingSuggestions.i18n.error, 'error');
                    $btn.prop('disabled', false).removeClass('loading');
                }.bind(this)
            });
        },

        showMessage: function($container, message, type) {
            $container.html('<div class="vt-message vt-message-' + type + '">' + message + '</div>');

            setTimeout(function() {
                $container.find('.vt-message').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        PendingSuggestionsWidget.init();
    });

})(jQuery);
