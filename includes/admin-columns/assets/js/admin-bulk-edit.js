/**
 * Admin Columns Bulk Edit
 *
 * Handles bulk editing of various column types in WordPress admin list tables.
 * Supports: taxonomy, post-relation, select, multiselect, switcher, text, number
 */

(function($) {
    'use strict';

    // Check if config is available
    if (typeof vtBulkEdit === 'undefined') {
        return;
    }

    var VTBulkEdit = {
        // State
        selectedPosts: [],
        currentColumn: null,
        allItems: [],
        selectedItems: [],
        currentAction: 'add',
        currentValue: '',
        isProcessing: false,
        isCancelled: false,

        // Elements
        $overlay: null,
        $modal: null,

        /**
         * Initialize
         */
        init: function() {
            this.injectButtons();
            this.bindEvents();
            this.createModal();
            this.updateSelectedPosts();
        },

        /**
         * Inject bulk edit buttons using index-based positioning
         */
        injectButtons: function() {
            var self = this;
            var $table = $('.wp-list-table');

            if (!$table.length || !vtBulkEdit.bulkEditColumns || !vtBulkEdit.bulkEditColumns.length) {
                return;
            }

            // Get header row
            var $headerRow = $table.find('thead tr').first();
            var $headerCells = $headerRow.find('th, td');

            // Create a container div for the buttons that will be positioned relatively
            var $container = $('<div class="vt-bulk-edit-container" style="display: none;"></div>');

            // Add buttons positioned based on header cell positions
            vtBulkEdit.bulkEditColumns.forEach(function(col) {
                var $headerCell = $headerCells.eq(col.index);
                if ($headerCell.length) {
                    var $btn = $('<button type="button" class="vt-bulk-edit-btn" ' +
                        'data-column-id="' + col.id + '" ' +
                        'data-field-key="' + col.field_key + '" ' +
                        'data-field-type="' + col.type + '" ' +
                        'data-label="' + self.escapeHtml(col.label) + '" ' +
                        'data-col-index="' + col.index + '"></button>');

                    // Add type-specific data attributes
                    if (col.taxonomy) {
                        $btn.attr('data-taxonomy', col.taxonomy);
                    }
                    if (col.relatedPostType) {
                        $btn.attr('data-related-post-type', col.relatedPostType);
                    }
                    if (col.options) {
                        $btn.attr('data-options', JSON.stringify(col.options));
                    }

                    $btn.append('<span class="dashicons dashicons-edit"></span>' + vtBulkEdit.i18n.bulkEdit);
                    $container.append($btn);
                }
            });

            // Insert container before the table
            $table.before($container);

            // Position buttons to align with their columns
            this.positionButtons();

            // Reposition on window resize
            $(window).on('resize', function() {
                self.positionButtons();
            });
        },

        /**
         * Position buttons to align with column headers
         */
        positionButtons: function() {
            var $table = $('.wp-list-table');
            var $headerRow = $table.find('thead tr').first();
            var $headerCells = $headerRow.find('th, td');
            var $container = $('.vt-bulk-edit-container');

            $container.find('.vt-bulk-edit-btn').each(function() {
                var $btn = $(this);
                var colIndex = parseInt($btn.attr('data-col-index'), 10);
                var $headerCell = $headerCells.eq(colIndex);

                if ($headerCell.length) {
                    var cellOffset = $headerCell.offset();
                    var containerOffset = $container.offset() || {left: 0};
                    var leftPos = cellOffset.left - containerOffset.left;

                    $btn.css({
                        'position': 'absolute',
                        'left': leftPos + 'px'
                    });
                }
            });
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Track checkbox changes
            $(document).on('change', '.wp-list-table input[type="checkbox"]', function() {
                self.updateSelectedPosts();
            });

            // Bulk edit button click
            $(document).on('click', '.vt-bulk-edit-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);

                if (self.selectedPosts.length === 0) {
                    return;
                }

                self.currentColumn = {
                    id: $btn.data('column-id'),
                    fieldKey: $btn.data('field-key'),
                    type: $btn.data('field-type'),
                    label: $btn.data('label'),
                    taxonomy: $btn.data('taxonomy'),
                    relatedPostType: $btn.data('related-post-type'),
                    options: $btn.data('options')
                };

                self.openModal();
            });

            // Close modal
            $(document).on('click', '.vt-bulk-edit-close, .vt-bulk-edit-overlay', function(e) {
                if (e.target === this && !self.isProcessing) {
                    self.closeModal();
                }
            });

            // ESC key to close
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27 && self.$overlay && self.$overlay.hasClass('active') && !self.isProcessing) {
                    self.closeModal();
                }
            });

            // Action selector
            $(document).on('click', '.vt-bulk-edit-actions label', function() {
                var $label = $(this);
                var action = $label.find('input').val();

                $('.vt-bulk-edit-actions label').removeClass('active');
                $label.addClass('active');
                self.currentAction = action;
            });

            // Dropdown trigger click
            $(document).on('click', '.vt-bulk-edit-dropdown-trigger', function(e) {
                e.stopPropagation();
                var $dropdown = $(this).siblings('.vt-bulk-edit-dropdown');
                var isOpen = $dropdown.is(':visible');

                if (isOpen) {
                    $dropdown.slideUp(150);
                    $(this).removeClass('open');
                } else {
                    $dropdown.slideDown(150);
                    $(this).addClass('open');
                    $dropdown.find('.vt-bulk-edit-search').focus();
                }
            });

            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.vt-bulk-edit-dropdown-container').length) {
                    $('.vt-bulk-edit-dropdown').slideUp(150);
                    $('.vt-bulk-edit-dropdown-trigger').removeClass('open');
                }
            });

            // Prevent dropdown from closing when clicking inside it
            $(document).on('click', '.vt-bulk-edit-dropdown', function(e) {
                e.stopPropagation();
            });

            // Search input
            $(document).on('input', '.vt-bulk-edit-search', function() {
                var search = $(this).val().toLowerCase();
                self.filterItems(search);
            });

            // Item selection (for list-based types)
            $(document).on('click', '.vt-bulk-edit-item', function(e) {
                if ($(e.target).is('input')) return;

                var $item = $(this);
                var $checkbox = $item.find('input[type="checkbox"]');
                $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
            });

            $(document).on('change', '.vt-bulk-edit-item input[type="checkbox"]', function() {
                var $checkbox = $(this);
                var itemId = $checkbox.val();
                var itemName = $checkbox.data('name');
                var $item = $checkbox.closest('.vt-bulk-edit-item');

                if ($checkbox.prop('checked')) {
                    $item.addClass('selected');
                    if (self.selectedItems.indexOf(itemId) === -1) {
                        self.selectedItems.push(itemId);
                        self.addItemChip(itemId, itemName);
                    }
                } else {
                    $item.removeClass('selected');
                    var idx = self.selectedItems.indexOf(itemId);
                    if (idx !== -1) {
                        self.selectedItems.splice(idx, 1);
                    }
                    self.removeItemChip(itemId);
                }
            });

            // Radio selection (for select type)
            $(document).on('change', '.vt-bulk-edit-radio input[type="radio"]', function() {
                self.currentValue = $(this).val();
            });

            // Switcher buttons
            $(document).on('click', '.vt-bulk-edit-switcher-btn', function() {
                $('.vt-bulk-edit-switcher-btn').removeClass('selected');
                $(this).addClass('selected');
                self.currentValue = $(this).data('value');
            });

            // Text/Number input
            $(document).on('input', '.vt-bulk-edit-input', function() {
                self.currentValue = $(this).val();
            });

            // Remove item chip
            $(document).on('click', '.vt-bulk-edit-chip-remove', function() {
                var itemId = $(this).closest('.vt-bulk-edit-chip').data('item-id');
                $('.vt-bulk-edit-item input[value="' + itemId + '"]').prop('checked', false).trigger('change');
            });

            // Save button
            $(document).on('click', '.vt-bulk-edit-save', function() {
                if (!self.validateInput()) {
                    return;
                }
                self.showConfirmation();
            });

            // Confirm button
            $(document).on('click', '.vt-bulk-edit-confirm-btn', function() {
                self.startBulkEdit();
            });

            // Cancel confirmation
            $(document).on('click', '.vt-bulk-edit-cancel-btn', function() {
                self.$modal.removeClass('confirming');
            });

            // Cancel processing
            $(document).on('click', '.vt-bulk-edit-progress-cancel .button', function() {
                self.isCancelled = true;
            });

            // Close complete
            $(document).on('click', '.vt-bulk-edit-complete .button', function() {
                self.closeModal();
                location.reload();
            });
        },

        /**
         * Update selected posts from checkboxes
         */
        updateSelectedPosts: function() {
            var self = this;
            self.selectedPosts = [];

            $('.wp-list-table tbody input[type="checkbox"][name="post[]"]:checked').each(function() {
                self.selectedPosts.push(parseInt($(this).val(), 10));
            });

            // Show/hide bulk edit row based on selection
            if (self.selectedPosts.length > 0) {
                $('.vt-bulk-edit-container').show();
                VTBulkEdit.positionButtons();
            } else {
                $('.vt-bulk-edit-container').hide();
            }
        },

        /**
         * Create modal HTML
         */
        createModal: function() {
            var html = '<div class="vt-bulk-edit-overlay">' +
                '<div class="vt-bulk-edit-modal">' +
                    '<div class="vt-bulk-edit-header">' +
                        '<h2>' + vtBulkEdit.i18n.bulkEdit + ': <span class="vt-bulk-edit-column-name"></span></h2>' +
                        '<button type="button" class="vt-bulk-edit-close">&times;</button>' +
                    '</div>' +
                    '<div class="vt-bulk-edit-body">' +
                        '<div class="vt-bulk-edit-content"></div>' +
                    '</div>' +
                    '<div class="vt-bulk-edit-footer">' +
                        '<span class="vt-bulk-edit-count"></span>' +
                        '<button type="button" class="button button-primary vt-bulk-edit-save">' + vtBulkEdit.i18n.saveChanges + '</button>' +
                    '</div>' +
                    '<div class="vt-bulk-edit-confirm">' +
                        '<div class="vt-bulk-edit-confirm-icon"><span class="dashicons dashicons-warning"></span></div>' +
                        '<p class="vt-bulk-edit-confirm-message"></p>' +
                        '<div class="vt-bulk-edit-confirm-actions">' +
                            '<button type="button" class="button vt-bulk-edit-cancel-btn">' + vtBulkEdit.i18n.cancel + '</button>' +
                            '<button type="button" class="button button-primary vt-bulk-edit-confirm-btn">' + vtBulkEdit.i18n.confirm + '</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="vt-bulk-edit-progress">' +
                        '<div class="vt-bulk-edit-progress-bar"><div class="vt-bulk-edit-progress-fill"></div></div>' +
                        '<p class="vt-bulk-edit-progress-text"></p>' +
                        '<div class="vt-bulk-edit-progress-cancel"><button type="button" class="button">' + vtBulkEdit.i18n.cancel + '</button></div>' +
                    '</div>' +
                    '<div class="vt-bulk-edit-complete">' +
                        '<div class="vt-bulk-edit-complete-icon"><span class="dashicons dashicons-yes-alt"></span></div>' +
                        '<p class="vt-bulk-edit-complete-message"></p>' +
                        '<div class="vt-bulk-edit-errors" style="display:none;">' +
                            '<div class="vt-bulk-edit-errors-title">Warnings:</div>' +
                            '<ul></ul>' +
                        '</div>' +
                        '<button type="button" class="button button-primary">' + vtBulkEdit.i18n.close + '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

            this.$overlay = $(html).appendTo('body');
            this.$modal = this.$overlay.find('.vt-bulk-edit-modal');
        },

        /**
         * Open modal
         */
        openModal: function() {
            var self = this;

            // Reset state
            this.selectedItems = [];
            this.currentAction = 'add';
            this.currentValue = '';
            this.allItems = [];
            this.isProcessing = false;
            this.isCancelled = false;

            // Reset UI
            this.$modal.removeClass('confirming processing complete');
            this.$overlay.find('.vt-bulk-edit-column-name').text(this.currentColumn.label);
            this.$overlay.find('.vt-bulk-edit-count').html('<strong>' + this.selectedPosts.length + '</strong> posts selected');

            // Render type-specific content
            this.renderContent();

            // Show modal
            this.$overlay.addClass('active');
        },

        /**
         * Render content based on field type
         */
        renderContent: function() {
            var self = this;
            var $content = this.$overlay.find('.vt-bulk-edit-content');
            var type = this.currentColumn.type;

            $content.empty();

            switch (type) {
                case 'taxonomy':
                case 'post-relation':
                case 'multiselect':
                    this.renderMultiValueContent($content);
                    break;

                case 'select':
                    this.renderSelectContent($content);
                    break;

                case 'switcher':
                    this.renderSwitcherContent($content);
                    break;

                case 'textarea':
                case 'texteditor':
                    this.renderTextareaContent($content);
                    break;

                case 'text':
                case 'email':
                case 'phone':
                case 'url':
                case 'number':
                case 'date':
                    this.renderInputContent($content);
                    break;
            }
        },

        /**
         * Render content for multi-value types (taxonomy, post-relation, multiselect)
         */
        renderMultiValueContent: function($content) {
            var self = this;
            var type = this.currentColumn.type;

            // Action selector
            var actionsHtml = '<div class="vt-bulk-edit-actions">' +
                '<label class="active"><input type="radio" name="vt_bulk_action" value="add" checked> ' + vtBulkEdit.i18n.addTo + '</label>' +
                '<label><input type="radio" name="vt_bulk_action" value="replace"> ' + vtBulkEdit.i18n.replace + '</label>' +
                '<label><input type="radio" name="vt_bulk_action" value="remove"> ' + vtBulkEdit.i18n.remove + '</label>' +
            '</div>';
            $content.append(actionsHtml);

            // Dropdown-style selector
            var searchPlaceholder = type === 'taxonomy' ? vtBulkEdit.i18n.searchTerms :
                                   type === 'post-relation' ? vtBulkEdit.i18n.searchPosts :
                                   'Select options...';

            var dropdownHtml = '<div class="vt-bulk-edit-dropdown-container">' +
                '<div class="vt-bulk-edit-selected-chips"></div>' +
                '<div class="vt-bulk-edit-dropdown-trigger">' +
                    '<span class="vt-bulk-edit-dropdown-placeholder">' + searchPlaceholder + '</span>' +
                    '<span class="dashicons dashicons-arrow-down-alt2"></span>' +
                '</div>' +
                '<div class="vt-bulk-edit-dropdown" style="display: none;">' +
                    '<div class="vt-bulk-edit-dropdown-search">' +
                        '<input type="text" class="vt-bulk-edit-search" placeholder="' + searchPlaceholder + '">' +
                    '</div>' +
                    '<div class="vt-bulk-edit-item-list">' +
                        '<div class="vt-bulk-edit-loading"><span class="spinner is-active"></span> Loading...</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
            $content.append(dropdownHtml);

            // Load items
            if (type === 'taxonomy') {
                this.loadTerms();
            } else if (type === 'post-relation') {
                this.loadRelatedPosts();
            } else if (type === 'multiselect') {
                this.loadOptions();
            }
        },

        /**
         * Render content for select type
         */
        renderSelectContent: function($content) {
            var options = this.currentColumn.options || [];
            var html = '<div class="vt-bulk-edit-radio-container">' +
                '<label class="vt-bulk-edit-label">' + vtBulkEdit.i18n.selectValue + '</label>';

            if (options.length === 0) {
                html += '<div class="vt-bulk-edit-no-items">' + vtBulkEdit.i18n.noOptionsFound + '</div>';
            } else {
                options.forEach(function(opt) {
                    html += '<label class="vt-bulk-edit-radio">' +
                        '<input type="radio" name="vt_bulk_value" value="' + opt.value + '"> ' +
                        '<span>' + opt.label + '</span>' +
                    '</label>';
                });
            }

            html += '</div>';
            $content.append(html);
        },

        /**
         * Render content for switcher type
         */
        renderSwitcherContent: function($content) {
            var html = '<div class="vt-bulk-edit-switcher-container">' +
                '<label class="vt-bulk-edit-switcher-label">' + vtBulkEdit.i18n.setValue + '</label>' +
                '<div class="vt-bulk-edit-switcher-options">' +
                    '<button type="button" class="vt-bulk-edit-switcher-btn true-btn" data-value="1">' +
                        '<span class="dashicons dashicons-yes"></span> ' + vtBulkEdit.i18n.setTrue +
                    '</button>' +
                    '<button type="button" class="vt-bulk-edit-switcher-btn false-btn" data-value="0">' +
                        '<span class="dashicons dashicons-no"></span> ' + vtBulkEdit.i18n.setFalse +
                    '</button>' +
                '</div>' +
            '</div>';
            $content.append(html);
        },

        /**
         * Render content for text/number/email/phone/url/date types
         */
        renderInputContent: function($content) {
            var type = this.currentColumn.type;
            var inputType = 'text';
            var placeholder = '';

            switch (type) {
                case 'number':
                    inputType = 'number';
                    placeholder = '0';
                    break;
                case 'email':
                    inputType = 'email';
                    placeholder = 'email@example.com';
                    break;
                case 'phone':
                    inputType = 'tel';
                    placeholder = '+1 (555) 123-4567';
                    break;
                case 'url':
                    inputType = 'url';
                    placeholder = 'https://example.com';
                    break;
                case 'date':
                    inputType = 'date';
                    break;
            }

            var html = '<div class="vt-bulk-edit-input-container">' +
                '<label class="vt-bulk-edit-input-label">' + vtBulkEdit.i18n.enterValue + '</label>' +
                '<input type="' + inputType + '" class="vt-bulk-edit-input-field vt-bulk-edit-input" placeholder="' + placeholder + '">' +
            '</div>';
            $content.append(html);
        },

        /**
         * Render content for textarea/texteditor types
         */
        renderTextareaContent: function($content) {
            var html = '<div class="vt-bulk-edit-textarea-container">' +
                '<label class="vt-bulk-edit-textarea-label">' + vtBulkEdit.i18n.enterValue + '</label>' +
                '<textarea class="vt-bulk-edit-textarea-field vt-bulk-edit-input" rows="6" placeholder=""></textarea>' +
            '</div>';
            $content.append(html);
        },

        /**
         * Close modal
         */
        closeModal: function() {
            if (this.isProcessing) {
                return;
            }
            this.$overlay.removeClass('active');
        },

        /**
         * Load terms for taxonomy field
         */
        loadTerms: function() {
            var self = this;

            $.ajax({
                url: vtBulkEdit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_admin_columns_bulk_get_terms',
                    nonce: vtBulkEdit.nonce,
                    taxonomy: self.currentColumn.taxonomy
                },
                success: function(response) {
                    if (response.success) {
                        self.allItems = response.data.map(function(item) {
                            return { id: String(item.term_id), name: item.name, count: item.count };
                        });
                        self.renderItems();
                    } else {
                        self.showLoadError(response.data.message);
                    }
                },
                error: function() {
                    self.showLoadError(vtBulkEdit.i18n.error);
                }
            });
        },

        /**
         * Load related posts for post-relation field
         */
        loadRelatedPosts: function() {
            var self = this;

            $.ajax({
                url: vtBulkEdit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_admin_columns_bulk_get_posts',
                    nonce: vtBulkEdit.nonce,
                    related_post_type: self.currentColumn.relatedPostType
                },
                success: function(response) {
                    if (response.success) {
                        self.allItems = response.data.map(function(item) {
                            return { id: String(item.id), name: item.title };
                        });
                        self.renderItems();
                    } else {
                        self.showLoadError(response.data.message);
                    }
                },
                error: function() {
                    self.showLoadError(vtBulkEdit.i18n.error);
                }
            });
        },

        /**
         * Load options for multiselect field
         */
        loadOptions: function() {
            var options = this.currentColumn.options || [];
            this.allItems = options.map(function(opt) {
                return { id: opt.value, name: opt.label };
            });
            this.renderItems();
        },

        /**
         * Show load error
         */
        showLoadError: function(message) {
            this.$overlay.find('.vt-bulk-edit-item-list').html(
                '<div class="vt-bulk-edit-no-items">' + message + '</div>'
            );
        },

        /**
         * Render items list
         */
        renderItems: function() {
            var self = this;
            var $list = this.$overlay.find('.vt-bulk-edit-item-list');
            var items = this.allItems;

            if (!items.length) {
                var noItemsMsg = this.currentColumn.type === 'taxonomy' ? vtBulkEdit.i18n.noTermsFound :
                                this.currentColumn.type === 'post-relation' ? vtBulkEdit.i18n.noPostsFound :
                                vtBulkEdit.i18n.noOptionsFound;
                $list.html('<div class="vt-bulk-edit-no-items">' + noItemsMsg + '</div>');
                return;
            }

            var html = '';
            items.forEach(function(item) {
                var isSelected = self.selectedItems.indexOf(item.id) !== -1;
                html += '<div class="vt-bulk-edit-item' + (isSelected ? ' selected' : '') + '">' +
                    '<input type="checkbox" value="' + item.id + '" data-name="' + self.escapeHtml(item.name) + '"' + (isSelected ? ' checked' : '') + '>' +
                    '<span class="vt-bulk-edit-item-name">' + self.escapeHtml(item.name) + '</span>' +
                    (item.count !== undefined ? '<span class="vt-bulk-edit-item-count">' + item.count + '</span>' : '') +
                '</div>';
            });

            $list.html(html);
        },

        /**
         * Filter items by search
         */
        filterItems: function(search) {
            if (!search) {
                this.renderItems();
                return;
            }

            var filtered = this.allItems.filter(function(item) {
                return item.name.toLowerCase().indexOf(search) !== -1;
            });

            var self = this;
            var $list = this.$overlay.find('.vt-bulk-edit-item-list');

            if (!filtered.length) {
                $list.html('<div class="vt-bulk-edit-no-items">' + vtBulkEdit.i18n.noTermsFound + '</div>');
                return;
            }

            var html = '';
            filtered.forEach(function(item) {
                var isSelected = self.selectedItems.indexOf(item.id) !== -1;
                html += '<div class="vt-bulk-edit-item' + (isSelected ? ' selected' : '') + '">' +
                    '<input type="checkbox" value="' + item.id + '" data-name="' + self.escapeHtml(item.name) + '"' + (isSelected ? ' checked' : '') + '>' +
                    '<span class="vt-bulk-edit-item-name">' + self.escapeHtml(item.name) + '</span>' +
                    (item.count !== undefined ? '<span class="vt-bulk-edit-item-count">' + item.count + '</span>' : '') +
                '</div>';
            });

            $list.html(html);
        },

        /**
         * Add item chip
         */
        addItemChip: function(itemId, itemName) {
            var $selected = this.$overlay.find('.vt-bulk-edit-selected-chips');
            var $chip = $('<span class="vt-bulk-edit-chip" data-item-id="' + itemId + '">' +
                this.escapeHtml(itemName) +
                '<button type="button" class="vt-bulk-edit-chip-remove">&times;</button>' +
            '</span>');
            $selected.append($chip);
            this.updateDropdownPlaceholder();
        },

        /**
         * Remove item chip
         */
        removeItemChip: function(itemId) {
            this.$overlay.find('.vt-bulk-edit-chip[data-item-id="' + itemId + '"]').remove();
            this.updateDropdownPlaceholder();
        },

        /**
         * Update dropdown placeholder based on selection
         */
        updateDropdownPlaceholder: function() {
            var $placeholder = this.$overlay.find('.vt-bulk-edit-dropdown-placeholder');
            var count = this.selectedItems.length;

            if (count > 0) {
                $placeholder.text(count + ' item' + (count > 1 ? 's' : '') + ' selected');
            } else {
                var type = this.currentColumn.type;
                var text = type === 'taxonomy' ? vtBulkEdit.i18n.searchTerms :
                          type === 'post-relation' ? vtBulkEdit.i18n.searchPosts :
                          'Select options...';
                $placeholder.text(text);
            }
        },

        /**
         * Validate input before save
         */
        validateInput: function() {
            var type = this.currentColumn.type;

            switch (type) {
                case 'taxonomy':
                case 'post-relation':
                case 'multiselect':
                    if (this.currentAction !== 'remove' && this.selectedItems.length === 0) {
                        alert(vtBulkEdit.i18n.selectValue);
                        return false;
                    }
                    break;

                case 'select':
                    if (!this.currentValue) {
                        alert(vtBulkEdit.i18n.selectValue);
                        return false;
                    }
                    break;

                case 'switcher':
                    if (this.currentValue === '') {
                        alert(vtBulkEdit.i18n.selectValue);
                        return false;
                    }
                    break;

                case 'text':
                case 'number':
                    // Allow empty values for text/number
                    break;
            }

            return true;
        },

        /**
         * Show confirmation
         */
        showConfirmation: function() {
            var actionText = this.getActionText();
            var message = vtBulkEdit.i18n.confirmAction
                .replace('%s', actionText)
                .replace('%d', this.selectedPosts.length);

            this.$overlay.find('.vt-bulk-edit-confirm-message').text(message);
            this.$modal.addClass('confirming');
        },

        /**
         * Get action text for display
         */
        getActionText: function() {
            var type = this.currentColumn.type;

            switch (type) {
                case 'taxonomy':
                case 'post-relation':
                case 'multiselect':
                    switch (this.currentAction) {
                        case 'add': return vtBulkEdit.i18n.addTo;
                        case 'replace': return vtBulkEdit.i18n.replace;
                        case 'remove': return vtBulkEdit.i18n.remove;
                    }
                    break;

                case 'select':
                case 'text':
                case 'number':
                    return vtBulkEdit.i18n.setValue;

                case 'switcher':
                    return this.currentValue === '1' ? vtBulkEdit.i18n.setTrue : vtBulkEdit.i18n.setFalse;
            }

            return this.currentAction;
        },

        /**
         * Start bulk edit process
         */
        startBulkEdit: function() {
            this.isProcessing = true;
            this.isCancelled = false;

            this.$modal.removeClass('confirming').addClass('processing');
            this.$overlay.find('.vt-bulk-edit-progress-fill').css('width', '0%');
            this.$overlay.find('.vt-bulk-edit-progress-text').text(
                vtBulkEdit.i18n.processing.replace('%d', '0').replace('%d', this.selectedPosts.length)
            );

            // Process in batches of 10
            this.processBatch(0, []);
        },

        /**
         * Process a batch of posts
         */
        processBatch: function(startIndex, allErrors) {
            var self = this;
            var batchSize = 10;
            var batch = this.selectedPosts.slice(startIndex, startIndex + batchSize);

            if (batch.length === 0 || this.isCancelled) {
                this.completeProcessing(startIndex, allErrors);
                return;
            }

            // Prepare values based on field type
            var values = [];
            var type = this.currentColumn.type;

            switch (type) {
                case 'taxonomy':
                case 'post-relation':
                case 'multiselect':
                    values = this.selectedItems;
                    break;

                case 'select':
                case 'switcher':
                case 'text':
                case 'number':
                    values = [this.currentValue];
                    break;
            }

            $.ajax({
                url: vtBulkEdit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vt_admin_columns_bulk_apply',
                    nonce: vtBulkEdit.nonce,
                    post_ids: batch,
                    field_key: this.currentColumn.fieldKey,
                    field_type: type,
                    taxonomy: this.currentColumn.taxonomy || '',
                    values: values,
                    bulk_action: this.currentAction,
                    post_type: vtBulkEdit.postType
                },
                success: function(response) {
                    var processed = startIndex + batch.length;
                    var percent = Math.round((processed / self.selectedPosts.length) * 100);

                    self.$overlay.find('.vt-bulk-edit-progress-fill').css('width', percent + '%');
                    self.$overlay.find('.vt-bulk-edit-progress-text').text(
                        vtBulkEdit.i18n.processing.replace('%d', processed).replace('%d', self.selectedPosts.length)
                    );

                    if (response.success && response.data.errors && response.data.errors.length) {
                        allErrors = allErrors.concat(response.data.errors);
                    }

                    // Process next batch
                    setTimeout(function() {
                        self.processBatch(processed, allErrors);
                    }, 100);
                },
                error: function(xhr, status, error) {
                    allErrors.push('Batch error: ' + error);

                    var processed = startIndex + batch.length;
                    setTimeout(function() {
                        self.processBatch(processed, allErrors);
                    }, 100);
                }
            });
        },

        /**
         * Complete processing
         */
        completeProcessing: function(processed, errors) {
            this.isProcessing = false;
            this.$modal.removeClass('processing').addClass('complete');

            var message;
            var $icon = this.$overlay.find('.vt-bulk-edit-complete-icon');

            if (this.isCancelled) {
                message = 'Cancelled. ' + processed + ' posts were updated.';
                $icon.addClass('error').find('.dashicons').removeClass('dashicons-yes-alt').addClass('dashicons-dismiss');
            } else {
                message = vtBulkEdit.i18n.complete.replace('%d', processed);
                $icon.removeClass('error').find('.dashicons').removeClass('dashicons-dismiss').addClass('dashicons-yes-alt');
            }

            this.$overlay.find('.vt-bulk-edit-complete-message').text(message);

            // Show errors if any
            var $errors = this.$overlay.find('.vt-bulk-edit-errors');
            if (errors.length > 0) {
                var $ul = $errors.find('ul').empty();
                errors.forEach(function(err) {
                    $ul.append('<li>' + err + '</li>');
                });
                $errors.show();
            } else {
                $errors.hide();
            }
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        VTBulkEdit.init();
    });

})(jQuery);
