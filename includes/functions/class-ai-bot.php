<?php
/**
 * AI Bot Function
 *
 * AI-powered search assistant that allows users to ask natural language questions
 * to find posts across the site.
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_AI_Bot {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Rate limit transient prefix
     */
    const RATE_LIMIT_PREFIX = 'vt_ai_bot_rate_';

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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        $search_handler_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/ai-bot/class-ai-bot-search-handler.php';
        if (file_exists($search_handler_file)) {
            require_once $search_handler_file;
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers for logged in users
        add_action('wp_ajax_vt_ai_bot_query', array($this, 'handle_query'));

        // AJAX handlers for guests (if enabled)
        add_action('wp_ajax_nopriv_vt_ai_bot_query', array($this, 'handle_guest_query'));
    }

    /**
     * Get settings for this feature
     */
    public function get_settings() {
        $defaults = array(
            'panel_position' => 'right',
            'panel_behavior' => 'push',
            'access_control' => 'everyone',
            'post_types' => array(),
            'card_templates' => array(), // Card template IDs per post type
            'system_prompt' => '',
            'max_results' => 6,
            'welcome_message' => __('Hi! How can I help you find what you are looking for?', 'voxel-toolkit'),
            'placeholder_text' => __('Ask me anything...', 'voxel-toolkit'),
            'panel_title' => __('AI Assistant', 'voxel-toolkit'),
            'suggested_queries' => array(), // Example questions to show as clickable chips
            'conversation_memory' => true,
            'max_memory_messages' => 10,
            'rate_limit_enabled' => true,
            'rate_limit_requests' => 10,
            'rate_limit_period' => 60,
            'messenger_integration' => false, // Enable AI Bot in messenger widget
            'messenger_display_mode' => 'sidebar', // sidebar or chat_window
            'ai_bot_avatar' => '', // Avatar URL for AI Bot in messenger
            // Styling options
            'style_primary_color' => '#0084ff',
            'style_header_text_color' => '#ffffff',
            'style_ai_bubble_color' => '#f0f2f5',
            'style_ai_text_color' => '#050505',
            'style_user_bubble_color' => '#0084ff',
            'style_user_text_color' => '#ffffff',
            'style_panel_width' => 400,
            'style_font_size' => 14,
            'style_border_radius' => 18,
        );

        $settings = Voxel_Toolkit_Settings::instance()->get_function_settings('ai_bot', array());
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Get frontend settings for localization
     */
    public function get_frontend_settings() {
        $settings = $this->get_settings();

        return array(
            'panelPosition' => $settings['panel_position'],
            'panelBehavior' => $settings['panel_behavior'],
            'welcomeMessage' => $settings['welcome_message'],
            'placeholderText' => $settings['placeholder_text'],
            'panelTitle' => $settings['panel_title'],
            'suggestedQueries' => (array) $settings['suggested_queries'],
            'conversationMemory' => (bool) $settings['conversation_memory'],
            'maxMemoryMessages' => absint($settings['max_memory_messages']),
            'accessControl' => $settings['access_control'],
            'messengerIntegration' => (bool) $settings['messenger_integration'],
        );
    }

    /**
     * Check if current user can access the AI Bot
     */
    private function can_access() {
        $settings = $this->get_settings();

        if ($settings['access_control'] === 'logged_in') {
            return is_user_logged_in();
        }

        return true; // 'everyone' can access
    }

    /**
     * Handle query from logged-in users
     */
    public function handle_query() {
        check_ajax_referer('vt_ai_bot', 'nonce');
        $this->process_query();
    }

    /**
     * Handle query from guests
     */
    public function handle_guest_query() {
        check_ajax_referer('vt_ai_bot', 'nonce');

        // Check access control
        if (!$this->can_access()) {
            wp_send_json_error(array(
                'message' => __('Please log in to use the AI assistant.', 'voxel-toolkit'),
            ));
        }

        $this->process_query();
    }

    /**
     * Process the AI query
     */
    private function process_query() {
        $settings = $this->get_settings();

        // Check rate limiting
        if ($settings['rate_limit_enabled'] && !$this->check_rate_limit()) {
            wp_send_json_error(array(
                'message' => __('You\'re sending messages too quickly. Please wait a moment.', 'voxel-toolkit'),
                'rate_limited' => true,
            ));
        }

        // Get message
        $message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';
        if (empty($message)) {
            wp_send_json_error(array(
                'message' => __('Please enter a message.', 'voxel-toolkit'),
            ));
        }

        // Get conversation history
        $history = isset($_POST['history']) ? $this->sanitize_history($_POST['history']) : array();

        // Get user location if provided
        $user_location = null;
        if (!empty($_POST['user_location'])) {
            $location_data = $_POST['user_location'];
            if (is_string($location_data)) {
                $location_data = json_decode(wp_unslash($location_data), true);
            }
            if (is_array($location_data) && isset($location_data['lat']) && isset($location_data['lng'])) {
                $user_location = array(
                    'lat' => floatval($location_data['lat']),
                    'lng' => floatval($location_data['lng']),
                    'city' => isset($location_data['city']) ? sanitize_text_field($location_data['city']) : '',
                    'state' => isset($location_data['state']) ? sanitize_text_field($location_data['state']) : '',
                    'source' => isset($location_data['source']) ? sanitize_text_field($location_data['source']) : 'unknown',
                );
            }
        }

        // Check AI configuration
        if (!class_exists('Voxel_Toolkit_AI_Settings') || !Voxel_Toolkit_AI_Settings::instance()->is_configured()) {
            wp_send_json_error(array(
                'message' => __('AI is not configured. Please contact the site administrator.', 'voxel-toolkit'),
            ));
        }

        // Get search handler
        if (!class_exists('Voxel_Toolkit_AI_Bot_Search_Handler')) {
            wp_send_json_error(array(
                'message' => __('Search handler not available.', 'voxel-toolkit'),
            ));
        }

        $search_handler = new Voxel_Toolkit_AI_Bot_Search_Handler($settings);

        // Build system prompt with schema and user location
        $system_prompt = $search_handler->build_system_prompt($user_location);

        // Build messages for AI
        $ai_messages = $this->build_ai_messages($system_prompt, $history, $message);

        // Debug logging
        error_log('VT AI Bot - Prompt length: ' . strlen($ai_messages) . ' chars');

        // Call AI
        $ai_settings = Voxel_Toolkit_AI_Settings::instance();
        $ai_response = $ai_settings->generate_completion($ai_messages, 1500, 0.7);

        if (is_wp_error($ai_response)) {
            error_log('VT AI Bot - API Error: ' . $ai_response->get_error_message());
            wp_send_json_error(array(
                'message' => $ai_response->get_error_message(),
            ));
        }

        error_log('VT AI Bot - AI Response: ' . substr($ai_response, 0, 500));

        // Parse AI response
        $parsed = $this->parse_ai_response($ai_response);

        if (!$parsed) {
            error_log('VT AI Bot - Could not parse JSON from response');
            // Fallback: return AI response as explanation without search results
            wp_send_json_success(array(
                'explanation' => $ai_response,
                'results' => array(),
            ));
        }

        // Execute searches with error handling
        error_log('VT AI Bot - Parsed searches: ' . wp_json_encode($parsed['searches'] ?? []));

        try {
            $results = $search_handler->execute_search($parsed);
            error_log('VT AI Bot - Search results count: ' . count($results));
        } catch (\Exception $e) {
            error_log('VT AI Bot - Search execution error: ' . $e->getMessage());
            // Return AI explanation without results on error
            wp_send_json_success(array(
                'explanation' => isset($parsed['explanation']) ? $parsed['explanation'] : 'I found some options but encountered an error displaying them.',
                'results' => array(),
            ));
            return;
        } catch (\Error $e) {
            error_log('VT AI Bot - Search execution fatal error: ' . $e->getMessage());
            wp_send_json_success(array(
                'explanation' => isset($parsed['explanation']) ? $parsed['explanation'] : 'I found some options but encountered an error displaying them.',
                'results' => array(),
            ));
            return;
        }

        // Increment rate limit counter
        if ($settings['rate_limit_enabled']) {
            $this->increment_rate_limit();
        }

        // Return response with debug info
        wp_send_json_success(array(
            'explanation' => isset($parsed['explanation']) ? $parsed['explanation'] : '',
            'results' => $results,
            'debug' => array(
                'ai_response_preview' => substr($ai_response, 0, 500),
                'parsed_searches' => isset($parsed['searches']) ? count($parsed['searches']) : 0,
                'results_count' => count($results),
            ),
        ));
    }

    /**
     * Build messages array for AI API
     */
    private function build_ai_messages($system_prompt, $history, $current_message) {
        $messages = $system_prompt . "\n\n";

        // Add conversation history if memory is enabled
        $settings = $this->get_settings();
        if ($settings['conversation_memory'] && !empty($history)) {
            $max_history = absint($settings['max_memory_messages']);
            $history = array_slice($history, -$max_history);

            foreach ($history as $msg) {
                if (!isset($msg['type']) || !isset($msg['content'])) {
                    continue;
                }

                if ($msg['type'] === 'user') {
                    $messages .= "User: " . $msg['content'] . "\n";
                } elseif ($msg['type'] === 'ai') {
                    $messages .= "Assistant: " . $msg['content'] . "\n";
                }
            }
        }

        $messages .= "\nUser: " . $current_message . "\n\nAssistant:";

        return $messages;
    }

    /**
     * Parse AI response to extract JSON
     */
    private function parse_ai_response($response) {
        // Try to find JSON in the response
        if (preg_match('/\{[\s\S]*\}/m', $response, $matches)) {
            $json_str = $matches[0];

            // Remove JavaScript-style comments that AI sometimes adds
            // Remove single-line comments: // comment
            $json_str = preg_replace('/\/\/[^\n\r]*/m', '', $json_str);
            // Remove multi-line comments: /* comment */
            $json_str = preg_replace('/\/\*[\s\S]*?\*\//', '', $json_str);

            // Clean up any trailing commas before closing brackets (invalid JSON)
            $json_str = preg_replace('/,\s*([\]}])/m', '$1', $json_str);

            $parsed = json_decode($json_str, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($parsed['searches'])) {
                return $parsed;
            }

            // Log parsing error for debugging
            error_log('VT AI Bot - JSON parse error: ' . json_last_error_msg());
            error_log('VT AI Bot - Cleaned JSON: ' . substr($json_str, 0, 500));
        }

        // If no valid JSON found, return null
        return null;
    }

    /**
     * Sanitize conversation history
     */
    private function sanitize_history($history) {
        if (!is_array($history)) {
            $history = json_decode(wp_unslash($history), true);
        }

        if (!is_array($history)) {
            return array();
        }

        $sanitized = array();
        foreach ($history as $msg) {
            if (!is_array($msg) || !isset($msg['type']) || !isset($msg['content'])) {
                continue;
            }

            $type = sanitize_text_field($msg['type']);
            if (!in_array($type, array('user', 'ai'), true)) {
                continue;
            }

            $sanitized[] = array(
                'type' => $type,
                'content' => sanitize_text_field($msg['content']),
            );
        }

        return $sanitized;
    }

    /**
     * Check rate limit
     */
    private function check_rate_limit() {
        $settings = $this->get_settings();
        $key = $this->get_rate_limit_key();
        $count = get_transient($key);

        if ($count === false) {
            return true;
        }

        return $count < $settings['rate_limit_requests'];
    }

    /**
     * Increment rate limit counter
     */
    private function increment_rate_limit() {
        $settings = $this->get_settings();
        $key = $this->get_rate_limit_key();
        $count = get_transient($key);

        if ($count === false) {
            set_transient($key, 1, $settings['rate_limit_period']);
        } else {
            set_transient($key, $count + 1, $settings['rate_limit_period']);
        }
    }

    /**
     * Get rate limit key for current user/IP
     */
    private function get_rate_limit_key() {
        if (is_user_logged_in()) {
            return self::RATE_LIMIT_PREFIX . 'user_' . get_current_user_id();
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'unknown';
        return self::RATE_LIMIT_PREFIX . 'ip_' . md5($ip);
    }

    /**
     * Sanitize settings input
     */
    public static function sanitize_settings($input) {
        $sanitized = array();

        // Panel position
        $sanitized['panel_position'] = isset($input['panel_position']) && in_array($input['panel_position'], array('left', 'right'), true)
            ? $input['panel_position']
            : 'right';

        // Panel behavior
        $sanitized['panel_behavior'] = isset($input['panel_behavior']) && in_array($input['panel_behavior'], array('push', 'overlay'), true)
            ? $input['panel_behavior']
            : 'push';

        // Access control
        $sanitized['access_control'] = isset($input['access_control']) && in_array($input['access_control'], array('everyone', 'logged_in'), true)
            ? $input['access_control']
            : 'everyone';

        // Post types
        $sanitized['post_types'] = isset($input['post_types']) && is_array($input['post_types'])
            ? array_map('sanitize_text_field', $input['post_types'])
            : array();

        // Card templates (per post type)
        $sanitized['card_templates'] = array();
        if (isset($input['card_templates']) && is_array($input['card_templates'])) {
            foreach ($input['card_templates'] as $post_type => $template_id) {
                $sanitized['card_templates'][sanitize_text_field($post_type)] = absint($template_id);
            }
        }

        // System prompt
        $sanitized['system_prompt'] = isset($input['system_prompt'])
            ? sanitize_textarea_field($input['system_prompt'])
            : '';

        // Max results
        $sanitized['max_results'] = isset($input['max_results'])
            ? max(1, min(20, absint($input['max_results'])))
            : 6;

        // Welcome message
        $sanitized['welcome_message'] = isset($input['welcome_message'])
            ? sanitize_text_field($input['welcome_message'])
            : '';

        // Placeholder text
        $sanitized['placeholder_text'] = isset($input['placeholder_text'])
            ? sanitize_text_field($input['placeholder_text'])
            : '';

        // Thinking text
        $sanitized['thinking_text'] = isset($input['thinking_text'])
            ? sanitize_text_field($input['thinking_text'])
            : '';

        // Panel title
        $sanitized['panel_title'] = isset($input['panel_title'])
            ? sanitize_text_field($input['panel_title'])
            : '';

        // Conversation memory
        $sanitized['conversation_memory'] = isset($input['conversation_memory']) && $input['conversation_memory'];

        // Max memory messages
        $sanitized['max_memory_messages'] = isset($input['max_memory_messages'])
            ? max(1, min(50, absint($input['max_memory_messages'])))
            : 10;

        // Rate limit enabled
        $sanitized['rate_limit_enabled'] = isset($input['rate_limit_enabled']) && $input['rate_limit_enabled'];

        // Rate limit requests
        $sanitized['rate_limit_requests'] = isset($input['rate_limit_requests'])
            ? max(1, min(100, absint($input['rate_limit_requests'])))
            : 10;

        // Rate limit period
        $sanitized['rate_limit_period'] = isset($input['rate_limit_period'])
            ? max(10, min(3600, absint($input['rate_limit_period'])))
            : 60;

        // Suggested queries
        $sanitized['suggested_queries'] = array();
        if (isset($input['suggested_queries']) && is_array($input['suggested_queries'])) {
            $sanitized['suggested_queries'] = array_filter(array_map('sanitize_text_field', $input['suggested_queries']));
        }

        // Messenger integration
        $sanitized['messenger_integration'] = isset($input['messenger_integration']) && $input['messenger_integration'];

        // Messenger display mode
        $sanitized['messenger_display_mode'] = isset($input['messenger_display_mode']) && in_array($input['messenger_display_mode'], array('sidebar', 'chat_window'), true)
            ? $input['messenger_display_mode']
            : 'sidebar';

        // Show quick actions
        $sanitized['show_quick_actions'] = isset($input['show_quick_actions']) && $input['show_quick_actions'];

        // AI Bot avatar
        $sanitized['ai_bot_avatar'] = isset($input['ai_bot_avatar'])
            ? esc_url_raw($input['ai_bot_avatar'])
            : '';

        // Styling options - colors
        $sanitized['style_primary_color'] = isset($input['style_primary_color'])
            ? sanitize_hex_color($input['style_primary_color']) ?: '#0084ff'
            : '#0084ff';

        $sanitized['style_header_text_color'] = isset($input['style_header_text_color'])
            ? sanitize_hex_color($input['style_header_text_color']) ?: '#ffffff'
            : '#ffffff';

        $sanitized['style_ai_bubble_color'] = isset($input['style_ai_bubble_color'])
            ? sanitize_hex_color($input['style_ai_bubble_color']) ?: '#f0f2f5'
            : '#f0f2f5';

        $sanitized['style_ai_text_color'] = isset($input['style_ai_text_color'])
            ? sanitize_hex_color($input['style_ai_text_color']) ?: '#050505'
            : '#050505';

        $sanitized['style_user_bubble_color'] = isset($input['style_user_bubble_color'])
            ? sanitize_hex_color($input['style_user_bubble_color']) ?: '#0084ff'
            : '#0084ff';

        $sanitized['style_user_text_color'] = isset($input['style_user_text_color'])
            ? sanitize_hex_color($input['style_user_text_color']) ?: '#ffffff'
            : '#ffffff';

        // Styling options - sizing
        $sanitized['style_panel_width'] = isset($input['style_panel_width'])
            ? max(300, min(600, absint($input['style_panel_width'])))
            : 400;

        $sanitized['style_font_size'] = isset($input['style_font_size'])
            ? max(12, min(18, absint($input['style_font_size'])))
            : 14;

        $sanitized['style_border_radius'] = isset($input['style_border_radius'])
            ? max(0, min(30, absint($input['style_border_radius'])))
            : 18;

        return $sanitized;
    }
}
