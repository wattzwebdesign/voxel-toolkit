/**
 * Voxel Toolkit - Image Optimization v5.0
 *
 * Client-side image optimization with WebP conversion, resizing, watermarks, and renaming.
 */
(function() {
    'use strict';

    if (typeof VT_ImageOptimization === 'undefined') {
        return;
    }

    const Settings = VT_ImageOptimization;
    const i18n = Settings.i18n || {};
    const processedFiles = new WeakSet();
    let isWorking = false;
    let fileCounter = 0;

    /**
     * Get translated string with sprintf-like replacement
     */
    function __(key, ...args) {
        let str = i18n[key] || key;
        if (args.length > 0) {
            // Handle positional placeholders like %1$d, %2$s
            str = str.replace(/%(\d+)\$([ds])/g, (match, pos, type) => {
                const idx = parseInt(pos, 10) - 1;
                return args[idx] !== undefined ? args[idx] : match;
            });
            // Handle simple placeholders like %d, %s
            let argIndex = 0;
            str = str.replace(/%([ds])/g, (match, type) => {
                return args[argIndex] !== undefined ? args[argIndex++] : match;
            });
        }
        return str;
    }

    // Check if browser supports WebP encoding (native canvas method)
    const supportsNativeWebP = (() => {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        return canvas.toDataURL('image/webp').startsWith('data:image/webp');
    })();

    // jSquash WebP encoder for Safari (loaded on demand)
    let jSquashWebP = null;
    let jSquashLoading = false;
    let jSquashLoadPromise = null;

    /**
     * Load jSquash WebP encoder from CDN (only for Safari)
     */
    async function loadJSquashEncoder() {
        if (jSquashWebP) return jSquashWebP;
        if (jSquashLoadPromise) return jSquashLoadPromise;

        jSquashLoading = true;
        jSquashLoadPromise = (async () => {
            try {
                // Dynamic import from esm.sh CDN
                const module = await import('https://esm.sh/@jsquash/webp@1.4.0');
                jSquashWebP = module;
                return module;
            } catch (e) {
                console.warn('Failed to load WebP encoder:', e);
                return null;
            } finally {
                jSquashLoading = false;
            }
        })();

        return jSquashLoadPromise;
    }

    /**
     * Convert canvas to blob with WebP support for Safari via jSquash
     */
    async function canvasToBlob(canvas, mimeType, quality) {
        // Try native toBlob first (Chrome, Firefox)
        if (supportsNativeWebP || mimeType !== 'image/webp') {
            return new Promise(resolve => {
                canvas.toBlob(resolve, mimeType, quality);
            });
        }

        // Safari: use jSquash WASM encoder
        try {
            const encoder = await loadJSquashEncoder();
            if (encoder && encoder.encode) {
                const ctx = canvas.getContext('2d');
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const webpBuffer = await encoder.encode(imageData, { quality: Math.round(quality * 100) });
                return new Blob([webpBuffer], { type: 'image/webp' });
            }
        } catch (e) {
            console.warn('jSquash WebP encoding failed:', e);
        }

        // Fallback: use JPEG for better compression than PNG
        return new Promise(resolve => {
            canvas.toBlob(resolve, 'image/jpeg', quality);
        });
    }

    /**
     * Toast notification manager
     */
    const Toast = {
        el: null,

        isEnabled() {
            return Settings.showToast !== false;
        },

        show(title, message, type = 'default') {
            if (!this.isEnabled()) return;
            this.hide();
            this.el = document.createElement('div');
            this.el.className = `vt-image-opt-toast ${type === 'error' ? 'vt-image-opt-toast-error' : ''}`;
            const iconClass = type === 'error' ? 'vt-image-opt-toast-error-icon' : 'vt-image-opt-toast-spinner';
            this.el.innerHTML = `
                <div class="${iconClass}"></div>
                <div class="vt-image-opt-toast-content">
                    <div class="vt-image-opt-toast-title">${title}</div>
                    <div class="vt-image-opt-toast-message">${message}</div>
                </div>
            `;
            document.body.appendChild(this.el);
            if (type === 'error') {
                setTimeout(() => this.hide(), 5000);
            }
        },

        update(title, message) {
            if (!this.isEnabled()) return;
            if (this.el) {
                this.el.querySelector('.vt-image-opt-toast-title').textContent = title;
                this.el.querySelector('.vt-image-opt-toast-message').textContent = message;
            }
        },

        success(title, message) {
            if (!this.isEnabled()) return;
            if (this.el) {
                this.el.classList.add('vt-image-opt-toast-success');
                this.el.innerHTML = `
                    <span style="font-size: 24px;">✓</span>
                    <div class="vt-image-opt-toast-content">
                        <div class="vt-image-opt-toast-title">${title}</div>
                        <div class="vt-image-opt-toast-message">${message}</div>
                    </div>
                `;
                setTimeout(() => this.hide(), 4000);
            }
        },

        hide() {
            if (this.el) {
                this.el.classList.add('vt-image-opt-toast-hidden');
                const el = this.el;
                setTimeout(() => el.remove(), 500);
                this.el = null;
            }
        }
    };

    /**
     * Image optimizer utilities
     */
    const ImageOptimizer = {
        /**
         * Get post title from various form inputs
         */
        getPostTitle() {
            const selectors = [
                'input[name="title"]',
                'input[name="post_title"]',
                '#title',
                '.ts-form input.ts-filter[type="text"]'
            ];
            for (let selector of selectors) {
                const el = document.querySelector(selector);
                if (el && el.value) {
                    return el.value;
                }
            }
            return '';
        },

        /**
         * Convert text to URL-friendly slug
         */
        slugify(text) {
            return text.toString()
                .toLowerCase()
                .trim()
                .replace(/\s+/g, '-')
                .replace(/[^\w\-]+/g, '')
                .replace(/\-\-+/g, '-');
        },

        /**
         * Format bytes to human readable string
         */
        formatBytes(bytes) {
            if (bytes === 0) return '0 ' + (i18n.bytes || 'Bytes');
            const k = 1024;
            const sizes = [i18n.bytes || 'Bytes', i18n.kb || 'KB', i18n.mb || 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Draw text watermark on canvas
         */
        drawText(ctx, w, h, text, pos) {
            const scaleFactor = (Settings.wmScale / 100);
            const fontSize = Math.floor(w * scaleFactor / 5);
            ctx.font = `bold ${fontSize}px sans-serif`;
            const opacity = Settings.wmOpacity || 0.7;
            ctx.fillStyle = `rgba(255, 255, 255, ${opacity})`;
            ctx.shadowColor = "rgba(0, 0, 0, 0.5)";
            ctx.shadowBlur = 4;
            ctx.textBaseline = 'middle';
            let x, y;
            if (pos === 'center') {
                ctx.textAlign = 'center';
                x = w / 2;
                y = h / 2;
            } else if (pos === 'top-left') {
                ctx.textAlign = 'left';
                x = 30;
                y = fontSize + 20;
            } else if (pos === 'top-right') {
                ctx.textAlign = 'right';
                x = w - 30;
                y = fontSize + 20;
            } else if (pos === 'bottom-left') {
                ctx.textAlign = 'left';
                x = 30;
                y = h - 30;
            } else {
                // bottom-right (default)
                ctx.textAlign = 'right';
                x = w - 30;
                y = h - 30;
            }
            ctx.fillText(text, x, y);
        },

        /**
         * Draw image watermark on canvas
         */
        async drawImage(ctx, w, h, url, pos) {
            return new Promise((resolve) => {
                const wm = new Image();
                wm.crossOrigin = "anonymous";
                wm.src = url;
                wm.onload = () => {
                    const scaleFactor = (Settings.wmScale / 100);
                    const wmW = w * scaleFactor;
                    const aspect = wm.height / wm.width;
                    const wmH = wmW * aspect;
                    let x, y;
                    if (pos === 'center') {
                        x = (w - wmW) / 2;
                        y = (h - wmH) / 2;
                    } else if (pos === 'top-left') {
                        x = 30;
                        y = 30;
                    } else if (pos === 'top-right') {
                        x = w - wmW - 30;
                        y = 30;
                    } else if (pos === 'bottom-left') {
                        x = 30;
                        y = h - wmH - 30;
                    } else {
                        // bottom-right (default)
                        x = w - wmW - 30;
                        y = h - wmH - 30;
                    }
                    ctx.globalAlpha = Settings.wmOpacity || 0.7;
                    ctx.drawImage(wm, x, y, wmW, wmH);
                    ctx.globalAlpha = 1.0;
                    resolve();
                };
                wm.onerror = () => resolve();
            });
        },

        /**
         * Optimize a single image file
         */
        async optimize(file, index, total) {
            // Check file size limit
            const maxBytes = Settings.maxFileSizeMB * 1024 * 1024;
            if (file.size > maxBytes) {
                Toast.show(
                    i18n.fileTooLarge || 'File too large!',
                    __('exceedsMbLimit', file.name, Settings.maxFileSizeMB),
                    'error'
                );
                return null;
            }

            // Skip non-image files and already processed files
            if (!file.type.match(/^image\/(jpeg|png|webp)$/) || processedFiles.has(file)) {
                return file;
            }

            Toast.update(
                i18n.optimizingImages || 'Optimizing images...',
                __('imageXOfY', index + 1, total, file.name)
            );

            return new Promise((resolve) => {
                const img = new Image();
                img.src = URL.createObjectURL(file);

                img.onload = async () => {
                    URL.revokeObjectURL(img.src);

                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;

                    // Resize if needed
                    if (width > Settings.maxWidth || height > Settings.maxHeight) {
                        const ratio = Math.min(Settings.maxWidth / width, Settings.maxHeight / height);
                        width *= ratio;
                        height *= ratio;
                    }

                    canvas.width = width;
                    canvas.height = height;

                    const ctx = canvas.getContext('2d');
                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';
                    ctx.drawImage(img, 0, 0, width, height);

                    // Apply watermark if enabled
                    if (Settings.wmType === 'text' && Settings.wmText) {
                        this.drawText(ctx, width, height, Settings.wmText, Settings.wmPos);
                    } else if (Settings.wmType === 'image' && Settings.wmImg) {
                        await this.drawImage(ctx, width, height, Settings.wmImg, Settings.wmPos);
                    }

                    // Determine target MIME type based on optimization mode
                    let targetMime = file.type;
                    const mode = Settings.optimizationMode || 'all_webp';

                    // Set target to WebP based on mode (canvasToBlob will handle fallback)
                    if (mode === 'all_webp') {
                        targetMime = 'image/webp';
                    } else if (mode === 'only_jpg' && file.type === 'image/jpeg') {
                        targetMime = 'image/webp';
                    } else if (mode === 'only_png' && file.type === 'image/png') {
                        targetMime = 'image/webp';
                    } else if (mode === 'both_to_webp' && (file.type === 'image/jpeg' || file.type === 'image/png')) {
                        targetMime = 'image/webp';
                    }
                    // 'originals_only' keeps the original format

                    // Use canvasToBlob which handles Safari WebP encoding
                    const blob = await canvasToBlob(canvas, targetMime, Settings.outputQuality);
                    const actualType = blob.type || targetMime;
                    const ext = actualType.split('/')[1].replace('jpeg', 'jpg');
                    const baseName = file.name.replace(/\.[^/.]+$/, '');
                    fileCounter++;
                    const counterStr = String(fileCounter).padStart(2, '0');
                    const title = this.getPostTitle();

                    let newName;
                    if (Settings.renameFormat === 'post_title' && title) {
                        newName = `${this.slugify(title)}-${counterStr}.${ext}`;
                    } else {
                        newName = `${baseName}-${counterStr}.${ext}`;
                    }

                    const optimized = new File([blob], newName, {
                        type: actualType,
                        lastModified: Date.now()
                    });

                    processedFiles.add(optimized);
                    resolve(optimized);
                };

                img.onerror = () => {
                    URL.revokeObjectURL(img.src);
                    resolve(file);
                };
            });
        }
    };

    /**
     * Process files and re-trigger the event
     */
    async function processAndTrigger(files, target, eventType) {
        isWorking = true;
        fileCounter = 0;
        let totalSaved = 0;

        const allFiles = Array.from(files);
        const imageFiles = allFiles.filter(f => f.type.match(/^image\/(jpeg|png|webp)$/));

        if (imageFiles.length === 0) {
            isWorking = false;
            return;
        }

        Toast.show(
            i18n.optimizing || 'Optimizing...',
            __('processingImages', imageFiles.length)
        );

        const optimizedFiles = [];
        for (let i = 0; i < allFiles.length; i++) {
            const originalSize = allFiles[i].size;
            const result = await ImageOptimizer.optimize(allFiles[i], i, allFiles.length);
            if (result === null) {
                // File exceeded size limit, abort
                isWorking = false;
                return;
            }
            optimizedFiles.push(result);
            if (result !== allFiles[i]) {
                totalSaved += (originalSize - result.size);
            }
        }

        Toast.success(
            i18n.done || 'Done!',
            __('imagesOptimized', imageFiles.length, ImageOptimizer.formatBytes(totalSaved))
        );

        // Create new DataTransfer with optimized files
        const dt = new DataTransfer();
        optimizedFiles.forEach(f => dt.items.add(f));

        isWorking = false;

        // Re-trigger the appropriate event
        if (eventType === 'change') {
            target.files = dt.files;
            target.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            target.dispatchEvent(new DragEvent('drop', {
                bubbles: true,
                cancelable: true,
                dataTransfer: dt
            }));
        }
    }

    /**
     * Handle file input change events
     */
    document.addEventListener('change', async (e) => {
        if (e.target.type === 'file' && e.target.files.length > 0 && !isWorking) {
            const files = Array.from(e.target.files);
            if (files.some(f => f.type.match(/^image\/(jpeg|png|webp)$/) && !processedFiles.has(f))) {
                e.stopImmediatePropagation();
                await processAndTrigger(e.target.files, e.target, 'change');
            }
        }
    }, true);

    /**
     * Handle drag and drop events
     */
    document.addEventListener('drop', async (e) => {
        if (!isWorking && e.dataTransfer && e.dataTransfer.files.length) {
            const files = Array.from(e.dataTransfer.files);
            if (files.some(f => f.type.match(/^image\/(jpeg|png|webp)$/) && !processedFiles.has(f))) {
                e.preventDefault();
                e.stopImmediatePropagation();
                await processAndTrigger(e.dataTransfer.files, e.target, 'drop');
            }
        }
    }, true);

    /**
     * Hook into WordPress Media Library uploads (wp.Uploader/plupload)
     * This handles Elementor image widget, Gutenberg, and native WP media uploads
     */
    const hookedUploaders = new WeakSet();

    /**
     * Hook a plupload uploader instance for file optimization
     */
    function hookPluploadInstance(uploader) {
        if (!uploader || hookedUploaders.has(uploader)) return;
        hookedUploaders.add(uploader);

        uploader.bind('FilesAdded', async function(up, files) {
            // Find image files that need processing
            const imageFiles = files.filter(f =>
                f.type && f.type.match(/^image\/(jpeg|png|webp)$/) &&
                !f._vtOptimized
            );

            if (imageFiles.length === 0 || isWorking) {
                return;
            }

            isWorking = true;
            fileCounter = 0;
            let totalSaved = 0;

            // Stop auto-start
            up.stop();

            Toast.show(
                i18n.optimizing || 'Optimizing...',
                __('processingImages', imageFiles.length)
            );

            // Process each file
            const filesToReplace = [];

            for (let i = 0; i < imageFiles.length; i++) {
                const plFile = imageFiles[i];
                const nativeFile = plFile.getNative ? plFile.getNative() : null;

                if (!nativeFile) continue;

                const originalSize = nativeFile.size;
                const originalId = plFile.id;

                Toast.update(
                    i18n.optimizingImages || 'Optimizing images...',
                    __('imageXOfY', i + 1, imageFiles.length, nativeFile.name)
                );

                try {
                    const optimizedFile = await ImageOptimizer.optimize(nativeFile, i, imageFiles.length);

                    if (optimizedFile && optimizedFile !== nativeFile) {
                        totalSaved += (originalSize - optimizedFile.size);
                        filesToReplace.push({
                            originalId: originalId,
                            optimizedFile: optimizedFile
                        });
                    }
                } catch (e) {
                    console.warn('VT Image Optimization: Failed to optimize', nativeFile.name, e);
                }
            }

            // Replace files in the queue
            for (const item of filesToReplace) {
                const plFile = up.getFile(item.originalId);
                if (plFile) {
                    // Remove original file
                    up.removeFile(plFile);

                    // Add optimized file
                    up.addFile(item.optimizedFile);

                    // Mark the newly added file as optimized
                    const newFile = up.files[up.files.length - 1];
                    if (newFile) {
                        newFile._vtOptimized = true;
                    }
                }
            }

            Toast.success(
                i18n.done || 'Done!',
                __('imagesOptimized', imageFiles.length, ImageOptimizer.formatBytes(totalSaved))
            );

            isWorking = false;

            // Resume upload
            up.start();
        });
    }

    /**
     * Find and hook all plupload uploaders on the page
     */
    function findAndHookUploaders() {
        // Check wp.Uploader instances
        if (typeof wp !== 'undefined' && wp.Uploader && wp.Uploader.queue) {
            // Hook existing queue uploader
            if (wp.Uploader.queue.uploader) {
                hookPluploadInstance(wp.Uploader.queue.uploader);
            }
        }

        // Check for plupload instances in the global scope
        if (typeof plupload !== 'undefined') {
            // Look for uploaders attached to the page
            document.querySelectorAll('[id*="plupload"]').forEach(el => {
                if (el.plupload) {
                    hookPluploadInstance(el.plupload);
                }
            });
        }

        // Hook into wp.media frames when they open
        if (typeof wp !== 'undefined' && wp.media && !wp.media._vtWrapped) {
            const originalMediaFn = wp.media;

            const wrappedMedia = function() {
                const frame = originalMediaFn.apply(this, arguments);

                if (frame && frame.on) {
                    frame.on('open', function() {
                        // Wait for uploader to be ready
                        setTimeout(() => {
                            tryHookMediaUploader();
                        }, 100);
                    });
                }

                return frame;
            };

            wrappedMedia._vtWrapped = true;

            // Copy over static properties and prototype
            for (const prop in originalMediaFn) {
                if (originalMediaFn.hasOwnProperty(prop)) {
                    wrappedMedia[prop] = originalMediaFn[prop];
                }
            }

            // Preserve prototype chain
            wrappedMedia.prototype = originalMediaFn.prototype;

            wp.media = wrappedMedia;
        }
    }

    /**
     * Try to find and hook the media uploader from various sources
     */
    function tryHookMediaUploader() {
        // Method 1: wp.Uploader.queue
        if (typeof wp !== 'undefined' && wp.Uploader && wp.Uploader.queue && wp.Uploader.queue.uploader) {
            hookPluploadInstance(wp.Uploader.queue.uploader);
        }

        // Method 2: wp.media.frame
        if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
            const frame = wp.media.frame;
            if (frame.uploader && frame.uploader.uploader) {
                hookPluploadInstance(frame.uploader.uploader);
            }
        }

        // Method 3: Look for plupload instances globally
        if (typeof plupload !== 'undefined' && plupload.instances) {
            plupload.instances.forEach(inst => hookPluploadInstance(inst));
        }

        // Method 4: Find plupload container in DOM and get uploader
        const pluploadContainer = document.querySelector('.moxie-shim input[type="file"]');
        if (pluploadContainer) {
            const container = pluploadContainer.closest('.uploader-window, .media-frame');
            if (container && container._plupload) {
                hookPluploadInstance(container._plupload);
            }
        }
    }

    /**
     * Watch for media modal to appear in DOM
     */
    function watchForMediaModal() {
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Check if media modal was added
                        if (node.classList && (
                            node.classList.contains('media-modal') ||
                            node.classList.contains('media-frame')
                        )) {
                            setTimeout(() => tryHookMediaUploader(), 200);
                        }
                        // Also check children
                        const modal = node.querySelector && node.querySelector('.media-modal, .media-frame');
                        if (modal) {
                            setTimeout(() => tryHookMediaUploader(), 200);
                        }
                    }
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Hook wp.Uploader prototype for new instances
     */
    function hookWPUploaderPrototype() {
        if (typeof wp === 'undefined' || !wp.Uploader) return;

        const originalInit = wp.Uploader.prototype.init;
        wp.Uploader.prototype.init = function() {
            originalInit.apply(this, arguments);
            if (this.uploader) {
                hookPluploadInstance(this.uploader);
            }
        };
    }

    // Initialize hooks when ready
    function initWPHooks() {
        hookWPUploaderPrototype();
        findAndHookUploaders();
        watchForMediaModal();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWPHooks);
    } else {
        initWPHooks();
    }

    // Periodic check for late-loaded uploaders
    let checkCount = 0;
    const maxChecks = 20; // 10 seconds
    const wpCheckInterval = setInterval(() => {
        checkCount++;
        if (typeof wp !== 'undefined') {
            hookWPUploaderPrototype();
            findAndHookUploaders();
            tryHookMediaUploader();
        }
        if (checkCount >= maxChecks) {
            clearInterval(wpCheckInterval);
        }
    }, 500);
})();
