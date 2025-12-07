<?php
/**
 * Admin Columns Settings Page Template
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get the admin columns instance and output JS data inline
$admin_columns = Voxel_Toolkit_Admin_Columns::instance();
$js_data = $admin_columns->get_js_data();
?>
<script>
    var vtAdminColumns = <?php echo wp_json_encode($js_data); ?>;
</script>
<div class="wrap vt-ac-wrap">
    <h1><?php _e('Admin Columns', 'voxel-toolkit'); ?></h1>
    <p class="description"><?php _e('Configure which columns appear in the WordPress admin list for each Voxel post type.', 'voxel-toolkit'); ?></p>

    <div id="vt-admin-columns-app" v-cloak>
        <!-- Header with Post Type Selector -->
        <div class="vt-ac-header">
            <select v-model="selectedPostType" :disabled="loading">
                <option :value="null">{{ vtAdminColumns.i18n.selectPostType }}</option>
                <option v-for="pt in postTypes" :key="pt.key" :value="pt.key">
                    {{ pt.label }}
                </option>
            </select>

            <a v-if="selectedPostType && currentPostType"
               :href="currentEditUrl"
               target="_blank"
               class="button">
                <?php _e('View', 'voxel-toolkit'); ?> {{ currentPostType.label }} &rarr;
            </a>

            <span v-if="loading" class="spinner is-active" style="float: none;"></span>
        </div>

        <!-- Loading State -->
        <div v-if="loadingFields" class="vt-ac-loading">
            <span class="spinner is-active" style="float: none;"></span>
            {{ vtAdminColumns.i18n.loading }}
        </div>

        <!-- No Post Type Selected -->
        <div v-else-if="!selectedPostType" class="vt-ac-empty-state">
            <p><?php _e('Select a post type above to configure its admin columns.', 'voxel-toolkit'); ?></p>
        </div>

        <!-- Main Content -->
        <div v-else class="vt-ac-main">
            <!-- Columns Area -->
            <div class="vt-ac-columns-area">
                <!-- Empty State -->
                <div v-if="columns.length === 0" class="vt-ac-empty-state">
                    <p>{{ vtAdminColumns.i18n.noColumns }}</p>
                    <button @click="addColumn" class="button button-primary">
                        + {{ vtAdminColumns.i18n.addColumn }}
                    </button>
                </div>

                <!-- Column List -->
                <div v-else class="vt-ac-column-list">
                    <div id="vt-sortable-columns">
                        <div v-for="(element, index) in columns"
                             :key="element.id"
                             class="vt-ac-column-card"
                             :class="{ expanded: expandedColumnId === element.id }">
                            <!-- Column Header -->
                            <div class="vt-ac-column-header" @click="toggleExpand(element.id)">
                                <span class="vt-ac-drag-handle">&#8942;&#8942;</span>

                                <span class="vt-ac-column-title">
                                    <span class="vt-ac-column-label">
                                        {{ element.label || element.field_key || '<?php _e('New Column', 'voxel-toolkit'); ?>' }}
                                    </span>
                                    <span class="vt-ac-row-actions">
                                        <a href="#" @click.stop.prevent="toggleExpand(element.id)"><?php _e('Edit', 'voxel-toolkit'); ?></a>
                                        <span class="vt-ac-sep">|</span>
                                        <a href="#" @click.stop.prevent="cloneColumn(element)"><?php _e('Clone', 'voxel-toolkit'); ?></a>
                                        <span class="vt-ac-sep">|</span>
                                        <a href="#" @click.stop.prevent="removeColumn(element.id)" class="vt-ac-action-remove"><?php _e('Remove', 'voxel-toolkit'); ?></a>
                                    </span>
                                </span>

                                <span v-if="element.field_key" class="vt-ac-column-type">
                                    {{ getFieldTypeLabel(element.field_key) }}
                                </span>

                                <span class="vt-ac-column-expand dashicons dashicons-arrow-down-alt2"></span>
                            </div>

                            <!-- Column Settings -->
                            <div v-if="expandedColumnId === element.id" class="vt-ac-column-settings">
                                <!-- Field Selection with Searchable Dropdown -->
                                <div class="vt-ac-field-group">
                                    <label>{{ vtAdminColumns.i18n.field }}</label>
                                    <div class="vt-ac-dropdown" :class="{ open: dropdownOpen === element.id }">
                                        <button type="button"
                                                class="vt-ac-dropdown-trigger"
                                                @click.stop="toggleDropdown(element.id)">
                                            <span v-if="element.field_key">{{ getField(element.field_key)?.label || element.field_key }}</span>
                                            <span v-else class="vt-ac-dropdown-placeholder"><?php _e('Select a field...', 'voxel-toolkit'); ?></span>
                                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                                        </button>
                                        <div v-if="dropdownOpen === element.id" class="vt-ac-dropdown-menu" @click.stop>
                                            <div class="vt-ac-dropdown-search">
                                                <input type="text"
                                                       v-model="fieldSearch"
                                                       placeholder="<?php _e('Search fields...', 'voxel-toolkit'); ?>"
                                                       ref="dropdownSearchInput"
                                                       @keydown.escape="closeDropdown">
                                            </div>
                                            <div class="vt-ac-dropdown-options">
                                                <div v-if="Object.keys(filteredGroupedFields).length === 0" class="vt-ac-dropdown-empty">
                                                    <?php _e('No fields found', 'voxel-toolkit'); ?>
                                                </div>
                                                <template v-for="(group, groupKey) in filteredGroupedFields" :key="groupKey">
                                                    <div class="vt-ac-dropdown-group-header">{{ group.label }}</div>
                                                    <button v-for="field in group.fields"
                                                            :key="field.key"
                                                            type="button"
                                                            class="vt-ac-dropdown-option"
                                                            :class="{ selected: element.field_key === field.key }"
                                                            @click="selectField(element, field.key)">
                                                        <span class="vt-ac-dropdown-option-label">{{ field.label }}</span>
                                                        <span class="vt-ac-dropdown-option-type">{{ field.type_label }}</span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Label -->
                                <div class="vt-ac-field-group">
                                    <label>{{ vtAdminColumns.i18n.label }}</label>
                                    <input type="text"
                                           v-model="element.label"
                                           @input="markChanged"
                                           :placeholder="getField(element.field_key)?.label || ''">
                                </div>

                                <!-- Width -->
                                <div class="vt-ac-field-group">
                                    <label>{{ vtAdminColumns.i18n.width }}</label>
                                    <div class="vt-ac-width-control">
                                        <select v-model="element.width.mode" @change="markChanged">
                                            <option value="auto">{{ vtAdminColumns.i18n.auto }}</option>
                                            <option value="px">px</option>
                                            <option value="%">%</option>
                                        </select>
                                        <input v-if="element.width.mode !== 'auto'"
                                               type="number"
                                               v-model.number="element.width.value"
                                               @input="markChanged"
                                               min="1"
                                               max="500"
                                               placeholder="100">
                                    </div>
                                </div>

                                <!-- Title Settings (only for title fields) -->
                                <div v-if="isTitleField(element.field_key) && element.title_settings" class="vt-ac-title-settings">
                                    <h4><?php _e('Title Settings', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-row vt-ac-checkbox-row">
                                        <label class="vt-ac-checkbox-label">
                                            <input type="checkbox"
                                                   v-model="element.title_settings.show_link"
                                                   @change="markChanged">
                                            <?php _e('Link to Edit Post', 'voxel-toolkit'); ?>
                                        </label>
                                        <label class="vt-ac-checkbox-label">
                                            <input type="checkbox"
                                                   v-model="element.title_settings.show_actions"
                                                   @change="markChanged">
                                            <?php _e('Show Row Actions', 'voxel-toolkit'); ?>
                                            <span class="vt-ac-checkbox-hint"><?php _e('(Edit, Quick Edit, Trash, View)', 'voxel-toolkit'); ?></span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Image Settings (only for image fields) -->
                                <div v-if="isImageField(element.field_key) && element.image_settings" class="vt-ac-image-settings">
                                    <h4><?php _e('Image Settings', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-row">
                                        <div class="vt-ac-field-group">
                                            <label><?php _e('WordPress Size', 'voxel-toolkit'); ?></label>
                                            <select v-model="element.image_settings.wp_size" @change="markChanged">
                                                <option value="thumbnail"><?php _e('Thumbnail', 'voxel-toolkit'); ?></option>
                                                <option value="medium"><?php _e('Medium', 'voxel-toolkit'); ?></option>
                                                <option value="medium_large"><?php _e('Medium Large', 'voxel-toolkit'); ?></option>
                                                <option value="large"><?php _e('Large', 'voxel-toolkit'); ?></option>
                                                <option value="full"><?php _e('Full', 'voxel-toolkit'); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="vt-ac-field-row">
                                        <div class="vt-ac-field-group">
                                            <label><?php _e('Display Width (px)', 'voxel-toolkit'); ?></label>
                                            <input type="number"
                                                   v-model.number="element.image_settings.display_width"
                                                   @input="markChanged"
                                                   min="20"
                                                   max="500"
                                                   placeholder="60">
                                        </div>
                                        <div class="vt-ac-field-group">
                                            <label><?php _e('Display Height (px)', 'voxel-toolkit'); ?></label>
                                            <input type="number"
                                                   v-model.number="element.image_settings.display_height"
                                                   @input="markChanged"
                                                   min="20"
                                                   max="500"
                                                   placeholder="60">
                                        </div>
                                    </div>
                                </div>

                                <!-- Product Settings (only for product fields) -->
                                <div v-if="isProductField(element.field_key) && element.product_settings" class="vt-ac-product-settings">
                                    <h4><?php _e('Product Display', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-group">
                                        <label><?php _e('Show', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.product_settings.display" @change="markChanged">
                                            <option value="price"><?php _e('Base Price', 'voxel-toolkit'); ?></option>
                                            <option value="discounted_price"><?php _e('Discounted Price', 'voxel-toolkit'); ?></option>
                                            <option value="price_range"><?php _e('Price Range', 'voxel-toolkit'); ?></option>
                                            <option value="product_type"><?php _e('Product Type', 'voxel-toolkit'); ?></option>
                                            <option value="booking_type"><?php _e('Booking Type', 'voxel-toolkit'); ?></option>
                                            <option value="stock"><?php _e('Stock Status', 'voxel-toolkit'); ?></option>
                                            <option value="calendar"><?php _e('Calendar Type', 'voxel-toolkit'); ?></option>
                                            <option value="deliverables"><?php _e('Deliverables', 'voxel-toolkit'); ?></option>
                                            <option value="summary"><?php _e('Summary (Type + Price)', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Poll Settings (only for poll-vt fields) -->
                                <div v-if="isPollField(element.field_key) && element.poll_settings" class="vt-ac-poll-settings">
                                    <h4><?php _e('Poll Display', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-group">
                                        <label><?php _e('Show', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.poll_settings.display" @change="markChanged">
                                            <option value="most_voted"><?php _e('Most Voted (Count)', 'voxel-toolkit'); ?></option>
                                            <option value="most_voted_percent"><?php _e('Most Voted (Percentage)', 'voxel-toolkit'); ?></option>
                                            <option value="least_voted"><?php _e('Least Voted (Count)', 'voxel-toolkit'); ?></option>
                                            <option value="least_voted_percent"><?php _e('Least Voted (Percentage)', 'voxel-toolkit'); ?></option>
                                            <option value="total_votes"><?php _e('Total Votes', 'voxel-toolkit'); ?></option>
                                            <option value="option_count"><?php _e('Number of Options', 'voxel-toolkit'); ?></option>
                                            <option value="summary"><?php _e('Summary (Top 3)', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Article Helpful Settings (only for article-helpful fields) -->
                                <div v-if="isArticleHelpfulField(element.field_key) && element.helpful_settings" class="vt-ac-poll-settings">
                                    <h4><?php _e('Helpful Display', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-group">
                                        <label><?php _e('Show', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.helpful_settings.display" @change="markChanged">
                                            <option value="summary"><?php _e('Summary (Yes/No + %)', 'voxel-toolkit'); ?></option>
                                            <option value="yes_count"><?php _e('Yes Count Only', 'voxel-toolkit'); ?></option>
                                            <option value="no_count"><?php _e('No Count Only', 'voxel-toolkit'); ?></option>
                                            <option value="total"><?php _e('Total Votes', 'voxel-toolkit'); ?></option>
                                            <option value="percentage"><?php _e('Percentage Only', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Text/Textarea Settings (for textarea, description, texteditor fields) -->
                                <div v-if="isTextareaField(element.field_key) && element.text_settings" class="vt-ac-product-settings">
                                    <h4><?php _e('Text Display', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-group">
                                        <label><?php _e('Limit Type', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.text_settings.limit_type" @change="markChanged">
                                            <option value="words"><?php _e('Word Limit', 'voxel-toolkit'); ?></option>
                                            <option value="characters"><?php _e('Character Limit', 'voxel-toolkit'); ?></option>
                                            <option value="none"><?php _e('No Limit', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                    <div v-if="element.text_settings.limit_type !== 'none'" class="vt-ac-field-group">
                                        <label>{{ element.text_settings.limit_type === 'words' ? '<?php _e('Word Count', 'voxel-toolkit'); ?>' : '<?php _e('Character Count', 'voxel-toolkit'); ?>' }}</label>
                                        <input type="number" v-model.number="element.text_settings.limit_value" @change="markChanged" min="1" max="1000" style="width: 80px;">
                                    </div>
                                </div>

                                <!-- Work Hours Settings (only for work-hours fields) -->
                                <div v-if="isWorkHoursField(element.field_key) && element.work_hours_settings" class="vt-ac-product-settings">
                                    <h4><?php _e('Work Hours Display', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-group">
                                        <label><?php _e('Show', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.work_hours_settings.display" @change="markChanged">
                                            <option value="status"><?php _e('Current Status (Open/Closed)', 'voxel-toolkit'); ?></option>
                                            <option value="today"><?php _e("Today's Hours", 'voxel-toolkit'); ?></option>
                                            <option value="badge"><?php _e('Hours Set (Badge)', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Location Settings (only for location fields) -->
                                <div v-if="isLocationField(element.field_key) && element.location_settings" class="vt-ac-product-settings">
                                    <h4><?php _e('Location Display', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-group">
                                        <label><?php _e('Show', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.location_settings.display" @change="markChanged">
                                            <option value="address"><?php _e('Address', 'voxel-toolkit'); ?></option>
                                            <option value="coordinates"><?php _e('Coordinates (Lat, Long)', 'voxel-toolkit'); ?></option>
                                            <option value="latitude"><?php _e('Latitude Only', 'voxel-toolkit'); ?></option>
                                            <option value="longitude"><?php _e('Longitude Only', 'voxel-toolkit'); ?></option>
                                            <option value="full"><?php _e('Address + Coordinates', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Date Settings (for Voxel date fields and WP date fields) -->
                                <div v-if="(isDateField(element.field_key) || isWpDateField(element.field_key)) && element.date_settings" class="vt-ac-product-settings">
                                    <h4><?php _e('Date Display', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-group">
                                        <label><?php _e('Show', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.date_settings.display" @change="markChanged">
                                            <option value="date"><?php _e('Date Only', 'voxel-toolkit'); ?></option>
                                            <option value="datetime"><?php _e('Date & Time', 'voxel-toolkit'); ?></option>
                                            <option value="relative"><?php _e('Relative (e.g., 2 days ago)', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                    <div v-if="element.date_settings.display !== 'relative'" class="vt-ac-field-group">
                                        <label><?php _e('Date Format', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.date_settings.date_format" @change="markChanged">
                                            <option value="wordpress"><?php _e('WordPress Default', 'voxel-toolkit'); ?> (<?php echo esc_html(date_i18n(get_option('date_format'))); ?>)</option>
                                            <option value="j F Y"><?php echo esc_html(date_i18n('j F Y')); ?></option>
                                            <option value="F j, Y"><?php echo esc_html(date_i18n('F j, Y')); ?></option>
                                            <option value="Y-m-d"><?php echo esc_html(date_i18n('Y-m-d')); ?></option>
                                            <option value="m/d/Y"><?php echo esc_html(date_i18n('m/d/Y')); ?></option>
                                            <option value="d/m/Y"><?php echo esc_html(date_i18n('d/m/Y')); ?></option>
                                            <option value="d.m.Y"><?php echo esc_html(date_i18n('d.m.Y')); ?></option>
                                            <option value="M j, Y"><?php echo esc_html(date_i18n('M j, Y')); ?></option>
                                            <option value="j M Y"><?php echo esc_html(date_i18n('j M Y')); ?></option>
                                            <option value="custom"><?php _e('Custom', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                    <div v-if="element.date_settings.display !== 'relative' && element.date_settings.date_format === 'custom'" class="vt-ac-field-group">
                                        <label><?php _e('Custom Date Format', 'voxel-toolkit'); ?></label>
                                        <input type="text" v-model="element.date_settings.custom_date_format" @change="markChanged" placeholder="e.g., Y-m-d" style="width: 120px;">
                                    </div>
                                    <div v-if="element.date_settings.display === 'datetime'" class="vt-ac-field-group">
                                        <label><?php _e('Time Format', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.date_settings.time_format" @change="markChanged">
                                            <option value="wordpress"><?php _e('WordPress Default', 'voxel-toolkit'); ?> (<?php echo esc_html(date_i18n(get_option('time_format'))); ?>)</option>
                                            <option value="g:i a"><?php echo esc_html(date_i18n('g:i a')); ?></option>
                                            <option value="g:i A"><?php echo esc_html(date_i18n('g:i A')); ?></option>
                                            <option value="H:i"><?php echo esc_html(date_i18n('H:i')); ?></option>
                                            <option value="H:i:s"><?php echo esc_html(date_i18n('H:i:s')); ?></option>
                                            <option value="custom"><?php _e('Custom', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                    <div v-if="element.date_settings.display === 'datetime' && element.date_settings.time_format === 'custom'" class="vt-ac-field-group">
                                        <label><?php _e('Custom Time Format', 'voxel-toolkit'); ?></label>
                                        <input type="text" v-model="element.date_settings.custom_time_format" @change="markChanged" placeholder="e.g., H:i" style="width: 120px;">
                                    </div>
                                </div>

                                <!-- Recurring Date / Event Date Settings -->
                                <div v-if="isRecurringDateField(element.field_key) && element.recurring_date_settings" class="vt-ac-product-settings">
                                    <h4><?php _e('Event Display', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-group">
                                        <label><?php _e('Show', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.recurring_date_settings.display" @change="markChanged">
                                            <option value="start_date"><?php _e('Start Date', 'voxel-toolkit'); ?></option>
                                            <option value="start_datetime"><?php _e('Start Date & Time', 'voxel-toolkit'); ?></option>
                                            <option value="end_date"><?php _e('End Date', 'voxel-toolkit'); ?></option>
                                            <option value="end_datetime"><?php _e('End Date & Time', 'voxel-toolkit'); ?></option>
                                            <option value="date_range"><?php _e('Date Range', 'voxel-toolkit'); ?></option>
                                            <option value="frequency"><?php _e('Frequency (e.g., Weekly until...)', 'voxel-toolkit'); ?></option>
                                            <option value="multiday"><?php _e('Multi-day Status', 'voxel-toolkit'); ?></option>
                                            <option value="allday"><?php _e('All Day Status', 'voxel-toolkit'); ?></option>
                                            <option value="summary"><?php _e('Summary (Date + Badges)', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Listing Plan Settings -->
                                <div v-if="isListingPlanField(element.field_key) && element.listing_plan_settings" class="vt-ac-product-settings">
                                    <h4><?php _e('Listing Plan Display', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-group">
                                        <label><?php _e('Show', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.listing_plan_settings.display" @change="markChanged">
                                            <option value="plan_name"><?php _e('Plan Name', 'voxel-toolkit'); ?></option>
                                            <option value="amount"><?php _e('Amount Paid', 'voxel-toolkit'); ?></option>
                                            <option value="frequency"><?php _e('Billing Frequency', 'voxel-toolkit'); ?></option>
                                            <option value="purchase_date"><?php _e('Purchase Date', 'voxel-toolkit'); ?></option>
                                            <option value="expiration"><?php _e('Expiration Date', 'voxel-toolkit'); ?></option>
                                            <option value="summary"><?php _e('Summary (Plan + Price)', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Post Count Settings (User Columns) -->
                                <div v-if="isPostCountField(element.field_key) && element.post_count_settings" class="vt-ac-product-settings">
                                    <h4><?php _e('Post Count Settings', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-group">
                                        <label><?php _e('Post Type', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.post_count_settings.post_type" @change="markChanged">
                                            <?php
                                            $post_types = get_post_types(array('public' => true), 'objects');
                                            foreach ($post_types as $pt) :
                                            ?>
                                            <option value="<?php echo esc_attr($pt->name); ?>"><?php echo esc_html($pt->label); ?></option>
                                            <?php endforeach; ?>
                                            <?php
                                            // Add Voxel post types if available
                                            if (class_exists('\Voxel\Post_Type')) {
                                                $voxel_types = \Voxel\Post_Type::get_voxel_types();
                                                foreach ($voxel_types as $key => $type) {
                                                    if (!isset($post_types[$key])) {
                                                        ?>
                                                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($type->get_label()); ?></option>
                                                        <?php
                                                    }
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="vt-ac-field-group">
                                        <label><?php _e('Post Statuses', 'voxel-toolkit'); ?></label>
                                        <div class="vt-ac-checkbox-group">
                                            <label class="vt-ac-checkbox-label">
                                                <input type="checkbox" value="publish" v-model="element.post_count_settings.post_statuses" @change="markChanged">
                                                <?php _e('Published', 'voxel-toolkit'); ?>
                                            </label>
                                            <label class="vt-ac-checkbox-label">
                                                <input type="checkbox" value="pending" v-model="element.post_count_settings.post_statuses" @change="markChanged">
                                                <?php _e('Pending', 'voxel-toolkit'); ?>
                                            </label>
                                            <label class="vt-ac-checkbox-label">
                                                <input type="checkbox" value="draft" v-model="element.post_count_settings.post_statuses" @change="markChanged">
                                                <?php _e('Draft', 'voxel-toolkit'); ?>
                                            </label>
                                            <label class="vt-ac-checkbox-label">
                                                <input type="checkbox" value="private" v-model="element.post_count_settings.post_statuses" @change="markChanged">
                                                <?php _e('Private', 'voxel-toolkit'); ?>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- User Avatar Settings -->
                                <div v-if="isUserAvatarField(element.field_key) && element.image_settings" class="vt-ac-image-settings">
                                    <h4><?php _e('Avatar Settings', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-row">
                                        <div class="vt-ac-field-group">
                                            <label><?php _e('Display Width (px)', 'voxel-toolkit'); ?></label>
                                            <input type="number"
                                                   v-model.number="element.image_settings.display_width"
                                                   @input="markChanged"
                                                   min="20"
                                                   max="200"
                                                   placeholder="40">
                                        </div>
                                        <div class="vt-ac-field-group">
                                            <label><?php _e('Display Height (px)', 'voxel-toolkit'); ?></label>
                                            <input type="number"
                                                   v-model.number="element.image_settings.display_height"
                                                   @input="markChanged"
                                                   min="20"
                                                   max="200"
                                                   placeholder="40">
                                        </div>
                                    </div>
                                </div>

                                <!-- User Registered Date Settings -->
                                <div v-if="isUserRegisteredField(element.field_key) && element.date_settings" class="vt-ac-product-settings">
                                    <h4><?php _e('Date Display', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-group">
                                        <label><?php _e('Show', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.date_settings.display" @change="markChanged">
                                            <option value="date"><?php _e('Date Only', 'voxel-toolkit'); ?></option>
                                            <option value="datetime"><?php _e('Date & Time', 'voxel-toolkit'); ?></option>
                                            <option value="relative"><?php _e('Relative (e.g., 2 days ago)', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                    <div v-if="element.date_settings.display !== 'relative'" class="vt-ac-field-group">
                                        <label><?php _e('Date Format', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.date_settings.date_format" @change="markChanged">
                                            <option value="wordpress"><?php _e('WordPress Default', 'voxel-toolkit'); ?> (<?php echo esc_html(date_i18n(get_option('date_format'))); ?>)</option>
                                            <option value="j F Y"><?php echo esc_html(date_i18n('j F Y')); ?></option>
                                            <option value="F j, Y"><?php echo esc_html(date_i18n('F j, Y')); ?></option>
                                            <option value="Y-m-d"><?php echo esc_html(date_i18n('Y-m-d')); ?></option>
                                            <option value="m/d/Y"><?php echo esc_html(date_i18n('m/d/Y')); ?></option>
                                            <option value="d/m/Y"><?php echo esc_html(date_i18n('d/m/Y')); ?></option>
                                            <option value="d.m.Y"><?php echo esc_html(date_i18n('d.m.Y')); ?></option>
                                            <option value="M j, Y"><?php echo esc_html(date_i18n('M j, Y')); ?></option>
                                            <option value="j M Y"><?php echo esc_html(date_i18n('j M Y')); ?></option>
                                            <option value="custom"><?php _e('Custom', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                    <div v-if="element.date_settings.display !== 'relative' && element.date_settings.date_format === 'custom'" class="vt-ac-field-group">
                                        <label><?php _e('Custom Date Format', 'voxel-toolkit'); ?></label>
                                        <input type="text" v-model="element.date_settings.custom_date_format" @change="markChanged" placeholder="e.g., Y-m-d" style="width: 120px;">
                                    </div>
                                    <div v-if="element.date_settings.display === 'datetime'" class="vt-ac-field-group">
                                        <label><?php _e('Time Format', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.date_settings.time_format" @change="markChanged">
                                            <option value="wordpress"><?php _e('WordPress Default', 'voxel-toolkit'); ?> (<?php echo esc_html(date_i18n(get_option('time_format'))); ?>)</option>
                                            <option value="g:i a"><?php echo esc_html(date_i18n('g:i a')); ?></option>
                                            <option value="g:i A"><?php echo esc_html(date_i18n('g:i A')); ?></option>
                                            <option value="H:i"><?php echo esc_html(date_i18n('H:i')); ?></option>
                                            <option value="H:i:s"><?php echo esc_html(date_i18n('H:i:s')); ?></option>
                                            <option value="custom"><?php _e('Custom', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                    <div v-if="element.date_settings.display === 'datetime' && element.date_settings.time_format === 'custom'" class="vt-ac-field-group">
                                        <label><?php _e('Custom Time Format', 'voxel-toolkit'); ?></label>
                                        <input type="text" v-model="element.date_settings.custom_time_format" @change="markChanged" placeholder="e.g., H:i" style="width: 120px;">
                                    </div>
                                </div>

                                <!-- User Membership Plan Settings -->
                                <div v-if="isMembershipPlanField(element.field_key) && element.membership_plan_settings" class="vt-ac-product-settings">
                                    <h4><?php _e('Membership Plan Display', 'voxel-toolkit'); ?></h4>
                                    <div class="vt-ac-field-group">
                                        <label><?php _e('Show', 'voxel-toolkit'); ?></label>
                                        <select v-model="element.membership_plan_settings.display" @change="markChanged">
                                            <option value="plan_name"><?php _e('Plan Name', 'voxel-toolkit'); ?></option>
                                            <option value="status"><?php _e('Status (Active/Expired/Canceled)', 'voxel-toolkit'); ?></option>
                                            <option value="expiration"><?php _e('Expiration Date', 'voxel-toolkit'); ?></option>
                                            <option value="summary"><?php _e('Summary (Name + Status + Expiration)', 'voxel-toolkit'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Toggles - Disabled based on field type capabilities -->
                                <div class="vt-ac-toggles">
                                    <label class="vt-ac-toggle" :class="{ disabled: !canBeSortable(element.field_key) }">
                                        <input type="checkbox"
                                               v-model="element.sortable"
                                               :disabled="!canBeSortable(element.field_key)"
                                               @change="markChanged">
                                        {{ vtAdminColumns.i18n.sortable }}
                                        <span v-if="!canBeSortable(element.field_key)" class="vt-ac-toggle-hint"><?php _e('(not supported)', 'voxel-toolkit'); ?></span>
                                    </label>

                                    <label class="vt-ac-toggle" :class="{ disabled: !canBeFilterable(element.field_key) }">
                                        <input type="checkbox"
                                               v-model="element.filterable"
                                               :disabled="!canBeFilterable(element.field_key)"
                                               @change="markChanged">
                                        {{ vtAdminColumns.i18n.filterable }}
                                        <span v-if="!canBeFilterable(element.field_key)" class="vt-ac-toggle-hint"><?php _e('(not supported)', 'voxel-toolkit'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Column Button -->
                    <button @click="addColumn" class="vt-ac-add-column">
                        + {{ vtAdminColumns.i18n.addColumn }}
                    </button>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="vt-ac-sidebar">
                <!-- Save Card -->
                <div class="vt-ac-sidebar-card">
                    <h3><?php _e('Actions', 'voxel-toolkit'); ?></h3>

                    <button @click="saveConfig"
                            :disabled="saving || columns.length === 0"
                            class="button button-primary vt-ac-save-button"
                            :class="{ saved: saveStatus === 'saved', error: saveStatus === 'error' }">
                        <span v-if="saving">{{ vtAdminColumns.i18n.saving }}</span>
                        <span v-else-if="saveStatus === 'saved'">{{ vtAdminColumns.i18n.saved }}</span>
                        <span v-else>{{ vtAdminColumns.i18n.save }}</span>
                    </button>

                    <a href="#" @click.prevent="restoreDefaults" class="vt-ac-restore-link">
                        {{ vtAdminColumns.i18n.restoreDefaults }}
                    </a>

                    <div v-if="hasChanges && !saving" class="vt-ac-status" style="margin-top: 12px; background: #fff8e5; color: #8a6d3b;">
                        <?php _e('You have unsaved changes.', 'voxel-toolkit'); ?>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="vt-ac-sidebar-card">
                    <h3><?php _e('How it works', 'voxel-toolkit'); ?></h3>
                    <p style="font-size: 13px; color: #666; margin: 0;">
                        <?php _e('Drag columns to reorder them. Click a column to expand its settings. Changes are applied immediately when you click Save.', 'voxel-toolkit'); ?>
                    </p>
                </div>

                <!-- Column Count -->
                <div class="vt-ac-sidebar-card">
                    <h3><?php _e('Column Summary', 'voxel-toolkit'); ?></h3>
                    <p style="font-size: 13px; color: #666; margin: 0;">
                        <strong>{{ columns.length }}</strong> <?php _e('columns configured', 'voxel-toolkit'); ?>
                        <br>
                        <strong>{{ availableFields.length }}</strong> <?php _e('fields available', 'voxel-toolkit'); ?>
                    </p>
                </div>

                <!-- User Quick Actions Setting (only for users) -->
                <div v-if="isUsersMode" class="vt-ac-sidebar-card">
                    <h3><?php _e('Quick Actions', 'voxel-toolkit'); ?></h3>
                    <p style="font-size: 13px; color: #666; margin: 0 0 10px;">
                        <?php _e('Select which column displays the row actions (Edit, Delete, View).', 'voxel-toolkit'); ?>
                    </p>
                    <select v-model="settings.quick_actions_column" @change="markChanged" style="width: 100%;">
                        <option value=""><?php _e('None (default WordPress behavior)', 'voxel-toolkit'); ?></option>
                        <option v-for="col in quickActionColumns" :key="col.id" :value="col.id">
                            {{ col.label || getField(col.field_key)?.label || col.field_key }}
                        </option>
                    </select>
                </div>

            </div>
        </div>
    </div>
</div>
