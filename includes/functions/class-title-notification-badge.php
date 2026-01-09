<?php
/**
 * Title Notification Badge
 *
 * Adds unread notification/message count to browser tab title
 * and updates in real-time via polling.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Title_Notification_Badge {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Settings
     */
    private $settings = array();

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
        $this->settings = $this->get_settings();
        $this->init_hooks();
    }

    /**
     * Get settings with defaults
     */
    public function get_settings() {
        $settings = Voxel_Toolkit_Settings::instance()->get_function_settings('title_notification_badge');

        return wp_parse_args($settings, array(
            'poll_interval' => 15, // seconds
            'include_notifications' => true,
            'include_messages' => true,
            'flash_on_new' => true,
            'flash_text' => __('New notification!', 'voxel-toolkit'),
        ));
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enqueue scripts for logged-in users only
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX endpoint for getting unread counts
        add_action('wp_ajax_vt_get_unread_counts', array($this, 'ajax_get_unread_counts'));
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        // Only for logged-in users
        if (!is_user_logged_in()) {
            return;
        }

        // Register and enqueue the script
        wp_register_script(
            'vt-title-notification-badge',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/title-notification-badge.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Localize script with settings
        wp_localize_script('vt-title-notification-badge', 'vtTitleBadge', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_title_badge'),
            'pollInterval' => intval($this->settings['poll_interval']) * 1000, // Convert to milliseconds
            'includeNotifications' => (bool) $this->settings['include_notifications'],
            'includeMessages' => (bool) $this->settings['include_messages'],
            'flashOnNew' => (bool) $this->settings['flash_on_new'],
            'flashText' => $this->settings['flash_text'],
            'originalTitle' => '', // Will be set by JS
        ));

        wp_enqueue_script('vt-title-notification-badge');
    }

    /**
     * AJAX handler to get unread counts
     */
    public function ajax_get_unread_counts() {
        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        // Verify nonce
        if (!check_ajax_referer('vt_title_badge', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
        }

        $user_id = get_current_user_id();
        $counts = array(
            'notifications' => 0,
            'messages' => 0,
            'total' => 0,
        );

        // Get notifications count
        if ($this->settings['include_notifications']) {
            $notifications_data = get_user_meta($user_id, 'voxel:notifications', true);
            if (!empty($notifications_data)) {
                $data = json_decode($notifications_data, true);
                if (is_array($data) && isset($data['unread'])) {
                    $counts['notifications'] = intval($data['unread']);
                }
            }
        }

        // Get messages count
        if ($this->settings['include_messages']) {
            $dms_data = get_user_meta($user_id, 'voxel:dms', true);
            if (!empty($dms_data)) {
                $data = json_decode($dms_data, true);
                if (is_array($data) && isset($data['unread']) && $data['unread'] !== false) {
                    $counts['messages'] = intval($data['unread']);
                }
            }
        }

        // Calculate total
        $counts['total'] = $counts['notifications'] + $counts['messages'];

        wp_send_json_success($counts);
    }

    /**
     * Deinitialize - called when function is disabled
     */
    public function deinit() {
        // Nothing to clean up
    }
}
