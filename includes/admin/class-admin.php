<?php
/**
 * Voxel Toolkit Admin Class
 * 
 * Handles admin interface, settings pages, and backend functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Admin {
    
    private static $instance = null;
    private $settings;
    private $functions_manager;
    
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
        $this->settings = Voxel_Toolkit_Settings::instance();
        $this->functions_manager = Voxel_Toolkit_Functions::instance();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Handle AJAX requests
        add_action('wp_ajax_voxel_toolkit_toggle_function', array($this, 'ajax_toggle_function'));
        add_action('wp_ajax_voxel_toolkit_toggle_widget', array($this, 'ajax_toggle_widget'));
        add_action('wp_ajax_voxel_toolkit_reset_settings', array($this, 'ajax_reset_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Voxel Toolkit', 'voxel-toolkit'),
            __('Voxel Toolkit', 'voxel-toolkit'),
            'manage_options',
            'voxel-toolkit',
            array($this, 'render_main_page'),
            'dashicons-admin-tools',
            58
        );
        
        add_submenu_page(
            'voxel-toolkit',
            __('Functions', 'voxel-toolkit'),
            __('Functions', 'voxel-toolkit'),
            'manage_options',
            'voxel-toolkit',
            array($this, 'render_main_page')
        );
        
        add_submenu_page(
            'voxel-toolkit',
            __('Widgets', 'voxel-toolkit'),
            __('Widgets', 'voxel-toolkit'),
            'manage_options',
            'voxel-toolkit-widgets',
            array($this, 'render_widgets_page')
        );
        
        add_submenu_page(
            'voxel-toolkit',
            __('Settings', 'voxel-toolkit'),
            __('Settings', 'voxel-toolkit'),
            'manage_options',
            'voxel-toolkit-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Initialize admin
     */
    public function admin_init() {
        // No need to register settings since we handle saving manually
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'voxel-toolkit') === false) {
            return;
        }
        
        wp_enqueue_script(
            'voxel-toolkit-admin',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );
        
        wp_enqueue_style(
            'voxel-toolkit-admin',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
        
        // Localize script
        wp_localize_script('voxel-toolkit-admin', 'voxelToolkit', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('voxel_toolkit_nonce'),
            'strings' => array(
                'confirmReset' => __('Are you sure you want to reset all settings? This cannot be undone.', 'voxel-toolkit'),
                'functionEnabled' => __('Function enabled successfully.', 'voxel-toolkit'),
                'functionDisabled' => __('Function disabled successfully.', 'voxel-toolkit'),
                'settingsReset' => __('Settings have been reset to defaults.', 'voxel-toolkit'),
                'error' => __('An error occurred. Please try again.', 'voxel-toolkit')
            )
        ));
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        if (isset($_GET['settings-updated']) && $_GET['page'] === 'voxel-toolkit-settings') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Settings saved successfully.', 'voxel-toolkit'); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Render main functions page
     */
    public function render_main_page() {
        $available_functions = $this->functions_manager->get_available_functions();
        
        // Sort functions alphabetically by name
        uasort($available_functions, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
        ?>
        <div class="wrap">
            <h1><?php _e('Voxel Toolkit - Functions', 'voxel-toolkit'); ?></h1>
            
            <div class="voxel-toolkit-intro">
                <p><?php _e('Welcome to Voxel Toolkit! This plugin provides additional functionality for your Voxel theme. Toggle functions on/off and configure their settings below.', 'voxel-toolkit'); ?></p>
            </div>
            
            <div class="voxel-toolkit-search">
                <div class="search-container">
                    <input type="text" 
                           id="voxel-toolkit-search" 
                           class="search-input" 
                           placeholder="<?php _e('Search functions...', 'voxel-toolkit'); ?>" 
                           autocomplete="off">
                    <button type="button" 
                            id="voxel-toolkit-search-reset" 
                            class="button button-secondary search-reset"
                            title="<?php _e('Reset search', 'voxel-toolkit'); ?>">
                        <?php _e('Reset', 'voxel-toolkit'); ?>
                    </button>
                </div>
                <div class="search-results-info" id="search-results-info"></div>
            </div>
            
            <div class="voxel-toolkit-functions" id="voxel-toolkit-functions">
                <?php foreach ($available_functions as $function_key => $function_data): ?>
                    <?php $this->render_function_card($function_key, $function_data); ?>
                <?php endforeach; ?>
            </div>
            
            <div class="voxel-toolkit-actions">
                <a href="<?php echo admin_url('admin.php?page=voxel-toolkit-settings'); ?>" class="button button-secondary">
                    <?php _e('Advanced Settings', 'voxel-toolkit'); ?>
                </a>
                
                <button type="button" class="button button-secondary" id="reset-all-settings">
                    <?php _e('Reset All Settings', 'voxel-toolkit'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render function card
     * 
     * @param string $function_key Function key
     * @param array $function_data Function data
     */
    private function render_function_card($function_key, $function_data) {
        $is_enabled = $this->settings->is_function_enabled($function_key);
        $is_active = $this->functions_manager->is_function_active($function_key);
        $settings_url = admin_url("admin.php?page=voxel-toolkit-settings#section-{$function_key}");
        ?>
        <div class="voxel-toolkit-function-card <?php echo $is_enabled ? 'enabled' : 'disabled'; ?>"
             data-function-key="<?php echo esc_attr($function_key); ?>"
             data-function-name="<?php echo esc_attr(strtolower($function_data['name'])); ?>"
             data-function-description="<?php echo esc_attr(strtolower($function_data['description'])); ?>">
            <div class="function-header">
                <h3><?php echo esc_html($function_data['name']); ?></h3>
                <div class="function-toggle">
                    <label class="toggle-switch">
                        <input type="checkbox" 
                               class="function-toggle-checkbox" 
                               data-function="<?php echo esc_attr($function_key); ?>"
                               <?php checked($is_enabled); ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
            
            <div class="function-description">
                <p><?php echo esc_html($function_data['description']); ?></p>
            </div>
            
            <div class="function-status">
                <span class="status-indicator <?php echo $is_active ? 'active' : 'inactive'; ?>">
                    <?php echo $is_active ? __('Active', 'voxel-toolkit') : __('Inactive', 'voxel-toolkit'); ?>
                </span>
                
                <?php if (isset($function_data['version'])): ?>
                    <span class="version">v<?php echo esc_html($function_data['version']); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if ($is_enabled): ?>
                <div class="function-actions">
                    <a href="<?php echo esc_url($settings_url); ?>" class="button button-secondary">
                        <?php _e('Configure', 'voxel-toolkit'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('voxel_toolkit_settings_nonce', 'voxel_toolkit_settings_nonce')) {
            
            $this->handle_settings_save($_POST);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'voxel-toolkit') . '</p></div>';
        }
        
        $available_functions = $this->functions_manager->get_available_functions();
        ?>
        <div class="wrap">
            <h1><?php _e('Voxel Toolkit - Settings', 'voxel-toolkit'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('voxel_toolkit_settings_nonce', 'voxel_toolkit_settings_nonce'); ?>
                
                <div class="voxel-toolkit-settings">
                    <?php foreach ($available_functions as $function_key => $function_data): ?>
                        <?php if ($this->settings->is_function_enabled($function_key)): ?>
                            <?php $this->render_function_settings_section($function_key, $function_data); ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle settings save
     */
    private function handle_settings_save($post_data) {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Get current options to preserve everything
        $current_options = get_option('voxel_toolkit_options', array());
        
        // Handle AI Review Summary API key specifically
        if (isset($post_data['ai_api_key']) && !empty(trim($post_data['ai_api_key']))) {
            $api_key = sanitize_text_field(trim($post_data['ai_api_key']));
            
            // Initialize ai_review_summary settings if not exists
            if (!isset($current_options['ai_review_summary'])) {
                $current_options['ai_review_summary'] = array();
            }
            
            // Always ensure the function is enabled when saving API key
            $current_options['ai_review_summary']['enabled'] = true;
            $current_options['ai_review_summary']['api_key'] = $api_key;
            
            // Update the options
            update_option('voxel_toolkit_options', $current_options);
            
            // Refresh the settings cache
            $this->settings->refresh_options();
        }
        
        // Process any other voxel_toolkit_options if they exist
        if (isset($post_data['voxel_toolkit_options'])) {
            foreach ($post_data['voxel_toolkit_options'] as $function_key => $function_settings) {
                if (!isset($current_options[$function_key])) {
                    $current_options[$function_key] = array();
                }
                
                // IMPORTANT: Preserve the 'enabled' status when saving settings
                // Only merge the new settings, don't overwrite the enabled status
                $enabled_status = isset($current_options[$function_key]['enabled']) ? $current_options[$function_key]['enabled'] : false;
                
                // Merge settings, preserving existing ones
                $current_options[$function_key] = array_merge($current_options[$function_key], $function_settings);
                
                // Ensure the enabled status is preserved
                $current_options[$function_key]['enabled'] = $enabled_status;
            }
            
            // Update the options
            update_option('voxel_toolkit_options', $current_options);
            
            // Refresh the settings cache
            $this->settings->refresh_options();
        }
    }
    
    /**
     * Render function settings section
     * 
     * @param string $function_key Function key
     * @param array $function_data Function data
     */
    private function render_function_settings_section($function_key, $function_data) {
        $function_settings = $this->settings->get_function_settings($function_key, array());
        ?>
        <div class="settings-section" id="section-<?php echo esc_attr($function_key); ?>">
            <h2><?php echo esc_html($function_data['name']); ?> <?php _e('Settings', 'voxel-toolkit'); ?></h2>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <?php
                    // Call custom settings callback if available
                    if (isset($function_data['settings_callback']) && is_callable($function_data['settings_callback'])) {
                        call_user_func($function_data['settings_callback'], $function_settings);
                    } else {
                        // If no custom settings, show a message
                        ?>
                        <tr>
                            <td colspan="2">
                                <p class="description"><?php echo esc_html($function_data['description']); ?></p>
                                <p><?php _e('This function is currently enabled and active. No additional configuration is required.', 'voxel-toolkit'); ?></p>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Sanitize options
     * 
     * @param array $input Raw input options
     * @return array Sanitized options
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        if (!is_array($input)) {
            return $sanitized;
        }
        
        
        $available_functions = $this->functions_manager->get_available_functions();
        
        foreach ($available_functions as $function_key => $function_data) {
            if (isset($input[$function_key])) {
                $function_input = $input[$function_key];
                $sanitized_function = array();
                
                // Sanitize enabled field
                $sanitized_function['enabled'] = !empty($function_input['enabled']);
                
                try {
                
                // Sanitize function-specific settings
                switch ($function_key) {
                    case 'auto_verify_posts':
                        if (isset($function_input['post_types']) && is_array($function_input['post_types'])) {
                            $sanitized_function['post_types'] = array_map('sanitize_text_field', $function_input['post_types']);
                        } else {
                            $sanitized_function['post_types'] = array();
                        }
                        break;
                    
                    case 'admin_menu_hide':
                        if (isset($function_input['hidden_menus']) && is_array($function_input['hidden_menus'])) {
                            // Validate menu keys against allowed values
                            $allowed_menus = array('structure', 'membership');
                            $sanitized_function['hidden_menus'] = array_intersect(
                                array_map('sanitize_text_field', $function_input['hidden_menus']),
                                $allowed_menus
                            );
                        } else {
                            $sanitized_function['hidden_menus'] = array();
                        }
                        break;
                    
                    
                    case 'admin_bar_publish':
                        if (isset($function_input['post_types']) && is_array($function_input['post_types'])) {
                            $sanitized_function['post_types'] = array_map('sanitize_text_field', $function_input['post_types']);
                        } else {
                            $sanitized_function['post_types'] = array();
                        }
                        break;
                    
                    case 'delete_post_media':
                        if (isset($function_input['post_types']) && is_array($function_input['post_types'])) {
                            $sanitized_function['post_types'] = array_map('sanitize_text_field', $function_input['post_types']);
                        } else {
                            $sanitized_function['post_types'] = array();
                        }
                        break;
                    
                    case 'membership_notifications':
                        if (isset($function_input['notifications']) && is_array($function_input['notifications'])) {
                            $sanitized_notifications = array();
                            foreach ($function_input['notifications'] as $notification) {
                                if (is_array($notification)) {
                                    // Allow notifications to be saved even if partially filled
                                    // This enables users to add new rows and fill them in before saving
                                    $unit = isset($notification['unit']) ? sanitize_text_field($notification['unit']) : 'days';
                                    $value = isset($notification['value']) ? intval($notification['value']) : 0;
                                    $subject = isset($notification['subject']) ? sanitize_text_field($notification['subject']) : '';
                                    $body = isset($notification['body']) ? wp_kses_post($notification['body']) : '';
                                    
                                    // Only skip completely empty notifications (no value at all)
                                    if ($value > 0 || !empty($subject) || !empty($body)) {
                                        $sanitized_notifications[] = array(
                                            'unit' => $unit,
                                            'value' => $value,
                                            'subject' => $subject,
                                            'body' => $body
                                        );
                                    }
                                }
                            }
                            $sanitized_function['notifications'] = $sanitized_notifications;
                        } else {
                            $sanitized_function['notifications'] = array();
                        }
                        break;
                    
                    case 'guest_view':
                        // Sanitize guest view settings
                        $sanitized_function['show_confirmation'] = !empty($function_input['show_confirmation']);
                        $sanitized_function['auto_exit_timeout'] = !empty($function_input['auto_exit_timeout']);
                        
                        // Position setting
                        if (isset($function_input['button_position']) && in_array($function_input['button_position'], ['top-left', 'top-right', 'middle-left', 'middle-right', 'bottom-left', 'bottom-right'])) {
                            $sanitized_function['button_position'] = sanitize_text_field($function_input['button_position']);
                        } else {
                            $sanitized_function['button_position'] = 'bottom-right';
                        }
                        
                        // Color settings
                        $color_fields = ['bg_color', 'text_color'];
                        $default_colors = [
                            'bg_color' => '#667eea',
                            'text_color' => '#ffffff'
                        ];
                        
                        foreach ($color_fields as $field) {
                            if (isset($function_input[$field]) && preg_match('/^#[0-9a-fA-F]{6}$/', $function_input[$field])) {
                                $sanitized_function[$field] = sanitize_hex_color($function_input[$field]);
                            } else {
                                $sanitized_function[$field] = $default_colors[$field];
                            }
                        }
                        break;
                    
                    case 'password_visibility_toggle':
                        // Icon color settings
                        $color_fields = ['icon_color', 'icon_hover_color'];
                        $default_colors = [
                            'icon_color' => '#666666',
                            'icon_hover_color' => '#333333'
                        ];
                        
                        foreach ($color_fields as $field) {
                            if (isset($function_input[$field]) && preg_match('/^#[0-9a-fA-F]{6}$/', $function_input[$field])) {
                                $sanitized_function[$field] = sanitize_hex_color($function_input[$field]);
                            } else {
                                $sanitized_function[$field] = $default_colors[$field];
                            }
                        }
                        break;
                    
                    case 'ai_review_summary':
                        // API key setting
                        $sanitized_function['api_key'] = isset($function_input['api_key']) ? 
                            sanitize_text_field($function_input['api_key']) : '';
                        break;
                    
                    case 'show_field_description':
                        // No additional settings needed - just enabled/disabled
                        break;
                    
                    default:
                        // Allow filtering for custom functions
                        $sanitized_function = apply_filters(
                            "voxel_toolkit/sanitize_function_settings/{$function_key}",
                            $sanitized_function,
                            $function_input
                        );
                        break;
                }
                
                } catch (Exception $e) {
                    // Log error but don't break the sanitization process
                    error_log('Voxel Toolkit sanitization error for ' . $function_key . ': ' . $e->getMessage());
                    // Ensure we still have a valid sanitized function array
                    if (!isset($sanitized_function['enabled'])) {
                        $sanitized_function['enabled'] = false;
                    }
                }
                
                $sanitized[$function_key] = $sanitized_function;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Handle AJAX function toggle
     */
    public function ajax_toggle_function() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'voxel_toolkit_nonce')) {
            wp_die(__('Security check failed.', 'voxel-toolkit'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'voxel-toolkit'));
        }
        
        $function_key = sanitize_text_field($_POST['function']);
        $enabled = !empty($_POST['enabled']);
        
        // Validate function exists
        $available_functions = $this->functions_manager->get_available_functions();
        if (!isset($available_functions[$function_key])) {
            wp_send_json_error(__('Invalid function.', 'voxel-toolkit'));
        }
        
        // Toggle function
        if ($enabled) {
            $result = $this->settings->enable_function($function_key);
            // Force settings refresh
            $this->settings->refresh_options();
        } else {
            $result = $this->settings->disable_function($function_key);
            // Force settings refresh
            $this->settings->refresh_options();
        }
        
        // Double-check the status after toggle
        $actual_status = $this->settings->is_function_enabled($function_key);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => $enabled ? __('Function enabled.', 'voxel-toolkit') : __('Function disabled.', 'voxel-toolkit'),
                'function' => $function_key,
                'enabled' => $enabled,
                'actual_status' => $actual_status,  // Debug info
                'debug' => array(
                    'requested' => $enabled,
                    'actual' => $actual_status,
                    'result' => $result
                )
            ));
        } else {
            wp_send_json_error(__('Failed to update function status.', 'voxel-toolkit'));
        }
    }
    
    /**
     * Handle AJAX widget toggle
     */
    public function ajax_toggle_widget() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'voxel_toolkit_widget_nonce')) {
            wp_send_json_error(__('Security check failed.', 'voxel-toolkit'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions.', 'voxel-toolkit'));
        }
        
        $widget_key = sanitize_text_field($_POST['widget_key']);
        $enabled = intval($_POST['enabled']);
        
        $widget_key_full = 'widget_' . $widget_key;
        
        if ($enabled) {
            $result = $this->settings->enable_function($widget_key_full);
        } else {
            $result = $this->settings->disable_function($widget_key_full);
        }
        
        if ($result) {
            wp_send_json_success(array(
                'message' => $enabled ? __('Widget enabled.', 'voxel-toolkit') : __('Widget disabled.', 'voxel-toolkit')
            ));
        } else {
            wp_send_json_error(__('Failed to update widget status.', 'voxel-toolkit'));
        }
    }
    
    /**
     * Handle AJAX settings reset
     */
    public function ajax_reset_settings() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'voxel_toolkit_nonce')) {
            wp_die(__('Security check failed.', 'voxel-toolkit'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'voxel-toolkit'));
        }
        
        $result = $this->settings->reset_settings();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Settings reset successfully.', 'voxel-toolkit')
            ));
        } else {
            wp_send_json_error(__('Failed to reset settings.', 'voxel-toolkit'));
        }
    }
    
    /**
     * Render widgets page
     */
    public function render_widgets_page() {
        $available_widgets = $this->functions_manager->get_available_widgets();
        ?>
        <div class="wrap">
            <h1><?php _e('Voxel Toolkit - Elementor Widgets', 'voxel-toolkit'); ?></h1>
            
            <div class="voxel-toolkit-intro">
                <p><?php _e('Enhance your Elementor page builder with these additional widgets. Each widget can be enabled/disabled independently and includes comprehensive styling controls.', 'voxel-toolkit'); ?></p>
            </div>
            
            <div class="voxel-toolkit-widgets">
                <?php foreach ($available_widgets as $widget_key => $widget_data): ?>
                    <?php $this->render_widget_card($widget_key, $widget_data); ?>
                <?php endforeach; ?>
                
                <?php if (empty($available_widgets)): ?>
                    <div class="voxel-toolkit-no-widgets">
                        <p><?php _e('No widgets are currently available. More widgets will be added in future updates!', 'voxel-toolkit'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
            .voxel-toolkit-widgets {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin-top: 20px;
            }
            
            @media (max-width: 1200px) {
                .voxel-toolkit-widgets {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
            
            @media (max-width: 768px) {
                .voxel-toolkit-widgets {
                    grid-template-columns: 1fr;
                }
            }
            .voxel-toolkit-widget-card {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 12px 12px 8px 12px;
                transition: border-color 0.2s ease;
            }
            .voxel-toolkit-widget-card:hover {
                border-color: #999;
            }
            .voxel-toolkit-widget-content {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .voxel-toolkit-widget-title {
                font-size: 14px;
                font-weight: 500;
                margin: 0;
                color: #1e1e1e;
            }
            .voxel-toolkit-widget-toggle {
                position: relative;
                display: inline-block;
                width: 40px;
                height: 20px;
            }
            .voxel-toolkit-widget-toggle input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .voxel-toolkit-widget-toggle-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: .2s;
                border-radius: 20px;
            }
            .voxel-toolkit-widget-toggle-slider:before {
                position: absolute;
                content: "";
                height: 16px;
                width: 16px;
                left: 2px;
                bottom: 2px;
                background-color: white;
                transition: .2s;
                border-radius: 50%;
            }
            .voxel-toolkit-widget-toggle input:checked + .voxel-toolkit-widget-toggle-slider {
                background-color: #2271b1;
            }
            .voxel-toolkit-widget-toggle input:checked + .voxel-toolkit-widget-toggle-slider:before {
                transform: translateX(20px);
            }
            .voxel-toolkit-widget-settings {
                margin-top: 12px;
            }
            .voxel-toolkit-no-widgets {
                background: #f8f9fa;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                padding: 40px 20px;
                text-align: center;
                color: #666;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.voxel-toolkit-widget-toggle input').on('change', function() {
                const widgetKey = $(this).data('widget');
                const enabled = $(this).is(':checked');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'voxel_toolkit_toggle_widget',
                        widget_key: widgetKey,
                        enabled: enabled ? 1 : 0,
                        nonce: '<?php echo wp_create_nonce('voxel_toolkit_widget_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            const message = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                            $('.wrap h1').after(message);
                            setTimeout(function() {
                                message.fadeOut();
                            }, 3000);
                        } else {
                            alert('Error: ' + response.data.message);
                            // Revert toggle
                            $(this).prop('checked', !enabled);
                        }
                    }.bind(this),
                    error: function() {
                        alert('Error updating widget status');
                        // Revert toggle
                        $(this).prop('checked', !enabled);
                    }.bind(this)
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render widget card
     * 
     * @param string $widget_key Widget key
     * @param array $widget_data Widget data
     */
    private function render_widget_card($widget_key, $widget_data) {
        $widget_key_full = 'widget_' . $widget_key;
        $is_enabled = $this->settings->is_function_enabled($widget_key_full);
        ?>
        <div class="voxel-toolkit-widget-card" title="<?php echo esc_attr($widget_data['description']); ?>">
            <div class="voxel-toolkit-widget-content">
                <h3 class="voxel-toolkit-widget-title"><?php echo esc_html($widget_data['name']); ?></h3>
                <label class="voxel-toolkit-widget-toggle">
                    <input type="checkbox" 
                           data-widget="<?php echo esc_attr($widget_key); ?>" 
                           <?php checked($is_enabled); ?>>
                    <span class="voxel-toolkit-widget-toggle-slider"></span>
                </label>
            </div>
            
            <?php if ($is_enabled && isset($widget_data['settings_callback']) && is_callable($widget_data['settings_callback'])): ?>
                <div class="voxel-toolkit-widget-settings">
                    <?php
                    $widget_settings = $this->settings->get_function_settings($widget_key_full, array());
                    echo '<table class="form-table" role="presentation">';
                    call_user_func($widget_data['settings_callback'], $widget_settings);
                    echo '</table>';
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}