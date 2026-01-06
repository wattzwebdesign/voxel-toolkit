/**
 * Advanced Phone Input Enhancement
 *
 * Initializes intl-tel-input on Voxel phone fields with per-field configuration.
 * Reads configuration from field.props.vt_phone_config set in frontend_props().
 */
(function() {
    'use strict';

    var config = window.vt_advanced_phone || {};
    var phoneInstances = new Map();
    var initialized = false;

    /**
     * Initialize on DOM ready
     */
    function init() {
        if (initialized) return;
        initialized = true;

        // Initial scan for phone inputs
        initPhoneInputs();

        // Watch for Vue-rendered forms (dynamically added inputs)
        observeDOM();

        // Hook into form submissions
        hookFormSubmission();
    }

    /**
     * Find and initialize all phone inputs
     */
    function initPhoneInputs() {
        var phoneInputs = document.querySelectorAll('input[type="tel"]:not(.vt-phone-initialized)');

        phoneInputs.forEach(function(input) {
            // Only init inputs in Voxel form contexts
            var isVoxelForm = input.closest('.ts-form-group') ||
                              input.closest('.create-post-form') ||
                              input.closest('.ts-field') ||
                              input.closest('.x-row') ||
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
        // Skip if already initialized by us
        if (input.classList.contains('vt-phone-initialized')) return;

        // Destroy any existing intl-tel-input instance first
        if (input.parentElement && input.parentElement.classList.contains('iti')) {
            var existingInstance = window.intlTelInputGlobals.getInstance(input);
            if (existingInstance) {
                existingInstance.destroy();
            }
        }

        input.classList.add('vt-phone-initialized');
        input.classList.add('iti-initialized'); // Prevent old script from initializing too

        // Try to get per-field config
        var fieldConfig = getFieldConfig(input);

        var itiOptions = {
            initialCountry: fieldConfig.initialCountry || 'us',
            nationalMode: true,
            separateDialCode: true, // Show +X dial code next to flag
            preferredCountries: [], // Disable preferred countries to prevent duplicates
            utilsScript: config.utils_url || 'https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/utils.js',
        };

        // Apply country restrictions if configured
        if (fieldConfig.onlyCountries && fieldConfig.onlyCountries.length > 0) {
            itiOptions.onlyCountries = fieldConfig.onlyCountries;
        }

        // Apply dropdown visibility setting
        if (fieldConfig.allowDropdown === false) {
            itiOptions.allowDropdown = false;
        }

        // Check for existing country code (for edit forms)
        var existingCountryCode = input.dataset.existingCountryCode || null;
        if (existingCountryCode) {
            itiOptions.initialCountry = '';
        }

        var iti = window.intlTelInput(input, itiOptions);

        // Store instance with field key
        var fieldKey = fieldConfig.fieldKey || getFieldKey(input);
        phoneInstances.set(input, {
            iti: iti,
            fieldKey: fieldKey
        });

        // Set initial country if we have existing code
        if (existingCountryCode) {
            setTimeout(function() {
                var countries = iti.getCountryData();
                for (var i = 0; i < countries.length; i++) {
                    if (countries[i].dialCode === existingCountryCode) {
                        iti.setCountry(countries[i].iso2);
                        break;
                    }
                }
                updateStoredCountryCode(input, iti, fieldKey);
            }, 100);
        }

        // Listen for country changes
        input.addEventListener('countrychange', function() {
            updateStoredCountryCode(input, iti, fieldKey);
        });

        // Set initial country code after geo lookup
        setTimeout(function() {
            updateStoredCountryCode(input, iti, fieldKey);
        }, 1000);
    }

    /**
     * Get field configuration from global config or data attributes
     */
    function getFieldConfig(input) {
        var defaultConfig = {
            initialCountry: '',
            onlyCountries: [],
            allowDropdown: true,
            fieldKey: null
        };

        // First, get the field key
        var fieldKey = getFieldKey(input);
        defaultConfig.fieldKey = fieldKey;

        // Check global config passed from PHP
        if (config.field_configs && fieldKey && config.field_configs[fieldKey]) {
            var fieldConfig = config.field_configs[fieldKey];
            fieldConfig.fieldKey = fieldKey;
            return fieldConfig;
        }

        // Try data attributes as fallback
        if (input.dataset.vtInitialCountry) {
            defaultConfig.initialCountry = input.dataset.vtInitialCountry;
        }
        if (input.dataset.vtOnlyCountries) {
            defaultConfig.onlyCountries = input.dataset.vtOnlyCountries.split(',').map(function(c) {
                return c.trim().toLowerCase();
            });
        }
        if (input.dataset.vtAllowDropdown === 'false') {
            defaultConfig.allowDropdown = false;
        }

        return defaultConfig;
    }

    /**
     * Get field key from input element
     */
    function getFieldKey(input) {
        // Try data attribute
        if (input.dataset.fieldKey) {
            return input.dataset.fieldKey;
        }

        // Try parent form group
        var formGroup = input.closest('.ts-form-group');
        if (formGroup && formGroup.dataset.fieldKey) {
            return formGroup.dataset.fieldKey;
        }

        // Try Vue component
        var vueComponent = input.closest('[data-field-key]');
        if (vueComponent) {
            return vueComponent.dataset.fieldKey;
        }

        // Try Voxel admin field container
        var fieldContainer = input.closest('[data-field-type="phone"]');
        if (fieldContainer && fieldContainer.dataset.key) {
            return fieldContainer.dataset.key;
        }

        // Try finding from label
        var row = input.closest('.x-row');
        if (row) {
            var label = row.querySelector('.x-label span');
            if (label && label.textContent) {
                return label.textContent.toLowerCase().replace(/\s+/g, '_');
            }
        }

        // Try name attribute
        if (input.name) {
            return input.name;
        }

        // Fallback
        return 'phone';
    }

    /**
     * Update stored country code in data attribute and hidden input
     */
    function updateStoredCountryCode(input, iti, fieldKey) {
        var countryData = iti.getSelectedCountryData();
        var dialCode = countryData.dialCode || '';

        // Store in data attribute
        input.dataset.countryCode = dialCode;

        // Create/update hidden input for form submission
        if (fieldKey) {
            var hiddenId = fieldKey + '_country_code';
            var form = input.closest('form') ||
                       input.closest('.ts-form') ||
                       input.closest('.create-post-form') ||
                       input.closest('.x-fields') ||
                       document.body;

            if (form) {
                var hidden = form.querySelector('input[name="' + hiddenId + '"]');
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = hiddenId;
                    hidden.id = hiddenId;
                    form.appendChild(hidden);
                }
                hidden.value = dialCode;
            }
        }
    }

    /**
     * Watch DOM for dynamically added phone inputs
     */
    function observeDOM() {
        var observer = new MutationObserver(function(mutations) {
            var shouldScan = false;

            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
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
     * Hook into form submissions to inject country codes
     */
    function hookFormSubmission() {
        // Hook XMLHttpRequest
        var originalXHRSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function(data) {
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

        // Hook fetch
        var originalFetch = window.fetch;
        window.fetch = function(url, options) {
            // Convert url to string for checking (url can be string, URL object, or Request object)
            var urlString = '';
            if (typeof url === 'string') {
                urlString = url;
            } else if (url instanceof URL) {
                urlString = url.href;
            } else if (url instanceof Request) {
                urlString = url.url;
            }

            if (urlString && (urlString.includes('vx=create_post') || urlString.includes('action=create_post'))) {
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

    // Also initialize after delay for Vue-rendered content
    setTimeout(init, 500);

})();
