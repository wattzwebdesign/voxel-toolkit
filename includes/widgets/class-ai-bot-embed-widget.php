<?php
/**
 * AI Bot Embed Widget Manager
 *
 * Registers the AI Bot Embed Elementor widget.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_AI_Bot_Embed_Widget {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register Elementor widget
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));
    }

    /**
     * Register Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        // Only register if AI Bot function is enabled
        if (!class_exists('Voxel_Toolkit_Settings')) {
            return;
        }

        $settings = Voxel_Toolkit_Settings::instance();
        if (!$settings->is_function_enabled('ai_bot')) {
            return;
        }

        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/elementor/widgets/ai-bot-embed.php';
        $widgets_manager->register(new \Voxel_Toolkit_Elementor_AI_Bot_Embed());
    }
}
