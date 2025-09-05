jQuery(document).ready(function($) {
    // Initialize Select2 for user search
    $('#vt-user-select').select2({
        ajax: {
            url: vt_admin_notifications.ajax_url,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        escapeMarkup: function(markup) {
            return markup;
        },
        minimumInputLength: 3,
        placeholder: 'Search for users by name or email...',
        allowClear: true,
        templateResult: function(user) {
            if (user.loading) {
                return 'Searching...';
            }
            return user.text;
        },
        templateSelection: function(user) {
            return user.text || user.id;
        }
    });

    // Add some styling improvements
    $('.vt-role-list input[type="checkbox"]').on('change', function() {
        var $li = $(this).closest('li');
        if (this.checked) {
            $li.addClass('selected');
        } else {
            $li.removeClass('selected');
        }
    });

    // Initialize already selected checkboxes
    $('.vt-role-list input[type="checkbox"]:checked').each(function() {
        $(this).closest('li').addClass('selected');
    });
});

// Add CSS for selected state
$('<style>')
    .prop('type', 'text/css')
    .html(`
        .vt-role-list li.selected {
            background: #e7f3ff !important;
            border-color: #2271b1 !important;
        }
        .vt-role-list li.selected label {
            font-weight: 600;
            color: #2271b1;
        }
    `)
    .appendTo('head');