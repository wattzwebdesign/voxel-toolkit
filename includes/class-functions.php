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
                'version' => '1.0.0'
            ),
            'admin_menu_hide' => array(
                'name' => __('Admin Menu', 'voxel-toolkit'),
                'description' => __('Hide specific admin menu items from the WordPress admin interface.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Admin_Menu_Hide',
                'file' => 'functions/class-admin-menu-hide.php',
                'settings_callback' => array($this, 'render_admin_menu_hide_settings'),
                'version' => '1.0.0'
            ),
            'admin_bar_publish' => array(
                'name' => __('Admin Bar Publish Toggle', 'voxel-toolkit'),
                'description' => __('Add Publish/Mark as Pending button in the admin bar for quick status changes.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Admin_Bar_Publish',
                'file' => 'functions/class-admin-bar-publish.php',
                'settings_callback' => array($this, 'render_admin_bar_publish_settings'),
                'version' => '1.0.0'
            ),
            'sticky_admin_bar' => array(
                'name' => __('Sticky Admin Bar', 'voxel-toolkit'),
                'description' => __('Make the WordPress admin bar sticky (fixed) instead of static on the frontend.', 'voxel-toolkit'),
                'class' => 'Voxel_Toolkit_Sticky_Admin_Bar',
                'file' => 'functions/class-sticky-admin-bar.php',
                'version' => '1.0.0'
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
}