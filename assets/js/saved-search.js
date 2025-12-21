/**
 * Saved Searches Display JavaScript
 * Displays and manages saved searches in the widget
 */
(function() {
    "use strict";

    // Filter components
    const filterKeywords = {
        template: "#vt-saved-search-keywords-filter",
        props: { filter: Object }
    };

    const filterStepper = {
        template: "#vt-saved-search-stepper-filter",
        props: { filter: Object }
    };

    const filterRange = {
        template: "#vt-saved-search-range-filter",
        props: { filter: Object },
        computed: {
            rangeDisplay() {
                if (!this.filter.value) {
                    return this.filter.props?.placeholder || '';
                }
                if (typeof this.filter.value === 'object') {
                    const min = this.filter.value.min || '';
                    const max = this.filter.value.max || '';
                    if (min && max) {
                        return min + ' - ' + max;
                    }
                    if (min) {
                        return 'From ' + min;
                    }
                    if (max) {
                        return 'Up to ' + max;
                    }
                }
                return this.filter.value;
            }
        }
    };

    const filterLocation = {
        template: "#vt-saved-search-location-filter",
        props: { filter: Object },
        computed: {
            locationDisplay() {
                if (!this.filter.value) {
                    return this.filter.props?.placeholder || '';
                }
                if (typeof this.filter.value === 'string') {
                    const parts = this.filter.value.split(';');
                    return parts[0] || this.filter.value;
                }
                if (typeof this.filter.value === 'object' && this.filter.value.address) {
                    return this.filter.value.address;
                }
                return this.filter.props?.placeholder || '';
            }
        }
    };

    const filterTerms = {
        template: "#vt-saved-search-terms-filter",
        props: { filter: Object }
    };

    const filterRecurringDate = {
        template: "#vt-saved-search-recurring-date-filter",
        props: { filter: Object }
    };

    const filterDate = {
        template: "#vt-saved-search-date-filter",
        props: { filter: Object },
        computed: {
            dateDisplay() {
                if (!this.filter.value) {
                    return this.filter.props?.placeholder || '';
                }
                return this.filter.value;
            }
        }
    };

    const filterSwitcher = {
        template: "#vt-saved-search-switcher-filter",
        props: { filter: Object }
    };

    const filterUser = {
        template: "#vt-saved-search-user-filter",
        props: { filter: Object }
    };

    const filterRelations = {
        template: "#vt-saved-search-relations-filter",
        props: { filter: Object }
    };

    const filterPostStatus = {
        template: "#vt-saved-search-post-status-filter",
        props: { filter: Object }
    };

    const filterAvailability = {
        template: "#vt-saved-search-availability-filter",
        props: { filter: Object },
        computed: {
            availabilityDisplay() {
                if (!this.filter.value) {
                    return this.filter.props?.placeholder || '';
                }
                return this.filter.value;
            }
        }
    };

    // Initialize saved searches widget
    window.render_vt_saved_searches = function() {
        // Check if Vue and Voxel are available
        if (typeof Vue === 'undefined' || typeof Voxel === 'undefined') {
            console.log('VT Saved Search: Waiting for Vue and Voxel...');
            setTimeout(window.render_vt_saved_searches, 100);
            return;
        }

        Array.from(document.querySelectorAll(".vt-saved-searches")).forEach((el) => {
            if (el.__vue_app__) return;

            let vtConfig = {};
            try {
                vtConfig = JSON.parse(el.dataset.config || "{}");
            } catch (err) {
                console.error("VT Saved Search: Failed to parse config", err);
            }

            const app = Vue.createApp({
                el: el,
                mixins: [Voxel.mixins.base],
                data() {
                    return {
                        config: {
                            showLabels: 1,
                            defaultType: "popup",
                            onSubmit: {},
                            searchOn: null,
                            ...vtConfig,
                        },
                        widget_id: vtConfig.widget_id || "vt_ss",
                        widget: "searches",
                        searches: {},
                        type: "all",
                        page: 1,
                        loading: true,
                        hasMore: false,
                        activePopup: null,
                        editingTitle: "",
                    };
                },
                created() {
                    this.getSearches();
                },
                computed: {
                    sortedSearches() {
                        return Object.entries(this.searches)
                            .sort((a, b) => b[0] - a[0])
                            .map(([key, value]) => value);
                    },
                },
                methods: {
                    getSearches() {
                        this.loading = true;
                        const url = Voxel_Config.ajax_url + "&action=vt_get_saved_searches";

                        jQuery.get(url, {
                            page: this.page,
                            type: this.type,
                        }, (response) => {
                            if (response.success) {
                                this.searches = response.data;
                                this.hasMore = response.has_more;
                            }
                            this.loading = false;
                        });
                    },
                    deleteSearch(search_id) {
                        if (!confirm("Are you sure you want to delete this search?")) {
                            return;
                        }

                        this.searches[search_id].isDeleting = true;
                        const url = Voxel_Config.ajax_url + "&action=vt_delete_saved_search";

                        jQuery.post(url, { search_id: search_id }, (response) => {
                            if (response.success) {
                                this.searches[search_id].isDeleting = false;
                                if (this.searches.hasOwnProperty(search_id)) {
                                    delete this.searches[search_id];
                                }
                            }
                        });
                    },
                    viewSearch(search_id) {
                        const searchParams = { ...this.searches[search_id].params };

                        const orderedParams = {};
                        if (searchParams.post_type) {
                            orderedParams.type = searchParams.post_type;
                            delete searchParams.post_type;
                        }

                        Object.keys(searchParams).forEach(key => {
                            let value = searchParams[key];

                            if (value === null || value === undefined) {
                                return;
                            }

                            if (Array.isArray(value)) {
                                value = value.join(',');
                            }
                            else if (typeof value === 'object') {
                                return;
                            }

                            orderedParams[key] = value;
                        });

                        const queryParts = [];
                        Object.keys(orderedParams).forEach(key => {
                            const value = String(orderedParams[key]);
                            const encodedValue = encodeURIComponent(value)
                                .replace(/%2C/g, ',')
                                .replace(/%3B/g, ';')
                                .replace(/%3A/g, ':')
                                .replace(/%2B/g, '+')
                                .replace(/%20/g, '+');
                            queryParts.push(key + '=' + encodedValue);
                        });

                        const queryString = queryParts.join('&');

                        let url = this.searches[search_id].post_type.archive_link;
                        if (queryString.length) {
                            url += '?' + queryString;
                        }

                        window.location.href = url;
                    },
                    toggleNotification(search_id) {
                        this.searches[search_id].isTogglingNotification = true;
                        const url = Voxel_Config.ajax_url + "&action=vt_update_saved_search";

                        jQuery.post(url, {
                            search_id: search_id,
                            data: {
                                notification: this.searches[search_id].notification ? 0 : 1,
                            },
                        }, (response) => {
                            this.searches[search_id].isTogglingNotification = false;
                            if (response.success) {
                                this.searches[search_id].notification = !this.searches[search_id].notification;
                            }
                        });
                    },
                    setType(type) {
                        this.type = type;
                        this.page = 1;
                        this.getSearches();
                        this.$refs.type?.blur();
                    },
                    openEditTitle(id) {
                        this.editingTitle = this.searches[id].title;
                        this.activePopup = this.widget_id + "_" + id;
                    },
                    onPopupClear(id) {
                        this.editingTitle = this.searches[id].title;
                    },
                    onPopupSave(id) {
                        this.saveTitle(id);
                        this.activePopup = null;
                    },
                    saveTitle(search_id) {
                        this.searches[search_id].isEditingTitle = true;
                        const url = Voxel_Config.ajax_url + "&action=vt_update_saved_search";

                        jQuery.post(url, {
                            search_id: search_id,
                            data: {
                                title: this.editingTitle,
                            },
                        }, (response) => {
                            this.searches[search_id].isEditingTitle = false;
                            if (response.success) {
                                this.searches[search_id].title = this.editingTitle;
                            }
                        });
                    },
                    shouldValidate(e) {
                        return false;
                    },
                    formatDate(dateString) {
                        if (!dateString) return '';
                        try {
                            const date = new Date(dateString);
                            if (isNaN(date.getTime())) return dateString;
                            return date.toLocaleDateString(undefined, {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric'
                            });
                        } catch (e) {
                            return dateString;
                        }
                    },
                },
            });

            // Register components
            app.component("form-popup", Voxel.components.popup);
            app.component("form-group", Voxel.components.formGroup);
            app.component("filter-keywords", filterKeywords);
            app.component("filter-stepper", filterStepper);
            app.component("filter-range", filterRange);
            app.component("filter-location", filterLocation);
            app.component("filter-availability", filterAvailability);
            app.component("filter-terms", filterTerms);
            app.component("filter-recurring-date", filterRecurringDate);
            app.component("filter-date", filterDate);
            app.component("filter-switcher", filterSwitcher);
            app.component("filter-user", filterUser);
            app.component("filter-relations", filterRelations);
            app.component("filter-post-status", filterPostStatus);

            app.mount(el);
        });
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.render_vt_saved_searches);
    } else {
        window.render_vt_saved_searches();
    }

    // Re-initialize on Voxel markup updates
    jQuery(document).on("voxel:markup-update", window.render_vt_saved_searches);
})();
