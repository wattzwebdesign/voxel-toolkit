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

            // Handle "Permanently Closed" checkbox
            $(document).on('change', '.vt-permanently-closed-checkbox', function() {
                var $modal = $(this).closest('.vt-suggest-modal');
                var isChecked = $(this).is(':checked');

                // Disable/enable all form inputs except the permanently closed checkbox itself
                $modal.find('input:not(.vt-permanently-closed-checkbox), textarea, select').prop('disabled', isChecked);

                // Clear values when disabling
                if (isChecked) {
                    $modal.find('input[type="text"]:not(.vt-permanently-closed-checkbox), input[type="email"], textarea').val('');
                    $modal.find('select').prop('selectedIndex', 0);
                    $modal.find('input[type="checkbox"]:not(.vt-permanently-closed-checkbox)').prop('checked', false);
                }
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

                self.openFileUploader($modal);
            });

            // Remove uploaded photo
            $(document).on('click', '.vt-remove-photo', function(e) {
                e.preventDefault();
                var $photo = $(this).closest('.vt-uploaded-photo');
                var tempId = $photo.data('temp-id');
                var $modal = $(this).closest('.vt-suggest-modal');

                // Remove from pending uploads if it's a temp file
                if (tempId) {
                    var pendingUploads = $modal.data('pending-uploads') || [];
                    pendingUploads = pendingUploads.filter(function(upload) {
                        return upload.id !== tempId;
                    });
                    $modal.data('pending-uploads', pendingUploads);
                }

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

        openFileUploader: function($modal) {
            var self = this;
            var $uploadedPhotos = $modal.find('.vt-uploaded-photos');
            var $uploadArea = $modal.find('.vt-photo-upload-area');
            var maxPhotos = 10;

            // Get current photo count
            var currentCount = $uploadedPhotos.find('.vt-uploaded-photo').length;

            if (currentCount >= maxPhotos) {
                alert('Maximum number of photos reached.');
                return;
            }

            // Create hidden file input if it doesn't exist
            var $fileInput = $uploadArea.find('.vt-file-input');
            if (!$fileInput.length) {
                $fileInput = $('<input type="file" class="vt-file-input" accept="image/*" multiple style="display:none;">');
                $uploadArea.append($fileInput);

                // Handle file selection
                $fileInput.on('change', function(e) {
                    var files = e.target.files;
                    if (!files.length) return;

                    var photosData = [];

                    // Process each file
                    Array.from(files).forEach(function(file, index) {
                        if (currentCount >= maxPhotos) {
                            return;
                        }

                        // Check if it's an image
                        if (!file.type.match('image.*')) {
                            return;
                        }

                        // Create a unique ID for this photo
                        var photoId = 'temp_' + Date.now() + '_' + index;

                        // Read the file and create preview
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            // Create preview element
                            var $photo = $('<div class="vt-uploaded-photo" data-temp-id="' + photoId + '">')
                                .append('<img src="' + e.target.result + '" alt="">')
                                .append('<button type="button" class="vt-remove-photo">&times;</button>');

                            $uploadedPhotos.append($photo);
                            currentCount++;

                            // Store the file data
                            photosData.push({
                                id: photoId,
                                file: file,
                                dataUrl: e.target.result
                            });

                            // Store in modal data for later upload
                            var existingData = $modal.data('pending-uploads') || [];
                            existingData.push({
                                id: photoId,
                                file: file,
                                dataUrl: e.target.result
                            });
                            $modal.data('pending-uploads', existingData);
                        };
                        reader.readAsDataURL(file);
                    });

                    // Reset file input
                    $fileInput.val('');
                });
            }

            // Trigger file input click
            $fileInput.click();
        },

        submitSuggestion: function($btn) {
            var $modal = $btn.closest('.vt-suggest-modal');
            var $messages = $modal.find('.vt-form-messages');
            var postId = $modal.closest('[data-post-id]').data('post-id') ||
                        $modal.attr('id').replace('vt-suggest-modal-', '');

            // Check if permanently closed is marked
            var isPermanentlyClosed = $modal.find('.vt-permanently-closed-checkbox').is(':checked');

            // Collect suggestions
            var suggestions = [];
            var hasChanges = false;

            // If permanently closed, we still have a change to report
            if (isPermanentlyClosed) {
                hasChanges = true;
            } else {
                // Only collect field suggestions if not permanently closed
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
            }

            if (!hasChanges) {
                this.showMessage($messages, vtSuggestEdits.i18n.noChanges, 'error');
                return;
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

            // Prepare form data for file upload
            var formData = new FormData();
            formData.append('action', 'vt_submit_suggestion');
            formData.append('nonce', vtSuggestEdits.nonce);
            formData.append('post_id', postId);
            formData.append('suggestions', JSON.stringify(suggestions));
            formData.append('suggester_name', suggesterName);
            formData.append('suggester_email', suggesterEmail);
            formData.append('permanently_closed', isPermanentlyClosed ? 1 : 0);

            // Add uploaded files
            var pendingUploads = $modal.data('pending-uploads') || [];
            pendingUploads.forEach(function(upload, index) {
                formData.append('proof_images[]', upload.file);
            });

            // Submit via AJAX
            $.ajax({
                url: vtSuggestEdits.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
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
                            $modal.data('pending-uploads', []);
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
