<?php
/**
 * Pre-Approve Posts Feature v1.1
 * 
 * Automatically publishes posts from pre-approved users
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Pre_Approve_Posts {
    
    private $settings;
    private $options = array();
    
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
        $this->options = $this->settings->get_function_settings('pre_approve_posts', array(
            'enabled' => false,
            'show_column' => true,
            'approve_verified' => false,
            'approved_roles' => array(),
            'post_types' => array()
        ));
        
        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }
    
    /**
     * Handle settings update
     */
    public function on_settings_updated($new_settings, $old_settings) {
        if (isset($new_settings['pre_approve_posts'])) {
            $this->options = $new_settings['pre_approve_posts'];
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // User profile fields
        add_action('show_user_profile', array($this, 'add_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_user_profile_fields'));
        add_action('personal_options_update', array($this, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_profile_fields'));
        
        // Users list column
        if (!empty($this->options['show_column'])) {
            add_filter('manage_users_columns', array($this, 'add_users_column'));
            add_filter('manage_users_custom_column', array($this, 'show_users_column_content'), 10, 3);
        }
        
        // Auto-publish logic
        add_action('wp_insert_post', array($this, 'auto_publish_pre_approved_posts'), 10, 3);
        add_action('transition_post_status', array($this, 'handle_post_status_transition'), 10, 3);
        
        // Add admin styles
        add_action('admin_head', array($this, 'add_admin_styles'));
        
    }
    
    /**
     * Add user profile fields
     */
    public function add_user_profile_fields($user) {
        if (!current_user_can('edit_users')) {
            return;
        }
        
        // Get all public post types
        $post_types = get_post_types(array('public' => true), 'objects');
        $user_pre_approvals = get_user_meta($user->ID, 'voxel_toolkit_pre_approved_post_types', true);
        
        if (!is_array($user_pre_approvals)) {
            $user_pre_approvals = array();
        }
        ?>
        <h3><?php _e('Voxel Toolkit Settings', 'voxel-toolkit'); ?></h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="voxel_toolkit_pre_approved">
                        <?php _e('Pre-Approved for Publishing', 'voxel-toolkit'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="voxel_toolkit_pre_approved" 
                               id="voxel_toolkit_pre_approved" 
                               value="yes" 
                               <?php checked(get_user_meta($user->ID, 'voxel_toolkit_pre_approved', true) === 'yes'); ?>>
                        <?php _e('Allow this user to publish posts without review', 'voxel-toolkit'); ?>
                    </label>
                    <p class="description">
                        <?php _e('When enabled, posts from this user will be automatically published instead of pending review.', 'voxel-toolkit'); ?>
                    </p>
                    
                    <?php 
                    // Check if user is auto-approved by role
                    $user = get_user_by('ID', $user->ID);
                    $role_approved = false;
                    $approved_roles = !empty($this->options['approved_roles']) ? $this->options['approved_roles'] : array();
                    
                    if ($user && !empty($approved_roles)) {
                        foreach ($approved_roles as $role) {
                            if (in_array($role, $user->roles)) {
                                $role_approved = true;
                                break;
                            }
                        }
                    }
                    
                    $verified_approved = false;
                    if (!empty($this->options['approve_verified']) && $this->is_user_verified($user->ID)) {
                        $verified_approved = true;
                    }
                    
                    if ($role_approved || $verified_approved): ?>
                        <div style="margin-top: 10px; padding: 10px; background: #f0f8ff; border-left: 4px solid #2271b1;">
                            <strong><?php _e('Auto-Approval Status:', 'voxel-toolkit'); ?></strong><br>
                            <?php if ($role_approved): ?>
                                <span style="color: #2271b1;">✓ <?php _e('Approved by user role', 'voxel-toolkit'); ?></span><br>
                            <?php endif; ?>
                            <?php if ($verified_approved): ?>
                                <span style="color: #2271b1;">✓ <?php _e('Approved by verified profile', 'voxel-toolkit'); ?></span><br>
                            <?php endif; ?>
                            <em><?php _e('This user is automatically pre-approved regardless of manual setting above.', 'voxel-toolkit'); ?></em>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save user profile fields
     */
    public function save_user_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        if (isset($_POST['voxel_toolkit_pre_approved'])) {
            update_user_meta($user_id, 'voxel_toolkit_pre_approved', 'yes');
        } else {
            delete_user_meta($user_id, 'voxel_toolkit_pre_approved');
        }
    }
    
    /**
     * Add users list column
     */
    public function add_users_column($columns) {
        $columns['pre_approved'] = __('Pre-Approved?', 'voxel-toolkit');
        return $columns;
    }
    
    /**
     * Show users column content
     */
    public function show_users_column_content($value, $column_name, $user_id) {
        if ($column_name === 'pre_approved') {
            if ($this->is_user_pre_approved_any($user_id)) {
                $manually_approved = get_user_meta($user_id, 'voxel_toolkit_pre_approved', true) === 'yes';
                $verified_approved = !empty($this->options['approve_verified']) && $this->is_user_verified($user_id);
                $role_approved = false;
                
                // Check role approval
                if (!empty($this->options['approved_roles'])) {
                    $user = get_user_by('ID', $user_id);
                    if ($user) {
                        foreach ($this->options['approved_roles'] as $role) {
                            if (in_array($role, $user->roles)) {
                                $role_approved = true;
                                break;
                            }
                        }
                    }
                }
                
                $methods = array();
                if ($manually_approved) $methods[] = __('Manual', 'voxel-toolkit');
                if ($verified_approved) $methods[] = __('Verified', 'voxel-toolkit');
                if ($role_approved) $methods[] = __('Role', 'voxel-toolkit');
                
                $title = !empty($methods) ? implode(', ', $methods) : __('Pre-Approved', 'voxel-toolkit');
                
                if ($manually_approved) {
                    return '<span class="voxel-toolkit-pre-approved-check" style="color: green;" title="' . esc_attr($title) . '">✓ ' . __('Manual', 'voxel-toolkit') . '</span>';
                } elseif ($verified_approved) {
                    return '<span class="voxel-toolkit-pre-approved-check" style="color: blue;" title="' . esc_attr($title) . '">✓ ' . __('Verified', 'voxel-toolkit') . '</span>';
                } elseif ($role_approved) {
                    return '<span class="voxel-toolkit-pre-approved-check" style="color: purple;" title="' . esc_attr($title) . '">✓ ' . __('Role', 'voxel-toolkit') . '</span>';
                }
            }
        }
        return $value;
    }
    
    /**
     * Auto-publish pre-approved posts
     */
    public function auto_publish_pre_approved_posts($post_id, $post, $update) {
        // Skip if it's an update
        if ($update) {
            return;
        }
        
        // Check if post status is pending
        if ($post->post_status !== 'pending') {
            return;
        }
        
        // Check if user is pre-approved for this post type
        if ($this->is_user_pre_approved($post->post_author, $post->post_type)) {
            // Update post status to published
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'publish'
            ));
        }
    }
    
    /**
     * Handle post status transition
     */
    public function handle_post_status_transition($new_status, $old_status, $post) {
        // Only process if changing to pending from draft or auto-draft
        if ($new_status !== 'pending') {
            return;
        }
        
        if (!in_array($old_status, array('draft', 'auto-draft', 'new'))) {
            return;
        }
        
        // Check if user is pre-approved for this post type
        if ($this->is_user_pre_approved($post->post_author, $post->post_type)) {
            // Update post status to published
            wp_update_post(array(
                'ID' => $post->ID,
                'post_status' => 'publish'
            ));
        }
    }
    
    /**
     * Check if user is pre-approved for a post type
     */
    private function is_user_pre_approved($user_id, $post_type) {
        return $this->is_user_pre_approved_any($user_id);
    }
    
    /**
     * Check if user is pre-approved by any method
     */
    public function is_user_pre_approved_any($user_id) {
        // Manual pre-approval
        if (get_user_meta($user_id, 'voxel_toolkit_pre_approved', true) === 'yes') {
            return true;
        }
        
        // Role-based approval
        if (!empty($this->options['approved_roles'])) {
            $user = get_user_by('ID', $user_id);
            if ($user) {
                foreach ($this->options['approved_roles'] as $role) {
                    if (in_array($role, $user->roles)) {
                        return true;
                    }
                }
            }
        }
        
        // Verified user approval
        if (!empty($this->options['approve_verified']) && $this->is_user_verified($user_id)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user has a verified profile
     */
    private function is_user_verified($user_id) {
        // Get user's profile post ID from user meta
        $profile_post_id = get_user_meta($user_id, 'voxel:profile_id', true);
        
        if (!$profile_post_id) {
            return false;
        }
        
        // Check if profile has verified status in post meta
        $verified = get_post_meta($profile_post_id, 'voxel:verified', true);
        
        return ($verified === '1' || $verified === 1);
    }
    
    /**
     * Add admin styles
     */
    public function add_admin_styles() {
        ?>
        <style>
            .voxel-toolkit-pre-approved-check {
                color: #46b450;
                font-size: 18px;
                font-weight: bold;
                cursor: help;
            }
            .form-table td, .form-table th {
                padding: 6px 0;
                vertical-align: top;
                font-weight: 400;
            }
        </style>
        <?php
    }
    
    /**
     * Get available post types for settings
     */
    public static function get_available_post_types() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $available = array();
        
        foreach ($post_types as $post_type) {
            if ($post_type->name === 'attachment') {
                continue;
            }
            $available[$post_type->name] = $post_type->labels->name;
        }
        
        return $available;
    }
    
}