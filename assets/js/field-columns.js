/**
 * Voxel Toolkit - Field Columns
 *
 * Injects a column size picker dropdown next to CSS Classes fields
 * in Voxel's post-type field settings.
 *
 * Also adds column width badges to field headers in the field list.
 */

(function() {
    'use strict';

    // List of column classes to detect and manage
    const VT_COLUMN_CLASSES = ['vx-1-1', 'vx-1-2', 'vx-1-3', 'vx-1-4', 'vx-1-6', 'vx-2-3', 'vx-3-4'];

    // Map column classes to display percentages
    const VT_COLUMN_LABELS = {
        'vx-1-1': '100%',
        'vx-3-4': '75%',
        'vx-2-3': '66%',
        'vx-1-2': '50%',
        'vx-1-3': '33%',
        'vx-1-4': '25%',
        'vx-1-6': '16%'
    };

    /**
     * MutationObserver to watch for field settings modals opening
     */
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) {
                    // Check if this node or its children contain CSS Classes fields
                    const formGroups = node.querySelectorAll ? node.querySelectorAll('.ts-form-group') : [];
                    formGroups.forEach(checkAndInjectPicker);

                    // Also check if the node itself is a form group
                    if (node.classList && node.classList.contains('ts-form-group')) {
                        checkAndInjectPicker(node);
                    }
                }
            });
        });
    });

    /**
     * Check if a form group is the CSS Classes field and inject picker if so
     */
    function checkAndInjectPicker(formGroup) {
        const label = formGroup.querySelector('label');
        if (!label) return;

        // Check if this is the CSS Classes field
        const labelText = label.textContent || label.innerText;

        if (!labelText.includes('CSS Classes')) return;

        // Don't inject if already present
        if (formGroup.querySelector('.vt-column-picker')) return;

        // Find the text input
        const input = formGroup.querySelector('input[type="text"]');
        if (!input) return;

        injectColumnPicker(formGroup, input);
    }

    /**
     * Create and inject the column picker dropdown
     */
    function injectColumnPicker(formGroup, input) {
        // Create wrapper for better layout
        const wrapper = document.createElement('div');
        wrapper.className = 'vt-column-picker-wrapper';

        // Create dropdown
        const select = document.createElement('select');
        select.className = 'vt-column-picker';

        // Add options from localized data
        if (typeof vtFieldColumns !== 'undefined' && vtFieldColumns.columns) {
            Object.entries(vtFieldColumns.columns).forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                select.appendChild(option);
            });
        }

        // Set current value from input
        const currentClass = getCurrentColumnClass(input.value);
        if (currentClass) {
            select.value = currentClass;
        }

        // Handle dropdown change
        select.addEventListener('change', () => {
            updateInputValue(input, select.value);
            // Update badges after a short delay to allow Vue to process the change
            setTimeout(() => {
                if (window.vtUpdateColumnBadges) {
                    window.vtUpdateColumnBadges();
                }
            }, 200);
        });

        // Watch input for manual changes (sync dropdown with manual edits)
        input.addEventListener('input', () => {
            const currentClass = getCurrentColumnClass(input.value);
            select.value = currentClass || '';
        });

        // Also watch for Vue reactivity updates
        const inputObserver = new MutationObserver(() => {
            const currentClass = getCurrentColumnClass(input.value);
            if (select.value !== (currentClass || '')) {
                select.value = currentClass || '';
            }
        });
        inputObserver.observe(input, { attributes: true, attributeFilter: ['value'] });

        // Create label for the dropdown
        const pickerLabel = document.createElement('label');
        pickerLabel.textContent = 'Column Width (VT)';

        wrapper.appendChild(pickerLabel);
        wrapper.appendChild(select);

        // Insert wrapper after the input
        input.parentNode.insertBefore(wrapper, input.nextSibling);
    }

    /**
     * Get the current column class from the input value
     */
    function getCurrentColumnClass(value) {
        if (!value) return '';
        const classes = value.split(/\s+/);
        return classes.find(c => VT_COLUMN_CLASSES.includes(c)) || '';
    }

    /**
     * Update the input value with the new column class
     * Removes any existing column class and adds the new one
     */
    function updateInputValue(input, newColumnClass) {
        // Get current classes, filtering out any existing column classes
        let classes = input.value.split(/\s+/).filter(c => c && !VT_COLUMN_CLASSES.includes(c));

        // Add new column class if selected
        if (newColumnClass) {
            classes.push(newColumnClass);
        }

        // Update input value
        input.value = classes.join(' ');

        // Trigger Vue reactivity by dispatching input event
        input.dispatchEvent(new Event('input', { bubbles: true }));

        // Also try setting via Vue's internal mechanism
        if (input._vModifiers || input.__vueParentComponent) {
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    /**
     * Initialize - start observing and check existing elements
     */
    function init() {
        // Start observing for new elements
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Check any existing form groups (in case modal is already open)
        document.querySelectorAll('.ts-form-group').forEach(checkAndInjectPicker);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /**
     * =====================================================
     * PART 2: Field Head Column Badges
     * =====================================================
     * Adds column width percentage badges to field headers
     * in the field list (e.g., "50%", "33%", etc.)
     */

    /**
     * MutationObserver to watch for field list items being added/modified
     */
    const fieldListObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) {
                    // Check for field items (single-field elements)
                    const fieldItems = node.querySelectorAll ? node.querySelectorAll('.single-field') : [];
                    fieldItems.forEach(injectColumnBadge);

                    // Also check if the node itself is a field item
                    if (node.classList && node.classList.contains('single-field')) {
                        injectColumnBadge(node);
                    }
                }
            });
        });
    });

    /**
     * Get field data from window.PTE (Voxel's Vue app)
     */
    function getFieldData(fieldKey) {
        if (!window.PTE || !window.PTE.config || !window.PTE.config.fields) {
            return null;
        }
        return window.PTE.config.fields.find(f => f.key === fieldKey);
    }

    /**
     * Extract field key from the field-type text (format: "type · key")
     */
    function extractFieldKey(fieldItem) {
        const fieldTypeSpan = fieldItem.querySelector('.field-type');
        if (!fieldTypeSpan) return null;

        const text = fieldTypeSpan.textContent || fieldTypeSpan.innerText;
        // Format is "type · key" - extract the key part
        const parts = text.split('·');
        if (parts.length >= 2) {
            return parts[1].trim();
        }
        return null;
    }

    /**
     * Inject column badge into field header
     */
    function injectColumnBadge(fieldItem) {
        // Don't inject if already present
        if (fieldItem.querySelector('.vt-column-badge')) return;

        const fieldHead = fieldItem.querySelector('.field-head');
        if (!fieldHead) return;

        const fieldActions = fieldHead.querySelector('.field-actions');
        if (!fieldActions) return;

        // Extract field key from DOM
        const fieldKey = extractFieldKey(fieldItem);
        if (!fieldKey) return;

        // Get field data from PTE
        const fieldData = getFieldData(fieldKey);
        if (!fieldData) return;

        // Check if field has a column class in css_class
        const cssClass = fieldData.css_class || '';
        const columnClass = getCurrentColumnClass(cssClass);
        if (!columnClass) return;

        // Get the percentage label
        const percentLabel = VT_COLUMN_LABELS[columnClass];
        if (!percentLabel) return;

        // Create badge element
        const badge = document.createElement('span');
        badge.className = 'field-action all-center vt-column-badge';
        badge.title = 'Column Width (VT)';
        badge.innerHTML = `<span class="vt-column-badge-text">${percentLabel}</span>`;

        // Insert badge at the beginning of field-actions
        fieldActions.insertBefore(badge, fieldActions.firstChild);
    }

    /**
     * Update all badges (called when column picker changes)
     */
    function updateAllBadges() {
        document.querySelectorAll('.single-field').forEach(fieldItem => {
            // Remove existing badge
            const existingBadge = fieldItem.querySelector('.vt-column-badge');
            if (existingBadge) {
                existingBadge.remove();
            }
            // Re-inject badge with updated data
            injectColumnBadge(fieldItem);
        });
    }

    /**
     * Initialize field list observer
     */
    function initFieldListObserver() {
        // Start observing for new field items
        fieldListObserver.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Check existing field items
        document.querySelectorAll('.single-field').forEach(injectColumnBadge);

        // Also set up a periodic check for PTE availability
        // (PTE may not be available immediately on page load)
        let checkCount = 0;
        const checkInterval = setInterval(() => {
            checkCount++;
            if (window.PTE) {
                document.querySelectorAll('.single-field').forEach(fieldItem => {
                    if (!fieldItem.querySelector('.vt-column-badge')) {
                        injectColumnBadge(fieldItem);
                    }
                });

                // Watch for Vue reactivity updates - refresh badges when config changes
                if (window.PTE.$watch) {
                    window.PTE.$watch('config.fields', () => {
                        setTimeout(updateAllBadges, 100);
                    }, { deep: true });
                }

                clearInterval(checkInterval);
            }
            if (checkCount > 50) { // Stop after ~5 seconds
                clearInterval(checkInterval);
            }
        }, 100);
    }

    // Initialize field list observer when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFieldListObserver);
    } else {
        initFieldListObserver();
    }

    // Export updateAllBadges for use from picker change handler
    window.vtUpdateColumnBadges = updateAllBadges;
})();
