<script type="text/html" id="vt-search-form-relations-filter">
    <form-group wrapper-class="prmr-popup" :popup-key="filter.id+':'+index" ref="formGroup" @save="onSave" @blur="saveValue" :wrapper-class="repeaterId"
        @clear="onClear">
        <template #trigger>
            <label v-if="$root.config.showLabels">
                {{ filter.label }}
            </label>
            <div class="ts-filter ts-popup-target" :class="{'ts-filled': Object.keys(selected).length > 0}"
                @mousedown="$root.activePopup = filter.id+':'+index; onOpen();">
                <template v-if="Object.keys(selected).length && selected[Object.keys(selected)[0]]?.logo">
                    <span v-html="selected[Object.keys(selected)[0]]?.logo"></span>
                </template>
                <template v-else>
                    <span v-html="filter.icon"></span>
                </template>
                <div class="ts-filter-text">
                    <span>{{ displayValue ? displayValue : filter?.props.placeholder || filter.label }}</span>
                </div>
            </div>
        </template>
        <template #popup>
            <div class="ts-sticky-top uib b-bottom">
                <div class="ts-input-icon flexify">
                    <?= \Voxel\svg('search.svg') ?>
                    <input v-model="search.term" ref="searchInput" type="text" class="autofocus"
                        placeholder="<?= esc_attr(_x('Search', 'post relation filter', 'voxel')) ?>">
                </div>
            </div>

            <div v-if="search.term.trim()" class="ts-term-dropdown ts-md-group ts-multilevel-dropdown"
                :class="{'vx-disabled': search.loading}">
                <ul class="simplify-ul ts-term-dropdown-list min-scroll">
                    <template v-if="search.list.length">
                        <li v-for="post in search.list">
                            <a href="#" class="flexify" @click.prevent="selectPost(post)">
                                <div class="ts-checkbox-container">
                                    <label class="container-radio">
                                        <input type="radio" :value="post.id"
                                            :checked="selected[post.id]" disabled hidden>
                                        <span class="checkmark"></span>
                                    </label>
                                </div>
                                <span>{{ post.title }}</span>
                                <div v-if="post.logo" class="ts-term-image">
                                    <span v-html="post.logo"></span>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="#" v-if="search.has_more"
                                @click.prevent="search.loading_more = true; serverSearchPosts(this, true)"
                                class="ts-btn ts-btn-4" :class="{'vx-pending': search.loading_more}">
                                <?= \Voxel\svg('reload.svg') ?>
                                <?= __('Load more', 'voxel') ?>
                            </a>
                        </li>
                    </template>
                    <template v-else>
                        <li class="ts-empty-user-tab">
                            <?= \Voxel\svg('search.svg') ?>
                            <p v-if="search.loading">
                                <?= _x('Searching posts', 'post relation filter', 'voxel') ?>
                            </p>
                            <p v-else>
                                <?= _x('No posts found', 'post relation filter', 'voxel') ?>
                            </p>
                        </li>
                    </template>
                </ul>
            </div>
            <div v-else class="ts-term-dropdown ts-md-group ts-multilevel-dropdown">
                <ul class="simplify-ul ts-term-dropdown-list min-scroll">
                    <template v-if="Object.keys(posts.list).length">
                        <li v-for="post in posts.list">
                            <a href="#" class="flexify" @click.prevent="selectPost(post)">
                                <div class="ts-checkbox-container">
                                    <label class="container-radio">
                                        <input type="radio" :value="post.id"
                                            :checked="selected[post.id]" disabled hidden>
                                        <span class="checkmark"></span>
                                    </label>
                                </div>
                                <span>{{ post.title }}</span>
                                <div v-if="post.logo" class="ts-term-image">
                                    <span v-html="post.logo"></span>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="#" v-if="posts.has_more" @click.prevent="loadPosts" class="ts-btn ts-btn-4"
                                :class="{'vx-pending': posts.loading}">
                                <?= \Voxel\svg('reload.svg') ?>
                                <?= __('Load more', 'voxel') ?>
                            </a>
                        </li>
                    </template>
                    <template v-else>
                        <li v-if="posts.loading" class="ts-empty-user-tab">
                            <?= \Voxel\svg('reload.svg') ?>
                            <p><?= __('Loading', 'voxel') ?></p>
                        </li>
                        <li v-else class="ts-empty-user-tab">
                            <?= \Voxel\svg('search.svg') ?>
                            <p><?= _x('No posts found', 'post relation filter', 'voxel') ?></p>
                        </li>
                    </template>
                </ul>
            </div>
        </template>
    </form-group>
</script>
