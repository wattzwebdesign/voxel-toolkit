/**
 * Add Category - Frontend JavaScript
 *
 * Injects "Add new term" UI into Voxel taxonomy field popups and inline displays
 */
(function($) {
    'use strict';

    if (typeof vt_add_category === 'undefined') {
        return;
    }

    const config = vt_add_category;
    const i18n = config.i18n || {};
    const fieldConfigs = config.field_configs || {};

    /**
     * Check if any taxonomy field allows adding terms
     */
    function hasAnyAddTermsEnabled() {
        for (const key in fieldConfigs) {
            if (fieldConfigs[key].allow_add_terms) {
                return true;
            }
        }
        return false;
    }

    /**
     * Initialize Add Category functionality
     */
    function init() {
        if (!hasAnyAddTermsEnabled()) {
            return;
        }

        // Watch for taxonomy popups and inline displays
        observeTaxonomyFields();

        // Also do an initial scan for any already-rendered fields
        setTimeout(scanExistingFields, 500);
    }

    /**
     * Observe for taxonomy fields appearing
     */
    function observeTaxonomyFields() {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType !== 1) return;

                    // Check for popup dropdowns
                    let termDropdowns = [];
                    if (node.classList && node.classList.contains('ts-term-dropdown')) {
                        termDropdowns = [node];
                    } else if (node.querySelectorAll) {
                        termDropdowns = node.querySelectorAll('.ts-term-dropdown');
                    }

                    termDropdowns.forEach(function(dropdown) {
                        setTimeout(function() {
                            handlePopupDropdown(dropdown);
                        }, 100);
                    });

                    // Check for inline term lists
                    let inlineFields = [];
                    if (node.classList && node.classList.contains('ts-term-field-inline')) {
                        inlineFields = [node];
                    } else if (node.querySelectorAll) {
                        inlineFields = node.querySelectorAll('.ts-term-field-inline');
                    }

                    inlineFields.forEach(function(inlineField) {
                        setTimeout(function() {
                            handleInlineField(inlineField);
                        }, 100);
                    });
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Scan for existing fields on page
     */
    function scanExistingFields() {
        // Scan popups
        document.querySelectorAll('.ts-term-dropdown').forEach(function(dropdown) {
            handlePopupDropdown(dropdown);
        });

        // Scan inline fields
        document.querySelectorAll('.ts-term-field-inline').forEach(function(inlineField) {
            handleInlineField(inlineField);
        });

        // Scan form groups that might contain taxonomy fields
        document.querySelectorAll('.ts-form-group').forEach(function(formGroup) {
            checkFormGroupForTaxonomy(formGroup);
        });
    }

    /**
     * Check if a form group contains a taxonomy field that allows adding
     */
    function checkFormGroupForTaxonomy(formGroup) {
        // Skip if already processed
        if (formGroup.querySelector('.vt-add-term-wrapper')) {
            return;
        }

        // Check if this is inside a create post form
        const createPostForm = formGroup.closest('.ts-create-post, .ts-form.create-post-form, [data-post-type]');
        if (!createPostForm) {
            return;
        }

        // Try to get taxonomy config from Vue component
        const taxonomyConfig = getTaxonomyConfigFromElement(formGroup);
        if (!taxonomyConfig || !taxonomyConfig.enabled) {
            return;
        }

        // For inline display, inject directly into the form group
        const termList = formGroup.querySelector('.ts-checkbox-container, .ts-radio-container, .ts-term-list');
        if (termList && !termList.closest('.ts-field-popup')) {
            injectInlineAddTermUI(formGroup, termList, taxonomyConfig);
        }
    }

    /**
     * Handle popup dropdown
     */
    function handlePopupDropdown(dropdown) {
        // Skip if already injected
        if (dropdown.querySelector('.vt-add-term-wrapper')) {
            return;
        }

        // Make sure this is a taxonomy term dropdown
        const termList = dropdown.querySelector('.ts-term-dropdown-list');
        if (!termList) {
            return;
        }

        // Get the form group that triggered this popup
        const formGroup = findRelatedFormGroup(dropdown);
        if (!formGroup) {
            return;
        }

        // Check if this is inside a create post form
        const createPostForm = formGroup.closest('.ts-create-post, .ts-form.create-post-form, [data-post-type]');
        if (!createPostForm) {
            return;
        }

        // Get taxonomy config
        const taxonomyConfig = getTaxonomyConfigFromElement(formGroup);
        if (!taxonomyConfig || !taxonomyConfig.enabled) {
            return;
        }

        injectPopupAddTermUI(dropdown, taxonomyConfig);
    }

    /**
     * Handle inline field display
     */
    function handleInlineField(inlineField) {
        const formGroup = inlineField.closest('.ts-form-group');
        if (!formGroup) {
            return;
        }

        checkFormGroupForTaxonomy(formGroup);
    }

    /**
     * Find the form group related to a popup dropdown
     */
    function findRelatedFormGroup(dropdown) {
        // Check if dropdown is inside a form group
        let formGroup = dropdown.closest('.ts-form-group');
        if (formGroup) {
            return formGroup;
        }

        // For teleported popups, try to find by popup key or other means
        const popup = dropdown.closest('.ts-field-popup');
        if (popup) {
            // Try to find the trigger element by various attributes
            const popupKey = popup.getAttribute('data-popup-key') || popup.getAttribute('data-key') || popup.id;
            if (popupKey) {
                const trigger = document.querySelector(`[data-popup="${popupKey}"], [aria-controls="${popupKey}"], [data-target="${popupKey}"]`);
                if (trigger) {
                    formGroup = trigger.closest('.ts-form-group');
                    if (formGroup) {
                        return formGroup;
                    }
                }
            }
        }

        // Fallback: look for any active popup target in a taxonomy form group
        const activeTargets = document.querySelectorAll('.ts-popup-target.ts-active, .ts-popup-target[aria-expanded="true"]');
        for (const target of activeTargets) {
            formGroup = target.closest('.ts-form-group');
            if (formGroup && getTaxonomyConfigFromElement(formGroup)) {
                return formGroup;
            }
        }

        // Fallback: look for any form group with active state
        const activeGroups = document.querySelectorAll('.ts-form-group.is-active, .ts-form-group:focus-within, .ts-form-group.ts-open');
        for (const group of activeGroups) {
            if (getTaxonomyConfigFromElement(group)) {
                return group;
            }
        }

        // Final fallback: find any taxonomy form group in a create post form
        const createForms = document.querySelectorAll('.ts-create-post, .ts-form.create-post-form, [data-post-type]');
        for (const form of createForms) {
            const groups = form.querySelectorAll('.ts-form-group');
            for (const group of groups) {
                const config = getTaxonomyConfigFromElement(group);
                if (config && config.enabled) {
                    return group;
                }
            }
        }

        return null;
    }

    /**
     * Get taxonomy config from element by traversing Vue components
     */
    function getTaxonomyConfigFromElement(element) {
        if (!element) return null;

        // Walk up to find Vue component with field data
        let el = element;
        let fieldKey = null;
        let taxonomy = null;

        while (el) {
            // Check Vue 3 style (__vueParentComponent)
            if (el.__vueParentComponent) {
                const component = el.__vueParentComponent;
                const field = component.props?.field || component.ctx?.field;
                if (field) {
                    // Check for our custom vt_add_category prop
                    if (field.props && field.props.vt_add_category) {
                        return field.props.vt_add_category;
                    }
                    // Capture field key for fallback
                    if (field.key) {
                        fieldKey = field.key;
                    }
                    if (field.props && field.props.taxonomy) {
                        taxonomy = field.props.taxonomy;
                    }
                }
            }

            // Check Vue 2 style (__vue__)
            if (el.__vue__) {
                const vue = el.__vue__;

                // Direct field access
                if (vue.field) {
                    if (vue.field.props && vue.field.props.vt_add_category) {
                        return vue.field.props.vt_add_category;
                    }
                    if (vue.field.key) {
                        fieldKey = vue.field.key;
                    }
                    if (vue.field.props && vue.field.props.taxonomy) {
                        taxonomy = vue.field.props.taxonomy;
                    }
                }

                // $props style
                if (vue.$props && vue.$props.field) {
                    if (vue.$props.field.props && vue.$props.field.props.vt_add_category) {
                        return vue.$props.field.props.vt_add_category;
                    }
                    if (vue.$props.field.key) {
                        fieldKey = vue.$props.field.key;
                    }
                }

                // Check for field directly on component (some Voxel components)
                if (vue.$data && vue.$data.field) {
                    if (vue.$data.field.key) {
                        fieldKey = vue.$data.field.key;
                    }
                }
            }

            el = el.parentElement;
        }

        // Fallback: try to match by form group data attributes or classes
        const formGroup = element.closest('.ts-form-group');
        if (formGroup) {
            // Try various ways to get field key
            if (!fieldKey) {
                // Method 1: data-field-key attribute
                fieldKey = formGroup.getAttribute('data-field-key');
            }
            if (!fieldKey) {
                // Method 2: class pattern field-key-{key}
                const fieldKeyMatch = formGroup.className.match(/field-key-([^\s]+)/);
                if (fieldKeyMatch) {
                    fieldKey = fieldKeyMatch[1];
                }
            }
            if (!fieldKey) {
                // Method 3: Look for input with name containing field key
                const input = formGroup.querySelector('input[name], select[name]');
                if (input && input.name) {
                    const nameMatch = input.name.match(/\[([^\]]+)\]/);
                    if (nameMatch) {
                        fieldKey = nameMatch[1];
                    }
                }
            }
        }

        // If we found a field key, check our configs
        if (fieldKey && fieldConfigs[fieldKey] && fieldConfigs[fieldKey].allow_add_terms) {
            const cfg = fieldConfigs[fieldKey];
            return {
                enabled: true,
                taxonomy: cfg.taxonomy,
                field_key: fieldKey,
                post_type: cfg.post_type,
                require_approval: cfg.require_approval
            };
        }

        // Final fallback: if there's only one taxonomy field with add terms enabled,
        // assume this is the one
        const enabledFields = Object.keys(fieldConfigs).filter(k => fieldConfigs[k].allow_add_terms);
        if (enabledFields.length === 1) {
            const key = enabledFields[0];
            const cfg = fieldConfigs[key];
            return {
                enabled: true,
                taxonomy: cfg.taxonomy,
                field_key: key,
                post_type: cfg.post_type,
                require_approval: cfg.require_approval
            };
        }

        return null;
    }

    /**
     * Inject Add Term UI into a popup dropdown
     */
    function injectPopupAddTermUI(dropdown, taxonomyConfig) {
        const addTermWrapper = document.createElement('div');
        addTermWrapper.className = 'vt-add-term-wrapper';
        addTermWrapper.setAttribute('data-taxonomy', taxonomyConfig.taxonomy);
        addTermWrapper.setAttribute('data-field-key', taxonomyConfig.field_key);
        addTermWrapper.setAttribute('data-post-type', taxonomyConfig.post_type);
        addTermWrapper.innerHTML = createAddTermHTML();

        // Find where to insert - after the search/sticky top
        const stickyTop = dropdown.querySelector('.ts-sticky-top');
        const termList = dropdown.querySelector('.ts-term-dropdown-list');

        if (stickyTop && stickyTop.nextSibling) {
            stickyTop.parentNode.insertBefore(addTermWrapper, stickyTop.nextSibling);
        } else if (termList) {
            termList.parentNode.insertBefore(addTermWrapper, termList);
        } else {
            dropdown.insertBefore(addTermWrapper, dropdown.firstChild);
        }

        bindAddTermEvents(addTermWrapper, taxonomyConfig, dropdown);
    }

    /**
     * Inject Add Term UI into an inline field display
     */
    function injectInlineAddTermUI(formGroup, termList, taxonomyConfig) {
        // Skip if already injected
        if (formGroup.querySelector('.vt-add-term-wrapper')) {
            return;
        }

        const addTermWrapper = document.createElement('div');
        addTermWrapper.className = 'vt-add-term-wrapper vt-add-term-inline';
        addTermWrapper.setAttribute('data-taxonomy', taxonomyConfig.taxonomy);
        addTermWrapper.setAttribute('data-field-key', taxonomyConfig.field_key);
        addTermWrapper.setAttribute('data-post-type', taxonomyConfig.post_type);
        addTermWrapper.innerHTML = createAddTermHTML();

        // Insert after the term list
        termList.parentNode.insertBefore(addTermWrapper, termList.nextSibling);

        bindAddTermEvents(addTermWrapper, taxonomyConfig, null);
    }

    /**
     * Create HTML for add term UI
     */
    function createAddTermHTML() {
        return `
            <div class="vt-add-term-trigger">
                <a href="#" class="flexify vt-add-term-btn">
                    <span class="vt-add-icon">+</span>
                    <span>${i18n.add_new || 'Add new'}</span>
                </a>
            </div>
            <div class="vt-add-term-form" style="display: none;">
                <div class="vt-add-term-form-inner">
                    <input type="text"
                           class="vt-add-term-name"
                           placeholder="${i18n.name_placeholder || 'Term name'}"
                           autocomplete="off">
                    <textarea class="vt-add-term-description"
                              placeholder="${i18n.description_placeholder || 'Description (optional)'}"
                              rows="2"></textarea>
                    <input type="hidden" class="vt-add-term-parent" value="0">
                    <div class="vt-add-term-actions">
                        <button type="button" class="vt-add-term-cancel">${i18n.cancel_button || 'Cancel'}</button>
                        <button type="button" class="vt-add-term-submit">${i18n.add_button || 'Add'}</button>
                    </div>
                    <div class="vt-add-term-message" style="display: none;"></div>
                </div>
            </div>
        `;
    }

    /**
     * Bind events to add term UI
     */
    function bindAddTermEvents(wrapper, taxonomyConfig, dropdown) {
        const trigger = wrapper.querySelector('.vt-add-term-trigger');
        const form = wrapper.querySelector('.vt-add-term-form');
        const nameInput = wrapper.querySelector('.vt-add-term-name');
        const descInput = wrapper.querySelector('.vt-add-term-description');
        const parentInput = wrapper.querySelector('.vt-add-term-parent');
        const cancelBtn = wrapper.querySelector('.vt-add-term-cancel');
        const submitBtn = wrapper.querySelector('.vt-add-term-submit');
        const messageEl = wrapper.querySelector('.vt-add-term-message');

        // Toggle form visibility
        trigger.querySelector('.vt-add-term-btn').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            trigger.style.display = 'none';
            form.style.display = 'block';
            nameInput.focus();

            // Get current parent term if navigated into a sub-term
            if (dropdown) {
                const currentParent = getCurrentParentTerm(dropdown);
                parentInput.value = currentParent || 0;
            }
        });

        // Cancel
        cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            resetForm();
        });

        // Submit
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            submitNewTerm();
        });

        // Submit on Enter in name field
        nameInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitNewTerm();
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                resetForm();
            }
        });

        // Prevent popup from closing when clicking form
        form.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        function resetForm() {
            nameInput.value = '';
            descInput.value = '';
            parentInput.value = '0';
            messageEl.style.display = 'none';
            messageEl.className = 'vt-add-term-message';
            form.style.display = 'none';
            trigger.style.display = 'block';
        }

        function showMessage(text, type) {
            messageEl.textContent = text;
            messageEl.className = 'vt-add-term-message vt-message-' + type;
            messageEl.style.display = 'block';
        }

        function submitNewTerm() {
            const name = nameInput.value.trim();
            const description = descInput.value.trim();
            const parent = parseInt(parentInput.value) || 0;

            if (!name) {
                showMessage(i18n.error_empty_name || 'Please enter a term name', 'error');
                nameInput.focus();
                return;
            }

            // Disable form
            submitBtn.disabled = true;
            submitBtn.textContent = i18n.adding || 'Adding...';

            // Make AJAX request
            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'vt_add_taxonomy_term',
                    nonce: config.nonce,
                    term_name: name,
                    term_description: description,
                    taxonomy: taxonomyConfig.taxonomy,
                    parent_id: parent,
                    field_key: taxonomyConfig.field_key,
                    post_type: taxonomyConfig.post_type
                },
                success: function(response) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = i18n.add_button || 'Add';

                    if (response.success) {
                        const term = response.data.term;

                        // Show success message
                        const msg = term.pending
                            ? (i18n.success_pending || 'Term submitted for approval. Once approved, you can edit your post to add it.')
                            : (i18n.success_added || 'Term added successfully');
                        showMessage(msg, 'success');

                        // If not pending, try to select the term
                        if (!term.pending) {
                            selectNewTerm(wrapper, term);
                        }

                        // Reset form after short delay
                        setTimeout(function() {
                            resetForm();
                        }, 2000);
                    } else {
                        showMessage(response.data.message || 'Error adding term', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('VT Add Category: AJAX error', error);
                    submitBtn.disabled = false;
                    submitBtn.textContent = i18n.add_button || 'Add';
                    showMessage('Network error. Please try again.', 'error');
                }
            });
        }
    }

    /**
     * Get current parent term ID if user has navigated into a subcategory
     */
    function getCurrentParentTerm(dropdown) {
        // Look for the "Go back" button which indicates we're in a sub-level
        const goBackBtn = dropdown.querySelector('.ts-term-centered a[href="#"]');
        if (!goBackBtn) {
            return 0;
        }

        // Try to find the parent term from the parent-item
        const parentItem = dropdown.querySelector('.ts-parent-item');
        if (parentItem) {
            const checkbox = parentItem.querySelector('input[type="checkbox"], input[type="radio"]');
            if (checkbox && checkbox.value) {
                // We have the slug, need ID - server will handle this
            }
        }

        return 0;
    }

    /**
     * Select the newly added term in the field
     */
    function selectNewTerm(wrapper, term) {
        const formGroup = wrapper.closest('.ts-form-group');
        if (!formGroup) return;

        let el = formGroup;
        while (el) {
            if (el.__vue__) {
                const vue = el.__vue__;
                // Try selectTerm method
                if (vue.selectTerm) {
                    vue.selectTerm({
                        id: term.id,
                        slug: term.slug,
                        label: term.name,
                        icon: ''
                    });
                    return;
                }
                // Try adding to value directly
                if (vue.value && typeof vue.value === 'object') {
                    vue.value[term.slug] = {
                        id: term.id,
                        label: term.name,
                        slug: term.slug,
                        icon: ''
                    };
                    return;
                }
            }
            el = el.parentElement;
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(jQuery);
