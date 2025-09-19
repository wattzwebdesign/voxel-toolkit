/**
 * Media Paste functionality for WordPress admin
 */
(function($) {
    'use strict';
    
    class MediaPaste {
        constructor() {
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.createPasteOverlay();
            this.showPasteHint();
        }
        
        bindEvents() {
            // Listen for paste events on media areas
            $(document).on('paste', this.handlePaste.bind(this));
            
            // Also listen for paste events on specific media containers
            $(document).on('paste', '.media-frame, .media-modal, .upload-php, .wp-media-wrapper', this.handlePaste.bind(this));
            
            // Prevent default drag/drop on media areas to show paste hint
            $(document).on('dragover dragenter', '.media-frame, .upload-php', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
        }
        
        handlePaste(e) {
            const clipboardData = e.originalEvent.clipboardData;
            
            if (!clipboardData || !clipboardData.items) {
                return;
            }
            
            // Check if we're in a media context
            if (!this.isMediaContext(e.target)) {
                return;
            }
            
            // Look for image files in clipboard
            for (let i = 0; i < clipboardData.items.length; i++) {
                const item = clipboardData.items[i];
                
                if (item.type.indexOf('image/') !== -1) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const file = item.getAsFile();
                    this.uploadPastedImage(file);
                    break;
                }
            }
        }
        
        isMediaContext(target) {
            const $target = $(target);
            
            // Check if we're in media library, upload page, or media frame
            return $target.closest('.media-frame, .upload-php, .wp-media-wrapper, .media-modal').length > 0 ||
                   $('body').hasClass('upload-php') ||
                   $('.media-frame').length > 0;
        }
        
        uploadPastedImage(file) {
            // Show loading indicator
            this.showLoadingIndicator();
            
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
                        this.hideLoadingIndicator();
                        
                        if (response.success) {
                            this.handleUploadSuccess(response.data);
                        } else {
                            this.showError(response.data.message || voxelMediaPaste.errorText);
                        }
                    },
                    error: () => {
                        this.hideLoadingIndicator();
                        this.showError(voxelMediaPaste.errorText);
                    }
                });
            };
            
            reader.onerror = () => {
                this.showError('Failed to read image file');
            };
            
            reader.readAsDataURL(file);
        }
        
        generateFilename(mimeType) {
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const extension = this.getExtensionFromMimeType(mimeType);
            return `pasted-image-${timestamp}.${extension}`;
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
        
        showLoadingIndicator() {
            // Remove existing indicator
            $('.voxel-paste-loading').remove();
            
            const $indicator = $(`
                <div class="voxel-paste-loading">
                    <div class="voxel-paste-loading-content">
                        <div class="voxel-paste-spinner"></div>
                        <div class="voxel-paste-loading-text">${voxelMediaPaste.uploadingText}</div>
                    </div>
                </div>
            `);
            
            $('body').append($indicator);
        }
        
        hideLoadingIndicator() {
            $('.voxel-paste-loading').fadeOut(300, function() {
                $(this).remove();
            });
        }
        
        handleUploadSuccess(data) {
            this.showSuccess(voxelMediaPaste.successText);
            
            // Refresh media library if we're in the media library
            if ($('body').hasClass('upload-php')) {
                // Reload the page to show new image
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
            
            // If media frame is open, try to refresh it
            if (wp && wp.media && wp.media.frame) {
                // Refresh the media library
                wp.media.frame.content.mode('browse');
                if (wp.media.frame.content.get().library) {
                    wp.media.frame.content.get().library.reset();
                }
            }
            
            // Trigger custom event for other scripts
            $(document).trigger('voxel-media-pasted', [data]);
        }
        
        showError(message) {
            this.showNotification(message, 'error');
        }
        
        showSuccess(message) {
            this.showNotification(message, 'success');
        }
        
        showNotification(message, type) {
            const $notification = $(`
                <div class="voxel-paste-notification voxel-paste-${type}">
                    <div class="voxel-paste-notification-content">
                        <span class="voxel-paste-notification-text">${message}</span>
                        <button class="voxel-paste-notification-close">&times;</button>
                    </div>
                </div>
            `);
            
            $('body').append($notification);
            
            // Auto hide after 3 seconds
            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
            
            // Manual close
            $notification.find('.voxel-paste-notification-close').on('click', function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
        
        createPasteOverlay() {
            // Create overlay for visual feedback when pasting
            const $overlay = $(`
                <div class="voxel-paste-overlay" style="display: none;">
                    <div class="voxel-paste-overlay-content">
                        <div class="voxel-paste-overlay-icon">📋</div>
                        <div class="voxel-paste-overlay-text">Paste image here</div>
                    </div>
                </div>
            `);
            
            $('body').append($overlay);
        }
        
        showPasteHint() {
            // Show hint about paste functionality in media areas
            if ($('body').hasClass('upload-php') || $('.media-frame').length > 0) {
                const $hint = $(`
                    <div class="voxel-paste-hint">
                        <span class="voxel-paste-hint-icon">💡</span>
                        <span class="voxel-paste-hint-text">Tip: You can paste images directly from your clipboard!</span>
                        <button class="voxel-paste-hint-close">&times;</button>
                    </div>
                `);
                
                // Add hint to media area
                if ($('body').hasClass('upload-php')) {
                    $('.wrap h1').after($hint);
                }
                
                // Close hint
                $hint.find('.voxel-paste-hint-close').on('click', function() {
                    $hint.fadeOut(300, function() {
                        $(this).remove();
                    });
                    // Remember that user closed hint
                    localStorage.setItem('voxel-paste-hint-closed', 'true');
                });
                
                // Don't show hint if user previously closed it
                if (localStorage.getItem('voxel-paste-hint-closed') === 'true') {
                    $hint.hide();
                }
            }
        }
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        new MediaPaste();
    });
    
})(jQuery);