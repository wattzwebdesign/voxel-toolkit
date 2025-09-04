<?php
/**
 * Auto Promotion Function
 * 
 * Automatically promotes posts by setting voxel:priority and reverting after a duration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Auto_Promotion {
    
    private static $instance = null;
    private $settings;
    
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
        $this->init_hooks();
        $this->schedule_cleanup_cron();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Hook into post status changes - multiple hooks to catch all scenarios
        add_action('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
        add_action('wp_insert_post', array($this, 'handle_wp_insert_post'), 10, 3);
        add_action('save_post', array($this, 'handle_save_post'), 20, 3);
        
        // Additional hooks to catch frontend form submissions
        add_action('wp_after_insert_post', array($this, 'handle_after_insert_post'), 10, 4);
        add_action('rest_after_insert_post', array($this, 'handle_rest_after_insert_post'), 10, 3);
        
        // Voxel-specific hooks (if they exist)
        add_action('voxel/post/created', array($this, 'handle_voxel_post_created'), 10, 1);
        add_action('voxel/post/published', array($this, 'handle_voxel_post_published'), 10, 1);
        
        // Register custom cron job
        add_action('voxel_toolkit_auto_promotion_cleanup', array($this, 'process_expired_promotions'));
        
        // Add custom cron schedule if not exists
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        // Add hourly schedule if not exists
        if (!isset($schedules['hourly'])) {
            $schedules['hourly'] = array(
                'interval' => HOUR_IN_SECONDS,
                'display'  => __('Once Hourly', 'voxel-toolkit')
            );
        }
        
        // Add every 5 minutes for more frequent checks
        $schedules['voxel_toolkit_frequent'] = array(
            'interval' => 300, // 5 minutes
            'display'  => __('Every 5 Minutes', 'voxel-toolkit')
        );
        
        return $schedules;
    }
    
    /**
     * Schedule cleanup cron job
     */
    private function schedule_cleanup_cron() {
        if (!wp_next_scheduled('voxel_toolkit_auto_promotion_cleanup')) {
            wp_schedule_event(time(), 'voxel_toolkit_frequent', 'voxel_toolkit_auto_promotion_cleanup');
        }
    }
    
    /**
     * Handle post status changes
     */
    public function handle_post_status_change($new_status, $old_status, $post) {
        
        // Only process when post becomes published
        if ($new_status !== 'publish') {
            return;
        }
        
        // Skip if already published (unless it's our special 'new' status)
        if ($old_status === 'publish') {
            return;
        }
        
        // Check if this post type is enabled for auto promotion
        if (!$this->is_post_type_enabled($post->post_type)) {
            return;
        }
        
        // Get settings for this post type
        $post_type_settings = $this->get_post_type_settings($post->post_type);
        if (empty($post_type_settings)) {
            return;
        }
        
        
        // Apply promotion
        $this->apply_promotion($post->ID, $post_type_settings);
    }
    
    /**
     * Handle wp_insert_post action
     */
    public function handle_wp_insert_post($post_id, $post, $update) {
        // Only handle new posts or status changes to publish
        if ($post->post_status !== 'publish') {
            return;
        }
        
        
        // For new posts, simulate status change
        if (!$update) {
            $this->handle_post_status_change('publish', 'new', $post);
        }
    }
    
    /**
     * Handle save_post action
     */
    public function handle_save_post($post_id, $post, $update) {
        // Skip revisions and auto-drafts
        if (wp_is_post_revision($post_id) || $post->post_status == 'auto-draft') {
            return;
        }
        
        // Only handle posts that are being published
        if ($post->post_status !== 'publish') {
            return;
        }
        
        
        // Check if this is a new publish by looking at previous status
        $previous_status = get_post_meta($post_id, '_previous_status', true);
        if ($previous_status && $previous_status !== 'publish') {
            $this->handle_post_status_change('publish', $previous_status, $post);
        } elseif (!$previous_status && !$update) {
            // New post being published for first time
            $this->handle_post_status_change('publish', 'new', $post);
        }
        
        // Store current status for next time
        update_post_meta($post_id, '_previous_status', $post->post_status);
    }
    
    /**
     * Handle wp_after_insert_post action (WordPress 5.6+)
     */
    public function handle_after_insert_post($post_id, $post, $update, $post_before) {
        
        if ($post->post_status === 'publish') {
            $old_status = $post_before ? $post_before->post_status : 'new';
            $this->handle_post_status_change('publish', $old_status, $post);
        }
    }
    
    /**
     * Handle rest_after_insert_post action (REST API)
     */
    public function handle_rest_after_insert_post($post, $request, $creating) {
        
        if ($post->post_status === 'publish' && $creating) {
            $this->handle_post_status_change('publish', 'new', $post);
        }
    }
    
    /**
     * Handle Voxel post created hook
     */
    public function handle_voxel_post_created($post_id) {
        $post = get_post($post_id);
        if ($post) {
            if ($post->post_status === 'publish') {
                $this->handle_post_status_change('publish', 'new', $post);
            }
        }
    }
    
    /**
     * Handle Voxel post published hook
     */
    public function handle_voxel_post_published($post_id) {
        $post = get_post($post_id);
        if ($post) {
            $this->handle_post_status_change('publish', 'pending', $post);
        }
    }
    
    
    /**
     * Check if post type is enabled for auto promotion
     */
    private function is_post_type_enabled($post_type) {
        $function_settings = $this->settings->get_function_settings('auto_promotion', array());
        $enabled_post_types = isset($function_settings['post_types']) ? $function_settings['post_types'] : array();
        
        return in_array($post_type, $enabled_post_types);
    }
    
    /**
     * Get settings for specific post type
     */
    private function get_post_type_settings($post_type) {
        $function_settings = $this->settings->get_function_settings('auto_promotion', array());
        $post_type_key = 'settings_' . $post_type;
        
        return isset($function_settings[$post_type_key]) ? $function_settings[$post_type_key] : array();
    }
    
    /**
     * Apply promotion to post
     */
    private function apply_promotion($post_id, $settings) {
        $priority = isset($settings['priority']) ? intval($settings['priority']) : 0;
        $duration = isset($settings['duration']) ? intval($settings['duration']) : 0;
        $duration_unit = isset($settings['duration_unit']) ? $settings['duration_unit'] : 'hours';
        
        
        if ($priority <= 0 || $duration <= 0) {
            return false;
        }
        
        // Check if post exists
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }
        
        // Store original priority (if it exists)
        $original_priority = get_post_meta($post_id, 'voxel:priority', true);
        if ($original_priority === '' || $original_priority === false) {
            $original_priority = 0;
        }
        
        // Set new priority - Force update even if same value
        $meta_updated = update_post_meta($post_id, 'voxel:priority', $priority);
        
        // Verify the meta was set
        $current_priority = get_post_meta($post_id, 'voxel:priority', true);
        
        // Also try to add the meta key directly if it doesn't exist
        if ($original_priority == 0 && !metadata_exists('post', $post_id, 'voxel:priority')) {
            $added = add_post_meta($post_id, 'voxel:priority', $priority, true);
        }
        
        // Calculate expiration time
        $expiration_time = $this->calculate_expiration_time($duration, $duration_unit);
        
        // Store promotion data for later cleanup
        $promotion_data = array(
            'post_id' => $post_id,
            'original_priority' => $original_priority,
            'promotion_priority' => $priority,
            'expires_at' => $expiration_time,
            'created_at' => time()
        );
        
        // Store in options table for tracking
        $active_promotions = get_option('voxel_toolkit_active_promotions', array());
        $active_promotions[$post_id] = $promotion_data;
        update_option('voxel_toolkit_active_promotions', $active_promotions);
        
        return true;
    }
    
    /**
     * Calculate expiration time based on duration and unit
     */
    private function calculate_expiration_time($duration, $unit) {
        $current_time = time();
        
        switch ($unit) {
            case 'hours':
                return $current_time + ($duration * HOUR_IN_SECONDS);
            case 'days':
                return $current_time + ($duration * DAY_IN_SECONDS);
            case 'weeks':
                return $current_time + ($duration * WEEK_IN_SECONDS);
            default:
                return $current_time + ($duration * HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Process expired promotions (called by cron)
     */
    public function process_expired_promotions() {
        $active_promotions = get_option('voxel_toolkit_active_promotions', array());
        
        if (empty($active_promotions)) {
            return;
        }
        
        $current_time = time();
        $updated_promotions = array();
        $expired_count = 0;
        
        foreach ($active_promotions as $post_id => $promotion_data) {
            if ($current_time >= $promotion_data['expires_at']) {
                // Promotion has expired, revert priority
                $this->revert_promotion($post_id, $promotion_data);
                $expired_count++;
            } else {
                // Keep active promotion
                $updated_promotions[$post_id] = $promotion_data;
            }
        }
        
        // Update the active promotions list
        update_option('voxel_toolkit_active_promotions', $updated_promotions);
    }
    
    /**
     * Revert promotion for a post
     */
    private function revert_promotion($post_id, $promotion_data) {
        $original_priority = $promotion_data['original_priority'];
        
        // Revert to original priority
        if ($original_priority == 0) {
            // Remove the meta if original was 0
            delete_post_meta($post_id, 'voxel:priority');
        } else {
            // Set back to original priority
            update_post_meta($post_id, 'voxel:priority', $original_priority);
        }
        
        return true;
    }
    
    /**
     * Get active promotions for admin display
     */
    public function get_active_promotions() {
        $active_promotions = get_option('voxel_toolkit_active_promotions', array());
        
        // Add post titles and remaining time for display
        foreach ($active_promotions as $post_id => &$promotion) {
            $post = get_post($post_id);
            if ($post) {
                $promotion['post_title'] = $post->post_title;
                $promotion['post_type'] = $post->post_type;
                $promotion['remaining_time'] = $promotion['expires_at'] - time();
            }
        }
        
        return $active_promotions;
    }
    
    
    /**
     * Clean up on plugin deactivation
     */
    public static function cleanup() {
        // Clear scheduled cron
        $timestamp = wp_next_scheduled('voxel_toolkit_auto_promotion_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'voxel_toolkit_auto_promotion_cleanup');
        }
        
        // Optionally revert all active promotions
        $instance = self::instance();
        $active_promotions = get_option('voxel_toolkit_active_promotions', array());
        
        foreach ($active_promotions as $post_id => $promotion_data) {
            $instance->revert_promotion($post_id, $promotion_data);
        }
        
        // Clean up options
        delete_option('voxel_toolkit_active_promotions');
    }
}

// Initialize if the function is enabled
add_action('init', function() {
    if (class_exists('Voxel_Toolkit_Settings')) {
        $settings = Voxel_Toolkit_Settings::instance();
        if ($settings->is_function_enabled('auto_promotion')) {
            Voxel_Toolkit_Auto_Promotion::instance();
        }
    }
});