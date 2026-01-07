<?php
/**
 * Solapi SMS Provider
 *
 * Implements SMS sending via Solapi REST API (Korean market).
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Require base class
require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/integrations/sms/class-sms-provider-base.php';

class Voxel_Toolkit_Solapi_Provider extends Voxel_Toolkit_SMS_Provider_Base {

    /**
     * Solapi API base URL
     */
    const API_BASE = 'https://api.solapi.com';

    /**
     * Send SMS via Solapi
     *
     * @param string $phone Recipient phone number
     * @param string $message SMS message content
     * @return array ['success' => bool, 'message_id' => string|null, 'error' => string|null]
     */
    public function send($phone, $message) {
        $this->clear_error();

        // Validate credentials
        if (!$this->has_required_credentials()) {
            $this->set_error(__('Solapi credentials are not configured', 'voxel-toolkit'));
            return array(
                'success' => false,
                'message_id' => null,
                'error' => $this->get_error_message(),
            );
        }

        // Normalize phone number and remove + for Korean format
        $phone = $this->normalize_phone($phone);
        $phone = ltrim($phone, '+');

        $from_number = ltrim($this->credentials['from_number'], '+');

        // Build API URL
        $url = self::API_BASE . '/messages/v4/send';

        // Prepare request body
        $body = array(
            'message' => array(
                'to' => $phone,
                'from' => $from_number,
                'text' => $message,
            ),
        );

        // Generate auth header
        $auth_header = $this->generate_auth_header();

        // Make API request
        $response = $this->make_request($url, array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            $this->log_send($phone, $message, false);
            return array(
                'success' => false,
                'message_id' => null,
                'error' => $this->get_error_message(),
            );
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        // Check for success (2xx status codes)
        if ($http_code >= 200 && $http_code < 300) {
            $message_id = isset($response_body['groupId']) ? $response_body['groupId'] : '';
            $this->log_send($phone, $message, true, $message_id);

            return array(
                'success' => true,
                'message_id' => $message_id,
                'error' => null,
            );
        }

        // Handle error
        $error_message = __('Unknown Solapi error', 'voxel-toolkit');
        if (isset($response_body['errorMessage'])) {
            $error_message = $response_body['errorMessage'];
        } elseif (isset($response_body['errorCode'])) {
            $error_message = sprintf(__('Solapi error: %s', 'voxel-toolkit'), $response_body['errorCode']);
        }

        $this->set_error($error_message);
        $this->log_send($phone, $message, false);

        return array(
            'success' => false,
            'message_id' => null,
            'error' => $error_message,
        );
    }

    /**
     * Validate Solapi credentials
     *
     * @return bool True if credentials are valid
     */
    public function validate_credentials() {
        $this->clear_error();

        if (!$this->has_required_credentials()) {
            $this->set_error(__('Missing required Solapi credentials', 'voxel-toolkit'));
            return false;
        }

        // Test credentials by fetching balance info
        $url = self::API_BASE . '/cash/v1/balance';

        // Generate auth header
        $auth_header = $this->generate_auth_header();

        $response = $this->make_request($url, array(
            'method' => 'GET',
            'headers' => array(
                'Authorization' => $auth_header,
            ),
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code === 200) {
            return true;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        $error_message = __('Invalid Solapi credentials', 'voxel-toolkit');
        if (isset($response_body['errorMessage'])) {
            $error_message = $response_body['errorMessage'];
        }

        $this->set_error($error_message);
        return false;
    }

    /**
     * Generate HMAC-SHA256 authorization header for Solapi
     *
     * @return string Authorization header value
     */
    private function generate_auth_header() {
        $api_key = $this->credentials['api_key'];
        $api_secret = $this->credentials['api_secret'];

        // Generate date in ISO 8601 format
        $date = gmdate('Y-m-d\TH:i:s\Z');

        // Generate random salt
        $salt = bin2hex(random_bytes(16));

        // Create signature string
        $signature_string = $date . $salt;

        // Generate HMAC-SHA256 signature
        $signature = hash_hmac('sha256', $signature_string, $api_secret);

        // Build auth header
        return sprintf(
            'HMAC-SHA256 apiKey=%s, date=%s, salt=%s, signature=%s',
            $api_key,
            $date,
            $salt,
            $signature
        );
    }

    /**
     * Get provider display name
     *
     * @return string
     */
    public function get_provider_name() {
        return __('Solapi', 'voxel-toolkit');
    }

    /**
     * Get provider key
     *
     * @return string
     */
    public function get_provider_key() {
        return 'solapi';
    }

    /**
     * Get required credential fields
     *
     * @return array
     */
    public function get_credential_fields() {
        return array(
            'api_key' => array(
                'label' => __('API Key', 'voxel-toolkit'),
                'type' => 'text',
                'placeholder' => 'NCSVT...',
                'description' => __('Your Solapi API Key from the console', 'voxel-toolkit'),
                'required' => true,
            ),
            'api_secret' => array(
                'label' => __('API Secret', 'voxel-toolkit'),
                'type' => 'password',
                'placeholder' => '',
                'description' => __('Your Solapi API Secret', 'voxel-toolkit'),
                'required' => true,
            ),
            'from_number' => array(
                'label' => __('From Phone Number', 'voxel-toolkit'),
                'type' => 'text',
                'placeholder' => '01012345678',
                'description' => __('Your registered sender phone number (Korean format)', 'voxel-toolkit'),
                'required' => true,
            ),
        );
    }

    /**
     * Check if required credentials are present
     *
     * @return bool
     */
    private function has_required_credentials() {
        return !empty($this->credentials['api_key'])
            && !empty($this->credentials['api_secret'])
            && !empty($this->credentials['from_number']);
    }
}
