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
        // Register settings
        register_setting(
            'voxel_toolkit_options',
            'voxel_toolkit_options',
            array($this, 'sanitize_options')
        );
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
        ?>
        <div class="wrap">
            <h1><?php _e('Voxel Toolkit - Functions', 'voxel-toolkit'); ?></h1>
            
            <div class="voxel-toolkit-intro">
                <p><?php _e('Welcome to Voxel Toolkit! This plugin provides additional functionality for your Voxel theme. Toggle functions on/off and configure their settings below.', 'voxel-toolkit'); ?></p>
            </div>
            
            <div class="voxel-toolkit-functions">
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
        <div class="voxel-toolkit-function-card <?php echo $is_enabled ? 'enabled' : 'disabled'; ?>">
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
        $available_functions = $this->functions_manager->get_available_functions();
        ?>
        <div class="wrap">
            <h1><?php _e('Voxel Toolkit - Settings', 'voxel-toolkit'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('voxel_toolkit_options');
                do_settings_sections('voxel_toolkit_options');
                ?>
                
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
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr($function_key); ?>_enabled">
                                <?php _e('Enable Function', 'voxel-toolkit'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="<?php echo esc_attr($function_key); ?>_enabled" 
                                   name="voxel_toolkit_options[<?php echo esc_attr($function_key); ?>][enabled]" 
                                   value="1" 
                                   <?php checked($function_settings['enabled'] ?? false); ?> />
                            <p class="description"><?php echo esc_html($function_data['description']); ?></p>
                        </td>
                    </tr>
                    
                    <?php
                    // Call custom settings callback if available
                    if (isset($function_data['settings_callback']) && is_callable($function_data['settings_callback'])) {
                        call_user_func($function_data['settings_callback'], $function_settings);
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
                    
                    case 'light_mode':
                        // Validate color scheme
                        $allowed_schemes = array('light', 'dark', 'auto');
                        $color_scheme = isset($function_input['color_scheme']) ? $function_input['color_scheme'] : 'auto';
                        $sanitized_function['color_scheme'] = in_array($color_scheme, $allowed_schemes) ? $color_scheme : 'auto';
                        
                        // Validate custom accent color
                        $custom_accent = isset($function_input['custom_accent']) ? $function_input['custom_accent'] : '#2271b1';
                        if (preg_match('/^#[0-9a-f]{6}$/i', $custom_accent)) {
                            $sanitized_function['custom_accent'] = $custom_accent;
                        } else {
                            $sanitized_function['custom_accent'] = '#2271b1';
                        }
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
        } else {
            $result = $this->settings->disable_function($function_key);
        }
        
        if ($result) {
            wp_send_json_success(array(
                'message' => $enabled ? __('Function enabled.', 'voxel-toolkit') : __('Function disabled.', 'voxel-toolkit')
            ));
        } else {
            wp_send_json_error(__('Failed to update function status.', 'voxel-toolkit'));
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
}