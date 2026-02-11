<?php
/**
 * Vault Widget — Management widget template
 * Replaces saved-search-widget.php + 12 filter templates
 */
if (!defined('ABSPATH')) exit;

$labels = $config['labels'];
$icons = $config['icons'];
$defaultIcons = $config['defaultIcons'] ?? [];
$template = $config['template'] ?? 'detailed';
?>

<!-- Dialog template (needed for widget Vue app) -->
<script type="text/html" id="vtk-dialog-tpl">
    <Teleport to="body">
        <div v-if="visible" class="vtk-dialog__overlay" @click.self="onOverlayClick">
            <div class="vtk-dialog__panel" :class="'vtk-dialog__panel--'+size">
                <div class="vtk-dialog__header">
                    <slot name="header">
                        <span class="vtk-dialog__title">{{ title }}</span>
                    </slot>
                    <button type="button" class="vtk-dialog__close" @click="$emit('close')">&times;</button>
                </div>
                <div class="vtk-dialog__body">
                    <slot></slot>
                </div>
                <div class="vtk-dialog__footer" v-if="$slots.footer">
                    <slot name="footer"></slot>
                </div>
            </div>
        </div>
    </Teleport>
</script>

<div v-cloak class="vtk-vault" data-config="<?php echo esc_attr(wp_json_encode($config)); ?>">

    <!-- Has Searches -->
    <div v-if="Object.keys(searches).length">
        <div class="vtk-vault__grid" :class="{'vx-disabled': loading}">

            <div class="vtk-vault__card" v-for="(search, key) in sortedSearches" :key="key">

                <!-- Card Header -->
                <div class="vtk-vault__header">
                    <div class="vtk-vault__header-left">
                        <?php if ($this->get_settings_for_display('vt_ss_show_post_type')): ?>
                            <span class="vtk-vault__badge" v-html="search.post_type.icon"></span>
                        <?php endif; ?>
                        <?php if ($this->get_settings_for_display('vt_ss_show_title') === 'yes'): ?>
                            <h4 class="vtk-vault__title" v-html="search.title"></h4>
                        <?php endif; ?>
                    </div>
                    <div class="vtk-vault__actions">
                        <?php if ($this->get_settings_for_display('vt_ss_show_search_btn') === 'yes'): ?>
                        <a href="#" class="vtk-vault__action vtk-vault__action--view" @click.prevent="viewSearch(search.id)"
                            title="<?php echo esc_attr($labels['search']); ?>">
                            <?php echo $icons['search'] ?: ($defaultIcons['search'] ?? (function_exists('\Voxel\svg') ? \Voxel\svg('search.svg') : '<i class="las la-search"></i>')); ?>
                        </a>
                        <?php endif; ?>

                        <?php if ($this->get_settings_for_display('vt_ss_show_share_btn') === 'yes'): ?>
                        <a href="#" class="vtk-vault__action vtk-vault__action--share" @click.prevent="shareSearch(search.id)"
                            title="<?php echo esc_attr($labels['share']); ?>">
                            <?php echo $icons['share'] ?: ($defaultIcons['share'] ?? '<i class="las la-share-alt"></i>'); ?>
                        </a>
                        <?php endif; ?>

                        <?php if ($this->get_settings_for_display('vt_ss_show_notification_btn') === 'yes'): ?>
                        <a href="#" class="vtk-vault__action"
                            :class="search.notification ? 'vtk-vault__action--notif-on' : 'vtk-vault__action--notif-off'"
                            @click.prevent="toggleNotification(search.id)"
                            :title="search.notification ? '<?php echo esc_attr($labels['disableNotification']); ?>' : '<?php echo esc_attr($labels['enableNotification']); ?>'">
                            <span v-if="search.isTogglingNotification" class="ts-loader"></span>
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
                        <a href="#" class="vtk-vault__action vtk-vault__action--edit" @click.prevent="openEditModal(search.id)"
                            title="<?php echo esc_attr($labels['editTitle']); ?>">
                            <?php echo $icons['editTitle'] ?: ($defaultIcons['editTitle'] ?? (function_exists('\Voxel\svg') ? \Voxel\svg('pencil.svg') : '<i class="las la-edit"></i>')); ?>
                        </a>
                        <?php endif; ?>

                        <?php if ($this->get_settings_for_display('vt_ss_show_delete_btn') === 'yes'): ?>
                        <a href="#" class="vtk-vault__action vtk-vault__action--delete" @click.prevent="openDeleteModal(search.id)"
                            title="<?php echo esc_attr($labels['delete']); ?>">
                            <?php echo $icons['delete'] ?: ($defaultIcons['delete'] ?? (function_exists('\Voxel\svg') ? \Voxel\svg('trash-can.svg') : '<i class="las la-trash"></i>')); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Card Body — Unified Criteria Rendering -->
                <div class="vtk-vault__body">
                    <?php if ($template === 'simple'): ?>
                    <div class="vtk-vault__summary" v-if="search.filters.length">
                        <span class="vtk-vault__criterion" v-for="(f, idx) in search.filters" :key="f.key">
                            <strong class="vtk-vault__criterion-label">{{ f.label }}:</strong>
                            <span class="vtk-vault__criterion-value">{{ renderCriterion(f) }}</span><span v-if="idx < search.filters.length - 1">, </span>
                        </span>
                    </div>
                    <?php else: ?>
                    <div class="vtk-vault__criteria" v-if="search.filters.length">
                        <span class="vtk-vault__criterion" v-for="f in search.filters" :key="f.key">
                            <?php if ($this->get_settings_for_display('vt_ss_show_filter_icons') === 'yes'): ?>
                            <span v-if="f.icon" class="vtk-vault__criterion-icon" v-html="f.icon"></span>
                            <?php endif; ?>
                            <strong class="vtk-vault__criterion-label">{{ f.label }}:</strong>
                            <span class="vtk-vault__criterion-value">{{ renderCriterion(f) }}</span>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="vtk-vault__no-filters" v-if="!search.filters.length">
                        <span><?php echo esc_html($labels['noFilter']); ?></span>
                    </div>
                </div>

                <!-- Card Footer -->
                <?php if ($this->get_settings_for_display('vt_ss_show_post_type') || $this->get_settings_for_display('vt_ss_show_created_date') === 'yes'): ?>
                <div class="vtk-vault__footer">
                    <?php if ($this->get_settings_for_display('vt_ss_show_post_type')): ?>
                        <span class="vtk-vault__pt-label" v-html="search.post_type.label"></span>
                    <?php endif; ?>
                    <?php if ($this->get_settings_for_display('vt_ss_show_created_date') === 'yes'): ?>
                        <span class="vtk-vault__date" v-if="search.time">{{ formatDate(search.time) }}</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Pagination -->
        <div class="vtk-vault__pagination" v-if="page > 1 || hasMore">
            <a href="#" class="ts-btn ts-btn-1" :class="{'vx-disabled': page <= 1}" @click.prevent="prevPage">
                <?php echo $icons['arrowLeft'] ?: (function_exists('\Voxel\svg') ? \Voxel\svg('chevron-left.svg') : '<i class="las la-angle-left"></i>'); ?>
                <?php _e('Previous', 'voxel-toolkit'); ?>
            </a>
            <a href="#" class="ts-btn ts-btn-1 ts-btn-large" :class="{'vx-disabled': !hasMore}" @click.prevent="nextPage">
                <?php _e('Next', 'voxel-toolkit'); ?>
                <?php echo $icons['arrowRight'] ?: (function_exists('\Voxel\svg') ? \Voxel\svg('chevron-right.svg') : '<i class="las la-angle-right"></i>'); ?>
            </a>
        </div>
    </div>

    <!-- No Searches -->
    <div v-else>
        <div v-if="loading" class="vtk-vault__spinner">
            <span class="ts-loader"></span>
        </div>
        <div v-else class="vtk-vault__empty">
            <?php echo function_exists('\Voxel\svg') ? \Voxel\svg('switch.svg') : '<i class="las la-search"></i>'; ?>
            <p><?php echo esc_html($labels['noResult']); ?></p>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <vtk-dialog :visible="showDeleteModal" title="<?php echo esc_attr($labels['delete'] ?? 'Delete'); ?>" size="sm" @close="closeDeleteModal">
        <p><?php echo esc_html($labels['confirmDelete']); ?></p>
        <template #footer>
            <button type="button" class="vtk-dialog__btn vtk-dialog__btn--secondary" @click="closeDeleteModal">
                <?php _e('Cancel', 'voxel-toolkit'); ?>
            </button>
            <button type="button" class="vtk-dialog__btn vtk-dialog__btn--danger" @click="confirmDelete" :disabled="deleting">
                <span v-if="deleting" class="ts-loader"></span>
                <span v-else><?php echo esc_html($labels['delete']); ?></span>
            </button>
        </template>
    </vtk-dialog>

    <!-- Edit Title Modal -->
    <vtk-dialog :visible="showEditModal" title="<?php echo esc_attr($labels['editTitle'] ?? 'Edit Title'); ?>" size="sm" @close="closeEditModal">
        <div class="vtk-dialog__field">
            <input class="vtk-dialog__input vtk-dialog__edit-input" type="text"
                v-model="editTitle" @keyup.enter="confirmEditTitle"
                placeholder="<?php echo esc_attr__('Enter title...', 'voxel-toolkit'); ?>">
        </div>
        <template #footer>
            <button type="button" class="vtk-dialog__btn vtk-dialog__btn--secondary" @click="closeEditModal">
                <?php _e('Cancel', 'voxel-toolkit'); ?>
            </button>
            <button type="button" class="vtk-dialog__btn vtk-dialog__btn--primary" @click="confirmEditTitle" :disabled="editSaving">
                <span v-if="editSaving" class="ts-loader"></span>
                <span v-else><?php _e('Save', 'voxel-toolkit'); ?></span>
            </button>
        </template>
    </vtk-dialog>
</div>
