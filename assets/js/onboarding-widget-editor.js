(function($) {
    'use strict';

    /**
     * Handle tour reset button in Elementor editor
     */
    $(window).on('elementor:init', function() {
        elementor.hooks.addAction('panel/open_editor/widget/voxel-onboarding', function(panel, model, view) {

            // Update version display when panel opens
            setTimeout(function() {
                updateVersionDisplay(model);
            }, 100);

            // Handle reset button click
            panel.$el.on('click', '.voxel-reset-tour-btn', function(e) {
                e.preventDefault();

                // Get current version
                const currentVersion = parseInt(model.getSetting('reset_tour_version')) || 1;
                const newVersion = currentVersion + 1;

                // Update the model
                model.setSetting('reset_tour_version', newVersion);

                // Mark the document as changed so Elementor enables the Update button
                elementor.saver.setFlagEditorChange(true);

                // Update the display
                panel.$el.find('.voxel-tour-version-display').text(newVersion);

                // Show confirmation
                const $btn = $(this);
                const originalText = $btn.text();
                $btn.text('Tour Reset! Version: ' + newVersion);
                $btn.css('background-color', '#5cb85c');

                setTimeout(function() {
                    $btn.text(originalText);
                    $btn.css('background-color', '');
                }, 2000);
            });
        });
    });

    /**
     * Update version display
     */
    function updateVersionDisplay(model) {
        const version = parseInt(model.getSetting('reset_tour_version')) || 1;
        $('.voxel-tour-version-display').text(version);
    }

})(jQuery);
