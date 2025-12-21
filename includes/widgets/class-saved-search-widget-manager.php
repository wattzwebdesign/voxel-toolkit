<?php
/**
 * Saved Search Widget Manager
 *
 * Handles registration of the Saved Search Elementor widget.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Saved_Search_Widget_Manager {

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
     * Register Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-saved-search-widget.php';
        $widgets_manager->register(new Voxel_Toolkit_Saved_Search_Widget());
    }
}
