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
                'filterable' => false,
            ),
            array(
                'key' => ':username',
                'label' => __('Username', 'voxel-toolkit'),
                'type' => 'user-username',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
            ),
            array(
                'key' => ':display_name',
                'label' => __('Display Name', 'voxel-toolkit'),
                'type' => 'user-display-name',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
            ),
            array(
                'key' => ':full_name',
                'label' => __('Full Name', 'voxel-toolkit'),
                'type' => 'user-full-name',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => false,
            ),
            array(
                'key' => ':first_name',
                'label' => __('First Name', 'voxel-toolkit'),
                'type' => 'user-first-name',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
            ),
            array(
                'key' => ':last_name',
                'label' => __('Last Name', 'voxel-toolkit'),
                'type' => 'user-last-name',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
            ),
            array(
                'key' => ':nickname',
                'label' => __('Nickname', 'voxel-toolkit'),
                'type' => 'user-nickname',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
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
                'filterable' => false,
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
                'filterable' => false,
            ),
            array(
                'key' => ':website',
                'label' => __('Website', 'voxel-toolkit'),
                'type' => 'user-website',
                'type_label' => __('WordPress', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => false,
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
                'filterable' => false,
                'has_post_type_setting' => true,
            ),
        );

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

        // Add filter dropdowns
        add_action('restrict_manage_users', array($this, 'render_filter_dropdowns'));

        // Handle filter query
        add_action('pre_get_users', array($this, 'handle_filter_query'));

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

        return $this->render_user_field($column_config['field_key'], $user_id, $column_config);
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

            default:
                return '&mdash;';
        }
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
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');
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
                        $query->set('orderby', 'ID');
                        break;
                    case ':username':
                        $query->set('orderby', 'user_login');
                        break;
                    case ':display_name':
                        $query->set('orderby', 'display_name');
                        break;
                    case ':email':
                        $query->set('orderby', 'user_email');
                        break;
                    case ':registered_date':
                        $query->set('orderby', 'registered');
                        break;
                    case ':first_name':
                        $query->set('meta_key', 'first_name');
                        $query->set('orderby', 'meta_value');
                        break;
                    case ':last_name':
                        $query->set('meta_key', 'last_name');
                        $query->set('orderby', 'meta_value');
                        break;
                    case ':nickname':
                        $query->set('meta_key', 'nickname');
                        $query->set('orderby', 'meta_value');
                        break;
                    case ':post_count':
                        $query->set('orderby', 'post_count');
                        break;
                    case ':language':
                        $query->set('meta_key', 'locale');
                        $query->set('orderby', 'meta_value');
                        break;
                }
                break;
            }
        }
    }

    /**
     * Render filter dropdowns
     */
    public function render_filter_dropdowns($which) {
        if ($which !== 'top') {
            return;
        }

        $config = get_option('voxel_toolkit_user_columns', array());
        if (empty($config) || empty($config['columns'])) {
            return;
        }

        foreach ($config['columns'] as $col) {
            if (empty($col['filterable'])) {
                continue;
            }

            if ($col['field_key'] === ':role') {
                // Role filter is already provided by WordPress
                continue;
            }

            if ($col['field_key'] === ':language') {
                $this->render_language_filter($col);
            }
        }
    }

    /**
     * Render language filter dropdown
     */
    private function render_language_filter($col) {
        $current_value = isset($_GET['vt_filter_language']) ? sanitize_text_field($_GET['vt_filter_language']) : '';

        require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        $translations = wp_get_available_translations();

        ?>
        <select name="vt_filter_language" style="float: none; margin-left: 6px;">
            <option value=""><?php echo esc_html($col['label'] ?: __('Language', 'voxel-toolkit')); ?></option>
            <option value="_site_default" <?php selected($current_value, '_site_default'); ?>><?php _e('Site Default', 'voxel-toolkit'); ?></option>
            <?php foreach ($translations as $locale => $translation) : ?>
                <option value="<?php echo esc_attr($locale); ?>" <?php selected($current_value, $locale); ?>>
                    <?php echo esc_html($translation['native_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Handle filter query
     */
    public function handle_filter_query($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $meta_query = $query->get('meta_query') ?: array();

        // Language filter
        if (isset($_GET['vt_filter_language']) && $_GET['vt_filter_language'] !== '') {
            $language = sanitize_text_field($_GET['vt_filter_language']);

            if ($language === '_site_default') {
                // Users with no locale set (using site default)
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'locale',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => 'locale',
                        'value' => '',
                    ),
                );
            } else {
                $meta_query[] = array(
                    'key' => 'locale',
                    'value' => $language,
                );
            }
        }

        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
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
