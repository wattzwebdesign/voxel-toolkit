<?php
/**
 * RSVP Function
 *
 * Allow users to RSVP to posts/events with guest support and approval workflow
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load RSVP Schema class for field definitions per post type
require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/class-rsvp-schema.php';

class Voxel_Toolkit_RSVP {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Database table name
     */
    private $table_name;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'voxel_toolkit_rsvps';

        // Create table on activation
        add_action('admin_init', array($this, 'maybe_create_table'));

        // AJAX handlers - public (guests allowed)
        add_action('wp_ajax_vt_rsvp_submit', array($this, 'ajax_submit_rsvp'));
        add_action('wp_ajax_nopriv_vt_rsvp_submit', array($this, 'ajax_submit_rsvp'));
        add_action('wp_ajax_vt_rsvp_cancel', array($this, 'ajax_cancel_rsvp'));
        add_action('wp_ajax_nopriv_vt_rsvp_cancel', array($this, 'ajax_cancel_rsvp'));
        add_action('wp_ajax_vt_rsvp_get_list', array($this, 'ajax_get_list'));
        add_action('wp_ajax_nopriv_vt_rsvp_get_list', array($this, 'ajax_get_list'));
        add_action('wp_ajax_vt_rsvp_get_widget_data', array($this, 'ajax_get_widget_data'));
        add_action('wp_ajax_nopriv_vt_rsvp_get_widget_data', array($this, 'ajax_get_widget_data'));

        // AJAX handlers - admin only
        add_action('wp_ajax_vt_rsvp_approve', array($this, 'ajax_approve_rsvp'));
        add_action('wp_ajax_vt_rsvp_reject', array($this, 'ajax_reject_rsvp'));
        add_action('wp_ajax_vt_rsvp_delete', array($this, 'ajax_delete_rsvp'));
        add_action('wp_ajax_vt_rsvp_export', array($this, 'ajax_export_csv'));

        // Register widgets
        add_action('elementor/widgets/register', array($this, 'register_widgets'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Register app events
        add_filter('voxel/app-events/register', array($this, 'register_app_events'));
        add_filter('voxel/app-events/categories', array($this, 'register_event_category'));

        // Scan for RSVP widget schemas when Elementor saves
        add_action('elementor/document/after_save', array($this, 'scan_elementor_for_rsvp_schemas'), 10, 2);
    }

    /**
     * Scan Elementor document for RSVP widgets and save their schemas
     */
    public function scan_elementor_for_rsvp_schemas($document, $data) {
        if (empty($data['elements'])) {
            return;
        }

        $this->extract_rsvp_widgets_recursive($data['elements']);
    }

    /**
     * Recursively extract RSVP widget settings from Elementor data
     */
    private function extract_rsvp_widgets_recursive($elements) {
        foreach ($elements as $element) {
            // Check if this is an RSVP widget
            if (isset($element['widgetType']) && $element['widgetType'] === 'voxel-rsvp-form') {
                $settings = $element['settings'] ?? [];
                $target_post_type = $settings['target_post_type'] ?? '';
                $guest_fields = $settings['guest_fields'] ?? [];

                if ($target_post_type && !empty($guest_fields)) {
                    $this->save_rsvp_schema_from_widget($target_post_type, $guest_fields);
                }
            }

            // Recurse into child elements
            if (!empty($element['elements'])) {
                $this->extract_rsvp_widgets_recursive($element['elements']);
            }
        }
    }

    /**
     * Save RSVP schema from widget settings
     */
    private function save_rsvp_schema_from_widget($post_type, $guest_fields) {
        $schema = Voxel_Toolkit_RSVP_Schema::instance();
        $fields = [];

        foreach ($guest_fields as $field) {
            $key = sanitize_key($field['field_key'] ?? '');
            if (empty($key)) {
                continue;
            }

            $fields[$key] = [
                'key' => $key,
                'label' => $field['field_label'] ?? ucwords(str_replace('_', ' ', $key)),
                'type' => $field['field_type'] ?? 'text',
                'required' => ($field['field_required'] ?? '') === 'yes',
            ];
        }

        if (!empty($fields)) {
            // Use replace (not merge) so re-saving clears old/stale fields
            $schema->replace_schema($post_type, $fields);
        }
    }

    /**
     * Create database table if it doesn't exist
     */
    public function maybe_create_table() {
        global $wpdb;

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");

        if ($table_exists != $this->table_name) {
            $this->create_table();
        }
    }

    /**
     * Create RSVPs table
     */
    private function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT 0,
            user_name varchar(255) NOT NULL,
            user_email varchar(255) NOT NULL,
            comment text,
            custom_fields longtext,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_email (post_id, user_email),
            KEY post_id (post_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Register Elementor widgets
     */
    public function register_widgets($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-rsvp-form-widget.php';
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-attendee-list-widget.php';

        $widgets_manager->register(new \Voxel_Toolkit_RSVP_Form_Widget());
        $widgets_manager->register(new \Voxel_Toolkit_RSVP_Attendee_List_Widget());
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        wp_register_style(
            'voxel-toolkit-rsvp',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/rsvp.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        wp_register_script(
            'voxel-toolkit-rsvp',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/rsvp.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        wp_localize_script('voxel-toolkit-rsvp', 'vtRsvp', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_rsvp_nonce'),
            'i18n' => array(
                'submitting' => __('Submitting...', 'voxel-toolkit'),
                'cancelling' => __('Cancelling...', 'voxel-toolkit'),
                'approving' => __('Approving...', 'voxel-toolkit'),
                'rejecting' => __('Rejecting...', 'voxel-toolkit'),
                'deleting' => __('Deleting...', 'voxel-toolkit'),
                'error' => __('An error occurred. Please try again.', 'voxel-toolkit'),
                'confirmCancel' => __('Are you sure you want to cancel your RSVP?', 'voxel-toolkit'),
                'confirmDelete' => __('Are you sure you want to delete this RSVP?', 'voxel-toolkit'),
            ),
        ));
    }

    /**
     * Get RSVP count for a post
     */
    public function get_rsvp_count($post_id, $statuses = array('approved')) {
        global $wpdb;

        if (empty($statuses)) {
            $statuses = array('approved');
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $query_args = array_merge(array($post_id), $statuses);

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE post_id = %d AND status IN ($placeholders)",
            $query_args
        ));
    }

    /**
     * Get RSVPs for a post
     */
    public function get_rsvps($post_id, $statuses = array('approved'), $page = 1, $per_page = 10) {
        global $wpdb;

        if (empty($statuses)) {
            $statuses = array('approved');
        }

        $offset = ($page - 1) * $per_page;
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $query_args = array_merge(array($post_id), $statuses, array($per_page, $offset));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE post_id = %d AND status IN ($placeholders)
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $query_args
        ));
    }

    /**
     * Check if user has RSVP for a post
     */
    public function user_has_rsvp($post_id, $email) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE post_id = %d AND user_email = %s",
            $post_id,
            $email
        ));
    }

    /**
     * Get user's RSVP for a post
     */
    public function get_user_rsvp($post_id, $email) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d AND user_email = %s",
            $post_id,
            $email
        ));
    }

    /**
     * Get RSVP by ID
     */
    public function get_rsvp_by_id($rsvp_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $rsvp_id
        ));
    }

    /**
     * AJAX: Submit new RSVP
     */
    public function ajax_submit_rsvp() {
        check_ajax_referer('vt_rsvp_nonce', 'nonce');

        $post_id = absint($_POST['post_id'] ?? 0);
        $require_approval = isset($_POST['require_approval']) && $_POST['require_approval'] === 'yes';
        $max_attendees = absint($_POST['max_attendees'] ?? 0);
        $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'voxel-toolkit')));
        }

        // Get user info
        $user_id = get_current_user_id();
        $user_name = '';
        $user_email = '';

        if ($user_id) {
            $user = get_userdata($user_id);
            $user_email = $user->user_email;
            $user_name = $user->display_name;
        } else {
            $user_name = sanitize_text_field(wp_unslash($_POST['user_name'] ?? ''));
            $user_email = sanitize_email(wp_unslash($_POST['user_email'] ?? ''));

            if (empty($user_name) || empty($user_email)) {
                wp_send_json_error(array('message' => __('Name and email are required', 'voxel-toolkit')));
            }

            if (!is_email($user_email)) {
                wp_send_json_error(array('message' => __('Please enter a valid email address', 'voxel-toolkit')));
            }
        }

        // Check if already RSVP'd
        if ($this->user_has_rsvp($post_id, $user_email)) {
            wp_send_json_error(array('message' => __('You have already RSVP\'d to this event', 'voxel-toolkit')));
        }

        // Check RSVP limit (only count approved RSVPs toward limit)
        if ($max_attendees > 0) {
            $current_count = $this->get_rsvp_count($post_id, array('approved'));
            if ($current_count >= $max_attendees) {
                wp_send_json_error(array('message' => __('Sorry, this event has reached maximum capacity', 'voxel-toolkit')));
            }
        }

        // Process custom fields
        $custom_fields = array();
        if (isset($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
            foreach ($_POST['custom_fields'] as $key => $value) {
                $sanitized_key = sanitize_key($key);
                $unslashed_value = wp_unslash($value);
                // Sanitize value based on whether it contains newlines (textarea) or not
                if (strpos($unslashed_value, "\n") !== false) {
                    $custom_fields[$sanitized_key] = sanitize_textarea_field($unslashed_value);
                } else {
                    $custom_fields[$sanitized_key] = sanitize_text_field($unslashed_value);
                }
            }
        }
        $custom_fields_json = !empty($custom_fields) ? wp_json_encode($custom_fields) : null;

        // Determine initial status
        $status = $require_approval ? 'pending' : 'approved';

        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'post_id' => $post_id,
                'user_id' => $user_id,
                'user_name' => $user_name,
                'user_email' => $user_email,
                'comment' => $comment,
                'custom_fields' => $custom_fields_json,
                'status' => $status,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to submit RSVP. Please try again.', 'voxel-toolkit')));
        }

        $rsvp_id = $wpdb->insert_id;
        $new_count = $this->get_rsvp_count($post_id, array('approved'));

        // Dispatch app event
        try {
            if (class_exists('Voxel_Toolkit_RSVP_Submitted_Event') && class_exists('\\Voxel\\Post')) {
                $post = \Voxel\Post::get($post_id);
                if ($post && $post->post_type) {
                    $event = new Voxel_Toolkit_RSVP_Submitted_Event($post->post_type);
                    $event->dispatch($rsvp_id, $post_id, $user_id);
                }
            }
        } catch (\Exception $e) {
            error_log('Voxel Toolkit: Failed to dispatch RSVP submitted event: ' . $e->getMessage());
        }

        wp_send_json_success(array(
            'rsvp_id' => $rsvp_id,
            'status' => $status,
            'count' => $new_count,
            'message' => $status === 'pending'
                ? __('Your RSVP has been submitted and is pending approval.', 'voxel-toolkit')
                : __('Thank you! Your RSVP has been confirmed.', 'voxel-toolkit'),
        ));
    }

    /**
     * AJAX: Cancel RSVP
     */
    public function ajax_cancel_rsvp() {
        check_ajax_referer('vt_rsvp_nonce', 'nonce');

        $post_id = absint($_POST['post_id'] ?? 0);

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'voxel-toolkit')));
        }

        // Get user email
        $user_id = get_current_user_id();
        $user_email = '';

        if ($user_id) {
            $user = get_userdata($user_id);
            $user_email = $user->user_email;
        } else {
            $user_email = sanitize_email($_POST['user_email'] ?? '');
        }

        if (empty($user_email)) {
            wp_send_json_error(array('message' => __('Unable to identify user', 'voxel-toolkit')));
        }

        // Check if RSVP exists
        $rsvp = $this->get_user_rsvp($post_id, $user_email);
        if (!$rsvp) {
            wp_send_json_error(array('message' => __('RSVP not found', 'voxel-toolkit')));
        }

        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array(
                'post_id' => $post_id,
                'user_email' => $user_email,
            ),
            array('%d', '%s')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to cancel RSVP. Please try again.', 'voxel-toolkit')));
        }

        $new_count = $this->get_rsvp_count($post_id, array('approved'));

        wp_send_json_success(array(
            'count' => $new_count,
            'message' => __('Your RSVP has been cancelled.', 'voxel-toolkit'),
        ));
    }

    /**
     * AJAX: Approve RSVP (admin only)
     */
    public function ajax_approve_rsvp() {
        check_ajax_referer('vt_rsvp_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'voxel-toolkit')));
        }

        $rsvp_id = absint($_POST['rsvp_id'] ?? 0);

        if (!$rsvp_id) {
            wp_send_json_error(array('message' => __('Invalid RSVP ID', 'voxel-toolkit')));
        }

        global $wpdb;

        // Get RSVP data before update for event dispatch
        $rsvp = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $rsvp_id
        ));

        if (!$rsvp) {
            wp_send_json_error(array('message' => __('RSVP not found', 'voxel-toolkit')));
        }

        $result = $wpdb->update(
            $this->table_name,
            array('status' => 'approved'),
            array('id' => $rsvp_id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to approve RSVP', 'voxel-toolkit')));
        }

        // Dispatch app event
        try {
            if (class_exists('Voxel_Toolkit_RSVP_Approved_Event') && class_exists('\\Voxel\\Post')) {
                $post = \Voxel\Post::get($rsvp->post_id);
                if ($post && $post->post_type) {
                    $event = new Voxel_Toolkit_RSVP_Approved_Event($post->post_type);
                    $event->dispatch($rsvp_id, $rsvp->post_id, $rsvp->user_id);
                }
            }
        } catch (\Exception $e) {
            error_log('Voxel Toolkit: Failed to dispatch RSVP approved event: ' . $e->getMessage());
        }

        wp_send_json_success(array(
            'message' => __('RSVP approved', 'voxel-toolkit'),
            'new_status' => 'approved',
        ));
    }

    /**
     * AJAX: Reject RSVP (admin only)
     */
    public function ajax_reject_rsvp() {
        check_ajax_referer('vt_rsvp_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'voxel-toolkit')));
        }

        $rsvp_id = absint($_POST['rsvp_id'] ?? 0);

        if (!$rsvp_id) {
            wp_send_json_error(array('message' => __('Invalid RSVP ID', 'voxel-toolkit')));
        }

        global $wpdb;

        // Get RSVP data before update for event dispatch
        $rsvp = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $rsvp_id
        ));

        if (!$rsvp) {
            wp_send_json_error(array('message' => __('RSVP not found', 'voxel-toolkit')));
        }

        $result = $wpdb->update(
            $this->table_name,
            array('status' => 'rejected'),
            array('id' => $rsvp_id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to reject RSVP', 'voxel-toolkit')));
        }

        // Dispatch app event
        try {
            if (class_exists('Voxel_Toolkit_RSVP_Rejected_Event') && class_exists('\\Voxel\\Post')) {
                $post = \Voxel\Post::get($rsvp->post_id);
                if ($post && $post->post_type) {
                    $event = new Voxel_Toolkit_RSVP_Rejected_Event($post->post_type);
                    $event->dispatch($rsvp_id, $rsvp->post_id, $rsvp->user_id);
                }
            }
        } catch (\Exception $e) {
            error_log('Voxel Toolkit: Failed to dispatch RSVP rejected event: ' . $e->getMessage());
        }

        wp_send_json_success(array(
            'message' => __('RSVP rejected', 'voxel-toolkit'),
            'new_status' => 'rejected',
        ));
    }

    /**
     * AJAX: Delete RSVP (admin only)
     */
    public function ajax_delete_rsvp() {
        check_ajax_referer('vt_rsvp_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'voxel-toolkit')));
        }

        $rsvp_id = absint($_POST['rsvp_id'] ?? 0);

        if (!$rsvp_id) {
            wp_send_json_error(array('message' => __('Invalid RSVP ID', 'voxel-toolkit')));
        }

        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $rsvp_id),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to delete RSVP', 'voxel-toolkit')));
        }

        wp_send_json_success(array(
            'message' => __('RSVP deleted', 'voxel-toolkit'),
        ));
    }

    /**
     * AJAX: Get paginated RSVP list
     */
    public function ajax_get_list() {
        check_ajax_referer('vt_rsvp_nonce', 'nonce');

        $post_id = absint($_GET['post_id'] ?? $_POST['post_id'] ?? 0);
        $page = absint($_GET['page'] ?? $_POST['page'] ?? 1);
        $per_page = absint($_GET['per_page'] ?? $_POST['per_page'] ?? 10);
        $statuses = isset($_GET['statuses']) ? array_map('sanitize_text_field', (array) $_GET['statuses']) : array('approved');

        if (empty($statuses)) {
            $statuses = isset($_POST['statuses']) ? array_map('sanitize_text_field', (array) $_POST['statuses']) : array('approved');
        }

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'voxel-toolkit')));
        }

        // Sanitize statuses
        $valid_statuses = array('pending', 'approved', 'rejected');
        $statuses = array_intersect($statuses, $valid_statuses);
        if (empty($statuses)) {
            $statuses = array('approved');
        }

        $per_page = min(100, max(1, $per_page));

        $rsvps = $this->get_rsvps($post_id, $statuses, $page, $per_page);
        $total = $this->get_rsvp_count($post_id, $statuses);
        $pages = ceil($total / $per_page);

        // Format RSVPs for response
        $formatted_rsvps = array();
        foreach ($rsvps as $rsvp) {
            $avatar_url = '';
            if ($rsvp->user_id) {
                $avatar_id = get_user_meta($rsvp->user_id, 'voxel:avatar', true);
                if ($avatar_id) {
                    $avatar_url = wp_get_attachment_image_url($avatar_id, 'thumbnail');
                }
                if (!$avatar_url) {
                    $avatar_url = get_avatar_url($rsvp->user_id, array('size' => 100));
                }
            } else {
                $avatar_url = get_avatar_url($rsvp->user_email, array('size' => 100));
            }

            // Parse custom fields
            $custom_fields = array();
            if (!empty($rsvp->custom_fields)) {
                $custom_fields = json_decode($rsvp->custom_fields, true);
                if (!is_array($custom_fields)) {
                    $custom_fields = array();
                }
            }

            $formatted_rsvps[] = array(
                'id' => (int) $rsvp->id,
                'user_name' => $rsvp->user_name,
                'user_email' => $rsvp->user_email,
                'comment' => $rsvp->comment,
                'custom_fields' => $custom_fields,
                'status' => $rsvp->status,
                'avatar_url' => $avatar_url,
                'created_at' => $rsvp->created_at,
                'time_ago' => human_time_diff(strtotime($rsvp->created_at), current_time('timestamp')) . ' ' . __('ago', 'voxel-toolkit'),
            );
        }

        wp_send_json_success(array(
            'rsvps' => $formatted_rsvps,
            'total' => $total,
            'pages' => $pages,
            'current_page' => $page,
        ));
    }

    /**
     * AJAX: Get widget data (count and user status) - cache-proof
     *
     * This endpoint is specifically for loading fresh RSVP data after page load
     * to ensure cached pages still show accurate counts and user status.
     */
    public function ajax_get_widget_data() {
        check_ajax_referer('vt_rsvp_nonce', 'nonce');

        $post_id = absint($_POST['post_id'] ?? $_GET['post_id'] ?? 0);

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'voxel-toolkit')));
        }

        // Get count of approved RSVPs
        $count = $this->get_rsvp_count($post_id, array('approved'));

        // Check if current user has RSVP'd
        $user_rsvp = null;
        $user_email = '';

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $user_email = $user->user_email;
            $user_rsvp = $this->get_user_rsvp($post_id, $user_email);
        }

        $response = array(
            'count' => $count,
            'has_rsvp' => $user_rsvp !== null,
            'rsvp_status' => $user_rsvp ? $user_rsvp->status : null,
            'user_email' => $user_email,
            'is_logged_in' => is_user_logged_in(),
        );

        wp_send_json_success($response);
    }

    /**
     * AJAX: Export RSVPs to CSV (admin only)
     */
    public function ajax_export_csv() {
        // Check nonce via GET parameter
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'vt_rsvp_nonce')) {
            wp_die(__('Security check failed', 'voxel-toolkit'));
        }

        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied', 'voxel-toolkit'));
        }

        $post_id = absint($_GET['post_id'] ?? 0);

        if (!$post_id) {
            wp_die(__('Invalid post ID', 'voxel-toolkit'));
        }

        $statuses = isset($_GET['statuses']) ? array_map('sanitize_text_field', (array) $_GET['statuses']) : array('approved', 'pending', 'rejected');

        // Sanitize statuses
        $valid_statuses = array('pending', 'approved', 'rejected');
        $statuses = array_intersect($statuses, $valid_statuses);
        if (empty($statuses)) {
            $statuses = array('approved', 'pending', 'rejected');
        }

        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $query_args = array_merge(array($post_id), $statuses);

        $rsvps = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d AND status IN ($placeholders) ORDER BY created_at DESC",
            $query_args
        ));

        $post_title = get_the_title($post_id);

        // Collect all unique custom field keys from RSVPs
        $custom_field_keys = array();
        foreach ($rsvps as $rsvp) {
            if (!empty($rsvp->custom_fields)) {
                $fields = json_decode($rsvp->custom_fields, true);
                if (is_array($fields)) {
                    $custom_field_keys = array_merge($custom_field_keys, array_keys($fields));
                }
            }
        }
        $custom_field_keys = array_unique($custom_field_keys);

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rsvps-' . sanitize_file_name($post_title) . '-' . date('Y-m-d-His') . '.csv"');

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // CSV headers
        $headers = array(
            'RSVP ID',
            'Post Title',
            'User Name',
            'User Email',
            'Status',
            'Comment',
            'RSVP Date/Time',
        );

        // Add custom field headers (title case the key)
        foreach ($custom_field_keys as $key) {
            $headers[] = ucwords(str_replace('_', ' ', $key));
        }

        fputcsv($output, $headers);

        // Process each RSVP
        foreach ($rsvps as $rsvp) {
            $row = array(
                $rsvp->id,
                $post_title,
                $rsvp->user_name,
                $rsvp->user_email,
                ucfirst($rsvp->status),
                $rsvp->comment,
                $rsvp->created_at,
            );

            // Add custom field values
            $custom_fields = array();
            if (!empty($rsvp->custom_fields)) {
                $custom_fields = json_decode($rsvp->custom_fields, true);
                if (!is_array($custom_fields)) {
                    $custom_fields = array();
                }
            }

            foreach ($custom_field_keys as $key) {
                $row[] = isset($custom_fields[$key]) ? $custom_fields[$key] : '';
            }

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * Render settings for admin page
     */
    public static function render_settings() {
        $settings = Voxel_Toolkit_Settings::instance();
        $config = $settings->get_function_settings('rsvp_form');
        ?>
        <div class="vt-settings-section">
            <h3><?php _e('RSVP Settings', 'voxel-toolkit'); ?></h3>
            <p class="description"><?php _e('Configure the RSVP feature. Use the RSVP Form widget to add RSVP functionality to your posts, and the Attendee List widget to display and manage RSVPs.', 'voxel-toolkit'); ?></p>
        </div>
        <?php
    }

    /**
     * Register event categories - RSVP as parent label, post types as subcategories
     */
    public function register_event_category($categories) {
        if (!class_exists('\\Voxel\\Post_Type')) {
            return $categories;
        }

        $post_types = array_values(\Voxel\Post_Type::get_voxel_types());
        if (empty($post_types)) {
            return $categories;
        }

        // First post type labeled "RSVP" (acts as parent, clicking shows its events)
        // ALL post types (including first) also appear as "— {PostType}" subcategories
        $first_post_type = $post_types[0];

        // Parent category - uses first post type's key so it has events
        $categories[sprintf('rsvp:%s', $first_post_type->get_key())] = [
            'key' => sprintf('rsvp:%s', $first_post_type->get_key()),
            'label' => 'RSVP',
        ];

        // All post types as subcategories with "— " prefix
        foreach ($post_types as $post_type) {
            $categories[sprintf('rsvp:%s:sub', $post_type->get_key())] = [
                'key' => sprintf('rsvp:%s', $post_type->get_key()),
                'label' => sprintf('— %s', $post_type->get_label()),
            ];
        }

        return $categories;
    }

    /**
     * Register app events - one set per Voxel post type
     */
    public function register_app_events($events) {
        if (!class_exists('\\Voxel\\Events\\Base_Event')) {
            return $events;
        }

        if (!class_exists('\\Voxel\\Post_Type')) {
            return $events;
        }

        // Register events for each Voxel post type
        foreach (\Voxel\Post_Type::get_voxel_types() as $post_type) {
            // RSVP Submitted
            $submitted = new Voxel_Toolkit_RSVP_Submitted_Event($post_type);
            $events[$submitted->get_key()] = $submitted;

            // RSVP Approved
            $approved = new Voxel_Toolkit_RSVP_Approved_Event($post_type);
            $events[$approved->get_key()] = $approved;

            // RSVP Rejected
            $rejected = new Voxel_Toolkit_RSVP_Rejected_Event($post_type);
            $events[$rejected->get_key()] = $rejected;
        }

        return $events;
    }
}

/**
 * RSVP Data Group for Dynamic Tags
 */
if (class_exists('\\Voxel\\Dynamic_Data\\Data_Groups\\Base_Data_Group')) {
    class Voxel_Toolkit_RSVP_Data_Group extends \Voxel\Dynamic_Data\Data_Groups\Base_Data_Group {

        public $rsvp_data;
        public $post_type;
        public $schema_fields = [];
        public $field_properties = []; // Pre-built field properties

        public function __construct($rsvp_data = null, $post_type = null) {
            $this->rsvp_data = $rsvp_data;
            $this->post_type = $post_type;

            // Load schema at construction time
            if ($post_type && class_exists('Voxel_Toolkit_RSVP_Schema')) {
                $this->schema_fields = Voxel_Toolkit_RSVP_Schema::instance()->get_schema($post_type);
            }

            // Build field properties eagerly (not lazily) to avoid Voxel's Tag caching issue
            $this->field_properties = $this->build_field_properties();
        }

        /**
         * Build field properties from schema + actual data
         * Called eagerly in constructor to avoid Voxel's Tag caching
         */
        private function build_field_properties() {
            $properties = [];
            $schema_fields = $this->schema_fields;

            // Get actual data values
            $custom_fields_data = [];
            if (!empty($this->rsvp_data->custom_fields)) {
                $custom_fields_data = json_decode($this->rsvp_data->custom_fields, true);
                if (!is_array($custom_fields_data)) {
                    $custom_fields_data = [];
                }
            }

            // Build from schema (ensures all configured fields appear)
            foreach ($schema_fields as $key => $field_config) {
                // Skip special fields that are handled at top level
                if (in_array($key, ['user_name', 'user_email', 'comment'])) {
                    continue;
                }

                $label = $field_config['label'] ?? ucwords(str_replace(['_', '-'], ' ', $key));
                $value = $custom_fields_data[$key] ?? '';

                $properties['field_' . $key] = \Voxel\Dynamic_Data\Tag::String($label)->render(function() use ($value) {
                    return $value;
                });
            }

            // Include data fields not in schema (backward compatibility)
            foreach ($custom_fields_data as $key => $value) {
                $prop_key = 'field_' . $key;
                if (!isset($properties[$prop_key])) {
                    $label = ucwords(str_replace(['_', '-'], ' ', $key));
                    $properties[$prop_key] = \Voxel\Dynamic_Data\Tag::String($label)->render(function() use ($value) {
                        return $value;
                    });
                }
            }

            return $properties;
        }

        public function get_type(): string {
            // Make type unique per post type to prevent Voxel from caching properties across instances
            return 'vt_rsvp' . ($this->post_type ? '_' . $this->post_type : '');
        }

        protected function properties(): array {
            // Base properties
            $base = [
                'name' => \Voxel\Dynamic_Data\Tag::String('Attendee name')->render(function() {
                    return $this->rsvp_data->user_name ?? '';
                }),
                'email' => \Voxel\Dynamic_Data\Tag::Email('Attendee email')->render(function() {
                    return $this->rsvp_data->user_email ?? '';
                }),
                'comment' => \Voxel\Dynamic_Data\Tag::String('Comment')->render(function() {
                    return $this->rsvp_data->comment ?? '';
                }),
                'status' => \Voxel\Dynamic_Data\Tag::String('Status')->render(function() {
                    return $this->rsvp_data->status ?? '';
                }),
                'created_at' => \Voxel\Dynamic_Data\Tag::Date('Created at')->render(function() {
                    return $this->rsvp_data->created_at ?? '';
                }),
            ];

            // Merge in custom field properties (built in constructor)
            return array_merge($base, $this->field_properties);
        }

        protected function aliases(): array {
            return [
                ':name' => 'name',
                ':email' => 'email',
                ':comment' => 'comment',
                ':status' => 'status',
                ':created_at' => 'created_at',
            ];
        }

        public static function mock($post_type = null): self {
            $mock_data = new \stdClass();
            $mock_data->user_name = 'John Doe';
            $mock_data->user_email = 'john@example.com';
            $mock_data->comment = 'Looking forward to it!';
            $mock_data->status = 'approved';
            $mock_data->created_at = date('Y-m-d H:i:s');

            // Build custom_fields from schema for realistic preview values
            $custom_fields = [];

            if ($post_type && class_exists('Voxel_Toolkit_RSVP_Schema')) {
                $schema_fields = Voxel_Toolkit_RSVP_Schema::instance()->get_schema($post_type);

                foreach ($schema_fields as $key => $config) {
                    // Skip special fields that are top-level
                    if (in_array($key, ['user_name', 'user_email', 'comment'])) {
                        continue;
                    }
                    $custom_fields[$key] = self::get_mock_value_for_field($config);
                }
            }

            $mock_data->custom_fields = json_encode($custom_fields);

            // Constructor will load schema and build field_properties
            return new static($mock_data, $post_type);
        }

        /**
         * Generate appropriate mock value based on field type
         */
        private static function get_mock_value_for_field($config) {
            $type = $config['type'] ?? 'text';
            $key = $config['key'] ?? '';
            $label = $config['label'] ?? '';

            switch ($type) {
                case 'email':
                    return 'sample@example.com';
                case 'number':
                    return '42';
                case 'textarea':
                    return 'Sample text content';
                case 'select':
                    return 'Option 1';
                default:
                    // Try to guess from key/label
                    $key_lower = strtolower($key . ' ' . $label);
                    if (strpos($key_lower, 'phone') !== false) {
                        return '555-1234';
                    }
                    if (strpos($key_lower, 'email') !== false) {
                        return 'sample@example.com';
                    }
                    if (strpos($key_lower, 'company') !== false) {
                        return 'Sample Company';
                    }
                    return 'Sample ' . ($label ?: ucwords(str_replace('_', ' ', $key)));
            }
        }
    }
}

/**
 * Base RSVP Event Class - common functionality for all RSVP events
 */
if (class_exists('\\Voxel\\Events\\Base_Event')) {
    abstract class Voxel_Toolkit_RSVP_Base_Event extends \Voxel\Events\Base_Event {

        public $post_type;
        public $post;
        public $attendee;
        public $rsvp_id;
        public $rsvp_data;

        public function __construct(\Voxel\Post_Type $post_type) {
            $this->post_type = $post_type;
        }

        public function get_category() {
            // All post types use 'rsvp:{post_type}' category
            return sprintf('rsvp:%s', $this->post_type->get_key());
        }

        protected function prepare_base($rsvp_id, $post_id, $user_id = 0) {
            $post = \Voxel\Post::force_get($post_id);
            if (!($post && $post->get_author())) {
                throw new \Exception('Missing post information.');
            }

            $this->post = $post;
            $this->rsvp_id = $rsvp_id;

            // Fetch RSVP data
            $rsvp_instance = Voxel_Toolkit_RSVP::instance();
            $this->rsvp_data = $rsvp_instance->get_rsvp_by_id($rsvp_id);

            if ($user_id) {
                $this->attendee = \Voxel\User::get($user_id);
            } else {
                $this->attendee = null;
            }
        }

        public function set_mock_props() {
            $this->post = \Voxel\Post::mock();
            $this->attendee = \Voxel\User::mock();
            $this->rsvp_id = 1;
            $this->rsvp_data = null;
        }

        public function dynamic_tags(): array {
            $post = $this->post ?: \Voxel\Post::mock();
            $author = $post->get_author() ?: \Voxel\User::mock();

            // Get post type key for schema lookup
            $post_type_key = $this->post_type->get_key();

            $tags = [
                'post' => \Voxel\Dynamic_Data\Group::Post($post),
                'author' => \Voxel\Dynamic_Data\Group::User($author),
            ];

            if ($this->attendee) {
                $tags['attendee'] = \Voxel\Dynamic_Data\Group::User($this->attendee);
            } else {
                $tags['attendee'] = \Voxel\Dynamic_Data\Group::User(\Voxel\User::mock());
            }

            // Add RSVP data group with post type context for schema-based fields
            if (class_exists('Voxel_Toolkit_RSVP_Data_Group')) {
                if ($this->rsvp_data) {
                    $tags['rsvp'] = new Voxel_Toolkit_RSVP_Data_Group($this->rsvp_data, $post_type_key);
                } else {
                    $tags['rsvp'] = Voxel_Toolkit_RSVP_Data_Group::mock($post_type_key);
                }
            }

            return $tags;
        }
    }

    /**
     * RSVP Submitted Event
     */
    class Voxel_Toolkit_RSVP_Submitted_Event extends Voxel_Toolkit_RSVP_Base_Event {

        public function prepare($rsvp_id, $post_id, $user_id = 0) {
            $this->prepare_base($rsvp_id, $post_id, $user_id);
        }

        public function get_key(): string {
            return sprintf('rsvp/%s/rsvp:submitted', $this->post_type->get_key());
        }

        public function get_label(): string {
            return sprintf('%s: RSVP submitted', $this->post_type->get_label());
        }

        public static function notifications(): array {
            return [
                'author' => [
                    'label' => 'Notify post author',
                    'recipient' => function($event) {
                        return $event->post->get_author();
                    },
                    'inapp' => [
                        'enabled' => true,
                        'subject' => 'New RSVP for @post(:title)',
                        'details' => function($event) {
                            return [];
                        },
                        'apply_details' => function($event, $details) {
                            // No additional details needed
                        },
                        'links_to' => function($event) {
                            return $event->post->get_link();
                        },
                        'image_id' => function($event) {
                            return $event->post->get_logo_id();
                        },
                    ],
                    'email' => [
                        'enabled' => false,
                        'subject' => 'New RSVP for @post(:title)',
                        'message' => '@rsvp(:name) has submitted an RSVP for your post "@post(:title)".',
                    ],
                ],
                'admin' => [
                    'label' => 'Notify admin',
                    'recipient' => function($event) {
                        return \Voxel\User::get(1);
                    },
                    'inapp' => [
                        'enabled' => false,
                        'subject' => 'New RSVP for @post(:title)',
                        'details' => function($event) {
                            return [];
                        },
                        'apply_details' => function($event, $details) {
                            // No additional details needed
                        },
                        'links_to' => function($event) {
                            return $event->post->get_link();
                        },
                        'image_id' => function($event) {
                            return $event->post->get_logo_id();
                        },
                    ],
                    'email' => [
                        'enabled' => false,
                        'subject' => 'New RSVP for @post(:title)',
                        'message' => 'A new RSVP has been submitted for "@post(:title)".',
                    ],
                ],
            ];
        }
    }

    /**
     * RSVP Approved Event
     */
    class Voxel_Toolkit_RSVP_Approved_Event extends Voxel_Toolkit_RSVP_Base_Event {

        public function prepare($rsvp_id, $post_id, $user_id = 0) {
            $this->prepare_base($rsvp_id, $post_id, $user_id);
        }

        public function get_key(): string {
            return sprintf('rsvp/%s/rsvp:approved', $this->post_type->get_key());
        }

        public function get_label(): string {
            return sprintf('%s: RSVP approved', $this->post_type->get_label());
        }

        public static function notifications(): array {
            return [
                'attendee' => [
                    'label' => 'Notify attendee',
                    'recipient' => function($event) {
                        return $event->attendee;
                    },
                    'inapp' => [
                        'enabled' => true,
                        'subject' => 'Your RSVP for @post(:title) was approved',
                        'details' => function($event) {
                            return [];
                        },
                        'apply_details' => function($event, $details) {
                            // No additional details needed
                        },
                        'links_to' => function($event) {
                            return $event->post->get_link();
                        },
                        'image_id' => function($event) {
                            return $event->post->get_logo_id();
                        },
                    ],
                    'email' => [
                        'enabled' => false,
                        'subject' => 'Your RSVP for @post(:title) was approved',
                        'message' => 'Your RSVP for "@post(:title)" has been approved. We look forward to seeing you!',
                    ],
                ],
                'admin' => [
                    'label' => 'Notify admin',
                    'recipient' => function($event) {
                        return \Voxel\User::get(1);
                    },
                    'inapp' => [
                        'enabled' => false,
                        'subject' => 'RSVP approved for @post(:title)',
                        'details' => function($event) {
                            return [];
                        },
                        'apply_details' => function($event, $details) {
                            // No additional details needed
                        },
                        'links_to' => function($event) {
                            return $event->post->get_link();
                        },
                        'image_id' => function($event) {
                            return $event->post->get_logo_id();
                        },
                    ],
                    'email' => [
                        'enabled' => false,
                        'subject' => 'RSVP approved for @post(:title)',
                        'message' => 'An RSVP for "@post(:title)" has been approved.',
                    ],
                ],
            ];
        }
    }

    /**
     * RSVP Rejected Event
     */
    class Voxel_Toolkit_RSVP_Rejected_Event extends Voxel_Toolkit_RSVP_Base_Event {

        public function prepare($rsvp_id, $post_id, $user_id = 0) {
            $this->prepare_base($rsvp_id, $post_id, $user_id);
        }

        public function get_key(): string {
            return sprintf('rsvp/%s/rsvp:rejected', $this->post_type->get_key());
        }

        public function get_label(): string {
            return sprintf('%s: RSVP rejected', $this->post_type->get_label());
        }

        public static function notifications(): array {
            return [
                'attendee' => [
                    'label' => 'Notify attendee',
                    'recipient' => function($event) {
                        return $event->attendee;
                    },
                    'inapp' => [
                        'enabled' => true,
                        'subject' => 'Your RSVP for @post(:title) was not approved',
                        'details' => function($event) {
                            return [];
                        },
                        'apply_details' => function($event, $details) {
                            // No additional details needed
                        },
                        'links_to' => function($event) {
                            return $event->post->get_link();
                        },
                        'image_id' => function($event) {
                            return $event->post->get_logo_id();
                        },
                    ],
                    'email' => [
                        'enabled' => false,
                        'subject' => 'Your RSVP for @post(:title) was not approved',
                        'message' => 'Unfortunately, your RSVP for "@post(:title)" was not approved.',
                    ],
                ],
                'admin' => [
                    'label' => 'Notify admin',
                    'recipient' => function($event) {
                        return \Voxel\User::get(1);
                    },
                    'inapp' => [
                        'enabled' => false,
                        'subject' => 'RSVP rejected for @post(:title)',
                        'details' => function($event) {
                            return [];
                        },
                        'apply_details' => function($event, $details) {
                            // No additional details needed
                        },
                        'links_to' => function($event) {
                            return $event->post->get_link();
                        },
                        'image_id' => function($event) {
                            return $event->post->get_logo_id();
                        },
                    ],
                    'email' => [
                        'enabled' => false,
                        'subject' => 'RSVP rejected for @post(:title)',
                        'message' => 'An RSVP for "@post(:title)" has been rejected.',
                    ],
                ],
            ];
        }
    }
}
