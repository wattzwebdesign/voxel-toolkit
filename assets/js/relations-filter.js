/**
 * Voxel Toolkit - Post Relation Search Component
 * Adds searchable dropdown to post relations filter in search forms
 */
document.addEventListener("voxel/search-form/init", (e) => {
    const { app, config, el } = e.detail;

    const vtRelationsFilter = {
        template: "#vt-search-form-relations-filter",
        props: {
            index: {
                type: Number,
                default: 0,
            },
            filter: Object,
            repeaterId: String,
        },
        data() {
            return {
                posts: {
                    loading: false,
                    has_more: true,
                    list: {},
                },
                displayValue: "",
                search: {
                    term: "",
                    offset: 0,
                    loading: false,
                    loading_more: false,
                    has_more: true,
                    list: [],
                },
                selected: {},
                resets_to: null,
            };
        },
        created() {
            this.displayValue = this._getDisplayValue();

            // Initialize with existing value from URL/default
            if (this.filter.props.post?.title) {
                this.selected = {
                    [this.filter.value]: {
                        id: this.filter.value,
                        ...this.filter.props.post,
                    },
                };
                this.posts.list = { ...this.selected };
            } else {
                this.filter.value = null;
            }

            // Load initial posts
            this.loadPosts();
        },
        methods: {
            onOpen() {
                if (Object.keys(this.posts.list).length === 0) {
                    this.loadPosts();
                }
            },

            // Get the field key - use source prop if available, otherwise use filter key
            getFieldKey() {
                return this.filter.props.source || this.filter.key;
            },

            loadPosts(data = {}, callback = null) {
                const fieldKey = this.getFieldKey();

                // Don't load for manual relations
                if (fieldKey === "(manual)") {
                    return;
                }

                this.posts.loading = true;

                const requestData = {
                    post_id: data.post_id ?? undefined,
                    exclude: this.excludeList,
                    limit: data.limit ?? undefined,
                    post_type: this.$root.post_type.key,
                    field_key: fieldKey,
                    field_path: null,
                    scope: this.filter.props.scope || 'any',
                    per_page: 10,
                };

                jQuery.get(
                    Voxel_Config.ajax_url + "&action=vt_relations_get_posts",
                    requestData
                ).always((response) => {
                    this.posts.loading = false;
                    if (response.success) {
                        this.posts.list = { ...this.posts.list, ...response.data };
                        this.posts.has_more = response.has_more;
                        if (callback) {
                            callback();
                        }
                    }
                });
            },

            saveValue() {
                if (this.isFilled()) {
                    this.filter.value = Object.keys(this.selected).join(",");
                } else {
                    this.filter.value = null;
                }

                this.displayValue = this._getDisplayValue();
            },

            onSave() {
                this.saveValue();
                this.$refs.formGroup.blur();
            },

            onClear() {
                this.selected = this.getResetsTo();
                this.search.term = "";
                this.search.list = [];
                this.saveValue();
            },

            onReset() {
                this.selected = Object.assign({}, this.getResetsTo());
                this.search.term = "";
                this.search.list = [];
                this.saveValue();
            },

            getResetsTo() {
                if (this.resets_to !== null) {
                    return this.resets_to;
                } else if (this.filter.resets_to) {
                    if (this.posts.list[this.filter.resets_to]) {
                        return {
                            [this.filter.resets_to]: this.posts.list[this.filter.resets_to],
                        };
                    }

                    this.loadPosts({ post_id: this.filter.resets_to, limit: 1 }, () => {
                        if (this.posts.list[this.filter.resets_to]) {
                            return {
                                [this.filter.resets_to]: this.posts.list[this.filter.resets_to],
                            };
                        }
                        return {};
                    });
                }
                return {};
            },

            isFilled() {
                return Object.keys(this.selected).length > 0;
            },

            _getDisplayValue() {
                const values = Object.values(this.selected);
                let display = "";

                if (values[0]) {
                    display += values[0].title;
                } else {
                    return false;
                }

                if (values.length > 1) {
                    display += " +" + (values.length - 1);
                }

                return display;
            },

            selectPost(post) {
                if (this.selected[post.id]) {
                    delete this.selected[post.id];
                } else {
                    // Clear existing selection (single select mode)
                    Object.keys(this.selected).forEach(key => delete this.selected[key]);
                    this.selected[post.id] = post;
                    this.onSave();
                }
            },

            clientSearchPosts() {
                const searchTerm = this.search.term.trim().toLowerCase();
                const searchResults = [];
                let stopSearch = false;

                Object.values(this.posts.list).forEach((post) => {
                    if (!stopSearch && post?.title?.toLowerCase().includes(searchTerm)) {
                        searchResults.push(post);
                        stopSearch = searchResults.length >= 10;
                    }
                });

                this.search.list = searchResults;
                this.search.loading = false;
                this.search.has_more = false;
                this.search.loading_more = false;
            },

            serverSearchPosts: Voxel.helpers.debounce(function(component, loadMore = false) {
                const fieldKey = component.getFieldKey();

                jQuery.get(
                    Voxel_Config.ajax_url + "&action=vt_relations_get_posts",
                    {
                        post_id: undefined,
                        exclude: component.excludeList,
                        search: component.search.term.trim(),
                        post_type: component.$root.post_type.key,
                        field_key: fieldKey,
                        field_path: null,
                        scope: component.filter.props.scope || 'any',
                    }
                ).always((response) => {
                    component.search.loading = false;
                    component.search.loading_more = false;

                    if (response.success) {
                        // Add to posts.list if not already present
                        for (const [id, post] of Object.entries(response.data)) {
                            if (!component.posts.list[id]) {
                                component.posts.list[id] = post;
                            }
                        }

                        component.search.has_more = response.has_more;

                        // Re-run client search with updated list
                        component.clientSearchPosts();
                    }
                });
            }, 300),
        },
        watch: {
            "search.term"() {
                if (this.search.term.trim() && this.posts.list) {
                    this.search.loading = true;

                    // Use client search for short terms or if all posts loaded
                    if (!this.posts.has_more || this.search.term.trim().length <= 2) {
                        this.clientSearchPosts();
                    } else {
                        this.serverSearchPosts(this);
                    }
                }
            },
            selected() {
                this.displayValue = this._getDisplayValue();
            },
        },
        computed: {
            excludeList() {
                return Object.keys(this.posts.list).join(",");
            },
        },
    };

    // Register the component, replacing the default one
    app.component("filter-relations", vtRelationsFilter);
});
