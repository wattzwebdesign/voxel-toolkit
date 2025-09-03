<?php
/**
 * Previous/Next Widget Manager
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Prev_Next_Widget_Manager {
    
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
        
        // Add frontend styles and scripts
        add_action('elementor/frontend/after_enqueue_styles', array($this, 'enqueue_frontend_assets'));
    }
    
    /**
     * Register Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-prev-next-widget.php';
        $widgets_manager->register(new \Voxel_Toolkit_Prev_Next_Widget());
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'voxel-toolkit-prev-next',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/prev-next.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
        
        wp_enqueue_script(
            'voxel-toolkit-prev-next',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/prev-next.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );
    }
}