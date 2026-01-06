<?php
/**
 * Plugin Name: VT Stats Receiver
 * Description: Receives anonymous usage stats from Voxel Toolkit installations. Only for codewattz.com.
 * Version: 1.1.0
 * Author: CodeWattz
 * Author URI: https://codewattz.com
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VT_Stats_Receiver {

    const TABLE_NAME = 'vt_plugin_stats';

    private static $instance = null;

    /**
     * Friendly names mapping for functions
     */
    private $function_names = array(
        'auto_verify_posts' => 'Auto Verify Posts',
        'claim_listing' => 'Claim Listing',
        'quick_search' => 'Quick Search',
        'duplicate_post' => 'Duplicate Post',
        'featured_posts' => 'Featured Posts',
        'share_menu' => 'Share Menu',
        'share_count' => 'Share Count',
        'analytics_integration' => 'Analytics Integration',
        'google_analytics' => 'Google Analytics',
        'sms_notifications' => 'SMS Notifications',
        'membership_notifications' => 'Membership Notifications',
        'admin_notifications' => 'Admin Notifications',
        'submission_reminder' => 'Submission Reminder',
        'suggest_edits' => 'Suggest Edits',
        'verified_reviews' => 'Verified Reviews',
        'ai_review_summary' => 'AI Review Summary',
        'visitor_location' => 'Visitor Location',
        'guest_view' => 'Guest View',
        'light_mode' => 'Light Mode',
        'intl_phone' => 'International Phone',
        'media_paste' => 'Media Paste',
        'calendar_week_start' => 'Calendar Week Start',
        'redirect_posts' => 'Redirect Posts',
        'delete_post_media' => 'Delete Post Media',
        'disable_auto_updates' => 'Disable Auto Updates',
        'disable_gutenberg' => 'Disable Gutenberg',
        'duplicate_title_checker' => 'Duplicate Title Checker',
        'export_orders' => 'Export Orders',
        'field_formatters' => 'Field Formatters',
        'fluent_forms_post_author' => 'Fluent Forms Post Author',
        'pending_posts_badge' => 'Pending Posts Badge',
        'post_fields_anywhere' => 'Post Fields Anywhere',
        'post_position_tracker' => 'Post Position Tracker',
        'pre_approve_posts' => 'Pre-Approve Posts',
        'show_field_description' => 'Show Field Description',
        'sticky_admin_bar' => 'Sticky Admin Bar',
        'widget_css_injector' => 'Widget CSS Injector',
        'admin_bar_publish' => 'Admin Bar Publish',
        'admin_columns' => 'Admin Columns',
        'admin_menu_hide' => 'Admin Menu Hide',
        'admin_taxonomy_search' => 'Admin Taxonomy Search',
        'auto_promotion' => 'Auto Promotion',
        'custom_submission_messages' => 'Custom Submission Messages',
        'message_moderation' => 'Message Moderation',
        'timeline_filters' => 'Timeline Filters',
        'add_category' => 'Add Category',
        'advanced_phone_input' => 'Advanced Phone Input',
        'anonymous_timeline' => 'Anonymous Timeline',
        'compare_posts' => 'Compare Posts',
        'enhanced_post_relation' => 'Enhanced Post Relation',
        'enhanced_tinymce_editor' => 'Enhanced TinyMCE Editor',
        'external_link_warning' => 'External Link Warning',
        'helpful_votes_sorting' => 'Helpful Votes Sorting',
        'route_planner' => 'Route Planner',
        'rsvp_system' => 'RSVP System',
        'saved_search' => 'Saved Search',
        'social_proof' => 'Social Proof',
        'team_members' => 'Team Members',
        'timeline_dynamic_tags' => 'Timeline Dynamic Tags',
        'timeline_reply_summary' => 'Timeline Reply Summary',
        'tools_page' => 'Tools Page',
        'synonym_search' => 'Synonym Search',
        'promotion_create_form' => 'Promotion Create Form',
    );

    /**
     * Friendly names mapping for widgets
     */
    private $widget_names = array(
        'active_filters' => 'Active Filters',
        'article_helpful' => 'Article Helpful',
        'breadcrumbs' => 'Breadcrumbs',
        'campaign_progress' => 'Campaign Progress',
        'duplicate_post' => 'Duplicate Post',
        'media_gallery' => 'Media Gallery',
        'messenger' => 'Messenger',
        'onboarding' => 'Onboarding',
        'poll_display' => 'Poll Display',
        'prev_next' => 'Previous/Next',
        'profile_progress' => 'Profile Progress',
        'reading_time' => 'Reading Time',
        'review_collection' => 'Review Collection',
        'suggest_edits' => 'Suggest Edits',
        'table_of_contents' => 'Table of Contents',
        'timeline_photos' => 'Timeline Photos',
        'users_purchased' => 'Users Purchased',
        'weather' => 'Weather',
        'business_hours' => 'Business Hours',
        'advanced_gallery' => 'Advanced Gallery',
    );

    /**
     * Friendly names mapping for post fields
     */
    private $post_field_names = array(
        'poll' => 'Poll Field',
        'color_picker' => 'Color Picker',
        'icon_picker' => 'Icon Picker',
    );

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Register REST API endpoint
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Add dashboard menu
        add_action('admin_menu', array($this, 'add_dashboard_menu'));

        // Ensure table exists
        add_action('admin_init', array($this, 'maybe_create_table'));

        // AJAX handlers
        add_action('wp_ajax_vt_stats_export_csv', array($this, 'ajax_export_csv'));
        add_action('wp_ajax_vt_stats_clear_data', array($this, 'ajax_clear_data'));
    }

    /**
     * Get friendly name for a function
     */
    private function get_friendly_name($key, $type = 'function') {
        switch ($type) {
            case 'widget':
                return isset($this->widget_names[$key]) ? $this->widget_names[$key] : ucwords(str_replace('_', ' ', $key));
            case 'post_field':
                return isset($this->post_field_names[$key]) ? $this->post_field_names[$key] : ucwords(str_replace('_', ' ', $key));
            default:
                return isset($this->function_names[$key]) ? $this->function_names[$key] : ucwords(str_replace('_', ' ', $key));
        }
    }

    /**
     * Plugin activation - create table
     */
    public static function activate() {
        self::create_table();
    }

    // =========================================
    // REST API - Receive Stats
    // =========================================

    public function register_rest_routes() {
        // Receive stats
        register_rest_route('voxel-toolkit/v1', '/stats', array(
            'methods' => 'POST',
            'callback' => array($this, 'receive_stats'),
            'permission_callback' => '__return_true',
        ));

        // Delete stats (for opt-out)
        register_rest_route('voxel-toolkit/v1', '/stats/delete', array(
            'methods' => 'POST',
            'callback' => array($this, 'delete_stats'),
            'permission_callback' => '__return_true',
        ));
    }

    public function receive_stats($request) {
        $data = $request->get_json_params();

        if (empty($data['site_key'])) {
            return new WP_Error('invalid_data', 'Missing required fields', array('status' => 400));
        }

        // Rate limiting - max 1 update per minute per site
        $last_update = $this->get_last_update($data['site_key']);
        if ($last_update && (time() - strtotime($last_update)) < 60) {
            return new WP_Error('rate_limited', 'Too many requests', array('status' => 429));
        }

        $this->save_site_stats($data);

        return array('success' => true);
    }

    /**
     * Delete stats for a site (called when user opts out)
     */
    public function delete_stats($request) {
        $data = $request->get_json_params();

        if (empty($data['site_key'])) {
            return new WP_Error('invalid_data', 'Missing site_key', array('status' => 400));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $site_key = sanitize_text_field($data['site_key']);

        $deleted = $wpdb->delete(
            $table_name,
            array('site_key' => $site_key),
            array('%s')
        );

        if ($deleted) {
            return array('success' => true, 'message' => 'Data deleted');
        } else {
            return array('success' => true, 'message' => 'No data found for this site');
        }
    }

    private function save_site_stats($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $site_key = sanitize_text_field($data['site_key']);
        $enabled_functions = isset($data['enabled_functions']) ? json_encode($data['enabled_functions']) : '[]';
        $enabled_widgets = isset($data['enabled_widgets']) ? json_encode($data['enabled_widgets']) : '[]';
        $enabled_post_fields = isset($data['enabled_post_fields']) ? json_encode($data['enabled_post_fields']) : '[]';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE site_key = %s",
            $site_key
        ));

        if ($existing) {
            $wpdb->update(
                $table_name,
                array(
                    'enabled_functions' => $enabled_functions,
                    'enabled_widgets' => $enabled_widgets,
                    'enabled_post_fields' => $enabled_post_fields,
                    'last_updated' => current_time('mysql'),
                ),
                array('site_key' => $site_key),
                array('%s', '%s', '%s', '%s'),
                array('%s')
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'site_key' => $site_key,
                    'enabled_functions' => $enabled_functions,
                    'enabled_widgets' => $enabled_widgets,
                    'enabled_post_fields' => $enabled_post_fields,
                    'last_updated' => current_time('mysql'),
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    private function get_last_update($site_key) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT last_updated FROM $table_name WHERE site_key = %s",
            $site_key
        ));
    }

    // =========================================
    // DATABASE
    // =========================================

    public function maybe_create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            self::create_table();
        } else {
            $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
            if (!in_array('enabled_post_fields', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN enabled_post_fields text AFTER enabled_widgets");
            }
        }
    }

    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_key varchar(64) NOT NULL,
            enabled_functions text,
            enabled_widgets text,
            enabled_post_fields text,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY site_key (site_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // =========================================
    // DASHBOARD
    // =========================================

    public function add_dashboard_menu() {
        add_menu_page(
            __('VT Stats', 'vt-stats-receiver'),
            __('VT Stats', 'vt-stats-receiver'),
            'manage_options',
            'vt-stats-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-chart-bar',
            100
        );
    }

    public function render_dashboard_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $sites = $wpdb->get_results("SELECT * FROM $table_name ORDER BY last_updated DESC");
        $total_sites = count($sites);

        $function_counts = array();
        $widget_counts = array();
        $post_field_counts = array();

        // Debug: show raw data if requested
        $show_debug = isset($_GET['debug']);

        foreach ($sites as $site) {
            $functions = json_decode($site->enabled_functions, true) ?: array();
            $widgets = json_decode($site->enabled_widgets, true) ?: array();
            $post_fields = isset($site->enabled_post_fields) ? (json_decode($site->enabled_post_fields, true) ?: array()) : array();

            foreach ($functions as $func) {
                // Normalize to friendly name
                $friendly_name = $this->get_friendly_name($func, 'function');
                if (!isset($function_counts[$friendly_name])) $function_counts[$friendly_name] = 0;
                $function_counts[$friendly_name]++;
            }
            foreach ($widgets as $widget) {
                // Normalize to friendly name
                $friendly_name = $this->get_friendly_name($widget, 'widget');
                if (!isset($widget_counts[$friendly_name])) $widget_counts[$friendly_name] = 0;
                $widget_counts[$friendly_name]++;
            }
            foreach ($post_fields as $field) {
                // Normalize to friendly name
                $friendly_name = $this->get_friendly_name($field, 'post_field');
                if (!isset($post_field_counts[$friendly_name])) $post_field_counts[$friendly_name] = 0;
                $post_field_counts[$friendly_name]++;
            }
        }

        arsort($function_counts);
        arsort($widget_counts);
        arsort($post_field_counts);

        // Calculate totals
        $total_function_uses = array_sum($function_counts);
        $total_widget_uses = array_sum($widget_counts);
        $total_field_uses = array_sum($post_field_counts);

        ?>
        <div class="wrap vt-stats-dashboard">
            <div class="vt-stats-header">
                <div class="vt-stats-header-content">
                    <h1>
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php esc_html_e('Voxel Toolkit Usage Stats', 'vt-stats-receiver'); ?>
                    </h1>
                    <p class="vt-stats-subtitle"><?php esc_html_e('Anonymous feature usage data from connected sites', 'vt-stats-receiver'); ?></p>
                </div>
                <div class="vt-stats-header-actions">
                    <button type="button" class="button button-primary" id="vt-export-csv">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export CSV', 'vt-stats-receiver'); ?>
                    </button>
                    <a href="<?php echo esc_url(add_query_arg('debug', '1')); ?>" class="button">
                        <span class="dashicons dashicons-database"></span>
                        <?php esc_html_e('Debug View', 'vt-stats-receiver'); ?>
                    </a>
                </div>
            </div>

            <div class="vt-stats-summary">
                <div class="vt-stats-card vt-stats-card-primary">
                    <div class="vt-stats-card-icon">
                        <span class="dashicons dashicons-admin-multisite"></span>
                    </div>
                    <div class="vt-stats-card-content">
                        <span class="vt-stats-number"><?php echo esc_html($total_sites); ?></span>
                        <span class="vt-stats-label"><?php esc_html_e('Connected Sites', 'vt-stats-receiver'); ?></span>
                    </div>
                </div>
                <div class="vt-stats-card vt-stats-card-functions">
                    <div class="vt-stats-card-icon">
                        <span class="dashicons dashicons-admin-tools"></span>
                    </div>
                    <div class="vt-stats-card-content">
                        <span class="vt-stats-number"><?php echo count($function_counts); ?></span>
                        <span class="vt-stats-label"><?php esc_html_e('Functions Used', 'vt-stats-receiver'); ?></span>
                        <span class="vt-stats-sublabel"><?php printf(__('%d total activations', 'vt-stats-receiver'), $total_function_uses); ?></span>
                    </div>
                </div>
                <div class="vt-stats-card vt-stats-card-widgets">
                    <div class="vt-stats-card-icon">
                        <span class="dashicons dashicons-screenoptions"></span>
                    </div>
                    <div class="vt-stats-card-content">
                        <span class="vt-stats-number"><?php echo count($widget_counts); ?></span>
                        <span class="vt-stats-label"><?php esc_html_e('Widgets Used', 'vt-stats-receiver'); ?></span>
                        <span class="vt-stats-sublabel"><?php printf(__('%d total activations', 'vt-stats-receiver'), $total_widget_uses); ?></span>
                    </div>
                </div>
                <div class="vt-stats-card vt-stats-card-fields">
                    <div class="vt-stats-card-icon">
                        <span class="dashicons dashicons-forms"></span>
                    </div>
                    <div class="vt-stats-card-content">
                        <span class="vt-stats-number"><?php echo count($post_field_counts); ?></span>
                        <span class="vt-stats-label"><?php esc_html_e('Post Fields Used', 'vt-stats-receiver'); ?></span>
                        <span class="vt-stats-sublabel"><?php printf(__('%d total activations', 'vt-stats-receiver'), $total_field_uses); ?></span>
                    </div>
                </div>
            </div>

            <?php if ($show_debug) : ?>
            <div class="vt-stats-debug">
                <h2>
                    <span class="dashicons dashicons-database"></span>
                    <?php esc_html_e('Raw Database Contents', 'vt-stats-receiver'); ?>
                    <a href="<?php echo esc_url(remove_query_arg('debug')); ?>" class="button button-small" style="margin-left: 10px;">
                        <?php esc_html_e('Hide Debug', 'vt-stats-receiver'); ?>
                    </a>
                    <button type="button" class="button button-small button-link-delete" id="vt-clear-data" style="margin-left: 10px;">
                        <?php esc_html_e('Clear All Data', 'vt-stats-receiver'); ?>
                    </button>
                </h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th style="width: 120px;">Site Key</th>
                            <th>Functions</th>
                            <th>Widgets</th>
                            <th>Post Fields</th>
                            <th style="width: 150px;">Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sites as $site) : ?>
                            <tr>
                                <td><?php echo esc_html($site->id); ?></td>
                                <td><code><?php echo esc_html(substr($site->site_key, 0, 8)); ?>...</code></td>
                                <td><small><?php echo esc_html($site->enabled_functions); ?></small></td>
                                <td><small><?php echo esc_html($site->enabled_widgets ?: '[]'); ?></small></td>
                                <td><small><?php echo esc_html($site->enabled_post_fields ?: '[]'); ?></small></td>
                                <td><?php echo esc_html($site->last_updated); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="vt-stats-grid">
                <?php $this->render_stats_table('Functions', $function_counts, $total_sites, 'function', '#2271b1'); ?>
                <?php $this->render_stats_table('Widgets', $widget_counts, $total_sites, 'widget', '#00a32a'); ?>
                <?php $this->render_stats_table('Post Fields', $post_field_counts, $total_sites, 'post_field', '#9b59b6'); ?>
            </div>
        </div>

        <style>
            .vt-stats-dashboard {
                max-width: 1600px;
                margin-right: 20px;
            }

            /* Header */
            .vt-stats-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 25px;
                padding-bottom: 20px;
                border-bottom: 1px solid #c3c4c7;
            }
            .vt-stats-header h1 {
                display: flex;
                align-items: center;
                gap: 10px;
                margin: 0;
                font-size: 23px;
                font-weight: 400;
            }
            .vt-stats-header h1 .dashicons {
                font-size: 28px;
                width: 28px;
                height: 28px;
                color: #2271b1;
            }
            .vt-stats-subtitle {
                margin: 5px 0 0 38px;
                color: #646970;
                font-size: 13px;
            }
            .vt-stats-header-actions {
                display: flex;
                gap: 10px;
            }
            .vt-stats-header-actions .button {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            .vt-stats-header-actions .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }

            /* Summary Cards */
            .vt-stats-summary {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 20px;
                margin-bottom: 30px;
            }
            @media (max-width: 1200px) {
                .vt-stats-summary {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
            @media (max-width: 600px) {
                .vt-stats-summary {
                    grid-template-columns: 1fr;
                }
            }
            .vt-stats-card {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 8px;
                padding: 20px;
                display: flex;
                align-items: center;
                gap: 15px;
                transition: box-shadow 0.2s, transform 0.2s;
            }
            .vt-stats-card:hover {
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                transform: translateY(-2px);
            }
            .vt-stats-card-icon {
                width: 50px;
                height: 50px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .vt-stats-card-icon .dashicons {
                font-size: 24px;
                width: 24px;
                height: 24px;
                color: #fff;
            }
            .vt-stats-card-content {
                flex: 1;
            }
            .vt-stats-number {
                display: block;
                font-size: 32px;
                font-weight: 600;
                line-height: 1.2;
                color: #1d2327;
            }
            .vt-stats-label {
                display: block;
                color: #646970;
                font-size: 13px;
                font-weight: 500;
            }
            .vt-stats-sublabel {
                display: block;
                color: #a0a5aa;
                font-size: 11px;
                margin-top: 2px;
            }

            /* Card Colors */
            .vt-stats-card-primary .vt-stats-card-icon {
                background: linear-gradient(135deg, #2271b1, #135e96);
            }
            .vt-stats-card-functions .vt-stats-card-icon {
                background: linear-gradient(135deg, #3498db, #2980b9);
            }
            .vt-stats-card-widgets .vt-stats-card-icon {
                background: linear-gradient(135deg, #00a32a, #008a20);
            }
            .vt-stats-card-fields .vt-stats-card-icon {
                background: linear-gradient(135deg, #9b59b6, #8e44ad);
            }

            /* Debug Section */
            .vt-stats-debug {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 30px;
            }
            .vt-stats-debug h2 {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 0 0 15px 0;
                font-size: 15px;
            }
            .vt-stats-debug .dashicons {
                color: #646970;
            }
            .vt-stats-debug table {
                margin: 0;
            }
            .vt-stats-debug td small {
                display: block;
                max-width: 300px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                color: #646970;
            }

            /* Stats Grid */
            .vt-stats-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
            }
            @media (max-width: 1200px) {
                .vt-stats-grid {
                    grid-template-columns: 1fr;
                }
            }

            /* Stats Section */
            .vt-stats-section {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 8px;
                overflow: hidden;
            }
            .vt-stats-section-header {
                padding: 15px 20px;
                border-bottom: 1px solid #c3c4c7;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .vt-stats-section-header .dashicons {
                font-size: 20px;
                width: 20px;
                height: 20px;
            }
            .vt-stats-section-header h2 {
                margin: 0;
                font-size: 14px;
                font-weight: 600;
            }
            .vt-stats-section-header .vt-section-count {
                margin-left: auto;
                background: #f0f0f1;
                padding: 2px 8px;
                border-radius: 10px;
                font-size: 12px;
                color: #646970;
            }
            .vt-stats-section table {
                margin: 0;
                border: none;
            }
            .vt-stats-section table th,
            .vt-stats-section table td {
                border-left: none;
                border-right: none;
            }
            .vt-stats-section tbody tr:last-child td {
                border-bottom: none;
            }
            .vt-count-col {
                width: 50px;
                text-align: center;
                font-weight: 600;
            }
            .vt-percent-col {
                width: 100px;
            }
            .vt-percent-bar {
                position: relative;
                background: #f0f0f1;
                border-radius: 10px;
                height: 20px;
                overflow: hidden;
            }
            .vt-percent-fill {
                position: absolute;
                top: 0;
                left: 0;
                height: 100%;
                border-radius: 10px;
                transition: width 0.3s ease;
            }
            .vt-percent-bar span {
                position: relative;
                z-index: 1;
                display: block;
                text-align: center;
                font-size: 11px;
                line-height: 20px;
                font-weight: 600;
                color: #1d2327;
            }
            .vt-no-data {
                padding: 40px 20px;
                text-align: center;
                color: #646970;
                font-style: italic;
            }
            .vt-item-name {
                font-weight: 500;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#vt-export-csv').on('click', function() {
                window.location.href = ajaxurl + '?action=vt_stats_export_csv&nonce=<?php echo wp_create_nonce('vt_stats_csv'); ?>';
            });

            $('#vt-clear-data').on('click', function() {
                if (confirm('<?php esc_attr_e('Are you sure you want to clear ALL stats data? This cannot be undone.', 'vt-stats-receiver'); ?>')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'vt_stats_clear_data',
                            nonce: '<?php echo wp_create_nonce('vt_stats_clear'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                            }
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    private function render_stats_table($title, $counts, $total_sites, $type = 'function', $color = '#2271b1') {
        $icons = array(
            'function' => 'dashicons-admin-tools',
            'widget' => 'dashicons-screenoptions',
            'post_field' => 'dashicons-forms',
        );
        $icon = isset($icons[$type]) ? $icons[$type] : 'dashicons-marker';
        ?>
        <div class="vt-stats-section">
            <div class="vt-stats-section-header" style="border-left: 4px solid <?php echo esc_attr($color); ?>;">
                <span class="dashicons <?php echo esc_attr($icon); ?>" style="color: <?php echo esc_attr($color); ?>;"></span>
                <h2><?php echo esc_html($title); ?></h2>
                <span class="vt-section-count"><?php echo count($counts); ?> <?php echo count($counts) === 1 ? 'item' : 'items'; ?></span>
            </div>
            <?php if (empty($counts)) : ?>
                <p class="vt-no-data"><?php esc_html_e('No data collected yet.', 'vt-stats-receiver'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'vt-stats-receiver'); ?></th>
                            <th class="vt-count-col">#</th>
                            <th class="vt-percent-col">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($counts as $name => $count) :
                            $percent = $total_sites > 0 ? round(($count / $total_sites) * 100) : 0;
                        ?>
                            <tr>
                                <td class="vt-item-name"><?php echo esc_html($name); ?></td>
                                <td class="vt-count-col"><?php echo esc_html($count); ?></td>
                                <td class="vt-percent-col">
                                    <div class="vt-percent-bar">
                                        <div class="vt-percent-fill" style="width: <?php echo esc_attr($percent); ?>%; background: <?php echo esc_attr($color); ?>;"></div>
                                        <span><?php echo esc_html($percent); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // =========================================
    // AJAX HANDLERS
    // =========================================

    public function ajax_export_csv() {
        check_ajax_referer('vt_stats_csv', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $sites = $wpdb->get_results("SELECT * FROM $table_name ORDER BY last_updated DESC");
        $total_sites = count($sites);

        $function_counts = array();
        $widget_counts = array();
        $post_field_counts = array();

        foreach ($sites as $site) {
            $functions = json_decode($site->enabled_functions, true) ?: array();
            $widgets = json_decode($site->enabled_widgets, true) ?: array();
            $post_fields = isset($site->enabled_post_fields) ? (json_decode($site->enabled_post_fields, true) ?: array()) : array();

            foreach ($functions as $func) {
                // Normalize to friendly name
                $friendly_name = $this->get_friendly_name($func, 'function');
                if (!isset($function_counts[$friendly_name])) $function_counts[$friendly_name] = 0;
                $function_counts[$friendly_name]++;
            }
            foreach ($widgets as $widget) {
                // Normalize to friendly name
                $friendly_name = $this->get_friendly_name($widget, 'widget');
                if (!isset($widget_counts[$friendly_name])) $widget_counts[$friendly_name] = 0;
                $widget_counts[$friendly_name]++;
            }
            foreach ($post_fields as $field) {
                // Normalize to friendly name
                $friendly_name = $this->get_friendly_name($field, 'post_field');
                if (!isset($post_field_counts[$friendly_name])) $post_field_counts[$friendly_name] = 0;
                $post_field_counts[$friendly_name]++;
            }
        }

        arsort($function_counts);
        arsort($widget_counts);
        arsort($post_field_counts);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="vt-stats-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        fputcsv($output, array('Voxel Toolkit Stats Export'));
        fputcsv($output, array('Generated', date('Y-m-d H:i:s')));
        fputcsv($output, array('Total Sites', $total_sites));
        fputcsv($output, array(''));

        fputcsv($output, array('FUNCTIONS'));
        fputcsv($output, array('Name', 'Sites', '%'));
        foreach ($function_counts as $name => $count) {
            fputcsv($output, array($name, $count, round(($count / $total_sites) * 100) . '%'));
        }
        fputcsv($output, array(''));

        fputcsv($output, array('WIDGETS'));
        fputcsv($output, array('Name', 'Sites', '%'));
        foreach ($widget_counts as $name => $count) {
            fputcsv($output, array($name, $count, round(($count / $total_sites) * 100) . '%'));
        }
        fputcsv($output, array(''));

        fputcsv($output, array('POST FIELDS'));
        fputcsv($output, array('Name', 'Sites', '%'));
        foreach ($post_field_counts as $name => $count) {
            fputcsv($output, array($name, $count, round(($count / $total_sites) * 100) . '%'));
        }

        fclose($output);
        exit;
    }

    public function ajax_clear_data() {
        check_ajax_referer('vt_stats_clear', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $wpdb->query("TRUNCATE TABLE $table_name");

        wp_send_json_success(array('message' => 'Data cleared'));
    }
}

// Initialize
VT_Stats_Receiver::instance();

// Activation hook
register_activation_hook(__FILE__, array('VT_Stats_Receiver', 'activate'));
