/**
 * Synonym Search Admin JS
 *
 * Handles AI synonym generation on taxonomy term edit pages.
 *
 * @package Voxel_Toolkit
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle AI generate synonyms button click
        $(document).on('click', '.vt-generate-synonyms-btn', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $wrapper = $button.closest('.vt-ai-synonyms-wrapper');
            var $spinner = $wrapper.find('.spinner');
            var $status = $wrapper.find('.vt-ai-status');
            var $textarea = $('#vt_synonyms');

            // Get term name - either from data attribute (edit form) or from input (add form)
            var termName = $button.data('term-name');
            if (!termName) {
                // Try to get from the name input field (add new term form)
                termName = $('#tag-name').val() || $('input[name="tag-name"]').val();
            }

            if (!termName) {
                $status.text(vtSynonymSearch.strings.noTermName).css('color', '#dc3232');
                return;
            }

            var termId = $button.data('term-id') || 0;
            var count = $button.data('count') || 5;
            var existing = $textarea.val();

            // Show loading state
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $status.text(vtSynonymSearch.strings.generating).css('color', '#666');

            // Make AJAX request
            $.ajax({
                url: vtSynonymSearch.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_generate_synonyms',
                    nonce: vtSynonymSearch.nonce,
                    term_name: termName,
                    term_id: termId,
                    count: count,
                    existing: existing
                },
                success: function(response) {
                    if (response.success) {
                        $textarea.val(response.data.synonyms);
                        $status.text(vtSynonymSearch.strings.generated).css('color', '#46b450');

                        // Clear success message after 3 seconds
                        setTimeout(function() {
                            $status.text('');
                        }, 3000);
                    } else {
                        $status.text(response.data.message || vtSynonymSearch.strings.error).css('color', '#dc3232');
                    }
                },
                error: function() {
                    $status.text(vtSynonymSearch.strings.error).css('color', '#dc3232');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });
    });

})(jQuery);
