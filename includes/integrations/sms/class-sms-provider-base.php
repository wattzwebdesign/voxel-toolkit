<?php
/**
 * SMS Provider Base Class
 *
 * Abstract base class for SMS provider integrations.
 * Provides common interface for Twilio, Vonage, and MessageBird.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class Voxel_Toolkit_SMS_Provider_Base {

    /**
     * Provider credentials
     */
    protected $credentials = array();

    /**
     * Last error message
     */
    protected $last_error = '';

    /**
     * Logging context (event_key, user_id, etc.)
     */
    protected $log_context = array();

    /**
     * Constructor
     *
     * @param array $credentials Provider-specific credentials
     */
    public function __construct($credentials = array()) {
        $this->credentials = $credentials;
    }

    /**
     * Send SMS message
     *
     * @param string $phone Recipient phone number (E.164 format recommended)
     * @param string $message SMS message content
     * @return array ['success' => bool, 'message_id' => string|null, 'error' => string|null]
     */
    abstract public function send($phone, $message);

    /**
     * Validate provider credentials
     *
     * @return bool True if credentials are valid
     */
    abstract public function validate_credentials();

    /**
     * Get provider display name
     *
     * @return string Human-readable provider name
     */
    abstract public function get_provider_name();

    /**
     * Get provider key
     *
     * @return string Provider identifier key
     */
    abstract public function get_provider_key();

    /**
     * Get required credential fields
     *
     * @return array Field definitions for admin settings
     */
    abstract public function get_credential_fields();

    /**
     * Get the last error message
     *
     * @return string Error message
     */
    public function get_error_message() {
        return $this->last_error;
    }

    /**
     * Set error message
     *
     * @param string $message Error message
     */
    protected function set_error($message) {
        $this->last_error = $message;
    }

    /**
     * Clear error message
     */
    protected function clear_error() {
        $this->last_error = '';
    }

    /**
     * Set logging context for the next send operation
     *
     * @param array $context Context data (event_key, user_id, etc.)
     */
    public function set_log_context($context) {
        $this->log_context = $context;
    }

    /**
     * Clear logging context
     */
    protected function clear_log_context() {
        $this->log_context = array();
    }

    /**
     * Normalize phone number to E.164 format
     *
     * @param string $phone Phone number
     * @return string Normalized phone number
     */
    protected function normalize_phone($phone) {
        // Remove all non-digit characters except leading +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Ensure it starts with +
        if (strpos($phone, '+') !== 0) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Truncate message to SMS length limit
     *
     * @param string $message Message content
     * @param int $max_length Maximum length (default 160 for single SMS)
     * @return string Truncated message
     */
    protected function truncate_message($message, $max_length = 160) {
        if (strlen($message) <= $max_length) {
            return $message;
        }

        return substr($message, 0, $max_length - 3) . '...';
    }

    /**
     * Factory method to get provider instance
     *
     * @param string $provider_key Provider identifier (twilio, vonage, messagebird)
     * @param array $credentials Provider credentials
     * @return Voxel_Toolkit_SMS_Provider_Base|null Provider instance or null if invalid
     */
    public static function get_provider($provider_key, $credentials = array()) {
        $provider_classes = array(
            'twilio' => 'Voxel_Toolkit_Twilio_Provider',
            'vonage' => 'Voxel_Toolkit_Vonage_Provider',
            'messagebird' => 'Voxel_Toolkit_MessageBird_Provider',
            'telnyx' => 'Voxel_Toolkit_Telnyx_Provider',
            'solapi' => 'Voxel_Toolkit_Solapi_Provider',
        );

        if (!isset($provider_classes[$provider_key])) {
            return null;
        }

        $class_name = $provider_classes[$provider_key];

        // Load provider file if not already loaded
        $provider_files = array(
            'twilio' => 'class-twilio-provider.php',
            'vonage' => 'class-vonage-provider.php',
            'messagebird' => 'class-messagebird-provider.php',
            'telnyx' => 'class-telnyx-provider.php',
            'solapi' => 'class-solapi-provider.php',
        );

        $file_path = VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/integrations/sms/' . $provider_files[$provider_key];

        if (!class_exists($class_name) && file_exists($file_path)) {
            require_once $file_path;
        }

        if (!class_exists($class_name)) {
            return null;
        }

        return new $class_name($credentials);
    }

    /**
     * Get all available providers
     *
     * @return array Provider definitions
     */
    public static function get_available_providers() {
        return array(
            'twilio' => array(
                'name' => __('Twilio', 'voxel-toolkit'),
                'description' => __('Popular SMS platform with global coverage', 'voxel-toolkit'),
            ),
            'vonage' => array(
                'name' => __('Vonage (Nexmo)', 'voxel-toolkit'),
                'description' => __('Global communications platform', 'voxel-toolkit'),
            ),
            'messagebird' => array(
                'name' => __('MessageBird', 'voxel-toolkit'),
                'description' => __('European-based omnichannel platform', 'voxel-toolkit'),
            ),
            'telnyx' => array(
                'name' => __('Telnyx', 'voxel-toolkit'),
                'description' => __('Developer-friendly SMS with competitive pricing', 'voxel-toolkit'),
            ),
            'solapi' => array(
                'name' => __('Solapi', 'voxel-toolkit'),
                'description' => __('Korean SMS platform with domestic coverage', 'voxel-toolkit'),
            ),
        );
    }

    /**
     * Make HTTP request using WordPress HTTP API
     *
     * @param string $url API endpoint
     * @param array $args Request arguments
     * @return array|WP_Error Response or error
     */
    protected function make_request($url, $args = array()) {
        $defaults = array(
            'timeout' => 30,
            'sslverify' => true,
        );

        $args = wp_parse_args($args, $defaults);

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->set_error($response->get_error_message());
            return $response;
        }

        return $response;
    }

    /**
     * Log SMS send attempt
     *
     * @param string $phone Recipient phone
     * @param string $message Message content
     * @param bool $success Whether send was successful
     * @param string $message_id Provider message ID (if available)
     * @param array $context Additional context (event_key, user_id)
     */
    protected function log_send($phone, $message, $success, $message_id = '', $context = array()) {
        // Log to database
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/integrations/sms/class-sms-log.php';

        // Merge passed context with stored context (passed takes precedence)
        $merged_context = array_merge($this->log_context, $context);

        Voxel_Toolkit_SMS_Log::log(array(
            'phone' => $phone,
            'message' => $message,
            'provider' => $this->get_provider_key(),
            'status' => $success ? 'success' : 'failed',
            'message_id' => $message_id,
            'error' => $success ? '' : $this->last_error,
            'event_key' => isset($merged_context['event_key']) ? $merged_context['event_key'] : '',
            'user_id' => isset($merged_context['user_id']) ? $merged_context['user_id'] : 0,
        ));

        // Clear context after logging
        $this->clear_log_context();

        // Optional: Also log to WordPress debug log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $status = $success ? 'SUCCESS' : 'FAILED';
            $log_message = sprintf(
                '[Voxel Toolkit SMS] %s - Provider: %s, Phone: %s, Message ID: %s',
                $status,
                $this->get_provider_key(),
                $this->mask_phone($phone),
                $message_id ?: 'N/A'
            );

            if (!$success) {
                $log_message .= ', Error: ' . $this->last_error;
            }

            error_log($log_message);
        }
    }

    /**
     * Mask phone number for logging
     *
     * @param string $phone Phone number
     * @return string Masked phone number
     */
    protected function mask_phone($phone) {
        $length = strlen($phone);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($phone, 0, 3) . str_repeat('*', $length - 6) . substr($phone, -3);
    }
}
