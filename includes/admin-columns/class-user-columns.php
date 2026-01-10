<?php
/**
 * User Admin Columns Feature
 *
 * Configure custom columns for WordPress users list table.
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_User_Columns {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Custom post count subquery for sorting
     */
    private $post_count_subquery = null;

    /**
     * Post count sort order
     */
    private $post_count_order = 'DESC';

    /**
     * Membership expiration filter value
     */
    private $membership_expiration_filter = null;

    /**
     * Membership expiration sort data
     */
    private $membership_expiration_sort_order = 'ASC';

    /**
     * User field filters for pre_user_query
     */
    private $user_field_filters = array();

    /**
     * Post count filter data
     */
    private $post_count_filter = null;

    /**
     * Filter logic (AND/OR)
     */
    private $filter_logic = 'AND';

    /**
     * All filters for combined processing
     */
    private $all_filters = array();

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

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX endpoints
        add_action('wp_ajax_vt_user_columns_get_fields', array($this, 'ajax_get_fields'));
        add_action('wp_ajax_vt_user_columns_save', array($this, 'ajax_save_config'));
        add_action('wp_ajax_vt_user_columns_load', array($this, 'ajax_load_config'));
        add_action('wp_ajax_vt_user_columns_restore_defaults', array($this, 'ajax_restore_defaults'));

        // Register column hooks for users
        add_action('admin_init', array($this, 'register_column_hooks'));

        // Enqueue assets on users page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue scripts on users page
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'users.php') {
            return;
        }

        $config = get_option('voxel_toolkit_user_columns', array());
        if (empty($config) || empty($config['columns'])) {
            return;
        }

        // Admin Columns CSS for column display styles
        wp_enqueue_style(
            'vt-admin-columns',
            VOXEL_TOOLKIT_PLUGIN_URL . 'includes/admin-columns/assets/css/admin-columns.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        // Check if any columns have filterable enabled
        $has_filterable = false;
        foreach ($config['columns'] as $col) {
            if (!empty($col['filterable'])) {
                $has_filterable = true;
                break;
            }
        }

        // Enqueue filter bar JS if there are filterable columns
        if ($has_filterable) {
            wp_enqueue_script(
                'vt-admin-filter-bar',
                VOXEL_TOOLKIT_PLUGIN_URL . 'includes/admin-columns/assets/js/admin-filter-bar.js',
                array('jquery'),
                VOXEL_TOOLKIT_VERSION,
                true
            );
        }

        // Output column width styles
        $this->output_column_width_styles($config);
    }

    /**
     * Output inline CSS for column widths
     */
    private function output_column_width_styles($config) {
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
     * AJAX: Get available user fields
     */
    public function ajax_get_fields() {
        check_ajax_referer('vt_admin_columns_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'voxel-toolkit')));
        }

        // Organize fields into groups
        $grouped_fields = array(
            'basic' => array(
                'label' => __('Basic Info', 'voxel-toolkit'),
                'fields' => array(),
            ),
            'contact' => array(
                'label' => __('Contact', 'voxel-toolkit'),
                'fields' => array(),
            ),
            'profile' => array(
                'label' => __('Profile', 'voxel-toolkit'),
                'fields' => array(),
            ),
            'stats' => array(
                'label' => __('Statistics', 'voxel-toolkit'),
                'fields' => array(),
            ),
        );

        // Basic Info fields
        $grouped_fields['basic']['fields'] = array(
            array(
                'key' => ':user_id',
                'label' => __('User ID', 'voxel-toolkit'),
                'type' => 'user-id',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => true,
            ),
            array(
                'key' => ':username',
                'label' => __('Username', 'voxel-toolkit'),
                'type' => 'user-username',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => true,
            ),
            array(
                'key' => ':display_name',
                'label' => __('Display Name', 'voxel-toolkit'),
                'type' => 'user-display-name',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => true,
            ),
            array(
                'key' => ':full_name',
                'label' => __('Full Name', 'voxel-toolkit'),
                'type' => 'user-full-name',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => true,
            ),
            array(
                'key' => ':first_name',
                'label' => __('First Name', 'voxel-toolkit'),
                'type' => 'user-first-name',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => true,
            ),
            array(
                'key' => ':last_name',
                'label' => __('Last Name', 'voxel-toolkit'),
                'type' => 'user-last-name',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => true,
            ),
            array(
                'key' => ':nickname',
                'label' => __('Nickname', 'voxel-toolkit'),
                'type' => 'user-nickname',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => true,
            ),
            array(
                'key' => ':role',
                'label' => __('Role', 'voxel-toolkit'),
                'type' => 'user-role',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => true,
            ),
            array(
                'key' => ':registered_date',
                'label' => __('Registered Date', 'voxel-toolkit'),
                'type' => 'user-registered',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => true,
            ),
        );

        // Contact fields
        $grouped_fields['contact']['fields'] = array(
            array(
                'key' => ':email',
                'label' => __('Email', 'voxel-toolkit'),
                'type' => 'user-email',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => true,
            ),
            array(
                'key' => ':website',
                'label' => __('Website', 'voxel-toolkit'),
                'type' => 'user-website',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => true,
            ),
        );

        // Profile fields
        $grouped_fields['profile']['fields'] = array(
            array(
                'key' => ':profile_picture',
                'label' => __('Profile Picture', 'voxel-toolkit'),
                'type' => 'user-avatar',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => false,
                'is_image' => true,
            ),
            array(
                'key' => ':language',
                'label' => __('Language', 'voxel-toolkit'),
                'type' => 'user-language',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => true,
            ),
        );

        // Statistics fields
        $grouped_fields['stats']['fields'] = array(
            array(
                'key' => ':post_count',
                'label' => __('Post Count', 'voxel-toolkit'),
                'type' => 'user-post-count',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => true,
                'has_post_type_setting' => true,
            ),
        );

        // Online Status fields (only if Online Status function is enabled)
        $settings = Voxel_Toolkit_Settings::instance();
        if ($settings->is_function_enabled('online_status')) {
            $online_settings = $settings->get_function_settings('online_status', array());
            $show_in_admin = isset($online_settings['show_in_admin']) ? (bool) $online_settings['show_in_admin'] : true;

            if ($show_in_admin) {
                $grouped_fields['stats']['fields'][] = array(
                    'key' => ':online_status',
                    'label' => __('Online Status', 'voxel-toolkit'),
                    'type' => 'user-online-status',
                    'type_label' => __('Voxel Toolkit', 'voxel-toolkit'),
                    'sortable' => true,
                    'filterable' => true,
                );
                $grouped_fields['stats']['fields'][] = array(
                    'key' => ':last_seen',
                    'label' => __('Last Seen', 'voxel-toolkit'),
                    'type' => 'user-last-seen',
                    'type_label' => __('Voxel Toolkit', 'voxel-toolkit'),
                    'sortable' => true,
                    'filterable' => false,
                );
            }
        }

        // Voxel fields (only show if Voxel is active)
        if (class_exists('\Voxel\Modules\Paid_Memberships\Plan')) {
            $grouped_fields['voxel'] = array(
                'label' => __('Voxel', 'voxel-toolkit'),
                'fields' => array(
                    array(
                        'key' => ':membership_plan',
                        'label' => __('Membership Plan', 'voxel-toolkit'),
                        'type' => 'user-membership-plan',
                        'type_label' => __('Voxel', 'voxel-toolkit'),
                        'sortable' => true,
                        'filterable' => true,
                        'has_membership_plan_setting' => true,
                    ),
                ),
            );
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

        $config = isset($_POST['config']) ? json_decode(stripslashes($_POST['config']), true) : null;

        if (!is_array($config)) {
            wp_send_json_error(array('message' => __('Invalid configuration', 'voxel-toolkit')));
        }

        $sanitized = $this->sanitize_config($config);
        update_option('voxel_toolkit_user_columns', $sanitized);

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

        $config = get_option('voxel_toolkit_user_columns', array());

        if (empty($config)) {
            // Return default columns
            $config = $this->get_default_config();
        }

        wp_send_json_success($config);
    }

    /**
     * AJAX: Restore default columns
     */
    public function ajax_restore_defaults() {
        check_ajax_referer('vt_admin_columns_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'voxel-toolkit')));
        }

        delete_option('voxel_toolkit_user_columns');

        wp_send_json_success(array('message' => __('Defaults restored', 'voxel-toolkit')));
    }

    /**
     * Get default column configuration
     */
    private function get_default_config() {
        return array(
            'columns' => array(
                array(
                    'id' => 'col_username',
                    'field_key' => ':username',
                    'label' => __('Username', 'voxel-toolkit'),
                    'width' => array('mode' => 'auto', 'value' => null),
                    'sortable' => true,
                    'filterable' => false,
                ),
                array(
                    'id' => 'col_email',
                    'field_key' => ':email',
                    'label' => __('Email', 'voxel-toolkit'),
                    'width' => array('mode' => 'auto', 'value' => null),
                    'sortable' => true,
                    'filterable' => false,
                ),
                array(
                    'id' => 'col_role',
                    'field_key' => ':role',
                    'label' => __('Role', 'voxel-toolkit'),
                    'width' => array('mode' => 'auto', 'value' => null),
                    'sortable' => false,
                    'filterable' => true,
                ),
                array(
                    'id' => 'col_registered',
                    'field_key' => ':registered_date',
                    'label' => __('Registered', 'voxel-toolkit'),
                    'width' => array('mode' => 'auto', 'value' => null),
                    'sortable' => true,
                    'filterable' => false,
                ),
            ),
            'settings' => array(
                'default_sort' => array('column' => 'registered_date', 'order' => 'desc'),
            ),
        );
    }

    /**
     * Sanitize configuration
     */
    private function sanitize_config($config) {
        $sanitized = array(
            'columns' => array(),
            'settings' => array(
                'default_sort' => array('column' => 'registered_date', 'order' => 'desc'),
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
                    $sanitized_column['image_settings'] = array(
                        'display_width' => isset($column['image_settings']['display_width'])
                            ? min(200, max(20, absint($column['image_settings']['display_width'])))
                            : 40,
                        'display_height' => isset($column['image_settings']['display_height'])
                            ? min(200, max(20, absint($column['image_settings']['display_height'])))
                            : 40,
                    );
                }

                // Sanitize date settings if present
                if (isset($column['date_settings']) && is_array($column['date_settings'])) {
                    $valid_displays = array('date', 'datetime', 'relative');
                    $valid_date_formats = array('wordpress', 'j F Y', 'F j, Y', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'd.m.Y', 'M j, Y', 'j M Y', 'custom');
                    $valid_time_formats = array('wordpress', 'g:i a', 'g:i A', 'H:i', 'H:i:s', 'custom');
                    $sanitized_column['date_settings'] = array(
                        'display' => isset($column['date_settings']['display']) && in_array($column['date_settings']['display'], $valid_displays)
                            ? $column['date_settings']['display']
                            : 'date',
                        'date_format' => isset($column['date_settings']['date_format']) && in_array($column['date_settings']['date_format'], $valid_date_formats)
                            ? $column['date_settings']['date_format']
                            : 'wordpress',
                        'custom_date_format' => isset($column['date_settings']['custom_date_format'])
                            ? sanitize_text_field($column['date_settings']['custom_date_format'])
                            : '',
                        'time_format' => isset($column['date_settings']['time_format']) && in_array($column['date_settings']['time_format'], $valid_time_formats)
                            ? $column['date_settings']['time_format']
                            : 'wordpress',
                        'custom_time_format' => isset($column['date_settings']['custom_time_format'])
                            ? sanitize_text_field($column['date_settings']['custom_time_format'])
                            : '',
                    );
                }

                // Sanitize post count settings if present
                if (isset($column['post_count_settings']) && is_array($column['post_count_settings'])) {
                    $sanitized_column['post_count_settings'] = array(
                        'post_type' => isset($column['post_count_settings']['post_type'])
                            ? sanitize_text_field($column['post_count_settings']['post_type'])
                            : 'post',
                        'post_statuses' => isset($column['post_count_settings']['post_statuses']) && is_array($column['post_count_settings']['post_statuses'])
                            ? array_map('sanitize_text_field', $column['post_count_settings']['post_statuses'])
                            : array('publish'),
                    );
                }

                // Sanitize membership plan settings if present
                if (isset($column['membership_plan_settings']) && is_array($column['membership_plan_settings'])) {
                    $valid_displays = array('plan_name', 'status', 'expiration', 'summary');
                    $sanitized_column['membership_plan_settings'] = array(
                        'display' => isset($column['membership_plan_settings']['display']) && in_array($column['membership_plan_settings']['display'], $valid_displays)
                            ? $column['membership_plan_settings']['display']
                            : 'plan_name',
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
                        : 'registered_date',
                    'order' => isset($config['settings']['default_sort']['order']) && in_array($config['settings']['default_sort']['order'], array('asc', 'desc'))
                        ? $config['settings']['default_sort']['order']
                        : 'desc',
                );
            }

            // Quick actions column - which column displays row actions
            if (isset($config['settings']['quick_actions_column'])) {
                $sanitized['settings']['quick_actions_column'] = sanitize_text_field($config['settings']['quick_actions_column']);
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
     * Register column hooks for users list
     */
    public function register_column_hooks() {
        $config = get_option('voxel_toolkit_user_columns', array());

        if (empty($config) || empty($config['columns'])) {
            return;
        }

        // Modify column headers
        add_filter('manage_users_columns', array($this, 'modify_columns'));

        // Render column content
        add_filter('manage_users_custom_column', array($this, 'render_column'), 10, 3);

        // Register sortable columns
        add_filter('manage_users_sortable_columns', array($this, 'register_sortable_columns'));

        // Handle sorting
        add_action('pre_get_users', array($this, 'handle_sort_query'));

        // Add filter bar
        add_action('restrict_manage_users', array($this, 'render_filter_bar'));

        // Handle filter query
        add_action('pre_get_users', array($this, 'handle_filter_query'));

        // Enqueue filter bar assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_filter_assets'));

        // Add Edit Columns link
        add_action('manage_users_extra_tablenav', array($this, 'render_edit_columns_link'));
    }

    /**
     * Modify columns for users list
     */
    public function modify_columns($columns) {
        $config = get_option('voxel_toolkit_user_columns', array());

        if (empty($config) || empty($config['columns'])) {
            return $columns;
        }

        $new_columns = array();

        // Keep checkbox column
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }

        // Add configured columns
        foreach ($config['columns'] as $col) {
            $column_key = 'vt_' . $col['id'];
            $new_columns[$column_key] = !empty($col['label']) ? $col['label'] : $col['field_key'];
        }

        return $new_columns;
    }

    /**
     * Render column content
     */
    public function render_column($output, $column_name, $user_id) {
        // Check if this is one of our columns
        if (strpos($column_name, 'vt_') !== 0) {
            return $output;
        }

        $config = get_option('voxel_toolkit_user_columns', array());
        if (empty($config) || empty($config['columns'])) {
            return $output;
        }

        $column_id = substr($column_name, 3); // Remove 'vt_' prefix

        // Find the column config by ID
        $column_config = null;
        foreach ($config['columns'] as $col) {
            if ($col['id'] === $column_id) {
                $column_config = $col;
                break;
            }
        }

        if (!$column_config) {
            return '&mdash;';
        }

        $rendered = $this->render_user_field($column_config['field_key'], $user_id, $column_config);

        // Check if this column should have row actions
        $quick_actions_column = isset($config['settings']['quick_actions_column']) ? $config['settings']['quick_actions_column'] : '';
        if ($quick_actions_column && $quick_actions_column === $column_id) {
            $rendered .= $this->render_user_row_actions($user_id);
        }

        return $rendered;
    }

    /**
     * Render row actions for user
     */
    private function render_user_row_actions($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return '';
        }

        $actions = array();

        // Edit link
        if (current_user_can('edit_user', $user_id)) {
            $edit_url = get_edit_user_link($user_id);
            $actions['edit'] = '<a href="' . esc_url($edit_url) . '">' . __('Edit', 'voxel-toolkit') . '</a>';
        }

        // Delete/Remove link
        if ($user_id !== get_current_user_id()) {
            if (is_multisite()) {
                if (current_user_can('remove_user', $user_id)) {
                    $remove_url = wp_nonce_url(
                        add_query_arg('action', 'remove', "users.php?user=$user_id"),
                        'bulk-users'
                    );
                    $actions['remove'] = '<a href="' . esc_url($remove_url) . '" class="submitdelete">' . __('Remove', 'voxel-toolkit') . '</a>';
                }
            } else {
                if (current_user_can('delete_user', $user_id)) {
                    $delete_url = wp_nonce_url(
                        add_query_arg('action', 'delete', "users.php?user=$user_id"),
                        'bulk-users'
                    );
                    $actions['delete'] = '<a href="' . esc_url($delete_url) . '" class="submitdelete">' . __('Delete', 'voxel-toolkit') . '</a>';
                }
            }
        }

        // View link (author archive)
        if (get_current_blog_id() === get_user_meta($user_id, 'primary_blog', true)) {
            $author_url = get_author_posts_url($user_id);
            if ($author_url) {
                $actions['view'] = '<a href="' . esc_url($author_url) . '" target="_blank">' . __('View', 'voxel-toolkit') . '</a>';
            }
        }

        // Send password reset
        if (current_user_can('edit_user', $user_id)) {
            $reset_url = wp_nonce_url(
                add_query_arg(array('action' => 'resetpassword', 'users' => $user_id), 'users.php'),
                'bulk-users'
            );
            $actions['resetpassword'] = '<a href="' . esc_url($reset_url) . '">' . __('Send password reset', 'voxel-toolkit') . '</a>';
        }

        if (empty($actions)) {
            return '';
        }

        // Build the row actions HTML
        $action_links = array();
        $i = 0;
        foreach ($actions as $action => $link) {
            $i++;
            if ($i === 1) {
                $action_links[] = '<span class="' . esc_attr($action) . '">' . $link . '</span>';
            } else {
                $action_links[] = '<span class="' . esc_attr($action) . '"> | ' . $link . '</span>';
            }
        }

        return '<div class="row-actions">' . implode('', $action_links) . '</div>';
    }

    /**
     * Render user field value
     */
    private function render_user_field($field_key, $user_id, $column_config = null) {
        $user = get_userdata($user_id);
        if (!$user) {
            return '&mdash;';
        }

        switch ($field_key) {
            case ':user_id':
                return '<span class="vt-ac-number">' . esc_html($user_id) . '</span>';

            case ':username':
                $edit_link = get_edit_user_link($user_id);
                return '<strong><a href="' . esc_url($edit_link) . '">' . esc_html($user->user_login) . '</a></strong>';

            case ':display_name':
                return esc_html($user->display_name);

            case ':full_name':
                $first = get_user_meta($user_id, 'first_name', true);
                $last = get_user_meta($user_id, 'last_name', true);
                $full = trim($first . ' ' . $last);
                return $full ? esc_html($full) : '&mdash;';

            case ':first_name':
                $first = get_user_meta($user_id, 'first_name', true);
                return $first ? esc_html($first) : '&mdash;';

            case ':last_name':
                $last = get_user_meta($user_id, 'last_name', true);
                return $last ? esc_html($last) : '&mdash;';

            case ':nickname':
                $nickname = get_user_meta($user_id, 'nickname', true);
                return $nickname ? esc_html($nickname) : '&mdash;';

            case ':email':
                return '<a href="mailto:' . esc_attr($user->user_email) . '">' . esc_html($user->user_email) . '</a>';

            case ':role':
                $roles = $user->roles;
                if (empty($roles)) {
                    return '&mdash;';
                }
                global $wp_roles;
                $role_names = array();
                foreach ($roles as $role) {
                    if (isset($wp_roles->role_names[$role])) {
                        $role_names[] = translate_user_role($wp_roles->role_names[$role]);
                    } else {
                        $role_names[] = ucfirst($role);
                    }
                }
                return '<span class="vt-ac-badge vt-ac-status-role">' . esc_html(implode(', ', $role_names)) . '</span>';

            case ':registered_date':
                return $this->render_user_date($user->user_registered, $column_config);

            case ':website':
                $url = $user->user_url;
                if (empty($url)) {
                    return '&mdash;';
                }
                return '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a>';

            case ':profile_picture':
                return $this->render_user_avatar($user_id, $column_config);

            case ':language':
                $locale = get_user_meta($user_id, 'locale', true);
                if (empty($locale)) {
                    return __('Site Default', 'voxel-toolkit');
                }
                require_once ABSPATH . 'wp-admin/includes/translation-install.php';
                $translations = wp_get_available_translations();
                if (isset($translations[$locale])) {
                    return esc_html($translations[$locale]['native_name']);
                }
                return esc_html($locale);

            case ':post_count':
                return $this->render_user_post_count($user_id, $column_config);

            case ':membership_plan':
                return $this->render_user_membership_plan($user_id, $column_config);

            case ':online_status':
                return $this->render_user_online_status($user_id);

            case ':last_seen':
                return $this->render_user_last_seen($user_id);

            default:
                return '&mdash;';
        }
    }

    /**
     * Render user online status badge
     */
    private function render_user_online_status($user_id) {
        if (!class_exists('Voxel_Toolkit_Online_Status')) {
            return '&mdash;';
        }

        $online_status = Voxel_Toolkit_Online_Status::instance();
        $is_online = $online_status->is_user_online($user_id);

        return sprintf(
            '<span class="vt-online-status-badge %s">%s</span>',
            $is_online ? 'vt-status-online' : 'vt-status-offline',
            $is_online ? __('Online', 'voxel-toolkit') : __('Offline', 'voxel-toolkit')
        );
    }

    /**
     * Render user last seen
     */
    private function render_user_last_seen($user_id) {
        if (!class_exists('Voxel_Toolkit_Online_Status')) {
            return '&mdash;';
        }

        $online_status = Voxel_Toolkit_Online_Status::instance();
        $last_seen = $online_status->get_last_seen($user_id);

        if (empty($last_seen)) {
            return '<span class="vt-last-seen">' . __('Never', 'voxel-toolkit') . '</span>';
        }

        $time_diff = time() - $last_seen;
        $formatted = human_time_diff($last_seen, time()) . ' ' . __('ago', 'voxel-toolkit');

        // Add "recent" class if within last 5 minutes
        $class = $time_diff < 300 ? 'vt-last-seen vt-last-seen--recent' : 'vt-last-seen';

        return '<span class="' . esc_attr($class) . '">' . esc_html($formatted) . '</span>';
    }

    /**
     * Render user date
     */
    private function render_user_date($date_string, $column_config = null) {
        $timestamp = strtotime($date_string);
        if ($timestamp === false) {
            return esc_html($date_string);
        }

        // Get display mode from column config
        $display_mode = 'date';
        if (isset($column_config['date_settings']['display'])) {
            $display_mode = $column_config['date_settings']['display'];
        }

        // Get date format
        $date_format = get_option('date_format');
        if (isset($column_config['date_settings']['date_format'])) {
            $format_setting = $column_config['date_settings']['date_format'];
            if ($format_setting === 'wordpress') {
                $date_format = get_option('date_format');
            } elseif ($format_setting === 'custom' && !empty($column_config['date_settings']['custom_date_format'])) {
                $date_format = $column_config['date_settings']['custom_date_format'];
            } else {
                $date_format = $format_setting;
            }
        }

        // Get time format
        $time_format = get_option('time_format');
        if (isset($column_config['date_settings']['time_format'])) {
            $format_setting = $column_config['date_settings']['time_format'];
            if ($format_setting === 'wordpress') {
                $time_format = get_option('time_format');
            } elseif ($format_setting === 'custom' && !empty($column_config['date_settings']['custom_time_format'])) {
                $time_format = $column_config['date_settings']['custom_time_format'];
            } else {
                $time_format = $format_setting;
            }
        }

        switch ($display_mode) {
            case 'date':
                $formatted = date_i18n($date_format, $timestamp);
                break;
            case 'datetime':
                $formatted = date_i18n($date_format . ' ' . $time_format, $timestamp);
                break;
            case 'relative':
                $formatted = human_time_diff($timestamp, current_time('timestamp')) . ' ' . __('ago', 'voxel-toolkit');
                break;
            default:
                $formatted = date_i18n($date_format, $timestamp);
        }

        return '<span class="vt-ac-date" title="' . esc_attr($date_string) . '">' . esc_html($formatted) . '</span>';
    }

    /**
     * Render user avatar
     */
    private function render_user_avatar($user_id, $column_config = null) {
        $size = 40;
        if (isset($column_config['image_settings']['display_width'])) {
            $size = intval($column_config['image_settings']['display_width']);
        }

        $avatar = get_avatar($user_id, $size);
        if (!$avatar) {
            return '&mdash;';
        }

        // Add custom styling
        $height = isset($column_config['image_settings']['display_height']) ? intval($column_config['image_settings']['display_height']) : $size;
        $avatar = preg_replace(
            '/class="([^"]*)"/',
            'class="$1 vt-ac-avatar" style="width:' . $size . 'px;height:' . $height . 'px;border-radius:50%;object-fit:cover;"',
            $avatar
        );

        return $avatar;
    }

    /**
     * Render user post count
     */
    private function render_user_post_count($user_id, $column_config = null) {
        // Get post type and statuses from settings
        $post_type = 'post';
        $post_statuses = array('publish');

        if (isset($column_config['post_count_settings'])) {
            if (!empty($column_config['post_count_settings']['post_type'])) {
                $post_type = $column_config['post_count_settings']['post_type'];
            }
            if (!empty($column_config['post_count_settings']['post_statuses'])) {
                $post_statuses = $column_config['post_count_settings']['post_statuses'];
            }
        }

        // Count posts
        global $wpdb;
        $statuses_placeholder = implode(',', array_fill(0, count($post_statuses), '%s'));
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = %s AND post_status IN ({$statuses_placeholder})",
            array_merge(array($user_id, $post_type), $post_statuses)
        );
        $count = $wpdb->get_var($query);

        if ($count == 0) {
            return '<span class="vt-ac-number vt-ac-muted">0</span>';
        }

        // Link to posts list filtered by author
        $posts_url = add_query_arg(array(
            'post_type' => $post_type,
            'author' => $user_id,
        ), admin_url('edit.php'));

        return '<a href="' . esc_url($posts_url) . '" class="vt-ac-number">' . number_format_i18n($count) . '</a>';
    }

    /**
     * Render user membership plan
     */
    private function render_user_membership_plan($user_id, $column_config = null) {
        // Get plan data from user meta (handles test mode)
        $plan_data = $this->get_user_membership_data($user_id);

        if (empty($plan_data)) {
            // Default/Guest plan
            return '<span class="vt-ac-badge vt-ac-membership-plan vt-ac-guest">' . esc_html__('Guest', 'voxel-toolkit') . '</span>';
        }

        // Get display mode from column config
        $display_mode = 'plan_name';
        if (isset($column_config['membership_plan_settings']['display'])) {
            $display_mode = $column_config['membership_plan_settings']['display'];
        }

        $plan_key = isset($plan_data['plan']) ? $plan_data['plan'] : '';

        // Handle default/guest plan explicitly
        if (empty($plan_key) || $plan_key === 'default') {
            return '<span class="vt-ac-badge vt-ac-membership-plan vt-ac-guest">' . esc_html__('Guest', 'voxel-toolkit') . '</span>';
        }

        switch ($display_mode) {
            case 'plan_name':
                return $this->render_membership_plan_name($plan_key);

            case 'status':
                return $this->render_membership_plan_status($plan_data);

            case 'expiration':
                return $this->render_membership_plan_expiration($plan_data);

            case 'summary':
                return $this->render_membership_plan_summary($plan_key, $plan_data);

            default:
                return $this->render_membership_plan_name($plan_key);
        }
    }

    /**
     * Get user membership data (handles both test mode and regular mode)
     */
    private function get_user_membership_data($user_id) {
        // Determine which meta key to use based on test mode
        $meta_key = 'voxel:plan';
        if (function_exists('\Voxel\is_test_mode') && \Voxel\is_test_mode()) {
            $meta_key = 'voxel:test_plan';
        }

        // Handle multisite if needed
        if (function_exists('\Voxel\get_site_specific_user_meta_key')) {
            $meta_key = \Voxel\get_site_specific_user_meta_key($meta_key);
        }

        $plan_data = get_user_meta($user_id, $meta_key, true);

        if (empty($plan_data)) {
            return null;
        }

        // Decode if JSON string
        if (is_string($plan_data)) {
            $plan_data = json_decode($plan_data, true);
        }

        if (!is_array($plan_data)) {
            return null;
        }

        return $plan_data;
    }

    /**
     * Get membership plan label from plan key
     */
    private function get_membership_plan_label($plan_key) {
        if (empty($plan_key) || $plan_key === 'default') {
            return __('Guest', 'voxel-toolkit');
        }

        // Try to get label from Voxel
        if (class_exists('\Voxel\Modules\Paid_Memberships\Plan')) {
            $plans = \Voxel\Modules\Paid_Memberships\Plan::active();
            foreach ($plans as $plan) {
                if ($plan->get_key() === $plan_key) {
                    return $plan->get_label();
                }
            }
        }

        // Fallback to formatted key
        return ucwords(str_replace(array('-', '_'), ' ', $plan_key));
    }

    /**
     * Render membership plan name
     */
    private function render_membership_plan_name($plan_key) {
        $label = $this->get_membership_plan_label($plan_key);
        return '<span class="vt-ac-badge vt-ac-membership-plan"><span class="dashicons dashicons-groups"></span> ' . esc_html($label) . '</span>';
    }

    /**
     * Render membership plan status
     */
    private function render_membership_plan_status($plan_data) {
        $is_active = false;
        $status_label = __('Unknown', 'voxel-toolkit');

        // New format (order-based)
        if (isset($plan_data['type']) && $plan_data['type'] === 'order') {
            if (isset($plan_data['billing']['is_active'])) {
                $is_active = $plan_data['billing']['is_active'];
            }
            if ($is_active) {
                $status_label = isset($plan_data['billing']['is_canceled']) && $plan_data['billing']['is_canceled']
                    ? __('Canceled', 'voxel-toolkit')
                    : __('Active', 'voxel-toolkit');
            } else {
                $status_label = __('Expired', 'voxel-toolkit');
            }
        }
        // Old format (Stripe subscription-based)
        elseif (isset($plan_data['type']) && $plan_data['type'] === 'subscription') {
            $status = isset($plan_data['status']) ? $plan_data['status'] : '';
            switch ($status) {
                case 'active':
                    $is_active = true;
                    $status_label = isset($plan_data['cancel_at_period_end']) && $plan_data['cancel_at_period_end']
                        ? __('Canceling', 'voxel-toolkit')
                        : __('Active', 'voxel-toolkit');
                    break;
                case 'trialing':
                    $is_active = true;
                    $status_label = __('Trial', 'voxel-toolkit');
                    break;
                case 'past_due':
                    $status_label = __('Past Due', 'voxel-toolkit');
                    break;
                case 'canceled':
                    $status_label = __('Canceled', 'voxel-toolkit');
                    break;
                default:
                    $status_label = ucfirst($status);
            }
        }

        $class = $is_active ? 'vt-ac-status-publish' : 'vt-ac-status-draft';
        return '<span class="vt-ac-badge ' . esc_attr($class) . '">' . esc_html($status_label) . '</span>';
    }

    /**
     * Render membership plan expiration
     */
    private function render_membership_plan_expiration($plan_data) {
        $expiration = null;

        // New format (order-based)
        if (isset($plan_data['type']) && $plan_data['type'] === 'order') {
            if (isset($plan_data['billing']['current_period']['end'])) {
                $expiration = strtotime($plan_data['billing']['current_period']['end']);
            }
        }
        // Old format (Stripe subscription-based)
        elseif (isset($plan_data['type']) && $plan_data['type'] === 'subscription') {
            if (isset($plan_data['current_period_end'])) {
                $expiration = intval($plan_data['current_period_end']);
            }
        }

        if (!$expiration) {
            return '&mdash;';
        }

        $now = time();
        $formatted = date_i18n(get_option('date_format'), $expiration);

        if ($expiration < $now) {
            return '<span class="vt-ac-date vt-ac-muted" title="' . esc_attr__('Expired', 'voxel-toolkit') . '">' . esc_html($formatted) . '</span>';
        }

        $days_left = ceil(($expiration - $now) / DAY_IN_SECONDS);
        $title = sprintf(_n('%d day left', '%d days left', $days_left, 'voxel-toolkit'), $days_left);

        return '<span class="vt-ac-date" title="' . esc_attr($title) . '">' . esc_html($formatted) . '</span>';
    }

    /**
     * Render membership plan summary
     */
    private function render_membership_plan_summary($plan_key, $plan_data) {
        $parts = array();

        // Plan name
        $label = $this->get_membership_plan_label($plan_key);
        $parts[] = '<span class="vt-ac-badge vt-ac-membership-plan">' . esc_html($label) . '</span>';

        // Status
        $parts[] = $this->render_membership_plan_status($plan_data);

        // Expiration
        $expiration = $this->render_membership_plan_expiration($plan_data);
        if ($expiration !== '&mdash;') {
            $parts[] = $expiration;
        }

        return '<span class="vt-ac-summary">' . implode(' ', $parts) . '</span>';
    }

    /**
     * Register sortable columns
     */
    public function register_sortable_columns($sortable) {
        $config = get_option('voxel_toolkit_user_columns', array());

        if (empty($config) || empty($config['columns'])) {
            return $sortable;
        }

        foreach ($config['columns'] as $col) {
            if (!empty($col['sortable'])) {
                $column_key = 'vt_' . $col['id'];
                $sortable[$column_key] = $col['field_key'];
            }
        }

        return $sortable;
    }

    /**
     * Handle sort query
     */
    public function handle_sort_query($query) {
        // Only run in admin on users.php
        if (!is_admin()) {
            return;
        }

        global $pagenow;
        if ($pagenow !== 'users.php') {
            return;
        }

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : '';
        if (empty($orderby)) {
            return;
        }

        $config = get_option('voxel_toolkit_user_columns', array());
        if (empty($config) || empty($config['columns'])) {
            return;
        }

        foreach ($config['columns'] as $col) {
            if ($col['field_key'] === $orderby && !empty($col['sortable'])) {
                switch ($col['field_key']) {
                    case ':user_id':
                        $query->query_vars['orderby'] = 'ID';
                        break;
                    case ':username':
                        $query->query_vars['orderby'] = 'user_login';
                        break;
                    case ':display_name':
                        $query->query_vars['orderby'] = 'display_name';
                        break;
                    case ':email':
                        $query->query_vars['orderby'] = 'user_email';
                        break;
                    case ':registered_date':
                        $query->query_vars['orderby'] = 'registered';
                        break;
                    case ':first_name':
                        $query->query_vars['meta_key'] = 'first_name';
                        $query->query_vars['orderby'] = 'meta_value';
                        break;
                    case ':last_name':
                        $query->query_vars['meta_key'] = 'last_name';
                        $query->query_vars['orderby'] = 'meta_value';
                        break;
                    case ':nickname':
                        $query->query_vars['meta_key'] = 'nickname';
                        $query->query_vars['orderby'] = 'meta_value';
                        break;
                    case ':post_count':
                        // Get the specific post type and statuses from the column config
                        $post_type = 'post';
                        $post_statuses = array('publish');
                        if (isset($col['post_count_settings'])) {
                            if (!empty($col['post_count_settings']['post_type'])) {
                                $post_type = $col['post_count_settings']['post_type'];
                            }
                            if (!empty($col['post_count_settings']['post_statuses'])) {
                                $post_statuses = $col['post_count_settings']['post_statuses'];
                            }
                        }

                        // Build the subquery for custom post type counting
                        global $wpdb;
                        $statuses_placeholder = implode(',', array_fill(0, count($post_statuses), '%s'));
                        $subquery = $wpdb->prepare(
                            "(SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = {$wpdb->users}.ID AND post_type = %s AND post_status IN ({$statuses_placeholder}))",
                            array_merge(array($post_type), $post_statuses)
                        );

                        // Store for later use in the query filter
                        $this->post_count_subquery = $subquery;
                        $this->post_count_order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'DESC';

                        // Use pre_user_query to modify the query
                        add_filter('pre_user_query', array($this, 'modify_post_count_query'));
                        break;
                    case ':language':
                        $query->query_vars['meta_key'] = 'locale';
                        $query->query_vars['orderby'] = 'meta_value';
                        break;
                    case ':membership_plan':
                        // Sort by expiration date
                        $this->membership_expiration_sort_order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
                        add_filter('pre_user_query', array($this, 'sort_by_membership_expiration'));
                        break;
                }
                break;
            }
        }
    }

    /**
     * Sort users by membership expiration date
     */
    public function sort_by_membership_expiration($user_query) {
        remove_filter('pre_user_query', array($this, 'sort_by_membership_expiration'));

        global $wpdb;
        $meta_key = $this->get_membership_meta_key();
        $order = $this->membership_expiration_sort_order === 'DESC' ? 'DESC' : 'ASC';

        // Join with usermeta to get the plan data and extract expiration
        // Handle both order format (billing.current_period.end as datetime) and subscription format (current_period_end as timestamp)
        $user_query->query_from .= $wpdb->prepare(
            " LEFT JOIN {$wpdb->usermeta} AS vt_plan_meta ON ({$wpdb->users}.ID = vt_plan_meta.user_id AND vt_plan_meta.meta_key = %s)",
            $meta_key
        );

        // Order by expiration date - coalesce both formats into a comparable value
        // For order type: extract datetime string and convert
        // For subscription type: extract unix timestamp and convert
        $user_query->query_orderby = "ORDER BY
            COALESCE(
                CASE
                    WHEN vt_plan_meta.meta_value LIKE '%\"type\":\"order\"%'
                    THEN STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(vt_plan_meta.meta_value, '$.billing.current_period.end')), '%Y-%m-%d %H:%i:%s')
                    WHEN vt_plan_meta.meta_value LIKE '%\"type\":\"subscription\"%'
                    THEN FROM_UNIXTIME(CAST(JSON_UNQUOTE(JSON_EXTRACT(vt_plan_meta.meta_value, '$.current_period_end')) AS UNSIGNED))
                    ELSE NULL
                END,
                '9999-12-31'
            ) {$order}";

        return $user_query;
    }

    /**
     * Modify user query for custom post count sorting
     */
    public function modify_post_count_query($user_query) {
        if (empty($this->post_count_subquery)) {
            return $user_query;
        }

        // Remove this filter to prevent running multiple times
        remove_filter('pre_user_query', array($this, 'modify_post_count_query'));

        // Add the orderby clause using the subquery
        $order = $this->post_count_order === 'ASC' ? 'ASC' : 'DESC';
        $user_query->query_orderby = "ORDER BY {$this->post_count_subquery} {$order}";

        // Clear the stored subquery
        $this->post_count_subquery = null;

        return $user_query;
    }

    /**
     * Enqueue filter bar assets
     */
    public function enqueue_filter_assets($hook) {
        if ($hook !== 'users.php') {
            return;
        }

        // Include the filter bar class
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/admin-columns/class-filter-bar.php';
        Voxel_Toolkit_Filter_Bar::enqueue_assets();
    }

    /**
     * Render filter bar
     */
    public function render_filter_bar($which) {
        if ($which !== 'top') {
            return;
        }

        $config = get_option('voxel_toolkit_user_columns', array());
        if (empty($config) || empty($config['columns'])) {
            return;
        }

        // Build filterable fields list
        $filterable_fields = $this->get_filterable_fields($config);

        if (empty($filterable_fields)) {
            return;
        }

        // Include the filter bar class
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/admin-columns/class-filter-bar.php';

        $filter_bar = new Voxel_Toolkit_Filter_Bar('users');
        $filter_bar->set_fields($filterable_fields);
        $filter_bar->render();
    }

    /**
     * Get filterable fields from config
     */
    private function get_filterable_fields($config) {
        $fields = array();

        foreach ($config['columns'] as $col) {
            if (empty($col['filterable'])) {
                continue;
            }

            $field = array(
                'key' => $col['field_key'],
                'label' => !empty($col['label']) ? $col['label'] : $this->get_field_label($col['field_key']),
                'filter_type' => $this->get_field_filter_type($col['field_key']),
            );

            // Add options for select-type fields
            $options = $this->get_field_filter_options($col);
            if (!empty($options)) {
                $field['options'] = $options;
            }

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Get field label
     */
    private function get_field_label($field_key) {
        $labels = array(
            ':user_id' => __('User ID', 'voxel-toolkit'),
            ':username' => __('Username', 'voxel-toolkit'),
            ':email' => __('Email', 'voxel-toolkit'),
            ':display_name' => __('Display Name', 'voxel-toolkit'),
            ':first_name' => __('First Name', 'voxel-toolkit'),
            ':last_name' => __('Last Name', 'voxel-toolkit'),
            ':nickname' => __('Nickname', 'voxel-toolkit'),
            ':full_name' => __('Full Name', 'voxel-toolkit'),
            ':role' => __('Role', 'voxel-toolkit'),
            ':registered_date' => __('Registered Date', 'voxel-toolkit'),
            ':language' => __('Language', 'voxel-toolkit'),
            ':post_count' => __('Post Count', 'voxel-toolkit'),
            ':membership_plan' => __('Membership Plan', 'voxel-toolkit'),
        );

        return isset($labels[$field_key]) ? $labels[$field_key] : $field_key;
    }

    /**
     * Get field filter type
     */
    private function get_field_filter_type($field_key) {
        $types = array(
            ':user_id' => 'number',
            ':username' => 'text',
            ':email' => 'text',
            ':display_name' => 'text',
            ':first_name' => 'text',
            ':last_name' => 'text',
            ':nickname' => 'text',
            ':full_name' => 'text',
            ':role' => 'select',
            ':registered_date' => 'date',
            ':language' => 'select',
            ':post_count' => 'number',
            ':membership_plan' => 'select',
        );

        return isset($types[$field_key]) ? $types[$field_key] : 'text';
    }

    /**
     * Get field filter options
     */
    private function get_field_filter_options($col) {
        $field_key = $col['field_key'];
        $options = array();

        switch ($field_key) {
            case ':role':
                global $wp_roles;
                foreach ($wp_roles->roles as $role_key => $role_data) {
                    $options[] = array('value' => $role_key, 'label' => $role_data['name']);
                }
                break;

            case ':language':
                require_once ABSPATH . 'wp-admin/includes/translation-install.php';
                $translations = wp_get_available_translations();

                $options[] = array('value' => '_site_default', 'label' => __('Site Default', 'voxel-toolkit'));
                foreach ($translations as $locale => $translation) {
                    $options[] = array('value' => $locale, 'label' => $translation['native_name']);
                }
                break;

            case ':membership_plan':
                // Check display mode first
                $display_mode = isset($col['membership_plan_settings']['display']) ? $col['membership_plan_settings']['display'] : 'plan_name';

                if ($display_mode === 'status') {
                    $options = array(
                        array('value' => 'active', 'label' => __('Active', 'voxel-toolkit')),
                        array('value' => 'canceled', 'label' => __('Canceled', 'voxel-toolkit')),
                        array('value' => 'expired', 'label' => __('Expired', 'voxel-toolkit')),
                        array('value' => 'trial', 'label' => __('Trial', 'voxel-toolkit')),
                        array('value' => 'guest', 'label' => __('Guest (No Plan)', 'voxel-toolkit')),
                    );
                } elseif ($display_mode === 'expiration') {
                    $options = array(
                        array('value' => 'expired', 'label' => __('Already Expired', 'voxel-toolkit')),
                        array('value' => '7days', 'label' => __('Expires within 7 days', 'voxel-toolkit')),
                        array('value' => '30days', 'label' => __('Expires within 30 days', 'voxel-toolkit')),
                        array('value' => '90days', 'label' => __('Expires within 90 days', 'voxel-toolkit')),
                        array('value' => 'active', 'label' => __('Not Expired', 'voxel-toolkit')),
                    );
                } else {
                    // Default: filter by plan name
                    // Add Guest option first
                    $options[] = array('value' => 'default', 'label' => __('Guest', 'voxel-toolkit'));

                    // Get membership plans (skip if key is 'default' to avoid duplicate)
                    if (class_exists('\Voxel\Modules\Paid_Memberships\Plan')) {
                        $voxel_plans = \Voxel\Modules\Paid_Memberships\Plan::active();
                        foreach ($voxel_plans as $plan) {
                            $plan_key = $plan->get_key();
                            if ($plan_key !== 'default') {
                                $options[] = array('value' => $plan_key, 'label' => $plan->get_label());
                            }
                        }
                    }
                }
                break;
        }

        return $options;
    }

    /**
     * Get available membership plans for filter
     */
    private function get_available_membership_plans() {
        $plans = array();

        // Add Guest/Default option first
        $plans['default'] = __('Guest', 'voxel-toolkit');

        // Get all active membership plans from Voxel
        if (class_exists('\Voxel\Modules\Paid_Memberships\Plan')) {
            $voxel_plans = \Voxel\Modules\Paid_Memberships\Plan::active();
            foreach ($voxel_plans as $plan) {
                $plans[$plan->get_key()] = $plan->get_label();
            }
        }

        return $plans;
    }

    /**
     * Handle filter query
     */
    public function handle_filter_query($query) {
        // Only run in admin on users.php
        if (!is_admin()) {
            return;
        }

        global $pagenow;
        if ($pagenow !== 'users.php') {
            return;
        }

        // Include the filter bar class
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/admin-columns/class-filter-bar.php';

        // Parse filters from the new system
        $filters = Voxel_Toolkit_Filter_Bar::parse_filters();

        if (empty($filters)) {
            return;
        }

        // Get filter logic FIRST before processing any filters
        $this->filter_logic = Voxel_Toolkit_Filter_Bar::get_filter_logic();

        // Get column config for looking up field settings
        $config = get_option('voxel_toolkit_user_columns', array());
        $columns_by_key = array();
        if (!empty($config['columns'])) {
            foreach ($config['columns'] as $col) {
                $columns_by_key[$col['field_key']] = $col;
            }
        }

        $membership_meta_key = $this->get_membership_meta_key();

        // Store all filter data for processing in pre_user_query
        $this->all_filters = array();

        foreach ($filters as $filter) {
            $field_key = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'];
            $column_config = isset($columns_by_key[$field_key]) ? $columns_by_key[$field_key] : array();

            $filter_data = array(
                'field_key' => $field_key,
                'operator' => $operator,
                'value' => $value,
                'column_config' => $column_config,
                'membership_meta_key' => $membership_meta_key,
            );

            $this->all_filters[] = $filter_data;
        }

        // Add single hook to handle all filters with proper AND/OR logic
        if (!empty($this->all_filters)) {
            add_filter('pre_user_query', array($this, 'apply_all_filters_to_query'));
        }
    }

    /**
     * Apply all filters to query with proper AND/OR logic
     */
    public function apply_all_filters_to_query($user_query) {
        if (empty($this->all_filters)) {
            return $user_query;
        }

        global $wpdb;

        $conditions = array();

        foreach ($this->all_filters as $filter) {
            $field_key = $filter['field_key'];
            $operator = $filter['operator'];
            $value = $filter['value'];
            $column_config = $filter['column_config'];
            $membership_meta_key = $filter['membership_meta_key'];

            $condition = $this->build_sql_condition($field_key, $operator, $value, $column_config, $membership_meta_key);
            if ($condition) {
                $conditions[] = $condition;
            }
        }

        // Apply conditions with AND or OR logic
        if (!empty($conditions)) {
            $logic = $this->filter_logic === 'OR' ? ' OR ' : ' AND ';
            $user_query->query_where .= ' AND (' . implode($logic, $conditions) . ')';
        }

        // Clear filters after applying
        $this->all_filters = array();

        return $user_query;
    }

    /**
     * Build SQL condition for a single filter
     */
    private function build_sql_condition($field_key, $operator, $value, $column_config, $membership_meta_key) {
        global $wpdb;

        switch ($field_key) {
            case ':user_id':
                return $this->build_user_field_sql('ID', $operator, $value);

            case ':username':
                return $this->build_user_field_sql('user_login', $operator, $value);

            case ':email':
                return $this->build_user_field_sql('user_email', $operator, $value);

            case ':display_name':
                return $this->build_user_field_sql('display_name', $operator, $value);

            case ':registered_date':
                return $this->build_user_field_sql('user_registered', $operator, $value);

            case ':website':
                return $this->build_user_field_sql('user_url', $operator, $value);

            case ':role':
                if ($operator === 'equals' && !empty($value)) {
                    // Role is stored in usermeta as wp_capabilities
                    $cap_key = $wpdb->prefix . 'capabilities';
                    return $wpdb->prepare(
                        "({$wpdb->users}.ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s))",
                        $cap_key,
                        '%"' . $wpdb->esc_like($value) . '"%'
                    );
                } elseif ($operator === 'not_equals' && !empty($value)) {
                    $cap_key = $wpdb->prefix . 'capabilities';
                    return $wpdb->prepare(
                        "({$wpdb->users}.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s))",
                        $cap_key,
                        '%"' . $wpdb->esc_like($value) . '"%'
                    );
                }
                return '';

            case ':first_name':
            case ':last_name':
            case ':nickname':
                $meta_key = ltrim($field_key, ':');
                return $this->build_meta_sql($meta_key, $operator, $value);

            case ':full_name':
                if ($operator === 'contains' && !empty($value)) {
                    $first = $this->build_meta_sql('first_name', 'contains', $value);
                    $last = $this->build_meta_sql('last_name', 'contains', $value);
                    return "({$first} OR {$last})";
                }
                return '';

            case ':post_count':
                $post_type = 'post';
                $post_statuses = array('publish');
                if (!empty($column_config['post_count_settings']['post_type'])) {
                    $post_type = $column_config['post_count_settings']['post_type'];
                }
                if (!empty($column_config['post_count_settings']['post_statuses'])) {
                    $post_statuses = $column_config['post_count_settings']['post_statuses'];
                }
                return $this->build_post_count_sql($operator, intval($value), $post_type, $post_statuses);

            case ':membership_plan':
                return $this->build_membership_sql($membership_meta_key, $operator, $value);

            case ':language':
                if ($value === '_site_default') {
                    return "({$wpdb->users}.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'locale' AND meta_value != ''))";
                }
                return $this->build_meta_sql('locale', $operator, $value);

            default:
                // Generic meta field
                $meta_key = ltrim($field_key, ':');
                return $this->build_meta_sql($meta_key, $operator, $value);
        }
    }

    /**
     * Build SQL for user table field
     */
    private function build_user_field_sql($field, $operator, $value) {
        global $wpdb;
        $column = "{$wpdb->users}.{$field}";

        switch ($operator) {
            case 'equals':
                return $wpdb->prepare("{$column} = %s", $value);
            case 'not_equals':
                return $wpdb->prepare("{$column} != %s", $value);
            case 'contains':
                return $wpdb->prepare("{$column} LIKE %s", '%' . $wpdb->esc_like($value) . '%');
            case 'not_contains':
                return $wpdb->prepare("{$column} NOT LIKE %s", '%' . $wpdb->esc_like($value) . '%');
            case 'starts_with':
                return $wpdb->prepare("{$column} LIKE %s", $wpdb->esc_like($value) . '%');
            case 'ends_with':
                return $wpdb->prepare("{$column} LIKE %s", '%' . $wpdb->esc_like($value));
            case 'greater_than':
                return $wpdb->prepare("{$column} > %s", $value);
            case 'less_than':
                return $wpdb->prepare("{$column} < %s", $value);
            case 'greater_equal':
                return $wpdb->prepare("{$column} >= %s", $value);
            case 'less_equal':
                return $wpdb->prepare("{$column} <= %s", $value);
            case 'is_empty':
                return "({$column} IS NULL OR {$column} = '')";
            case 'is_not_empty':
                return "({$column} IS NOT NULL AND {$column} != '')";
        }
        return '';
    }

    /**
     * Build SQL for meta field
     */
    private function build_meta_sql($meta_key, $operator, $value) {
        global $wpdb;

        switch ($operator) {
            case 'equals':
                return $wpdb->prepare(
                    "({$wpdb->users}.ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s))",
                    $meta_key, $value
                );
            case 'not_equals':
                return $wpdb->prepare(
                    "({$wpdb->users}.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s))",
                    $meta_key, $value
                );
            case 'contains':
                return $wpdb->prepare(
                    "({$wpdb->users}.ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s))",
                    $meta_key, '%' . $wpdb->esc_like($value) . '%'
                );
            case 'not_contains':
                return $wpdb->prepare(
                    "({$wpdb->users}.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s))",
                    $meta_key, '%' . $wpdb->esc_like($value) . '%'
                );
            case 'is_empty':
                return $wpdb->prepare(
                    "({$wpdb->users}.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''))",
                    $meta_key
                );
            case 'is_not_empty':
                return $wpdb->prepare(
                    "({$wpdb->users}.ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''))",
                    $meta_key
                );
        }
        return '';
    }

    /**
     * Build SQL for post count
     */
    private function build_post_count_sql($operator, $value, $post_type, $post_statuses) {
        global $wpdb;

        $status_placeholders = implode(',', array_fill(0, count($post_statuses), '%s'));
        $prepare_args = array_merge(array($post_type), $post_statuses);

        $subquery = $wpdb->prepare(
            "(SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$wpdb->posts}.post_author = {$wpdb->users}.ID AND {$wpdb->posts}.post_type = %s AND {$wpdb->posts}.post_status IN ({$status_placeholders}))",
            $prepare_args
        );

        switch ($operator) {
            case 'equals':
                return "{$subquery} = {$value}";
            case 'not_equals':
                return "{$subquery} != {$value}";
            case 'greater_than':
                return "{$subquery} > {$value}";
            case 'less_than':
                return "{$subquery} < {$value}";
            case 'greater_equal':
                return "{$subquery} >= {$value}";
            case 'less_equal':
                return "{$subquery} <= {$value}";
        }
        return '';
    }

    /**
     * Build SQL for membership plan
     */
    private function build_membership_sql($meta_key, $operator, $value) {
        global $wpdb;

        switch ($operator) {
            case 'equals':
                if ($value === 'default') {
                    // Guest users have no membership meta or it's empty
                    return $wpdb->prepare(
                        "({$wpdb->users}.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != '' AND meta_value IS NOT NULL))",
                        $meta_key
                    );
                }
                return $wpdb->prepare(
                    "({$wpdb->users}.ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s))",
                    $meta_key, '%"plan":"' . $wpdb->esc_like($value) . '"%'
                );
            case 'not_equals':
                if ($value === 'default') {
                    return $wpdb->prepare(
                        "({$wpdb->users}.ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != '' AND meta_value IS NOT NULL))",
                        $meta_key
                    );
                }
                return $wpdb->prepare(
                    "({$wpdb->users}.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s))",
                    $meta_key, '%"plan":"' . $wpdb->esc_like($value) . '"%'
                );
        }
        return '';
    }

    /**
     * Apply filter to user table field (non-meta) - DEPRECATED, kept for compatibility
     */
    private function apply_user_field_filter($query, $field, $operator, $value) {
        $this->user_field_filters[] = array(
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
        );

        // Add filter hook if not already added
        if (count($this->user_field_filters) === 1) {
            add_filter('pre_user_query', array($this, 'apply_user_field_filters_to_query'));
        }
    }

    /**
     * Apply user field filters to query
     */
    public function apply_user_field_filters_to_query($user_query) {
        if (empty($this->user_field_filters)) {
            return $user_query;
        }

        global $wpdb;

        $conditions = array();

        foreach ($this->user_field_filters as $filter) {
            $field = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'];

            $column = "{$wpdb->users}.{$field}";
            $condition = '';

            switch ($operator) {
                case 'equals':
                    $condition = $wpdb->prepare("{$column} = %s", $value);
                    break;
                case 'not_equals':
                    $condition = $wpdb->prepare("{$column} != %s", $value);
                    break;
                case 'contains':
                    $condition = $wpdb->prepare("{$column} LIKE %s", '%' . $wpdb->esc_like($value) . '%');
                    break;
                case 'not_contains':
                    $condition = $wpdb->prepare("{$column} NOT LIKE %s", '%' . $wpdb->esc_like($value) . '%');
                    break;
                case 'starts_with':
                    $condition = $wpdb->prepare("{$column} LIKE %s", $wpdb->esc_like($value) . '%');
                    break;
                case 'ends_with':
                    $condition = $wpdb->prepare("{$column} LIKE %s", '%' . $wpdb->esc_like($value));
                    break;
                case 'greater_than':
                    $condition = $wpdb->prepare("{$column} > %s", $value);
                    break;
                case 'less_than':
                    $condition = $wpdb->prepare("{$column} < %s", $value);
                    break;
                case 'greater_equal':
                    $condition = $wpdb->prepare("{$column} >= %s", $value);
                    break;
                case 'less_equal':
                    $condition = $wpdb->prepare("{$column} <= %s", $value);
                    break;
                case 'is_empty':
                    $condition = "({$column} IS NULL OR {$column} = '')";
                    break;
                case 'is_not_empty':
                    $condition = "{$column} IS NOT NULL AND {$column} != ''";
                    break;
            }

            if ($condition) {
                $conditions[] = $condition;
            }
        }

        // Apply conditions with AND or OR logic
        if (!empty($conditions)) {
            $logic = $this->filter_logic === 'OR' ? ' OR ' : ' AND ';
            $user_query->query_where .= ' AND (' . implode($logic, $conditions) . ')';
        }

        // Clear filters after applying
        $this->user_field_filters = array();

        return $user_query;
    }

    /**
     * Apply post count filter to query
     */
    public function apply_post_count_filter_to_query($user_query) {
        if (empty($this->post_count_filter)) {
            return $user_query;
        }

        global $wpdb;

        $operator = $this->post_count_filter['operator'];
        $value = $this->post_count_filter['value'];
        $post_type = isset($this->post_count_filter['post_type']) ? $this->post_count_filter['post_type'] : 'post';
        $post_statuses = isset($this->post_count_filter['post_statuses']) ? $this->post_count_filter['post_statuses'] : array('publish');

        // Build status condition
        $status_placeholders = implode(',', array_fill(0, count($post_statuses), '%s'));
        $status_sql = $wpdb->prepare("AND {$wpdb->posts}.post_status IN ({$status_placeholders})", $post_statuses);

        // Subquery to count posts
        $post_count_sql = "(SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$wpdb->posts}.post_author = {$wpdb->users}.ID AND {$wpdb->posts}.post_type = %s {$status_sql})";
        $post_count_sql = $wpdb->prepare($post_count_sql, $post_type);

        switch ($operator) {
            case 'equals':
                $user_query->query_where .= $wpdb->prepare(" AND {$post_count_sql} = %d", $value);
                break;
            case 'not_equals':
                $user_query->query_where .= $wpdb->prepare(" AND {$post_count_sql} != %d", $value);
                break;
            case 'greater_than':
                $user_query->query_where .= $wpdb->prepare(" AND {$post_count_sql} > %d", $value);
                break;
            case 'less_than':
                $user_query->query_where .= $wpdb->prepare(" AND {$post_count_sql} < %d", $value);
                break;
            case 'greater_equal':
                $user_query->query_where .= $wpdb->prepare(" AND {$post_count_sql} >= %d", $value);
                break;
            case 'less_equal':
                $user_query->query_where .= $wpdb->prepare(" AND {$post_count_sql} <= %d", $value);
                break;
        }

        // Clear filter after applying
        $this->post_count_filter = null;

        return $user_query;
    }

    /**
     * Build meta filter array
     */
    private function build_meta_filter($meta_key, $operator, $value) {
        $meta_query = array();

        switch ($operator) {
            case 'equals':
                $meta_query[] = array(
                    'key' => $meta_key,
                    'value' => $value,
                    'compare' => '=',
                );
                break;
            case 'not_equals':
                $meta_query[] = array(
                    'key' => $meta_key,
                    'value' => $value,
                    'compare' => '!=',
                );
                break;
            case 'contains':
                $meta_query[] = array(
                    'key' => $meta_key,
                    'value' => $value,
                    'compare' => 'LIKE',
                );
                break;
            case 'not_contains':
                $meta_query[] = array(
                    'key' => $meta_key,
                    'value' => $value,
                    'compare' => 'NOT LIKE',
                );
                break;
            case 'starts_with':
                $meta_query[] = array(
                    'key' => $meta_key,
                    'value' => $value . '%',
                    'compare' => 'LIKE',
                );
                break;
            case 'ends_with':
                $meta_query[] = array(
                    'key' => $meta_key,
                    'value' => '%' . $value,
                    'compare' => 'LIKE',
                );
                break;
            case 'greater_than':
                $meta_query[] = array(
                    'key' => $meta_key,
                    'value' => $value,
                    'compare' => '>',
                    'type' => is_numeric($value) ? 'NUMERIC' : 'CHAR',
                );
                break;
            case 'less_than':
                $meta_query[] = array(
                    'key' => $meta_key,
                    'value' => $value,
                    'compare' => '<',
                    'type' => is_numeric($value) ? 'NUMERIC' : 'CHAR',
                );
                break;
            case 'greater_equal':
                $meta_query[] = array(
                    'key' => $meta_key,
                    'value' => $value,
                    'compare' => '>=',
                    'type' => is_numeric($value) ? 'NUMERIC' : 'CHAR',
                );
                break;
            case 'less_equal':
                $meta_query[] = array(
                    'key' => $meta_key,
                    'value' => $value,
                    'compare' => '<=',
                    'type' => is_numeric($value) ? 'NUMERIC' : 'CHAR',
                );
                break;
            case 'is_empty':
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => $meta_key,
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => $meta_key,
                        'value' => '',
                        'compare' => '=',
                    ),
                );
                break;
            case 'is_not_empty':
                $meta_query[] = array(
                    'key' => $meta_key,
                    'compare' => 'EXISTS',
                );
                $meta_query[] = array(
                    'key' => $meta_key,
                    'value' => '',
                    'compare' => '!=',
                );
                break;
        }

        return $meta_query;
    }

    /**
     * Build membership plan filter
     */
    private function build_membership_filter($meta_key, $operator, $value) {
        $meta_query = array();

        // Handle special membership values
        switch ($value) {
            case 'default':
            case 'guest':
                // Guest users (no plan)
                if ($operator === 'equals') {
                    $meta_query[] = array(
                        'relation' => 'OR',
                        array(
                            'key' => $meta_key,
                            'compare' => 'NOT EXISTS',
                        ),
                        array(
                            'key' => $meta_key,
                            'value' => '',
                        ),
                        array(
                            'key' => $meta_key,
                            'value' => '"plan":"default"',
                            'compare' => 'LIKE',
                        ),
                    );
                } else {
                    // not_equals guest = has a plan
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'compare' => 'EXISTS',
                    );
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => '',
                        'compare' => '!=',
                    );
                }
                break;

            case 'active':
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => $meta_key,
                        'value' => '"is_active":true',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => $meta_key,
                        'value' => '"status":"active"',
                        'compare' => 'LIKE',
                    ),
                );
                break;

            case 'canceled':
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => $meta_key,
                        'value' => '"is_canceled":true',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => $meta_key,
                        'value' => '"status":"canceled"',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => $meta_key,
                        'value' => '"cancel_at_period_end":true',
                        'compare' => 'LIKE',
                    ),
                );
                break;

            case 'trial':
                $meta_query[] = array(
                    'key' => $meta_key,
                    'value' => '"status":"trialing"',
                    'compare' => 'LIKE',
                );
                break;

            case 'expired':
            case '7days':
            case '30days':
            case '90days':
                // Handle expiration filters via custom SQL
                $this->membership_expiration_filter = $value;
                add_filter('pre_user_query', array($this, 'filter_membership_by_expiration'));
                break;

            default:
                // Regular plan key filter
                if ($operator === 'equals') {
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => '"plan":"' . $value . '"',
                        'compare' => 'LIKE',
                    );
                } else {
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => '"plan":"' . $value . '"',
                        'compare' => 'NOT LIKE',
                    );
                }
                break;
        }

        return $meta_query;
    }

    /**
     * Filter users by membership expiration date
     */
    public function filter_membership_by_expiration($user_query) {
        if (empty($this->membership_expiration_filter)) {
            return $user_query;
        }

        remove_filter('pre_user_query', array($this, 'filter_membership_by_expiration'));

        global $wpdb;
        $meta_key = $this->get_membership_meta_key();
        $filter = $this->membership_expiration_filter;
        $now = current_time('timestamp');

        // Build the WHERE clause based on filter type
        switch ($filter) {
            case 'expired':
                // Find users whose plan has expired
                $user_query->query_where .= $wpdb->prepare(
                    " AND {$wpdb->users}.ID IN (
                        SELECT user_id FROM {$wpdb->usermeta}
                        WHERE meta_key = %s
                        AND (
                            (meta_value LIKE %s AND CAST(JSON_UNQUOTE(JSON_EXTRACT(meta_value, '$.billing.current_period.end')) AS DATETIME) < NOW())
                            OR
                            (meta_value LIKE %s AND CAST(JSON_UNQUOTE(JSON_EXTRACT(meta_value, '$.current_period_end')) AS UNSIGNED) < %d)
                        )
                    )",
                    $meta_key,
                    '%"type":"order"%',
                    '%"type":"subscription"%',
                    $now
                );
                break;
            case '7days':
            case '30days':
            case '90days':
                $days = intval($filter);
                $future = $now + ($days * DAY_IN_SECONDS);
                $user_query->query_where .= $wpdb->prepare(
                    " AND {$wpdb->users}.ID IN (
                        SELECT user_id FROM {$wpdb->usermeta}
                        WHERE meta_key = %s
                        AND (
                            (meta_value LIKE %s AND CAST(JSON_UNQUOTE(JSON_EXTRACT(meta_value, '$.billing.current_period.end')) AS DATETIME) BETWEEN NOW() AND %s)
                            OR
                            (meta_value LIKE %s AND CAST(JSON_UNQUOTE(JSON_EXTRACT(meta_value, '$.current_period_end')) AS UNSIGNED) BETWEEN %d AND %d)
                        )
                    )",
                    $meta_key,
                    '%"type":"order"%',
                    date('Y-m-d H:i:s', $future),
                    '%"type":"subscription"%',
                    $now,
                    $future
                );
                break;
            case 'active':
                // Not expired
                $user_query->query_where .= $wpdb->prepare(
                    " AND {$wpdb->users}.ID IN (
                        SELECT user_id FROM {$wpdb->usermeta}
                        WHERE meta_key = %s
                        AND (
                            (meta_value LIKE %s AND CAST(JSON_UNQUOTE(JSON_EXTRACT(meta_value, '$.billing.current_period.end')) AS DATETIME) > NOW())
                            OR
                            (meta_value LIKE %s AND CAST(JSON_UNQUOTE(JSON_EXTRACT(meta_value, '$.current_period_end')) AS UNSIGNED) > %d)
                        )
                    )",
                    $meta_key,
                    '%"type":"order"%',
                    '%"type":"subscription"%',
                    $now
                );
                break;
        }

        $this->membership_expiration_filter = null;
        return $user_query;
    }

    /**
     * Get the correct meta key for membership plan (handles test mode and multisite)
     */
    private function get_membership_meta_key() {
        // Determine base meta key based on test mode
        $base_key = 'voxel:plan';
        if (function_exists('\Voxel\is_test_mode') && \Voxel\is_test_mode()) {
            $base_key = 'voxel:test_plan';
        }

        // Handle multisite - use Voxel's helper if available
        if (function_exists('\Voxel\get_site_specific_user_meta_key')) {
            return \Voxel\get_site_specific_user_meta_key($base_key);
        }

        return $base_key;
    }

    /**
     * Render Edit Columns link
     */
    public function render_edit_columns_link($which) {
        if ($which !== 'top') {
            return;
        }

        $config = get_option('voxel_toolkit_user_columns', array());
        if (empty($config) || empty($config['columns'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $edit_url = admin_url('admin.php?page=vt-admin-columns&type=users');

        ?>
        <a href="<?php echo esc_url($edit_url); ?>" class="button vt-edit-columns-btn" style="margin-left: 8px;">
            <span class="dashicons dashicons-admin-generic" style="vertical-align: middle; margin-top: -2px; font-size: 16px;"></span>
            <?php _e('Edit Columns', 'voxel-toolkit'); ?>
        </a>
        <?php
    }

    /**
     * Get available post types for post count field
     */
    public static function get_post_types_for_count() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $options = array();

        foreach ($post_types as $post_type) {
            $options[$post_type->name] = $post_type->label;
        }

        // Also add Voxel post types if available
        if (class_exists('\Voxel\Post_Type')) {
            $voxel_types = \Voxel\Post_Type::get_voxel_types();
            foreach ($voxel_types as $key => $type) {
                if (!isset($options[$key])) {
                    $options[$key] = $type->get_label();
                }
            }
        }

        return $options;
    }

    /**
     * Get available post statuses
     */
    public static function get_post_statuses() {
        return array(
            'publish' => __('Published', 'voxel-toolkit'),
            'pending' => __('Pending', 'voxel-toolkit'),
            'draft' => __('Draft', 'voxel-toolkit'),
            'private' => __('Private', 'voxel-toolkit'),
            'trash' => __('Trash', 'voxel-toolkit'),
        );
    }
}
