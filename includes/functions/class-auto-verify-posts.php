<?php
/**
 * Auto Verify Posts Function
 * 
 * Automatically marks posts as verified when submitted for selected post types
 * Uses Voxel theme hooks for seamless integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Auto_Verify_Posts {
    
    private $settings;
    private $enabled_post_types = array();
    private $current_post_type_being_created = '';
    
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
        $function_settings = $this->settings->get_function_settings('auto_verify_posts', array(
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
        
        // Hook into Voxel's post save action (when post is saved in admin)
        add_action('voxel/admin/save_post', array($this, 'on_admin_save_post'), 10, 1);
        
        // Hook into post status transitions (when post status changes)
        add_action('transition_post_status', array($this, 'on_post_status_transition'), 10, 3);
        
        // Hook into Voxel's create post validation (during post creation)
        add_action('voxel/create-post-validation', array($this, 'on_create_post_validation'), 10, 1);
        
        // Hook into wp_insert_post_data to modify post data before saving
        add_filter('wp_insert_post_data', array($this, 'on_insert_post_data'), 10, 2);
        
        // Hook into post submission via AJAX if Voxel uses AJAX for submissions
        add_action('wp_ajax_voxel_submit_post', array($this, 'on_ajax_submit_post'), 5);
        add_action('wp_ajax_nopriv_voxel_submit_post', array($this, 'on_ajax_submit_post'), 5);
    }
    
    /**
     * Handle admin post save (Voxel hook)
     * 
     * @param object $voxel_post Voxel post object
     */
    public function on_admin_save_post($voxel_post) {
        if (!$voxel_post) {
            return;
        }
        
        $post_id = method_exists($voxel_post, 'get_id') ? $voxel_post->get_id() : $voxel_post->ID;
        $post_type = method_exists($voxel_post, 'get_post_type') ? $voxel_post->get_post_type() : get_post_type($post_id);
        
        if ($this->should_auto_verify_post_type($post_type)) {
            $this->verify_post($post_id);
            
            // Log the action
                'Voxel Toolkit: Auto-verified post ID %d of type %s via admin save',
                $post_id,
                $post_type
            ));
        }
    }
    
    /**
     * Handle post status transitions
     * 
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param WP_Post $post Post object
     */
    public function on_post_status_transition($new_status, $old_status, $post) {
        // Only process when post is being published for the first time
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        
        if ($this->should_auto_verify_post_type($post->post_type)) {
            $this->verify_post($post->ID);
            
            // Log the action
                'Voxel Toolkit: Auto-verified post ID %d of type %s via status transition',
                $post->ID,
                $post->post_type
            ));
        }
    }
    
    /**
     * Handle create post validation (Voxel hook)
     * 
     * @param object $post_type Voxel post type object
     */
    public function on_create_post_validation($post_type) {
        if (!$post_type) {
            return;
        }
        
        $post_type_key = method_exists($post_type, 'get_key') ? $post_type->get_key() : $post_type->name;
        
        // Store the post type for later use in the insert_post_data filter
        $this->current_post_type_being_created = $post_type_key;
    }
    
    /**
     * Filter post data before insertion
     * 
     * @param array $data Post data
     * @param array $postarr Post array
     * @return array Modified post data
     */
    public function on_insert_post_data($data, $postarr) {
        // Check if this is a new post (not an update)
        $is_new_post = empty($postarr['ID']) || $postarr['ID'] == 0;
        
        if (!$is_new_post) {
            return $data;
        }
        
        // Check if we should auto-verify this post type
        $post_type = isset($data['post_type']) ? $data['post_type'] : 'post';
        
        if ($this->should_auto_verify_post_type($post_type)) {
            // We'll handle verification after the post is inserted
            add_action('wp_insert_post', array($this, 'on_post_inserted'), 10, 3);
        }
        
        return $data;
    }
    
    /**
     * Handle post insertion
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public function on_post_inserted($post_id, $post, $update) {
        // Only process new posts
        if ($update) {
            return;
        }
        
        if ($this->should_auto_verify_post_type($post->post_type)) {
            // Add a slight delay to ensure all post meta is saved
            wp_schedule_single_event(time() + 1, 'voxel_toolkit_delayed_verify', array($post_id));
            
            // Also verify immediately in case the scheduled event doesn't work
            $this->verify_post($post_id);
            
            // Log the action
                'Voxel Toolkit: Auto-verified post ID %d of type %s via post insertion',
                $post_id,
                $post->post_type
            ));
        }
        
        // Remove the hook to prevent it from running on other posts
        remove_action('wp_insert_post', array($this, 'on_post_inserted'), 10);
    }
    
    /**
     * Handle AJAX post submission
     */
    public function on_ajax_submit_post() {
        // Validate post type parameter
        $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : '';
        
        // Validate post type exists
        if (!empty($post_type) && !post_type_exists($post_type)) {
            return;
        }
        
        if ($this->should_auto_verify_post_type($post_type)) {
            // Hook into the post insertion for this AJAX request
            add_action('wp_insert_post', array($this, 'on_ajax_post_inserted'), 10, 3);
        }
    }
    
    /**
     * Handle AJAX post insertion
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public function on_ajax_post_inserted($post_id, $post, $update) {
        if (!$update && $this->should_auto_verify_post_type($post->post_type)) {
            $this->verify_post($post_id);
            
            // Log the action
                'Voxel Toolkit: Auto-verified post ID %d of type %s via AJAX submission',
                $post_id,
                $post->post_type
            ));
        }
    }
    
    /**
     * Check if post type should be auto-verified
     * 
     * @param string $post_type Post type
     * @return bool Whether to auto-verify
     */
    private function should_auto_verify_post_type($post_type) {
        return in_array($post_type, $this->enabled_post_types);
    }
    
    /**
     * Verify a post
     * 
     * @param int $post_id Post ID
     * @return bool Whether verification was successful
     */
    private function verify_post($post_id) {
        if (!$post_id) {
            return false;
        }
        
        // Validate post exists
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }
        
        // Primary method: Voxel's actual verification meta key
        $verified_primary = update_post_meta($post_id, 'voxel:verified', '1');
        
        // Fallback methods: Try other possible verification meta keys
        $verified_fallback1 = update_post_meta($post_id, '_voxel_verified', '1');
        $verified_fallback2 = update_post_meta($post_id, 'voxel_verified', '1');
        $verified_fallback3 = update_post_meta($post_id, '_verified', '1');
        $verified_fallback4 = update_post_meta($post_id, 'verified', '1');
        
        // Additional Voxel-specific meta that might be used
        update_post_meta($post_id, '_voxel_post_status', 'verified');
        update_post_meta($post_id, '_voxel_verification_status', 'approved');
        
        // Method 4: Add to a verification taxonomy if it exists
        $verification_taxonomies = array('verification_status', 'voxel_verification', 'post_verification');
        
        foreach ($verification_taxonomies as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                // Try to set a "verified" or "approved" term
                $verified_terms = get_terms(array(
                    'taxonomy' => $taxonomy,
                    'slug' => array('verified', 'approved', 'confirmed'),
                    'hide_empty' => false
                ));
                
                if (!empty($verified_terms)) {
                    wp_set_post_terms($post_id, array($verified_terms[0]->term_id), $taxonomy);
                }
            }
        }
        
        // Fire custom action for other plugins to hook into
        do_action('voxel_toolkit/post_auto_verified', $post_id, get_post_type($post_id));
        
        // Try to trigger Voxel's own verification hooks if they exist
        do_action('voxel/post/verified', $post_id);
        do_action('voxel_post_verified', $post_id);
        
        return true;
    }
    
    /**
     * Handle settings updates
     * 
     * @param array $new_settings New settings
     * @param array $old_settings Old settings
     */
    public function on_settings_updated($new_settings, $old_settings) {
        $function_settings = isset($new_settings['auto_verify_posts']) ? $new_settings['auto_verify_posts'] : array();
        $this->enabled_post_types = isset($function_settings['post_types']) ? $function_settings['post_types'] : array();
        
        // Reinitialize hooks with new settings
        $this->remove_hooks();
        $this->init_hooks();
    }
    
    /**
     * Remove hooks (for cleanup)
     */
    private function remove_hooks() {
        remove_action('voxel/admin/save_post', array($this, 'on_admin_save_post'), 10);
        remove_action('transition_post_status', array($this, 'on_post_status_transition'), 10);
        remove_action('voxel/create-post-validation', array($this, 'on_create_post_validation'), 10);
        remove_filter('wp_insert_post_data', array($this, 'on_insert_post_data'), 10);
        remove_action('wp_ajax_voxel_submit_post', array($this, 'on_ajax_submit_post'), 5);
        remove_action('wp_ajax_nopriv_voxel_submit_post', array($this, 'on_ajax_submit_post'), 5);
    }
    
    /**
     * Deinitialize (cleanup when function is disabled)
     */
    public function deinit() {
        $this->remove_hooks();
        remove_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10);
    }
}

// Add scheduled event handler for delayed verification
add_action('voxel_toolkit_delayed_verify', 'voxel_toolkit_delayed_verify_handler');

function voxel_toolkit_delayed_verify_handler($post_id) {
    if (!$post_id || !get_post($post_id)) {
        return;
    }
    
    // Primary verification method for Voxel
    update_post_meta($post_id, 'voxel:verified', '1');
    
    // Fallback verification methods
    $verification_methods = array(
        '_voxel_verified' => '1',
        'voxel_verified' => '1',
        '_verified' => '1',
        'verified' => '1',
        '_voxel_post_status' => 'verified',
        '_voxel_verification_status' => 'approved'
    );
    
    foreach ($verification_methods as $meta_key => $meta_value) {
        update_post_meta($post_id, $meta_key, $meta_value);
    }
    
    // Fire hooks
    do_action('voxel_toolkit/post_auto_verified', $post_id, get_post_type($post_id));
    do_action('voxel/post/verified', $post_id);
    
    // Log the verification
        'Voxel Toolkit: Delayed verification completed for post ID %d',
        $post_id
    ));
}