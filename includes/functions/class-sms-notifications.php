<?php
/**
 * SMS Notifications Function
 *
 * Sends SMS notifications via Twilio, Vonage, or MessageBird when Voxel app events occur.
 * Extends Voxel Base_Controller for event handling.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler class - defined separately so it works even when Voxel isn't fully loaded
 * This handles the App Events page SMS toggle functionality
 */
if (!class_exists('Voxel_Toolkit_SMS_Ajax_Handler')) {
    class Voxel_Toolkit_SMS_Ajax_Handler {

        private static $instance = null;

        public static function instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function __construct() {
            add_action('wp_ajax_vt_save_sms_event_settings', array($this, 'ajax_save_event_settings'));
            // Note: vt_send_test_sms is handled by Voxel_Toolkit_Functions::ajax_send_test_sms()
            // which works without requiring Voxel Base_Controller to be loaded
            add_action('wp_ajax_vt_get_sms_event_settings', array($this, 'ajax_get_event_settings'));
        }

        /**
         * AJAX: Save SMS event settings
         */
        public function ajax_save_event_settings() {
            check_ajax_referer('vt_sms_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('Permission denied', 'voxel-toolkit')));
            }

            $event_key = isset($_POST['event_key']) ? sanitize_text_field($_POST['event_key']) : '';
            $destination = isset($_POST['destination']) ? sanitize_text_field($_POST['destination']) : '';
            $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
            $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

            if (empty($event_key) || empty($destination)) {
                wp_send_json_error(array('message' => __('Invalid parameters', 'voxel-toolkit')));
            }

            // Get current settings
            $settings = Voxel_Toolkit_Settings::instance();
            $current_settings = $settings->get_function_settings('sms_notifications', array());

            // Initialize events array if needed
            if (!isset($current_settings['events'])) {
                $current_settings['events'] = array();
            }

            if (!isset($current_settings['events'][$event_key])) {
                $current_settings['events'][$event_key] = array();
            }

            // Update settings for this event/destination
            $current_settings['events'][$event_key][$destination] = array(
                'enabled' => $enabled,
                'message' => $message,
            );

            // Save settings
            $settings->update_function_settings('sms_notifications', $current_settings);

            wp_send_json_success(array('message' => __('SMS settings saved', 'voxel-toolkit')));
        }

        /**
         * AJAX: Get SMS event settings
         */
        public function ajax_get_event_settings() {
            check_ajax_referer('vt_sms_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('Permission denied', 'voxel-toolkit')));
            }

            $settings = Voxel_Toolkit_Settings::instance();
            $function_settings = $settings->get_function_settings('sms_notifications', array());
            $events = isset($function_settings['events']) ? $function_settings['events'] : array();
            $enabled = isset($function_settings['enabled']) ? $function_settings['enabled'] : false;

            wp_send_json_success(array(
                'events' => $events,
                'enabled' => $enabled,
                'phone_configured' => !empty($function_settings['phone_field']),
                'provider' => isset($function_settings['provider']) ? $function_settings['provider'] : 'twilio',
            ));
        }

    }

    // Initialize AJAX handler immediately
    Voxel_Toolkit_SMS_Ajax_Handler::instance();
}

// Check if Voxel Base_Controller exists before defining main class
// This prevents fatal errors during WP-CLI operations (cPanel, staging/live pushes, etc.)
if (!class_exists('\Voxel\Controllers\Base_Controller')) {
    return;
}

class Voxel_Toolkit_SMS_Notifications extends \Voxel\Controllers\Base_Controller {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Settings instance
     */
    private $settings;

    /**
     * Function enabled status
     */
    private $enabled = false;

    /**
     * Function settings
     */
    private $function_settings = array();

    /**
     * Intl Phone instance
     */
    private $intl_phone = null;

    /**
     * Get singleton instance
     *
     * @return Voxel_Toolkit_SMS_Notifications|null
     */
    public static function instance() {
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Set singleton instance
        self::$instance = $this;

        $this->settings = Voxel_Toolkit_Settings::instance();
        $this->load_settings();

        if ($this->enabled) {
            parent::__construct();

            // Initialize international phone input enhancement
            $this->init_intl_phone();
        }

        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }

    /**
     * Initialize international phone input enhancement
     */
    private function init_intl_phone() {
        // Skip if Advanced Phone Input is enabled (it replaces this functionality)
        if (class_exists('Voxel_Toolkit_Settings')) {
            $settings = Voxel_Toolkit_Settings::instance();
            if ($settings->is_function_enabled('advanced_phone_input')) {
                return;
            }
        }

        // Load the intl phone class
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/class-intl-phone.php';

        // Initialize it
        $this->intl_phone = new Voxel_Toolkit_Intl_Phone();
    }

    /**
     * Load settings
     */
    private function load_settings() {
        $this->function_settings = $this->settings->get_function_settings('sms_notifications', array(
            'enabled' => false,
            'provider' => 'twilio',
            'phone_number' => '',
            'events' => array(),
        ));

        $this->enabled = isset($this->function_settings['enabled']) ? $this->function_settings['enabled'] : false;
    }

    /**
     * Handle settings updates
     */
    public function on_settings_updated($old_settings, $new_settings) {
        $this->load_settings();

        // Re-initialize if we're enabled
        if ($this->enabled) {
            parent::__construct();
        }
    }

    /**
     * Hook registration for all Voxel events
     */
    protected function hooks() {
        $events = \Voxel\Events\Base_Event::get_all();

        foreach ($events as $event) {
            $hook_name = sprintf('voxel/app-events/%s', $event->get_key());
            $this->on($hook_name, '@send_sms_notification', 10, 1);
        }
    }

    /**
     * Send SMS notification when event fires
     *
     * @param \Voxel\Events\Base_Event $event The event that triggered
     */
    public function send_sms_notification($event) {
        if (!$this->enabled) {
            return;
        }

        $event_key = $event->get_key();
        $notifications = $event->get_notifications();

        // Check each notification destination (customer, vendor, admin)
        foreach ($notifications as $destination => $notification) {
            // Get SMS config for this event/destination
            $sms_config = $this->get_sms_config($event_key, $destination);

            if (!$sms_config['enabled']) {
                continue;
            }

            // Get recipient user based on destination
            $recipient_user = $this->get_recipient_user($event, $destination);

            if (!$recipient_user) {
                continue;
            }

            // Get recipient phone number from their profile
            $phone = $this->get_recipient_phone_from_profile($recipient_user->ID);

            if (empty($phone)) {
                // Silently skip if no phone number configured for this user
                continue;
            }

            // Render message with dynamic tags
            $message = $this->render_message($sms_config['message'], $event);

            if (empty($message)) {
                continue;
            }

            // Send SMS
            $this->send_sms($phone, $message);
        }
    }

    /**
     * Get SMS configuration for specific event and destination
     *
     * @param string $event_key Event key
     * @param string $destination Notification destination (customer, vendor, admin)
     * @return array SMS config
     */
    private function get_sms_config($event_key, $destination) {
        $default = array(
            'enabled' => false,
            'message' => '',
        );

        $events = isset($this->function_settings['events']) ? $this->function_settings['events'] : array();

        if (!isset($events[$event_key]) || !isset($events[$event_key][$destination])) {
            return $default;
        }

        return wp_parse_args($events[$event_key][$destination], $default);
    }

    /**
     * Get recipient user based on destination type
     *
     * @param \Voxel\Events\Base_Event $event Event instance
     * @param string $destination Notification destination (customer, vendor, admin)
     * @return WP_User|null User object or null
     */
    private function get_recipient_user($event, $destination) {
        // Try to get recipient from notification config
        $notifications = $event::notifications();

        if (!isset($notifications[$destination])) {
            return null;
        }

        $notification = $notifications[$destination];

        // Check if notification has a recipient callback
        if (isset($notification['recipient']) && is_callable($notification['recipient'])) {
            $recipient = call_user_func($notification['recipient'], $event);
            if ($recipient instanceof \WP_User) {
                return $recipient;
            }
            if (is_numeric($recipient)) {
                return get_user_by('id', $recipient);
            }
        }

        // Fallback: Try to extract user from event based on common destination names
        switch ($destination) {
            case 'customer':
            case 'user':
            case 'author':
                // Try to get customer/user from event
                if (method_exists($event, 'get_customer')) {
                    $customer = $event->get_customer();
                    if ($customer instanceof \WP_User) {
                        return $customer;
                    }
                }
                if (method_exists($event, 'get_user')) {
                    $user = $event->get_user();
                    if ($user instanceof \WP_User) {
                        return $user;
                    }
                }
                // Try to get from post author
                if (method_exists($event, 'get_post')) {
                    $post = $event->get_post();
                    if ($post && $post->post_author) {
                        return get_user_by('id', $post->post_author);
                    }
                }
                break;

            case 'vendor':
            case 'seller':
            case 'host':
                // Try to get vendor from event
                if (method_exists($event, 'get_vendor')) {
                    $vendor = $event->get_vendor();
                    if ($vendor instanceof \WP_User) {
                        return $vendor;
                    }
                }
                if (method_exists($event, 'get_seller')) {
                    $seller = $event->get_seller();
                    if ($seller instanceof \WP_User) {
                        return $seller;
                    }
                }
                // Try to get from post author (for listing events)
                if (method_exists($event, 'get_post')) {
                    $post = $event->get_post();
                    if ($post && $post->post_author) {
                        return get_user_by('id', $post->post_author);
                    }
                }
                break;

            case 'admin':
                // Get admin user - use first administrator
                $admins = get_users(array('role' => 'administrator', 'number' => 1));
                if (!empty($admins)) {
                    return $admins[0];
                }
                break;
        }

        return null;
    }

    /**
     * Get recipient phone number from their profile
     *
     * @param int $user_id User ID
     * @return string Phone number or empty string
     */
    private function get_recipient_phone_from_profile($user_id) {
        if (!$user_id) {
            return '';
        }

        // Get the phone field key from settings
        $phone_field = isset($this->function_settings['phone_field']) ? $this->function_settings['phone_field'] : '';

        if (empty($phone_field)) {
            return '';
        }

        // Get user's profile post ID
        $profile_id = get_user_meta($user_id, 'voxel:profile_id', true);

        if (!$profile_id) {
            return '';
        }

        // Get phone field value from profile post meta
        // Voxel stores field values in the post's voxel_fields meta
        $voxel_fields = get_post_meta($profile_id, 'voxel:fields', true);

        if (!is_array($voxel_fields)) {
            // Try JSON decode if it's a string
            if (is_string($voxel_fields)) {
                $voxel_fields = json_decode($voxel_fields, true);
            }
        }

        $phone = '';

        if (!is_array($voxel_fields) || !isset($voxel_fields[$phone_field])) {
            // Fallback: try direct meta key
            $phone = get_post_meta($profile_id, $phone_field, true);
        } else {
            $phone = $voxel_fields[$phone_field];

            // Handle if phone is stored as array
            if (is_array($phone)) {
                $phone = isset($phone['value']) ? $phone['value'] : (isset($phone['number']) ? $phone['number'] : '');
            }
        }

        if (empty($phone)) {
            return '';
        }

        // Check for stored country code from intl-phone-input feature
        $stored_country_code = get_post_meta($profile_id, $phone_field . '_country_code', true);

        if (!empty($stored_country_code)) {
            // Use the stored country code (from intl-tel-input)
            return $this->format_phone_with_country($phone, $stored_country_code);
        }

        // Fall back to global country code setting
        return $this->normalize_phone($phone);
    }

    /**
     * Format phone number with a specific country code
     *
     * @param string $phone Phone number (local format)
     * @param string $country_code Country dial code (without +)
     * @return string E.164 formatted phone number
     */
    private function format_phone_with_country($phone, $country_code) {
        if (empty($phone)) {
            return '';
        }

        // Clean phone number - remove non-digits
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading 0 if present (common in local formats)
        if (strpos($phone, '0') === 0) {
            $phone = substr($phone, 1);
        }

        // Ensure country code doesn't have +
        $country_code = preg_replace('/[^0-9]/', '', $country_code);

        return '+' . $country_code . $phone;
    }

    /**
     * Normalize phone number to E.164 format
     *
     * @param string $phone Phone number
     * @return string Normalized phone number
     */
    private function normalize_phone($phone) {
        if (empty($phone)) {
            return '';
        }

        // Remove all non-numeric characters except leading +
        $phone = trim($phone);
        $has_plus = strpos($phone, '+') === 0;
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If phone already has + prefix, it's already international format
        if ($has_plus) {
            return '+' . $phone;
        }

        // Get default country code from settings
        $country_code = isset($this->function_settings['country_code']) ? $this->function_settings['country_code'] : '';

        // Clean country code (remove + if present, we'll add it)
        $country_code = preg_replace('/[^0-9]/', '', $country_code);

        // If we have a country code, always prepend it
        // (local numbers stored in Voxel don't include country code)
        if (!empty($country_code)) {
            // Remove leading 0 if present (common in local formats like UK: 07xxx -> 7xxx)
            if (strpos($phone, '0') === 0) {
                $phone = substr($phone, 1);
            }
            $phone = $country_code . $phone;
        }

        return '+' . $phone;
    }

    /**
     * Render message with Voxel dynamic tags
     *
     * @param string $template Message template
     * @param \Voxel\Events\Base_Event $event Event instance
     * @return string Rendered message
     */
    private function render_message($template, $event) {
        if (empty($template)) {
            return '';
        }

        // Use Voxel's render function for dynamic tags
        if (function_exists('\Voxel\render')) {
            return \Voxel\render($template, $event->get_dynamic_tags());
        }

        return $template;
    }

    /**
     * Send SMS via configured provider
     *
     * @param string $phone Recipient phone number
     * @param string $message SMS message
     * @return array Result with success/error
     */
    private function send_sms($phone, $message) {
        $provider_key = isset($this->function_settings['provider']) ? $this->function_settings['provider'] : 'twilio';

        // Get provider credentials
        $credentials = $this->get_provider_credentials($provider_key);

        // Load provider class
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/integrations/sms/class-sms-provider-base.php';

        $provider = Voxel_Toolkit_SMS_Provider_Base::get_provider($provider_key, $credentials);

        if (!$provider) {
            return array(
                'success' => false,
                'error' => __('SMS provider not configured', 'voxel-toolkit'),
            );
        }

        return $provider->send($phone, $message);
    }

    /**
     * Get provider credentials from settings
     *
     * @param string $provider_key Provider key
     * @return array Credentials
     */
    private function get_provider_credentials($provider_key) {
        $credentials = array();

        switch ($provider_key) {
            case 'twilio':
                $credentials = array(
                    'account_sid' => isset($this->function_settings['twilio_account_sid']) ? $this->function_settings['twilio_account_sid'] : '',
                    'auth_token' => isset($this->function_settings['twilio_auth_token']) ? $this->function_settings['twilio_auth_token'] : '',
                    'from_number' => isset($this->function_settings['twilio_from_number']) ? $this->function_settings['twilio_from_number'] : '',
                );
                break;

            case 'vonage':
                $credentials = array(
                    'api_key' => isset($this->function_settings['vonage_api_key']) ? $this->function_settings['vonage_api_key'] : '',
                    'api_secret' => isset($this->function_settings['vonage_api_secret']) ? $this->function_settings['vonage_api_secret'] : '',
                    'from' => isset($this->function_settings['vonage_from']) ? $this->function_settings['vonage_from'] : '',
                );
                break;

            case 'messagebird':
                $credentials = array(
                    'api_key' => isset($this->function_settings['messagebird_api_key']) ? $this->function_settings['messagebird_api_key'] : '',
                    'originator' => isset($this->function_settings['messagebird_originator']) ? $this->function_settings['messagebird_originator'] : '',
                );
                break;
        }

        return $credentials;
    }

    /**
     * Get all available Voxel events for settings
     *
     * @return array Events list
     */
    public static function get_available_events() {
        if (!class_exists('\Voxel\Events\Base_Event')) {
            return array();
        }

        $events = \Voxel\Events\Base_Event::get_all();
        $result = array();

        foreach ($events as $event) {
            $notifications = $event::notifications();
            $destinations = array();

            foreach ($notifications as $key => $notification) {
                $destinations[$key] = array(
                    'label' => isset($notification['label']) ? $notification['label'] : $key,
                );
            }

            $result[$event->get_key()] = array(
                'key' => $event->get_key(),
                'label' => $event->get_label(),
                'category' => $event->get_category(),
                'destinations' => $destinations,
            );
        }

        return $result;
    }

    /**
     * Send SMS to a user by their ID
     * Public method for use by other functions (e.g., Admin Notifications)
     *
     * @param int $user_id User ID
     * @param string $message SMS message
     * @return array Result with success/error
     */
    public function send_sms_to_user($user_id, $message) {
        if (!$this->enabled) {
            return array(
                'success' => false,
                'error' => __('SMS notifications not enabled', 'voxel-toolkit'),
            );
        }

        // Get the user's phone number from their profile
        $phone = $this->get_recipient_phone_from_profile($user_id);

        if (empty($phone)) {
            return array(
                'success' => false,
                'error' => __('No phone number found for user', 'voxel-toolkit'),
            );
        }

        return $this->send_sms($phone, $message);
    }

    /**
     * Check if SMS is enabled for a specific event and destination
     *
     * @param string $event_key Event key
     * @param string $destination Destination (e.g., 'admin', 'user')
     * @return bool
     */
    public function is_sms_enabled_for_event($event_key, $destination = 'admin') {
        if (!$this->enabled) {
            return false;
        }

        $events = isset($this->function_settings['events']) ? $this->function_settings['events'] : array();

        if (!isset($events[$event_key][$destination])) {
            return false;
        }

        return !empty($events[$event_key][$destination]['enabled']);
    }

    /**
     * Get SMS message template for a specific event and destination
     *
     * @param string $event_key Event key
     * @param string $destination Destination (e.g., 'admin', 'user')
     * @return string Message template or empty string
     */
    public function get_sms_message_for_event($event_key, $destination = 'admin') {
        $events = isset($this->function_settings['events']) ? $this->function_settings['events'] : array();

        if (!isset($events[$event_key][$destination])) {
            return '';
        }

        return isset($events[$event_key][$destination]['message']) ? $events[$event_key][$destination]['message'] : '';
    }

    /**
     * Render and send SMS for an event to a specific user
     * Public method for use by Admin Notifications
     *
     * @param \Voxel\Events\Base_Event $event Event instance
     * @param int $user_id User ID to send SMS to
     * @param string $destination Destination key (e.g., 'admin')
     * @return array Result with success/error
     */
    public function send_event_sms_to_user($event, $user_id, $destination = 'admin') {
        $event_key = $event->get_key();

        // Check if SMS is enabled for this event/destination
        if (!$this->is_sms_enabled_for_event($event_key, $destination)) {
            return array(
                'success' => false,
                'error' => __('SMS not enabled for this event', 'voxel-toolkit'),
            );
        }

        // Get message template
        $message_template = $this->get_sms_message_for_event($event_key, $destination);

        if (empty($message_template)) {
            return array(
                'success' => false,
                'error' => __('No SMS message configured for this event', 'voxel-toolkit'),
            );
        }

        // Render message with dynamic tags
        $message = $this->render_message($message_template, $event);

        // Send SMS to user
        return $this->send_sms_to_user($user_id, $message);
    }
}
