<?php
/**
 * Plugin Name: Voxel Toolkit
 * Plugin URI: https://codewattz.com/voxel-toolkit-plugin/
 * Description: A comprehensive toolkit for extending Voxel theme functionality with toggleable features and customizable settings.
 * Version: 1.1.0.2
 * Author: Code Wattz
 * Author URI: https://codewattz.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: voxel-toolkit
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8.2
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VOXEL_TOOLKIT_VERSION', '1.1.0.2');
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
        
        // Initialize licensing system
        $this->init_licensing();
        
        // Load plugin components
        $this->load_includes();
        $this->init_hooks();
        
        // Initialize admin if in admin area
        if (is_admin()) {
            $this->init_admin();
            $this->init_license_notices();
            $this->init_deactivation_warning();
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
            'includes/functions/class-admin-menu-hide.php'
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
     * Initialize licensing system
     */
    private function init_licensing() {
        try {
            if (!class_exists('\VoxelToolkit\FluentLicensing')) {
                require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'updater/FluentLicensing.php';
            }
            
            $licensing = new \VoxelToolkit\FluentLicensing();
            
            $licensing->register([
                'version'     => VOXEL_TOOLKIT_VERSION,
                'item_id'     => '179',
                'basename'    => plugin_basename(VOXEL_TOOLKIT_PLUGIN_FILE),
                'api_url'     => 'https://codewattz.com/'
            ]);
            
            // Initialize license settings page
            if (!class_exists('\VoxelToolkit\LicenseSettings')) {
                require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'updater/LicenseSettings.php';
            }
            
            $licenseSettings = new \VoxelToolkit\LicenseSettings();
            $licenseSettings->register($licensing)
                ->setConfig([
                    'menu_title'      => __('License', 'voxel-toolkit'),
                    'title'           => __('Voxel Toolkit License', 'voxel-toolkit'),
                    'license_key'     => __('License Key', 'voxel-toolkit'),
                    'purchase_url'    => 'https://codewattz.com/?fluent-cart=instant_checkout&item_id=10&quantity=1',
                    'account_url'     => 'https://codewattz.com/account/',
                    'plugin_name'     => __('Voxel Toolkit', 'voxel-toolkit'),
                ]);
            
            // Add license page as submenu under Voxel Toolkit
            $licenseSettings->addPage([
                'type' => 'submenu',
                'parent_slug' => 'voxel-toolkit'
            ]);
            
        } catch (Exception $e) {
            error_log('Voxel Toolkit: Error initializing licensing - ' . $e->getMessage());
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
     * Initialize license notices
     */
    private function init_license_notices() {
        add_action('admin_notices', array($this, 'license_admin_notice'));
    }
    
    /**
     * Initialize deactivation warning
     */
    private function init_deactivation_warning() {
        add_action('admin_footer', array($this, 'add_deactivation_warning_script'));
    }
    
    /**
     * Display license activation notice
     */
    public function license_admin_notice() {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Don't show on license page itself
        if (isset($_GET['page']) && $_GET['page'] === 'voxel-toolkit-manage-license') {
            return;
        }
        
        try {
            // Get license instance
            $licensing = \VoxelToolkit\FluentLicensing::getInstance();
            if (!$licensing) {
                return;
            }
            
            // Check license status
            $status = $licensing->getStatus();
            
            // Only show notice if license is not valid
            if (!is_wp_error($status) && isset($status['status']) && $status['status'] === 'valid') {
                return; // License is valid, don't show notice
            }
            
            // Get current license key
            $currentKey = $licensing->getCurrentLicenseKey();
            $licensePageUrl = admin_url('admin.php?page=voxel-toolkit-manage-license');
            $purchaseUrl = 'https://codewattz.com/?fluent-cart=instant_checkout&item_id=10&quantity=1';
            
            ?>
            <div class="notice notice-warning is-dismissible" style="border-left-color: #2271b1;">
                <div style="display: flex; align-items: center; padding: 5px 0;">
                    <div style="margin-right: 10px;">
                        <span class="dashicons dashicons-admin-tools" style="font-size: 20px; color: #2271b1;"></span>
                    </div>
                    <div style="flex-grow: 1;">
                        <p style="margin: 0; font-weight: 600;">
                            <?php _e('Voxel Toolkit License Required', 'voxel-toolkit'); ?>
                        </p>
                        <p style="margin: 5px 0 0 0;">
                            <?php if (empty($currentKey)): ?>
                                <?php _e('Please activate your license to receive updates and support for Voxel Toolkit.', 'voxel-toolkit'); ?>
                            <?php else: ?>
                                <?php _e('Your license key appears to be invalid or expired. Please check your license status.', 'voxel-toolkit'); ?>
                            <?php endif; ?>
                        </p>
                        <p style="margin: 5px 0 0 0;">
                            <a href="<?php echo esc_url($licensePageUrl); ?>" class="button button-primary" style="margin-right: 10px;">
                                <?php _e('Activate License', 'voxel-toolkit'); ?>
                            </a>
                            <a href="<?php echo esc_url($purchaseUrl); ?>" class="button button-secondary" target="_blank">
                                <?php _e('Purchase License', 'voxel-toolkit'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
            <?php
            
        } catch (Exception $e) {
            // Silently fail if there's an error checking license status
            error_log('Voxel Toolkit: Error checking license status for admin notice - ' . $e->getMessage());
        }
    }
    
    /**
     * Add deactivation warning script
     */
    public function add_deactivation_warning_script() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'plugins') {
            $plugin_basename = plugin_basename(VOXEL_TOOLKIT_PLUGIN_FILE);
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Find the deactivate link for our plugin
                var deactivateLink = $('tr[data-plugin="<?php echo esc_js($plugin_basename); ?>"] .deactivate a');
                
                if (deactivateLink.length) {
                    deactivateLink.on('click', function(e) {
                        e.preventDefault();
                        
                        var confirmed = confirm(
                            "⚠️ IMPORTANT WARNING\n\n" +
                            "Deactivating Voxel Toolkit will immediately break:\n" +
                            "• All embedded review badges on external websites\n" +
                            "• All Voxel Toolkit widgets on your site\n" +
                            "• Custom functionality provided by enabled functions\n\n" +
                            "If you have embedded review badges on other websites, they will show errors until you reactivate this plugin.\n\n" +
                            "Are you sure you want to continue with deactivation?"
                        );
                        
                        if (confirmed) {
                            // User confirmed, proceed with deactivation
                            window.location.href = this.href;
                        }
                        // If not confirmed, do nothing (stay on page)
                    });
                }
            });
            </script>
            <?php
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

// Activation hook to flush rewrite rules for embed endpoints
register_activation_hook(__FILE__, function() {
    // Set flag to flush rules on next init
    update_option('voxel_toolkit_flush_rules', '1');
    flush_rewrite_rules();
});

// Deactivation hook to cleanup rewrite rules
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});