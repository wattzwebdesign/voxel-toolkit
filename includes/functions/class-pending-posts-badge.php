<?php
/**
 * Pending Posts Badge functionality
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Pending_Posts_Badge {
    
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
        add_action('admin_menu', array($this, 'add_pending_badges'), 999);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('wp_ajax_voxel_toolkit_update_pending_count', array($this, 'ajax_update_pending_count'));
        
        // Update badge counts when post status changes
        add_action('transition_post_status', array($this, 'update_badge_on_status_change'), 10, 3);
    }
    
    /**
     * Add pending post badges to admin menu
     */
    public function add_pending_badges() {
        global $menu, $submenu;
        
        $settings = Voxel_Toolkit_Settings::instance();
        $function_settings = $settings->get_function_settings('pending_posts_badge', array());
        $enabled_post_types = isset($function_settings['post_types']) ? $function_settings['post_types'] : array();
        
        if (empty($enabled_post_types)) {
            return;
        }
        
        // Get all post types
        $post_types = get_post_types(array('public' => true), 'objects');
        
        foreach ($post_types as $post_type) {
            if (!in_array($post_type->name, $enabled_post_types)) {
                continue;
            }
            
            // Get pending count for this post type
            $pending_count = $this->get_pending_count($post_type->name);
            
            // Only add badge if there are pending posts
            if ($pending_count > 0) {
                $this->add_badge_to_menu($post_type, $pending_count);
            }
        }
    }
    
    /**
     * Get pending posts count for a post type
     */
    private function get_pending_count($post_type) {
        // First try wp_count_posts
        $count = wp_count_posts($post_type);
        
        if (isset($count->pending) && $count->pending > 0) {
            return (int) $count->pending;
        }
        
        // Fallback: Direct query for pending posts
        global $wpdb;
        
        $pending_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = %s 
             AND post_status = 'pending'",
            $post_type
        ));
        
        return (int) $pending_count;
    }
    
    /**
     * Add badge to menu item
     */
    private function add_badge_to_menu($post_type, $count) {
        global $menu, $submenu;
        
        $settings = Voxel_Toolkit_Settings::instance();
        $function_settings = $settings->get_function_settings('pending_posts_badge', array());
        $badge_color = isset($function_settings['background_color']) ? $function_settings['background_color'] : '#d63638';
        $text_color = isset($function_settings['text_color']) ? $function_settings['text_color'] : '#ffffff';
        
        $badge_html = sprintf(
            '<span class="voxel-pending-badge" style="background-color: %s; color: %s;" data-post-type="%s">%d</span>',
            esc_attr($badge_color),
            esc_attr($text_color),
            esc_attr($post_type->name),
            $count
        );
        
        // Find the menu item for this post type
        foreach ($menu as $key => $menu_item) {
            if (isset($menu_item[5]) && $menu_item[5] === 'menu-posts-' . $post_type->name) {
                // Custom post type
                $menu[$key][0] .= ' ' . $badge_html;
                break;
            } elseif ($post_type->name === 'post' && isset($menu_item[2]) && $menu_item[2] === 'edit.php') {
                // Default posts
                $menu[$key][0] .= ' ' . $badge_html;
                break;
            } elseif ($post_type->name === 'page' && isset($menu_item[2]) && $menu_item[2] === 'edit.php?post_type=page') {
                // Pages
                $menu[$key][0] .= ' ' . $badge_html;
                break;
            }
        }
    }
    
    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles() {
        if (!is_admin()) {
            return;
        }
        
        $settings = Voxel_Toolkit_Settings::instance();
        $function_settings = $settings->get_function_settings('pending_posts_badge', array());
        $badge_color = isset($function_settings['background_color']) ? $function_settings['background_color'] : '#d63638';
        $text_color = isset($function_settings['text_color']) ? $function_settings['text_color'] : '#ffffff';
        
        ?>
        <style>
            .voxel-pending-badge {
                display: inline-block;
                background-color: <?php echo esc_attr($badge_color); ?>;
                color: <?php echo esc_attr($text_color); ?>;
                border-radius: 10px;
                padding: 2px 6px;
                font-size: 11px;
                font-weight: 600;
                line-height: 1;
                margin-left: 5px;
                min-width: 16px;
                text-align: center;
                vertical-align: top;
                position: relative;
                top: -1px;
            }
            
            .voxel-pending-badge:empty {
                display: none;
            }
            
            /* Ensure badge is visible in different admin color schemes */
            .admin-color-light .voxel-pending-badge,
            .admin-color-blue .voxel-pending-badge,
            .admin-color-coffee .voxel-pending-badge,
            .admin-color-ectoplasm .voxel-pending-badge,
            .admin-color-midnight .voxel-pending-badge,
            .admin-color-ocean .voxel-pending-badge,
            .admin-color-sunrise .voxel-pending-badge {
                background-color: <?php echo esc_attr($badge_color); ?> !important;
                color: <?php echo esc_attr($text_color); ?> !important;
            }
            
            /* Animation for count updates */
            .voxel-pending-badge.updating {
                animation: voxel-badge-pulse 0.6s ease-in-out;
            }
            
            @keyframes voxel-badge-pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.2); }
                100% { transform: scale(1); }
            }
        </style>
        
        <script>
            jQuery(document).ready(function($) {
                // Update badge counts periodically
                setInterval(function() {
                    $('.voxel-pending-badge').each(function() {
                        var badge = $(this);
                        var postType = badge.data('post-type');
                        
                        if (postType) {
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'voxel_toolkit_update_pending_count',
                                    post_type: postType,
                                    nonce: '<?php echo wp_create_nonce('voxel_toolkit_pending_count'); ?>'
                                },
                                success: function(response) {
                                    if (response.success && response.data.count !== undefined) {
                                        var newCount = parseInt(response.data.count);
                                        var currentCount = parseInt(badge.text()) || 0;
                                        
                                        if (newCount !== currentCount) {
                                            if (newCount > 0) {
                                                badge.addClass('updating');
                                                badge.text(newCount);
                                                badge.show();
                                                
                                                setTimeout(function() {
                                                    badge.removeClass('updating');
                                                }, 600);
                                            } else {
                                                // If count is 0, remove the badge entirely
                                                badge.remove();
                                            }
                                        }
                                    }
                                }
                            });
                        }
                    });
                }, 30000); // Update every 30 seconds
            });
        </script>
        <?php
    }
    
    /**
     * AJAX handler to update pending count
     */
    public function ajax_update_pending_count() {
        if (!wp_verify_nonce($_POST['nonce'], 'voxel_toolkit_pending_count')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $post_type = sanitize_text_field($_POST['post_type']);
        $count = $this->get_pending_count($post_type);
        
        wp_send_json_success(array('count' => $count));
    }
    
    /**
     * Update badge when post status changes
     */
    public function update_badge_on_status_change($new_status, $old_status, $post) {
        // Only update if status is changing to/from pending
        if ($new_status === 'pending' || $old_status === 'pending') {
            // The badge will be updated on next page load or via AJAX
            // This hook is mainly for future enhancements like real-time updates
        }
    }
    
    /**
     * Remove hooks when deactivated
     */
    public function deactivate() {
        remove_action('admin_menu', array($this, 'add_pending_badges'), 999);
        remove_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        remove_action('wp_ajax_voxel_toolkit_update_pending_count', array($this, 'ajax_update_pending_count'));
        remove_action('transition_post_status', array($this, 'update_badge_on_status_change'), 10, 3);
    }
}