<?php
/**
 * MessageBird SMS Provider
 *
 * Implements SMS sending via MessageBird REST API.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Require base class
require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/integrations/sms/class-sms-provider-base.php';

class Voxel_Toolkit_MessageBird_Provider extends Voxel_Toolkit_SMS_Provider_Base {

    /**
     * MessageBird API base URL
     */
    const API_BASE = 'https://rest.messagebird.com';

    /**
     * Send SMS via MessageBird
     *
     * @param string $phone Recipient phone number
     * @param string $message SMS message content
     * @return array ['success' => bool, 'message_id' => string|null, 'error' => string|null]
     */
    public function send($phone, $message) {
        $this->clear_error();

        // Validate credentials
        if (!$this->has_required_credentials()) {
            $this->set_error(__('MessageBird credentials are not configured', 'voxel-toolkit'));
            return array(
                'success' => false,
                'message_id' => null,
                'error' => $this->get_error_message(),
            );
        }

        $api_key = $this->credentials['api_key'];
        $originator = $this->credentials['originator'];

        // Normalize phone number (remove + for MessageBird)
        $phone = $this->normalize_phone($phone);
        $phone = ltrim($phone, '+');

        // Build API URL
        $url = self::API_BASE . '/messages';

        // Prepare request body
        $body = array(
            'recipients' => $phone,
            'originator' => $originator,
            'body' => $message,
        );

        // Make API request
        $response = $this->make_request($url, array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'AccessKey ' . $api_key,
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

        // Check for success (201 Created)
        if ($http_code === 201 && isset($response_body['id'])) {
            $message_id = $response_body['id'];
            $this->log_send($phone, $message, true, $message_id);

            return array(
                'success' => true,
                'message_id' => $message_id,
                'error' => null,
            );
        }

        // Handle error
        $error_message = __('Unknown MessageBird error', 'voxel-toolkit');

        if (isset($response_body['errors']) && is_array($response_body['errors'])) {
            $errors = array();
            foreach ($response_body['errors'] as $error) {
                $errors[] = isset($error['description']) ? $error['description'] : '';
            }
            $error_message = implode(', ', array_filter($errors));
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
     * Validate MessageBird credentials
     *
     * @return bool True if credentials are valid
     */
    public function validate_credentials() {
        $this->clear_error();

        if (!$this->has_required_credentials()) {
            $this->set_error(__('Missing required MessageBird credentials', 'voxel-toolkit'));
            return false;
        }

        $api_key = $this->credentials['api_key'];

        // Test credentials by fetching balance
        $url = self::API_BASE . '/balance';

        $response = $this->make_request($url, array(
            'method' => 'GET',
            'headers' => array(
                'Authorization' => 'AccessKey ' . $api_key,
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

        if (isset($response_body['errors']) && is_array($response_body['errors'])) {
            $error_message = isset($response_body['errors'][0]['description'])
                ? $response_body['errors'][0]['description']
                : __('Invalid MessageBird credentials', 'voxel-toolkit');
            $this->set_error($error_message);
        } else {
            $this->set_error(__('Invalid MessageBird credentials', 'voxel-toolkit'));
        }

        return false;
    }

    /**
     * Get provider display name
     *
     * @return string
     */
    public function get_provider_name() {
        return __('MessageBird', 'voxel-toolkit');
    }

    /**
     * Get provider key
     *
     * @return string
     */
    public function get_provider_key() {
        return 'messagebird';
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
                'placeholder' => '',
                'description' => __('Your MessageBird API key (live or test)', 'voxel-toolkit'),
                'required' => true,
            ),
            'originator' => array(
                'label' => __('Originator', 'voxel-toolkit'),
                'type' => 'text',
                'placeholder' => 'MyBusiness or +15551234567',
                'description' => __('Sender name (max 11 chars alphanumeric) or phone number', 'voxel-toolkit'),
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
            && !empty($this->credentials['originator']);
    }
}
