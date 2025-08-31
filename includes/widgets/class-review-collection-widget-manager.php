<?php
/**
 * Review Collection Widget Manager
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Review_Collection_Widget_Manager {
    
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
        
        // Add frontend styles
        add_action('elementor/frontend/after_enqueue_styles', array($this, 'enqueue_frontend_styles'));
    }
    
    /**
     * Register Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-review-collection-widget.php';
        $widgets_manager->register(new \Voxel_Toolkit_Review_Collection_Widget());
    }
    
    /**
     * Enqueue frontend styles
     */
    public function enqueue_frontend_styles() {
        wp_enqueue_style(
            'voxel-toolkit-review-collection',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/review-collection.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
    }
}