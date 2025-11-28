<?php
/**
 * Admin Columns Feature
 *
 * Configure custom columns for Voxel post types in WordPress admin list tables.
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Admin_Columns {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Column renderer instance
     */
    private $renderer = null;

    /**
     * Column types instance
     */
    private $column_types = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Only run in admin
        if (!is_admin()) {
            return;
        }

        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/admin-columns/class-column-types.php';
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/admin-columns/class-column-renderer.php';

        $this->column_types = new Voxel_Toolkit_Column_Types();
        $this->renderer = new Voxel_Toolkit_Column_Renderer($this->column_types);
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'), 25);

        // Scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX endpoints
        add_action('wp_ajax_vt_admin_columns_get_post_types', array($this, 'ajax_get_post_types'));
        add_action('wp_ajax_vt_admin_columns_get_fields', array($this, 'ajax_get_fields'));
        add_action('wp_ajax_vt_admin_columns_save', array($this, 'ajax_save_config'));
        add_action('wp_ajax_vt_admin_columns_load', array($this, 'ajax_load_config'));
        add_action('wp_ajax_vt_admin_columns_restore_defaults', array($this, 'ajax_restore_defaults'));
        add_action('wp_ajax_vt_admin_columns_export', array($this, 'ajax_export_data'));

        // Register column hooks for configured post types
        add_action('admin_init', array($this, 'register_column_hooks'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'voxel-toolkit',
            __('Admin Columns', 'voxel-toolkit'),
            __('Admin Columns', 'voxel-toolkit'),
            'manage_options',
            'vt-admin-columns',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Load on settings page
        if (strpos($hook, 'vt-admin-columns') !== false) {
            $this->enqueue_settings_page_assets();
            return;
        }

        // Load on post list pages (edit.php) for configured post types
        if ($hook === 'edit.php') {
            $this->enqueue_post_list_assets();
        }
    }

    /**
     * Enqueue assets for the settings page
     */
    private function enqueue_settings_page_assets() {
        // Deregister any existing Vue to ensure we use the full build with template compiler
        wp_deregister_script('vue');

        // Vue 3 (full build with template compiler for in-DOM templates)
        wp_enqueue_script(
            'vue',
            'https://unpkg.com/vue@3.4.21/dist/vue.global.js',
            array(),
            '3.4.21',
            true
        );

        // SortableJS for drag and drop
        wp_enqueue_script(
            'sortablejs',
            'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js',
            array(),
            '1.15.2',
            true
        );

        // Admin Columns App
        wp_enqueue_script(
            'vt-admin-columns-app',
            VOXEL_TOOLKIT_PLUGIN_URL . 'includes/admin-columns/assets/js/admin-columns-app.js',
            array('vue', 'sortablejs'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Admin Columns CSS
        wp_enqueue_style(
            'vt-admin-columns',
            VOXEL_TOOLKIT_PLUGIN_URL . 'includes/admin-columns/assets/css/admin-columns.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
    }

    /**
     * Enqueue assets for post list pages
     */
    private function enqueue_post_list_assets() {
        global $typenow;

        if (empty($typenow)) {
            return;
        }

        $configs = get_option('voxel_toolkit_admin_columns', array());

        if (!isset($configs[$typenow])) {
            return;
        }

        // Admin Columns CSS for column display styles
        wp_enqueue_style(
            'vt-admin-columns',
            VOXEL_TOOLKIT_PLUGIN_URL . 'includes/admin-columns/assets/css/admin-columns.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        // SortableJS for export modal drag and drop
        wp_enqueue_script(
            'sortablejs',
            'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js',
            array(),
            '1.15.2',
            true
        );

        // Output column width styles
        $this->output_column_width_styles($typenow, $configs[$typenow]);

        // Always remove Voxel's default columns when Admin Columns is active for this post type
        add_action('admin_footer', array($this, 'output_voxel_cleanup_script'));

        // Add CSS to hide Voxel's default columns
        wp_add_inline_style('vt-admin-columns', '
            .wp-list-table .column-vx_listing_plan,
            .wp-list-table .column-vx_verified,
            .wp-list-table .column-vx_author,
            .wp-list-table th.column-vx_listing_plan,
            .wp-list-table th.column-vx_verified,
            .wp-list-table th.column-vx_author { display: none !important; }
        ');

        // Add inline script for dropdown positioning
        add_action('admin_footer', array($this, 'output_dropdown_positioning_script'));
    }

    /**
     * Output inline script for dropdown positioning
     */
    public function output_dropdown_positioning_script() {
        ?>
        <script>
        (function() {
            document.addEventListener('mouseover', function(e) {
                var toggle = e.target.closest('.vt-ac-relations-toggle');
                if (!toggle) return;

                var dropdown = toggle.nextElementSibling;
                if (!dropdown || !dropdown.classList.contains('vt-ac-relations-dropdown')) return;

                var rect = toggle.getBoundingClientRect();
                var viewportWidth = window.innerWidth;
                var viewportHeight = window.innerHeight;

                // Reset positioning
                dropdown.style.left = '';
                dropdown.style.right = '';
                dropdown.style.top = '';
                dropdown.style.bottom = '';

                // Check horizontal position
                if (rect.right + 300 > viewportWidth) {
                    // Would overflow right, align to right
                    dropdown.style.right = '0';
                    dropdown.style.left = 'auto';
                } else if (rect.left < 300) {
                    // Near left edge, align to left
                    dropdown.style.left = '0';
                    dropdown.style.right = 'auto';
                } else {
                    // Default: align right
                    dropdown.style.right = '0';
                    dropdown.style.left = 'auto';
                }

                // Check vertical position
                if (rect.bottom + 260 > viewportHeight) {
                    // Would overflow bottom, open upward
                    dropdown.style.bottom = '100%';
                    dropdown.style.top = 'auto';
                    dropdown.style.marginTop = '0';
                    dropdown.style.marginBottom = '4px';
                } else {
                    // Default: open downward
                    dropdown.style.top = '100%';
                    dropdown.style.bottom = 'auto';
                    dropdown.style.marginTop = '4px';
                    dropdown.style.marginBottom = '0';
                }
            });
        })();
        </script>
        <?php
    }

    /**
     * Output script to clean up Voxel's injected content
     */
    public function output_voxel_cleanup_script() {
        ?>
        <style>
            /* Hide Voxel's Plan column */
            .wp-list-table .column-vx_listing_plan,
            .wp-list-table th.column-vx_listing_plan,
            .wp-list-table td.column-vx_listing_plan { display: none !important; }
            /* Hide Voxel's injected listing plan links */
            .wp-list-table td > a[href*="voxel-paid-listings"] { display: none !important; }
        </style>
        <script>
        (function() {
            function cleanupVoxelContent() {
                // Remove paid-listings links from table cells only
                document.querySelectorAll('.wp-list-table td a[href*="paid-listings"]').forEach(function(link) {
                    link.remove();
                });
                // Hide plan columns
                document.querySelectorAll('.column-vx_listing_plan, [class*="vx_listing_plan"]').forEach(function(el) {
                    el.style.display = 'none';
                });
            }

            // Run immediately
            cleanupVoxelContent();

            // Run again after a delay (for AJAX content)
            setTimeout(cleanupVoxelContent, 100);
            setTimeout(cleanupVoxelContent, 500);
            setTimeout(cleanupVoxelContent, 1000);

            // Watch for dynamic changes
            var table = document.querySelector('.wp-list-table');
            if (table) {
                var observer = new MutationObserver(function(mutations) {
                    cleanupVoxelContent();
                });
                observer.observe(table, { childList: true, subtree: true });
            }
        })();
        </script>
        <?php
    }

    /**
     * Output inline CSS for column widths
     */
    private function output_column_width_styles($post_type, $config) {
        if (empty($config['columns'])) {
            return;
        }

        $styles = array();

        foreach ($config['columns'] as $col) {
            if (isset($col['width']) && $col['width']['mode'] !== 'auto' && !empty($col['width']['value'])) {
                $column_key = 'vt_' . $col['id'];
                $width_value = intval($col['width']['value']);
                $width_unit = $col['width']['mode'] === '%' ? '%' : 'px';

                $styles[] = sprintf(
                    '.wp-list-table .column-%s { width: %d%s; }',
                    esc_attr($column_key),
                    $width_value,
                    $width_unit
                );
            }
        }

        if (!empty($styles)) {
            wp_add_inline_style('vt-admin-columns', implode("\n", $styles));
        }
    }

    /**
     * Get localization data for JavaScript
     */
    public function get_js_data() {
        return array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_admin_columns_nonce'),
            'fieldTypes' => $this->column_types->get_field_type_info(),
            'i18n' => array(
                'save' => __('Save Changes', 'voxel-toolkit'),
                'saving' => __('Saving...', 'voxel-toolkit'),
                'saved' => __('Saved!', 'voxel-toolkit'),
                'addColumn' => __('Add Column', 'voxel-toolkit'),
                'removeColumn' => __('Remove', 'voxel-toolkit'),
                'selectPostType' => __('Select a post type', 'voxel-toolkit'),
                'selectField' => __('Select a field', 'voxel-toolkit'),
                'noColumns' => __('No columns configured. Click "Add Column" to get started.', 'voxel-toolkit'),
                'label' => __('Label', 'voxel-toolkit'),
                'field' => __('Field', 'voxel-toolkit'),
                'width' => __('Width', 'voxel-toolkit'),
                'auto' => __('Auto', 'voxel-toolkit'),
                'sortable' => __('Sortable', 'voxel-toolkit'),
                'filterable' => __('Filterable', 'voxel-toolkit'),
                'viewPosts' => __('View Posts', 'voxel-toolkit'),
                'restoreDefaults' => __('Restore Defaults', 'voxel-toolkit'),
                'confirmRestore' => __('Are you sure you want to restore default columns for this post type?', 'voxel-toolkit'),
                'error' => __('An error occurred. Please try again.', 'voxel-toolkit'),
                'loading' => __('Loading...', 'voxel-toolkit'),
            ),
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/admin-columns/templates/admin-columns-page.php';
    }

    /**
     * AJAX: Get Voxel post types
     */
    public function ajax_get_post_types() {
        check_ajax_referer('vt_admin_columns_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'voxel-toolkit')));
        }

        if (!class_exists('\Voxel\Post_Type')) {
            wp_send_json_error(array('message' => __('Voxel is not active', 'voxel-toolkit')));
        }

        $voxel_types = \Voxel\Post_Type::get_voxel_types();
        $post_types = array();

        foreach ($voxel_types as $key => $type) {
            $post_types[] = array(
                'key' => $key,
                'label' => $type->get_label(),
                'singular' => $type->get_singular_name(),
                'edit_url' => admin_url("edit.php?post_type={$key}"),
            );
        }

        wp_send_json_success($post_types);
    }

    /**
     * AJAX: Get fields for a post type
     */
    public function ajax_get_fields() {
        check_ajax_referer('vt_admin_columns_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'voxel-toolkit')));
        }

        $post_type_key = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';

        if (empty($post_type_key)) {
            wp_send_json_error(array('message' => __('Post type is required', 'voxel-toolkit')));
        }

        if (!class_exists('\Voxel\Post_Type')) {
            wp_send_json_error(array('message' => __('Voxel is not active', 'voxel-toolkit')));
        }

        $post_type = \Voxel\Post_Type::get($post_type_key);

        if (!$post_type) {
            wp_send_json_error(array('message' => __('Invalid post type', 'voxel-toolkit')));
        }

        // Organize fields into groups
        $grouped_fields = array(
            'voxel' => array(
                'label' => __('Voxel Fields', 'voxel-toolkit'),
                'fields' => array(),
            ),
            'wordpress' => array(
                'label' => __('WordPress', 'voxel-toolkit'),
                'fields' => array(),
            ),
            'toolkit' => array(
                'label' => __('Voxel Toolkit', 'voxel-toolkit'),
                'fields' => array(),
            ),
        );

        // Add Voxel post type fields first
        $fields = $post_type->get_fields();
        $skip_types = array('ui-step', 'ui-heading', 'ui-image');

        // Track toolkit field types
        $toolkit_field_types = array('poll');

        foreach ($fields as $field) {
            $type = $field->get_type();

            // Skip UI-only fields
            if (in_array($type, $skip_types)) {
                continue;
            }

            $type_info = $this->column_types->get_type_info($type);

            $field_entry = array(
                'key' => $field->get_key(),
                'label' => $field->get_label(),
                'type' => $type,
                'type_label' => $type_info['label'],
                'sortable' => $type_info['sortable'],
                'filterable' => $type_info['filterable'],
            );

            // Flag image fields for special handling
            if (in_array($type, array('image', 'profile-avatar'))) {
                $field_entry['is_image'] = true;
            }

            // Put toolkit fields in their own group
            if (in_array($type, $toolkit_field_types)) {
                $field_entry['type_label'] = $type_info['label'] . ' (VT)';
                $grouped_fields['toolkit']['fields'][] = $field_entry;
            } else {
                $grouped_fields['voxel']['fields'][] = $field_entry;
            }
        }

        // Add Voxel system fields (view counts, reviews, listing plan)
        $grouped_fields['voxel']['fields'][] = array(
            'key' => ':view_counts',
            'label' => __('View Counts', 'voxel-toolkit'),
            'type' => 'voxel-view-counts',
            'type_label' => __('Voxel', 'voxel-toolkit'),
            'sortable' => true,
            'filterable' => false,
        );

        $grouped_fields['voxel']['fields'][] = array(
            'key' => ':review_stats',
            'label' => __('Review Stats', 'voxel-toolkit'),
            'type' => 'voxel-review-stats',
            'type_label' => __('Voxel', 'voxel-toolkit'),
            'sortable' => true,
            'filterable' => false,
        );

        $grouped_fields['voxel']['fields'][] = array(
            'key' => ':listing_plan',
            'label' => __('Listing Plan', 'voxel-toolkit'),
            'type' => 'voxel-listing-plan',
            'type_label' => __('Voxel', 'voxel-toolkit'),
            'sortable' => false,
            'filterable' => true,
        );

        // Add WordPress core fields (sorted alphabetically)
        $wp_fields = array(
            array(
                'key' => ':author',
                'label' => __('Author', 'voxel-toolkit'),
                'type' => 'wp-author',
                'sortable' => true,
                'filterable' => true,
            ),
            array(
                'key' => ':comments',
                'label' => __('Comments', 'voxel-toolkit'),
                'type' => 'wp-comments',
                'sortable' => true,
                'filterable' => false,
            ),
            array(
                'key' => ':date',
                'label' => __('Date Published', 'voxel-toolkit'),
                'type' => 'wp-date',
                'sortable' => true,
                'filterable' => false,
            ),
            array(
                'key' => ':excerpt',
                'label' => __('Excerpt', 'voxel-toolkit'),
                'type' => 'wp-excerpt',
                'sortable' => false,
                'filterable' => false,
            ),
            array(
                'key' => ':thumbnail',
                'label' => __('Featured Image', 'voxel-toolkit'),
                'type' => 'wp-thumbnail',
                'sortable' => false,
                'filterable' => false,
                'is_image' => true,
            ),
            array(
                'key' => ':modified',
                'label' => __('Last Modified', 'voxel-toolkit'),
                'type' => 'wp-modified',
                'sortable' => true,
                'filterable' => false,
            ),
            array(
                'key' => ':menu_order',
                'label' => __('Menu Order', 'voxel-toolkit'),
                'type' => 'wp-menu-order',
                'sortable' => true,
                'filterable' => false,
            ),
            array(
                'key' => ':parent',
                'label' => __('Parent', 'voxel-toolkit'),
                'type' => 'wp-parent',
                'sortable' => false,
                'filterable' => false,
            ),
            array(
                'key' => ':permalink',
                'label' => __('Permalink', 'voxel-toolkit'),
                'type' => 'wp-permalink',
                'sortable' => false,
                'filterable' => false,
            ),
            array(
                'key' => ':id',
                'label' => __('Post ID', 'voxel-toolkit'),
                'type' => 'wp-id',
                'sortable' => true,
                'filterable' => false,
            ),
            array(
                'key' => ':slug',
                'label' => __('Slug', 'voxel-toolkit'),
                'type' => 'wp-slug',
                'sortable' => true,
                'filterable' => false,
            ),
            array(
                'key' => ':status',
                'label' => __('Status', 'voxel-toolkit'),
                'type' => 'wp-status',
                'sortable' => false,
                'filterable' => true,
            ),
            array(
                'key' => ':word_count',
                'label' => __('Word Count', 'voxel-toolkit'),
                'type' => 'wp-word-count',
                'sortable' => false,
                'filterable' => false,
            ),
        );

        foreach ($wp_fields as $wp_field) {
            $wp_field['type_label'] = __('WordPress', 'voxel-toolkit');
            $grouped_fields['wordpress']['fields'][] = $wp_field;
        }

        // Remove empty groups
        $grouped_fields = array_filter($grouped_fields, function($group) {
            return !empty($group['fields']);
        });

        wp_send_json_success($grouped_fields);
    }

    /**
     * AJAX: Save column configuration
     */
    public function ajax_save_config() {
        check_ajax_referer('vt_admin_columns_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'voxel-toolkit')));
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        $config = isset($_POST['config']) ? json_decode(stripslashes($_POST['config']), true) : null;

        if (empty($post_type)) {
            wp_send_json_error(array('message' => __('Post type is required', 'voxel-toolkit')));
        }

        if (!is_array($config)) {
            wp_send_json_error(array('message' => __('Invalid configuration', 'voxel-toolkit')));
        }

        $sanitized = $this->sanitize_config($config);

        $all_configs = get_option('voxel_toolkit_admin_columns', array());
        $all_configs[$post_type] = $sanitized;

        update_option('voxel_toolkit_admin_columns', $all_configs);

        wp_send_json_success(array('message' => __('Configuration saved', 'voxel-toolkit')));
    }

    /**
     * AJAX: Load column configuration
     */
    public function ajax_load_config() {
        check_ajax_referer('vt_admin_columns_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'voxel-toolkit')));
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';

        if (empty($post_type)) {
            wp_send_json_error(array('message' => __('Post type is required', 'voxel-toolkit')));
        }

        $all_configs = get_option('voxel_toolkit_admin_columns', array());

        if (isset($all_configs[$post_type])) {
            // Return saved config
            $config = $all_configs[$post_type];
        } else {
            // Return default columns for new post types
            $config = $this->get_default_config($post_type);
        }

        wp_send_json_success($config);
    }

    /**
     * Get default column configuration for a post type
     */
    private function get_default_config($post_type) {
        $default_columns = array(
            array(
                'id' => 'col_title',
                'field_key' => 'title',
                'label' => __('Title', 'voxel-toolkit'),
                'width' => array('mode' => 'auto', 'value' => null),
                'sortable' => true,
                'filterable' => false,
            ),
            array(
                'id' => 'col_date',
                'field_key' => ':date',
                'label' => __('Date Published', 'voxel-toolkit'),
                'width' => array('mode' => 'auto', 'value' => null),
                'sortable' => true,
                'filterable' => false,
            ),
            array(
                'id' => 'col_author',
                'field_key' => ':author',
                'label' => __('Author', 'voxel-toolkit'),
                'width' => array('mode' => 'auto', 'value' => null),
                'sortable' => true,
                'filterable' => false,
            ),
        );

        return array(
            'columns' => $default_columns,
            'settings' => array(
                'default_sort' => array('column' => 'date', 'order' => 'desc'),
                'primary_column' => 'title',
            ),
        );
    }

    /**
     * AJAX: Restore default columns
     */
    public function ajax_restore_defaults() {
        check_ajax_referer('vt_admin_columns_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'voxel-toolkit')));
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';

        if (empty($post_type)) {
            wp_send_json_error(array('message' => __('Post type is required', 'voxel-toolkit')));
        }

        $all_configs = get_option('voxel_toolkit_admin_columns', array());

        if (isset($all_configs[$post_type])) {
            unset($all_configs[$post_type]);
            update_option('voxel_toolkit_admin_columns', $all_configs);
        }

        wp_send_json_success(array('message' => __('Defaults restored', 'voxel-toolkit')));
    }

    /**
     * AJAX: Export data
     */
    public function ajax_export_data() {
        check_ajax_referer('vt_admin_columns_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'voxel-toolkit')));
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        $columns = isset($_POST['columns']) ? array_map('sanitize_text_field', $_POST['columns']) : array();
        $query_args = isset($_POST['query_args']) ? $_POST['query_args'] : array();

        if (empty($post_type) || empty($columns)) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'voxel-toolkit')));
        }

        // Get config
        $configs = get_option('voxel_toolkit_admin_columns', array());
        if (!isset($configs[$post_type])) {
            wp_send_json_error(array('message' => __('No configuration found', 'voxel-toolkit')));
        }

        $config = $configs[$post_type];

        // Build query args
        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => 'any',
        );

        // Apply filters from URL params
        if (!empty($query_args['s'])) {
            $args['s'] = sanitize_text_field($query_args['s']);
        }
        if (!empty($query_args['post_status']) && $query_args['post_status'] !== 'all') {
            $args['post_status'] = sanitize_text_field($query_args['post_status']);
        }
        if (!empty($query_args['author'])) {
            $args['author'] = intval($query_args['author']);
        }
        if (!empty($query_args['m'])) {
            $args['m'] = intval($query_args['m']);
        }
        if (!empty($query_args['orderby'])) {
            $args['orderby'] = sanitize_text_field($query_args['orderby']);
            $args['order'] = !empty($query_args['order']) ? sanitize_text_field($query_args['order']) : 'DESC';
        }

        // Apply custom filters (vt_filter_*)
        $meta_query = array();
        $tax_query = array();

        foreach ($query_args as $key => $value) {
            if (strpos($key, 'vt_filter_') === 0 && !empty($value)) {
                $field_key = str_replace('vt_filter_', '', $key);

                // Check if it's a taxonomy filter
                $field_type = $this->get_field_type($field_key, $post_type);
                if ($field_type === 'taxonomy') {
                    $taxonomy = $this->get_field_taxonomy($field_key, $post_type);
                    if ($taxonomy) {
                        $tax_query[] = array(
                            'taxonomy' => $taxonomy,
                            'field' => 'slug',
                            'terms' => sanitize_text_field($value),
                        );
                    }
                } else {
                    $meta_query[] = array(
                        'key' => $field_key,
                        'value' => sanitize_text_field($value),
                    );
                }
            }
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $posts = get_posts($args);

        // Build column mapping
        $column_map = array();
        foreach ($config['columns'] as $col) {
            if (in_array($col['id'], $columns)) {
                $column_map[$col['id']] = $col;
            }
        }

        // Generate CSV data
        $csv_data = array();

        // Headers
        $headers = array();
        foreach ($columns as $col_id) {
            if (isset($column_map[$col_id])) {
                $headers[] = $column_map[$col_id]['label'];
            }
        }
        $csv_data[] = $headers;

        // Rows
        foreach ($posts as $post) {
            $row = array();
            foreach ($columns as $col_id) {
                if (isset($column_map[$col_id])) {
                    $col = $column_map[$col_id];
                    $value = $this->get_export_value($col['field_key'], $post->ID, $col);
                    $row[] = $value;
                }
            }
            $csv_data[] = $row;
        }

        wp_send_json_success(array(
            'data' => $csv_data,
            'filename' => $post_type . '-export-' . date('Y-m-d') . '.csv',
        ));
    }

    /**
     * Get plain text value for export
     */
    private function get_export_value($field_key, $post_id, $column_config = null) {
        // Handle WordPress core fields
        if (strpos($field_key, ':') === 0) {
            return $this->get_wp_export_value($field_key, $post_id, $column_config);
        }

        // Check if Voxel is available
        if (!class_exists('\Voxel\Post')) {
            $meta_value = get_post_meta($post_id, $field_key, true);
            return is_array($meta_value) ? wp_json_encode($meta_value) : $meta_value;
        }

        $post = \Voxel\Post::get($post_id);
        if (!$post) {
            return '';
        }

        $field = $post->get_field($field_key);
        if (!$field) {
            $meta_value = get_post_meta($post_id, $field_key, true);
            return is_array($meta_value) ? wp_json_encode($meta_value) : $meta_value;
        }

        $value = $field->get_value();
        $type = $field->get_type();

        // Handle different field types
        switch ($type) {
            case 'title':
            case 'text':
            case 'textarea':
            case 'description':
            case 'texteditor':
                return wp_strip_all_tags($value);

            case 'number':
                return is_numeric($value) ? $value : '';

            case 'email':
            case 'phone':
            case 'url':
            case 'color':
            case 'timezone':
                return $value;

            case 'date':
                if (!empty($value)) {
                    return date_i18n(get_option('date_format'), strtotime($value));
                }
                return '';

            case 'select':
                return $value;

            case 'multiselect':
                return is_array($value) ? implode(', ', $value) : $value;

            case 'switcher':
                return $value ? __('Yes', 'voxel-toolkit') : __('No', 'voxel-toolkit');

            case 'image':
            case 'file':
                if (is_array($value)) {
                    $urls = array();
                    foreach ($value as $attachment_id) {
                        $url = wp_get_attachment_url($attachment_id);
                        if ($url) {
                            $urls[] = $url;
                        }
                    }
                    return implode(', ', $urls);
                }
                return wp_get_attachment_url($value) ?: '';

            case 'location':
                if (is_array($value) && isset($value['address'])) {
                    return $value['address'];
                }
                return '';

            case 'taxonomy':
                $taxonomy = $field->get_prop('taxonomy');
                if ($taxonomy) {
                    $terms = get_the_terms($post_id, $taxonomy);
                    if ($terms && !is_wp_error($terms)) {
                        return implode(', ', wp_list_pluck($terms, 'name'));
                    }
                }
                return '';

            case 'product':
                if (is_array($value) && isset($value['base_price']['amount'])) {
                    return $value['base_price']['amount'];
                }
                return '';

            case 'post-relation':
                if (is_array($value)) {
                    $titles = array();
                    foreach ($value as $related_id) {
                        $titles[] = get_the_title($related_id);
                    }
                    return implode(', ', $titles);
                }
                return '';

            default:
                if (is_array($value)) {
                    return wp_json_encode($value);
                }
                return $value;
        }
    }

    /**
     * Get WordPress field value for export
     */
    private function get_wp_export_value($field_key, $post_id, $column_config = null) {
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }

        switch ($field_key) {
            case ':date':
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post->post_date));

            case ':modified':
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post->post_modified));

            case ':author':
                $author = get_userdata($post->post_author);
                return $author ? $author->display_name : '';

            case ':status':
                $status_obj = get_post_status_object($post->post_status);
                return $status_obj ? $status_obj->label : $post->post_status;

            case ':id':
                return $post_id;

            case ':slug':
                return $post->post_name;

            case ':excerpt':
                return wp_strip_all_tags($post->post_excerpt);

            case ':thumbnail':
                $thumbnail_id = get_post_thumbnail_id($post_id);
                return $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';

            case ':permalink':
                return get_permalink($post_id);

            case ':comments':
                return $post->comment_count;

            case ':menu_order':
                return $post->menu_order;

            case ':parent':
                return $post->post_parent ? get_the_title($post->post_parent) : '';

            case ':word_count':
                return str_word_count(wp_strip_all_tags($post->post_content));

            case ':listing_plan':
                if (class_exists('\Voxel\Post')) {
                    $voxel_post = \Voxel\Post::get($post_id);
                    if ($voxel_post && method_exists($voxel_post, 'get_priority')) {
                        $priority = $voxel_post->get_priority();
                        if ($priority && isset($priority['plan'])) {
                            return $priority['plan']['label'] ?? '';
                        }
                    }
                }
                return '';

            default:
                return '';
        }
    }

    /**
     * Sanitize configuration
     */
    private function sanitize_config($config) {
        $sanitized = array(
            'columns' => array(),
            'settings' => array(
                'default_sort' => array('column' => 'date', 'order' => 'desc'),
                'primary_column' => 'title',
            ),
        );

        // Sanitize columns
        if (isset($config['columns']) && is_array($config['columns'])) {
            foreach ($config['columns'] as $column) {
                if (!isset($column['field_key']) || empty($column['field_key'])) {
                    continue;
                }

                $sanitized_column = array(
                    'id' => isset($column['id']) ? sanitize_text_field($column['id']) : $this->generate_id(),
                    'field_key' => sanitize_text_field($column['field_key']),
                    'label' => isset($column['label']) ? sanitize_text_field($column['label']) : '',
                    'width' => array(
                        'mode' => isset($column['width']['mode']) && in_array($column['width']['mode'], array('auto', 'px', '%'))
                            ? $column['width']['mode']
                            : 'auto',
                        'value' => isset($column['width']['value']) ? absint($column['width']['value']) : null,
                    ),
                    'sortable' => !empty($column['sortable']),
                    'filterable' => !empty($column['filterable']),
                );

                // Sanitize image settings if present
                if (isset($column['image_settings']) && is_array($column['image_settings'])) {
                    $valid_sizes = array('thumbnail', 'medium', 'medium_large', 'large', 'full');
                    $sanitized_column['image_settings'] = array(
                        'display_width' => isset($column['image_settings']['display_width'])
                            ? min(500, max(20, absint($column['image_settings']['display_width'])))
                            : 60,
                        'display_height' => isset($column['image_settings']['display_height'])
                            ? min(500, max(20, absint($column['image_settings']['display_height'])))
                            : 60,
                        'wp_size' => isset($column['image_settings']['wp_size']) && in_array($column['image_settings']['wp_size'], $valid_sizes)
                            ? $column['image_settings']['wp_size']
                            : 'thumbnail',
                    );
                }

                // Sanitize product settings if present
                if (isset($column['product_settings']) && is_array($column['product_settings'])) {
                    $valid_displays = array('price', 'discounted_price', 'price_range', 'product_type', 'booking_type', 'stock', 'calendar', 'deliverables', 'summary');
                    $sanitized_column['product_settings'] = array(
                        'display' => isset($column['product_settings']['display']) && in_array($column['product_settings']['display'], $valid_displays)
                            ? $column['product_settings']['display']
                            : 'price',
                    );
                }

                // Sanitize poll settings if present
                if (isset($column['poll_settings']) && is_array($column['poll_settings'])) {
                    $valid_displays = array('most_voted', 'most_voted_percent', 'least_voted', 'least_voted_percent', 'total_votes', 'option_count', 'summary');
                    $sanitized_column['poll_settings'] = array(
                        'display' => isset($column['poll_settings']['display']) && in_array($column['poll_settings']['display'], $valid_displays)
                            ? $column['poll_settings']['display']
                            : 'most_voted',
                    );
                }

                // Sanitize work hours settings if present
                if (isset($column['work_hours_settings']) && is_array($column['work_hours_settings'])) {
                    $valid_displays = array('status', 'today', 'badge');
                    $sanitized_column['work_hours_settings'] = array(
                        'display' => isset($column['work_hours_settings']['display']) && in_array($column['work_hours_settings']['display'], $valid_displays)
                            ? $column['work_hours_settings']['display']
                            : 'status',
                    );
                }

                // Sanitize location settings if present
                if (isset($column['location_settings']) && is_array($column['location_settings'])) {
                    $valid_displays = array('address', 'coordinates', 'latitude', 'longitude', 'full');
                    $sanitized_column['location_settings'] = array(
                        'display' => isset($column['location_settings']['display']) && in_array($column['location_settings']['display'], $valid_displays)
                            ? $column['location_settings']['display']
                            : 'address',
                    );
                }

                // Sanitize date settings if present
                if (isset($column['date_settings']) && is_array($column['date_settings'])) {
                    $valid_displays = array('date', 'datetime', 'relative');
                    $sanitized_column['date_settings'] = array(
                        'display' => isset($column['date_settings']['display']) && in_array($column['date_settings']['display'], $valid_displays)
                            ? $column['date_settings']['display']
                            : 'date',
                    );
                }

                // Sanitize recurring date settings if present
                if (isset($column['recurring_date_settings']) && is_array($column['recurring_date_settings'])) {
                    $valid_displays = array('start_date', 'start_datetime', 'end_date', 'end_datetime', 'date_range', 'frequency', 'multiday', 'allday', 'summary');
                    $sanitized_column['recurring_date_settings'] = array(
                        'display' => isset($column['recurring_date_settings']['display']) && in_array($column['recurring_date_settings']['display'], $valid_displays)
                            ? $column['recurring_date_settings']['display']
                            : 'start_date',
                    );
                }

                // Sanitize listing plan settings if present
                if (isset($column['listing_plan_settings']) && is_array($column['listing_plan_settings'])) {
                    $valid_displays = array('plan_name', 'amount', 'frequency', 'purchase_date', 'expiration', 'summary');
                    $sanitized_column['listing_plan_settings'] = array(
                        'display' => isset($column['listing_plan_settings']['display']) && in_array($column['listing_plan_settings']['display'], $valid_displays)
                            ? $column['listing_plan_settings']['display']
                            : 'plan_name',
                    );
                }

                // Sanitize title settings if present
                if (isset($column['title_settings']) && is_array($column['title_settings'])) {
                    $sanitized_column['title_settings'] = array(
                        'show_link' => isset($column['title_settings']['show_link'])
                            ? (bool) $column['title_settings']['show_link']
                            : true,
                        'show_actions' => isset($column['title_settings']['show_actions'])
                            ? (bool) $column['title_settings']['show_actions']
                            : true,
                    );
                }

                $sanitized['columns'][] = $sanitized_column;
            }
        }

        // Sanitize settings
        if (isset($config['settings']) && is_array($config['settings'])) {
            if (isset($config['settings']['default_sort'])) {
                $sanitized['settings']['default_sort'] = array(
                    'column' => isset($config['settings']['default_sort']['column'])
                        ? sanitize_text_field($config['settings']['default_sort']['column'])
                        : 'date',
                    'order' => isset($config['settings']['default_sort']['order']) && in_array($config['settings']['default_sort']['order'], array('asc', 'desc'))
                        ? $config['settings']['default_sort']['order']
                        : 'desc',
                );
            }

            if (isset($config['settings']['primary_column'])) {
                $sanitized['settings']['primary_column'] = sanitize_text_field($config['settings']['primary_column']);
            }
        }

        return $sanitized;
    }

    /**
     * Generate unique ID
     */
    private function generate_id() {
        return 'col_' . substr(md5(uniqid()), 0, 8);
    }

    /**
     * Track if hooks have been registered
     */
    private static $hooks_registered = false;

    /**
     * Register column hooks for all configured post types
     */
    public function register_column_hooks() {
        // Prevent double registration
        if (self::$hooks_registered) {
            return;
        }
        self::$hooks_registered = true;

        $configs = get_option('voxel_toolkit_admin_columns', array());

        if (empty($configs)) {
            return;
        }

        foreach ($configs as $post_type => $config) {
            if (empty($config['columns'])) {
                continue;
            }

            // Store config in closure
            $columns_config = $config;
            $renderer = $this->renderer;
            $column_types = $this->column_types;

            // Store for closure
            $current_post_type = $post_type;
            $self = $this;

            // Set up column handling on current_screen (runs after Voxel sets up its handlers)
            add_action('current_screen', function($screen) use ($post_type, $columns_config, $renderer, $self) {
                if ($screen->id !== "edit-{$post_type}") {
                    return;
                }

                // Remove Voxel's column handlers (they output to every column incorrectly)
                remove_all_actions("manage_{$post_type}_posts_custom_column");

                // Add our handler
                add_action("manage_{$post_type}_posts_custom_column", function($column, $post_id) use ($columns_config, $renderer, $self) {
                    $self->render_column_content_public($column, $post_id, $columns_config, $renderer);
                }, 10, 2);

                // Modify column headers at max priority - remove Voxel's default columns
                add_filter("manage_{$post_type}_posts_columns", function($columns) use ($columns_config, $self) {
                    unset($columns['vx_listing_plan']);
                    unset($columns['vx_verified']);
                    unset($columns['vx_author']);
                    return $self->modify_columns($columns, $columns_config);
                }, PHP_INT_MAX);

                // Disable WordPress's default row actions completely
                // We handle row actions ourselves in the title field renderer
                add_filter('list_table_primary_column', function($primary, $screen_id) use ($post_type) {
                    if ($screen_id === "edit-{$post_type}") {
                        return '_vt_no_primary_column';
                    }
                    return $primary;
                }, PHP_INT_MAX, 2);

                // Also filter post_row_actions to return empty array
                // This ensures no row actions are added by WordPress or other plugins
                add_filter('post_row_actions', function($actions, $post) use ($post_type) {
                    if ($post->post_type === $post_type) {
                        return array();
                    }
                    return $actions;
                }, PHP_INT_MAX, 2);

                // Also filter page_row_actions for hierarchical post types
                add_filter('page_row_actions', function($actions, $post) use ($post_type) {
                    if ($post->post_type === $post_type) {
                        return array();
                    }
                    return $actions;
                }, PHP_INT_MAX, 2);
            }, PHP_INT_MAX);

            // Register sortable columns
            add_filter("manage_edit-{$post_type}_sortable_columns", function($sortable) use ($columns_config) {
                return $this->register_sortable_columns($sortable, $columns_config);
            });
        }

        // Handle sorting query
        add_action('pre_get_posts', array($this, 'handle_sort_query'));

        // Handle filter query
        add_action('pre_get_posts', array($this, 'handle_filter_query'));

        // Add filter dropdowns
        add_action('restrict_manage_posts', array($this, 'render_filter_dropdowns'));

        // Add clear filter button (via JavaScript to position it correctly)
        add_action('admin_footer', array($this, 'render_clear_filter_button'));

        // Add export modal
        add_action('admin_footer', array($this, 'render_export_modal'));

        // Add Edit Columns link
        add_action('manage_posts_extra_tablenav', array($this, 'render_edit_columns_link'));
    }

    /**
     * Render the Edit Columns link in the post list table
     */
    public function render_edit_columns_link($which) {
        // Only show on the top tablenav
        if ($which !== 'top') {
            return;
        }

        global $typenow;

        if (empty($typenow)) {
            return;
        }

        $configs = get_option('voxel_toolkit_admin_columns', array());

        // Only show for configured post types
        if (!isset($configs[$typenow])) {
            return;
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            return;
        }

        $edit_url = admin_url('admin.php?page=vt-admin-columns&type=' . urlencode($typenow));

        ?>
        <a href="<?php echo esc_url($edit_url); ?>" class="button vt-edit-columns-btn" style="margin-left: 8px;">
            <span class="dashicons dashicons-admin-generic" style="vertical-align: middle; margin-top: -2px; font-size: 16px;"></span>
            <?php _e('Edit Columns', 'voxel-toolkit'); ?>
        </a>
        <button type="button" class="button vt-export-btn" id="vt-export-btn" style="margin-left: 4px;">
            <span class="dashicons dashicons-download" style="vertical-align: middle; margin-top: -2px; font-size: 16px;"></span>
            <?php _e('Export', 'voxel-toolkit'); ?>
        </button>
        <?php
    }

    /**
     * Render clear filter button via JavaScript
     */
    public function render_clear_filter_button() {
        global $typenow;

        if (empty($typenow)) {
            return;
        }

        $configs = get_option('voxel_toolkit_admin_columns', array());

        if (!isset($configs[$typenow])) {
            return;
        }

        // Check if any filters are active
        $has_active_filters = false;
        $filter_params = array();

        foreach ($configs[$typenow]['columns'] as $col) {
            if (empty($col['filterable'])) {
                continue;
            }

            // Determine the filter key based on field type
            $filter_key = 'vt_filter_' . $col['field_key'];

            // Special cases for WordPress/Voxel native parameters
            if ($col['field_key'] === ':status') {
                $filter_key = 'post_status';
            } elseif ($col['field_key'] === ':author') {
                $filter_key = 'author';
            } elseif ($col['field_key'] === ':listing_plan') {
                $filter_key = 'vt_filter_listing_plan';
            }

            if (isset($_GET[$filter_key]) && $_GET[$filter_key] !== '') {
                $has_active_filters = true;
                $filter_params[] = $filter_key;
            }
        }

        // Build clear URL (current URL without filter params)
        $clear_url = remove_query_arg($filter_params);

        ?>
        <script>
        (function() {
            var hasActiveFilters = <?php echo $has_active_filters ? 'true' : 'false'; ?>;
            var clearUrl = <?php echo wp_json_encode($clear_url); ?>;

            if (!hasActiveFilters) {
                return;
            }

            function addClearButton() {
                // Find the Filter button
                var filterButton = document.querySelector('#post-query-submit');
                if (!filterButton) {
                    return;
                }

                // Check if clear button already exists
                if (document.querySelector('.vt-clear-filter-btn')) {
                    return;
                }

                // Create clear button
                var clearBtn = document.createElement('a');
                clearBtn.href = clearUrl;
                clearBtn.className = 'button vt-clear-filter-btn';
                clearBtn.style.marginLeft = '4px';
                clearBtn.innerHTML = '<?php echo esc_js(__('Clear', 'voxel-toolkit')); ?>';

                // Insert after Filter button
                filterButton.parentNode.insertBefore(clearBtn, filterButton.nextSibling);
            }

            // Run when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', addClearButton);
            } else {
                addClearButton();
            }
        })();
        </script>
        <?php
    }

    /**
     * Render export modal
     */
    public function render_export_modal() {
        global $typenow;

        if (empty($typenow)) {
            return;
        }

        $configs = get_option('voxel_toolkit_admin_columns', array());

        if (!isset($configs[$typenow])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $config = $configs[$typenow];
        $columns = $config['columns'];

        // Get total count based on current filters
        global $wp_query;
        $total_items = $wp_query->found_posts ?? 0;

        ?>
        <!-- Export Modal Overlay -->
        <div id="vt-export-modal-overlay" class="vt-export-overlay" style="display: none;">
            <div class="vt-export-modal">
                <div class="vt-export-header">
                    <h2><?php _e('Export', 'voxel-toolkit'); ?></h2>
                    <button type="button" class="vt-export-close" id="vt-export-close">&times;</button>
                </div>
                <div class="vt-export-body">
                    <div class="vt-export-columns" id="vt-export-columns">
                        <?php foreach ($columns as $col) : ?>
                        <div class="vt-export-column-item" data-column-id="<?php echo esc_attr($col['id']); ?>">
                            <span class="vt-export-drag-handle">⋮⋮</span>
                            <label class="vt-export-toggle-switch">
                                <input type="checkbox" checked data-column-id="<?php echo esc_attr($col['id']); ?>">
                                <span class="vt-export-toggle-slider"></span>
                            </label>
                            <span class="vt-export-column-label"><?php echo esc_html($col['label']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="vt-export-footer">
                    <div class="vt-export-count">
                        <?php printf(__('This will affect <strong>%s items</strong>', 'voxel-toolkit'), '<span id="vt-export-count">' . number_format_i18n($total_items) . '</span>'); ?>
                    </div>
                    <button type="button" class="button button-primary vt-export-submit" id="vt-export-submit">
                        <?php _e('Export', 'voxel-toolkit'); ?>
                    </button>
                </div>
            </div>
        </div>

        <style>
        .vt-export-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .vt-export-modal {
            background: #fff;
            border-radius: 8px;
            width: 400px;
            max-width: 90vw;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        .vt-export-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid #eee;
        }
        .vt-export-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        .vt-export-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            line-height: 1;
        }
        .vt-export-close:hover {
            color: #333;
        }
        .vt-export-body {
            padding: 16px 20px;
            overflow-y: auto;
            flex: 1;
        }
        .vt-export-columns {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .vt-export-column-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
        }
        .vt-export-drag-handle {
            cursor: grab;
            color: #999;
            font-size: 14px;
            user-select: none;
        }
        .vt-export-drag-handle:active {
            cursor: grabbing;
        }
        .vt-export-column-label {
            flex: 1;
            font-size: 14px;
        }
        /* Toggle Switch */
        .vt-export-toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }
        .vt-export-toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .vt-export-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 24px;
        }
        .vt-export-toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        .vt-export-toggle-switch input:checked + .vt-export-toggle-slider {
            background-color: #2271b1;
        }
        .vt-export-toggle-switch input:checked + .vt-export-toggle-slider:before {
            transform: translateX(20px);
        }
        .vt-export-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-top: 1px solid #eee;
            background: #f9f9f9;
            border-radius: 0 0 8px 8px;
        }
        .vt-export-count {
            font-size: 14px;
            color: #666;
        }
        .vt-export-submit {
            min-width: 100px;
        }
        .vt-export-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        /* Sortable styles */
        .vt-export-column-item.sortable-ghost {
            opacity: 0.4;
        }
        .vt-export-column-item.sortable-drag {
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        </style>

        <script>
        (function() {
            var modal = document.getElementById('vt-export-modal-overlay');
            var openBtn = document.getElementById('vt-export-btn');
            var closeBtn = document.getElementById('vt-export-close');
            var submitBtn = document.getElementById('vt-export-submit');
            var columnsContainer = document.getElementById('vt-export-columns');

            if (!modal || !openBtn) return;

            // Open modal
            openBtn.addEventListener('click', function(e) {
                e.preventDefault();
                modal.style.display = 'flex';
                initSortable();
            });

            // Close modal
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });

            // Close on overlay click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.style.display === 'flex') {
                    modal.style.display = 'none';
                }
            });

            // Initialize Sortable for column reordering
            var sortableInstance = null;
            function initSortable() {
                if (sortableInstance) return;
                if (typeof Sortable !== 'undefined') {
                    sortableInstance = new Sortable(columnsContainer, {
                        handle: '.vt-export-drag-handle',
                        animation: 150,
                        ghostClass: 'sortable-ghost',
                        dragClass: 'sortable-drag'
                    });
                }
            }

            // Export functionality
            submitBtn.addEventListener('click', function() {
                var selectedColumns = [];
                var items = columnsContainer.querySelectorAll('.vt-export-column-item');

                items.forEach(function(item) {
                    var checkbox = item.querySelector('input[type="checkbox"]');
                    if (checkbox && checkbox.checked) {
                        selectedColumns.push(item.dataset.columnId);
                    }
                });

                if (selectedColumns.length === 0) {
                    alert('<?php echo esc_js(__('Please select at least one column to export.', 'voxel-toolkit')); ?>');
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.textContent = '<?php echo esc_js(__('Exporting...', 'voxel-toolkit')); ?>';

                // Get current URL params for filters
                var urlParams = new URLSearchParams(window.location.search);
                var queryArgs = {};
                urlParams.forEach(function(value, key) {
                    queryArgs[key] = value;
                });

                // Make AJAX request
                var formData = new FormData();
                formData.append('action', 'vt_admin_columns_export');
                formData.append('nonce', '<?php echo wp_create_nonce('vt_admin_columns_nonce'); ?>');
                formData.append('post_type', '<?php echo esc_js($typenow); ?>');
                selectedColumns.forEach(function(col) {
                    formData.append('columns[]', col);
                });
                formData.append('query_args', JSON.stringify(queryArgs));

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        downloadCSV(data.data.data, data.data.filename);
                        modal.style.display = 'none';
                    } else {
                        alert(data.data.message || '<?php echo esc_js(__('Export failed.', 'voxel-toolkit')); ?>');
                    }
                })
                .catch(function(error) {
                    console.error('Export error:', error);
                    alert('<?php echo esc_js(__('Export failed.', 'voxel-toolkit')); ?>');
                })
                .finally(function() {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '<?php echo esc_js(__('Export', 'voxel-toolkit')); ?>';
                });
            });

            // Download CSV
            function downloadCSV(data, filename) {
                var csv = data.map(function(row) {
                    return row.map(function(cell) {
                        // Escape quotes and wrap in quotes if contains comma, quote, or newline
                        var cellStr = String(cell === null || cell === undefined ? '' : cell);
                        if (cellStr.indexOf(',') !== -1 || cellStr.indexOf('"') !== -1 || cellStr.indexOf('\n') !== -1) {
                            cellStr = '"' + cellStr.replace(/"/g, '""') + '"';
                        }
                        return cellStr;
                    }).join(',');
                }).join('\n');

                // Add BOM for Excel UTF-8 compatibility
                var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement('a');
                var url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        })();
        </script>
        <?php
    }

    /**
     * Modify columns for a post type
     */
    private function modify_columns($columns, $config) {
        $new_columns = array();

        // Keep checkbox column
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }

        // Add configured columns - use unique ID to allow duplicate fields
        foreach ($config['columns'] as $col) {
            $column_key = 'vt_' . $col['id'];
            $new_columns[$column_key] = !empty($col['label']) ? $col['label'] : $col['field_key'];
        }

        // Note: We don't keep the default 'date' column since users can add :date if they want it

        return $new_columns;
    }

    /**
     * Public wrapper for render_column_content (needed for closure callback)
     */
    public function render_column_content_public($column, $post_id, $config, $renderer) {
        $this->render_column_content($column, $post_id, $config, $renderer);
    }

    /**
     * Render column content
     */
    private function render_column_content($column, $post_id, $config, $renderer) {
        // Check if this is one of our columns
        if (strpos($column, 'vt_') !== 0) {
            return;
        }

        $column_id = substr($column, 3); // Remove 'vt_' prefix

        // Find the column config by ID
        $column_config = null;
        foreach ($config['columns'] as $col) {
            if ($col['id'] === $column_id) {
                $column_config = $col;
                break;
            }
        }

        if (!$column_config) {
            echo '&mdash;';
            return;
        }


        // Pass column config to renderer for image sizing - use field_key for rendering
        echo $renderer->render($column_config['field_key'], $post_id, $column_config);
    }

    /**
     * Register sortable columns
     */
    private function register_sortable_columns($sortable, $config) {
        foreach ($config['columns'] as $col) {
            if (!empty($col['sortable'])) {
                $column_key = 'vt_' . $col['id'];
                $sortable[$column_key] = $col['field_key'];
            }
        }

        return $sortable;
    }

    /**
     * Handle sort query modifications
     */
    public function handle_sort_query($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');

        if (empty($orderby)) {
            return;
        }

        $post_type = $query->get('post_type');
        $configs = get_option('voxel_toolkit_admin_columns', array());

        if (!isset($configs[$post_type])) {
            return;
        }

        // Check if sorting by one of our columns
        foreach ($configs[$post_type]['columns'] as $col) {
            if ($col['field_key'] === $orderby && !empty($col['sortable'])) {
                // Handle WordPress core fields (prefixed with :)
                if (strpos($col['field_key'], ':') === 0) {
                    $this->handle_wp_field_sort($query, $col['field_key']);
                }
                // Handle Voxel title field (maps to post_title)
                elseif ($col['field_key'] === 'title') {
                    $query->set('orderby', 'title');
                }
                else {
                    $field_type = $this->get_field_type($col['field_key'], $post_type);

                    // Handle product fields with custom sorting
                    if ($field_type === 'product') {
                        $this->handle_product_sort($query, $col['field_key']);
                    } else {
                        // Handle Voxel meta fields
                        $query->set('meta_key', $col['field_key']);

                        // Determine if numeric sort is needed
                        $type_info = $this->column_types->get_type_info($field_type);
                        $query->set('orderby', $type_info['numeric'] ? 'meta_value_num' : 'meta_value');
                    }
                }

                break;
            }
        }
    }

    /**
     * Handle sorting for product fields (extract price from JSON)
     */
    private function handle_product_sort($query, $field_key) {
        // Add filter to modify SQL query
        add_filter('posts_clauses', function($clauses, $wp_query) use ($field_key) {
            global $wpdb;

            if (!$wp_query->is_main_query() || is_admin() === false) {
                return $clauses;
            }

            // Extract price from JSON using MySQL JSON functions
            // JSON path: $.base_price.amount
            $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS vt_product_sort ON {$wpdb->posts}.ID = vt_product_sort.post_id AND vt_product_sort.meta_key = '" . esc_sql($field_key) . "'";

            // Use CAST(JSON_EXTRACT()) to get numeric price value
            $clauses['orderby'] = "CAST(JSON_UNQUOTE(JSON_EXTRACT(vt_product_sort.meta_value, '$.base_price.amount')) AS DECIMAL(10,2)) " . esc_sql($wp_query->get('order', 'ASC'));

            return $clauses;
        }, 10, 2);
    }

    /**
     * Handle sorting for WordPress core fields
     */
    private function handle_wp_field_sort($query, $field_key) {
        switch ($field_key) {
            case ':date':
                $query->set('orderby', 'date');
                break;

            case ':modified':
                $query->set('orderby', 'modified');
                break;

            case ':author':
                $query->set('orderby', 'author');
                break;

            case ':id':
                $query->set('orderby', 'ID');
                break;

            case ':slug':
                $query->set('orderby', 'name');
                break;

            case ':comments':
                $query->set('orderby', 'comment_count');
                break;

            case ':menu_order':
                $query->set('orderby', 'menu_order');
                break;
        }
    }

    /**
     * Render filter dropdowns
     */
    public function render_filter_dropdowns($post_type) {
        $configs = get_option('voxel_toolkit_admin_columns', array());

        if (!isset($configs[$post_type])) {
            return;
        }

        foreach ($configs[$post_type]['columns'] as $col) {
            if (empty($col['filterable'])) {
                continue;
            }

            // Handle WordPress core fields
            if ($col['field_key'] === ':status') {
                $this->render_status_filter($col);
                continue;
            }

            if ($col['field_key'] === ':author') {
                $this->render_author_filter($col);
                continue;
            }

            if ($col['field_key'] === ':listing_plan') {
                $this->render_listing_plan_filter($col, $post_type);
                continue;
            }

            $field_type = $this->get_field_type($col['field_key'], $post_type);

            // Handle taxonomy fields
            if ($field_type === 'taxonomy') {
                $this->render_taxonomy_filter($col, $post_type);
                continue;
            }

            // Render filters for select and switcher types
            if (in_array($field_type, array('select', 'switcher'))) {
                $this->render_single_filter($col, $post_type, $field_type);
            }
        }
    }

    /**
     * Render the post status filter dropdown
     */
    private function render_status_filter($col) {
        $current_value = isset($_GET['post_status']) ? sanitize_text_field($_GET['post_status']) : '';

        $statuses = get_post_stati(array('show_in_admin_status_list' => true), 'objects');

        ?>
        <select name="post_status">
            <option value=""><?php echo esc_html($col['label'] ?: __('Status', 'voxel-toolkit')); ?></option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?php echo esc_attr($status->name); ?>" <?php selected($current_value, $status->name); ?>>
                    <?php echo esc_html($status->label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render author filter dropdown
     */
    private function render_author_filter($col) {
        $current_value = isset($_GET['author']) ? absint($_GET['author']) : '';

        // Get authors who have posts
        $authors = get_users(array(
            'who' => 'authors',
            'has_published_posts' => true,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ));

        ?>
        <select name="author">
            <option value=""><?php echo esc_html($col['label'] ?: __('Author', 'voxel-toolkit')); ?></option>
            <?php foreach ($authors as $author): ?>
                <option value="<?php echo esc_attr($author->ID); ?>" <?php selected($current_value, $author->ID); ?>>
                    <?php echo esc_html($author->display_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render listing plan filter dropdown
     */
    private function render_listing_plan_filter($col, $post_type) {
        $current_value = isset($_GET['vt_filter_listing_plan']) ? sanitize_text_field($_GET['vt_filter_listing_plan']) : '';

        // Get available plans for this post type
        $plans = $this->get_listing_plans($post_type);

        if (empty($plans)) {
            return;
        }

        ?>
        <select name="vt_filter_listing_plan">
            <option value=""><?php echo esc_html($col['label'] ?: __('Listing Plan', 'voxel-toolkit')); ?></option>
            <?php foreach ($plans as $plan_key => $plan_label): ?>
                <option value="<?php echo esc_attr($plan_key); ?>" <?php selected($current_value, $plan_key); ?>>
                    <?php echo esc_html($plan_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Get listing plans for a post type
     */
    private function get_listing_plans($post_type) {
        if (!class_exists('\Voxel\Post_Type')) {
            return array();
        }

        $voxel_post_type = \Voxel\Post_Type::get($post_type);
        if (!$voxel_post_type) {
            return array();
        }

        $plans = array();

        // Try to get plans from Voxel
        if (method_exists($voxel_post_type, 'get_plans')) {
            $voxel_plans = $voxel_post_type->get_plans();
            foreach ($voxel_plans as $plan) {
                if (method_exists($plan, 'get_key') && method_exists($plan, 'get_label')) {
                    $plans[$plan->get_key()] = $plan->get_label();
                }
            }
        }

        return $plans;
    }

    /**
     * Render taxonomy filter dropdown
     */
    private function render_taxonomy_filter($col, $post_type) {
        // Get the taxonomy from the field
        if (!class_exists('\Voxel\Post_Type')) {
            return;
        }

        $voxel_post_type = \Voxel\Post_Type::get($post_type);
        if (!$voxel_post_type) {
            return;
        }

        $field = $voxel_post_type->get_field($col['field_key']);
        if (!$field) {
            return;
        }

        // Get taxonomy key from field props
        $taxonomy = null;
        if (method_exists($field, 'get_prop')) {
            $taxonomy = $field->get_prop('taxonomy');
        }

        if (empty($taxonomy)) {
            return;
        }

        $current_value = isset($_GET['vt_filter_' . $col['field_key']])
            ? sanitize_text_field($_GET['vt_filter_' . $col['field_key']])
            : '';

        // Get terms for this taxonomy
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC',
        ));

        if (is_wp_error($terms) || empty($terms)) {
            return;
        }

        ?>
        <select name="vt_filter_<?php echo esc_attr($col['field_key']); ?>">
            <option value=""><?php echo esc_html($col['label'] ?: $col['field_key']); ?></option>
            <?php foreach ($terms as $term): ?>
                <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($current_value, $term->term_id); ?>>
                    <?php echo esc_html($term->name); ?> (<?php echo $term->count; ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render a single filter dropdown
     */
    private function render_single_filter($col, $post_type, $field_type) {
        $current_value = isset($_GET['vt_filter_' . $col['field_key']])
            ? sanitize_text_field($_GET['vt_filter_' . $col['field_key']])
            : '';

        $options = $this->get_filter_options($col['field_key'], $post_type, $field_type);

        if (empty($options)) {
            return;
        }

        ?>
        <select name="vt_filter_<?php echo esc_attr($col['field_key']); ?>">
            <option value=""><?php echo esc_html($col['label'] ?: $col['field_key']); ?></option>
            <?php foreach ($options as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_value, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Get filter options for a field
     */
    private function get_filter_options($field_key, $post_type, $field_type) {
        if ($field_type === 'switcher') {
            return array(
                '1' => __('Yes', 'voxel-toolkit'),
                '0' => __('No', 'voxel-toolkit'),
            );
        }

        // For select fields, get options from field config
        if (!class_exists('\Voxel\Post_Type')) {
            return array();
        }

        $voxel_post_type = \Voxel\Post_Type::get($post_type);
        if (!$voxel_post_type) {
            return array();
        }

        $field = $voxel_post_type->get_field($field_key);
        if (!$field) {
            return array();
        }

        $options = array();
        $choices = null;

        // Try to get choices from field using get_prop (Voxel's method)
        if (method_exists($field, 'get_prop')) {
            $choices = $field->get_prop('choices');
        }

        // Fallback: try get_choices method if available
        if (empty($choices) && method_exists($field, 'get_choices')) {
            $choices = $field->get_choices();
        }

        // Process choices in various formats
        if (is_array($choices)) {
            foreach ($choices as $key => $choice) {
                // Format 1: array of ['value' => ..., 'label' => ...]
                if (is_array($choice) && isset($choice['value']) && isset($choice['label'])) {
                    $options[$choice['value']] = $choice['label'];
                }
                // Format 2: array of ['value' => ..., 'label' => ...] with 'key' instead of 'value'
                elseif (is_array($choice) && isset($choice['key']) && isset($choice['label'])) {
                    $options[$choice['key']] = $choice['label'];
                }
                // Format 3: simple key => label associative array
                elseif (is_string($choice) && is_string($key)) {
                    $options[$key] = $choice;
                }
                // Format 4: simple indexed array of values (use value as both key and label)
                elseif (is_string($choice) && is_int($key)) {
                    $options[$choice] = ucfirst(str_replace(array('-', '_'), ' ', $choice));
                }
            }
        }

        return $options;
    }

    /**
     * Handle filter query modifications
     */
    public function handle_filter_query($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $post_type = $query->get('post_type');
        $configs = get_option('voxel_toolkit_admin_columns', array());

        if (!isset($configs[$post_type])) {
            return;
        }

        $meta_query = $query->get('meta_query') ?: array();
        $tax_query = $query->get('tax_query') ?: array();

        foreach ($configs[$post_type]['columns'] as $col) {
            if (empty($col['filterable'])) {
                continue;
            }

            // Handle listing plan filter
            if ($col['field_key'] === ':listing_plan') {
                if (isset($_GET['vt_filter_listing_plan']) && $_GET['vt_filter_listing_plan'] !== '') {
                    $plan_key = sanitize_text_field($_GET['vt_filter_listing_plan']);
                    // Listing plan is stored in voxel_subscriptions table, use meta query on plan key
                    $meta_query[] = array(
                        'key' => 'voxel:plan',
                        'value' => $plan_key,
                        'compare' => '=',
                    );
                }
                continue;
            }

            // Handle taxonomy filter
            $field_type = $this->get_field_type($col['field_key'], $post_type);
            if ($field_type === 'taxonomy') {
                $filter_key = 'vt_filter_' . $col['field_key'];
                if (isset($_GET[$filter_key]) && $_GET[$filter_key] !== '') {
                    $term_id = absint($_GET[$filter_key]);

                    // Get taxonomy from field
                    $taxonomy = $this->get_field_taxonomy($col['field_key'], $post_type);
                    if ($taxonomy) {
                        $tax_query[] = array(
                            'taxonomy' => $taxonomy,
                            'field' => 'term_id',
                            'terms' => $term_id,
                        );
                    }
                }
                continue;
            }

            // Handle regular meta field filters (select, switcher)
            $filter_key = 'vt_filter_' . $col['field_key'];

            if (!isset($_GET[$filter_key]) || $_GET[$filter_key] === '') {
                continue;
            }

            $filter_value = sanitize_text_field($_GET[$filter_key]);

            $meta_query[] = array(
                'key' => $col['field_key'],
                'value' => $filter_value,
                'compare' => '=',
            );
        }

        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }

        if (!empty($tax_query)) {
            $query->set('tax_query', $tax_query);
        }
    }

    /**
     * Get taxonomy key from a taxonomy field
     */
    private function get_field_taxonomy($field_key, $post_type) {
        if (!class_exists('\Voxel\Post_Type')) {
            return null;
        }

        $voxel_post_type = \Voxel\Post_Type::get($post_type);
        if (!$voxel_post_type) {
            return null;
        }

        $field = $voxel_post_type->get_field($field_key);
        if (!$field) {
            return null;
        }

        if (method_exists($field, 'get_prop')) {
            return $field->get_prop('taxonomy');
        }

        return null;
    }

    /**
     * Get field type for a given field key
     */
    private function get_field_type($field_key, $post_type) {
        if (!class_exists('\Voxel\Post_Type')) {
            return 'text';
        }

        $voxel_post_type = \Voxel\Post_Type::get($post_type);
        if (!$voxel_post_type) {
            return 'text';
        }

        $field = $voxel_post_type->get_field($field_key);
        if (!$field) {
            return 'text';
        }

        return $field->get_type();
    }
}
