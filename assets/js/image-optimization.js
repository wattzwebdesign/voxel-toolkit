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
    const processedFiles = new WeakSet();
    let isWorking = false;
    let fileCounter = 0;

    // Check if browser supports WebP encoding
    const supportsWebP = (() => {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        return canvas.toDataURL('image/webp').startsWith('data:image/webp');
    })();

    /**
     * Toast notification manager
     */
    const Toast = {
        el: null,

        show(title, message, type = 'default') {
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
            if (this.el) {
                this.el.querySelector('.vt-image-opt-toast-title').textContent = title;
                this.el.querySelector('.vt-image-opt-toast-message').textContent = message;
            }
        },

        success(title, message) {
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
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
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
            ctx.fillStyle = "rgba(255, 255, 255, 0.7)";
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
                    ctx.globalAlpha = 0.7;
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
                Toast.show('File too large!', `${file.name} exceeds ${Settings.maxFileSizeMB}MB limit.`, 'error');
                return null;
            }

            // Skip non-image files and already processed files
            if (!file.type.match(/^image\/(jpeg|png|webp)$/) || processedFiles.has(file)) {
                return file;
            }

            Toast.update('Optimizing images...', `Image ${index + 1} of ${total}: ${file.name}`);

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

                    // Only convert to WebP if browser supports it
                    if (supportsWebP) {
                        if (mode === 'all_webp') {
                            targetMime = 'image/webp';
                        } else if (mode === 'only_jpg' && file.type === 'image/jpeg') {
                            targetMime = 'image/webp';
                        } else if (mode === 'only_png' && file.type === 'image/png') {
                            targetMime = 'image/webp';
                        } else if (mode === 'both_to_webp' && (file.type === 'image/jpeg' || file.type === 'image/png')) {
                            targetMime = 'image/webp';
                        }
                    }
                    // 'originals_only' keeps the original format

                    canvas.toBlob((blob) => {
                        // Use actual blob type (browser may not support WebP encoding)
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
                    }, targetMime, Settings.outputQuality);
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

        Toast.show('Optimizing...', `Processing ${imageFiles.length} image(s)`);

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

        Toast.success('Done!', `${imageFiles.length} images optimized. Saved: ${ImageOptimizer.formatBytes(totalSaved)}`);

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
})();
