<?php
/**
 * Online Status Feature
 *
 * Track and display user online/offline status indicators.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Online_Status {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Settings instance
     */
    private $settings;

    /**
     * Function settings
     */
    private $function_settings = array();

    /**
     * Default timeout in minutes
     */
    private $timeout_minutes = 3;

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
        $this->settings = Voxel_Toolkit_Settings::instance();
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * Load settings
     */
    private function load_settings() {
        $this->function_settings = $this->settings->get_function_settings('online_status', array(
            'enabled' => true,
            'show_in_dashboard' => true,
            'show_in_inbox' => true,
            'show_in_messenger' => true,
            'show_in_admin' => true,
            'timeout_minutes' => 3,
        ));

        $this->timeout_minutes = isset($this->function_settings['timeout_minutes'])
            ? intval($this->function_settings['timeout_minutes'])
            : 3;

        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }

    /**
     * Handle settings update
     */
    public function on_settings_updated($new_value, $old_value) {
        $this->load_settings();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Update activity on page load for logged-in users
        add_action('init', array($this, 'update_last_activity'), 20);

        // AJAX endpoint for heartbeat
        add_action('wp_ajax_vt_online_heartbeat', array($this, 'ajax_heartbeat'));

        // AJAX endpoint to check user status
        add_action('wp_ajax_vt_get_online_status', array($this, 'ajax_get_online_status'));

        // AJAX endpoint to get multiple users' status (for messenger)
        add_action('wp_ajax_vt_get_users_online_status', array($this, 'ajax_get_users_online_status'));

        // AJAX endpoint to get targets' online status (posts/users for messenger)
        add_action('wp_ajax_vt_get_targets_online_status', array($this, 'ajax_get_targets_online_status'));

        // AJAX endpoint to get online status by display names (for Voxel inbox)
        add_action('wp_ajax_vt_get_online_status_by_names', array($this, 'ajax_get_online_status_by_names'));

        // Enqueue scripts on frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));

        // Register visibility rules
        add_filter('voxel/dynamic-data/visibility-rules', array($this, 'register_visibility_rules'));
    }

    /**
     * Register visibility rules with Voxel
     */
    public function register_visibility_rules($rules) {
        if (class_exists('\Voxel\Dynamic_Data\Visibility_Rules\Base_Visibility_Rule')) {
            $rules['user:is_online'] = 'Voxel_Toolkit_User_Is_Online_Rule';
            $rules['author:is_online'] = 'Voxel_Toolkit_Author_Is_Online_Rule';
        }
        return $rules;
    }

    /**
     * Update last activity timestamp
     */
    public function update_last_activity() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $last_activity = get_user_meta($user_id, 'vt_last_activity', true);

            // Only update if more than 30 seconds have passed (reduce DB writes)
            if (empty($last_activity) || (time() - intval($last_activity)) > 30) {
                update_user_meta($user_id, 'vt_last_activity', time());
            }
        }
    }

    /**
     * AJAX heartbeat handler
     */
    public function ajax_heartbeat() {
        check_ajax_referer('vt_online_status_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        update_user_meta(get_current_user_id(), 'vt_last_activity', time());
        wp_send_json_success(array('timestamp' => time()));
    }

    /**
     * AJAX get single user online status
     */
    public function ajax_get_online_status() {
        check_ajax_referer('vt_online_status_nonce', 'nonce');

        // Only logged-in users can check status
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (empty($user_id)) {
            wp_send_json_error(array('message' => 'Invalid user ID'));
        }

        wp_send_json_success(array(
            'user_id' => $user_id,
            'is_online' => $this->is_user_online($user_id),
            'last_seen' => $this->get_last_seen($user_id),
        ));
    }

    /**
     * AJAX get multiple users' online status
     */
    public function ajax_get_users_online_status() {
        check_ajax_referer('vt_online_status_nonce', 'nonce');

        // Only logged-in users can check status
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_ids = isset($_POST['user_ids']) ? array_map('intval', (array) $_POST['user_ids']) : array();

        if (empty($user_ids)) {
            wp_send_json_success(array('users' => array()));
        }

        $users = array();
        foreach ($user_ids as $user_id) {
            $users[$user_id] = array(
                'is_online' => $this->is_user_online($user_id),
                'last_seen' => $this->get_last_seen($user_id),
            );
        }

        wp_send_json_success(array('users' => $users));
    }

    /**
     * AJAX get online status for targets (posts/users) - for messenger widget
     */
    public function ajax_get_targets_online_status() {
        check_ajax_referer('vt_online_status_nonce', 'nonce');

        // Only logged-in users can check status
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $targets = isset($_POST['targets']) ? (array) $_POST['targets'] : array();

        if (empty($targets)) {
            wp_send_json_success(array('statuses' => array()));
        }

        $statuses = array();
        foreach ($targets as $target) {
            // Target format: "type:id" (e.g., "user:123" or "post:456")
            $parts = explode(':', sanitize_text_field($target));
            if (count($parts) !== 2) {
                continue;
            }

            $type = $parts[0];
            $id = intval($parts[1]);

            if ($id <= 0) {
                continue;
            }

            $user_id = 0;

            if ($type === 'user') {
                $user_id = $id;
            } elseif ($type === 'post') {
                // Get the post author
                $post = get_post($id);
                if ($post && $post->post_author) {
                    $user_id = intval($post->post_author);
                }
            }

            if ($user_id > 0) {
                $statuses[$target] = array(
                    'is_online' => $this->is_user_online($user_id),
                    'last_seen' => $this->get_last_seen($user_id),
                    'user_id' => $user_id,
                );
            }
        }

        wp_send_json_success(array('statuses' => $statuses));
    }

    /**
     * AJAX get online status by display names (for Voxel inbox where we can't get user IDs)
     */
    public function ajax_get_online_status_by_names() {
        check_ajax_referer('vt_online_status_nonce', 'nonce');

        // Only logged-in users can check status
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $names = isset($_POST['names']) ? array_map('sanitize_text_field', (array) $_POST['names']) : array();

        if (empty($names)) {
            wp_send_json_success(array('statuses' => array()));
        }

        $statuses = array();
        foreach ($names as $name) {
            if (empty($name)) {
                continue;
            }

            // Try to find user by display name
            $user = get_user_by('login', $name);
            if (!$user) {
                // Try display name
                $users = get_users(array(
                    'meta_key' => 'nickname',
                    'meta_value' => $name,
                    'number' => 1,
                ));
                if (!empty($users)) {
                    $user = $users[0];
                }
            }
            if (!$user) {
                // Try by display_name field
                global $wpdb;
                $user_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->users} WHERE display_name = %s LIMIT 1",
                    $name
                ));
                if ($user_id) {
                    $user = get_user_by('id', $user_id);
                }
            }

            // Also check for Voxel posts (listings) by title
            if (!$user) {
                $posts = get_posts(array(
                    'title' => $name,
                    'post_type' => 'any',
                    'post_status' => 'publish',
                    'numberposts' => 1,
                ));
                if (!empty($posts)) {
                    $post = $posts[0];
                    if ($post->post_author) {
                        $user = get_user_by('id', $post->post_author);
                    }
                }
            }

            if ($user) {
                $statuses[$name] = array(
                    'is_online' => $this->is_user_online($user->ID),
                    'last_seen' => $this->get_last_seen($user->ID),
                    'user_id' => $user->ID,
                );
            }
        }

        wp_send_json_success(array('statuses' => $statuses));
    }

    /**
     * Check if a user is online
     *
     * @param int $user_id User ID to check
     * @return bool True if user is online
     */
    public function is_user_online($user_id) {
        // Only logged-in users can see online status
        if (!is_user_logged_in()) {
            return false;
        }

        $last_activity = get_user_meta($user_id, 'vt_last_activity', true);

        if (empty($last_activity)) {
            return false;
        }

        $timeout_seconds = $this->timeout_minutes * 60;
        return (time() - intval($last_activity)) < $timeout_seconds;
    }

    /**
     * Get user's last seen timestamp
     *
     * @param int $user_id User ID
     * @return int|null Unix timestamp or null if never seen
     */
    public function get_last_seen($user_id) {
        $last_activity = get_user_meta($user_id, 'vt_last_activity', true);
        return !empty($last_activity) ? intval($last_activity) : null;
    }

    /**
     * Get formatted last seen string
     *
     * @param int $user_id User ID
     * @return string Human-readable last seen string
     */
    public function get_last_seen_formatted($user_id) {
        // Only logged-in users can see this
        if (!is_user_logged_in()) {
            return '';
        }

        $last_seen = $this->get_last_seen($user_id);

        if (empty($last_seen)) {
            return __('Never', 'voxel-toolkit');
        }

        return human_time_diff($last_seen, time()) . ' ' . __('ago', 'voxel-toolkit');
    }

    /**
     * Check if a specific display location is enabled
     *
     * @param string $location Location key (dashboard, inbox, messenger, admin)
     * @return bool
     */
    public function is_location_enabled($location) {
        $key = 'show_in_' . $location;
        return isset($this->function_settings[$key]) ? (bool) $this->function_settings[$key] : true;
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Only for logged-in users
        if (!is_user_logged_in()) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'vt-online-status',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/online-status.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        // Enqueue JS
        wp_enqueue_script(
            'vt-online-status',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/online-status.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Localize script with locations config
        wp_localize_script('vt-online-status', 'vtOnlineStatus', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_online_status_nonce'),
            'enabled' => true,
            'heartbeatInterval' => 60000, // 60 seconds in milliseconds
            'locations' => array(
                'dashboard' => $this->is_location_enabled('dashboard'),
                'inbox' => $this->is_location_enabled('inbox'),
                'messenger' => $this->is_location_enabled('messenger'),
            ),
        ));
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        // Only on users page
        if ($hook !== 'users.php') {
            return;
        }

        if (!$this->is_location_enabled('admin')) {
            return;
        }

        wp_enqueue_style(
            'vt-online-status-admin',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/online-status.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
    }

    /**
     * Get online status HTML indicator
     *
     * @param int $user_id User ID
     * @param string $size Size class (small, medium, large)
     * @return string HTML for the indicator
     */
    public function get_status_indicator_html($user_id, $size = 'small') {
        // Only logged-in users can see this
        if (!is_user_logged_in()) {
            return '';
        }

        $is_online = $this->is_user_online($user_id);
        $status_class = $is_online ? 'vt-online' : 'vt-offline';
        $status_text = $is_online ? __('Online', 'voxel-toolkit') : __('Offline', 'voxel-toolkit');

        return sprintf(
            '<span class="vt-online-indicator vt-online-indicator--%s %s" title="%s"></span>',
            esc_attr($size),
            esc_attr($status_class),
            esc_attr($status_text)
        );
    }

    /**
     * Get online status badge HTML (for admin)
     *
     * @param int $user_id User ID
     * @return string HTML for the badge
     */
    public function get_status_badge_html($user_id) {
        $is_online = $this->is_user_online($user_id);
        $status_class = $is_online ? 'vt-status-online' : 'vt-status-offline';
        $status_text = $is_online ? __('Online', 'voxel-toolkit') : __('Offline', 'voxel-toolkit');

        return sprintf(
            '<span class="vt-online-status-badge %s">%s</span>',
            esc_attr($status_class),
            esc_html($status_text)
        );
    }

    /**
     * Get timeout setting
     *
     * @return int Timeout in minutes
     */
    public function get_timeout_minutes() {
        return $this->timeout_minutes;
    }
}

/**
 * User Is Online Visibility Rule
 *
 * Checks if the current logged-in user is online
 */
if (class_exists('\Voxel\Dynamic_Data\Visibility_Rules\Base_Visibility_Rule')) {

    class Voxel_Toolkit_User_Is_Online_Rule extends \Voxel\Dynamic_Data\Visibility_Rules\Base_Visibility_Rule {

        public function get_type(): string {
            return 'user:is_online';
        }

        public function get_label(): string {
            return _x('User is online', 'visibility rules', 'voxel-toolkit');
        }

        public function evaluate(): bool {
            if (!is_user_logged_in()) {
                return false;
            }

            $user_id = get_current_user_id();
            return Voxel_Toolkit_Online_Status::instance()->is_user_online($user_id);
        }
    }

    /**
     * Author Is Online Visibility Rule
     *
     * Checks if the current post's author is online
     */
    class Voxel_Toolkit_Author_Is_Online_Rule extends \Voxel\Dynamic_Data\Visibility_Rules\Base_Visibility_Rule {

        public function get_type(): string {
            return 'author:is_online';
        }

        public function get_label(): string {
            return _x('Author is online', 'visibility rules', 'voxel-toolkit');
        }

        public function evaluate(): bool {
            if (!is_user_logged_in()) {
                return false;
            }

            $post = \Voxel\get_current_post();
            if (!$post) {
                return false;
            }

            $author_id = $post->get_author_id();
            if (!$author_id) {
                return false;
            }

            return Voxel_Toolkit_Online_Status::instance()->is_user_online($author_id);
        }
    }
}
