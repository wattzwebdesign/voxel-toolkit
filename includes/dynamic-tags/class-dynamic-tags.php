<?php
/**
 * Dynamic Tags Manager
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Dynamic_Tags {

    /**
     * Constructor
     */
    public function __construct() {
        // Load classes first
        $this->load_methods();
        $this->load_properties();

        // Register filters - these need to be added immediately, not on a hook
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register methods with Voxel - always active
        add_filter('voxel/dynamic-data/groups/user/methods', array($this, 'register_user_methods'), 10, 1);
        add_filter('voxel/dynamic-data/groups/author/methods', array($this, 'register_author_methods'), 10, 1);

        // Register properties with Voxel
        // Temporarily disabled until proper Property classes are implemented
        // add_filter('voxel/dynamic-data/groups/post/properties', array($this, 'register_post_properties'), 10, 2);
        // add_filter('voxel/dynamic-data/groups/user/properties', array($this, 'register_user_properties'), 10, 2);
        // add_filter('voxel/dynamic-data/groups/author/properties', array($this, 'register_author_properties'), 10, 2);
    }

    /**
     * Load method classes
     */
    public function load_methods() {
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-profile-completion-method.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-profile-completion-method.php';
        }
    }

    /**
     * Load property classes
     */
    public function load_properties() {
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-reading-time-property.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-reading-time-property.php';
        }
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-word-count-property.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-word-count-property.php';
        }
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-membership-expiration-property.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-membership-expiration-property.php';
        }
    }

    /**
     * Register methods for user group
     */
    public function register_user_methods($methods) {
        $methods['profile_completion'] = \Voxel_Toolkit_Profile_Completion_Method::class;
        return $methods;
    }

    /**
     * Register methods for author group
     */
    public function register_author_methods($methods) {
        $methods['profile_completion'] = \Voxel_Toolkit_Profile_Completion_Method::class;
        return $methods;
    }

    /**
     * Register properties for post group
     */
    public function register_post_properties($properties, $group) {
        // Add reading time property
        if (class_exists('Voxel_Toolkit_Reading_Time_Property')) {
            $properties['reading_time'] = \Voxel_Toolkit_Reading_Time_Property::register();
        }

        // Add word count property
        if (class_exists('Voxel_Toolkit_Word_Count_Property')) {
            $properties['word_count'] = \Voxel_Toolkit_Word_Count_Property::register();
        }

        return $properties;
    }

    /**
     * Register properties for user group
     */
    public function register_user_properties($properties, $group) {
        // Add membership expiration property
        if (class_exists('Voxel_Toolkit_Membership_Expiration_Property')) {
            $properties['membership_expiration'] = \Voxel_Toolkit_Membership_Expiration_Property::register();
        }

        return $properties;
    }

    /**
     * Register properties for author group
     */
    public function register_author_properties($properties, $group) {
        // Add membership expiration property
        if (class_exists('Voxel_Toolkit_Membership_Expiration_Property')) {
            $properties['membership_expiration'] = \Voxel_Toolkit_Membership_Expiration_Property::register();
        }

        return $properties;
    }
}
