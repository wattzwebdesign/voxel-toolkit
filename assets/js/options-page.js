/**
 * Options Page - Media Library Integration
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Media uploader for image fields
        var mediaUploader;

        $('.voxel-toolkit-select-image').on('click', function(e) {
            e.preventDefault();

            var button = $(this);
            var container = button.closest('.voxel-toolkit-image-field');
            var input = container.find('.voxel-toolkit-image-id');
            var preview = container.find('.voxel-toolkit-image-preview');

            // If the uploader object has already been created, reopen the dialog
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            // Create the media uploader
            mediaUploader = wp.media({
                title: 'Select Image',
                button: {
                    text: 'Use This Image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            // When an image is selected, run a callback
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();

                // Set the image ID
                input.val(attachment.id);

                // Show preview
                var imgUrl = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                preview.html('<img src="' + imgUrl + '" style="max-width: 150px; height: auto; display: block;" />');

                // Update button text
                button.text('Change Image');

                // Add remove button if it doesn't exist
                if (!container.find('.voxel-toolkit-remove-image').length) {
                    button.after('<button type="button" class="button voxel-toolkit-remove-image">Remove</button>');
                }
            });

            // Open the uploader dialog
            mediaUploader.open();
        });

        // Remove image
        $(document).on('click', '.voxel-toolkit-remove-image', function(e) {
            e.preventDefault();

            var button = $(this);
            var container = button.closest('.voxel-toolkit-image-field');
            var input = container.find('.voxel-toolkit-image-id');
            var preview = container.find('.voxel-toolkit-image-preview');
            var selectButton = container.find('.voxel-toolkit-select-image');

            // Clear the image ID
            input.val('');

            // Clear preview
            preview.html('');

            // Update button text
            selectButton.text('Select Image');

            // Remove the remove button
            button.remove();
        });
    });

})(jQuery);
