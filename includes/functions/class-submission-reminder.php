<?php
/**
 * Submission Reminder Function
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Submission_Reminder {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->schedule_reminder_events();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Track post submissions when posts are published or pending
        add_action('transition_post_status', array($this, 'track_post_submission'), 10, 3);
        
        // Schedule reminder emails
        add_action('voxel_toolkit_submission_reminder_check', array($this, 'process_reminder_emails'));
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_init', array($this, 'maybe_create_meta_fields'));
        }
    }
    
    /**
     * Track post submission when status changes
     */
    public function track_post_submission($new_status, $old_status, $post) {
        // Only track when post becomes published or pending
        if (!in_array($new_status, array('publish', 'pending'))) {
            return;
        }
        
        // Skip if post was already published/pending
        if (in_array($old_status, array('publish', 'pending'))) {
            return;
        }
        
        $settings = $this->get_settings();
        $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array();
        
        // Check if this post type is being tracked
        if (!in_array($post->post_type, $enabled_post_types)) {
            return;
        }
        
        // Get post author
        $user_id = $post->post_author;
        if (!$user_id) {
            return;
        }
        
        // Update submission count for this post type
        $this->increment_submission_count($user_id, $post->post_type);
        
        // Update last submission date
        update_user_meta($user_id, 'voxel_toolkit_last_submission_' . $post->post_type, current_time('mysql'));
        update_user_meta($user_id, 'voxel_toolkit_last_submission_any', current_time('mysql'));
    }
    
    /**
     * Increment submission count for user and post type
     */
    private function increment_submission_count($user_id, $post_type) {
        $meta_key = 'voxel_toolkit_submissions_' . $post_type;
        $current_count = get_user_meta($user_id, $meta_key, true);
        $current_count = $current_count ? intval($current_count) : 0;
        
        update_user_meta($user_id, $meta_key, $current_count + 1);
        
        // Also update total submissions count
        $total_count = get_user_meta($user_id, 'voxel_toolkit_total_submissions', true);
        $total_count = $total_count ? intval($total_count) : 0;
        update_user_meta($user_id, 'voxel_toolkit_total_submissions', $total_count + 1);
    }
    
    /**
     * Schedule reminder events
     */
    private function schedule_reminder_events() {
        if (!wp_next_scheduled('voxel_toolkit_submission_reminder_check')) {
            wp_schedule_event(time(), 'daily', 'voxel_toolkit_submission_reminder_check');
        }
    }
    
    /**
     * Process reminder emails
     */
    public function process_reminder_emails() {
        $settings = $this->get_settings();
        
        $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array();
        
        if (empty($enabled_post_types)) {
            return;
        }
        
        foreach ($enabled_post_types as $post_type) {
            $post_type_notifications = isset($settings['notifications'][$post_type]) ? $settings['notifications'][$post_type] : array();
            
            if (empty($post_type_notifications)) {
                continue;
            }
            
            foreach ($post_type_notifications as $notification_id => $notification) {
                if (empty($notification['enabled']) || $notification['enabled'] !== 'yes') {
                    continue;
                }
                
                $this->send_reminder_for_post_type($post_type, $notification_id, $notification);
            }
        }
    }
    
    /**
     * Send reminder emails for specific post type
     */
    private function send_reminder_for_post_type($post_type, $notification_id, $notification) {
        // Calculate time threshold based on notification settings
        $time_value = isset($notification['time_value']) ? intval($notification['time_value']) : 7;
        $time_unit = isset($notification['time_unit']) ? $notification['time_unit'] : 'days';
        
        // Convert to days for threshold calculation
        $days = $this->convert_to_days($time_value, $time_unit);
        $threshold_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get users who haven't submitted this post type in the specified period
        $users = $this->get_users_for_post_type_reminder($post_type, $threshold_date, $notification_id);
        
        foreach ($users as $user) {
            $this->send_reminder_email($user, $notification, $post_type, $notification_id, $time_value, $time_unit);
        }
    }
    
    /**
     * Convert time value and unit to days
     */
    private function convert_to_days($time_value, $time_unit) {
        switch ($time_unit) {
            case 'hours':
                return $time_value / 24;
            case 'days':
                return $time_value;
            case 'weeks':
                return $time_value * 7;
            case 'months':
                return $time_value * 30;
            default:
                return $time_value;
        }
    }
    
    /**
     * Get users who need reminders for specific post type
     */
    private function get_users_for_post_type_reminder($post_type, $threshold_date, $notification_id) {
        global $wpdb;
        
        // Get all users with submissions for this post type
        $users_with_submissions = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT user_id, meta_value as last_submission
            FROM {$wpdb->usermeta} 
            WHERE meta_key = %s
            AND meta_value < %s
        ", 'voxel_toolkit_last_submission_' . $post_type, $threshold_date));
        
        $eligible_users = array();
        
        foreach ($users_with_submissions as $user_data) {
            $user = get_user_by('id', $user_data->user_id);
            if (!$user) {
                continue;
            }
            
            // Check if user has already received this specific reminder recently
            $last_reminder_key = 'voxel_toolkit_last_reminder_' . $post_type . '_' . $notification_id;
            $last_reminder = get_user_meta($user->ID, $last_reminder_key, true);
            
            // Skip if already sent this reminder in last 24 hours
            if ($last_reminder && strtotime($last_reminder) > strtotime('-1 day')) {
                continue;
            }
            
            $user->last_submission = $user_data->last_submission;
            $eligible_users[] = $user;
        }
        
        return $eligible_users;
    }
    
    /**
     * Send reminder email to user
     */
    private function send_reminder_email($user, $notification, $post_type, $notification_id, $time_value, $time_unit) {
        $settings = $this->get_settings();
        
        // Get user submission stats
        $stats = $this->get_user_submission_stats($user->ID);
        $post_type_obj = get_post_type_object($post_type);
        $post_type_label = $post_type_obj ? $post_type_obj->label : $post_type;
        
        // Prepare email content
        $subject = isset($notification['subject']) ? $notification['subject'] : sprintf(__('Time to submit a new %s!', 'voxel-toolkit'), $post_type_label);
        $message = isset($notification['message']) ? $notification['message'] : $this->get_default_message($post_type, $time_value, $time_unit);
        
        // Calculate days since last submission for this post type
        $last_submission = get_user_meta($user->ID, 'voxel_toolkit_last_submission_' . $post_type, true);
        $days_since_last = $last_submission ? $this->get_days_since($last_submission) : null;
        
        // Replace placeholders
        $placeholders = array(
            '{user_name}' => $user->display_name,
            '{user_email}' => $user->user_email,
            '{days_since_last}' => $days_since_last ? $days_since_last : __('Never', 'voxel-toolkit'),
            '{time_value}' => $time_value,
            '{time_unit}' => $time_unit,
            '{post_type}' => $post_type_label,
            '{total_submissions}' => $stats['total'],
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => home_url(),
        );
        
        // Add post type specific placeholders
        foreach ($stats['by_post_type'] as $pt => $count) {
            $placeholders['{submissions_' . $pt . '}'] = $count;
        }
        
        $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
        $message = str_replace(array_keys($placeholders), array_values($placeholders), $message);
        
        // Send email
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($user->user_email, $subject, wpautop($message), $headers);
        
        if ($sent) {
            // Record that reminder was sent
            $reminder_key = 'voxel_toolkit_last_reminder_' . $post_type . '_' . $notification_id;
            update_user_meta($user->ID, $reminder_key, current_time('mysql'));
            
            // Log for admin
            error_log("Voxel Toolkit: Submission reminder sent to {$user->user_email} ({$post_type}: {$time_value} {$time_unit})");
        }
    }
    
    /**
     * Get user submission statistics
     */
    private function get_user_submission_stats($user_id) {
        $settings = $this->get_settings();
        $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array();
        
        $stats = array(
            'total' => intval(get_user_meta($user_id, 'voxel_toolkit_total_submissions', true)),
            'by_post_type' => array()
        );
        
        foreach ($enabled_post_types as $post_type) {
            $count = get_user_meta($user_id, 'voxel_toolkit_submissions_' . $post_type, true);
            $stats['by_post_type'][$post_type] = $count ? intval($count) : 0;
        }
        
        return $stats;
    }
    
    /**
     * Get default reminder message
     */
    private function get_default_message($post_type = '', $time_value = 7, $time_unit = 'days') {
        $post_type_obj = get_post_type_object($post_type);
        $post_type_label = $post_type_obj ? $post_type_obj->label : $post_type;
        
        return sprintf(__("Hi {user_name},\n\nIt's been {time_value} {time_unit} since your last %s submission on {site_name}. We'd love to see more content from you!\n\nYour submission stats:\n- Total submissions: {total_submissions}\n- %s submissions: {submissions_%s}\n\nVisit {site_url} to submit a new %s.\n\nThanks for being part of our community!", 'voxel-toolkit'), 
            strtolower($post_type_label), 
            $post_type_label, 
            $post_type, 
            strtolower($post_type_label)
        );
    }
    
    /**
     * Create meta fields for enabled post types
     */
    public function maybe_create_meta_fields() {
        $settings = $this->get_settings();
        $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array();
        
        // This will be handled automatically by WordPress when we use update_user_meta
        // No need to create fields manually
    }
    
    /**
     * Get submission reminder settings
     */
    private function get_settings() {
        $voxel_toolkit_options = get_option('voxel_toolkit_options', array());
        return isset($voxel_toolkit_options['submission_reminder']) ? $voxel_toolkit_options['submission_reminder'] : array();
    }
    
    /**
     * Get available post types from Voxel configuration
     */
    public function get_available_post_types() {
        // First try to get post types from Voxel configuration
        $voxel_post_types = get_option('voxel:post_types', array());
        
        // Handle different data formats (JSON string, array, etc.)
        if (is_string($voxel_post_types)) {
            // Try JSON decode first (Voxel uses JSON)
            $decoded = json_decode($voxel_post_types, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $voxel_post_types = $decoded;
            } else {
                // Fallback to unserialize if not JSON
                $voxel_post_types = maybe_unserialize($voxel_post_types);
            }
        }
        
        // Ensure we have an array
        if (!is_array($voxel_post_types)) {
            $voxel_post_types = array();
        }
        
        $available = array();
        
        if (!empty($voxel_post_types)) {
            // Use Voxel configured post types
            foreach ($voxel_post_types as $post_type_key => $post_type_config) {
                // Skip 'page' post type
                if ($post_type_key === 'page') {
                    continue;
                }
                
                if (isset($post_type_config['settings']['singular']) && isset($post_type_config['settings']['plural'])) {
                    $available[$post_type_key] = $post_type_config['settings']['plural'];
                } else {
                    // Fallback to getting from WordPress if label is missing
                    $post_type_obj = get_post_type_object($post_type_key);
                    if ($post_type_obj) {
                        $available[$post_type_key] = $post_type_obj->label;
                    }
                }
            }
        } else {
            // Fallback to WordPress post types if Voxel config is not available
            $post_types = get_post_types(array(), 'objects');
            
            foreach ($post_types as $post_type) {
                // Skip certain post types that shouldn't be tracked
                if (in_array($post_type->name, array('attachment', 'nav_menu_item', 'wp_block', 'revision', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_navigation', 'page'))) {
                    continue;
                }
                
                // Skip built-in WordPress core types that don't make sense to track
                if (strpos($post_type->name, 'acf-') === 0) { // Skip ACF field groups
                    continue;
                }
                
                $available[$post_type->name] = $post_type->label;
            }
        }
        
        // Ensure common WordPress post types are included (only 'post', not 'page')
        $wp_post_types = array('post');
        foreach ($wp_post_types as $wp_type) {
            if (!isset($available[$wp_type])) {
                $post_type_obj = get_post_type_object($wp_type);
                if ($post_type_obj) {
                    $available[$wp_type] = $post_type_obj->label;
                }
            }
        }
        
        return $available;
    }
    
    /**
     * Get user submission data for admin display
     */
    public function get_user_submission_data($user_id) {
        $stats = $this->get_user_submission_stats($user_id);
        $last_submission = get_user_meta($user_id, 'voxel_toolkit_last_submission_any', true);
        
        return array(
            'stats' => $stats,
            'last_submission' => $last_submission,
            'days_since_last' => $last_submission ? $this->get_days_since($last_submission) : null
        );
    }
    
    /**
     * Calculate days since date
     */
    private function get_days_since($date) {
        $now = new DateTime();
        $submission_date = new DateTime($date);
        $diff = $now->diff($submission_date);
        return $diff->days;
    }
    
    
    /**
     * Sync existing posts to populate submission tracking data
     */
    public function sync_existing_posts() {
        $settings = $this->get_settings();
        $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array();
        
        if (empty($enabled_post_types)) {
            return array(
                'success' => false,
                'message' => __('No post types are enabled for tracking.', 'voxel-toolkit')
            );
        }
        
        global $wpdb;
        
        // Get all published posts for enabled post types
        $post_types_placeholder = implode(',', array_fill(0, count($enabled_post_types), '%s'));
        $query = $wpdb->prepare("
            SELECT ID, post_author, post_type, post_date 
            FROM {$wpdb->posts} 
            WHERE post_status = 'publish' 
            AND post_type IN ($post_types_placeholder)
            ORDER BY post_author, post_type, post_date ASC
        ", $enabled_post_types);
        
        $posts = $wpdb->get_results($query);
        
        if (empty($posts)) {
            return array(
                'success' => false,
                'message' => __('No published posts found for the selected post types.', 'voxel-toolkit')
            );
        }
        
        // Clear existing submission data first
        $this->clear_all_submission_data();
        
        // Group posts by user and post type
        $user_submissions = array();
        $user_last_submissions = array();
        
        foreach ($posts as $post) {
            $user_id = $post->post_author;
            $post_type = $post->post_type;
            
            // Count submissions by user and post type
            if (!isset($user_submissions[$user_id])) {
                $user_submissions[$user_id] = array();
            }
            if (!isset($user_submissions[$user_id][$post_type])) {
                $user_submissions[$user_id][$post_type] = 0;
            }
            $user_submissions[$user_id][$post_type]++;
            
            // Track last submission date for each user and post type
            if (!isset($user_last_submissions[$user_id])) {
                $user_last_submissions[$user_id] = array();
            }
            if (!isset($user_last_submissions[$user_id][$post_type]) || 
                strtotime($post->post_date) > strtotime($user_last_submissions[$user_id][$post_type])) {
                $user_last_submissions[$user_id][$post_type] = $post->post_date;
            }
        }
        
        // Update user meta with calculated data
        $synced_users = 0;
        $total_posts = 0;
        
        foreach ($user_submissions as $user_id => $post_types_data) {
            $user_total = 0;
            $user_last_any = null;
            
            foreach ($post_types_data as $post_type => $count) {
                // Update post type specific count
                update_user_meta($user_id, 'voxel_toolkit_submissions_' . $post_type, $count);
                $user_total += $count;
                $total_posts += $count;
                
                // Update last submission date for this post type
                if (isset($user_last_submissions[$user_id][$post_type])) {
                    $last_date = $user_last_submissions[$user_id][$post_type];
                    update_user_meta($user_id, 'voxel_toolkit_last_submission_' . $post_type, $last_date);
                    
                    // Track the most recent submission across all post types
                    if (!$user_last_any || strtotime($last_date) > strtotime($user_last_any)) {
                        $user_last_any = $last_date;
                    }
                }
            }
            
            // Update total submissions for this user
            update_user_meta($user_id, 'voxel_toolkit_total_submissions', $user_total);
            
            // Update last submission date (any post type)
            if ($user_last_any) {
                update_user_meta($user_id, 'voxel_toolkit_last_submission_any', $user_last_any);
            }
            
            $synced_users++;
        }
        
        return array(
            'success' => true,
            'message' => sprintf(
                __('Successfully synced %d posts from %d users across %s.', 'voxel-toolkit'),
                $total_posts,
                $synced_users,
                implode(', ', $enabled_post_types)
            ),
            'stats' => array(
                'posts' => $total_posts,
                'users' => $synced_users,
                'post_types' => $enabled_post_types
            )
        );
    }
    
    /**
     * Clear all submission tracking data
     */
    private function clear_all_submission_data() {
        global $wpdb;
        
        $wpdb->query("
            DELETE FROM {$wpdb->usermeta} 
            WHERE meta_key LIKE 'voxel_toolkit_%submission%'
        ");
    }
    
    /**
     * Get sync statistics for display
     */
    public function get_sync_stats() {
        $settings = $this->get_settings();
        $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array();
        
        if (empty($enabled_post_types)) {
            return null;
        }
        
        global $wpdb;
        
        // Count published posts by type
        $post_types_placeholder = implode(',', array_fill(0, count($enabled_post_types), '%s'));
        $query = $wpdb->prepare("
            SELECT post_type, COUNT(*) as count 
            FROM {$wpdb->posts} 
            WHERE post_status = 'publish' 
            AND post_type IN ($post_types_placeholder)
            GROUP BY post_type
        ", $enabled_post_types);
        
        $results = $wpdb->get_results($query);
        
        $stats = array();
        $total = 0;
        foreach ($results as $row) {
            $stats[$row->post_type] = $row->count;
            $total += $row->count;
        }
        
        return array(
            'total' => $total,
            'by_type' => $stats
        );
    }
}