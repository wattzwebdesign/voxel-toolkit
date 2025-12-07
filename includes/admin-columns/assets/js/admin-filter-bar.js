/**
 * Admin Filter Bar
 *
 * Advanced filtering system for admin list tables (posts and users).
 */
(function($) {
    'use strict';

    // Filter Bar Controller
    var VTFilterBar = {
        config: null,
        filters: [],
        container: null,
        isExpanded: false,
        filterLogic: 'and', // 'and' or 'or'

        init: function(config) {
            this.config = config;
            this.container = $('#vt-filter-bar-container');

            if (!this.container.length) {
                return;
            }

            // Move container after .tablenav.top (it renders inside by default)
            var tablenav = $('.tablenav.top');
            if (tablenav.length) {
                this.container.insertAfter(tablenav);
            }

            this.parseUrlFilters();
            // Auto-expand if there are active filters
            this.isExpanded = this.filters.length > 0;
            this.render();
            this.bindEvents();
        },

        // Parse existing filters from URL
        parseUrlFilters: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var self = this;

            // Parse filter logic
            if (urlParams.has('vt_filter_logic')) {
                this.filterLogic = urlParams.get('vt_filter_logic') === 'or' ? 'or' : 'and';
            }

            urlParams.forEach(function(value, key) {
                if (key.indexOf('vt_filter[') === 0) {
                    // Parse: vt_filter[0][field], vt_filter[0][operator], vt_filter[0][value]
                    var match = key.match(/vt_filter\[(\d+)\]\[(\w+)\]/);
                    if (match) {
                        var index = parseInt(match[1]);
                        var prop = match[2];

                        if (!self.filters[index]) {
                            self.filters[index] = { field: '', operator: 'equals', value: '' };
                        }
                        self.filters[index][prop] = value;
                    }
                }
            });

            // Clean up sparse array
            this.filters = this.filters.filter(function(f) {
                return f && f.field;
            });
        },

        // Get operators based on field type
        getOperators: function(fieldType) {
            var operators = {
                'text': [
                    { value: 'equals', label: this.config.i18n.equals },
                    { value: 'not_equals', label: this.config.i18n.not_equals },
                    { value: 'contains', label: this.config.i18n.contains },
                    { value: 'not_contains', label: this.config.i18n.not_contains },
                    { value: 'starts_with', label: this.config.i18n.starts_with },
                    { value: 'ends_with', label: this.config.i18n.ends_with },
                    { value: 'is_empty', label: this.config.i18n.is_empty },
                    { value: 'is_not_empty', label: this.config.i18n.is_not_empty }
                ],
                'number': [
                    { value: 'equals', label: this.config.i18n.equals },
                    { value: 'not_equals', label: this.config.i18n.not_equals },
                    { value: 'greater_than', label: this.config.i18n.greater_than },
                    { value: 'less_than', label: this.config.i18n.less_than },
                    { value: 'greater_equal', label: this.config.i18n.greater_equal },
                    { value: 'less_equal', label: this.config.i18n.less_equal },
                    { value: 'is_empty', label: this.config.i18n.is_empty },
                    { value: 'is_not_empty', label: this.config.i18n.is_not_empty }
                ],
                'date': [
                    { value: 'equals', label: this.config.i18n.equals },
                    { value: 'not_equals', label: this.config.i18n.not_equals },
                    { value: 'greater_than', label: this.config.i18n.after },
                    { value: 'less_than', label: this.config.i18n.before },
                    { value: 'is_empty', label: this.config.i18n.is_empty },
                    { value: 'is_not_empty', label: this.config.i18n.is_not_empty }
                ],
                'select': [
                    { value: 'equals', label: this.config.i18n.is },
                    { value: 'not_equals', label: this.config.i18n.is_not },
                    { value: 'is_empty', label: this.config.i18n.is_empty },
                    { value: 'is_not_empty', label: this.config.i18n.is_not_empty }
                ],
                'taxonomy': [
                    { value: 'equals', label: this.config.i18n.is },
                    { value: 'not_equals', label: this.config.i18n.is_not },
                    { value: 'is_empty', label: this.config.i18n.is_empty },
                    { value: 'is_not_empty', label: this.config.i18n.is_not_empty }
                ],
                'boolean': [
                    { value: 'equals', label: this.config.i18n.is }
                ]
            };

            return operators[fieldType] || operators['text'];
        },

        // Get field configuration
        getField: function(fieldKey) {
            for (var i = 0; i < this.config.fields.length; i++) {
                if (this.config.fields[i].key === fieldKey) {
                    return this.config.fields[i];
                }
            }
            return null;
        },

        // Check if operator needs a value input
        operatorNeedsValue: function(operator) {
            return ['is_empty', 'is_not_empty'].indexOf(operator) === -1;
        },

        // Render the filter bar
        render: function() {
            var self = this;
            var hasFilters = this.filters.length > 0;
            var toggleClass = this.isExpanded ? 'active' : '';
            var panelStyle = this.isExpanded ? '' : ' style="display: none;"';

            var html = '<div class="vt-filter-bar">';

            // Toggle button
            html += '<label class="vt-filter-toggle ' + toggleClass + '">';
            html += '<span class="vt-filter-toggle-track"><span class="vt-filter-toggle-thumb"></span></span>';
            html += '<span class="vt-filter-toggle-label">' + this.config.i18n.advanced_filter + '</span>';
            html += '</label>';

            // Filter count badge
            if (hasFilters) {
                html += '<span class="vt-filter-count">' + this.filters.length + '</span>';
            }

            html += '</div>';

            // Expandable panel
            html += '<div class="vt-filter-panel"' + panelStyle + '>';

            // Add property button row
            html += '<div class="vt-filter-add-row">';
            html += '<button type="button" class="vt-filter-add">';
            html += '<span class="dashicons dashicons-plus-alt2"></span> ' + this.config.i18n.add_property;
            html += '</button>';
            html += '</div>';

            // Filter rows
            if (hasFilters) {
                html += '<div class="vt-filter-rows">';
                this.filters.forEach(function(filter, index) {
                    html += self.renderFilterRow(filter, index);
                });
                html += '</div>';

                // AND/OR toggle (only show when more than 1 filter)
                if (this.filters.length > 1) {
                    html += '<div class="vt-filter-logic">';
                    html += '<input type="hidden" name="vt_filter_logic" value="' + this.filterLogic + '">';
                    html += '<label class="vt-filter-logic-option' + (this.filterLogic === 'and' ? ' active' : '') + '">';
                    html += '<input type="radio" name="vt_filter_logic_radio" value="and"' + (this.filterLogic === 'and' ? ' checked' : '') + '>';
                    html += '<span>' + this.config.i18n.match_all + '</span>';
                    html += '</label>';
                    html += '<label class="vt-filter-logic-option' + (this.filterLogic === 'or' ? ' active' : '') + '">';
                    html += '<input type="radio" name="vt_filter_logic_radio" value="or"' + (this.filterLogic === 'or' ? ' checked' : '') + '>';
                    html += '<span>' + this.config.i18n.match_any + '</span>';
                    html += '</label>';
                    html += '</div>';
                }

                // Action buttons
                html += '<div class="vt-filter-actions">';
                html += '<button type="submit" class="button button-primary vt-filter-apply">' + this.config.i18n.filter + '</button>';
                html += '<a href="' + this.config.baseUrl + '" class="vt-filter-clear">' + this.config.i18n.clear + '</a>';
                html += '</div>';
            }

            html += '</div>';

            this.container.html(html);
        },

        // Render a single filter row
        renderFilterRow: function(filter, index) {
            var self = this;
            var field = this.getField(filter.field);
            var fieldType = field ? field.filter_type : 'text';
            var operators = this.getOperators(fieldType);
            var needsValue = this.operatorNeedsValue(filter.operator);

            var html = '<div class="vt-filter-row" data-index="' + index + '">';

            // Field selector
            html += '<select name="vt_filter[' + index + '][field]" class="vt-filter-field">';
            html += '<option value="">' + this.config.i18n.select_field + '</option>';
            this.config.fields.forEach(function(f) {
                var selected = filter.field === f.key ? ' selected' : '';
                html += '<option value="' + self.escapeHtml(f.key) + '"' + selected + '>' + self.escapeHtml(f.label) + '</option>';
            });
            html += '</select>';

            // Operator selector
            html += '<select name="vt_filter[' + index + '][operator]" class="vt-filter-operator">';
            operators.forEach(function(op) {
                var selected = filter.operator === op.value ? ' selected' : '';
                html += '<option value="' + op.value + '"' + selected + '>' + self.escapeHtml(op.label) + '</option>';
            });
            html += '</select>';

            // Value input (depends on field type and operator)
            if (needsValue) {
                html += this.renderValueInput(filter, index, field, fieldType);
            } else {
                html += '<input type="hidden" name="vt_filter[' + index + '][value]" value="">';
            }

            // Remove button
            html += '<button type="button" class="vt-filter-remove" title="' + this.config.i18n.remove + '">';
            html += '<span class="dashicons dashicons-no-alt"></span>';
            html += '</button>';

            html += '</div>';

            return html;
        },

        // Render value input based on field type
        renderValueInput: function(filter, index, field, fieldType) {
            var self = this;
            var html = '';

            // If field has predefined options (select, taxonomy, etc.)
            if (field && field.options && field.options.length > 0) {
                html += '<select name="vt_filter[' + index + '][value]" class="vt-filter-value-select">';
                html += '<option value="">' + this.config.i18n.select_value + '</option>';
                field.options.forEach(function(opt) {
                    var selected = filter.value === opt.value ? ' selected' : '';
                    html += '<option value="' + self.escapeHtml(opt.value) + '"' + selected + '>' + self.escapeHtml(opt.label) + '</option>';
                });
                html += '</select>';
            }
            // Date field
            else if (fieldType === 'date') {
                html += '<input type="date" name="vt_filter[' + index + '][value]" class="vt-filter-value" value="' + this.escapeHtml(filter.value || '') + '">';
            }
            // Number field
            else if (fieldType === 'number') {
                html += '<input type="number" name="vt_filter[' + index + '][value]" class="vt-filter-value" value="' + this.escapeHtml(filter.value || '') + '" step="any">';
            }
            // Default text input
            else {
                html += '<input type="text" name="vt_filter[' + index + '][value]" class="vt-filter-value" value="' + this.escapeHtml(filter.value || '') + '" placeholder="' + this.config.i18n.enter_value + '">';
            }

            return html;
        },

        // Bind events (only once)
        bindEvents: function() {
            var self = this;

            // Unbind all previous events to prevent duplicates
            this.container.off('.vtfilter');

            // Toggle panel
            this.container.on('click.vtfilter', '.vt-filter-toggle', function(e) {
                e.preventDefault();
                self.isExpanded = !self.isExpanded;
                $(this).toggleClass('active', self.isExpanded);
                self.container.find('.vt-filter-panel').slideToggle(200);
            });

            // Add filter
            this.container.on('click.vtfilter', '.vt-filter-add', function(e) {
                e.preventDefault();
                self.filters.push({ field: '', operator: 'equals', value: '' });
                self.render();
                self.bindEvents();
            });

            // Remove filter
            this.container.on('click.vtfilter', '.vt-filter-remove', function(e) {
                e.preventDefault();
                var index = $(this).closest('.vt-filter-row').data('index');
                self.filters.splice(index, 1);
                self.render();
                self.bindEvents();
            });

            // Field change - update operators and value input
            this.container.on('change.vtfilter', '.vt-filter-field', function() {
                var row = $(this).closest('.vt-filter-row');
                var index = row.data('index');
                var fieldKey = $(this).val();

                self.filters[index].field = fieldKey;
                self.filters[index].value = ''; // Reset value when field changes

                self.render();
                self.bindEvents();
            });

            // Operator change - may need to show/hide value input
            this.container.on('change.vtfilter', '.vt-filter-operator', function() {
                var row = $(this).closest('.vt-filter-row');
                var index = row.data('index');
                var operator = $(this).val();

                self.filters[index].operator = operator;

                self.render();
                self.bindEvents();
            });

            // Value change
            this.container.on('change.vtfilter input.vtfilter', '.vt-filter-value, .vt-filter-value-select', function() {
                var row = $(this).closest('.vt-filter-row');
                var index = row.data('index');
                self.filters[index].value = $(this).val();
            });

            // Logic toggle change
            this.container.on('change.vtfilter', 'input[name="vt_filter_logic_radio"]', function() {
                self.filterLogic = $(this).val();
                self.container.find('input[name="vt_filter_logic"]').val(self.filterLogic);
                self.container.find('.vt-filter-logic-option').removeClass('active');
                $(this).closest('.vt-filter-logic-option').addClass('active');
            });
        },

        // Escape HTML
        escapeHtml: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    // Initialize when ready
    $(document).ready(function() {
        if (typeof vtFilterBarConfig !== 'undefined') {
            VTFilterBar.init(vtFilterBarConfig);
        }
    });

})(jQuery);
