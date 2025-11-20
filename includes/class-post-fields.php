<?php
/**
 * Voxel Toolkit Post Fields Manager
 *
 * Manages custom post field types
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Post_Fields {

    private static $instance = null;
    private $available_post_fields = array();
    private $settings;

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
        $this->settings = Voxel_Toolkit_Settings::instance();
        // Initialize immediately, not on init hook
        $this->register_post_fields();
        $this->init_active_post_fields();
    }


    /**
     * Register available post fields
     */
    private function register_post_fields() {
        $this->available_post_fields = array(
            'poll_field' => array(
                'name' => __('Poll (VT)', 'voxel-toolkit'),
                'description' => __('Interactive polls with voting, user-submitted options, and multi-select support.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Poll_Field',
                'file' => 'post-fields/class-poll-field.php',
                'icon' => 'dashicons-chart-bar',
                'required_widgets' => array('widget_poll_display'), // Auto-enable these widgets when field is enabled
            ),
        );

        // Allow other plugins/themes to register post fields
        $this->available_post_fields = apply_filters('voxel_toolkit/available_post_fields', $this->available_post_fields);
    }

    /**
     * Initialize active post fields
     */
    private function init_active_post_fields() {
        foreach ($this->available_post_fields as $field_key => $field_data) {
            // Always load the field file so the class is available
            // This prevents errors when post types still reference disabled fields
            $this->load_post_field_file($field_key, $field_data);

            $field_key_full = 'post_field_' . $field_key;
            if ($this->settings->is_function_enabled($field_key_full)) {
                $this->init_post_field($field_key, $field_data);

                // Auto-enable required widgets
                if (!empty($field_data['required_widgets'])) {
                    foreach ($field_data['required_widgets'] as $widget_key) {
                        if (!$this->settings->is_function_enabled($widget_key)) {
                            $this->settings->enable_function($widget_key);
                        }
                    }
                }
            }
        }
    }

    /**
     * Load post field file
     *
     * @param string $field_key Field key
     * @param array $field_data Field data
     */
    private function load_post_field_file($field_key, $field_data) {
        // Include field file if specified
        if (isset($field_data['file'])) {
            $file_path = VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/' . $field_data['file'];
            if (file_exists($file_path)) {
                // Check if Voxel's Base_Post_Field class is loaded
                if (!class_exists('\Voxel\Post_Types\Fields\Base_Post_Field')) {
                    error_log('Voxel Toolkit: Cannot load post field - Voxel Base_Post_Field class not loaded yet');
                    return;
                }

                require_once $file_path;
            }
        }
    }

    /**
     * Initialize a specific post field
     *
     * @param string $field_key Field key
     * @param array $field_data Field data
     */
    private function init_post_field($field_key, $field_data) {
        // Initialize the field class (file should already be loaded)
        if (isset($field_data['class']) && class_exists($field_data['class'])) {
            new $field_data['class']();
        }
    }

    /**
     * Get available post fields
     *
     * @return array
     */
    public function get_available_post_fields() {
        return $this->available_post_fields;
    }

    /**
     * Check if a post field is active
     *
     * @param string $field_key Field key
     * @return bool
     */
    public function is_post_field_active($field_key) {
        $field_key_full = 'post_field_' . $field_key;
        return $this->settings->is_function_enabled($field_key_full);
    }
}
