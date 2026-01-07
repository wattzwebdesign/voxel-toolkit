<?php
/**
 * Telnyx SMS Provider
 *
 * Implements SMS sending via Telnyx REST API v2.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Require base class
require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/integrations/sms/class-sms-provider-base.php';

class Voxel_Toolkit_Telnyx_Provider extends Voxel_Toolkit_SMS_Provider_Base {

    /**
     * Telnyx API base URL
     */
    const API_BASE = 'https://api.telnyx.com/v2';

    /**
     * Send SMS via Telnyx
     *
     * @param string $phone Recipient phone number
     * @param string $message SMS message content
     * @return array ['success' => bool, 'message_id' => string|null, 'error' => string|null]
     */
    public function send($phone, $message) {
        $this->clear_error();

        // Validate credentials
        if (!$this->has_required_credentials()) {
            $this->set_error(__('Telnyx credentials are not configured', 'voxel-toolkit'));
            return array(
                'success' => false,
                'message_id' => null,
                'error' => $this->get_error_message(),
            );
        }

        $api_key = $this->credentials['api_key'];
        $from_number = $this->credentials['from_number'];

        // Normalize phone number
        $phone = $this->normalize_phone($phone);

        // Build API URL
        $url = self::API_BASE . '/messages';

        // Prepare request body
        $body = array(
            'from' => $from_number,
            'to' => $phone,
            'text' => $message,
        );

        // Make API request
        $response = $this->make_request($url, array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
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
            $message_id = isset($response_body['data']['id']) ? $response_body['data']['id'] : '';
            $this->log_send($phone, $message, true, $message_id);

            return array(
                'success' => true,
                'message_id' => $message_id,
                'error' => null,
            );
        }

        // Handle error
        $error_message = __('Unknown Telnyx error', 'voxel-toolkit');
        if (isset($response_body['errors']) && is_array($response_body['errors']) && !empty($response_body['errors'])) {
            $first_error = $response_body['errors'][0];
            $error_message = isset($first_error['detail']) ? $first_error['detail'] : (isset($first_error['title']) ? $first_error['title'] : $error_message);
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
     * Validate Telnyx credentials
     *
     * @return bool True if credentials are valid
     */
    public function validate_credentials() {
        $this->clear_error();

        if (!$this->has_required_credentials()) {
            $this->set_error(__('Missing required Telnyx credentials', 'voxel-toolkit'));
            return false;
        }

        $api_key = $this->credentials['api_key'];

        // Test credentials by fetching balance info
        $url = self::API_BASE . '/balance';

        $response = $this->make_request($url, array(
            'method' => 'GET',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
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
        $error_message = __('Invalid Telnyx credentials', 'voxel-toolkit');
        if (isset($response_body['errors']) && is_array($response_body['errors']) && !empty($response_body['errors'])) {
            $first_error = $response_body['errors'][0];
            $error_message = isset($first_error['detail']) ? $first_error['detail'] : (isset($first_error['title']) ? $first_error['title'] : $error_message);
        }

        $this->set_error($error_message);
        return false;
    }

    /**
     * Get provider display name
     *
     * @return string
     */
    public function get_provider_name() {
        return __('Telnyx', 'voxel-toolkit');
    }

    /**
     * Get provider key
     *
     * @return string
     */
    public function get_provider_key() {
        return 'telnyx';
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
                'type' => 'password',
                'placeholder' => 'KEY...',
                'description' => __('Your Telnyx API v2 Key from the portal', 'voxel-toolkit'),
                'required' => true,
            ),
            'from_number' => array(
                'label' => __('From Phone Number', 'voxel-toolkit'),
                'type' => 'text',
                'placeholder' => '+15551234567',
                'description' => __('Your Telnyx phone number to send from (E.164 format)', 'voxel-toolkit'),
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
            && !empty($this->credentials['from_number']);
    }
}
