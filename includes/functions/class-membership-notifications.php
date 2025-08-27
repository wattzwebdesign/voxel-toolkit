<?php
/**
 * Membership Notifications Function
 * 
 * Sends email notifications to users based on membership expiration dates
 * Integrates with Voxel theme membership system
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Membership_Notifications {
    
    private $settings;
    private $notifications_config = array();
    
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
        $function_settings = $this->settings->get_function_settings('membership_notifications', array(
            'enabled' => false,
            'notifications' => array()
        ));
        
        $this->notifications_config = isset($function_settings['notifications']) ? $function_settings['notifications'] : array();
        
        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        if (!$this->settings->is_function_enabled('membership_notifications')) {
            return;
        }
        
        // Register cron event
        if (!wp_next_scheduled('voxel_toolkit_membership_notifications_hourly')) {
            wp_schedule_event(time(), 'hourly', 'voxel_toolkit_membership_notifications_hourly');
        }
        
        // Hook the cron function
        add_action('voxel_toolkit_membership_notifications_hourly', array($this, 'check_and_run'));
        
        // Handle AJAX requests
        add_action('wp_ajax_voxel_toolkit_send_test_notification', array($this, 'ajax_send_test_notification'));
        add_action('wp_ajax_voxel_toolkit_manual_notifications', array($this, 'ajax_manual_notifications'));
    }
    
    /**
     * Handle settings updates
     */
    public function on_settings_updated($new_settings, $old_settings) {
        $this->load_settings();
        $this->init_hooks();
    }
    
    /**
     * Check time and run notifications if needed
     */
    public function check_and_run() {
        $now = new DateTime('now', new DateTimeZone('America/New_York'));
        if ($now->format('H') === '06') {
            $this->run_notifications();
        }
    }
    
    /**
     * Run notifications check
     */
    public function run_notifications() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'voxel:plan'"
        );
        
        if (empty($results) || empty($this->notifications_config)) {
            return;
        }
        
        foreach ($results as $row) {
            $this->process_user_notification($row);
        }
    }
    
    /**
     * Process notification for a single user
     */
    private function process_user_notification($row) {
        $data = json_decode($row->meta_value, true);
        if (!$data || empty($data['current_period_end'])) {
            return;
        }
        
        $exp = (int) $data['current_period_end'];
        if ($exp <= time()) {
            return; // Already expired
        }
        
        $seconds_left = $exp - time();
        
        $user = get_user_by('id', $row->user_id);
        if (!$user || empty($user->user_email)) {
            return;
        }
        
        $sent_json = get_user_meta($row->user_id, 'voxel_toolkit_membership_notifications_sent', true);
        $sent = json_decode($sent_json, true) ?? array();
        $updated_sent = false;
        
        foreach ($this->notifications_config as $notif) {
            if (!$this->is_valid_notification($notif)) {
                continue;
            }
            
            $threshold_seconds = ($notif['unit'] === 'days') ? $notif['value'] * 86400 : $notif['value'] * 3600;
            $key = $notif['value'] . '-' . $notif['unit'];
            
            if (in_array($key, $sent, true)) {
                continue;
            }
            
            if ($seconds_left <= $threshold_seconds) {
                $this->send_notification($user->user_email, $notif, $data, $exp, $seconds_left);
                $sent[] = $key;
                $updated_sent = true;
            }
        }
        
        if ($updated_sent) {
            update_user_meta($row->user_id, 'voxel_toolkit_membership_notifications_sent', json_encode($sent));
        }
    }
    
    /**
     * Check if notification is valid
     */
    private function is_valid_notification($notif) {
        return !empty($notif['value']) && 
               !empty($notif['unit']) && 
               !empty($notif['subject']) && 
               !empty($notif['body']);
    }
    
    /**
     * Send notification email
     */
    private function send_notification($email, $notif, $data, $exp, $seconds_left) {
        $plan_name = $data['plan'] ?? 'Unknown';
        $amount_raw = $data['amount'] ?? 0;
        $amount = number_format($amount_raw / 100, 2);
        $currency = strtoupper($data['currency'] ?? 'USD');
        $expiration_date = date_i18n(get_option('date_format'), $exp);
        $remaining_days = ceil($seconds_left / 86400);
        
        $subject = str_replace(
            array('{expiration_date}', '{amount}', '{currency}', '{plan_name}', '{remaining_days}'),
            array($expiration_date, $amount, $currency, $plan_name, $remaining_days),
            $notif['subject']
        );
        
        $body = str_replace(
            array('{expiration_date}', '{amount}', '{currency}', '{plan_name}', '{remaining_days}'),
            array($expiration_date, $amount, $currency, $plan_name, $remaining_days),
            $notif['body']
        );
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($email, $subject, $body, $headers);
    }
    
    
    /**
     * Handle AJAX test notification
     */
    public function ajax_send_test_notification() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'voxel_toolkit_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(__('Security check failed.', 'voxel-toolkit'));
        }
        
        $test_email = sanitize_email($_POST['test_email']);
        $unit = sanitize_text_field($_POST['unit']);
        $value = intval($_POST['value']);
        $subject = sanitize_text_field($_POST['subject']);
        $body = wp_kses_post($_POST['body']);
        
        if (empty($test_email) || empty($unit) || empty($value) || empty($subject) || empty($body)) {
            wp_send_json_error(__('Invalid data provided.', 'voxel-toolkit'));
        }
        
        // Simulate data for test
        $threshold_seconds = ($unit === 'days') ? $value * 86400 : $value * 3600;
        $exp = time() + $threshold_seconds;
        $seconds_left = $exp - time();
        
        $plan_name = 'Sample Plan';
        $amount = '25.00';
        $currency = 'USD';
        $expiration_date = date_i18n(get_option('date_format'), $exp);
        $remaining_days = ceil($seconds_left / 86400);
        
        $subject = str_replace(
            array('{expiration_date}', '{amount}', '{currency}', '{plan_name}', '{remaining_days}'),
            array($expiration_date, $amount, $currency, $plan_name, $remaining_days),
            $subject
        );
        
        $body = str_replace(
            array('{expiration_date}', '{amount}', '{currency}', '{plan_name}', '{remaining_days}'),
            array($expiration_date, $amount, $currency, $plan_name, $remaining_days),
            $body
        );
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($test_email, $subject, $body, $headers);
        
        if ($sent) {
            wp_send_json_success(__('Test email sent successfully.', 'voxel-toolkit'));
        } else {
            wp_send_json_error(__('Failed to send test email.', 'voxel-toolkit'));
        }
    }
    
    /**
     * Handle AJAX manual notifications
     */
    public function ajax_manual_notifications() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'voxel_toolkit_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(__('Security check failed.', 'voxel-toolkit'));
        }
        
        $this->run_notifications();
        wp_send_json_success(__('Manual notifications sent.', 'voxel-toolkit'));
    }
    
    /**
     * Deactivate cron when function is disabled
     */
    public static function deactivate_cron() {
        wp_clear_scheduled_hook('voxel_toolkit_membership_notifications_hourly');
    }
}