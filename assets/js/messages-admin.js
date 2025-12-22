/**
 * Messages Admin JavaScript
 *
 * @package Voxel_Toolkit
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initRowActions();
    });

    function initRowActions() {
        // Handle row action clicks
        $(document).on('click', '.msg__action', function(e) {
            e.preventDefault();

            var $link = $(this);
            var action = $link.data('action');
            var messageId = $link.closest('.row-actions').data('message-id');
            var $row = $link.closest('tr');

            // Confirmation for delete
            if (action === 'delete') {
                if (!confirm(vtMessagesAdmin.i18n.confirmDelete)) {
                    return;
                }
            }

            // Disable row during processing
            $row.addClass('vt-disabled');

            // Determine the AJAX action name
            var ajaxAction = 'vt_messages_' + action;

            // Make AJAX request
            $.ajax({
                url: vtMessagesAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: ajaxAction,
                    message_id: messageId,
                    nonce: vtMessagesAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page to show updated data
                        location.reload();
                    } else {
                        $row.removeClass('vt-disabled');
                        alert(response.data.message || vtMessagesAdmin.i18n.ajaxError);
                    }
                },
                error: function() {
                    $row.removeClass('vt-disabled');
                    alert(vtMessagesAdmin.i18n.ajaxError);
                }
            });
        });
    }

})(jQuery);
