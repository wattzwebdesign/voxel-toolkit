/**
 * Suggest Edits Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Accept suggestion
        $(document).on('click', '.vt-accept-suggestion', function(e) {
            e.preventDefault();

            var $button = $(this);
            var suggestionId = $button.data('suggestion-id');
            var $row = $button.closest('tr');

            if (!confirm('Are you sure you want to accept this suggestion? This will apply the change immediately.')) {
                return;
            }

            // Disable buttons
            $button.prop('disabled', true).text('Accepting...');
            $row.find('.vt-reject-suggestion').prop('disabled', true);

            $.ajax({
                url: vtSuggestEdits.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_accept_suggestion',
                    nonce: vtSuggestEdits.nonce,
                    suggestion_id: suggestionId
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        $row.addClass('status-accepted');
                        $row.find('.status-badge')
                            .removeClass('status-pending')
                            .addClass('status-accepted')
                            .text('Accepted');
                        $row.find('.actions').html('<em>Applied</em>');

                        // Show success message
                        showNotice('success', response.data);
                    } else {
                        showNotice('error', response.data || 'Failed to accept suggestion');
                        $button.prop('disabled', false).text('Accept');
                        $row.find('.vt-reject-suggestion').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    showNotice('error', 'AJAX error: ' + error);
                    $button.prop('disabled', false).text('Accept');
                    $row.find('.vt-reject-suggestion').prop('disabled', false);
                }
            });
        });

        // Reject suggestion
        $(document).on('click', '.vt-reject-suggestion', function(e) {
            e.preventDefault();

            var $button = $(this);
            var suggestionId = $button.data('suggestion-id');
            var $row = $button.closest('tr');

            if (!confirm('Are you sure you want to reject this suggestion?')) {
                return;
            }

            // Disable buttons
            $button.prop('disabled', true).text('Rejecting...');
            $row.find('.vt-accept-suggestion').prop('disabled', true);

            $.ajax({
                url: vtSuggestEdits.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_reject_suggestion',
                    nonce: vtSuggestEdits.nonce,
                    suggestion_id: suggestionId
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        $row.addClass('status-rejected');
                        $row.find('.status-badge')
                            .removeClass('status-pending')
                            .addClass('status-rejected')
                            .text('Rejected');
                        $row.find('.actions').html('<em>Rejected</em>');

                        // Show success message
                        showNotice('success', response.data);
                    } else {
                        showNotice('error', response.data || 'Failed to reject suggestion');
                        $button.prop('disabled', false).text('Reject');
                        $row.find('.vt-accept-suggestion').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    showNotice('error', 'AJAX error: ' + error);
                    $button.prop('disabled', false).text('Reject');
                    $row.find('.vt-accept-suggestion').prop('disabled', false);
                }
            });
        });

        // Helper function to show admin notices
        function showNotice(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.vt-suggest-edits-admin h1').after($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }

    });

})(jQuery);
