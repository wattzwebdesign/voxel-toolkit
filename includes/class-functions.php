<?php
/**
 * Voxel Toolkit Functions Manager
 * 
 * Manages all plugin functions and their initialization
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Functions {
    
    private static $instance = null;
    private $available_functions = array();
    private $active_functions = array();
    private $available_widgets = array();
    
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
        // Initialize dynamic tags early
        add_action('after_setup_theme', array($this, 'init_dynamic_tags'), 5);

        // Initialize share menu (always enabled) - call directly since after_setup_theme may have passed
        $this->init_share_menu();

        add_action('init', array($this, 'init'), 20);
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);

        // AJAX handlers
        add_action('wp_ajax_voxel_toolkit_sync_submissions', array($this, 'ajax_sync_submissions'));
        add_action('wp_ajax_vt_send_test_sms', array($this, 'ajax_send_test_sms'));

        // Add post type groups to exporter early on settings page
        add_action('admin_init', array($this, 'add_post_type_groups_to_exporter'), 5);
    }

    /**
     * Add all post type groups to the global exporter (for Enhanced Post Relation settings page)
     * This needs to run early before Voxel's dynamic data template exports the groups
     */
    public function add_post_type_groups_to_exporter() {
        // Only run on our settings page
        if (!isset($_GET['page']) || $_GET['page'] !== 'voxel-toolkit') {
            return;
        }

        // Only if Voxel classes are available
        if (!class_exists('\Voxel\Post_Type') || !class_exists('\Voxel\Dynamic_Data\Exporter')) {
            return;
        }

        $voxel_post_types = \Voxel\Post_Type::get_voxel_types();
        $exporter = \Voxel\Dynamic_Data\Exporter::get();

        foreach ($voxel_post_types as $pt) {
            $exporter->add_group_by_key('post', $pt->get_key());
        }

        // Ensure site group is available
        $exporter->add_group_by_key('site');
    }
    
    /**
     * Initialize
     */
    public function init() {
        $this->register_functions();
        $this->register_widgets();
        $this->init_active_functions();
        $this->init_active_widgets();
        $this->register_shortcodes();
    }

    /**
     * Register shortcodes
     */
    private function register_shortcodes() {
        add_shortcode('vt_messenger', array($this, 'messenger_shortcode'));
    }

    /**
     * Messenger shortcode callback
     */
    public function messenger_shortcode($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '';
        }

        // Check if messenger widget class exists
        if (!class_exists('Voxel_Toolkit_Messenger_Widget')) {
            return '';
        }

        // Parse attributes
        $atts = shortcode_atts(array(
            'position' => 'bottom-right',
            'show_unread_badge' => 'yes',
            'enable_sound' => 'no',
            'max_open_chats' => 3,
        ), $atts);

        // Create a fake widget instance to render
        $widget = new Voxel_Toolkit_Messenger_Widget(array(), array());

        // Start output buffering
        ob_start();

        // Manually render with shortcode settings
        $settings = get_option('voxel_toolkit_messenger_settings', array());
        if (empty($settings['enabled'])) {
            return '';
        }

        $position_class = 'vt-messenger-position-' . $atts['position'];
        $max_chats = intval($atts['max_open_chats']);
        ?>
        <div class="vt-messenger-container <?php echo esc_attr($position_class); ?>"
             data-max-chats="<?php echo esc_attr($max_chats); ?>"
             data-show-badge="<?php echo esc_attr($atts['show_unread_badge']); ?>"
             data-enable-sound="<?php echo esc_attr($atts['enable_sound']); ?>">

            <button class="vt-messenger-button" aria-label="<?php _e('Open messenger', 'voxel-toolkit'); ?>">
                <i class="eicon-comments"></i>
                <?php if ($atts['show_unread_badge'] === 'yes'): ?>
                    <span class="vt-messenger-badge" style="display: none;">0</span>
                <?php endif; ?>
            </button>

            <div class="vt-messenger-popup" style="display: none;">
                <div class="vt-messenger-popup-header">
                    <h3><?php _e('Chats', 'voxel-toolkit'); ?></h3>
                    <button class="vt-messenger-close" aria-label="<?php _e('Close', 'voxel-toolkit'); ?>">
                        <i class="eicon-close"></i>
                    </button>
                </div>

                <div class="vt-messenger-search">
                    <input type="text"
                           class="vt-messenger-search-input"
                           placeholder="<?php echo esc_attr__('Search messages...', 'voxel-toolkit'); ?>">
                </div>

                <div class="vt-messenger-chat-list">
                    <div class="vt-messenger-loading">
                        <i class="eicon-loading eicon-animation-spin"></i>
                    </div>
                </div>
            </div>

            <div class="vt-messenger-chat-windows"></div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Initialize dynamic tags
     */
    public function init_dynamic_tags() {
        // Load dynamic tags class
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-dynamic-tags.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/dynamic-tags/class-dynamic-tags.php';

            // Initialize dynamic tags (always active)
            if (class_exists('Voxel_Toolkit_Dynamic_Tags')) {
                new Voxel_Toolkit_Dynamic_Tags();
            }
        }
    }

    /**
     * Initialize share menu additions
     */
    public function init_share_menu() {
        // Load share menu class
        if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/class-share-menu.php')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/class-share-menu.php';

            // Initialize share menu (always active)
            if (class_exists('Voxel_Toolkit_Share_Menu')) {
                new Voxel_Toolkit_Share_Menu();
            }
        }
    }

    /**
     * Register available functions
     */
    private function register_functions() {
        $this->available_functions = array(
            'auto_verify_posts' => array(
                'name' => __('Auto Verify Posts', 'voxel-toolkit'),
                'description' => __('Automatically mark posts as verified when submitted for selected post types.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Auto_Verify_Posts',
                'file' => 'functions/class-auto-verify-posts.php',
                'settings_callback' => array($this, 'render_auto_verify_posts_settings'),
            ),
            'admin_menu_hide' => array(
                'name' => __('Admin Menu', 'voxel-toolkit'),
                'description' => __('Hide specific admin menu items from the WordPress admin interface.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Admin_Menu_Hide',
                'file' => 'functions/class-admin-menu-hide.php',
                'settings_callback' => array($this, 'render_admin_menu_hide_settings'),
            ),
            'admin_bar_publish' => array(
                'name' => __('Admin Bar Publish Toggle', 'voxel-toolkit'),
                'description' => __('Add Publish/Mark as Pending button in the admin bar for quick status changes.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Admin_Bar_Publish',
                'file' => 'functions/class-admin-bar-publish.php',
                'settings_callback' => array($this, 'render_admin_bar_publish_settings'),
            ),
            'sticky_admin_bar' => array(
                'name' => __('Sticky Admin Bar', 'voxel-toolkit'),
                'description' => __('Make the WordPress admin bar sticky (fixed) instead of static on the frontend.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Sticky_Admin_Bar',
                'file' => 'functions/class-sticky-admin-bar.php',
            ),
            'delete_post_media' => array(
                'name' => __('Delete Post Media', 'voxel-toolkit'),
                'description' => __('Automatically delete all attached media when a post is deleted, with double confirmation.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Delete_Post_Media',
                'file' => 'functions/class-delete-post-media.php',
                'settings_callback' => array($this, 'render_delete_post_media_settings'),
            ),
            'light_mode' => array(
                'name' => __('Light Mode', 'voxel-toolkit'),
                'description' => __('Enable light mode styling for the Voxel admin interface.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Light_Mode',
                'file' => 'functions/class-light-mode.php',
            ),
            'admin_notifications' => array(
                'name' => __('Admin Notifications', 'voxel-toolkit'),
                'description' => __('Override default admin notifications to send to multiple users based on roles or individual selection instead.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Admin_Notifications',
                'file' => 'functions/class-admin-notifications.php',
                'settings_callback' => array($this, 'render_admin_notifications_settings'),
            ),
            'sms_notifications' => array(
                'name' => __('SMS Notifications', 'voxel-toolkit'),
                'description' => __('Send SMS notifications via Twilio, Vonage, or MessageBird when Voxel app events occur.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_SMS_Notifications',
                'file' => 'functions/class-sms-notifications.php',
                'settings_callback' => array($this, 'render_sms_notifications_settings'),
            ),
            'advanced_phone_input' => array(
                'name' => __('Advanced Phone Input', 'voxel-toolkit'),
                'description' => __('Enhanced phone fields with country selection, default country, and country restrictions using intl-tel-input library.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Advanced_Phone_Input',
                'file' => 'functions/class-advanced-phone-input.php',
                'settings_callback' => array($this, 'render_advanced_phone_input_settings'),
                'icon' => 'dashicons-phone',
            ),
            'membership_notifications' => array(
                'name' => __('Membership Notifications', 'voxel-toolkit'),
                'description' => __('Send email notifications to users based on membership expiration dates.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Membership_Notifications',
                'file' => 'functions/class-membership-notifications.php',
                'settings_callback' => array($this, 'render_membership_notifications_settings'),
            ),
            'guest_view' => array(
                'name' => __('Guest View', 'voxel-toolkit'),
                'description' => __('Allow logged-in users to temporarily view the site as a guest with an Elementor widget and admin bar button.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Guest_View',
                'file' => 'functions/class-guest-view.php',
                'settings_callback' => array($this, 'render_guest_view_settings'),
            ),
            'ai_review_summary' => array(
                'name' => __('AI Review Summary', 'voxel-toolkit'),
                'description' => __('Generate AI-powered review summaries and category opinions using ChatGPT API with caching.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_AI_Review_Summary',
                'file' => 'functions/class-ai-review-summary.php',
                'settings_callback' => array($this, 'render_ai_review_summary_settings'),
            ),
            'show_field_description' => array(
                'name' => __('Show Field Description', 'voxel-toolkit'),
                'description' => __('Display form field descriptions as subtitles below labels instead of tooltip icons.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Show_Field_Description',
                'file' => 'functions/class-show-field-description.php',
                'settings_callback' => array($this, 'render_show_field_description_settings'),
            ),
            'suggest_edits' => array(
                'name' => __('Suggest Edits', 'voxel-toolkit'),
                'description' => __('Allow users to suggest edits to posts with moderation workflow and notifications.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Suggest_Edits',
                'file' => 'functions/class-suggest-edits.php',
                'settings_callback' => array($this, 'render_suggest_edits_settings'),
            ),
            'duplicate_post' => array(
                'name' => __('Duplicate Post/Page', 'voxel-toolkit'),
                'description' => __('Enable post/page duplication with quick actions and edit screen button for selected post types.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Duplicate_Post',
                'file' => 'functions/class-duplicate-post.php',
                'settings_callback' => array($this, 'render_duplicate_post_settings'),
            ),
            'media_paste' => array(
                'name' => __('Media Paste', 'voxel-toolkit'),
                'description' => __('Paste images directly from clipboard into WordPress media library and other media pickers. Elementor integration coming soon.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Media_Paste',
                'file' => 'functions/class-media-paste.php',
                'settings_callback' => array($this, 'render_media_paste_settings'),
            ),
            'admin_taxonomy_search' => array(
                'name' => __('Admin Taxonomy Search', 'voxel-toolkit'),
                'description' => __('Add search functionality to taxonomy metaboxes on post edit pages for easier term selection.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Admin_Taxonomy_Search',
                'file' => 'functions/class-admin-taxonomy-search.php',
                'settings_callback' => array($this, 'render_admin_taxonomy_search_settings'),
            ),
            'pending_posts_badge' => array(
                'name' => __('Pending Posts Badge', 'voxel-toolkit'),
                'description' => __('Add badges with pending post counts to admin menu items for selected post types with customizable styling.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Pending_Posts_Badge',
                'file' => 'functions/class-pending-posts-badge.php',
                'settings_callback' => array($this, 'render_pending_posts_badge_settings'),
            ),
            'admin_columns' => array(
                'name' => __('Admin Columns', 'voxel-toolkit'),
                'description' => __('Configure custom columns for Voxel post types in WordPress admin list tables with sorting and filtering support.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Admin_Columns',
                'file' => 'admin-columns/class-admin-columns.php',
                'configure_url' => admin_url('admin.php?page=vt-admin-columns'),
            ),
            'pre_approve_posts' => array(
                'name' => __('Pre-Approve Posts', 'voxel-toolkit'),
                'description' => __('Automatically publish posts from pre-approved users instead of marking them as pending.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Pre_Approve_Posts',
                'file' => 'functions/class-pre-approve-posts.php',
                'settings_callback' => array($this, 'render_pre_approve_posts_settings'),
            ),
            'disable_auto_updates' => array(
                'name' => __('Disable Automatic Updates', 'voxel-toolkit'),
                'description' => __('Disable automatic updates for plugins, themes, and WordPress core with individual controls.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Disable_Auto_Updates',
                'file' => 'functions/class-disable-auto-updates.php',
                'settings_callback' => array($this, 'render_disable_auto_updates_settings'),
            ),
            'redirect_posts' => array(
                'name' => __('Redirect Posts', 'voxel-toolkit'),
                'description' => __('Automatically redirect posts with specific statuses to specified URLs based on post type with flexible status and expiration detection.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Redirect_Posts',
                'file' => 'functions/class-redirect-posts.php',
                'settings_callback' => array($this, 'render_redirect_posts_settings'),
            ),
            'auto_promotion' => array(
                'name' => __('Auto Promotion', 'voxel-toolkit'),
                'description' => __('Automatically boost newly published posts for a set duration to increase their visibility and ranking.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Auto_Promotion',
                'file' => 'functions/class-auto-promotion.php',
                'settings_callback' => array($this, 'render_auto_promotion_settings'),
            ),
            'custom_submission_messages' => array(
                'name' => __('Custom Submission Messages', 'voxel-toolkit'),
                'description' => __('Customize confirmation messages shown to users after submitting different post types.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Custom_Submission_Messages',
                'file' => 'functions/class-custom-submission-messages.php',
                'settings_callback' => array($this, 'render_custom_submission_messages_settings'),
            ),
            'export_orders' => array(
                'name' => __('Export Orders', 'voxel-toolkit'),
                'description' => __('Add an export button to the Voxel orders page to export all orders to CSV format with comprehensive details.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Export_Orders',
                'file' => 'functions/class-export-orders.php',
            ),
            'fluent_forms_post_author' => array(
                'name' => __('Fluent Forms Post Author', 'voxel-toolkit'),
                'description' => __('Adds a "Voxel Post Author" email field to Fluent Forms that automatically populates with the post author\'s email when embedded on posts. Requires Fluent Forms plugin to be active.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Fluent_Forms_Post_Author',
                'file' => 'functions/class-fluent-forms-post-author.php',
                'settings_callback' => array($this, 'render_fluent_forms_post_author_settings'),
            ),
            'featured_posts' => array(
                'name' => __('Featured Posts', 'voxel-toolkit'),
                'description' => __('Add featured functionality to posts with star icons, filtering, and bulk actions. Sets Voxel Priority meta field.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Featured_Posts',
                'file' => 'functions/class-featured-posts.php',
                'settings_callback' => array($this, 'render_featured_posts_settings'),
            ),
            'google_analytics' => array(
                'name' => __('Google Analytics & Custom Tags', 'voxel-toolkit'),
                'description' => __('Add Google Analytics tracking code and custom scripts/tags to head, body, and footer sections of your site.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Google_Analytics',
                'file' => 'functions/class-google-analytics.php',
                'settings_callback' => array($this, 'render_google_analytics_settings'),
            ),
            'submission_reminder' => array(
                'name' => __('Submission Reminder', 'voxel-toolkit'),
                'description' => __('Track user post submissions by post type and send reminder emails at configurable intervals to encourage more submissions.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Submission_Reminder',
                'file' => 'functions/class-submission-reminder.php',
                'settings_callback' => array($this, 'render_submission_reminder_settings'),
            ),
            'duplicate_title_checker' => array(
                'name' => __('Duplicate Title Checker', 'voxel-toolkit'),
                'description' => __('Check for duplicate post titles in real-time while creating or editing posts to prevent duplicate content.', 'voxel-toolkit'),
                'class' => '\VoxelToolkit\Functions\Duplicate_Title_Checker',
                'file' => 'functions/class-duplicate-title-checker.php',
                'settings_callback' => array($this, 'render_duplicate_title_checker_settings'),
            ),
            'options_page' => array(
                'name' => __('Site Options', 'voxel-toolkit'),
                'description' => __('Create global site options accessible via dynamic tags like @site(options.field_name). Perfect for site-wide settings like contact info, social links, and branding.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Options_Page',
                'file' => 'functions/class-options-page.php',
                'settings_callback' => array('Voxel_Toolkit_Options_Page', 'render_settings'),
                'configure_url' => admin_url('admin.php?page=voxel-toolkit-configure-fields'),
            ),
            'visitor_location' => array(
                'name' => __('Visitor Location', 'voxel-toolkit'),
                'description' => __('Display visitor\'s location (city, state, country) using IP geolocation with dynamic tags like @site(visitor.location). Queries multiple free services (geojs.io, ipapi.co, ip-api.com) and picks best result for accuracy.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Visitor_Location',
                'file' => 'functions/class-visitor-location.php',
                'settings_callback' => array($this, 'render_visitor_location_settings'),
            ),
            'post_fields_anywhere' => array(
                'name' => __('Post Fields Anywhere', 'voxel-toolkit'),
                'description' => __('Render any @post() tag in the context of a different post. Usage: @site().render_post_tag(post_id, @post(field))', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Post_Fields_Anywhere',
                'file' => 'functions/class-post-fields-anywhere.php',
                'always_enabled' => true,
                'hidden' => true,
            ),
            'calendar_week_start' => array(
                'name' => __('Calendar Week Start', 'voxel-toolkit'),
                'description' => __('Makes Voxel date pickers respect WordPress "Week Starts On" setting instead of always starting on Monday.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Calendar_Week_Start',
                'file' => 'functions/class-calendar-week-start.php',
                'icon' => 'dashicons-calendar-alt',
            ),
            'disable_gutenberg' => array(
                'name' => __('Disable Gutenberg', 'voxel-toolkit'),
                'description' => __('Disables the Gutenberg block editor site-wide and restores the classic WordPress editor for all post types and widgets.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Disable_Gutenberg',
                'file' => 'functions/class-disable-gutenberg.php',
                'icon' => 'dashicons-editor-classic',
            ),
            'widget_css_injector' => array(
                'name' => __('Widget CSS Class & ID', 'voxel-toolkit'),
                'description' => __('Add CSS Class and ID fields to Voxel widgets (Navbar, User Bar, Advanced List) for custom styling of individual items.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Widget_CSS_Injector',
                'file' => 'functions/class-widget-css-injector.php',
                'settings_callback' => array('Voxel_Toolkit_Widget_CSS_Injector', 'render_settings'),
                'always_enabled' => true,
                'hidden' => true,
            ),
            'share_count' => array(
                'name' => __('Share Count', 'voxel-toolkit'),
                'description' => __('Track the number of times posts are shared via the share menu. Adds @post(share_count) dynamic tag.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Share_Count',
                'file' => 'functions/class-share-count.php',
                'icon' => 'dashicons-share',
            ),
            'compare_posts' => array(
                'name' => __('Compare Posts', 'voxel-toolkit'),
                'description' => __('Allow users to compare 2-4 posts of the same type side-by-side with a floating comparison bar and comparison table.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Compare_Posts_Widget_Manager',
                'file' => 'widgets/class-compare-posts-widget-manager.php',
                'icon' => 'eicon-table',
                'settings_callback' => array($this, 'render_compare_posts_settings'),
            ),
            'social_proof' => array(
                'name' => __('Social Proof', 'voxel-toolkit'),
                'description' => __('Display toast notifications showing recent activity like new bookings, posts, and orders to create social proof.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Social_Proof',
                'file' => 'functions/class-social-proof.php',
                'icon' => 'dashicons-megaphone',
                'settings_callback' => array($this, 'render_social_proof_settings'),
                'beta' => true,
            ),
            'enhanced_editor' => array(
                'name' => __('Enhanced TinyMCE Editor', 'voxel-toolkit'),
                'description' => __('Adds media upload, text color, background color, and character map to WP Editor Advanced mode.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Enhanced_Editor',
                'file' => 'functions/class-enhanced-editor.php',
                'icon' => 'dashicons-editor-kitchensink',
                'settings_callback' => array($this, 'render_enhanced_editor_settings'),
            ),
            'enhanced_post_relation' => array(
                'name' => __('Enhanced Post Relation', 'voxel-toolkit'),
                'description' => __('Customize post display in Post Relation field selectors using dynamic tag templates. Configure different display formats per post type.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Enhanced_Post_Relation',
                'file' => 'functions/class-enhanced-post-relation.php',
                'icon' => 'dashicons-networking',
                'settings_callback' => array($this, 'render_enhanced_post_relation_settings'),
            ),
            'team_members' => array(
                'name' => __('Team Members', 'voxel-toolkit'),
                'description' => __('Allow post authors to invite team members by email who can then edit the post. Includes custom field, invite system, and email notifications.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Team_Members',
                'file' => 'functions/class-team-members.php',
                'icon' => 'dashicons-groups',
                'settings_callback' => array($this, 'render_team_members_settings'),
            ),
            'link_management' => array(
                'name' => __('External Link Warning', 'voxel-toolkit'),
                'description' => __('Show warning modals when users click external links, with customizable messages and domain whitelisting.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Link_Management',
                'file' => 'functions/class-link-management.php',
                'icon' => 'dashicons-external',
                'settings_callback' => array($this, 'render_link_management_settings'),
            ),
            'add_category' => array(
                'name' => __('Add Category', 'voxel-toolkit'),
                'description' => __('Allow users to add new taxonomy terms from the frontend Create Post form. Supports approval workflow, overridable permissions, and app events.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Add_Category',
                'file' => 'functions/class-add-category.php',
                'icon' => 'dashicons-plus-alt',
            ),
            'saved_search' => array(
                'name' => __('Saved Search', 'voxel-toolkit'),
                'description' => __('Allow users to save search filters and receive notifications when new posts match their saved searches.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Saved_Search',
                'file' => 'functions/class-saved-search.php',
                'settings_callback' => array($this, 'render_saved_search_settings'),
                'icon' => 'dashicons-search',
                'required_widgets' => array('saved_search_widget'),
            ),
            'message_moderation' => array(
                'name' => __('Message Moderation', 'voxel-toolkit'),
                'description' => __('Add a top-level admin menu to view, search, and moderate all direct messages on the site.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Messages_Admin',
                'file' => 'admin/messages/class-messages-admin.php',
                'icon' => 'dashicons-email-alt',
            ),
            'timeline_filters' => array(
                'name' => __('Timeline Filters', 'voxel-toolkit'),
                'description' => __('Add custom filtering options to Voxel Timeline widgets, including an "Unanswered" filter for posts with no replies.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Timeline_Filters',
                'file' => 'functions/class-timeline-filters.php',
                'icon' => 'dashicons-filter',
                'settings_callback' => array($this, 'render_timeline_filters_settings'),
            ),
            'timeline_reply_summary' => array(
                'name' => __('Timeline Reply Summary', 'voxel-toolkit'),
                'description' => __('AI-generated TL;DR summaries of timeline post replies. Requires OpenAI or Anthropic API key.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Timeline_Reply_Summary',
                'file' => 'functions/class-timeline-reply-summary.php',
                'icon' => 'dashicons-text',
                'settings_callback' => array($this, 'render_timeline_reply_summary_settings'),
            ),
            'ai_settings' => array(
                'name' => __('AI Settings', 'voxel-toolkit'),
                'description' => __('Central configuration for AI providers (OpenAI, Anthropic). Required for AI-powered features.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_AI_Settings',
                'file' => 'functions/class-ai-settings.php',
                'settings_callback' => array($this, 'render_ai_settings'),
                'always_enabled' => true,
                'icon' => 'dashicons-admin-generic',
            ),
            'ai_post_summary' => array(
                'name' => __('AI Post Summary', 'voxel-toolkit'),
                'description' => __('Auto-generate AI summaries for posts on publish/update. Access via @post(ai.summary) dynamic tag.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_AI_Post_Summary',
                'file' => 'functions/class-ai-post-summary.php',
                'settings_callback' => array($this, 'render_ai_post_summary_settings'),
                'icon' => 'dashicons-format-aside',
            ),
            'ai_bot' => array(
                'name' => __('AI Bot', 'voxel-toolkit'),
                'description' => __('AI-powered search assistant. Users ask natural language questions to find posts with Voxel card template results.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_AI_Bot',
                'file' => 'functions/class-ai-bot.php',
                'settings_callback' => array($this, 'render_ai_bot_settings'),
                'icon' => 'dashicons-format-chat',
                'required_widgets' => array('ai_bot_widget'),
            ),
        );

        // Allow other plugins/themes to register functions
        $this->available_functions = apply_filters('voxel_toolkit/available_functions', $this->available_functions);
    }
    
    /**
     * Register available widgets
     */
    private function register_widgets() {
        $this->available_widgets = array(
            'weather' => array(
                'name' => __('Weather Widget', 'voxel-toolkit'),
                'description' => __('Display current weather, forecasts with customizable styling using OpenWeatherMap API.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Weather_Widget_Manager',
                'file' => 'widgets/class-weather-widget-manager.php',
                'icon' => 'eicon-flash',
                'widget_name' => 'voxel-toolkit-weather',
            ),
            'reading_time' => array(
                'name' => __('Reading Time', 'voxel-toolkit'),
                'description' => __('Display estimated reading time for posts with customizable prefix, postfix, and styling options.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Reading_Time_Widget',
                'file' => 'widgets/class-reading-time-widget.php',
                'settings_callback' => array($this, 'render_reading_time_widget_settings'),
                'icon' => 'eicon-clock-o',
                'widget_name' => 'voxel-reading-time',
            ),
            'table_of_contents' => array(
                'name' => __('Table of Contents', 'voxel-toolkit'),
                'description' => __('Display a table of contents showing all ui-step fields from any Voxel post type form with customizable styling.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Table_Of_Contents_Widget',
                'file' => 'widgets/class-table-of-contents-widget.php',
                'icon' => 'eicon-bullet-list',
                'widget_name' => 'voxel-table-of-contents',
            ),
            'review_collection' => array(
                'name' => __('Review Collection', 'voxel-toolkit'),
                'description' => __('Display a collection of user reviews with advanced filtering and styling options.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Review_Collection_Widget_Manager',
                'file' => 'widgets/class-review-collection-widget-manager.php',
                'icon' => 'eicon-review',
                'widget_name' => 'voxel_toolkit_review_collection',
            ),
            'prev_next_widget' => array(
                'name' => __('Previous/Next Navigation', 'voxel-toolkit'),
                'description' => __('Navigate between posts with customizable previous/next buttons and post information display.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Prev_Next_Widget_Manager',
                'file' => 'widgets/class-prev-next-widget-manager.php',
                'icon' => 'eicon-navigation-horizontal',
                'widget_name' => 'voxel_prev_next_navigation',
            ),
            'poll_display' => array(
                'name' => __('Poll Display (VT)', 'voxel-toolkit'),
                'description' => __('Display an interactive poll from a Poll (VT) field with voting, progress bars, and user-submitted options.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Poll_Display_Widget_Manager',
                'file' => 'widgets/class-poll-display-widget-manager.php',
                'icon' => 'eicon-poll',
                'widget_name' => 'vt-poll-display',
                'hidden' => true, // Hidden from widgets page, auto-enabled by poll field
            ),
            'timeline_photos' => array(
                'name' => __('Timeline Photos', 'voxel-toolkit'),
                'description' => __('Display photos from post reviews in a customizable gallery with masonry, grid, and justified layouts.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Timeline_Photos_Widget',
                'file' => 'widgets/class-timeline-photos-widget.php',
                'icon' => 'eicon-gallery-grid',
                'widget_name' => 'voxel-timeline-photos',
            ),
            'users_purchased' => array(
                'name' => __('Users Purchased', 'voxel-toolkit'),
                'description' => __('Display users who have purchased the current product with avatar grid or list views.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Users_Purchased_Widget_Manager',
                'file' => 'widgets/class-users-purchased-widget-manager.php',
                'icon' => 'eicon-cart',
                'widget_name' => 'voxel-toolkit-users-purchased',
            ),
            'article_helpful' => array(
                'name' => __('Article Helpful', 'voxel-toolkit'),
                'description' => __('Display "Was this Article Helpful?" widget with yes/no voting and admin statistics tracking.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Article_Helpful_Widget_Manager',
                'file' => 'widgets/class-article-helpful-widget-manager.php',
                'icon' => 'eicon-favorite',
                'widget_name' => 'voxel-article-helpful',
            ),
            'campaign_progress' => array(
                'name' => __('Campaign Progress', 'voxel-toolkit'),
                'description' => __('Display donation/crowdfunding progress with goal tracking, progress bar, and donor list.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Campaign_Progress_Widget_Manager',
                'file' => 'widgets/class-campaign-progress-widget-manager.php',
                'icon' => 'eicon-product-rating',
                'widget_name' => 'voxel-toolkit-campaign-progress',
            ),
            'onboarding' => array(
                'name' => __('Onboarding', 'voxel-toolkit'),
                'description' => __('Create interactive onboarding tours for first-time users using intro.js library.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit\Widgets\Onboarding_Widget_Manager',
                'file' => 'widgets/class-onboarding-widget-manager.php',
                'icon' => 'eicon-navigator',
                'widget_name' => 'voxel-onboarding',
            ),
            'breadcrumbs' => array(
                'name' => __('Breadcrumbs', 'voxel-toolkit'),
                'description' => __('Display hierarchical navigation breadcrumbs with schema markup and customizable styling.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Breadcrumbs_Widget_Manager',
                'file' => 'widgets/class-breadcrumbs-widget-manager.php',
                'icon' => 'eicon-navigation-horizontal',
                'widget_name' => 'voxel-toolkit-breadcrumbs',
            ),
            'active_filters' => array(
                'name' => __('Active Filters', 'voxel-toolkit'),
                'description' => __('Display active search filters as clickable tags with remove functionality.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Active_Filters_Widget_Manager',
                'file' => 'widgets/class-active-filters-widget-manager.php',
                'icon' => 'eicon-filter',
                'widget_name' => 'voxel-toolkit-active-filters',
            ),
            'messenger' => array(
                'name' => __('Messenger (VT)', 'voxel-toolkit'),
                'description' => __('Facebook-style floating messenger widget with multi-chat support and customizable positioning.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Messenger_Widget_Manager',
                'file' => 'widgets/class-messenger-widget-manager.php',
                'icon' => 'eicon-comments',
                'widget_name' => 'voxel-messenger',
            ),
            'media_gallery' => array(
                'name' => __('Media Gallery (VT)', 'voxel-toolkit'),
                'description' => __('Enhanced gallery widget supporting images, videos, and mixed media with customizable layouts.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Media_Gallery_Widget_Manager',
                'file' => 'widgets/class-media-gallery-widget-manager.php',
                'icon' => 'eicon-gallery-justified',
                'widget_name' => 'vt-media-gallery',
            ),
            'rsvp_form' => array(
                'name' => __('RSVP Form', 'voxel-toolkit'),
                'description' => __('Allow users to RSVP to posts/events with guest support, approval workflow, and attendee limits.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_RSVP',
                'file' => 'functions/class-rsvp.php',
                'icon' => 'eicon-form-horizontal',
                'widget_name' => 'voxel-rsvp-form',
                'required_widgets' => array('rsvp_attendee_list'),
            ),
            'rsvp_attendee_list' => array(
                'name' => __('RSVP Attendee List', 'voxel-toolkit'),
                'description' => __('Display list of RSVPs with admin approval, rejection, and CSV export functionality.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_RSVP_Attendee_List_Widget',
                'file' => 'widgets/class-attendee-list-widget.php',
                'icon' => 'eicon-person',
                'widget_name' => 'voxel-rsvp-attendee-list',
                'hidden' => true,
            ),
            'route_planner' => array(
                'name' => __('Route Planner', 'voxel-toolkit'),
                'description' => __('Display interactive routes with turn-by-turn directions using waypoints from repeater or post relation fields.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Route_Planner_Widget_Manager',
                'file' => 'widgets/class-route-planner-widget-manager.php',
                'icon' => 'eicon-google-maps',
                'widget_name' => 'voxel-toolkit-route-planner',
            ),
            'saved_search_widget' => array(
                'name' => __('Saved Search (VT)', 'voxel-toolkit'),
                'description' => __('Display and manage user saved searches.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Saved_Search_Widget_Manager',
                'file' => 'widgets/class-saved-search-widget-manager.php',
                'icon' => 'eicon-search',
                'widget_name' => 'vt-saved-search',
                'hidden' => true,
            ),
            'ai_bot_widget' => array(
                'name' => __('AI Bot (VT)', 'voxel-toolkit'),
                'description' => __('Trigger button that opens the AI-powered search assistant panel.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_AI_Bot_Widget_Manager',
                'file' => 'widgets/class-ai-bot-widget-manager.php',
                'icon' => 'eicon-commenting-o',
                'widget_name' => 'vt-ai-bot',
                'hidden' => true,
            ),
        );

        // Allow other plugins/themes to register widgets
        $this->available_widgets = apply_filters('voxel_toolkit/available_widgets', $this->available_widgets);
    }
    
    /**
     * Initialize active functions
     */
    private function init_active_functions() {
        $settings = Voxel_Toolkit_Settings::instance();
        
        foreach ($this->available_functions as $function_key => $function_data) {
            // Initialize if enabled in settings OR if it's always enabled
            $is_always_enabled = isset($function_data['always_enabled']) && $function_data['always_enabled'];
            
            if ($settings->is_function_enabled($function_key) || $is_always_enabled) {
                $this->init_function($function_key, $function_data);
            }
        }
    }
    
    /**
     * Initialize active widgets
     */
    private function init_active_widgets() {
        $settings = Voxel_Toolkit_Settings::instance();

        // Always initialize Profile Progress widget (required for dynamic tags)
        $this->init_widget('profile_progress', array(
            'name' => __('Profile Progress', 'voxel-toolkit'),
            'description' => __('Display user profile completion progress with customizable field tracking and visual styles.', 'voxel-toolkit'),
            'class' => 'Voxel_Toolkit_Profile_Progress_Widget',
            'file' => 'widgets/class-profile-progress-widget.php',
        ));

        // Collect widgets required by enabled functions
        $required_by_functions = array();
        foreach ($this->available_functions as $function_key => $function_data) {
            if ($settings->is_function_enabled($function_key) && !empty($function_data['required_widgets'])) {
                foreach ($function_data['required_widgets'] as $req_widget) {
                    $required_by_functions[$req_widget] = true;
                }
            }
        }

        foreach ($this->available_widgets as $widget_key => $widget_data) {
            $widget_key_full = 'widget_' . $widget_key;
            // Initialize if explicitly enabled OR required by an enabled function
            if ($settings->is_function_enabled($widget_key_full) || isset($required_by_functions[$widget_key])) {
                $this->init_widget($widget_key, $widget_data);
            }
        }
    }
    
    /**
     * Initialize a specific widget
     * 
     * @param string $widget_key Widget key
     * @param array $widget_data Widget data
     */
    private function init_widget($widget_key, $widget_data) {
        // Include widget file if specified
        if (isset($widget_data['file']) && !empty($widget_data['file'])) {
            $file_path = VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/' . $widget_data['file'];
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
        
        // Initialize widget class if exists
        if (isset($widget_data['class']) && class_exists($widget_data['class'])) {
            new $widget_data['class']();
        }
        
        // Add widget category
        add_action('elementor/elements/categories_registered', function($elements_manager) {
            $elements_manager->add_category(
                'voxel-toolkit',
                [
                    'title' => __('Voxel Toolkit', 'voxel-toolkit'),
                    'icon' => 'fa fa-toolbox',
                ]
            );
        });
    }
    
    /**
     * Initialize a specific function
     * 
     * @param string $function_key Function key
     * @param array $function_data Function data
     */
    private function init_function($function_key, $function_data) {
        if (isset($this->active_functions[$function_key])) {
            return; // Already initialized
        }

        // Include function file if specified
        if (isset($function_data['file'])) {
            $file_path = VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/' . $function_data['file'];
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }

        // Initialize function class if specified
        if (isset($function_data['class']) && class_exists($function_data['class'])) {
            $class_name = $function_data['class'];

            // Check if class has instance method (singleton pattern)
            if (method_exists($class_name, 'instance')) {
                $this->active_functions[$function_key] = $class_name::instance();
            } else {
                // Use regular constructor
                $this->active_functions[$function_key] = new $class_name();
            }
        }
        
        // Fire action hook
        do_action("voxel_toolkit/function_initialized/{$function_key}", $function_data);
    }
    
    /**
     * Deinitialize a function
     * 
     * @param string $function_key Function key
     */
    private function deinit_function($function_key) {
        if (!isset($this->active_functions[$function_key])) {
            return; // Not initialized
        }
        
        $function_instance = $this->active_functions[$function_key];
        
        // Call deinit method if it exists
        if (method_exists($function_instance, 'deinit')) {
            $function_instance->deinit();
        }
        
        // Special handling for membership notifications cron
        if ($function_key === 'membership_notifications') {
            if (class_exists('Voxel_Toolkit_Membership_Notifications')) {
                Voxel_Toolkit_Membership_Notifications::deactivate_cron();
            }
        }
        
        // Special handling for auto promotion cleanup
        if ($function_key === 'auto_promotion') {
            if (class_exists('Voxel_Toolkit_Auto_Promotion')) {
                Voxel_Toolkit_Auto_Promotion::cleanup();
            }
        }
        
        unset($this->active_functions[$function_key]);
        
        // Fire action hook
        do_action("voxel_toolkit/function_deinitialized/{$function_key}");
    }
    
    /**
     * Get available functions
     * 
     * @return array Available functions
     */
    public function get_available_functions() {
        return $this->available_functions;
    }
    
    /**
     * Get active functions
     * 
     * @return array Active function instances
     */
    public function get_active_functions() {
        return $this->active_functions;
    }
    
    /**
     * Get available widgets
     * 
     * @return array Available widget configurations
     */
    public function get_available_widgets() {
        return $this->available_widgets;
    }
    
    /**
     * Check if a function is available
     * 
     * @param string $function_key Function key
     * @return bool Whether function is available
     */
    public function is_function_available($function_key) {
        return isset($this->available_functions[$function_key]);
    }
    
    /**
     * Check if a function is active
     * 
     * @param string $function_key Function key
     * @return bool Whether function is active
     */
    public function is_function_active($function_key) {
        return isset($this->active_functions[$function_key]);
    }
    
    /**
     * Handle settings updates
     * 
     * @param array $new_settings New settings
     * @param array $old_settings Old settings
     */
    public function on_settings_updated($new_settings, $old_settings) {
        foreach ($this->available_functions as $function_key => $function_data) {
            $was_enabled = isset($old_settings[$function_key]['enabled']) && $old_settings[$function_key]['enabled'];
            $is_enabled = isset($new_settings[$function_key]['enabled']) && $new_settings[$function_key]['enabled'];
            
            if (!$was_enabled && $is_enabled) {
                // Function was just enabled
                $this->init_function($function_key, $function_data);
            } elseif ($was_enabled && !$is_enabled) {
                // Function was just disabled
                $this->deinit_function($function_key);
            }
        }
    }
    
    /**
     * Render settings for auto verify posts function
     * 
     * @param array $settings Current settings
     */
    public function render_auto_verify_posts_settings($settings) {
        $post_types = Voxel_Toolkit_Settings::instance()->get_available_post_types();
        $selected_types = isset($settings['post_types']) ? $settings['post_types'] : array();

        ?>
        <div class="vt-info-box">
            <?php _e('Select which post types should be automatically marked as verified when submitted.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-checkbox-grid">
            <?php foreach ($post_types as $post_type => $label): ?>
                <label>
                    <input type="checkbox"
                           name="voxel_toolkit_options[auto_verify_posts][post_types][]"
                           value="<?php echo esc_attr($post_type); ?>"
                           <?php checked(in_array($post_type, $selected_types)); ?> />
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings for admin menu hide function
     * 
     * @param array $settings Current settings
     */
    public function render_admin_menu_hide_settings($settings) {
        // Get available menus from the class if possible
        $available_menus = array(
            'voxel_settings' => array(
                'name' => __('Voxel Menu', 'voxel-toolkit'),
                'description' => __('Hide the main Voxel settings menu', 'voxel-toolkit')
            ),
            'voxel_post_types' => array(
                'name' => __('Structure', 'voxel-toolkit'),
                'description' => __('Hide the Voxel Structure (Post Types) menu', 'voxel-toolkit')
            ),
            'voxel_templates' => array(
                'name' => __('Design', 'voxel-toolkit'),
                'description' => __('Hide the Voxel Design/Templates menu', 'voxel-toolkit')
            ),
            'voxel_users' => array(
                'name' => __('Users (Voxel)', 'voxel-toolkit'),
                'description' => __('Hide the Users (Voxel) submenu under Users', 'voxel-toolkit')
            )
        );

        $hidden_menus = isset($settings['hidden_menus']) ? $settings['hidden_menus'] : array();

        ?>
        <div class="vt-info-box">
            <?php _e('Select which admin menus should be hidden from the WordPress admin interface.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-checkbox-list">
            <?php foreach ($available_menus as $menu_key => $menu_data): ?>
                <label class="vt-checkbox-item">
                    <input type="checkbox"
                           name="voxel_toolkit_options[admin_menu_hide][hidden_menus][]"
                           value="<?php echo esc_attr($menu_key); ?>"
                           <?php checked(in_array($menu_key, $hidden_menus)); ?> />
                    <div class="vt-checkbox-item-content">
                        <span class="vt-checkbox-item-label"><?php echo esc_html($menu_data['name']); ?></span>
                        <p class="vt-checkbox-item-description"><?php echo esc_html($menu_data['description']); ?></p>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    
    /**
     * Render settings for admin bar publish function
     * 
     * @param array $settings Current settings
     */
    public function render_admin_bar_publish_settings($settings) {
        $post_types = Voxel_Toolkit_Settings::instance()->get_available_post_types();
        $selected_types = isset($settings['post_types']) ? $settings['post_types'] : array();

        ?>
        <div class="vt-info-box">
            <?php _e('Select which post types should show the Publish/Mark as Pending button in the admin bar. The button will appear when viewing or editing posts of these types.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-checkbox-grid">
            <?php foreach ($post_types as $post_type => $label): ?>
                <label>
                    <input type="checkbox"
                           name="voxel_toolkit_options[admin_bar_publish][post_types][]"
                           value="<?php echo esc_attr($post_type); ?>"
                           <?php checked(in_array($post_type, $selected_types)); ?> />
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings for delete post media function
     * 
     * @param array $settings Current settings
     */
    public function render_delete_post_media_settings($settings) {
        $post_types = Voxel_Toolkit_Settings::instance()->get_available_post_types();
        $selected_types = isset($settings['post_types']) ? $settings['post_types'] : array();

        ?>
        <div class="vt-info-box">
            <?php _e('Select which post types should automatically delete all attached media when the post is deleted. A double confirmation dialog will appear to prevent accidental deletions.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-warning-box">
            <strong><?php _e('Warning:', 'voxel-toolkit'); ?></strong> <?php _e('This will permanently delete media files from your server. This action cannot be undone.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-checkbox-grid">
            <?php foreach ($post_types as $post_type => $label): ?>
                <label>
                    <input type="checkbox"
                           name="voxel_toolkit_options[delete_post_media][post_types][]"
                           value="<?php echo esc_attr($post_type); ?>"
                           <?php checked(in_array($post_type, $selected_types)); ?> />
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings for admin notifications function
     * 
     * @param array $settings Current settings
     */
    public function render_admin_notifications_settings($settings) {
        $user_roles = isset($settings['user_roles']) ? $settings['user_roles'] : array();
        $selected_users = isset($settings['selected_users']) ? $settings['selected_users'] : array();
        $roles = get_editable_roles();
        ?>
        <div class="vt-info-box">
            <?php _e('Replace default admin notifications with notifications sent to all users with the selected roles.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('User Roles', 'voxel-toolkit'); ?></h4>
            <div class="vt-checkbox-grid">
                <?php foreach ($roles as $role_key => $role_data): ?>
                    <label>
                        <input type="checkbox"
                               name="voxel_toolkit_options[admin_notifications][user_roles][]"
                               value="<?php echo esc_attr($role_key); ?>"
                               <?php checked(in_array($role_key, $user_roles)); ?> />
                        <?php echo esc_html($role_data['name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Individual Users', 'voxel-toolkit'); ?></h4>
            <div class="vt-field-group">
                <input type="hidden" name="voxel_toolkit_options[admin_notifications][selected_users]" value="" />
                <select id="admin_notifications_selected_users"
                        name="voxel_toolkit_options[admin_notifications][selected_users][]"
                        multiple="multiple"
                        class="vt-user-search-select"
                        style="width: 100%; max-width: 500px; min-height: 120px;">
                    <?php foreach($selected_users as $user_id): ?>
                        <?php if ($user_data = get_userdata($user_id)): ?>
                            <option selected value="<?php echo esc_attr($user_id); ?>">
                                <?php echo esc_html($user_data->display_name . ' (' . $user_data->user_email . ')'); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <p class="vt-field-description">
                    <?php _e('Search and select individual users to receive admin notifications. Type at least 3 characters to search.', 'voxel-toolkit'); ?>
                </p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            if (!$('#admin_notifications_selected_users').hasClass('select2-hidden-accessible')) {
                $('#admin_notifications_selected_users').select2({
                    ajax: {
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                action: 'vt_admin_notifications_user_search',
                                nonce: '<?php echo wp_create_nonce('vt_admin_notifications_user_search'); ?>',
                                q: params.term
                            };
                        },
                        processResults: function(data) {
                            return { results: data };
                        },
                        cache: true
                    },
                    escapeMarkup: function(markup) { return markup; },
                    minimumInputLength: 3,
                    placeholder: '<?php _e('Search for users by name or email...', 'voxel-toolkit'); ?>',
                    allowClear: true
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Get phone fields from Voxel profile post type
     *
     * @return array Phone fields as key => label pairs
     */
    public function get_voxel_phone_fields() {
        $post_types = get_option('voxel:post_types', array());

        // Handle serialized data
        if (is_string($post_types)) {
            $post_types = maybe_unserialize($post_types);
        }

        // Try JSON decode if it's a JSON string
        if (is_string($post_types)) {
            $decoded = json_decode($post_types, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $post_types = $decoded;
            }
        }

        if (empty($post_types) || !is_array($post_types)) {
            return array();
        }

        // Look for 'profile' post type
        if (!isset($post_types['profile']) || !is_array($post_types['profile'])) {
            return array();
        }

        $profile_data = $post_types['profile'];

        // Get fields array
        if (!isset($profile_data['fields']) || !is_array($profile_data['fields'])) {
            return array();
        }

        $phone_fields = array();

        // Loop through fields and extract phone type fields
        foreach ($profile_data['fields'] as $field) {
            if (!isset($field['key']) || empty($field['key'])) {
                continue;
            }

            // Only include phone type fields
            if (!isset($field['type']) || $field['type'] !== 'phone') {
                continue;
            }

            $label = isset($field['label']) && !empty($field['label'])
                ? $field['label']
                : $field['key'];

            $phone_fields[$field['key']] = $label . ' (' . $field['key'] . ')';
        }

        return $phone_fields;
    }

    /**
     * Render settings for SMS notifications function
     *
     * @param array $settings Current settings
     */
    public function render_sms_notifications_settings($settings) {
        $provider = isset($settings['provider']) ? $settings['provider'] : 'twilio';
        $phone_number = isset($settings['phone_number']) ? $settings['phone_number'] : '';

        // Twilio credentials
        $twilio_account_sid = isset($settings['twilio_account_sid']) ? $settings['twilio_account_sid'] : '';
        $twilio_auth_token = isset($settings['twilio_auth_token']) ? $settings['twilio_auth_token'] : '';
        $twilio_from_number = isset($settings['twilio_from_number']) ? $settings['twilio_from_number'] : '';

        // Vonage credentials
        $vonage_api_key = isset($settings['vonage_api_key']) ? $settings['vonage_api_key'] : '';
        $vonage_api_secret = isset($settings['vonage_api_secret']) ? $settings['vonage_api_secret'] : '';
        $vonage_from = isset($settings['vonage_from']) ? $settings['vonage_from'] : '';

        // MessageBird credentials
        $messagebird_api_key = isset($settings['messagebird_api_key']) ? $settings['messagebird_api_key'] : '';
        $messagebird_originator = isset($settings['messagebird_originator']) ? $settings['messagebird_originator'] : '';

        // Check if any provider is configured
        $has_twilio = !empty($twilio_account_sid) && !empty($twilio_auth_token);
        $has_vonage = !empty($vonage_api_key) && !empty($vonage_api_secret);
        $has_messagebird = !empty($messagebird_api_key);
        ?>
        <tr>
            <th scope="row">
                <label><?php _e('SMS Notifications Settings', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; max-width: 800px;">

                    <!-- Provider Selection -->
                    <div style="margin-bottom: 25px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;">
                            <?php _e('SMS Provider', 'voxel-toolkit'); ?>
                        </h3>
                        <select name="voxel_toolkit_options[sms_notifications][provider]"
                                id="sms_provider_select"
                                style="width: 100%; max-width: 300px; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="twilio" <?php selected($provider, 'twilio'); ?>><?php _e('Twilio', 'voxel-toolkit'); ?></option>
                            <option value="vonage" <?php selected($provider, 'vonage'); ?>><?php _e('Vonage (Nexmo)', 'voxel-toolkit'); ?></option>
                            <option value="messagebird" <?php selected($provider, 'messagebird'); ?>><?php _e('MessageBird', 'voxel-toolkit'); ?></option>
                        </select>
                        <p style="margin: 10px 0 0 0; font-size: 13px; color: #666;">
                            <?php _e('Select your preferred SMS provider. Configure the credentials below.', 'voxel-toolkit'); ?>
                        </p>
                    </div>

                    <!-- Twilio Configuration -->
                    <div id="twilio_config" class="sms-provider-config" style="margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 6px; <?php echo $provider !== 'twilio' ? 'display: none;' : ''; ?>">
                        <h4 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 15px;">
                            <?php _e('Twilio Configuration', 'voxel-toolkit'); ?>
                            <?php if ($has_twilio): ?>
                                <span style="background: #d4edda; color: #155724; padding: 3px 10px; border-radius: 12px; font-size: 11px; margin-left: 10px;"><?php _e('Configured', 'voxel-toolkit'); ?></span>
                            <?php endif; ?>
                        </h4>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 5px;"><?php _e('Account SID', 'voxel-toolkit'); ?></label>
                            <?php if (!empty($twilio_account_sid)): ?>
                                <div style="background: #f1f1f1; padding: 8px 12px; border-radius: 4px; margin-bottom: 8px; font-family: monospace; font-size: 13px;">
                                    <?php echo esc_html(substr($twilio_account_sid, 0, 8) . str_repeat('*', 20)); ?>
                                </div>
                            <?php endif; ?>
                            <input type="text"
                                   name="voxel_toolkit_options[sms_notifications][twilio_account_sid]"
                                   value=""
                                   placeholder="<?php echo !empty($twilio_account_sid) ? __('Enter new SID to replace', 'voxel-toolkit') : 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'; ?>"
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;" />
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 5px;"><?php _e('Auth Token', 'voxel-toolkit'); ?></label>
                            <?php if (!empty($twilio_auth_token)): ?>
                                <div style="background: #f1f1f1; padding: 8px 12px; border-radius: 4px; margin-bottom: 8px; font-family: monospace; font-size: 13px;">
                                    <?php echo str_repeat('*', 32); ?>
                                </div>
                            <?php endif; ?>
                            <input type="password"
                                   name="voxel_toolkit_options[sms_notifications][twilio_auth_token]"
                                   value=""
                                   placeholder="<?php echo !empty($twilio_auth_token) ? __('Enter new token to replace', 'voxel-toolkit') : __('Auth Token', 'voxel-toolkit'); ?>"
                                   autocomplete="off"
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" />
                        </div>

                        <div>
                            <label style="display: block; font-weight: 500; margin-bottom: 5px;"><?php _e('From Phone Number', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[sms_notifications][twilio_from_number]"
                                   value="<?php echo esc_attr($twilio_from_number); ?>"
                                   placeholder="+15551234567"
                                   style="width: 100%; max-width: 250px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;" />
                            <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                                <?php _e('Your Twilio phone number in E.164 format', 'voxel-toolkit'); ?>
                            </p>
                        </div>

                        <p style="margin: 15px 0 0 0; font-size: 13px; color: #666;">
                            <?php _e('Get your credentials from', 'voxel-toolkit'); ?>
                            <a href="https://console.twilio.com/" target="_blank" style="color: #2271b1;"><?php _e('Twilio Console', 'voxel-toolkit'); ?></a>
                        </p>
                    </div>

                    <!-- Vonage Configuration -->
                    <div id="vonage_config" class="sms-provider-config" style="margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 6px; <?php echo $provider !== 'vonage' ? 'display: none;' : ''; ?>">
                        <h4 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 15px;">
                            <?php _e('Vonage (Nexmo) Configuration', 'voxel-toolkit'); ?>
                            <?php if ($has_vonage): ?>
                                <span style="background: #d4edda; color: #155724; padding: 3px 10px; border-radius: 12px; font-size: 11px; margin-left: 10px;"><?php _e('Configured', 'voxel-toolkit'); ?></span>
                            <?php endif; ?>
                        </h4>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 5px;"><?php _e('API Key', 'voxel-toolkit'); ?></label>
                            <?php if (!empty($vonage_api_key)): ?>
                                <div style="background: #f1f1f1; padding: 8px 12px; border-radius: 4px; margin-bottom: 8px; font-family: monospace; font-size: 13px;">
                                    <?php echo esc_html(substr($vonage_api_key, 0, 4) . str_repeat('*', 8)); ?>
                                </div>
                            <?php endif; ?>
                            <input type="text"
                                   name="voxel_toolkit_options[sms_notifications][vonage_api_key]"
                                   value=""
                                   placeholder="<?php echo !empty($vonage_api_key) ? __('Enter new key to replace', 'voxel-toolkit') : 'abcd1234'; ?>"
                                   style="width: 100%; max-width: 250px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;" />
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 5px;"><?php _e('API Secret', 'voxel-toolkit'); ?></label>
                            <?php if (!empty($vonage_api_secret)): ?>
                                <div style="background: #f1f1f1; padding: 8px 12px; border-radius: 4px; margin-bottom: 8px; font-family: monospace; font-size: 13px;">
                                    <?php echo str_repeat('*', 16); ?>
                                </div>
                            <?php endif; ?>
                            <input type="password"
                                   name="voxel_toolkit_options[sms_notifications][vonage_api_secret]"
                                   value=""
                                   placeholder="<?php echo !empty($vonage_api_secret) ? __('Enter new secret to replace', 'voxel-toolkit') : __('API Secret', 'voxel-toolkit'); ?>"
                                   autocomplete="off"
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" />
                        </div>

                        <div>
                            <label style="display: block; font-weight: 500; margin-bottom: 5px;"><?php _e('From Name/Number', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[sms_notifications][vonage_from]"
                                   value="<?php echo esc_attr($vonage_from); ?>"
                                   placeholder="MyBusiness or +15551234567"
                                   style="width: 100%; max-width: 250px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" />
                            <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                                <?php _e('Alphanumeric (max 11 chars) or phone number', 'voxel-toolkit'); ?>
                            </p>
                        </div>

                        <p style="margin: 15px 0 0 0; font-size: 13px; color: #666;">
                            <?php _e('Get your credentials from', 'voxel-toolkit'); ?>
                            <a href="https://dashboard.nexmo.com/" target="_blank" style="color: #2271b1;"><?php _e('Vonage Dashboard', 'voxel-toolkit'); ?></a>
                        </p>
                    </div>

                    <!-- MessageBird Configuration -->
                    <div id="messagebird_config" class="sms-provider-config" style="margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 6px; <?php echo $provider !== 'messagebird' ? 'display: none;' : ''; ?>">
                        <h4 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 15px;">
                            <?php _e('MessageBird Configuration', 'voxel-toolkit'); ?>
                            <?php if ($has_messagebird): ?>
                                <span style="background: #d4edda; color: #155724; padding: 3px 10px; border-radius: 12px; font-size: 11px; margin-left: 10px;"><?php _e('Configured', 'voxel-toolkit'); ?></span>
                            <?php endif; ?>
                        </h4>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 5px;"><?php _e('API Key', 'voxel-toolkit'); ?></label>
                            <?php if (!empty($messagebird_api_key)): ?>
                                <div style="background: #f1f1f1; padding: 8px 12px; border-radius: 4px; margin-bottom: 8px; font-family: monospace; font-size: 13px;">
                                    <?php echo esc_html(substr($messagebird_api_key, 0, 8) . str_repeat('*', 20)); ?>
                                </div>
                            <?php endif; ?>
                            <input type="password"
                                   name="voxel_toolkit_options[sms_notifications][messagebird_api_key]"
                                   value=""
                                   placeholder="<?php echo !empty($messagebird_api_key) ? __('Enter new key to replace', 'voxel-toolkit') : __('API Key', 'voxel-toolkit'); ?>"
                                   autocomplete="off"
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" />
                        </div>

                        <div>
                            <label style="display: block; font-weight: 500; margin-bottom: 5px;"><?php _e('Originator', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[sms_notifications][messagebird_originator]"
                                   value="<?php echo esc_attr($messagebird_originator); ?>"
                                   placeholder="MyBusiness or +15551234567"
                                   style="width: 100%; max-width: 250px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" />
                            <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                                <?php _e('Sender name (max 11 chars) or phone number', 'voxel-toolkit'); ?>
                            </p>
                        </div>

                        <p style="margin: 15px 0 0 0; font-size: 13px; color: #666;">
                            <?php _e('Get your credentials from', 'voxel-toolkit'); ?>
                            <a href="https://dashboard.messagebird.com/" target="_blank" style="color: #2271b1;"><?php _e('MessageBird Dashboard', 'voxel-toolkit'); ?></a>
                        </p>
                    </div>

                    <!-- Phone Field Selection -->
                    <?php
                    $phone_field = isset($settings['phone_field']) ? $settings['phone_field'] : '';
                    $phone_fields = $this->get_voxel_phone_fields();
                    $country_code = isset($settings['country_code']) ? $settings['country_code'] : '';
                    ?>
                    <div style="margin-bottom: 25px; padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px;">
                        <h4 style="margin: 0 0 15px 0; color: #856404; font-size: 15px;">
                            <?php _e('Phone Field Selection', 'voxel-toolkit'); ?>
                        </h4>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px;"><?php _e('Profile Phone Field', 'voxel-toolkit'); ?></label>
                            <select name="voxel_toolkit_options[sms_notifications][phone_field]"
                                    style="width: 100%; max-width: 350px; padding: 12px; border: 2px solid #ffc107; border-radius: 6px; font-size: 14px; background: white;">
                                <option value=""><?php _e('-- Select Phone Field --', 'voxel-toolkit'); ?></option>
                                <?php foreach ($phone_fields as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($phone_field, $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p style="margin: 8px 0 0 0; font-size: 12px; color: #856404;">
                                <?php _e('Select the phone field from user profiles. SMS notifications will be sent to the recipient\'s phone number stored in this field.', 'voxel-toolkit'); ?>
                            </p>
                            <?php if (empty($phone_fields)): ?>
                                <p style="margin: 8px 0 0 0; font-size: 12px; color: #d63638;">
                                    <strong><?php _e('No phone fields found.', 'voxel-toolkit'); ?></strong>
                                    <?php _e('Add a phone field to your Profile post type in Voxel → Post Types → Profile → Fields.', 'voxel-toolkit'); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div style="margin-bottom: 15px; margin-top: 15px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px;"><?php _e('Default Country Code', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[sms_notifications][country_code]"
                                   value="<?php echo esc_attr($country_code); ?>"
                                   placeholder="+1"
                                   style="width: 100%; max-width: 150px; padding: 12px; border: 2px solid #ffc107; border-radius: 6px; font-size: 14px; font-family: monospace;" />
                            <p style="margin: 8px 0 0 0; font-size: 12px; color: #856404;">
                                <?php _e('Country code to prepend to phone numbers that don\'t include one (e.g., +1 for US/Canada, +44 for UK, +61 for Australia).', 'voxel-toolkit'); ?>
                            </p>
                        </div>

                        <div style="padding: 12px; background: rgba(255,255,255,0.8); border-radius: 4px; font-size: 13px; color: #856404;">
                            <strong><?php _e('How it works:', 'voxel-toolkit'); ?></strong>
                            <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                                <li><?php _e('SMS to Customer → Uses customer\'s profile phone field', 'voxel-toolkit'); ?></li>
                                <li><?php _e('SMS to Vendor/Author → Uses vendor\'s profile phone field', 'voxel-toolkit'); ?></li>
                                <li><?php _e('SMS to Admin → Uses admin user\'s profile phone field', 'voxel-toolkit'); ?></li>
                            </ul>
                            <p style="margin: 8px 0 0 0;">
                                <?php _e('If a recipient has no phone number, the SMS will be silently skipped.', 'voxel-toolkit'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Test SMS -->
                    <div style="margin-bottom: 25px; padding: 20px; background: #f0f6fc; border: 1px solid #c8d7e5; border-radius: 6px;">
                        <h4 style="margin: 0 0 15px 0; color: #0d3c61; font-size: 15px;">
                            <?php _e('Test SMS', 'voxel-toolkit'); ?>
                        </h4>
                        <div style="display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <input type="text"
                                       id="test_sms_phone"
                                       placeholder="+15551234567"
                                       style="width: 100%; max-width: 250px; padding: 12px; border: 1px solid #c8d7e5; border-radius: 6px; font-family: monospace; font-size: 14px; background: white;" />
                                <p style="margin: 8px 0 0 0; font-size: 12px; color: #0d3c61;">
                                    <?php _e('Enter a phone number to test your SMS provider configuration', 'voxel-toolkit'); ?>
                                </p>
                            </div>
                            <button type="button" id="test_sms_btn" class="button button-secondary" style="padding: 10px 20px;">
                                <?php _e('Send Test SMS', 'voxel-toolkit'); ?>
                            </button>
                        </div>
                        <div id="test_sms_result" style="margin-top: 10px; display: none;"></div>
                    </div>

                    <!-- Usage Instructions -->
                    <div style="padding: 15px; background: #e8f5e9; border: 1px solid #81c784; border-radius: 6px;">
                        <h4 style="margin: 0 0 10px 0; color: #2e7d32; font-size: 14px;">
                            <?php _e('Setup Steps', 'voxel-toolkit'); ?>
                        </h4>
                        <ol style="margin: 0; padding-left: 20px; font-size: 13px; color: #2e7d32;">
                            <li><?php _e('Configure your SMS provider credentials above', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Select the phone field from your Profile post type', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Send a test SMS to verify your provider works', 'voxel-toolkit'); ?></li>
                            <li><?php printf(
                                __('Go to %s to enable SMS for specific events', 'voxel-toolkit'),
                                '<a href="' . esc_url(admin_url('admin.php?page=voxel-events')) . '" style="color: #2e7d32; font-weight: 600;">Voxel → App Events & Notifications</a>'
                            ); ?></li>
                        </ol>
                    </div>

                </div>

                <script>
                jQuery(document).ready(function($) {
                    // Provider selection toggle
                    $('#sms_provider_select').on('change', function() {
                        var provider = $(this).val();
                        $('.sms-provider-config').hide();
                        $('#' + provider + '_config').show();
                    });

                    // Test SMS button
                    $('#test_sms_btn').on('click', function() {
                        var $btn = $(this);
                        var $result = $('#test_sms_result');
                        var testPhone = $('#test_sms_phone').val().trim();

                        if (!testPhone) {
                            $result.html('<div style="color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px;"><?php _e('Please enter a phone number to test', 'voxel-toolkit'); ?></div>').show();
                            return;
                        }

                        $btn.prop('disabled', true).text('<?php _e('Sending...', 'voxel-toolkit'); ?>');
                        $result.hide();

                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'vt_send_test_sms',
                                nonce: '<?php echo wp_create_nonce('vt_sms_nonce'); ?>',
                                phone: testPhone
                            },
                            success: function(response) {
                                if (response.success) {
                                    $result.html('<div style="color: #155724; background: #d4edda; padding: 10px; border-radius: 4px;">' + response.data.message + '</div>').show();
                                } else {
                                    $result.html('<div style="color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px;">' + response.data.message + '</div>').show();
                                }
                            },
                            error: function() {
                                $result.html('<div style="color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px;"><?php _e('Network error. Please try again.', 'voxel-toolkit'); ?></div>').show();
                            },
                            complete: function() {
                                $btn.prop('disabled', false).text('<?php _e('Send Test SMS', 'voxel-toolkit'); ?>');
                            }
                        });
                    });
                });
                </script>
            </td>
        </tr>
        <?php
    }

    /**
     * Render settings for advanced phone input function
     *
     * @param array $settings Current settings
     */
    public function render_advanced_phone_input_settings($settings) {
        ?>
        <tr>
            <th scope="row">
                <label><?php _e('Advanced Phone Input', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <div class="vt-info-box" style="background: #f0f6fc; border: 1px solid #0969da; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                    <strong style="display: block; margin-bottom: 10px; color: #0969da;">
                        <span class="dashicons dashicons-info" style="margin-right: 5px;"></span>
                        <?php _e('Per-Field Configuration', 'voxel-toolkit'); ?>
                    </strong>
                    <p style="margin: 0 0 10px 0; color: #1e1e1e;">
                        <?php _e('This function adds international phone number input capabilities to Voxel phone fields. Configure each phone field individually in the Post Type editor.', 'voxel-toolkit'); ?>
                    </p>
                    <p style="margin: 0; color: #1e1e1e;">
                        <strong><?php _e('New settings appear under each phone field:', 'voxel-toolkit'); ?></strong>
                    </p>
                    <ul style="list-style: disc; margin: 10px 0 0 20px; color: #1e1e1e;">
                        <li><strong><?php _e('Default Country', 'voxel-toolkit'); ?></strong> - <?php _e('Set the initial country code (e.g., "us", "gb", "de")', 'voxel-toolkit'); ?></li>
                        <li><strong><?php _e('Only Countries', 'voxel-toolkit'); ?></strong> - <?php _e('Restrict to specific countries', 'voxel-toolkit'); ?></li>
                        <li><strong><?php _e('Country Selector Dropdown', 'voxel-toolkit'); ?></strong> - <?php _e('Toggle the country selection dropdown visibility', 'voxel-toolkit'); ?></li>
                    </ul>
                </div>

                <div class="vt-info-box" style="background: #fff8e6; border: 1px solid #f0c36d; border-radius: 8px; padding: 15px;">
                    <strong style="display: block; margin-bottom: 8px; color: #735c0f;">
                        <span class="dashicons dashicons-warning" style="margin-right: 5px;"></span>
                        <?php _e('Note', 'voxel-toolkit'); ?>
                    </strong>
                    <p style="margin: 0; color: #735c0f;">
                        <?php _e('If you have SMS Notifications enabled, the international phone input from that function will be replaced by this enhanced version with per-field configuration.', 'voxel-toolkit'); ?>
                    </p>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Render settings for membership notifications function
     *
     * @param array $settings Current settings
     */
    public function render_membership_notifications_settings($settings) {
        $notifications = isset($settings['notifications']) ? $settings['notifications'] : array();
        ?>
                <div id="membership-notifications-container">
                    <div class="vt-info-box" style="margin-bottom: 20px;">
                        <strong><?php _e('Email Notification Setup', 'voxel-toolkit'); ?></strong><br>
                        <?php _e('Configure automated email notifications to send to members before their subscription expires. Create multiple notification rules with different timing.', 'voxel-toolkit'); ?>

                        <div style="background: white; padding: 15px; border-radius: 6px; border: 1px solid #e1e5e9; margin-top: 15px;">
                            <strong style="display: block; margin-bottom: 8px; color: #1e1e1e;">Available Variables (click to copy):</strong>
                            <div class="variable-tags" style="display: flex; flex-wrap: wrap; gap: 8px;">
                                <span class="variable-tag" data-variable="{expiration_date}" style="background: #f1f1f1; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-family: monospace; font-size: 13px; border: 1px solid #ddd; transition: all 0.2s;" title="Click to copy">{expiration_date}</span>
                                <span class="variable-tag" data-variable="{amount}" style="background: #f1f1f1; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-family: monospace; font-size: 13px; border: 1px solid #ddd; transition: all 0.2s;" title="Click to copy">{amount}</span>
                                <span class="variable-tag" data-variable="{currency}" style="background: #f1f1f1; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-family: monospace; font-size: 13px; border: 1px solid #ddd; transition: all 0.2s;" title="Click to copy">{currency}</span>
                                <span class="variable-tag" data-variable="{plan_name}" style="background: #f1f1f1; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-family: monospace; font-size: 13px; border: 1px solid #ddd; transition: all 0.2s;" title="Click to copy">{plan_name}</span>
                                <span class="variable-tag" data-variable="{remaining_days}" style="background: #f1f1f1; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-family: monospace; font-size: 13px; border: 1px solid #ddd; transition: all 0.2s;" title="Click to copy">{remaining_days}</span>
                            </div>
                            <small style="display: block; margin-top: 8px; color: #646970;">HTML is supported in the email body. Variables will be replaced with actual member data when emails are sent.</small>
                        </div>
                    </div>
                    
                    <div style="background: white; border: 1px solid #e1e5e9; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <table class="wp-list-table widefat" id="notifications-table" style="margin: 0; border: none;">
                            <thead style="background: #f8f9fa;">
                                <tr>
                                    <th style="padding: 15px 20px; font-weight: 600; color: #1e1e1e; border-bottom: 2px solid #e1e5e9; width: 120px;"><?php _e('Timing', 'voxel-toolkit'); ?></th>
                                    <th style="padding: 15px 20px; font-weight: 600; color: #1e1e1e; border-bottom: 2px solid #e1e5e9; width: 100px;"><?php _e('Value', 'voxel-toolkit'); ?></th>
                                    <th style="padding: 15px 20px; font-weight: 600; color: #1e1e1e; border-bottom: 2px solid #e1e5e9;"><?php _e('Email Subject', 'voxel-toolkit'); ?></th>
                                    <th style="padding: 15px 20px; font-weight: 600; color: #1e1e1e; border-bottom: 2px solid #e1e5e9;"><?php _e('Email Body', 'voxel-toolkit'); ?></th>
                                    <th style="padding: 15px 20px; font-weight: 600; color: #1e1e1e; border-bottom: 2px solid #e1e5e9; width: 140px; text-align: center;"><?php _e('Actions', 'voxel-toolkit'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($notifications)): ?>
                                    <tr id="no-notifications-row">
                                        <td colspan="5" style="padding: 40px; text-align: center; color: #646970; font-style: italic;">
                                            <?php _e('No notifications configured yet. Click "Add New Notification" to get started.', 'voxel-toolkit'); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($notifications as $index => $notif): ?>
                                        <tr style="border-bottom: 1px solid #f0f0f1;">
                                            <td style="padding: 20px; vertical-align: top;">
                                                <select name="voxel_toolkit_options[membership_notifications][notifications][<?php echo esc_attr($index); ?>][unit]" 
                                                        style="width: 100%; padding: 8px 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 14px; background: white;">
                                                    <option value="days" <?php selected($notif['unit'] ?? '', 'days'); ?>><?php _e('Days', 'voxel-toolkit'); ?></option>
                                                    <option value="hours" <?php selected($notif['unit'] ?? '', 'hours'); ?>><?php _e('Hours', 'voxel-toolkit'); ?></option>
                                                </select>
                                            </td>
                                            <td style="padding: 20px; vertical-align: top;">
                                                <input type="number" min="1" 
                                                       name="voxel_toolkit_options[membership_notifications][notifications][<?php echo esc_attr($index); ?>][value]" 
                                                       value="<?php echo esc_attr($notif['value'] ?? ''); ?>"
                                                       style="width: 80px; padding: 8px 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 14px; text-align: center;" />
                                            </td>
                                            <td style="padding: 20px; vertical-align: top;">
                                                <input type="text" 
                                                       name="voxel_toolkit_options[membership_notifications][notifications][<?php echo esc_attr($index); ?>][subject]" 
                                                       value="<?php echo esc_attr($notif['subject'] ?? ''); ?>"
                                                       placeholder="<?php _e('e.g., Your membership expires in {remaining_days} days', 'voxel-toolkit'); ?>"
                                                       style="width: 100%; padding: 8px 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 14px;" />
                                            </td>
                                            <td style="padding: 20px; vertical-align: top;">
                                                <textarea name="voxel_toolkit_options[membership_notifications][notifications][<?php echo esc_attr($index); ?>][body]" 
                                                          placeholder="<?php _e('e.g., Hello! Your {plan_name} membership expires on {expiration_date}. Renew now for ${amount} {currency}.', 'voxel-toolkit'); ?>"
                                                          style="width: 100%; height: 100px; padding: 8px 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; resize: vertical;"><?php echo esc_textarea($notif['body'] ?? ''); ?></textarea>
                                            </td>
                                            <td style="padding: 20px; text-align: center; vertical-align: top;">
                                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                                    <button type="button" class="button button-secondary notification-test-btn" 
                                                            data-index="<?php echo esc_attr($index); ?>">
                                                        <?php _e('Test', 'voxel-toolkit'); ?>
                                                    </button>
                                                    <button type="button" class="button button-secondary notification-remove-btn">
                                                        <?php _e('Remove', 'voxel-toolkit'); ?>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e1e5e9;">
                        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                            <button type="button" class="button button-primary" id="add-notification-btn">
                                <?php _e('Add New Notification', 'voxel-toolkit'); ?>
                            </button>
                            <button type="button" class="button button-secondary" id="manual-notifications-btn">
                                <?php _e('Send Manual Notifications', 'voxel-toolkit'); ?>
                            </button>
                        </div>
                        <p style="margin: 15px 0 0 0; color: #646970; font-size: 13px;">
                            <strong>Tip:</strong> Create multiple notification rules to remind users at different times (e.g., 30 days, 7 days, and 1 day before expiration).
                        </p>
                    </div>
                </div>
                
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    
                    let notificationIndex = <?php echo count($notifications); ?>;
                    let currentTestIndex = 0;
                    
                    
                    // Copy to clipboard functionality for variable tags
                    $('.variable-tag').click(function() {
                        const variable = $(this).data('variable');
                        
                        // Create temporary input element
                        const tempInput = $('<input>');
                        $('body').append(tempInput);
                        tempInput.val(variable).select();
                        document.execCommand('copy');
                        tempInput.remove();
                        
                        // Visual feedback
                        const originalBg = $(this).css('background');
                        $(this).css({
                            'background': '#e0e0e0',
                            'transform': 'scale(1.05)'
                        });
                        
                        setTimeout(() => {
                            $(this).css({
                                'background': originalBg,
                                'transform': 'scale(1)'
                            });
                        }, 200);
                        
                        // Show tooltip
                        const tooltip = $('<div style="position: absolute; background: #333; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; z-index: 9999;">Copied!</div>');
                        $('body').append(tooltip);
                        
                        const offset = $(this).offset();
                        tooltip.css({
                            top: offset.top - 30,
                            left: offset.left + ($(this).width() / 2) - (tooltip.width() / 2)
                        });
                        
                        setTimeout(() => tooltip.remove(), 1000);
                    });
                    
                    // Add hover effects for variable tags
                    $('.variable-tag').hover(
                        function() {
                            $(this).css({
                                'background': '#e0e0e0',
                                'transform': 'translateY(-1px)'
                            });
                        },
                        function() {
                            $(this).css({
                                'background': '#f1f1f1',
                                'transform': 'translateY(0)'
                            });
                        }
                    );
                    
                    // Add notification row (using event delegation)
                    $(document).on('click', '#add-notification-btn', function(e) {
                        e.preventDefault(); // Prevent form submission
                        e.stopPropagation(); // Stop event bubbling
                        
                        // Remove the "no notifications" row if it exists
                        $('#no-notifications-row').remove();
                        
                        const row = `
                            <tr style="border-bottom: 1px solid #f0f0f1;">
                                <td style="padding: 20px; vertical-align: top;">
                                    <select name="voxel_toolkit_options[membership_notifications][notifications][${notificationIndex}][unit]" 
                                            style="width: 100%; padding: 8px 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 14px; background: white;">
                                        <option value="days"><?php _e('Days', 'voxel-toolkit'); ?></option>
                                        <option value="hours"><?php _e('Hours', 'voxel-toolkit'); ?></option>
                                    </select>
                                </td>
                                <td style="padding: 20px; vertical-align: top;">
                                    <input type="number" min="1" 
                                           name="voxel_toolkit_options[membership_notifications][notifications][${notificationIndex}][value]"
                                           style="width: 80px; padding: 8px 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 14px; text-align: center;" />
                                </td>
                                <td style="padding: 20px; vertical-align: top;">
                                    <input type="text" 
                                           name="voxel_toolkit_options[membership_notifications][notifications][${notificationIndex}][subject]"
                                           placeholder="e.g., Your membership expires in {remaining_days} days"
                                           style="width: 100%; padding: 8px 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 14px;" />
                                </td>
                                <td style="padding: 20px; vertical-align: top;">
                                    <textarea name="voxel_toolkit_options[membership_notifications][notifications][${notificationIndex}][body]"
                                              placeholder="e.g., Hello! Your {plan_name} membership expires on {expiration_date}. Renew now for $25.00 USD."
                                              style="width: 100%; height: 100px; padding: 8px 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; resize: vertical;"></textarea>
                                </td>
                                <td style="padding: 20px; text-align: center; vertical-align: top;">
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        <button type="button" class="button button-secondary notification-test-btn" 
                                                data-index="${notificationIndex}">
                                            <?php _e('Test', 'voxel-toolkit'); ?>
                                        </button>
                                        <button type="button" class="button button-secondary notification-remove-btn">
                                            <?php _e('Remove', 'voxel-toolkit'); ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                        
                        $('#notifications-table tbody').append(row);
                        notificationIndex++;
                        
                    });
                    
                    
                    // Remove notification row
                    $(document).on('click', '.notification-remove-btn', function() {
                        $(this).closest('tr').remove();
                    });
                    
                    // Create test email modal and append to body (outside the form)
                    const testEmailModal = $(`
                        <div id="test-email-modal" style="display: none;">
                            <div style="background: rgba(0,0,0,0.7); position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 100000;">
                                <div style="background: white; width: 500px; margin: 80px auto; padding: 0; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); overflow: hidden;">
                                    <div style="background: #f8f9fa; border-bottom: 1px solid #e1e5e9; padding: 20px;">
                                        <h3 style="margin: 0; font-size: 18px; color: #1e1e1e;">
                                            <?php _e('Send Test Email', 'voxel-toolkit'); ?>
                                        </h3>
                                    </div>
                                    <div style="padding: 25px;">
                                        <p style="margin: 0 0 15px 0; color: #646970;">
                                            <?php _e('Enter an email address to receive a test notification with sample data:', 'voxel-toolkit'); ?>
                                        </p>
                                        <div style="margin-bottom: 20px;">
                                            <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #1e1e1e;">
                                                <?php _e('Test Email Address:', 'voxel-toolkit'); ?>
                                            </label>
                                            <input type="email" id="test-email-address" 
                                                   style="width: 100%; padding: 12px 16px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;"
                                                   placeholder="your-email@example.com">
                                        </div>
                                        <div style="display: flex; gap: 12px; justify-content: flex-end;">
                                            <button type="button" class="button button-secondary" id="cancel-test-email-btn">
                                                <?php _e('Cancel', 'voxel-toolkit'); ?>
                                            </button>
                                            <button type="button" class="button button-primary" id="send-test-email-btn">
                                                <?php _e('Send Test Email', 'voxel-toolkit'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `);
                    $('body').append(testEmailModal);
                    
                    // Test notification
                    $(document).on('click', '.notification-test-btn', function() {
                        currentTestIndex = $(this).data('index');
                        $('#test-email-modal').show();
                    });
                    
                    // Cancel test email
                    $(document).on('click', '#cancel-test-email-btn', function() {
                        $('#test-email-modal').hide();
                        $('#test-email-address').val('');
                    });
                    
                    // Send test email
                    $(document).on('click', '#send-test-email-btn', function() {
                        const email = $('#test-email-address').val();
                        if (!email || !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                            alert('<?php _e('Please enter a valid email address.', 'voxel-toolkit'); ?>');
                            return;
                        }
                        
                        const row = $('#notifications-table tbody tr').eq(currentTestIndex);
                        const unit = row.find('select[name*="[unit]"]').val();
                        const value = row.find('input[name*="[value]"]').val();
                        const subject = row.find('input[name*="[subject]"]').val();
                        const body = row.find('textarea[name*="[body]"]').val();
                        
                        if (!unit || !value || !subject || !body) {
                            alert('<?php _e('Please fill in all notification fields first.', 'voxel-toolkit'); ?>');
                            return;
                        }
                        
                        $.post(ajaxurl, {
                            action: 'voxel_toolkit_send_test_notification',
                            nonce: '<?php echo wp_create_nonce('voxel_toolkit_nonce'); ?>',
                            test_email: email,
                            unit: unit,
                            value: value,
                            subject: subject,
                            body: body
                        }, function(response) {
                            if (response.success) {
                                alert('<?php _e('Test email sent successfully!', 'voxel-toolkit'); ?>');
                                $('#test-email-modal').hide();
                                $('#test-email-address').val('');
                            } else {
                                alert('<?php _e('Error sending test email: ', 'voxel-toolkit'); ?>' + (response.data || '<?php _e('Unknown error', 'voxel-toolkit'); ?>'));
                            }
                        });
                    });
                    
                    // Manual notifications
                    $('#manual-notifications-btn').click(function() {
                        if (!confirm('<?php _e('Are you sure you want to manually send out reminders? This will send emails to all applicable users.', 'voxel-toolkit'); ?>')) {
                            return;
                        }
                        
                        $(this).prop('disabled', true).text('<?php _e('Sending...', 'voxel-toolkit'); ?>');
                        
                        $.post(ajaxurl, {
                            action: 'voxel_toolkit_manual_notifications',
                            nonce: '<?php echo wp_create_nonce('voxel_toolkit_nonce'); ?>'
                        }, function(response) {
                            if (response.success) {
                                alert('<?php _e('Manual notifications sent successfully!', 'voxel-toolkit'); ?>');
                            } else {
                                alert('<?php _e('Error sending manual notifications: ', 'voxel-toolkit'); ?>' + (response.data || '<?php _e('Unknown error', 'voxel-toolkit'); ?>'));
                            }
                            $('#manual-notifications-btn').prop('disabled', false).text('<?php _e('Send Manual Notifications', 'voxel-toolkit'); ?>');
                        });
                    });
                });
                </script>
        <?php
    }

    /**
     * Render settings for guest view function
     * 
     * @param array $settings Current settings
     */
    public function render_guest_view_settings($settings) {
        ?>
        <div class="vt-info-box">
            <?php _e('Add the "Guest View Button" widget to your pages using Elementor (found in "Voxel Toolkit" category).', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('General Settings', 'voxel-toolkit'); ?></h4>
            <div class="vt-checkbox-list">
                <label class="vt-checkbox-item">
                    <input type="checkbox"
                           name="voxel_toolkit_options[guest_view][show_confirmation]"
                           value="1"
                           <?php checked(!empty($settings['show_confirmation'])); ?> />
                    <div class="vt-checkbox-item-content">
                        <span class="vt-checkbox-item-label"><?php _e('Show confirmation dialog', 'voxel-toolkit'); ?></span>
                        <p class="vt-checkbox-item-description"><?php _e('Ask for confirmation before switching to guest view', 'voxel-toolkit'); ?></p>
                    </div>
                </label>
            </div>

            <div class="vt-field-group" style="margin-top: 20px;">
                <label class="vt-field-label"><?php _e('Button Position', 'voxel-toolkit'); ?></label>
                <select name="voxel_toolkit_options[guest_view][button_position]" class="vt-select">
                    <option value="top-left" <?php selected(isset($settings['button_position']) ? $settings['button_position'] : '', 'top-left'); ?>><?php _e('Top Left', 'voxel-toolkit'); ?></option>
                    <option value="top-right" <?php selected(isset($settings['button_position']) ? $settings['button_position'] : '', 'top-right'); ?>><?php _e('Top Right', 'voxel-toolkit'); ?></option>
                    <option value="middle-left" <?php selected(isset($settings['button_position']) ? $settings['button_position'] : '', 'middle-left'); ?>><?php _e('Middle Left', 'voxel-toolkit'); ?></option>
                    <option value="middle-right" <?php selected(isset($settings['button_position']) ? $settings['button_position'] : '', 'middle-right'); ?>><?php _e('Middle Right', 'voxel-toolkit'); ?></option>
                    <option value="bottom-left" <?php selected(isset($settings['button_position']) ? $settings['button_position'] : '', 'bottom-left'); ?>><?php _e('Bottom Left', 'voxel-toolkit'); ?></option>
                    <option value="bottom-right" <?php selected(isset($settings['button_position']) ? $settings['button_position'] : '', 'bottom-right'); ?>><?php _e('Bottom Right', 'voxel-toolkit'); ?></option>
                </select>
                <p class="vt-field-description"><?php _e('Where to show the floating "Exit Guest View" button (always bottom center on mobile)', 'voxel-toolkit'); ?></p>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Exit Button Colors', 'voxel-toolkit'); ?></h4>
            <div class="vt-checkbox-inline" style="gap: 32px; margin-bottom: 20px;">
                <div class="vt-field-group">
                    <label class="vt-field-label"><?php _e('Background Color', 'voxel-toolkit'); ?></label>
                    <input type="text"
                           name="voxel_toolkit_options[guest_view][bg_color]"
                           value="<?php echo esc_attr(isset($settings['bg_color']) ? $settings['bg_color'] : '#667eea'); ?>"
                           placeholder="#667eea"
                           class="vt-text-input guest-view-bg-color"
                           style="max-width: 150px; font-family: monospace;" />
                </div>
                <div class="vt-field-group">
                    <label class="vt-field-label"><?php _e('Text Color', 'voxel-toolkit'); ?></label>
                    <input type="text"
                           name="voxel_toolkit_options[guest_view][text_color]"
                           value="<?php echo esc_attr(isset($settings['text_color']) ? $settings['text_color'] : '#ffffff'); ?>"
                           placeholder="#ffffff"
                           class="vt-text-input guest-view-text-color"
                           style="max-width: 150px; font-family: monospace;" />
                </div>
            </div>

            <div style="background: #f8f9fa; border: 1px solid #e5e7eb; border-radius: 6px; padding: 20px; text-align: center;">
                <p style="margin: 0 0 15px 0; font-weight: 500; color: #1e293b;"><?php _e('Live Preview:', 'voxel-toolkit'); ?></p>
                <div id="guest-view-button-preview">
                    <button type="button" style="background: <?php echo esc_attr(isset($settings['bg_color']) ? $settings['bg_color'] : '#667eea'); ?>; color: <?php echo esc_attr(isset($settings['text_color']) ? $settings['text_color'] : '#ffffff'); ?>; border: none; padding: 12px 20px; border-radius: 25px; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);">
                        <?php _e('Exit Guest View', 'voxel-toolkit'); ?>
                    </button>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            function isValidHex(hex) { return /^#[0-9A-Fa-f]{6}$/.test(hex); }
            function updatePreview() {
                var bgColor = $('.guest-view-bg-color').val();
                var textColor = $('.guest-view-text-color').val();
                var $btn = $('#guest-view-button-preview button');
                if (isValidHex(bgColor)) $btn.css('background', bgColor);
                if (isValidHex(textColor)) $btn.css('color', textColor);
            }
            $('.guest-view-bg-color, .guest-view-text-color').on('input keyup paste', function() { setTimeout(updatePreview, 50); });
        });
        </script>
        <?php
    }
    
    /**
     * Render settings for AI Review Summary function
     * 
     * @param array $settings Current settings
     */
    public function render_ai_review_summary_settings($settings) {
        $cache_refreshed = isset($_GET['ai_cache_refreshed']) ? intval($_GET['ai_cache_refreshed']) : 0;
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
        $has_api_key = !empty($api_key) && strlen($api_key) > 10;
        $current_language = isset($settings['language']) ? $settings['language'] : 'en';

        $languages = array(
            'en' => __('English', 'voxel-toolkit'), 'it' => __('Italian', 'voxel-toolkit'),
            'es' => __('Spanish', 'voxel-toolkit'), 'fr' => __('French', 'voxel-toolkit'),
            'de' => __('German', 'voxel-toolkit'), 'pt' => __('Portuguese', 'voxel-toolkit'),
            'nl' => __('Dutch', 'voxel-toolkit'), 'ru' => __('Russian', 'voxel-toolkit'),
            'zh' => __('Chinese', 'voxel-toolkit'), 'ja' => __('Japanese', 'voxel-toolkit'),
            'ko' => __('Korean', 'voxel-toolkit'), 'ar' => __('Arabic', 'voxel-toolkit'),
            'hi' => __('Hindi', 'voxel-toolkit'), 'tr' => __('Turkish', 'voxel-toolkit'),
            'pl' => __('Polish', 'voxel-toolkit'), 'sv' => __('Swedish', 'voxel-toolkit'),
            'da' => __('Danish', 'voxel-toolkit'), 'no' => __('Norwegian', 'voxel-toolkit'),
            'fi' => __('Finnish', 'voxel-toolkit'), 'cs' => __('Czech', 'voxel-toolkit'),
            'hu' => __('Hungarian', 'voxel-toolkit'), 'ro' => __('Romanian', 'voxel-toolkit'),
            'bg' => __('Bulgarian', 'voxel-toolkit'), 'hr' => __('Croatian', 'voxel-toolkit'),
            'sk' => __('Slovak', 'voxel-toolkit'), 'sl' => __('Slovenian', 'voxel-toolkit'),
            'et' => __('Estonian', 'voxel-toolkit'), 'lv' => __('Latvian', 'voxel-toolkit'),
            'lt' => __('Lithuanian', 'voxel-toolkit'), 'el' => __('Greek', 'voxel-toolkit'),
            'he' => __('Hebrew', 'voxel-toolkit'), 'th' => __('Thai', 'voxel-toolkit'),
            'vi' => __('Vietnamese', 'voxel-toolkit'), 'id' => __('Indonesian', 'voxel-toolkit'),
            'ms' => __('Malay', 'voxel-toolkit'), 'uk' => __('Ukrainian', 'voxel-toolkit'),
        );
        ?>
        <div class="vt-warning-box">
            <strong><?php _e('Important:', 'voxel-toolkit'); ?></strong>
            <?php _e('Summaries are cached until new reviews are added. API calls are only made when cache is empty or outdated. OpenAI API usage costs apply.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('ChatGPT API Configuration', 'voxel-toolkit'); ?></h4>
            <div class="vt-field-group">
                <label class="vt-field-label"><?php _e('OpenAI API Key', 'voxel-toolkit'); ?></label>
                <?php if ($has_api_key): ?>
                    <div class="vt-tip-box" style="margin-bottom: 12px;">
                        <span style="font-family: monospace;"><?php echo esc_html(substr($api_key, 0, 7) . str_repeat('*', 20)); ?></span>
                        <span style="background: #065f46; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 10px;"><?php _e('Active', 'voxel-toolkit'); ?></span>
                    </div>
                <?php endif; ?>
                <input type="text"
                       name="ai_api_key"
                       value=""
                       placeholder="<?php echo $has_api_key ? __('Enter new API key to replace existing one', 'voxel-toolkit') : 'sk-proj-...'; ?>"
                       autocomplete="off"
                       class="vt-text-input"
                       style="max-width: 500px; font-family: monospace;" />
                <p class="vt-field-description">
                    <?php _e('Get your OpenAI API key from', 'voxel-toolkit'); ?>
                    <a href="https://platform.openai.com/api-keys" target="_blank"><?php _e('OpenAI Platform', 'voxel-toolkit'); ?></a>
                </p>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Language Settings', 'voxel-toolkit'); ?></h4>
            <div class="vt-field-group">
                <label class="vt-field-label"><?php _e('AI Output Language', 'voxel-toolkit'); ?></label>
                <select name="voxel_toolkit_options[ai_review_summary][language]" class="vt-select" style="max-width: 300px;">
                    <?php foreach ($languages as $code => $name): ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($current_language, $code); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="vt-field-description"><?php _e('Select the language for AI-generated summaries and opinions.', 'voxel-toolkit'); ?></p>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Cache Management', 'voxel-toolkit'); ?></h4>
            <?php if ($cache_refreshed): ?>
                <div class="vt-tip-box" style="margin-bottom: 16px;">
                    <strong><?php _e('Success:', 'voxel-toolkit'); ?></strong> <?php _e('AI cached summaries have been refreshed.', 'voxel-toolkit'); ?>
                </div>
            <?php endif; ?>
            <p class="vt-field-description" style="margin-bottom: 12px;"><?php _e('Clear all cached AI-generated summaries. New summaries will be generated on the next page load.', 'voxel-toolkit'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('voxel_toolkit_refresh_ai_cache', 'voxel_toolkit_refresh_ai_cache_nonce'); ?>
                <input type="hidden" name="action" value="voxel_toolkit_refresh_ai_cache">
                <button type="submit" class="button button-secondary" onclick="return confirm('<?php _e('Are you sure? This will trigger new API calls.', 'voxel-toolkit'); ?>');">
                    <?php _e('Refresh All Cached Summaries', 'voxel-toolkit'); ?>
                </button>
            </form>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Available Shortcodes', 'voxel-toolkit'); ?></h4>
            <div style="display: grid; gap: 16px;">
                <div style="background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px;">
                    <strong><?php _e('Review Summary', 'voxel-toolkit'); ?></strong>
                    <button type="button" class="button button-small copy-shortcode-btn" data-shortcode="[review_summary]" style="margin-left: 8px;"><?php _e('Copy', 'voxel-toolkit'); ?></button>
                    <p class="vt-field-description" style="margin: 8px 0;"><?php _e('Generates an AI-powered summary of all reviews for a post.', 'voxel-toolkit'); ?></p>
                    <code style="display: block; background: #1e293b; color: #f8fafc; padding: 10px; border-radius: 4px; font-size: 13px;">[review_summary]</code>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px;">
                    <strong><?php _e('Category Opinions', 'voxel-toolkit'); ?></strong>
                    <button type="button" class="button button-small copy-shortcode-btn" data-shortcode="[category_opinions]" style="margin-left: 8px;"><?php _e('Copy', 'voxel-toolkit'); ?></button>
                    <p class="vt-field-description" style="margin: 8px 0;"><?php _e('Creates category opinion boxes with one-word AI summaries.', 'voxel-toolkit'); ?></p>
                    <code style="display: block; background: #1e293b; color: #f8fafc; padding: 10px; border-radius: 4px; font-size: 13px;">[category_opinions categories="Food, Service, Value"]</code>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.copy-shortcode-btn').on('click', function() {
                var shortcode = $(this).data('shortcode');
                var $btn = $(this);
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(shortcode).select();
                document.execCommand('copy');
                $temp.remove();
                var originalText = $btn.text();
                $btn.text('<?php _e('Copied!', 'voxel-toolkit'); ?>').prop('disabled', true);
                setTimeout(function() { $btn.text(originalText).prop('disabled', false); }, 2000);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render settings for Show Field Description function
     *
     * @param array $settings Current settings
     */
    public function render_show_field_description_settings($settings) {
        ?>
        <div class="vt-info-box">
            <?php _e('This function automatically converts Voxel form field tooltip icons into visible descriptions displayed below field labels.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-tip-box">
            <strong><?php _e('Styling:', 'voxel-toolkit'); ?></strong>
            <?php _e('Style the field descriptions directly in Elementor using the "Field Description Style (VT)" section in the Create Post widget\'s Style tab.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Features', 'voxel-toolkit'); ?></h4>
            <ul class="vt-feature-list">
                <li><?php _e('Tooltip icons are automatically hidden', 'voxel-toolkit'); ?></li>
                <li><?php _e('Field descriptions appear as visible subtitles below labels', 'voxel-toolkit'); ?></li>
                <li><?php _e('Works on frontend and in Elementor editor preview', 'voxel-toolkit'); ?></li>
                <li><?php _e('Improves form accessibility and user experience', 'voxel-toolkit'); ?></li>
            </ul>
        </div>
        <?php
    }

    /**
     * Render settings for Suggest Edits function
     *
     * @param array $settings Current settings
     */
    public function render_suggest_edits_settings($settings) {
        ?>
        <div class="vt-info-box">
            <?php _e('This feature allows users to suggest edits to posts with a complete moderation workflow and notification system.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Features', 'voxel-toolkit'); ?></h4>
            <ul class="vt-feature-list">
                <li><?php _e('Users can suggest edits to posts via Suggest Edits widget', 'voxel-toolkit'); ?></li>
                <li><?php _e('Post authors receive notifications when edits are suggested', 'voxel-toolkit'); ?></li>
                <li><?php _e('Authors can approve, reject, or delete suggestions', 'voxel-toolkit'); ?></li>
                <li><?php _e('Pending Suggestions widget shows all suggestions for review', 'voxel-toolkit'); ?></li>
                <li><?php _e('Complete audit trail of all suggestion activity', 'voxel-toolkit'); ?></li>
            </ul>
        </div>
        <?php
    }

    /**
     * Render settings for Duplicate Post function
     *
     * @param array $settings Current settings
     */
    public function render_duplicate_post_settings($settings) {
        $post_types = isset($settings['post_types']) ? $settings['post_types'] : array();
        $allowed_roles = isset($settings['allowed_roles']) ? $settings['allowed_roles'] : array('contributor', 'author', 'editor', 'administrator');
        $redirect_pages = isset($settings['redirect_pages']) ? $settings['redirect_pages'] : array();
        $available_post_types = get_post_types(array(
            'public' => true,
            'show_ui' => true
        ), 'objects');
        
        // Get all user roles
        global $wp_roles;
        $all_roles = $wp_roles->roles;
        ?>
        <div class="vt-info-box">
            <?php _e('Adds a "Duplicate" option to quickly create copies of posts and pages. The duplicate will be created as a draft with "(Copy)" added to the title.', 'voxel-toolkit'); ?>
        </div>
                    
                    <!-- Post Types Selection -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;">
                            <?php _e('Enable for Post Types', 'voxel-toolkit'); ?>
                        </h3>
                        <p style="margin: 0 0 15px 0; color: #666; font-size: 14px;">
                            <?php _e('Select which post types should have the duplicate feature enabled:', 'voxel-toolkit'); ?>
                        </p>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <?php foreach ($available_post_types as $post_type): ?>
                                <?php 
                                // Skip certain post types
                                if (in_array($post_type->name, array('attachment', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part'))) {
                                    continue;
                                }
                                ?>
                                <label style="display: flex; align-items: center; padding: 8px; background: #f8f9fa; border-radius: 4px; cursor: pointer;">
                                    <input type="checkbox" 
                                           name="voxel_toolkit_options[duplicate_post][post_types][]" 
                                           value="<?php echo esc_attr($post_type->name); ?>"
                                           <?php checked(in_array($post_type->name, $post_types)); ?>
                                           style="margin-right: 8px;">
                                    <span><?php echo esc_html($post_type->label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Redirect Pages Selection -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;">
                            <?php _e('Duplication Redirect Pages', 'voxel-toolkit'); ?>
                        </h3>
                        <p style="margin: 0 0 15px 0; color: #666; font-size: 14px;">
                            <?php _e('For each enabled post type, select which page to redirect to after duplication:', 'voxel-toolkit'); ?>
                        </p>
                        <div style="padding: 12px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; font-size: 13px; margin-bottom: 15px;">
                            <strong style="color: #856404;"><?php _e('Important:', 'voxel-toolkit'); ?></strong> 
                            <?php _e('The selected page must have a "Create Post" widget configured for the respective post type. If no page is selected, the default "/create-{post-type}/" URL will be used.', 'voxel-toolkit'); ?>
                        </div>
                        <?php 
                        // Get all pages for dropdown
                        $all_pages = get_pages(array(
                            'post_status' => 'publish',
                            'sort_column' => 'post_title',
                            'sort_order' => 'ASC'
                        ));
                        ?>
                        <div style="background: #f8f9fa; border-radius: 6px; padding: 15px;">
                            <?php foreach ($available_post_types as $post_type): ?>
                                <?php 
                                // Skip certain post types
                                if (in_array($post_type->name, array('attachment', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part'))) {
                                    continue;
                                }
                                // Only show if post type is enabled
                                if (!in_array($post_type->name, $post_types)) {
                                    continue;
                                }
                                ?>
                                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 12px;">
                                    <label style="flex: 0 0 150px; font-weight: 500; color: #1e1e1e;">
                                        <?php echo esc_html($post_type->label); ?>:
                                    </label>
                                    <select name="voxel_toolkit_options[duplicate_post][redirect_pages][<?php echo esc_attr($post_type->name); ?>]" 
                                            style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        <option value=""><?php _e('— Use Default URL —', 'voxel-toolkit'); ?></option>
                                        <?php foreach ($all_pages as $page): ?>
                                            <option value="<?php echo esc_attr($page->ID); ?>" 
                                                    <?php selected(isset($redirect_pages[$post_type->name]) ? $redirect_pages[$post_type->name] : '', $page->ID); ?>>
                                                <?php echo esc_html($page->post_title); ?> (<?php echo esc_html(get_permalink($page->ID)); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($post_types)): ?>
                                <p style="margin: 0; color: #666; font-style: italic;">
                                    <?php _e('Please select post types above to configure redirect pages.', 'voxel-toolkit'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <script>
                        // Show/hide redirect page options based on enabled post types
                        jQuery(document).ready(function($) {
                            $('input[name="voxel_toolkit_options[duplicate_post][post_types][]"]').on('change', function() {
                                // Submit form to refresh redirect pages section
                                var $form = $(this).closest('form');
                                $form.find('input[name="action"]').val('update');
                                // Add a flag to indicate we're just updating the display
                                if (!$form.find('input[name="refresh_only"]').length) {
                                    $form.append('<input type="hidden" name="refresh_only" value="1">');
                                }
                            });
                        });
                        </script>
                    </div>
                    
                    <!-- User Roles Selection -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;">
                            <?php _e('Allowed User Roles', 'voxel-toolkit'); ?>
                        </h3>
                        <p style="margin: 0 0 15px 0; color: #666; font-size: 14px;">
                            <?php _e('Select which user roles can duplicate posts (check "All Roles" to allow everyone including subscribers):', 'voxel-toolkit'); ?>
                        </p>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                            <!-- All Roles Option -->
                            <label style="display: flex; align-items: center; padding: 8px; background: #e8f5e8; border: 2px solid #4caf50; border-radius: 4px; cursor: pointer; font-weight: bold;">
                                <input type="checkbox" 
                                       name="voxel_toolkit_options[duplicate_post][allowed_roles][]" 
                                       value="all_roles"
                                       <?php checked(in_array('all_roles', $allowed_roles)); ?>
                                       style="margin-right: 8px;"
                                       onchange="toggleAllRoles(this)">
                                <span><?php _e('All Roles (Including Subscribers)', 'voxel-toolkit'); ?></span>
                            </label>
                            
                            <?php foreach ($all_roles as $role_key => $role_data): ?>
                                <label style="display: flex; align-items: center; padding: 8px; background: #f8f9fa; border-radius: 4px; cursor: pointer;" class="role-checkbox">
                                    <input type="checkbox" 
                                           name="voxel_toolkit_options[duplicate_post][allowed_roles][]" 
                                           value="<?php echo esc_attr($role_key); ?>"
                                           <?php checked(in_array($role_key, $allowed_roles) || in_array('all_roles', $allowed_roles)); ?>
                                           <?php echo in_array('all_roles', $allowed_roles) ? 'disabled' : ''; ?>
                                           style="margin-right: 8px;">
                                    <span><?php echo esc_html(translate_user_role($role_data['name'])); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <script>
                        function toggleAllRoles(checkbox) {
                            const roleCheckboxes = document.querySelectorAll('.role-checkbox input[type="checkbox"]');
                            roleCheckboxes.forEach(cb => {
                                cb.disabled = checkbox.checked;
                                if (checkbox.checked) {
                                    cb.checked = true;
                                }
                            });
                        }
                        
                        // Initialize on page load
                        document.addEventListener('DOMContentLoaded', function() {
                            const allRolesCheckbox = document.querySelector('input[value="all_roles"]');
                            if (allRolesCheckbox && allRolesCheckbox.checked) {
                                toggleAllRoles(allRolesCheckbox);
                            }
                        });
                        </script>
                    </div>
                    
                    <!-- Features -->
                    <div style="margin-bottom: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; font-size: 14px;">
                        <strong style="color: #856404;"><?php _e('Features:', 'voxel-toolkit'); ?></strong>
                        <ul style="margin: 10px 0 0 20px; color: #856404;">
                            <li><?php _e('"Duplicate" link in post/page list quick actions', 'voxel-toolkit'); ?></li>
                            <li><?php _e('"Duplicate This" button in the post edit sidebar', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Copies all post content, meta data, and taxonomies', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Creates draft copy with "(Copy)" suffix in title', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Available to all logged-in users', 'voxel-toolkit'); ?></li>
                        </ul>
                    </div>
                    
                    <!-- What Gets Duplicated -->
                    <div style="background: #e7f6ff; border: 1px solid #b3d9ff; border-radius: 4px; padding: 15px; font-size: 14px; margin-bottom: 20px;">
                        <strong style="color: #0066cc;"><?php _e('What gets duplicated:', 'voxel-toolkit'); ?></strong>
                        <p style="margin: 10px 0 0 0; color: #0066cc;">
                            <?php _e('Content, excerpt, custom fields, featured image, categories, tags, and all other taxonomies. The new post is created as a draft by the current user.', 'voxel-toolkit'); ?>
                        </p>
                    </div>
                    
                    <!-- Usage Instructions -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;">
                            <?php _e('Usage Instructions', 'voxel-toolkit'); ?>
                        </h3>
                        
                        <!-- Backend Usage -->
                        <div style="background: #fff; border: 1px solid #e1e5e9; border-radius: 6px; padding: 15px; margin-bottom: 15px;">
                            <h4 style="margin: 0 0 10px 0; color: #1e1e1e; font-size: 14px;">
                                <span class="dashicons dashicons-admin-settings" style="margin-right: 5px;"></span>
                                <?php _e('Backend (Admin)', 'voxel-toolkit'); ?>
                            </h4>
                            <ul style="margin: 0; padding-left: 20px; color: #666;">
                                <li><?php _e('In post/page list: Hover over a post → click "Duplicate" in quick actions', 'voxel-toolkit'); ?></li>
                                <li><?php _e('In post editor: Look for "Duplicate This" button in the publish box sidebar', 'voxel-toolkit'); ?></li>
                                <li><?php _e('Both methods create a draft copy and show success message', 'voxel-toolkit'); ?></li>
                            </ul>
                        </div>
                        
                        <!-- Frontend Usage -->
                        <div style="background: #fff; border: 1px solid #e1e5e9; border-radius: 6px; padding: 15px;">
                            <h4 style="margin: 0 0 10px 0; color: #1e1e1e; font-size: 14px;">
                                <span class="dashicons dashicons-admin-appearance" style="margin-right: 5px;"></span>
                                <?php _e('Frontend (Elementor Widget)', 'voxel-toolkit'); ?>
                            </h4>
                            <ol style="margin: 0; padding-left: 20px; color: #666;">
                                <li><?php _e('Edit page/template with Elementor', 'voxel-toolkit'); ?></li>
                                <li><?php _e('Search for "Duplicate Post" widget in Voxel Toolkit category', 'voxel-toolkit'); ?></li>
                                <li><?php _e('Drag widget to desired location', 'voxel-toolkit'); ?></li>
                                <li><?php _e('Customize button text and styling in widget settings', 'voxel-toolkit'); ?></li>
                                <li><?php _e('Choose redirect behavior: "Create/Edit Page" or "Current Page"', 'voxel-toolkit'); ?></li>
                                <li><?php _e('Save and view page - button will duplicate current post when clicked', 'voxel-toolkit'); ?></li>
                            </ol>
                            <div style="margin-top: 10px; padding: 8px; background: #f8f9fa; border-radius: 4px; font-size: 13px; color: #666;">
                                <strong><?php _e('Note:', 'voxel-toolkit'); ?></strong> <?php _e('Frontend widget works for all logged-in users. Button redirects to the create/edit page for the duplicated post.', 'voxel-toolkit'); ?>
                            </div>
                        </div>
                    </div>
        <?php
    }

    /**
     * Render Media Paste settings
     */
    public function render_media_paste_settings($settings) {
        $allowed_roles = isset($settings['allowed_roles']) ? $settings['allowed_roles'] : array('administrator', 'editor');
        $max_file_size = isset($settings['max_file_size']) ? $settings['max_file_size'] : '';
        $allowed_types = isset($settings['allowed_types']) ? $settings['allowed_types'] : array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        $available_roles = wp_roles()->roles;
        $image_types = array('image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/gif' => 'GIF', 'image/webp' => 'WebP');
        ?>
        <div class="vt-info-box">
            <?php _e('Paste images directly from clipboard (Ctrl/Cmd+V) in WordPress Media Library, Elementor, and all media frames.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('User Permissions', 'voxel-toolkit'); ?></h4>
            <p class="vt-field-description" style="margin-bottom: 12px;"><?php _e('Select which user roles can paste images from clipboard.', 'voxel-toolkit'); ?></p>
            <div class="vt-checkbox-grid">
                <label style="background: #ecfdf5; border-color: #059669;">
                    <input type="checkbox"
                           name="voxel_toolkit_options[media_paste][allowed_roles][]"
                           value="all_roles"
                           <?php checked(in_array('all_roles', $allowed_roles)); ?>
                           onchange="toggleMediaPasteRoles(this)" />
                    <?php _e('All Roles', 'voxel-toolkit'); ?>
                </label>
                <?php foreach ($available_roles as $role_key => $role_data): ?>
                    <label class="media-paste-role">
                        <input type="checkbox"
                               name="voxel_toolkit_options[media_paste][allowed_roles][]"
                               value="<?php echo esc_attr($role_key); ?>"
                               <?php checked(in_array($role_key, $allowed_roles) || in_array('all_roles', $allowed_roles)); ?>
                               <?php echo in_array('all_roles', $allowed_roles) ? 'disabled' : ''; ?> />
                        <?php echo esc_html(translate_user_role($role_data['name'])); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('File Settings', 'voxel-toolkit'); ?></h4>
            <div class="vt-checkbox-inline" style="gap: 32px; align-items: flex-start;">
                <div class="vt-field-group">
                    <label class="vt-field-label"><?php _e('Max File Size (MB)', 'voxel-toolkit'); ?></label>
                    <input type="number"
                           name="voxel_toolkit_options[media_paste][max_file_size]"
                           value="<?php echo esc_attr($max_file_size); ?>"
                           placeholder="<?php echo esc_attr(wp_max_upload_size() / (1024 * 1024)); ?>"
                           min="1"
                           class="vt-text-input"
                           style="max-width: 100px;" />
                    <p class="vt-field-description"><?php printf(__('Default: %s MB', 'voxel-toolkit'), number_format(wp_max_upload_size() / (1024 * 1024), 1)); ?></p>
                </div>
                <div class="vt-field-group">
                    <label class="vt-field-label"><?php _e('Allowed Image Types', 'voxel-toolkit'); ?></label>
                    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <?php foreach ($image_types as $type => $label): ?>
                            <label style="display: flex; align-items: center; gap: 6px;">
                                <input type="checkbox"
                                       name="voxel_toolkit_options[media_paste][allowed_types][]"
                                       value="<?php echo esc_attr($type); ?>"
                                       <?php checked(in_array($type, $allowed_types)); ?> />
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Features', 'voxel-toolkit'); ?></h4>
            <ul class="vt-feature-list">
                <li><?php _e('Paste images directly from clipboard (Ctrl/Cmd+V)', 'voxel-toolkit'); ?></li>
                <li><?php _e('Works in WordPress media library and Elementor', 'voxel-toolkit'); ?></li>
                <li><?php _e('Automatic file naming with timestamps', 'voxel-toolkit'); ?></li>
                <li><?php _e('Visual feedback during upload', 'voxel-toolkit'); ?></li>
            </ul>
        </div>

        <div class="vt-tip-box">
            <strong><?php _e('Browser Support:', 'voxel-toolkit'); ?></strong>
            <?php _e('Chrome, Firefox, Safari, and Edge. Works with screenshots and copied images.', 'voxel-toolkit'); ?>
        </div>

        <script>
        function toggleMediaPasteRoles(checkbox) {
            document.querySelectorAll('.media-paste-role input').forEach(cb => {
                cb.disabled = checkbox.checked;
                if (checkbox.checked) cb.checked = true;
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            const allRoles = document.querySelector('input[value="all_roles"]');
            if (allRoles && allRoles.checked) toggleMediaPasteRoles(allRoles);
        });
        </script>
        <?php
    }
    
    /**
     * Render Admin Taxonomy Search settings
     */
    public function render_admin_taxonomy_search_settings($settings) {
        $taxonomies = get_taxonomies(array('public' => true), 'objects');
        $selected_taxonomies = isset($settings['taxonomies']) ? $settings['taxonomies'] : array();
        ?>
        <div class="vt-info-box">
            <?php _e('Adds a search box to taxonomy metaboxes on post edit pages for quick term filtering.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Enable Search for Taxonomies', 'voxel-toolkit'); ?></h4>
            <?php if (!empty($taxonomies)): ?>
                <div class="vt-checkbox-grid-3col">
                    <?php foreach ($taxonomies as $taxonomy_key => $taxonomy): ?>
                        <label>
                            <input type="checkbox"
                                   name="voxel_toolkit_options[admin_taxonomy_search][taxonomies][]"
                                   value="<?php echo esc_attr($taxonomy_key); ?>"
                                   <?php checked(in_array($taxonomy_key, $selected_taxonomies)); ?> />
                            <?php echo esc_html($taxonomy->label); ?>
                            <span style="color: #64748b; font-size: 12px;">(<?php echo esc_html($taxonomy_key); ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="vt-field-description"><?php _e('No public taxonomies found.', 'voxel-toolkit'); ?></p>
            <?php endif; ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Features', 'voxel-toolkit'); ?></h4>
            <ul class="vt-feature-list">
                <li><?php _e('Real-time search filtering as you type', 'voxel-toolkit'); ?></li>
                <li><?php _e('Works with categories and tags', 'voxel-toolkit'); ?></li>
                <li><?php _e('Shows parent terms when child terms match', 'voxel-toolkit'); ?></li>
                <li><?php _e('Clear button to quickly reset search', 'voxel-toolkit'); ?></li>
            </ul>
        </div>
        <?php
    }
    
    
    /**
     * Render Reading Time widget settings
     */
    public function render_reading_time_widget_settings($settings) {
        // No additional settings needed for reading time widget
        // All configuration is done through the Elementor widget
    }
    
    
    /**
     * Render Pending Posts Badge settings
     */
    public function render_pending_posts_badge_settings($settings) {
        $post_types = Voxel_Toolkit_Settings::instance()->get_available_post_types();
        $selected_types = isset($settings['post_types']) ? $settings['post_types'] : array();
        $background_color = isset($settings['background_color']) ? $settings['background_color'] : '#d63638';
        $text_color = isset($settings['text_color']) ? $settings['text_color'] : '#ffffff';

        ?>
        <div class="vt-info-box">
            <?php _e('Display pending post count badges in the admin menu for selected post types.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Post Types', 'voxel-toolkit'); ?></h4>
            <div class="vt-checkbox-grid">
                <?php foreach ($post_types as $post_type => $label): ?>
                    <label>
                        <input type="checkbox"
                               name="voxel_toolkit_options[pending_posts_badge][post_types][]"
                               value="<?php echo esc_attr($post_type); ?>"
                               <?php checked(in_array($post_type, $selected_types)); ?> />
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Badge Colors', 'voxel-toolkit'); ?></h4>
            <div class="vt-checkbox-inline" style="gap: 32px;">
                <div class="vt-field-group">
                    <label class="vt-field-label"><?php _e('Background Color', 'voxel-toolkit'); ?></label>
                    <input type="color"
                           id="pending_posts_badge_background_color"
                           name="voxel_toolkit_options[pending_posts_badge][background_color]"
                           value="<?php echo esc_attr($background_color); ?>"
                           class="color-picker" />
                </div>
                <div class="vt-field-group">
                    <label class="vt-field-label"><?php _e('Text Color', 'voxel-toolkit'); ?></label>
                    <input type="color"
                           id="pending_posts_badge_text_color"
                           name="voxel_toolkit_options[pending_posts_badge][text_color]"
                           value="<?php echo esc_attr($text_color); ?>"
                           class="color-picker" />
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Pre-Approve Posts settings
     */
    public function render_pre_approve_posts_settings($settings) {
        $show_column = isset($settings['show_column']) ? $settings['show_column'] : true;
        $approve_verified = isset($settings['approve_verified']) ? $settings['approve_verified'] : false;
        $approved_roles = isset($settings['approved_roles']) ? $settings['approved_roles'] : array();

        ?>
        <div class="vt-info-box">
            <?php _e('Configure which users should have their posts automatically approved without manual review.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Auto-Approve Options', 'voxel-toolkit'); ?></h4>
            <div class="vt-checkbox-list">
                <label class="vt-checkbox-item">
                    <input type="checkbox"
                           name="voxel_toolkit_options[pre_approve_posts][approve_verified]"
                           value="1"
                           <?php checked($approve_verified); ?> />
                    <div class="vt-checkbox-item-content">
                        <span class="vt-checkbox-item-label"><?php _e('Auto-Approve Verified Users', 'voxel-toolkit'); ?></span>
                        <p class="vt-checkbox-item-description"><?php _e('Users with verified Voxel profiles will have posts automatically approved.', 'voxel-toolkit'); ?></p>
                    </div>
                </label>
                <label class="vt-checkbox-item">
                    <input type="checkbox"
                           name="voxel_toolkit_options[pre_approve_posts][show_column]"
                           value="1"
                           <?php checked($show_column); ?> />
                    <div class="vt-checkbox-item-content">
                        <span class="vt-checkbox-item-label"><?php _e('Show Pre-Approved Column', 'voxel-toolkit'); ?></span>
                        <p class="vt-checkbox-item-description"><?php _e('Display a "Pre-Approved?" column in the users list.', 'voxel-toolkit'); ?></p>
                    </div>
                </label>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Auto-Approve Roles', 'voxel-toolkit'); ?></h4>
            <p class="vt-field-description" style="margin-bottom: 12px;"><?php _e('Select user roles that should have posts automatically approved.', 'voxel-toolkit'); ?></p>
            <div class="vt-checkbox-grid">
                <?php
                $all_roles = wp_roles()->roles;
                foreach($all_roles as $role_key => $role_info): ?>
                    <label>
                        <input type="checkbox"
                               name="voxel_toolkit_options[pre_approve_posts][approved_roles][]"
                               value="<?php echo esc_attr($role_key); ?>"
                               <?php checked(in_array($role_key, $approved_roles)); ?> />
                        <?php echo esc_html($role_info['name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings for Disable Auto Updates function
     * 
     * @param array $settings Current settings
     */
    public function render_disable_auto_updates_settings($settings) {
        $disable_plugin_updates = isset($settings['disable_plugin_updates']) ? $settings['disable_plugin_updates'] : false;
        $disable_theme_updates = isset($settings['disable_theme_updates']) ? $settings['disable_theme_updates'] : false;
        $disable_core_updates = isset($settings['disable_core_updates']) ? $settings['disable_core_updates'] : false;

        ?>
        <div class="vt-info-box">
            <?php _e('Disables automatic updates for plugins, themes, and WordPress core. Choose which types of updates to disable.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-warning-box">
            <strong><?php _e('Security Notice:', 'voxel-toolkit'); ?></strong>
            <?php _e('Disabling automatic updates means you\'ll need to manually update plugins, themes, and WordPress core. Make sure to regularly check for and install updates to maintain security.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Disable Updates For', 'voxel-toolkit'); ?></h4>
            <div class="vt-checkbox-list">
                <label class="vt-checkbox-item">
                    <input type="checkbox"
                           name="voxel_toolkit_options[disable_auto_updates][disable_plugin_updates]"
                           value="1"
                           <?php checked($disable_plugin_updates); ?> />
                    <div class="vt-checkbox-item-content">
                        <span class="vt-checkbox-item-label"><?php _e('Plugin Updates', 'voxel-toolkit'); ?></span>
                        <p class="vt-checkbox-item-description"><?php _e('Prevent plugins from updating automatically', 'voxel-toolkit'); ?></p>
                    </div>
                </label>
                <label class="vt-checkbox-item">
                    <input type="checkbox"
                           name="voxel_toolkit_options[disable_auto_updates][disable_theme_updates]"
                           value="1"
                           <?php checked($disable_theme_updates); ?> />
                    <div class="vt-checkbox-item-content">
                        <span class="vt-checkbox-item-label"><?php _e('Theme Updates', 'voxel-toolkit'); ?></span>
                        <p class="vt-checkbox-item-description"><?php _e('Prevent themes from updating automatically', 'voxel-toolkit'); ?></p>
                    </div>
                </label>
                <label class="vt-checkbox-item">
                    <input type="checkbox"
                           name="voxel_toolkit_options[disable_auto_updates][disable_core_updates]"
                           value="1"
                           <?php checked($disable_core_updates); ?> />
                    <div class="vt-checkbox-item-content">
                        <span class="vt-checkbox-item-label"><?php _e('WordPress Core Updates', 'voxel-toolkit'); ?></span>
                        <p class="vt-checkbox-item-description"><?php _e('Prevent WordPress core from updating automatically (includes major and minor updates)', 'voxel-toolkit'); ?></p>
                    </div>
                </label>
            </div>
        </div>

        <div class="vt-tip-box">
            <strong><?php _e('Manual Updates:', 'voxel-toolkit'); ?></strong>
            <?php _e('You can still update manually from the WordPress admin dashboard. Go to Dashboard → Updates to see and install available updates when you\'re ready.', 'voxel-toolkit'); ?>
        </div>
        <?php
    }
    
    /**
     * Render settings for Redirect Posts function
     * 
     * @param array $settings Current settings
     */
    public function render_redirect_posts_settings($settings) {
        $redirect_urls = isset($settings['redirect_urls']) ? $settings['redirect_urls'] : array();
        $redirect_statuses = isset($settings['redirect_statuses']) ? $settings['redirect_statuses'] : array();
        
        // Get all public post types
        $post_types = get_post_types(array(
            'public' => true,
            'show_ui' => true
        ), 'objects');
        
        // Get all post statuses
        $post_statuses = get_post_stati(array(), 'objects');
        ?>
        <div class="vt-info-box">
            <?php _e('Automatically redirects visitors from posts with specific statuses to specified URLs. Also detects expiration using Voxel expiration dates and common meta fields.', 'voxel-toolkit'); ?>
        </div>

        <!-- Post Status Selection -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;">
                            <?php _e('Post Statuses to Redirect', 'voxel-toolkit'); ?>
                        </h3>
                        <p style="margin: 0 0 15px 0; color: #666; font-size: 14px;">
                            <?php _e('Select which post statuses should trigger redirects:', 'voxel-toolkit'); ?>
                        </p>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px;">
                            <?php foreach ($post_statuses as $status_key => $status_obj): ?>
                                <?php if (in_array($status_key, array('auto-draft', 'inherit'))) continue; ?>
                                <label style="display: flex; align-items: center; padding: 8px; background: #f8f9fa; border-radius: 4px; cursor: pointer;">
                                    <input type="checkbox" 
                                           name="voxel_toolkit_options[redirect_posts][redirect_statuses][]" 
                                           value="<?php echo esc_attr($status_key); ?>"
                                           <?php checked(in_array($status_key, $redirect_statuses)); ?>
                                           style="margin-right: 8px;">
                                    <span><?php echo esc_html($status_obj->label); ?></span>
                                </label>
                            <?php endforeach; ?>
                            
                            <!-- Add Expired Status -->
                            <label style="display: flex; align-items: center; padding: 8px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; cursor: pointer;">
                                <input type="checkbox" 
                                       name="voxel_toolkit_options[redirect_posts][redirect_statuses][]" 
                                       value="expired"
                                       <?php checked(in_array('expired', $redirect_statuses)); ?>
                                       style="margin-right: 8px;">
                                <span><?php _e('Expired', 'voxel-toolkit'); ?></span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Detection Methods -->
                    <div style="background: #e7f6ff; border: 1px solid #b3d9ff; border-radius: 4px; padding: 15px; font-size: 14px; margin-bottom: 20px;">
                        <strong style="color: #0066cc;"><?php _e('How It Works:', 'voxel-toolkit'); ?></strong>
                        <p style="margin: 8px 0 0 0; color: #0066cc;">
                            <?php _e('Redirects posts that match any of the selected statuses above. Only affects single post pages, not archive pages.', 'voxel-toolkit'); ?>
                        </p>
                    </div>
                    
                    <!-- Post Type Redirects -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;">
                            <?php _e('Redirect URLs by Post Type', 'voxel-toolkit'); ?>
                        </h3>
                        <p style="margin: 0 0 15px 0; color: #666; font-size: 14px;">
                            <?php _e('Set where to redirect expired posts for each post type. Leave blank to disable redirects for that post type.', 'voxel-toolkit'); ?>
                        </p>
                        
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <?php foreach ($post_types as $post_type): ?>
                                <?php 
                                // Skip certain post types
                                if (in_array($post_type->name, array('attachment', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part'))) {
                                    continue;
                                }
                                
                                $current_url = isset($redirect_urls[$post_type->name]) ? $redirect_urls[$post_type->name] : '';
                                ?>
                                <div style="padding: 15px; background: #f8f9fa; border-radius: 4px; border: 1px solid #e1e5e9;">
                                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                        <span class="dashicons dashicons-<?php echo $this->get_post_type_icon($post_type->name); ?>" style="margin-right: 8px; color: #666;"></span>
                                        <strong style="color: #1e1e1e; font-size: 14px;">
                                            <?php echo esc_html($post_type->label); ?>
                                        </strong>
                                        <span style="color: #666; font-size: 12px; margin-left: 8px;">
                                            (<?php echo esc_html($post_type->name); ?>)
                                        </span>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="url" 
                                               name="voxel_toolkit_options[redirect_posts][redirect_urls][<?php echo esc_attr($post_type->name); ?>]" 
                                               value="<?php echo esc_url($current_url); ?>"
                                               placeholder="https://example.com/expired-<?php echo esc_attr($post_type->name); ?>"
                                               style="flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 3px; font-size: 13px;">
                                        <?php if (!empty($current_url)): ?>
                                            <a href="<?php echo esc_url($current_url); ?>" target="_blank" style="color: #0073aa; text-decoration: none;">
                                                <span class="dashicons dashicons-external" title="Test redirect URL"></span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Important Notes -->
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; font-size: 14px; margin-bottom: 20px;">
                        <strong style="color: #856404;"><?php _e('⚠️ Important Notes:', 'voxel-toolkit'); ?></strong>
                        <ul style="margin: 8px 0 0 20px; color: #856404;">
                            <li><?php _e('Redirects use 301 (permanent) status codes', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Only affects single post pages (not archives)', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Test your redirect URLs before enabling', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Leave URL empty to disable redirects for that post type', 'voxel-toolkit'); ?></li>
                        </ul>
                    </div>
        <?php
    }

    /**
     * Render settings for Auto Promotion function
     * 
     * @param array $settings Current settings
     */
    public function render_auto_promotion_settings($settings) {
        $post_types = isset($settings['post_types']) ? $settings['post_types'] : array();
        
        // Get all public post types
        $available_post_types = get_post_types(array(
            'public' => true,
            'show_ui' => true
        ), 'objects');
        ?>
        <div class="vt-info-box">
            <?php _e('When a post is published, it automatically gets boosted with a higher priority ranking for the duration you specify. After that time expires, it returns to normal ranking.', 'voxel-toolkit'); ?>
        </div>
                    
                    <!-- Post Type Selection -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;">
                            <?php _e('Enabled Post Types', 'voxel-toolkit'); ?>
                        </h3>
                        <p style="margin: 0 0 15px 0; color: #666; font-size: 14px;">
                            <?php _e('Select which post types should automatically get promoted when published:', 'voxel-toolkit'); ?>
                        </p>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px;">
                            <?php foreach ($available_post_types as $post_type): ?>
                                <?php
                                // Skip certain post types
                                if (in_array($post_type->name, array('attachment', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part'))) {
                                    continue;
                                }
                                ?>
                                <label style="display: flex; align-items: center; padding: 8px; background: #f8f9fa; border-radius: 4px; cursor: pointer;">
                                    <input type="checkbox" 
                                           name="voxel_toolkit_options[auto_promotion][post_types][]" 
                                           value="<?php echo esc_attr($post_type->name); ?>"
                                           <?php checked(in_array($post_type->name, $post_types)); ?>
                                           style="margin-right: 8px;"
                                           class="auto-promotion-post-type"
                                           data-post-type="<?php echo esc_attr($post_type->name); ?>">
                                    <span><?php echo esc_html($post_type->label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Individual Post Type Settings -->
                    <div id="auto-promotion-post-type-settings">
                        <?php foreach ($available_post_types as $post_type): ?>
                            <?php
                            // Skip certain post types
                            if (in_array($post_type->name, array('attachment', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part'))) {
                                continue;
                            }
                            
                            $is_enabled = in_array($post_type->name, $post_types);
                            $settings_key = 'settings_' . $post_type->name;
                            $post_type_settings = isset($settings[$settings_key]) ? $settings[$settings_key] : array();
                            $priority = isset($post_type_settings['priority']) ? $post_type_settings['priority'] : 10;
                            $duration = isset($post_type_settings['duration']) ? $post_type_settings['duration'] : 24;
                            $duration_unit = isset($post_type_settings['duration_unit']) ? $post_type_settings['duration_unit'] : 'hours';
                            ?>
                            <div class="post-type-settings" data-post-type="<?php echo esc_attr($post_type->name); ?>" style="<?php echo $is_enabled ? '' : 'display: none;'; ?> margin-bottom: 25px; padding: 15px; background: #f8faff; border: 1px solid #d4e5ff; border-radius: 6px;">
                                <h4 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 15px; display: flex; align-items: center;">
                                    <span class="dashicons dashicons-<?php echo $this->get_post_type_icon($post_type->name); ?>"></span>
                                    &nbsp;<?php echo esc_html($post_type->label); ?> <?php _e('Settings', 'voxel-toolkit'); ?>
                                </h4>
                                
                                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
                                    <!-- Priority Level -->
                                    <div>
                                        <label style="display: block; font-weight: 500; margin-bottom: 5px;">
                                            <?php _e('Priority Level', 'voxel-toolkit'); ?>
                                        </label>
                                        <input type="number" 
                                               name="voxel_toolkit_options[auto_promotion][<?php echo esc_attr($settings_key); ?>][priority]"
                                               value="<?php echo esc_attr($priority); ?>"
                                               min="1"
                                               max="999"
                                               step="1"
                                               style="width: 100px; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        <p style="margin: 5px 0 0 0; color: #666; font-size: 12px;">
                                            <?php _e('Higher numbers = higher priority', 'voxel-toolkit'); ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Duration -->
                                    <div>
                                        <label style="display: block; font-weight: 500; margin-bottom: 5px;">
                                            <?php _e('Promotion Duration', 'voxel-toolkit'); ?>
                                        </label>
                                        <div style="display: flex; gap: 10px; align-items: center;">
                                            <input type="number" 
                                                   name="voxel_toolkit_options[auto_promotion][<?php echo esc_attr($settings_key); ?>][duration]"
                                                   value="<?php echo esc_attr($duration); ?>"
                                                   min="1"
                                                   max="999"
                                                   step="1"
                                                   style="width: 80px; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px;">
                                            <select name="voxel_toolkit_options[auto_promotion][<?php echo esc_attr($settings_key); ?>][duration_unit]"
                                                    style="padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; min-width: 80px;">
                                                <option value="hours" <?php selected($duration_unit, 'hours'); ?>><?php _e('Hours', 'voxel-toolkit'); ?></option>
                                                <option value="days" <?php selected($duration_unit, 'days'); ?>><?php _e('Days', 'voxel-toolkit'); ?></option>
                                                <option value="weeks" <?php selected($duration_unit, 'weeks'); ?>><?php _e('Weeks', 'voxel-toolkit'); ?></option>
                                            </select>
                                        </div>
                                        <p style="margin: 5px 0 0 0; color: #666; font-size: 12px;">
                                            <?php _e('How long to keep the promotion active', 'voxel-toolkit'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Active Promotions Display -->
                    <?php
                    $active_promotions = array();
                    if (class_exists('Voxel_Toolkit_Auto_Promotion')) {
                        $instance = Voxel_Toolkit_Auto_Promotion::instance();
                        $active_promotions = $instance->get_active_promotions();
                    }
                    ?>
                    <?php if (!empty($active_promotions)): ?>
                        <div style="margin-top: 25px; padding: 15px; background: #e8f5e8; border: 1px solid #c8e6c9; border-radius: 6px;">
                            <h4 style="margin: 0 0 10px 0; color: #2e7d32;">
                                <span class="dashicons dashicons-clock"></span>
                                <?php _e('Currently Active Promotions', 'voxel-toolkit'); ?>
                            </h4>
                            <div style="display: grid; gap: 8px;">
                                <?php foreach ($active_promotions as $promotion): ?>
                                    <?php 
                                    $remaining_hours = max(0, floor($promotion['remaining_time'] / 3600));
                                    $remaining_minutes = max(0, floor(($promotion['remaining_time'] % 3600) / 60));
                                    ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background: white; border-radius: 4px; font-size: 14px;">
                                        <div>
                                            <strong><?php echo esc_html($promotion['post_title']); ?></strong>
                                            <span style="color: #666; margin-left: 10px;">(<?php echo esc_html($promotion['post_type']); ?>)</span>
                                        </div>
                                        <div style="color: #2e7d32; font-weight: 500;">
                                            <?php if ($promotion['remaining_time'] > 0): ?>
                                                <?php printf(__('Expires in %dh %dm', 'voxel-toolkit'), $remaining_hours, $remaining_minutes); ?>
                                            <?php else: ?>
                                                <?php _e('Expired (will be processed soon)', 'voxel-toolkit'); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- JavaScript for dynamic settings -->
                    <script>
                    jQuery(document).ready(function($) {
                        // Toggle post type settings when checkboxes change
                        $('.auto-promotion-post-type').change(function() {
                            const postType = $(this).data('post-type');
                            const isChecked = $(this).is(':checked');
                            const settingsDiv = $(`.post-type-settings[data-post-type="${postType}"]`);
                            
                            if (isChecked) {
                                settingsDiv.slideDown();
                            } else {
                                settingsDiv.slideUp();
                            }
                        });
                    });
                    </script>
        <?php
    }

    /**
     * Render Custom Submission Messages settings
     */
    public function render_custom_submission_messages_settings($settings) {
        $post_type_settings = isset($settings['post_type_settings']) ? $settings['post_type_settings'] : array();
        
        // Check if pre-approve posts function is enabled
        $settings_instance = Voxel_Toolkit_Settings::instance();
        $pre_approve_enabled = $settings_instance->is_function_enabled('pre_approve_posts');
        
        // Get available post types
        $post_types = get_post_types(array('public' => true), 'objects');
        ?>
        <div class="vt-info-box">
            <?php _e('Customize confirmation messages shown to users after submitting different post types. You can set different messages for pending review, published posts, and pre-approved users.', 'voxel-toolkit'); ?>
        </div>

        <?php foreach ($post_types as $post_type): ?>
                        <?php if (in_array($post_type->name, array('attachment', 'page'))) continue; ?>
                        <?php
                        $enabled = isset($post_type_settings[$post_type->name]['enabled']) ? $post_type_settings[$post_type->name]['enabled'] : false;
                        $messages = isset($post_type_settings[$post_type->name]['messages']) ? $post_type_settings[$post_type->name]['messages'] : array();
                        ?>
                        
                        <!-- Post Type Section -->
                        <div style="background: #e7f6ff; border: 1px solid #b3d9ff; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                            <!-- Post Type Header -->
                            <div style="margin-bottom: 20px;">
                                <label style="display: flex; align-items: center; cursor: pointer; margin-bottom: 10px;">
                                    <input type="checkbox" 
                                           name="voxel_toolkit_options[custom_submission_messages][post_type_settings][<?php echo esc_attr($post_type->name); ?>][enabled]" 
                                           id="custom_msg_<?php echo esc_attr($post_type->name); ?>_enabled"
                                           value="1" 
                                           <?php checked($enabled); ?>
                                           onchange="toggleCustomMessageSection('<?php echo esc_js($post_type->name); ?>')"
                                           style="margin-right: 12px; transform: scale(1.2);">
                                    <div>
                                        <strong style="display: block; color: #1e1e1e; font-size: 16px;">
                                            <?php echo esc_html($post_type->labels->name); ?>
                                        </strong>
                                        <span style="color: #666; font-size: 13px;">
                                            <?php printf(__('Customize submission messages for %s', 'voxel-toolkit'), $post_type->labels->name); ?>
                                        </span>
                                    </div>
                                </label>
                            </div>
                            
                            <!-- Message Type Settings -->
                            <div id="custom_msg_<?php echo esc_attr($post_type->name); ?>_settings" style="<?php echo $enabled ? '' : 'display: none;'; ?>">
                                
                                <!-- Pending Review Message -->
                                <div style="margin-bottom: 20px;">
                                    <h4 style="margin: 0 0 10px 0; color: #1e1e1e; font-size: 14px; font-weight: 600;">
                                        <?php _e('Pending Review Message', 'voxel-toolkit'); ?>
                                    </h4>
                                    <textarea name="voxel_toolkit_options[custom_submission_messages][post_type_settings][<?php echo esc_attr($post_type->name); ?>][messages][pending_review]"
                                              rows="3" 
                                              style="width: 100%; border: 1px solid #ccd0d4; border-radius: 4px; padding: 8px;"
                                              placeholder="<?php printf(__('Message shown when %s are submitted for review (e.g., "Thanks for your submission! We\'ll review it within 24 hours.")', 'voxel-toolkit'), strtolower($post_type->labels->name)); ?>"><?php echo esc_textarea($messages['pending_review'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Published Message -->
                                <div style="margin-bottom: 20px;">
                                    <h4 style="margin: 0 0 10px 0; color: #1e1e1e; font-size: 14px; font-weight: 600;">
                                        <?php _e('Published Message', 'voxel-toolkit'); ?>
                                    </h4>
                                    <textarea name="voxel_toolkit_options[custom_submission_messages][post_type_settings][<?php echo esc_attr($post_type->name); ?>][messages][published]"
                                              rows="3" 
                                              style="width: 100%; border: 1px solid #ccd0d4; border-radius: 4px; padding: 8px;"
                                              placeholder="<?php printf(__('Message shown when %s are published immediately (e.g., "Congratulations! Your %s is now live.")', 'voxel-toolkit'), strtolower($post_type->labels->name), strtolower($post_type->labels->singular_name)); ?>"><?php echo esc_textarea($messages['published'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Pre-Approved Message -->
                                <div style="margin-bottom: 10px; <?php echo !$pre_approve_enabled ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                                    <h4 style="margin: 0 0 10px 0; color: #1e1e1e; font-size: 14px; font-weight: 600;">
                                        <?php _e('Pre-Approved Message', 'voxel-toolkit'); ?>
                                        <?php if (!$pre_approve_enabled): ?>
                                            <span style="font-size: 12px; color: #d63638; font-weight: normal;">
                                                (<?php _e('Pre-Approve Posts function required', 'voxel-toolkit'); ?>)
                                            </span>
                                        <?php endif; ?>
                                    </h4>
                                    <textarea name="voxel_toolkit_options[custom_submission_messages][post_type_settings][<?php echo esc_attr($post_type->name); ?>][messages][pre_approved]"
                                              rows="3" 
                                              style="width: 100%; border: 1px solid #ccd0d4; border-radius: 4px; padding: 8px;"
                                              placeholder="<?php printf(__('Message shown to pre-approved users (e.g., "Thanks for your %s! It\'s been published automatically.")', 'voxel-toolkit'), strtolower($post_type->labels->singular_name)); ?>"
                                              <?php echo !$pre_approve_enabled ? 'disabled' : ''; ?>><?php echo esc_textarea($messages['pre_approved'] ?? ''); ?></textarea>
                                    <?php if (!$pre_approve_enabled): ?>
                                        <p style="margin: 8px 0 0 0; color: #d63638; font-size: 13px;">
                                            <?php _e('Enable the Pre-Approve Posts function to use this message type.', 'voxel-toolkit'); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                            </div>
                        </div>
                        
        <?php endforeach; ?>

        <script type="text/javascript">
        function toggleCustomMessageSection(postType) {
            var checkbox = document.getElementById('custom_msg_' + postType + '_enabled');
            var settings = document.getElementById('custom_msg_' + postType + '_settings');
            settings.style.display = checkbox.checked ? 'block' : 'none';
        }
        </script>
        <?php
    }

    /**
     * Render Fluent Forms Post Author settings
     */
    public function render_fluent_forms_post_author_settings($settings) {
        ?>
        <div class="voxel-toolkit-function-settings">
            <h4><?php _e('How to Use', 'voxel-toolkit'); ?></h4>
            <div class="voxel-instructions">
                <p><strong><?php _e('Instructions:', 'voxel-toolkit'); ?></strong></p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><?php _e('Add the "Voxel Post Author" field to your form', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Go to Settings and Integrations → Email Notifications', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Edit the notification you want to configure', 'voxel-toolkit'); ?></li>
                    <li><?php _e('In "Send To" select "A field value" and choose the Voxel Post Author field', 'voxel-toolkit'); ?></li>
                </ul>
            </div>
            
            <div class="voxel-tip" style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 10px; margin: 15px 0;">
                <span style="font-size: 18px;">💡</span>
                <strong><?php _e('Tip:', 'voxel-toolkit'); ?></strong>
                <?php _e('Add "hidden" to the container class to hide the element from the front end while keeping it functional for notifications.', 'voxel-toolkit'); ?>
            </div>
            
            <p><em><?php _e('This function is currently enabled and active. No additional configuration is required.', 'voxel-toolkit'); ?></em></p>
        </div>
        <?php
    }
    
    /**
     * Render Featured Posts settings
     */
    public function render_featured_posts_settings($settings) {
        // Debug what we're receiving
        
        $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array();
        $priority_values = isset($settings['priority_values']) ? $settings['priority_values'] : array();
        
        // Get all public post types
        $post_types = get_post_types(array('public' => true), 'objects');
        unset($post_types['attachment']); // Remove attachments
        ?>
        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Enable for Post Types', 'voxel-toolkit'); ?></h4>
            <div class="vt-checkbox-grid">
                <?php foreach ($post_types as $post_type_key => $post_type_obj): ?>
                    <label>
                        <input type="checkbox"
                               name="voxel_toolkit_options[featured_posts][post_types][]"
                               value="<?php echo esc_attr($post_type_key); ?>"
                               <?php checked(in_array($post_type_key, $enabled_post_types)); ?>
                               class="featured-post-type-toggle"
                               data-post-type="<?php echo esc_attr($post_type_key); ?>" />
                        <span class="dashicons <?php echo esc_attr($this->get_post_type_icon($post_type_key)); ?>"></span>
                        <?php echo esc_html($post_type_obj->labels->name); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="vt-field-description"><?php _e('Select which post types should have featured functionality enabled.', 'voxel-toolkit'); ?></p>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Priority Values', 'voxel-toolkit'); ?></h4>
            <div id="priority-values-container">
                <?php foreach ($post_types as $post_type_key => $post_type_obj): ?>
                    <?php
                    $is_enabled = in_array($post_type_key, $enabled_post_types);
                    $priority_value = isset($priority_values[$post_type_key]) ? $priority_values[$post_type_key] : 10;
                    ?>
                    <div class="priority-value-row priority-row-<?php echo esc_attr($post_type_key); ?>"
                         style="<?php echo $is_enabled ? '' : 'display: none;'; ?> margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <label style="min-width: 150px;">
                            <span class="dashicons <?php echo esc_attr($this->get_post_type_icon($post_type_key)); ?>"></span>
                            <?php echo esc_html($post_type_obj->labels->name); ?>:
                        </label>
                        <input type="number"
                               name="voxel_toolkit_options[featured_posts][priority_values][<?php echo esc_attr($post_type_key); ?>]"
                               value="<?php echo esc_attr($priority_value); ?>"
                               min="1" max="999" style="width: 80px;" />
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="vt-field-description"><?php _e('Set the Voxel Priority meta value for featured posts. Higher numbers = higher priority.', 'voxel-toolkit'); ?></p>
        </div>

        <div class="vt-info-box">
            <strong><?php _e('How to Use:', 'voxel-toolkit'); ?></strong>
            <ul style="margin: 10px 0 0 20px; padding: 0;">
                <li><?php _e('Enable featured functionality for desired post types above', 'voxel-toolkit'); ?></li>
                <li><?php _e('Go to the post list page and click the star icon to feature posts', 'voxel-toolkit'); ?></li>
                <li><?php _e('Use the "Featured" filter or bulk actions to manage featured posts', 'voxel-toolkit'); ?></li>
            </ul>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Show/hide priority value inputs based on enabled post types
            $('.featured-post-type-toggle').change(function() {
                var postType = $(this).data('post-type');
                var priorityRow = $('.priority-row-' + postType);
                
                if ($(this).is(':checked')) {
                    priorityRow.show();
                } else {
                    priorityRow.hide();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get appropriate dashicon for post type
     */
    private function get_post_type_icon($post_type) {
        $icons = array(
            'post' => 'admin-post',
            'page' => 'admin-page',
            'product' => 'products',
            'event' => 'calendar-alt',
            'job' => 'businessman',
            'place' => 'location',
            'listing' => 'list-view',
            'portfolio' => 'portfolio',
            'testimonial' => 'testimonial'
        );
        
        return isset($icons[$post_type]) ? $icons[$post_type] : 'admin-generic';
    }
    
    /**
     * Get function instance
     * 
     * @param string $function_key Function key
     * @return object|null Function instance or null
     */
    private function get_function_instance($function_key) {
        return isset($this->active_functions[$function_key]) ? $this->active_functions[$function_key] : null;
    }
    
    /**
     * Render Google Analytics settings
     */
    public function render_google_analytics_settings($settings) {
        $voxel_toolkit_options = get_option('voxel_toolkit_options', array());
        $ga_settings = isset($voxel_toolkit_options['google_analytics']) ? $voxel_toolkit_options['google_analytics'] : array();
        
        $ga4_measurement_id = isset($ga_settings['ga4_measurement_id']) ? $ga_settings['ga4_measurement_id'] : '';
        $ua_tracking_id = isset($ga_settings['ua_tracking_id']) ? $ga_settings['ua_tracking_id'] : '';
        $gtm_container_id = isset($ga_settings['gtm_container_id']) ? $ga_settings['gtm_container_id'] : '';
        $custom_head_tags = isset($ga_settings['custom_head_tags']) ? $ga_settings['custom_head_tags'] : '';
        $custom_body_tags = isset($ga_settings['custom_body_tags']) ? $ga_settings['custom_body_tags'] : '';
        $custom_footer_tags = isset($ga_settings['custom_footer_tags']) ? $ga_settings['custom_footer_tags'] : '';
        ?>
        
        <div class="vt-ga-warning">
            <strong><?php _e('Important:', 'voxel-toolkit'); ?></strong> 
            <?php _e('Only add tracking codes and scripts that you trust. Custom code will be executed on your website.', 'voxel-toolkit'); ?>
        </div>
        
        <!-- Google Analytics Section -->
        <div class="vt-ga-settings-section">
            <h3><?php _e('Google Analytics', 'voxel-toolkit'); ?></h3>
            
            <div class="vt-ga-input-group">
                <label for="ga4_measurement_id"><?php _e('Google Analytics 4 (GA4) Measurement ID', 'voxel-toolkit'); ?></label>
                <input type="text" 
                       id="ga4_measurement_id" 
                       name="voxel_toolkit_options[google_analytics][ga4_measurement_id]" 
                       value="<?php echo esc_attr($ga4_measurement_id); ?>"
                       placeholder="G-XXXXXXXXXX" 
                       class="regular-text" />
                <p class="vt-ga-help-text"><?php _e('Enter your GA4 Measurement ID (e.g., G-XXXXXXXXXX). Recommended for new websites.', 'voxel-toolkit'); ?></p>
            </div>
            
            <div class="vt-ga-input-group">
                <label for="ua_tracking_id"><?php _e('Universal Analytics Tracking ID (Legacy)', 'voxel-toolkit'); ?></label>
                <input type="text" 
                       id="ua_tracking_id" 
                       name="voxel_toolkit_options[google_analytics][ua_tracking_id]" 
                       value="<?php echo esc_attr($ua_tracking_id); ?>"
                       placeholder="UA-XXXXXXXX-X" 
                       class="regular-text" />
                <p class="vt-ga-help-text"><?php _e('Enter your Universal Analytics ID (e.g., UA-XXXXXXXX-X). Note: Universal Analytics stopped collecting data in July 2023.', 'voxel-toolkit'); ?></p>
            </div>
        </div>
        
        <!-- Google Tag Manager Section -->
        <div class="vt-ga-settings-section">
            <h3><?php _e('Google Tag Manager', 'voxel-toolkit'); ?></h3>
            
            <div class="vt-ga-input-group">
                <label for="gtm_container_id"><?php _e('Google Tag Manager Container ID', 'voxel-toolkit'); ?></label>
                <input type="text" 
                       id="gtm_container_id" 
                       name="voxel_toolkit_options[google_analytics][gtm_container_id]" 
                       value="<?php echo esc_attr($gtm_container_id); ?>"
                       placeholder="GTM-XXXXXXX" 
                       class="regular-text" />
                <p class="vt-ga-help-text"><?php _e('Enter your GTM Container ID (e.g., GTM-XXXXXXX). This will add both head and body GTM code.', 'voxel-toolkit'); ?></p>
            </div>
        </div>
        
        <!-- Custom Tags Section -->
        <div class="vt-ga-settings-section">
            <h3><?php _e('Custom Tags & Scripts', 'voxel-toolkit'); ?></h3>
            
            <div class="vt-ga-input-group">
                <label for="custom_head_tags"><?php _e('Custom Head Tags', 'voxel-toolkit'); ?></label>
                <textarea id="custom_head_tags" 
                          name="voxel_toolkit_options[google_analytics][custom_head_tags]" 
                          placeholder="<script>&#10;// Your custom head scripts&#10;</script>"><?php echo esc_textarea($custom_head_tags); ?></textarea>
                <p class="vt-ga-help-text"><?php _e('Add custom scripts/tags to the <head> section. Include <script> tags for JavaScript code.', 'voxel-toolkit'); ?></p>
            </div>
            
            <div class="vt-ga-input-group">
                <label for="custom_body_tags"><?php _e('Custom Body Tags (After <body>)', 'voxel-toolkit'); ?></label>
                <textarea id="custom_body_tags" 
                          name="voxel_toolkit_options[google_analytics][custom_body_tags]" 
                          placeholder="<script>&#10;// Your custom body scripts&#10;</script>"><?php echo esc_textarea($custom_body_tags); ?></textarea>
                <p class="vt-ga-help-text"><?php _e('Add custom scripts/tags immediately after the opening <body> tag.', 'voxel-toolkit'); ?></p>
            </div>
            
            <div class="vt-ga-input-group">
                <label for="custom_footer_tags"><?php _e('Custom Footer Tags (Before </body>)', 'voxel-toolkit'); ?></label>
                <textarea id="custom_footer_tags" 
                          name="voxel_toolkit_options[google_analytics][custom_footer_tags]" 
                          placeholder="<script>&#10;// Your custom footer scripts&#10;</script>"><?php echo esc_textarea($custom_footer_tags); ?></textarea>
                <p class="vt-ga-help-text"><?php _e('Add custom scripts/tags before the closing </body> tag.', 'voxel-toolkit'); ?></p>
            </div>
        </div>
        
        <!-- Preview Section -->
        <?php if (!empty($ga4_measurement_id) || !empty($ua_tracking_id) || !empty($gtm_container_id)): ?>
        <div class="vt-ga-settings-section">
            <h3><?php _e('Code Preview', 'voxel-toolkit'); ?></h3>
            
            <?php if (!empty($ga4_measurement_id)): ?>
            <div class="vt-ga-preview-title"><?php _e('Google Analytics 4 Code (Head):', 'voxel-toolkit'); ?></div>
            <div class="vt-ga-preview">&lt;script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_html($ga4_measurement_id); ?>"&gt;&lt;/script&gt;
&lt;script&gt;
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?php echo esc_html($ga4_measurement_id); ?>');
&lt;/script&gt;</div>
            <?php endif; ?>
            
            <?php if (!empty($gtm_container_id)): ?>
            <div class="vt-ga-preview-title"><?php _e('Google Tag Manager Code (Head):', 'voxel-toolkit'); ?></div>
            <div class="vt-ga-preview">&lt;script&gt;(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo esc_html($gtm_container_id); ?>');&lt;/script&gt;</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #2271b1;">
            <strong><?php _e('Usage Instructions:', 'voxel-toolkit'); ?></strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><?php _e('For Google Analytics 4: Use the GA4 Measurement ID (recommended)', 'voxel-toolkit'); ?></li>
                <li><?php _e('For Google Tag Manager: Use the GTM Container ID (most flexible)', 'voxel-toolkit'); ?></li>
                <li><?php _e('For other tracking: Use custom tag sections', 'voxel-toolkit'); ?></li>
                <li><?php _e('Test your setup using browser developer tools or Google Analytics Real-Time reports', 'voxel-toolkit'); ?></li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Render Submission Reminder settings
     */
    public function render_submission_reminder_settings($settings) {
        $voxel_toolkit_options = get_option('voxel_toolkit_options', array());
        $sr_settings = isset($voxel_toolkit_options['submission_reminder']) ? $voxel_toolkit_options['submission_reminder'] : array();
        
        $post_types = isset($sr_settings['post_types']) ? $sr_settings['post_types'] : array();
        $notifications = isset($sr_settings['notifications']) ? $sr_settings['notifications'] : array();
        
        // Get available post types - try to use existing instance or create new one
        if (isset($this->active_functions['submission_reminder'])) {
            $submission_reminder = $this->active_functions['submission_reminder'];
        } else {
            $submission_reminder = new Voxel_Toolkit_Submission_Reminder();
        }
        
        $available_post_types_list = $submission_reminder->get_available_post_types();
        
        // Convert to objects for consistency with existing code
        $available_post_types = array();
        foreach ($available_post_types_list as $post_type_name => $post_type_label) {
            $post_type_obj = get_post_type_object($post_type_name);
            if ($post_type_obj) {
                $available_post_types[] = $post_type_obj;
            } else {
                // Create a fake object if post type doesn't exist in WordPress but is in Voxel config
                $fake_obj = new stdClass();
                $fake_obj->name = $post_type_name;
                $fake_obj->label = $post_type_label;
                $available_post_types[] = $fake_obj;
            }
        }
        
        ?>
        
        <style>
        .vt-submission-reminder-admin {
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
        }
        .vt-sr-main-content {
            min-width: 0;
        }
        .vt-sr-sidebar {
            position: sticky;
            top: 32px;
            height: fit-content;
            max-height: calc(100vh - 60px);
            overflow-y: auto;
        }
        .vt-sr-sidebar-content {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }
        .vt-sr-sidebar h3 {
            margin-top: 0;
            color: #2271b1;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .vt-sr-placeholder-group {
            margin-bottom: 25px;
        }
        .vt-sr-placeholder-group h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 13px;
            font-weight: 600;
        }
        .vt-sr-placeholder-item {
            display: flex;
            align-items: center;
            padding: 8px 10px;
            margin-bottom: 2px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .vt-sr-placeholder-item:hover {
            background: #f0f6fc;
            border-color: #0969da;
        }
        .vt-sr-placeholder-item code {
            background: #f6f8fa;
            color: #0969da;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            margin-right: auto;
            border: 1px solid #d1d9e0;
        }
        .vt-sr-copy-icon {
            opacity: 0;
            transition: opacity 0.2s ease;
            font-size: 12px;
            margin-left: 8px;
        }
        .vt-sr-placeholder-item:hover .vt-sr-copy-icon {
            opacity: 1;
        }
        .vt-sr-placeholder-description {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 1000;
            margin-bottom: 5px;
        }
        .vt-sr-placeholder-description::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: #333;
        }
        .vt-sr-placeholder-item:hover .vt-sr-placeholder-description {
            opacity: 1;
        }
        .vt-sr-post-type-section {
            margin-bottom: 30px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .vt-sr-post-type-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .vt-sr-post-type-header input[type="checkbox"] {
            margin-right: 10px;
        }
        .vt-sr-post-type-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            color: #2271b1;
        }
        .vt-sr-notification-list {
            padding: 20px;
        }
        .vt-sr-notification-item {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin-bottom: 15px;
            padding: 15px;
        }
        .vt-sr-notification-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .vt-sr-notification-title {
            display: flex;
            align-items: center;
        }
        .vt-sr-notification-title input[type="checkbox"] {
            margin-right: 8px;
        }
        .vt-sr-notification-fields {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        .vt-sr-email-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .vt-sr-field {
            display: flex;
            flex-direction: column;
        }
        .vt-sr-field label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        .vt-sr-field input,
        .vt-sr-field select,
        .vt-sr-field textarea {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .vt-sr-field textarea {
            height: 80px;
            resize: vertical;
            font-size: 12px;
        }
        .vt-sr-add-notification {
            text-align: center;
            padding: 15px;
            border-top: 1px solid #ddd;
            background: #f8f9fa;
        }
        .vt-sr-add-notification button {
            background: #2271b1;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 3px;
            cursor: pointer;
        }
        .vt-sr-add-notification button:hover {
            background: #135e96;
        }
        .vt-sr-remove-notification {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .vt-sr-remove-notification:hover {
            background: #c82333;
        }
        .vt-sr-main-content {
            /* Main content area */
        }
        .vt-sr-sidebar {
            position: sticky;
            top: 32px;
            height: fit-content;
            max-height: calc(100vh - 50px);
            overflow-y: auto;
        }
        .vt-sr-placeholders {
            background: #e7f3ff;
            border: 1px solid #b3d7ff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .vt-sr-placeholders h4 {
            margin: 0 0 15px 0;
            color: #2271b1;
            font-size: 16px;
            border-bottom: 1px solid #b3d7ff;
            padding-bottom: 8px;
        }
        .vt-sr-placeholder-group {
            margin-bottom: 15px;
        }
        .vt-sr-placeholder-group h5 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 13px;
            font-weight: 600;
        }
        .vt-sr-placeholder-item {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            margin: 4px 0;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .vt-sr-placeholder-item:hover {
            background: #f8f9fa;
            border-color: #2271b1;
            transform: translateX(2px);
        }
        .vt-sr-placeholder-item code {
            font-family: Monaco, 'Courier New', monospace;
            font-size: 11px;
            color: #2271b1;
            font-weight: 600;
        }
        .vt-sr-placeholder-item .copy-icon {
            width: 14px;
            height: 14px;
            opacity: 0.5;
            transition: opacity 0.2s ease;
        }
        .vt-sr-placeholder-item:hover .copy-icon {
            opacity: 1;
        }
        .vt-sr-copy-feedback {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #2271b1;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .vt-sr-copy-feedback.show {
            opacity: 1;
        }
        </style>
        
        <div class="vt-submission-reminder-admin">
            <!-- Main Content Column -->
            <div class="vt-sr-main-content">
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin-bottom: 20px; color: #856404;">
                    <strong><?php _e('How it works:', 'voxel-toolkit'); ?></strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <li><?php _e('Automatically tracks submission counts when posts are published or set to pending', 'voxel-toolkit'); ?></li>
                        <li><?php _e('Each post type can have multiple notification intervals (hours, days, weeks, months)', 'voxel-toolkit'); ?></li>
                        <li><?php _e('Sends reminder emails based on time since last submission for each specific post type', 'voxel-toolkit'); ?></li>
                        <li><?php _e('Anti-spam protection: maximum one reminder per notification per 24 hours', 'voxel-toolkit'); ?></li>
                    </ul>
                </div>
                
                <?php foreach ($available_post_types as $post_type): ?>
                    <?php if (in_array($post_type->name, array('attachment', 'nav_menu_item', 'wp_block'))): continue; endif; ?>
                    <?php 
                    $is_enabled = in_array($post_type->name, $post_types);
                    $post_type_notifications = isset($notifications[$post_type->name]) ? $notifications[$post_type->name] : array();
                    ?>
                    
                    <div class="vt-sr-post-type-section">
                        <div class="vt-sr-post-type-header">
                            <h3>
                                <input type="checkbox" 
                                       name="voxel_toolkit_options[submission_reminder][post_types][]" 
                                       value="<?php echo esc_attr($post_type->name); ?>"
                                       <?php checked($is_enabled); ?>
                                       onchange="togglePostTypeNotifications('<?php echo esc_js($post_type->name); ?>', this.checked)" />
                                <?php echo esc_html($post_type->label); ?>
                                <small style="font-weight: normal; color: #666; margin-left: 8px;">(<?php echo esc_html($post_type->name); ?>)</small>
                            </h3>
                            <span style="font-size: 12px; color: #666;">
                                <?php echo sprintf(__('%d notifications configured', 'voxel-toolkit'), count($post_type_notifications)); ?>
                            </span>
                        </div>
                        
                        <div id="notifications-<?php echo esc_attr($post_type->name); ?>" class="vt-sr-notification-list" style="<?php echo $is_enabled ? '' : 'display: none;'; ?>">
                            <?php if (empty($post_type_notifications)): ?>
                                <p style="color: #666; text-align: center; margin: 20px 0;">
                                    <?php _e('No notifications configured for this post type. Click "Add Notification" to create one.', 'voxel-toolkit'); ?>
                                </p>
                            <?php else: ?>
                                <?php foreach ($post_type_notifications as $notification_id => $notification): ?>
                                    <?php $this->render_notification_item($post_type->name, $notification_id, $notification); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <div class="vt-sr-add-notification">
                                <button type="button" onclick="addNotification('<?php echo esc_js($post_type->name); ?>', '<?php echo esc_js($post_type->label); ?>')">
                                    <?php _e('Add Notification', 'voxel-toolkit'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Sync Section -->
                <div class="vt-sr-post-type-section" style="margin-top: 40px;">
                    <div class="vt-sr-post-type-header">
                        <h3>
                            🔄 <?php _e('Sync Existing Posts', 'voxel-toolkit'); ?>
                            <small style="font-weight: normal; color: #666; margin-left: 8px;"><?php _e('Populate tracking data from existing published posts', 'voxel-toolkit'); ?></small>
                        </h3>
                    </div>
                    
                    <?php 
                    // Check if submission reminder is instantiated
                    if (isset($this->active_functions['submission_reminder'])) {
                        $sr_instance = $this->active_functions['submission_reminder'];
                        $sync_stats = $sr_instance->get_sync_stats();
                        
                        if ($sync_stats && $sync_stats['total'] > 0) {
                            echo '<div style="display: grid; grid-template-columns: 1fr 200px; gap: 20px; align-items: start; padding: 20px;">';
                            
                            // Left column - stats and description
                            echo '<div>';
                            echo '<p style="margin: 0 0 15px 0;">';
                            echo sprintf(__('Found <strong>%d published posts</strong> that can be synced:', 'voxel-toolkit'), $sync_stats['total']);
                            echo '</p>';
                            
                            echo '<div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 4px; margin-bottom: 15px;">';
                            foreach ($sync_stats['by_type'] as $post_type => $count) {
                                $post_type_obj = get_post_type_object($post_type);
                                $label = $post_type_obj ? $post_type_obj->label : $post_type;
                                echo '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">';
                                echo '<span>' . $label . ':</span>';
                                echo '<strong>' . $count . '</strong>';
                                echo '</div>';
                            }
                            echo '</div>';
                            
                            echo '<p style="font-size: 13px; color: #d63638; margin: 0;">';
                            echo '<strong>' . __('Warning:', 'voxel-toolkit') . '</strong> ';
                            echo __('This will clear existing submission data and recalculate from all published posts.', 'voxel-toolkit');
                            echo '</p>';
                            echo '</div>';
                            
                            // Right column - button
                            echo '<div>';
                            echo '<button type="button" id="vt-sync-posts-btn" class="button button-primary" style="width: 100%; height: 40px; font-size: 14px;">';
                            echo __('Sync All Posts', 'voxel-toolkit');
                            echo '</button>';
                            echo '<div id="vt-sync-result" style="margin-top: 15px; display: none;"></div>';
                            echo '</div>';
                            
                            echo '</div>';
                        } else {
                            echo '<div style="padding: 20px; text-align: center;">';
                            echo '<p style="color: #d63638; margin: 0;">';
                            echo __('No published posts found for selected post types.', 'voxel-toolkit');
                            echo '</p>';
                            echo '</div>';
                        }
                    } else {
                        echo '<div style="padding: 20px; text-align: center;">';
                        echo '<p style="color: #d63638; margin: 0;">';
                        echo __('Submission Reminder function not active.', 'voxel-toolkit');
                        echo '</p>';
                        echo '</div>';
                    }
                    ?>
                </div>
                
                
            </div>
            
            <!-- Sidebar Column -->
            <div class="vt-sr-sidebar">
                <div class="vt-sr-sidebar-content">
                    <h3><?php _e('Available Placeholders', 'voxel-toolkit'); ?></h3>
                    <p style="font-size: 12px; color: #666; margin-bottom: 20px;">
                        <?php _e('Click any placeholder to copy it to your clipboard:', 'voxel-toolkit'); ?>
                    </p>
                    
                    <!-- User Information -->
                    <div class="vt-sr-placeholder-group">
                        <h4><?php _e('User Information', 'voxel-toolkit'); ?></h4>
                        <div class="vt-sr-placeholder-item" data-code="{user_name}">
                            <code>{user_name}</code>
                            <span class="vt-sr-copy-icon">📋</span>
                            <div class="vt-sr-placeholder-description"><?php _e('User display name', 'voxel-toolkit'); ?></div>
                        </div>
                        <div class="vt-sr-placeholder-item" data-code="{user_email}">
                            <code>{user_email}</code>
                            <span class="vt-sr-copy-icon">📋</span>
                            <div class="vt-sr-placeholder-description"><?php _e('User email address', 'voxel-toolkit'); ?></div>
                        </div>
                    </div>
                    
                    <!-- Submission Statistics -->
                    <div class="vt-sr-placeholder-group">
                        <h4><?php _e('Submission Statistics', 'voxel-toolkit'); ?></h4>
                        <div class="vt-sr-placeholder-item" data-code="{total_submissions}">
                            <code>{total_submissions}</code>
                            <span class="vt-sr-copy-icon">📋</span>
                            <div class="vt-sr-placeholder-description"><?php _e('Total submissions across all post types', 'voxel-toolkit'); ?></div>
                        </div>
                        <div class="vt-sr-placeholder-item" data-code="{days_since_last}">
                            <code>{days_since_last}</code>
                            <span class="vt-sr-copy-icon">📋</span>
                            <div class="vt-sr-placeholder-description"><?php _e('Days since last submission for this post type', 'voxel-toolkit'); ?></div>
                        </div>
                    </div>
                    
                    <!-- Notification Settings -->
                    <div class="vt-sr-placeholder-group">
                        <h4><?php _e('Notification Settings', 'voxel-toolkit'); ?></h4>
                        <div class="vt-sr-placeholder-item" data-code="{time_value}">
                            <code>{time_value}</code>
                            <span class="vt-sr-copy-icon">📋</span>
                            <div class="vt-sr-placeholder-description"><?php _e('Time value from notification settings', 'voxel-toolkit'); ?></div>
                        </div>
                        <div class="vt-sr-placeholder-item" data-code="{time_unit}">
                            <code>{time_unit}</code>
                            <span class="vt-sr-copy-icon">📋</span>
                            <div class="vt-sr-placeholder-description"><?php _e('Time unit from notification settings', 'voxel-toolkit'); ?></div>
                        </div>
                        <div class="vt-sr-placeholder-item" data-code="{post_type}">
                            <code>{post_type}</code>
                            <span class="vt-sr-copy-icon">📋</span>
                            <div class="vt-sr-placeholder-description"><?php _e('Post type label', 'voxel-toolkit'); ?></div>
                        </div>
                    </div>
                    
                    <!-- Site Information -->
                    <div class="vt-sr-placeholder-group">
                        <h4><?php _e('Site Information', 'voxel-toolkit'); ?></h4>
                        <div class="vt-sr-placeholder-item" data-code="{site_name}">
                            <code>{site_name}</code>
                            <span class="vt-sr-copy-icon">📋</span>
                            <div class="vt-sr-placeholder-description"><?php _e('Site name from WordPress settings', 'voxel-toolkit'); ?></div>
                        </div>
                        <div class="vt-sr-placeholder-item" data-code="{site_url}">
                            <code>{site_url}</code>
                            <span class="vt-sr-copy-icon">📋</span>
                            <div class="vt-sr-placeholder-description"><?php _e('Site homepage URL', 'voxel-toolkit'); ?></div>
                        </div>
                    </div>
                    
                    <!-- Post Type Submissions -->
                    <div class="vt-sr-placeholder-group">
                        <h4><?php _e('Post Type Submissions', 'voxel-toolkit'); ?></h4>
                        <p style="font-size: 11px; color: #888; margin-bottom: 10px;">
                            <?php _e('Dynamic placeholders based on enabled post types:', 'voxel-toolkit'); ?>
                        </p>
                        <?php foreach ($available_post_types as $pt_obj): ?>
                            <?php if (in_array($pt_obj->name, array('attachment', 'nav_menu_item', 'wp_block')) || !in_array($pt_obj->name, $post_types)): continue; endif; ?>
                            <?php $code = '{submissions_' . $pt_obj->name . '}'; ?>
                            <div class="vt-sr-placeholder-item" data-code="<?php echo esc_attr($code); ?>">
                                <code><?php echo esc_html($code); ?></code>
                                <span class="vt-sr-copy-icon">📋</span>
                                <div class="vt-sr-placeholder-description">
                                    <?php echo sprintf(__('Number of %s submissions', 'voxel-toolkit'), $pt_obj->label); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function togglePostTypeNotifications(postType, enabled) {
            const container = document.getElementById('notifications-' + postType);
            if (container) {
                container.style.display = enabled ? 'block' : 'none';
            }
        }
        
        function addNotification(postType, postTypeLabel) {
            const container = document.getElementById('notifications-' + postType);
            const notificationId = 'notification_' + Date.now();
            
            const notificationHtml = `
                <div class="vt-sr-notification-item" id="${notificationId}">
                    <div class="vt-sr-notification-header">
                        <div class="vt-sr-notification-title">
                            <input type="checkbox" 
                                   name="voxel_toolkit_options[submission_reminder][notifications][${postType}][${notificationId}][enabled]" 
                                   value="yes" checked />
                            <strong>New Notification</strong>
                        </div>
                        <button type="button" class="vt-sr-remove-notification" onclick="removeNotification('${notificationId}')">
                            Remove
                        </button>
                    </div>
                    
                    <div class="vt-sr-notification-fields">
                        <div class="vt-sr-field">
                            <label>Time Value</label>
                            <input type="number" 
                                   name="voxel_toolkit_options[submission_reminder][notifications][${postType}][${notificationId}][time_value]" 
                                   value="7" min="1" />
                        </div>
                        <div class="vt-sr-field">
                            <label>Time Unit</label>
                            <select name="voxel_toolkit_options[submission_reminder][notifications][${postType}][${notificationId}][time_unit]">
                                <option value="hours">Hours</option>
                                <option value="days" selected>Days</option>
                                <option value="weeks">Weeks</option>
                                <option value="months">Months</option>
                            </select>
                        </div>
                        <div class="vt-sr-field">
                            <label>Description</label>
                            <input type="text" 
                                   name="voxel_toolkit_options[submission_reminder][notifications][${postType}][${notificationId}][description]" 
                                   placeholder="e.g., Weekly reminder for ${postTypeLabel}" />
                        </div>
                    </div>
                    
                    <div class="vt-sr-email-fields">
                        <div class="vt-sr-field">
                            <label>Email Subject</label>
                            <input type="text" 
                                   name="voxel_toolkit_options[submission_reminder][notifications][${postType}][${notificationId}][subject]" 
                                   placeholder="Time to submit a new ${postTypeLabel}!" />
                        </div>
                        <div class="vt-sr-field">
                            <label>Email Message</label>
                            <textarea name="voxel_toolkit_options[submission_reminder][notifications][${postType}][${notificationId}][message]" 
                                      placeholder="Hi {user_name}, it's been {time_value} {time_unit} since your last ${postTypeLabel} submission..."></textarea>
                        </div>
                    </div>
                </div>
            `;
            
            // Insert before the add button
            const addButton = container.querySelector('.vt-sr-add-notification');
            addButton.insertAdjacentHTML('beforebegin', notificationHtml);
            
            // Hide the "no notifications" message if it exists
            const noNotificationsMsg = container.querySelector('p');
            if (noNotificationsMsg) {
                noNotificationsMsg.style.display = 'none';
            }
        }
        
        function removeNotification(notificationId) {
            const notification = document.getElementById(notificationId);
            if (notification && confirm('Are you sure you want to remove this notification?')) {
                notification.remove();
            }
        }
        
        // Copy functionality for placeholders
        document.addEventListener('DOMContentLoaded', function() {
            const placeholderItems = document.querySelectorAll('.vt-sr-placeholder-item');
            
            placeholderItems.forEach(item => {
                item.addEventListener('click', function() {
                    const code = this.dataset.code;
                    if (code) {
                        copyToClipboard(code);
                        showCopyFeedback(this);
                    }
                });
            });
        });
        
        function copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                // Use modern clipboard API if available
                navigator.clipboard.writeText(text).then(function() {
                    console.log('Copied to clipboard: ' + text);
                }).catch(function(err) {
                    console.error('Failed to copy: ', err);
                    fallbackCopyTextToClipboard(text);
                });
            } else {
                // Fallback for older browsers
                fallbackCopyTextToClipboard(text);
            }
        }
        
        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            
            // Avoid scrolling to bottom
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            textArea.style.opacity = "0";
            
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    console.log('Fallback: Copied to clipboard: ' + text);
                } else {
                    console.error('Fallback: Unable to copy');
                }
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
            }
            
            document.body.removeChild(textArea);
        }
        
        function showCopyFeedback(element) {
            // Create feedback element
            const feedback = document.createElement('div');
            feedback.className = 'vt-sr-copy-feedback';
            feedback.textContent = 'Copied!';
            
            // Position it relative to the clicked element
            const rect = element.getBoundingClientRect();
            feedback.style.position = 'fixed';
            feedback.style.left = (rect.left + rect.width / 2) + 'px';
            feedback.style.top = (rect.top - 30) + 'px';
            feedback.style.transform = 'translateX(-50%)';
            
            document.body.appendChild(feedback);
            
            // Show and then hide
            setTimeout(() => feedback.classList.add('show'), 10);
            setTimeout(() => {
                feedback.classList.remove('show');
                setTimeout(() => document.body.removeChild(feedback), 300);
            }, 1500);
        }
        
        // Sync posts functionality
        document.addEventListener('DOMContentLoaded', function() {
            const syncButton = document.getElementById('vt-sync-posts-btn');
            if (syncButton) {
                syncButton.addEventListener('click', function() {
                    if (!confirm('Are you sure you want to sync all posts? This will clear existing submission data and recalculate from all published posts.')) {
                        return;
                    }
                    
                    syncSubmissionData();
                });
            }
        });
        
        function syncSubmissionData() {
            const button = document.getElementById('vt-sync-posts-btn');
            const resultDiv = document.getElementById('vt-sync-result');
            
            if (!button || !resultDiv) return;
            
            // Update button state
            button.disabled = true;
            button.textContent = 'Syncing...';
            
            // Show loading in result div
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<p style="color: #666; font-size: 12px;">Syncing posts, please wait...</p>';
            
            // Make AJAX request
            const formData = new FormData();
            formData.append('action', 'voxel_toolkit_sync_submissions');
            formData.append('nonce', '<?php echo wp_create_nonce('voxel_toolkit_sync_submissions'); ?>');
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button
                button.disabled = false;
                button.textContent = 'Sync All Posts';
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div style="background: #d1edcc; border: 1px solid #5cb85c; padding: 10px; border-radius: 4px; color: #3c763d; font-size: 12px;">
                            <strong>Success!</strong><br>
                            ${data.data.message}
                        </div>
                    `;
                    
                    // Refresh the page after 2 seconds to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    resultDiv.innerHTML = `
                        <div style="background: #f2dede; border: 1px solid #d9534f; padding: 10px; border-radius: 4px; color: #a94442; font-size: 12px;">
                            <strong>Error:</strong><br>
                            ${data.data || 'An unknown error occurred.'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                // Reset button
                button.disabled = false;
                button.textContent = 'Sync All Posts';
                
                resultDiv.innerHTML = `
                    <div style="background: #f2dede; border: 1px solid #d9534f; padding: 10px; border-radius: 4px; color: #a94442; font-size: 12px;">
                        <strong>Error:</strong><br>
                        Failed to sync posts. Please try again.
                    </div>
                `;
                
                console.error('Sync error:', error);
            });
        }
        </script>
        <?php
    }
    
    /**
     * Render individual notification item
     */
    private function render_notification_item($post_type, $notification_id, $notification) {
        $enabled = isset($notification['enabled']) ? $notification['enabled'] : 'no';
        $time_value = isset($notification['time_value']) ? $notification['time_value'] : 7;
        $time_unit = isset($notification['time_unit']) ? $notification['time_unit'] : 'days';
        $description = isset($notification['description']) ? $notification['description'] : '';
        $subject = isset($notification['subject']) ? $notification['subject'] : '';
        $message = isset($notification['message']) ? $notification['message'] : '';
        ?>
        <div class="vt-sr-notification-item" id="<?php echo esc_attr($notification_id); ?>">
            <div class="vt-sr-notification-header">
                <div class="vt-sr-notification-title">
                    <input type="checkbox" 
                           name="voxel_toolkit_options[submission_reminder][notifications][<?php echo esc_attr($post_type); ?>][<?php echo esc_attr($notification_id); ?>][enabled]" 
                           value="yes" 
                           <?php checked($enabled, 'yes'); ?> />
                    <strong><?php echo $description ? esc_html($description) : sprintf(__('%d %s notification', 'voxel-toolkit'), $time_value, $time_unit); ?></strong>
                </div>
                <button type="button" class="vt-sr-remove-notification" onclick="removeNotification('<?php echo esc_js($notification_id); ?>')">
                    <?php _e('Remove', 'voxel-toolkit'); ?>
                </button>
            </div>
            
            <div class="vt-sr-notification-fields">
                <div class="vt-sr-field">
                    <label><?php _e('Time Value', 'voxel-toolkit'); ?></label>
                    <input type="number" 
                           name="voxel_toolkit_options[submission_reminder][notifications][<?php echo esc_attr($post_type); ?>][<?php echo esc_attr($notification_id); ?>][time_value]" 
                           value="<?php echo esc_attr($time_value); ?>" 
                           min="1" />
                </div>
                <div class="vt-sr-field">
                    <label><?php _e('Time Unit', 'voxel-toolkit'); ?></label>
                    <select name="voxel_toolkit_options[submission_reminder][notifications][<?php echo esc_attr($post_type); ?>][<?php echo esc_attr($notification_id); ?>][time_unit]">
                        <option value="hours" <?php selected($time_unit, 'hours'); ?>><?php _e('Hours', 'voxel-toolkit'); ?></option>
                        <option value="days" <?php selected($time_unit, 'days'); ?>><?php _e('Days', 'voxel-toolkit'); ?></option>
                        <option value="weeks" <?php selected($time_unit, 'weeks'); ?>><?php _e('Weeks', 'voxel-toolkit'); ?></option>
                        <option value="months" <?php selected($time_unit, 'months'); ?>><?php _e('Months', 'voxel-toolkit'); ?></option>
                    </select>
                </div>
                <div class="vt-sr-field">
                    <label><?php _e('Description', 'voxel-toolkit'); ?></label>
                    <input type="text" 
                           name="voxel_toolkit_options[submission_reminder][notifications][<?php echo esc_attr($post_type); ?>][<?php echo esc_attr($notification_id); ?>][description]" 
                           value="<?php echo esc_attr($description); ?>"
                           placeholder="<?php _e('e.g., Weekly reminder', 'voxel-toolkit'); ?>" />
                </div>
            </div>
            
            <div class="vt-sr-email-fields">
                <div class="vt-sr-field">
                    <label><?php _e('Email Subject', 'voxel-toolkit'); ?></label>
                    <input type="text" 
                           name="voxel_toolkit_options[submission_reminder][notifications][<?php echo esc_attr($post_type); ?>][<?php echo esc_attr($notification_id); ?>][subject]" 
                           value="<?php echo esc_attr($subject); ?>"
                           placeholder="<?php _e('Time to submit a new post!', 'voxel-toolkit'); ?>" />
                </div>
                <div class="vt-sr-field">
                    <label><?php _e('Email Message', 'voxel-toolkit'); ?></label>
                    <textarea name="voxel_toolkit_options[submission_reminder][notifications][<?php echo esc_attr($post_type); ?>][<?php echo esc_attr($notification_id); ?>][message]" 
                              placeholder="<?php _e('Hi {user_name}, it\'s been {time_value} {time_unit} since your last submission...', 'voxel-toolkit'); ?>"><?php echo esc_textarea($message); ?></textarea>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for syncing submission data
     */
    public function ajax_sync_submissions() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'voxel-toolkit'));
        }
        
        // Verify nonce
        check_ajax_referer('voxel_toolkit_sync_submissions', 'nonce');
        
        // Get submission reminder instance
        if (!isset($this->active_functions['submission_reminder'])) {
            wp_send_json_error(__('Submission Reminder function is not active.', 'voxel-toolkit'));
        }
        
        $sr_instance = $this->active_functions['submission_reminder'];
        
        // Perform sync
        $result = $sr_instance->sync_existing_posts();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX handler for sending test SMS
     * This is registered here so it works even when SMS Notifications function is disabled
     */
    public function ajax_send_test_sms() {
        check_ajax_referer('vt_sms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'voxel-toolkit')));
        }

        // Get phone number from request
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

        if (empty($phone)) {
            wp_send_json_error(array('message' => __('Please enter a phone number', 'voxel-toolkit')));
        }

        // Get SMS settings
        $settings = Voxel_Toolkit_Settings::instance();
        $sms_settings = $settings->get_function_settings('sms_notifications', array());

        $provider = isset($sms_settings['provider']) ? $sms_settings['provider'] : 'twilio';

        // Normalize phone number (basic normalization)
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (!empty($phone) && $phone[0] !== '+') {
            $phone = '+' . $phone;
        }

        if (empty($phone) || strlen($phone) < 10) {
            wp_send_json_error(array('message' => __('Invalid phone number format. Include country code (e.g., +1234567890)', 'voxel-toolkit')));
        }

        $message = sprintf(
            __('Test SMS from %s - Voxel Toolkit SMS Notifications is working!', 'voxel-toolkit'),
            get_bloginfo('name')
        );

        // Send based on provider
        $result = $this->send_test_sms_via_provider($provider, $phone, $message, $sms_settings);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Test SMS sent successfully!', 'voxel-toolkit'),
                'message_id' => isset($result['message_id']) ? $result['message_id'] : '',
            ));
        } else {
            wp_send_json_error(array(
                'message' => sprintf(__('Failed to send SMS: %s', 'voxel-toolkit'), $result['error']),
            ));
        }
    }

    /**
     * Send test SMS via the configured provider
     */
    private function send_test_sms_via_provider($provider, $phone, $message, $settings) {
        switch ($provider) {
            case 'twilio':
                return $this->send_sms_twilio($phone, $message, $settings);
            case 'vonage':
                return $this->send_sms_vonage($phone, $message, $settings);
            case 'messagebird':
                return $this->send_sms_messagebird($phone, $message, $settings);
            default:
                return array('success' => false, 'error' => __('Unknown SMS provider', 'voxel-toolkit'));
        }
    }

    /**
     * Send SMS via Twilio
     */
    private function send_sms_twilio($phone, $message, $settings) {
        $account_sid = isset($settings['twilio_account_sid']) ? $settings['twilio_account_sid'] : '';
        $auth_token = isset($settings['twilio_auth_token']) ? $settings['twilio_auth_token'] : '';
        $from_number = isset($settings['twilio_from_number']) ? $settings['twilio_from_number'] : '';

        if (empty($account_sid) || empty($auth_token) || empty($from_number)) {
            return array('success' => false, 'error' => __('Twilio credentials not configured', 'voxel-toolkit'));
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("{$account_sid}:{$auth_token}"),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'To' => $phone,
                'From' => $from_number,
                'Body' => $message,
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 200 && $status_code < 300 && isset($body['sid'])) {
            return array('success' => true, 'message_id' => $body['sid']);
        }

        $error = isset($body['message']) ? $body['message'] : __('Unknown Twilio error', 'voxel-toolkit');
        return array('success' => false, 'error' => $error);
    }

    /**
     * Send SMS via Vonage (Nexmo)
     */
    private function send_sms_vonage($phone, $message, $settings) {
        $api_key = isset($settings['vonage_api_key']) ? $settings['vonage_api_key'] : '';
        $api_secret = isset($settings['vonage_api_secret']) ? $settings['vonage_api_secret'] : '';
        $from = isset($settings['vonage_from']) ? $settings['vonage_from'] : '';

        if (empty($api_key) || empty($api_secret) || empty($from)) {
            return array('success' => false, 'error' => __('Vonage credentials not configured', 'voxel-toolkit'));
        }

        $url = 'https://rest.nexmo.com/sms/json';

        $response = wp_remote_post($url, array(
            'body' => array(
                'api_key' => $api_key,
                'api_secret' => $api_secret,
                'to' => preg_replace('/[^0-9]/', '', $phone),
                'from' => $from,
                'text' => $message,
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['messages'][0]['status']) && $body['messages'][0]['status'] === '0') {
            return array('success' => true, 'message_id' => $body['messages'][0]['message-id']);
        }

        $error = isset($body['messages'][0]['error-text']) ? $body['messages'][0]['error-text'] : __('Unknown Vonage error', 'voxel-toolkit');
        return array('success' => false, 'error' => $error);
    }

    /**
     * Send SMS via MessageBird
     */
    private function send_sms_messagebird($phone, $message, $settings) {
        $api_key = isset($settings['messagebird_api_key']) ? $settings['messagebird_api_key'] : '';
        $originator = isset($settings['messagebird_originator']) ? $settings['messagebird_originator'] : '';

        if (empty($api_key) || empty($originator)) {
            return array('success' => false, 'error' => __('MessageBird credentials not configured', 'voxel-toolkit'));
        }

        $url = 'https://rest.messagebird.com/messages';

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'AccessKey ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'recipients' => array(preg_replace('/[^0-9]/', '', $phone)),
                'originator' => $originator,
                'body' => $message,
            )),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 200 && $status_code < 300 && isset($body['id'])) {
            return array('success' => true, 'message_id' => $body['id']);
        }

        $error = isset($body['errors'][0]['description']) ? $body['errors'][0]['description'] : __('Unknown MessageBird error', 'voxel-toolkit');
        return array('success' => false, 'error' => $error);
    }

    /**
     * Render settings for Duplicate Title Checker
     */
    public function render_duplicate_title_checker_settings($settings) {
        // Get duplicate title checker instance
        if (isset($this->active_functions['duplicate_title_checker'])) {
            $instance = $this->active_functions['duplicate_title_checker'];
            $instance->render_settings($settings);
        }
    }

    /**
     * Render settings for visitor location function
     *
     * @param array $settings Current settings
     */
    public function render_visitor_location_settings($settings) {
        $cache_duration = isset($settings['visitor_location_cache_duration']) ? absint($settings['visitor_location_cache_duration']) : 3600;
        $detection_mode = isset($settings['visitor_location_mode']) ? sanitize_text_field($settings['visitor_location_mode']) : 'ip';

        ?>
        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Detection Mode', 'voxel-toolkit'); ?></h4>
            <div class="vt-checkbox-list">
                <label class="vt-checkbox-item">
                    <input type="radio"
                           name="voxel_toolkit_options[visitor_location][visitor_location_mode]"
                           value="ip"
                           <?php checked($detection_mode, 'ip'); ?> />
                    <div class="vt-checkbox-item-content">
                        <span class="vt-checkbox-item-label"><?php _e('IP Geolocation', 'voxel-toolkit'); ?></span>
                        <p class="vt-checkbox-item-description"><?php _e('Automatic detection using IP address. City-level accuracy (~50-100 mile radius).', 'voxel-toolkit'); ?></p>
                    </div>
                </label>
                <label class="vt-checkbox-item">
                    <input type="radio"
                           name="voxel_toolkit_options[visitor_location][visitor_location_mode]"
                           value="browser"
                           <?php checked($detection_mode, 'browser'); ?> />
                    <div class="vt-checkbox-item-content">
                        <span class="vt-checkbox-item-label"><?php _e('Browser Geolocation (More Accurate)', 'voxel-toolkit'); ?></span>
                        <p class="vt-checkbox-item-description"><?php _e('Uses GPS/WiFi/cell towers. Requires user permission. GPS-level accuracy.', 'voxel-toolkit'); ?></p>
                    </div>
                </label>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Cache Duration', 'voxel-toolkit'); ?></h4>
            <div class="vt-field-group">
                <input type="number"
                       name="voxel_toolkit_options[visitor_location][visitor_location_cache_duration]"
                       value="<?php echo esc_attr($cache_duration); ?>"
                       min="0"
                       class="vt-text-input"
                       style="max-width: 150px;" />
                <p class="vt-field-description"><?php _e('Seconds to cache location data. Default: 3600 (1 hour). Set to 0 to disable.', 'voxel-toolkit'); ?></p>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Available Dynamic Tags', 'voxel-toolkit'); ?></h4>
            <div style="background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; font-family: monospace; font-size: 13px;">
                <div style="margin-bottom: 8px;"><code>@site(visitor.location)</code> - <?php _e('Full location', 'voxel-toolkit'); ?></div>
                <div style="margin-bottom: 8px;"><code>@site(visitor.city)</code> - <?php _e('City name', 'voxel-toolkit'); ?></div>
                <div style="margin-bottom: 8px;"><code>@site(visitor.state)</code> - <?php _e('State/region', 'voxel-toolkit'); ?></div>
                <div><code>@site(visitor.country)</code> - <?php _e('Country name', 'voxel-toolkit'); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings for Compare Posts widget
     *
     * @param array $settings Current settings
     */
    public function render_compare_posts_settings($settings) {
        $max_posts = isset($settings['max_posts']) ? absint($settings['max_posts']) : 4;
        $comparison_pages = isset($settings['comparison_pages']) ? (array) $settings['comparison_pages'] : array();

        // Styling options
        $badge_bg_color = isset($settings['badge_bg_color']) ? sanitize_hex_color($settings['badge_bg_color']) : '#3b82f6';
        $badge_text_color = isset($settings['badge_text_color']) ? sanitize_hex_color($settings['badge_text_color']) : '#ffffff';
        $badge_border_radius = isset($settings['badge_border_radius']) ? absint($settings['badge_border_radius']) : 8;
        $popup_bg_color = isset($settings['popup_bg_color']) ? sanitize_hex_color($settings['popup_bg_color']) : '#ffffff';
        $popup_text_color = isset($settings['popup_text_color']) ? sanitize_hex_color($settings['popup_text_color']) : '#111827';
        $popup_border_radius = isset($settings['popup_border_radius']) ? absint($settings['popup_border_radius']) : 12;
        $button_bg_color = isset($settings['button_bg_color']) ? sanitize_hex_color($settings['button_bg_color']) : '#3b82f6';
        $button_text_color = isset($settings['button_text_color']) ? sanitize_hex_color($settings['button_text_color']) : '#ffffff';
        $button_border_radius = isset($settings['button_border_radius']) ? absint($settings['button_border_radius']) : 6;
        $secondary_bg_color = isset($settings['secondary_bg_color']) ? sanitize_hex_color($settings['secondary_bg_color']) : '#f3f4f6';
        $secondary_text_color = isset($settings['secondary_text_color']) ? sanitize_hex_color($settings['secondary_text_color']) : '#374151';

        // Text/Labels
        $badge_text = isset($settings['badge_text']) ? sanitize_text_field($settings['badge_text']) : __('Compare', 'voxel-toolkit');
        $popup_title = isset($settings['popup_title']) ? sanitize_text_field($settings['popup_title']) : __('Compare Posts', 'voxel-toolkit');
        $view_button_text = isset($settings['view_button_text']) ? sanitize_text_field($settings['view_button_text']) : __('View Comparison', 'voxel-toolkit');
        $clear_button_text = isset($settings['clear_button_text']) ? sanitize_text_field($settings['clear_button_text']) : __('Clear All', 'voxel-toolkit');

        // Get all published pages for dropdown
        $pages = get_pages(array(
            'post_status' => 'publish',
            'sort_column' => 'post_title',
            'sort_order' => 'ASC'
        ));

        // Get Voxel post types
        $voxel_post_types = array();
        $post_types_option = get_option('voxel:post_types', '[]');
        $post_types_data = json_decode($post_types_option, true);
        if (is_array($post_types_data)) {
            foreach ($post_types_data as $pt) {
                if (isset($pt['settings']['key']) && isset($pt['settings']['singular'])) {
                    $voxel_post_types[$pt['settings']['key']] = $pt['settings']['singular'];
                }
            }
        }
        ?>
        <div class="vt-info-box">
            <?php _e('Allow users to compare posts of the same type side-by-side. Use the "Add to Compare" action in the Action (VX) widget, and place the Comparison Table widget on a dedicated comparison page.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Comparison Pages', 'voxel-toolkit'); ?></h4>
            <p class="vt-field-description" style="margin-bottom: 15px;">
                <?php _e('Select the comparison page for each post type. When users click "View Comparison", they will be redirected to the appropriate page.', 'voxel-toolkit'); ?>
            </p>
            <?php foreach ($voxel_post_types as $pt_key => $pt_label):
                $selected_page = isset($comparison_pages[$pt_key]) ? absint($comparison_pages[$pt_key]) : 0;
            ?>
            <div class="vt-field-group" style="margin-bottom: 12px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">
                    <?php echo esc_html($pt_label); ?>
                </label>
                <select name="voxel_toolkit_options[compare_posts][comparison_pages][<?php echo esc_attr($pt_key); ?>]" style="width: 100%; max-width: 400px;">
                    <option value=""><?php _e('— Select a page —', 'voxel-toolkit'); ?></option>
                    <?php foreach ($pages as $page): ?>
                        <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($selected_page, $page->ID); ?>>
                            <?php echo esc_html($page->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Maximum Posts to Compare', 'voxel-toolkit'); ?></h4>
            <div class="vt-field-group">
                <select name="voxel_toolkit_options[compare_posts][max_posts]" style="width: 100px;">
                    <?php for ($i = 2; $i <= 4; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected($max_posts, $i); ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
                <p class="vt-field-description">
                    <?php _e('Maximum number of posts that can be compared at once. Minimum is always 2.', 'voxel-toolkit'); ?>
                </p>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Badge & Popup Styling', 'voxel-toolkit'); ?></h4>

            <div style="display: grid; grid-template-columns: 1fr 320px; gap: 30px; align-items: start;">
                <!-- Settings Column -->
                <div>
                    <h5 style="margin: 0 0 15px; font-size: 13px; font-weight: 600; color: #374151;"><?php _e('Compare Badge', 'voxel-toolkit'); ?></h5>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px;">
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('Background', 'voxel-toolkit'); ?></label>
                            <input type="color"
                                   name="voxel_toolkit_options[compare_posts][badge_bg_color]"
                                   value="<?php echo esc_attr($badge_bg_color); ?>"
                                   class="vt-compare-badge-bg"
                                   style="width: 100%; height: 36px; padding: 2px; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                        </div>
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('Text Color', 'voxel-toolkit'); ?></label>
                            <input type="color"
                                   name="voxel_toolkit_options[compare_posts][badge_text_color]"
                                   value="<?php echo esc_attr($badge_text_color); ?>"
                                   class="vt-compare-badge-text"
                                   style="width: 100%; height: 36px; padding: 2px; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                        </div>
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('Border Radius', 'voxel-toolkit'); ?></label>
                            <input type="number"
                                   name="voxel_toolkit_options[compare_posts][badge_border_radius]"
                                   value="<?php echo esc_attr($badge_border_radius); ?>"
                                   min="0"
                                   max="30"
                                   class="vt-compare-badge-radius"
                                   style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        </div>
                    </div>

                    <h5 style="margin: 0 0 15px; font-size: 13px; font-weight: 600; color: #374151;"><?php _e('Popup Panel', 'voxel-toolkit'); ?></h5>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px;">
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('Background', 'voxel-toolkit'); ?></label>
                            <input type="color"
                                   name="voxel_toolkit_options[compare_posts][popup_bg_color]"
                                   value="<?php echo esc_attr($popup_bg_color); ?>"
                                   class="vt-compare-popup-bg"
                                   style="width: 100%; height: 36px; padding: 2px; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                        </div>
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('Text Color', 'voxel-toolkit'); ?></label>
                            <input type="color"
                                   name="voxel_toolkit_options[compare_posts][popup_text_color]"
                                   value="<?php echo esc_attr($popup_text_color); ?>"
                                   class="vt-compare-popup-text"
                                   style="width: 100%; height: 36px; padding: 2px; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                        </div>
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('Border Radius', 'voxel-toolkit'); ?></label>
                            <input type="number"
                                   name="voxel_toolkit_options[compare_posts][popup_border_radius]"
                                   value="<?php echo esc_attr($popup_border_radius); ?>"
                                   min="0"
                                   max="30"
                                   class="vt-compare-popup-radius"
                                   style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        </div>
                    </div>

                    <h5 style="margin: 0 0 15px; font-size: 13px; font-weight: 600; color: #374151;"><?php _e('Primary Button', 'voxel-toolkit'); ?></h5>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px;">
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('Background', 'voxel-toolkit'); ?></label>
                            <input type="color"
                                   name="voxel_toolkit_options[compare_posts][button_bg_color]"
                                   value="<?php echo esc_attr($button_bg_color); ?>"
                                   class="vt-compare-btn-bg"
                                   style="width: 100%; height: 36px; padding: 2px; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                        </div>
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('Text Color', 'voxel-toolkit'); ?></label>
                            <input type="color"
                                   name="voxel_toolkit_options[compare_posts][button_text_color]"
                                   value="<?php echo esc_attr($button_text_color); ?>"
                                   class="vt-compare-btn-text"
                                   style="width: 100%; height: 36px; padding: 2px; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                        </div>
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('Border Radius', 'voxel-toolkit'); ?></label>
                            <input type="number"
                                   name="voxel_toolkit_options[compare_posts][button_border_radius]"
                                   value="<?php echo esc_attr($button_border_radius); ?>"
                                   min="0"
                                   max="30"
                                   class="vt-compare-btn-radius"
                                   style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        </div>
                    </div>

                    <h5 style="margin: 0 0 15px; font-size: 13px; font-weight: 600; color: #374151;"><?php _e('Secondary Button', 'voxel-toolkit'); ?></h5>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('Background', 'voxel-toolkit'); ?></label>
                            <input type="color"
                                   name="voxel_toolkit_options[compare_posts][secondary_bg_color]"
                                   value="<?php echo esc_attr($secondary_bg_color); ?>"
                                   class="vt-compare-secondary-bg"
                                   style="width: 100%; height: 36px; padding: 2px; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                        </div>
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('Text Color', 'voxel-toolkit'); ?></label>
                            <input type="color"
                                   name="voxel_toolkit_options[compare_posts][secondary_text_color]"
                                   value="<?php echo esc_attr($secondary_text_color); ?>"
                                   class="vt-compare-secondary-text"
                                   style="width: 100%; height: 36px; padding: 2px; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                        </div>
                    </div>

                    <h5 style="margin: 0 0 15px; font-size: 13px; font-weight: 600; color: #374151;"><?php _e('Labels', 'voxel-toolkit'); ?></h5>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('Badge Text', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[compare_posts][badge_text]"
                                   value="<?php echo esc_attr($badge_text); ?>"
                                   class="vt-compare-badge-label"
                                   placeholder="<?php esc_attr_e('Compare', 'voxel-toolkit'); ?>"
                                   style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        </div>
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('Popup Title', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[compare_posts][popup_title]"
                                   value="<?php echo esc_attr($popup_title); ?>"
                                   class="vt-compare-popup-title"
                                   placeholder="<?php esc_attr_e('Compare Posts', 'voxel-toolkit'); ?>"
                                   style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        </div>
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('View Button', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[compare_posts][view_button_text]"
                                   value="<?php echo esc_attr($view_button_text); ?>"
                                   class="vt-compare-view-btn-text"
                                   placeholder="<?php esc_attr_e('View Comparison', 'voxel-toolkit'); ?>"
                                   style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        </div>
                        <div class="vt-field-group">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #6b7280;"><?php _e('Clear Button', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[compare_posts][clear_button_text]"
                                   value="<?php echo esc_attr($clear_button_text); ?>"
                                   class="vt-compare-clear-btn-text"
                                   placeholder="<?php esc_attr_e('Clear All', 'voxel-toolkit'); ?>"
                                   style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        </div>
                    </div>
                </div>

                <!-- Preview Column -->
                <div>
                    <h5 style="margin: 0 0 15px; font-size: 13px; font-weight: 600; color: #374151;"><?php _e('Preview', 'voxel-toolkit'); ?></h5>
                    <div style="background: #f3f4f6; border-radius: 8px; padding: 20px; position: relative; min-height: 280px;">
                        <!-- Badge Preview -->
                        <div class="vt-preview-badge" style="
                            position: absolute;
                            right: 0;
                            top: 50%;
                            transform: translateY(-50%);
                            writing-mode: vertical-rl;
                            text-orientation: mixed;
                            background: <?php echo esc_attr($badge_bg_color); ?>;
                            color: <?php echo esc_attr($badge_text_color); ?>;
                            padding: 12px 8px;
                            border-radius: <?php echo esc_attr($badge_border_radius); ?>px 0 0 <?php echo esc_attr($badge_border_radius); ?>px;
                            font-size: 12px;
                            font-weight: 500;
                            display: flex;
                            align-items: center;
                            gap: 6px;
                        ">
                            <span class="vt-preview-badge-text"><?php echo esc_html($badge_text); ?></span>
                            <span class="vt-preview-badge-count" style="
                                writing-mode: horizontal-tb;
                                background: <?php echo esc_attr($badge_text_color); ?>;
                                color: <?php echo esc_attr($badge_bg_color); ?>;
                                border-radius: 50%;
                                min-width: 18px;
                                height: 18px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 11px;
                                font-weight: 600;
                            ">2</span>
                        </div>

                        <!-- Popup Preview -->
                        <div class="vt-preview-popup" style="
                            background: <?php echo esc_attr($popup_bg_color); ?>;
                            border-radius: <?php echo esc_attr($popup_border_radius); ?>px;
                            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                            width: 240px;
                            overflow: hidden;
                        ">
                            <div style="padding: 12px 15px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                                <span class="vt-preview-popup-title" style="font-weight: 600; font-size: 13px; color: <?php echo esc_attr($popup_text_color); ?>;"><?php echo esc_html($popup_title); ?></span>
                                <span style="color: #9ca3af; font-size: 16px;">×</span>
                            </div>
                            <div style="padding: 8px 0;">
                                <div style="padding: 10px 15px; color: <?php echo esc_attr($popup_text_color); ?>; font-size: 12px; border-bottom: 1px solid #f3f4f6;">Beach House</div>
                                <div style="padding: 10px 15px; color: <?php echo esc_attr($popup_text_color); ?>; font-size: 12px;">Mountain Cabin</div>
                            </div>
                            <div style="padding: 12px 15px; border-top: 1px solid #e5e7eb; display: flex; gap: 8px;">
                                <button type="button" class="vt-preview-btn-primary" style="
                                    flex: 1;
                                    padding: 8px 12px;
                                    background: <?php echo esc_attr($button_bg_color); ?>;
                                    color: <?php echo esc_attr($button_text_color); ?>;
                                    border: none;
                                    border-radius: <?php echo esc_attr($button_border_radius); ?>px;
                                    font-size: 11px;
                                    font-weight: 500;
                                    cursor: default;
                                "><?php echo esc_html($view_button_text); ?></button>
                                <button type="button" class="vt-preview-btn-secondary" style="
                                    flex: 1;
                                    padding: 8px 12px;
                                    background: <?php echo esc_attr($secondary_bg_color); ?>;
                                    color: <?php echo esc_attr($secondary_text_color); ?>;
                                    border: none;
                                    border-radius: <?php echo esc_attr($button_border_radius); ?>px;
                                    font-size: 11px;
                                    font-weight: 500;
                                    cursor: default;
                                "><?php echo esc_html($clear_button_text); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('How to Use', 'voxel-toolkit'); ?></h4>
            <div style="background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px;">
                <ol style="margin: 0; padding-left: 20px; color: #374151;">
                    <li style="margin-bottom: 8px;"><?php _e('Create a comparison page for each post type you want to enable comparisons for.', 'voxel-toolkit'); ?></li>
                    <li style="margin-bottom: 8px;"><?php _e('Add the <strong>Comparison Table (VT)</strong> widget to each page and configure which fields to display.', 'voxel-toolkit'); ?></li>
                    <li style="margin-bottom: 8px;"><?php _e('Select the appropriate page for each post type in the "Comparison Pages" settings above.', 'voxel-toolkit'); ?></li>
                    <li style="margin-bottom: 8px;"><?php _e('Add the <strong>Action (VX)</strong> widget to your post cards and select "Add to Compare" as the action.', 'voxel-toolkit'); ?></li>
                    <li><?php _e('When users add posts to compare, a floating badge appears on the right side. Clicking it opens the compare panel.', 'voxel-toolkit'); ?></li>
                </ol>
            </div>
        </div>

        <script>
        jQuery(function($) {
            // Live preview updates
            function updatePreview() {
                // Colors and styling
                var badgeBg = $('.vt-compare-badge-bg').val();
                var badgeTextColor = $('.vt-compare-badge-text').val();
                var badgeRadius = $('.vt-compare-badge-radius').val();
                var popupBg = $('.vt-compare-popup-bg').val();
                var popupTextColor = $('.vt-compare-popup-text').val();
                var popupRadius = $('.vt-compare-popup-radius').val();
                var btnBg = $('.vt-compare-btn-bg').val();
                var btnTextColor = $('.vt-compare-btn-text').val();
                var btnRadius = $('.vt-compare-btn-radius').val();
                var secondaryBg = $('.vt-compare-secondary-bg').val();
                var secondaryTextColor = $('.vt-compare-secondary-text').val();

                // Labels
                var badgeLabel = $('.vt-compare-badge-label').val() || 'Compare';
                var popupTitle = $('.vt-compare-popup-title').val() || 'Compare Posts';
                var viewBtnText = $('.vt-compare-view-btn-text').val() || 'View Comparison';
                var clearBtnText = $('.vt-compare-clear-btn-text').val() || 'Clear All';

                // Apply colors
                $('.vt-preview-badge').css({
                    'background': badgeBg,
                    'color': badgeTextColor,
                    'border-radius': badgeRadius + 'px 0 0 ' + badgeRadius + 'px'
                });
                $('.vt-preview-badge-count').css({
                    'background': badgeTextColor,
                    'color': badgeBg
                });
                $('.vt-preview-popup').css({
                    'background': popupBg,
                    'border-radius': popupRadius + 'px'
                });
                $('.vt-preview-popup-title').css('color', popupTextColor);
                $('.vt-preview-popup').find('[style*="font-size: 12px"]').css('color', popupTextColor);
                $('.vt-preview-btn-primary').css({
                    'background': btnBg,
                    'color': btnTextColor,
                    'border-radius': btnRadius + 'px'
                });
                $('.vt-preview-btn-secondary').css({
                    'background': secondaryBg,
                    'color': secondaryTextColor,
                    'border-radius': btnRadius + 'px'
                });

                // Apply labels
                $('.vt-preview-badge-text').text(badgeLabel);
                $('.vt-preview-popup-title').text(popupTitle);
                $('.vt-preview-btn-primary').text(viewBtnText);
                $('.vt-preview-btn-secondary').text(clearBtnText);
            }

            $('.vt-compare-badge-bg, .vt-compare-badge-text, .vt-compare-badge-radius, .vt-compare-popup-bg, .vt-compare-popup-text, .vt-compare-popup-radius, .vt-compare-btn-bg, .vt-compare-btn-text, .vt-compare-btn-radius, .vt-compare-secondary-bg, .vt-compare-secondary-text, .vt-compare-badge-label, .vt-compare-popup-title, .vt-compare-view-btn-text, .vt-compare-clear-btn-text').on('input change', updatePreview);
        });
        </script>
        <?php
    }

    /**
     * Render Social Proof settings
     *
     * @param array $settings Current settings
     */
    public function render_social_proof_settings($settings) {
        $enabled = !empty($settings['enabled']);
        $position = isset($settings['position']) ? sanitize_text_field($settings['position']) : 'bottom-left';
        $display_duration = isset($settings['display_duration']) ? absint($settings['display_duration']) : 5;
        $delay_between = isset($settings['delay_between']) ? absint($settings['delay_between']) : 3;
        $max_events = isset($settings['max_events']) ? absint($settings['max_events']) : 10;
        $poll_interval = isset($settings['poll_interval']) ? absint($settings['poll_interval']) : 30;
        $hide_on_mobile = !empty($settings['hide_on_mobile']);
        $show_close_button = !empty($settings['show_close_button']);
        $animation = isset($settings['animation']) ? sanitize_text_field($settings['animation']) : 'slide';
        $bg_color = isset($settings['background_color']) ? sanitize_hex_color($settings['background_color']) : '#ffffff';
        $text_color = isset($settings['text_color']) ? sanitize_hex_color($settings['text_color']) : '#1a1a1a';
        $border_radius = isset($settings['border_radius']) ? absint($settings['border_radius']) : 10;
        $avatar_size = isset($settings['avatar_size']) ? absint($settings['avatar_size']) : 48;
        $default_avatar = isset($settings['default_avatar']) ? esc_url($settings['default_avatar']) : '';
        $events_settings = isset($settings['events']) ? (array) $settings['events'] : array();

        // Activity Boost settings
        $boost_enabled = !empty($settings['boost_enabled']);
        $boost_mode = isset($settings['boost_mode']) ? sanitize_text_field($settings['boost_mode']) : 'fill_gaps';
        $boost_names = isset($settings['boost_names']) ? $settings['boost_names'] : "Emma\nJames\nSofia\nOliver\nMia\nLucas\nAva\nNoah\nIsabella\nLiam";
        $boost_listings = isset($settings['boost_listings']) ? $settings['boost_listings'] : "Beach House\nMountain Cabin\nCity Apartment\nLake Cottage\nCozy Studio";
        $boost_messages = isset($settings['boost_messages']) ? (array) $settings['boost_messages'] : array(
            'booking' => '{name} just booked {listing}',
            'signup' => '{name} just joined',
            'review' => '{name} left a review on {listing}',
        );

        // Get available events
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/class-social-proof.php';
        $available_events = Voxel_Toolkit_Social_Proof::get_available_events();
        ?>
        <div class="vt-info-box">
            <?php _e('Display toast notifications showing recent activity like new bookings, listings, and orders to create social proof and urgency.', 'voxel-toolkit'); ?>
        </div>

        <!-- Global Enable -->
        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Enable Social Proof', 'voxel-toolkit'); ?></h4>
            <div class="vt-field-group">
                <label class="vt-toggle">
                    <input type="checkbox"
                           name="voxel_toolkit_options[social_proof][enabled]"
                           value="1"
                           <?php checked($enabled); ?>>
                    <span class="vt-toggle-slider"></span>
                </label>
                <span style="margin-left: 10px;"><?php _e('Show social proof notifications on the frontend', 'voxel-toolkit'); ?></span>
            </div>
        </div>

        <!-- General Settings -->
        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Display Settings', 'voxel-toolkit'); ?></h4>

            <div class="vt-field-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Position', 'voxel-toolkit'); ?></label>
                <select name="voxel_toolkit_options[social_proof][position]" style="width: 200px;">
                    <option value="bottom-left" <?php selected($position, 'bottom-left'); ?>><?php _e('Bottom Left', 'voxel-toolkit'); ?></option>
                    <option value="bottom-right" <?php selected($position, 'bottom-right'); ?>><?php _e('Bottom Right', 'voxel-toolkit'); ?></option>
                    <option value="top-left" <?php selected($position, 'top-left'); ?>><?php _e('Top Left', 'voxel-toolkit'); ?></option>
                    <option value="top-right" <?php selected($position, 'top-right'); ?>><?php _e('Top Right', 'voxel-toolkit'); ?></option>
                </select>
            </div>

            <div class="vt-field-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Display Duration (seconds)', 'voxel-toolkit'); ?></label>
                <input type="number"
                       name="voxel_toolkit_options[social_proof][display_duration]"
                       value="<?php echo esc_attr($display_duration); ?>"
                       min="2"
                       max="30"
                       style="width: 100px;">
                <p class="vt-field-description"><?php _e('How long each notification stays visible.', 'voxel-toolkit'); ?></p>
            </div>

            <div class="vt-field-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Delay Between Toasts (seconds)', 'voxel-toolkit'); ?></label>
                <input type="number"
                       name="voxel_toolkit_options[social_proof][delay_between]"
                       value="<?php echo esc_attr($delay_between); ?>"
                       min="1"
                       max="60"
                       style="width: 100px;">
                <p class="vt-field-description"><?php _e('Pause between consecutive notifications.', 'voxel-toolkit'); ?></p>
            </div>

            <div class="vt-field-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Max Events to Rotate', 'voxel-toolkit'); ?></label>
                <input type="number"
                       name="voxel_toolkit_options[social_proof][max_events]"
                       value="<?php echo esc_attr($max_events); ?>"
                       min="5"
                       max="50"
                       style="width: 100px;">
                <p class="vt-field-description"><?php _e('Number of recent events to cycle through.', 'voxel-toolkit'); ?></p>
            </div>

            <div class="vt-field-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Polling Interval (seconds)', 'voxel-toolkit'); ?></label>
                <input type="number"
                       name="voxel_toolkit_options[social_proof][poll_interval]"
                       value="<?php echo esc_attr($poll_interval); ?>"
                       min="10"
                       max="300"
                       style="width: 100px;">
                <p class="vt-field-description"><?php _e('How often to check for new events. Set higher to reduce server load.', 'voxel-toolkit'); ?></p>
            </div>

            <div class="vt-field-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Animation', 'voxel-toolkit'); ?></label>
                <select name="voxel_toolkit_options[social_proof][animation]" style="width: 150px;">
                    <option value="slide" <?php selected($animation, 'slide'); ?>><?php _e('Slide', 'voxel-toolkit'); ?></option>
                    <option value="fade" <?php selected($animation, 'fade'); ?>><?php _e('Fade', 'voxel-toolkit'); ?></option>
                </select>
            </div>

            <div class="vt-field-group" style="margin-bottom: 15px;">
                <label class="vt-toggle">
                    <input type="checkbox"
                           name="voxel_toolkit_options[social_proof][hide_on_mobile]"
                           value="1"
                           <?php checked($hide_on_mobile); ?>>
                    <span class="vt-toggle-slider"></span>
                </label>
                <span style="margin-left: 10px;"><?php _e('Hide on mobile devices', 'voxel-toolkit'); ?></span>
            </div>

            <div class="vt-field-group">
                <label class="vt-toggle">
                    <input type="checkbox"
                           name="voxel_toolkit_options[social_proof][show_close_button]"
                           value="1"
                           <?php checked($show_close_button); ?>>
                    <span class="vt-toggle-slider"></span>
                </label>
                <span style="margin-left: 10px;"><?php _e('Show close button on notifications', 'voxel-toolkit'); ?></span>
            </div>
        </div>

        <!-- Style Settings -->
        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Style Settings', 'voxel-toolkit'); ?></h4>

            <div style="display: flex; gap: 40px; align-items: flex-start;">
                <!-- Style Fields -->
                <div style="flex: 1; min-width: 280px;">
                    <div class="vt-field-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Background Color', 'voxel-toolkit'); ?></label>
                        <input type="color"
                               name="voxel_toolkit_options[social_proof][background_color]"
                               value="<?php echo esc_attr($bg_color); ?>"
                               style="width: 60px; height: 36px; padding: 2px;"
                               id="vt-sp-bg-color">
                    </div>

                    <div class="vt-field-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Text Color', 'voxel-toolkit'); ?></label>
                        <input type="color"
                               name="voxel_toolkit_options[social_proof][text_color]"
                               value="<?php echo esc_attr($text_color); ?>"
                               style="width: 60px; height: 36px; padding: 2px;"
                               id="vt-sp-text-color">
                    </div>

                    <div class="vt-field-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Border Radius (px)', 'voxel-toolkit'); ?></label>
                        <input type="number"
                               name="voxel_toolkit_options[social_proof][border_radius]"
                               value="<?php echo esc_attr($border_radius); ?>"
                               min="0"
                               max="50"
                               style="width: 100px;"
                               id="vt-sp-border-radius">
                    </div>

                    <div class="vt-field-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Avatar Size (px)', 'voxel-toolkit'); ?></label>
                        <input type="number"
                               name="voxel_toolkit_options[social_proof][avatar_size]"
                               value="<?php echo esc_attr($avatar_size); ?>"
                               min="24"
                               max="80"
                               style="width: 100px;"
                               id="vt-sp-avatar-size">
                    </div>

                    <div class="vt-field-group">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Default Avatar', 'voxel-toolkit'); ?></label>
                        <p class="vt-field-description" style="margin-bottom: 10px;"><?php _e('Used when a user has no avatar or for Activity Boost notifications.', 'voxel-toolkit'); ?></p>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <?php if ($default_avatar): ?>
                                <img id="vt-sp-default-avatar-preview" src="<?php echo esc_url($default_avatar); ?>" alt="" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
                            <?php endif; ?>
                            <div>
                                <input type="hidden"
                                       name="voxel_toolkit_options[social_proof][default_avatar]"
                                       id="vt-sp-default-avatar"
                                       value="<?php echo esc_url($default_avatar); ?>">
                                <button type="button" class="button" id="vt-sp-upload-avatar">
                                    <?php echo $default_avatar ? __('Change Image', 'voxel-toolkit') : __('Upload Image', 'voxel-toolkit'); ?>
                                </button>
                                <?php if ($default_avatar): ?>
                                    <button type="button" class="button" id="vt-sp-remove-avatar">
                                        <?php _e('Remove', 'voxel-toolkit'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Preview -->
                <div style="width: 360px; flex-shrink: 0;">
                    <label style="display: block; margin-bottom: 10px; font-weight: 500;"><?php _e('Live Preview', 'voxel-toolkit'); ?></label>
                    <div id="vt-sp-preview-container" style="background: #f0f0f1; border-radius: 8px; padding: 20px; min-height: 100px;">
                        <div id="vt-sp-preview-toast" style="
                            display: flex;
                            align-items: center;
                            gap: 12px;
                            padding: 14px 16px;
                            background-color: <?php echo esc_attr($bg_color); ?>;
                            border-radius: <?php echo esc_attr($border_radius); ?>px;
                            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                            max-width: 100%;
                            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                            position: relative;
                        ">
                            <div id="vt-sp-preview-avatar" style="
                                flex-shrink: 0;
                                width: <?php echo esc_attr($avatar_size); ?>px;
                                height: <?php echo esc_attr($avatar_size); ?>px;
                            ">
                                <img src="<?php echo esc_url(get_avatar_url(get_current_user_id(), array('size' => 96))); ?>"
                                     alt=""
                                     style="
                                        width: <?php echo esc_attr($avatar_size); ?>px;
                                        height: <?php echo esc_attr($avatar_size); ?>px;
                                        border-radius: 50%;
                                        object-fit: cover;
                                     ">
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div id="vt-sp-preview-message" style="
                                    font-size: 14px;
                                    font-weight: 500;
                                    color: <?php echo esc_attr($text_color); ?>;
                                    line-height: 1.4;
                                    margin-bottom: 4px;
                                "><?php echo esc_html(wp_get_current_user()->display_name); ?> <?php _e('just booked a service', 'voxel-toolkit'); ?></div>
                                <div id="vt-sp-preview-time" style="
                                    font-size: 12px;
                                    color: <?php echo esc_attr($text_color); ?>;
                                    opacity: 0.7;
                                    line-height: 1.3;
                                "><?php _e('2 minutes ago', 'voxel-toolkit'); ?></div>
                            </div>
                            <button type="button" id="vt-sp-preview-close" style="
                                position: absolute;
                                top: 8px;
                                right: 8px;
                                width: 20px;
                                height: 20px;
                                padding: 0;
                                background: none;
                                border: none;
                                cursor: pointer;
                                opacity: 0.5;
                                display: <?php echo $show_close_button ? 'flex' : 'none'; ?>;
                                align-items: center;
                                justify-content: center;
                                color: <?php echo esc_attr($text_color); ?>;
                            ">
                                <span class="dashicons dashicons-no-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
                            </button>
                        </div>

                        <div style="margin-top: 15px; text-align: center;">
                            <button type="button" id="vt-sp-preview-animation" class="button button-secondary">
                                <span class="dashicons dashicons-controls-play" style="margin-top: 3px;"></span>
                                <?php _e('Preview Animation', 'voxel-toolkit'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Media uploader for default avatar
            var mediaUploader;

            $('#vt-sp-upload-avatar').on('click', function(e) {
                e.preventDefault();

                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                mediaUploader = wp.media({
                    title: '<?php echo esc_js(__('Select Default Avatar', 'voxel-toolkit')); ?>',
                    button: { text: '<?php echo esc_js(__('Use as Avatar', 'voxel-toolkit')); ?>' },
                    multiple: false,
                    library: { type: 'image' }
                });

                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

                    $('#vt-sp-default-avatar').val(url);

                    // Update or create preview image
                    if ($('#vt-sp-default-avatar-preview').length) {
                        $('#vt-sp-default-avatar-preview').attr('src', url);
                    } else {
                        $('<img id="vt-sp-default-avatar-preview" src="' + url + '" alt="" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">').insertBefore($('#vt-sp-upload-avatar').parent());
                    }

                    // Show remove button or create it
                    if ($('#vt-sp-remove-avatar').length) {
                        $('#vt-sp-remove-avatar').show();
                    } else {
                        $('<button type="button" class="button" id="vt-sp-remove-avatar"><?php echo esc_js(__('Remove', 'voxel-toolkit')); ?></button>').insertAfter($('#vt-sp-upload-avatar'));
                    }

                    $('#vt-sp-upload-avatar').text('<?php echo esc_js(__('Change Image', 'voxel-toolkit')); ?>');

                    // Update live preview
                    $('#vt-sp-preview-avatar img').attr('src', url);
                });

                mediaUploader.open();
            });

            $(document).on('click', '#vt-sp-remove-avatar', function(e) {
                e.preventDefault();
                $('#vt-sp-default-avatar').val('');
                $('#vt-sp-default-avatar-preview').remove();
                $(this).remove();
                $('#vt-sp-upload-avatar').text('<?php echo esc_js(__('Upload Image', 'voxel-toolkit')); ?>');

                // Reset live preview to current user avatar
                var currentUserAvatar = '<?php echo esc_url(get_avatar_url(get_current_user_id(), array('size' => 96))); ?>';
                $('#vt-sp-preview-avatar img').attr('src', currentUserAvatar);
            });

            // Live Preview Updates
            var $toast = $('#vt-sp-preview-toast');
            var $avatar = $('#vt-sp-preview-avatar');
            var $avatarImg = $avatar.find('img');
            var $message = $('#vt-sp-preview-message');
            var $time = $('#vt-sp-preview-time');
            var $close = $('#vt-sp-preview-close');

            // Background color
            $('#vt-sp-bg-color').on('input', function() {
                $toast.css('background-color', $(this).val());
            });

            // Text color
            $('#vt-sp-text-color').on('input', function() {
                var color = $(this).val();
                $message.css('color', color);
                $time.css('color', color);
                $close.css('color', color);
            });

            // Border radius
            $('#vt-sp-border-radius').on('input', function() {
                $toast.css('border-radius', $(this).val() + 'px');
            });

            // Avatar size
            $('#vt-sp-avatar-size').on('input', function() {
                var size = $(this).val() + 'px';
                $avatar.css({ width: size, height: size });
                $avatarImg.css({ width: size, height: size });
            });

            // Show close button
            $('input[name="voxel_toolkit_options[social_proof][show_close_button]"]').on('change', function() {
                $close.css('display', $(this).is(':checked') ? 'flex' : 'none');
            });

            // Animation preview
            $('#vt-sp-preview-animation').on('click', function() {
                var animation = $('select[name="voxel_toolkit_options[social_proof][animation]"]').val();

                if (animation === 'slide') {
                    $toast.css({ transform: 'translateY(50px)', opacity: 0 });
                    setTimeout(function() {
                        $toast.css({ transition: 'all 0.4s ease', transform: 'translateY(0)', opacity: 1 });
                    }, 50);
                } else {
                    $toast.css({ transform: 'scale(0.9)', opacity: 0 });
                    setTimeout(function() {
                        $toast.css({ transition: 'all 0.4s ease', transform: 'scale(1)', opacity: 1 });
                    }, 50);
                }

                // Reset after animation
                setTimeout(function() {
                    $toast.css({ transition: '', transform: '', opacity: '' });
                }, 500);
            });
        });
        </script>

        <!-- Event Settings -->
        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Event Types', 'voxel-toolkit'); ?></h4>
            <p class="vt-field-description" style="margin-bottom: 15px;">
                <?php _e('Enable the event types you want to display as social proof notifications. Configure the message template and display options for each.', 'voxel-toolkit'); ?>
            </p>

            <?php
            // Group events by category
            $categories = array(
                'membership' => __('Membership', 'voxel-toolkit'),
                'posts' => __('Posts & Listings', 'voxel-toolkit'),
                'bookings' => __('Bookings', 'voxel-toolkit'),
                'orders' => __('Orders', 'voxel-toolkit'),
                'reviews' => __('Reviews', 'voxel-toolkit'),
                'social' => __('Social', 'voxel-toolkit'),
                'promotions' => __('Promotions', 'voxel-toolkit'),
            );

            foreach ($categories as $cat_key => $cat_label):
                $cat_events = array_filter($available_events, function($e) use ($cat_key) {
                    return isset($e['category']) && $e['category'] === $cat_key;
                });

                if (empty($cat_events)) continue;
            ?>
            <div class="vt-event-category" style="margin-bottom: 20px;">
                <h5 style="margin: 0 0 10px 0; font-size: 14px; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px;">
                    <?php echo esc_html($cat_label); ?>
                </h5>

                <?php foreach ($cat_events as $event_key => $event_config):
                    $event_settings = isset($events_settings[$event_key]) ? $events_settings[$event_key] : array();
                    $event_enabled = !empty($event_settings['enabled']);
                    $event_message = isset($event_settings['message_template']) ? $event_settings['message_template'] : $event_config['default_message'];
                    $event_show_avatar = isset($event_settings['show_avatar']) ? $event_settings['show_avatar'] : true;
                    $event_show_link = isset($event_settings['show_link']) ? $event_settings['show_link'] : true;
                    $event_show_time = isset($event_settings['show_time']) ? $event_settings['show_time'] : true;
                    $safe_key = esc_attr(str_replace(array('/', ':'), '_', $event_key));
                ?>
                <div class="vt-event-item" style="background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-bottom: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <label class="vt-toggle">
                            <input type="checkbox"
                                   name="voxel_toolkit_options[social_proof][events][<?php echo esc_attr($event_key); ?>][enabled]"
                                   value="1"
                                   <?php checked($event_enabled); ?>>
                            <span class="vt-toggle-slider"></span>
                        </label>
                        <strong style="font-size: 13px;"><?php echo esc_html($event_config['label']); ?></strong>
                        <code style="font-size: 11px; color: #666; background: #e5e7eb; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($event_key); ?></code>
                    </div>

                    <div style="margin-left: 50px;">
                        <div class="vt-field-group" style="margin-bottom: 8px;">
                            <label style="display: block; margin-bottom: 4px; font-size: 12px; color: #666;"><?php _e('Message Template', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[social_proof][events][<?php echo esc_attr($event_key); ?>][message_template]"
                                   value="<?php echo esc_attr($event_message); ?>"
                                   placeholder="<?php echo esc_attr($event_config['default_message']); ?>"
                                   style="width: 100%; max-width: 400px; font-size: 13px;">
                            <p style="margin: 4px 0 0; font-size: 11px; color: #666;"><?php _e('Use: {user}, {post}, {time}', 'voxel-toolkit'); ?></p>
                        </div>

                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                            <label style="font-size: 12px; cursor: pointer;">
                                <input type="checkbox"
                                       name="voxel_toolkit_options[social_proof][events][<?php echo esc_attr($event_key); ?>][show_avatar]"
                                       value="1"
                                       <?php checked($event_show_avatar); ?>
                                       style="margin-right: 4px;">
                                <?php _e('Show avatar', 'voxel-toolkit'); ?>
                            </label>
                            <label style="font-size: 12px; cursor: pointer;">
                                <input type="checkbox"
                                       name="voxel_toolkit_options[social_proof][events][<?php echo esc_attr($event_key); ?>][show_link]"
                                       value="1"
                                       <?php checked($event_show_link); ?>
                                       style="margin-right: 4px;">
                                <?php _e('Clickable link', 'voxel-toolkit'); ?>
                            </label>
                            <label style="font-size: 12px; cursor: pointer;">
                                <input type="checkbox"
                                       name="voxel_toolkit_options[social_proof][events][<?php echo esc_attr($event_key); ?>][show_time]"
                                       value="1"
                                       <?php checked($event_show_time); ?>
                                       style="margin-right: 4px;">
                                <?php _e('Show time ago', 'voxel-toolkit'); ?>
                            </label>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Activity Boost -->
        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Activity Boost', 'voxel-toolkit'); ?></h4>
            <p class="vt-field-description" style="margin-bottom: 15px;">
                <?php _e('Generate additional notifications to boost activity appearance. Useful for new sites or during slow periods.', 'voxel-toolkit'); ?>
            </p>

            <div class="vt-field-group" style="margin-bottom: 15px;">
                <label class="vt-toggle">
                    <input type="checkbox"
                           name="voxel_toolkit_options[social_proof][boost_enabled]"
                           value="1"
                           <?php checked($boost_enabled); ?>>
                    <span class="vt-toggle-slider"></span>
                </label>
                <span style="margin-left: 10px;"><?php _e('Enable Activity Boost', 'voxel-toolkit'); ?></span>
            </div>

            <div class="vt-field-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Mode', 'voxel-toolkit'); ?></label>
                <select name="voxel_toolkit_options[social_proof][boost_mode]" style="width: 250px;">
                    <option value="fill_gaps" <?php selected($boost_mode, 'fill_gaps'); ?>><?php _e('Fill gaps (when no real events)', 'voxel-toolkit'); ?></option>
                    <option value="mixed" <?php selected($boost_mode, 'mixed'); ?>><?php _e('Mixed with real events', 'voxel-toolkit'); ?></option>
                    <option value="boost_only" <?php selected($boost_mode, 'boost_only'); ?>><?php _e('Boost only (ignore real events)', 'voxel-toolkit'); ?></option>
                </select>
                <p class="vt-field-description"><?php _e('Choose when to show boosted notifications.', 'voxel-toolkit'); ?></p>
            </div>

            <div class="vt-field-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Names Pool', 'voxel-toolkit'); ?></label>
                <textarea name="voxel_toolkit_options[social_proof][boost_names]"
                          rows="5"
                          style="width: 100%; max-width: 400px; font-size: 13px;"
                          placeholder="<?php esc_attr_e('One name per line', 'voxel-toolkit'); ?>"><?php echo esc_textarea($boost_names); ?></textarea>
                <p class="vt-field-description"><?php _e('Enter names to use in boosted notifications, one per line.', 'voxel-toolkit'); ?></p>
            </div>

            <div class="vt-field-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Listings Pool', 'voxel-toolkit'); ?></label>
                <textarea name="voxel_toolkit_options[social_proof][boost_listings]"
                          rows="5"
                          style="width: 100%; max-width: 400px; font-size: 13px;"
                          placeholder="<?php esc_attr_e('One listing name per line', 'voxel-toolkit'); ?>"><?php echo esc_textarea($boost_listings); ?></textarea>
                <p class="vt-field-description"><?php _e('Enter listing/post names to use in boosted notifications, one per line.', 'voxel-toolkit'); ?></p>
            </div>

            <div class="vt-field-group">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Message Templates', 'voxel-toolkit'); ?></label>
                <p class="vt-field-description" style="margin-bottom: 10px;"><?php _e('Use {name} and {listing} placeholders.', 'voxel-toolkit'); ?></p>

                <div style="display: flex; flex-direction: column; gap: 10px; max-width: 400px;">
                    <div>
                        <label style="display: block; margin-bottom: 4px; font-size: 12px; color: #666;"><?php _e('Booking message', 'voxel-toolkit'); ?></label>
                        <input type="text"
                               name="voxel_toolkit_options[social_proof][boost_messages][booking]"
                               value="<?php echo esc_attr($boost_messages['booking'] ?? '{name} just booked {listing}'); ?>"
                               style="width: 100%; font-size: 13px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 4px; font-size: 12px; color: #666;"><?php _e('Signup message', 'voxel-toolkit'); ?></label>
                        <input type="text"
                               name="voxel_toolkit_options[social_proof][boost_messages][signup]"
                               value="<?php echo esc_attr($boost_messages['signup'] ?? '{name} just joined'); ?>"
                               style="width: 100%; font-size: 13px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 4px; font-size: 12px; color: #666;"><?php _e('Review message', 'voxel-toolkit'); ?></label>
                        <input type="text"
                               name="voxel_toolkit_options[social_proof][boost_messages][review]"
                               value="<?php echo esc_attr($boost_messages['review'] ?? '{name} left a review on {listing}'); ?>"
                               style="width: 100%; font-size: 13px;">
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Enhanced TinyMCE Editor settings
     *
     * @param array $settings Current settings
     */
    public function render_enhanced_editor_settings($settings) {
        ?>
        <div class="vt-info-box">
            <?php _e('Enhances Voxel\'s "WP Editor Advanced" mode with additional formatting options. These features are automatically added to any texteditor field set to use Advanced controls.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Features Added', 'voxel-toolkit'); ?></h4>
            <div style="background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px;">
                <ul style="margin: 0; padding-left: 20px; color: #374151;">
                    <li style="margin-bottom: 8px;"><strong><?php _e('Add Media', 'voxel-toolkit'); ?></strong> - <?php _e('Upload and insert images, videos, audio, and files from the WordPress media library', 'voxel-toolkit'); ?></li>
                    <li style="margin-bottom: 8px;"><strong><?php _e('Text Color', 'voxel-toolkit'); ?></strong> - <?php _e('Change the color of selected text', 'voxel-toolkit'); ?></li>
                    <li style="margin-bottom: 8px;"><strong><?php _e('Background Color', 'voxel-toolkit'); ?></strong> - <?php _e('Add background color/highlight to text', 'voxel-toolkit'); ?></li>
                    <li><strong><?php _e('Character Map', 'voxel-toolkit'); ?></strong> - <?php _e('Insert special characters and symbols', 'voxel-toolkit'); ?></li>
                </ul>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('How It Works', 'voxel-toolkit'); ?></h4>
            <div style="background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px;">
                <ol style="margin: 0; padding-left: 20px; color: #374151;">
                    <li style="margin-bottom: 8px;"><?php _e('Go to your Post Type settings in Voxel', 'voxel-toolkit'); ?></li>
                    <li style="margin-bottom: 8px;"><?php _e('Edit a texteditor field (like Description)', 'voxel-toolkit'); ?></li>
                    <li style="margin-bottom: 8px;"><?php _e('Set "Editor type" to "WP Editor — Advanced controls"', 'voxel-toolkit'); ?></li>
                    <li><?php _e('The enhanced toolbar will automatically appear in the Create Post form', 'voxel-toolkit'); ?></li>
                </ol>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Note', 'voxel-toolkit'); ?></h4>
            <p style="color: #6b7280; margin: 0;">
                <?php _e('This enhancement only affects fields set to "WP Editor — Advanced controls". Fields using "Plain text" or "Basic controls" remain unchanged.', 'voxel-toolkit'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render Enhanced Post Relation settings
     *
     * @param array $settings Current settings
     */
    public function render_enhanced_post_relation_settings($settings) {
        $post_type_settings = isset($settings['post_type_settings']) ? $settings['post_type_settings'] : array();

        // Get Voxel post types (only show Voxel-managed post types)
        $voxel_post_types = array();
        if (class_exists('\Voxel\Post_Type')) {
            $voxel_post_types = \Voxel\Post_Type::get_voxel_types();
        }

        // Export each post type's dynamic data groups separately
        // We'll inject these into JavaScript since the global exporter may have already run
        $post_type_groups = array();
        $post_type_export_keys = array();

        if (class_exists('\Voxel\Dynamic_Data\Exporter')) {
            foreach ($voxel_post_types as $pt) {
                $pt_key = $pt->get_key();
                $export_key = 'post_type:' . $pt_key;
                $post_type_export_keys[$pt_key] = $export_key;

                // Create a fresh exporter for each post type to get its specific groups
                $exporter = new \Voxel\Dynamic_Data\Exporter();
                $exporter->add_group_by_key('post', $pt_key);
                $exports = $exporter->export();

                // Store the exported group data
                if (isset($exports['groups'][$export_key])) {
                    $post_type_groups[$export_key] = $exports['groups'][$export_key];
                }
            }
        }
        ?>
        <div class="vt-info-box">
            <?php _e('Customize how posts are displayed in Post Relation field dropdown selectors. Use Voxel dynamic tags to show additional information like location, category, or custom fields. The template will be rendered in the context of each related post.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-tip-box" style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
            <strong style="display: block; margin-bottom: 8px; color: #166534;"><?php _e('Example Templates:', 'voxel-toolkit'); ?></strong>
            <ul style="margin: 0 0 0 16px; list-style: disc; color: #15803d;">
                <li><code style="background: #dcfce7; padding: 2px 6px; border-radius: 4px;">@post(:title) - @post(location.address)</code> - <?php _e('Title with address', 'voxel-toolkit'); ?></li>
                <li><code style="background: #dcfce7; padding: 2px 6px; border-radius: 4px;">@post(:title) (@post(category))</code> - <?php _e('Title with category', 'voxel-toolkit'); ?></li>
                <li><code style="background: #dcfce7; padding: 2px 6px; border-radius: 4px;">@post(:title) - @post(price)</code> - <?php _e('Title with price', 'voxel-toolkit'); ?></li>
            </ul>
        </div>

        <?php if (empty($voxel_post_types)): ?>
            <div class="notice notice-warning" style="margin: 0 0 20px;">
                <p><?php _e('No Voxel post types found. Please make sure you have created post types in Voxel.', 'voxel-toolkit'); ?></p>
            </div>
        <?php endif; ?>

        <?php foreach ($voxel_post_types as $post_type): ?>
            <?php
            $pt_key = $post_type->get_key();
            $enabled = isset($post_type_settings[$pt_key]['enabled']) ? $post_type_settings[$pt_key]['enabled'] : false;
            $template = isset($post_type_settings[$pt_key]['display_template']) ? $post_type_settings[$pt_key]['display_template'] : '';
            ?>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 16px;">
                <div style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox"
                               name="voxel_toolkit_options[enhanced_post_relation][post_type_settings][<?php echo esc_attr($pt_key); ?>][enabled]"
                               id="epr_<?php echo esc_attr($pt_key); ?>_enabled"
                               value="1"
                               <?php checked($enabled); ?>
                               onchange="toggleEprSection('<?php echo esc_js($pt_key); ?>')"
                               style="margin-right: 12px; transform: scale(1.2);">
                        <div>
                            <strong style="display: block; color: #1e1e1e; font-size: 15px;">
                                <?php echo esc_html($post_type->get_label()); ?>
                            </strong>
                            <span style="color: #64748b; font-size: 12px;">
                                (<?php echo esc_html($pt_key); ?>)
                            </span>
                        </div>
                    </label>
                </div>

                <div id="epr_<?php echo esc_attr($pt_key); ?>_settings" style="margin-left: 28px; <?php echo $enabled ? '' : 'display: none;'; ?>">
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #374151;">
                        <?php _e('Display Template', 'voxel-toolkit'); ?>
                    </label>
                    <div class="vt-dtag-field" onclick="VT_openDtagPicker(this, '<?php echo esc_js($pt_key); ?>')" style="cursor: pointer;">
                        <input type="text"
                               name="voxel_toolkit_options[enhanced_post_relation][post_type_settings][<?php echo esc_attr($pt_key); ?>][display_template]"
                               value="<?php echo esc_attr($template); ?>"
                               placeholder="<?php esc_attr_e('Click to open dynamic tag picker...', 'voxel-toolkit'); ?>"
                               readonly
                               style="width: 100%; max-width: 500px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; cursor: pointer;" />
                    </div>
                    <p style="margin: 8px 0 0; color: #6b7280; font-size: 12px;">
                        <?php _e('Click the field to open the dynamic tag picker with all fields for this post type.', 'voxel-toolkit'); ?>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>

        <script type="text/javascript">
        // Map post type keys to their export keys (post_type:key format)
        window.VT_PostTypeExportKeys = <?php echo wp_json_encode($post_type_export_keys); ?>;

        // Post type specific group data exported from PHP
        window.VT_PostTypeGroups = <?php echo wp_json_encode($post_type_groups); ?>;

        // Inject our post type groups into Dynamic_Data_Store when it's available
        (function() {
            function injectGroups() {
                if (window.Dynamic_Data_Store && window.Dynamic_Data_Store.groups && window.VT_PostTypeGroups) {
                    for (var key in window.VT_PostTypeGroups) {
                        if (window.VT_PostTypeGroups.hasOwnProperty(key)) {
                            window.Dynamic_Data_Store.groups[key] = window.VT_PostTypeGroups[key];
                        }
                    }
                }
            }

            // Try immediately
            injectGroups();

            // Also try after DOM is ready (in case Dynamic_Data_Store is set up later)
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', injectGroups);
            }

            // And after window load as final fallback
            window.addEventListener('load', injectGroups);
        })();

        function toggleEprSection(postType) {
            var checkbox = document.getElementById('epr_' + postType + '_enabled');
            var settings = document.getElementById('epr_' + postType + '_settings');
            if (settings) {
                settings.style.display = checkbox.checked ? 'block' : 'none';
            }
        }

        function VT_openDtagPicker(container, postTypeKey) {
            // Check if Voxel_Dynamic is available
            if (typeof Voxel_Dynamic === 'undefined') {
                alert('<?php echo esc_js(__('Voxel dynamic tag picker is not available. Please make sure Voxel theme is active.', 'voxel-toolkit')); ?>');
                return;
            }

            var input = container.querySelector('input');
            if (!input) return;

            // Get the export key for this post type (format: post_type:key)
            var groupKey = window.VT_PostTypeExportKeys && window.VT_PostTypeExportKeys[postTypeKey]
                ? window.VT_PostTypeExportKeys[postTypeKey]
                : 'post_type:' + postTypeKey;

            // Ensure groups are injected before opening picker
            if (window.Dynamic_Data_Store && window.Dynamic_Data_Store.groups && window.VT_PostTypeGroups) {
                for (var key in window.VT_PostTypeGroups) {
                    if (window.VT_PostTypeGroups.hasOwnProperty(key)) {
                        window.Dynamic_Data_Store.groups[key] = window.VT_PostTypeGroups[key];
                    }
                }
            }

            // Build groups object with labels (required format for Voxel_Dynamic.edit)
            // The 'type' property must match the key in Dynamic_Data_Store.groups
            var groups = {};

            if (window.Dynamic_Data_Store && window.Dynamic_Data_Store.groups && window.Dynamic_Data_Store.groups[groupKey]) {
                // Get a nice label from the post type key
                var postTypeLabel = postTypeKey.charAt(0).toUpperCase() + postTypeKey.slice(1);
                groups['post'] = {
                    label: postTypeLabel,
                    type: groupKey  // e.g., 'post_type:place' - must match key in Dynamic_Data_Store.groups
                };
                groups['site'] = {
                    label: 'Site',
                    type: 'site'
                };
            } else {
                // Fallback to default groups
                groups = Voxel_Dynamic.getDefaultGroups ? Voxel_Dynamic.getDefaultGroups() : {
                    'post': { label: 'Post', type: 'simple-post' },
                    'site': { label: 'Site', type: 'site' },
                    'user': { label: 'User', type: 'user' }
                };
                console.warn('VT: Post type group not found for', postTypeKey, '- using defaults');
            }

            // Open Voxel's dynamic tag editor
            Voxel_Dynamic.edit(input.value, {
                groups: groups,
                onSave: function(newValue) {
                    input.value = newValue;
                }
            });
        }
        </script>
        <?php
    }

    /**
     * Render Team Members settings
     */
    public function render_team_members_settings($settings) {
        $login_page_id = isset($settings['login_page_id']) ? absint($settings['login_page_id']) : 0;

        // Get all published pages for dropdown
        $pages = get_pages(array(
            'post_status' => 'publish',
            'sort_column' => 'post_title',
            'sort_order' => 'ASC'
        ));
        ?>
        <div class="vt-info-box">
            <?php _e('Allow post authors to invite team members by email who can then edit the post. Add the "Team Members (VT)" field to your post types to enable this feature.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Login/Register Page', 'voxel-toolkit'); ?></h4>
            <p class="vt-field-description" style="margin-bottom: 15px;">
                <?php _e('Select the page where users should be redirected to log in or register when accepting an invite. This should be your Voxel login/register template page.', 'voxel-toolkit'); ?>
            </p>
            <div class="vt-field-group">
                <select name="voxel_toolkit_options[team_members][login_page_id]" style="width: 100%; max-width: 400px;">
                    <option value=""><?php _e('— Use WordPress default login —', 'voxel-toolkit'); ?></option>
                    <?php foreach ($pages as $page): ?>
                        <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($login_page_id, $page->ID); ?>>
                            <?php echo esc_html($page->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="vt-settings-section" style="margin-top: 20px;">
            <h4 class="vt-settings-section-title"><?php _e('Tips', 'voxel-toolkit'); ?></h4>
            <ul style="list-style: disc; margin-left: 20px; margin-top: 10px;">
                <li style="margin-bottom: 8px;">
                    <strong><?php _e('Post Field:', 'voxel-toolkit'); ?></strong>
                    <?php _e('Add the "Team Members (VT)" field to your post type in Voxel > Post Types > [Your Post Type] > Fields.', 'voxel-toolkit'); ?>
                </li>
                <li style="margin-bottom: 8px;">
                    <strong><?php _e('Visibility Condition:', 'voxel-toolkit'); ?></strong>
                    <?php _e('Use "User is team member of current post" in Elementor visibility conditions to show/hide elements for team members such as delete post, or specific fields you want them to be able to edit.', 'voxel-toolkit'); ?>
                </li>
                <li style="margin-bottom: 8px;">
                    <strong><?php _e('App Events:', 'voxel-toolkit'); ?></strong>
                    <?php _e('Three app events are available under "Voxel Toolkit" category: Team Member Invited, Accepted, and Declined.', 'voxel-toolkit'); ?>
                </li>
                <li style="margin-bottom: 8px;">
                    <strong><?php _e('Author Filter:', 'voxel-toolkit'); ?></strong>
                    <?php _e('The Author filter is automatically extended to include posts where a user is a team member.', 'voxel-toolkit'); ?>
                </li>
            </ul>
        </div>
        <?php
    }

    /**
     * Render settings for Link Management function
     *
     * @param array $settings Current settings
     */
    public function render_link_management_settings($settings) {
        $site_name = get_bloginfo('name');
        $default_title = sprintf(__("You're leaving %s", 'voxel-toolkit'), $site_name);
        $default_message = __('You are about to leave this website and visit an external site. We are not responsible for the content or privacy practices of external sites.', 'voxel-toolkit');

        // Default colors
        $defaults = array(
            'overlay_color' => 'rgba(0,0,0,0.6)',
            'modal_bg' => '#ffffff',
            'title_color' => '#1e293b',
            'message_color' => '#64748b',
            'icon_bg' => '#fef3c7',
            'icon_color' => '#d97706',
            'continue_bg' => '#3b82f6',
            'continue_text_color' => '#ffffff',
            'cancel_bg' => '#f1f5f9',
            'cancel_text_color' => '#475569',
        );

        // Get current values with defaults
        $overlay_color = isset($settings['overlay_color']) ? $settings['overlay_color'] : $defaults['overlay_color'];
        $modal_bg = isset($settings['modal_bg']) ? $settings['modal_bg'] : $defaults['modal_bg'];
        $title_color = isset($settings['title_color']) ? $settings['title_color'] : $defaults['title_color'];
        $message_color = isset($settings['message_color']) ? $settings['message_color'] : $defaults['message_color'];
        $icon_bg = isset($settings['icon_bg']) ? $settings['icon_bg'] : $defaults['icon_bg'];
        $icon_color = isset($settings['icon_color']) ? $settings['icon_color'] : $defaults['icon_color'];
        $continue_bg = isset($settings['continue_bg']) ? $settings['continue_bg'] : $defaults['continue_bg'];
        $continue_text_color = isset($settings['continue_text_color']) ? $settings['continue_text_color'] : $defaults['continue_text_color'];
        $cancel_bg = isset($settings['cancel_bg']) ? $settings['cancel_bg'] : $defaults['cancel_bg'];
        $cancel_text_color = isset($settings['cancel_text_color']) ? $settings['cancel_text_color'] : $defaults['cancel_text_color'];

        $title_val = isset($settings['title']) && !empty($settings['title']) ? $settings['title'] : $default_title;
        $message_val = isset($settings['message']) && !empty($settings['message']) ? $settings['message'] : $default_message;
        $continue_text_val = isset($settings['continue_text']) && !empty($settings['continue_text']) ? $settings['continue_text'] : __('Continue', 'voxel-toolkit');
        $cancel_text_val = isset($settings['cancel_text']) && !empty($settings['cancel_text']) ? $settings['cancel_text'] : __('Go Back', 'voxel-toolkit');
        ?>
        <div class="vt-info-box">
            <?php _e('Show a warning modal when users click external links. Similar to government websites that warn users when leaving the site.', 'voxel-toolkit'); ?>
        </div>

        <div style="display: flex; gap: 40px; flex-wrap: wrap;">
            <!-- Left column: Settings -->
            <div style="flex: 1; min-width: 400px;">

                <div class="vt-settings-section">
                    <h4 class="vt-settings-section-title"><?php _e('Modal Content', 'voxel-toolkit'); ?></h4>

                    <div class="vt-field-group" style="margin-bottom: 20px;">
                        <label class="vt-field-label"><?php _e('Warning Title', 'voxel-toolkit'); ?></label>
                        <input type="text"
                               name="voxel_toolkit_options[link_management][title]"
                               id="lm-title"
                               value="<?php echo esc_attr(isset($settings['title']) ? $settings['title'] : ''); ?>"
                               placeholder="<?php echo esc_attr($default_title); ?>"
                               class="vt-text-input"
                               style="width: 100%; max-width: 500px;" />
                    </div>

                    <div class="vt-field-group" style="margin-bottom: 20px;">
                        <label class="vt-field-label"><?php _e('Warning Message', 'voxel-toolkit'); ?></label>
                        <textarea name="voxel_toolkit_options[link_management][message]"
                                  id="lm-message"
                                  rows="3"
                                  placeholder="<?php echo esc_attr($default_message); ?>"
                                  class="vt-textarea"
                                  style="width: 100%; max-width: 600px;"><?php echo esc_textarea(isset($settings['message']) ? $settings['message'] : ''); ?></textarea>
                    </div>

                    <div class="vt-checkbox-inline" style="gap: 32px; margin-bottom: 20px;">
                        <div class="vt-field-group">
                            <label class="vt-field-label"><?php _e('Continue Button Text', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[link_management][continue_text]"
                                   id="lm-continue-text"
                                   value="<?php echo esc_attr(isset($settings['continue_text']) ? $settings['continue_text'] : ''); ?>"
                                   placeholder="<?php esc_attr_e('Continue', 'voxel-toolkit'); ?>"
                                   class="vt-text-input"
                                   style="width: 180px;" />
                        </div>
                        <div class="vt-field-group">
                            <label class="vt-field-label"><?php _e('Cancel Button Text', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[link_management][cancel_text]"
                                   id="lm-cancel-text"
                                   value="<?php echo esc_attr(isset($settings['cancel_text']) ? $settings['cancel_text'] : ''); ?>"
                                   placeholder="<?php esc_attr_e('Go Back', 'voxel-toolkit'); ?>"
                                   class="vt-text-input"
                                   style="width: 180px;" />
                        </div>
                    </div>

                    <div class="vt-checkbox-list">
                        <label class="vt-checkbox-item">
                            <input type="checkbox"
                                   name="voxel_toolkit_options[link_management][show_url]"
                                   id="lm-show-url"
                                   value="1"
                                   <?php checked(!empty($settings['show_url'])); ?> />
                            <div class="vt-checkbox-item-content">
                                <span class="vt-checkbox-item-label"><?php _e('Show external URL in modal', 'voxel-toolkit'); ?></span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="vt-settings-section">
                    <h4 class="vt-settings-section-title"><?php _e('Modal Styling', 'voxel-toolkit'); ?></h4>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 20px;">
                        <div class="vt-field-group">
                            <label class="vt-field-label"><?php _e('Modal Background', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[link_management][modal_bg]"
                                   id="lm-modal-bg"
                                   value="<?php echo esc_attr($modal_bg); ?>"
                                   placeholder="#ffffff"
                                   class="vt-text-input lm-color-input"
                                   style="width: 120px; font-family: monospace;" />
                        </div>
                        <div class="vt-field-group">
                            <label class="vt-field-label"><?php _e('Title Color', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[link_management][title_color]"
                                   id="lm-title-color"
                                   value="<?php echo esc_attr($title_color); ?>"
                                   placeholder="#1e293b"
                                   class="vt-text-input lm-color-input"
                                   style="width: 120px; font-family: monospace;" />
                        </div>
                        <div class="vt-field-group">
                            <label class="vt-field-label"><?php _e('Message Color', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[link_management][message_color]"
                                   id="lm-message-color"
                                   value="<?php echo esc_attr($message_color); ?>"
                                   placeholder="#64748b"
                                   class="vt-text-input lm-color-input"
                                   style="width: 120px; font-family: monospace;" />
                        </div>
                    </div>

                    <h5 style="margin: 20px 0 12px; font-size: 13px; color: #666;"><?php _e('Icon', 'voxel-toolkit'); ?></h5>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 20px;">
                        <div class="vt-field-group">
                            <label class="vt-field-label"><?php _e('Icon Background', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[link_management][icon_bg]"
                                   id="lm-icon-bg"
                                   value="<?php echo esc_attr($icon_bg); ?>"
                                   placeholder="#fef3c7"
                                   class="vt-text-input lm-color-input"
                                   style="width: 120px; font-family: monospace;" />
                        </div>
                        <div class="vt-field-group">
                            <label class="vt-field-label"><?php _e('Icon Color', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[link_management][icon_color]"
                                   id="lm-icon-color"
                                   value="<?php echo esc_attr($icon_color); ?>"
                                   placeholder="#d97706"
                                   class="vt-text-input lm-color-input"
                                   style="width: 120px; font-family: monospace;" />
                        </div>
                    </div>

                    <h5 style="margin: 20px 0 12px; font-size: 13px; color: #666;"><?php _e('Continue Button', 'voxel-toolkit'); ?></h5>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 20px;">
                        <div class="vt-field-group">
                            <label class="vt-field-label"><?php _e('Background', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[link_management][continue_bg]"
                                   id="lm-continue-bg"
                                   value="<?php echo esc_attr($continue_bg); ?>"
                                   placeholder="#3b82f6"
                                   class="vt-text-input lm-color-input"
                                   style="width: 120px; font-family: monospace;" />
                        </div>
                        <div class="vt-field-group">
                            <label class="vt-field-label"><?php _e('Text Color', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[link_management][continue_text_color]"
                                   id="lm-continue-text-color"
                                   value="<?php echo esc_attr($continue_text_color); ?>"
                                   placeholder="#ffffff"
                                   class="vt-text-input lm-color-input"
                                   style="width: 120px; font-family: monospace;" />
                        </div>
                    </div>

                    <h5 style="margin: 20px 0 12px; font-size: 13px; color: #666;"><?php _e('Cancel Button', 'voxel-toolkit'); ?></h5>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 20px;">
                        <div class="vt-field-group">
                            <label class="vt-field-label"><?php _e('Background', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[link_management][cancel_bg]"
                                   id="lm-cancel-bg"
                                   value="<?php echo esc_attr($cancel_bg); ?>"
                                   placeholder="#f1f5f9"
                                   class="vt-text-input lm-color-input"
                                   style="width: 120px; font-family: monospace;" />
                        </div>
                        <div class="vt-field-group">
                            <label class="vt-field-label"><?php _e('Text Color', 'voxel-toolkit'); ?></label>
                            <input type="text"
                                   name="voxel_toolkit_options[link_management][cancel_text_color]"
                                   id="lm-cancel-text-color"
                                   value="<?php echo esc_attr($cancel_text_color); ?>"
                                   placeholder="#475569"
                                   class="vt-text-input lm-color-input"
                                   style="width: 120px; font-family: monospace;" />
                        </div>
                    </div>
                </div>

                <div class="vt-settings-section">
                    <h4 class="vt-settings-section-title"><?php _e('Domain Whitelist', 'voxel-toolkit'); ?></h4>
                    <p class="vt-field-description" style="margin-bottom: 10px;">
                        <?php _e('External domains that should NOT trigger the warning modal.', 'voxel-toolkit'); ?>
                    </p>
                    <div class="vt-field-group">
                        <textarea name="voxel_toolkit_options[link_management][whitelist]"
                                  rows="4"
                                  placeholder="google.com&#10;facebook.com&#10;twitter.com"
                                  class="vt-textarea"
                                  style="width: 100%; max-width: 400px; font-family: monospace;"><?php echo esc_textarea(isset($settings['whitelist']) ? $settings['whitelist'] : ''); ?></textarea>
                        <p class="vt-field-description"><?php _e('One domain per line. Subdomains included automatically.', 'voxel-toolkit'); ?></p>
                    </div>
                </div>

                <div class="vt-settings-section">
                    <h4 class="vt-settings-section-title"><?php _e('Exclusion Selectors', 'voxel-toolkit'); ?></h4>
                    <div class="vt-field-group">
                        <textarea name="voxel_toolkit_options[link_management][exclusion_selectors]"
                                  rows="3"
                                  placeholder=".no-warning&#10;[data-external='safe']"
                                  class="vt-textarea"
                                  style="width: 100%; max-width: 400px; font-family: monospace;"><?php echo esc_textarea(isset($settings['exclusion_selectors']) ? $settings['exclusion_selectors'] : ''); ?></textarea>
                        <p class="vt-field-description"><?php _e('CSS selectors for links to exclude. One per line.', 'voxel-toolkit'); ?></p>
                    </div>
                </div>

            </div>

            <!-- Right column: Preview -->
            <div style="flex: 0 0 420px;">
                <div class="vt-settings-section" style="position: sticky; top: 50px;">
                    <h4 class="vt-settings-section-title"><?php _e('Live Preview', 'voxel-toolkit'); ?></h4>
                    <div id="lm-preview-container" style="background: #1a1a1a; border-radius: 8px; padding: 30px; min-height: 350px; display: flex; align-items: center; justify-content: center;">
                        <div id="lm-preview-modal" style="background: <?php echo esc_attr($modal_bg); ?>; border-radius: 12px; padding: 32px; max-width: 360px; width: 100%; text-align: center; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
                            <div id="lm-preview-icon" style="width: 64px; height: 64px; background: <?php echo esc_attr($icon_bg); ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: <?php echo esc_attr($icon_color); ?>;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </div>
                            <h3 id="lm-preview-title" style="margin: 0 0 12px; font-size: 18px; font-weight: 600; color: <?php echo esc_attr($title_color); ?>; line-height: 1.3;"><?php echo esc_html($title_val); ?></h3>
                            <p id="lm-preview-message" style="margin: 0 0 16px; font-size: 14px; color: <?php echo esc_attr($message_color); ?>; line-height: 1.5;"><?php echo esc_html($message_val); ?></p>
                            <p id="lm-preview-url" style="margin: 0 0 20px; padding: 8px 12px; background: #f1f5f9; border-radius: 6px; font-size: 12px; color: #475569; font-family: monospace; display: <?php echo !empty($settings['show_url']) ? 'block' : 'none'; ?>;">https://example.com/page</p>
                            <div style="display: flex; gap: 12px; justify-content: center;">
                                <button type="button" id="lm-preview-cancel" style="padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; flex: 1; background: <?php echo esc_attr($cancel_bg); ?>; color: <?php echo esc_attr($cancel_text_color); ?>;"><?php echo esc_html($cancel_text_val); ?></button>
                                <button type="button" id="lm-preview-continue" style="padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; flex: 1; background: <?php echo esc_attr($continue_bg); ?>; color: <?php echo esc_attr($continue_text_color); ?>;"><?php echo esc_html($continue_text_val); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var defaultTitle = <?php echo json_encode($default_title); ?>;
            var defaultMessage = <?php echo json_encode($default_message); ?>;
            var defaultContinue = <?php echo json_encode(__('Continue', 'voxel-toolkit')); ?>;
            var defaultCancel = <?php echo json_encode(__('Go Back', 'voxel-toolkit')); ?>;

            function updatePreview() {
                // Text content
                var title = $('#lm-title').val() || defaultTitle;
                var message = $('#lm-message').val() || defaultMessage;
                var continueText = $('#lm-continue-text').val() || defaultContinue;
                var cancelText = $('#lm-cancel-text').val() || defaultCancel;

                $('#lm-preview-title').text(title);
                $('#lm-preview-message').text(message);
                $('#lm-preview-continue').text(continueText);
                $('#lm-preview-cancel').text(cancelText);

                // Show URL
                $('#lm-preview-url').toggle($('#lm-show-url').is(':checked'));

                // Colors
                $('#lm-preview-modal').css('background', $('#lm-modal-bg').val() || '#ffffff');
                $('#lm-preview-title').css('color', $('#lm-title-color').val() || '#1e293b');
                $('#lm-preview-message').css('color', $('#lm-message-color').val() || '#64748b');
                $('#lm-preview-icon').css({
                    'background': $('#lm-icon-bg').val() || '#fef3c7',
                    'color': $('#lm-icon-color').val() || '#d97706'
                });
                $('#lm-preview-continue').css({
                    'background': $('#lm-continue-bg').val() || '#3b82f6',
                    'color': $('#lm-continue-text-color').val() || '#ffffff'
                });
                $('#lm-preview-cancel').css({
                    'background': $('#lm-cancel-bg').val() || '#f1f5f9',
                    'color': $('#lm-cancel-text-color').val() || '#475569'
                });
            }

            // Bind events
            $('#lm-title, #lm-message, #lm-continue-text, #lm-cancel-text').on('input keyup', updatePreview);
            $('#lm-show-url').on('change', updatePreview);
            $('.lm-color-input').on('input keyup paste', function() {
                setTimeout(updatePreview, 50);
            });
        });
        </script>
        <?php
    }

    /**
     * Render settings for Saved Search function
     *
     * @param array $settings Current settings
     */
    public function render_saved_search_settings($settings) {
        // Get all pages for the dropdown
        $pages = get_pages(array(
            'sort_column' => 'post_title',
            'sort_order' => 'ASC',
        ));
        $saved_searches_page = isset($settings['saved_searches_page']) ? absint($settings['saved_searches_page']) : 0;
        ?>
        <div class="vt-info-box">
            <?php _e('Allow users to save search filters and receive notifications when new posts match their saved searches.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Features', 'voxel-toolkit'); ?></h4>
            <ul class="vt-feature-list">
                <li><?php _e('Save button added to Voxel search form widget', 'voxel-toolkit'); ?></li>
                <li><?php _e('Saved Search (VT) widget displays user\'s saved searches', 'voxel-toolkit'); ?></li>
                <li><?php _e('App Events for notifications when new posts match saved searches', 'voxel-toolkit'); ?></li>
                <li><?php _e('Users can enable/disable notifications per saved search', 'voxel-toolkit'); ?></li>
                <li><?php _e('Optional title for saved searches', 'voxel-toolkit'); ?></li>
            </ul>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Configuration', 'voxel-toolkit'); ?></h4>

            <div class="vt-field-group">
                <label class="vt-field-label"><?php _e('Saved Searches Page', 'voxel-toolkit'); ?></label>
                <select name="voxel_toolkit_options[saved_search][saved_searches_page]" class="vt-select" style="width: 300px;">
                    <option value=""><?php _e('— Select Page —', 'voxel-toolkit'); ?></option>
                    <?php foreach ($pages as $page) : ?>
                        <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($saved_searches_page, $page->ID); ?>>
                            <?php echo esc_html($page->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="vt-field-description">
                    <?php _e('Select the page containing the Saved Search (VT) widget. This link will be shown in success messages.', 'voxel-toolkit'); ?>
                </p>
            </div>

            <?php
            $expiration = isset($settings['expiration']) ? $settings['expiration'] : 'never';
            $expiration_options = array(
                'never' => __('Never (no expiration)', 'voxel-toolkit'),
                '7' => __('7 days', 'voxel-toolkit'),
                '14' => __('14 days', 'voxel-toolkit'),
                '30' => __('30 days', 'voxel-toolkit'),
                '90' => __('90 days', 'voxel-toolkit'),
                '180' => __('6 months', 'voxel-toolkit'),
                '365' => __('1 year', 'voxel-toolkit'),
            );
            ?>
            <div class="vt-field-group">
                <label class="vt-field-label"><?php _e('Auto-delete Saved Searches', 'voxel-toolkit'); ?></label>
                <select name="voxel_toolkit_options[saved_search][expiration]" class="vt-select" style="width: 300px;">
                    <?php foreach ($expiration_options as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($expiration, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="vt-field-description">
                    <?php _e('Automatically delete saved searches after this period. Expired searches are cleaned up daily.', 'voxel-toolkit'); ?>
                </p>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Email Batching', 'voxel-toolkit'); ?></h4>

            <div class="vt-info-box" style="margin-bottom: 15px;">
                <?php _e('Queue email notifications and send them in batches to reduce server load. In-app and SMS notifications will still be sent immediately.', 'voxel-toolkit'); ?>
            </div>

            <?php
            $email_batching_enabled = !empty($settings['email_batching_enabled']);
            $email_batch_size = isset($settings['email_batch_size']) ? intval($settings['email_batch_size']) : 25;
            $email_batch_interval = isset($settings['email_batch_interval']) ? intval($settings['email_batch_interval']) : 5;

            $batch_size_options = array(
                '10' => __('10 emails', 'voxel-toolkit'),
                '25' => __('25 emails (recommended)', 'voxel-toolkit'),
                '50' => __('50 emails', 'voxel-toolkit'),
                '100' => __('100 emails', 'voxel-toolkit'),
            );

            $interval_options = array(
                '1' => __('Every 1 minute', 'voxel-toolkit'),
                '5' => __('Every 5 minutes (recommended)', 'voxel-toolkit'),
                '10' => __('Every 10 minutes', 'voxel-toolkit'),
                '15' => __('Every 15 minutes', 'voxel-toolkit'),
                '30' => __('Every 30 minutes', 'voxel-toolkit'),
            );
            ?>

            <div class="vt-field-group">
                <label class="vt-checkbox-label">
                    <input type="checkbox"
                           name="voxel_toolkit_options[saved_search][email_batching_enabled]"
                           value="1"
                           <?php checked($email_batching_enabled); ?>>
                    <?php _e('Enable email batching', 'voxel-toolkit'); ?>
                </label>
                <p class="vt-field-description">
                    <?php _e('When enabled, email notifications are queued and sent in batches via WordPress cron.', 'voxel-toolkit'); ?>
                </p>
            </div>

            <div class="vt-field-group">
                <label class="vt-field-label"><?php _e('Batch Size', 'voxel-toolkit'); ?></label>
                <select name="voxel_toolkit_options[saved_search][email_batch_size]" class="vt-select" style="width: 300px;">
                    <?php foreach ($batch_size_options as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($email_batch_size, intval($value)); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="vt-field-description">
                    <?php _e('Number of emails to send per batch.', 'voxel-toolkit'); ?>
                </p>
            </div>

            <div class="vt-field-group">
                <label class="vt-field-label"><?php _e('Processing Interval', 'voxel-toolkit'); ?></label>
                <select name="voxel_toolkit_options[saved_search][email_batch_interval]" class="vt-select" style="width: 300px;">
                    <?php foreach ($interval_options as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($email_batch_interval, intval($value)); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="vt-field-description">
                    <?php _e('How often to process the email queue.', 'voxel-toolkit'); ?>
                </p>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Usage Instructions', 'voxel-toolkit'); ?></h4>
            <ol class="vt-feature-list">
                <li><?php _e('Add the Saved Search (VT) widget to a page where users can manage their searches', 'voxel-toolkit'); ?></li>
                <li><?php _e('Configure the Search Form widget\'s "Saved Search (VT)" section to enable the save button', 'voxel-toolkit'); ?></li>
                <li><?php _e('Set up App Events in Voxel to send notifications for new matching posts', 'voxel-toolkit'); ?></li>
            </ol>
        </div>
        <?php
    }

    /**
     * Render Timeline Filters settings
     */
    public function render_timeline_filters_settings($settings) {
        $enable_unanswered = isset($settings['enable_unanswered']) ? (bool) $settings['enable_unanswered'] : false;
        $unanswered_label = isset($settings['unanswered_label']) ? $settings['unanswered_label'] : '';
        ?>
        <div class="vt-info-box">
            <?php _e('Add custom filtering options to Voxel Timeline widgets. These filters appear in the ordering dropdown alongside native Voxel options.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Unanswered Filter', 'voxel-toolkit'); ?></h4>

            <div class="vt-field-group">
                <label class="vt-checkbox-label">
                    <input type="checkbox"
                           name="voxel_toolkit_options[timeline_filters][enable_unanswered]"
                           value="1"
                           <?php checked($enable_unanswered); ?>>
                    <?php _e('Enable "Unanswered" ordering option', 'voxel-toolkit'); ?>
                </label>
                <p class="vt-field-description">
                    <?php _e('Shows timeline posts that have no replies (reply_count = 0), sorted by newest first. Useful for community sites where users want to find and help with unanswered questions.', 'voxel-toolkit'); ?>
                </p>
            </div>

            <div class="vt-field-group">
                <label class="vt-field-label"><?php _e('Button Label', 'voxel-toolkit'); ?></label>
                <input type="text"
                       name="voxel_toolkit_options[timeline_filters][unanswered_label]"
                       value="<?php echo esc_attr($unanswered_label); ?>"
                       class="vt-input"
                       placeholder="<?php esc_attr_e('Unanswered', 'voxel-toolkit'); ?>"
                       style="width: 300px;">
                <p class="vt-field-description">
                    <?php _e('Custom label for the Unanswered filter button. Leave empty to use the default "Unanswered" label.', 'voxel-toolkit'); ?>
                </p>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('How It Works', 'voxel-toolkit'); ?></h4>
            <ul class="vt-feature-list">
                <li><?php _e('Adds custom ordering options to all Timeline widgets on your site', 'voxel-toolkit'); ?></li>
                <li><?php _e('Works with all Timeline modes: Reviews, Wall, Timeline, Global Feed, and User Feed', 'voxel-toolkit'); ?></li>
                <li><?php _e('Uses custom AJAX endpoint to query posts with zero replies', 'voxel-toolkit'); ?></li>
                <li><?php _e('Results are sorted by newest first within the unanswered filter', 'voxel-toolkit'); ?></li>
            </ul>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Future Filters', 'voxel-toolkit'); ?></h4>
            <p class="vt-field-description">
                <?php _e('Additional timeline filters can be added here in future updates. Have a suggestion? Let us know!', 'voxel-toolkit'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Get default summary prompt
     */
    public function get_default_summary_prompt() {
        return __(
            "Summarize the following discussion replies concisely in 2-3 sentences.\n" .
            "Focus on:\n" .
            "- Main points and key takeaways\n" .
            "- Any consensus or common themes\n" .
            "- Notable disagreements or different perspectives\n\n" .
            "Keep the tone neutral and informative. Do not include usernames.\n\n" .
            "Replies:\n{{replies}}\n\n" .
            "Summary:",
            'voxel-toolkit'
        );
    }

    /**
     * Render Timeline Reply Summary settings
     */
    public function render_timeline_reply_summary_settings($settings) {
        $ai_provider = isset($settings['ai_provider']) ? $settings['ai_provider'] : 'openai';
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
        $reply_threshold = isset($settings['reply_threshold']) ? absint($settings['reply_threshold']) : 3;
        $max_summary_length = isset($settings['max_summary_length']) ? absint($settings['max_summary_length']) : 300;
        $label_text = isset($settings['label_text']) ? $settings['label_text'] : '';
        $prompt_template = isset($settings['prompt_template']) ? $settings['prompt_template'] : '';
        $feeds = isset($settings['feeds']) ? (array) $settings['feeds'] : array('post_reviews', 'post_wall', 'post_timeline');
        ?>
        <div class="vt-info-box">
            <?php _e('Generate AI-powered TL;DR summaries of timeline post replies. Similar to Reddit\'s summary bot, this feature helps users quickly understand long discussions.', 'voxel-toolkit'); ?>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('AI Provider Settings', 'voxel-toolkit'); ?></h4>

            <div class="vt-field-group">
                <label class="vt-field-label"><?php _e('AI Provider', 'voxel-toolkit'); ?></label>
                <select name="voxel_toolkit_options[timeline_reply_summary][ai_provider]" class="vt-select" style="width: 300px;">
                    <option value="openai" <?php selected($ai_provider, 'openai'); ?>><?php _e('OpenAI (GPT-4o-mini)', 'voxel-toolkit'); ?></option>
                    <option value="anthropic" <?php selected($ai_provider, 'anthropic'); ?>><?php _e('Anthropic (Claude 3 Haiku)', 'voxel-toolkit'); ?></option>
                </select>
                <p class="vt-field-description">
                    <?php _e('Choose which AI service to use for generating summaries. Both use cost-effective models optimized for summarization.', 'voxel-toolkit'); ?>
                </p>
            </div>

            <div class="vt-field-group">
                <label class="vt-field-label"><?php _e('API Key', 'voxel-toolkit'); ?></label>
                <input type="password"
                       name="voxel_toolkit_options[timeline_reply_summary][api_key]"
                       value="<?php echo esc_attr($api_key); ?>"
                       class="vt-input"
                       placeholder="<?php esc_attr_e('Enter your API key', 'voxel-toolkit'); ?>"
                       style="width: 400px;"
                       autocomplete="off">
                <p class="vt-field-description">
                    <?php _e('Your API key for the selected provider. Get your key from:', 'voxel-toolkit'); ?>
                    <br>
                    <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI API Keys</a> |
                    <a href="https://console.anthropic.com/settings/keys" target="_blank">Anthropic API Keys</a>
                </p>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Summary Settings', 'voxel-toolkit'); ?></h4>

            <div class="vt-field-group">
                <label class="vt-field-label"><?php _e('Reply Count Threshold', 'voxel-toolkit'); ?></label>
                <input type="number"
                       name="voxel_toolkit_options[timeline_reply_summary][reply_threshold]"
                       value="<?php echo esc_attr($reply_threshold); ?>"
                       class="vt-input"
                       min="1"
                       max="100"
                       style="width: 100px;">
                <p class="vt-field-description">
                    <?php _e('Minimum number of replies required before showing the summary. Recommended: 3 or more.', 'voxel-toolkit'); ?>
                </p>
            </div>

            <div class="vt-field-group">
                <label class="vt-field-label"><?php _e('Max Summary Length (tokens)', 'voxel-toolkit'); ?></label>
                <input type="number"
                       name="voxel_toolkit_options[timeline_reply_summary][max_summary_length]"
                       value="<?php echo esc_attr($max_summary_length); ?>"
                       class="vt-input"
                       min="50"
                       max="1000"
                       style="width: 100px;">
                <p class="vt-field-description">
                    <?php _e('Maximum length of the generated summary in tokens (~4 characters per token). Default: 300.', 'voxel-toolkit'); ?>
                </p>
            </div>

            <div class="vt-field-group">
                <label class="vt-field-label"><?php _e('Summary Label', 'voxel-toolkit'); ?></label>
                <input type="text"
                       name="voxel_toolkit_options[timeline_reply_summary][label_text]"
                       value="<?php echo esc_attr($label_text); ?>"
                       class="vt-input"
                       placeholder="<?php esc_attr_e('TL;DR', 'voxel-toolkit'); ?>"
                       style="width: 200px;">
                <p class="vt-field-description">
                    <?php _e('Label displayed on the summary header. Default: "TL;DR"', 'voxel-toolkit'); ?>
                </p>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Enabled Feeds', 'voxel-toolkit'); ?></h4>
            <p class="vt-field-description" style="margin-bottom: 10px;">
                <?php _e('Select which timeline feeds should show reply summaries:', 'voxel-toolkit'); ?>
            </p>

            <div class="vt-field-group">
                <label class="vt-checkbox-label">
                    <input type="checkbox"
                           name="voxel_toolkit_options[timeline_reply_summary][feeds][]"
                           value="post_reviews"
                           <?php checked(in_array('post_reviews', $feeds)); ?>>
                    <?php _e('Post Reviews', 'voxel-toolkit'); ?>
                </label>
            </div>

            <div class="vt-field-group">
                <label class="vt-checkbox-label">
                    <input type="checkbox"
                           name="voxel_toolkit_options[timeline_reply_summary][feeds][]"
                           value="post_wall"
                           <?php checked(in_array('post_wall', $feeds)); ?>>
                    <?php _e('Post Wall', 'voxel-toolkit'); ?>
                </label>
            </div>

            <div class="vt-field-group">
                <label class="vt-checkbox-label">
                    <input type="checkbox"
                           name="voxel_toolkit_options[timeline_reply_summary][feeds][]"
                           value="post_timeline"
                           <?php checked(in_array('post_timeline', $feeds)); ?>>
                    <?php _e('Post Timeline', 'voxel-toolkit'); ?>
                </label>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('Custom Prompt Template', 'voxel-toolkit'); ?></h4>
            <p class="vt-field-description" style="margin-bottom: 10px;">
                <?php _e('Customize the prompt sent to the AI. Use {{replies}} as a placeholder for the reply content.', 'voxel-toolkit'); ?>
            </p>

            <div class="vt-field-group">
                <textarea name="voxel_toolkit_options[timeline_reply_summary][prompt_template]"
                          class="vt-textarea"
                          rows="8"
                          style="width: 100%; font-family: monospace;"
                          placeholder="<?php echo esc_attr($this->get_default_summary_prompt()); ?>"><?php echo esc_textarea($prompt_template); ?></textarea>
                <p class="vt-field-description">
                    <?php _e('Leave empty to use the default prompt. The {{replies}} placeholder will be replaced with the actual reply content formatted as a bulleted list.', 'voxel-toolkit'); ?>
                </p>
            </div>
        </div>

        <div class="vt-settings-section">
            <h4 class="vt-settings-section-title"><?php _e('How It Works', 'voxel-toolkit'); ?></h4>
            <ul class="vt-feature-list">
                <li><?php _e('Summaries are generated when a post reaches the reply threshold', 'voxel-toolkit'); ?></li>
                <li><?php _e('Summaries are cached in the database and only regenerated when new replies are added', 'voxel-toolkit'); ?></li>
                <li><?php _e('Users can expand/collapse the summary by clicking the TL;DR header', 'voxel-toolkit'); ?></li>
                <li><?php _e('Summaries are loaded on-demand to minimize API costs', 'voxel-toolkit'); ?></li>
            </ul>
        </div>
        <?php
    }

    /**
     * Render AI Settings
     */
    public function render_ai_settings($settings) {
        $provider = isset($settings['provider']) ? $settings['provider'] : 'openai';
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
        $openai_model = isset($settings['openai_model']) ? $settings['openai_model'] : 'gpt-4o-mini';
        $anthropic_model = isset($settings['anthropic_model']) ? $settings['anthropic_model'] : 'claude-3-5-haiku-20241022';
        ?>

        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('AI Provider', 'voxel-toolkit'); ?></th>
                <td>
                    <select name="voxel_toolkit_options[ai_settings][provider]" class="vt-select" style="width: 300px;">
                        <option value="openai" <?php selected($provider, 'openai'); ?>><?php _e('OpenAI', 'voxel-toolkit'); ?></option>
                        <option value="anthropic" <?php selected($provider, 'anthropic'); ?>><?php _e('Anthropic (Claude)', 'voxel-toolkit'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('API Key', 'voxel-toolkit'); ?></th>
                <td>
                    <input type="password"
                           name="voxel_toolkit_options[ai_settings][api_key]"
                           value="<?php echo esc_attr($api_key); ?>"
                           class="regular-text"
                           style="width: 300px;"
                           placeholder="<?php _e('Enter your API key', 'voxel-toolkit'); ?>">
                    <p class="description"><?php _e('Your API key for the selected provider.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('OpenAI Model', 'voxel-toolkit'); ?></th>
                <td>
                    <select name="voxel_toolkit_options[ai_settings][openai_model]" class="vt-select" style="width: 300px;">
                        <option value="gpt-4o-mini" <?php selected($openai_model, 'gpt-4o-mini'); ?>>GPT-4o Mini (Recommended)</option>
                        <option value="gpt-4o" <?php selected($openai_model, 'gpt-4o'); ?>>GPT-4o</option>
                        <option value="gpt-4.1" <?php selected($openai_model, 'gpt-4.1'); ?>>GPT-4.1</option>
                        <option value="gpt-4.1-mini" <?php selected($openai_model, 'gpt-4.1-mini'); ?>>GPT-4.1 Mini</option>
                        <option value="o1" <?php selected($openai_model, 'o1'); ?>>o1</option>
                        <option value="o1-mini" <?php selected($openai_model, 'o1-mini'); ?>>o1-mini</option>
                        <option value="o3-mini" <?php selected($openai_model, 'o3-mini'); ?>>o3-mini</option>
                        <option value="gpt-4-turbo" <?php selected($openai_model, 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                    </select>
                    <p class="description"><?php _e('Used when OpenAI is selected as provider.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Anthropic Model', 'voxel-toolkit'); ?></th>
                <td>
                    <select name="voxel_toolkit_options[ai_settings][anthropic_model]" class="vt-select" style="width: 300px;">
                        <option value="claude-3-5-haiku-20241022" <?php selected($anthropic_model, 'claude-3-5-haiku-20241022'); ?>>Claude 3.5 Haiku (Recommended)</option>
                        <option value="claude-3-5-sonnet-20241022" <?php selected($anthropic_model, 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet</option>
                        <option value="claude-sonnet-4-20250514" <?php selected($anthropic_model, 'claude-sonnet-4-20250514'); ?>>Claude Sonnet 4</option>
                        <option value="claude-opus-4-20250514" <?php selected($anthropic_model, 'claude-opus-4-20250514'); ?>>Claude Opus 4</option>
                        <option value="claude-3-opus-20240229" <?php selected($anthropic_model, 'claude-3-opus-20240229'); ?>>Claude 3 Opus</option>
                    </select>
                    <p class="description"><?php _e('Used when Anthropic is selected as provider.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
        </table>

        <div class="vt-settings-info" style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-radius: 6px; border-left: 4px solid #0073aa;">
            <strong><?php _e('About AI Settings:', 'voxel-toolkit'); ?></strong>
            <p style="margin: 10px 0 0 0;"><?php _e('This is the central AI configuration used by all AI-powered features in Voxel Toolkit, including AI Post Summary. Configure your API key here once and all AI features will use it.', 'voxel-toolkit'); ?></p>
        </div>
        <?php
    }

    /**
     * Render AI Post Summary settings
     */
    public function render_ai_post_summary_settings($settings) {
        // Check if AI Settings is configured
        $ai_configured = class_exists('Voxel_Toolkit_AI_Settings') && Voxel_Toolkit_AI_Settings::instance()->is_configured();

        if (!$ai_configured) {
            ?>
            <div class="vt-settings-notice vt-settings-notice-warning">
                <span class="dashicons dashicons-warning"></span>
                <?php _e('AI Settings must be configured first. Please enable and configure AI Settings before using this feature.', 'voxel-toolkit'); ?>
            </div>
            <?php
            return;
        }

        // Get Voxel post types
        $voxel_post_types = array();
        if (class_exists('\Voxel\Post_Type')) {
            $voxel_post_types = \Voxel\Post_Type::get_voxel_types();
        }

        $enabled_post_types = isset($settings['post_types']) ? (array) $settings['post_types'] : array();
        $max_tokens = isset($settings['max_tokens']) ? absint($settings['max_tokens']) : 300;
        $prompt_template = isset($settings['prompt_template']) ? $settings['prompt_template'] : '';
        ?>

        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable for Post Types', 'voxel-toolkit'); ?></th>
                <td>
                    <fieldset>
                        <?php if (!empty($voxel_post_types)): ?>
                            <?php foreach ($voxel_post_types as $pt): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox"
                                           name="voxel_toolkit_options[ai_post_summary][post_types][]"
                                           value="<?php echo esc_attr($pt->get_key()); ?>"
                                           <?php checked(in_array($pt->get_key(), $enabled_post_types)); ?>>
                                    <?php echo esc_html($pt->get_label()); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="description"><?php _e('No Voxel post types found.', 'voxel-toolkit'); ?></p>
                        <?php endif; ?>
                    </fieldset>
                    <p class="description"><?php _e('Select which post types should auto-generate AI summaries.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Max Tokens', 'voxel-toolkit'); ?></th>
                <td>
                    <input type="number"
                           name="voxel_toolkit_options[ai_post_summary][max_tokens]"
                           value="<?php echo esc_attr($max_tokens); ?>"
                           min="50"
                           max="1000"
                           class="small-text">
                    <p class="description"><?php _e('Maximum tokens for the summary (50-1000). Higher = longer summaries.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Prompt Template', 'voxel-toolkit'); ?></th>
                <td>
                    <textarea name="voxel_toolkit_options[ai_post_summary][prompt_template]"
                              rows="6"
                              class="large-text code"
                              placeholder="<?php echo esc_attr($this->get_default_ai_post_summary_prompt()); ?>"><?php echo esc_textarea($prompt_template); ?></textarea>
                    <p class="description"><?php _e('Custom prompt template. Use {{post_data}} as placeholder for post content. Leave empty for default.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
        </table>

        <div class="vt-settings-info" style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-radius: 6px; border-left: 4px solid #0073aa;">
            <strong><?php _e('How to use:', 'voxel-toolkit'); ?></strong>
            <ul style="margin: 10px 0 0 20px; list-style: disc;">
                <li><?php _e('Summaries are auto-generated when posts are published or updated.', 'voxel-toolkit'); ?></li>
                <li><?php _e('Access the summary using the dynamic tag: <code>@post(ai.summary)</code>', 'voxel-toolkit'); ?></li>
                <li><?php _e('Summaries are stored in post meta and only regenerated when content changes.', 'voxel-toolkit'); ?></li>
            </ul>
        </div>

        <div class="vt-settings-section" style="margin-top: 30px;">
            <h4><?php _e('Bulk Generate Summaries', 'voxel-toolkit'); ?></h4>
            <p class="description"><?php _e('Generate AI summaries for existing posts that don\'t have one yet.', 'voxel-toolkit'); ?></p>
            <button type="button" id="vt-bulk-generate-summaries" class="button button-secondary" style="margin-top: 10px;">
                <?php _e('Generate Summaries', 'voxel-toolkit'); ?>
            </button>
            <span id="vt-bulk-generate-status" style="margin-left: 10px;"></span>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#vt-bulk-generate-summaries').on('click', function() {
                var $btn = $(this);
                var $status = $('#vt-bulk-generate-status');
                var isRunning = false;

                if (isRunning) return;
                isRunning = true;

                $btn.prop('disabled', true).text('<?php _e('Processing...', 'voxel-toolkit'); ?>');
                $status.html('<span style="color: #666;"><?php _e('Starting bulk generation...', 'voxel-toolkit'); ?></span>');

                var offset = 0;
                var totalProcessed = 0;
                var totalGenerated = 0;

                function processBatch() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'vt_bulk_generate_ai_summaries',
                            nonce: '<?php echo wp_create_nonce('vt_ai_summary_nonce'); ?>',
                            offset: offset
                        },
                        success: function(response) {
                            if (response.success) {
                                totalProcessed += response.data.processed;
                                totalGenerated += response.data.generated;

                                if (response.data.has_more) {
                                    offset += response.data.processed;
                                    $status.html('<span style="color: #666;"><?php _e('Processed', 'voxel-toolkit'); ?> ' + totalProcessed + ' <?php _e('posts, generated', 'voxel-toolkit'); ?> ' + totalGenerated + ' <?php _e('summaries...', 'voxel-toolkit'); ?></span>');
                                    processBatch();
                                } else {
                                    isRunning = false;
                                    $btn.prop('disabled', false).text('<?php _e('Generate Summaries', 'voxel-toolkit'); ?>');
                                    $status.html('<span style="color: #46b450;"><?php _e('Complete! Processed', 'voxel-toolkit'); ?> ' + totalProcessed + ' <?php _e('posts, generated', 'voxel-toolkit'); ?> ' + totalGenerated + ' <?php _e('new summaries.', 'voxel-toolkit'); ?></span>');
                                }
                            } else {
                                isRunning = false;
                                $btn.prop('disabled', false).text('<?php _e('Generate Summaries', 'voxel-toolkit'); ?>');
                                $status.html('<span style="color: #dc2626;">' + (response.data.message || '<?php _e('Error occurred.', 'voxel-toolkit'); ?>') + '</span>');
                            }
                        },
                        error: function() {
                            isRunning = false;
                            $btn.prop('disabled', false).text('<?php _e('Generate Summaries', 'voxel-toolkit'); ?>');
                            $status.html('<span style="color: #dc2626;"><?php _e('Request failed. Please try again.', 'voxel-toolkit'); ?></span>');
                        }
                    });
                }

                processBatch();
            });
        });
        </script>
        <?php
    }

    /**
     * Get default AI Post Summary prompt
     */
    public function get_default_ai_post_summary_prompt() {
        return "Based on the following information about a listing/post, write a concise, engaging summary (2-3 sentences) that highlights the key features and value proposition. Focus on what makes this unique and appealing to potential visitors or customers.\n\n{{post_data}}\n\nSummary:";
    }

    /**
     * Render AI Bot settings
     */
    public function render_ai_bot_settings($settings) {
        // Check if AI Settings is configured
        $ai_configured = class_exists('Voxel_Toolkit_AI_Settings') && Voxel_Toolkit_AI_Settings::instance()->is_configured();

        if (!$ai_configured) {
            ?>
            <div class="vt-settings-notice vt-settings-notice-warning">
                <span class="dashicons dashicons-warning"></span>
                <?php _e('AI Settings must be configured first. Please enable and configure AI Settings before using this feature.', 'voxel-toolkit'); ?>
            </div>
            <?php
            return;
        }

        // Get Voxel post types
        $voxel_post_types = array();
        if (class_exists('\Voxel\Post_Type')) {
            $voxel_post_types = \Voxel\Post_Type::get_voxel_types();
        }

        // Get current settings with defaults
        $panel_position = isset($settings['panel_position']) ? $settings['panel_position'] : 'right';
        $access_control = isset($settings['access_control']) ? $settings['access_control'] : 'everyone';
        $enabled_post_types = isset($settings['post_types']) ? (array) $settings['post_types'] : array();
        $system_prompt = isset($settings['system_prompt']) ? $settings['system_prompt'] : '';
        $max_results = isset($settings['max_results']) ? absint($settings['max_results']) : 6;
        $welcome_message = isset($settings['welcome_message']) ? $settings['welcome_message'] : __('Hi! How can I help you find what you\'re looking for?', 'voxel-toolkit');
        $placeholder_text = isset($settings['placeholder_text']) ? $settings['placeholder_text'] : __('Ask me anything...', 'voxel-toolkit');
        $panel_title = isset($settings['panel_title']) ? $settings['panel_title'] : __('AI Assistant', 'voxel-toolkit');
        $conversation_memory = isset($settings['conversation_memory']) ? (bool) $settings['conversation_memory'] : true;
        $max_memory_messages = isset($settings['max_memory_messages']) ? absint($settings['max_memory_messages']) : 10;
        $rate_limit_enabled = isset($settings['rate_limit_enabled']) ? (bool) $settings['rate_limit_enabled'] : true;
        $rate_limit_requests = isset($settings['rate_limit_requests']) ? absint($settings['rate_limit_requests']) : 10;
        $rate_limit_period = isset($settings['rate_limit_period']) ? absint($settings['rate_limit_period']) : 60;
        ?>

        <div class="vt-settings-info" style="margin-bottom: 20px; padding: 15px; background: #f0f6fc; border-radius: 6px; border-left: 4px solid #0073aa;">
            <strong><?php _e('How to use:', 'voxel-toolkit'); ?></strong>
            <ul style="margin: 10px 0 0 20px; list-style: disc;">
                <li><?php _e('Add an "Actions (VX)" widget in Elementor and select "Open AI Assistant" as the action.', 'voxel-toolkit'); ?></li>
                <li><?php _e('Users can click the action to open the AI search panel.', 'voxel-toolkit'); ?></li>
                <li><?php _e('Users can ask natural language questions to find posts across your site.', 'voxel-toolkit'); ?></li>
                <li><?php _e('AI understands review/rating filters like "places with 4 stars" or "at least 1 review".', 'voxel-toolkit'); ?></li>
            </ul>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Panel Position', 'voxel-toolkit'); ?></th>
                <td>
                    <select name="voxel_toolkit_options[ai_bot][panel_position]">
                        <option value="right" <?php selected($panel_position, 'right'); ?>><?php _e('Right side', 'voxel-toolkit'); ?></option>
                        <option value="left" <?php selected($panel_position, 'left'); ?>><?php _e('Left side', 'voxel-toolkit'); ?></option>
                    </select>
                    <p class="description"><?php _e('Which side of the screen the AI panel slides in from.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Access Control', 'voxel-toolkit'); ?></th>
                <td>
                    <select name="voxel_toolkit_options[ai_bot][access_control]">
                        <option value="everyone" <?php selected($access_control, 'everyone'); ?>><?php _e('Everyone (guests & logged-in)', 'voxel-toolkit'); ?></option>
                        <option value="logged_in" <?php selected($access_control, 'logged_in'); ?>><?php _e('Logged-in users only', 'voxel-toolkit'); ?></option>
                    </select>
                    <p class="description"><?php _e('Who can use the AI search assistant.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Panel Title', 'voxel-toolkit'); ?></th>
                <td>
                    <input type="text"
                           name="voxel_toolkit_options[ai_bot][panel_title]"
                           value="<?php echo esc_attr($panel_title); ?>"
                           class="regular-text">
                    <p class="description"><?php _e('The title shown in the panel header.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Welcome Message', 'voxel-toolkit'); ?></th>
                <td>
                    <input type="text"
                           name="voxel_toolkit_options[ai_bot][welcome_message]"
                           value="<?php echo esc_attr($welcome_message); ?>"
                           class="large-text">
                    <p class="description"><?php _e('Initial greeting shown when the panel opens.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Placeholder Text', 'voxel-toolkit'); ?></th>
                <td>
                    <input type="text"
                           name="voxel_toolkit_options[ai_bot][placeholder_text]"
                           value="<?php echo esc_attr($placeholder_text); ?>"
                           class="regular-text">
                    <p class="description"><?php _e('Placeholder text in the message input field.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Searchable Post Types', 'voxel-toolkit'); ?></th>
                <td>
                    <fieldset>
                        <?php if (!empty($voxel_post_types)): ?>
                            <?php foreach ($voxel_post_types as $pt): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox"
                                           name="voxel_toolkit_options[ai_bot][post_types][]"
                                           value="<?php echo esc_attr($pt->get_key()); ?>"
                                           <?php checked(empty($enabled_post_types) || in_array($pt->get_key(), $enabled_post_types)); ?>>
                                    <?php echo esc_html($pt->get_label()); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="description"><?php _e('No Voxel post types found.', 'voxel-toolkit'); ?></p>
                        <?php endif; ?>
                    </fieldset>
                    <p class="description"><?php _e('Select which post types the AI can search. Leave empty for all.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Max Results', 'voxel-toolkit'); ?></th>
                <td>
                    <input type="number"
                           name="voxel_toolkit_options[ai_bot][max_results]"
                           value="<?php echo esc_attr($max_results); ?>"
                           min="1"
                           max="20"
                           class="small-text">
                    <p class="description"><?php _e('Maximum number of results to show per search (1-20).', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
        </table>

        <?php
        // Card templates section
        $card_templates = isset($settings['card_templates']) ? (array) $settings['card_templates'] : array();
        ?>
        <h3><?php _e('Card Templates', 'voxel-toolkit'); ?></h3>
        <p class="description" style="margin-bottom: 15px;"><?php _e('Choose which card template to display for each post type in AI search results.', 'voxel-toolkit'); ?></p>
        <table class="form-table">
            <?php if (!empty($voxel_post_types)): ?>
                <?php foreach ($voxel_post_types as $pt):
                    $pt_key = $pt->get_key();
                    $selected_template = isset($card_templates[$pt_key]) ? absint($card_templates[$pt_key]) : 0;

                    // Get default card template
                    $default_templates = $pt->get_templates();
                    $default_card_id = isset($default_templates['card']) ? $default_templates['card'] : 0;

                    // Get custom card templates
                    $custom_templates = array();
                    if (method_exists($pt, 'templates') || isset($pt->templates)) {
                        $pt_templates = $pt->templates->get_custom_templates();
                        $custom_templates = isset($pt_templates['card']) ? $pt_templates['card'] : array();
                    }
                    ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($pt->get_label()); ?></th>
                        <td>
                            <select name="voxel_toolkit_options[ai_bot][card_templates][<?php echo esc_attr($pt_key); ?>]">
                                <option value="0" <?php selected($selected_template, 0); ?>><?php _e('Default Card', 'voxel-toolkit'); ?></option>
                                <?php if (!empty($custom_templates)): ?>
                                    <?php foreach ($custom_templates as $template): ?>
                                        <option value="<?php echo esc_attr($template['id']); ?>" <?php selected($selected_template, $template['id']); ?>>
                                            <?php echo esc_html($template['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="2">
                        <p class="description"><?php _e('No Voxel post types found.', 'voxel-toolkit'); ?></p>
                    </td>
                </tr>
            <?php endif; ?>
        </table>

        <h3><?php _e('Conversation Settings', 'voxel-toolkit'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Conversation Memory', 'voxel-toolkit'); ?></th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="voxel_toolkit_options[ai_bot][conversation_memory]"
                               value="1"
                               <?php checked($conversation_memory); ?>>
                        <?php _e('Enable conversation memory', 'voxel-toolkit'); ?>
                    </label>
                    <p class="description"><?php _e('Allow follow-up questions like "show me cheaper ones" by remembering previous messages.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Max Memory Messages', 'voxel-toolkit'); ?></th>
                <td>
                    <input type="number"
                           name="voxel_toolkit_options[ai_bot][max_memory_messages]"
                           value="<?php echo esc_attr($max_memory_messages); ?>"
                           min="1"
                           max="50"
                           class="small-text">
                    <p class="description"><?php _e('Number of previous messages to include for context (1-50).', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php _e('Rate Limiting', 'voxel-toolkit'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Rate Limiting', 'voxel-toolkit'); ?></th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="voxel_toolkit_options[ai_bot][rate_limit_enabled]"
                               value="1"
                               <?php checked($rate_limit_enabled); ?>>
                        <?php _e('Limit number of requests per user', 'voxel-toolkit'); ?>
                    </label>
                    <p class="description"><?php _e('Helps control API costs and prevent abuse.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Requests Allowed', 'voxel-toolkit'); ?></th>
                <td>
                    <input type="number"
                           name="voxel_toolkit_options[ai_bot][rate_limit_requests]"
                           value="<?php echo esc_attr($rate_limit_requests); ?>"
                           min="1"
                           max="100"
                           class="small-text">
                    <span><?php _e('requests per', 'voxel-toolkit'); ?></span>
                    <input type="number"
                           name="voxel_toolkit_options[ai_bot][rate_limit_period]"
                           value="<?php echo esc_attr($rate_limit_period); ?>"
                           min="10"
                           max="3600"
                           class="small-text">
                    <span><?php _e('seconds', 'voxel-toolkit'); ?></span>
                    <p class="description"><?php _e('Default: 10 requests per 60 seconds.', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php _e('Custom System Prompt', 'voxel-toolkit'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('System Prompt', 'voxel-toolkit'); ?></th>
                <td>
                    <textarea name="voxel_toolkit_options[ai_bot][system_prompt]"
                              rows="10"
                              class="large-text code"
                              placeholder="<?php _e('Leave empty for default. Use {{site_name}}, {{schema}}, and {{max_results}} as placeholders.', 'voxel-toolkit'); ?>"><?php echo esc_textarea($system_prompt); ?></textarea>
                    <p class="description"><?php _e('Advanced: Customize the AI system prompt. Available placeholders: {{site_name}}, {{schema}}, {{max_results}}', 'voxel-toolkit'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

}