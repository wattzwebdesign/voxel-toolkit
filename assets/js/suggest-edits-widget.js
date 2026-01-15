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
                var $modal = $('#vt-suggest-modal-' + postId);

                // Move modal to body to escape any parent stacking contexts
                if (!$modal.parent().is('body')) {
                    $modal.appendTo('body');
                }

                $modal.fadeIn(200);
                $('body').addClass('vt-modal-open');

                // Initialize location autocomplete for any location fields
                self.initLocationAutocomplete($modal);
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

            // Prevent clicks on autocomplete dropdown from closing modal
            $(document).on('click', '.ts-autocomplete-dropdown', function(e) {
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

            // Work Hours: Open popup
            $(document).on('click', '.vt-wh-suggest-btn', function(e) {
                e.preventDefault();
                self.openWorkHoursPopup($(this));
            });

            // Work Hours: Close popup
            $(document).on('click', '.vt-wh-popup-close, .vt-wh-popup-cancel, .vt-wh-popup-overlay', function(e) {
                e.preventDefault();
                $('.vt-wh-popup').fadeOut(200);
            });

            // Work Hours: Prevent popup close when clicking inside
            $(document).on('click', '.vt-wh-popup-content', function(e) {
                e.stopPropagation();
            });

            // Work Hours: Save and close popup
            $(document).on('click', '.vt-wh-popup-save', function(e) {
                e.preventDefault();
                var $popup = $(this).closest('.vt-wh-popup');
                var fieldKey = $popup.data('field-key');

                // Mark this field as having changes
                var $modal = $popup.closest('.vt-suggest-modal');
                var $fieldItem = $modal.find('.vt-field-item[data-field-key="' + fieldKey + '"]');
                $fieldItem.data('wh-changed', true);

                $popup.fadeOut(200);
            });

            // Work Hours: Add schedule group
            $(document).on('click', '.vt-wh-add-group', function(e) {
                e.preventDefault();
                self.addWorkHoursGroup($(this));
            });

            // Work Hours: Remove schedule group
            $(document).on('click', '.vt-wh-remove-group', function(e) {
                e.preventDefault();
                $(this).closest('.vt-wh-group').remove();
            });

            // Work Hours: Toggle day selection
            $(document).on('click', '.vt-wh-day-btn', function(e) {
                e.preventDefault();
                $(this).toggleClass('active');
            });

            // Work Hours: Add time slot
            $(document).on('click', '.vt-wh-add-time', function(e) {
                e.preventDefault();
                self.addWorkHoursTimeSlot($(this));
            });

            // Work Hours: Remove time slot
            $(document).on('click', '.vt-wh-remove-time', function(e) {
                e.preventDefault();
                $(this).closest('.vt-wh-time-slot').remove();
            });

            // Work Hours: Status change
            $(document).on('change', '.vt-wh-status', function() {
                var $group = $(this).closest('.vt-wh-group');
                var status = $(this).val();
                var $hoursContainer = $group.find('.vt-wh-hours-container');

                if (status === 'hours') {
                    $hoursContainer.show();
                } else {
                    $hoursContainer.hide();
                }
            });

            // Frontend: Accept suggestion button
            $(document).on('click', '.vt-accept-btn', function(e) {
                e.preventDefault();
                self.handleFrontendAccept($(this));
            });

            // Frontend: Reject suggestion button
            $(document).on('click', '.vt-reject-btn', function(e) {
                e.preventDefault();
                self.handleFrontendReject($(this));
            });

            // Multicheck: Toggle item selection
            $(document).on('click', '.vt-multicheck__item', function(e) {
                e.preventDefault();
                var $item = $(this);
                var $container = $item.closest('.vt-multicheck');
                var $hiddenSelect = $container.siblings('.vt-multicheck__select');
                var value = $item.data('value');

                // Toggle visual state
                $item.toggleClass('vt-multicheck__item--checked');

                // Update hidden select to keep in sync
                var $option = $hiddenSelect.find('option[value="' + value + '"]');
                if ($item.hasClass('vt-multicheck__item--checked')) {
                    $option.prop('selected', true);
                } else {
                    $option.prop('selected', false);
                }

                // Trigger change for any listeners
                $hiddenSelect.trigger('change');
            });
        },

        openWorkHoursPopup: function($btn) {
            var $modal = $btn.closest('.vt-suggest-modal');
            var $popup = $modal.find('.vt-wh-popup');
            var $fieldItem = $btn.closest('.vt-field-item');
            var fieldKey = $fieldItem.data('field-key');

            // Get current schedule data
            var $dataContainer = $fieldItem.find('.vt-wh-data-container');
            var scheduleData = $dataContainer.data('schedule');

            // Clear existing groups
            var $groups = $popup.find('.vt-wh-groups');
            $groups.empty();

            // Store field key for later
            $popup.data('field-key', fieldKey);

            // Load schedule data into popup
            if (scheduleData && scheduleData.length > 0) {
                var self = this;
                scheduleData.forEach(function(group) {
                    self.addWorkHoursGroup($popup.find('.vt-wh-add-group'), group);
                });
            } else {
                // Add one empty group to start
                this.addWorkHoursGroup($popup.find('.vt-wh-add-group'));
            }

            // Show popup
            $popup.fadeIn(200);
        },

        addWorkHoursGroup: function($btn, groupData) {
            var $container = $btn.closest('.vt-wh-editor').find('.vt-wh-groups');

            // Default group data if not provided
            groupData = groupData || {
                days: [],
                status: 'hours',
                hours: [{from: '09:00', to: '17:00'}]
            };

            // Build days buttons HTML with active states
            var days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
            var dayLabels = {mon: 'Mon', tue: 'Tue', wed: 'Wed', thu: 'Thu', fri: 'Fri', sat: 'Sat', sun: 'Sun'};
            var daysHtml = '';
            days.forEach(function(day) {
                var isActive = groupData.days && groupData.days.indexOf(day) !== -1 ? ' active' : '';
                daysHtml += '<button type="button" class="vt-wh-day-btn' + isActive + '" data-day="' + day + '">' + dayLabels[day] + '</button>';
            });

            var groupHtml = '<div class="vt-wh-group">' +
                '<div class="vt-wh-group-header">' +
                    '<div class="vt-wh-days">' + daysHtml + '</div>' +
                    '<select class="vt-wh-status">' +
                        '<option value="hours"' + (groupData.status === 'hours' ? ' selected' : '') + '>Specific hours</option>' +
                        '<option value="open"' + (groupData.status === 'open' ? ' selected' : '') + '>Open 24 hours</option>' +
                        '<option value="closed"' + (groupData.status === 'closed' ? ' selected' : '') + '>Closed</option>' +
                        '<option value="appointments_only"' + (groupData.status === 'appointments_only' ? ' selected' : '') + '>Appointments only</option>' +
                    '</select>' +
                    '<button type="button" class="vt-wh-remove-group">×</button>' +
                '</div>' +
                '<div class="vt-wh-hours-container" style="' + (groupData.status !== 'hours' ? 'display: none;' : '') + '">' +
                    '<div class="vt-wh-time-slots"></div>' +
                    '<button type="button" class="vt-wh-add-time">+ Add hours</button>' +
                '</div>' +
            '</div>';

            $container.append(groupHtml);

            // Add time slots if status is 'hours'
            if (groupData.status === 'hours' && groupData.hours && groupData.hours.length > 0) {
                var self = this;
                var $group = $container.find('.vt-wh-group:last');
                groupData.hours.forEach(function(timeSlot) {
                    self.addWorkHoursTimeSlot($group.find('.vt-wh-add-time'), timeSlot);
                });
            }
        },

        addWorkHoursTimeSlot: function($btn, timeSlotData) {
            var $container = $btn.closest('.vt-wh-hours-container').find('.vt-wh-time-slots');

            // Default time slot if not provided
            timeSlotData = timeSlotData || {from: '09:00', to: '17:00'};

            var timeSlotHtml = '<div class="vt-wh-time-slot">' +
                '<input type="time" class="vt-wh-time-from" value="' + timeSlotData.from + '">' +
                '<span>to</span>' +
                '<input type="time" class="vt-wh-time-to" value="' + timeSlotData.to + '">' +
                '<button type="button" class="vt-wh-remove-time">×</button>' +
            '</div>';

            $container.append(timeSlotHtml);
        },

        serializeWorkHours: function($container) {
            // $container can be either .vt-field-item or .vt-wh-popup
            var schedule = [];
            var $modal = $container.closest('.vt-suggest-modal');
            var $popup = $modal.find('.vt-wh-popup');
            var $groups = $popup.find('.vt-wh-group');

            $groups.each(function() {
                var $group = $(this);
                var days = [];

                // Get selected days
                $group.find('.vt-wh-day-btn.active').each(function() {
                    days.push($(this).data('day'));
                });

                if (days.length === 0) return; // Skip groups with no days selected

                var status = $group.find('.vt-wh-status').val();
                var hours = [];

                // Get time slots if status is 'hours'
                if (status === 'hours') {
                    $group.find('.vt-wh-time-slot').each(function() {
                        var from = $(this).find('.vt-wh-time-from').val();
                        var to = $(this).find('.vt-wh-time-to').val();

                        if (from && to) {
                            hours.push({
                                from: from,
                                to: to
                            });
                        }
                    });
                }

                schedule.push({
                    days: days,
                    status: status,
                    hours: hours
                });
            });

            return JSON.stringify(schedule);
        },

        serializeLocation: function($item) {
            var $input = $item.find('.vt-suggestion-input');

            // Get location data from data attributes (set by autocomplete)
            var address = $input.data('location-address') || $input.val().trim();
            var latitude = $input.data('location-latitude') || null;
            var longitude = $input.data('location-longitude') || null;

            // Only return JSON if we have an address
            if (!address) {
                return '';
            }

            var location = {
                address: address,
                map_picker: false,
                latitude: latitude,
                longitude: longitude
            };

            return JSON.stringify(location);
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
            var self = this;
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

                    // Handle work hours field
                    if ($item.hasClass('vt-work-hours-field')) {
                        // Only serialize if user clicked "Save Hours"
                        if ($item.data('wh-changed')) {
                            suggestedValue = self.serializeWorkHours($item);
                        }
                    }
                    // Handle location field
                    else if ($item.data('field-type') === 'location') {
                        suggestedValue = self.serializeLocation($item);
                    }
                    // Handle multiple select (returns array)
                    else if ($input.is('select[multiple]')) {
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

            // Show progress bar if files are being uploaded
            var pendingUploads = $modal.data('pending-uploads') || [];
            var $progressContainer = $modal.find('.vt-upload-progress');
            var $progressText = $modal.find('.vt-upload-progress-text');

            if (pendingUploads.length > 0) {
                $progressContainer.show();
                $progressText.text('Uploading...');
            }

            // Track timing for debugging
            var startTime = Date.now();

            // Prepare form data for file upload
            var formData = new FormData();
            formData.append('action', 'vt_submit_suggestion');
            formData.append('nonce', vtSuggestEdits.nonce);
            formData.append('post_id', postId);
            formData.append('suggestions', JSON.stringify(suggestions));
            formData.append('suggester_name', suggesterName);
            formData.append('suggester_email', suggesterEmail);
            formData.append('permanently_closed', isPermanentlyClosed ? 1 : 0);

            // Get suggester comment if field exists
            var suggesterComment = $modal.find('.vt-comment-textarea').val() || '';
            formData.append('suggester_comment', suggesterComment);

            // Add uploaded files
            console.log('VT Frontend: Adding ' + pendingUploads.length + ' files to form data');
            pendingUploads.forEach(function(upload, index) {
                console.log('VT Frontend: Adding file ' + index + ':', upload.file.name, upload.file.size + ' bytes');
                formData.append('proof_images[]', upload.file);
            });

            // Debug: log all form data keys
            console.log('VT Frontend: FormData keys:');
            for (var pair of formData.entries()) {
                if (pair[1] instanceof File) {
                    console.log('  ' + pair[0] + ': File - ' + pair[1].name);
                } else {
                    console.log('  ' + pair[0] + ': ' + (typeof pair[1] === 'string' && pair[1].length > 50 ? pair[1].substring(0, 50) + '...' : pair[1]));
                }
            }

            // Submit via AJAX
            $.ajax({
                url: vtSuggestEdits.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();

                    // When upload completes, show processing message
                    xhr.upload.addEventListener('load', function() {
                        if (pendingUploads.length > 0) {
                            $progressText.text('Processing images on server...');
                        }
                    }, false);

                    return xhr;
                },
                success: function(response) {
                    var totalTime = ((Date.now() - startTime) / 1000).toFixed(2);
                    console.log('Suggestion submission response:', response);
                    console.log('Total submission time: ' + totalTime + ' seconds');
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
                            // Reset multicheck items
                            $modal.find('.vt-multicheck__item').removeClass('vt-multicheck__item--checked');
                            $modal.find('.vt-multicheck__select option').prop('selected', false);
                            $modal.fadeOut(200);
                            $('body').removeClass('vt-modal-open');
                        }, 2000);
                    } else {
                        console.error('Submission failed:', response);
                        this.showMessage($messages, response.data || vtSuggestEdits.i18n.submitError, 'error');
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    console.error('AJAX error:', xhr, status, error);
                    this.showMessage($messages, vtSuggestEdits.i18n.submitError + ' (' + error + ')', 'error');
                }.bind(this),
                complete: function() {
                    $btn.prop('disabled', false).text($btn.data('original-text') || 'Submit');
                    $progressContainer.hide();
                }
            });
        },

        handleFrontendAccept: function($btn) {
            var suggestionId = $btn.data('suggestion-id');
            var $item = $btn.closest('.vt-suggestion-item');
            var $list = $item.closest('.vt-suggestions-list');

            // Check if this is a permanently closed suggestion
            var fieldLabel = $item.find('.vt-suggestion-header strong').first().text().trim();
            var isPermanentlyClosed = (fieldLabel === 'Permanently Closed?');

            // Get custom messages from widget settings (or use defaults)
            var confirmAccept = $list.data('confirm-accept') || 'Are you sure you want to accept this suggestion?';
            var confirmDeleteFirst = $list.data('confirm-delete-first') || 'WARNING: Accepting this will PERMANENTLY DELETE the post. This action CANNOT be undone!\n\nThe post will be moved to trash and cannot be recovered.\n\nAre you absolutely sure you want to proceed?';
            var confirmDeleteSecond = $list.data('confirm-delete-second') || 'FINAL WARNING: You are about to delete this post permanently.\n\nClick OK to confirm deletion, or Cancel to stop.';

            // Show appropriate confirmation
            var confirmMessage = isPermanentlyClosed ? confirmDeleteFirst : confirmAccept;

            if (!confirm(confirmMessage)) {
                return;
            }

            // For permanently closed, add a second confirmation
            if (isPermanentlyClosed) {
                if (!confirm(confirmDeleteSecond)) {
                    return;
                }
            }

            // Disable buttons
            $btn.prop('disabled', true).text('Accepting...');
            $item.find('.vt-reject-btn').prop('disabled', true);

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
                        $item.addClass('vt-status-accepted').removeClass('vt-status-pending vt-status-queued');
                        $item.find('.vt-status-badge')
                            .removeClass('vt-status-pending vt-status-queued')
                            .addClass('vt-status-accepted')
                            .text('Accepted');
                        $item.find('.vt-suggestion-actions').remove();

                        // Show a brief success message
                        var $message = $('<div class="vt-inline-success">Change applied successfully!</div>');
                        $item.find('.vt-suggestion-body').append($message);
                        setTimeout(function() {
                            $message.fadeOut(function() { $(this).remove(); });
                        }, 3000);
                    } else {
                        alert(response.data || 'Failed to accept suggestion');
                        $btn.prop('disabled', false).text('Accept');
                        $item.find('.vt-reject-btn').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                    $btn.prop('disabled', false).text('Accept');
                    $item.find('.vt-reject-btn').prop('disabled', false);
                }
            });
        },

        handleFrontendReject: function($btn) {
            var suggestionId = $btn.data('suggestion-id');
            var $item = $btn.closest('.vt-suggestion-item');
            var $list = $item.closest('.vt-suggestions-list');

            // Get custom reject message from widget settings (or use default)
            var confirmReject = $list.data('confirm-reject') || 'Are you sure you want to reject this suggestion?';

            if (!confirm(confirmReject)) {
                return;
            }

            // Disable buttons
            $btn.prop('disabled', true).text('Rejecting...');
            $item.find('.vt-accept-btn').prop('disabled', true);

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
                        $item.addClass('vt-status-rejected').removeClass('vt-status-pending vt-status-queued');
                        $item.find('.vt-status-badge')
                            .removeClass('vt-status-pending vt-status-queued')
                            .addClass('vt-status-rejected')
                            .text('Rejected');
                        $item.find('.vt-suggestion-actions').remove();

                        // Show a brief success message
                        var $message = $('<div class="vt-inline-success">Suggestion rejected</div>');
                        $item.find('.vt-suggestion-body').append($message);
                        setTimeout(function() {
                            $message.fadeOut(function() { $(this).remove(); });
                        }, 3000);
                    } else {
                        alert(response.data || 'Failed to reject suggestion');
                        $btn.prop('disabled', false).text('Reject');
                        $item.find('.vt-accept-btn').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                    $btn.prop('disabled', false).text('Reject');
                    $item.find('.vt-accept-btn').prop('disabled', false);
                }
            });
        },

        initLocationAutocomplete: function($modal) {
            console.log('VT: initLocationAutocomplete called');

            // Check if Voxel Maps is available
            if (typeof Voxel === 'undefined' || typeof Voxel.Maps === 'undefined') {
                console.log('VT: Voxel Maps not available', typeof Voxel, typeof Voxel?.Maps);
                return;
            }

            console.log('VT: Voxel.Maps is available');

            // Find all location field suggested value inputs in the modal
            var $locationInputs = $modal.find('.vt-field-item[data-field-type="location"] .vt-suggestion-input');
            console.log('VT: Found location inputs:', $locationInputs.length);

            $locationInputs.each(function() {
                var $input = $(this);
                var fieldKey = $input.closest('.vt-field-item').data('field-key');

                console.log('VT: Processing location input for field:', fieldKey);

                // Skip if already initialized
                if ($input.hasClass('vt-autocomplete-initialized')) {
                    console.log('VT: Input already initialized, skipping');
                    return;
                }

                console.log('VT: Waiting for Voxel.Maps library...');

                // Wait for maps library to load
                Voxel.Maps.await(function() {
                    console.log('VT: Maps library loaded, initializing autocomplete');

                    try {
                        // Get autocomplete config from Voxel
                        var autocompleteOptions = {};
                        if (typeof Voxel_Config !== 'undefined' && Voxel_Config.maps && Voxel_Config.maps.autocomplete) {
                            autocompleteOptions = Voxel_Config.maps.autocomplete;
                        }

                        console.log('VT: Autocomplete options:', autocompleteOptions);

                        // Initialize autocomplete
                        new Voxel.Maps.Autocomplete(
                            $input[0],  // Native DOM element
                            function(place) {
                                console.log('VT: Autocomplete callback', place);
                                if (place) {
                                    // Store location data in data attributes
                                    $input.data('location-address', place.address);
                                    $input.data('location-latitude', place.latlng.getLatitude());
                                    $input.data('location-longitude', place.latlng.getLongitude());
                                    $input.val(place.address);
                                    console.log('VT: Stored location data:', {
                                        address: place.address,
                                        lat: place.latlng.getLatitude(),
                                        lng: place.latlng.getLongitude()
                                    });
                                } else {
                                    // User typed manually without selecting from dropdown
                                    $input.data('location-address', $input.val());
                                    $input.data('location-latitude', null);
                                    $input.data('location-longitude', null);
                                    console.log('VT: Manual entry (no place selected)');
                                }
                            },
                            autocompleteOptions
                        );

                        $input.addClass('vt-autocomplete-initialized');
                        console.log('VT: Autocomplete initialized successfully');

                        // Debug: Check if dropdown was created
                        setTimeout(function() {
                            var $dropdown = jQuery('.ts-autocomplete-dropdown');
                            console.log('VT: Dropdown elements found:', $dropdown.length);
                            if ($dropdown.length > 0) {
                                console.log('VT: Dropdown exists. CSS:', {
                                    display: $dropdown.css('display'),
                                    visibility: $dropdown.css('visibility'),
                                    zIndex: $dropdown.css('z-index'),
                                    position: $dropdown.css('position')
                                });
                            }

                            // Test input trigger
                            console.log('VT: Testing input trigger...');
                            $input.trigger('input');
                        }, 500);
                    } catch (error) {
                        console.error('VT: Error initializing autocomplete:', error);
                    }
                });
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
