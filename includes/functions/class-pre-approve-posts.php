<?php
/**
 * Pre-Approve Posts Feature
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
            'post_types' => array()
        ));
        
        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }
    
    /**
     * Handle settings update
     */
    public function on_settings_updated($old_settings, $new_settings) {
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
        <h3><?php _e('Pre-Approve Posts Settings', 'voxel-toolkit'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Pre-Approve Posts?', 'voxel-toolkit'); ?></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Pre-Approve Posts', 'voxel-toolkit'); ?></span></legend>
                        <?php foreach ($post_types as $post_type): ?>
                            <?php if ($post_type->name === 'attachment') continue; ?>
                            <label for="pre_approve_<?php echo esc_attr($post_type->name); ?>">
                                <input type="checkbox" 
                                       name="voxel_toolkit_pre_approved_post_types[]" 
                                       id="pre_approve_<?php echo esc_attr($post_type->name); ?>"
                                       value="<?php echo esc_attr($post_type->name); ?>"
                                       <?php checked(in_array($post_type->name, $user_pre_approvals)); ?> />
                                <?php echo esc_html($post_type->labels->name); ?>
                            </label><br />
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="description">
                        <?php _e('When enabled, posts from this user will automatically be published instead of being marked as pending.', 'voxel-toolkit'); ?>
                    </p>
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
        
        $pre_approved_types = isset($_POST['voxel_toolkit_pre_approved_post_types']) 
            ? array_map('sanitize_text_field', $_POST['voxel_toolkit_pre_approved_post_types']) 
            : array();
        
        update_user_meta($user_id, 'voxel_toolkit_pre_approved_post_types', $pre_approved_types);
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
            $pre_approved_types = get_user_meta($user_id, 'voxel_toolkit_pre_approved_post_types', true);
            
            if (!empty($pre_approved_types) && is_array($pre_approved_types)) {
                // Show green checkmark
                return '<span class="voxel-toolkit-pre-approved-check" title="' . 
                       esc_attr(implode(', ', $pre_approved_types)) . '">✓</span>';
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
        $pre_approved_types = get_user_meta($user_id, 'voxel_toolkit_pre_approved_post_types', true);
        
        if (!is_array($pre_approved_types)) {
            return false;
        }
        
        return in_array($post_type, $pre_approved_types);
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