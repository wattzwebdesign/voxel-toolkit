<?php
/**
 * Vault Trigger — Save + Load buttons & modals for search form
 * Replaces save-search-button.php and load-search-button.php
 */
if (!defined('ABSPATH')) exit;
?>

<!-- VtkDialog shared template (rendered once) -->
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

<!-- VtkVaultTrigger template (save & load in one component, driven by mode prop) -->
<script type="text/html" id="vtk-vault-trigger-tpl">
    <!-- ═══ SAVE MODE ═══ -->
    <template v-if="isSave && config.enable">
        <!-- Top popup icon button -->
        <li class="flexify" ref="topBtn" v-show="showTopBtn">
            <a href="#" class="ts-icon-btn vtk-trigger__icon" @mousedown.prevent="onSaveClick">
                <span v-html="config.icon"></span>
            </a>
        </li>

        <!-- Main button in filter wrapper -->
        <div class="ts-form-group vtk-trigger vtk-trigger--save" :class="showMainBtn ? '' : 'hidden'">
            <div class="ts-filter ts-popup-target vtk-trigger__btn" @mousedown.prevent="onSaveClick">
                <span v-html="config.icon"></span>
                {{ config.label }}
            </div>
        </div>

        <!-- Save modal -->
        <vtk-dialog :visible="showSaveModal" :title="config.modalTitle || 'Save Search'" size="sm" @close="closeSaveModal">
            <div class="vtk-dialog__field">
                <input class="vtk-dialog__input vtk-dialog__save-input" type="text"
                    v-model="saveTitle" :placeholder="config.placeholder"
                    @keyup.enter="confirmSave">
            </div>
            <label class="vtk-dialog__toggle">
                <input type="checkbox" v-model="saveNotification">
                <span>{{ config.notificationLabel || 'Enable notifications' }}</span>
            </label>
            <template #footer>
                <button type="button" class="vtk-dialog__btn vtk-dialog__btn--secondary" @click="closeSaveModal">
                    {{ config.cancelLabel || 'Cancel' }}
                </button>
                <button type="button" class="vtk-dialog__btn vtk-dialog__btn--primary" @click="confirmSave" :disabled="saving">
                    <span v-if="saving" class="ts-loader"></span>
                    <span v-else>{{ config.saveLabel || 'Save' }}</span>
                </button>
            </template>
        </vtk-dialog>
    </template>

    <!-- ═══ LOAD MODE ═══ -->
    <template v-if="isLoad && config.enableLoadSearch">
        <!-- Top popup icon button -->
        <li class="flexify" ref="topBtn" v-show="showTopBtn">
            <a href="#" class="ts-icon-btn vtk-trigger__icon" :class="{'vtk-trigger--has-active': hasActiveSearch}" @mousedown.prevent="onLoadClick">
                <span v-html="config.loadIcon"></span>
            </a>
        </li>

        <!-- Main button in filter wrapper -->
        <div class="ts-form-group vtk-trigger vtk-trigger--load" :class="showMainBtn ? '' : 'hidden'">
            <div class="ts-filter ts-popup-target vtk-trigger__btn" :class="{'vtk-trigger--has-active': hasActiveSearch}" @mousedown.prevent="onLoadClick">
                <span v-html="config.loadIcon"></span>
                <span class="vtk-trigger__label">{{ activeSearchTitle || config.loadLabel }}</span>
            </div>
        </div>

        <!-- Load modal -->
        <vtk-dialog :visible="showLoadModal" :title="config.loadModalTitle || 'Load Search'" size="md" @close="closeLoadModal">
            <div class="vtk-dialog__search">
                <input class="vtk-dialog__input" type="text" v-model="searchQuery"
                    :placeholder="config.searchPlaceholder || 'Search saved...'">
            </div>

            <div v-if="loadingSearches" class="vtk-vault__spinner">
                <span class="ts-loader"></span>
            </div>

            <div v-else class="vtk-dialog__scroll">
                <!-- Clear active -->
                <div v-if="hasActiveSearch" class="vtk-dialog__item vtk-dialog__item--clear" @click="clearSearch">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><path d="M18,6h0a1,1,0,0,0-1.414,0L12,10.586,7.414,6A1,1,0,0,0,6,6H6A1,1,0,0,0,6,7.414L10.586,12,6,16.586A1,1,0,0,0,6,18H6a1,1,0,0,0,1.414,0L12,13.414,16.586,18A1,1,0,0,0,18,18h0a1,1,0,0,0,0-1.414L13.414,12,18,7.414A1,1,0,0,0,18,6Z"/></svg>
                    <span>{{ config.clearLabel || 'Clear filters' }}</span>
                </div>

                <!-- Search list -->
                <div v-for="search in filteredSearches" :key="search.id"
                     class="vtk-dialog__item" :class="{'vtk-dialog__item--active': activeSearchId === search.id}"
                     @click="applySearch(search)">
                    <span class="vtk-dialog__item-icon" v-if="search.post_type && search.post_type.icon" v-html="search.post_type.icon"></span>
                    <span class="vtk-dialog__item-body">
                        <span class="vtk-dialog__item-title">{{ search.title || 'Saved Search' }}</span>
                        <span class="vtk-dialog__item-meta" v-if="search.filters && search.filters.length">
                            {{ search.filters.length }} filter{{ search.filters.length !== 1 ? 's' : '' }}
                        </span>
                    </span>
                    <svg v-if="activeSearchId === search.id" class="vtk-dialog__check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"><path d="M9,20a1,1,0,0,1-.707-.293l-5-5A1,1,0,0,1,4.707,13.293L9,17.586,19.293,7.293a1,1,0,1,1,1.414,1.414l-11,11A1,1,0,0,1,9,20Z"/></svg>
                </div>

                <!-- Empty -->
                <div v-if="filteredSearches.length === 0" class="vtk-dialog__empty">
                    <span v-if="searchQuery">{{ config.noResultsText || 'No matching searches' }}</span>
                    <span v-else>{{ config.emptyText || 'No saved searches yet' }}</span>
                </div>
            </div>
        </vtk-dialog>
    </template>
</script>
