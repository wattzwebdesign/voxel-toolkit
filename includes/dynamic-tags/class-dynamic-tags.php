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
        $this->load_trackers();

        // Register filters - these need to be added immediately, not on a hook
        $this->init_hooks();
    }

    /**
     * Load tracker classes for feed context
     */
    public function load_trackers() {
        // Load post position tracker
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/class-post-position-tracker.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/class-post-position-tracker.php';

            // Initialize tracker
            if (class_exists('Voxel_Toolkit_Post_Position_Tracker')) {
                Voxel_Toolkit_Post_Position_Tracker::instance();
            }
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register methods with Voxel - always active
        add_filter('voxel/dynamic-data/groups/user/methods', array($this, 'register_user_methods'), 10, 1);
        add_filter('voxel/dynamic-data/groups/author/methods', array($this, 'register_author_methods'), 10, 1);
        add_filter('voxel/dynamic-data/groups/order/methods', array($this, 'register_order_methods'), 10, 1);
        add_filter('voxel/dynamic-data/groups/post/methods', array($this, 'register_post_methods'), 10, 1);
        add_filter('voxel/dynamic-data/groups/site/methods', array($this, 'register_site_methods'), 10, 1);

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
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-order-summary-method.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-order-summary-method.php';
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
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-sold-modifier.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-sold-modifier.php';
        }
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-qr-code-modifier.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-qr-code-modifier.php';
        }
        // Load post field anywhere modifier (always enabled)
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-post-field-anywhere.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-post-field-anywhere.php';
        }
        // Load see more modifier (always enabled)
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-see-more-modifier.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-see-more-modifier.php';
        }
        // Load initial modifier (always enabled)
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-initial-modifier.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-initial-modifier.php';
        }

        // Load timeline tags (always enabled)
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-timeline-tags.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-timeline-tags.php';
            if (class_exists('Voxel_Toolkit_Timeline_Tags')) {
                Voxel_Toolkit_Timeline_Tags::instance();
            }
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
        $modifiers['sold'] = \Voxel_Toolkit_Sold_Modifier::class;
        $modifiers['generate_qr_code'] = \Voxel_Toolkit_QR_Code_Modifier::class;
        $modifiers['see_more'] = \Voxel_Toolkit_See_More_Modifier::class;
        $modifiers['initial'] = \Voxel_Toolkit_Initial_Modifier::class;

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
     * Register methods for order group
     */
    public function register_order_methods($methods) {
        $methods['summary'] = \Voxel_Toolkit_Order_Summary_Method::class;
        return $methods;
    }

    /**
     * Register methods for post group
     */
    public function register_post_methods($methods) {
        return $methods;
    }

    /**
     * Register methods for site group
     */
    public function register_site_methods($methods) {
        // Register render_post_tag method (always enabled)
        if (class_exists('Voxel_Toolkit_Render_Post_Tag_Method')) {
            $methods['render_post_tag'] = \Voxel_Toolkit_Render_Post_Tag_Method::class;
        }
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

        // Add campaign progress properties only if widget is enabled
        $settings = Voxel_Toolkit_Settings::instance();
        if ($settings->is_function_enabled('widget_campaign_progress')) {
            // Add campaign total raised property
            $properties['campaign_amount_donated'] = \Voxel\Dynamic_Data\Tag::Number('Campaign Amount Donated')
            ->render( function() use ( $group ) {
                if (!$group->post || !$group->post->get_id()) {
                    return 0;
                }

                if (!class_exists('Voxel_Toolkit_Campaign_Progress_Widget_Manager')) {
                    return 0;
                }

                $progress = \Voxel_Toolkit_Campaign_Progress_Widget_Manager::get_campaign_progress($group->post->get_id());
                return round($progress['total_raised'], 2);
            } );

        // Add campaign donation count property
        $properties['campaign_number_of_donors'] = \Voxel\Dynamic_Data\Tag::Number('Campaign Number of Donors')
            ->render( function() use ( $group ) {
                if (!$group->post || !$group->post->get_id()) {
                    return 0;
                }

                if (!class_exists('Voxel_Toolkit_Campaign_Progress_Widget_Manager')) {
                    return 0;
                }

                $progress = \Voxel_Toolkit_Campaign_Progress_Widget_Manager::get_campaign_progress($group->post->get_id());
                return intval($progress['donation_count']);
            } );

        // Add campaign percentage donated property
        $properties['campaign_percentage_donated'] = \Voxel\Dynamic_Data\Tag::Number('Campaign Percentage Donated')
            ->render( function() use ( $group ) {
                if (!$group->post || !$group->post->get_id()) {
                    return 0;
                }

                if (!class_exists('Voxel_Toolkit_Campaign_Progress_Widget_Manager')) {
                    return 0;
                }

                // Get campaign data
                $progress = \Voxel_Toolkit_Campaign_Progress_Widget_Manager::get_campaign_progress($group->post->get_id());

                // Get goal from post meta 'vt_campaign_goal'
                $goal = floatval(get_post_meta($group->post->get_id(), 'vt_campaign_goal', true));

                // If no goal set, return 0
                if ($goal <= 0) {
                    return 0;
                }

                // Calculate percentage
                $percentage = min(100, ($progress['total_raised'] / $goal) * 100);
                return round($percentage);
            } );
        }

        // Add article helpful properties only if widget is enabled
        if ($settings->is_function_enabled('widget_article_helpful')) {
            // Add article helpful yes count property
            $properties['article_helpful_yes_count'] = \Voxel\Dynamic_Data\Tag::Number('Article Helpful Yes Count')
            ->render( function() use ( $group ) {
                if (!$group->post || !$group->post->get_id()) {
                    return 0;
                }

                $yes_count = get_post_meta($group->post->get_id(), '_article_helpful_yes', true);
                return intval($yes_count ? $yes_count : 0);
            } );

            // Add article helpful no count property
            $properties['article_helpful_no_count'] = \Voxel\Dynamic_Data\Tag::Number('Article Helpful No Count')
            ->render( function() use ( $group ) {
                if (!$group->post || !$group->post->get_id()) {
                    return 0;
                }

                $no_count = get_post_meta($group->post->get_id(), '_article_helpful_no', true);
                return intval($no_count ? $no_count : 0);
            } );

            // Add article helpful total votes property
            $properties['article_helpful_total_votes'] = \Voxel\Dynamic_Data\Tag::Number('Article Helpful Total Votes')
            ->render( function() use ( $group ) {
                if (!$group->post || !$group->post->get_id()) {
                    return 0;
                }

                $yes_count = intval(get_post_meta($group->post->get_id(), '_article_helpful_yes', true));
                $no_count = intval(get_post_meta($group->post->get_id(), '_article_helpful_no', true));
                return $yes_count + $no_count;
            } );

            // Add article helpful percentage property
            $properties['article_helpful_percentage'] = \Voxel\Dynamic_Data\Tag::Number('Article Helpful Percentage')
            ->render( function() use ( $group ) {
                if (!$group->post || !$group->post->get_id()) {
                    return 0;
                }

                $yes_count = intval(get_post_meta($group->post->get_id(), '_article_helpful_yes', true));
                $no_count = intval(get_post_meta($group->post->get_id(), '_article_helpful_no', true));
                $total = $yes_count + $no_count;

                // If no votes, return 0
                if ($total <= 0) {
                    return 0;
                }

                // Calculate percentage of yes votes
                $percentage = ($yes_count / $total) * 100;
                return round($percentage);
            } );

            // Add article helpful latest yes vote timestamp property
            $properties['article_helpful_latest_yes'] = \Voxel\Dynamic_Data\Tag::Date('Article Helpful Latest Yes')
                ->render( function() use ( $group ) {
                    if (!$group->post || !$group->post->get_id()) {
                        return null;
                    }

                    $timestamp = get_post_meta($group->post->get_id(), '_article_helpful_last_yes_vote', true);

                    if (empty($timestamp)) {
                        return null;
                    }

                    return date('Y-m-d H:i:s', intval($timestamp));
                } );

            // Add article helpful latest no vote timestamp property
            $properties['article_helpful_latest_no'] = \Voxel\Dynamic_Data\Tag::Date('Article Helpful Latest No')
                ->render( function() use ( $group ) {
                    if (!$group->post || !$group->post->get_id()) {
                        return null;
                    }

                    $timestamp = get_post_meta($group->post->get_id(), '_article_helpful_last_no_vote', true);

                    if (empty($timestamp)) {
                        return null;
                    }

                    return date('Y-m-d H:i:s', intval($timestamp));
                } );
        }

        // Add feed position property (absolute position across all pages)
        if (class_exists('Voxel_Toolkit_Post_Position_Tracker')) {
            $properties['feed_position'] = \Voxel\Dynamic_Data\Tag::Number('Feed Position')
                ->render(function() use ($group) {
                    if (!$group->post || !$group->post->get_id()) {
                        return null;
                    }

                    return Voxel_Toolkit_Post_Position_Tracker::get_current_position();
                });
        }

        // Add is_team_member property if team members function is enabled
        if ($settings->is_function_enabled('team_members')) {
            $properties['is_team_member'] = \Voxel\Dynamic_Data\Tag::String('Is Team Member')
                ->render(function() use ($group) {
                    if (!$group->post || !$group->post->get_id()) {
                        return '';
                    }

                    if (!is_user_logged_in()) {
                        return '';
                    }

                    // Check if Voxel_Toolkit_Team_Members class exists
                    if (!class_exists('Voxel_Toolkit_Team_Members')) {
                        return '';
                    }

                    $team_members = Voxel_Toolkit_Team_Members::instance();
                    if (!$team_members) {
                        return '';
                    }

                    return $team_members->is_team_member($group->post->get_id()) ? '1' : '';
                });
        }

        // Add AI properties if AI Post Summary is enabled
        if ($settings->is_function_enabled('ai_post_summary')) {
            $properties['ai'] = \Voxel\Dynamic_Data\Tag::Object('AI')->properties(function() use ($group) {
                return [
                    'summary' => \Voxel\Dynamic_Data\Tag::String('AI Summary')
                        ->render(function() use ($group) {
                            if (!$group->post || !$group->post->get_id()) {
                                return '';
                            }

                            if (!class_exists('Voxel_Toolkit_AI_Post_Summary')) {
                                return '';
                            }

                            $summary = Voxel_Toolkit_AI_Post_Summary::instance()->get_summary($group->post->get_id());
                            return !empty($summary) ? esc_html($summary) : '';
                        }),
                ];
            });
        }

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
        $settings = Voxel_Toolkit_Settings::instance();

        // Register visitor location tags if enabled
        if ($settings->is_function_enabled('visitor_location')) {
            $properties['visitor'] = \Voxel\Dynamic_Data\Tag::Object('Visitor Information')->properties(function() {
                return [
                    'location' => \Voxel\Dynamic_Data\Tag::String('Full Location')
                        ->render(function() {
                            return Voxel_Toolkit_Visitor_Location::instance()->get_location();
                        }),
                    'city' => \Voxel\Dynamic_Data\Tag::String('City')
                        ->render(function() {
                            return Voxel_Toolkit_Visitor_Location::instance()->get_city();
                        }),
                    'state' => \Voxel\Dynamic_Data\Tag::String('State/Region')
                        ->render(function() {
                            return Voxel_Toolkit_Visitor_Location::instance()->get_state();
                        }),
                    'country' => \Voxel\Dynamic_Data\Tag::String('Country')
                        ->render(function() {
                            return Voxel_Toolkit_Visitor_Location::instance()->get_country();
                        }),
                    'latitude' => \Voxel\Dynamic_Data\Tag::String('Latitude')
                        ->render(function() {
                            return Voxel_Toolkit_Visitor_Location::instance()->get_latitude();
                        }),
                    'longitude' => \Voxel\Dynamic_Data\Tag::String('Longitude')
                        ->render(function() {
                            return Voxel_Toolkit_Visitor_Location::instance()->get_longitude();
                        }),
                ];
            });
        }

        // Check if options page is enabled
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
