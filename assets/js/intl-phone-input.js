/**
 * International Phone Input Enhancement
 *
 * Initializes intl-tel-input on Voxel phone fields with country auto-detection.
 * Stores country code separately so phone numbers display cleanly.
 */
(function() {
    'use strict';

    var config = window.vt_intl_phone || {};
    var phoneInstances = new Map();
    var initialized = false;

    /**
     * Check if we're on an admin page or admin context (including Voxel admin iframes)
     */
    function isAdminContext() {
        var href = window.location.href;

        // Check if URL contains wp-admin
        if (href.indexOf('wp-admin') !== -1) {
            return true;
        }

        // Check for Voxel admin form iframe (action=admin.*)
        if (href.indexOf('action=admin.') !== -1) {
            return true;
        }

        // Check for admin body class
        if (document.body && document.body.classList.contains('wp-admin')) {
            return true;
        }

        // Check if we're in an iframe on an admin page
        try {
            if (window.parent !== window && window.parent.location.href.indexOf('wp-admin') !== -1) {
                return true;
            }
        } catch (e) {
            // Cross-origin iframe, can't check parent
        }

        return false;
    }

    /**
     * Initialize phone inputs on page load and dynamically added ones
     */
    function init() {
        if (initialized) return;
        initialized = true;

        console.log('[VT Intl Phone] Initializing');

        // Initial scan
        initPhoneInputs();

        // Watch for Vue-rendered forms
        observeDOM();

        // Hook into Voxel form submission
        hookVoxelSubmission();
    }

    /**
     * Find and initialize all phone inputs
     */
    function initPhoneInputs() {
        var phoneInputs = document.querySelectorAll('input[type="tel"]:not(.iti-initialized)');

        phoneInputs.forEach(function(input) {
            // Init inputs in Voxel form contexts (frontend and admin)
            var isVoxelForm = input.closest('.ts-form-group') ||
                              input.closest('.create-post-form') ||
                              input.closest('.ts-field') ||
                              input.closest('.x-row') ||  // Voxel admin form
                              input.closest('[data-field-type="phone"]');

            if (isVoxelForm) {
                initSingleInput(input);
            }
        });
    }

    /**
     * Initialize a single phone input with intl-tel-input
     */
    function initSingleInput(input) {
        // Skip if already initialized
        if (input.classList.contains('iti-initialized')) return;
        input.classList.add('iti-initialized');

        // Get field key from Vue or parent
        var fieldKey = getFieldKey(input);

        // Check for existing country code (for edit forms)
        var existingCountryCode = input.dataset.existingCountryCode || null;

        var itiOptions = {
            initialCountry: 'us', // Default to US
            nationalMode: true, // Show national format (no country code in input value)
            separateDialCode: false,
            formatOnDisplay: false, // Don't auto-format to keep user's input
            utilsScript: config.utils_url || 'https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/utils.js',
        };

        // If we have an existing country code, use it as initial
        if (existingCountryCode) {
            itiOptions.initialCountry = '';
            // We'll set it after init
        }

        var iti = window.intlTelInput(input, itiOptions);

        // Store instance
        phoneInstances.set(input, {
            iti: iti,
            fieldKey: fieldKey
        });

        // Set initial country code if we have one
        if (existingCountryCode) {
            // Find country by dial code
            setTimeout(function() {
                var countries = iti.getCountryData();
                for (var i = 0; i < countries.length; i++) {
                    if (countries[i].dialCode === existingCountryCode) {
                        iti.setCountry(countries[i].iso2);
                        break;
                    }
                }
                updateStoredCountryCode(input, iti);
            }, 100);
        }

        // Listen for country changes
        input.addEventListener('countrychange', function() {
            updateStoredCountryCode(input, iti);
        });

        // Set initial country code after geo lookup completes
        setTimeout(function() {
            updateStoredCountryCode(input, iti);
        }, 1000);

        console.log('[VT Intl Phone] Initialized input:', fieldKey, 'Country code:', input.dataset.countryCode);
    }

    /**
     * Update stored country code in data attribute and hidden input
     */
    function updateStoredCountryCode(input, iti) {
        var countryData = iti.getSelectedCountryData();
        var dialCode = countryData.dialCode || '';

        console.log('[VT Intl Phone] Updating country code:', dialCode, 'Country:', countryData.iso2);

        // Store in data attribute
        input.dataset.countryCode = dialCode;

        // Create/update hidden input for form submission
        var fieldKey = getFieldKey(input);
        console.log('[VT Intl Phone] Field key for hidden input:', fieldKey);

        if (fieldKey) {
            var hiddenId = fieldKey + '_country_code';
            var form = input.closest('form') || input.closest('.ts-form') || input.closest('.create-post-form') || input.closest('.x-fields') || document.body;
            console.log('[VT Intl Phone] Form container found:', form ? form.tagName || form.className : 'none');

            if (form) {
                var hidden = form.querySelector('input[name="' + hiddenId + '"]');
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = hiddenId;
                    hidden.id = hiddenId;
                    form.appendChild(hidden);
                    console.log('[VT Intl Phone] Created hidden input:', hiddenId);
                }
                hidden.value = dialCode;
                console.log('[VT Intl Phone] Set hidden input value:', hiddenId, '=', dialCode);
            }
        }
    }

    /**
     * Get field key from input element
     */
    function getFieldKey(input) {
        // Try data attribute first
        if (input.dataset.fieldKey) {
            return input.dataset.fieldKey;
        }

        // Try parent form group
        var formGroup = input.closest('.ts-form-group');
        if (formGroup && formGroup.dataset.fieldKey) {
            return formGroup.dataset.fieldKey;
        }

        // Try Vue component with data-field-key
        var vueComponent = input.closest('[data-field-key]');
        if (vueComponent) {
            return vueComponent.dataset.fieldKey;
        }

        // Try Voxel admin field container with data-field-type
        var fieldContainer = input.closest('[data-field-type="phone"]');
        if (fieldContainer && fieldContainer.dataset.key) {
            return fieldContainer.dataset.key;
        }

        // Try finding field key from label
        var row = input.closest('.x-row');
        if (row) {
            var label = row.querySelector('.x-label span');
            if (label && label.textContent) {
                // Convert label to snake_case field key
                return label.textContent.toLowerCase().replace(/\s+/g, '_');
            }
        }

        // Try name attribute
        if (input.name) {
            return input.name;
        }

        // Generate from id
        if (input.id) {
            return input.id;
        }

        // Use configured phone field key from SMS settings, or 'phone' as fallback
        return config.phone_field_key || 'phone';
    }

    /**
     * Watch DOM for dynamically added phone inputs (Vue renders)
     */
    function observeDOM() {
        var observer = new MutationObserver(function(mutations) {
            var shouldScan = false;

            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            // Check if node is or contains a phone input
                            if (node.matches && node.matches('input[type="tel"]')) {
                                shouldScan = true;
                            } else if (node.querySelector && node.querySelector('input[type="tel"]')) {
                                shouldScan = true;
                            }
                        }
                    });
                }
            });

            if (shouldScan) {
                // Debounce to avoid multiple inits
                clearTimeout(observer.scanTimer);
                observer.scanTimer = setTimeout(initPhoneInputs, 100);
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Hook into Voxel's form submission to include country codes
     */
    function hookVoxelSubmission() {
        // Voxel uses custom AJAX submission via Vue
        // We need to intercept the XMLHttpRequest or fetch

        // Hook XMLHttpRequest
        var originalXHRSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function(data) {
            // Check if this is a Voxel form submission
            if (this._url && (this._url.includes('vx=create_post') || this._url.includes('action=create_post'))) {
                data = injectCountryCodes(data);
            }
            return originalXHRSend.apply(this, [data]);
        };

        var originalXHROpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function(method, url) {
            this._url = url;
            return originalXHROpen.apply(this, arguments);
        };

        // Also hook fetch for newer implementations
        var originalFetch = window.fetch;
        window.fetch = function(url, options) {
            if (url && (url.includes('vx=create_post') || url.includes('action=create_post'))) {
                if (options && options.body) {
                    options.body = injectCountryCodes(options.body);
                }
            }
            return originalFetch.apply(this, arguments);
        };
    }

    /**
     * Inject country codes into form submission data
     */
    function injectCountryCodes(data) {
        if (!data) return data;

        // Handle FormData
        if (data instanceof FormData) {
            phoneInstances.forEach(function(instance, input) {
                if (instance.fieldKey && input.dataset.countryCode) {
                    var countryKey = instance.fieldKey + '_country_code';
                    data.set(countryKey, input.dataset.countryCode);
                }
            });
            return data;
        }

        // Handle URL-encoded string
        if (typeof data === 'string') {
            var countryCodes = [];
            phoneInstances.forEach(function(instance, input) {
                if (instance.fieldKey && input.dataset.countryCode) {
                    var countryKey = instance.fieldKey + '_country_code';
                    countryCodes.push(encodeURIComponent(countryKey) + '=' + encodeURIComponent(input.dataset.countryCode));
                }
            });

            if (countryCodes.length > 0) {
                data += '&' + countryCodes.join('&');
            }
            return data;
        }

        return data;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Also initialize after a short delay to catch Vue-rendered content
    setTimeout(init, 500);

})();
