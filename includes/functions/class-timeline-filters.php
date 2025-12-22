<?php
/**
 * Timeline Filters Function
 *
 * Adds custom filtering options to Voxel Timeline widgets
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Timeline_Filters {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Track which widgets have unanswered enabled
     */
    private $enabled_widget_ids = array();

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register custom AJAX endpoint for unanswered posts (using Voxel's AJAX system)
        add_action('voxel_ajax_vt_timeline.unanswered', array($this, 'get_unanswered_feed'));
        add_action('voxel_ajax_nopriv_vt_timeline.unanswered', array($this, 'get_unanswered_feed'));

        // Enqueue frontend scripts to handle custom order types
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Add "Unanswered" filter toggle to Elementor Timeline widget settings
        // Using generic hook pattern which is proven to work (receives widget, section_id, args)
        add_action('elementor/element/before_section_end', array($this, 'add_elementor_order_option'), 10, 3);

        // Hook into widget render to collect settings (runs during page render)
        add_action('elementor/frontend/widget/before_render', array($this, 'before_widget_render'));

        // Output widget config as inline script before our main JS
        add_action('wp_footer', array($this, 'output_widget_config'), 5);
    }

    /**
     * Check widget settings before render
     */
    public function before_widget_render($widget) {
        if ($widget->get_name() !== 'ts-timeline') {
            return;
        }

        $settings = $widget->get_settings_for_display();

        // Check if our toggle is enabled
        if (!empty($settings['vt_enable_unanswered_filter']) && $settings['vt_enable_unanswered_filter'] === 'yes') {
            $label = !empty($settings['vt_unanswered_label'])
                ? $settings['vt_unanswered_label']
                : __('Unanswered', 'voxel-toolkit');

            $this->enabled_widget_ids[$widget->get_id()] = array(
                'label' => $label,
                'order' => 'unanswered',
                'time' => 'all_time',
            );
        }
    }

    /**
     * Output config for widgets that have unanswered enabled
     * This runs early in wp_footer (priority 5) before scripts are printed (priority 20)
     */
    public function output_widget_config() {
        // Only output if we have widgets with the filter enabled
        if (empty($this->enabled_widget_ids)) {
            return;
        }

        // Get toolkit filters config
        $filters = $this->get_enabled_filters();
        if (empty($filters['unanswered'])) {
            return;
        }

        ?>
        <script type="text/javascript">
        window.vtTimelineWidgetConfig = <?php echo wp_json_encode($this->enabled_widget_ids); ?>;
        </script>
        <?php
    }

    /**
     * Add "Unanswered" filter toggle to Elementor Timeline widget settings
     */
    public function add_elementor_order_option($widget, $section_id, $args) {
        // Only add to Timeline widget's settings section
        if ($widget->get_name() !== 'ts-timeline') {
            return;
        }

        if ($section_id !== 'ts_timeline_settings') {
            return;
        }

        // Only add if unanswered filter is enabled in toolkit settings
        $filters = $this->get_enabled_filters();
        if (empty($filters['unanswered'])) {
            return;
        }

        $label = $filters['unanswered']['label'];

        // Add our own toggle control instead of modifying the complex Repeater
        $widget->add_control(
            'vt_enable_unanswered_filter',
            array(
                'label' => sprintf(__('Enable "%s" Filter (VT)', 'voxel-toolkit'), $label),
                'description' => __('Adds an additional ordering option from Voxel Toolkit to show posts with no replies.', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'separator' => 'before',
            )
        );

        // Custom label for this instance
        $widget->add_control(
            'vt_unanswered_label',
            array(
                'label' => __('Custom Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => $label,
                'placeholder' => $label,
                'condition' => array(
                    'vt_enable_unanswered_filter' => 'yes',
                ),
            )
        );
    }

    /**
     * Get enabled filters from settings
     */
    private function get_enabled_filters() {
        $settings = Voxel_Toolkit_Settings::instance()->get_function_settings('timeline_filters', array());
        $filters = array();

        if (!empty($settings['enable_unanswered'])) {
            $filters['unanswered'] = array(
                'label' => !empty($settings['unanswered_label'])
                    ? $settings['unanswered_label']
                    : __('Unanswered', 'voxel-toolkit'),
                'order' => 'unanswered',
                'time' => 'all_time',
            );
        }

        return $filters;
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        $filters = $this->get_enabled_filters();

        // Only enqueue if we have enabled filters
        if (empty($filters)) {
            return;
        }

        $js_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/js/timeline-filters.js';

        wp_enqueue_script(
            'vt-timeline-filters',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/timeline-filters.js',
            array('jquery'),
            file_exists($js_file) ? filemtime($js_file) : VOXEL_TOOLKIT_VERSION,
            true
        );

        // Localize script with AJAX URL and filter config
        wp_localize_script('vt-timeline-filters', 'vtTimelineFilters', array(
            'ajaxUrl' => home_url('/?vx=1&action=vt_timeline.unanswered'),
            'filters' => $filters,
        ));
    }

    /**
     * AJAX handler for unanswered posts feed
     */
    public function get_unanswered_feed() {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? null) !== 'GET') {
                throw new \Exception(__('Invalid request method.', 'voxel-toolkit'));
            }

            // Check if Voxel Status class exists
            if (!class_exists('\Voxel\Timeline\Status')) {
                throw new \Exception(__('Voxel Timeline not available.', 'voxel-toolkit'));
            }

            global $wpdb;
            $table = $wpdb->prefix . 'voxel_timeline';

            // Get request parameters
            $page = absint($_REQUEST['page'] ?? 1);
            $per_page = absint(\Voxel\get('settings.timeline.posts.per_page', 10));
            $post_id = !empty($_REQUEST['post_id']) ? absint($_REQUEST['post_id']) : null;
            $user_id = !empty($_REQUEST['user_id']) ? absint($_REQUEST['user_id']) : null;
            $mode = sanitize_text_field($_REQUEST['mode'] ?? '');

            // Validate mode
            $allowed_modes = array('post_reviews', 'post_wall', 'post_timeline', 'author_timeline', 'user_feed', 'global_feed');
            if (!in_array($mode, $allowed_modes, true)) {
                throw new \Exception(__('Invalid timeline mode.', 'voxel-toolkit'));
            }

            // Build WHERE clause
            $where = array(
                "reply_count = 0",
                "moderation = 1",
                "repost_of IS NULL"
            );
            $params = array();

            // Mode-specific conditions
            switch ($mode) {
                case 'post_reviews':
                    if (!$post_id) {
                        throw new \Exception(__('Post ID required for reviews.', 'voxel-toolkit'));
                    }
                    $where[] = "feed = 'post_reviews'";
                    $where[] = "post_id = %d";
                    $params[] = $post_id;
                    break;

                case 'post_wall':
                    if (!$post_id) {
                        throw new \Exception(__('Post ID required for wall.', 'voxel-toolkit'));
                    }
                    $where[] = "feed = 'post_wall'";
                    $where[] = "post_id = %d";
                    $params[] = $post_id;
                    break;

                case 'post_timeline':
                    if (!$post_id) {
                        throw new \Exception(__('Post ID required for timeline.', 'voxel-toolkit'));
                    }
                    $where[] = "feed = 'post_timeline'";
                    $where[] = "post_id = %d";
                    $params[] = $post_id;
                    break;

                case 'author_timeline':
                    if (!$user_id) {
                        throw new \Exception(__('User ID required for author timeline.', 'voxel-toolkit'));
                    }
                    $where[] = "(feed IN ('user_timeline', 'post_wall', 'post_reviews'))";
                    $where[] = "user_id = %d";
                    $params[] = $user_id;
                    break;

                case 'user_feed':
                    $current_user_id = get_current_user_id();
                    if (!$current_user_id) {
                        return wp_send_json(array(
                            'success' => true,
                            'data' => array(),
                            'has_more' => false,
                        ));
                    }
                    // Get posts from followed users/posts
                    $user_feed_clause = $this->get_user_feed_where_clause($current_user_id);
                    if ($user_feed_clause) {
                        $where[] = $user_feed_clause;
                    }
                    break;

                case 'global_feed':
                    // No additional filters for global feed
                    break;
            }

            // Calculate pagination
            $offset = ($page - 1) * $per_page;
            $limit = $per_page + 1; // Fetch one extra to detect "has_more"

            // Build and execute query
            $where_sql = implode(' AND ', $where);

            $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
            $params[] = $limit;
            $params[] = $offset;

            $query = $wpdb->prepare($sql, $params);
            $results = $wpdb->get_results($query, ARRAY_A);

            if (!is_array($results)) {
                $results = array();
            }

            // Check if there are more results
            $has_more = count($results) > $per_page;
            if ($has_more) {
                array_pop($results);
            }

            // Convert to Status objects and get frontend config
            $statuses = array();
            foreach ($results as $row) {
                $status = \Voxel\Timeline\Status::get($row);
                if ($status) {
                    $statuses[] = $status->get_frontend_config(array(
                        'timeline_mode' => $mode,
                    ));
                }
            }

            // Build response (matching Voxel's format)
            return wp_send_json(array(
                'success' => true,
                'data' => $statuses,
                'has_more' => $has_more,
                'meta' => array(
                    'review_config' => array(),
                ),
            ));

        } catch (\Exception $e) {
            return wp_send_json(array(
                'success' => false,
                'message' => $e->getMessage(),
            ));
        }
    }

    /**
     * Build WHERE clause for user feed (followed users/posts)
     */
    private function get_user_feed_where_clause($user_id) {
        global $wpdb;
        $followers_table = $wpdb->prefix . 'voxel_followers';
        $timeline_table = $wpdb->prefix . 'voxel_timeline';

        // Check if followers table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $followers_table
        ));

        if (!$table_exists) {
            // If no followers table, just show user's own posts
            return $wpdb->prepare("user_id = %d", $user_id);
        }

        // Include posts from:
        // 1. The user themselves
        // 2. Users they follow
        // 3. Posts they follow
        return $wpdb->prepare("(
            user_id = %d
            OR EXISTS (
                SELECT 1 FROM {$followers_table} f
                WHERE f.follower_type = 'user'
                AND f.follower_id = %d
                AND f.status = 1
                AND (
                    (f.object_type = 'user' AND f.object_id = {$timeline_table}.user_id)
                    OR (f.object_type = 'post' AND f.object_id = {$timeline_table}.post_id)
                )
            )
        )", $user_id, $user_id);
    }
}
