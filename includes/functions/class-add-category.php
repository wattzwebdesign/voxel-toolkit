<?php
/**
 * Add Category - Allow users to add new taxonomy terms from the frontend
 *
 * Features:
 * - Per-field toggle to allow adding terms (overridable via visibility rules)
 * - Optional approval workflow for new terms
 * - Pending terms admin integration (badge + approve action)
 * - App events for notifications
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Add_Category {

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
    public function __construct() {
        // Frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handlers
        add_action('wp_ajax_vt_add_taxonomy_term', array($this, 'ajax_add_term'));
        add_action('wp_ajax_vt_approve_taxonomy_term', array($this, 'ajax_approve_term'));

        // Admin: Modify terms list table
        add_action('admin_init', array($this, 'setup_term_columns'));

        // Admin: Add pending badge to taxonomy menus
        add_action('admin_menu', array($this, 'add_pending_badges'), 999);

        // Admin: Handle approve action
        add_action('admin_init', array($this, 'handle_approve_action'));

        // Admin: Enqueue admin styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Register app events
        add_filter('voxel/app-events/register', array($this, 'register_app_events'));
        add_filter('voxel/app-events/categories', array($this, 'register_event_category'));

        // Hide pending terms from frontend queries
        add_filter('get_terms', array($this, 'filter_pending_terms'), 10, 4);
        add_filter('get_terms_args', array($this, 'exclude_pending_from_args'), 10, 2);

        // Hook into ALL database queries to filter Voxel's direct SQL queries
        add_filter('query', array($this, 'filter_voxel_term_queries'));

        // Elementor controls for styling - add new section after post types section
        add_action('elementor/element/ts-create-post/ts_sf_post_types/after_section_end', array($this, 'register_elementor_controls'), 10, 2);
    }

    /**
     * Filter out pending terms from term query results
     */
    public function filter_pending_terms($terms, $taxonomies, $args, $term_query) {
        // Only filter on frontend (not in admin or AJAX)
        if (is_admin() || wp_doing_ajax()) {
            return $terms;
        }

        // Skip if empty or not an array of term objects
        if (empty($terms) || !is_array($terms)) {
            return $terms;
        }

        // Filter out pending terms
        $filtered = array_filter($terms, function($term) {
            // Handle both term objects and term IDs
            $term_id = is_object($term) ? $term->term_id : (is_numeric($term) ? (int) $term : 0);

            if (!$term_id) {
                return true; // Keep non-standard items
            }

            // Check if this term is pending
            $is_pending = get_term_meta($term_id, '_vt_pending_approval', true);

            return !$is_pending; // Keep term if NOT pending
        });

        return array_values($filtered); // Re-index array
    }

    /**
     * Exclude pending terms via query args
     */
    public function exclude_pending_from_args($args, $taxonomies) {
        // Only filter on frontend (not in admin or AJAX)
        if (is_admin() || wp_doing_ajax()) {
            return $args;
        }

        $pending_ids = $this->get_pending_term_ids();
        if (empty($pending_ids)) {
            return $args;
        }

        // Merge with existing exclude
        $existing = isset($args['exclude']) ? (array) $args['exclude'] : [];
        $args['exclude'] = array_unique(array_merge($existing, $pending_ids));

        return $args;
    }

    /**
     * Filter Voxel's direct SQL term queries to exclude pending terms
     */
    public function filter_voxel_term_queries($query) {
        // Prevent recursion
        static $filtering = false;
        if ($filtering) {
            return $query;
        }

        // Only filter on frontend (not in admin or AJAX)
        if (is_admin() || wp_doing_ajax()) {
            return $query;
        }

        // Only filter SELECT queries on terms table that look like Voxel's query
        // Voxel's query pattern: SELECT ... FROM wp_terms AS t INNER JOIN wp_term_taxonomy AS tt
        global $wpdb;

        // Must be a SELECT query
        if (stripos(trim($query), 'SELECT') !== 0) {
            return $query;
        }

        // Must have Voxel's specific pattern
        if (
            stripos($query, "FROM {$wpdb->terms} AS t") === false ||
            stripos($query, "{$wpdb->term_taxonomy} AS tt") === false
        ) {
            return $query;
        }

        // Must have WHERE clause
        if (stripos($query, 'WHERE') === false) {
            return $query;
        }

        // Get pending term IDs
        $filtering = true;
        $pending_ids = $this->get_pending_term_ids();
        $filtering = false;

        if (empty($pending_ids)) {
            return $query;
        }

        // Add exclusion to WHERE clause
        $exclude_ids = implode(',', $pending_ids);

        // Replace WHERE with our condition added
        $query = preg_replace(
            '/\bWHERE\s+/i',
            "WHERE t.term_id NOT IN ({$exclude_ids}) AND ",
            $query,
            1
        );

        return $query;
    }

    /**
     * Get all term IDs that are pending approval
     */
    private function get_pending_term_ids() {
        global $wpdb;

        $pending_ids = $wpdb->get_col(
            "SELECT term_id FROM {$wpdb->termmeta}
            WHERE meta_key = '_vt_pending_approval'
            AND meta_value = '1'"
        );

        return array_map('intval', $pending_ids);
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        // Only enqueue on pages with Voxel forms
        if (!$this->should_enqueue_scripts()) {
            return;
        }

        wp_enqueue_style(
            'vt-add-category',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/add-category.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        wp_enqueue_script(
            'vt-add-category',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/add-category.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Get field configs for all taxonomy fields
        $field_configs = $this->get_taxonomy_field_configs();

        wp_localize_script('vt-add-category', 'vt_add_category', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_add_category_nonce'),
            'field_configs' => $field_configs,
            'i18n' => array(
                'add_new' => __('Add new', 'voxel-toolkit'),
                'name_placeholder' => __('Term name', 'voxel-toolkit'),
                'description_placeholder' => __('Description (optional)', 'voxel-toolkit'),
                'add_button' => __('Add', 'voxel-toolkit'),
                'cancel_button' => __('Cancel', 'voxel-toolkit'),
                'adding' => __('Adding...', 'voxel-toolkit'),
                'error_empty_name' => __('Please enter a term name', 'voxel-toolkit'),
                'error_duplicate' => __('This term already exists', 'voxel-toolkit'),
                'success_added' => __('Term added successfully', 'voxel-toolkit'),
                'success_pending' => __('Term submitted for approval. Once approved, you can edit your post to add it.', 'voxel-toolkit'),
            ),
        ));
    }

    /**
     * Check if scripts should be enqueued
     */
    private function should_enqueue_scripts() {
        // Check if user is logged in (required for adding terms)
        if (!is_user_logged_in()) {
            return false;
        }

        // Check if Voxel is active
        if (!class_exists('\Voxel\Post_Type')) {
            return false;
        }

        return true;
    }

    /**
     * Get taxonomy field configurations from all Voxel post types
     *
     * Uses compound keys ({post_type}:{field_key}) to avoid collisions
     * when multiple post types have fields with the same key.
     */
    private function get_taxonomy_field_configs() {
        $configs = [];

        if (!class_exists('\Voxel\Post_Type')) {
            return $configs;
        }

        $post_types = \Voxel\Post_Type::get_all();

        foreach ($post_types as $post_type) {
            $fields = $post_type->get_fields();
            $post_type_key = $post_type->get_key();

            foreach ($fields as $field) {
                if ($field->get_type() !== 'taxonomy') {
                    continue;
                }

                $field_key = $field->get_key();

                // Use get_model_value which handles overrides automatically
                $allow_add_terms = $field->get_model_value('vt_allow_add_terms');
                $require_approval = $field->get_prop('vt_require_approval');

                // Default require_approval to true if not set
                if ($require_approval === null) {
                    $require_approval = true;
                }

                $taxonomy = $field->get_prop('taxonomy');

                // Use compound key to avoid collisions across post types
                $config_key = $post_type_key . ':' . $field_key;

                $configs[$config_key] = [
                    'allow_add_terms' => (bool) $allow_add_terms,
                    'require_approval' => (bool) $require_approval,
                    'taxonomy' => $taxonomy,
                    'post_type' => $post_type_key,
                    'field_key' => $field_key,
                ];
            }
        }

        return $configs;
    }

    /**
     * AJAX handler: Add new term
     */
    public function ajax_add_term() {
        try {
            check_ajax_referer('vt_add_category_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => __('You must be logged in to add terms.', 'voxel-toolkit')));
            }

            $term_name = sanitize_text_field($_POST['term_name'] ?? '');
            $term_description = sanitize_textarea_field($_POST['term_description'] ?? '');
            $taxonomy = sanitize_key($_POST['taxonomy'] ?? '');
            $parent_id = absint($_POST['parent_id'] ?? 0);
            $field_key = sanitize_key($_POST['field_key'] ?? '');
            $post_type_key = sanitize_key($_POST['post_type'] ?? '');

            if (empty($term_name)) {
                wp_send_json_error(array('message' => __('Term name is required.', 'voxel-toolkit')));
            }

            if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
                wp_send_json_error(array('message' => __('Invalid taxonomy: ' . $taxonomy, 'voxel-toolkit')));
            }

            // Check if user is allowed to add terms for this field
            $can_add = $this->user_can_add_term($field_key, $post_type_key);
            if (!$can_add) {
                wp_send_json_error(array('message' => __('You are not allowed to add terms. Field: ' . $field_key . ', Post Type: ' . $post_type_key, 'voxel-toolkit')));
            }

        // Check for duplicate
        $existing = term_exists($term_name, $taxonomy);
        if ($existing) {
            wp_send_json_error(array('message' => __('A term with this name already exists.', 'voxel-toolkit')));
        }

        // Get field settings
        $require_approval = $this->get_field_require_approval($field_key, $post_type_key);

        // Insert the term
        $args = array(
            'description' => $term_description,
        );

        if ($parent_id > 0) {
            $args['parent'] = $parent_id;
        }

        $result = wp_insert_term($term_name, $taxonomy, $args);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $term_id = $result['term_id'];
        $term = get_term($term_id, $taxonomy);

        // Store submitter info
        update_term_meta($term_id, '_vt_submitted_by', get_current_user_id());
        update_term_meta($term_id, '_vt_submitted_at', current_time('mysql'));

        if ($require_approval) {
            // Mark as pending
            update_term_meta($term_id, '_vt_pending_approval', '1');
            update_term_meta($term_id, '_vt_source_field', $field_key);
            update_term_meta($term_id, '_vt_source_post_type', $post_type_key);

            // Clear term caches
            clean_term_cache($term_id, $taxonomy);

            // Trigger app event: term pending approval
            $this->trigger_term_pending_event($term_id, $taxonomy);

            wp_send_json_success(array(
                'message' => __('Term submitted for approval.', 'voxel-toolkit'),
                'term' => array(
                    'id' => $term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'pending' => true,
                ),
                'debug' => array(
                    'require_approval' => $require_approval,
                    'meta_set' => get_term_meta($term_id, '_vt_pending_approval', true),
                ),
            ));
        } else {
            // Trigger app event: term added
            $this->trigger_term_added_event($term_id, $taxonomy);

            wp_send_json_success(array(
                'message' => __('Term added successfully.', 'voxel-toolkit'),
                'term' => array(
                    'id' => $term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'pending' => false,
                ),
            ));
        }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        } catch (\Error $e) {
            wp_send_json_error(array('message' => 'PHP Error: ' . $e->getMessage()));
        }
    }

    /**
     * Check if current user can add term for a specific field
     */
    private function user_can_add_term($field_key, $post_type_key) {
        if (!class_exists('\Voxel\Post_Type')) {
            return false;
        }

        $post_type = \Voxel\Post_Type::get($post_type_key);
        if (!$post_type) {
            return false;
        }

        $fields = $post_type->get_fields();
        if (!isset($fields[$field_key])) {
            return false;
        }

        $field = $fields[$field_key];

        // Debug: Log field class
        error_log('VT Add Category: Field class for ' . $field_key . ' is ' . get_class($field));

        // Use get_model_value if available (handles overrides)
        // This works because get_model_value returns the prop value if no override is active
        $allow = $field->get_model_value('vt_allow_add_terms');

        // Debug: Log the result
        error_log('VT Add Category: get_model_value returned: ' . var_export($allow, true));

        return (bool) $allow;
    }

    /**
     * Get require_approval setting for a field
     */
    private function get_field_require_approval($field_key, $post_type_key) {
        if (!class_exists('\Voxel\Post_Type')) {
            return true;
        }

        $post_type = \Voxel\Post_Type::get($post_type_key);
        if (!$post_type) {
            return true;
        }

        $fields = $post_type->get_fields();
        if (!isset($fields[$field_key])) {
            return true;
        }

        $field = $fields[$field_key];
        $require = $field->get_prop('vt_require_approval');

        return $require === null ? true : (bool) $require;
    }

    /**
     * AJAX handler: Approve pending term
     */
    public function ajax_approve_term() {
        check_ajax_referer('vt_add_category_nonce', 'nonce');

        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('You do not have permission to approve terms.', 'voxel-toolkit')));
        }

        $term_id = absint($_POST['term_id'] ?? 0);
        $taxonomy = sanitize_key($_POST['taxonomy'] ?? '');

        if (!$term_id || !$taxonomy) {
            wp_send_json_error(array('message' => __('Invalid term.', 'voxel-toolkit')));
        }

        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(array('message' => __('Term not found.', 'voxel-toolkit')));
        }

        // Remove pending status
        delete_term_meta($term_id, '_vt_pending_approval');

        // Trigger app event: term approved
        $this->trigger_term_approved_event($term_id, $taxonomy);

        wp_send_json_success(array(
            'message' => __('Term approved successfully.', 'voxel-toolkit'),
        ));
    }

    /**
     * Setup term list table columns for all taxonomies
     */
    public function setup_term_columns() {
        $taxonomies = get_taxonomies(array('public' => true), 'names');

        foreach ($taxonomies as $taxonomy) {
            add_filter("manage_edit-{$taxonomy}_columns", array($this, 'add_pending_column'));
            add_filter("manage_{$taxonomy}_custom_column", array($this, 'render_pending_column'), 10, 3);
            add_filter("{$taxonomy}_row_actions", array($this, 'add_approve_action'), 10, 2);
        }
    }

    /**
     * Add pending status column
     */
    public function add_pending_column($columns) {
        $new_columns = array();

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            // Add after name column
            if ($key === 'name') {
                $new_columns['vt_status'] = __('Status', 'voxel-toolkit');
            }
        }

        return $new_columns;
    }

    /**
     * Render pending status column
     */
    public function render_pending_column($content, $column_name, $term_id) {
        if ($column_name !== 'vt_status') {
            return $content;
        }

        $is_pending = get_term_meta($term_id, '_vt_pending_approval', true);

        if ($is_pending) {
            $submitted_by = get_term_meta($term_id, '_vt_submitted_by', true);
            $user = $submitted_by ? get_user_by('id', $submitted_by) : null;
            $username = $user ? $user->display_name : __('Unknown', 'voxel-toolkit');

            return sprintf(
                '<span class="vt-term-pending" title="%s">%s</span>',
                esc_attr(sprintf(__('Submitted by %s', 'voxel-toolkit'), $username)),
                __('Pending', 'voxel-toolkit')
            );
        }

        return '<span class="vt-term-published">' . __('Published', 'voxel-toolkit') . '</span>';
    }

    /**
     * Add approve action to row actions
     */
    public function add_approve_action($actions, $term) {
        $is_pending = get_term_meta($term->term_id, '_vt_pending_approval', true);

        if ($is_pending && current_user_can('manage_categories')) {
            $approve_url = wp_nonce_url(
                add_query_arg(array(
                    'action' => 'vt_approve_term',
                    'term_id' => $term->term_id,
                    'taxonomy' => $term->taxonomy,
                ), admin_url('admin.php')),
                'vt_approve_term_' . $term->term_id
            );

            $actions['vt_approve'] = sprintf(
                '<a href="%s" class="vt-approve-term">%s</a>',
                esc_url($approve_url),
                __('Approve', 'voxel-toolkit')
            );
        }

        return $actions;
    }

    /**
     * Handle approve action from admin
     */
    public function handle_approve_action() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'vt_approve_term') {
            return;
        }

        $term_id = absint($_GET['term_id'] ?? 0);
        $taxonomy = sanitize_key($_GET['taxonomy'] ?? '');

        if (!$term_id || !$taxonomy) {
            return;
        }

        check_admin_referer('vt_approve_term_' . $term_id);

        if (!current_user_can('manage_categories')) {
            wp_die(__('You do not have permission to approve terms.', 'voxel-toolkit'));
        }

        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            wp_die(__('Term not found.', 'voxel-toolkit'));
        }

        // Remove pending status
        delete_term_meta($term_id, '_vt_pending_approval');

        // Trigger app event: term approved
        $this->trigger_term_approved_event($term_id, $taxonomy);

        // Redirect back to terms page
        wp_redirect(add_query_arg(array(
            'taxonomy' => $taxonomy,
            'vt_approved' => 1,
        ), admin_url('edit-tags.php')));
        exit;
    }

    /**
     * Add pending count badges to taxonomy menus
     */
    public function add_pending_badges() {
        global $submenu;

        $taxonomies = get_taxonomies(array('public' => true), 'objects');

        foreach ($taxonomies as $taxonomy) {
            $pending_count = $this->get_pending_term_count($taxonomy->name);

            if ($pending_count === 0) {
                continue;
            }

            $badge = sprintf(
                ' <span class="awaiting-mod vt-pending-terms-badge">%d</span>',
                $pending_count
            );

            // Find and update menu item
            foreach ($submenu as $parent_slug => &$menu_items) {
                foreach ($menu_items as &$item) {
                    if (isset($item[2]) && $item[2] === 'edit-tags.php?taxonomy=' . $taxonomy->name) {
                        $item[0] .= $badge;
                        break 2;
                    }
                }
            }
        }
    }

    /**
     * Get count of pending terms for a taxonomy
     */
    private function get_pending_term_count($taxonomy) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT tm.term_id)
            FROM {$wpdb->termmeta} tm
            INNER JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id
            WHERE tm.meta_key = '_vt_pending_approval'
            AND tm.meta_value = '1'
            AND tt.taxonomy = %s",
            $taxonomy
        ));

        return (int) $count;
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'edit-tags.php' && $hook !== 'term.php') {
            return;
        }

        wp_add_inline_style('wp-admin', '
            .vt-term-pending {
                display: inline-block;
                padding: 2px 8px;
                background: #f0c33c;
                color: #1d2327;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
            }
            .vt-term-published {
                display: inline-block;
                padding: 2px 8px;
                background: #00a32a;
                color: #fff;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
            }
            .vt-approve-term {
                color: #00a32a !important;
                font-weight: 500;
            }
            .vt-pending-terms-badge {
                background: #f0c33c !important;
                color: #1d2327 !important;
            }
        ');

        // Show success message after approval
        if (isset($_GET['vt_approved'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    esc_html__('Term approved successfully.', 'voxel-toolkit') .
                    '</p></div>';
            });
        }
    }

    /**
     * Trigger term pending approval event
     */
    private function trigger_term_pending_event($term_id, $taxonomy) {
        do_action('voxel_toolkit/term_pending_approval', $term_id, $taxonomy);
    }

    /**
     * Trigger term added event
     */
    private function trigger_term_added_event($term_id, $taxonomy) {
        do_action('voxel_toolkit/term_added', $term_id, $taxonomy);
    }

    /**
     * Trigger term approved event
     */
    private function trigger_term_approved_event($term_id, $taxonomy) {
        do_action('voxel_toolkit/term_approved', $term_id, $taxonomy);
    }

    /**
     * Register event category
     */
    public function register_event_category($categories) {
        if (!isset($categories['voxel_toolkit'])) {
            $categories['voxel_toolkit'] = [
                'key' => 'voxel_toolkit',
                'label' => 'Voxel Toolkit',
            ];
        }
        return $categories;
    }

    /**
     * Register app events
     */
    public function register_app_events($events) {
        if (!class_exists('\\Voxel\\Events\\Base_Event')) {
            return $events;
        }

        // Term Added Event
        $term_added = new Voxel_Toolkit_Term_Added_Event();
        $events[$term_added->get_key()] = $term_added;

        // Term Pending Approval Event
        $term_pending = new Voxel_Toolkit_Term_Pending_Event();
        $events[$term_pending->get_key()] = $term_pending;

        // Term Approved Event
        $term_approved = new Voxel_Toolkit_Term_Approved_Event();
        $events[$term_approved->get_key()] = $term_approved;

        return $events;
    }

    /**
     * Register Elementor controls for Add Category button styling
     */
    public function register_elementor_controls($element, $args) {
        $element->start_controls_section(
            'vt_add_category_section',
            [
                'label' => __('Add Category (VT)', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $element->add_control(
            'vt_add_category_heading',
            [
                'label' => __('Add New Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
            ]
        );

        $element->add_control(
            'vt_add_category_bg',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-add-term-trigger .vt-add-term-btn' => 'background: {{VALUE}};',
                    '.vt-add-term-trigger .vt-add-term-btn' => 'background: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_add_category_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-add-term-trigger .vt-add-term-btn' => 'color: {{VALUE}};',
                    '.vt-add-term-trigger .vt-add-term-btn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_add_category_icon_bg',
            [
                'label' => __('Icon Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-add-term-trigger .vt-add-icon' => 'background: {{VALUE}};',
                    '.vt-add-term-trigger .vt-add-icon' => 'background: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_add_category_icon_color',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-add-term-trigger .vt-add-icon' => 'color: {{VALUE}};',
                    '.vt-add-term-trigger .vt-add-icon' => 'color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_add_category_hover_bg',
            [
                'label' => __('Hover Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-add-term-trigger .vt-add-term-btn:hover' => 'background: {{VALUE}};',
                    '.vt-add-term-trigger .vt-add-term-btn:hover' => 'background: {{VALUE}};',
                ],
            ]
        );

        $element->add_responsive_control(
            'vt_add_category_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-add-term-trigger .vt-add-term-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '.vt-add-term-trigger .vt-add-term-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $element->add_responsive_control(
            'vt_add_category_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-add-term-trigger .vt-add-term-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                    '.vt-add-term-trigger .vt-add-term-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $element->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'vt_add_category_typography',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-add-term-trigger .vt-add-term-btn, .vt-add-term-trigger .vt-add-term-btn',
            ]
        );

        // Form styling
        $element->add_control(
            'vt_add_category_form_heading',
            [
                'label' => __('Add Category Form', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $element->add_control(
            'vt_add_category_input_bg',
            [
                'label' => __('Input Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-add-term-form input[type="text"]' => 'background: {{VALUE}} !important;',
                    '{{WRAPPER}} .vt-add-term-form textarea' => 'background: {{VALUE}} !important;',
                    '.vt-add-term-form input[type="text"]' => 'background: {{VALUE}} !important;',
                    '.vt-add-term-form textarea' => 'background: {{VALUE}} !important;',
                ],
            ]
        );

        $element->add_control(
            'vt_add_category_input_text',
            [
                'label' => __('Input Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-add-term-form input[type="text"]' => 'color: {{VALUE}} !important;',
                    '{{WRAPPER}} .vt-add-term-form textarea' => 'color: {{VALUE}} !important;',
                    '.vt-add-term-form input[type="text"]' => 'color: {{VALUE}} !important;',
                    '.vt-add-term-form textarea' => 'color: {{VALUE}} !important;',
                ],
            ]
        );

        $element->add_control(
            'vt_add_category_input_border',
            [
                'label' => __('Input Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-add-term-form input[type="text"]' => 'border-color: {{VALUE}} !important;',
                    '{{WRAPPER}} .vt-add-term-form textarea' => 'border-color: {{VALUE}} !important;',
                    '.vt-add-term-form input[type="text"]' => 'border-color: {{VALUE}} !important;',
                    '.vt-add-term-form textarea' => 'border-color: {{VALUE}} !important;',
                ],
            ]
        );

        $element->add_control(
            'vt_add_category_submit_bg',
            [
                'label' => __('Submit Button Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-add-term-submit' => 'background: {{VALUE}};',
                    '.vt-add-term-submit' => 'background: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_add_category_submit_color',
            [
                'label' => __('Submit Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-add-term-submit' => 'color: {{VALUE}};',
                    '.vt-add-term-submit' => 'color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_add_category_cancel_bg',
            [
                'label' => __('Cancel Button Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-add-term-cancel' => 'background: {{VALUE}};',
                    '.vt-add-term-cancel' => 'background: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_add_category_cancel_color',
            [
                'label' => __('Cancel Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-add-term-cancel' => 'color: {{VALUE}};',
                    '.vt-add-term-cancel' => 'color: {{VALUE}};',
                ],
            ]
        );

        $element->end_controls_section();
    }
}

// Only define event classes if Voxel Base_Event exists
if (class_exists('\Voxel\Events\Base_Event')) {

    /**
     * Term Added Event (no approval needed)
     */
    class Voxel_Toolkit_Term_Added_Event extends \Voxel\Events\Base_Event {

        public $term;
        public $taxonomy;
        public $submitter;
        protected $term_id;
        protected $taxonomy_name;

        public function prepare($term_id = null, $taxonomy = null) {
            // Store or use stored values
            if ($term_id !== null) {
                $this->term_id = $term_id;
            }
            if ($taxonomy !== null) {
                $this->taxonomy_name = $taxonomy;
            }

            // If already prepared, skip
            if ($this->term !== null) {
                return;
            }

            // Need both values
            if (empty($this->term_id) || empty($this->taxonomy_name)) {
                return;
            }

            $term = get_term($this->term_id, $this->taxonomy_name);
            if ($term && !is_wp_error($term)) {
                $this->term = $term;
                $this->taxonomy = get_taxonomy($this->taxonomy_name);

                $submitter_id = get_term_meta($this->term_id, '_vt_submitted_by', true);
                $this->submitter = $submitter_id ? \Voxel\User::get($submitter_id) : null;
            }
            // Don't throw - allow notification to display with fallback
        }

        public function get_key(): string {
            return 'voxel_toolkit/term:added';
        }

        public function get_label(): string {
            return 'Voxel Toolkit: New term added';
        }

        public function get_category() {
            return 'voxel_toolkit';
        }

        public static function notifications(): array {
            return [
                'admin' => [
                    'label' => 'Notify admin',
                    'recipient' => function($event) {
                        return \Voxel\User::get(\Voxel\get('settings.notifications.admin_user'));
                    },
                    'inapp' => [
                        'enabled' => true,
                        'subject' => 'New term added',
                        'details' => function($event) {
                            return [
                                'term_id' => $event->term->term_id,
                                'term_name' => $event->term->name,
                                'taxonomy' => $event->term->taxonomy,
                            ];
                        },
                        'apply_details' => function($event, $details, $notification = null) {
                            $event->prepare(
                                $details['term_id'] ?? null,
                                $details['taxonomy'] ?? ''
                            );
                        },
                    ],
                    'email' => [
                        'enabled' => false,
                        'subject' => 'New term added to your site',
                        'message' => 'A new term has been added to your site. Please check the admin panel for details.',
                    ],
                ],
            ];
        }
    }

    /**
     * Term Pending Approval Event
     */
    class Voxel_Toolkit_Term_Pending_Event extends \Voxel\Events\Base_Event {

        public $term;
        public $taxonomy;
        public $submitter;
        protected $term_id;
        protected $taxonomy_name;

        public function prepare($term_id = null, $taxonomy = null) {
            // Store or use stored values
            if ($term_id !== null) {
                $this->term_id = $term_id;
            }
            if ($taxonomy !== null) {
                $this->taxonomy_name = $taxonomy;
            }

            // If already prepared, skip
            if ($this->term !== null) {
                return;
            }

            // Need both values
            if (empty($this->term_id) || empty($this->taxonomy_name)) {
                return;
            }

            $term = get_term($this->term_id, $this->taxonomy_name);
            if ($term && !is_wp_error($term)) {
                $this->term = $term;
                $this->taxonomy = get_taxonomy($this->taxonomy_name);

                $submitter_id = get_term_meta($this->term_id, '_vt_submitted_by', true);
                $this->submitter = $submitter_id ? \Voxel\User::get($submitter_id) : null;
            }
            // Don't throw - allow notification to display with fallback
        }

        public function get_key(): string {
            return 'voxel_toolkit/term:pending';
        }

        public function get_label(): string {
            return 'Voxel Toolkit: Term pending approval';
        }

        public function get_category() {
            return 'voxel_toolkit';
        }

        public static function notifications(): array {
            return [
                'admin' => [
                    'label' => 'Notify admin',
                    'recipient' => function($event) {
                        return \Voxel\User::get(\Voxel\get('settings.notifications.admin_user'));
                    },
                    'inapp' => [
                        'enabled' => true,
                        'subject' => 'Term pending approval',
                        'details' => function($event) {
                            return [
                                'term_id' => $event->term->term_id,
                                'term_name' => $event->term->name,
                                'taxonomy' => $event->term->taxonomy,
                            ];
                        },
                        'apply_details' => function($event, $details, $notification = null) {
                            $event->prepare(
                                $details['term_id'] ?? null,
                                $details['taxonomy'] ?? ''
                            );
                        },
                        'links_to' => function($event) {
                            if ($event->term) {
                                $post_type = get_term_meta($event->term->term_id, '_vt_source_post_type', true);
                                $url = 'edit-tags.php?taxonomy=' . $event->term->taxonomy;
                                if ($post_type) {
                                    $url .= '&post_type=' . $post_type;
                                }
                                return admin_url($url);
                            }
                            return admin_url('edit-tags.php');
                        },
                    ],
                    'email' => [
                        'enabled' => false,
                        'subject' => 'Term pending approval',
                        'message' => 'A new term has been submitted and needs your approval. Please check the admin panel to review and approve it.',
                    ],
                ],
            ];
        }
    }

    /**
     * Term Approved Event
     */
    class Voxel_Toolkit_Term_Approved_Event extends \Voxel\Events\Base_Event {

        public $term;
        public $taxonomy;
        public $submitter;
        protected $term_id;
        protected $taxonomy_name;

        public function prepare($term_id = null, $taxonomy = null) {
            // Store or use stored values
            if ($term_id !== null) {
                $this->term_id = $term_id;
            }
            if ($taxonomy !== null) {
                $this->taxonomy_name = $taxonomy;
            }

            // If already prepared, skip
            if ($this->term !== null) {
                return;
            }

            // Need both values
            if (empty($this->term_id) || empty($this->taxonomy_name)) {
                return;
            }

            $term = get_term($this->term_id, $this->taxonomy_name);
            if ($term && !is_wp_error($term)) {
                $this->term = $term;
                $this->taxonomy = get_taxonomy($this->taxonomy_name);

                $submitter_id = get_term_meta($this->term_id, '_vt_submitted_by', true);
                $this->submitter = $submitter_id ? \Voxel\User::get($submitter_id) : null;
            }
            // Don't throw - allow notification to display with fallback
        }

        public function get_key(): string {
            return 'voxel_toolkit/term:approved';
        }

        public function get_label(): string {
            return 'Voxel Toolkit: Term approved';
        }

        public function get_category() {
            return 'voxel_toolkit';
        }

        public static function notifications(): array {
            return [
                'submitter' => [
                    'label' => 'Notify submitter',
                    'recipient' => function($event) {
                        return $event->submitter;
                    },
                    'inapp' => [
                        'enabled' => true,
                        'subject' => 'Your term has been approved',
                        'details' => function($event) {
                            return [
                                'term_id' => $event->term->term_id,
                                'term_name' => $event->term->name,
                                'taxonomy' => $event->term->taxonomy,
                            ];
                        },
                        'apply_details' => function($event, $details, $notification = null) {
                            $event->prepare(
                                $details['term_id'] ?? null,
                                $details['taxonomy'] ?? ''
                            );
                        },
                    ],
                    'email' => [
                        'enabled' => false,
                        'subject' => 'Your term has been approved',
                        'message' => 'Great news! Your submitted term has been approved and is now live. Thank you for your contribution!',
                    ],
                ],
            ];
        }
    }

    // Hook up the events to fire
    add_action('voxel_toolkit/term_added', function($term_id, $taxonomy) {
        try {
            $event = new Voxel_Toolkit_Term_Added_Event();
            $event->prepare($term_id, $taxonomy);
            $event->dispatch();
        } catch (\Exception $e) {
            // Silently fail
        }
    }, 10, 2);

    add_action('voxel_toolkit/term_pending_approval', function($term_id, $taxonomy) {
        try {
            $event = new Voxel_Toolkit_Term_Pending_Event();
            $event->prepare($term_id, $taxonomy);
            $event->dispatch();
        } catch (\Exception $e) {
            // Silently fail
        }
    }, 10, 2);

    add_action('voxel_toolkit/term_approved', function($term_id, $taxonomy) {
        try {
            $event = new Voxel_Toolkit_Term_Approved_Event();
            $event->prepare($term_id, $taxonomy);
            $event->dispatch();
        } catch (\Exception $e) {
            // Silently fail
        }
    }, 10, 2);
}

/**
 * Extended Taxonomy Field Type
 *
 * Extends Voxel's native Taxonomy_Field to add "Add Category" configuration options
 */
if (class_exists('\Voxel\Post_Types\Fields\Taxonomy_Field')) {

    class Voxel_Toolkit_Extended_Taxonomy_Field extends \Voxel\Post_Types\Fields\Taxonomy_Field {

        protected $props = [
            'type' => 'taxonomy',
            'label' => 'Taxonomy',
            'taxonomy' => '',
            'placeholder' => '',
            'multiple' => true,
            'display_as' => 'popup',
            'backend_edit_mode' => 'custom_field',
            'min' => null,
            'max' => null,
            'default' => null,
            // VT: Add Category options
            'vt_allow_add_terms' => false,
            'vt_require_approval' => true,
        ];

        /**
         * Get field editor models
         */
        public function get_models(): array {
            $parent_models = parent::get_models();

            // Insert VT settings after max selections
            $new_models = [];
            foreach ($parent_models as $key => $model) {
                $new_models[$key] = $model;

                // Insert our fields after max
                if ($key === 'max') {
                    $new_models['vt_allow_add_terms'] = [
                        'type' => \Voxel\Form_Models\Switcher_Model::class,
                        'label' => 'Allow users to add new terms',
                        'description' => 'When enabled, users can add new terms to this taxonomy from the frontend form. Use overrides to control per membership/role.',
                        'classes' => 'x-col-12',
                    ];

                    $new_models['vt_require_approval'] = [
                        'type' => \Voxel\Form_Models\Switcher_Model::class,
                        'label' => 'Require approval for new terms',
                        'description' => 'When enabled, new terms will be pending until an admin approves them.',
                        'classes' => 'x-col-12',
                        'v-if' => 'field.vt_allow_add_terms',
                    ];
                }
            }

            return $new_models;
        }

        /**
         * Define overridable models (for visibility rules / membership conditions)
         */
        protected function overridable_models(): array {
            $parent_overridable = parent::overridable_models();

            // Add our overridable model
            $parent_overridable['vt_allow_add_terms'] = [
                'type' => 'switcher',
                'label' => 'Allow users to add new terms',
            ];

            return $parent_overridable;
        }

        /**
         * Get frontend props (passed to JavaScript)
         */
        protected function frontend_props() {
            $props = parent::frontend_props();

            // Get allow_add_terms with override support
            $allow_add_terms = $this->get_model_value('vt_allow_add_terms');
            if ($allow_add_terms === null) {
                $allow_add_terms = $this->get_prop('vt_allow_add_terms');
            }

            // Get require_approval (not overridable)
            $require_approval = $this->get_prop('vt_require_approval');
            if ($require_approval === null) {
                $require_approval = true;
            }

            // Add VT config
            $props['vt_add_category'] = [
                'enabled' => (bool) $allow_add_terms,
                'require_approval' => (bool) $require_approval,
                'field_key' => $this->get_key(),
                'post_type' => $this->post_type->get_key(),
                'taxonomy' => $this->get_prop('taxonomy'),
            ];

            return $props;
        }
    }
}
