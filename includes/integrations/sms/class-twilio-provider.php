<?php
/**
 * Twilio SMS Provider
 *
 * Implements SMS sending via Twilio REST API.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Require base class
require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/integrations/sms/class-sms-provider-base.php';

class Voxel_Toolkit_Twilio_Provider extends Voxel_Toolkit_SMS_Provider_Base {

    /**
     * Twilio API base URL
     */
    const API_BASE = 'https://api.twilio.com/2010-04-01';

    /**
     * Send SMS via Twilio
     *
     * @param string $phone Recipient phone number
     * @param string $message SMS message content
     * @return array ['success' => bool, 'message_id' => string|null, 'error' => string|null]
     */
    public function send($phone, $message) {
        $this->clear_error();

        // Validate credentials
        if (!$this->has_required_credentials()) {
            $this->set_error(__('Twilio credentials are not configured', 'voxel-toolkit'));
            return array(
                'success' => false,
                'message_id' => null,
                'error' => $this->get_error_message(),
            );
        }

        $account_sid = $this->credentials['account_sid'];
        $auth_token = $this->credentials['auth_token'];
        $from_number = $this->credentials['from_number'];

        // Normalize phone number
        $phone = $this->normalize_phone($phone);

        // Build API URL
        $url = sprintf('%s/Accounts/%s/Messages.json', self::API_BASE, $account_sid);

        // Prepare request body
        $body = array(
            'To' => $phone,
            'From' => $from_number,
            'Body' => $message,
        );

        // Make API request
        $response = $this->make_request($url, array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($account_sid . ':' . $auth_token),
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

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        // Check for success (2xx status codes)
        if ($http_code >= 200 && $http_code < 300) {
            $message_id = isset($response_body['sid']) ? $response_body['sid'] : '';
            $this->log_send($phone, $message, true, $message_id);

            return array(
                'success' => true,
                'message_id' => $message_id,
                'error' => null,
            );
        }

        // Handle error
        $error_message = isset($response_body['message'])
            ? $response_body['message']
            : __('Unknown Twilio error', 'voxel-toolkit');

        $this->set_error($error_message);
        $this->log_send($phone, $message, false);

        return array(
            'success' => false,
            'message_id' => null,
            'error' => $error_message,
        );
    }

    /**
     * Validate Twilio credentials
     *
     * @return bool True if credentials are valid
     */
    public function validate_credentials() {
        $this->clear_error();

        if (!$this->has_required_credentials()) {
            $this->set_error(__('Missing required Twilio credentials', 'voxel-toolkit'));
            return false;
        }

        $account_sid = $this->credentials['account_sid'];
        $auth_token = $this->credentials['auth_token'];

        // Test credentials by fetching account info
        $url = sprintf('%s/Accounts/%s.json', self::API_BASE, $account_sid);

        $response = $this->make_request($url, array(
            'method' => 'GET',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($account_sid . ':' . $auth_token),
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
        $error_message = isset($response_body['message'])
            ? $response_body['message']
            : __('Invalid Twilio credentials', 'voxel-toolkit');

        $this->set_error($error_message);
        return false;
    }

    /**
     * Get provider display name
     *
     * @return string
     */
    public function get_provider_name() {
        return __('Twilio', 'voxel-toolkit');
    }

    /**
     * Get provider key
     *
     * @return string
     */
    public function get_provider_key() {
        return 'twilio';
    }

    /**
     * Get required credential fields
     *
     * @return array
     */
    public function get_credential_fields() {
        return array(
            'account_sid' => array(
                'label' => __('Account SID', 'voxel-toolkit'),
                'type' => 'text',
                'placeholder' => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                'description' => __('Your Twilio Account SID from the console dashboard', 'voxel-toolkit'),
                'required' => true,
            ),
            'auth_token' => array(
                'label' => __('Auth Token', 'voxel-toolkit'),
                'type' => 'password',
                'placeholder' => '',
                'description' => __('Your Twilio Auth Token', 'voxel-toolkit'),
                'required' => true,
            ),
            'from_number' => array(
                'label' => __('From Phone Number', 'voxel-toolkit'),
                'type' => 'text',
                'placeholder' => '+15551234567',
                'description' => __('Your Twilio phone number to send from (E.164 format)', 'voxel-toolkit'),
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
        return !empty($this->credentials['account_sid'])
            && !empty($this->credentials['auth_token'])
            && !empty($this->credentials['from_number']);
    }
}
