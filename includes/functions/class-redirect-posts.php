<?php
/**
 * Redirect Posts Feature
 * 
 * Redirects posts with specific statuses to specified URLs based on post type
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Redirect_Posts {
    
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
        $this->options = $this->settings->get_function_settings('redirect_posts', array(
            'enabled' => false,
            'redirect_urls' => array(),
            'redirect_statuses' => array()
        ));
        
        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }
    
    /**
     * Handle settings update
     */
    public function on_settings_updated($old_settings, $new_settings) {
        if (isset($new_settings['redirect_posts'])) {
            $this->options = $new_settings['redirect_posts'];
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Only apply redirects if the function is enabled
        if (!empty($this->options['enabled'])) {
            // Use higher priority to run before other redirects
            add_action('template_redirect', array($this, 'check_and_redirect_posts'), 1);
            // Also hook into parse_query to catch posts before 404 is determined
            add_action('parse_query', array($this, 'check_query_redirect'));
        }
    }
    
    /**
     * Check for redirects during query parsing (catches posts before 404)
     */
    public function check_query_redirect($wp_query) {
        // Only on main query and single post requests
        if (!$wp_query->is_main_query() || !$wp_query->is_singular()) {
            return;
        }
        
        // Get the post ID from query vars
        $post_id = null;
        if (!empty($wp_query->query_vars['p'])) {
            $post_id = intval($wp_query->query_vars['p']);
        } elseif (!empty($wp_query->query_vars['page_id'])) {
            $post_id = intval($wp_query->query_vars['page_id']);
        } elseif (!empty($wp_query->query_vars['name'])) {
            // Try to find post by slug for any post type
            $post_types = get_post_types(array('public' => true));
            $post = get_page_by_path($wp_query->query_vars['name'], OBJECT, $post_types);
            if ($post) {
                $post_id = $post->ID;
            }
        }
        
        // Check for custom post type queries
        foreach (get_post_types(array('public' => true)) as $post_type) {
            if (!empty($wp_query->query_vars[$post_type])) {
                $post = get_page_by_path($wp_query->query_vars[$post_type], OBJECT, $post_type);
                if ($post) {
                    $post_id = $post->ID;
                    break;
                }
            }
        }
        
        if (!$post_id) {
            return;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        // Check if post should be redirected
        if ($this->should_redirect_post($post)) {
            $redirect_url = $this->get_redirect_url_for_post_type($post->post_type);
            if ($redirect_url) {
                wp_redirect($redirect_url, 301);
                exit;
            }
        }
    }
    
    /**
     * Check if post should be redirected and redirect if necessary
     */
    public function check_and_redirect_posts() {
        // Only check on single post pages
        if (!is_singular()) {
            return;
        }
        
        global $post;
        
        if (!$post) {
            return;
        }
        
        // Check if post should be redirected
        if (!$this->should_redirect_post($post)) {
            return;
        }
        
        // Get redirect URL for this post type
        $redirect_url = $this->get_redirect_url_for_post_type($post->post_type);
        
        if (!$redirect_url) {
            return;
        }
        
        // Perform the redirect
        wp_redirect($redirect_url, 301);
        exit;
    }
    
    /**
     * Check if a post should be redirected based on status
     */
    private function should_redirect_post($post) {
        $redirect_statuses = isset($this->options['redirect_statuses']) ? $this->options['redirect_statuses'] : array();
        
        // Check if post status is in redirect statuses list
        if (in_array($post->post_status, $redirect_statuses)) {
            return true;
        }
        
        // Allow other plugins to determine if post should be redirected
        return apply_filters('voxel_toolkit/should_redirect_post', false, $post, $redirect_statuses);
    }
    
    /**
     * Get redirect URL for a specific post type
     */
    private function get_redirect_url_for_post_type($post_type) {
        $redirect_urls = isset($this->options['redirect_urls']) ? $this->options['redirect_urls'] : array();
        
        if (isset($redirect_urls[$post_type]) && !empty($redirect_urls[$post_type])) {
            return esc_url($redirect_urls[$post_type]);
        }
        
        return false;
    }
}