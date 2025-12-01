<?php
/**
 * Media Gallery Widget Manager
 *
 * Handles registration and initialization of the media gallery widget
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Media_Gallery_Widget_Manager {

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
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'), 1001);
        add_action('wp_enqueue_scripts', array($this, 'register_styles'));
    }

    /**
     * Register the Elementor widget
     *
     * @param object $widgets_manager Elementor widgets manager instance
     */
    public function register_elementor_widget($widgets_manager) {
        // Check if Voxel Gallery widget exists
        if (!class_exists('\Voxel\Widgets\Gallery')) {
            return;
        }

        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-media-gallery-widget.php';
        $widgets_manager->register(new \Voxel_Toolkit\Widgets\Media_Gallery_Widget());
    }

    /**
     * Register CSS styles
     */
    public function register_styles() {
        wp_register_style(
            'vt-media-gallery',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/media-gallery.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
    }
}
