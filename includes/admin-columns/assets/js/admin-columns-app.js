/**
 * Admin Columns Vue 3 Application
 *
 * Settings interface for configuring admin columns.
 */

// Wait for both Vue and DOM to be ready
function initAdminColumnsApp() {
    // Check if Vue is available
    if (typeof Vue === 'undefined') {
        console.error('Admin Columns: Vue is not loaded');
        return;
    }

    // Check if vtAdminColumns config is available
    if (typeof vtAdminColumns === 'undefined') {
        console.error('Admin Columns: vtAdminColumns config is not available');
        return;
    }

    const { createApp, ref, reactive, computed, watch, onMounted, nextTick } = Vue;

    // Main application
    const AdminColumnsApp = {
        setup() {
            // ======================
            // State
            // ======================
            const postTypes = ref([]);
            const selectedPostType = ref(null);
            const groupedFields = ref({});
            const columns = ref([]);
            const settings = reactive({
                default_sort: { column: 'date', order: 'desc' },
                primary_column: 'title',
                quick_actions_column: '', // For user columns - which column shows row actions
            });

            const loading = ref(false);
            const loadingFields = ref(false);
            const saving = ref(false);
            const saveStatus = ref('');
            const expandedColumnId = ref(null);
            const hasChanges = ref(false);
            const fieldSearch = ref('');
            const dropdownOpen = ref(null);

            // Sortable instance
            let sortableInstance = null;

            // ======================
            // Computed
            // ======================
            const currentPostType = computed(() => {
                return postTypes.value.find(pt => pt.key === selectedPostType.value);
            });

            const currentEditUrl = computed(() => {
                return currentPostType.value ? currentPostType.value.edit_url : '#';
            });

            // Flatten grouped fields for lookups
            const availableFields = computed(() => {
                const fields = [];
                for (const groupKey in groupedFields.value) {
                    const group = groupedFields.value[groupKey];
                    if (group && group.fields) {
                        fields.push(...group.fields);
                    }
                }
                return fields;
            });

            const unusedFields = computed(() => {
                const usedKeys = columns.value.map(col => col.field_key);
                return availableFields.value.filter(field => !usedKeys.includes(field.key));
            });

            // Columns that can have quick actions (user columns only)
            const quickActionColumns = computed(() => {
                const allowedKeys = [':username', ':email', ':full_name', ':first_name', ':last_name', ':nickname', ':display_name'];
                return columns.value.filter(col => allowedKeys.includes(col.field_key));
            });

            // Filtered and grouped fields for the dropdown
            const filteredGroupedFields = computed(() => {
                const search = fieldSearch.value.toLowerCase().trim();
                const result = {};

                for (const groupKey in groupedFields.value) {
                    const group = groupedFields.value[groupKey];
                    if (!group || !group.fields) continue;

                    let filteredFields = group.fields;
                    if (search) {
                        filteredFields = group.fields.filter(field =>
                            field.label.toLowerCase().includes(search) ||
                            field.key.toLowerCase().includes(search) ||
                            (field.type_label && field.type_label.toLowerCase().includes(search))
                        );
                    }

                    if (filteredFields.length > 0) {
                        result[groupKey] = {
                            label: group.label,
                            fields: filteredFields
                        };
                    }
                }

                return result;
            });

            // ======================
            // Methods
            // ======================

            function generateId() {
                return 'col_' + Math.random().toString(36).substr(2, 9);
            }

            async function loadPostTypes() {
                loading.value = true;

                try {
                    const response = await fetch(vtAdminColumns.ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'vt_admin_columns_get_post_types',
                            nonce: vtAdminColumns.nonce,
                        }),
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Add Users as an option at the beginning
                        postTypes.value = [
                            {
                                key: 'users',
                                label: vtAdminColumns.i18n.users || 'Users',
                                singular: vtAdminColumns.i18n.user || 'User',
                                edit_url: vtAdminColumns.usersUrl || '/wp-admin/users.php',
                                is_users: true,
                            },
                            ...data.data
                        ];
                    } else {
                        console.error('Failed to load post types:', data);
                    }
                } catch (error) {
                    console.error('Error loading post types:', error);
                } finally {
                    loading.value = false;
                }
            }

            // Check if currently viewing users
            const isUsersMode = computed(() => {
                return selectedPostType.value === 'users';
            });

            async function loadFields() {
                if (!selectedPostType.value) return;

                loadingFields.value = true;

                try {
                    // Use different AJAX action for users
                    const action = isUsersMode.value
                        ? 'vt_user_columns_get_fields'
                        : 'vt_admin_columns_get_fields';

                    const params = {
                        action: action,
                        nonce: vtAdminColumns.nonce,
                    };

                    // Only include post_type for non-users
                    if (!isUsersMode.value) {
                        params.post_type = selectedPostType.value;
                    }

                    const response = await fetch(vtAdminColumns.ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams(params),
                    });

                    const data = await response.json();

                    if (data.success) {
                        groupedFields.value = data.data;
                    } else {
                        console.error('Failed to load fields:', data);
                    }
                } catch (error) {
                    console.error('Error loading fields:', error);
                } finally {
                    loadingFields.value = false;
                }
            }

            async function loadConfig() {
                if (!selectedPostType.value) return;

                try {
                    // Use different AJAX action for users
                    const action = isUsersMode.value
                        ? 'vt_user_columns_load'
                        : 'vt_admin_columns_load';

                    const params = {
                        action: action,
                        nonce: vtAdminColumns.nonce,
                    };

                    // Only include post_type for non-users
                    if (!isUsersMode.value) {
                        params.post_type = selectedPostType.value;
                    }

                    const response = await fetch(vtAdminColumns.ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams(params),
                    });

                    const data = await response.json();

                    if (data.success && data.data) {
                        let loadedColumns = data.data.columns || [];

                        // Ensure image_settings exists for image fields
                        loadedColumns = loadedColumns.map(col => {
                            const field = availableFields.value.find(f => f.key === col.field_key);
                            if (field && field.is_image && !col.image_settings) {
                                col.image_settings = {
                                    display_width: 60,
                                    display_height: 60,
                                    wp_size: 'thumbnail'
                                };
                            }
                            // Ensure product_settings exists for product fields
                            if (field && field.type === 'product' && !col.product_settings) {
                                col.product_settings = {
                                    display: 'price'
                                };
                            }
                            // Ensure poll_settings exists for poll-vt fields
                            if (field && field.type === 'poll-vt' && !col.poll_settings) {
                                col.poll_settings = {
                                    display: 'most_voted'
                                };
                            }
                            // Ensure helpful_settings exists for article-helpful fields
                            if (col.field_key === ':article_helpful' && !col.helpful_settings) {
                                col.helpful_settings = {
                                    display: 'summary'
                                };
                            }
                            // Ensure text_settings exists for textarea/description/texteditor fields
                            if (field && (field.type === 'textarea' || field.type === 'description' || field.type === 'texteditor') && !col.text_settings) {
                                col.text_settings = {
                                    limit_type: 'words',
                                    limit_value: 20
                                };
                            }
                            // Ensure work_hours_settings exists for work-hours fields
                            if (field && field.type === 'work-hours' && !col.work_hours_settings) {
                                col.work_hours_settings = {
                                    display: 'status'
                                };
                            }
                            // Ensure location_settings exists for location fields
                            if (field && field.type === 'location' && !col.location_settings) {
                                col.location_settings = {
                                    display: 'address'
                                };
                            }
                            // Ensure date_settings exists for date fields
                            if (field && field.type === 'date') {
                                if (!col.date_settings) {
                                    col.date_settings = {
                                        display: 'date',
                                        date_format: 'wordpress',
                                        custom_date_format: '',
                                        time_format: 'wordpress',
                                        custom_time_format: ''
                                    };
                                } else {
                                    // Ensure new properties exist on existing configs
                                    if (!col.date_settings.date_format) col.date_settings.date_format = 'wordpress';
                                    if (!col.date_settings.custom_date_format) col.date_settings.custom_date_format = '';
                                    if (!col.date_settings.time_format) col.date_settings.time_format = 'wordpress';
                                    if (!col.date_settings.custom_time_format) col.date_settings.custom_time_format = '';
                                }
                            }
                            // Ensure recurring_date_settings exists for recurring-date/event-date fields
                            if (field && (field.type === 'recurring-date' || field.type === 'event-date') && !col.recurring_date_settings) {
                                col.recurring_date_settings = {
                                    display: 'start_date'
                                };
                            }
                            // Ensure listing_plan_settings exists for listing plan field
                            if (col.field_key === ':listing_plan' && !col.listing_plan_settings) {
                                col.listing_plan_settings = {
                                    display: 'plan_name'
                                };
                            }
                            // Ensure verification_settings exists for verification status field
                            if (col.field_key === ':verification_status' && !col.verification_settings) {
                                col.verification_settings = {
                                    verified_label: 'Verified',
                                    not_verified_label: 'Not Verified',
                                    verified_icon: '',
                                    show_icon: true,
                                    show_label: true
                                };
                            }
                            // Ensure date_settings exists for WP date fields (:date, :modified)
                            if ((col.field_key === ':date' || col.field_key === ':modified')) {
                                if (!col.date_settings) {
                                    col.date_settings = {
                                        display: 'datetime',
                                        date_format: 'wordpress',
                                        custom_date_format: '',
                                        time_format: 'wordpress',
                                        custom_time_format: ''
                                    };
                                } else {
                                    // Ensure new properties exist on existing configs
                                    if (!col.date_settings.display) col.date_settings.display = 'datetime';
                                    if (!col.date_settings.date_format) col.date_settings.date_format = 'wordpress';
                                    if (!col.date_settings.custom_date_format) col.date_settings.custom_date_format = '';
                                    if (!col.date_settings.time_format) col.date_settings.time_format = 'wordpress';
                                    if (!col.date_settings.custom_time_format) col.date_settings.custom_time_format = '';
                                }
                            }
                            // Ensure title_settings exists for title field (with defaults enabled)
                            if (col.field_key === 'title' && !col.title_settings) {
                                col.title_settings = {
                                    show_link: true,
                                    show_actions: true
                                };
                            }
                            // User columns: ensure post_count_settings exists for post count field
                            if (col.field_key === ':post_count' && !col.post_count_settings) {
                                col.post_count_settings = {
                                    post_type: 'post',
                                    post_statuses: ['publish']
                                };
                            }
                            // User columns: ensure date_settings exists for registered date field
                            if (col.field_key === ':registered_date') {
                                if (!col.date_settings) {
                                    col.date_settings = {
                                        display: 'date',
                                        date_format: 'wordpress',
                                        custom_date_format: '',
                                        time_format: 'wordpress',
                                        custom_time_format: ''
                                    };
                                }
                            }
                            // User columns: ensure image_settings exists for profile picture
                            if (col.field_key === ':profile_picture' && !col.image_settings) {
                                col.image_settings = {
                                    display_width: 40,
                                    display_height: 40
                                };
                            }
                            // User columns: ensure membership_plan_settings exists for membership plan field
                            if (col.field_key === ':membership_plan' && !col.membership_plan_settings) {
                                col.membership_plan_settings = {
                                    display: 'plan_name'
                                };
                            }
                            return col;
                        });

                        columns.value = loadedColumns;
                        if (data.data.settings) {
                            Object.assign(settings, data.data.settings);
                        }
                    } else {
                        columns.value = [];
                    }

                    hasChanges.value = false;

                    // Initialize sortable after columns are loaded
                    await nextTick();
                    initSortable();
                } catch (error) {
                    console.error('Error loading config:', error);
                }
            }

            async function saveConfig() {
                if (!selectedPostType.value) return;

                saving.value = true;
                saveStatus.value = '';

                try {
                    const config = {
                        columns: columns.value,
                        settings: settings,
                    };

                    // Use different AJAX action for users
                    const action = isUsersMode.value
                        ? 'vt_user_columns_save'
                        : 'vt_admin_columns_save';

                    const params = {
                        action: action,
                        nonce: vtAdminColumns.nonce,
                        config: JSON.stringify(config),
                    };

                    // Only include post_type for non-users
                    if (!isUsersMode.value) {
                        params.post_type = selectedPostType.value;
                    }

                    const response = await fetch(vtAdminColumns.ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams(params),
                    });

                    const data = await response.json();

                    if (data.success) {
                        saveStatus.value = 'saved';
                        hasChanges.value = false;

                        setTimeout(() => {
                            saveStatus.value = '';
                        }, 2000);
                    } else {
                        saveStatus.value = 'error';
                        console.error('Failed to save:', data);
                    }
                } catch (error) {
                    saveStatus.value = 'error';
                    console.error('Error saving config:', error);
                } finally {
                    saving.value = false;
                }
            }

            async function restoreDefaults() {
                if (!selectedPostType.value) return;

                if (!confirm(vtAdminColumns.i18n.confirmRestore)) {
                    return;
                }

                try {
                    // Use different AJAX action for users
                    const action = isUsersMode.value
                        ? 'vt_user_columns_restore_defaults'
                        : 'vt_admin_columns_restore_defaults';

                    const params = {
                        action: action,
                        nonce: vtAdminColumns.nonce,
                    };

                    // Only include post_type for non-users
                    if (!isUsersMode.value) {
                        params.post_type = selectedPostType.value;
                    }

                    const response = await fetch(vtAdminColumns.ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams(params),
                    });

                    const data = await response.json();

                    if (data.success) {
                        columns.value = [];
                        hasChanges.value = false;
                    }
                } catch (error) {
                    console.error('Error restoring defaults:', error);
                }
            }

            function addColumn() {
                const newColumn = {
                    id: generateId(),
                    field_key: '',
                    label: '',
                    width: { mode: 'auto', value: null },
                    sortable: undefined, // Use undefined so field defaults are applied
                    filterable: undefined, // Use undefined so field defaults are applied
                };

                columns.value.push(newColumn);
                expandedColumnId.value = newColumn.id;
                hasChanges.value = true;

                // Re-initialize sortable after adding column
                nextTick(() => {
                    initSortable();
                });
            }

            function removeColumn(id) {
                const index = columns.value.findIndex(col => col.id === id);
                if (index !== -1) {
                    columns.value.splice(index, 1);
                    hasChanges.value = true;

                    if (expandedColumnId.value === id) {
                        expandedColumnId.value = null;
                    }
                }
            }

            function cloneColumn(column) {
                // Deep clone the column
                const cloned = JSON.parse(JSON.stringify(column));
                // Generate new ID
                cloned.id = generateId();
                // Append "(Copy)" to label if it exists
                if (cloned.label) {
                    cloned.label = cloned.label + ' (Copy)';
                }
                // Find original column's index and insert after it
                const index = columns.value.findIndex(col => col.id === column.id);
                if (index !== -1) {
                    columns.value.splice(index + 1, 0, cloned);
                } else {
                    columns.value.push(cloned);
                }
                hasChanges.value = true;
                // Expand the cloned column
                expandedColumnId.value = cloned.id;
            }

            function toggleExpand(id) {
                expandedColumnId.value = expandedColumnId.value === id ? null : id;
            }

            function getField(key) {
                return availableFields.value.find(f => f.key === key);
            }

            function getFieldTypeLabel(key) {
                const field = getField(key);
                return field ? field.type_label : '';
            }

            function onFieldSelect(column) {
                const field = getField(column.field_key);
                if (field) {
                    // Always update label when field changes
                    column.label = field.label;

                    // Set sortable/filterable based on field capabilities
                    // If field doesn't support it, force to false
                    // If field supports it, use field default on first selection
                    if (field.sortable) {
                        if (column.sortable === undefined) {
                            column.sortable = true;
                        }
                    } else {
                        column.sortable = false;
                    }

                    if (field.filterable) {
                        if (column.filterable === undefined) {
                            column.filterable = true;
                        }
                    } else {
                        column.filterable = false;
                    }

                    // Add default image settings for image fields
                    if (field.is_image) {
                        if (!column.image_settings) {
                            column.image_settings = {
                                display_width: 60,
                                display_height: 60,
                                wp_size: 'thumbnail'
                            };
                        }
                    } else {
                        // Remove image settings if not an image field
                        delete column.image_settings;
                    }

                    // Add default product settings for product fields
                    if (field.type === 'product') {
                        if (!column.product_settings) {
                            column.product_settings = {
                                display: 'price'
                            };
                        }
                    } else {
                        // Remove product settings if not a product field
                        delete column.product_settings;
                    }

                    // Add default poll settings for poll-vt fields
                    if (field.type === 'poll-vt') {
                        if (!column.poll_settings) {
                            column.poll_settings = {
                                display: 'most_voted'
                            };
                        }
                    } else {
                        // Remove poll settings if not a poll field
                        delete column.poll_settings;
                    }

                    // Add default helpful settings for article-helpful fields
                    if (column.field_key === ':article_helpful') {
                        if (!column.helpful_settings) {
                            column.helpful_settings = {
                                display: 'summary'
                            };
                        }
                    } else {
                        // Remove helpful settings if not an article-helpful field
                        delete column.helpful_settings;
                    }

                    // Add default text settings for textarea/description/texteditor fields
                    if (field.type === 'textarea' || field.type === 'description' || field.type === 'texteditor') {
                        if (!column.text_settings) {
                            column.text_settings = {
                                limit_type: 'words',
                                limit_value: 20
                            };
                        }
                    } else {
                        // Remove text settings if not a textarea field
                        delete column.text_settings;
                    }

                    // Add default work hours settings for work-hours fields
                    if (field.type === 'work-hours') {
                        if (!column.work_hours_settings) {
                            column.work_hours_settings = {
                                display: 'status'
                            };
                        }
                    } else {
                        // Remove work hours settings if not a work-hours field
                        delete column.work_hours_settings;
                    }

                    // Add default location settings for location fields
                    if (field.type === 'location') {
                        if (!column.location_settings) {
                            column.location_settings = {
                                display: 'address'
                            };
                        }
                    } else {
                        // Remove location settings if not a location field
                        delete column.location_settings;
                    }

                    // Add default date settings for date fields
                    if (field.type === 'date') {
                        if (!column.date_settings) {
                            column.date_settings = {
                                display: 'date',
                                date_format: 'wordpress',
                                custom_date_format: '',
                                time_format: 'wordpress',
                                custom_time_format: ''
                            };
                        }
                    } else {
                        // Remove date settings if not a date field
                        delete column.date_settings;
                    }

                    // Add default recurring date settings for recurring-date/event-date fields
                    if (field.type === 'recurring-date' || field.type === 'event-date') {
                        if (!column.recurring_date_settings) {
                            column.recurring_date_settings = {
                                display: 'start_date'
                            };
                        }
                    } else {
                        // Remove recurring date settings if not a recurring date field
                        delete column.recurring_date_settings;
                    }
                }

                // Add default listing plan settings for listing plan field
                if (column.field_key === ':listing_plan') {
                    if (!column.listing_plan_settings) {
                        column.listing_plan_settings = {
                            display: 'plan_name'
                        };
                    }
                } else {
                    // Remove listing plan settings if not a listing plan field
                    delete column.listing_plan_settings;
                }

                // Add default verification settings for verification status field
                if (column.field_key === ':verification_status') {
                    if (!column.verification_settings) {
                        column.verification_settings = {
                            verified_label: 'Verified',
                            not_verified_label: 'Not Verified',
                            verified_icon: '',
                            show_icon: true,
                            show_label: true
                        };
                    }
                } else {
                    // Remove verification settings if not a verification status field
                    delete column.verification_settings;
                }

                // Add default date settings for WP date fields (:date, :modified)
                if (column.field_key === ':date' || column.field_key === ':modified') {
                    if (!column.date_settings) {
                        column.date_settings = {
                            display: 'datetime',
                            date_format: 'wordpress',
                            custom_date_format: '',
                            time_format: 'wordpress',
                            custom_time_format: ''
                        };
                    }
                }

                // Add default title settings for title field
                if (column.field_key === 'title') {
                    if (!column.title_settings) {
                        column.title_settings = {
                            show_link: true,
                            show_actions: true
                        };
                    }
                } else {
                    // Remove title settings if not a title field
                    delete column.title_settings;
                }

                // User columns: Add default post_count_settings for post count field
                if (column.field_key === ':post_count') {
                    if (!column.post_count_settings) {
                        column.post_count_settings = {
                            post_type: 'post',
                            post_statuses: ['publish']
                        };
                    }
                } else {
                    delete column.post_count_settings;
                }

                // User columns: Add default date_settings for registered date field
                if (column.field_key === ':registered_date') {
                    if (!column.date_settings) {
                        column.date_settings = {
                            display: 'date',
                            date_format: 'wordpress',
                            custom_date_format: '',
                            time_format: 'wordpress',
                            custom_time_format: ''
                        };
                    }
                }

                // User columns: Add default image settings for profile picture
                if (column.field_key === ':profile_picture') {
                    if (!column.image_settings) {
                        column.image_settings = {
                            display_width: 40,
                            display_height: 40
                        };
                    }
                }

                // User columns: Add default membership_plan_settings for membership plan field
                if (column.field_key === ':membership_plan') {
                    if (!column.membership_plan_settings) {
                        column.membership_plan_settings = {
                            display: 'plan_name'
                        };
                    }
                } else {
                    delete column.membership_plan_settings;
                }

                // Clear search after selecting
                fieldSearch.value = '';
                hasChanges.value = true;
            }

            function clearFieldSearch() {
                fieldSearch.value = '';
            }

            function toggleDropdown(columnId) {
                if (dropdownOpen.value === columnId) {
                    dropdownOpen.value = null;
                    fieldSearch.value = '';
                } else {
                    dropdownOpen.value = columnId;
                    fieldSearch.value = '';
                    // Focus search input after dropdown opens
                    nextTick(() => {
                        const searchInput = document.querySelector('.vt-ac-dropdown.open .vt-ac-dropdown-search input');
                        if (searchInput) {
                            searchInput.focus();
                        }
                    });
                }
            }

            function closeDropdown() {
                dropdownOpen.value = null;
                fieldSearch.value = '';
            }

            function selectField(column, fieldKey) {
                column.field_key = fieldKey;
                onFieldSelect(column);
                closeDropdown();
            }

            // Close dropdown when clicking outside
            function handleClickOutside(event) {
                if (dropdownOpen.value !== null) {
                    const dropdown = event.target.closest('.vt-ac-dropdown');
                    if (!dropdown) {
                        closeDropdown();
                    }
                }
            }

            function isImageField(fieldKey) {
                const field = getField(fieldKey);
                return field && field.is_image;
            }

            function isTitleField(fieldKey) {
                return fieldKey === 'title';
            }

            function isProductField(fieldKey) {
                const field = getField(fieldKey);
                return field && field.type === 'product';
            }

            function isPollField(fieldKey) {
                const field = getField(fieldKey);
                return field && field.type === 'poll-vt';
            }

            function isArticleHelpfulField(fieldKey) {
                return fieldKey === ':article_helpful';
            }

            function isTextareaField(fieldKey) {
                const field = getField(fieldKey);
                return field && (field.type === 'textarea' || field.type === 'description' || field.type === 'texteditor');
            }

            function isWorkHoursField(fieldKey) {
                const field = getField(fieldKey);
                return field && field.type === 'work-hours';
            }

            function isLocationField(fieldKey) {
                const field = getField(fieldKey);
                return field && field.type === 'location';
            }

            function isDateField(fieldKey) {
                const field = getField(fieldKey);
                return field && field.type === 'date';
            }

            function isWpDateField(fieldKey) {
                return fieldKey === ':date' || fieldKey === ':modified';
            }

            function isRecurringDateField(fieldKey) {
                const field = getField(fieldKey);
                return field && (field.type === 'recurring-date' || field.type === 'event-date');
            }

            function isListingPlanField(fieldKey) {
                return fieldKey === ':listing_plan';
            }

            function isVerificationStatusField(fieldKey) {
                return fieldKey === ':verification_status';
            }

            // User column field type checks
            function isPostCountField(fieldKey) {
                return fieldKey === ':post_count';
            }

            function isUserRegisteredField(fieldKey) {
                return fieldKey === ':registered_date';
            }

            function isUserAvatarField(fieldKey) {
                return fieldKey === ':profile_picture';
            }

            function isMembershipPlanField(fieldKey) {
                return fieldKey === ':membership_plan';
            }

            function canBeSortable(fieldKey) {
                if (!fieldKey) return false;
                const field = getField(fieldKey);
                if (field) {
                    return field.sortable === true;
                }
                return false;
            }

            function canBeFilterable(fieldKey) {
                if (!fieldKey) return false;
                const field = getField(fieldKey);
                if (field) {
                    return field.filterable === true;
                }
                return false;
            }

            function markChanged() {
                hasChanges.value = true;
            }

            // ======================
            // Sortable Integration
            // ======================

            function initSortable() {
                // Destroy existing instance
                if (sortableInstance) {
                    sortableInstance.destroy();
                    sortableInstance = null;
                }

                const el = document.getElementById('vt-sortable-columns');
                if (!el || typeof Sortable === 'undefined') {
                    return;
                }

                sortableInstance = new Sortable(el, {
                    handle: '.vt-ac-drag-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag',
                    onEnd: function(evt) {
                        // Reorder the columns array
                        const item = columns.value.splice(evt.oldIndex, 1)[0];
                        columns.value.splice(evt.newIndex, 0, item);
                        hasChanges.value = true;
                    }
                });
            }

            // ======================
            // URL Helpers
            // ======================

            function getUrlParam(param) {
                const urlParams = new URLSearchParams(window.location.search);
                return urlParams.get(param);
            }

            function updateUrl(postType) {
                const url = new URL(window.location.href);
                // Use 'type' instead of 'post_type' to avoid WordPress conflicts
                if (postType) {
                    url.searchParams.set('type', postType);
                } else {
                    url.searchParams.delete('type');
                }
                window.history.replaceState({}, '', url.toString());
            }

            // ======================
            // Watchers
            // ======================

            watch(selectedPostType, async (newVal) => {
                // Update URL when post type changes
                updateUrl(newVal);

                if (newVal) {
                    expandedColumnId.value = null;
                    await loadFields();
                    await loadConfig();
                }
            });

            // ======================
            // Lifecycle
            // ======================

            onMounted(async () => {
                await loadPostTypes();

                // Check URL for type parameter and auto-select
                const urlPostType = getUrlParam('type');
                if (urlPostType && postTypes.value.find(pt => pt.key === urlPostType)) {
                    selectedPostType.value = urlPostType;
                }

                // Add click outside listener for dropdown
                document.addEventListener('click', handleClickOutside);
            });

            // ======================
            // Return
            // ======================

            return {
                // Expose vtAdminColumns to template
                vtAdminColumns: window.vtAdminColumns,
                // State
                postTypes,
                selectedPostType,
                availableFields,
                columns,
                settings,
                loading,
                loadingFields,
                saving,
                saveStatus,
                expandedColumnId,
                hasChanges,
                currentPostType,
                currentEditUrl,
                unusedFields,
                filteredGroupedFields,
                fieldSearch,
                dropdownOpen,
                isUsersMode,
                quickActionColumns,
                // Methods
                loadPostTypes,
                saveConfig,
                restoreDefaults,
                addColumn,
                removeColumn,
                cloneColumn,
                toggleExpand,
                getField,
                getFieldTypeLabel,
                onFieldSelect,
                markChanged,
                isImageField,
                isTitleField,
                isProductField,
                isPollField,
                isArticleHelpfulField,
                isTextareaField,
                isWorkHoursField,
                isLocationField,
                isDateField,
                isWpDateField,
                isRecurringDateField,
                isListingPlanField,
                isVerificationStatusField,
                isPostCountField,
                isUserRegisteredField,
                isUserAvatarField,
                isMembershipPlanField,
                canBeSortable,
                canBeFilterable,
                clearFieldSearch,
                toggleDropdown,
                closeDropdown,
                selectField,
            };
        },
    };

    // Mount the app
    const mountEl = document.getElementById('vt-admin-columns-app');

    if (mountEl) {
        const app = createApp(AdminColumnsApp);
        app.mount('#vt-admin-columns-app');
        console.log('Admin Columns: App mounted successfully');
    } else {
        console.error('Admin Columns: Mount element not found');
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminColumnsApp);
} else {
    // DOM is already ready
    initAdminColumnsApp();
}
