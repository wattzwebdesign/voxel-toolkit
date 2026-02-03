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

            // Sync field-level conditional visibility
            this.syncFieldConditions();
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
                // Silently fail if Vue structure changed
                return [];
            }
        }

        /**
         * Get visible field keys by checking Voxel's Vue app data
         * For conditional fields, check if Voxel is showing them in the DOM
         */
        getVisibleFieldKeys() {
            const formElement = document.querySelector('.ts-form.ts-create-post.create-post-form');
            if (!formElement || !formElement.__vue_app__) {
                return [];
            }

            try {
                const form_app = formElement.__vue_app__;
                const node_data = form_app._container._vnode.component.data;

                if (!node_data || !node_data.fields) {
                    return [];
                }

                const visibleFields = [];

                // Iterate through all fields
                $.each(node_data.fields, (fieldKey, field_data) => {
                    // Skip step-type fields (they're handled separately)
                    if (field_data.type === 'ui-step') {
                        return true; // continue
                    }

                    let include_field = true;

                    // Check if field has conditions
                    if (field_data.conditions && field_data.conditions.length) {
                        // For conditional fields, check if Voxel is showing them in the DOM
                        // This leverages Voxel's own condition evaluation
                        const $formField = $(`.field-key-${fieldKey}`);

                        if ($formField.length > 0) {
                            // Field exists in DOM - check if it's visible
                            include_field = $formField.is(':visible') && $formField.css('display') !== 'none';
                        } else {
                            // Field not in DOM - Voxel hasn't rendered it, so condition not met
                            include_field = false;
                        }
                    }
                    // Fields without conditions are always included

                    if (include_field) {
                        visibleFields.push(fieldKey);
                    }
                });

                return visibleFields;
            } catch (e) {
                // Silently fail if Vue structure changed
                return [];
            }
        }

        /**
         * Evaluate a condition ourselves as fallback when _passes isn't updated
         */
        evaluateCondition(sourceField, conditionType, sourceFieldKey) {
            if (!sourceField) return false;

            // Get the field value - check multiple possible locations
            let fieldValue = sourceField.value;

            // For some field types, value might be in props
            if ((fieldValue === null || fieldValue === undefined) && sourceField.props) {
                fieldValue = sourceField.props.value || sourceField.props.modelValue;
            }

            // Check for 'input' property (Voxel might store current input here)
            if ((fieldValue === null || fieldValue === undefined || fieldValue === '') && sourceField.input !== undefined) {
                fieldValue = sourceField.input;
            }

            // Check for 'modelValue' directly on field
            if ((fieldValue === null || fieldValue === undefined || fieldValue === '') && sourceField.modelValue !== undefined) {
                fieldValue = sourceField.modelValue;
            }

            // If still null, check if the source field's TOC indicator shows filled
            // This leverages the existing completion detection which IS working
            if ((fieldValue === null || fieldValue === undefined || fieldValue === '') && sourceFieldKey) {
                const $tocField = $(`.vt-toc-field[data-field-key="${sourceFieldKey}"]`);
                if ($tocField.length && $tocField.hasClass('is-filled')) {
                    fieldValue = '__HAS_VALUE__';
                }
            }

            // Parse condition type (e.g., "text:not_empty", "text:empty", "number:gt:5")
            const parts = conditionType.split(':');
            const operator = parts[1];
            const compareValue = parts[2];

            switch (operator) {
                case 'not_empty':
                    if (fieldValue === null || fieldValue === undefined) return false;
                    if (typeof fieldValue === 'string') return fieldValue.trim().length > 0;
                    if (Array.isArray(fieldValue)) return fieldValue.length > 0;
                    if (typeof fieldValue === 'object') return Object.keys(fieldValue).length > 0;
                    return !!fieldValue;

                case 'empty':
                    if (fieldValue === null || fieldValue === undefined) return true;
                    if (typeof fieldValue === 'string') return fieldValue.trim().length === 0;
                    if (Array.isArray(fieldValue)) return fieldValue.length === 0;
                    return !fieldValue;

                case 'equals':
                case 'eq':
                    return String(fieldValue) === String(compareValue);

                case 'not_equals':
                case 'neq':
                    return String(fieldValue) !== String(compareValue);

                case 'gt':
                    return Number(fieldValue) > Number(compareValue);

                case 'gte':
                    return Number(fieldValue) >= Number(compareValue);

                case 'lt':
                    return Number(fieldValue) < Number(compareValue);

                case 'lte':
                    return Number(fieldValue) <= Number(compareValue);

                case 'checked':
                    return fieldValue === true || fieldValue === 1 || fieldValue === '1';

                case 'unchecked':
                    return fieldValue === false || fieldValue === 0 || fieldValue === '0' || !fieldValue;

                default:
                    // For unknown operators, fall back to checking if field has any value
                    return fieldValue !== null && fieldValue !== undefined && fieldValue !== '';
            }
        }

        /**
         * Sync field visibility in TOC based on Voxel conditional logic
         */
        syncFieldConditions() {
            const $tocs = $('.voxel-table-of-contents[data-show-fields="true"]');
            if (!$tocs.length) return;

            const visibleFieldKeys = this.getVisibleFieldKeys();

            // If we couldn't determine visibility, show all fields
            if (visibleFieldKeys.length === 0) {
                $tocs.find('.vt-toc-field').removeClass('vt-toc-hidden');
                return;
            }

            $tocs.each((index, tocElement) => {
                const $toc = $(tocElement);
                const $fields = $toc.find('.vt-toc-field');

                $fields.each((i, fieldElement) => {
                    const $field = $(fieldElement);
                    const fieldKey = $field.data('field-key');

                    if (!fieldKey) return;

                    if (visibleFieldKeys.includes(fieldKey)) {
                        $field.removeClass('vt-toc-hidden');
                    } else {
                        $field.addClass('vt-toc-hidden');
                    }
                });
            });
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
                // Use 250ms to give Vue time to finish rendering conditional fields
                if (this.syncDebounceTimer) {
                    clearTimeout(this.syncDebounceTimer);
                }
                this.syncDebounceTimer = setTimeout(() => {
                    this.syncWithVoxelForm();
                    this.checkFieldCompletion();
                    this.syncFieldConditions();
                }, 250);
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

                    // Also poll Vue data periodically for real-time updates
                    // Vue.js doesn't always fire DOM events when values change through reactivity
                    this.startFieldPolling();
                } else {
                    setTimeout(() => tryInit(attempts + 1), 500);
                }
            };

            setTimeout(() => tryInit(), 500);
        }

        /**
         * Poll Vue data for field changes (real-time updates)
         */
        startFieldPolling() {
            // Check every 300ms for Vue data changes
            setInterval(() => {
                this.checkFieldCompletionFromVue();
                this.syncFieldConditions();
            }, 300);
        }

        /**
         * Check field completion from Vue data directly
         */
        checkFieldCompletionFromVue() {
            const $tocs = $('.voxel-table-of-contents[data-show-fields="true"]');
            if (!$tocs.length) return;

            const formElement = document.querySelector('.ts-form.ts-create-post.create-post-form');
            if (!formElement || !formElement.__vue_app__) return;

            try {
                const form_app = formElement.__vue_app__;
                const node_data = form_app._container._vnode.component.data;

                if (!node_data || !node_data.fields) return;

                $tocs.each((index, tocElement) => {
                    const $toc = $(tocElement);
                    const $fields = $toc.find('.vt-toc-field');

                    $fields.each((i, fieldElement) => {
                        const $field = $(fieldElement);
                        const fieldKey = $field.data('field-key');

                        if (!fieldKey || !node_data.fields[fieldKey]) return;

                        const fieldData = node_data.fields[fieldKey];
                        const isFilled = this.isFieldFilledFromVueData(fieldData, fieldKey);

                        if (isFilled) {
                            $field.addClass('is-filled');
                        } else {
                            $field.removeClass('is-filled');
                        }
                    });
                });
            } catch (e) {
                // Silently fail - Vue structure may have changed
            }
        }

        /**
         * Check if field has value based on Vue data
         */
        isFieldFilledFromVueData(fieldData, fieldKey) {
            if (!fieldData) return false;

            const fieldType = fieldData.type;

            // Handle repeater fields FIRST - they have empty 'value' but items in props.rows
            if (fieldType === 'repeater') {
                if (fieldData.props && fieldData.props.rows && Array.isArray(fieldData.props.rows) && fieldData.props.rows.length > 0) {
                    return true;
                }
                return false;
            }

            // Check for 'value' property which Voxel uses for most fields
            if (fieldData.hasOwnProperty('value')) {
                const value = fieldData.value;

                // Handle different value types
                if (value === null || value === undefined) return false;
                if (typeof value === 'string') return value.trim().length > 0;
                if (typeof value === 'number') return true;
                if (typeof value === 'boolean') return value;
                if (Array.isArray(value)) return value.length > 0;
                if (typeof value === 'object') return Object.keys(value).length > 0;
            }

            // Check for 'selected' property (used by taxonomy/select fields)
            if (fieldData.hasOwnProperty('selected')) {
                const selected = fieldData.selected;
                if (Array.isArray(selected)) return selected.length > 0;
                if (selected) return true;
            }

            // Check for 'files' property (used by image/file fields)
            if (fieldData.hasOwnProperty('files')) {
                const files = fieldData.files;
                if (Array.isArray(files)) return files.length > 0;
            }

            // Check for 'address' property (used by location field)
            if (fieldData.hasOwnProperty('address')) {
                return fieldData.address && fieldData.address.trim().length > 0;
            }

            // Check for 'items' property (used by repeater fields)
            if (fieldData.items && Array.isArray(fieldData.items) && fieldData.items.length > 0) {
                return true;
            }

            // Check for 'rows' property (also used by repeater fields)
            if (fieldData.rows && Array.isArray(fieldData.rows) && fieldData.rows.length > 0) {
                return true;
            }

            // Check for repeater items inside 'props' object
            // Vue Proxy objects need direct property access, not hasOwnProperty
            if (fieldData.props) {
                const props = fieldData.props;
                // Check rows first (most common for repeaters)
                if (props.rows && Array.isArray(props.rows) && props.rows.length > 0) {
                    return true;
                }
                if (props.items && Array.isArray(props.items) && props.items.length > 0) {
                    return true;
                }
                if (props.value && Array.isArray(props.value) && props.value.length > 0) {
                    return true;
                }
            }

            return false;
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
                    this.syncFieldConditions();
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

                case 'repeater':
                    // Check for repeater items in DOM - Voxel uses .ts-repeater-container children
                    const $repeaterContainer = $field.find('.ts-repeater-container');
                    if ($repeaterContainer.length && $repeaterContainer.children().length > 0) {
                        return true;
                    }
                    // Fallback: check for various repeater item classes
                    const $repeaterItems = $field.find('.ts-repeater-item, .ts-object-list-item, .ts-field-repeater-item');
                    if ($repeaterItems.length > 0) {
                        return true;
                    }
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
                    this.syncFieldConditions();
                }
            }, 100);

            // Also watch for pushState/replaceState
            const originalPushState = history.pushState;
            const originalReplaceState = history.replaceState;

            history.pushState = (...args) => {
                originalPushState.apply(history, args);
                this.updateActiveStates();
                this.syncWithVoxelForm();
                this.syncFieldConditions();
            };

            history.replaceState = (...args) => {
                originalReplaceState.apply(history, args);
                this.updateActiveStates();
                this.syncWithVoxelForm();
                this.syncFieldConditions();
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
