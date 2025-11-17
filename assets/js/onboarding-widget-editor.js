(function($) {
    'use strict';

    /**
     * Handle tour reset button in Elementor editor
     */
    $(window).on('elementor:init', function() {
        elementor.hooks.addAction('panel/open_editor/widget/voxel-onboarding', function(panel, model, view) {

            // Update version display when panel opens
            setTimeout(function() {
                updateVersionDisplay(panel, model);
            }, 100);

            // Handle reset button click
            panel.$el.on('click', '.voxel-reset-tour-btn', function(e) {
                e.preventDefault();

                // Get current version
                const currentVersion = parseInt(model.getSetting('reset_tour_version')) || 1;
                const newVersion = currentVersion + 1;

                // Update the model
                model.setSetting('reset_tour_version', newVersion);

                // Update the display in THIS panel only
                updateVersionDisplay(panel, model);

                // Show confirmation
                const $btn = $(this);
                const originalText = $btn.text();
                $btn.text('Saving...');
                $btn.prop('disabled', true);

                // Auto-save the page to persist the change
                elementor.saver.setFlagEditorChange(true);
                $e.run('document/save/auto', {
                    force: true,
                    onSuccess: function() {
                        $btn.text('Tour Reset! Version: ' + newVersion);
                        $btn.css('background-color', '#5cb85c');
                        $btn.prop('disabled', false);

                        setTimeout(function() {
                            $btn.text(originalText);
                            $btn.css('background-color', '');
                        }, 2000);
                    }
                });
            });

            // Handle preview tour button click
            panel.$el.on('click', '.voxel-preview-tour-btn', function(e) {
                e.preventDefault();

                const $btn = $(this);
                const originalText = $btn.text();

                try {
                    // Get the widget element in the preview iframe
                    const widgetId = model.get('id');
                    const previewWindow = elementor.$preview[0].contentWindow;
                    const previewJQuery = previewWindow.jQuery;

                    if (!previewJQuery) {
                        alert('Preview not ready. Please wait a moment and try again.');
                        return;
                    }

                    // Find the widget in the preview using the preview iframe's jQuery
                    const $previewWidget = previewJQuery('[data-id="' + widgetId + '"]').find('.voxel-onboarding-tour-widget');

                    if ($previewWidget.length === 0) {
                        alert('Widget not found in preview. Make sure you have added the widget to the page.');
                        return;
                    }

                    // Get the tour instance stored on the widget element (in the iframe's context)
                    let tourInstance = $previewWidget.data('voxelTourInstance');

                    if (tourInstance) {
                        // Clear completion and start the tour
                        tourInstance.clearCompletion();
                        tourInstance.start();

                        // Show confirmation
                        $btn.text('Tour Started!');
                        setTimeout(function() {
                            $btn.text(originalText);
                        }, 2000);
                    } else {
                        // Tour not initialized yet, show message
                        $btn.text('Initializing...');

                        // Wait and try again
                        setTimeout(function() {
                            tourInstance = $previewWidget.data('voxelTourInstance');
                            if (tourInstance) {
                                tourInstance.clearCompletion();
                                tourInstance.start();
                                $btn.text('Tour Started!');
                            } else {
                                $btn.text('Not Ready');
                            }
                            setTimeout(function() {
                                $btn.text(originalText);
                            }, 2000);
                        }, 1000);
                    }
                } catch (error) {
                    console.error('Preview tour error:', error);
                    $btn.text('Error');
                    setTimeout(function() {
                        $btn.text(originalText);
                    }, 2000);
                }
            });
        });
    });

    /**
     * Update version display
     */
    function updateVersionDisplay(panel, model) {
        const version = parseInt(model.getSetting('reset_tour_version')) || 1;
        // Update only in the current panel to avoid affecting other widgets
        panel.$el.find('.voxel-tour-version-display').text(version);
    }

})(jQuery);
