/**
 * Save Search Button JavaScript
 * Adds save search functionality to Voxel search form
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
        console.error("VT Saved Search: Failed to parse config", err);
        return;
    }

    const wrapper = el.querySelector("form .ts-filter-wrapper.flexify");
    if (btnConfig?.enable && wrapper && !wrapper.querySelector(".vt_save_search")) {
        const btn = document.createElement("vt-save-search");
        wrapper.appendChild(btn);
    }

    const vtSaveSearch = {
        template: "#vt-search-form-save-search",
        data() {
            return {
                value: "",
                placeholder: "",
                config: btnConfig,
                el: el,
                showTopPopupButton: null,
                showMainButton: null,
                showModal: false,
                breakpoint: this.$root.breakpoint,
            };
        },
        computed: {
            widget_id() {
                return this.config?.widgetId || 'vt_save_search';
            }
        },
        mounted() {
            if (!this.config?.enable) return;

            this.showTopPopupButton = this.config?.showTopPopupButton?.[this.breakpoint];
            this.showMainButton = this.config?.showMainButton?.[this.breakpoint];

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
                    console.error("VT Saved Search: Error inserting popup button:", err);
                }
            }
        },
        methods: {
            onClick() {
                if (!Voxel_Config.is_logged_in) {
                    return Voxel.authRequired();
                }
                if (this.config?.askForTitle) {
                    this.showModal = true;
                    this.$nextTick(() => {
                        this.$refs.modalInput?.focus();
                    });
                } else {
                    this.saveSearch("");
                }
            },
            closeModal() {
                this.showModal = false;
                this.value = "";
            },
            onModalSave() {
                this.saveSearch(this.value);
                this.closeModal();
            },
            saveSearch(title) {
                if (!Voxel_Config.is_logged_in) {
                    return Voxel.authRequired();
                }

                jQuery.post(Voxel_Config.ajax_url + "&action=vt_save_search", {
                    title: title,
                    details: {
                        ...this.$root.currentValues,
                        post_type: this.$root.post_type.key,
                    },
                }).always((response) => {
                    if (response.success) {
                        Voxel.alert(
                            this.config?.successMessage,
                            "success",
                            [{
                                link: this.config?.link,
                                label: this.config?.linkLabel,
                            }],
                            7500
                        );
                    } else {
                        Voxel.alert(response.message || "Error saving search", "error");
                    }
                });
            },
        },
    };

    app.component("vt-save-search", vtSaveSearch);
});
