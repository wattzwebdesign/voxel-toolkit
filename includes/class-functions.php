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
        add_action('init', array($this, 'init'), 20);
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_voxel_toolkit_sync_submissions', array($this, 'ajax_sync_submissions'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        $this->register_functions();
        $this->register_widgets();
        $this->init_active_functions();
        $this->init_active_widgets();
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
            'password_visibility_toggle' => array(
                'name' => __('Password Visibility Toggle', 'voxel-toolkit'),
                'description' => __('Add eye icons to password fields to show/hide password text site-wide.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Password_Visibility_Toggle',
                'file' => 'functions/class-password-visibility-toggle.php',
                'settings_callback' => array($this, 'render_password_visibility_toggle_settings'),
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
            )
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
            ),
            'reading_time' => array(
                'name' => __('Reading Time', 'voxel-toolkit'),
                'description' => __('Display estimated reading time for posts with customizable prefix, postfix, and styling options.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Reading_Time_Widget',
                'file' => 'widgets/class-reading-time-widget.php',
                'settings_callback' => array($this, 'render_reading_time_widget_settings'),
            ),
            'table_of_contents' => array(
                'name' => __('Table of Contents', 'voxel-toolkit'),
                'description' => __('Display a table of contents showing all ui-step fields from any Voxel post type form with customizable styling.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Table_Of_Contents_Widget',
                'file' => 'widgets/class-table-of-contents-widget.php',
            ),
            'review_collection' => array(
                'name' => __('Review Collection', 'voxel-toolkit'),
                'description' => __('Display a collection of user reviews with advanced filtering and styling options.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Review_Collection_Widget_Manager',
                'file' => 'widgets/class-review-collection-widget-manager.php',
            ),
            'prev_next_widget' => array(
                'name' => __('Previous/Next Navigation', 'voxel-toolkit'),
                'description' => __('Navigate between posts with customizable previous/next buttons and post information display.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Prev_Next_Widget_Manager',
                'file' => 'widgets/class-prev-next-widget-manager.php',
            ),
            'profile_progress' => array(
                'name' => __('Profile Progress', 'voxel-toolkit'),
                'description' => __('Display user profile completion progress with customizable field tracking and visual styles.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Profile_Progress_Widget',
                'file' => 'widgets/class-profile-progress-widget.php',
            ),
            'timeline_photos' => array(
                'name' => __('Timeline Photos', 'voxel-toolkit'),
                'description' => __('Display photos from post reviews in a customizable gallery with masonry, grid, and justified layouts.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Timeline_Photos_Widget',
                'file' => 'widgets/class-timeline-photos-widget.php',
            ),
            'users_purchased' => array(
                'name' => __('Users Purchased', 'voxel-toolkit'),
                'description' => __('Display users who have purchased the current product with avatar grid or list views.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Users_Purchased_Widget_Manager',
                'file' => 'widgets/class-users-purchased-widget-manager.php',
            ),
            'article_helpful' => array(
                'name' => __('Article Helpful', 'voxel-toolkit'),
                'description' => __('Display "Was this Article Helpful?" widget with yes/no voting and admin statistics tracking.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Article_Helpful_Widget_Manager',
                'file' => 'widgets/class-article-helpful-widget-manager.php',
            )
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
        
        foreach ($this->available_widgets as $widget_key => $widget_data) {
            $widget_key_full = 'widget_' . $widget_key;
            if ($settings->is_function_enabled($widget_key_full)) {
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
        <tr>
            <th scope="row">
                <label for="auto_verify_posts_post_types"><?php _e('Post Types to Auto-Verify', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php _e('Select post types to automatically verify', 'voxel-toolkit'); ?></span>
                    </legend>
                    <?php foreach ($post_types as $post_type => $label): ?>
                        <label>
                            <input type="checkbox" 
                                   name="voxel_toolkit_options[auto_verify_posts][post_types][]" 
                                   value="<?php echo esc_attr($post_type); ?>"
                                   <?php checked(in_array($post_type, $selected_types)); ?> />
                            <?php echo esc_html($label); ?>
                        </label><br>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php _e('Select which post types should be automatically marked as verified when submitted.', 'voxel-toolkit'); ?>
                    </p>
                </fieldset>
            </td>
        </tr>
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
            'structure' => array(
                'name' => __('Structure (Post Types)', 'voxel-toolkit'),
                'description' => __('Hide the Voxel Post Types configuration page', 'voxel-toolkit')
            ),
            'membership' => array(
                'name' => __('Membership', 'voxel-toolkit'),
                'description' => __('Hide the Voxel Membership configuration page', 'voxel-toolkit')
            )
        );
        
        $hidden_menus = isset($settings['hidden_menus']) ? $settings['hidden_menus'] : array();
        
        ?>
        <tr>
            <th scope="row">
                <label for="admin_menu_hide_menus"><?php _e('Menus to Hide', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php _e('Select admin menus to hide', 'voxel-toolkit'); ?></span>
                    </legend>
                    <?php foreach ($available_menus as $menu_key => $menu_data): ?>
                        <label>
                            <input type="checkbox" 
                                   name="voxel_toolkit_options[admin_menu_hide][hidden_menus][]" 
                                   value="<?php echo esc_attr($menu_key); ?>"
                                   <?php checked(in_array($menu_key, $hidden_menus)); ?> />
                            <strong><?php echo esc_html($menu_data['name']); ?></strong>
                        </label>
                        <p class="description" style="margin-left: 25px; margin-bottom: 10px;">
                            <?php echo esc_html($menu_data['description']); ?>
                        </p>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php _e('Select which admin menus should be hidden from the WordPress admin interface.', 'voxel-toolkit'); ?>
                    </p>
                </fieldset>
            </td>
        </tr>
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
        <tr>
            <th scope="row">
                <label for="admin_bar_publish_post_types"><?php _e('Post Types with Admin Bar Button', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php _e('Select post types to show admin bar publish button', 'voxel-toolkit'); ?></span>
                    </legend>
                    <?php foreach ($post_types as $post_type => $label): ?>
                        <label>
                            <input type="checkbox" 
                                   name="voxel_toolkit_options[admin_bar_publish][post_types][]" 
                                   value="<?php echo esc_attr($post_type); ?>"
                                   <?php checked(in_array($post_type, $selected_types)); ?> />
                            <?php echo esc_html($label); ?>
                        </label><br>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php _e('Select which post types should show the Publish/Mark as Pending button in the admin bar. The button will appear when viewing or editing posts of these types.', 'voxel-toolkit'); ?>
                    </p>
                </fieldset>
            </td>
        </tr>
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
        <tr>
            <th scope="row">
                <label for="delete_post_media_post_types"><?php _e('Post Types with Media Auto-Delete', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php _e('Select post types to automatically delete media when post is deleted', 'voxel-toolkit'); ?></span>
                    </legend>
                    <?php foreach ($post_types as $post_type => $label): ?>
                        <label>
                            <input type="checkbox" 
                                   name="voxel_toolkit_options[delete_post_media][post_types][]" 
                                   value="<?php echo esc_attr($post_type); ?>"
                                   <?php checked(in_array($post_type, $selected_types)); ?> />
                            <?php echo esc_html($label); ?>
                        </label><br>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php _e('Select which post types should automatically delete all attached media when the post is deleted. A double confirmation dialog will appear to prevent accidental deletions.', 'voxel-toolkit'); ?>
                    </p>
                    <p class="description" style="color: #d63638; font-weight: 600;">
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('Warning: This will permanently delete media files from your server. This action cannot be undone.', 'voxel-toolkit'); ?>
                    </p>
                </fieldset>
            </td>
        </tr>
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
        <tr>
            <th scope="row">
                <label><?php _e('User Roles', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php _e('Select user roles to receive admin notifications', 'voxel-toolkit'); ?></span>
                    </legend>
                    <div class="vt-role-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 15px;">
                        <?php foreach ($roles as $role_key => $role_data): ?>
                            <label style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 10px; display: flex; align-items: center; cursor: pointer; transition: all 0.2s;">
                                <input type="checkbox" 
                                       name="voxel_toolkit_options[admin_notifications][user_roles][]" 
                                       value="<?php echo esc_attr($role_key); ?>"
                                       <?php checked(in_array($role_key, $user_roles)); ?> 
                                       style="margin-right: 8px;" />
                                <?php echo esc_html($role_data['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="description">
                        <?php _e('Replace default admin notifications with notifications sent to all users with the selected roles.', 'voxel-toolkit'); ?>
                    </p>
                </fieldset>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="admin_notifications_selected_users"><?php _e('Individual Users', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <!-- Hidden field to ensure empty selection is properly handled -->
                <input type="hidden" name="voxel_toolkit_options[admin_notifications][selected_users]" value="" />
                
                <select id="admin_notifications_selected_users" 
                        name="voxel_toolkit_options[admin_notifications][selected_users][]" 
                        multiple="multiple" 
                        style="width: 100%; min-height: 120px;"
                        class="vt-user-search-select">
                    <?php foreach($selected_users as $user_id): ?>
                        <?php if ($user_data = get_userdata($user_id)): ?>
                            <option selected value="<?php echo esc_attr($user_id); ?>">
                                <?php echo esc_html($user_data->display_name . ' (' . $user_data->user_email . ')'); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php _e('Search and select individual users to receive admin notifications instead of the default admin. Type at least 3 characters to search.', 'voxel-toolkit'); ?>
                </p>
            </td>
        </tr>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize Select2 for user search if not already done
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
                            return {
                                results: data
                            };
                        },
                        cache: true
                    },
                    escapeMarkup: function(markup) {
                        return markup;
                    },
                    minimumInputLength: 3,
                    placeholder: '<?php _e('Search for users by name or email...', 'voxel-toolkit'); ?>',
                    allowClear: true
                });
            }
            
            // Handle form submission to ensure empty selection is properly saved
            $('form').on('submit', function() {
                var selectedValues = $('#admin_notifications_selected_users').val();
                if (!selectedValues || selectedValues.length === 0) {
                    // If no users selected, ensure the hidden field value is kept empty
                    $('input[name="voxel_toolkit_options[admin_notifications][selected_users]"]').val('');
                }
            });
        });
        </script>
        
        <style>
        .vt-role-grid label:hover {
            background: #f0f0f1 !important;
        }
        .vt-role-grid input[type="checkbox"]:checked + span {
            font-weight: 600;
            color: #2271b1;
        }
        .select2-container {
            max-width: 100%;
        }
        .select2-container--default .select2-selection--multiple {
            min-height: 38px;
            padding: 2px;
        }
        </style>
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
        <tr>
            <th scope="row">
                <label><?php _e('Email Notifications', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <div id="membership-notifications-container">
                    <div class="membership-notifications-intro" style="background: #f8f9fa; border: 1px solid #e1e5e9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 18px;">Email Notification Setup</h3>
                        <p style="margin: 0 0 15px 0; line-height: 1.6; color: #646970;">Configure automated email notifications to send to members before their subscription expires. Create multiple notification rules with different timing.</p>
                        
                        <div style="background: white; padding: 15px; border-radius: 6px; border: 1px solid #e1e5e9;">
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
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render settings for guest view function
     * 
     * @param array $settings Current settings
     */
    public function render_guest_view_settings($settings) {
        ?>
        <tr>
            <th scope="row">
                <label><?php _e('Guest View Settings', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; max-width: 600px;">
                    <!-- General Settings -->
                    <div style="margin-bottom: 30px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;"><?php _e('General Settings', 'voxel-toolkit'); ?></h3>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: flex; align-items: center; gap: 8px; font-weight: 500;">
                                <input type="checkbox" 
                                       name="voxel_toolkit_options[guest_view][show_confirmation]" 
                                       value="1"
                                       <?php checked(!empty($settings['show_confirmation'])); ?> />
                                <?php _e('Show confirmation dialog', 'voxel-toolkit'); ?>
                            </label>
                            <p style="margin: 5px 0 0 26px; font-size: 13px; color: #666; font-style: italic;"><?php _e('Ask for confirmation before switching to guest view', 'voxel-toolkit'); ?></p>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px;"><?php _e('Button Position', 'voxel-toolkit'); ?></label>
                            <select name="voxel_toolkit_options[guest_view][button_position]" style="width: 200px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="top-left" <?php selected(isset($settings['button_position']) ? $settings['button_position'] : '', 'top-left'); ?>><?php _e('Top Left', 'voxel-toolkit'); ?></option>
                                <option value="top-right" <?php selected(isset($settings['button_position']) ? $settings['button_position'] : '', 'top-right'); ?>><?php _e('Top Right', 'voxel-toolkit'); ?></option>
                                <option value="middle-left" <?php selected(isset($settings['button_position']) ? $settings['button_position'] : '', 'middle-left'); ?>><?php _e('Middle Left', 'voxel-toolkit'); ?></option>
                                <option value="middle-right" <?php selected(isset($settings['button_position']) ? $settings['button_position'] : '', 'middle-right'); ?>><?php _e('Middle Right', 'voxel-toolkit'); ?></option>
                                <option value="bottom-left" <?php selected(isset($settings['button_position']) ? $settings['button_position'] : '', 'bottom-left'); ?>><?php _e('Bottom Left', 'voxel-toolkit'); ?></option>
                                <option value="bottom-right" <?php selected(isset($settings['button_position']) ? $settings['button_position'] : '', 'bottom-right'); ?>><?php _e('Bottom Right', 'voxel-toolkit'); ?></option>
                            </select>
                            <p style="margin: 5px 0 0 0; font-size: 13px; color: #666; font-style: italic;"><?php _e('Where to show the floating "Exit Guest View" button (always bottom center on mobile)', 'voxel-toolkit'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Color Settings -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;"><?php _e('Exit Button Colors', 'voxel-toolkit'); ?></h3>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 5px;"><?php _e('Background Color', 'voxel-toolkit'); ?></label>
                                <input type="text" 
                                       name="voxel_toolkit_options[guest_view][bg_color]" 
                                       value="<?php echo esc_attr(isset($settings['bg_color']) ? $settings['bg_color'] : '#667eea'); ?>"
                                       placeholder="#667eea"
                                       pattern="^#[A-Fa-f0-9]{6}$"
                                       class="guest-view-bg-color"
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;" />
                                <p style="margin: 3px 0 0 0; font-size: 12px; color: #666; font-style: italic;"><?php _e('Button background color', 'voxel-toolkit'); ?></p>
                            </div>
                            
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 5px;"><?php _e('Text Color', 'voxel-toolkit'); ?></label>
                                <input type="text" 
                                       name="voxel_toolkit_options[guest_view][text_color]" 
                                       value="<?php echo esc_attr(isset($settings['text_color']) ? $settings['text_color'] : '#ffffff'); ?>"
                                       placeholder="#ffffff"
                                       pattern="^#[A-Fa-f0-9]{6}$"
                                       class="guest-view-text-color"
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;" />
                                <p style="margin: 3px 0 0 0; font-size: 12px; color: #666; font-style: italic;"><?php _e('"Exit Guest View" text color', 'voxel-toolkit'); ?></p>
                            </div>
                        </div>
                        
                        <!-- Live Preview -->
                        <div style="background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 6px; padding: 20px; text-align: center;">
                            <p style="margin: 0 0 15px 0; font-weight: 500; color: #1e1e1e;"><?php _e('Live Preview:', 'voxel-toolkit'); ?></p>
                            <div id="guest-view-button-preview" style="display: inline-block;">
                                <button type="button" 
                                        style="background: <?php echo esc_attr(isset($settings['bg_color']) ? $settings['bg_color'] : '#667eea'); ?>; 
                                               color: <?php echo esc_attr(isset($settings['text_color']) ? $settings['text_color'] : '#ffffff'); ?>; 
                                               border: none; 
                                               padding: 12px 20px; 
                                               border-radius: 25px; 
                                               font-size: 14px; 
                                               font-weight: 600; 
                                               cursor: pointer; 
                                               transition: all 0.2s; 
                                               white-space: nowrap; 
                                               text-decoration: none; 
                                               box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); 
                                               min-width: 140px;">
                                    <?php _e('Exit Guest View', 'voxel-toolkit'); ?>
                                </button>
                            </div>
                            <p style="margin: 10px 0 0 0; font-size: 12px; color: #666; font-style: italic;"><?php _e('This is how the button will appear when guest view is active', 'voxel-toolkit'); ?></p>
                        </div>
                    </div>
                    
                    <div style="padding: 15px; background: #f8f9fa; border-left: 3px solid #2271b1; border-radius: 4px; font-size: 14px;">
                        <strong><?php _e('How to use:', 'voxel-toolkit'); ?></strong> <?php _e('Add the "Guest View Button" widget to your pages using Elementor (found in "Voxel Toolkit" category).', 'voxel-toolkit'); ?>
                    </div>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    // Function to validate hex color
                    function isValidHex(hex) {
                        return /^#[0-9A-Fa-f]{6}$/.test(hex);
                    }
                    
                    // Function to update preview
                    function updatePreview() {
                        var bgColor = $('.guest-view-bg-color').val();
                        var textColor = $('.guest-view-text-color').val();
                        var $previewBtn = $('#guest-view-button-preview button');
                        
                        // Update background color
                        if (isValidHex(bgColor)) {
                            $previewBtn.css('background', bgColor);
                        }
                        
                        // Update text color
                        if (isValidHex(textColor)) {
                            $previewBtn.css('color', textColor);
                        }
                    }
                    
                    // Bind events to color inputs
                    $('.guest-view-bg-color, .guest-view-text-color').on('input keyup paste', function() {
                        setTimeout(updatePreview, 50);
                    });
                    
                    // Add hover effect to preview button
                    $('#guest-view-button-preview button').hover(
                        function() {
                            $(this).css('transform', 'translateY(-2px)');
                        },
                        function() {
                            $(this).css('transform', 'translateY(0)');
                        }
                    );
                });
                </script>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render settings for password visibility toggle function
     * 
     * @param array $settings Current settings
     */
    public function render_password_visibility_toggle_settings($settings) {
        ?>
        <tr>
            <th scope="row">
                <label><?php _e('Password Toggle Settings', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; max-width: 600px;">
                    <!-- Icon Color Settings -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;"><?php _e('Eye Icon Colors', 'voxel-toolkit'); ?></h3>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 5px;"><?php _e('Icon Color', 'voxel-toolkit'); ?></label>
                                <input type="text" 
                                       name="voxel_toolkit_options[password_visibility_toggle][icon_color]" 
                                       value="<?php echo esc_attr(isset($settings['icon_color']) ? $settings['icon_color'] : '#666666'); ?>"
                                       placeholder="#666666"
                                       pattern="^#[A-Fa-f0-9]{6}$"
                                       class="password-toggle-icon-color"
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;" />
                                <p style="margin: 3px 0 0 0; font-size: 12px; color: #666; font-style: italic;"><?php _e('Default eye icon color', 'voxel-toolkit'); ?></p>
                            </div>
                            
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 5px;"><?php _e('Hover Color', 'voxel-toolkit'); ?></label>
                                <input type="text" 
                                       name="voxel_toolkit_options[password_visibility_toggle][icon_hover_color]" 
                                       value="<?php echo esc_attr(isset($settings['icon_hover_color']) ? $settings['icon_hover_color'] : '#333333'); ?>"
                                       placeholder="#333333"
                                       pattern="^#[A-Fa-f0-9]{6}$"
                                       class="password-toggle-hover-color"
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;" />
                                <p style="margin: 3px 0 0 0; font-size: 12px; color: #666; font-style: italic;"><?php _e('Eye icon color on hover', 'voxel-toolkit'); ?></p>
                            </div>
                        </div>
                        
                        <!-- Live Preview -->
                        <div style="background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 6px; padding: 20px;">
                            <p style="margin: 0 0 15px 0; font-weight: 500; color: #1e1e1e;"><?php _e('Live Preview:', 'voxel-toolkit'); ?></p>
                            <div style="max-width: 300px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Password Field:', 'voxel-toolkit'); ?></label>
                                <div id="password-toggle-preview" style="position: relative; display: inline-block; width: 100%;">
                                    <input type="password" 
                                           value="samplepassword" 
                                           readonly
                                           style="width: 100%; padding: 10px 45px 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;" />
                                    <span id="preview-toggle-btn"
                                          tabindex="0"
                                          role="button"
                                          style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; color: <?php echo esc_attr(isset($settings['icon_color']) ? $settings['icon_color'] : '#666666'); ?>; font-size: 16px; transition: color 0.2s ease;">
                                        <svg class="voxel-password-toggle-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </span>
                                </div>
                            </div>
                            <p style="margin: 10px 0 0 0; font-size: 12px; color: #666; font-style: italic;"><?php _e('Try clicking the eye icon to see the toggle in action', 'voxel-toolkit'); ?></p>
                        </div>
                    </div>
                    
                    <div style="padding: 15px; background: #f8f9fa; border-left: 3px solid #2271b1; border-radius: 4px; font-size: 14px;">
                        <strong><?php _e('How it works:', 'voxel-toolkit'); ?></strong> <?php _e('Eye icons are automatically added to all password fields site-wide. Users can click to show/hide their password text.', 'voxel-toolkit'); ?>
                    </div>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    // Eye icons
                    var eyeIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                    var eyeSlashIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
                    
                    // Function to validate hex color
                    function isValidHex(hex) {
                        return /^#[0-9A-Fa-f]{6}$/.test(hex);
                    }
                    
                    // Function to update preview colors
                    function updatePreviewColors() {
                        var iconColor = $('.password-toggle-icon-color').val();
                        var hoverColor = $('.password-toggle-hover-color').val();
                        var $previewBtn = $('#preview-toggle-btn');
                        
                        // Update icon color
                        if (isValidHex(iconColor)) {
                            $previewBtn.css('color', iconColor);
                        }
                        
                        // Update hover color (store as data attribute for hover event)
                        if (isValidHex(hoverColor)) {
                            $previewBtn.attr('data-hover-color', hoverColor);
                        }
                    }
                    
                    // Bind events to color inputs
                    $('.password-toggle-icon-color, .password-toggle-hover-color').on('input keyup paste', function() {
                        setTimeout(updatePreviewColors, 50);
                    });
                    
                    // Preview toggle functionality
                    $('#preview-toggle-btn').on('click', function() {
                        var $btn = $(this);
                        var $input = $('#password-toggle-preview input');
                        var currentType = $input.attr('type');
                        
                        if (currentType === 'password') {
                            $input.attr('type', 'text');
                            $btn.html(eyeSlashIcon);
                        } else {
                            $input.attr('type', 'password');
                            $btn.html(eyeIcon);
                        }
                    });
                    
                    // Add hover effects to preview button
                    $('#preview-toggle-btn').hover(
                        function() {
                            var hoverColor = $(this).attr('data-hover-color');
                            if (hoverColor && isValidHex(hoverColor)) {
                                $(this).css('color', hoverColor);
                            }
                        },
                        function() {
                            var iconColor = $('.password-toggle-icon-color').val();
                            if (iconColor && isValidHex(iconColor)) {
                                $(this).css('color', iconColor);
                            }
                        }
                    );
                    
                    // Initialize preview colors
                    updatePreviewColors();
                });
                </script>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render settings for AI Review Summary function
     * 
     * @param array $settings Current settings
     */
    public function render_ai_review_summary_settings($settings) {
        // Check if cache was refreshed
        $cache_refreshed = isset($_GET['ai_cache_refreshed']) ? intval($_GET['ai_cache_refreshed']) : 0;
        ?>
        <tr>
            <th scope="row">
                <label><?php _e('AI Review Summary Settings', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; max-width: 800px;">
                    <!-- API Settings -->
                    <div style="margin-bottom: 30px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;"><?php _e('ChatGPT API Configuration', 'voxel-toolkit'); ?></h3>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px;"><?php _e('OpenAI API Key', 'voxel-toolkit'); ?></label>
                            
                            <?php
                            $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
                            $has_api_key = !empty($api_key) && strlen($api_key) > 10;
                            ?>
                            
                            <?php if ($has_api_key): ?>
                                <!-- Show existing key status -->
                                <div style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                        <span style="font-family: monospace; color: #666; font-size: 14px;">
                                            <?php echo esc_html(substr($api_key, 0, 7) . str_repeat('*', 20)); ?>
                                        </span>
                                        <span style="background: #d4edda; color: #155724; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase;">
                                            <?php _e('Active', 'voxel-toolkit'); ?>
                                        </span>
                                    </div>
                                    <p style="margin: 0; font-size: 12px; color: #6c757d;">
                                        <?php _e('API key is configured and ready to use. Enter a new key below to replace it.', 'voxel-toolkit'); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <input type="text" 
                                   name="ai_api_key" 
                                   value=""
                                   placeholder="<?php echo $has_api_key ? 'Enter new API key to replace existing one' : 'sk-proj-...'; ?>"
                                   autocomplete="off"
                                   spellcheck="false"
                                   style="width: 100%; max-width: 500px; padding: 12px; border: 2px solid <?php echo $has_api_key ? '#28a745' : '#ddd'; ?>; border-radius: 6px; font-family: monospace; font-size: 14px; background: <?php echo $has_api_key ? '#f8fff9' : 'white'; ?>;" />
                            
                            <p style="margin: 10px 0 0 0; font-size: 13px; color: #666;">
                                <?php _e('Get your OpenAI API key from ', 'voxel-toolkit'); ?>
                                <a href="https://platform.openai.com/api-keys" target="_blank" style="color: #0073aa;"><?php _e('OpenAI Platform', 'voxel-toolkit'); ?></a>
                                <?php _e(' (API usage costs apply)', 'voxel-toolkit'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Language Settings -->
                    <div style="margin-bottom: 30px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;"><?php _e('Language Settings', 'voxel-toolkit'); ?></h3>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px;"><?php _e('AI Output Language', 'voxel-toolkit'); ?></label>
                            
                            <?php 
                            $current_language = isset($settings['language']) ? $settings['language'] : 'en';
                            $languages = array(
                                'en' => __('English', 'voxel-toolkit'),
                                'it' => __('Italian', 'voxel-toolkit'),
                                'es' => __('Spanish', 'voxel-toolkit'),
                                'fr' => __('French', 'voxel-toolkit'),
                                'de' => __('German', 'voxel-toolkit'),
                                'pt' => __('Portuguese', 'voxel-toolkit'),
                                'nl' => __('Dutch', 'voxel-toolkit'),
                                'ru' => __('Russian', 'voxel-toolkit'),
                                'zh' => __('Chinese', 'voxel-toolkit'),
                                'ja' => __('Japanese', 'voxel-toolkit'),
                                'ko' => __('Korean', 'voxel-toolkit'),
                                'ar' => __('Arabic', 'voxel-toolkit'),
                                'hi' => __('Hindi', 'voxel-toolkit'),
                                'tr' => __('Turkish', 'voxel-toolkit'),
                                'pl' => __('Polish', 'voxel-toolkit'),
                                'sv' => __('Swedish', 'voxel-toolkit'),
                                'da' => __('Danish', 'voxel-toolkit'),
                                'no' => __('Norwegian', 'voxel-toolkit'),
                                'fi' => __('Finnish', 'voxel-toolkit'),
                                'cs' => __('Czech', 'voxel-toolkit'),
                                'hu' => __('Hungarian', 'voxel-toolkit'),
                                'ro' => __('Romanian', 'voxel-toolkit'),
                                'bg' => __('Bulgarian', 'voxel-toolkit'),
                                'hr' => __('Croatian', 'voxel-toolkit'),
                                'sk' => __('Slovak', 'voxel-toolkit'),
                                'sl' => __('Slovenian', 'voxel-toolkit'),
                                'et' => __('Estonian', 'voxel-toolkit'),
                                'lv' => __('Latvian', 'voxel-toolkit'),
                                'lt' => __('Lithuanian', 'voxel-toolkit'),
                                'el' => __('Greek', 'voxel-toolkit'),
                                'he' => __('Hebrew', 'voxel-toolkit'),
                                'th' => __('Thai', 'voxel-toolkit'),
                                'vi' => __('Vietnamese', 'voxel-toolkit'),
                                'id' => __('Indonesian', 'voxel-toolkit'),
                                'ms' => __('Malay', 'voxel-toolkit'),
                                'uk' => __('Ukrainian', 'voxel-toolkit'),
                            );
                            ?>
                            
                            <select name="voxel_toolkit_options[ai_review_summary][language]" 
                                    style="width: 100%; max-width: 300px; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; background: white;">
                                <?php foreach ($languages as $code => $name): ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($current_language, $code); ?>>
                                        <?php echo esc_html($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <p style="margin: 10px 0 0 0; font-size: 13px; color: #666;">
                                <?php _e('Select the language for AI-generated summaries and opinions. The AI will respond in the selected language.', 'voxel-toolkit'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Cache Management -->
                    <div style="margin-bottom: 30px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;"><?php _e('Cache Management', 'voxel-toolkit'); ?></h3>
                        
                        <?php if ($cache_refreshed): ?>
                            <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                                <strong><?php _e('Success:', 'voxel-toolkit'); ?></strong> <?php _e('AI cached summaries have been refreshed.', 'voxel-toolkit'); ?>
                            </div>
                        <?php endif; ?>
                        
                        <p style="margin-bottom: 15px; color: #666;">
                            <?php _e('Clear all cached AI-generated summaries and category opinions. New summaries will be generated on the next page load.', 'voxel-toolkit'); ?>
                        </p>
                        
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline-block;">
                            <?php wp_nonce_field('voxel_toolkit_refresh_ai_cache', 'voxel_toolkit_refresh_ai_cache_nonce'); ?>
                            <input type="hidden" name="action" value="voxel_toolkit_refresh_ai_cache">
                            <button type="submit" class="button button-secondary" onclick="return confirm('Are you sure you want to refresh all cached AI summaries? This will trigger new API calls when users next view pages with review summaries.');">
                                <?php _e('Refresh All Cached Summaries', 'voxel-toolkit'); ?>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Shortcode Documentation -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;"><?php _e('Available Shortcodes', 'voxel-toolkit'); ?></h3>
                        
                        <!-- Review Summary Shortcode -->
                        <div style="background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 6px; padding: 20px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 10px 0; color: #1e1e1e; display: flex; align-items: center; gap: 10px;">
                                <?php _e('Review Summary', 'voxel-toolkit'); ?>
                                <button type="button" class="button button-small copy-shortcode-btn" data-shortcode='[review_summary]'>
                                    <?php _e('Copy', 'voxel-toolkit'); ?>
                                </button>
                            </h4>
                            <p style="margin: 0 0 10px 0; color: #666; line-height: 1.5;">
                                <?php _e('Generates an AI-powered summary of all reviews for a post, similar to TripAdvisor summaries. Focuses on main strengths and weaknesses mentioned by users.', 'voxel-toolkit'); ?>
                            </p>
                            <div style="background: #2c3e50; color: #ecf0f1; padding: 12px; border-radius: 4px; font-family: monospace; font-size: 13px; margin-bottom: 10px;">
                                <strong><?php _e('Basic usage:', 'voxel-toolkit'); ?></strong><br>
                                <code style="color: #f39c12;">[review_summary]</code>
                            </div>
                            <div style="background: #2c3e50; color: #ecf0f1; padding: 12px; border-radius: 4px; font-family: monospace; font-size: 13px;">
                                <strong><?php _e('Specific post:', 'voxel-toolkit'); ?></strong><br>
                                <code style="color: #f39c12;">[review_summary post_id="123"]</code>
                            </div>
                        </div>
                        
                        <!-- Category Opinions Shortcode -->
                        <div style="background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 6px; padding: 20px;">
                            <h4 style="margin: 0 0 10px 0; color: #1e1e1e; display: flex; align-items: center; gap: 10px;">
                                <?php _e('Category Opinions', 'voxel-toolkit'); ?>
                                <button type="button" class="button button-small copy-shortcode-btn" data-shortcode='[category_opinions]'>
                                    <?php _e('Copy', 'voxel-toolkit'); ?>
                                </button>
                            </h4>
                            <p style="margin: 0 0 10px 0; color: #666; line-height: 1.5;">
                                <?php _e('Creates a grid of category opinion boxes with one-word AI summaries for different aspects of the listing (Food, Service, Atmosphere, etc.).', 'voxel-toolkit'); ?>
                            </p>
                            <div style="background: #2c3e50; color: #ecf0f1; padding: 12px; border-radius: 4px; font-family: monospace; font-size: 13px; margin-bottom: 10px;">
                                <strong><?php _e('Default categories:', 'voxel-toolkit'); ?></strong><br>
                                <code style="color: #f39c12;">[category_opinions]</code><br>
                                <small style="color: #95a5a6;"><?php _e('Uses: Food, Atmosphere, Service, Value', 'voxel-toolkit'); ?></small>
                            </div>
                            <div style="background: #2c3e50; color: #ecf0f1; padding: 12px; border-radius: 4px; font-family: monospace; font-size: 13px; margin-bottom: 10px;">
                                <strong><?php _e('Custom categories:', 'voxel-toolkit'); ?></strong><br>
                                <code style="color: #f39c12;">[category_opinions categories="Quality, Price, Staff, Location"]</code>
                            </div>
                            <div style="background: #2c3e50; color: #ecf0f1; padding: 12px; border-radius: 4px; font-family: monospace; font-size: 13px;">
                                <strong><?php _e('Specific post with custom categories:', 'voxel-toolkit'); ?></strong><br>
                                <code style="color: #f39c12;">[category_opinions post_id="123" categories="Food, Service, Ambiance"]</code>
                            </div>
                        </div>
                    </div>
                    
                    <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; font-size: 14px;">
                        <strong style="color: #856404;"><?php _e('Important:', 'voxel-toolkit'); ?></strong>
                        <span style="color: #856404;">
                            <?php _e('Summaries are cached until new reviews are added. API calls are only made when cache is empty or outdated. OpenAI API usage costs apply.', 'voxel-toolkit'); ?>
                        </span>
                    </div>
                    
                    <div style="margin-top: 20px; padding: 12px; background: #f8f9fa; border-radius: 4px; text-align: center; border-top: 1px solid #dee2e6;">
                        <p style="margin: 0; font-size: 13px; color: #6c757d;">
                            <?php _e('AI Review Summary developed by', 'voxel-toolkit'); ?> 
                            <strong style="color: #495057;">Miguel Gomes</strong>
                        </p>
                    </div>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    // Copy shortcode functionality
                    $('.copy-shortcode-btn').on('click', function() {
                        var shortcode = $(this).data('shortcode');
                        var $btn = $(this);
                        
                        // Create temporary textarea to copy text
                        var $temp = $('<textarea>');
                        $('body').append($temp);
                        $temp.val(shortcode).select();
                        document.execCommand('copy');
                        $temp.remove();
                        
                        // Show feedback
                        var originalText = $btn.text();
                        $btn.text('<?php _e('Copied!', 'voxel-toolkit'); ?>').prop('disabled', true);
                        
                        setTimeout(function() {
                            $btn.text(originalText).prop('disabled', false);
                        }, 2000);
                    });
                });
                </script>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render settings for Show Field Description function
     * 
     * @param array $settings Current settings
     */
    public function render_show_field_description_settings($settings) {
        ?>
        <tr>
            <th scope="row">
                <label><?php _e('Show Field Description Settings', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; max-width: 600px;">
                    <div style="padding: 15px; background: #f8f9fa; border-left: 3px solid #2271b1; border-radius: 4px; font-size: 14px;">
                        <strong><?php _e('How it works:', 'voxel-toolkit'); ?></strong>
                        <?php _e('This function automatically converts Voxel form field tooltip icons into visible descriptions displayed below field labels. No additional configuration needed - simply enable the function and it will work on all Voxel forms site-wide.', 'voxel-toolkit'); ?>
                    </div>
                    
                    <div style="margin-top: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; font-size: 14px;">
                        <strong style="color: #856404;"><?php _e('Effect:', 'voxel-toolkit'); ?></strong>
                        <ul style="margin: 10px 0 0 20px; color: #856404;">
                            <li><?php _e('Tooltip icons are hidden', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Field descriptions appear as subtitles below labels', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Improves form accessibility and user experience', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Works on all create/edit post forms', 'voxel-toolkit'); ?></li>
                        </ul>
                    </div>
                    
                    <div style="margin-top: 20px; padding: 12px; background: #f8f9fa; border-radius: 4px; text-align: center; border-top: 1px solid #dee2e6;">
                        <p style="margin: 0; font-size: 13px; color: #6c757d;">
                            <?php _e('Show Field Description developed by', 'voxel-toolkit'); ?> 
                            <strong style="color: #495057;">Michał Maciak</strong>
                        </p>
                    </div>
                </div>
            </td>
        </tr>
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
        <tr>
            <th scope="row">
                <label><?php _e('Duplicate Post Settings', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; max-width: 600px;">
                    <!-- How it works -->
                    <div style="padding: 15px; background: #f8f9fa; border-left: 3px solid #2271b1; border-radius: 4px; font-size: 14px; margin-bottom: 20px;">
                        <strong><?php _e('How it works:', 'voxel-toolkit'); ?></strong>
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
                </div>
            </td>
        </tr>
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
        ?>
        <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            
            <!-- User Roles -->
            <div style="margin-bottom: 25px;">
                <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;">
                    <?php _e('User Permissions', 'voxel-toolkit'); ?>
                </h3>
                <p style="margin: 0 0 15px 0; color: #666; font-size: 13px;">
                    <?php _e('Select which user roles can paste images from clipboard.', 'voxel-toolkit'); ?>
                </p>
                
                <div class="role-options" style="display: flex; flex-wrap: wrap; gap: 15px;">
                    <label style="display: flex; align-items: center; margin-bottom: 8px; padding: 8px 12px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; cursor: pointer;">
                        <input type="checkbox" 
                               name="voxel_toolkit_options[media_paste][allowed_roles][]" 
                               value="all_roles"
                               <?php checked(in_array('all_roles', $allowed_roles)); ?>
                               style="margin-right: 8px;"
                               onchange="toggleAllRoles(this)">
                        <span><?php _e('All Roles (Including Subscribers)', 'voxel-toolkit'); ?></span>
                    </label>
                </div>
                
                <div class="role-options" style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 10px;">
                    <?php foreach ($available_roles as $role_key => $role_data): ?>
                        <label class="role-checkbox" style="display: flex; align-items: center; margin-bottom: 8px; padding: 8px 12px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; cursor: pointer;">
                            <input type="checkbox" 
                                   name="voxel_toolkit_options[media_paste][allowed_roles][]" 
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
            
            <!-- File Settings -->
            <div style="margin-bottom: 25px;">
                <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;">
                    <?php _e('File Settings', 'voxel-toolkit'); ?>
                </h3>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #333;">
                        <?php _e('Maximum File Size (MB)', 'voxel-toolkit'); ?>
                    </label>
                    <input type="number" 
                           name="voxel_toolkit_options[media_paste][max_file_size]"
                           value="<?php echo esc_attr($max_file_size); ?>"
                           placeholder="<?php echo esc_attr(wp_max_upload_size() / (1024 * 1024)); ?>"
                           min="1"
                           max="<?php echo esc_attr(wp_max_upload_size() / (1024 * 1024)); ?>"
                           style="width: 100px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 12px;">
                        <?php printf(__('Leave empty for server default (%s MB)', 'voxel-toolkit'), number_format(wp_max_upload_size() / (1024 * 1024), 1)); ?>
                    </p>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 10px; font-weight: 500; color: #333;">
                        <?php _e('Allowed Image Types', 'voxel-toolkit'); ?>
                    </label>
                    <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                        <?php 
                        $image_types = array(
                            'image/jpeg' => 'JPEG',
                            'image/png' => 'PNG', 
                            'image/gif' => 'GIF',
                            'image/webp' => 'WebP'
                        );
                        foreach ($image_types as $type => $label): 
                        ?>
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" 
                                       name="voxel_toolkit_options[media_paste][allowed_types][]"
                                       value="<?php echo esc_attr($type); ?>"
                                       <?php checked(in_array($type, $allowed_types)); ?>
                                       style="margin-right: 8px;">
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Features -->
            <div style="margin-bottom: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; font-size: 14px;">
                <strong style="color: #856404;"><?php _e('Features:', 'voxel-toolkit'); ?></strong>
                <ul style="margin: 10px 0 0 20px; color: #856404;">
                    <li><?php _e('Paste images directly from clipboard (Ctrl/Cmd+V)', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Works in WordPress media library', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Works in Elementor media picker', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Works in all WordPress media frames', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Automatic file naming with timestamps', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Visual feedback during upload', 'voxel-toolkit'); ?></li>
                </ul>
            </div>
            
            <!-- Usage Instructions -->
            <div style="margin-bottom: 20px;">
                <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;">
                    <?php _e('How to Use', 'voxel-toolkit'); ?>
                </h3>
                <ol style="margin: 0; padding-left: 20px; color: #333;">
                    <li style="margin-bottom: 8px;"><?php _e('Copy an image to your clipboard (Ctrl/Cmd+C)', 'voxel-toolkit'); ?></li>
                    <li style="margin-bottom: 8px;"><?php _e('Go to WordPress Media Library or open Elementor media picker', 'voxel-toolkit'); ?></li>
                    <li style="margin-bottom: 8px;"><?php _e('Press Ctrl/Cmd+V to paste the image', 'voxel-toolkit'); ?></li>
                    <li style="margin-bottom: 8px;"><?php _e('Image will be automatically uploaded and added to media library', 'voxel-toolkit'); ?></li>
                </ol>
            </div>
            
            <!-- Browser Support -->
            <div style="background: #e7f6ff; border: 1px solid #b3d9ff; border-radius: 4px; padding: 15px; font-size: 14px;">
                <strong style="color: #0066cc;"><?php _e('Browser Support:', 'voxel-toolkit'); ?></strong>
                <p style="margin: 10px 0 0 0; color: #0066cc;">
                    <?php _e('Chrome, Firefox, Safari, and Edge. Works with images copied from other applications, screenshots, and image files.', 'voxel-toolkit'); ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Admin Taxonomy Search settings
     */
    public function render_admin_taxonomy_search_settings($settings) {
        $taxonomies = get_taxonomies(array('public' => true), 'objects');
        $selected_taxonomies = isset($settings['taxonomies']) ? $settings['taxonomies'] : array();
        ?>
        <div class="voxel-settings-group">
            <label class="voxel-settings-label">
                <?php _e('Enable Search for Taxonomies', 'voxel-toolkit'); ?>
            </label>
            <div class="voxel-settings-description">
                <?php _e('Select which taxonomies should have search functionality in their metaboxes on post edit pages.', 'voxel-toolkit'); ?>
            </div>
            
            <div class="taxonomy-options" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                <?php foreach ($taxonomies as $taxonomy_key => $taxonomy): ?>
                    <label style="display: flex; align-items: center; padding: 12px; background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 6px; cursor: pointer; transition: all 0.2s ease;">
                        <input type="checkbox" 
                               name="voxel_toolkit_options[admin_taxonomy_search][taxonomies][]" 
                               value="<?php echo esc_attr($taxonomy_key); ?>"
                               <?php checked(in_array($taxonomy_key, $selected_taxonomies)); ?>
                               style="margin-right: 10px;">
                        <div>
                            <strong><?php echo esc_html($taxonomy->label); ?></strong>
                            <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                <?php echo esc_html($taxonomy_key); ?>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($taxonomies)): ?>
                <p style="color: #666; font-style: italic;">
                    <?php _e('No public taxonomies found.', 'voxel-toolkit'); ?>
                </p>
            <?php endif; ?>
        </div>
        
        <div class="voxel-settings-group">
            <label class="voxel-settings-label">
                <?php _e('How it works', 'voxel-toolkit'); ?>
            </label>
            <div class="voxel-settings-description">
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><?php _e('Adds a search box to the top of taxonomy metaboxes on post edit pages', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Search filters terms in real-time as you type', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Works with both hierarchical (categories) and non-hierarchical (tags) taxonomies', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Shows parent terms when child terms match the search', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Clear button (×) to quickly reset the search', 'voxel-toolkit'); ?></li>
                </ul>
            </div>
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
        <tr>
            <th scope="row">
                <label for="pending_posts_badge_post_types"><?php _e('Post Types to Show Badges', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php _e('Select post types to show pending badges', 'voxel-toolkit'); ?></span>
                    </legend>
                    <?php foreach ($post_types as $post_type => $label): ?>
                        <label>
                            <input type="checkbox" 
                                   name="voxel_toolkit_options[pending_posts_badge][post_types][]" 
                                   value="<?php echo esc_attr($post_type); ?>"
                                   <?php checked(in_array($post_type, $selected_types)); ?> />
                            <?php echo esc_html($label); ?>
                        </label><br>
                    <?php endforeach; ?>
                    <p class="description"><?php _e('Select which post types should display pending post count badges in the admin menu.', 'voxel-toolkit'); ?></p>
                </fieldset>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="pending_posts_badge_background_color"><?php _e('Background Color', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <input 
                    type="color" 
                    id="pending_posts_badge_background_color" 
                    name="voxel_toolkit_options[pending_posts_badge][background_color]" 
                    value="<?php echo esc_attr($background_color); ?>"
                    class="color-picker"
                />
                <p class="description"><?php _e('Choose the background color for the badges.', 'voxel-toolkit'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="pending_posts_badge_text_color"><?php _e('Text Color', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <input 
                    type="color" 
                    id="pending_posts_badge_text_color" 
                    name="voxel_toolkit_options[pending_posts_badge][text_color]" 
                    value="<?php echo esc_attr($text_color); ?>"
                    class="color-picker"
                />
                <p class="description"><?php _e('Choose the text color for the badges.', 'voxel-toolkit'); ?></p>
            </td>
        </tr>
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
        <tr>
            <th>
                <label for="pre_approve_posts_auto_approve_verified">
                    <?php _e('Auto-Approve Verified Users', 'voxel-toolkit'); ?>
                </label>
            </th>
            <td>
                <label>
                    <input type="checkbox" 
                           name="voxel_toolkit_options[pre_approve_posts][approve_verified]" 
                           id="pre_approve_posts_auto_approve_verified" 
                           value="1" 
                           <?php checked($approve_verified); ?>>
                    <?php _e('Automatically approve posts from users with verified profiles', 'voxel-toolkit'); ?>
                </label>
                <p class="description">
                    <?php _e('When enabled, users with verified Voxel profiles will have posts automatically approved.', 'voxel-toolkit'); ?>
                </p>
            </td>
        </tr>
        
        <tr>
            <th>
                <label for="pre_approve_posts_approved_roles">
                    <?php _e('Auto-Approve Roles', 'voxel-toolkit'); ?>
                </label>
            </th>
            <td>
                <?php 
                $all_roles = wp_roles()->roles;
                foreach($all_roles as $role_key => $role_info): ?>
                    <label style="display: block; margin: 5px 0;">
                        <input type="checkbox" 
                               name="voxel_toolkit_options[pre_approve_posts][approved_roles][]" 
                               value="<?php echo esc_attr($role_key); ?>"
                               <?php checked(in_array($role_key, $approved_roles)); ?>>
                        <?php echo esc_html($role_info['name']); ?>
                    </label>
                <?php endforeach; ?>
                <p class="description">
                    <?php _e('Select user roles that should have posts automatically approved.', 'voxel-toolkit'); ?>
                </p>
            </td>
        </tr>
        
        <tr>
            <th>
                <label for="pre_approve_posts_show_column">
                    <?php _e('Show Pre-Approved Column', 'voxel-toolkit'); ?>
                </label>
            </th>
            <td>
                <label>
                    <input type="checkbox" 
                           name="voxel_toolkit_options[pre_approve_posts][show_column]" 
                           id="pre_approve_posts_show_column" 
                           value="1" 
                           <?php checked($show_column); ?>>
                    <?php _e('Display "Pre-Approved?" column in the users list', 'voxel-toolkit'); ?>
                </label>
                <p class="description">
                    <?php _e('Shows a column in the users list indicating which users have pre-approval settings.', 'voxel-toolkit'); ?>
                </p>
            </td>
        </tr>
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
        <tr>
            <th scope="row">
                <label><?php _e('Disable Auto Updates Settings', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; max-width: 600px;">
                    <!-- How it works -->
                    <div style="padding: 15px; background: #f8f9fa; border-left: 3px solid #2271b1; border-radius: 4px; font-size: 14px; margin-bottom: 20px;">
                        <strong><?php _e('How it works:', 'voxel-toolkit'); ?></strong>
                        <?php _e('Disables automatic updates for plugins, themes, and WordPress core. Choose which types of updates to disable with individual controls.', 'voxel-toolkit'); ?>
                    </div>
                    
                    <!-- Update Types Selection -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e1e1e; font-size: 16px; border-bottom: 2px solid #f0f0f1; padding-bottom: 8px;">
                            <?php _e('Disable Updates For', 'voxel-toolkit'); ?>
                        </h3>
                        <p style="margin: 0 0 15px 0; color: #666; font-size: 14px;">
                            <?php _e('Select which types of automatic updates to disable:', 'voxel-toolkit'); ?>
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <!-- Plugin Updates -->
                            <label style="display: flex; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 4px; cursor: pointer; border: 2px solid #e1e5e9;">
                                <input type="checkbox" 
                                       name="voxel_toolkit_options[disable_auto_updates][disable_plugin_updates]" 
                                       value="1"
                                       <?php checked($disable_plugin_updates); ?>
                                       style="margin-right: 12px; transform: scale(1.2);">
                                <div>
                                    <strong style="display: block; color: #1e1e1e; font-size: 14px;">
                                        <?php _e('Plugin Updates', 'voxel-toolkit'); ?>
                                    </strong>
                                    <span style="color: #666; font-size: 13px;">
                                        <?php _e('Prevent plugins from updating automatically', 'voxel-toolkit'); ?>
                                    </span>
                                </div>
                            </label>
                            
                            <!-- Theme Updates -->
                            <label style="display: flex; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 4px; cursor: pointer; border: 2px solid #e1e5e9;">
                                <input type="checkbox" 
                                       name="voxel_toolkit_options[disable_auto_updates][disable_theme_updates]" 
                                       value="1"
                                       <?php checked($disable_theme_updates); ?>
                                       style="margin-right: 12px; transform: scale(1.2);">
                                <div>
                                    <strong style="display: block; color: #1e1e1e; font-size: 14px;">
                                        <?php _e('Theme Updates', 'voxel-toolkit'); ?>
                                    </strong>
                                    <span style="color: #666; font-size: 13px;">
                                        <?php _e('Prevent themes from updating automatically', 'voxel-toolkit'); ?>
                                    </span>
                                </div>
                            </label>
                            
                            <!-- WordPress Core Updates -->
                            <label style="display: flex; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 4px; cursor: pointer; border: 2px solid #e1e5e9;">
                                <input type="checkbox" 
                                       name="voxel_toolkit_options[disable_auto_updates][disable_core_updates]" 
                                       value="1"
                                       <?php checked($disable_core_updates); ?>
                                       style="margin-right: 12px; transform: scale(1.2);">
                                <div>
                                    <strong style="display: block; color: #1e1e1e; font-size: 14px;">
                                        <?php _e('WordPress Core Updates', 'voxel-toolkit'); ?>
                                    </strong>
                                    <span style="color: #666; font-size: 13px;">
                                        <?php _e('Prevent WordPress core from updating automatically (includes major and minor updates)', 'voxel-toolkit'); ?>
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Warning -->
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; font-size: 14px; margin-bottom: 20px;">
                        <strong style="color: #856404;"><?php _e('⚠️ Important Security Notice:', 'voxel-toolkit'); ?></strong>
                        <p style="margin: 8px 0 0 0; color: #856404;">
                            <?php _e('Disabling automatic updates means you\'ll need to manually update plugins, themes, and WordPress core. Make sure to regularly check for and install updates to maintain security.', 'voxel-toolkit'); ?>
                        </p>
                    </div>
                    
                    <!-- What Gets Disabled -->
                    <div style="background: #e7f6ff; border: 1px solid #b3d9ff; border-radius: 4px; padding: 15px; font-size: 14px; margin-bottom: 20px;">
                        <strong style="color: #0066cc;"><?php _e('Technical Details:', 'voxel-toolkit'); ?></strong>
                        <ul style="margin: 8px 0 0 20px; color: #0066cc;">
                            <li><?php _e('Plugin Updates: Uses add_filter(\'auto_update_plugin\', \'__return_false\')', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Theme Updates: Uses add_filter(\'auto_update_theme\', \'__return_false\')', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Core Updates: Uses multiple filters and WP_AUTO_UPDATE_CORE constant', 'voxel-toolkit'); ?></li>
                        </ul>
                    </div>
                    
                    <!-- Manual Updates Note -->
                    <div style="background: #fff; border: 1px solid #e1e5e9; border-radius: 6px; padding: 15px;">
                        <h4 style="margin: 0 0 10px 0; color: #1e1e1e; font-size: 14px;">
                            <span class="dashicons dashicons-update" style="margin-right: 5px;"></span>
                            <?php _e('Manual Updates', 'voxel-toolkit'); ?>
                        </h4>
                        <p style="margin: 0; color: #666; font-size: 13px;">
                            <?php _e('You can still update manually from the WordPress admin dashboard. Go to Dashboard > Updates to see and install available updates when you\'re ready.', 'voxel-toolkit'); ?>
                        </p>
                    </div>
                </div>
            </td>
        </tr>
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
        <tr>
            <th scope="row">
                <label><?php _e('Redirect Posts Settings', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; max-width: 700px;">
                    <!-- How it works -->
                    <div style="padding: 15px; background: #f8f9fa; border-left: 3px solid #2271b1; border-radius: 4px; font-size: 14px; margin-bottom: 20px;">
                        <strong><?php _e('How it works:', 'voxel-toolkit'); ?></strong>
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
                </div>
            </td>
        </tr>
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
        <tr>
            <th scope="row">
                <label><?php _e('Auto Promotion Settings', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; max-width: 800px;">
                    <!-- How it works -->
                    <div style="padding: 15px; background: #f8f9fa; border-left: 3px solid #2271b1; border-radius: 4px; font-size: 14px; margin-bottom: 20px;">
                        <strong><?php _e('How it works:', 'voxel-toolkit'); ?></strong>
                        <?php _e('When a post is published, it automatically gets boosted with a higher priority ranking for the duration you specify. After that time expires, it returns to normal ranking. Perfect for giving new content initial visibility.', 'voxel-toolkit'); ?>
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
                </div>
            </td>
        </tr>
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
        <tr>
            <th scope="row">
                <label><?php _e('Custom Submission Messages Settings', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; max-width: 800px;">
                    <!-- How it works -->
                    <div style="padding: 15px; background: #f8f9fa; border-left: 3px solid #2271b1; border-radius: 4px; font-size: 14px; margin-bottom: 20px;">
                        <strong><?php _e('How it works:', 'voxel-toolkit'); ?></strong>
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
                    
                </div>
            </td>
        </tr>
        
        <script type="text/javascript">
        function toggleCustomMessageSection(postType) {
            var checkbox = document.getElementById('custom_msg_' + postType + '_enabled');
            var settings = document.getElementById('custom_msg_' + postType + '_settings');
            
            if (checkbox.checked) {
                settings.style.display = 'block';
            } else {
                settings.style.display = 'none';
            }
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
        error_log('Voxel Toolkit: Featured Posts settings received: ' . print_r($settings, true));
        
        $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array();
        $priority_values = isset($settings['priority_values']) ? $settings['priority_values'] : array();
        
        // Get all public post types
        $post_types = get_post_types(array('public' => true), 'objects');
        unset($post_types['attachment']); // Remove attachments
        ?>
                    <tr>
                        <th scope="row">
                            <label><?php _e('Enable for Post Types', 'voxel-toolkit'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <?php foreach ($post_types as $post_type_key => $post_type_obj): ?>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input 
                                            type="checkbox" 
                                            name="voxel_toolkit_options[featured_posts][post_types][]" 
                                            value="<?php echo esc_attr($post_type_key); ?>"
                                            <?php checked(in_array($post_type_key, $enabled_post_types)); ?>
                                            class="featured-post-type-toggle"
                                            data-post-type="<?php echo esc_attr($post_type_key); ?>"
                                        />
                                        <span class="dashicons <?php echo esc_attr($this->get_post_type_icon($post_type_key)); ?>"></span>
                                        <?php echo esc_html($post_type_obj->labels->name); ?>
                                        <em>(<?php echo esc_html($post_type_key); ?>)</em>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description">
                                <?php _e('Select which post types should have featured functionality enabled.', 'voxel-toolkit'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Priority Values', 'voxel-toolkit'); ?></label>
                        </th>
                        <td>
                            <div id="priority-values-container">
                                <?php foreach ($post_types as $post_type_key => $post_type_obj): ?>
                                    <?php 
                                    $is_enabled = in_array($post_type_key, $enabled_post_types);
                                    $priority_value = isset($priority_values[$post_type_key]) ? $priority_values[$post_type_key] : 10;
                                    ?>
                                    <div class="priority-value-row priority-row-<?php echo esc_attr($post_type_key); ?>" 
                                         style="<?php echo $is_enabled ? '' : 'display: none;'; ?> margin-bottom: 10px;">
                                        <label style="display: inline-block; min-width: 150px;">
                                            <span class="dashicons <?php echo esc_attr($this->get_post_type_icon($post_type_key)); ?>"></span>
                                            <?php echo esc_html($post_type_obj->labels->name); ?>:
                                        </label>
                                        <input 
                                            type="number" 
                                            name="voxel_toolkit_options[featured_posts][priority_values][<?php echo esc_attr($post_type_key); ?>]" 
                                            value="<?php echo esc_attr($priority_value); ?>"
                                            min="1"
                                            max="999"
                                            style="width: 80px;"
                                        />
                                        <span class="description" style="margin-left: 10px;">
                                            <?php _e('Voxel Priority value for featured posts', 'voxel-toolkit'); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="description">
                                <?php _e('Set the Voxel Priority meta value that will be used when posts are marked as featured. Higher numbers typically mean higher priority in listings.', 'voxel-toolkit'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td colspan="2">
                            <div class="voxel-instructions" style="margin-top: 20px;">
                                <h4><?php _e('How to Use', 'voxel-toolkit'); ?></h4>
                                <ul style="list-style-type: disc; margin-left: 20px;">
                                    <li><?php _e('Enable featured functionality for desired post types above', 'voxel-toolkit'); ?></li>
                                    <li><?php _e('Set the priority value that will be assigned to featured posts', 'voxel-toolkit'); ?></li>
                                    <li><?php _e('Go to the post list page for any enabled post type', 'voxel-toolkit'); ?></li>
                                    <li><?php _e('Click the star icon next to any post to make it featured', 'voxel-toolkit'); ?></li>
                                    <li><?php _e('Use the "Featured" filter dropdown to show only featured or non-featured posts', 'voxel-toolkit'); ?></li>
                                    <li><?php _e('Use bulk actions to make multiple posts featured or remove featured status', 'voxel-toolkit'); ?></li>
                                </ul>
                            </div>
                            
                            <div class="voxel-tip" style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 10px; margin: 15px 0;">
                                <span style="font-size: 18px;">💡</span>
                                <strong><?php _e('Tip:', 'voxel-toolkit'); ?></strong>
                                <?php _e('The priority values set the Voxel Priority meta field which is used by Voxel theme to determine post ranking in listings. Higher values typically appear first.', 'voxel-toolkit'); ?>
                            </div>
                        </td>
                    </tr>
        
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
     * Render settings for Duplicate Title Checker
     */
    public function render_duplicate_title_checker_settings($settings) {
        // Get duplicate title checker instance
        if (isset($this->active_functions['duplicate_title_checker'])) {
            $instance = $this->active_functions['duplicate_title_checker'];
            $instance->render_settings($settings);
        }
    }
}