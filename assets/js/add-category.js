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

    // Track the last clicked taxonomy form group (for teleported popups)
    let lastClickedTaxonomyFormGroup = null;

    /**
     * Setup click tracking for taxonomy popup triggers
     * This helps us identify which form group opened a teleported popup
     */
    function setupClickTracking() {
        // Track click handler to capture form group before popup opens
        const trackClick = function(e) {
            // Check if clicked element or its parent is a popup trigger
            const popupTrigger = e.target.closest('.ts-popup-target, [data-popup], .ts-filter-trigger, .ts-field-popup-trigger');
            if (!popupTrigger) return;

            // Find the form group containing this trigger
            const formGroup = popupTrigger.closest('.ts-form-group');
            if (!formGroup) return;

            // Check if this is in a create post form
            const createPostForm = formGroup.closest('.ts-create-post, .ts-form.create-post-form, [data-post-type]');
            if (!createPostForm) return;

            // Get field key and check if it's a taxonomy field with add_terms enabled
            const fieldKey = getFieldKeyFromFormGroup(formGroup);
            const postType = getFormPostType(formGroup);

            if (fieldKey) {
                // Check if this field has add_terms enabled (using compound key or fallback)
                const configKey = getConfigKey(postType, fieldKey);
                let hasAddTerms = false;

                if (configKey && fieldConfigs[configKey] && fieldConfigs[configKey].allow_add_terms) {
                    hasAddTerms = true;
                } else {
                    // Fallback: search all configs for matching field_key
                    for (const key in fieldConfigs) {
                        if (fieldConfigs[key].field_key === fieldKey && fieldConfigs[key].allow_add_terms) {
                            hasAddTerms = true;
                            break;
                        }
                    }
                }

                if (hasAddTerms) {
                    lastClickedTaxonomyFormGroup = formGroup;
                }
            }
        };

        // Listen to both click and mousedown to catch all popup triggers
        // Some custom popup implementations may use mousedown instead of click
        document.addEventListener('click', trackClick, true);
        document.addEventListener('mousedown', trackClick, true);
    }

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

        // Setup click tracking for teleported popups
        setupClickTracking();

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

                    // Check for inline term lists (inline-terms-wrapper or ts-inline-filter)
                    let inlineFields = [];
                    if (node.classList && (node.classList.contains('inline-terms-wrapper') || node.classList.contains('ts-inline-filter'))) {
                        inlineFields = [node];
                    } else if (node.querySelectorAll) {
                        inlineFields = node.querySelectorAll('.inline-terms-wrapper, .ts-inline-filter');
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
        // Only scan within create post forms
        const createForms = document.querySelectorAll('.ts-create-post, .ts-form.create-post-form');

        if (createForms.length === 0) {
            return;
        }

        createForms.forEach(function(form) {
            // Scan popups within form
            form.querySelectorAll('.ts-term-dropdown').forEach(function(dropdown) {
                handlePopupDropdown(dropdown);
            });

            // Scan inline fields with expanded selectors
            const inlineSelectors = [
                '.inline-terms-wrapper',
                '.ts-inline-filter',
                '.ts-checkbox-container',
                '.ts-radio-container',
                '.ts-term-list',
                '.ts-filter-options',
                '[data-field-type="taxonomy"]'
            ].join(', ');

            const inlineFields = form.querySelectorAll(inlineSelectors);
            inlineFields.forEach(function(inlineField) {
                handleInlineField(inlineField);
            });

            // Scan ALL form groups to find taxonomy fields
            const formGroups = form.querySelectorAll('.ts-form-group');
            formGroups.forEach(function(formGroup) {
                checkFormGroupForTaxonomy(formGroup);
            });
        });
    }

    /**
     * Check if a form group contains a taxonomy field that allows adding
     */
    function checkFormGroupForTaxonomy(formGroup) {
        const fieldKey = getFieldKeyFromFormGroup(formGroup);

        // Skip if already processed
        if (formGroup.querySelector('.vt-add-term-wrapper')) {
            return;
        }

        // Check if this is inside a create post form
        const createPostForm = formGroup.closest('.ts-create-post, .ts-form.create-post-form');
        if (!createPostForm) {
            return;
        }

        // Try to get taxonomy config
        const taxonomyConfig = getTaxonomyConfigFromElement(formGroup);

        if (!taxonomyConfig || !taxonomyConfig.enabled) {
            return;
        }

        // For inline display, find the term list container
        const termListSelectors = '.ts-checkbox-container, .ts-radio-container, .ts-term-list, .inline-terms-wrapper ul, .ts-filter-options, .field-inlined-terms, ul.ts-term-list';
        const termList = formGroup.querySelector(termListSelectors);

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

        const fieldKey = getFieldKeyFromFormGroup(formGroup);

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
        // The inline field might BE the form group, or be inside one
        let formGroup = inlineField;
        if (!inlineField.classList.contains('ts-form-group')) {
            formGroup = inlineField.closest('.ts-form-group');
        }
        if (!formGroup) {
            return;
        }

        checkFormGroupForTaxonomy(formGroup);
    }

    /**
     * Find the form group related to a popup dropdown
     */
    function findRelatedFormGroup(dropdown) {
        // Check if dropdown is inside a form group (non-teleported)
        let formGroup = dropdown.closest('.ts-form-group');
        if (formGroup) {
            // Verify it's in a create post form
            const createPostForm = formGroup.closest('.ts-create-post, .ts-form.create-post-form');
            if (createPostForm && getFieldKeyFromFormGroup(formGroup)) {
                return formGroup;
            }
        }

        // For teleported popups, try to find by popup key
        const popup = dropdown.closest('.ts-field-popup, .ts-popup');
        if (popup) {
            // Method 1: Check for popup-key attribute with multiple patterns
            const popupKey = popup.getAttribute('data-popup-key') ||
                             popup.getAttribute('data-key') ||
                             popup.id ||
                             popup.getAttribute('ref');

            if (popupKey) {
                // Try multiple selector patterns for triggers
                const triggerSelectors = [
                    `[data-popup="${popupKey}"]`,
                    `[aria-controls="${popupKey}"]`,
                    `[data-target="${popupKey}"]`,
                    `.ts-popup-target[ref="${popupKey}"]`,
                    `[data-popup-key="${popupKey}"]`
                ];

                for (const selector of triggerSelectors) {
                    try {
                        const trigger = document.querySelector(selector);
                        if (trigger) {
                            formGroup = trigger.closest('.ts-form-group');
                            if (formGroup) {
                                const createPostForm = formGroup.closest('.ts-create-post, .ts-form.create-post-form');
                                if (createPostForm && getFieldKeyFromFormGroup(formGroup)) {
                                    return formGroup;
                                }
                            }
                        }
                    } catch (e) {
                        // Selector may be invalid, continue to next
                    }
                }
            }

            // Method 2: Check Vue component for field reference
            let el = popup;
            while (el) {
                if (el.__vueParentComponent || el.__vue__) {
                    const component = el.__vueParentComponent || el.__vue__;
                    const field = component?.props?.field ||
                                  component?.ctx?.field ||
                                  component?.$props?.field;
                    if (field && field.key) {
                        // Found field key from Vue - search for matching form group
                        const createForms = document.querySelectorAll('.ts-create-post, .ts-form.create-post-form');
                        for (const form of createForms) {
                            const fg = form.querySelector(`.ts-form-group.field-key-${field.key}`);
                            if (fg) {
                                return fg;
                            }
                        }
                    }
                }
                el = el.parentElement;
            }
        }

        // Method 3: Try finding by looking for open/active state on form groups
        const createForms = document.querySelectorAll('.ts-create-post, .ts-form.create-post-form');
        for (const form of createForms) {
            // Look for form group with active popup state
            const activeFormGroups = form.querySelectorAll('.ts-form-group.ts-active, .ts-form-group.popup-open, .ts-form-group[data-popup-open="true"]');
            for (const fg of activeFormGroups) {
                const fieldKey = getFieldKeyFromFormGroup(fg);
                if (fieldKey) {
                    // Search through all configs for matching field_key with allow_add_terms
                    for (const key in fieldConfigs) {
                        const cfg = fieldConfigs[key];
                        if (cfg.field_key === fieldKey && cfg.allow_add_terms) {
                            return fg;
                        }
                    }
                }
            }
        }

        // Method 4: Fallback to last clicked taxonomy form group (for teleported popups)
        if (lastClickedTaxonomyFormGroup) {
            // Verify it's still valid (in DOM and has a field key)
            if (document.body.contains(lastClickedTaxonomyFormGroup)) {
                const fieldKey = getFieldKeyFromFormGroup(lastClickedTaxonomyFormGroup);
                if (fieldKey) {
                    return lastClickedTaxonomyFormGroup;
                }
            }
        }

        // No aggressive fallbacks - return null if we can't definitively find the form group
        return null;
    }

    /**
     * Get field key from form group's class name
     */
    function getFieldKeyFromFormGroup(formGroup) {
        if (!formGroup) return null;

        // Look for field-key-{key} class pattern - stop at space or period
        const match = formGroup.className.match(/field-key-([^\s.]+)/);
        if (match) {
            return match[1];
        }

        // Try data attribute
        return formGroup.getAttribute('data-field-key');
    }

    /**
     * Get post type from the create post form
     */
    function getFormPostType(element) {
        if (!element) return null;

        // Find the create post form
        const form = element.closest('.ts-create-post, .ts-form.create-post-form, [data-post-type]');
        if (!form) return null;

        // Try data-post-type attribute
        if (form.dataset.postType) {
            return form.dataset.postType;
        }

        // Try to find Vue component with post type info
        let el = form;
        while (el) {
            // Vue 3
            if (el.__vueParentComponent) {
                const component = el.__vueParentComponent;
                const postType = component.props?.postType ||
                                 component.ctx?.postType ||
                                 component.props?.post_type ||
                                 component.ctx?.post_type;
                if (postType) return postType;

                // Check for config object
                const config = component.props?.config || component.ctx?.config;
                if (config && config.post_type) return config.post_type;

                // Check for postType in exposed/setupState
                if (component.exposed?.postType) return component.exposed.postType;
                if (component.setupState?.postType) return component.setupState.postType;
            }

            // Vue 2
            if (el.__vue__) {
                const vue = el.__vue__;
                if (vue.postType) return vue.postType;
                if (vue.post_type) return vue.post_type;
                if (vue.$props?.postType) return vue.$props.postType;
                if (vue.$props?.post_type) return vue.$props.post_type;
                if (vue.config?.post_type) return vue.config.post_type;

                // Check $data
                if (vue.$data?.postType) return vue.$data.postType;
                if (vue.$data?.post_type) return vue.$data.post_type;
            }

            el = el.parentElement;
        }

        // Try to find from form action or hidden input
        const hiddenInput = form.querySelector('input[name="post_type"], input[name="postType"]');
        if (hiddenInput) return hiddenInput.value;

        // Try to extract from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const urlPostType = urlParams.get('post_type') || urlParams.get('postType');
        if (urlPostType) return urlPostType;

        // Try to find from form ID or class
        const formIdMatch = form.id?.match(/create-(\w+)-form/);
        if (formIdMatch) return formIdMatch[1];

        return null;
    }

    /**
     * Get config key for lookup (compound key: postType:fieldKey)
     */
    function getConfigKey(postType, fieldKey) {
        if (postType && fieldKey) {
            return postType + ':' + fieldKey;
        }
        return null;
    }

    /**
     * Get taxonomy config from element by traversing Vue components
     */
    function getTaxonomyConfigFromElement(element) {
        if (!element) return null;

        let fieldKey = null;
        let postType = null;

        // Get the post type from the form
        postType = getFormPostType(element);

        // FIRST: Try to get field key from form group class (most reliable)
        const formGroup = element.closest('.ts-form-group');
        if (formGroup) {
            fieldKey = getFieldKeyFromFormGroup(formGroup);

            // If we got a field key AND post type, use compound key lookup
            if (fieldKey && postType) {
                const configKey = getConfigKey(postType, fieldKey);
                if (configKey && fieldConfigs[configKey] && fieldConfigs[configKey].allow_add_terms) {
                    const cfg = fieldConfigs[configKey];
                    return {
                        enabled: true,
                        taxonomy: cfg.taxonomy,
                        field_key: cfg.field_key || fieldKey,
                        post_type: cfg.post_type,
                        require_approval: cfg.require_approval
                    };
                }
            }
        }

        // SECOND: Try Vue component props for vt_add_category
        let el = element;
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
                    // Capture field key
                    if (field.key && !fieldKey) {
                        fieldKey = field.key;
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
                    if (vue.field.key && !fieldKey) {
                        fieldKey = vue.field.key;
                    }
                }

                // $props style
                if (vue.$props && vue.$props.field) {
                    if (vue.$props.field.props && vue.$props.field.props.vt_add_category) {
                        return vue.$props.field.props.vt_add_category;
                    }
                    if (vue.$props.field.key && !fieldKey) {
                        fieldKey = vue.$props.field.key;
                    }
                }

                // Check for field directly on component
                if (vue.$data && vue.$data.field && vue.$data.field.key && !fieldKey) {
                    fieldKey = vue.$data.field.key;
                }
            }

            el = el.parentElement;
        }

        // If we found a field key, check our configs with compound key
        if (fieldKey) {
            // Try compound key first (with post type)
            if (postType) {
                const configKey = getConfigKey(postType, fieldKey);
                if (configKey && fieldConfigs[configKey] && fieldConfigs[configKey].allow_add_terms) {
                    const cfg = fieldConfigs[configKey];
                    return {
                        enabled: true,
                        taxonomy: cfg.taxonomy,
                        field_key: cfg.field_key || fieldKey,
                        post_type: cfg.post_type,
                        require_approval: cfg.require_approval
                    };
                }
            }

            // Fallback: search all configs for matching field_key (for backwards compat)
            for (const key in fieldConfigs) {
                const cfg = fieldConfigs[key];
                if (cfg.field_key === fieldKey && cfg.allow_add_terms) {
                    // If we have a post type, verify it matches
                    if (postType && cfg.post_type !== postType) {
                        continue;
                    }
                    return {
                        enabled: true,
                        taxonomy: cfg.taxonomy,
                        field_key: cfg.field_key || fieldKey,
                        post_type: cfg.post_type,
                        require_approval: cfg.require_approval
                    };
                }
            }
        }

        // No aggressive fallbacks - require definitive field key match
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

                        // Select the term (even if pending, so the field is populated)
                        selectNewTerm(wrapper, term, dropdown);

                        // Reset form after short delay
                        setTimeout(function() {
                            resetForm();
                        }, 2000);
                    } else {
                        showMessage(response.data.message || 'Error adding term', 'error');
                    }
                },
                error: function() {
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
     * Find Vue instance on an element (supports Vue 2 and Vue 3)
     */
    function findVueInstance(element) {
        // First, search upward from the element
        let el = element;
        while (el) {
            const instance = getVueFromElement(el);
            if (instance) return instance;
            el = el.parentElement;
        }

        // If not found, search downward within the formGroup
        const allElements = element.querySelectorAll('*');
        for (let i = 0; i < allElements.length; i++) {
            const instance = getVueFromElement(allElements[i]);
            if (instance) return instance;
        }

        return null;
    }

    /**
     * Get Vue instance from a single element
     */
    function getVueFromElement(el) {
        // Vue 3
        if (el.__vueParentComponent) {
            const proxy = el.__vueParentComponent.proxy;
            if (proxy && (proxy.selectTerm || proxy.terms)) {
                return proxy;
            }
        }
        // Vue 2
        if (el.__vue__) {
            const vue = el.__vue__;
            if (vue.selectTerm || vue.terms) {
                return vue;
            }
        }
        return null;
    }

    /**
     * Recursively find all Vue 3 components in the virtual DOM tree
     */
    function findAllComponents(vnode, targetFieldKey, termObj, depth) {
        if (!vnode || depth > 30) return false;

        const component = vnode.component;
        if (component) {
            const proxy = component.proxy;
            if (proxy) {
                // Check if this is a field component
                if (proxy.field && proxy.field.key) {
                    if (proxy.field.key === targetFieldKey) {
                        // Build term object matching existing structure
                        const newTermObj = {
                            id: termObj.id,
                            slug: termObj.slug,
                            label: termObj.label,
                            icon: termObj.icon || ''
                        };

                        // Add the term to the terms array if it exists
                        if (proxy.terms && Array.isArray(proxy.terms)) {
                            proxy.terms.push(newTermObj);
                        }

                        // Call selectTerm to select the new term
                        if (typeof proxy.selectTerm === 'function') {
                            try {
                                proxy.selectTerm(newTermObj);
                                if (typeof proxy.saveValue === 'function') {
                                    proxy.saveValue();
                                }
                            } catch (e) {
                                // Silently fail
                            }
                            return true;
                        }

                        // Fallback: direct value update
                        if (proxy.value !== undefined && typeof proxy.value === 'object') {
                            proxy.value[termObj.slug] = newTermObj;
                            if (typeof proxy.saveValue === 'function') {
                                proxy.saveValue();
                            }
                            return true;
                        }
                    }
                }

                // Check for terms/selectTerm directly
                if (proxy.selectTerm && typeof proxy.selectTerm === 'function') {
                    proxy.selectTerm(termObj);
                    return true;
                }
            }

            // Recurse into component's subTree
            if (component.subTree) {
                if (findAllComponents(component.subTree, targetFieldKey, termObj, depth + 1)) {
                    return true;
                }
            }
        }

        // Check children array
        if (Array.isArray(vnode.children)) {
            for (const child of vnode.children) {
                if (typeof child === 'object' && child !== null) {
                    if (findAllComponents(child, targetFieldKey, termObj, depth + 1)) {
                        return true;
                    }
                }
            }
        }

        // Check dynamicChildren
        if (vnode.dynamicChildren) {
            for (const child of vnode.dynamicChildren) {
                if (findAllComponents(child, targetFieldKey, termObj, depth + 1)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Select term by accessing Vue 3 component tree
     */
    function selectTermViaDOM(formGroup, dropdown, term) {
        const termSlug = term.slug;
        const termName = term.name;
        const termId = term.id;

        // Build term object matching Voxel's structure
        const termObj = {
            id: termId,
            label: termName,
            slug: termSlug,
            icon: ''
        };

        // Find the field key from the form group
        const fieldKeyMatch = formGroup.className.match(/field-key-(\S+)/);
        const fieldKey = fieldKeyMatch ? fieldKeyMatch[1] : 'taxonomy';

        // Find the create post form
        const createPostForm = formGroup.closest('.ts-create-post, .ts-form');
        if (!createPostForm) {
            return;
        }

        // Find the Vue 3 app
        const vueAppEl = createPostForm.closest('[data-v-app]') || createPostForm;
        const vueApp = vueAppEl.__vue_app__;

        if (vueApp) {
            // Try different ways to access root component
            const container = vueApp._container;
            let rootInstance = vueApp._instance;

            // Check container for __vue_instance__
            if (!rootInstance && container && container.__vue_instance__) {
                rootInstance = container.__vue_instance__;
            }

            if (rootInstance) {
                // Check for fields directly on root instance
                if (rootInstance.fields) {
                    const fieldData = rootInstance.fields[fieldKey];
                    if (fieldData && typeof fieldData.selectTerm === 'function') {
                        fieldData.selectTerm(termObj);
                        return;
                    }
                }

                // Try to access the internal component (the _ property)
                const internalInstance = rootInstance._ || rootInstance.$;
                if (internalInstance && internalInstance.subTree) {
                    if (findAllComponents(internalInstance.subTree, fieldKey, termObj, 0)) {
                        return;
                    }
                }
            }
        }
    }

    /**
     * Select the newly added term in the field
     */
    function selectNewTerm(wrapper, term, dropdown) {
        // For teleported popups, use findRelatedFormGroup; otherwise use closest
        let formGroup = wrapper.closest('.ts-form-group');
        if (!formGroup && dropdown) {
            formGroup = findRelatedFormGroup(dropdown);
        }
        if (!formGroup) {
            return;
        }

        // Find Vue instance (Vue 2 or Vue 3)
        let vueInstance = findVueInstance(formGroup);

        // For Vue 3 apps, try to access via __vue_app__
        if (!vueInstance) {
            const vueApp = formGroup.closest('[data-v-app]');
            if (vueApp && vueApp.__vue_app__) {
                selectTermViaDOM(formGroup, dropdown, term);
                return;
            }
        }

        if (!vueInstance) {
            selectTermViaDOM(formGroup, dropdown, term);
            return;
        }

        // Build properly structured term object
        const termObj = {
            id: term.id,
            label: term.name,
            slug: term.slug,
            icon: '',
            children: [],
            parentRef: null
        };

        // Inject into terms array so it appears in the list
        if (vueInstance.terms && Array.isArray(vueInstance.terms)) {
            vueInstance.terms.push(termObj);
        }

        // Use setTimeout to ensure Vue has processed the terms update, then select
        setTimeout(function() {
            if (typeof vueInstance.selectTerm === 'function') {
                vueInstance.selectTerm(termObj);
            } else if (vueInstance.value) {
                // Fallback: direct value manipulation
                vueInstance.value[termObj.slug] = termObj;
                if (typeof vueInstance.saveValue === 'function') {
                    vueInstance.saveValue();
                }
            }
        }, 50);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Additional delayed scans for Vue-rendered content
    setTimeout(function() {
        scanExistingFields();
    }, 1500);

    setTimeout(function() {
        scanExistingFields();
    }, 3000);

})(jQuery);
