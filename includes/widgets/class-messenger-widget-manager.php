<?php
/**
 * Messenger Widget Manager
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Messenger_Widget_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        // Load event handlers
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-messenger-events.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register Elementor widget
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));

        // Register and enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'register_scripts'));
        add_action('elementor/frontend/after_enqueue_scripts', array($this, 'register_scripts'));

        // AJAX endpoint for getting chat target info
        add_action('wp_ajax_vt_get_chat_target_info', array($this, 'ajax_get_chat_target_info'));
    }

    /**
     * AJAX handler to get chat target info (post or user)
     */
    public function ajax_get_chat_target_info() {
        try {
            // Verify user is logged in
            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => 'Not logged in'));
            }

            $target_type = isset($_GET['target_type']) ? sanitize_text_field($_GET['target_type']) : '';
            $target_id = isset($_GET['target_id']) ? absint($_GET['target_id']) : 0;

            if (!$target_type || !$target_id) {
                wp_send_json_error(array('message' => 'Invalid parameters'));
            }

            $result = array(
                'name' => '',
                'avatar' => '',
            );

            if ($target_type === 'post') {
                // Get post info using Voxel
                if (class_exists('\Voxel\Post')) {
                    $post = \Voxel\Post::get($target_id);
                    if ($post) {
                        // Try get_display_name first, fallback to get_title
                        if (method_exists($post, 'get_display_name')) {
                            $result['name'] = $post->get_display_name();
                        } elseif (method_exists($post, 'get_title')) {
                            $result['name'] = $post->get_title();
                        }

                        // Try to get logo/avatar markup (returns HTML)
                        if (method_exists($post, 'get_logo_markup')) {
                            $logo_markup = $post->get_logo_markup();
                            if ($logo_markup) {
                                $result['avatar'] = $logo_markup;
                            }
                        }
                        // Fallback to avatar markup
                        if (empty($result['avatar']) && method_exists($post, 'get_avatar_markup')) {
                            $avatar_markup = $post->get_avatar_markup();
                            if ($avatar_markup) {
                                $result['avatar'] = $avatar_markup;
                            }
                        }
                    }
                }

                // Fallback to standard WordPress
                if (empty($result['name'])) {
                    $post = get_post($target_id);
                    if ($post) {
                        $result['name'] = $post->post_title;
                        $thumbnail = get_the_post_thumbnail_url($target_id, 'thumbnail');
                        if ($thumbnail) {
                            $result['avatar'] = '<img src="' . esc_url($thumbnail) . '" alt="' . esc_attr($result['name']) . '">';
                        }
                    }
                }
            } elseif ($target_type === 'user') {
                // Get user info using Voxel
                if (class_exists('\Voxel\User')) {
                    $user = \Voxel\User::get($target_id);
                    if ($user) {
                        if (method_exists($user, 'get_display_name')) {
                            $result['name'] = $user->get_display_name();
                        }
                        if (method_exists($user, 'get_avatar_markup')) {
                            $avatar = $user->get_avatar_markup();
                            if ($avatar) {
                                $result['avatar'] = $avatar;
                            }
                        }
                    }
                }

                // Fallback to standard WordPress
                if (empty($result['name'])) {
                    $user = get_user_by('ID', $target_id);
                    if ($user) {
                        $result['name'] = $user->display_name;
                        $avatar_url = get_avatar_url($target_id, array('size' => 96));
                        if ($avatar_url) {
                            $result['avatar'] = '<img src="' . esc_url($avatar_url) . '" alt="' . esc_attr($result['name']) . '">';
                        }
                    }
                }
            }

            if (empty($result['name'])) {
                $result['name'] = ucfirst($target_type) . ' #' . $target_id;
            }

            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        } catch (Error $e) {
            wp_send_json_error(array('message' => 'Fatal: ' . $e->getMessage()));
        }
    }

    /**
     * Register scripts and styles
     */
    public function register_scripts() {
        // Register scripts
        wp_register_script(
            'voxel-toolkit-messenger',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/messenger-widget.js',
            array('jquery', 'wp-util'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Register styles
        wp_register_style(
            'voxel-toolkit-messenger',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/messenger-widget.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        // Localize script with configuration
        $current_user_id = get_current_user_id();
        $messenger_settings = get_option('voxel_toolkit_messenger_settings', array());
        $default_avatar = !empty($messenger_settings['default_avatar']) ? $messenger_settings['default_avatar'] : '';
        $open_chats_in_window = !empty($messenger_settings['open_chats_in_window']);

        // Get AI Bot settings for messenger integration
        $ai_bot_config = array(
            'enabled' => false,
            'avatar' => '',
            'displayMode' => 'sidebar', // sidebar or chat_window
        );
        if (class_exists('Voxel_Toolkit_AI_Bot')) {
            $ai_bot = Voxel_Toolkit_AI_Bot::instance();
            $ai_bot_settings = $ai_bot->get_settings();
            if (!empty($ai_bot_settings['messenger_integration'])) {
                $ai_bot_config['enabled'] = true;
                $ai_bot_config['avatar'] = !empty($ai_bot_settings['ai_bot_avatar']) ? $ai_bot_settings['ai_bot_avatar'] : '';
                $ai_bot_config['displayMode'] = !empty($ai_bot_settings['messenger_display_mode']) ? $ai_bot_settings['messenger_display_mode'] : 'sidebar';

                // Additional settings needed for chat window mode
                if ($ai_bot_config['displayMode'] === 'chat_window') {
                    $ai_bot_config['nonce'] = wp_create_nonce('vt_ai_bot');
                    $ai_bot_config['panelTitle'] = !empty($ai_bot_settings['panel_title']) ? $ai_bot_settings['panel_title'] : __('AI Assistant', 'voxel-toolkit');
                    $ai_bot_config['welcomeMessage'] = !empty($ai_bot_settings['welcome_message']) ? $ai_bot_settings['welcome_message'] : __('Hi! How can I help you find what you are looking for?', 'voxel-toolkit');
                    $ai_bot_config['placeholderText'] = !empty($ai_bot_settings['placeholder_text']) ? $ai_bot_settings['placeholder_text'] : __('Ask me anything...', 'voxel-toolkit');
                    $ai_bot_config['thinkingText'] = !empty($ai_bot_settings['thinking_text']) ? $ai_bot_settings['thinking_text'] : __('AI is thinking', 'voxel-toolkit');
                    $ai_bot_config['accessControl'] = !empty($ai_bot_settings['access_control']) ? $ai_bot_settings['access_control'] : 'everyone';
                    $ai_bot_config['showQuickActions'] = isset($ai_bot_settings['show_quick_actions']) ? (bool) $ai_bot_settings['show_quick_actions'] : true;
                }
            }
        }

        wp_localize_script('voxel-toolkit-messenger', 'vtMessenger', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vx_chat'),
            'userId' => $current_user_id,
            'defaultAvatar' => $default_avatar,
            'pluginUrl' => VOXEL_TOOLKIT_PLUGIN_URL,
            'autoOpen' => true, // Auto-open new incoming chats
            'openChatsInWindow' => $open_chats_in_window,
            'polling' => array(
                'enabled' => true, // Now safe with concurrent request protection
                'frequency' => 30000, // milliseconds - 30 seconds (conservative)
            ),
            'maxOpenChats' => 3,
            'aiBot' => $ai_bot_config,
            'i18n' => array(
                'searchPlaceholder' => __('Search messages...', 'voxel-toolkit'),
                'noChats' => __('No conversations yet', 'voxel-toolkit'),
                'typeMessage' => __('Type a message...', 'voxel-toolkit'),
                'replyAs' => __('Reply as %s', 'voxel-toolkit'),
                'send' => __('Send', 'voxel-toolkit'),
                'newMessage' => __('New message', 'voxel-toolkit'),
                'minimize' => __('Minimize', 'voxel-toolkit'),
                'close' => __('Close', 'voxel-toolkit'),
            ),
        ));
    }

    /**
     * Register Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-messenger-widget.php';
        $widgets_manager->register(new \Voxel_Toolkit_Messenger_Widget());
    }
}
