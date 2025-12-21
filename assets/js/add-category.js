/**
 * Add Category - Frontend JavaScript
 *
 * Injects "Add new term" UI into Voxel taxonomy field popups
 */
(function($) {
    'use strict';

    if (typeof vt_add_category === 'undefined') {
        console.log('VT Add Category: Config not found');
        return;
    }

    const config = vt_add_category;
    const i18n = config.i18n || {};
    const fieldConfigs = config.field_configs || {};

    // Debug: Log configs
    console.log('VT Add Category: Loaded with configs', fieldConfigs);

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
     * Get config by taxonomy name
     */
    function getConfigByTaxonomy(taxonomy) {
        for (const key in fieldConfigs) {
            if (fieldConfigs[key].taxonomy === taxonomy && fieldConfigs[key].allow_add_terms) {
                return fieldConfigs[key];
            }
        }
        return null;
    }

    // Track the last clicked popup trigger inside create post form
    let lastCreatePostTrigger = null;

    /**
     * Initialize Add Category functionality
     */
    function init() {
        if (!hasAnyAddTermsEnabled()) {
            console.log('VT Add Category: No fields with add_terms enabled');
            return;
        }

        console.log('VT Add Category: Initializing...');

        // Track clicks on popup triggers inside create post forms
        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('.ts-popup-target');
            if (!trigger) {
                lastCreatePostTrigger = null;
                return;
            }

            // Check if this trigger is inside a create post form AND in a taxonomy field
            const createPostForm = trigger.closest('.ts-create-post, .ts-form.create-post-form');
            const taxonomyField = trigger.closest('.ts-form-group[class*="field-key-taxonomy"]');

            if (createPostForm && taxonomyField) {
                lastCreatePostTrigger = trigger;
                console.log('VT Add Category: Tracked taxonomy trigger in create post form');
            } else {
                lastCreatePostTrigger = null;
            }
        }, true); // Use capture phase to run before popup opens

        // Watch for taxonomy field popups opening
        observeTaxonomyPopups();
    }

    /**
     * Observe for taxonomy popups opening
     */
    function observeTaxonomyPopups() {
        // Use MutationObserver to detect when popups open
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        // Check if this is a taxonomy dropdown or contains one
                        let termDropdowns = [];

                        if (node.classList && node.classList.contains('ts-term-dropdown')) {
                            termDropdowns = [node];
                        } else if (node.querySelectorAll) {
                            termDropdowns = node.querySelectorAll('.ts-term-dropdown');
                        }

                        termDropdowns.forEach(function(dropdown) {
                            setTimeout(function() {
                                tryInjectAddTermUI(dropdown);
                            }, 100);
                        });
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Try to inject Add Term UI into a dropdown
     */
    function tryInjectAddTermUI(dropdown) {
        // Skip if already injected
        if (dropdown.querySelector('.vt-add-term-wrapper')) {
            return;
        }

        // CRITICAL: Only inject if the popup was triggered from a taxonomy field in create post form
        if (!lastCreatePostTrigger) {
            console.log('VT Add Category: Skipping - not triggered from create post taxonomy field');
            return;
        }

        // Make sure this is a taxonomy term dropdown (has term list items)
        const termList = dropdown.querySelector('.ts-term-dropdown-list');
        if (!termList) {
            return;
        }

        console.log('VT Add Category: Found term dropdown triggered from create post form');

        // Try to determine the taxonomy config
        const taxonomyConfig = detectTaxonomyConfig(null, dropdown);

        if (!taxonomyConfig) {
            console.log('VT Add Category: Could not detect taxonomy config');
            return;
        }

        console.log('VT Add Category: Injecting UI for', taxonomyConfig.taxonomy);
        injectAddTermUI(dropdown, taxonomyConfig);
    }

    /**
     * Detect which taxonomy this field is for
     */
    function detectTaxonomyConfig(formGroup, dropdown) {
        // Method 1: Try to find via Vue component (Voxel stores field data here)
        const vueEl = (formGroup && formGroup.closest('[data-v-app]')) || formGroup || dropdown;

        // Try Vue 2 style
        if (vueEl && vueEl.__vue__) {
            const vue = vueEl.__vue__;
            if (vue.field && vue.field.props && vue.field.props.vt_add_category) {
                const vtConfig = vue.field.props.vt_add_category;
                if (vtConfig.enabled) {
                    return {
                        taxonomy: vtConfig.taxonomy,
                        fieldKey: vtConfig.field_key,
                        postType: vtConfig.post_type,
                        requireApproval: vtConfig.require_approval
                    };
                }
            }
        }

        // Method 2: Walk up to find the component with field data
        let el = formGroup || dropdown;
        while (el) {
            if (el.__vue__ && el.__vue__.field) {
                const field = el.__vue__.field;
                if (field.props && field.props.vt_add_category && field.props.vt_add_category.enabled) {
                    const vtConfig = field.props.vt_add_category;
                    return {
                        taxonomy: vtConfig.taxonomy,
                        fieldKey: vtConfig.field_key,
                        postType: vtConfig.post_type,
                        requireApproval: vtConfig.require_approval
                    };
                }
                // Even without our config, try to get taxonomy from standard props
                if (field.props && field.props.taxonomy) {
                    const taxName = field.props.taxonomy.label || '';
                    // Try to match by taxonomy
                    for (const key in fieldConfigs) {
                        const cfg = fieldConfigs[key];
                        if (cfg.allow_add_terms) {
                            // Check if this field's taxonomy matches
                            return {
                                taxonomy: cfg.taxonomy,
                                fieldKey: cfg.field_key || key,
                                postType: cfg.post_type,
                                requireApproval: cfg.require_approval
                            };
                        }
                    }
                }
            }
            el = el.parentElement;
        }

        // Method 3: Try to detect taxonomy from dropdown content
        // Look at the terms in the dropdown to identify the taxonomy
        const termItems = dropdown.querySelectorAll('.ts-term-dropdown-list li');
        console.log('VT Add Category: Found', termItems.length, 'term items in dropdown');

        // Method 4: Fallback - only if there's exactly ONE taxonomy field with add enabled
        let enabledCount = 0;
        let enabledConfig = null;
        for (const key in fieldConfigs) {
            if (fieldConfigs[key].allow_add_terms) {
                enabledCount++;
                enabledConfig = fieldConfigs[key];
            }
        }

        if (enabledCount === 1 && enabledConfig) {
            console.log('VT Add Category: Using single enabled config for', enabledConfig.taxonomy);
            return {
                taxonomy: enabledConfig.taxonomy,
                fieldKey: enabledConfig.field_key || Object.keys(fieldConfigs).find(k => fieldConfigs[k] === enabledConfig),
                postType: enabledConfig.post_type,
                requireApproval: enabledConfig.require_approval
            };
        }

        console.log('VT Add Category: Multiple or no configs enabled, cannot determine which taxonomy');
        return null;
    }

    /**
     * Inject Add Term UI into a taxonomy dropdown
     */
    function injectAddTermUI(dropdown, taxonomyConfig) {
        // Create the add term UI
        const addTermWrapper = document.createElement('div');
        addTermWrapper.className = 'vt-add-term-wrapper';
        addTermWrapper.setAttribute('data-taxonomy', taxonomyConfig.taxonomy);
        addTermWrapper.setAttribute('data-field-key', taxonomyConfig.fieldKey);
        addTermWrapper.setAttribute('data-post-type', taxonomyConfig.postType);
        addTermWrapper.innerHTML = createAddTermHTML();

        // Find where to insert - before the term list
        const termList = dropdown.querySelector('.ts-term-dropdown-list');
        if (termList) {
            termList.parentNode.insertBefore(addTermWrapper, termList);
        } else {
            dropdown.insertBefore(addTermWrapper, dropdown.firstChild);
        }

        // Bind events
        bindAddTermEvents(addTermWrapper, taxonomyConfig, dropdown);
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
            const currentParent = getCurrentParentTerm(dropdown);
            parentInput.value = currentParent || 0;
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

            console.log('VT Add Category: Submitting term', {
                name: name,
                taxonomy: taxonomyConfig.taxonomy,
                fieldKey: taxonomyConfig.fieldKey,
                postType: taxonomyConfig.postType,
                parent: parent
            });

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
                    field_key: taxonomyConfig.fieldKey,
                    post_type: taxonomyConfig.postType
                },
                success: function(response) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = i18n.add_button || 'Add';

                    console.log('VT Add Category: Response', response);

                    if (response.success) {
                        const term = response.data.term;

                        // Show success message
                        const msg = term.pending
                            ? (i18n.success_pending || 'Term submitted for approval. Once approved, you can edit your post to add it.')
                            : (i18n.success_added || 'Term added successfully');
                        showMessage(msg, 'success');

                        // If not pending, try to select the term
                        if (!term.pending) {
                            selectNewTerm(dropdown, term);
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
            // The parent item contains a checkbox with the term slug
            const checkbox = parentItem.querySelector('input[type="checkbox"], input[type="radio"]');
            if (checkbox && checkbox.value) {
                // We have the slug, but we need the ID
                // For now, return 0 as we'd need another lookup
                // The parent will be determined server-side if needed
            }
        }

        return 0;
    }

    /**
     * Select the newly added term in the field
     */
    function selectNewTerm(dropdown, term) {
        // Find the Vue component
        const formGroup = dropdown.closest('.ts-form-group');
        if (!formGroup) return;

        let el = formGroup;
        while (el) {
            if (el.__vue__ && el.__vue__.selectTerm) {
                el.__vue__.selectTerm({
                    id: term.id,
                    slug: term.slug,
                    label: term.name,
                    icon: ''
                });
                console.log('VT Add Category: Term selected via Vue');
                return;
            }
            if (el.__vue__ && el.__vue__.value) {
                // Manually add to value object
                el.__vue__.value[term.slug] = {
                    id: term.id,
                    label: term.name,
                    slug: term.slug,
                    icon: ''
                };
                console.log('VT Add Category: Term added to value');
                return;
            }
            el = el.parentElement;
        }

        console.log('VT Add Category: Could not select term automatically');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(jQuery);
