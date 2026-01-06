<?php
/**
 * Checklist Display Widget Manager
 *
 * Handles widget registration and asset enqueuing.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Checklist_Display_Widget_Manager {

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

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Register Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-checklist-display-widget.php';
        $widgets_manager->register(new \Voxel_Toolkit_Checklist_Display_Widget());
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_assets() {
        // Enqueue CSS
        wp_enqueue_style(
            'vt-checklist-display',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/checklist-display.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        // Enqueue JS
        wp_enqueue_script(
            'vt-checklist-display',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/checklist-display.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Localize script
        wp_localize_script('vt-checklist-display', 'vtChecklist', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => array(
                'error' => __('Something went wrong. Please try again.', 'voxel-toolkit'),
                'noPermission' => __('You do not have permission to modify this checklist.', 'voxel-toolkit'),
            ),
        ));
    }
}
