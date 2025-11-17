<?php
/**
 * HTML Field for Voxel
 * 
 * Adds a new HTML field type to Voxel that allows users to paste raw code
 * and displays it on the frontend as entered
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Html_Field {
    
    private $enabled = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Check if enabled via Voxel Toolkit settings
        $toolkit_settings = Voxel_Toolkit_Settings::instance();
        $this->enabled = $toolkit_settings->is_function_enabled('html_field');
        
        if ($this->enabled) {
            $this->init();
        }
    }
    
    /**
     * Initialize the function
     */
    private function init() {
        // Hook very early to catch Voxel's field registration
        add_action('plugins_loaded', array($this, 'register_field_early'), 1);
        add_action('after_setup_theme', array($this, 'register_field_early'), 5);
        add_action('init', array($this, 'register_field_early'), 1);
        
        // Add filter with high priority
        add_filter('voxel/field-types', array($this, 'register_html_field'), 5);
        
        // Alternative approach: Try to register directly when Voxel config is loaded
        add_action('voxel/app/loaded', array($this, 'register_field_direct'), 5);
    }
    
    /**
     * Register field early in the init process
     */
    public function register_field_early() {
        // Check if Voxel is available
        if (!function_exists('\\Voxel\\Post_Type') || !class_exists('\\Voxel\\Post_Types\\Fields\\Base_Post_Field')) {
            return;
        }
        
        // Ensure our field class is loaded
        $this->load_field_class();
        
        // Debug output for testing
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }
    }
    
    /**
     * Enable the function
     */
    public function enable() {
        $this->enabled = true;
        $this->init();
    }
    
    /**
     * Disable the function
     */
    public function disable() {
        $this->enabled = false;
        remove_filter('voxel/field-types', array($this, 'register_html_field'));
    }
    
    /**
     * Check if function is enabled
     */
    public function is_enabled() {
        return $this->enabled;
    }
    
    /**
     * Load the field class
     */
    public function load_field_class() {
        if (!class_exists('Voxel_Toolkit_Voxel_Html_Field')) {
            require_once __DIR__ . '/html-field-class.php';
        }
    }
    
    /**
     * Register HTML field with Voxel
     */
    public function register_html_field($field_types) {
        // Debug output
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }
        
        // Ensure field class is loaded
        $this->load_field_class();
        
        // Check if our class exists
        if (!class_exists('Voxel_Toolkit_Voxel_Html_Field')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
            return $field_types;
        }
        
        // Add our custom HTML field
        $field_types['html'] = 'Voxel_Toolkit_Voxel_Html_Field';
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }
        
        return $field_types;
    }
    
    /**
     * Get function info
     */
    public static function get_info() {
        return array(
            'title' => __('HTML Field', 'voxel-toolkit'),
            'description' => __('Adds a new HTML field type to Voxel that allows users to paste raw HTML/code and displays it as entered on the frontend.', 'voxel-toolkit'),
            'category' => 'voxel'
        );
    }
}

