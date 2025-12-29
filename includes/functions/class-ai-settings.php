<?php
/**
 * Central AI Settings Manager
 *
 * Provides unified AI provider configuration for all AI-powered features.
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_AI_Settings {

    /**
     * Singleton instance
     */
    private static $instance = null;

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
    private function __construct() {
        // No hooks needed - this is a utility class
    }

    /**
     * Get all AI settings
     */
    public function get_settings() {
        return Voxel_Toolkit_Settings::instance()->get_function_settings('ai_settings', array());
    }

    /**
     * Get configured AI provider
     *
     * @return string 'openai' or 'anthropic'
     */
    public function get_provider() {
        $settings = $this->get_settings();
        $provider = isset($settings['provider']) ? $settings['provider'] : 'openai';

        // Validate provider
        if (!in_array($provider, array('openai', 'anthropic'), true)) {
            return 'openai';
        }

        return $provider;
    }

    /**
     * Get API key for configured provider
     *
     * @return string API key or empty string
     */
    public function get_api_key() {
        $settings = $this->get_settings();
        return isset($settings['api_key']) ? $settings['api_key'] : '';
    }

    /**
     * Get the model for the current provider
     *
     * @return string Model identifier
     */
    public function get_model() {
        $settings = $this->get_settings();
        $provider = $this->get_provider();

        if ($provider === 'anthropic') {
            $model = isset($settings['anthropic_model']) ? $settings['anthropic_model'] : 'claude-3-5-haiku-20241022';
            $allowed = array('claude-3-5-haiku-20241022', 'claude-3-5-sonnet-20241022', 'claude-sonnet-4-20250514', 'claude-opus-4-20250514', 'claude-3-opus-20240229');
            return in_array($model, $allowed, true) ? $model : 'claude-3-5-haiku-20241022';
        }

        // OpenAI
        $model = isset($settings['openai_model']) ? $settings['openai_model'] : 'gpt-4o-mini';
        $allowed = array('gpt-4o-mini', 'gpt-4o', 'gpt-4.1', 'gpt-4.1-mini', 'o1', 'o1-mini', 'o3-mini', 'gpt-4-turbo');
        return in_array($model, $allowed, true) ? $model : 'gpt-4o-mini';
    }

    /**
     * Check if AI is configured
     *
     * @return bool True if API key is set
     */
    public function is_configured() {
        $api_key = $this->get_api_key();
        return !empty($api_key) && strlen($api_key) > 10;
    }

    /**
     * Generate AI completion with unified interface
     *
     * @param string $prompt         The prompt to send
     * @param int    $max_tokens     Maximum tokens in response (default 500)
     * @param float  $temperature    Creativity level 0-1 (default 0.7)
     * @param string $system_message Optional system message for context
     * @return string|WP_Error Generated text or error
     */
    public function generate_completion($prompt, $max_tokens = 500, $temperature = 0.7, $system_message = '') {
        $provider = $this->get_provider();
        $api_key = $this->get_api_key();

        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('AI API key not configured. Please set it in Voxel Toolkit > AI Settings.', 'voxel-toolkit'));
        }

        // Sanitize parameters
        $max_tokens = max(50, min(4000, absint($max_tokens)));
        $temperature = max(0, min(1, floatval($temperature)));

        if ($provider === 'anthropic') {
            return $this->call_anthropic_api($prompt, $api_key, $max_tokens, $temperature, $system_message);
        }

        return $this->call_openai_api($prompt, $api_key, $max_tokens, $temperature, $system_message);
    }

    /**
     * Call OpenAI API
     *
     * @param string $prompt         The prompt
     * @param string $api_key        API key
     * @param int    $max_tokens     Max tokens
     * @param float  $temperature    Temperature
     * @param string $system_message Optional system message
     * @return string|WP_Error Response or error
     */
    private function call_openai_api($prompt, $api_key, $max_tokens, $temperature, $system_message = '') {
        $model = $this->get_model();

        // Build messages array
        $messages = array();
        if (!empty($system_message)) {
            $messages[] = array('role' => 'system', 'content' => $system_message);
        }
        $messages[] = array('role' => 'user', 'content' => $prompt);

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $max_tokens,
                'temperature' => $temperature,
            )),
        ));

        if (is_wp_error($response)) {
            error_log('VT AI Settings - OpenAI Error: ' . $response->get_error_message());
            return new WP_Error('api_error', __('Failed to connect to OpenAI API.', 'voxel-toolkit'));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['choices'][0]['message']['content'])) {
            return trim($body['choices'][0]['message']['content']);
        }

        if (isset($body['error']['message'])) {
            error_log('VT AI Settings - OpenAI API Error: ' . $body['error']['message']);
            return new WP_Error('api_error', $body['error']['message']);
        }

        return new WP_Error('unknown_error', __('Unknown error from OpenAI API.', 'voxel-toolkit'));
    }

    /**
     * Call Anthropic API
     *
     * @param string $prompt         The prompt
     * @param string $api_key        API key
     * @param int    $max_tokens     Max tokens
     * @param float  $temperature    Temperature
     * @param string $system_message Optional system message
     * @return string|WP_Error Response or error
     */
    private function call_anthropic_api($prompt, $api_key, $max_tokens, $temperature, $system_message = '') {
        $model = $this->get_model();

        // Build request body
        $body = array(
            'model' => $model,
            'max_tokens' => $max_tokens,
            'messages' => array(
                array('role' => 'user', 'content' => $prompt),
            ),
        );

        // Add system message if provided (Anthropic uses separate 'system' parameter)
        if (!empty($system_message)) {
            $body['system'] = $system_message;
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'timeout' => 60,
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            error_log('VT AI Settings - Anthropic Error: ' . $response->get_error_message());
            return new WP_Error('api_error', __('Failed to connect to Anthropic API.', 'voxel-toolkit'));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['content'][0]['text'])) {
            return trim($body['content'][0]['text']);
        }

        if (isset($body['error']['message'])) {
            error_log('VT AI Settings - Anthropic API Error: ' . $body['error']['message']);
            return new WP_Error('api_error', $body['error']['message']);
        }

        return new WP_Error('unknown_error', __('Unknown error from Anthropic API.', 'voxel-toolkit'));
    }

    /**
     * Sanitize AI settings input
     *
     * @param array $input Raw input
     * @return array Sanitized settings
     */
    public static function sanitize_settings($input) {
        $sanitized = array();

        // Provider
        $sanitized['provider'] = isset($input['provider'])
            ? sanitize_text_field($input['provider'])
            : 'openai';
        if (!in_array($sanitized['provider'], array('openai', 'anthropic'), true)) {
            $sanitized['provider'] = 'openai';
        }

        // API Key
        $sanitized['api_key'] = isset($input['api_key'])
            ? sanitize_text_field($input['api_key'])
            : '';

        // OpenAI Model
        $sanitized['openai_model'] = isset($input['openai_model'])
            ? sanitize_text_field($input['openai_model'])
            : 'gpt-4o-mini';
        $allowed_openai = array('gpt-4o-mini', 'gpt-4o', 'gpt-4.1', 'gpt-4.1-mini', 'o1', 'o1-mini', 'o3-mini', 'gpt-4-turbo');
        if (!in_array($sanitized['openai_model'], $allowed_openai, true)) {
            $sanitized['openai_model'] = 'gpt-4o-mini';
        }

        // Anthropic Model
        $sanitized['anthropic_model'] = isset($input['anthropic_model'])
            ? sanitize_text_field($input['anthropic_model'])
            : 'claude-3-5-haiku-20241022';
        $allowed_anthropic = array('claude-3-5-haiku-20241022', 'claude-3-5-sonnet-20241022', 'claude-sonnet-4-20250514', 'claude-opus-4-20250514', 'claude-3-opus-20240229');
        if (!in_array($sanitized['anthropic_model'], $allowed_anthropic, true)) {
            $sanitized['anthropic_model'] = 'claude-3-5-haiku-20241022';
        }

        return $sanitized;
    }
}
