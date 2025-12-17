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
    private $post_fields_manager;
    
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
        $this->post_fields_manager = Voxel_Toolkit_Post_Fields::instance();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Handle AJAX requests
        add_action('wp_ajax_voxel_toolkit_toggle_function', array($this, 'ajax_toggle_function'));
        add_action('wp_ajax_voxel_toolkit_toggle_widget', array($this, 'ajax_toggle_widget'));
        add_action('wp_ajax_voxel_toolkit_bulk_toggle_widgets', array($this, 'ajax_bulk_toggle_widgets'));
        add_action('wp_ajax_voxel_toolkit_get_widget_usage', array($this, 'ajax_get_widget_usage'));
        add_action('wp_ajax_voxel_toolkit_reset_settings', array($this, 'ajax_reset_settings'));
        add_action('wp_ajax_vt_admin_notifications_user_search', array($this, 'ajax_admin_notifications_user_search'));
        add_action('wp_ajax_voxel_toolkit_reorder_fields', array($this, 'ajax_reorder_fields'));
        add_action('wp_ajax_vt_save_function_settings', array($this, 'ajax_save_function_settings'));
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
            'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+Cjxzdmcgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgdmlld0JveD0iMCAwIDQwMCAzNzAiIHZlcnNpb249IjEuMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgeG1sbnM6c2VyaWY9Imh0dHA6Ly93d3cuc2VyaWYuY29tLyIgc3R5bGU9ImZpbGwtcnVsZTpldmVub2RkO2NsaXAtcnVsZTpldmVub2RkO3N0cm9rZS1saW5lam9pbjpyb3VuZDtzdHJva2UtbWl0ZXJsaW1pdDoyOyI+CiAgICA8ZyB0cmFuc2Zvcm09Im1hdHJpeCgxLDAsMCwxLC05MC45MDg4ODIsLTExNS4zNjY4NzMpIj4KICAgICAgICA8ZyB0cmFuc2Zvcm09Im1hdHJpeCgzLjQ0OTA4LDAsMCwzLjQ0OTA4LC0yNzI2LjM5MDI5NywtNDQ0LjAxOTEwMSkiPgogICAgICAgICAgICA8cGF0aCBkPSJNODcyLjA0OCwyNjkuMDI0Qzg3Mi44MTIsMjY5LjAyNCA4NzQuODY1LDI2Ny4wNTUgODc2LjAxMSwyNjUuMjIyQzg3Ni41ODUsMjY0LjMwNiA4NzkuNzU2LDI1OC43OTUgODgzLjA1OSwyNTIuOTc0Qzg5MC40MjgsMjM5Ljk4OSA5MDcuNjk1LDIwOS44ODEgOTA4LjM5NiwyMDguNzk1QzkwOS4zMjIsMjA3LjM2MSA5MTAuNDI0LDIwNy43NyA5MTIuNjcxLDIxMC4zODJDOTEzLjc5OCwyMTEuNjkxIDkxNS4yNzIsMjEzLjUzMiA5MTUuOTQ3LDIxNC40NzNDOTE3LjI3MiwyMTYuMzE5IDkxOC41NTMsMjE3LjE3MiA5MjAuMDAyLDIxNy4xNzJDOTIxLjUzMywyMTcuMTcyIDkyMi40NDgsMjE1Ljg3MSA5MjMuMTA3LDIxMi43NkM5MjMuNDM1LDIxMS4yMSA5MjUuNzAzLDIwMC41NzYgOTI4LjE0NiwxODkuMTNDOTMwLjU5LDE3Ny42ODMgOTMyLjY2MywxNjcuNjgzIDkzMi43NTMsMTY2LjkwN0M5MzIuOTYsMTY1LjEyNiA5MzEuOTcsMTYzLjQzMiA5MzAuMjQsMTYyLjYwN0M5MjkuMTgyLDE2Mi4xMDIgOTI4LjYyLDE2Mi4wNzIgOTI2LjI2OSwxNjIuMzk1QzkxOS4wNjgsMTYzLjM4MiA4ODMuODY5LDE2OC44ODggODgyLjcyOCwxNjkuMjA1Qzg4Mi4wMTUsMTY5LjQwMyA4ODEuMDA2LDE3MC4wNzEgODgwLjQ4NiwxNzAuNjg4Qzg3OS43MDcsMTcxLjYxNCA4NzkuNTc0LDE3Mi4wNTIgODc5LjcyNiwxNzMuMTg2Qzg3OS44MjcsMTczLjk0MiA4ODAuMjgxLDE3NC45NjEgODgwLjczMywxNzUuNDVDODgxLjE4NSwxNzUuOTQgODg0LjQxMywxNzguMjQyIDg4Ny45MDUsMTgwLjU2NkM4OTIuMiwxODMuNDI1IDg5NC4yNTQsMTg0Ljk5NyA4OTQuMjU0LDE4NS40MjVDODk0LjI1NCwxODYuNTQ5IDg3MS40ODIsMjExLjMwMiA4NzAuMzA0LDIxMS40NThDODY5LjI4NCwyMTEuNTkzIDg2OS4wOTksMjExLjM1NiA4NjYuNzY3LDIwNi45MTlDODYwLjIyNiwxOTQuNDc2IDg0OC40NDYsMTczLjM3IDg0Ny4zMSwxNzIuMDU5Qzg0Ni41MzEsMTcxLjE1OSA4NDUuMTQ2LDE3MC4xMjQgODQ0LjAwOCwxNjkuNTlDODQyLjA2MSwxNjguNjc3IDg0MS45ODgsMTY4LjY3IDgzMS45MDUsMTY4LjU1OUM4MjAuODM5LDE2OC40MzYgODE4LjUwOSwxNjguNzIyIDgxNy4zNzQsMTcwLjM0MkM4MTYuNDQ2LDE3MS42NjcgODE2LjY5OSwxNzMuNzM3IDgxOC4xMTksMTc2LjQ0M0M4MjIuNTk2LDE4NC45NzEgODM3LjE0MSwyMTIuMTAxIDg0My41NDgsMjIzLjg3NEM4NTAuMTYxLDIzNi4wMjYgODU1LjA3OSwyNDUuMDc3IDg2NC44MzQsMjYzLjA0N0M4NjYuMTU3LDI2NS40ODIgODY3LjU0NiwyNjcuNzU0IDg2Ny45MjIsMjY4LjA5NEM4NjguNzEsMjY4LjgwNyA4NzAuNDA1LDI2OS4zODIgODcxLjA5LDI2OS4xN0M4NzEuMzQ5LDI2OS4wODkgODcxLjc4LDI2OS4wMjQgODcyLjA0OCwyNjkuMDI0WiIgc3R5bGU9ImZpbGw6d2hpdGU7Ii8+CiAgICAgICAgPC9nPgogICAgPC9nPgo8L3N2Zz4K',
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
            __('Post Fields', 'voxel-toolkit'),
            __('Post Fields', 'voxel-toolkit'),
            'manage_options',
            'voxel-toolkit-post-fields',
            array($this, 'render_post_fields_page')
        );

        add_submenu_page(
            'voxel-toolkit',
            __('Dynamic Tags', 'voxel-toolkit'),
            __('Dynamic Tags', 'voxel-toolkit'),
            'manage_options',
            'voxel-toolkit-dynamic-tags',
            array($this, 'render_dynamic_tags_page')
        );

        add_submenu_page(
            'voxel-toolkit',
            __('Tag Usage', 'voxel-toolkit'),
            __('Tag Usage', 'voxel-toolkit'),
            'manage_options',
            'voxel-toolkit-tag-usage',
            array($this, 'render_tag_usage_page')
        );

        // Add Site Options as separate top-level menu if enabled
        if ($this->settings->is_function_enabled('options_page')) {
            add_menu_page(
                __('Site Options', 'voxel-toolkit'),
                __('Site Options', 'voxel-toolkit'),
                'manage_options',
                'voxel-toolkit-site-options',
                array($this, 'render_site_options_page'),
                'dashicons-admin-settings',
                30
            );

            // Add Configure Fields as submenu under Site Options
            add_submenu_page(
                'voxel-toolkit-site-options',
                __('Configure Fields', 'voxel-toolkit'),
                __('Configure Fields', 'voxel-toolkit'),
                'manage_options',
                'voxel-toolkit-configure-fields',
                array($this, 'render_configure_fields_page')
            );
        }

        add_submenu_page(
            'voxel-toolkit',
            __('Settings', 'voxel-toolkit'),
            __('Settings', 'voxel-toolkit'),
            'manage_options',
            'voxel-toolkit-settings',
            array($this, 'render_settings_page')
        );

        // Docs - External link
        add_submenu_page(
            'voxel-toolkit',
            __('Docs', 'voxel-toolkit'),
            __('Docs', 'voxel-toolkit'),
            'manage_options',
            'voxel-toolkit-docs',
            '__return_null'
        );
    }
    
    /**
     * Initialize admin
     */
    public function admin_init() {
        // Handle configure fields form submission
        $has_save_button = isset($_POST['voxel_toolkit_save_fields']) || isset($_POST['voxel_toolkit_save_field']);
        $has_page = isset($_GET['page']) && $_GET['page'] === 'voxel-toolkit-configure-fields';

        if ($has_save_button && $has_page) {
            if (check_admin_referer('voxel_toolkit_configure_fields', 'voxel_toolkit_fields_nonce')) {
                $this->save_configure_fields();
            }
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        // Always load basic styles for dark mode support
        wp_enqueue_style(
            'voxel-toolkit-admin',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        // Hide WP footer on Voxel Toolkit pages
        if (strpos($hook, 'voxel-toolkit') !== false) {
            wp_add_inline_style('voxel-toolkit-admin', '#wpfooter { display: none !important; }');
        }

        // Make Docs menu item open in new tab
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                var docsLink = $("#adminmenu a[href*=\"page=voxel-toolkit-docs\"]");
                if (docsLink.length) {
                    docsLink.attr("href", "https://codewattz.com/doc/").attr("target", "_blank");
                }
            });
        ');

        // Enqueue Elementor icons for widget cards
        wp_enqueue_style(
            'elementor-icons',
            plugins_url('elementor/assets/lib/eicons/css/elementor-icons.min.css'),
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        // Load SMS notifications script on Voxel app-events page
        if (isset($_GET['page']) && $_GET['page'] === 'voxel-events') {
            $this->enqueue_sms_notifications_script();
        }

        // Load full scripts on our plugin pages and Voxel taxonomies pages
        $load_full_scripts = (strpos($hook, 'voxel-toolkit') !== false) ||
                            (isset($_GET['page']) && $_GET['page'] === 'voxel-taxonomies');

        if (!$load_full_scripts) {
            return;
        }
        
        // Enqueue jQuery UI Sortable
        wp_enqueue_script('jquery-ui-sortable');

        // Enqueue Select2 for user search functionality
        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '', true);

        wp_enqueue_script(
            'voxel-toolkit-admin',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'select2'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Localize script with data for JavaScript
        wp_localize_script('voxel-toolkit-admin', 'voxelToolkitAdmin', array(
            'widgetNonce' => wp_create_nonce('voxel_toolkit_widget_nonce'),
            'i18n' => array(
                'enabled' => __('Enabled', 'voxel-toolkit'),
                'disabled' => __('Disabled', 'voxel-toolkit'),
                'confirmEnableAll' => __('Are you sure you want to enable all widgets?', 'voxel-toolkit'),
                'confirmDisableAll' => __('Are you sure you want to disable all widgets?', 'voxel-toolkit'),
            )
        ));

        // Enqueue media library and options page scripts for Site Options page
        if (isset($_GET['page']) && $_GET['page'] === 'voxel-toolkit-site-options') {
            wp_enqueue_media();
            wp_enqueue_script(
                'voxel-toolkit-options-page',
                VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/options-page.js',
                array('jquery'),
                VOXEL_TOOLKIT_VERSION,
                true
            );
        }
        
        // Add inline styles and JS for Voxel taxonomies page - only if Light Mode is enabled
        if (isset($_GET['page']) && $_GET['page'] === 'voxel-taxonomies' && isset($_GET['action']) && $_GET['action'] === 'reorder-terms') {
            // Check if Light Mode is enabled
            $settings = Voxel_Toolkit_Settings::instance();
            $light_mode_enabled = $settings->is_function_enabled('light_mode');

            if ($light_mode_enabled) {
                wp_add_inline_style('voxel-toolkit-admin', '
                    .ts-terms-order .field-level-1>.field-head {
                        background: #e9e9e9 !important;
                    }
                    .ts-terms-order .field-level-2>.field-head {
                        background: #e9e9e9 !important;
                    }
                    .ts-form-group select[multiple] {
                        background: #e9e9e9 !important;
                    }
                    .ts-form-group select[multiple] option {
                        padding: 5px !important;
                        color: black !important;
                        background: #f5f5f5 !important;
                    }
                    .ts-form-group select[multiple] option:hover {
                        background: #e0e0e0 !important;
                    }
                    .ts-form-group select[multiple] option:checked {
                        background: #393e42 linear-gradient(0deg, #393e42, #393e42) !important;
                        color: #fff !important;
                        font-weight: 400 !important;
                    }
                ');

                wp_add_inline_script('voxel-toolkit-admin', "
                    jQuery(document).ready(function($) {
                        // Apply styles once on load - no continuous monitoring to prevent freezing
                        setTimeout(function() {
                            // Multi-select styles
                            $('.ts-form-group select[multiple]').each(function() {
                                if (!$(this).data('voxel-styled')) {
                                    $(this).attr('style', 'background: #e9e9e9 !important; background-color: #e9e9e9 !important;');
                                    $(this).data('voxel-styled', true);
                                }
                            });

                            $('.ts-form-group select[multiple] option').each(function() {
                                if (!$(this).data('voxel-styled')) {
                                    $(this).attr('style', 'padding: 5px !important; color: black !important; background: #f5f5f5 !important;');
                                    $(this).data('voxel-styled', true);
                                }
                            });
                        
                        $('.ts-form-group select[multiple] option:checked').each(function() {
                            $(this).attr('style', 'background: #393e42 linear-gradient(0deg, #393e42, #393e42) !important; color: #fff !important; font-weight: 400 !important;');
                        });
                        
                        // Terms order field head
                        $('.ts-terms-order .field-level-1>.field-head, .ts-terms-order .field-level-2>.field-head').each(function() {
                            if (!$(this).data('voxel-styled')) {
                                $(this).attr('style', 'background: #e9e9e9 !important;');
                                $(this).data('voxel-styled', true);
                            }
                        });
                    }, 100);
                    
                    // Reapply on specific events only
                    $(document).on('change', '.ts-form-group select[multiple]', function() {
                        $(this).find('option:checked').attr('style', 'background: #393e42 linear-gradient(0deg, #393e42, #393e42) !important; color: #fff !important; font-weight: 400 !important;');
                        $(this).find('option:not(:checked)').attr('style', 'padding: 5px !important; color: black !important; background: #f5f5f5 !important;');
                    });
                });
            ");
            }
        }
        
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
            
            <div class="voxel-toolkit-controls">
                <div class="controls-row">
                    <div class="search-section">
                        <label for="voxel-toolkit-search" class="search-label">
                            <span class="dashicons dashicons-search"></span>
                            <?php _e('Search', 'voxel-toolkit'); ?>
                        </label>
                        <input type="text" 
                               id="voxel-toolkit-search" 
                               class="search-input" 
                               placeholder="<?php _e('Type to search functions...', 'voxel-toolkit'); ?>" 
                               autocomplete="off">
                    </div>
                    
                    <div class="filter-section">
                        <label class="filter-label"><?php _e('Show', 'voxel-toolkit'); ?></label>
                        <div class="filter-options">
                            <label class="filter-option">
                                <input type="radio" name="function-filter" value="all" checked>
                                <span><?php _e('All', 'voxel-toolkit'); ?></span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="function-filter" value="enabled">
                                <span><?php _e('Enabled', 'voxel-toolkit'); ?></span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="function-filter" value="disabled">
                                <span><?php _e('Disabled', 'voxel-toolkit'); ?></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="controls-actions">
                        <button type="button" 
                                id="voxel-toolkit-controls-reset" 
                                class="button button-secondary controls-reset"
                                title="<?php _e('Reset all filters', 'voxel-toolkit'); ?>">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Reset', 'voxel-toolkit'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="controls-results" id="controls-results-info"></div>
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
        // Skip hidden functions
        if (!empty($function_data['hidden'])) {
            return;
        }

        $is_enabled = $this->settings->is_function_enabled($function_key);
        $is_active = $this->functions_manager->is_function_active($function_key);
        $is_always_enabled = isset($function_data['always_enabled']) && $function_data['always_enabled'];
        $settings_url = admin_url("admin.php?page=voxel-toolkit-settings#{$function_key}");
        
        // Always enabled functions should show as enabled
        $display_enabled = $is_enabled || $is_always_enabled;
        ?>
        <div class="voxel-toolkit-function-card <?php echo $display_enabled ? 'enabled' : 'disabled'; ?> <?php echo $is_always_enabled ? 'always-enabled' : ''; ?>"
             data-function-key="<?php echo esc_attr($function_key); ?>"
             data-function-name="<?php echo esc_attr(strtolower($function_data['name'])); ?>"
             data-function-description="<?php echo esc_attr(strtolower($function_data['description'])); ?>">
            <div class="function-header">
                <div class="function-title-row">
                    <h3><?php echo esc_html($function_data['name']); ?><?php if (!empty($function_data['beta'])): ?> <span class="voxel-toolkit-badge-beta"><?php _e('Beta', 'voxel-toolkit'); ?></span><?php endif; ?></h3>
                    <?php if ($is_always_enabled): ?>
                        <span class="voxel-toolkit-function-badge voxel-toolkit-badge-always-enabled"><?php _e('Always Enabled', 'voxel-toolkit'); ?></span>
                    <?php elseif ($is_enabled): ?>
                        <span class="voxel-toolkit-function-badge voxel-toolkit-badge-enabled"><?php _e('Enabled', 'voxel-toolkit'); ?></span>
                    <?php else: ?>
                        <span class="voxel-toolkit-function-badge voxel-toolkit-badge-disabled"><?php _e('Disabled', 'voxel-toolkit'); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!$is_always_enabled): ?>
                <div class="function-toggle">
                    <label class="toggle-switch">
                        <input type="checkbox"
                               class="function-toggle-checkbox"
                               data-function="<?php echo esc_attr($function_key); ?>"
                               <?php checked($is_enabled); ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="function-description">
                <p><?php echo esc_html($function_data['description']); ?></p>
            </div>

            <?php if ($is_enabled): ?>
                <div class="function-actions">
                    <?php
                    $configure_url = isset($function_data['configure_url']) ? $function_data['configure_url'] : $settings_url;
                    ?>
                    <a href="<?php echo esc_url($configure_url); ?>" class="button button-secondary">
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

        // Get only enabled functions that have settings
        $enabled_functions = array();
        foreach ($available_functions as $function_key => $function_data) {
            // Skip hidden functions
            if (!empty($function_data['hidden'])) {
                continue;
            }

            $is_enabled = $this->settings->is_function_enabled($function_key);
            $is_always_enabled = isset($function_data['always_enabled']) && $function_data['always_enabled'];

            if ($is_enabled || $is_always_enabled) {
                $enabled_functions[$function_key] = $function_data;
            }
        }

        // Sort functions alphabetically by name
        uasort($enabled_functions, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        ?>
        <div class="wrap">
            <h1><?php _e('Voxel Toolkit Settings', 'voxel-toolkit'); ?></h1>
            <p class="description"><?php _e('Configure your enabled functions. Each function saves independently.', 'voxel-toolkit'); ?></p>

            <?php if (empty($enabled_functions)): ?>
                <!-- Empty State -->
                <div class="vt-settings-container">
                    <div class="vt-settings-empty">
                        <span class="dashicons dashicons-admin-generic vt-settings-empty-icon"></span>
                        <h3><?php _e('No Functions Enabled', 'voxel-toolkit'); ?></h3>
                        <p><?php _e('Enable functions on the Functions page to configure them here.', 'voxel-toolkit'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=voxel-toolkit')); ?>" class="button button-primary">
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                            <?php _e('Go to Functions', 'voxel-toolkit'); ?>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="vt-settings-container">
                    <!-- Tab Sidebar -->
                    <div class="vt-settings-tabs">
                        <div class="vt-settings-tabs-header">
                            <div class="vt-settings-search">
                                <input type="text"
                                       class="vt-settings-search-input"
                                       placeholder="<?php esc_attr_e('Search functions...', 'voxel-toolkit'); ?>"
                                       id="vt-settings-search">
                                <span class="dashicons dashicons-search"></span>
                            </div>
                        </div>
                        <div class="vt-settings-tabs-list">
                            <?php
                            $first = true;
                            foreach ($enabled_functions as $function_key => $function_data):
                            ?>
                                <button type="button"
                                        class="vt-settings-tab <?php echo $first ? 'active' : ''; ?>"
                                        data-tab="<?php echo esc_attr($function_key); ?>">
                                    <?php echo esc_html($function_data['name']); ?><?php if (!empty($function_data['beta'])): ?> <span class="vt-badge-beta"><?php _e('Beta', 'voxel-toolkit'); ?></span><?php endif; ?>
                                </button>
                            <?php
                                $first = false;
                            endforeach;
                            ?>
                            <div class="vt-settings-no-results" style="display: none;">
                                <span class="dashicons dashicons-search"></span>
                                <?php _e('No functions found', 'voxel-toolkit'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Content Panels -->
                    <div class="vt-settings-content">
                        <?php
                        $first = true;
                        foreach ($enabled_functions as $function_key => $function_data):
                            $function_settings = $this->settings->get_function_settings($function_key, array());
                        ?>
                            <div class="vt-settings-panel <?php echo $first ? 'active' : ''; ?>"
                                 data-panel="<?php echo esc_attr($function_key); ?>">

                                <div class="vt-settings-panel-header">
                                    <div class="vt-settings-panel-header-left">
                                        <h2 class="vt-settings-panel-title"><?php echo esc_html($function_data['name']); ?><?php if (!empty($function_data['beta'])): ?> <span class="vt-badge-beta"><?php _e('Beta', 'voxel-toolkit'); ?></span><?php endif; ?></h2>
                                        <p class="vt-settings-panel-description"><?php echo esc_html($function_data['description']); ?></p>
                                    </div>
                                    <div class="vt-settings-panel-header-right">
                                        <button type="button"
                                                class="button vt-settings-save-btn"
                                                data-function="<?php echo esc_attr($function_key); ?>">
                                            <span class="dashicons dashicons-saved"></span>
                                            <span class="vt-save-text"><?php _e('Save Settings', 'voxel-toolkit'); ?></span>
                                        </button>
                                        <span class="vt-settings-save-status"></span>
                                    </div>
                                </div>

                                <div class="vt-settings-panel-body">
                                    <form class="vt-settings-form" data-function="<?php echo esc_attr($function_key); ?>">
                                        <?php wp_nonce_field('vt_save_function_settings', 'vt_settings_nonce'); ?>
                                        <input type="hidden" name="function_key" value="<?php echo esc_attr($function_key); ?>">

                                        <?php
                                        // Call custom settings callback if available
                                        if (isset($function_data['settings_callback']) && is_callable($function_data['settings_callback'])) {
                                            call_user_func($function_data['settings_callback'], $function_settings);
                                        } else {
                                            // If no custom settings, show a message
                                            ?>
                                            <div class="vt-info-box">
                                                <?php _e('This function is enabled and active. No additional configuration is required.', 'voxel-toolkit'); ?>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </form>
                                </div>
                            </div>
                        <?php
                            $first = false;
                        endforeach;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
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
            // Use sanitize_options to properly sanitize function-specific settings
            $sanitized_input = $this->sanitize_options($post_data['voxel_toolkit_options']);

            foreach ($sanitized_input as $function_key => $sanitized_settings) {
                if (!isset($current_options[$function_key])) {
                    $current_options[$function_key] = array();
                }

                // Special handling for options_page field configuration
                if ($function_key === 'options_page') {
                    $current_options[$function_key] = $this->handle_options_page_config($post_data['voxel_toolkit_options'][$function_key], $current_options[$function_key]);
                    continue;
                }

                // IMPORTANT: Preserve the 'enabled' status when saving settings
                // Only merge the new settings, don't overwrite the enabled status
                $enabled_status = isset($current_options[$function_key]['enabled']) ? $current_options[$function_key]['enabled'] : false;

                // Merge sanitized settings, preserving existing ones
                $current_options[$function_key] = array_merge($current_options[$function_key], $sanitized_settings);

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
     * AJAX handler for saving individual function settings
     */
    public function ajax_save_function_settings() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vt_save_function_settings')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'voxel-toolkit')));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to do this.', 'voxel-toolkit')));
        }

        // Get function key
        $function_key = isset($_POST['function_key']) ? sanitize_key($_POST['function_key']) : '';
        if (empty($function_key)) {
            wp_send_json_error(array('message' => __('Invalid function key.', 'voxel-toolkit')));
        }

        // Verify function exists
        $available_functions = $this->functions_manager->get_available_functions();
        if (!isset($available_functions[$function_key])) {
            wp_send_json_error(array('message' => __('Function not found.', 'voxel-toolkit')));
        }

        // Get current options
        $current_options = get_option('voxel_toolkit_options', array());

        // Initialize function settings if not exists
        if (!isset($current_options[$function_key])) {
            $current_options[$function_key] = array();
        }

        // Preserve the enabled status
        $enabled_status = isset($current_options[$function_key]['enabled']) ? $current_options[$function_key]['enabled'] : false;

        // Get the settings data from POST
        $settings_data = isset($_POST['settings']) ? $_POST['settings'] : array();

        // Handle AI Review Summary API key specifically
        if ($function_key === 'ai_review_summary' && isset($_POST['ai_api_key'])) {
            $api_key = sanitize_text_field(trim($_POST['ai_api_key']));
            if (!empty($api_key)) {
                $current_options[$function_key]['api_key'] = $api_key;
            }
        }

        // Process settings - always run sanitization even if settings appear empty
        // This ensures unchecked checkboxes (empty arrays) are properly saved
        // Get the function data, defaulting to empty array if not set
        $function_data = isset($settings_data[$function_key]) ? $settings_data[$function_key] : array();

        // Build input array for sanitization
        $input = array($function_key => $function_data);

        // Sanitize the input
        $sanitized = $this->sanitize_options($input);

        if (isset($sanitized[$function_key])) {
            // Special handling for options_page
            if ($function_key === 'options_page') {
                $current_options[$function_key] = $this->handle_options_page_config(
                    $function_data,
                    $current_options[$function_key]
                );
            } else {
                // Merge sanitized settings, ensuring arrays are replaced not merged
                foreach ($sanitized[$function_key] as $key => $value) {
                    $current_options[$function_key][$key] = $value;
                }
            }
        }

        // Restore enabled status
        $current_options[$function_key]['enabled'] = $enabled_status;

        // Save options
        $result = update_option('voxel_toolkit_options', $current_options);

        // Refresh settings cache
        $this->settings->refresh_options();

        wp_send_json_success(array(
            'message' => __('Settings saved successfully.', 'voxel-toolkit'),
            'function' => $function_key
        ));
    }

    /**
     * Handle options page field configuration
     */
    private function handle_options_page_config($new_settings, $current_settings) {
        // Preserve enabled status
        $enabled_status = isset($current_settings['enabled']) ? $current_settings['enabled'] : false;

        // Get existing fields
        $existing_fields = isset($current_settings['fields']) ? $current_settings['fields'] : array();

        // Handle delete fields (comma-separated list)
        if (isset($new_settings['delete_fields']) && !empty($new_settings['delete_fields'])) {
            $fields_to_delete = explode(',', $new_settings['delete_fields']);
            foreach ($fields_to_delete as $delete_field) {
                $delete_field = sanitize_key(trim($delete_field));
                if (!empty($delete_field) && isset($existing_fields[$delete_field])) {
                    unset($existing_fields[$delete_field]);
                    // Also delete the option value
                    delete_option('voxel_options_' . $delete_field);
                }
            }
        }

        // Handle add multiple new fields (JSON array)
        if (isset($new_settings['new_fields']) && !empty($new_settings['new_fields'])) {
            $new_fields = json_decode($new_settings['new_fields'], true);

            if (is_array($new_fields)) {
                foreach ($new_fields as $new_field) {
                    if (!isset($new_field['name']) || empty(trim($new_field['name']))) {
                        continue;
                    }

                    $field_name = Voxel_Toolkit_Options_Page::sanitize_field_name($new_field['name']);
                    $field_label = isset($new_field['label']) && !empty(trim($new_field['label']))
                        ? sanitize_text_field($new_field['label'])
                        : ucwords(str_replace('_', ' ', $field_name));
                    $field_type = isset($new_field['type']) ? Voxel_Toolkit_Options_Page::validate_field_type($new_field['type']) : 'text';
                    $field_default = isset($new_field['default']) ? sanitize_text_field($new_field['default']) : '';

                    // Only add if field name is valid and doesn't exist, and under limit
                    if (!empty($field_name) && !isset($existing_fields[$field_name]) && count($existing_fields) < Voxel_Toolkit_Options_Page::MAX_FIELDS) {
                        $existing_fields[$field_name] = array(
                            'label' => $field_label,
                            'type' => $field_type,
                            'default' => $field_default,
                        );
                    }
                }
            }
        }

        return array(
            'enabled' => $enabled_status,
            'fields' => $existing_fields,
        );
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
                            $allowed_menus = array('voxel_settings', 'voxel_post_types', 'voxel_templates', 'voxel_users');
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

                    case 'duplicate_post':
                        // Post types
                        if (isset($function_input['post_types']) && is_array($function_input['post_types'])) {
                            $sanitized_function['post_types'] = array_map('sanitize_text_field', $function_input['post_types']);
                        } else {
                            $sanitized_function['post_types'] = array();
                        }
                        // Allowed roles
                        if (isset($function_input['allowed_roles']) && is_array($function_input['allowed_roles'])) {
                            $sanitized_function['allowed_roles'] = array_map('sanitize_text_field', $function_input['allowed_roles']);
                        } else {
                            $sanitized_function['allowed_roles'] = array('contributor', 'author', 'editor', 'administrator');
                        }
                        // Redirect pages
                        if (isset($function_input['redirect_pages']) && is_array($function_input['redirect_pages'])) {
                            $sanitized_function['redirect_pages'] = array();
                            foreach ($function_input['redirect_pages'] as $post_type => $page_id) {
                                $sanitized_function['redirect_pages'][sanitize_text_field($post_type)] = absint($page_id);
                            }
                        } else {
                            $sanitized_function['redirect_pages'] = array();
                        }
                        break;

                    case 'admin_notifications':
                        // Sanitize user roles array
                        if (isset($function_input['user_roles']) && is_array($function_input['user_roles'])) {
                            $sanitized_function['user_roles'] = array_map('sanitize_text_field', $function_input['user_roles']);
                        } else {
                            $sanitized_function['user_roles'] = array();
                        }

                        // Sanitize selected users array
                        if (isset($function_input['selected_users']) && is_array($function_input['selected_users'])) {
                            $sanitized_function['selected_users'] = array_map('absint', array_filter($function_input['selected_users']));
                        } elseif (isset($function_input['selected_users']) && $function_input['selected_users'] === '') {
                            // Handle empty hidden field value
                            $sanitized_function['selected_users'] = array();
                        } else {
                            $sanitized_function['selected_users'] = array();
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
                        // Get current settings to preserve API key
                        $current_ai_settings = Voxel_Toolkit_Settings::instance()->get_function_settings('ai_review_summary', array());

                        // API key - preserve existing if not provided in form
                        // Note: API key is sent separately via 'ai_api_key' field, not through voxel_toolkit_options
                        if (!empty($function_input['api_key'])) {
                            $sanitized_function['api_key'] = sanitize_text_field($function_input['api_key']);
                        } elseif (isset($current_ai_settings['api_key'])) {
                            $sanitized_function['api_key'] = $current_ai_settings['api_key'];
                        }

                        // Language setting
                        if (isset($function_input['language'])) {
                            $sanitized_function['language'] = sanitize_text_field($function_input['language']);
                        }
                        break;

                    case 'sms_notifications':
                        // Provider selection
                        $valid_providers = array('twilio', 'vonage', 'messagebird');
                        $sanitized_function['provider'] = isset($function_input['provider']) && in_array($function_input['provider'], $valid_providers)
                            ? sanitize_text_field($function_input['provider'])
                            : 'twilio';

                        // Phone field selection
                        $sanitized_function['phone_field'] = isset($function_input['phone_field'])
                            ? sanitize_text_field($function_input['phone_field'])
                            : '';

                        // Twilio credentials - only update if new value provided
                        $current_settings = Voxel_Toolkit_Settings::instance()->get_function_settings('sms_notifications', array());

                        // Preserve phone_field if not in new input
                        if (empty($sanitized_function['phone_field']) && isset($current_settings['phone_field'])) {
                            $sanitized_function['phone_field'] = $current_settings['phone_field'];
                        }

                        // Country code
                        $sanitized_function['country_code'] = isset($function_input['country_code'])
                            ? sanitize_text_field($function_input['country_code'])
                            : (isset($current_settings['country_code']) ? $current_settings['country_code'] : '');

                        if (!empty($function_input['twilio_account_sid'])) {
                            $sanitized_function['twilio_account_sid'] = sanitize_text_field($function_input['twilio_account_sid']);
                        } elseif (isset($current_settings['twilio_account_sid'])) {
                            $sanitized_function['twilio_account_sid'] = $current_settings['twilio_account_sid'];
                        }

                        if (!empty($function_input['twilio_auth_token'])) {
                            $sanitized_function['twilio_auth_token'] = sanitize_text_field($function_input['twilio_auth_token']);
                        } elseif (isset($current_settings['twilio_auth_token'])) {
                            $sanitized_function['twilio_auth_token'] = $current_settings['twilio_auth_token'];
                        }

                        $sanitized_function['twilio_from_number'] = isset($function_input['twilio_from_number'])
                            ? sanitize_text_field($function_input['twilio_from_number'])
                            : (isset($current_settings['twilio_from_number']) ? $current_settings['twilio_from_number'] : '');

                        // Vonage credentials - only update if new value provided
                        if (!empty($function_input['vonage_api_key'])) {
                            $sanitized_function['vonage_api_key'] = sanitize_text_field($function_input['vonage_api_key']);
                        } elseif (isset($current_settings['vonage_api_key'])) {
                            $sanitized_function['vonage_api_key'] = $current_settings['vonage_api_key'];
                        }

                        if (!empty($function_input['vonage_api_secret'])) {
                            $sanitized_function['vonage_api_secret'] = sanitize_text_field($function_input['vonage_api_secret']);
                        } elseif (isset($current_settings['vonage_api_secret'])) {
                            $sanitized_function['vonage_api_secret'] = $current_settings['vonage_api_secret'];
                        }

                        $sanitized_function['vonage_from'] = isset($function_input['vonage_from'])
                            ? sanitize_text_field($function_input['vonage_from'])
                            : (isset($current_settings['vonage_from']) ? $current_settings['vonage_from'] : '');

                        // MessageBird credentials - only update if new value provided
                        if (!empty($function_input['messagebird_api_key'])) {
                            $sanitized_function['messagebird_api_key'] = sanitize_text_field($function_input['messagebird_api_key']);
                        } elseif (isset($current_settings['messagebird_api_key'])) {
                            $sanitized_function['messagebird_api_key'] = $current_settings['messagebird_api_key'];
                        }

                        $sanitized_function['messagebird_originator'] = isset($function_input['messagebird_originator'])
                            ? sanitize_text_field($function_input['messagebird_originator'])
                            : (isset($current_settings['messagebird_originator']) ? $current_settings['messagebird_originator'] : '');

                        // Preserve events settings (managed via AJAX)
                        if (isset($current_settings['events'])) {
                            $sanitized_function['events'] = $current_settings['events'];
                        }
                        break;

                    case 'show_field_description':
                        // No settings needed - styling controlled via Elementor widget
                        break;

                    case 'disable_auto_updates':
                        // Single checkbox settings - check for "1" string or truthy value
                        $sanitized_function['disable_plugin_updates'] = !empty($function_input['disable_plugin_updates']) && $function_input['disable_plugin_updates'] !== '0';
                        $sanitized_function['disable_theme_updates'] = !empty($function_input['disable_theme_updates']) && $function_input['disable_theme_updates'] !== '0';
                        $sanitized_function['disable_core_updates'] = !empty($function_input['disable_core_updates']) && $function_input['disable_core_updates'] !== '0';
                        break;

                    case 'auto_promotion':
                        // Sanitize post types
                        if (isset($function_input['post_types']) && is_array($function_input['post_types'])) {
                            $sanitized_function['post_types'] = array_map('sanitize_text_field', $function_input['post_types']);
                        } else {
                            $sanitized_function['post_types'] = array();
                        }
                        
                        // Sanitize individual post type settings
                        foreach ($function_input as $key => $value) {
                            if (strpos($key, 'settings_') === 0 && is_array($value)) {
                                $post_type = str_replace('settings_', '', $key);
                                $post_type = sanitize_text_field($post_type);
                                
                                $sanitized_settings = array();
                                
                                // Sanitize priority
                                if (isset($value['priority'])) {
                                    $priority = intval($value['priority']);
                                    $sanitized_settings['priority'] = max(1, min(999, $priority)); // Clamp between 1-999
                                } else {
                                    $sanitized_settings['priority'] = 10; // Default
                                }
                                
                                // Sanitize duration
                                if (isset($value['duration'])) {
                                    $duration = intval($value['duration']);
                                    $sanitized_settings['duration'] = max(1, min(999, $duration)); // Clamp between 1-999
                                } else {
                                    $sanitized_settings['duration'] = 24; // Default
                                }
                                
                                // Sanitize duration unit
                                if (isset($value['duration_unit']) && in_array($value['duration_unit'], array('hours', 'days', 'weeks'))) {
                                    $sanitized_settings['duration_unit'] = sanitize_text_field($value['duration_unit']);
                                } else {
                                    $sanitized_settings['duration_unit'] = 'hours'; // Default
                                }
                                
                                $sanitized_function['settings_' . $post_type] = $sanitized_settings;
                            }
                        }
                        break;

                    case 'pre_approve_posts':
                        // Approved roles (array of role slugs)
                        if (isset($function_input['approved_roles']) && is_array($function_input['approved_roles'])) {
                            $sanitized_function['approved_roles'] = array_map('sanitize_text_field', $function_input['approved_roles']);
                        } else {
                            $sanitized_function['approved_roles'] = array();
                        }

                        // Auto-approve verified users (checkbox)
                        $sanitized_function['approve_verified'] = !empty($function_input['approve_verified']);

                        // Show column in users list (checkbox)
                        $sanitized_function['show_column'] = !empty($function_input['show_column']);
                        break;

                    case 'duplicate_title_checker':
                        // Block duplicate submissions (checkbox)
                        $sanitized_function['block_duplicate'] = !empty($function_input['block_duplicate']);

                        // Error message (text)
                        if (isset($function_input['error_message']) && !empty(trim($function_input['error_message']))) {
                            $sanitized_function['error_message'] = sanitize_text_field($function_input['error_message']);
                        }

                        // Success message (text)
                        if (isset($function_input['success_message']) && !empty(trim($function_input['success_message']))) {
                            $sanitized_function['success_message'] = sanitize_text_field($function_input['success_message']);
                        }
                        break;

                    case 'admin_taxonomy_search':
                        if (isset($function_input['taxonomies']) && is_array($function_input['taxonomies'])) {
                            $sanitized_function['taxonomies'] = array_map('sanitize_text_field', $function_input['taxonomies']);
                        } else {
                            $sanitized_function['taxonomies'] = array();
                        }
                        break;

                    case 'pending_posts_badge':
                        if (isset($function_input['post_types']) && is_array($function_input['post_types'])) {
                            $sanitized_function['post_types'] = array_map('sanitize_text_field', $function_input['post_types']);
                        } else {
                            $sanitized_function['post_types'] = array();
                        }
                        $sanitized_function['background_color'] = isset($function_input['background_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $function_input['background_color'])
                            ? sanitize_hex_color($function_input['background_color'])
                            : '#d63638';
                        $sanitized_function['text_color'] = isset($function_input['text_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $function_input['text_color'])
                            ? sanitize_hex_color($function_input['text_color'])
                            : '#ffffff';
                        break;

                    case 'redirect_posts':
                        if (isset($function_input['post_types']) && is_array($function_input['post_types'])) {
                            $sanitized_function['post_types'] = array_map('sanitize_text_field', $function_input['post_types']);
                        } else {
                            $sanitized_function['post_types'] = array();
                        }
                        if (isset($function_input['redirect_statuses']) && is_array($function_input['redirect_statuses'])) {
                            $sanitized_function['redirect_statuses'] = array_map('sanitize_text_field', $function_input['redirect_statuses']);
                        } else {
                            $sanitized_function['redirect_statuses'] = array();
                        }
                        if (isset($function_input['redirect_urls']) && is_array($function_input['redirect_urls'])) {
                            $sanitized_function['redirect_urls'] = array();
                            foreach ($function_input['redirect_urls'] as $post_type => $url) {
                                $sanitized_function['redirect_urls'][sanitize_text_field($post_type)] = esc_url_raw($url);
                            }
                        } else {
                            $sanitized_function['redirect_urls'] = array();
                        }
                        break;

                    case 'custom_submission_messages':
                        if (isset($function_input['post_type_settings']) && is_array($function_input['post_type_settings'])) {
                            $sanitized_function['post_type_settings'] = array();
                            foreach ($function_input['post_type_settings'] as $post_type => $pt_settings) {
                                $sanitized_pt = array();
                                $sanitized_pt['enabled'] = !empty($pt_settings['enabled']);
                                if (isset($pt_settings['messages']) && is_array($pt_settings['messages'])) {
                                    $sanitized_pt['messages'] = array();
                                    foreach ($pt_settings['messages'] as $msg_key => $msg_value) {
                                        $sanitized_pt['messages'][sanitize_text_field($msg_key)] = wp_kses_post($msg_value);
                                    }
                                }
                                $sanitized_function['post_type_settings'][sanitize_text_field($post_type)] = $sanitized_pt;
                            }
                        } else {
                            $sanitized_function['post_type_settings'] = array();
                        }
                        break;

                    case 'featured_posts':
                        if (isset($function_input['post_types']) && is_array($function_input['post_types'])) {
                            $sanitized_function['post_types'] = array_map('sanitize_text_field', $function_input['post_types']);
                        } else {
                            $sanitized_function['post_types'] = array();
                        }
                        if (isset($function_input['priority_values']) && is_array($function_input['priority_values'])) {
                            $sanitized_function['priority_values'] = array();
                            foreach ($function_input['priority_values'] as $post_type => $priority) {
                                $sanitized_function['priority_values'][sanitize_text_field($post_type)] = absint($priority);
                            }
                        } else {
                            $sanitized_function['priority_values'] = array();
                        }
                        break;

                    case 'google_analytics':
                        $sanitized_function['ga4_measurement_id'] = isset($function_input['ga4_measurement_id'])
                            ? sanitize_text_field($function_input['ga4_measurement_id']) : '';
                        $sanitized_function['ua_tracking_id'] = isset($function_input['ua_tracking_id'])
                            ? sanitize_text_field($function_input['ua_tracking_id']) : '';
                        $sanitized_function['gtm_container_id'] = isset($function_input['gtm_container_id'])
                            ? sanitize_text_field($function_input['gtm_container_id']) : '';
                        $sanitized_function['custom_head_tags'] = isset($function_input['custom_head_tags'])
                            ? $function_input['custom_head_tags'] : '';
                        $sanitized_function['custom_body_tags'] = isset($function_input['custom_body_tags'])
                            ? $function_input['custom_body_tags'] : '';
                        $sanitized_function['custom_footer_tags'] = isset($function_input['custom_footer_tags'])
                            ? $function_input['custom_footer_tags'] : '';
                        break;

                    case 'media_paste':
                        if (isset($function_input['allowed_roles']) && is_array($function_input['allowed_roles'])) {
                            $sanitized_function['allowed_roles'] = array_map('sanitize_text_field', $function_input['allowed_roles']);
                        } else {
                            $sanitized_function['allowed_roles'] = array('administrator', 'editor', 'author');
                        }
                        $sanitized_function['max_file_size'] = isset($function_input['max_file_size'])
                            ? absint($function_input['max_file_size']) : 5;
                        if (isset($function_input['allowed_types']) && is_array($function_input['allowed_types'])) {
                            $sanitized_function['allowed_types'] = array_map('sanitize_text_field', $function_input['allowed_types']);
                        } else {
                            $sanitized_function['allowed_types'] = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
                        }
                        break;

                    case 'submission_reminder':
                        if (isset($function_input['post_types']) && is_array($function_input['post_types'])) {
                            $sanitized_function['post_types'] = array_map('sanitize_text_field', $function_input['post_types']);
                        } else {
                            $sanitized_function['post_types'] = array();
                        }
                        if (isset($function_input['notifications']) && is_array($function_input['notifications'])) {
                            $sanitized_function['notifications'] = array();
                            foreach ($function_input['notifications'] as $post_type => $pt_notifications) {
                                if (is_array($pt_notifications)) {
                                    $sanitized_function['notifications'][sanitize_text_field($post_type)] = array();
                                    foreach ($pt_notifications as $notif_id => $notification) {
                                        if (is_array($notification)) {
                                            $sanitized_function['notifications'][$post_type][sanitize_text_field($notif_id)] = array(
                                                'enabled' => isset($notification['enabled']) ? sanitize_text_field($notification['enabled']) : 'no',
                                                'time_value' => isset($notification['time_value']) ? absint($notification['time_value']) : 7,
                                                'time_unit' => isset($notification['time_unit']) ? sanitize_text_field($notification['time_unit']) : 'days',
                                                'description' => isset($notification['description']) ? sanitize_text_field($notification['description']) : '',
                                                'subject' => isset($notification['subject']) ? sanitize_text_field($notification['subject']) : '',
                                                'message' => isset($notification['message']) ? wp_kses_post($notification['message']) : ''
                                            );
                                        }
                                    }
                                }
                            }
                        } else {
                            $sanitized_function['notifications'] = array();
                        }
                        break;

                    case 'visitor_location':
                        $valid_modes = array('ip', 'browser', 'gps', 'both');
                        $sanitized_function['visitor_location_mode'] = isset($function_input['visitor_location_mode']) && in_array($function_input['visitor_location_mode'], $valid_modes)
                            ? sanitize_text_field($function_input['visitor_location_mode'])
                            : 'ip';
                        $sanitized_function['visitor_location_cache_duration'] = isset($function_input['visitor_location_cache_duration'])
                            ? absint($function_input['visitor_location_cache_duration'])
                            : 3600;
                        break;

                    case 'compare_posts':
                        // Comparison pages per post type
                        $sanitized_function['comparison_pages'] = array();
                        if (isset($function_input['comparison_pages']) && is_array($function_input['comparison_pages'])) {
                            foreach ($function_input['comparison_pages'] as $pt_key => $page_id) {
                                $sanitized_key = sanitize_key($pt_key);
                                $sanitized_function['comparison_pages'][$sanitized_key] = absint($page_id);
                            }
                        }
                        // Max posts (2-4)
                        $max = isset($function_input['max_posts']) ? absint($function_input['max_posts']) : 4;
                        $sanitized_function['max_posts'] = ($max >= 2 && $max <= 4) ? $max : 4;

                        // Badge styling
                        $sanitized_function['badge_bg_color'] = isset($function_input['badge_bg_color'])
                            ? sanitize_hex_color($function_input['badge_bg_color'])
                            : '#3b82f6';
                        $sanitized_function['badge_text_color'] = isset($function_input['badge_text_color'])
                            ? sanitize_hex_color($function_input['badge_text_color'])
                            : '#ffffff';
                        $sanitized_function['badge_border_radius'] = isset($function_input['badge_border_radius'])
                            ? max(0, min(50, absint($function_input['badge_border_radius'])))
                            : 8;

                        // Popup styling
                        $sanitized_function['popup_bg_color'] = isset($function_input['popup_bg_color'])
                            ? sanitize_hex_color($function_input['popup_bg_color'])
                            : '#ffffff';
                        $sanitized_function['popup_text_color'] = isset($function_input['popup_text_color'])
                            ? sanitize_hex_color($function_input['popup_text_color'])
                            : '#111827';
                        $sanitized_function['popup_border_radius'] = isset($function_input['popup_border_radius'])
                            ? max(0, min(50, absint($function_input['popup_border_radius'])))
                            : 12;

                        // Button styling
                        $sanitized_function['button_bg_color'] = isset($function_input['button_bg_color'])
                            ? sanitize_hex_color($function_input['button_bg_color'])
                            : '#3b82f6';
                        $sanitized_function['button_text_color'] = isset($function_input['button_text_color'])
                            ? sanitize_hex_color($function_input['button_text_color'])
                            : '#ffffff';
                        $sanitized_function['button_border_radius'] = isset($function_input['button_border_radius'])
                            ? max(0, min(30, absint($function_input['button_border_radius'])))
                            : 6;

                        // Secondary button styling
                        $sanitized_function['secondary_bg_color'] = isset($function_input['secondary_bg_color'])
                            ? sanitize_hex_color($function_input['secondary_bg_color'])
                            : '#f3f4f6';
                        $sanitized_function['secondary_text_color'] = isset($function_input['secondary_text_color'])
                            ? sanitize_hex_color($function_input['secondary_text_color'])
                            : '#374151';

                        // Text labels
                        $sanitized_function['badge_text'] = isset($function_input['badge_text'])
                            ? sanitize_text_field($function_input['badge_text'])
                            : __('Compare', 'voxel-toolkit');
                        $sanitized_function['popup_title'] = isset($function_input['popup_title'])
                            ? sanitize_text_field($function_input['popup_title'])
                            : __('Compare Posts', 'voxel-toolkit');
                        $sanitized_function['view_button_text'] = isset($function_input['view_button_text'])
                            ? sanitize_text_field($function_input['view_button_text'])
                            : __('View Comparison', 'voxel-toolkit');
                        $sanitized_function['clear_button_text'] = isset($function_input['clear_button_text'])
                            ? sanitize_text_field($function_input['clear_button_text'])
                            : __('Clear All', 'voxel-toolkit');
                        break;

                    case 'social_proof':
                        // Display settings
                        $valid_positions = array('bottom-left', 'bottom-right', 'top-left', 'top-right');
                        $sanitized_function['position'] = isset($function_input['position']) && in_array($function_input['position'], $valid_positions)
                            ? sanitize_text_field($function_input['position'])
                            : 'bottom-left';

                        $sanitized_function['display_duration'] = isset($function_input['display_duration'])
                            ? max(1, min(60, absint($function_input['display_duration'])))
                            : 5;

                        $sanitized_function['delay_between'] = isset($function_input['delay_between'])
                            ? max(1, min(60, absint($function_input['delay_between'])))
                            : 10;

                        $sanitized_function['max_events'] = isset($function_input['max_events'])
                            ? max(1, min(50, absint($function_input['max_events'])))
                            : 10;

                        $sanitized_function['poll_interval'] = isset($function_input['poll_interval'])
                            ? max(10, min(300, absint($function_input['poll_interval'])))
                            : 30;

                        $valid_animations = array('slide', 'fade');
                        $sanitized_function['animation'] = isset($function_input['animation']) && in_array($function_input['animation'], $valid_animations)
                            ? sanitize_text_field($function_input['animation'])
                            : 'slide';

                        $sanitized_function['hide_on_mobile'] = !empty($function_input['hide_on_mobile']);
                        $sanitized_function['show_close_button'] = !empty($function_input['show_close_button']);

                        // Style settings
                        $sanitized_function['background_color'] = isset($function_input['background_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $function_input['background_color'])
                            ? sanitize_hex_color($function_input['background_color'])
                            : '#ffffff';

                        $sanitized_function['text_color'] = isset($function_input['text_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $function_input['text_color'])
                            ? sanitize_hex_color($function_input['text_color'])
                            : '#1a1a1a';

                        $sanitized_function['border_radius'] = isset($function_input['border_radius'])
                            ? max(0, min(50, absint($function_input['border_radius'])))
                            : 10;

                        $sanitized_function['avatar_size'] = isset($function_input['avatar_size'])
                            ? max(20, min(100, absint($function_input['avatar_size'])))
                            : 48;

                        $sanitized_function['default_avatar'] = isset($function_input['default_avatar'])
                            ? esc_url_raw($function_input['default_avatar'])
                            : '';

                        // Events settings
                        if (isset($function_input['events']) && is_array($function_input['events'])) {
                            $sanitized_function['events'] = array();
                            foreach ($function_input['events'] as $event_key => $event_settings) {
                                $sanitized_event_key = sanitize_text_field($event_key);
                                $sanitized_function['events'][$sanitized_event_key] = array(
                                    'enabled' => !empty($event_settings['enabled']),
                                    'message_template' => isset($event_settings['message_template'])
                                        ? sanitize_text_field($event_settings['message_template'])
                                        : '',
                                    'show_avatar' => !empty($event_settings['show_avatar']),
                                    'show_link' => !empty($event_settings['show_link']),
                                    'show_time' => !empty($event_settings['show_time']),
                                );
                            }
                        } else {
                            $sanitized_function['events'] = array();
                        }

                        // Activity Boost settings
                        $sanitized_function['boost_enabled'] = !empty($function_input['boost_enabled']);

                        $valid_boost_modes = array('fill_gaps', 'mixed', 'boost_only');
                        $sanitized_function['boost_mode'] = isset($function_input['boost_mode']) && in_array($function_input['boost_mode'], $valid_boost_modes)
                            ? sanitize_text_field($function_input['boost_mode'])
                            : 'fill_gaps';

                        $sanitized_function['boost_names'] = isset($function_input['boost_names'])
                            ? sanitize_textarea_field($function_input['boost_names'])
                            : '';

                        $sanitized_function['boost_listings'] = isset($function_input['boost_listings'])
                            ? sanitize_textarea_field($function_input['boost_listings'])
                            : '';

                        // Boost messages
                        if (isset($function_input['boost_messages']) && is_array($function_input['boost_messages'])) {
                            $sanitized_function['boost_messages'] = array();
                            foreach ($function_input['boost_messages'] as $msg_key => $msg_template) {
                                $sanitized_function['boost_messages'][sanitize_key($msg_key)] = sanitize_text_field($msg_template);
                            }
                        } else {
                            $sanitized_function['boost_messages'] = array(
                                'booking' => '{name} just booked {listing}',
                                'signup' => '{name} just joined',
                                'review' => '{name} left a review on {listing}',
                            );
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
                
                } catch (Exception $e) {
                    // Log error but don't break the sanitization process
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

        // Check if it's a post field (with post_field_ prefix)
        $is_post_field = strpos($function_key, 'post_field_') === 0;

        if ($is_post_field) {
            // Extract the actual field key (remove post_field_ prefix)
            $field_key = str_replace('post_field_', '', $function_key);
            $available_post_fields = $this->post_fields_manager->get_available_post_fields();

            // Validate post field exists
            if (!isset($available_post_fields[$field_key])) {
                wp_send_json_error(__('Invalid post field.', 'voxel-toolkit'));
            }
        } else {
            // Validate function exists
            $available_functions = $this->functions_manager->get_available_functions();
            if (!isset($available_functions[$function_key])) {
                wp_send_json_error(__('Invalid function.', 'voxel-toolkit'));
            }
        }

        // Toggle function or post field
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
     * Handle bulk toggle widgets AJAX request
     */
    public function ajax_bulk_toggle_widgets() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'voxel_toolkit_widget_nonce')) {
            wp_send_json_error(__('Security check failed.', 'voxel-toolkit'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions.', 'voxel-toolkit'));
        }

        $action = sanitize_text_field($_POST['bulk_action']); // 'enable' or 'disable'
        $available_widgets = $this->functions_manager->get_available_widgets();

        $success_count = 0;
        $total_count = count($available_widgets);

        foreach ($available_widgets as $widget_key => $widget_data) {
            $widget_key_full = 'widget_' . $widget_key;

            if ($action === 'enable') {
                $result = $this->settings->enable_function($widget_key_full);
            } else {
                $result = $this->settings->disable_function($widget_key_full);
            }

            if ($result) {
                $success_count++;
            }
        }

        if ($success_count === $total_count) {
            wp_send_json_success(array(
                'message' => $action === 'enable'
                    ? __('All widgets enabled successfully.', 'voxel-toolkit')
                    : __('All widgets disabled successfully.', 'voxel-toolkit'),
                'count' => $success_count
            ));
        } else {
            wp_send_json_error(sprintf(
                __('Only %d of %d widgets were updated.', 'voxel-toolkit'),
                $success_count,
                $total_count
            ));
        }
    }

    /**
     * Get widget usage details AJAX handler
     */
    public function ajax_get_widget_usage() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'voxel_toolkit_widget_nonce')) {
            wp_send_json_error(__('Security check failed.', 'voxel-toolkit'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions.', 'voxel-toolkit'));
        }

        $widget_key = sanitize_text_field($_POST['widget_key']);
        $pages = $this->get_widget_usage_pages($widget_key);

        if (!empty($pages)) {
            wp_send_json_success(array(
                'pages' => $pages,
                'count' => count($pages)
            ));
        } else {
            wp_send_json_success(array(
                'pages' => array(),
                'count' => 0,
                'message' => __('This widget is not currently used on any pages.', 'voxel-toolkit')
            ));
        }
    }

    /**
     * Get detailed list of pages using a widget
     *
     * @param string $widget_key Widget key
     * @return array List of pages with title, ID, edit link, and view link
     */
    private function get_widget_usage_pages($widget_key) {
        global $wpdb;

        // Get widget data to find the actual widget name
        $available_widgets = $this->functions_manager->get_available_widgets();
        $widget_data = isset($available_widgets[$widget_key]) ? $available_widgets[$widget_key] : null;

        // Get the actual widget name used in Elementor
        // Use widget_name field if available, otherwise fall back to old format
        if ($widget_data && isset($widget_data['widget_name'])) {
            $widget_type = $widget_data['widget_name'];
        } else {
            $widget_type = 'voxel-' . $widget_key;
        }

        // Search for the specific widget type in Elementor JSON data
        // Using JSON pattern to be more precise: "widgetType":"widget_name"
        $widget_pattern = '%"widgetType":"' . $widget_type . '"%';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.post_id, pm.meta_value, p.post_title, p.post_type, p.post_status
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_elementor_data'
            AND pm.meta_value LIKE %s
            AND p.post_type != 'revision'
            AND p.post_status != 'trash'
            AND p.post_status != 'auto-draft'
            ORDER BY p.post_modified DESC",
            $widget_pattern
        ));

        $pages = array();
        foreach ($results as $result) {
            // Double-check by parsing JSON to verify widget actually exists
            // (not just a string match in a text field or similar)
            $elementor_data = json_decode($result->meta_value, true);
            if ($this->widget_exists_in_elementor_data($elementor_data, $widget_type)) {
                $post_type_obj = get_post_type_object($result->post_type);

                $pages[] = array(
                    'id' => $result->post_id,
                    'title' => $result->post_title ?: __('(no title)', 'voxel-toolkit'),
                    'type' => $post_type_obj ? $post_type_obj->labels->singular_name : $result->post_type,
                    'status' => $result->post_status,
                    'edit_link' => get_edit_post_link($result->post_id),
                    'view_link' => get_permalink($result->post_id),
                    'elementor_edit_link' => admin_url('post.php?post=' . $result->post_id . '&action=elementor')
                );
            }
        }

        return $pages;
    }

    /**
     * Recursively check if widget exists in Elementor data structure
     *
     * @param array $data Elementor data array
     * @param string $widget_type Widget type to search for
     * @return bool True if widget found
     */
    private function widget_exists_in_elementor_data($data, $widget_type) {
        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $element) {
            if (!is_array($element)) {
                continue;
            }

            // Check if this element is the widget we're looking for
            if (isset($element['widgetType']) && $element['widgetType'] === $widget_type) {
                return true;
            }

            // Recursively check nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                if ($this->widget_exists_in_elementor_data($element['elements'], $widget_type)) {
                    return true;
                }
            }
        }

        return false;
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

        // Filter out hidden widgets
        $available_widgets = array_filter($available_widgets, function($widget) {
            return empty($widget['hidden']);
        });

        // Sort widgets alphabetically by name
        uasort($available_widgets, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        ?>
        <div class="wrap voxel-toolkit-widgets-page">
            <h1><?php _e('Voxel Toolkit - Elementor Widgets', 'voxel-toolkit'); ?></h1>

            <div class="voxel-toolkit-intro">
                <p><?php _e('Enhance your Elementor page builder with these additional widgets. Each widget can be enabled/disabled independently and includes comprehensive styling controls.', 'voxel-toolkit'); ?></p>
            </div>

            <!-- Controls Bar -->
            <div class="voxel-toolkit-widgets-controls">
                <div class="voxel-toolkit-widgets-search">
                    <input type="text"
                           id="voxel-widgets-search"
                           class="voxel-toolkit-search-input"
                           placeholder="<?php _e('Search widgets...', 'voxel-toolkit'); ?>"
                           autocomplete="off">
                    <span class="dashicons dashicons-search"></span>
                </div>
                <div class="voxel-toolkit-widgets-actions">
                    <button type="button" class="button button-secondary" id="voxel-widgets-disable-all">
                        <?php _e('Disable All', 'voxel-toolkit'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="voxel-widgets-enable-all">
                        <?php _e('Enable All', 'voxel-toolkit'); ?>
                    </button>
                </div>
            </div>

            <div class="voxel-toolkit-widgets-grid">
                <?php foreach ($available_widgets as $widget_key => $widget_data): ?>
                    <?php $this->render_widget_card($widget_key, $widget_data); ?>
                <?php endforeach; ?>

                <?php if (empty($available_widgets)): ?>
                    <div class="voxel-toolkit-no-widgets">
                        <p><?php _e('No widgets are currently available. More widgets will be added in future updates!', 'voxel-toolkit'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="voxel-toolkit-no-results" style="display: none;">
                <div class="voxel-toolkit-no-results-inner">
                    <span class="dashicons dashicons-search"></span>
                    <p><?php _e('No widgets found matching your search.', 'voxel-toolkit'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render post fields page
     */
    public function render_post_fields_page() {
        $available_post_fields = $this->post_fields_manager->get_available_post_fields();

        // Sort fields alphabetically by name
        uasort($available_post_fields, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        ?>
        <div class="wrap voxel-toolkit-post-fields-page">
            <h1><?php _e('Voxel Toolkit - Custom Post Fields', 'voxel-toolkit'); ?></h1>

            <div class="voxel-toolkit-intro">
                <p><?php _e('Extend Voxel post types with custom field types. Once enabled, these fields will be available when creating or editing post type field configurations in Voxel.', 'voxel-toolkit'); ?></p>
            </div>

            <div class="voxel-toolkit-widgets-grid">
                <?php foreach ($available_post_fields as $field_key => $field_data): ?>
                    <?php $this->render_post_field_card($field_key, $field_data); ?>
                <?php endforeach; ?>

                <?php if (empty($available_post_fields)): ?>
                    <div class="voxel-toolkit-no-widgets">
                        <p><?php _e('No custom post fields are currently available. More fields will be added in future updates!', 'voxel-toolkit'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render post field card
     *
     * @param string $field_key Field key
     * @param array $field_data Field data
     */
    private function render_post_field_card($field_key, $field_data) {
        $field_key_full = 'post_field_' . $field_key;
        $is_enabled = $this->settings->is_function_enabled($field_key_full);
        ?>
        <div class="voxel-toolkit-widget-card"
             data-widget-key="<?php echo esc_attr($field_key); ?>"
             data-widget-name="<?php echo esc_attr(strtolower($field_data['name'])); ?>"
             data-widget-description="<?php echo esc_attr(strtolower($field_data['description'])); ?>">

            <div class="voxel-toolkit-widget-header">
                <div class="voxel-toolkit-widget-icon">
                    <span class="dashicons <?php echo esc_attr($field_data['icon'] ?? 'dashicons-forms'); ?>"></span>
                </div>
                <div class="voxel-toolkit-widget-meta">
                    <div class="voxel-toolkit-widget-title-row">
                        <h3 class="voxel-toolkit-widget-title"><?php echo esc_html($field_data['name']); ?></h3>
                        <?php if ($is_enabled): ?>
                            <span class="voxel-toolkit-widget-badge voxel-toolkit-badge-enabled"><?php _e('Enabled', 'voxel-toolkit'); ?></span>
                        <?php else: ?>
                            <span class="voxel-toolkit-widget-badge voxel-toolkit-badge-disabled"><?php _e('Disabled', 'voxel-toolkit'); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="voxel-toolkit-widget-description"><?php echo esc_html($field_data['description']); ?></p>
                </div>
            </div>

            <div class="voxel-toolkit-widget-footer">
                <div class="voxel-toolkit-widget-toggle">
                    <label class="toggle-switch">
                        <input type="checkbox"
                               class="function-toggle-checkbox"
                               data-function="<?php echo esc_attr($field_key_full); ?>"
                               <?php checked($is_enabled); ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>
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
        $usage_count = $this->get_widget_usage_count($widget_key, $widget_data);
        ?>
        <div class="voxel-toolkit-widget-card"
             data-widget-key="<?php echo esc_attr($widget_key); ?>"
             data-widget-name="<?php echo esc_attr(strtolower($widget_data['name'])); ?>"
             data-widget-description="<?php echo esc_attr(strtolower($widget_data['description'])); ?>">

            <div class="voxel-toolkit-widget-header">
                <div class="voxel-toolkit-widget-icon">
                    <?php if (isset($widget_data['icon'])): ?>
                        <i class="<?php echo esc_attr($widget_data['icon']); ?>"></i>
                    <?php else: ?>
                        <span class="dashicons dashicons-admin-customizer"></span>
                    <?php endif; ?>
                </div>
                <div class="voxel-toolkit-widget-meta">
                    <div class="voxel-toolkit-widget-title-row">
                        <h3 class="voxel-toolkit-widget-title"><?php echo esc_html($widget_data['name']); ?></h3>
                        <?php if ($is_enabled): ?>
                            <span class="voxel-toolkit-widget-badge voxel-toolkit-badge-enabled"><?php _e('Enabled', 'voxel-toolkit'); ?></span>
                        <?php else: ?>
                            <span class="voxel-toolkit-widget-badge voxel-toolkit-badge-disabled"><?php _e('Disabled', 'voxel-toolkit'); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="voxel-toolkit-widget-description"><?php echo esc_html($widget_data['description']); ?></p>
                </div>
            </div>

            <div class="voxel-toolkit-widget-footer">
                <div class="voxel-toolkit-widget-usage">
                    <?php if ($usage_count > 0): ?>
                        <span class="voxel-toolkit-usage-badge"
                              data-widget="<?php echo esc_attr($widget_key); ?>"
                              title="<?php _e('Click to see where this widget is used', 'voxel-toolkit'); ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                            <?php printf(_n('Used in %d page', 'Used in %d pages', $usage_count, 'voxel-toolkit'), $usage_count); ?>
                        </span>
                    <?php else: ?>
                        <span class="voxel-toolkit-usage-badge voxel-toolkit-usage-none">
                            <?php _e('Not used yet', 'voxel-toolkit'); ?>
                        </span>
                    <?php endif; ?>
                </div>
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

    /**
     * Get widget usage count
     *
     * @param string $widget_key Widget key
     * @return int Usage count
     */
    private function get_widget_usage_count($widget_key, $widget_data = null) {
        global $wpdb;

        // Get widget data if not provided
        if ($widget_data === null) {
            $available_widgets = $this->functions_manager->get_available_widgets();
            $widget_data = isset($available_widgets[$widget_key]) ? $available_widgets[$widget_key] : null;
        }

        // Get the actual widget name used in Elementor
        // Use widget_name field if available, otherwise fall back to old format
        if (isset($widget_data['widget_name'])) {
            $widget_type = $widget_data['widget_name'];
        } else {
            $widget_type = 'voxel-' . $widget_key;
        }

        // Search for the specific widget type in Elementor JSON data
        // Using JSON pattern to be more precise: "widgetType":"widget_name"
        $widget_pattern = '%"widgetType":"' . $widget_type . '"%';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.post_id, pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_elementor_data'
            AND pm.meta_value LIKE %s
            AND p.post_type != 'revision'
            AND p.post_status != 'trash'
            AND p.post_status != 'auto-draft'",
            $widget_pattern
        ));

        // Verify each result by parsing JSON to avoid false positives
        $count = 0;
        foreach ($results as $result) {
            $elementor_data = json_decode($result->meta_value, true);
            if ($this->widget_exists_in_elementor_data($elementor_data, $widget_type)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Render dynamic tags page
     */
    public function render_dynamic_tags_page() {
        ?>
        <div class="wrap voxel-toolkit-dynamic-tags-page">
            <h1><?php _e('Voxel Toolkit - Dynamic Tags', 'voxel-toolkit'); ?></h1>

            <div class="voxel-toolkit-intro">
                <p><?php _e('Custom dynamic data properties and methods that extend Voxel\'s built-in dynamic tags. Use these tags in any Voxel template, Elementor widget, or anywhere dynamic data is supported.', 'voxel-toolkit'); ?></p>
            </div>

            <!-- Post Properties -->
            <div class="settings-section">
                <h2><?php _e('Post Properties', 'voxel-toolkit'); ?></h2>
                <p class="description"><?php _e('Dynamic properties available for post objects. Use with @post() syntax.', 'voxel-toolkit'); ?></p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Property', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Description', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Usage Example', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Output Example', 'voxel-toolkit'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>reading_time</code></td>
                            <td><?php _e('Estimated reading time based on word count (200 words per minute)', 'voxel-toolkit'); ?></td>
                            <td><code>@post(reading_time)</code></td>
                            <td><em>5 min</em> or <em>1 hr 30 min</em></td>
                        </tr>
                        <tr>
                            <td><code>word_count</code></td>
                            <td><?php _e('Total word count in post content', 'voxel-toolkit'); ?></td>
                            <td><code>@post(word_count)</code></td>
                            <td><em>1250</em></td>
                        </tr>
                        <tr>
                            <td><code>feed_position</code></td>
                            <td><?php _e('Position number in post feed (1, 2, 3, etc.). Absolute across pages.', 'voxel-toolkit'); ?></td>
                            <td><code>@post(feed_position)</code></td>
                            <td><em>1</em>, <em>2</em>, <em>11</em> (on page 2)</td>
                        </tr>
                    </tbody>
                </table>
                <p style="margin-top: 15px;">
                    <strong><?php _e('Note for feed_position:', 'voxel-toolkit'); ?></strong>
                    <em><?php _e('After editing a preview card template in Elementor, refresh the page with the post feed to see correct numbering. This tag only works inside post feed preview cards.', 'voxel-toolkit'); ?></em>
                </p>
            </div>

            <!-- User/Author Properties -->
            <div class="settings-section" style="margin-top: 30px;">
                <h2><?php _e('User & Author Properties', 'voxel-toolkit'); ?></h2>
                <p class="description"><?php _e('Dynamic properties available for user and author objects. Use with @user() or @author() syntax.', 'voxel-toolkit'); ?></p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Property', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Description', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Usage Example', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Output Example', 'voxel-toolkit'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>membership_expiration</code></td>
                            <td><?php _e('User\'s membership expiration date from Voxel plan (formatted per WordPress date settings)', 'voxel-toolkit'); ?></td>
                            <td><code>@user(membership_expiration)</code><br><code>@author(membership_expiration)</code></td>
                            <td><em>February 24, 2026</em></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Campaign Progress -->
            <?php if ($this->settings->is_function_enabled('widget_campaign_progress')): ?>
                <div class="settings-section" style="margin-top: 30px;">
                    <h2><?php _e('Campaign Progress', 'voxel-toolkit'); ?></h2>
                    <p class="description"><?php _e('Dynamic properties for campaign/crowdfunding data. Use with @post() syntax on campaign posts.', 'voxel-toolkit'); ?></p>

                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Property', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Description', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Usage Example', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Output Example', 'voxel-toolkit'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>campaign_amount_donated</code></td>
                                <td><?php _e('Total amount raised for the campaign', 'voxel-toolkit'); ?></td>
                                <td><code>@post(campaign_amount_donated)</code></td>
                                <td><em>1250.50</em></td>
                            </tr>
                            <tr>
                                <td><code>campaign_number_of_donors</code></td>
                                <td><?php _e('Total number of unique donors/donations', 'voxel-toolkit'); ?></td>
                                <td><code>@post(campaign_number_of_donors)</code></td>
                                <td><em>25</em></td>
                            </tr>
                            <tr>
                                <td><code>campaign_percentage_donated</code></td>
                                <td><?php _e('Percentage of goal reached (whole number, 0-100). Requires goal to be set via Campaign Progress widget.', 'voxel-toolkit'); ?></td>
                                <td><code>@post(campaign_percentage_donated)</code></td>
                                <td><em>65</em></td>
                            </tr>
                        </tbody>
                    </table>
                    <p style="margin-top: 15px;">
                        <em><?php _e('Note: These tags require the Campaign Progress widget to be placed on a page and rendered at least once to store the goal amount.', 'voxel-toolkit'); ?></em>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Article Helpful -->
            <?php if ($this->settings->is_function_enabled('widget_article_helpful')): ?>
                <div class="settings-section" style="margin-top: 30px;">
                    <h2><?php _e('Article Helpful', 'voxel-toolkit'); ?></h2>
                    <p class="description"><?php _e('Dynamic properties for article helpful voting data. Use with @post() syntax on posts with Article Helpful widget.', 'voxel-toolkit'); ?></p>

                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Property', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Description', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Usage Example', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Output Example', 'voxel-toolkit'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>article_helpful_yes_count</code></td>
                                <td><?php _e('Number of "Yes" votes for this article', 'voxel-toolkit'); ?></td>
                                <td><code>@post(article_helpful_yes_count)</code></td>
                                <td><em>42</em></td>
                            </tr>
                            <tr>
                                <td><code>article_helpful_no_count</code></td>
                                <td><?php _e('Number of "No" votes for this article', 'voxel-toolkit'); ?></td>
                                <td><code>@post(article_helpful_no_count)</code></td>
                                <td><em>8</em></td>
                            </tr>
                            <tr>
                                <td><code>article_helpful_total_votes</code></td>
                                <td><?php _e('Total number of votes (Yes + No)', 'voxel-toolkit'); ?></td>
                                <td><code>@post(article_helpful_total_votes)</code></td>
                                <td><em>50</em></td>
                            </tr>
                            <tr>
                                <td><code>article_helpful_percentage</code></td>
                                <td><?php _e('Percentage of "Yes" votes (whole number, 0-100)', 'voxel-toolkit'); ?></td>
                                <td><code>@post(article_helpful_percentage)</code></td>
                                <td><em>84</em></td>
                            </tr>
                        </tbody>
                    </table>
                    <p style="margin-top: 15px;">
                        <em><?php _e('Note: These tags return 0 if no votes have been recorded for the post.', 'voxel-toolkit'); ?></em>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Share Count -->
            <?php if ($this->settings->is_function_enabled('share_count')): ?>
                <div class="settings-section" style="margin-top: 30px;">
                    <h2><?php _e('Share Count', 'voxel-toolkit'); ?></h2>
                    <p class="description"><?php _e('Dynamic properties for tracking share button clicks. Use with @post() syntax on posts with share functionality.', 'voxel-toolkit'); ?></p>

                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Property', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Description', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Usage Example', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Output Example', 'voxel-toolkit'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>share_count</code></td>
                                <td><?php _e('Total number of shares across all networks (shorthand for share_count.total)', 'voxel-toolkit'); ?></td>
                                <td><code>@post(share_count)</code></td>
                                <td><em>156</em></td>
                            </tr>
                            <tr>
                                <td><code>share_count.total</code></td>
                                <td><?php _e('Total number of shares across all networks', 'voxel-toolkit'); ?></td>
                                <td><code>@post(share_count.total)</code></td>
                                <td><em>156</em></td>
                            </tr>
                            <tr>
                                <td><code>share_count.facebook</code></td>
                                <td><?php _e('Number of Facebook shares', 'voxel-toolkit'); ?></td>
                                <td><code>@post(share_count.facebook)</code></td>
                                <td><em>42</em></td>
                            </tr>
                            <tr>
                                <td><code>share_count.twitter</code></td>
                                <td><?php _e('Number of X/Twitter shares', 'voxel-toolkit'); ?></td>
                                <td><code>@post(share_count.twitter)</code></td>
                                <td><em>28</em></td>
                            </tr>
                            <tr>
                                <td><code>share_count.linkedin</code></td>
                                <td><?php _e('Number of LinkedIn shares', 'voxel-toolkit'); ?></td>
                                <td><code>@post(share_count.linkedin)</code></td>
                                <td><em>15</em></td>
                            </tr>
                            <tr>
                                <td><code>share_count.whatsapp</code></td>
                                <td><?php _e('Number of WhatsApp shares', 'voxel-toolkit'); ?></td>
                                <td><code>@post(share_count.whatsapp)</code></td>
                                <td><em>22</em></td>
                            </tr>
                            <tr>
                                <td><code>share_count.telegram</code></td>
                                <td><?php _e('Number of Telegram shares', 'voxel-toolkit'); ?></td>
                                <td><code>@post(share_count.telegram)</code></td>
                                <td><em>8</em></td>
                            </tr>
                            <tr>
                                <td><code>share_count.copy-link</code></td>
                                <td><?php _e('Number of Copy Link actions', 'voxel-toolkit'); ?></td>
                                <td><code>@post(share_count.copy-link)</code></td>
                                <td><em>35</em></td>
                            </tr>
                            <tr>
                                <td><code>share_count.email</code></td>
                                <td><?php _e('Number of Email shares', 'voxel-toolkit'); ?></td>
                                <td><code>@post(share_count.email)</code></td>
                                <td><em>12</em></td>
                            </tr>
                        </tbody>
                    </table>
                    <p style="margin-top: 15px;">
                        <strong><?php _e('Additional Networks:', 'voxel-toolkit'); ?></strong>
                        <code>reddit</code>, <code>tumblr</code>, <code>pinterest</code>, <code>threads</code>, <code>bluesky</code>, <code>sms</code>, <code>line</code>, <code>viber</code>, <code>snapchat</code>, <code>kakaotalk</code>, <code>native-share</code>
                    </p>
                    <p style="margin-top: 10px;">
                        <em><?php _e('Note: Shares are tracked when users click on share menu items. Counts are stored per-post and per-network.', 'voxel-toolkit'); ?></em>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Auto Reply Field -->
            <?php if ($this->settings->is_function_enabled('post_field_auto_reply_field')): ?>
                <div class="settings-section" style="margin-top: 30px;">
                    <h2><?php _e('Auto Reply Field', 'voxel-toolkit'); ?></h2>
                    <p class="description"><?php _e('Custom field type for automatic message responses. Add this field to any post type and access the value via dynamic tags.', 'voxel-toolkit'); ?></p>

                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Property', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Description', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Usage Example', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Output Example', 'voxel-toolkit'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>auto-reply-vt</code></td>
                                <td><?php _e('The auto-reply message configured for this post', 'voxel-toolkit'); ?></td>
                                <td><code>@post(auto-reply-vt)</code></td>
                                <td><em>Thanks for reaching out! I'll get back to you shortly.</em></td>
                            </tr>
                        </tbody>
                    </table>
                    <p style="margin-top: 15px;">
                        <em><?php _e('Note: The field key may vary if you customize it when adding the field to your post type.', 'voxel-toolkit'); ?></em>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Site Options -->
            <?php if ($this->settings->is_function_enabled('options_page')): ?>
                <?php
                $options_config = $this->settings->get_function_settings('options_page');
                $options_fields = isset($options_config['fields']) ? $options_config['fields'] : array();
                ?>
                <div class="settings-section" style="margin-top: 30px;">
                    <h2><?php _e('Site Options', 'voxel-toolkit'); ?></h2>
                    <p class="description"><?php _e('Custom site-wide options configured in the Options Page function. Use with @site(options.field_name) syntax.', 'voxel-toolkit'); ?></p>

                    <?php if (!empty($options_fields)): ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Field Name', 'voxel-toolkit'); ?></th>
                                    <th><?php _e('Type', 'voxel-toolkit'); ?></th>
                                    <th><?php _e('Usage Example', 'voxel-toolkit'); ?></th>
                                    <th><?php _e('Description', 'voxel-toolkit'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($options_fields as $field_name => $field_config): ?>
                                    <tr>
                                        <td><code><?php echo esc_html($field_name); ?></code></td>
                                        <td><?php echo esc_html(ucfirst($field_config['type'])); ?></td>
                                        <td><code>@site(options.<?php echo esc_html($field_name); ?>)</code><?php if ($field_config['type'] === 'image'): ?><br><code>@site(options.<?php echo esc_html($field_name); ?>).url</code><?php endif; ?></td>
                                        <td><?php echo esc_html($field_config['label']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="margin-top: 15px;">
                            <a href="<?php echo admin_url('admin.php?page=voxel-toolkit-site-options'); ?>" class="button">
                                <?php _e('Edit Site Options', 'voxel-toolkit'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=voxel-toolkit-settings#section-options_page'); ?>" class="button">
                                <?php _e('Configure Fields', 'voxel-toolkit'); ?>
                            </a>
                        </p>
                    <?php else: ?>
                        <p><em><?php _e('No fields configured yet.', 'voxel-toolkit'); ?></em></p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=voxel-toolkit-settings#section-options_page'); ?>" class="button">
                                <?php _e('Configure Fields', 'voxel-toolkit'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Visitor Location -->
            <?php if ($this->settings->is_function_enabled('visitor_location')): ?>
                <div class="settings-section" style="margin-top: 30px;">
                    <h2><?php _e('Visitor Location', 'voxel-toolkit'); ?></h2>
                    <p class="description"><?php _e('Visitor location properties detected via IP geolocation or browser GPS. Use with @site(visitor.property) syntax.', 'voxel-toolkit'); ?></p>

                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Property', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Description', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Usage Example', 'voxel-toolkit'); ?></th>
                                <th><?php _e('Output Example', 'voxel-toolkit'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>visitor.location</code></td>
                                <td><?php _e('Full location with smart formatting (City, State for US / City, Country for international)', 'voxel-toolkit'); ?></td>
                                <td><code>@site(visitor.location)</code></td>
                                <td><em>Baltimore, MD</em> or <em>Paris, France</em></td>
                            </tr>
                            <tr>
                                <td><code>visitor.city</code></td>
                                <td><?php _e('City name only', 'voxel-toolkit'); ?></td>
                                <td><code>@site(visitor.city)</code></td>
                                <td><em>Baltimore</em></td>
                            </tr>
                            <tr>
                                <td><code>visitor.state</code></td>
                                <td><?php _e('State or region name', 'voxel-toolkit'); ?></td>
                                <td><code>@site(visitor.state)</code></td>
                                <td><em>Maryland</em></td>
                            </tr>
                            <tr>
                                <td><code>visitor.country</code></td>
                                <td><?php _e('Country name', 'voxel-toolkit'); ?></td>
                                <td><code>@site(visitor.country)</code></td>
                                <td><em>United States</em></td>
                            </tr>
                        </tbody>
                    </table>
                    <p style="margin-top: 15px;">
                        <strong><?php _e('Detection Modes:', 'voxel-toolkit'); ?></strong><br>
                        <em><?php _e('IP Geolocation:', 'voxel-toolkit'); ?></em> <?php _e('Automatic detection using visitor IP address. Queries multiple services for best accuracy (~50-100 mile radius).', 'voxel-toolkit'); ?><br>
                        <em><?php _e('Browser Geolocation:', 'voxel-toolkit'); ?></em> <?php _e('GPS-level accuracy using device location (requires user permission). Falls back to IP if denied.', 'voxel-toolkit'); ?>
                    </p>
                    <p style="margin-top: 10px;">
                        <a href="<?php echo admin_url('admin.php?page=voxel-toolkit-settings#section-visitor_location'); ?>" class="button">
                            <?php _e('Configure Settings', 'voxel-toolkit'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Post Fields Anywhere -->
            <div class="settings-section" style="margin-top: 30px;">
                <h2><?php _e('Post Fields Anywhere', 'voxel-toolkit'); ?></h2>
                <p class="description"><?php _e('Render any @post() tag in the context of a different post. Perfect for displaying related post data, featured listings, or cross-post references.', 'voxel-toolkit'); ?></p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Method', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Description', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Parameters', 'voxel-toolkit'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>render_post_tag</code></td>
                            <td><?php _e('Renders any dynamic tag expression in the context of a specific post ID', 'voxel-toolkit'); ?></td>
                            <td>
                                <code>post_id</code> - <?php _e('The ID of the post to use as context', 'voxel-toolkit'); ?><br>
                                <code>tag</code> - <?php _e('The @post() tag to render', 'voxel-toolkit'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h3 style="margin-top: 20px;"><?php _e('Usage Examples', 'voxel-toolkit'); ?></h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Example', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Description', 'voxel-toolkit'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>@site().render_post_tag(123, @post(title))</code></td>
                            <td><?php _e('Get the title of post ID 123', 'voxel-toolkit'); ?></td>
                        </tr>
                        <tr>
                            <td><code>@site().render_post_tag(123, @post(taxonomy.slug))</code></td>
                            <td><?php _e('Get the taxonomy slug of post ID 123', 'voxel-toolkit'); ?></td>
                        </tr>
                        <tr>
                            <td><code>@site().render_post_tag(123, @post(location.lng))</code></td>
                            <td><?php _e('Get the location longitude of post ID 123', 'voxel-toolkit'); ?></td>
                        </tr>
                        <tr>
                            <td><code>@site().render_post_tag(@post(related_post), @post(price))</code></td>
                            <td><?php _e('Get price from a related post field (dynamic post ID)', 'voxel-toolkit'); ?></td>
                        </tr>
                        <tr>
                            <td><code>@site().render_post_tag(123, @post(logo.url))</code></td>
                            <td><?php _e('Get the logo image URL of post ID 123', 'voxel-toolkit'); ?></td>
                        </tr>
                    </tbody>
                </table>

                <p style="margin-top: 15px; background: #f0f6fc; padding: 12px; border-radius: 4px; border-left: 4px solid #2271b1;">
                    <strong><?php _e('Tip:', 'voxel-toolkit'); ?></strong> <?php _e('This is especially useful for displaying featured posts, related content, or any scenario where you need to show data from a post other than the current one.', 'voxel-toolkit'); ?>
                </p>
            </div>

            <!-- Modifiers -->
            <div class="settings-section" style="margin-top: 30px;">
                <h2><?php _e('Modifiers', 'voxel-toolkit'); ?></h2>
                <p class="description"><?php _e('Dynamic modifiers that can be applied to any value. Use with .modifier() syntax.', 'voxel-toolkit'); ?></p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Modifier', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Description', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Parameters', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Usage Example', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Output Example', 'voxel-toolkit'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>file_size</code></td>
                            <td><?php _e('Get formatted file size from file ID', 'voxel-toolkit'); ?></td>
                            <td><?php _e('None', 'voxel-toolkit'); ?></td>
                            <td><code>@post(upload-media.id).file_size()</code></td>
                            <td><em>2.45 MB</em></td>
                        </tr>
                        <tr>
                            <td><code>file_extension</code></td>
                            <td><?php _e('Get file extension from file ID', 'voxel-toolkit'); ?></td>
                            <td><?php _e('None', 'voxel-toolkit'); ?></td>
                            <td><code>@post(upload-media.id).file_extension()</code></td>
                            <td><em>zip</em></td>
                        </tr>
                        <tr>
                            <td><code>address_part</code></td>
                            <td><?php _e('Extract specific component from address field', 'voxel-toolkit'); ?></td>
                            <td><?php _e('Component: number, street, city, state, postal_code, country', 'voxel-toolkit'); ?></td>
                            <td>
                                <code>@post(location.address).address_part(city)</code><br>
                                <code>@post(location.address).address_part(postal_code)</code>
                            </td>
                            <td>
                                <em>New York</em><br>
                                <em>10001</em>
                            </td>
                        </tr>
                        <tr>
                            <td><code>tally</code></td>
                            <td><?php _e('Count published posts in a post type', 'voxel-toolkit'); ?></td>
                            <td><?php _e('None (detects post type from chain)', 'voxel-toolkit'); ?></td>
                            <td>
                                <code>@site(post_types.member.singular).tally</code><br>
                                <code>@site(post_types.event.plural).tally</code>
                            </td>
                            <td>
                                <em>500</em><br>
                                <em>1234</em>
                            </td>
                        </tr>
                        <tr>
                            <td><code>sold</code></td>
                            <td><?php _e('Count total quantity sold for a product from orders', 'voxel-toolkit'); ?></td>
                            <td><?php _e('None (uses post ID from chain)', 'voxel-toolkit'); ?></td>
                            <td>
                                <code>@post(id).sold()</code>
                            </td>
                            <td>
                                <em>42</em>
                            </td>
                        </tr>
                        <tr>
                            <td><code>generate_qr_code</code></td>
                            <td><?php _e('Generate a QR code image from any URL with optional logo overlay and download button.', 'voxel-toolkit'); ?> <strong><?php _e('Must be used in an Elementor HTML widget.', 'voxel-toolkit'); ?></strong></td>
                            <td>
                                <?php _e('1. Logo URL (optional)', 'voxel-toolkit'); ?><br>
                                <?php _e('2. QR Color hex (optional)', 'voxel-toolkit'); ?><br>
                                <?php _e('3. Button text (leave blank to hide)', 'voxel-toolkit'); ?><br>
                                <?php _e('4. Quality: 1500/2000/3000 (optional)', 'voxel-toolkit'); ?><br>
                                <?php _e('5. Button color hex (optional)', 'voxel-toolkit'); ?><br>
                                <?php _e('6. Filename (optional)', 'voxel-toolkit'); ?>
                            </td>
                            <td>
                                <code>@post(permalink).generate_qr_code(@post(logo.url),#ff0000,Download QR,2000,#ff0000,@post(title)-qr)</code><br>
                                <code>@post(permalink).generate_qr_code(,,,,)</code> <em><?php _e('(no button)', 'voxel-toolkit'); ?></em>
                            </td>
                            <td>
                                <em><?php _e('QR code image with optional logo and download button', 'voxel-toolkit'); ?></em>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p style="margin-top: 10px; font-size: 12px; color: #666;">
                    <?php _e('QR Code modifier contributed by', 'voxel-toolkit'); ?> <a href="https://www.linkedin.com/in/kevinekelmans/" target="_blank" rel="noopener">Kevin Ekelmans</a>
                </p>
            </div>

            <!-- User/Author Methods -->
            <div class="settings-section" style="margin-top: 30px;">
                <h2><?php _e('User & Author Methods', 'voxel-toolkit'); ?></h2>
                <p class="description"><?php _e('Dynamic methods available for user and author objects. Methods accept parameters and use @user().method() or @author().method() syntax.', 'voxel-toolkit'); ?></p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Method', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Description', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Parameters', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Usage Example', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Output Example', 'voxel-toolkit'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>profile_completion</code></td>
                            <td><?php _e('Calculate profile completion percentage based on specified fields', 'voxel-toolkit'); ?></td>
                            <td><?php _e('Comma-separated list of profile field keys', 'voxel-toolkit'); ?></td>
                            <td><code>@user().profile_completion(@user(profile.content)\,@user(profile.title))</code></td>
                            <td><em>75</em></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Order Modifiers -->
            <div class="settings-section" style="margin-top: 30px;">
                <h2><?php _e('Order Modifiers', 'voxel-toolkit'); ?></h2>
                <p class="description"><?php _e('Modifiers that can be applied to order data. Use with @order(id).modifier() syntax in order notifications and emails.', 'voxel-toolkit'); ?></p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Modifier', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Description', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Parameters', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Usage Example', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Output Example', 'voxel-toolkit'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>summary</code></td>
                            <td><?php _e('Generate email-friendly HTML table of order items with product names, quantities, addons, date ranges (for bookings), and pricing totals', 'voxel-toolkit'); ?></td>
                            <td><?php _e('None', 'voxel-toolkit'); ?></td>
                            <td><code>@order(id).summary()</code></td>
                            <td><em>HTML table showing all order items</em></td>
                        </tr>
                    </tbody>
                </table>
                <p style="margin-top: 15px;">
                    <em><?php _e('Note: The summary modifier queries order items from the database and formats them with inline styles for email compatibility. It supports both regular products and booking products with addons.', 'voxel-toolkit'); ?></em>
                </p>
            </div>

            <!-- Usage Tips -->
            <div class="settings-section" style="margin-top: 30px;">
                <h2><?php _e('Usage Tips', 'voxel-toolkit'); ?></h2>
                <div class="voxel-toolkit-info-box">
                    <h3><?php _e('Where to use dynamic tags:', 'voxel-toolkit'); ?></h3>
                    <ul style="margin-left: 20px;">
                        <li><?php _e('Elementor widgets and templates', 'voxel-toolkit'); ?></li>
                        <li><?php _e('Voxel theme templates', 'voxel-toolkit'); ?></li>
                        <li><?php _e('Post type and taxonomy templates', 'voxel-toolkit'); ?></li>
                        <li><?php _e('Custom fields and descriptions', 'voxel-toolkit'); ?></li>
                        <li><?php _e('Email templates and notifications', 'voxel-toolkit'); ?></li>
                    </ul>

                    <h3 style="margin-top: 20px;"><?php _e('Syntax examples:', 'voxel-toolkit'); ?></h3>
                    <ul style="margin-left: 20px;">
                        <li><strong><?php _e('Properties:', 'voxel-toolkit'); ?></strong> <code>@post(reading_time)</code></li>
                        <li><strong><?php _e('Methods:', 'voxel-toolkit'); ?></strong> <code>@user().profile_completion(field1,field2)</code></li>
                        <li><strong><?php _e('Combining with text:', 'voxel-toolkit'); ?></strong> <?php _e('This article takes @post(reading_time) to read', 'voxel-toolkit'); ?></li>
                        <li><strong><?php _e('In conditionals:', 'voxel-toolkit'); ?></strong> <code>@if(@post(word_count) > 1000)Long article@endif</code></li>
                    </ul>

                    <h3 style="margin-top: 20px;"><?php _e('Notes:', 'voxel-toolkit'); ?></h3>
                    <ul style="margin-left: 20px;">
                        <li><?php _e('All dynamic tags are automatically registered when the respective function/widget is enabled', 'voxel-toolkit'); ?></li>
                        <li><?php _e('Date formats follow WordPress settings (Settings > General > Date Format)', 'voxel-toolkit'); ?></li>
                        <li><?php _e('Tags return empty strings when data is unavailable', 'voxel-toolkit'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for admin notifications user search
     */
    public function ajax_admin_notifications_user_search() {
        // Verify nonce
        if (!wp_verify_nonce($_REQUEST['nonce'], "vt_admin_notifications_user_search")) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $search_filter = isset($_REQUEST['q']) ? sanitize_text_field($_REQUEST['q']) : false;

        if (empty($search_filter) || strlen($search_filter) < 3) {
            wp_send_json(array());
            return;
        }

        $args = array(
            'search'         => '*' . $search_filter . '*',
            'search_columns' => array('user_login', 'user_nicename', 'user_email'),
            'number'         => 20, // Limit results
        );

        $user_query = new WP_User_Query($args);
        $user_results = $user_query->get_results();

        $results = array();

        foreach ($user_results as $user) {
            $results[] = array(
                'id' => $user->ID,
                'text' => $user->display_name . ' (' . $user->user_email . ')',
            );
        }

        wp_send_json($results);
    }

    /**
     * Render Dynamic Tag Usage page
     */
    public function render_tag_usage_page() {
        global $wpdb;

        // Scan for dynamic tags in post meta
        $tag_usage = $this->scan_dynamic_tag_usage();

        ?>
        <div class="wrap voxel-toolkit-tag-usage-page">
            <h1><?php _e('Tag Usage', 'voxel-toolkit'); ?></h1>

            <div class="voxel-toolkit-intro">
                <p><?php _e('This page shows all dynamic tags (both Voxel native and Voxel Toolkit custom) used across your site, and where they appear.', 'voxel-toolkit'); ?></p>
                <p><strong><?php _e('Note:', 'voxel-toolkit'); ?></strong> <?php _e('Scans Elementor data in pages, posts, and templates. Detects patterns like @post(), @user(), @site(), @author(), etc.', 'voxel-toolkit'); ?></p>
            </div>

            <?php if (empty($tag_usage)): ?>
                <div class="notice notice-info">
                    <p><?php _e('No dynamic tags found in use on your site.', 'voxel-toolkit'); ?></p>
                </div>
            <?php else: ?>
                <div class="vt-search-box">
                    <input type="text" id="vt-tag-search" placeholder="<?php esc_attr_e('Search tags...', 'voxel-toolkit'); ?>" />
                    <span class="vt-search-icon dashicons dashicons-search"></span>
                </div>

                <div class="vt-tags-grid">
                    <?php foreach ($tag_usage as $tag => $data): ?>
                        <div class="vt-tag-card" data-tag="<?php echo esc_attr($tag); ?>">
                            <div class="vt-tag-header">
                                <div class="vt-tag-title">
                                    <code class="vt-tag-code"><?php echo esc_html($tag); ?></code>
                                    <button class="vt-copy-btn" data-tag="<?php echo esc_attr($tag); ?>" title="<?php esc_attr_e('Copy tag', 'voxel-toolkit'); ?>">
                                        <span class="dashicons dashicons-clipboard"></span>
                                    </button>
                                </div>
                                <span class="vt-usage-badge"><?php echo intval($data['count']); ?> <?php echo _n('location', 'locations', intval($data['count']), 'voxel-toolkit'); ?></span>
                            </div>

                            <?php if (!empty($data['locations'])): ?>
                                <div class="vt-locations">
                                    <?php
                                    $visible_count = 3;
                                    $has_more = count($data['locations']) > $visible_count;
                                    ?>

                                    <?php foreach (array_slice($data['locations'], 0, $visible_count) as $index => $location): ?>
                                        <div class="vt-location-item">
                                            <span class="vt-location-icon">
                                                <?php
                                                $icon_class = 'dashicons-admin-post';
                                                if (strpos($location['post_type'], 'elementor_library') !== false) {
                                                    $icon_class = 'dashicons-editor-code';
                                                } elseif ($location['post_type'] === 'page') {
                                                    $icon_class = 'dashicons-admin-page';
                                                }
                                                ?>
                                                <span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
                                            </span>
                                            <div class="vt-location-content">
                                                <a href="<?php echo esc_url(get_edit_post_link($location['post_id'])); ?>" target="_blank" class="vt-location-title">
                                                    <?php echo esc_html($location['post_title']); ?>
                                                </a>
                                                <div class="vt-location-meta">
                                                    <span class="vt-post-type"><?php echo esc_html($location['post_type']); ?></span>
                                                    <span class="vt-separator">•</span>
                                                    <span class="vt-post-id">ID: <?php echo intval($location['post_id']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if ($has_more): ?>
                                        <?php $hidden_count = count($data['locations']) - $visible_count; ?>
                                        <div class="vt-hidden-locations" style="display: none;">
                                            <?php foreach (array_slice($data['locations'], $visible_count) as $location): ?>
                                                <div class="vt-location-item">
                                                    <span class="vt-location-icon">
                                                        <?php
                                                        $icon_class = 'dashicons-admin-post';
                                                        if (strpos($location['post_type'], 'elementor_library') !== false) {
                                                            $icon_class = 'dashicons-editor-code';
                                                        } elseif ($location['post_type'] === 'page') {
                                                            $icon_class = 'dashicons-admin-page';
                                                        }
                                                        ?>
                                                        <span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
                                                    </span>
                                                    <div class="vt-location-content">
                                                        <a href="<?php echo esc_url(get_edit_post_link($location['post_id'])); ?>" target="_blank" class="vt-location-title">
                                                            <?php echo esc_html($location['post_title']); ?>
                                                        </a>
                                                        <div class="vt-location-meta">
                                                            <span class="vt-post-type"><?php echo esc_html($location['post_type']); ?></span>
                                                            <span class="vt-separator">•</span>
                                                            <span class="vt-post-id">ID: <?php echo intval($location['post_id']); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button class="vt-show-more-btn">
                                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                                            <?php printf(esc_html__('Show %d more', 'voxel-toolkit'), $hidden_count); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="vt-no-results" style="display: none;">
                    <div class="notice notice-warning">
                        <p><?php _e('No tags found matching your search.', 'voxel-toolkit'); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .voxel-toolkit-tag-usage-page {
                max-width: 1400px;
            }

            .vt-search-box {
                position: relative;
                margin: 20px 0;
            }

            .vt-search-box input {
                width: 100%;
                max-width: 500px;
                padding: 12px 45px 12px 20px;
                font-size: 15px;
                border: 2px solid #e1e5e9;
                border-radius: 8px;
                transition: all 0.3s;
            }

            .vt-search-box input:focus {
                border-color: #1e3a5f;
                outline: none;
                box-shadow: 0 0 0 3px rgba(30, 58, 95, 0.1);
            }

            .vt-search-icon {
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
                color: #999;
                pointer-events: none;
            }

            .vt-tags-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }

            .vt-tag-card {
                background: #fff;
                border: 1px solid #e1e5e9;
                border-radius: 12px;
                padding: 30px;
                position: relative;
                overflow: hidden;
            }

            .vt-tag-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: #1e3a5f;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .vt-tag-card:hover::before {
                opacity: 1;
            }

            .vt-tag-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 15px;
                gap: 10px;
            }

            .vt-tag-title {
                display: flex;
                align-items: center;
                gap: 8px;
                flex: 1;
                min-width: 0;
            }

            .vt-tag-code {
                background: #f8f9fa;
                padding: 8px 12px;
                border-radius: 6px;
                font-family: 'Courier New', monospace;
                font-size: 13px;
                color: #1e3a5f;
                border: 1px solid #e9ecef;
                word-break: break-all;
                flex: 1;
            }

            .vt-copy-btn {
                background: #f0f0f1;
                border: none;
                padding: 6px;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .vt-copy-btn:hover {
                background: #1e3a5f;
                color: white;
            }

            .vt-copy-btn .dashicons {
                font-size: 18px;
                width: 18px;
                height: 18px;
            }

            .vt-usage-badge {
                background: #1e3a5f;
                color: white;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
                flex-shrink: 0;
            }

            .vt-locations {
                border-top: 1px solid #e1e5e9;
                padding-top: 15px;
            }

            .vt-location-item {
                display: flex;
                gap: 12px;
                padding: 10px;
                border-radius: 6px;
                transition: background 0.2s;
                margin-bottom: 8px;
            }

            .vt-location-item:hover {
                background: #f8f9fa;
            }

            .vt-location-icon {
                flex-shrink: 0;
                color: #1e3a5f;
            }

            .vt-location-icon .dashicons {
                font-size: 20px;
                width: 20px;
                height: 20px;
            }

            .vt-location-content {
                flex: 1;
                min-width: 0;
            }

            .vt-location-title {
                font-weight: 500;
                color: #1e3a5f;
                text-decoration: none;
                display: block;
                margin-bottom: 4px;
            }

            .vt-location-title:hover {
                color: #0f1f3a;
                text-decoration: underline;
            }

            .vt-location-meta {
                font-size: 12px;
                color: #666;
            }

            .vt-post-type {
                text-transform: capitalize;
            }

            .vt-separator {
                margin: 0 6px;
            }

            .vt-show-more-btn {
                width: 100%;
                background: #f8f9fa;
                border: 1px solid #e1e5e9;
                padding: 10px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 13px;
                font-weight: 500;
                color: #1e3a5f;
                transition: all 0.2s;
                margin-top: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
            }

            .vt-show-more-btn:hover {
                background: #1e3a5f;
                color: white;
                border-color: #1e3a5f;
            }

            .vt-show-more-btn .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
                transition: transform 0.3s;
            }

            .vt-show-more-btn.expanded .dashicons {
                transform: rotate(180deg);
            }

            #vt-no-results {
                margin-top: 20px;
            }

            @media (max-width: 768px) {
                .vt-tags-grid {
                    grid-template-columns: 1fr;
                }

                .vt-tag-header {
                    flex-direction: column;
                    align-items: flex-start;
                }
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Search functionality
            $('#vt-tag-search').on('keyup', function() {
                var searchTerm = $(this).val().toLowerCase();
                var visibleCards = 0;

                $('.vt-tag-card').each(function() {
                    var tagText = $(this).data('tag').toLowerCase();
                    if (tagText.indexOf(searchTerm) > -1) {
                        $(this).show();
                        visibleCards++;
                    } else {
                        $(this).hide();
                    }
                });

                if (visibleCards === 0) {
                    $('#vt-no-results').show();
                    $('.vt-tags-grid').hide();
                } else {
                    $('#vt-no-results').hide();
                    $('.vt-tags-grid').show();
                }
            });

            // Show more/less functionality
            $('.vt-show-more-btn').on('click', function() {
                var $btn = $(this);
                var $hiddenLocations = $btn.siblings('.vt-hidden-locations');
                var isExpanded = $btn.hasClass('expanded');

                if (isExpanded) {
                    $hiddenLocations.slideUp(300);
                    $btn.removeClass('expanded');
                    var count = $hiddenLocations.find('.vt-location-item').length;
                    $btn.html('<span class="dashicons dashicons-arrow-down-alt2"></span> <?php echo esc_js(__('Show', 'voxel-toolkit')); ?> ' + count + ' <?php echo esc_js(__('more', 'voxel-toolkit')); ?>');
                } else {
                    $hiddenLocations.slideDown(300);
                    $btn.addClass('expanded');
                    $btn.html('<span class="dashicons dashicons-arrow-up-alt2"></span> <?php echo esc_js(__('Show less', 'voxel-toolkit')); ?>');
                }
            });

            // Copy to clipboard functionality
            $('.vt-copy-btn').on('click', function() {
                var tag = $(this).data('tag');
                var $btn = $(this);

                // Create temporary input
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(tag).select();
                document.execCommand('copy');
                $temp.remove();

                // Visual feedback
                var originalHTML = $btn.html();
                $btn.html('<span class="dashicons dashicons-yes"></span>');
                $btn.css('background', '#46b450');
                $btn.css('color', 'white');

                setTimeout(function() {
                    $btn.html(originalHTML);
                    $btn.css('background', '');
                    $btn.css('color', '');
                }, 1000);
            });
        });
        </script>
        <?php
    }

    /**
     * Scan for dynamic tag usage across the site
     */
    private function scan_dynamic_tag_usage() {
        global $wpdb;

        $tag_usage = array();
        $batch_size = 50;
        $offset = 0;

        // Process in batches to avoid memory exhaustion
        do {
            // Get post IDs with Elementor data in batches
            $post_ids = $wpdb->get_col($wpdb->prepare("
                SELECT DISTINCT post_id
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_elementor_data'
                LIMIT %d OFFSET %d
            ", $batch_size, $offset));

            if (empty($post_ids)) {
                break;
            }

            foreach ($post_ids as $post_id) {
                // Get post details first
                $post = get_post($post_id);
                if (!$post) {
                    continue;
                }

                // Get Elementor data for this specific post
                $elementor_data = get_post_meta($post_id, '_elementor_data', true);
                if (empty($elementor_data)) {
                    continue;
                }

                // Find all dynamic tags using regex
                // Pattern matches: @post(...), @user(...), @site(...), @author(...), etc.
                preg_match_all('/@(post|user|site|author|current_user)\([^)]*\)(?:\.[a-zA-Z_]+\([^)]*\))*/', $elementor_data, $matches);

                if (!empty($matches[0])) {
                    foreach ($matches[0] as $tag) {
                        if (!isset($tag_usage[$tag])) {
                            $tag_usage[$tag] = array(
                                'count' => 0,
                                'locations' => array()
                            );
                        }

                        // Check if this post is already in locations for this tag
                        $post_exists = false;
                        foreach ($tag_usage[$tag]['locations'] as $location) {
                            if ($location['post_id'] === $post_id) {
                                $post_exists = true;
                                break;
                            }
                        }

                        if (!$post_exists) {
                            $tag_usage[$tag]['count']++;
                            $tag_usage[$tag]['locations'][] = array(
                                'post_id' => $post_id,
                                'post_title' => $post->post_title,
                                'post_type' => $post->post_type
                            );
                        }
                    }
                }

                // Free memory
                unset($elementor_data);
            }

            $offset += $batch_size;

            // Free memory between batches
            unset($post_ids);

        } while (true);

        // Sort by usage count (descending)
        uasort($tag_usage, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        return $tag_usage;
    }

    /**
     * Render Site Options page
     */
    public function render_site_options_page() {
        // Get the options page instance
        $functions = Voxel_Toolkit_Functions::instance()->get_active_functions();

        if (isset($functions['options_page']) && $functions['options_page'] instanceof Voxel_Toolkit_Options_Page) {
            $functions['options_page']->render_options_page();
        } else {
            ?>
            <div class="wrap">
                <h1><?php _e('Site Options', 'voxel-toolkit'); ?></h1>
                <div class="notice notice-error">
                    <p><?php _e('Options Page function is not properly initialized.', 'voxel-toolkit'); ?></p>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render Configure Fields page
     */
    public function render_configure_fields_page() {
        $settings = Voxel_Toolkit_Settings::instance();
        $config = $settings->get_function_settings('options_page');
        $fields = isset($config['fields']) ? $config['fields'] : array();
        $field_types = array(
            'text' => 'Text',
            'textarea' => 'Textarea',
            'number' => 'Number',
            'url' => 'URL',
            'image' => 'Image',
        );
        ?>
        <div class="wrap voxel-toolkit-configure-fields-page">
            <h1><?php _e('Site Options - Configure Fields', 'voxel-toolkit'); ?></h1>

            <div class="voxel-toolkit-intro">
                <p><?php _e('Configure custom fields that will be available site-wide via dynamic tags. Perfect for contact info, social links, and other global settings.', 'voxel-toolkit'); ?></p>
            </div>

            <?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Fields saved successfully!', 'voxel-toolkit'); ?></p>
                </div>
            <?php endif; ?>

            <style>
                .voxel-toolkit-configure-fields-page .voxel-toolkit-intro {
                    margin-bottom: 20px;
                }
                .vt-add-field-card {
                    background: #fff;
                    border: 1px solid #e1e5e9;
                    border-radius: 12px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
                    padding: 30px;
                    margin-bottom: 20px;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }
                .vt-add-field-card h2 {
                    margin-top: 0;
                    font-size: 18px;
                    margin-bottom: 15px;
                    color: #1d2327;
                }
                .vt-form-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 15px;
                    margin-bottom: 15px;
                }
                .vt-form-row label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 5px;
                    font-size: 13px;
                }
                .vt-form-row input[type="text"],
                .vt-form-row select {
                    width: 100%;
                }
                .vt-form-row .description {
                    font-size: 12px;
                    color: #646970;
                    margin-top: 3px;
                }
                .vt-fields-container {
                    background: #fff;
                    border: 1px solid #e1e5e9;
                    border-radius: 12px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
                    margin-top: 20px;
                    overflow: hidden;
                }
                .vt-field-row {
                    padding: 25px 30px;
                    border-bottom: 1px solid #e1e5e9;
                    transition: background-color 0.2s ease;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    cursor: move;
                }
                .vt-field-row:last-child {
                    border-bottom: none;
                }
                .vt-field-row:hover {
                    background-color: #f8f9fa;
                }
                .vt-field-row.ui-sortable-helper {
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    background: #fff;
                }
                .vt-field-row.ui-sortable-placeholder {
                    background: #f0f6ff;
                    border: 2px dashed #1e3a5f;
                    visibility: visible !important;
                }
                .vt-drag-handle {
                    margin-right: 15px;
                    color: #a7aaad;
                    cursor: grab;
                    display: flex;
                    align-items: center;
                }
                .vt-drag-handle:active {
                    cursor: grabbing;
                }
                .vt-drag-handle .dashicons {
                    font-size: 20px;
                    width: 20px;
                    height: 20px;
                }
                .vt-field-info {
                    flex: 1;
                }
                .vt-field-header {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin-bottom: 8px;
                }
                .vt-field-title {
                    font-family: monospace;
                    font-weight: 600;
                    color: #1d2327;
                    font-size: 14px;
                    margin: 0;
                }
                .vt-field-type {
                    display: inline-block;
                    background: #e8f4f8;
                    color: #0c5d8c;
                    padding: 4px 12px;
                    border-radius: 12px;
                    font-size: 11px;
                    text-transform: uppercase;
                    font-weight: 600;
                    letter-spacing: 0.5px;
                }
                .vt-field-label {
                    color: #646970;
                    font-size: 13px;
                    margin-bottom: 6px;
                }
                .vt-field-tag {
                    font-size: 12px;
                    color: #646970;
                }
                .vt-field-tag code {
                    background: #f8f9fa;
                    padding: 3px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    border: 1px solid #e1e5e9;
                    font-family: monospace;
                    color: #1e3a5f;
                }
                .vt-field-actions {
                    margin-left: 20px;
                    display: flex;
                    gap: 8px;
                }
                .vt-field-actions .edit-field {
                    background: #fff;
                    border: 1px solid #e1e5e9;
                    color: #1e3a5f;
                    padding: 6px 16px;
                    border-radius: 6px;
                    font-size: 13px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                .vt-field-actions .edit-field:hover {
                    background: #1e3a5f;
                    color: #fff;
                    border-color: #1e3a5f;
                    transform: translateY(-1px);
                    box-shadow: 0 2px 4px rgba(30, 58, 95, 0.2);
                }
                .vt-field-actions .delete-field {
                    background: #fff;
                    border: 1px solid #e1e5e9;
                    color: #d63638;
                    padding: 6px 16px;
                    border-radius: 6px;
                    font-size: 13px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                .vt-field-actions .delete-field:hover {
                    background: #d63638;
                    color: #fff;
                    border-color: #d63638;
                    transform: translateY(-1px);
                    box-shadow: 0 2px 4px rgba(214, 54, 56, 0.2);
                }
            </style>

            <form method="post">
                <?php wp_nonce_field('voxel_toolkit_configure_fields', 'voxel_toolkit_fields_nonce'); ?>

                <!-- Add New Field Card -->
                <div class="vt-add-field-card">
                    <h2><?php _e('Add New Field', 'voxel-toolkit'); ?></h2>

                    <div class="vt-form-grid">
                        <div class="vt-form-row">
                            <label for="field_name"><?php _e('Field Name', 'voxel-toolkit'); ?></label>
                            <input type="text" id="field_name" name="field_name" class="regular-text" placeholder="e.g., company_phone">
                            <p class="description"><?php _e('Lowercase, numbers, underscores only', 'voxel-toolkit'); ?></p>
                        </div>

                        <div class="vt-form-row">
                            <label for="field_label"><?php _e('Label', 'voxel-toolkit'); ?></label>
                            <input type="text" id="field_label" name="field_label" class="regular-text" placeholder="e.g., Company Phone">
                            <p class="description"><?php _e('Human-readable label', 'voxel-toolkit'); ?></p>
                        </div>

                        <div class="vt-form-row">
                            <label for="field_type"><?php _e('Type', 'voxel-toolkit'); ?></label>
                            <select id="field_type" name="field_type" class="regular-text">
                                <?php foreach ($field_types as $type => $label): ?>
                                    <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="vt-form-row">
                            <label for="field_default"><?php _e('Default Value', 'voxel-toolkit'); ?> <span style="font-weight:normal;color:#646970;">(<?php _e('optional', 'voxel-toolkit'); ?>)</span></label>
                            <input type="text" id="field_default" name="field_default" class="regular-text" placeholder="">
                        </div>
                    </div>

                    <input type="hidden" name="is_update" id="is_update" value="">

                    <button type="submit" name="voxel_toolkit_save_field" class="button button-primary">
                        <?php _e('Save Field', 'voxel-toolkit'); ?>
                    </button>
                </div>

                <!-- Existing Fields -->
                <h2 style="margin-top: 30px;">
                    <?php _e('Configured Fields', 'voxel-toolkit'); ?>
                </h2>

                <?php if (!empty($fields)): ?>
                    <div class="vt-fields-container" id="sortable-fields">
                        <?php foreach ($fields as $field_name => $field_config): ?>
                            <div class="vt-field-row" data-field-name="<?php echo esc_attr($field_name); ?>">
                                <div class="vt-drag-handle">
                                    <span class="dashicons dashicons-move"></span>
                                </div>
                                <div class="vt-field-info">
                                    <div class="vt-field-header">
                                        <h3 class="vt-field-title"><?php echo esc_html($field_name); ?></h3>
                                        <span class="vt-field-type"><?php echo esc_html($field_types[$field_config['type']]); ?></span>
                                    </div>
                                    <div class="vt-field-label"><?php echo esc_html($field_config['label']); ?></div>
                                    <div class="vt-field-tag">
                                        <code>@site(options.<?php echo esc_html($field_name); ?>)</code>
                                    </div>
                                </div>
                                <div class="vt-field-actions">
                                    <button type="button" class="button button-small edit-field" data-field="<?php echo esc_attr($field_name); ?>" data-label="<?php echo esc_attr($field_config['label']); ?>" data-type="<?php echo esc_attr($field_config['type']); ?>" data-default="<?php echo esc_attr($field_config['default']); ?>">
                                        <?php _e('Edit', 'voxel-toolkit'); ?>
                                    </button>
                                    <button type="button" class="button button-small delete-field" data-field="<?php echo esc_attr($field_name); ?>">
                                        <?php _e('Delete', 'voxel-toolkit'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="field_order" id="field_order" value="">
                <?php else: ?>
                    <div class="vt-add-field-card">
                        <p style="text-align: center; color: #646970; margin: 20px 0;">
                            <?php _e('No fields configured yet. Add your first field above!', 'voxel-toolkit'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </form>

            <script>
            jQuery(document).ready(function($) {
                // Initialize sortable for field reordering
                $('#sortable-fields').sortable({
                    handle: '.vt-drag-handle',
                    placeholder: 'ui-sortable-placeholder',
                    helper: function(e, ui) {
                        ui.children().each(function() {
                            $(this).width($(this).width());
                        });
                        return ui;
                    },
                    update: function(event, ui) {
                        // Get the new order
                        var order = [];
                        $('#sortable-fields .vt-field-row').each(function() {
                            order.push($(this).data('field-name'));
                        });

                        // Save order via AJAX
                        $.post(ajaxurl, {
                            action: 'voxel_toolkit_reorder_fields',
                            nonce: '<?php echo wp_create_nonce('voxel_toolkit_reorder_fields'); ?>',
                            order: order
                        }, function(response) {
                            if (response.success) {
                                // Show brief success indicator
                                var $notice = $('<div class="notice notice-success" style="position: fixed; top: 32px; right: 20px; z-index: 9999; padding: 10px 15px;"><p>Field order saved!</p></div>');
                                $('body').append($notice);
                                setTimeout(function() {
                                    $notice.fadeOut(function() { $(this).remove(); });
                                }, 2000);
                            }
                        });
                    }
                });

                // Auto-format field name
                $('#field_name').on('input', function() {
                    var value = $(this).val();
                    var formatted = value.toLowerCase().replace(/[\s-]+/g, '_').replace(/[^a-z0-9_]/g, '');
                    $(this).val(formatted);
                });

                // Edit existing field
                $(document).on('click', '.edit-field', function(e) {
                    e.preventDefault();
                    var $button = $(this);
                    var fieldName = $button.data('field');
                    var fieldLabel = $button.data('label');
                    var fieldType = $button.data('type');
                    var fieldDefault = $button.data('default');

                    // Populate the form with existing values
                    $('#field_name').val(fieldName).prop('readonly', true);
                    $('#field_label').val(fieldLabel);
                    $('#field_type').val(fieldType);
                    $('#field_default').val(fieldDefault);
                    $('#is_update').val('1');

                    // Scroll to the add field card
                    $('html, body').animate({
                        scrollTop: $('.vt-add-field-card').offset().top - 50
                    }, 500);

                    // Update card title
                    $('.vt-add-field-card h2').text('<?php _e('Update Field', 'voxel-toolkit'); ?>');

                    // Show cancel button if it doesn't exist
                    if ($('#cancel-edit').length === 0) {
                        $('button[name="voxel_toolkit_save_field"]').after('<button type="button" id="cancel-edit" class="button" style="margin-left: 10px;">Cancel</button>');
                    }
                });

                // Cancel edit mode
                $(document).on('click', '#cancel-edit', function() {
                    $('#field_name').val('').prop('readonly', false);
                    $('#field_label').val('');
                    $('#field_type').val('text');
                    $('#field_default').val('');
                    $('#is_update').val('');
                    $('.vt-add-field-card h2').text('<?php _e('Add New Field', 'voxel-toolkit'); ?>');
                    $(this).remove();
                });

                // Delete existing field
                $(document).on('click', '.delete-field', function(e) {
                    e.preventDefault();
                    var field = $(this).data('field');

                    if (confirm('Delete this field? This will remove all data and reload the page.')) {
                        var $form = $('<form>', {
                            method: 'POST',
                            action: ''
                        });

                        $form.append($('<input>', {
                            type: 'hidden',
                            name: 'voxel_toolkit_fields_nonce',
                            value: '<?php echo wp_create_nonce('voxel_toolkit_configure_fields'); ?>'
                        }));

                        $form.append($('<input>', {
                            type: 'hidden',
                            name: 'delete_fields',
                            value: field
                        }));

                        $form.append($('<input>', {
                            type: 'hidden',
                            name: 'voxel_toolkit_save_fields',
                            value: '1'
                        }));

                        $('body').append($form);
                        $form.submit();
                    }
                });

                // Form submit validation
                $('form').on('submit', function(e) {
                    var name = $('#field_name').val().trim();
                    var label = $('#field_label').val().trim();

                    if (!name) {
                        alert('Please enter a field name');
                        e.preventDefault();
                        return false;
                    }

                    // Auto-generate label if empty
                    if (!label) {
                        label = name.split('_').map(function(word) {
                            return word.charAt(0).toUpperCase() + word.slice(1);
                        }).join(' ');
                        $('#field_label').val(label);
                    }
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Save configure fields
     */
    private function save_configure_fields() {
        $current_options = get_option('voxel_toolkit_options', array());
        $config = isset($current_options['options_page']) ? $current_options['options_page'] : array();
        $fields = isset($config['fields']) ? $config['fields'] : array();

        $is_delete = false;

        // Handle deletes
        if (!empty($_POST['delete_fields'])) {
            $is_delete = true;
            $to_delete = explode(',', $_POST['delete_fields']);
            foreach ($to_delete as $field_name) {
                $field_name = sanitize_key(trim($field_name));
                if (isset($fields[$field_name])) {
                    unset($fields[$field_name]);
                    delete_option('voxel_options_' . $field_name);
                }
            }
        }

        // Handle single field add/update
        if (isset($_POST['voxel_toolkit_save_field'])) {
            $name = !empty($_POST['field_name']) ? Voxel_Toolkit_Options_Page::sanitize_field_name($_POST['field_name']) : '';
            $label = !empty($_POST['field_label']) ? sanitize_text_field($_POST['field_label']) : '';
            $type = !empty($_POST['field_type']) ? Voxel_Toolkit_Options_Page::validate_field_type($_POST['field_type']) : 'text';
            $default = !empty($_POST['field_default']) ? sanitize_text_field($_POST['field_default']) : '';
            $is_update = !empty($_POST['is_update']);

            if (!empty($name)) {
                // Auto-generate label if empty
                if (empty($label)) {
                    $label = ucwords(str_replace('_', ' ', $name));
                }

                // Allow updates to existing fields or new fields
                if ($is_update || !isset($fields[$name])) {
                    $fields[$name] = array(
                        'label' => $label,
                        'type' => $type,
                        'default' => $default,
                    );
                }
            }
        }

        // Save
        $current_options['options_page']['fields'] = $fields;
        update_option('voxel_toolkit_options', $current_options);

        // Refresh settings cache
        Voxel_Toolkit_Settings::instance()->refresh_options();

        // Redirect back to configure fields page
        wp_safe_redirect(admin_url('admin.php?page=voxel-toolkit-configure-fields&saved=1'));
        exit;
    }

    /**
     * AJAX handler for reordering fields
     */
    public function ajax_reorder_fields() {
        check_ajax_referer('voxel_toolkit_reorder_fields', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $order = isset($_POST['order']) ? $_POST['order'] : array();

        if (empty($order)) {
            wp_send_json_error('No order provided');
        }

        // Get current options
        $current_options = get_option('voxel_toolkit_options', array());
        $config = isset($current_options['options_page']) ? $current_options['options_page'] : array();
        $fields = isset($config['fields']) ? $config['fields'] : array();

        // Reorder fields based on the new order
        $reordered_fields = array();
        foreach ($order as $field_name) {
            $field_name = sanitize_key($field_name);
            if (isset($fields[$field_name])) {
                $reordered_fields[$field_name] = $fields[$field_name];
            }
        }

        // Add any fields that weren't in the order (shouldn't happen, but just in case)
        foreach ($fields as $field_name => $field_config) {
            if (!isset($reordered_fields[$field_name])) {
                $reordered_fields[$field_name] = $field_config;
            }
        }

        // Save the reordered fields
        $current_options['options_page']['fields'] = $reordered_fields;
        update_option('voxel_toolkit_options', $current_options);

        // Refresh settings cache
        Voxel_Toolkit_Settings::instance()->refresh_options();

        wp_send_json_success();
    }

    /**
     * Enqueue SMS notifications script for Voxel app-events page
     */
    private function enqueue_sms_notifications_script() {
        // Check if SMS notifications function is enabled
        $settings = Voxel_Toolkit_Settings::instance();

        // Don't load the script at all if SMS notifications is disabled
        if (!$settings->is_function_enabled('sms_notifications')) {
            return;
        }

        $sms_settings = $settings->get_function_settings('sms_notifications', array());

        wp_enqueue_script(
            'voxel-toolkit-sms-notifications',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/sms-notifications.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Localize script with SMS settings
        wp_localize_script('voxel-toolkit-sms-notifications', 'vt_sms_config', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_sms_nonce'),
            'enabled' => true,
            'phone_configured' => !empty($sms_settings['phone_field']),
            'provider' => isset($sms_settings['provider']) ? $sms_settings['provider'] : 'twilio',
            'events' => isset($sms_settings['events']) ? $sms_settings['events'] : array(),
            'settings_url' => admin_url('admin.php?page=voxel-toolkit'),
        ));
    }

}