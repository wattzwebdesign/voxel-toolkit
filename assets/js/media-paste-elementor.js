/**
 * Media Paste functionality for Elementor
 */
(function($) {
    'use strict';
    
    class ElementorMediaPaste {
        constructor() {
            this.init();
        }
        
        init() {
            // Wait for Elementor to load
            if (typeof elementor === 'undefined') {
                setTimeout(() => this.init(), 500);
                return;
            }
            
            this.bindEvents();
        }
        
        bindEvents() {
            // Listen for paste events in Elementor editor
            $(document).on('paste', this.handlePaste.bind(this));
            
            // Listen for Elementor media control events
            elementor.hooks.addAction('panel/open_editor/widget', this.onWidgetOpen.bind(this));
            
            // Listen for Elementor media library events
            this.bindMediaLibraryEvents();
            
            // Simple periodic check for media frames
            this.startPeriodicFrameCheck();
        }
        
        onWidgetOpen(panel, model, view) {
            // Add paste functionality to media controls when widget opens
            setTimeout(() => {
                this.enhanceMediaControls();
            }, 100);
        }
        
        enhanceMediaControls() {
            // Find media controls in the current panel
            $('.elementor-control-media .elementor-control-media__preview').each((index, element) => {
                const $element = $(element);
                
                // Skip if already enhanced
                if ($element.hasClass('voxel-paste-enhanced')) {
                    return;
                }
                
                $element.addClass('voxel-paste-enhanced');
                
                // Add paste zone overlay
                const $pasteZone = $(`
                    <div class="voxel-elementor-paste-zone">
                        <div class="voxel-elementor-paste-content">
                            <span class="voxel-elementor-paste-icon">📋</span>
                            <span class="voxel-elementor-paste-text">Paste image here</span>
                        </div>
                    </div>
                `);
                
                $element.append($pasteZone);
                
                // Bind paste event to this specific control
                $element.on('paste', (e) => {
                    this.handleElementorPaste(e, $element);
                });
                
                // Make it focusable for paste events
                $element.attr('tabindex', '0');
            });
        }
        
        handlePaste(e) {
            // Only handle if we're in Elementor and focused on a media control
            if (!this.isElementorMediaContext(e.target)) {
                return;
            }
            
            this.handleElementorPaste(e, $(e.target).closest('.elementor-control-media__preview'));
        }
        
        handleElementorPaste(e, $mediaControl) {
            const clipboardData = e.originalEvent.clipboardData;
            
            if (!clipboardData || !clipboardData.items) {
                return;
            }
            
            // Look for image files in clipboard
            for (let i = 0; i < clipboardData.items.length; i++) {
                const item = clipboardData.items[i];
                
                if (item.type.indexOf('image/') !== -1) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const file = item.getAsFile();
                    this.uploadPastedImageToElementor(file, $mediaControl);
                    break;
                }
            }
        }
        
        bindMediaLibraryEvents() {
            // Watch for Elementor media library opening
            $(document).on('click', '.elementor-control-media-upload-button, .elementor-control-media__upload-button', () => {
                setTimeout(() => {
                    this.enhanceMediaFrames();
                }, 500);
            });
            
            // Watch for "Insert Media" button clicks
            $(document).on('click', '.elementor-control-media .media-button, .elementor-control-media .insert-media', () => {
                setTimeout(() => {
                    this.enhanceMediaFrames();
                }, 500);
            });
        }
        
        startPeriodicFrameCheck() {
            // Simple periodic check for media frames
            setInterval(() => {
                this.enhanceMediaFrames();
            }, 1000);
        }
        
        enhanceMediaFrames() {
            // Target WordPress media frames specifically
            $('.media-frame').each((index, frame) => {
                const $frame = $(frame);
                
                // Skip if already enhanced
                if ($frame.hasClass('voxel-paste-enhanced')) {
                    return;
                }
                
                $frame.addClass('voxel-paste-enhanced');
                
                // Add paste functionality to the entire frame
                $frame.on('paste', (e) => {
                    this.handleMediaFramePaste(e, $frame);
                });
                
                // Make frame focusable for paste events
                $frame.attr('tabindex', '0');
            });
            
            // Also enhance specific media areas
            $('.media-frame-content, .media-frame-router, .media-toolbar').each((index, element) => {
                const $element = $(element);
                
                if ($element.hasClass('voxel-paste-enhanced')) {
                    return;
                }
                
                $element.addClass('voxel-paste-enhanced');
                
                $element.on('paste', (e) => {
                    this.handleMediaFramePaste(e, $element.closest('.media-frame'));
                });
                
                $element.attr('tabindex', '0');
            });
        }
        
        
        handleMediaFramePaste(e, $frame) {
            const clipboardData = e.originalEvent.clipboardData;
            
            if (!clipboardData || !clipboardData.items) {
                return;
            }
            
            // Look for image files in clipboard
            for (let i = 0; i < clipboardData.items.length; i++) {
                const item = clipboardData.items[i];
                
                if (item.type.indexOf('image/') !== -1) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const file = item.getAsFile();
                    this.uploadPastedImageToMediaFrame(file, $frame);
                    break;
                }
            }
        }
        
        uploadPastedImageToMediaFrame(file, $frame) {
            // Show loading overlay on the frame
            this.showFrameLoading($frame);
            
            // Convert file to base64
            const reader = new FileReader();
            reader.onload = (e) => {
                const imageData = e.target.result;
                
                // Send to server
                $.ajax({
                    url: voxelMediaPaste.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'voxel_toolkit_paste_image',
                        nonce: voxelMediaPaste.nonce,
                        image_data: imageData,
                        filename: this.generateFilename(file.type)
                    },
                    success: (response) => {
                        this.hideFrameLoading($frame);
                        
                        if (response.success) {
                            this.handleMediaFrameSuccess($frame, response.data);
                        } else {
                            this.showFrameError($frame, response.data.message || voxelMediaPaste.errorText);
                        }
                    },
                    error: () => {
                        this.hideFrameLoading($frame);
                        this.showFrameError($frame, voxelMediaPaste.errorText);
                    }
                });
            };
            
            reader.onerror = () => {
                this.hideFrameLoading($frame);
                this.showFrameError($frame, 'Failed to read image file');
            };
            
            reader.readAsDataURL(file);
        }
        
        handleMediaFrameSuccess($frame, imageData) {
            this.showFrameSuccess($frame, voxelMediaPaste.successText);
            
            // Try to refresh the media library
            if (wp && wp.media && wp.media.frame) {
                // Get current media frame
                const frame = wp.media.frame;
                
                // Refresh the library
                if (frame.content && frame.content.get()) {
                    const content = frame.content.get();
                    if (content.collection) {
                        content.collection.more().done(() => {
                            // Scroll to show the new image
                            const $attachment = $frame.find(`[data-id="${imageData.id}"]`);
                            if ($attachment.length > 0) {
                                $attachment[0].scrollIntoView({ behavior: 'smooth' });
                                $attachment.addClass('voxel-just-added');
                            }
                        });
                    }
                }
            }
        }
        
        showFrameLoading($frame) {
            const $loading = $(`
                <div class="voxel-frame-loading-overlay">
                    <div class="voxel-frame-loading-content">
                        <div class="voxel-elementor-spinner"></div>
                        <div class="voxel-frame-loading-text">${voxelMediaPaste.uploadingText}</div>
                    </div>
                </div>
            `);
            
            $frame.css('position', 'relative').append($loading);
        }
        
        hideFrameLoading($frame) {
            $frame.find('.voxel-frame-loading-overlay').remove();
        }
        
        showFrameSuccess($frame, message) {
            const $success = $(`
                <div class="voxel-frame-notification voxel-frame-success">
                    ✅ ${message}
                </div>
            `);
            
            $frame.append($success);
            
            setTimeout(() => {
                $success.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
        
        showFrameError($frame, message) {
            const $error = $(`
                <div class="voxel-frame-notification voxel-frame-error">
                    ❌ ${message}
                </div>
            `);
            
            $frame.append($error);
            
            setTimeout(() => {
                $error.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 4000);
        }
        
        isElementorMediaContext(target) {
            const $target = $(target);
            return $target.closest('.elementor-control-media, .elementor-control-media__preview, .media-frame').length > 0;
        }
        
        uploadPastedImageToElementor(file, $mediaControl) {
            // Show loading state
            this.showElementorLoading($mediaControl);
            
            // Convert file to base64
            const reader = new FileReader();
            reader.onload = (e) => {
                const imageData = e.target.result;
                
                // Send to server
                $.ajax({
                    url: voxelMediaPaste.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'voxel_toolkit_paste_image',
                        nonce: voxelMediaPaste.nonce,
                        image_data: imageData,
                        filename: this.generateFilename(file.type)
                    },
                    success: (response) => {
                        this.hideElementorLoading($mediaControl);
                        
                        if (response.success) {
                            this.setElementorMediaValue($mediaControl, response.data);
                            this.showElementorSuccess($mediaControl);
                        } else {
                            this.showElementorError($mediaControl, response.data.message || voxelMediaPaste.errorText);
                        }
                    },
                    error: () => {
                        this.hideElementorLoading($mediaControl);
                        this.showElementorError($mediaControl, voxelMediaPaste.errorText);
                    }
                });
            };
            
            reader.onerror = () => {
                this.hideElementorLoading($mediaControl);
                this.showElementorError($mediaControl, 'Failed to read image file');
            };
            
            reader.readAsDataURL(file);
        }
        
        setElementorMediaValue($mediaControl, imageData) {
            // Find the control instance
            const $control = $mediaControl.closest('.elementor-control');
            const controlName = $control.data('setting');
            
            if (!controlName) {
                return;
            }
            
            // Get the current widget model
            const activeWidget = elementor.panel.currentView.currentPageView.model;
            
            if (!activeWidget) {
                return;
            }
            
            // Set the media value
            const settings = {};
            settings[controlName] = {
                id: imageData.id,
                url: imageData.url
            };
            
            // Update the widget settings
            activeWidget.setSetting(settings);
            
            // Update the preview image
            $mediaControl.find('.elementor-control-media__preview').css('background-image', `url(${imageData.url})`);
            $mediaControl.removeClass('elementor-control-media-empty');
            
            // Update remove button
            $mediaControl.find('.elementor-control-media__remove').show();
        }
        
        showElementorLoading($mediaControl) {
            $mediaControl.addClass('voxel-elementor-loading');
            
            const $loading = $(`
                <div class="voxel-elementor-loading-overlay">
                    <div class="voxel-elementor-spinner"></div>
                    <div class="voxel-elementor-loading-text">${voxelMediaPaste.uploadingText}</div>
                </div>
            `);
            
            $mediaControl.append($loading);
        }
        
        hideElementorLoading($mediaControl) {
            $mediaControl.removeClass('voxel-elementor-loading');
            $mediaControl.find('.voxel-elementor-loading-overlay').remove();
        }
        
        showElementorSuccess($mediaControl) {
            const $success = $(`
                <div class="voxel-elementor-feedback voxel-elementor-success">
                    ✅ ${voxelMediaPaste.successText}
                </div>
            `);
            
            $mediaControl.append($success);
            
            setTimeout(() => {
                $success.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 2000);
        }
        
        showElementorError($mediaControl, message) {
            const $error = $(`
                <div class="voxel-elementor-feedback voxel-elementor-error">
                    ❌ ${message}
                </div>
            `);
            
            $mediaControl.append($error);
            
            setTimeout(() => {
                $error.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
        
        generateFilename(mimeType) {
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const extension = this.getExtensionFromMimeType(mimeType);
            return `elementor-pasted-${timestamp}.${extension}`;
        }
        
        getExtensionFromMimeType(mimeType) {
            const typeMap = {
                'image/jpeg': 'jpg',
                'image/png': 'png',
                'image/gif': 'gif',
                'image/webp': 'webp'
            };
            return typeMap[mimeType] || 'png';
        }
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        new ElementorMediaPaste();
    });
    
})(jQuery);