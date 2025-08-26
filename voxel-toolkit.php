<?php
/**
 * Plugin Name: Voxel Toolkit
 * Plugin URI: https://your-domain.com/voxel-toolkit
 * Description: A comprehensive toolkit for extending Voxel theme functionality with toggleable features and customizable settings.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: voxel-toolkit
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VOXEL_TOOLKIT_VERSION', '1.0.0');
define('VOXEL_TOOLKIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VOXEL_TOOLKIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VOXEL_TOOLKIT_PLUGIN_FILE', __FILE__);

/**
 * Main Voxel Toolkit Class
 */
class Voxel_Toolkit {
    
    private static $instance = null;
    
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
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Delay theme check until after themes are loaded
        if (did_action('after_setup_theme')) {
            $this->check_theme_and_init();
        } else {
            add_action('after_setup_theme', array($this, 'check_theme_and_init'), 20);
        }
    }
    
    /**
     * Check theme and initialize if valid
     */
    public function check_theme_and_init() {
        // Check if Voxel theme is active
        if (!$this->is_voxel_theme_active()) {
            add_action('admin_notices', array($this, 'voxel_theme_required_notice'));
            return;
        }
        
        // Load plugin components
        $this->load_includes();
        $this->init_hooks();
        
        // Initialize admin if in admin area
        if (is_admin()) {
            $this->init_admin();
        }
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('voxel-toolkit', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Check if Voxel theme is active
     */
    private function is_voxel_theme_active() {
        // Make sure we can safely call wp_get_theme()
        if (!function_exists('wp_get_theme')) {
            return false;
        }
        
        $theme = wp_get_theme();
        
        // Check both theme name and template for child theme compatibility
        $theme_name = $theme->get('Name');
        $template = $theme->get_template();
        $stylesheet = $theme->get_stylesheet();
        
        return (
            $theme_name === 'Voxel' || 
            $template === 'voxel' || 
            $stylesheet === 'voxel' ||
            strpos(strtolower($theme_name), 'voxel') !== false
        );
    }
    
    /**
     * Show notice if Voxel theme is not active
     */
    public function voxel_theme_required_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('Voxel Toolkit requires the Voxel theme to be active.', 'voxel-toolkit');
        echo '</p></div>';
    }
    
    /**
     * Load includes
     */
    private function load_includes() {
        $files = array(
            'includes/class-settings.php',
            'includes/class-functions.php',
            'includes/functions/class-auto-verify-posts.php',
            'includes/functions/class-admin-menu-hide.php',
            'includes/functions/class-light-mode.php'
        );
        
        if (is_admin()) {
            $files[] = 'includes/admin/class-admin.php';
        }
        
        foreach ($files as $file) {
            $file_path = VOXEL_TOOLKIT_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log("Voxel Toolkit: Required file missing - {$file}");
            }
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        try {
            // Initialize settings
            if (class_exists('Voxel_Toolkit_Settings')) {
                Voxel_Toolkit_Settings::instance();
            }
            
            // Initialize functions manager
            if (class_exists('Voxel_Toolkit_Functions')) {
                Voxel_Toolkit_Functions::instance();
            }
        } catch (Exception $e) {
            error_log('Voxel Toolkit: Error initializing hooks - ' . $e->getMessage());
        }
    }
    
    /**
     * Initialize admin
     */
    private function init_admin() {
        try {
            if (class_exists('Voxel_Toolkit_Admin')) {
                Voxel_Toolkit_Admin::instance();
            }
        } catch (Exception $e) {
            error_log('Voxel Toolkit: Error initializing admin - ' . $e->getMessage());
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $default_options = array(
            'auto_verify_posts' => array(
                'enabled' => false,
                'post_types' => array()
            ),
            'admin_menu_hide' => array(
                'enabled' => false,
                'hidden_menus' => array()
            ),
            'light_mode' => array(
                'enabled' => false,
                'color_scheme' => 'auto',
                'custom_accent' => '#2271b1'
            )
        );
        
        add_option('voxel_toolkit_options', $default_options);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize plugin
Voxel_Toolkit::instance();