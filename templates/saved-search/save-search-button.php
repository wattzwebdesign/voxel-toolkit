<script type="text/html" id="vt-search-form-save-search">
    <li class="flexify" ref="popupPopupButton" v-show="showTopPopupButton">
        <a href="#" class="ts-icon-btn vt-save-search-link ts-popup-target" @mousedown="onClick">
            <span v-html="config?.icon"></span>
        </a>
    </li>

    <form-group :popup-key="widget_id + '_save_search'" ref="formGroup" @save="onPopupSave"
        @clear="onPopupClear" :wrapper-class="widget_id" class="vt_save_search" :class="showMainButton ? '' : 'hidden'">
        <template #trigger v-if="showMainButton">
            <div class="ts-filter ts-popup-target" @mousedown="onClick">
                <span v-html="config?.icon"></span>
                {{config?.label}}
            </div>
        </template>
        <template #popup v-if="config?.askForTitle">
            <div class="">
                <div class="ts-input-icon ts-sticky-top flexify">
                    <span v-html="config?.icon"></span>
                    <input ref="input" v-model="value" type="text"
                        :placeholder="config?.placeholder" class="autofocus border-none"
                        @keyup.enter="onPopupSave">
                </div>
            </div>
        </template>
    </form-group>
</script>
