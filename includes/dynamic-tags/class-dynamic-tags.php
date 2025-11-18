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
        add_filter('voxel/dynamic-data/groups/site/properties', array($this, 'register_site_properties'), 10, 2);
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
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-tally-modifier.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-tally-modifier.php';
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
        $modifiers['tally'] = \Voxel_Toolkit_Tally_Modifier::class;
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

                // Average reading speed: 200-250 words per minute (we'll use 225)
                $reading_time_minutes = ceil($word_count / 225);

                if ($reading_time_minutes < 1) {
                    return '1 min';
                } else if ($reading_time_minutes < 60) {
                    return $reading_time_minutes . ' min';
                } else {
                    $hours = floor($reading_time_minutes / 60);
                    $minutes = $reading_time_minutes % 60;
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
        $properties['membership_expiration'] = \Voxel\Dynamic_Data\Tag::Date('Membership Expiration')
            ->render( function() use ( $group ) {
                // Get user object
                $user = $group->get_user();

                if (!$user || !method_exists($user, 'get_membership')) {
                    return null;
                }

                // Get membership
                $membership = $user->get_membership();

                if (!$membership || !method_exists($membership, 'get_expiration_date')) {
                    return null;
                }

                // Get expiration date
                $expiration = $membership->get_expiration_date();

                if (!$expiration) {
                    return null;
                }

                // Return the DateTime object or timestamp
                if ($expiration instanceof \DateTime) {
                    return $expiration->format('Y-m-d H:i:s');
                }

                return $expiration;
            } );

        return $properties;
    }

    /**
     * Register properties for author group
     */
    public function register_author_properties($properties, $group) {
        // Add membership expiration property for author
        $properties['membership_expiration'] = \Voxel\Dynamic_Data\Tag::Date('Membership Expiration')
            ->render( function() use ( $group ) {
                // Get author from post
                if (!$group->post || !$group->post->get_author()) {
                    return null;
                }

                $author = $group->post->get_author();

                if (!method_exists($author, 'get_membership')) {
                    return null;
                }

                // Get membership
                $membership = $author->get_membership();

                if (!$membership || !method_exists($membership, 'get_expiration_date')) {
                    return null;
                }

                // Get expiration date
                $expiration = $membership->get_expiration_date();

                if (!$expiration) {
                    return null;
                }

                // Return the DateTime object or timestamp
                if ($expiration instanceof \DateTime) {
                    return $expiration->format('Y-m-d H:i:s');
                }

                // Try to format if it's a string or timestamp
                try {
                    $date = new \DateTime($expiration);
                    $date_format = get_option('date_format') . ' ' . get_option('time_format');
                    $timestamp = $date->getTimestamp();
                    $formatted_date = date_i18n($date_format, $timestamp);

                    return $formatted_date;
                } catch (Exception $e) {
                    return '';
                }
            } );

        return $properties;
    }

    /**
     * Register properties for site group (Options Page)
     */
    public function register_site_properties($properties, $group) {
        // Check if options page is enabled
        $settings = Voxel_Toolkit_Settings::instance();
        if (!$settings->is_function_enabled('options_page')) {
            return $properties;
        }

        // Get configured fields
        $config = $settings->get_function_settings('options_page');
        $fields = isset($config['fields']) ? $config['fields'] : array();

        if (empty($fields)) {
            return $properties;
        }

        // Create an 'options' object property
        $properties['options'] = \Voxel\Dynamic_Data\Tag::Object('Site Options')->properties(function() use ($fields) {
            $option_properties = array();

            foreach ($fields as $field_name => $field_config) {
                $option_name = 'voxel_options_' . $field_name;
                $type = $field_config['type'];
                $label = $field_config['label'];
                $default = isset($field_config['default']) ? $field_config['default'] : '';

                switch ($type) {
                    case 'text':
                    case 'textarea':
                        $option_properties[$field_name] = \Voxel\Dynamic_Data\Tag::String($label)
                            ->render(function() use ($option_name, $default) {
                                return get_option($option_name, $default);
                            });
                        break;

                    case 'number':
                        $option_properties[$field_name] = \Voxel\Dynamic_Data\Tag::Number($label)
                            ->render(function() use ($option_name, $default) {
                                $value = get_option($option_name, $default);
                                return is_numeric($value) ? intval($value) : 0;
                            });
                        break;

                    case 'url':
                        $option_properties[$field_name] = \Voxel\Dynamic_Data\Tag::URL($label)
                            ->render(function() use ($option_name, $default) {
                                return get_option($option_name, $default);
                            });
                        break;

                    case 'image':
                        $option_properties[$field_name] = \Voxel\Dynamic_Data\Tag::Number($label)
                            ->render(function() use ($option_name, $default) {
                                $value = get_option($option_name, $default);
                                return is_numeric($value) ? intval($value) : 0;
                            });
                        break;
                }
            }

            return $option_properties;
        });

        return $properties;
    }
}
