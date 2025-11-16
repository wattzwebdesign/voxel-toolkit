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

        // Register modifiers with Voxel
        add_filter('voxel/dynamic-data/modifiers', array($this, 'register_modifiers'), 10, 1);

        // Register properties with Voxel
        add_filter('voxel/dynamic-data/groups/post/properties', array($this, 'register_post_properties'), 10, 2);
        add_filter('voxel/dynamic-data/groups/user/properties', array($this, 'register_user_properties'), 10, 2);
        add_filter('voxel/dynamic-data/groups/author/properties', array($this, 'register_author_properties'), 10, 2);
    }

    /**
     * Load method classes
     */
    public function load_methods() {
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-profile-completion-method.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-profile-completion-method.php';
        }
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-file-modifiers.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-file-modifiers.php';
        }
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-address-modifier.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-address-modifier.php';
        }
    }

    /**
     * Load property classes
     *
     * Note: Properties are now registered inline using Tag::String() and Tag::Number()
     * No separate class files needed for properties.
     */
    public function load_properties() {
        // Properties are registered inline in register_*_properties methods
        // No class loading needed
    }

    /**
     * Register modifiers with Voxel
     */
    public function register_modifiers($modifiers) {
        $modifiers['file_size'] = \Voxel_Toolkit_File_Size_Modifier::class;
        $modifiers['file_extension'] = \Voxel_Toolkit_File_Extension_Modifier::class;
        $modifiers['address_part'] = \Voxel_Toolkit_Address_Part_Modifier::class;
        return $modifiers;
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
        $properties['reading_time'] = \Voxel\Dynamic_Data\Tag::String('Reading Time')
            ->render( function() use ( $group ) {
                // Access the post directly - Voxel\Post extends WP_Post
                if (!$group->post || !$group->post->get_id()) {
                    return '';
                }

                // Get post content
                $content = get_post_field('post_content', $group->post->get_id());

                // Strip shortcodes and HTML tags
                $content = strip_shortcodes($content);
                $content = wp_strip_all_tags($content);

                // Count words
                $word_count = str_word_count($content);

                // Calculate reading time (average reading speed: 200 words per minute)
                $reading_time = ceil($word_count / 200);

                // Format output
                if ($reading_time < 1) {
                    return '< 1 min';
                } elseif ($reading_time < 60) {
                    return $reading_time . ' min';
                } else {
                    // Convert to hours if 60+ minutes
                    $hours = floor($reading_time / 60);
                    $minutes = $reading_time % 60;
                    if ($minutes > 0) {
                        return $hours . ' hr ' . $minutes . ' min';
                    } else {
                        return $hours . ' hr';
                    }
                }
            } );

        // Add word count property
        $properties['word_count'] = \Voxel\Dynamic_Data\Tag::Number('Word Count')
            ->render( function() use ( $group ) {
                // Access the post directly - Voxel\Post extends WP_Post
                if (!$group->post || !$group->post->get_id()) {
                    return 0;
                }

                // Get post content
                $content = get_post_field('post_content', $group->post->get_id());

                // Strip shortcodes and HTML tags
                $content = strip_shortcodes($content);
                $content = wp_strip_all_tags($content);

                // Count words
                $word_count = str_word_count($content);

                return $word_count;
            } );

        return $properties;
    }

    /**
     * Register properties for user group
     */
    public function register_user_properties($properties, $group) {
        // Add membership expiration property
        $properties['membership_expiration'] = \Voxel\Dynamic_Data\Tag::String('Membership Expiration Date')
            ->render( function() use ( $group ) {
                // Get user ID from the group
                $user_id = null;
                if (isset($group->user) && method_exists($group->user, 'get_id')) {
                    $user_id = $group->user->get_id();
                } elseif (isset($group->user) && $group->user instanceof \WP_User) {
                    $user_id = $group->user->ID;
                }

                if (!$user_id) {
                    return '';
                }

                // Get the voxel:plan meta
                $plan_meta = get_user_meta($user_id, 'voxel:plan', true);

                if (empty($plan_meta)) {
                    return '';
                }

                // Decode JSON if it's a string
                if (is_string($plan_meta)) {
                    $plan_data = json_decode($plan_meta, true);
                } else {
                    $plan_data = $plan_meta;
                }

                // Validate plan data structure
                if (!is_array($plan_data) || !isset($plan_data['billing']['current_period']['end'])) {
                    return '';
                }

                // Get the end date
                $end_date = $plan_data['billing']['current_period']['end'];

                if (empty($end_date)) {
                    return '';
                }

                // Parse the date
                try {
                    $timestamp = strtotime($end_date);
                    if ($timestamp === false) {
                        return '';
                    }

                    // Format according to WordPress date settings
                    $date_format = get_option('date_format');
                    $formatted_date = date_i18n($date_format, $timestamp);

                    return $formatted_date;
                } catch (Exception $e) {
                    return '';
                }
            } );

        return $properties;
    }

    /**
     * Register properties for author group
     */
    public function register_author_properties($properties, $group) {
        // Add membership expiration property
        $properties['membership_expiration'] = \Voxel\Dynamic_Data\Tag::String('Membership Expiration Date')
            ->render( function() use ( $group ) {
                // Get user ID from the group
                $user_id = null;
                if (isset($group->user) && method_exists($group->user, 'get_id')) {
                    $user_id = $group->user->get_id();
                } elseif (isset($group->user) && $group->user instanceof \WP_User) {
                    $user_id = $group->user->ID;
                }

                if (!$user_id) {
                    return '';
                }

                // Get the voxel:plan meta
                $plan_meta = get_user_meta($user_id, 'voxel:plan', true);

                if (empty($plan_meta)) {
                    return '';
                }

                // Decode JSON if it's a string
                if (is_string($plan_meta)) {
                    $plan_data = json_decode($plan_meta, true);
                } else {
                    $plan_data = $plan_meta;
                }

                // Validate plan data structure
                if (!is_array($plan_data) || !isset($plan_data['billing']['current_period']['end'])) {
                    return '';
                }

                // Get the end date
                $end_date = $plan_data['billing']['current_period']['end'];

                if (empty($end_date)) {
                    return '';
                }

                // Parse the date
                try {
                    $timestamp = strtotime($end_date);
                    if ($timestamp === false) {
                        return '';
                    }

                    // Format according to WordPress date settings
                    $date_format = get_option('date_format');
                    $formatted_date = date_i18n($date_format, $timestamp);

                    return $formatted_date;
                } catch (Exception $e) {
                    return '';
                }
            } );

        return $properties;
    }
}
