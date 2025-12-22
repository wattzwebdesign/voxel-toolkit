<script type="text/html" id="vt-search-form-load-search">
    <li class="flexify" ref="popupPopupButton" v-show="showTopPopupButton">
        <a href="#" class="ts-icon-btn vt-load-search-link ts-popup-target" :class="{ 'vt-has-active': hasActiveSearch }" @mousedown="openPopup">
            <span v-html="config?.loadIcon"></span>
        </a>
    </li>

    <form-group :popup-key="widget_id + '_load_search'" ref="formGroup" @save="onPopupSave"
        @clear="onPopupClear" :wrapper-class="widget_id" class="vt_load_search" :class="showMainButton ? '' : 'hidden'">
        <template #trigger v-if="showMainButton">
            <div class="ts-filter ts-popup-target" :class="{ 'vt-has-active': hasActiveSearch }" @mousedown="openPopup">
                <span v-html="config?.loadIcon"></span>
                <span class="vt-load-label">{{ activeSearchTitle || config?.loadLabel }}</span>
                <span v-if="hasActiveSearch" class="vt-active-indicator"></span>
            </div>
        </template>
        <template #popup>
            <div class="vt-load-search-popup">
                <div class="ts-sticky-top">
                    <div class="ts-input-icon flexify">
                        <span v-html="config?.loadIcon"></span>
                        <input ref="searchInput" v-model="searchQuery" type="text"
                            :placeholder="config?.searchPlaceholder || 'Search saved...'" class="autofocus border-none">
                    </div>
                </div>

                <div class="vt-load-search-list" v-if="!loading">
                    <div v-if="hasActiveSearch" class="vt-load-search-item vt-clear-item" @click="clearSearch">
                        <div class="vt-load-search-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><path d="M18,6h0a1,1,0,0,0-1.414,0L12,10.586,7.414,6A1,1,0,0,0,6,6H6A1,1,0,0,0,6,7.414L10.586,12,6,16.586A1,1,0,0,0,6,18H6a1,1,0,0,0,1.414,0L12,13.414,16.586,18A1,1,0,0,0,18,18h0a1,1,0,0,0,0-1.414L13.414,12,18,7.414A1,1,0,0,0,18,6Z"/></svg>
                        </div>
                        <div class="vt-load-search-content">
                            <span class="vt-load-search-title">{{ config?.clearLabel || 'Clear filters' }}</span>
                        </div>
                    </div>

                    <div v-for="search in filteredSearches" :key="search.id"
                         class="vt-load-search-item"
                         :class="{ 'vt-active': activeSearchId === search.id }"
                         @click="applySearch(search)">
                        <div class="vt-load-search-icon" v-if="search.post_type?.icon" v-html="search.post_type.icon"></div>
                        <div class="vt-load-search-content">
                            <span class="vt-load-search-title">{{ search.title || 'Saved Search' }}</span>
                            <span class="vt-load-search-meta" v-if="search.filters?.length">
                                {{ search.filters.length }} filter{{ search.filters.length !== 1 ? 's' : '' }}
                            </span>
                        </div>
                        <div class="vt-load-search-check" v-if="activeSearchId === search.id">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"><path d="M9,20a1,1,0,0,1-.707-.293l-5-5A1,1,0,0,1,4.707,13.293L9,17.586,19.293,7.293a1,1,0,1,1,1.414,1.414l-11,11A1,1,0,0,1,9,20Z"/></svg>
                        </div>
                    </div>

                    <div v-if="filteredSearches.length === 0 && !loading" class="vt-load-search-empty">
                        <span v-if="searchQuery">{{ config?.noResultsText || 'No matching searches' }}</span>
                        <span v-else>{{ config?.emptyText || 'No saved searches yet' }}</span>
                    </div>
                </div>

                <div v-if="loading" class="vt-load-search-loading">
                    <span class="ts-loader"></span>
                </div>
            </div>
        </template>
    </form-group>
</script>
