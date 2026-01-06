/**
 * Load Search Button JavaScript
 * Allows users to quickly load their saved searches into the search form
 */
document.addEventListener("voxel/search-form/init", (e) => {
    const { app, config, el } = e.detail;

    // Find the config element
    const configEl = el.closest(".elementor-element")?.querySelector(".vtSavedSearchConfig");
    if (!configEl) return;

    let btnConfig;
    try {
        btnConfig = JSON.parse(configEl.innerHTML);
    } catch (err) {
        console.error("VT Load Search: Failed to parse config", err);
        return;
    }

    // Only proceed if load search is enabled
    if (!btnConfig?.enableLoadSearch) return;

    const wrapper = el.querySelector("form .ts-filter-wrapper.flexify");
    if (wrapper && !wrapper.querySelector(".vt_load_search")) {
        const btn = document.createElement("vt-load-search");
        wrapper.appendChild(btn);
    }

    const vtLoadSearch = {
        template: "#vt-search-form-load-search",
        data() {
            return {
                config: btnConfig,
                el: el,
                showTopPopupButton: null,
                showMainButton: null,
                breakpoint: this.$root.breakpoint,
                searches: [],
                loading: false,
                loaded: false,
                searchQuery: "",
                activeSearchId: null,
                activeSearchTitle: null,
            };
        },
        computed: {
            widget_id() {
                return this.config?.widgetId || 'vt_load_search';
            },
            filteredSearches() {
                if (!this.searchQuery) {
                    return this.searches.filter(s => s.post_type?.id === this.$root.post_type?.key);
                }
                const query = this.searchQuery.toLowerCase();
                return this.searches.filter(s => {
                    if (s.post_type?.id !== this.$root.post_type?.key) return false;
                    const title = (s.title || 'Saved Search').toLowerCase();
                    return title.includes(query);
                });
            },
            hasActiveSearch() {
                return this.activeSearchId !== null;
            },
            storageKey() {
                return `vt_active_search_${this.$root.post_type?.key || 'default'}`;
            }
        },
        mounted() {
            if (!this.config?.enableLoadSearch) return;

            this.showTopPopupButton = this.config?.showLoadTopPopupButton?.[this.breakpoint];
            this.showMainButton = this.config?.showLoadMainButton?.[this.breakpoint];

            // Move popup button to top popup area
            const popupActions = document.querySelector(
                ".ts-search-portal-" + this.config?.widgetId + " .ts-popup-controller > ul"
            );

            if (popupActions && this.$refs?.popupPopupButton) {
                const lastLi = popupActions.querySelector("li:last-child");
                try {
                    if (lastLi) {
                        popupActions.insertBefore(this.$refs.popupPopupButton, lastLi);
                    } else {
                        popupActions.appendChild(this.$refs.popupPopupButton);
                    }
                } catch (err) {
                    console.error("VT Load Search: Error inserting popup button:", err);
                }
            }

            // Check for auto-apply on mount
            if (this.config?.autoApply) {
                this.checkAutoApply();
            }
        },
        methods: {
            openPopup() {
                if (!Voxel_Config.is_logged_in) {
                    return Voxel.authRequired();
                }

                this.$root.activePopup = this.widget_id + "_load_search";

                if (!this.loaded) {
                    this.fetchSearches();
                }
            },
            fetchSearches() {
                this.loading = true;

                jQuery.get(Voxel_Config.ajax_url + "&action=vt_get_saved_searches", {
                    page: 1
                }).always((response) => {
                    this.loading = false;
                    this.loaded = true;

                    if (response.success && response.data) {
                        // Convert object to array
                        this.searches = Object.values(response.data);
                    }
                });
            },
            applySearch(search) {
                if (!search || !search.params) return;

                // Store active search
                this.activeSearchId = search.id;
                this.activeSearchTitle = search.title || 'Saved Search';

                // Save to localStorage
                this.saveToStorage(search);

                // Close popup
                this.$refs.formGroup?.blur();

                // Build URL with filter params and redirect
                this.redirectWithFilters(search.params);
            },
            redirectWithFilters(params) {
                if (!params) return;

                // Get current URL base (archive page)
                const postType = this.$root.post_type;
                let baseUrl = postType?.archive_link || window.location.pathname;

                // Build query string from params
                const urlParams = new URLSearchParams();

                Object.keys(params).forEach(key => {
                    if (key === 'post_type') return; // Skip post_type
                    const value = params[key];
                    if (value !== null && value !== undefined && value !== '') {
                        urlParams.set(key, value);
                    }
                });

                // Redirect to URL with params
                const queryString = urlParams.toString();
                const newUrl = queryString ? `${baseUrl}?${queryString}` : baseUrl;

                window.location.href = newUrl;
            },
            clearSearch() {
                this.activeSearchId = null;
                this.activeSearchTitle = null;

                // Remove from localStorage
                this.removeFromStorage();

                // Close popup
                this.$refs.formGroup?.blur();

                // Redirect to archive page without filters
                const postType = this.$root.post_type;
                const baseUrl = postType?.archive_link || window.location.pathname;
                window.location.href = baseUrl;
            },
            saveToStorage(search) {
                try {
                    localStorage.setItem(this.storageKey, JSON.stringify({
                        id: search.id,
                        title: search.title,
                        params: search.params
                    }));
                } catch (e) {
                    console.error("VT Load Search: Failed to save to storage", e);
                }
            },
            removeFromStorage() {
                try {
                    localStorage.removeItem(this.storageKey);
                } catch (e) {
                    console.error("VT Load Search: Failed to remove from storage", e);
                }
            },
            loadFromStorage() {
                try {
                    const stored = localStorage.getItem(this.storageKey);
                    if (stored) {
                        return JSON.parse(stored);
                    }
                } catch (e) {
                    console.error("VT Load Search: Failed to load from storage", e);
                }
                return null;
            },
            checkAutoApply() {
                if (!Voxel_Config.is_logged_in) return;

                const stored = this.loadFromStorage();
                if (!stored || !stored.id || !stored.params) return;

                // Always set the active state for UI
                this.activeSearchId = stored.id;
                this.activeSearchTitle = stored.title || 'Saved Search';

                // Check if current URL has any filter params
                const currentParams = new URLSearchParams(window.location.search);
                const hasFilters = Array.from(currentParams.keys()).some(key => key !== 'post_type');

                // Only redirect if page loaded without filters
                if (!hasFilters) {
                    // Build the target URL to check if it would actually change
                    const postType = this.$root.post_type;
                    let baseUrl = postType?.archive_link || window.location.pathname;
                    const urlParams = new URLSearchParams();

                    Object.keys(stored.params).forEach(key => {
                        if (key === 'post_type') return;
                        const value = stored.params[key];
                        if (value !== null && value !== undefined && value !== '') {
                            urlParams.set(key, value);
                        }
                    });

                    const queryString = urlParams.toString();

                    // Only redirect if we actually have filter params to apply
                    if (queryString) {
                        setTimeout(() => {
                            this.redirectWithFilters(stored.params);
                        }, 100);
                    }
                }
            },
            onPopupSave() {
                // Not used for load search
            },
            onPopupClear() {
                this.searchQuery = "";
                this.$refs.searchInput?.focus();
            }
        },
    };

    app.component("vt-load-search", vtLoadSearch);
});
