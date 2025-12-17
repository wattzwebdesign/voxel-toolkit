/**
 * Enhanced TinyMCE Editor - Media Button
 *
 * Adds a media upload button to Voxel's TinyMCE editor that opens
 * the WordPress media library.
 *
 * Security: This script is only loaded for users with upload_files capability.
 * The server-side also validates capability before adding the button to the toolbar.
 *
 * @package Voxel_Toolkit
 */
(function() {
    'use strict';

    // Wait for TinyMCE to be available
    if (typeof tinymce === 'undefined') {
        return;
    }

    // Security check: Verify user has upload capability (passed from PHP)
    if (typeof vtEnhancedEditor === 'undefined' || !vtEnhancedEditor.canUpload) {
        return;
    }

    // Register the custom plugin
    tinymce.PluginManager.add('vt_media', function(editor) {
        // Add the media button
        editor.addButton('vt_media', {
            title: 'Add Media',
            icon: 'image',
            onclick: function() {
                // Check if wp.media is available
                if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                    console.error('VT Enhanced Editor: wp.media is not available');
                    return;
                }

                // Create the media frame
                var mediaFrame = wp.media({
                    title: 'Select or Upload Media',
                    button: {
                        text: 'Insert into editor'
                    },
                    multiple: false
                });

                // When media is selected
                mediaFrame.on('select', function() {
                    var attachment = mediaFrame.state().get('selection').first().toJSON();
                    var html = '';

                    // Build HTML based on media type
                    if (attachment.type === 'image') {
                        // Use medium size if available, otherwise full
                        var url = attachment.sizes && attachment.sizes.medium
                            ? attachment.sizes.medium.url
                            : attachment.url;
                        html = '<img src="' + url + '" alt="' + (attachment.alt || attachment.title || '') + '" />';
                    } else if (attachment.type === 'video') {
                        // Use HTML5 video tag with src attribute directly
                        html = '<video controls src="' + attachment.url + '" style="max-width: 100%; height: auto;"></video>';
                    } else if (attachment.type === 'audio') {
                        // Use HTML5 audio tag with src attribute directly
                        html = '<audio controls src="' + attachment.url + '" style="width: 100%;"></audio>';
                    } else {
                        // For other files, insert a link
                        html = '<a href="' + attachment.url + '">' + (attachment.title || attachment.filename) + '</a>';
                    }

                    // Insert into editor
                    editor.insertContent(html);
                });

                // Open the media frame
                mediaFrame.open();
            }
        });
    });
})();
