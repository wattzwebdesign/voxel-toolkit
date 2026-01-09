<?php
/**
 * Saved Searches widget template.
 *
 * @since 1.0
 */
if (!defined('ABSPATH')) {
    exit;
}

$deferred_templates = [
    VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/filters/recurring-date-filter.php',
    VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/filters/keywords-filter.php',
    VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/filters/date-filter.php',
    VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/filters/location-filter.php',
    VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/filters/post-status-filter.php',
    VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/filters/range-filter.php',
    VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/filters/relations-filter.php',
    VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/filters/stepper-filter.php',
    VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/filters/switcher-filter.php',
    VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/filters/terms-filter.php',
    VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/filters/user-filter.php',
    VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/filters/availability-filter.php',
];

$labels = $config['labels'];
$icons = $config['icons'];
$defaultIcons = $config['defaultIcons'] ?? [];
$template = $config['template'] ?? 'detailed';
?>

<div v-cloak class="vt-saved-searches" data-config="<?php echo esc_attr(wp_json_encode($config)); ?>">

    <!-- Has Searches -->
    <div v-if="Object.keys(searches).length">
        <div class="vt-saved-searches-grid" :class="{'vx-disabled': loading}">

            <div class="vt-search-card" v-for="(search, key) in sortedSearches" :key="key">

                <!-- Card Header -->
                <div class="vt-search-card-header">
                    <div class="vt-search-card-header-left">
                        <?php if ($this->get_settings_for_display('vt_ss_show_post_type')): ?>
                            <span class="vt-search-post-type-badge" v-html="search.post_type.icon"></span>
                        <?php endif; ?>
                        <?php if ($this->get_settings_for_display('vt_ss_show_title') === 'yes'): ?>
                            <h4 class="vt-search-card-title" v-html="search.title"></h4>
                        <?php endif; ?>
                    </div>
                    <div class="vt-search-card-actions">
                        <?php if ($this->get_settings_for_display('vt_ss_show_search_btn') === 'yes'): ?>
                        <!-- View Search -->
                        <a href="#" class="vt-action-btn vt-action-view" @click.prevent="viewSearch(search.id)" :title="'<?php echo esc_attr($labels['search']); ?>'">
                            <?php echo $icons['search'] ?: ($defaultIcons['search'] ?? (function_exists('\Voxel\svg') ? \Voxel\svg('search.svg') : '<i class="las la-search"></i>')); ?>
                        </a>
                        <?php endif; ?>

                        <?php if ($this->get_settings_for_display('vt_ss_show_notification_btn') === 'yes'): ?>
                        <!-- Toggle Notification -->
                        <a href="#" class="vt-action-btn" :class="search.notification ? 'vt-action-notification-on' : 'vt-action-notification-off'"
                           @click.prevent="toggleNotification(search.id)"
                           :title="search.notification ? '<?php echo esc_attr($labels['disableNotification']); ?>' : '<?php echo esc_attr($labels['enableNotification']); ?>'">
                            <div v-if="search.isTogglingNotification" class="vt-loader">
                                <span class="ts-loader"></span>
                            </div>
                            <template v-else>
                                <template v-if="search.notification">
                                    <?php echo $icons['disableNotification'] ?: ($defaultIcons['notification'] ?? (function_exists('\Voxel\svg') ? \Voxel\svg('notification.svg') : '<i class="las la-bell-slash"></i>')); ?>
                                </template>
                                <template v-else>
                                    <?php echo $icons['enableNotification'] ?: ($defaultIcons['notification'] ?? (function_exists('\Voxel\svg') ? \Voxel\svg('notification.svg') : '<i class="las la-bell"></i>')); ?>
                                </template>
                            </template>
                        </a>
                        <?php endif; ?>

                        <?php if ($this->get_settings_for_display('vt_ss_show_edit_title') === 'yes'): ?>
                        <!-- Edit Title Popup -->
                        <form-group :popup-key="widget_id+'_'+search.id" ref="formGroup" @save="onPopupSave(search.id)"
                            @clear="onPopupClear(search.id)"
                            clear-label="<?php echo esc_attr($labels['reset']); ?>"
                            :wrapper-class="search.id" :popup-target="'editTitleBtn'+search.id">
                            <template #trigger>
                                <a class="vt-action-btn vt-action-edit ts-popup-target" href="#"
                                    @click.prevent="openEditTitle(search.id)" :ref="'editTitleBtn'+search.id"
                                    :title="'<?php echo esc_attr($labels['editTitle']); ?>'">
                                    <div v-if="search.isEditingTitle" class="vt-loader">
                                        <span class="ts-loader"></span>
                                    </div>
                                    <template v-else>
                                        <?php echo $icons['editTitle'] ?: ($defaultIcons['editTitle'] ?? (function_exists('\Voxel\svg') ? \Voxel\svg('pencil.svg') : '<i class="las la-edit"></i>')); ?>
                                    </template>
                                </a>
                            </template>
                            <template #popup>
                                <div class="">
                                    <div class="ts-input-icon ts-sticky-top flexify">
                                        <?php echo $icons['editTitle'] ?: ($defaultIcons['editTitle'] ?? (function_exists('\Voxel\svg') ? \Voxel\svg('pencil.svg') : '<i class="las la-edit"></i>')); ?>
                                        <input ref="input" v-model="editingTitle" type="text" class="autofocus border-none"
                                            @keyup.enter="onPopupSave(search.id)">
                                    </div>
                                </div>
                            </template>
                        </form-group>
                        <?php endif; ?>

                        <?php if ($this->get_settings_for_display('vt_ss_show_delete_btn') === 'yes'): ?>
                        <!-- Delete -->
                        <a class="vt-action-btn vt-action-delete" href="#" @click.prevent="deleteSearch(search.id)" :title="'<?php echo esc_attr($labels['delete']); ?>'">
                            <div v-if="search.isDeleting" class="vt-loader">
                                <span class="ts-loader"></span>
                            </div>
                            <template v-else>
                                <?php echo $icons['delete'] ?: ($defaultIcons['delete'] ?? (function_exists('\Voxel\svg') ? \Voxel\svg('trash-can.svg') : '<i class="las la-trash"></i>')); ?>
                            </template>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Card Body - Filters -->
                <div class="vt-search-card-body">
                    <?php if ($template === 'simple'): ?>
                    <!-- Simple Template: Filter Summary -->
                    <div class="vt-search-filters-summary" v-if="search.filters.length">
                        <span v-for="(filter, idx) in search.filters" :key="filter.key">
                            <strong>{{ filter.label }}:</strong> {{ getFilterDisplayValue(filter) }}<span v-if="idx < search.filters.length - 1">, </span>
                        </span>
                    </div>
                    <?php else: ?>
                    <!-- Detailed Template: Filter Tags -->
                    <div class="vt-search-filters" v-if="search.filters.length">
                        <div class="vt-filter-tag" :data-type="filter.type" v-for="filter in search.filters" :key="filter.key">
                            <component :is="'filter-'+filter.type" :filter="filter"></component>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="vt-search-no-filters" v-if="!search.filters.length">
                        <span><?php echo esc_html($labels['noFilter']); ?></span>
                    </div>
                </div>

                <!-- Card Footer -->
                <div class="vt-search-card-footer">
                    <?php if ($this->get_settings_for_display('vt_ss_show_post_type')): ?>
                        <span class="vt-search-post-type-label" v-html="search.post_type.label"></span>
                    <?php endif; ?>
                    <?php if ($this->get_settings_for_display('vt_ss_show_created_date') === 'yes'): ?>
                        <span class="vt-search-date" v-if="search.time">
                            {{ formatDate(search.time) }}
                        </span>
                    <?php endif; ?>
                </div>

            </div>

        </div>

        <!-- Pagination -->
        <div class="vt-searches-pagination" v-if="page > 1 || hasMore">
            <a href="#" class="ts-btn ts-btn-1" :class="{'vx-disabled': page <= 1}"
                @click.prevent="page -= 1; getSearches();">
                <?php echo $icons['arrowLeft'] ?: (function_exists('\Voxel\svg') ? \Voxel\svg('chevron-left.svg') : '<i class="las la-angle-left"></i>'); ?>
                <?php _e('Previous', 'voxel-toolkit'); ?>
            </a>
            <a href="#" class="ts-btn ts-btn-1 ts-btn-large" :class="{'vx-disabled': !hasMore}"
                @click.prevent="page += 1; getSearches();">
                <?php _e('Next', 'voxel-toolkit'); ?>
                <?php echo $icons['arrowRight'] ?: (function_exists('\Voxel\svg') ? \Voxel\svg('chevron-right.svg') : '<i class="las la-angle-right"></i>'); ?>
            </a>
        </div>
    </div>

    <!-- No Searches -->
    <div v-else>
        <div v-if="loading" class="vt-searches-loading">
            <span class="ts-loader"></span>
        </div>
        <div v-else class="vt-searches-empty">
            <?php echo function_exists('\Voxel\svg') ? \Voxel\svg('switch.svg') : '<i class="las la-search"></i>'; ?>
            <p><?php echo esc_html($labels['noResult']); ?></p>
        </div>
    </div>
</div>

<?php foreach ($deferred_templates as $template_path): ?>
    <?php if ($template_path && file_exists($template_path)): ?>
        <?php require_once $template_path; ?>
    <?php endif; ?>
<?php endforeach; ?>
