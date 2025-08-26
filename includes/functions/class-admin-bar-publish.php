<?php
/**
 * Admin Bar Publish Function
 * 
 * Adds Publish/Mark as Pending button to WordPress admin bar based on post status
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Admin_Bar_Publish {
    
    private $settings;
    private $enabled_post_types = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = Voxel_Toolkit_Settings::instance();
        $this->load_settings();
        $this->init_hooks();
    }
    
    /**
     * Load settings
     */
    private function load_settings() {
        $function_settings = $this->settings->get_function_settings('admin_bar_publish', array(
            'enabled' => false,
            'post_types' => array()
        ));
        
        $this->enabled_post_types = isset($function_settings['post_types']) ? $function_settings['post_types'] : array();
        
        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        if (empty($this->enabled_post_types)) {
            return;
        }
        
        // Add admin bar node
        add_action('admin_bar_menu', array($this, 'add_admin_bar_button'), 100);
        
        // Handle AJAX requests
        add_action('wp_ajax_voxel_toolkit_toggle_post_status', array($this, 'ajax_toggle_post_status'));
        add_action('wp_ajax_nopriv_voxel_toolkit_toggle_post_status', array($this, 'ajax_toggle_post_status'));
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add admin bar button
     *
     * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance
     */
    public function add_admin_bar_button($wp_admin_bar) {
        // Only show on single post pages or in admin edit screens
        if (!$this->should_show_button()) {
            return;
        }
        
        $post_id = $this->get_current_post_id();
        if (!$post_id) {
            return;
        }
        
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, $this->enabled_post_types)) {
            return;
        }
        
        // Determine button text and action based on current status
        if ($post->post_status === 'pending') {
            $button_text = __('Publish', 'voxel-toolkit');
            $button_action = 'publish';
            $button_class = 'voxel-toolkit-publish-btn';
        } elseif ($post->post_status === 'publish') {
            $button_text = __('Mark as Pending', 'voxel-toolkit');
            $button_action = 'pending';
            $button_class = 'voxel-toolkit-pending-btn';
        } else {
            // Don't show button for other statuses (draft, trash, etc.)
            return;
        }
        
        $wp_admin_bar->add_node(array(
            'id' => 'voxel-toolkit-publish-toggle',
            'title' => '<span class="' . $button_class . '" data-post-id="' . $post_id . '" data-action="' . $button_action . '">' . $button_text . '</span>',
            'href' => '#',
            'meta' => array(
                'class' => 'voxel-toolkit-admin-bar-button',
                'onclick' => 'return false;'
            )
        ));
    }
    
    /**
     * Check if button should be shown
     *
     * @return bool
     */
    private function should_show_button() {
        global $pagenow;
        
        // Show in admin edit screens
        if (is_admin() && in_array($pagenow, array('post.php', 'edit.php'))) {
            return true;
        }
        
        // Show on single post pages on frontend
        if (!is_admin() && is_singular()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get current post ID
     *
     * @return int|false
     */
    private function get_current_post_id() {
        global $pagenow, $post;
        
        // Admin edit screen
        if (is_admin() && $pagenow === 'post.php' && isset($_GET['post'])) {
            return intval($_GET['post']);
        }
        
        // Frontend single post
        if (!is_admin() && is_singular() && $post) {
            return $post->ID;
        }
        
        return false;
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        if (!$this->should_show_button()) {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        // Add inline JavaScript
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                $("#wp-admin-bar-voxel-toolkit-publish-toggle").on("click", function(e) {
                    e.preventDefault();
                    
                    var $button = $(this).find("span[data-post-id]");
                    var postId = $button.data("post-id");
                    var action = $button.data("action");
                    
                    if (!postId || !action) {
                        return;
                    }
                    
                    // Disable button and show loading state
                    var originalText = $button.text();
                    $button.text("' . esc_js(__('Processing...', 'voxel-toolkit')) . '");
                    $(this).addClass("disabled");
                    
                    $.ajax({
                        url: "' . admin_url('admin-ajax.php') . '",
                        type: "POST",
                        data: {
                            action: "voxel_toolkit_toggle_post_status",
                            post_id: postId,
                            new_status: action,
                            nonce: "' . wp_create_nonce('voxel_toolkit_publish_nonce') . '"
                        },
                        success: function(response) {
                            if (response.success) {
                                // Update button text and action
                                if (action === "publish") {
                                    $button.text("' . esc_js(__('Mark as Pending', 'voxel-toolkit')) . '");
                                    $button.data("action", "pending");
                                    $button.removeClass("voxel-toolkit-publish-btn").addClass("voxel-toolkit-pending-btn");
                                } else {
                                    $button.text("' . esc_js(__('Publish', 'voxel-toolkit')) . '");
                                    $button.data("action", "publish");
                                    $button.removeClass("voxel-toolkit-pending-btn").addClass("voxel-toolkit-publish-btn");
                                }
                                
                                // Show success message if available
                                if (response.data && response.data.message) {
                                    if (typeof window.wp !== "undefined" && window.wp.data) {
                                        // Use Gutenberg notices if available
                                        window.wp.data.dispatch("core/notices").createSuccessNotice(response.data.message);
                                    } else {
                                        // Fallback: simple alert
                                        alert(response.data.message);
                                    }
                                }
                            } else {
                                $button.text(originalText);
                                alert(response.data || "' . esc_js(__('An error occurred.', 'voxel-toolkit')) . '");
                            }
                        },
                        error: function() {
                            $button.text(originalText);
                            alert("' . esc_js(__('Network error occurred.', 'voxel-toolkit')) . '");
                        },
                        complete: function() {
                            $(this).removeClass("disabled");
                        }
                    });
                });
            });
        ');
        
        // Add inline CSS for button styling
        wp_add_inline_style('admin-bar', '
            .voxel-toolkit-admin-bar-button .voxel-toolkit-publish-btn {
                background: #00a32a !important;
                color: #fff !important;
                padding: 4px 8px !important;
                border-radius: 3px !important;
                font-weight: 600 !important;
            }
            
            .voxel-toolkit-admin-bar-button .voxel-toolkit-pending-btn {
                background: #dba617 !important;
                color: #fff !important;
                padding: 4px 8px !important;
                border-radius: 3px !important;
                font-weight: 600 !important;
            }
            
            .voxel-toolkit-admin-bar-button:hover .voxel-toolkit-publish-btn {
                background: #008a20 !important;
            }
            
            .voxel-toolkit-admin-bar-button:hover .voxel-toolkit-pending-btn {
                background: #c09853 !important;
            }
            
            .voxel-toolkit-admin-bar-button.disabled {
                opacity: 0.6;
                pointer-events: none;
            }
        ');
    }
    
    /**
     * Handle AJAX post status toggle
     */
    public function ajax_toggle_post_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'voxel_toolkit_publish_nonce')) {
            wp_send_json_error(__('Security check failed.', 'voxel-toolkit'));
        }
        
        $post_id = intval($_POST['post_id']);
        $new_status = sanitize_text_field($_POST['new_status']);
        
        // Validate inputs
        if (!$post_id || !in_array($new_status, array('publish', 'pending'))) {
            wp_send_json_error(__('Invalid request.', 'voxel-toolkit'));
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(__('Post not found.', 'voxel-toolkit'));
        }
        
        // Check if post type is enabled for this function
        if (!in_array($post->post_type, $this->enabled_post_types)) {
            wp_send_json_error(__('Post type not supported.', 'voxel-toolkit'));
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('You do not have permission to edit this post.', 'voxel-toolkit'));
        }
        
        // Update post status
        $result = wp_update_post(array(
            'ID' => $post_id,
            'post_status' => $new_status
        ));
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        if ($result) {
            // Fire action for other plugins to hook into
            do_action('voxel_toolkit/post_status_changed', $post_id, $new_status, $post->post_status);
            
            $success_message = $new_status === 'publish' 
                ? __('Post published successfully.', 'voxel-toolkit')
                : __('Post marked as pending successfully.', 'voxel-toolkit');
            
            wp_send_json_success(array(
                'message' => $success_message,
                'new_status' => $new_status,
                'post_id' => $post_id
            ));
        } else {
            wp_send_json_error(__('Failed to update post status.', 'voxel-toolkit'));
        }
    }
    
    /**
     * Handle settings updates
     *
     * @param array $new_settings New settings
     * @param array $old_settings Old settings
     */
    public function on_settings_updated($new_settings, $old_settings) {
        $function_settings = isset($new_settings['admin_bar_publish']) ? $new_settings['admin_bar_publish'] : array();
        $this->enabled_post_types = isset($function_settings['post_types']) ? $function_settings['post_types'] : array();
        
        // Reinitialize hooks with new settings
        $this->remove_hooks();
        $this->init_hooks();
    }
    
    /**
     * Remove hooks (for cleanup)
     */
    private function remove_hooks() {
        remove_action('admin_bar_menu', array($this, 'add_admin_bar_button'), 100);
        remove_action('wp_ajax_voxel_toolkit_toggle_post_status', array($this, 'ajax_toggle_post_status'));
        remove_action('wp_ajax_nopriv_voxel_toolkit_toggle_post_status', array($this, 'ajax_toggle_post_status'));
        remove_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        remove_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Deinitialize (cleanup when function is disabled)
     */
    public function deinit() {
        $this->remove_hooks();
        remove_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10);
    }
}