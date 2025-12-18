/**
 * Voxel Toolkit - RSVP JavaScript
 */

(function($) {
    'use strict';

    // Check if vtRsvp is defined
    if (typeof vtRsvp === 'undefined') {
        console.error('vtRsvp is not defined');
        return;
    }

    /**
     * RSVP Form Handler
     */
    function initRsvpForm() {
        // Form submission
        $(document).on('submit', '.vt-rsvp-form', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $wrapper = $form.closest('.vt-rsvp-form-wrapper');
            var $submitBtn = $form.find('.vt-rsvp-submit-btn');
            var $messageContainer = $form.find('.vt-rsvp-message-container');

            // Get wrapper data
            var postId = $wrapper.data('post-id');
            var requireApproval = $wrapper.data('require-approval') === 'yes';
            var maxAttendees = $wrapper.data('max-attendees');

            // Collect form values - start with base data
            var formData = {
                action: 'vt_rsvp_submit',
                nonce: vtRsvp.nonce,
                post_id: postId,
                require_approval: requireApproval ? 'yes' : 'no',
                max_attendees: maxAttendees
            };

            // Collect all form fields
            $form.find('input, textarea, select').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                if (name) {
                    formData[name] = $field.val();
                }
            });

            // Disable form
            $wrapper.addClass('vt-loading');
            $submitBtn.prop('disabled', true).text(vtRsvp.i18n.submitting);
            $messageContainer.empty();

            // Submit via AJAX
            $.post(vtRsvp.ajaxUrl, formData)
                .done(function(response) {
                    if (response.success) {
                        // Show success message and reload form state
                        location.reload();
                    } else {
                        showFormMessage($messageContainer, response.data.message || vtRsvp.i18n.error, 'error');
                    }
                })
                .fail(function() {
                    showFormMessage($messageContainer, vtRsvp.i18n.error, 'error');
                })
                .always(function() {
                    $wrapper.removeClass('vt-loading');
                    $submitBtn.prop('disabled', false).text($submitBtn.data('original-text') || 'RSVP');
                });
        });

        // Cancel RSVP
        $(document).on('click', '.vt-rsvp-cancel-btn', function(e) {
            e.preventDefault();

            if (!confirm(vtRsvp.i18n.confirmCancel)) {
                return;
            }

            var $btn = $(this);
            var $wrapper = $btn.closest('.vt-rsvp-form-wrapper');
            var postId = $wrapper.data('post-id');
            var email = $btn.data('email');

            $wrapper.addClass('vt-loading');
            $btn.prop('disabled', true).text(vtRsvp.i18n.cancelling);

            $.post(vtRsvp.ajaxUrl, {
                action: 'vt_rsvp_cancel',
                nonce: vtRsvp.nonce,
                post_id: postId,
                user_email: email
            })
                .done(function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || vtRsvp.i18n.error);
                    }
                })
                .fail(function() {
                    alert(vtRsvp.i18n.error);
                })
                .always(function() {
                    $wrapper.removeClass('vt-loading');
                    $btn.prop('disabled', false);
                });
        });

        // Character counter for comment field
        $(document).on('input', '.vt-rsvp-textarea', function() {
            var $textarea = $(this);
            var $counter = $textarea.siblings('.vt-rsvp-char-count').find('.current');
            var maxLength = parseInt($textarea.attr('maxlength'), 10);
            var currentLength = $textarea.val().length;

            $counter.text(currentLength);

            if (currentLength >= maxLength) {
                $counter.css('color', '#ef4444');
            } else {
                $counter.css('color', '');
            }
        });

        // Store original button text
        $('.vt-rsvp-submit-btn').each(function() {
            $(this).data('original-text', $(this).text());
        });
    }

    /**
     * Attendee List Handler
     */
    function initAttendeeList() {
        // Approve RSVP
        $(document).on('click', '.vt-approve-btn', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $item = $btn.closest('.vt-attendee-item');
            var rsvpId = $btn.data('rsvp-id');

            $btn.prop('disabled', true).text(vtRsvp.i18n.approving);

            $.post(vtRsvp.ajaxUrl, {
                action: 'vt_rsvp_approve',
                nonce: vtRsvp.nonce,
                rsvp_id: rsvpId
            })
                .done(function(response) {
                    if (response.success) {
                        // Update status badge
                        var $badge = $item.find('.vt-status-badge');
                        $badge.removeClass('vt-status-pending vt-status-rejected')
                              .addClass('vt-status-approved')
                              .text('Approved');

                        // Remove approve/reject buttons, keep delete
                        $btn.remove();
                        $item.find('.vt-reject-btn').remove();
                    } else {
                        alert(response.data.message || vtRsvp.i18n.error);
                        $btn.prop('disabled', false).text('Approve');
                    }
                })
                .fail(function() {
                    alert(vtRsvp.i18n.error);
                    $btn.prop('disabled', false).text('Approve');
                });
        });

        // Reject RSVP
        $(document).on('click', '.vt-reject-btn', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $item = $btn.closest('.vt-attendee-item');
            var rsvpId = $btn.data('rsvp-id');

            $btn.prop('disabled', true).text(vtRsvp.i18n.rejecting);

            $.post(vtRsvp.ajaxUrl, {
                action: 'vt_rsvp_reject',
                nonce: vtRsvp.nonce,
                rsvp_id: rsvpId
            })
                .done(function(response) {
                    if (response.success) {
                        // Update status badge
                        var $badge = $item.find('.vt-status-badge');
                        $badge.removeClass('vt-status-pending vt-status-approved')
                              .addClass('vt-status-rejected')
                              .text('Rejected');

                        // Remove approve/reject buttons, keep delete
                        $btn.remove();
                        $item.find('.vt-approve-btn').remove();
                    } else {
                        alert(response.data.message || vtRsvp.i18n.error);
                        $btn.prop('disabled', false).text('Reject');
                    }
                })
                .fail(function() {
                    alert(vtRsvp.i18n.error);
                    $btn.prop('disabled', false).text('Reject');
                });
        });

        // Delete RSVP
        $(document).on('click', '.vt-delete-btn', function(e) {
            e.preventDefault();

            if (!confirm(vtRsvp.i18n.confirmDelete)) {
                return;
            }

            var $btn = $(this);
            var $item = $btn.closest('.vt-attendee-item');
            var rsvpId = $btn.data('rsvp-id');

            $btn.prop('disabled', true).text(vtRsvp.i18n.deleting);

            $.post(vtRsvp.ajaxUrl, {
                action: 'vt_rsvp_delete',
                nonce: vtRsvp.nonce,
                rsvp_id: rsvpId
            })
                .done(function(response) {
                    if (response.success) {
                        // Animate removal
                        $item.addClass('vt-removing');
                        setTimeout(function() {
                            $item.remove();

                            // Check if list is empty
                            var $wrapper = $('.vt-attendee-list-wrapper');
                            if ($wrapper.find('.vt-attendee-item').length === 0) {
                                location.reload();
                            }
                        }, 300);
                    } else {
                        alert(response.data.message || vtRsvp.i18n.error);
                        $btn.prop('disabled', false).text('Delete');
                    }
                })
                .fail(function() {
                    alert(vtRsvp.i18n.error);
                    $btn.prop('disabled', false).text('Delete');
                });
        });

        // Load more
        $(document).on('click', '.vt-load-more-btn', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $wrapper = $btn.closest('.vt-attendee-list-wrapper');
            var $list = $wrapper.find('.vt-attendee-list');

            var postId = $wrapper.data('post-id');
            var currentPage = parseInt($wrapper.data('page'), 10);
            var perPage = parseInt($wrapper.data('per-page'), 10);
            var total = parseInt($wrapper.data('total'), 10);
            var statuses = $wrapper.data('statuses');

            var nextPage = currentPage + 1;

            $btn.prop('disabled', true).text('Loading...');

            $.ajax({
                url: vtRsvp.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'vt_rsvp_get_list',
                    nonce: vtRsvp.nonce,
                    post_id: postId,
                    page: nextPage,
                    per_page: perPage,
                    statuses: statuses.split(',')
                }
            })
                .done(function(response) {
                    if (response.success && response.data.rsvps) {
                        // Append new items
                        response.data.rsvps.forEach(function(rsvp) {
                            var $item = createAttendeeItem(rsvp, $wrapper);
                            $item.addClass('vt-new');
                            $list.append($item);
                        });

                        // Update page counter
                        $wrapper.data('page', nextPage);

                        // Hide load more if no more pages
                        if (nextPage >= response.data.pages) {
                            $btn.closest('.vt-load-more-wrapper').remove();
                        } else {
                            $btn.prop('disabled', false).text('Load More');
                        }
                    } else {
                        alert(vtRsvp.i18n.error);
                        $btn.prop('disabled', false).text('Load More');
                    }
                })
                .fail(function() {
                    alert(vtRsvp.i18n.error);
                    $btn.prop('disabled', false).text('Load More');
                });
        });
    }

    /**
     * Create attendee item HTML
     */
    function createAttendeeItem(rsvp, $wrapper) {
        var showAvatars = $wrapper.find('.vt-attendee-avatar').length > 0;
        var showComments = $wrapper.find('.vt-attendee-comment').length > 0;
        var showTimestamps = $wrapper.find('.vt-attendee-timestamp').length > 0;
        var showStatusBadge = $wrapper.find('.vt-status-badge').length > 0;
        var showAdminActions = $wrapper.find('.vt-admin-actions').length > 0;

        var html = '<div class="vt-attendee-item" data-rsvp-id="' + rsvp.id + '">';
        html += '<div class="vt-attendee-main">';

        if (showAvatars && rsvp.avatar_url) {
            html += '<img src="' + escapeHtml(rsvp.avatar_url) + '" alt="" class="vt-attendee-avatar">';
        }

        html += '<div class="vt-attendee-info">';
        html += '<div class="vt-attendee-name-row">';
        html += '<span class="vt-attendee-name">' + escapeHtml(rsvp.user_name) + '</span>';

        if (showStatusBadge) {
            html += '<span class="vt-status-badge vt-status-' + escapeHtml(rsvp.status) + '">';
            html += escapeHtml(rsvp.status.charAt(0).toUpperCase() + rsvp.status.slice(1));
            html += '</span>';
        }

        html += '</div>';

        if (showComments && rsvp.comment) {
            html += '<div class="vt-attendee-comment">' + escapeHtml(rsvp.comment) + '</div>';
        }

        if (showTimestamps && rsvp.time_ago) {
            html += '<div class="vt-attendee-timestamp">' + escapeHtml(rsvp.time_ago) + '</div>';
        }

        html += '</div></div>';

        if (showAdminActions) {
            html += '<div class="vt-admin-actions">';
            if (rsvp.status === 'pending') {
                html += '<button type="button" class="vt-approve-btn" data-rsvp-id="' + rsvp.id + '">Approve</button>';
                html += '<button type="button" class="vt-reject-btn" data-rsvp-id="' + rsvp.id + '">Reject</button>';
            }
            html += '<button type="button" class="vt-delete-btn" data-rsvp-id="' + rsvp.id + '">Delete</button>';
            html += '</div>';
        }

        html += '</div>';

        return $(html);
    }

    /**
     * Show form message
     */
    function showFormMessage($container, message, type) {
        var className = 'vt-rsvp-message';
        if (type === 'error') {
            className += ' vt-rsvp-error';
        } else if (type === 'success') {
            className += ' vt-rsvp-success';
        } else if (type === 'pending') {
            className += ' vt-rsvp-pending';
        }

        $container.html('<p class="' + className + '">' + escapeHtml(message) + '</p>');
    }

    /**
     * Escape HTML special characters
     */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initRsvpForm();
        initAttendeeList();
    });

})(jQuery);
