/**
 * Voxel Toolkit - Field Columns
 *
 * Injects a column size picker dropdown next to CSS Classes fields
 * in Voxel's post-type field settings.
 */

(function() {
    'use strict';

    // List of column classes to detect and manage
    const VT_COLUMN_CLASSES = ['vx-1-1', 'vx-1-2', 'vx-1-3', 'vx-1-4', 'vx-1-6', 'vx-2-3', 'vx-3-4'];

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
})();
