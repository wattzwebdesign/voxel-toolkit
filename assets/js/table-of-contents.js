(function($) {
    'use strict';

    /**
     * Table of Contents Active State Handler
     */
    class TableOfContentsHandler {
        constructor() {
            this.syncDebounceTimer = null;
            this.fieldCheckDebounceTimer = null;
            this.init();
        }

        init() {
            // Update active state on page load
            this.updateActiveStates();

            // Listen for URL changes (history API)
            this.watchUrlChanges();

            // Listen for popstate (browser back/forward)
            window.addEventListener('popstate', () => {
                this.updateActiveStates();
            });

            // Sync with Voxel form after it initializes
            this.initVoxelSync();

            // Check field completion status
            this.initFieldTracking();
        }

        /**
         * Initialize sync with Voxel form
         */
        initVoxelSync() {
            // Wait for Voxel to initialize, then sync
            const trySync = (attempts = 0) => {
                if (attempts > 10) return; // Give up after 5 seconds

                const hasVoxelForm = $('.ts-create-post, .ts-form').length > 0;

                if (hasVoxelForm) {
                    this.syncWithVoxelForm();
                    this.observeVoxelForm();
                } else {
                    // Retry in 500ms
                    setTimeout(() => trySync(attempts + 1), 500);
                }
            };

            // Start trying after DOM is ready
            setTimeout(() => trySync(), 500);
        }

        /**
         * Sync TOC visibility with Voxel form's visible steps
         */
        syncWithVoxelForm() {
            const $toc = $('.voxel-table-of-contents');
            if (!$toc.length) return;

            const $tocItems = $toc.find('.voxel-toc-item');
            if (!$tocItems.length) return;

            // Get active steps by checking Vue app data and conditions
            const activeStepKeys = this.getActiveStepKeys();

            if (activeStepKeys.length > 0) {
                $tocItems.each(function() {
                    const $item = $(this);
                    const stepKey = $item.data('step-key');

                    if (activeStepKeys.includes(stepKey)) {
                        $item.removeClass('vt-toc-hidden');
                    } else {
                        $item.addClass('vt-toc-hidden');
                    }
                });
            } else {
                // Can't determine which steps - show all
                $tocItems.removeClass('vt-toc-hidden');
            }

            // Update active state to first visible item if current active is hidden
            this.ensureActiveVisible();
        }

        /**
         * Get active step keys by checking Voxel's Vue app data and evaluating conditions
         */
        getActiveStepKeys() {
            const formElement = document.querySelector('.ts-form.ts-create-post.create-post-form');
            if (!formElement || !formElement.__vue_app__) {
                return [];
            }

            try {
                const form_app = formElement.__vue_app__;
                const node_data = form_app._container._vnode.component.data;

                if (!node_data || !node_data.steps || !node_data.fields) {
                    return [];
                }

                const activeSteps = [];

                // Iterate through all steps and check their conditions
                $.each(node_data.steps, (i, stepKey) => {
                    if (!node_data.fields.hasOwnProperty(stepKey)) {
                        return true; // continue
                    }

                    const step_data = node_data.fields[stepKey];
                    let include_step = true;

                    // Check if step has conditions
                    if (step_data.conditions && step_data.conditions.length) {
                        const behavior = step_data.conditions_behavior || 'show';
                        let conditions_passed = false;

                        // Check if any condition group passes (OR between groups)
                        $.each(step_data.conditions, (k, condition_group) => {
                            let max_conds = condition_group.length;
                            let cur_conds = 0;

                            // Check if all conditions in this group pass (AND within group)
                            $.each(condition_group, (g, condition) => {
                                if (condition._passes) {
                                    cur_conds += 1;
                                }
                            });

                            if (max_conds === cur_conds) {
                                conditions_passed = true;
                                return false; // break - one group passed
                            }
                        });

                        // Apply behavior logic
                        if (behavior === 'hide') {
                            // Hide behavior: if conditions pass, hide the step
                            include_step = !conditions_passed;
                        } else {
                            // Show behavior: if conditions pass, show the step
                            include_step = conditions_passed;
                        }
                    }

                    if (include_step) {
                        activeSteps.push(stepKey);
                    }
                });

                return activeSteps;
            } catch (e) {
                console.warn('VT TOC: Error getting active steps', e);
                return [];
            }
        }

        /**
         * Ensure the active item is visible, otherwise activate first visible
         */
        ensureActiveVisible() {
            $('.voxel-table-of-contents').each((index, container) => {
                const $container = $(container);
                const $activeItem = $container.find('.voxel-toc-item.active');
                const $visibleItems = $container.find('.voxel-toc-item:not(.vt-toc-hidden)');

                // If active item is hidden, activate first visible item
                if ($activeItem.hasClass('vt-toc-hidden') && $visibleItems.length > 0) {
                    $container.find('.voxel-toc-item').removeClass('active');
                    $visibleItems.first().addClass('active');
                }
            });
        }

        /**
         * Observe Voxel form for changes and re-sync
         */
        observeVoxelForm() {
            const formContainer = document.querySelector('.ts-create-post, .ts-form');
            if (!formContainer) return;

            const observer = new MutationObserver(() => {
                // Debounce the sync to prevent excessive updates
                if (this.syncDebounceTimer) {
                    clearTimeout(this.syncDebounceTimer);
                }
                this.syncDebounceTimer = setTimeout(() => {
                    this.syncWithVoxelForm();
                    this.checkFieldCompletion();
                }, 100);
            });

            observer.observe(formContainer, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class']
            });
        }

        /**
         * Initialize field completion tracking
         */
        initFieldTracking() {
            const $toc = $('.voxel-table-of-contents[data-show-fields="true"]');
            if (!$toc.length) return;

            // Wait for Voxel form to initialize
            const tryInit = (attempts = 0) => {
                if (attempts > 20) return; // Give up after 10 seconds

                const hasVoxelForm = $('.ts-create-post, .ts-form').length > 0;

                if (hasVoxelForm) {
                    // Initial check
                    setTimeout(() => this.checkFieldCompletion(), 500);

                    // Listen for form input changes
                    this.observeFieldChanges();
                } else {
                    setTimeout(() => tryInit(attempts + 1), 500);
                }
            };

            setTimeout(() => tryInit(), 500);
        }

        /**
         * Observe field changes for real-time updates
         */
        observeFieldChanges() {
            const formContainer = document.querySelector('.ts-create-post, .ts-form');
            if (!formContainer) return;

            // Listen for input changes
            $(formContainer).on('input change', 'input, textarea, select', () => {
                if (this.fieldCheckDebounceTimer) {
                    clearTimeout(this.fieldCheckDebounceTimer);
                }
                this.fieldCheckDebounceTimer = setTimeout(() => {
                    this.checkFieldCompletion();
                }, 150);
            });

            // Listen for clicks on form elements (for dropdowns, taxonomies, etc.)
            $(formContainer).on('click', '.ts-form-group, .ts-taxonomy-list li, .ts-radio-container', () => {
                if (this.fieldCheckDebounceTimer) {
                    clearTimeout(this.fieldCheckDebounceTimer);
                }
                this.fieldCheckDebounceTimer = setTimeout(() => {
                    this.checkFieldCompletion();
                }, 300);
            });
        }

        /**
         * Check field completion status and update indicators
         */
        checkFieldCompletion() {
            const $tocs = $('.voxel-table-of-contents[data-show-fields="true"]');
            if (!$tocs.length) return;

            $tocs.each((index, tocElement) => {
                const $toc = $(tocElement);
                const $fields = $toc.find('.vt-toc-field');

                $fields.each((i, fieldElement) => {
                    const $field = $(fieldElement);
                    const fieldKey = $field.data('field-key');
                    const fieldType = $field.data('field-type');

                    if (!fieldKey) return;

                    const isFilled = this.isFieldFilled(fieldKey, fieldType);

                    if (isFilled) {
                        $field.addClass('is-filled');
                    } else {
                        $field.removeClass('is-filled');
                    }
                });
            });
        }

        /**
         * Check if a specific field has a value
         */
        isFieldFilled(fieldKey, fieldType) {
            // Voxel uses field-key-{fieldKey} class on field Vue components
            const $field = $(`.field-key-${fieldKey}`);

            if (!$field.length) {
                return false;
            }

            return this.checkFieldValue($field, fieldType, fieldKey);
        }

        /**
         * Check if a field element has a value based on its type
         * Voxel uses Vue.js and specific DOM patterns to indicate filled state
         */
        checkFieldValue($field, fieldType, fieldKey) {
            // First check for Voxel's ts-filled class (used by taxonomy, select, date fields)
            const $tsFilled = $field.find('.ts-filter.ts-filled, .ts-filled');
            if ($tsFilled.length > 0) {
                return true;
            }

            // Check for ts-selected items in taxonomy/select inline mode
            const $selectedItems = $field.find('.ts-selected, li.ts-selected');
            if ($selectedItems.length > 0) {
                return true;
            }

            // Check for checked checkmarks in inline terms
            const $checkedCheckmarks = $field.find('.checkmark').filter(function() {
                return $(this).prev('input').is(':checked');
            });
            if ($checkedCheckmarks.length > 0) {
                return true;
            }

            // Check for various Voxel field types
            switch (fieldType) {
                case 'title':
                case 'text':
                case 'email':
                case 'url':
                case 'phone':
                case 'time':
                    // Text-based fields - check input value
                    const $textInput = $field.find('input[type="text"], input[type="email"], input[type="url"], input[type="tel"]');
                    if ($textInput.length) {
                        const val = $textInput.val();
                        return val && val.trim().length > 0;
                    }
                    break;

                case 'texteditor':
                case 'description':
                    // Texteditor fields - check textarea or contenteditable
                    const $textarea = $field.find('textarea');
                    if ($textarea.length) {
                        const val = $textarea.val();
                        return val && val.trim().length > 0;
                    }
                    // Check for TinyMCE editor (mce-content-body)
                    const $editor = $field.find('.mce-content-body, .editor-container, [contenteditable="true"]');
                    if ($editor.length) {
                        const text = $editor.text().trim();
                        // Also check innerHTML for content
                        const html = $editor.html();
                        return text.length > 0 || (html && html.trim().length > 0 && html !== '<p><br></p>' && html !== '<br>');
                    }
                    break;

                case 'number':
                    const $numberInput = $field.find('input[type="number"], input[type="text"], .ts-stepper-input input');
                    if ($numberInput.length) {
                        const val = $numberInput.first().val();
                        return val !== '' && val !== null && val !== undefined && !isNaN(val);
                    }
                    break;

                case 'taxonomy':
                case 'select':
                case 'multiselect':
                    // Check for ts-filled class on ts-filter (popup mode)
                    if ($field.find('.ts-filter.ts-filled').length > 0) {
                        return true;
                    }
                    // Check for selected items in inline mode
                    if ($field.find('li.ts-selected').length > 0) {
                        return true;
                    }
                    // Check for checked radio/checkbox in inline terms
                    if ($field.find('input[type="radio"]:checked, input[type="checkbox"]:checked').length > 0) {
                        return true;
                    }
                    // Check select element
                    const $select = $field.find('select');
                    if ($select.length) {
                        const val = $select.val();
                        return val && val !== '' && val !== null;
                    }
                    break;

                case 'image':
                case 'file':
                    // Check for uploaded file items (.ts-file elements, NOT .pick-file-input which is the upload button)
                    const $uploadedFiles = $field.find('.ts-file-list .ts-file');
                    if ($uploadedFiles.length > 0) return true;
                    break;

                case 'location':
                    // Check for address input with value
                    const $addressInput = $field.find('input[type="text"]').first();
                    if ($addressInput.length && $addressInput.val() && $addressInput.val().trim().length > 0) {
                        return true;
                    }
                    break;

                case 'switcher':
                    // Check for on/off switch state
                    const $switcher = $field.find('.onoffswitch-checkbox, input[type="checkbox"]');
                    if ($switcher.length && $switcher.is(':checked')) {
                        return true;
                    }
                    break;

                case 'post-relation':
                    // Check for selected related posts
                    if ($field.find('.ts-filter.ts-filled').length > 0) {
                        return true;
                    }
                    // Check for added items
                    const $relatedPosts = $field.find('.ts-relation-picker .ts-selected-item, .selected-item');
                    if ($relatedPosts.length > 0) return true;
                    break;

                case 'product':
                    // Check for base price - the input is inside .input-container with type="number"
                    const $basePriceInput = $field.find('.input-container input[type="number"]').first();
                    if ($basePriceInput.length > 0) {
                        const priceVal = $basePriceInput.val();
                        if (priceVal !== '' && priceVal !== null && priceVal !== undefined && !isNaN(priceVal) && Number(priceVal) > 0) {
                            return true;
                        }
                    }
                    break;

                case 'date':
                case 'recurring-date':
                    // Check for ts-filled class
                    if ($field.find('.ts-filter.ts-filled').length > 0) {
                        return true;
                    }
                    const $dateInput = $field.find('input');
                    if ($dateInput.length && $dateInput.val()) return true;
                    break;

                case 'work-hours':
                    // Check for configured work hours
                    const $workDays = $field.find('.ts-work-hours .work-day-status');
                    if ($workDays.length > 0) return true;
                    break;

                case 'color':
                    const $colorInput = $field.find('input[type="color"], input[type="text"]');
                    if ($colorInput.length && $colorInput.val()) return true;
                    break;

                case 'profile-name':
                    const $nameInput = $field.find('input[type="text"]');
                    if ($nameInput.length && $nameInput.val() && $nameInput.val().trim().length > 0) {
                        return true;
                    }
                    break;

                case 'profile-avatar':
                    // Check for avatar image
                    const $avatar = $field.find('.ts-file, .avatar-preview img, img.ts-status-avatar');
                    if ($avatar.length > 0) return true;
                    break;

                default:
                    // Generic check - look for ts-filled or any filled input
                    if ($field.find('.ts-filled').length > 0) {
                        return true;
                    }
                    const $anyInput = $field.find('input:not([type="hidden"]), textarea, select');
                    if ($anyInput.length) {
                        let hasValue = false;
                        $anyInput.each(function() {
                            const $this = $(this);
                            if ($this.is(':checkbox') || $this.is(':radio')) {
                                if ($this.is(':checked')) {
                                    hasValue = true;
                                    return false;
                                }
                            } else {
                                const val = $this.val();
                                if (val && String(val).trim().length > 0) {
                                    hasValue = true;
                                    return false;
                                }
                            }
                        });
                        return hasValue;
                    }
            }

            return false;
        }

        /**
         * Watch for URL changes using MutationObserver and setInterval
         */
        watchUrlChanges() {
            let lastUrl = location.href;

            // Check URL every 100ms
            setInterval(() => {
                const currentUrl = location.href;
                if (currentUrl !== lastUrl) {
                    lastUrl = currentUrl;
                    this.updateActiveStates();
                    this.syncWithVoxelForm();
                }
            }, 100);

            // Also watch for pushState/replaceState
            const originalPushState = history.pushState;
            const originalReplaceState = history.replaceState;

            history.pushState = (...args) => {
                originalPushState.apply(history, args);
                this.updateActiveStates();
                this.syncWithVoxelForm();
            };

            history.replaceState = (...args) => {
                originalReplaceState.apply(history, args);
                this.updateActiveStates();
                this.syncWithVoxelForm();
            };
        }

        /**
         * Update active states on all TOC widgets
         */
        updateActiveStates() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentStep = urlParams.get('step');

            // Find all TOC widgets
            $('.voxel-table-of-contents').each((index, container) => {
                const $container = $(container);
                const $items = $container.find('.voxel-toc-item:not(.vt-toc-hidden)');

                // If no step in URL, activate first visible item
                if (!currentStep || currentStep === '') {
                    $container.find('.voxel-toc-item').removeClass('active');
                    $items.first().addClass('active');
                    $container.attr('data-current-step', $items.first().attr('data-step-key') || '');
                } else {
                    // Remove active from all items
                    $container.find('.voxel-toc-item').removeClass('active');

                    // Add active to matching item
                    $container.find('.voxel-toc-item').each((i, item) => {
                        const $item = $(item);
                        const stepKey = $item.attr('data-step-key');

                        if (stepKey === currentStep) {
                            $item.addClass('active');
                            $container.attr('data-current-step', currentStep);
                        }
                    });
                }
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new TableOfContentsHandler();
    });

    // Also initialize after Elementor preview loads
    $(window).on('elementor/frontend/init', () => {
        new TableOfContentsHandler();
    });

})(jQuery);
