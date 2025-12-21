<script type="text/html" id="vt-saved-search-keywords-filter">
<span v-if="$root.config.showFilterIcons && filter.icon" class="filter-icon" v-html="filter.icon"></span>
<div class="ts-form-group">
    <label v-if="$root.config.showLabels" class="">{{ filter.label }}</label>
    <div class="ts-saved-search-filter ts-popup-target" :class="{'ts-filled': filter.value !== null}">
        <div class="ts-saved-search-filter-text">{{ filter.value ? filter.value : filter.props.placeholder }}</div>
    </div>
</div>
</script>
