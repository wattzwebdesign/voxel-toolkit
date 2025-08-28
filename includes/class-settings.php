<?php
/**
 * Voxel Toolkit Settings Class
 * 
 * Handles plugin settings and options management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Settings {
    
    private static $instance = null;
    private $options = array();
    
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
    private function __construct() {
        $this->load_options();
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Hook for settings updates
        add_action('updated_option', array($this, 'on_option_updated'), 10, 3);
    }
    
    /**
     * Load plugin options
     */
    private function load_options() {
        $this->options = get_option('voxel_toolkit_options', array());
    }
    
    /**
     * Refresh options from database
     */
    public function refresh_options() {
        $this->load_options();
    }
    
    /**
     * Get option value
     * 
     * @param string $key Option key
     * @param mixed $default Default value
     * @return mixed Option value
     */
    public function get_option($key, $default = null) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }
    
    /**
     * Update option value
     * 
     * @param string $key Option key
     * @param mixed $value Option value
     * @return bool Whether the option was updated
     */
    public function update_option($key, $value) {
        $this->options[$key] = $value;
        return update_option('voxel_toolkit_options', $this->options);
    }
    
    /**
     * Delete option
     * 
     * @param string $key Option key
     * @return bool Whether the option was deleted
     */
    public function delete_option($key) {
        unset($this->options[$key]);
        return update_option('voxel_toolkit_options', $this->options);
    }
    
    /**
     * Get all options
     * 
     * @return array All plugin options
     */
    public function get_all_options() {
        return $this->options;
    }
    
    /**
     * Check if a function is enabled
     * 
     * @param string $function_name Function name
     * @return bool Whether the function is enabled
     */
    public function is_function_enabled($function_name) {
        $function_options = $this->get_option($function_name, array());
        return isset($function_options['enabled']) && $function_options['enabled'] === true;
    }
    
    /**
     * Enable a function
     * 
     * @param string $function_name Function name
     * @param array $settings Function settings
     * @return bool Whether the function was enabled
     */
    public function enable_function($function_name, $settings = array()) {
        $function_options = $this->get_option($function_name, array());
        $function_options['enabled'] = true;
        
        // Merge with existing settings
        if (!empty($settings)) {
            $function_options = array_merge($function_options, $settings);
        }
        
        return $this->update_option($function_name, $function_options);
    }
    
    /**
     * Disable a function
     * 
     * @param string $function_name Function name
     * @return bool Whether the function was disabled
     */
    public function disable_function($function_name) {
        $function_options = $this->get_option($function_name, array());
        $function_options['enabled'] = false;
        
        return $this->update_option($function_name, $function_options);
    }
    
    /**
     * Get function settings
     * 
     * @param string $function_name Function name
     * @param array $defaults Default settings
     * @return array Function settings
     */
    public function get_function_settings($function_name, $defaults = array()) {
        $function_options = $this->get_option($function_name, $defaults);
        return $function_options;
    }
    
    /**
     * Update function settings
     * 
     * @param string $function_name Function name
     * @param array $settings Function settings
     * @return bool Whether the settings were updated
     */
    public function update_function_settings($function_name, $settings) {
        $function_options = $this->get_option($function_name, array());
        $function_options = array_merge($function_options, $settings);
        
        return $this->update_option($function_name, $function_options);
    }
    
    /**
     * Handle option updates
     * 
     * @param string $option Option name
     * @param mixed $old_value Old value
     * @param mixed $value New value
     */
    public function on_option_updated($option, $old_value, $value) {
        if ($option === 'voxel_toolkit_options') {
            $this->options = $value;
            
            // Trigger action for other components to react to settings changes
            do_action('voxel_toolkit/settings_updated', $value, $old_value);
        }
    }
    
    /**
     * Get available post types for selection
     * 
     * @return array Available post types
     */
    public function get_available_post_types() {
        $post_types = get_post_types(array(
            'public' => true,
            'show_ui' => true
        ), 'objects');
        
        $available_types = array();
        
        foreach ($post_types as $post_type) {
            // Skip built-in WordPress post types that aren't relevant
            if (in_array($post_type->name, array('attachment', 'nav_menu_item', 'wp_block'))) {
                continue;
            }
            
            $available_types[$post_type->name] = $post_type->label;
        }
        
        return $available_types;
    }
    
    /**
     * Reset all settings to defaults
     * 
     * @return bool Whether settings were reset
     */
    public function reset_settings() {
        $default_options = array(
            'auto_verify_posts' => array(
                'enabled' => false,
                'post_types' => array()
            )
        );
        
        $this->options = $default_options;
        return update_option('voxel_toolkit_options', $default_options);
    }
}