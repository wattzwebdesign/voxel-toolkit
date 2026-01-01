/**
 * Temporary Login Admin JavaScript
 */
(function($) {
    'use strict';

    var VTTempLogin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // User type toggle
            $('#vt-user-type').on('change', this.handleUserTypeChange);

            // Form submission
            $('#vt-temp-login-form').on('submit', this.handleFormSubmit.bind(this));

            // Copy URL button
            $('#vt-copy-url').on('click', this.handleCopyUrl);

            // Table actions
            $('#vt-tokens-tbody').on('click', '.vt-copy-token-url', this.handleCopyTokenUrl);
            $('#vt-tokens-tbody').on('click', '.vt-toggle-token', this.handleToggleToken.bind(this));
            $('#vt-tokens-tbody').on('click', '.vt-delete-token', this.handleDeleteToken.bind(this));
        },

        /**
         * Handle user type change
         */
        handleUserTypeChange: function() {
            var userType = $(this).val();

            if (userType === 'new') {
                $('.vt-existing-user-field').hide();
                $('.vt-new-user-field').show();
            } else {
                $('.vt-existing-user-field').show();
                $('.vt-new-user-field').hide();
            }
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            e.preventDefault();

            var self = this;
            var $form = $(e.target);
            var $btn = $('#vt-create-btn');

            // Get form data
            var userType = $('#vt-user-type').val();
            var data = {
                action: 'vt_create_temp_login',
                nonce: vtTempLogin.nonce,
                user_type: userType,
                expiry_value: $('#vt-expiry-value').val(),
                expiry_unit: $('#vt-expiry-unit').val(),
                redirect_url: $('#vt-redirect-url').val(),
                notes: $('#vt-notes').val()
            };

            // Add user-specific data
            if (userType === 'new') {
                data.new_username = $('#vt-new-username').val();
                data.new_email = $('#vt-new-email').val();
                data.new_role = $('#vt-new-role').val();
            } else {
                data.user_id = $('#vt-user-id').val();
            }

            // Disable button
            $btn.prop('disabled', true).text(vtTempLogin.strings.creating);

            $.ajax({
                url: vtTempLogin.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        // Show the generated URL
                        $('#vt-generated-url').val(response.data.url);
                        $('#vt-generated-url-section').slideDown();

                        // Refresh the table
                        self.refreshTable();

                        // Reset form
                        self.resetForm();

                        // Show success toast
                        self.showToast(response.data.message, 'success');
                    } else {
                        self.showToast(response.data || vtTempLogin.strings.error, 'error');
                    }
                },
                error: function() {
                    self.showToast(vtTempLogin.strings.error, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(vtTempLogin.strings.create);
                }
            });
        },

        /**
         * Reset form
         */
        resetForm: function() {
            $('#vt-user-type').val('existing').trigger('change');
            $('#vt-user-id').val('');
            $('#vt-new-username').val('');
            $('#vt-new-email').val('');
            $('#vt-new-role').val('subscriber');
            $('#vt-expiry-value').val('7');
            $('#vt-expiry-unit').val('days');
            $('#vt-redirect-url').val('');
            $('#vt-notes').val('');
        },

        /**
         * Handle copy URL button click
         */
        handleCopyUrl: function() {
            var $input = $('#vt-generated-url');
            $input.select();
            document.execCommand('copy');

            VTTempLogin.showToast(vtTempLogin.strings.copied, 'success');
        },

        /**
         * Handle copy token URL from table
         */
        handleCopyTokenUrl: function(e) {
            var $btn = $(e.currentTarget);
            var token = $btn.data('token');

            if (!token) {
                VTTempLogin.showToast(vtTempLogin.strings.error, 'error');
                return;
            }

            var url = vtTempLogin.homeUrl + '/?vt_temp_login=' + token;

            // Copy to clipboard
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    VTTempLogin.showToast(vtTempLogin.strings.copied, 'success');
                }).catch(function() {
                    VTTempLogin.fallbackCopy(url);
                });
            } else {
                VTTempLogin.fallbackCopy(url);
            }
        },

        /**
         * Fallback copy method for older browsers
         */
        fallbackCopy: function(text) {
            var $temp = $('<input type="text" value="' + text + '">');
            $('body').append($temp);
            $temp.select();
            document.execCommand('copy');
            $temp.remove();
            this.showToast(vtTempLogin.strings.copied, 'success');
        },

        /**
         * Handle toggle token status
         */
        handleToggleToken: function(e) {
            var self = this;
            var $btn = $(e.currentTarget);
            var $row = $btn.closest('tr');
            var tokenId = $row.data('token-id');
            var action = $btn.data('action');

            $.ajax({
                url: vtTempLogin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_toggle_temp_login',
                    nonce: vtTempLogin.nonce,
                    token_id: tokenId,
                    toggle_action: action
                },
                success: function(response) {
                    if (response.success) {
                        self.refreshTable();
                        self.showToast(response.data.message, 'success');
                    } else {
                        self.showToast(response.data || vtTempLogin.strings.error, 'error');
                    }
                },
                error: function() {
                    self.showToast(vtTempLogin.strings.error, 'error');
                }
            });
        },

        /**
         * Handle delete token
         */
        handleDeleteToken: function(e) {
            var self = this;
            var $btn = $(e.currentTarget);
            var $row = $btn.closest('tr');
            var tokenId = $row.data('token-id');
            var isTempUser = $row.data('is-temp-user') == 1;

            var confirmMsg = isTempUser
                ? vtTempLogin.strings.confirm_delete_user
                : vtTempLogin.strings.confirm_delete;

            if (!confirm(confirmMsg)) {
                return;
            }

            $.ajax({
                url: vtTempLogin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_delete_temp_login',
                    nonce: vtTempLogin.nonce,
                    token_id: tokenId,
                    delete_user: isTempUser ? 'true' : 'false'
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            self.checkEmptyTable();
                        });
                        self.showToast(response.data.message, 'success');
                    } else {
                        self.showToast(response.data || vtTempLogin.strings.error, 'error');
                    }
                },
                error: function() {
                    self.showToast(vtTempLogin.strings.error, 'error');
                }
            });
        },

        /**
         * Refresh the tokens table
         */
        refreshTable: function() {
            var self = this;

            $.ajax({
                url: vtTempLogin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_get_temp_logins',
                    nonce: vtTempLogin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#vt-tokens-tbody').html(response.data.html);
                    }
                }
            });
        },

        /**
         * Check if table is empty and show message
         */
        checkEmptyTable: function() {
            if ($('#vt-tokens-tbody tr').length === 0) {
                $('#vt-tokens-tbody').html(
                    '<tr class="vt-no-tokens"><td colspan="7">No temporary logins created yet.</td></tr>'
                );
            }
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            type = type || 'success';

            // Remove existing toasts
            $('.vt-toast').remove();

            var $toast = $('<div class="vt-toast vt-toast-' + type + '">' + message + '</div>');
            $('body').append($toast);

            setTimeout(function() {
                $toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        VTTempLogin.init();
    });

})(jQuery);
