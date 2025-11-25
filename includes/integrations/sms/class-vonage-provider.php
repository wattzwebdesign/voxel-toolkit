<?php
/**
 * Vonage (Nexmo) SMS Provider
 *
 * Implements SMS sending via Vonage REST API.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Require base class
require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/integrations/sms/class-sms-provider-base.php';

class Voxel_Toolkit_Vonage_Provider extends Voxel_Toolkit_SMS_Provider_Base {

    /**
     * Vonage API base URL
     */
    const API_BASE = 'https://rest.nexmo.com';

    /**
     * Send SMS via Vonage
     *
     * @param string $phone Recipient phone number
     * @param string $message SMS message content
     * @return array ['success' => bool, 'message_id' => string|null, 'error' => string|null]
     */
    public function send($phone, $message) {
        $this->clear_error();

        // Validate credentials
        if (!$this->has_required_credentials()) {
            $this->set_error(__('Vonage credentials are not configured', 'voxel-toolkit'));
            return array(
                'success' => false,
                'message_id' => null,
                'error' => $this->get_error_message(),
            );
        }

        $api_key = $this->credentials['api_key'];
        $api_secret = $this->credentials['api_secret'];
        $from = $this->credentials['from'];

        // Normalize phone number (remove + for Vonage)
        $phone = $this->normalize_phone($phone);
        $phone = ltrim($phone, '+');

        // Build API URL
        $url = self::API_BASE . '/sms/json';

        // Prepare request body
        $body = array(
            'api_key' => $api_key,
            'api_secret' => $api_secret,
            'to' => $phone,
            'from' => $from,
            'text' => $message,
        );

        // Make API request
        $response = $this->make_request($url, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => $body,
        ));

        if (is_wp_error($response)) {
            $this->log_send($phone, $message, false);
            return array(
                'success' => false,
                'message_id' => null,
                'error' => $this->get_error_message(),
            );
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        // Check Vonage response
        if (isset($response_body['messages']) && is_array($response_body['messages'])) {
            $first_message = $response_body['messages'][0];

            // Status 0 means success
            if (isset($first_message['status']) && $first_message['status'] === '0') {
                $message_id = isset($first_message['message-id']) ? $first_message['message-id'] : '';
                $this->log_send($phone, $message, true, $message_id);

                return array(
                    'success' => true,
                    'message_id' => $message_id,
                    'error' => null,
                );
            }

            // Handle error
            $error_message = isset($first_message['error-text'])
                ? $first_message['error-text']
                : __('Unknown Vonage error', 'voxel-toolkit');

            $this->set_error($error_message);
        } else {
            $this->set_error(__('Invalid response from Vonage', 'voxel-toolkit'));
        }

        $this->log_send($phone, $message, false);

        return array(
            'success' => false,
            'message_id' => null,
            'error' => $this->get_error_message(),
        );
    }

    /**
     * Validate Vonage credentials
     *
     * @return bool True if credentials are valid
     */
    public function validate_credentials() {
        $this->clear_error();

        if (!$this->has_required_credentials()) {
            $this->set_error(__('Missing required Vonage credentials', 'voxel-toolkit'));
            return false;
        }

        $api_key = $this->credentials['api_key'];
        $api_secret = $this->credentials['api_secret'];

        // Test credentials by checking account balance
        $url = sprintf('%s/account/get-balance?api_key=%s&api_secret=%s',
            self::API_BASE,
            urlencode($api_key),
            urlencode($api_secret)
        );

        $response = $this->make_request($url, array(
            'method' => 'GET',
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        // If we get a value back, credentials are valid
        if (isset($response_body['value'])) {
            return true;
        }

        // Check for error response
        if (isset($response_body['error-code'])) {
            $error_message = isset($response_body['error-code-label'])
                ? $response_body['error-code-label']
                : __('Invalid Vonage credentials', 'voxel-toolkit');
            $this->set_error($error_message);
        } else {
            $this->set_error(__('Invalid Vonage credentials', 'voxel-toolkit'));
        }

        return false;
    }

    /**
     * Get provider display name
     *
     * @return string
     */
    public function get_provider_name() {
        return __('Vonage (Nexmo)', 'voxel-toolkit');
    }

    /**
     * Get provider key
     *
     * @return string
     */
    public function get_provider_key() {
        return 'vonage';
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
                'placeholder' => 'abcd1234',
                'description' => __('Your Vonage API Key from the dashboard', 'voxel-toolkit'),
                'required' => true,
            ),
            'api_secret' => array(
                'label' => __('API Secret', 'voxel-toolkit'),
                'type' => 'password',
                'placeholder' => '',
                'description' => __('Your Vonage API Secret', 'voxel-toolkit'),
                'required' => true,
            ),
            'from' => array(
                'label' => __('From Name/Number', 'voxel-toolkit'),
                'type' => 'text',
                'placeholder' => 'MyBusiness or +15551234567',
                'description' => __('Sender ID - can be alphanumeric (max 11 chars) or phone number', 'voxel-toolkit'),
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
            && !empty($this->credentials['from']);
    }
}
