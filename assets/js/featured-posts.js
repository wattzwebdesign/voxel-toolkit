jQuery(document).ready(function($) {
    console.log('Voxel Toolkit Featured Posts JS loaded');
    console.log('voxelFeaturedPosts object:', voxelFeaturedPosts);
    
    // Handle featured post toggle
    $(document).on('click', '.toggle-featured', function(e) {
        e.preventDefault();
        console.log('Featured toggle clicked');
        
        var $link = $(this);
        var $icon = $link.find('.dashicons');
        var postId = $link.data('post-id');
        var nonce = $link.data('nonce');
        
        // Prevent multiple clicks
        if ($link.hasClass('processing')) {
            return;
        }
        
        $link.addClass('processing');
        
        // Show loading state
        $icon.removeClass('dashicons-star-filled dashicons-star-empty featured-active');
        $icon.addClass('dashicons-update-alt');
        $icon.css('animation', 'rotation 1s infinite linear');
        
        // AJAX request
        $.ajax({
            url: voxelFeaturedPosts.ajax_url,
            type: 'POST',
            data: {
                action: 'toggle_featured_post',
                post_id: postId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update icon based on new state
                    $icon.css('animation', '');
                    $icon.removeClass('dashicons-update-alt');
                    
                    if (response.data.featured) {
                        $icon.addClass('dashicons-star-filled featured-active');
                        $link.attr('title', 'Remove from featured');
                    } else {
                        $icon.addClass('dashicons-star-empty');
                        $link.attr('title', 'Make featured');
                    }
                } else {
                    // Reset to original state on error
                    $icon.css('animation', '');
                    $icon.removeClass('dashicons-update-alt');
                    $icon.addClass('dashicons-star-empty');
                    alert(response.data.message || voxelFeaturedPosts.error_message);
                }
            },
            error: function() {
                // Reset to original state on error
                $icon.css('animation', '');
                $icon.removeClass('dashicons-update-alt');
                $icon.addClass('dashicons-star-empty');
                alert(voxelFeaturedPosts.error_message);
            },
            complete: function() {
                $link.removeClass('processing');
            }
        });
    });
});