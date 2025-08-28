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
        $this->init_active_functions();
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
            )
        );
        
        // Allow other plugins/themes to register functions
        $this->available_functions = apply_filters('voxel_toolkit/available_functions', $this->available_functions);
    }
    
    /**
     * Initialize active functions
     */
    private function init_active_functions() {
        $settings = Voxel_Toolkit_Settings::instance();
        
        foreach ($this->available_functions as $function_key => $function_data) {
            if ($settings->is_function_enabled($function_key)) {
                $this->init_function($function_key, $function_data);
            }
        }
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
            $this->active_functions[$function_key] = new $function_data['class']();
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
}