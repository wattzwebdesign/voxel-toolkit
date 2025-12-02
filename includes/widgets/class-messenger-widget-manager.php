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

        wp_localize_script('voxel-toolkit-messenger', 'vtMessenger', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vx_chat'),
            'userId' => $current_user_id,
            'defaultAvatar' => $default_avatar,
            'pluginUrl' => VOXEL_TOOLKIT_PLUGIN_URL,
            'autoOpen' => true, // Auto-open new incoming chats
            'polling' => array(
                'enabled' => true, // Now safe with concurrent request protection
                'frequency' => 30000, // milliseconds - 30 seconds (conservative)
            ),
            'maxOpenChats' => 3,
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
