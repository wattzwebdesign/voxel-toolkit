<script type="text/html" id="vt-search-form-save-search">
    <li class="flexify" ref="popupPopupButton" v-show="showTopPopupButton">
        <a href="#" class="ts-icon-btn vt-save-search-link ts-popup-target" @mousedown="onClick">
            <span v-html="config?.icon"></span>
        </a>
    </li>

    <form-group :popup-key="widget_id + '_save_search'" ref="formGroup"
        :wrapper-class="widget_id" class="vt_save_search" :class="showMainButton ? '' : 'hidden'">
        <template #trigger v-if="showMainButton">
            <div class="ts-filter ts-popup-target" @mousedown="onClick">
                <span v-html="config?.icon"></span>
                {{config?.label}}
            </div>
        </template>
    </form-group>

    <!-- Modal overlay (teleported to body for proper centering) -->
    <Teleport to="body">
        <div class="vt-save-modal-overlay" v-if="showModal" @click.self="closeModal">
            <div class="vt-save-modal">
                <div class="vt-save-modal-header">
                    <span v-html="config?.icon"></span>
                    <span>{{ config?.modalTitle || 'Save Search' }}</span>
                    <button class="vt-save-modal-close" @click="closeModal">&times;</button>
                </div>
                <div class="vt-save-modal-body">
                    <input ref="modalInput" v-model="value" type="text"
                        :placeholder="config?.placeholder"
                        @keyup.enter="onModalSave"
                        class="vt-save-modal-input">
                </div>
                <div class="vt-save-modal-footer">
                    <button class="vt-save-modal-btn vt-save-modal-btn-cancel" @click="closeModal">
                        {{ config?.cancelLabel || 'Cancel' }}
                    </button>
                    <button class="vt-save-modal-btn vt-save-modal-btn-save" @click="onModalSave">
                        {{ config?.saveLabel || 'Save' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</script>
