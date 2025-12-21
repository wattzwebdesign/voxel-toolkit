<script type="text/html" id="vt-saved-search-availability-filter">
<span v-if="$root.config.showFilterIcons && filter.icon" class="filter-icon" v-html="filter.icon"></span>
<div class="ts-form-group">
    <label v-if="$root.config.showLabels">{{ filter.label }}</label>
    <div class="ts-saved-search-filter" :class="{'ts-filled': filter.value !== null}">
        <div class="ts-saved-search-filter-text">{{ availabilityDisplay }}</div>
    </div>
</div>
</script>
