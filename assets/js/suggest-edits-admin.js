/**
 * Suggest Edits Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // View suggestion modal
        $(document).on('click', '.vt-view-suggestion', function(e) {
            e.preventDefault();

            var $button = $(this);
            var data = $button.data();

            console.log('=== VT Admin Modal Debug ===');
            console.log('All button data:', data);

            // Populate modal
            $('#modal-post-title').text(data.postTitle);
            $('#modal-field-key').text(data.fieldKey);

            // Current value
            if (data.isIncorrect == 1 && !data.suggestedValue) {
                $('#modal-current-value').html('<span class="incorrect-marker">Marked as incorrect</span>');
            } else {
                $('#modal-current-value').text(data.currentValue || '(Empty)');
            }

            // Suggested value
            if (data.suggestedValue) {
                $('#modal-suggested-value').text(data.suggestedValue);
            } else {
                $('#modal-suggested-value').html('<em>(Remove)</em>');
            }

            // Proof images
            console.log('Proof images data:', data.proofImages);
            console.log('Proof images type:', typeof data.proofImages);
            if (data.proofImages && data.proofImages !== '' && data.proofImages !== 'undefined') {
                try {
                    // The data might already be an array if jQuery parsed it
                    var imageUrls;
                    if (typeof data.proofImages === 'string') {
                        imageUrls = JSON.parse(data.proofImages);
                    } else if (Array.isArray(data.proofImages)) {
                        imageUrls = data.proofImages;
                    } else {
                        imageUrls = [];
                    }

                    console.log('Parsed image URLs:', imageUrls);
                    console.log('Image URLs is array:', Array.isArray(imageUrls));
                    console.log('Image URLs length:', imageUrls ? imageUrls.length : 0);

                    if (Array.isArray(imageUrls) && imageUrls.length > 0) {
                        var imagesHtml = '';
                        imageUrls.forEach(function(imageUrl) {
                            console.log('Adding image:', imageUrl);
                            imagesHtml += '<a href="' + imageUrl + '" target="_blank" class="vt-proof-image">';
                            imagesHtml += '<img src="' + imageUrl + '" alt="Proof" onerror="console.error(\'Image failed to load:\', this.src)">';
                            imagesHtml += '</a>';
                        });
                        console.log('Generated images HTML:', imagesHtml);
                        $('#modal-proof-images').html(imagesHtml);
                        $('#modal-proof-images-row').show();
                    } else {
                        console.log('No image URLs found or empty array');
                        $('#modal-proof-images-row').hide();
                    }
                } catch(e) {
                    console.error('Error parsing proof images:', e);
                    console.error('Raw data.proofImages:', data.proofImages);
                    $('#modal-proof-images-row').hide();
                }
            } else {
                console.log('No proof images data (empty or undefined)');
                $('#modal-proof-images-row').hide();
            }

            // Suggester comment
            if (data.suggesterComment && data.suggesterComment.trim() !== '') {
                $('#modal-comment').text(data.suggesterComment);
                $('#modal-comment-row').show();
            } else {
                $('#modal-comment-row').hide();
            }

            // Suggester
            var suggesterText = data.suggesterName;
            if (data.suggesterUserId == 0) {
                suggesterText += ' <em>(Guest)</em>';
                if (data.suggesterEmail) {
                    suggesterText += ' - ' + data.suggesterEmail;
                }
            }
            $('#modal-suggester').html(suggesterText);

            // Date
            $('#modal-date').text(data.date);

            // Status
            var statusHtml = '<span class="status-badge status-' + data.status + '">' + data.status.charAt(0).toUpperCase() + data.status.slice(1) + '</span>';
            $('#modal-status').html(statusHtml);

            // Actions
            var actionsHtml = '';
            if (data.status === 'pending') {
                // Check if this is a permanently closed suggestion
                if (data.fieldKey === '_permanently_closed') {
                    actionsHtml += '<button class="button button-primary vt-delete-post" data-suggestion-id="' + data.suggestionId + '" data-post-id="' + data.postId + '">Delete Post</button>';
                    actionsHtml += '<button class="button vt-reject-suggestion" data-suggestion-id="' + data.suggestionId + '">Reject</button>';
                } else {
                    actionsHtml += '<button class="button button-primary vt-accept-suggestion" data-suggestion-id="' + data.suggestionId + '">Accept</button>';
                    actionsHtml += '<button class="button vt-reject-suggestion" data-suggestion-id="' + data.suggestionId + '">Reject</button>';
                }
            }
            $('#modal-actions').html(actionsHtml);

            // Show modal
            $('#vt-suggestion-modal').fadeIn(200);
            $('body').addClass('vt-modal-open');
        });

        // Close modal
        $(document).on('click', '.vt-modal-close, .vt-modal-overlay', function(e) {
            e.preventDefault();
            $('#vt-suggestion-modal').fadeOut(200);
            $('body').removeClass('vt-modal-open');
        });

        // Close modal on ESC key
        $(document).on('keyup', function(e) {
            if (e.key === 'Escape' && $('#vt-suggestion-modal').is(':visible')) {
                $('#vt-suggestion-modal').fadeOut(200);
                $('body').removeClass('vt-modal-open');
            }
        });

        // Accept suggestion
        $(document).on('click', '.vt-accept-suggestion', function(e) {
            e.preventDefault();

            var $link = $(this);
            var suggestionId = $link.data('suggestion-id');
            var $row = $link.closest('tr');

            if (!confirm('Are you sure you want to accept this suggestion? This will apply the change immediately.')) {
                return;
            }

            // Disable links
            var originalText = $link.text();
            $link.css('pointer-events', 'none').text('Accepting...');
            $row.find('.vt-reject-suggestion').css('pointer-events', 'none');

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
                        $row.find('.row-actions').find('.accept, .reject').remove();

                        // Close modal if open
                        if ($('#vt-suggestion-modal').is(':visible')) {
                            $('#vt-suggestion-modal').fadeOut(200);
                            $('body').removeClass('vt-modal-open');
                        }

                        // Show success message
                        showNotice('success', response.data);
                    } else {
                        showNotice('error', response.data || 'Failed to accept suggestion');
                        $link.css('pointer-events', 'auto').text(originalText);
                        $row.find('.vt-reject-suggestion').css('pointer-events', 'auto');
                    }
                },
                error: function(xhr, status, error) {
                    showNotice('error', 'AJAX error: ' + error);
                    $link.css('pointer-events', 'auto').text(originalText);
                    $row.find('.vt-reject-suggestion').css('pointer-events', 'auto');
                }
            });
        });

        // Reject suggestion
        $(document).on('click', '.vt-reject-suggestion', function(e) {
            e.preventDefault();

            var $link = $(this);
            var suggestionId = $link.data('suggestion-id');
            var $row = $link.closest('tr');

            if (!confirm('Are you sure you want to reject this suggestion?')) {
                return;
            }

            // Disable links
            var originalText = $link.text();
            $link.css('pointer-events', 'none').text('Rejecting...');
            $row.find('.vt-accept-suggestion').css('pointer-events', 'none');

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
                        $row.find('.row-actions').find('.accept, .reject').remove();

                        // Close modal if open
                        if ($('#vt-suggestion-modal').is(':visible')) {
                            $('#vt-suggestion-modal').fadeOut(200);
                            $('body').removeClass('vt-modal-open');
                        }

                        // Show success message
                        showNotice('success', response.data);
                    } else {
                        showNotice('error', response.data || 'Failed to reject suggestion');
                        $link.css('pointer-events', 'auto').text(originalText);
                        $row.find('.vt-accept-suggestion').css('pointer-events', 'auto');
                    }
                },
                error: function(xhr, status, error) {
                    showNotice('error', 'AJAX error: ' + error);
                    $link.css('pointer-events', 'auto').text(originalText);
                    $row.find('.vt-accept-suggestion').css('pointer-events', 'auto');
                }
            });
        });

        // Delete post (for permanently closed suggestions)
        $(document).on('click', '.vt-delete-post', function(e) {
            e.preventDefault();

            var $link = $(this);
            var suggestionId = $link.data('suggestion-id');
            var postId = $link.data('post-id');
            var $row = $link.closest('tr');

            // First confirmation
            if (!confirm('WARNING: Accepting this will PERMANENTLY DELETE the post. This action CANNOT be undone!\n\nThe post will be moved to trash and cannot be recovered.\n\nAre you absolutely sure you want to proceed?')) {
                return;
            }

            // Second confirmation
            if (!confirm('FINAL WARNING: You are about to delete this post permanently.\n\nClick OK to confirm deletion, or Cancel to stop.')) {
                return;
            }

            // Disable links
            var originalText = $link.text();
            $link.css('pointer-events', 'none').text('Deleting...');
            $row.find('.vt-reject-suggestion').css('pointer-events', 'none');

            $.ajax({
                url: vtSuggestEdits.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_delete_post_suggestion',
                    nonce: vtSuggestEdits.nonce,
                    suggestion_id: suggestionId,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        $row.addClass('status-accepted');
                        $row.find('.status-badge')
                            .removeClass('status-pending')
                            .addClass('status-accepted')
                            .text('Accepted');
                        $row.find('.row-actions').find('.delete, .reject').remove();

                        // Close modal if open
                        if ($('#vt-suggestion-modal').is(':visible')) {
                            $('#vt-suggestion-modal').fadeOut(200);
                            $('body').removeClass('vt-modal-open');
                        }

                        // Show success message
                        showNotice('success', response.data);
                    } else {
                        showNotice('error', response.data || 'Failed to delete post');
                        $link.css('pointer-events', 'auto').text(originalText);
                        $row.find('.vt-reject-suggestion').css('pointer-events', 'auto');
                    }
                },
                error: function(xhr, status, error) {
                    showNotice('error', 'AJAX error: ' + error);
                    $link.css('pointer-events', 'auto').text(originalText);
                    $row.find('.vt-reject-suggestion').css('pointer-events', 'auto');
                }
            });
        });

        // Select all checkboxes
        $('#select-all-suggestions').on('change', function() {
            $('.suggestion-checkbox').prop('checked', $(this).prop('checked'));
        });

        // Update select-all state when individual checkboxes change
        $(document).on('change', '.suggestion-checkbox', function() {
            var allChecked = $('.suggestion-checkbox:checked').length === $('.suggestion-checkbox').length;
            $('#select-all-suggestions').prop('checked', allChecked);
        });

        // Bulk action apply
        $('#bulk-action-apply').on('click', function(e) {
            e.preventDefault();

            var action = $('#bulk-action-selector').val();
            var $checkedBoxes = $('.suggestion-checkbox:checked');
            var suggestionIds = [];

            $checkedBoxes.each(function() {
                suggestionIds.push($(this).val());
            });

            if (!action) {
                alert('Please select a bulk action.');
                return;
            }

            if (suggestionIds.length === 0) {
                alert('Please select at least one suggestion.');
                return;
            }

            // Confirmation for destructive actions
            var actionLabel = $('#bulk-action-selector option:selected').text();
            if (!confirm('Are you sure you want to ' + actionLabel.toLowerCase() + ' ' + suggestionIds.length + ' suggestion(s)?')) {
                return;
            }

            // Disable button
            $('#bulk-action-apply').prop('disabled', true).text('Processing...');

            $.ajax({
                url: vtSuggestEdits.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_bulk_action_suggestions',
                    nonce: vtSuggestEdits.nonce,
                    bulk_action: action,
                    suggestion_ids: suggestionIds
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        showNotice('success', response.data);

                        // Remove or update rows based on action
                        $checkedBoxes.each(function() {
                            var suggestionId = $(this).val();
                            var $row = $(this).closest('tr');

                            if (action === 'delete') {
                                // Remove row with fade effect
                                $row.fadeOut(300, function() {
                                    $(this).remove();

                                    // Check if table is now empty
                                    if ($('.suggestion-checkbox').length === 0) {
                                        location.reload();
                                    }
                                });
                            } else if (action === 'accept') {
                                // Update row to accepted state
                                $row.addClass('status-accepted').removeClass('status-pending status-rejected');
                                $row.find('.status-badge')
                                    .removeClass('status-pending status-rejected')
                                    .addClass('status-accepted')
                                    .text('Accepted');
                                $row.find('.row-actions').find('.accept, .reject, .delete').remove();
                                $row.find('.suggestion-checkbox').prop('checked', false);
                            } else if (action === 'reject') {
                                // Update row to rejected state
                                $row.addClass('status-rejected').removeClass('status-pending status-accepted');
                                $row.find('.status-badge')
                                    .removeClass('status-pending status-accepted')
                                    .addClass('status-rejected')
                                    .text('Rejected');
                                $row.find('.row-actions').find('.accept, .reject, .delete').remove();
                                $row.find('.suggestion-checkbox').prop('checked', false);
                            }
                        });

                        // Reset bulk action controls
                        $('#bulk-action-selector').val('');
                        $('#select-all-suggestions').prop('checked', false);
                    } else {
                        showNotice('error', response.data || 'Failed to process bulk action');
                    }

                    // Re-enable button
                    $('#bulk-action-apply').prop('disabled', false).text('Apply');
                },
                error: function(xhr, status, error) {
                    showNotice('error', 'AJAX error: ' + error);
                    $('#bulk-action-apply').prop('disabled', false).text('Apply');
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
