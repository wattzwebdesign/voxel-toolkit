<script type="text/html" id="sf-user-role-filter">
	<template v-if="filter.props.display_as === 'buttons'">
		<div class="ts-form-group" :class="{'vx-inert':isPending}">
			<label v-if="$root.config.showLabels">{{ filter.label }}</label>
			<ul class="simplify-ul addon-buttons flexify">
				<template v-for="role in filteredRoles">
					<li class="flexify" @click.prevent="selectRole(role)" :class="{'adb-selected': !!value[role.key]}">
						{{ role.label }}
					</li>
				</template>
			</ul>
		</div>
	</template>
	<form-group v-else :popup-key="filter.id" ref="formGroup" @save="onSave" @blur="onBlur" @clear="onClear"
		:wrapper-class="[repeaterId, 'vx-full-popup'].join(' ')"
		:class="{'vx-inert':isPending}">
		<template #trigger>
			<label v-if="$root.config.showLabels">{{ filter.label }}</label>
	 		<div class="ts-filter ts-popup-target" @mousedown="$root.activePopup = filter.id" :class="{'ts-filled': filter.value !== null}">
				<span v-html="filter.icon"></span>
	 			<div class="ts-filter-text">
	 				<template v-if="filter.value">
	 					{{ firstLabel }}
	 					<span v-if="remainingCount > 0" class="term-count">
	 						+{{ remainingCount.toLocaleString() }}
	 					</span>
	 				</template>
	 				<template v-else>{{ filter.props.placeholder }}</template>
	 			</div>
	 			<div class="ts-down-icon"></div>
	 		</div>
	 	</template>
		<template #popup>
			<div class="ts-sticky-top uib b-bottom" v-if="Object.keys(filter.props.choices).length >= 5">
				<div class="ts-input-icon flexify">
					<i class="las la-search"></i>
					<input v-model="search" ref="searchInput" type="text" placeholder="Search roles..." class="autofocus">
				</div>
			</div>
			<div class="ts-term-dropdown ts-multilevel-dropdown ts-md-group">
				<ul class="simplify-ul ts-term-dropdown-list">
					<li v-for="role in filteredRoles" :key="role.key" :class="{'ts-selected': !!value[role.key]}">
						<a href="#" class="flexify" @click.prevent="selectRole(role)">
							<div class="ts-checkbox-container">
								<label class="container-checkbox">
									<input type="checkbox" :value="role.key"
										:checked="value[role.key]" disabled hidden>
									<span class="checkmark"></span>
								</label>
							</div>
							<span>{{ role.label }}</span>
						</a>
					</li>
				</ul>
				<div v-if="!filteredRoles.length" class="ts-empty-user-tab">
					<span v-html="filter.icon"></span>
					<p>No roles found</p>
				</div>
			</div>
		</template>
	</form-group>
</script>
