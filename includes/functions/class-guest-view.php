<?php
/**
 * Guest View Function - Version 2
 * 
 * Uses proper WordPress user switching based on User Switching plugin approach
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Guest_View {
    
    private $cookie_name = 'voxel_toolkit_olduser';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->init_elementor_widget();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add AJAX handlers
        add_action('wp_ajax_voxel_toolkit_toggle_guest_view', array($this, 'ajax_toggle_guest_view'));
        add_action('wp_ajax_nopriv_voxel_toolkit_switch_back', array($this, 'ajax_switch_back')); // nopriv because user appears logged out
        
        // Handle the actual switching logic
        add_action('init', array($this, 'handle_guest_view_actions'));
        
        // Add floating switch back button
        add_action('wp_footer', array($this, 'render_switch_back_button'));
        
        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Add body class when in guest view
        add_filter('body_class', array($this, 'add_body_class'));
        
        // Add CSS to hide elements when in guest view
        add_action('wp_head', array($this, 'add_guest_view_styles'), 999);
        
        // Add admin bar button
        add_action('admin_bar_menu', array($this, 'add_admin_bar_button'), 999);
    }
    
    /**
     * Initialize Elementor widget
     */
    private function init_elementor_widget() {
        // Register Elementor widget
        add_action('elementor/widgets/widgets_registered', array($this, 'register_elementor_widget'));
        
        // Register widget category
        add_action('elementor/elements/categories_registered', array($this, 'add_elementor_widget_category'));
    }
    
    /**
     * Handle guest view actions (switch to guest, switch back)
     */
    public function handle_guest_view_actions() {
        if (!isset($_REQUEST['action'])) {
            return;
        }
        
        // Debug logging
        if (isset($_GET['debug'])) {
            error_log('Guest View Debug - Action detected: ' . $_REQUEST['action']);
            error_log('Guest View Debug - Request data: ' . print_r($_REQUEST, true));
            error_log('Guest View Debug - User logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
            error_log('Guest View Debug - Current user ID: ' . get_current_user_id());
        }
        
        switch ($_REQUEST['action']) {
            case 'voxel_switch_to_guest':
                $this->handle_switch_to_guest();
                break;
                
            case 'voxel_switch_back_from_guest':
                $this->handle_switch_back_from_guest();
                break;
        }
    }
    
    /**
     * Handle switching to guest view
     */
    private function handle_switch_to_guest() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_die(__('You must be logged in to use guest view.', 'voxel-toolkit'), 403);
        }
        
        // Check nonce
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'voxel_switch_to_guest')) {
            wp_die(__('Security check failed.', 'voxel-toolkit'), 403);
        }
        
        $current_user_id = get_current_user_id();
        
        // Set cookie to remember the original user
        $this->set_olduser_cookie($current_user_id);
        
        // Actually log out the user (like User Switching does)
        wp_clear_auth_cookie();
        wp_set_current_user(0);
        
        // Redirect back to the page
        $redirect_to = home_url();
        if (!empty($_REQUEST['redirect_to'])) {
            // Properly decode the redirect URL
            $redirect_to = rawurldecode($_REQUEST['redirect_to']);
            $redirect_to = esc_url_raw($redirect_to);
        }
        
        // Clean the redirect URL of any problematic query args
        $redirect_to = remove_query_arg(array('action', '_wpnonce', 'switched_off'), $redirect_to);
        
        $redirect_to = add_query_arg(array(
            'guest_view_active' => 'true'
        ), $redirect_to);
        
        wp_safe_redirect($redirect_to, 302);
        exit;
    }
    
    /**
     * Handle switching back from guest view
     */
    private function handle_switch_back_from_guest() {
        $old_user = $this->get_old_user();
        if (!$old_user) {
            wp_die(__('Could not switch back to user.', 'voxel-toolkit'), 400);
        }
        
        // Check nonce
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'voxel_switch_back_from_guest_' . $old_user->ID)) {
            wp_die(__('Security check failed.', 'voxel-toolkit'), 403);
        }
        
        // Clear the old user cookie
        $this->clear_olduser_cookie();
        
        // Log the user back in (like User Switching does)
        wp_set_auth_cookie($old_user->ID, false);
        wp_set_current_user($old_user->ID);
        
        // Redirect back to the page
        $redirect_to = home_url();
        if (!empty($_REQUEST['redirect_to'])) {
            // Properly decode the redirect URL
            $redirect_to = rawurldecode($_REQUEST['redirect_to']);
            $redirect_to = esc_url_raw($redirect_to);
        }
        
        // Clean the redirect URL of any problematic query args
        $redirect_to = remove_query_arg(array('action', '_wpnonce', 'guest_view_active'), $redirect_to);
        
        $redirect_to = add_query_arg(array(
            'switched_back' => 'true'
        ), $redirect_to);
        
        wp_safe_redirect($redirect_to, 302);
        exit;
    }
    
    /**
     * Set the old user cookie
     */
    private function set_olduser_cookie($user_id) {
        $expiration = time() + 3600; // 1 hour
        $cookie_value = wp_generate_auth_cookie($user_id, $expiration, 'logged_in');
        
        setcookie($this->cookie_name, $cookie_value, $expiration, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    }
    
    /**
     * Clear the old user cookie
     */
    private function clear_olduser_cookie() {
        $expire = time() - 3600;
        setcookie($this->cookie_name, '', $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    }
    
    /**
     * Get the old user from cookie
     */
    private function get_old_user() {
        if (!isset($_COOKIE[$this->cookie_name])) {
            return false;
        }
        
        $old_user_id = wp_validate_auth_cookie($_COOKIE[$this->cookie_name], 'logged_in');
        if ($old_user_id) {
            return get_userdata($old_user_id);
        }
        
        return false;
    }
    
    /**
     * Check if currently in guest view
     */
    public function is_guest_view_active() {
        return !is_user_logged_in() && $this->get_old_user();
    }
    
    /**
     * Add body class when in guest view
     */
    public function add_body_class($classes) {
        if ($this->is_guest_view_active()) {
            $classes[] = 'voxel-toolkit-guest-view-active';
            $classes[] = 'guest-view-mode';
        }
        return $classes;
    }
    
    /**
     * AJAX handler to toggle guest view
     */
    public function ajax_toggle_guest_view() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'voxel_toolkit_guest_view')) {
            wp_send_json_error(__('Security check failed.', 'voxel-toolkit'));
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to use guest view.', 'voxel-toolkit'));
        }
        
        $enable = isset($_POST['enable']) && $_POST['enable'] === 'true';
        
        if ($enable) {
            // Do the switching directly in the AJAX handler - no redirect needed
            $current_user_id = get_current_user_id();
            
            // Set cookie to remember the original user
            $this->set_olduser_cookie($current_user_id);
            
            // Actually log out the user (like User Switching does)
            wp_clear_auth_cookie();
            wp_set_current_user(0);
            
            wp_send_json_success(array(
                'message' => __('Guest view enabled. The page will reload.', 'voxel-toolkit'),
                'reload' => true
            ));
        } else {
            wp_send_json_error(__('Invalid action.', 'voxel-toolkit'));
        }
    }
    
    /**
     * AJAX handler to switch back from guest view
     */
    public function ajax_switch_back() {
        // Get the old user from cookie
        $old_user = $this->get_old_user();
        if (!$old_user) {
            wp_send_json_error(__('Could not switch back to user.', 'voxel-toolkit'));
        }
        
        // Clear the old user cookie
        $this->clear_olduser_cookie();
        
        // Log the user back in
        wp_set_auth_cookie($old_user->ID, false);
        wp_set_current_user($old_user->ID);
        
        wp_send_json_success(array(
            'message' => __('Switched back successfully. The page will reload.', 'voxel-toolkit'),
            'reload' => true
        ));
    }
    
    /**
     * Render floating switch back button
     */
    public function render_switch_back_button() {
        if (!$this->is_guest_view_active()) {
            return;
        }
        
        $old_user = $this->get_old_user();
        if (!$old_user) {
            return;
        }
        
        ?>
        <div id="voxel-toolkit-guest-view-switcher" class="voxel-guest-view-switcher">
            <button id="voxel-toolkit-switch-back-btn" class="switcher-button">
                <?php _e('Exit Guest View', 'voxel-toolkit'); ?>
            </button>
        </div>
        
        <!-- Debug Info -->
        <?php if (isset($_GET['debug'])): ?>
        <div style="position: fixed; top: 10px; left: 10px; background: yellow; padding: 10px; z-index: 999999; border: 2px solid red;">
            <strong>Guest View Debug V2:</strong><br>
            Is Logged In: <?php echo is_user_logged_in() ? 'YES' : 'NO'; ?><br>
            Current User ID: <?php echo get_current_user_id(); ?><br>
            Old User: <?php echo $old_user ? $old_user->display_name : 'NONE'; ?><br>
            Is Guest View: <?php echo $this->is_guest_view_active() ? 'YES' : 'NO'; ?><br>
            Cookie Exists: <?php echo isset($_COOKIE[$this->cookie_name]) ? 'YES' : 'NO'; ?>
        </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Add guest view styles to hide logged-in elements
     */
    public function add_guest_view_styles() {
        if (!$this->is_guest_view_active()) {
            return;
        }
        
        // Get settings for dynamic styling
        $settings = get_option('voxel_toolkit_options', array());
        $guest_view_settings = isset($settings['guest_view']) ? $settings['guest_view'] : array();
        
        // Position settings
        $position = isset($guest_view_settings['button_position']) ? $guest_view_settings['button_position'] : 'bottom-right';
        
        // Color settings
        $bg_color = isset($guest_view_settings['bg_color']) ? $guest_view_settings['bg_color'] : '#667eea';
        $text_color = isset($guest_view_settings['text_color']) ? $guest_view_settings['text_color'] : '#ffffff';
        
        ?>
        <style id="voxel-toolkit-guest-view-styles">
            /* Hide WordPress admin bar */
            body.voxel-toolkit-guest-view-active #wpadminbar {
                display: none !important;
            }
            
            body.voxel-toolkit-guest-view-active {
                margin-top: 0 !important;
                border: 3px solid #667eea !important;
                box-sizing: border-box;
            }
            
            /* Hide Voxel user menu and account elements */
            body.voxel-toolkit-guest-view-active .vx-user-menu,
            body.voxel-toolkit-guest-view-active .user-menu,
            body.voxel-toolkit-guest-view-active .ts-user-menu,
            body.voxel-toolkit-guest-view-active .ts-user-area,
            body.voxel-toolkit-guest-view-active .user-area,
            body.voxel-toolkit-guest-view-active .account-menu,
            body.voxel-toolkit-guest-view-active .ts-navbar .ts-user-menu,
            body.voxel-toolkit-guest-view-active [class*="user-logged-in"],
            body.voxel-toolkit-guest-view-active .logged-in-only,
            body.voxel-toolkit-guest-view-active .voxel-logged-in,
            body.voxel-toolkit-guest-view-active .ts-status-user {
                display: none !important;
            }
            
            /* Show guest/login elements */
            body.voxel-toolkit-guest-view-active .ts-login-menu,
            body.voxel-toolkit-guest-view-active .login-menu,
            body.voxel-toolkit-guest-view-active .guest-only,
            body.voxel-toolkit-guest-view-active .logged-out-only,
            body.voxel-toolkit-guest-view-active .voxel-logged-out {
                display: flex !important;
            }
            
<?php
            // Position classes
            $position_classes = array(
                'top-left' => 'top: 30px; left: 30px;',
                'top-right' => 'top: 30px; right: 30px;',
                'middle-left' => 'top: 50%; left: 30px; transform: translateY(-50%);',
                'middle-right' => 'top: 50%; right: 30px; transform: translateY(-50%);',
                'bottom-left' => 'bottom: 30px; left: 30px;',
                'bottom-right' => 'bottom: 30px; right: 30px;'
            );
            
            $position_style = isset($position_classes[$position]) ? $position_classes[$position] : $position_classes['bottom-right'];
            ?>
            
            /* Guest View Switcher Styles */
            .voxel-guest-view-switcher {
                position: fixed;
                <?php echo $position_style; ?>
                z-index: 999999;
                animation: slideIn 0.3s ease-out;
            }
            
            .voxel-guest-view-switcher .switcher-button {
                background: <?php echo esc_attr($bg_color); ?>;
                color: <?php echo esc_attr($text_color); ?>;
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
                min-width: 140px;
            }
            
            .voxel-guest-view-switcher .switcher-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
                text-decoration: none;
                color: <?php echo esc_attr($text_color); ?>;
                opacity: 0.9;
            }
            
            .voxel-guest-view-switcher .switcher-button:active {
                transform: translateY(0);
            }
            
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: scale(0.8);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }
            
            @media (max-width: 768px) {
                .voxel-guest-view-switcher {
                    <?php
                    // Mobile positioning - always bottom center on mobile
                    echo 'bottom: 20px; left: 50%; transform: translateX(-50%); right: auto; top: auto;';
                    ?>
                }
                
                .voxel-guest-view-switcher .switcher-button {
                    padding: 10px 16px;
                    font-size: 13px;
                    min-width: 120px;
                }
            }
        </style>
        <?php
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_script(
            'voxel-toolkit-guest-view',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/guest-view.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );
        
        // Get settings
        $settings = get_option('voxel_toolkit_options', array());
        $guest_view_settings = isset($settings['guest_view']) ? $settings['guest_view'] : array();
        
        wp_localize_script('voxel-toolkit-guest-view', 'voxelToolkitGuestView', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('voxel_toolkit_guest_view'),
            'isGuestView' => $this->is_guest_view_active(),
            'isLoggedIn' => is_user_logged_in(),
            'currentUrl' => (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
            'settings' => array(
                'showConfirmation' => !empty($guest_view_settings['show_confirmation']),
                'position' => isset($guest_view_settings['button_position']) ? $guest_view_settings['button_position'] : 'bottom-right',
                'colors' => array(
                    'background' => isset($guest_view_settings['bg_color']) ? $guest_view_settings['bg_color'] : '#667eea',
                    'backgroundEnd' => isset($guest_view_settings['bg_color_end']) ? $guest_view_settings['bg_color_end'] : '#764ba2',
                    'text' => isset($guest_view_settings['text_color']) ? $guest_view_settings['text_color'] : '#ffffff',
                    'buttonBg' => isset($guest_view_settings['button_bg_color']) ? $guest_view_settings['button_bg_color'] : '#ffffff',
                    'buttonText' => isset($guest_view_settings['button_text_color']) ? $guest_view_settings['button_text_color'] : '#667eea'
                )
            )
        ));
    }
    
    /**
     * Register Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        // Make sure Elementor is loaded
        if (!class_exists('\Elementor\Widget_Base')) {
            return;
        }
        
        // Include widget file
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/elementor/guest-view-button-widget.php';
        
        // Register widget
        if (class_exists('Voxel_Toolkit_Guest_View_Button_Widget')) {
            $widgets_manager->register_widget_type(new Voxel_Toolkit_Guest_View_Button_Widget());
        }
    }
    
    /**
     * Add Elementor widget category
     */
    public function add_elementor_widget_category($elements_manager) {
        $elements_manager->add_category(
            'voxel-toolkit',
            [
                'title' => __('Voxel Toolkit', 'voxel-toolkit'),
                'icon' => 'fa fa-plug',
            ]
        );
    }
    
    /**
     * Add admin bar button for guest view
     */
    public function add_admin_bar_button($wp_admin_bar) {
        // Only show if user is logged in and we're not already in guest view
        if (!is_user_logged_in() || $this->is_guest_view_active()) {
            return;
        }
        
        // Check if on frontend
        if (is_admin()) {
            return;
        }
        
        // Get settings
        $settings = Voxel_Toolkit_Settings::instance();
        $guest_view_settings = $settings->get_function_settings('guest_view', array());
        
        // Check if show confirmation is enabled
        $show_confirmation = isset($guest_view_settings['show_confirmation']) ? $guest_view_settings['show_confirmation'] : true;
        
        // Add the admin bar button
        $wp_admin_bar->add_node(array(
            'id' => 'voxel-toolkit-guest-view',
            'title' => '<span class="ab-icon dashicons dashicons-visibility" style="margin-top: 2px;"></span> ' . __('View as Guest', 'voxel-toolkit'),
            'href' => '#',
            'meta' => array(
                'class' => 'voxel-toolkit-guest-view-btn voxel-toolkit-guest-view-admin-bar'
            )
        ));
    }
}