<?php
/**
 * Guest View Function
 * 
 * Allows logged-in users to temporarily view the site as a guest
 * Includes Elementor widget for switching views
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Guest_View {
    
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
        add_action('wp_ajax_voxel_toolkit_exit_guest_view', array($this, 'ajax_exit_guest_view'));
        
        // Initialize guest view on frontend - very early
        add_action('init', array($this, 'init_guest_view_session'), 1);
        
        // Filter the current user - use multiple hooks for compatibility
        add_filter('determine_current_user', array($this, 'filter_current_user'), 999999);
        add_action('init', array($this, 'maybe_override_current_user'), 5);
        
        // Add floating switch back button when in guest view
        add_action('wp_footer', array($this, 'render_switch_back_button'), 999);
        
        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // Debug output
        if (isset($_GET['voxel_debug']) && current_user_can('manage_options')) {
            add_action('wp_footer', array($this, 'debug_output'));
        }
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
     * Initialize guest view session
     */
    public function init_guest_view_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
        
        // Debug logging - don't use current_user_can here as it might not be ready
        if (isset($_GET['voxel_debug'])) {
            error_log('Guest View Debug - init_guest_view_session called');
            error_log('Guest View Debug - Session ID: ' . session_id());
            error_log('Guest View Debug - Session Data: ' . print_r($_SESSION, true));
            if (isset($_SESSION['voxel_toolkit_guest_view'])) {
                error_log('Guest View is SET in session');
            } else {
                error_log('Guest View is NOT set in session');
            }
        }
    }
    
    /**
     * Filter current user when in guest view mode
     */
    public function filter_current_user($user_id) {
        // Debug logging - don't use current_user_can as it would create infinite loop
        if (isset($_GET['voxel_debug'])) {
            error_log('Guest View Debug - filter_current_user called with user_id: ' . $user_id);
            error_log('Guest View Debug - is_admin: ' . (is_admin() ? 'true' : 'false'));
            error_log('Guest View Debug - is_guest_view_active: ' . ($this->is_guest_view_active() ? 'true' : 'false'));
        }
        
        if (!is_admin() && $this->is_guest_view_active()) {
            if (isset($_GET['voxel_debug'])) {
                error_log('Guest View Debug - Returning 0 to simulate logged-out state');
            }
            return 0; // Return 0 to simulate logged-out state
        }
        return $user_id;
    }
    
    /**
     * Check if guest view is active
     */
    private function is_guest_view_active() {
        if (!isset($_SESSION['voxel_toolkit_guest_view'])) {
            if (isset($_GET['voxel_debug'])) {
                error_log('Guest View Debug - No guest view in session');
            }
            return false;
        }
        
        $guest_view = $_SESSION['voxel_toolkit_guest_view'];
        
        if (isset($_GET['voxel_debug'])) {
            error_log('Guest View Debug - Session guest view data: ' . print_r($guest_view, true));
        }
        
        // Check if guest view is enabled and belongs to current user
        if (!empty($guest_view['enabled']) && 
            !empty($guest_view['user_id']) && 
            !empty($guest_view['original_user_id'])) {
            
            // We need to check differently here - don't use get_current_user_id() as it might be filtered
            // Instead, check if the session data is valid
            if (isset($_GET['voxel_debug'])) {
                error_log('Guest View Debug - Guest view appears valid, returning true');
            }
            return true;
        }
        
        if (isset($_GET['voxel_debug'])) {
            error_log('Guest View Debug - Guest view invalid, returning false');
        }
        
        return false;
    }
    
    /**
     * Maybe override current user - alternative method
     */
    public function maybe_override_current_user() {
        if (!is_admin() && $this->is_guest_view_active()) {
            // Force WordPress to treat the user as logged out
            wp_set_current_user(0);
            
            if (isset($_GET['voxel_debug'])) {
                error_log('Guest View Debug - Forced current user to 0 via wp_set_current_user');
            }
        }
    }
    
    /**
     * Get the real user ID even when in guest view
     */
    private function get_real_user_id() {
        // Temporarily remove our filter to get the real user ID
        remove_filter('determine_current_user', array($this, 'filter_current_user'), 999999);
        $user_id = get_current_user_id();
        add_filter('determine_current_user', array($this, 'filter_current_user'), 999999);
        return $user_id;
    }
    
    /**
     * Debug output
     */
    public function debug_output() {
        // Check for debug without using current_user_can as it might be filtered
        $real_user_id = $this->get_real_user_id();
        if (!$real_user_id) {
            return;
        }
        
        $user = get_user_by('ID', $real_user_id);
        if (!$user || !in_array('administrator', $user->roles)) {
            return;
        }
        ?>
        <div style="position: fixed; top: 50px; left: 10px; background: #fff; border: 2px solid #000; padding: 10px; z-index: 999999; max-width: 300px;">
            <h4 style="margin: 0 0 10px 0;">Guest View Debug</h4>
            <p><strong>Session Active:</strong> <?php echo $this->is_guest_view_active() ? 'Yes' : 'No'; ?></p>
            <p><strong>Current User ID:</strong> <?php echo get_current_user_id(); ?></p>
            <p><strong>Real User ID:</strong> <?php echo $real_user_id; ?></p>
            <p><strong>Is User Logged In:</strong> <?php echo is_user_logged_in() ? 'Yes' : 'No'; ?></p>
            <?php if (isset($_SESSION['voxel_toolkit_guest_view'])): ?>
                <p><strong>Session Data:</strong></p>
                <pre style="font-size: 10px;"><?php print_r($_SESSION['voxel_toolkit_guest_view']); ?></pre>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * AJAX handler to toggle guest view
     */
    public function ajax_toggle_guest_view() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'voxel_toolkit_guest_view')) {
            wp_send_json_error(__('Security check failed.', 'voxel-toolkit'));
        }
        
        $real_user_id = $this->get_real_user_id();
        
        // Check if user is logged in
        if (!$real_user_id) {
            wp_send_json_error(__('You must be logged in to use guest view.', 'voxel-toolkit'));
        }
        
        // Enable guest view
        $_SESSION['voxel_toolkit_guest_view'] = array(
            'enabled' => true,
            'user_id' => $real_user_id,
            'original_user_id' => $real_user_id,
            'started_at' => time()
        );
        
        wp_send_json_success(array(
            'message' => __('Guest view enabled. The page will reload.', 'voxel-toolkit'),
            'reload' => true
        ));
    }
    
    /**
     * AJAX handler to exit guest view
     */
    public function ajax_exit_guest_view() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'voxel_toolkit_guest_view')) {
            wp_send_json_error(__('Security check failed.', 'voxel-toolkit'));
        }
        
        // Clear guest view session
        if (isset($_SESSION['voxel_toolkit_guest_view'])) {
            unset($_SESSION['voxel_toolkit_guest_view']);
        }
        
        wp_send_json_success(array(
            'message' => __('Returning to logged-in view.', 'voxel-toolkit'),
            'reload' => true
        ));
    }
    
    /**
     * Render floating switch back button
     */
    public function render_switch_back_button() {
        // Debug logging
        if (isset($_GET['voxel_debug'])) {
            error_log('Guest View Debug - render_switch_back_button called');
            error_log('Guest View Debug - is_guest_view_active: ' . ($this->is_guest_view_active() ? 'true' : 'false'));
            if (isset($_SESSION['voxel_toolkit_guest_view'])) {
                error_log('Guest View Debug - Session data exists: ' . print_r($_SESSION['voxel_toolkit_guest_view'], true));
            }
        }
        
        if (!$this->is_guest_view_active()) {
            if (isset($_GET['voxel_debug'])) {
                error_log('Guest View Debug - Not showing button, guest view not active');
            }
            return;
        }
        
        $real_user = get_user_by('ID', $_SESSION['voxel_toolkit_guest_view']['user_id']);
        if (!$real_user) {
            if (isset($_GET['voxel_debug'])) {
                error_log('Guest View Debug - Not showing button, real user not found for ID: ' . $_SESSION['voxel_toolkit_guest_view']['user_id']);
            }
            return;
        }
        
        if (isset($_GET['voxel_debug'])) {
            error_log('Guest View Debug - Showing switch back button for user: ' . $real_user->display_name);
        }
        ?>
        <div id="voxel-toolkit-guest-view-switcher" style="position: fixed; bottom: 30px; right: 30px; z-index: 999999;">
            <div style="background: rgba(0, 0, 0, 0.9); color: white; padding: 15px 20px; border-radius: 50px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); display: flex; align-items: center; gap: 12px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 20a6 6 0 0 0-12 0"></path>
                    <circle cx="12" cy="10" r="4"></circle>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                </svg>
                <div>
                    <div style="font-size: 12px; opacity: 0.8; margin-bottom: 2px;"><?php _e('Guest View Active', 'voxel-toolkit'); ?></div>
                    <div style="font-size: 14px; font-weight: 500;"><?php echo esc_html($real_user->display_name); ?></div>
                </div>
                <button id="voxel-toolkit-exit-guest-view" 
                        style="background: white; color: #333; border: none; padding: 8px 16px; border-radius: 25px; font-size: 14px; font-weight: 500; cursor: pointer; margin-left: 10px; transition: all 0.2s;">
                    <?php _e('Switch Back', 'voxel-toolkit'); ?>
                </button>
            </div>
        </div>
        <style>
            #voxel-toolkit-guest-view-switcher {
                animation: voxelToolkitSlideIn 0.3s ease-out;
            }
            
            #voxel-toolkit-exit-guest-view:hover {
                transform: scale(1.05);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            }
            
            @keyframes voxelToolkitSlideIn {
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
                #voxel-toolkit-guest-view-switcher {
                    bottom: 20px;
                    right: 20px;
                    left: 20px;
                }
                
                #voxel-toolkit-guest-view-switcher > div {
                    width: 100%;
                    justify-content: space-between;
                }
            }
        </style>
        <?php
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_script(
            'voxel-toolkit-guest-view',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/guest-view.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );
        
        wp_localize_script('voxel-toolkit-guest-view', 'voxelToolkitGuestView', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('voxel_toolkit_guest_view'),
            'isGuestView' => $this->is_guest_view_active(),
            'strings' => array(
                'confirmEnable' => __('View the site as a guest? You can switch back anytime.', 'voxel-toolkit'),
                'confirmDisable' => __('Return to your logged-in view?', 'voxel-toolkit'),
                'enabling' => __('Switching to guest view...', 'voxel-toolkit'),
                'disabling' => __('Switching back...', 'voxel-toolkit')
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
}