<?php
/**
 * Password Visibility Toggle functionality
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Password_Visibility_Toggle {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_footer', array($this, 'add_password_toggle_styles'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_script(
            'voxel-toolkit-password-toggle',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/password-visibility-toggle.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );
        
        // Get settings
        $settings = get_option('voxel_toolkit_options', array());
        $password_toggle_settings = isset($settings['password_visibility_toggle']) ? $settings['password_visibility_toggle'] : array();
        
        wp_localize_script('voxel-toolkit-password-toggle', 'voxelToolkitPasswordToggle', array(
            'settings' => $password_toggle_settings,
            'icons' => array(
                'show' => $this->get_eye_icon(),
                'hide' => $this->get_eye_slash_icon()
            )
        ));
    }
    
    /**
     * Add password toggle styles
     */
    public function add_password_toggle_styles() {
        $settings = get_option('voxel_toolkit_options', array());
        $password_toggle_settings = isset($settings['password_visibility_toggle']) ? $settings['password_visibility_toggle'] : array();
        
        // Icon color settings
        $icon_color = isset($password_toggle_settings['icon_color']) ? $password_toggle_settings['icon_color'] : '#666666';
        $icon_hover_color = isset($password_toggle_settings['icon_hover_color']) ? $password_toggle_settings['icon_hover_color'] : '#333333';
        
        ?>
        <style id="voxel-toolkit-password-toggle-styles">
            .voxel-password-field-wrapper {
                position: relative;
                display: inline-block;
                width: 100%;
            }
            
            .voxel-password-toggle-btn {
                position: absolute;
                right: 12px;
                top: 50%;
                transform: translateY(-50%);
                background: none !important;
                border: none !important;
                cursor: pointer;
                padding: 4px !important;
                margin: 0 !important;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10;
                transition: color 0.2s ease;
                color: <?php echo esc_attr($icon_color); ?>;
                font-size: 16px !important;
                line-height: 1 !important;
                box-shadow: none !important;
                text-decoration: none !important;
                outline: none;
            }
            
            .voxel-password-toggle-btn:hover {
                color: <?php echo esc_attr($icon_hover_color); ?>;
            }
            
            .voxel-password-toggle-btn:focus {
                outline: 2px solid #0073aa !important;
                outline-offset: 2px;
                border-radius: 2px !important;
            }
            
            .voxel-password-field-wrapper input[type="password"],
            .voxel-password-field-wrapper input[type="text"] {
                padding-right: 45px !important;
            }
            
            .voxel-password-toggle-icon {
                width: 18px;
                height: 18px;
                display: block;
            }
            
            /* Ensure it works with common form styles */
            .elementor-field-group .voxel-password-field-wrapper input,
            .ts-form .voxel-password-field-wrapper input,
            .vx-form .voxel-password-field-wrapper input {
                padding-right: 45px !important;
            }
        </style>
        <?php
    }
    
    /**
     * Get eye icon SVG
     */
    private function get_eye_icon() {
        return '<svg class="voxel-password-toggle-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    }
    
    /**
     * Get eye-slash icon SVG
     */
    private function get_eye_slash_icon() {
        return '<svg class="voxel-password-toggle-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
    }
}