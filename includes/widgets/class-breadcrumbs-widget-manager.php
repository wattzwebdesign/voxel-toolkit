<?php
/**
 * Breadcrumbs Widget Manager
 *
 * Handles registration and initialization of the breadcrumbs widget
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Breadcrumbs_Widget_Manager {

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
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));
    }

    /**
     * Register the Elementor widget
     *
     * @param object $widgets_manager Elementor widgets manager instance
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-breadcrumbs-widget.php';
        $widgets_manager->register(new \Voxel_Toolkit_Breadcrumbs_Widget());
    }
}
