<?php
/**
 * Sticky Admin Bar Function
 * 
 * Makes the WordPress admin bar sticky (fixed) instead of static on the frontend
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Sticky_Admin_Bar {
    
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = Voxel_Toolkit_Settings::instance();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Only apply to frontend when admin bar is showing
        if (!is_admin() && is_admin_bar_showing()) {
            // Use wp_head with high priority to ensure it loads after theme CSS
            add_action('wp_head', array($this, 'add_sticky_css'), 999);
        }
        
        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }
    
    /**
     * Add sticky CSS directly to head
     */
    public function add_sticky_css() {
        ?>
        <style id="voxel-toolkit-sticky-admin-bar">
        /* Override Voxel theme static admin bar */
        #wpadminbar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 99999 !important;
        }
        </style>
        <?php
    }
    
    /**
     * Handle settings updates
     *
     * @param array $new_settings New settings
     * @param array $old_settings Old settings
     */
    public function on_settings_updated($new_settings, $old_settings) {
        // Reinitialize hooks
        $this->remove_hooks();
        $this->init_hooks();
    }
    
    /**
     * Remove hooks (for cleanup)
     */
    private function remove_hooks() {
        remove_action('wp_head', array($this, 'add_sticky_css'), 999);
    }
    
    /**
     * Deinitialize (cleanup when function is disabled)
     */
    public function deinit() {
        $this->remove_hooks();
        remove_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10);
    }
}