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
                'version' => '1.0'
            ),
            'admin_menu_hide' => array(
                'name' => __('Admin Menu', 'voxel-toolkit'),
                'description' => __('Hide specific admin menu items from the WordPress admin interface.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Admin_Menu_Hide',
                'file' => 'functions/class-admin-menu-hide.php',
                'settings_callback' => array($this, 'render_admin_menu_hide_settings'),
                'version' => '1.0'
            ),
            'admin_bar_publish' => array(
                'name' => __('Admin Bar Publish Toggle', 'voxel-toolkit'),
                'description' => __('Add Publish/Mark as Pending button in the admin bar for quick status changes.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Admin_Bar_Publish',
                'file' => 'functions/class-admin-bar-publish.php',
                'settings_callback' => array($this, 'render_admin_bar_publish_settings'),
                'version' => '1.0'
            ),
            'sticky_admin_bar' => array(
                'name' => __('Sticky Admin Bar', 'voxel-toolkit'),
                'description' => __('Make the WordPress admin bar sticky (fixed) instead of static on the frontend.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Sticky_Admin_Bar',
                'file' => 'functions/class-sticky-admin-bar.php',
                'version' => '1.0'
            ),
            'delete_post_media' => array(
                'name' => __('Delete Post Media', 'voxel-toolkit'),
                'description' => __('Automatically delete all attached media when a post is deleted, with double confirmation.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Delete_Post_Media',
                'file' => 'functions/class-delete-post-media.php',
                'settings_callback' => array($this, 'render_delete_post_media_settings'),
                'version' => '1.0'
            ),
            'light_mode' => array(
                'name' => __('Light Mode', 'voxel-toolkit'),
                'description' => __('Enable light mode styling for the Voxel admin interface.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Light_Mode',
                'file' => 'functions/class-light-mode.php',
                'version' => '1.0'
            ),
            'membership_notifications' => array(
                'name' => __('Membership Notifications', 'voxel-toolkit'),
                'description' => __('Send email notifications to users based on membership expiration dates.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Membership_Notifications',
                'file' => 'functions/class-membership-notifications.php',
                'settings_callback' => array($this, 'render_membership_notifications_settings'),
                'version' => '1.0'
            ),
            'guest_view' => array(
                'name' => __('Guest View', 'voxel-toolkit'),
                'description' => __('Allow logged-in users to temporarily view the site as a guest with an Elementor widget and admin bar button.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Guest_View',
                'file' => 'functions/class-guest-view.php',
                'settings_callback' => array($this, 'render_guest_view_settings'),
                'version' => '1.1'
            ),
            'password_visibility_toggle' => array(
                'name' => __('Password Visibility Toggle', 'voxel-toolkit'),
                'description' => __('Add eye icons to password fields to show/hide password text site-wide.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Password_Visibility_Toggle',
                'file' => 'functions/class-password-visibility-toggle.php',
                'settings_callback' => array($this, 'render_password_visibility_toggle_settings'),
                'version' => '1.0'
            ),
            'ai_review_summary' => array(
                'name' => __('AI Review Summary', 'voxel-toolkit'),
                'description' => __('Generate AI-powered review summaries and category opinions using ChatGPT API with caching.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_AI_Review_Summary',
                'file' => 'functions/class-ai-review-summary.php',
                'settings_callback' => array($this, 'render_ai_review_summary_settings'),
                'version' => '1.0'
            ),
            'show_field_description' => array(
                'name' => __('Show Field Description', 'voxel-toolkit'),
                'description' => __('Display form field descriptions as subtitles below labels instead of tooltip icons.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Show_Field_Description',
                'file' => 'functions/class-show-field-description.php',
                'settings_callback' => array($this, 'render_show_field_description_settings'),
                'version' => '1.0'
            ),
            'duplicate_post' => array(
                'name' => __('Duplicate Post/Page', 'voxel-toolkit'),
                'description' => __('Enable post/page duplication with quick actions and edit screen button for selected post types.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Duplicate_Post',
                'file' => 'functions/class-duplicate-post.php',
                'settings_callback' => array($this, 'render_duplicate_post_settings'),
                'version' => '1.1'
            ),
            'pending_posts_badge' => array(
                'name' => __('Pending Posts Badge', 'voxel-toolkit'),
                'description' => __('Add badges with pending post counts to admin menu items for selected post types with customizable styling.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Pending_Posts_Badge',
                'file' => 'functions/class-pending-posts-badge.php',
                'settings_callback' => array($this, 'render_pending_posts_badge_settings'),
                'version' => '1.0'
            ),
            'pre_approve_posts' => array(
                'name' => __('Pre-Approve Posts', 'voxel-toolkit'),
                'description' => __('Automatically publish posts from pre-approved users instead of marking them as pending.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Pre_Approve_Posts',
                'file' => 'functions/class-pre-approve-posts.php',
                'settings_callback' => array($this, 'render_pre_approve_posts_settings'),
                'version' => '1.0'
            ),
            'disable_auto_updates' => array(
                'name' => __('Disable Automatic Updates', 'voxel-toolkit'),
                'description' => __('Disable automatic updates for plugins, themes, and WordPress core with individual controls.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Disable_Auto_Updates',
                'file' => 'functions/class-disable-auto-updates.php',
                'settings_callback' => array($this, 'render_disable_auto_updates_settings'),
                'version' => '1.0'
            ),
            'redirect_posts' => array(
                'name' => __('Redirect Posts', 'voxel-toolkit'),
                'description' => __('Automatically redirect posts with specific statuses to specified URLs based on post type with flexible status and expiration detection.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Redirect_Posts',
                'file' => 'functions/class-redirect-posts.php',
                'settings_callback' => array($this, 'render_redirect_posts_settings'),
                'version' => '1.0'
            ),
            'auto_promotion' => array(
                'name' => __('Auto Promotion', 'voxel-toolkit'),
                'description' => __('Automatically boost newly published posts for a set duration to increase their visibility and ranking.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Auto_Promotion',
                'file' => 'functions/class-auto-promotion.php',
                'settings_callback' => array($this, 'render_auto_promotion_settings'),
                'version' => '1.0'
            ),
            'custom_submission_messages' => array(
                'name' => __('Custom Submission Messages', 'voxel-toolkit'),
                'description' => __('Customize confirmation messages shown to users after submitting different post types.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Custom_Submission_Messages',
                'file' => 'functions/class-custom-submission-messages.php',
                'settings_callback' => array($this, 'render_custom_submission_messages_settings'),
                'version' => '1.0'
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
                'settings_callback' => array($this, 'render_weather_widget_settings'),
                'version' => '1.0'
            ),
            'reading_time' => array(
                'name' => __('Reading Time', 'voxel-toolkit'),
                'description' => __('Display estimated reading time for posts with customizable prefix, postfix, and styling options.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Reading_Time_Widget',
                'file' => 'widgets/class-reading-time-widget.php',
                'settings_callback' => array($this, 'render_reading_time_widget_settings'),
                'version' => '1.0'
            ),
            'review_collection' => array(
                'name' => __('Review Collection', 'voxel-toolkit'),
                'description' => __('Display a collection of user reviews with advanced filtering and styling options.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Review_Collection_Widget_Manager',
                'file' => 'widgets/class-review-collection-widget-manager.php',
                'version' => '1.0'
            ),
            'prev_next_widget' => array(
                'name' => __('Previous/Next Navigation', 'voxel-toolkit'),
                'description' => __('Navigate between posts with customizable previous/next buttons and post information display.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Prev_Next_Widget_Manager',
                'file' => 'widgets/class-prev-next-widget-manager.php',
                'version' => '1.0'
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
                    console.log('jQuery loaded and ready'); // Debug log
                    
                    let notificationIndex = <?php echo count($notifications); ?>;
                    let currentTestIndex = 0;
                    
                    console.log('Initial notificationIndex:', notificationIndex); // Debug log
                    console.log('Add button exists:', $('#add-notification-btn').length > 0); // Debug log
                    
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
                        console.log('Add notification button clicked'); // Debug log
                        
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
                        
                        console.log('New row added, notificationIndex is now:', notificationIndex); // Debug log
                    });
                    
                    // Test if button can be found immediately
                    setTimeout(function() {
                        console.log('Delayed check - Add button exists:', $('#add-notification-btn').length > 0);
                        console.log('Button element:', $('#add-notification-btn')[0]);
                    }, 1000);
                    
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
     * Render settings for Weather Widget
     * 
     * @param array $settings Current settings
     */
    public function render_weather_widget_settings($settings) {
        // No additional settings for weather widget
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
}