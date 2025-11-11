/**
 * Article Helpful Widget JavaScript
 *
 * Handles AJAX voting for the "Was this Article Helpful?" widget
 *
 * @package Voxel_Toolkit
 * @since 1.4.0
 */

(function($) {
    'use strict';

    /**
     * Handle article helpful vote submission
     */
    $(document).on('click', '.voxel-helpful-btn', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $wrapper = $button.closest('.voxel-article-helpful-wrapper');
        var $content = $wrapper.find('.voxel-article-helpful-content');
        var $success = $wrapper.find('.voxel-helpful-success');
        var $allButtons = $wrapper.find('.voxel-helpful-btn');
        var postId = $wrapper.data('post-id');
        var voteType = $button.data('vote');

        // Disable buttons to prevent double-clicking
        $allButtons.prop('disabled', true).css('opacity', '0.6');

        // Send AJAX request
        $.ajax({
            url: voxelArticleHelpful.ajaxUrl,
            type: 'POST',
            data: {
                action: 'voxel_article_helpful_vote',
                nonce: voxelArticleHelpful.nonce,
                post_id: postId,
                vote_type: voteType
            },
            success: function(response) {
                if (response.success) {
                    // Remove active class from all buttons
                    $allButtons.removeClass('active');

                    // Add active class to clicked button
                    $button.addClass('active');

                    // Update wrapper data attribute
                    $wrapper.attr('data-user-vote', response.data.vote_type);

                    // Show success message temporarily
                    var originalText = $success.text();
                    $success.text(response.data.message).fadeIn(300);

                    setTimeout(function() {
                        $success.fadeOut(300, function() {
                            $success.text(originalText);
                        });
                    }, 2000);

                    // Set cookie manually for guest users (backup, since PHP also sets it)
                    setCookie('voxel_helpful_' + postId, voteType, 30);

                    // Re-enable buttons
                    $allButtons.prop('disabled', false).css('opacity', '1');
                } else {
                    // Show error message
                    alert(response.data.message || 'An error occurred. Please try again.');

                    // Re-enable buttons
                    $allButtons.prop('disabled', false).css('opacity', '1');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                alert('An error occurred. Please try again.');

                // Re-enable buttons
                $allButtons.prop('disabled', false).css('opacity', '1');
            }
        });
    });

    /**
     * Set a cookie
     *
     * @param {string} name Cookie name
     * @param {string} value Cookie value
     * @param {number} days Days until expiration
     */
    function setCookie(name, value, days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
    }

})(jQuery);
