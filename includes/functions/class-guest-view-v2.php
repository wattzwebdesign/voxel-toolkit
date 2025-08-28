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
            $redirect_to = esc_url_raw($_REQUEST['redirect_to']);
        }
        
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
            $redirect_to = esc_url_raw($_REQUEST['redirect_to']);
        }
        
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
            // Create the switch to guest URL
            $url = wp_nonce_url(add_query_arg(array(
                'action' => 'voxel_switch_to_guest',
                'redirect_to' => $_POST['current_url'] ?? home_url()
            ), home_url()), 'voxel_switch_to_guest');
            
            wp_send_json_success(array(
                'message' => __('Redirecting to guest view...', 'voxel-toolkit'),
                'redirect_url' => $url
            ));
        } else {
            wp_send_json_error(__('Invalid action.', 'voxel-toolkit'));
        }
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
        
        // Create the switch back URL
        $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $switch_back_url = wp_nonce_url(add_query_arg(array(
            'action' => 'voxel_switch_back_from_guest',
            'redirect_to' => $current_url
        ), home_url()), 'voxel_switch_back_from_guest_' . $old_user->ID);
        
        ?>
        <div id="voxel-toolkit-guest-view-switcher" class="voxel-guest-view-switcher">
            <div class="switcher-content">
                <div class="switcher-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 20a6 6 0 0 0-12 0"></path>
                        <circle cx="12" cy="10" r="4"></circle>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </div>
                <div class="switcher-info">
                    <div class="switcher-label"><?php _e('Guest View Active', 'voxel-toolkit'); ?></div>
                    <div class="switcher-user"><?php echo esc_html($old_user->display_name); ?></div>
                </div>
                <a href="<?php echo esc_url($switch_back_url); ?>" class="switcher-button">
                    <?php _e('Switch Back', 'voxel-toolkit'); ?>
                </a>
            </div>
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
            
            /* Guest View Switcher Styles */
            .voxel-guest-view-switcher {
                position: fixed;
                bottom: 30px;
                right: 30px;
                z-index: 999999;
                animation: slideInRight 0.3s ease-out;
            }
            
            .voxel-guest-view-switcher .switcher-content {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 15px 20px;
                border-radius: 50px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .voxel-guest-view-switcher .switcher-icon svg {
                width: 24px;
                height: 24px;
            }
            
            .voxel-guest-view-switcher .switcher-info {
                flex: 1;
            }
            
            .voxel-guest-view-switcher .switcher-label {
                font-size: 11px;
                opacity: 0.9;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .voxel-guest-view-switcher .switcher-user {
                font-size: 14px;
                font-weight: 600;
            }
            
            .voxel-guest-view-switcher .switcher-button {
                background: white;
                color: #667eea;
                border: none;
                padding: 10px 20px;
                border-radius: 25px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                white-space: nowrap;
                text-decoration: none;
            }
            
            .voxel-guest-view-switcher .switcher-button:hover {
                transform: scale(1.05);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
                text-decoration: none;
                color: #667eea;
            }
            
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @media (max-width: 768px) {
                .voxel-guest-view-switcher {
                    bottom: 20px;
                    right: 20px;
                    left: 20px;
                }
                
                .voxel-guest-view-switcher .switcher-content {
                    justify-content: space-between;
                    padding: 12px 15px;
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
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/guest-view-v2.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );
        
        wp_localize_script('voxel-toolkit-guest-view', 'voxelToolkitGuestView', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('voxel_toolkit_guest_view'),
            'isGuestView' => $this->is_guest_view_active(),
            'isLoggedIn' => is_user_logged_in(),
            'currentUrl' => (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
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
}