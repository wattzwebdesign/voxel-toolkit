<?php
/**
 * Disable Automatic Updates Feature
 * 
 * Allows disabling automatic updates for plugins, themes, and WordPress core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Disable_Auto_Updates {
    
    private $settings;
    private $options = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = Voxel_Toolkit_Settings::instance();
        $this->load_settings();
        $this->init_hooks();
    }
    
    /**
     * Load settings
     */
    private function load_settings() {
        $this->options = $this->settings->get_function_settings('disable_auto_updates', array(
            'enabled' => false,
            'disable_plugin_updates' => false,
            'disable_theme_updates' => false,
            'disable_core_updates' => false
        ));
        
        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }
    
    /**
     * Handle settings update
     */
    public function on_settings_updated($old_settings, $new_settings) {
        if (isset($new_settings['disable_auto_updates'])) {
            $this->options = $new_settings['disable_auto_updates'];
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Only apply filters if the function is enabled
        if (!empty($this->options['enabled'])) {
            $this->apply_update_filters();
        }
    }
    
    /**
     * Apply the update filters based on settings
     */
    private function apply_update_filters() {
        // Disable plugin auto-updates
        if (!empty($this->options['disable_plugin_updates'])) {
            add_filter('auto_update_plugin', '__return_false');
        }
        
        // Disable theme auto-updates
        if (!empty($this->options['disable_theme_updates'])) {
            add_filter('auto_update_theme', '__return_false');
        }
        
        // Disable WordPress core auto-updates
        if (!empty($this->options['disable_core_updates'])) {
            add_filter('auto_update_core', '__return_false');
            add_filter('allow_major_auto_core_updates', '__return_false');
            add_filter('allow_minor_auto_core_updates', '__return_false');
            
            // Also define the constant if not already defined
            if (!defined('WP_AUTO_UPDATE_CORE')) {
                define('WP_AUTO_UPDATE_CORE', false);
            }
        }
    }
}