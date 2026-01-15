<?php
/**
 * Social Proof Notifications
 *
 * Displays toast notifications showing recent Voxel app events
 * (new bookings, posts, orders, etc.) to create social proof and urgency.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Social_Proof {

    /**
     * Database table name
     */
    private $table_name;

    /**
     * Available events for social proof
     */
    private $available_events = array(
        'membership/user:registered' => array(
            'label' => 'New User Registration',
            'default_message' => '{user} just joined',
            'category' => 'membership',
        ),
        'post:created' => array(
            'label' => 'New Listing Created',
            'default_message' => '{user} just listed {post}',
            'category' => 'posts',
            'dynamic' => true, // Has post type variants
        ),
        'post:approved' => array(
            'label' => 'Listing Approved',
            'default_message' => 'New listing: {post}',
            'category' => 'posts',
            'dynamic' => true,
        ),
        'booking:placed' => array(
            'label' => 'New Booking',
            'default_message' => '{user} just booked {post}',
            'category' => 'bookings',
            'dynamic' => true,
        ),
        'booking:confirmed' => array(
            'label' => 'Booking Confirmed',
            'default_message' => 'Booking confirmed for {post}',
            'category' => 'bookings',
            'dynamic' => true,
        ),
        'customer:order_placed' => array(
            'label' => 'New Order',
            'default_message' => '{user} just placed an order',
            'category' => 'orders',
        ),
        'timeline/post:reviews/review:created' => array(
            'label' => 'New Review',
            'default_message' => '{user} left a review on {post}',
            'category' => 'reviews',
            'dynamic' => true,
        ),
        'timeline/followers/user-followed-event' => array(
            'label' => 'User Followed',
            'default_message' => '{user} just followed someone',
            'category' => 'social',
        ),
        'timeline/followers/post-followed-event' => array(
            'label' => 'Listing Followed',
            'default_message' => '{user} just followed {post}',
            'category' => 'social',
        ),
        'promotions/promotion:activated' => array(
            'label' => 'Listing Promoted',
            'default_message' => '{post} was just promoted',
            'category' => 'promotions',
        ),
    );

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'voxel_toolkit_social_proof';

        // Create table if needed
        add_action('admin_init', array($this, 'maybe_create_table'));

        // Hook into app events
        add_action('init', array($this, 'register_event_hooks'), 20);

        // AJAX endpoints
        add_action('wp_ajax_vt_social_proof_get', array($this, 'ajax_get_events'));
        add_action('wp_ajax_nopriv_vt_social_proof_get', array($this, 'ajax_get_events'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Render toast container
        add_action('wp_footer', array($this, 'render_container'));
    }

    /**
     * Create database table if it doesn't exist
     */
    public function maybe_create_table() {
        global $wpdb;

        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->table_name
        ));

        if ($table_exists) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_key VARCHAR(128) NOT NULL,
            event_data TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_key (event_key),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get settings
     */
    private function get_settings() {
        $settings = Voxel_Toolkit_Settings::instance();
        return $settings->get_function_settings('social_proof', array());
    }

    /**
     * Check if feature is enabled
     */
    private function is_enabled() {
        $settings = $this->get_settings();
        return !empty($settings['enabled']);
    }

    /**
     * Get enabled events
     */
    private function get_enabled_events() {
        $settings = $this->get_settings();
        $enabled = array();

        if (empty($settings['events'])) {
            return $enabled;
        }

        foreach ($settings['events'] as $event_key => $event_settings) {
            if (!empty($event_settings['enabled'])) {
                $enabled[] = $event_key;
            }
        }

        return $enabled;
    }

    /**
     * Get event settings
     */
    private function get_event_settings($event_key) {
        $settings = $this->get_settings();
        $defaults = $this->get_event_defaults($event_key);

        if (empty($settings['events'][$event_key])) {
            return $defaults;
        }

        return wp_parse_args($settings['events'][$event_key], $defaults);
    }

    /**
     * Get event defaults
     */
    private function get_event_defaults($event_key) {
        // Check for dynamic event (post type specific)
        $base_key = $this->get_base_event_key($event_key);

        $event_config = isset($this->available_events[$base_key])
            ? $this->available_events[$base_key]
            : array();

        return array(
            'enabled' => false,
            'message_template' => isset($event_config['default_message']) ? $event_config['default_message'] : '{user} performed an action',
            'show_avatar' => true,
            'show_link' => true,
            'show_time' => true,
        );
    }

    /**
     * Get base event key (strips post type prefix for dynamic events)
     */
    private function get_base_event_key($event_key) {
        // Handle post type specific events like "post-types/place/post:created"
        if (preg_match('/^post-types\/[^\/]+\/(.+)$/', $event_key, $matches)) {
            return $matches[1];
        }
        // Handle product type specific events like "product-types/booking/bookings/booking:placed"
        if (preg_match('/^product-types\/[^\/]+\/bookings\/(.+)$/', $event_key, $matches)) {
            return $matches[1];
        }
        return $event_key;
    }

    /**
     * Register event hooks
     */
    public function register_event_hooks() {
        if (!$this->is_enabled()) {
            return;
        }

        $enabled_events = $this->get_enabled_events();

        foreach ($enabled_events as $event_key) {
            add_action("voxel/app-events/{$event_key}", function($event) use ($event_key) {
                $this->log_event($event_key, $event);
            });
        }
    }

    /**
     * Log event to database
     */
    private function log_event($event_key, $event) {
        global $wpdb;

        $event_data = $this->extract_event_data($event_key, $event);

        $wpdb->insert(
            $this->table_name,
            array(
                'event_key' => $event_key,
                'event_data' => wp_json_encode($event_data),
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s')
        );

        // Cleanup old events (keep last 100)
        $this->cleanup_old_events();
    }

    /**
     * Extract event data
     */
    private function extract_event_data($event_key, $event) {
        $data = array(
            'time' => time(),
        );

        // Try to get user data
        $user = null;
        if (isset($event->author)) {
            $user = $event->author;
        } elseif (isset($event->customer)) {
            $user = $event->customer;
        } elseif (isset($event->user)) {
            $user = $event->user;
        }

        if ($user) {
            if (is_object($user)) {
                $data['user_name'] = method_exists($user, 'get_display_name')
                    ? $user->get_display_name()
                    : (isset($user->display_name) ? $user->display_name : 'Someone');

                $data['user_avatar'] = method_exists($user, 'get_avatar_url')
                    ? $user->get_avatar_url()
                    : get_avatar_url($user->ID ?? 0);

                $data['user_url'] = method_exists($user, 'get_link')
                    ? $user->get_link()
                    : '';
            }
        }

        // Try to get post data
        if (isset($event->post)) {
            $post = $event->post;
            if (is_object($post)) {
                $data['post_title'] = method_exists($post, 'get_title')
                    ? $post->get_title()
                    : (isset($post->post_title) ? $post->post_title : '');

                $data['post_url'] = method_exists($post, 'get_link')
                    ? $post->get_link()
                    : get_permalink($post->ID ?? 0);

                if (isset($post->post_type) && is_object($post->post_type)) {
                    $data['post_type'] = method_exists($post->post_type, 'get_label')
                        ? $post->post_type->get_label()
                        : '';
                }
            }
        }

        // Order/booking data
        if (isset($event->order)) {
            $data['order_id'] = method_exists($event->order, 'get_id')
                ? $event->order->get_id()
                : 0;
        }

        return $data;
    }

    /**
     * Cleanup old events
     */
    private function cleanup_old_events($keep = 100) {
        global $wpdb;

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        if ($count > $keep) {
            $delete_count = $count - $keep;
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->table_name} ORDER BY created_at ASC LIMIT %d",
                $delete_count
            ));
        }
    }

    /**
     * AJAX handler for getting events
     */
    public function ajax_get_events() {
        $last_id = isset($_GET['last_id']) ? absint($_GET['last_id']) : 0;
        $limit = isset($_GET['limit']) ? min(20, absint($_GET['limit'])) : 10;

        global $wpdb;

        // Get events
        if ($last_id > 0) {
            // Polling for new events
            $events = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE id > %d
                 ORDER BY created_at DESC
                 LIMIT %d",
                $last_id,
                $limit
            ));
        } else {
            // Initial fetch - get recent events
            $events = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 ORDER BY created_at DESC
                 LIMIT %d",
                $limit
            ));
        }

        // Get max ID for polling
        $max_id = $wpdb->get_var("SELECT MAX(id) FROM {$this->table_name}");

        // Format events
        $formatted = array();
        foreach ($events as $event) {
            $formatted[] = $this->format_event_for_display($event);
        }

        wp_send_json_success(array(
            'events' => $formatted,
            'last_id' => $max_id ? intval($max_id) : $last_id,
        ));
    }

    /**
     * Format event for display
     */
    private function format_event_for_display($event) {
        $data = json_decode($event->event_data, true);
        $settings = $this->get_event_settings($event->event_key);

        // Get function settings for default avatar
        $function_settings = Voxel_Toolkit_Settings::instance()->get_function_settings('social_proof', array());
        $default_avatar = !empty($function_settings['default_avatar'])
            ? $function_settings['default_avatar']
            : get_avatar_url(0, array('size' => 96, 'default' => 'mystery'));

        // Build message from template
        $message = $settings['message_template'];
        $message = str_replace('{user}', isset($data['user_name']) ? $data['user_name'] : __('Someone', 'voxel-toolkit'), $message);
        $message = str_replace('{post}', isset($data['post_title']) ? $data['post_title'] : '', $message);

        $time_ago = isset($data['time']) ? human_time_diff($data['time']) . ' ' . __('ago', 'voxel-toolkit') : '';
        $message = str_replace('{time}', $time_ago, $message);

        // Use user avatar if available, otherwise use default avatar
        $user_avatar = '';
        if ($settings['show_avatar']) {
            $user_avatar = !empty($data['user_avatar']) ? $data['user_avatar'] : $default_avatar;
        }

        return array(
            'id' => intval($event->id),
            'message' => $message,
            'user_avatar' => $user_avatar,
            'post_url' => $settings['show_link'] && isset($data['post_url']) ? $data['post_url'] : '',
            'time_ago' => $settings['show_time'] ? $time_ago : '',
            'event_key' => $event->event_key,
        );
    }

    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        if (!$this->is_enabled()) {
            return;
        }

        // Don't load in admin or Elementor editor
        if (is_admin()) {
            return;
        }

        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            return;
        }

        $settings = $this->get_settings();

        // Check if we should hide on mobile
        if (!empty($settings['hide_on_mobile']) && wp_is_mobile()) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'voxel-toolkit-social-proof',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/social-proof.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        // Inline styles from settings
        $custom_css = $this->generate_custom_css($settings);
        if ($custom_css) {
            wp_add_inline_style('voxel-toolkit-social-proof', $custom_css);
        }

        // JavaScript
        wp_enqueue_script(
            'voxel-toolkit-social-proof',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/social-proof.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Prepare boost settings
        $boost_names = isset($settings['boost_names']) ? array_filter(array_map('trim', explode("\n", $settings['boost_names']))) : array();
        $boost_listings = isset($settings['boost_listings']) ? array_filter(array_map('trim', explode("\n", $settings['boost_listings']))) : array();
        $boost_messages = isset($settings['boost_messages']) ? $settings['boost_messages'] : array(
            'booking' => '{name} just booked {listing}',
            'signup' => '{name} just joined',
            'review' => '{name} left a review on {listing}',
        );

        // Default avatar - use custom if set, otherwise WordPress mystery avatar
        $default_avatar = !empty($settings['default_avatar'])
            ? $settings['default_avatar']
            : get_avatar_url(0, array('size' => 96, 'default' => 'mystery'));

        // Localize script
        wp_localize_script('voxel-toolkit-social-proof', 'voxelSocialProof', array(
            'enabled' => true,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'position' => isset($settings['position']) ? $settings['position'] : 'bottom-left',
            'displayDuration' => isset($settings['display_duration']) ? intval($settings['display_duration']) : 5,
            'delayBetween' => isset($settings['delay_between']) ? intval($settings['delay_between']) : 3,
            'maxEvents' => isset($settings['max_events']) ? intval($settings['max_events']) : 10,
            'pollInterval' => isset($settings['poll_interval']) ? intval($settings['poll_interval']) : 30,
            'animation' => isset($settings['animation']) ? $settings['animation'] : 'slide',
            'showCloseButton' => !empty($settings['show_close_button']),
            // Activity Boost settings
            'boostEnabled' => !empty($settings['boost_enabled']),
            'boostMode' => isset($settings['boost_mode']) ? $settings['boost_mode'] : 'fill_gaps',
            'boostNames' => !empty($boost_names) ? array_values($boost_names) : array('Emma', 'James', 'Sofia', 'Oliver', 'Mia', 'Lucas', 'Ava', 'Noah'),
            'boostListings' => !empty($boost_listings) ? array_values($boost_listings) : array('Beach House', 'Mountain Cabin', 'City Apartment', 'Lake Cottage'),
            'boostMessages' => $boost_messages,
            'defaultAvatar' => $default_avatar,
            'i18n' => array(
                'minute' => __('minute', 'voxel-toolkit'),
                'minutes' => __('minutes', 'voxel-toolkit'),
                'hour' => __('hour', 'voxel-toolkit'),
                'hours' => __('hours', 'voxel-toolkit'),
                'day' => __('day', 'voxel-toolkit'),
                'days' => __('days', 'voxel-toolkit'),
                'ago' => __('ago', 'voxel-toolkit'),
                'justNow' => __('just now', 'voxel-toolkit'),
            ),
        ));
    }

    /**
     * Generate custom CSS from settings
     */
    private function generate_custom_css($settings) {
        $css = '';

        // Background color
        if (!empty($settings['background_color'])) {
            $css .= ".vt-social-proof-toast { background-color: {$settings['background_color']}; }";
        }

        // Text color
        if (!empty($settings['text_color'])) {
            $css .= ".vt-social-proof-toast { color: {$settings['text_color']}; }";
            $css .= ".vt-social-proof-toast .vt-sp-message { color: {$settings['text_color']}; }";
        }

        // Border radius
        if (!empty($settings['border_radius'])) {
            $css .= ".vt-social-proof-toast { border-radius: {$settings['border_radius']}px; }";
        }

        // Avatar size
        if (!empty($settings['avatar_size'])) {
            $css .= ".vt-social-proof-toast .vt-sp-avatar { width: {$settings['avatar_size']}px; height: {$settings['avatar_size']}px; }";
            $css .= ".vt-social-proof-toast .vt-sp-avatar img { width: {$settings['avatar_size']}px; height: {$settings['avatar_size']}px; }";
        }

        return $css;
    }

    /**
     * Render toast container in footer
     */
    public function render_container() {
        if (!$this->is_enabled()) {
            return;
        }

        // Don't render in admin or Elementor editor
        if (is_admin()) {
            return;
        }

        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            return;
        }

        $settings = $this->get_settings();

        // Check if we should hide on mobile
        if (!empty($settings['hide_on_mobile']) && wp_is_mobile()) {
            return;
        }

        $position = isset($settings['position']) ? $settings['position'] : 'bottom-left';
        ?>
        <div id="vt-social-proof-container" class="vt-sp-position-<?php echo esc_attr($position); ?>">
            <div class="vt-social-proof-toast vt-sp-hidden">
                <div class="vt-sp-avatar">
                    <img src="" alt="">
                </div>
                <div class="vt-sp-content">
                    <div class="vt-sp-message"></div>
                    <div class="vt-sp-time"></div>
                </div>
                <?php if (!empty($settings['show_close_button'])) : ?>
                <button class="vt-sp-close" aria-label="<?php esc_attr_e('Close', 'voxel-toolkit'); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get available events for settings
     */
    public static function get_available_events() {
        $instance = new self();
        $events = array();

        // Add static events
        foreach ($instance->available_events as $key => $config) {
            if (empty($config['dynamic'])) {
                $events[$key] = $config;
            }
        }

        // Add dynamic post type events
        $post_types = get_option('voxel:post_types', array());
        if (is_string($post_types)) {
            $post_types = json_decode($post_types, true);
        }

        if (is_array($post_types)) {
            foreach ($post_types as $pt_key => $pt_config) {
                $pt_label = isset($pt_config['settings']['singular'])
                    ? $pt_config['settings']['singular']
                    : ucfirst($pt_key);

                // Post created
                $events["post-types/{$pt_key}/post:created"] = array(
                    'label' => sprintf(__('%s Created', 'voxel-toolkit'), $pt_label),
                    'default_message' => "{user} just listed a new {$pt_label}",
                    'category' => 'posts',
                );

                // Post approved
                $events["post-types/{$pt_key}/post:approved"] = array(
                    'label' => sprintf(__('%s Approved', 'voxel-toolkit'), $pt_label),
                    'default_message' => "New {$pt_label}: {post}",
                    'category' => 'posts',
                );

                // Reviews
                $events["post-types/{$pt_key}/timeline/post:reviews/review:created"] = array(
                    'label' => sprintf(__('New %s Review', 'voxel-toolkit'), $pt_label),
                    'default_message' => "{user} left a review on {post}",
                    'category' => 'reviews',
                );
            }
        }

        return $events;
    }
}
