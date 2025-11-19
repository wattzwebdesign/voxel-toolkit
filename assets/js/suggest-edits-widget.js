/**
 * Suggest Edits Widget JavaScript
 */
(function($) {
    'use strict';

    var SuggestEditsWidget = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Open modal on button click
            $(document).on('click', '.vt-suggest-edit-btn', function(e) {
                e.preventDefault();
                var postId = $(this).data('post-id');
                $('#vt-suggest-modal-' + postId).fadeIn(200);
                $('body').addClass('vt-modal-open');
            });

            // Close modal
            $(document).on('click', '.vt-modal-close, .vt-modal-cancel, .vt-suggest-modal-overlay', function(e) {
                e.preventDefault();
                $(this).closest('.vt-suggest-modal').fadeOut(200);
                $('body').removeClass('vt-modal-open');
            });

            // Prevent modal close when clicking inside modal content
            $(document).on('click', '.vt-suggest-modal-content', function(e) {
                e.stopPropagation();
            });

            // Handle "Don't know" checkbox
            $(document).on('change', '.vt-incorrect-checkbox', function() {
                var fieldKey = $(this).data('field-key');
                var $input = $('.vt-suggestion-input[data-field-key="' + fieldKey + '"]');

                if ($(this).is(':checked')) {
                    $input.prop('disabled', true).val('');
                } else {
                    $input.prop('disabled', false);
                }
            });

            // Photo upload
            $(document).on('click', '.vt-upload-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $modal = $btn.closest('.vt-suggest-modal');

                self.openMediaUploader($modal);
            });

            // Remove uploaded photo
            $(document).on('click', '.vt-remove-photo', function(e) {
                e.preventDefault();
                var $photo = $(this).closest('.vt-uploaded-photo');
                var imageId = $photo.data('image-id');
                var $modal = $(this).closest('.vt-suggest-modal');
                var $hiddenInput = $modal.find('.vt-photo-ids');

                // Remove from array
                var photoIds = $hiddenInput.val() ? $hiddenInput.val().split(',') : [];
                photoIds = photoIds.filter(function(id) {
                    return id != imageId;
                });
                $hiddenInput.val(photoIds.join(','));

                $photo.remove();
            });

            // Submit suggestion
            $(document).on('click', '.vt-modal-submit', function(e) {
                e.preventDefault();
                self.submitSuggestion($(this));
            });

            // ESC key to close modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('.vt-suggest-modal:visible').length) {
                    $('.vt-suggest-modal:visible').fadeOut(200);
                    $('body').removeClass('vt-modal-open');
                }
            });
        },

        openMediaUploader: function($modal) {
            var self = this;
            var $uploadedPhotos = $modal.find('.vt-uploaded-photos');
            var $hiddenInput = $modal.find('.vt-photo-ids');
            var maxPhotos = 10; // Can be made dynamic from widget settings

            // Get current photo count
            var currentCount = $uploadedPhotos.find('.vt-uploaded-photo').length;

            if (currentCount >= maxPhotos) {
                alert('Maximum number of photos reached.');
                return;
            }

            // Create media uploader
            var frame = wp.media({
                title: 'Select Proof Images',
                multiple: true,
                library: {
                    type: 'image'
                },
                button: {
                    text: 'Add Photos'
                }
            });

            frame.on('select', function() {
                var selection = frame.state().get('selection');
                var photoIds = $hiddenInput.val() ? $hiddenInput.val().split(',') : [];

                selection.each(function(attachment) {
                    attachment = attachment.toJSON();

                    if (currentCount >= maxPhotos) {
                        return false;
                    }

                    // Add to hidden input
                    photoIds.push(attachment.id);

                    // Add preview
                    var $photo = $('<div class="vt-uploaded-photo" data-image-id="' + attachment.id + '">')
                        .append('<img src="' + attachment.sizes.thumbnail.url + '" alt="">')
                        .append('<button type="button" class="vt-remove-photo">&times;</button>');

                    $uploadedPhotos.append($photo);
                    currentCount++;
                });

                $hiddenInput.val(photoIds.join(','));
            });

            frame.open();
        },

        submitSuggestion: function($btn) {
            var $modal = $btn.closest('.vt-suggest-modal');
            var $messages = $modal.find('.vt-form-messages');
            var postId = $modal.closest('[data-post-id]').data('post-id') ||
                        $modal.attr('id').replace('vt-suggest-modal-', '');

            // Collect suggestions
            var suggestions = [];
            var hasChanges = false;

            $modal.find('.vt-field-item').each(function() {
                var $item = $(this);
                var fieldKey = $item.data('field-key');
                var $input = $item.find('.vt-suggestion-input');
                var $checkbox = $item.find('.vt-incorrect-checkbox');
                var suggestedValue = '';
                var isIncorrect = $checkbox.is(':checked');

                // Handle multiple select (returns array)
                if ($input.is('select[multiple]')) {
                    var selectedValues = $input.val();
                    if (selectedValues && selectedValues.length > 0) {
                        suggestedValue = selectedValues.join(',');
                    }
                } else {
                    suggestedValue = $input.val() ? $input.val().trim() : '';
                }

                if (suggestedValue || isIncorrect) {
                    hasChanges = true;
                    suggestions.push({
                        field_key: fieldKey,
                        suggested_value: suggestedValue,
                        is_incorrect: isIncorrect ? 1 : 0
                    });
                }
            });

            if (!hasChanges) {
                this.showMessage($messages, vtSuggestEdits.i18n.noChanges, 'error');
                return;
            }

            // Get proof images
            var proofImages = $modal.find('.vt-photo-ids').val();
            if (proofImages) {
                var imageIds = proofImages.split(',');
                suggestions.forEach(function(suggestion) {
                    suggestion.proof_images = imageIds;
                });
            }

            // Get guest info if not logged in
            var suggesterName = $modal.find('input[name="suggester_name"]').val();
            var suggesterEmail = $modal.find('input[name="suggester_email"]').val();

            if ($modal.find('input[name="suggester_email"]').length && !suggesterEmail) {
                this.showMessage($messages, vtSuggestEdits.i18n.emailRequired, 'error');
                return;
            }

            // Disable submit button
            $btn.prop('disabled', true).text('Submitting...');

            // Submit via AJAX
            $.ajax({
                url: vtSuggestEdits.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_submit_suggestion',
                    nonce: vtSuggestEdits.nonce,
                    post_id: postId,
                    suggestions: suggestions,
                    suggester_name: suggesterName,
                    suggester_email: suggesterEmail
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        this.showMessage($messages, response.data.message || vtSuggestEdits.i18n.submitSuccess, 'success');

                        // Reset form
                        setTimeout(function() {
                            $modal.find('input[type="text"], input[type="email"], textarea').val('');
                            $modal.find('select').prop('selectedIndex', 0);
                            $modal.find('input[type="checkbox"]').prop('checked', false);
                            $modal.find('.vt-uploaded-photos').empty();
                            $modal.find('.vt-photo-ids').val('');
                            $modal.fadeOut(200);
                            $('body').removeClass('vt-modal-open');
                        }, 2000);
                    } else {
                        this.showMessage($messages, response.data || vtSuggestEdits.i18n.submitError, 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showMessage($messages, vtSuggestEdits.i18n.submitError, 'error');
                }.bind(this),
                complete: function() {
                    $btn.prop('disabled', false).text($btn.data('original-text') || 'Submit');
                }
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
        SuggestEditsWidget.init();
    });

})(jQuery);
