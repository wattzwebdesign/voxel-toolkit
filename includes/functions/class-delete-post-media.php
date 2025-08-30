<?php
/**
 * Delete Post Media Function
 * 
 * Automatically deletes all media attachments when a post is deleted
 * Includes double confirmation for security
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Delete_Post_Media {
    
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = Voxel_Toolkit_Settings::instance();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add confirmation dialog to delete post actions
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Hook into post deletion
        add_action('before_delete_post', array($this, 'delete_post_attachments'));
        
        // Add AJAX handler for confirmation
        add_action('wp_ajax_voxel_toolkit_confirm_post_delete', array($this, 'ajax_confirm_post_delete'));
        
        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }
    
    /**
     * Enqueue admin scripts for confirmation dialog
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on post edit/list pages
        if (!in_array($hook, array('post.php', 'edit.php'))) {
            return;
        }
        
        // Check if current post type is enabled
        $screen = get_current_screen();
        if (!$screen || !$this->is_post_type_enabled($screen->post_type)) {
            return;
        }
        
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        wp_add_inline_script('jquery', $this->get_confirmation_script());
        wp_add_inline_style('wp-admin', $this->get_confirmation_styles());
    }
    
    /**
     * Get JavaScript for confirmation dialog
     */
    private function get_confirmation_script() {
        $nonce = wp_create_nonce('voxel_toolkit_delete_confirm');
        
        return "
        jQuery(document).ready(function($) {
            // Intercept delete post actions
            $('body').on('click', '.submitdelete, .delete a', function(e) {
                var link = $(this);
                var href = link.attr('href');
                
                // Only intercept if it's a delete action
                if (!href || href.indexOf('action=delete') === -1) {
                    return true;
                }
                
                e.preventDefault();
                
                // Create confirmation dialog
                var dialog = $('<div id=\"voxel-delete-confirmation\" title=\"Delete Post with Media\"></div>');
                dialog.html(`
                    <div style=\"margin: 20px 0;\">
                        <div style=\"display: flex; align-items: center; margin-bottom: 15px;\">
                            <span class=\"dashicons dashicons-warning\" style=\"color: #d63638; font-size: 24px; margin-right: 10px;\"></span>
                            <strong style=\"color: #d63638;\">Warning: This will delete ALL media attachments!</strong>
                        </div>
                        <p>This post and <strong>all its attached media files</strong> (images, documents, etc.) will be permanently deleted.</p>
                        <p><strong>This action cannot be undone.</strong></p>
                        <p>Are you absolutely sure you want to continue?</p>
                        
                        <div style=\"margin: 20px 0; padding: 10px; background: #fff2cd; border-left: 4px solid #dba617;\">
                            <label style=\"display: flex; align-items: center;\">
                                <input type=\"checkbox\" id=\"delete-media-confirm\" style=\"margin-right: 8px;\">
                                <span>I understand that all media will be permanently deleted</span>
                            </label>
                        </div>
                    </div>
                `);
                
                dialog.dialog({
                    modal: true,
                    width: 500,
                    resizable: false,
                    close: function() {
                        dialog.remove();
                    },
                    buttons: {
                        'Cancel': function() {
                            $(this).dialog('close');
                        },
                        'Delete Post & Media': {
                            text: 'Delete Post & Media',
                            class: 'button-primary',
                            style: 'background-color: #d63638; border-color: #d63638;',
                            click: function() {
                                if (!$('#delete-media-confirm').is(':checked')) {
                                    alert('Please confirm that you understand all media will be deleted.');
                                    return;
                                }
                                
                                $(this).dialog('close');
                                window.location.href = href;
                            }
                        }
                    }
                });
                
                // Style the delete button
                $('.ui-dialog-buttonset button:last-child').css({
                    'background-color': '#d63638',
                    'border-color': '#d63638',
                    'color': '#fff'
                });
                
                return false;
            });
        });
        ";
    }
    
    /**
     * Get CSS styles for confirmation dialog
     */
    private function get_confirmation_styles() {
        return "
        #voxel-delete-confirmation .dashicons-warning {
            color: #d63638;
        }
        
        .ui-dialog .ui-dialog-titlebar {
            background: #d63638;
            color: white;
            border: none;
        }
        
        .ui-dialog .ui-dialog-titlebar-close {
            color: white;
        }
        
        .ui-dialog .ui-dialog-titlebar-close:hover {
            background: rgba(255,255,255,0.2);
        }
        ";
    }
    
    /**
     * Delete all media associated with a post when it's deleted
     * 
     * @param int $post_id Post ID being deleted
     */
    public function delete_post_attachments($post_id) {
        // Check if this post type is enabled for media deletion
        $post_type = get_post_type($post_id);
        if (!$this->is_post_type_enabled($post_type)) {
            return;
        }
        
        $media_ids = $this->find_all_post_media($post_id);
        
        if (empty($media_ids)) {
            return;
        }
        
        $deleted_count = 0;
        $deleted_files = array();
        
        foreach ($media_ids as $media_id) {
            // Get file path before deletion for logging
            $file_path = get_attached_file($media_id);
            $file_name = basename($file_path);
            
            // Force delete the attachment (bypass trash)
            $deleted = wp_delete_attachment($media_id, true);
            
            if ($deleted) {
                $deleted_count++;
                $deleted_files[] = $file_name;
            }
        }
        
        // Log the deletion for debugging
        if ($deleted_count > 0) {
            error_log("Voxel Toolkit: Deleted {$deleted_count} media files for post {$post_id}: " . implode(', ', $deleted_files));
        }
    }
    
    /**
     * Find all media associated with a post
     * 
     * @param int $post_id Post ID
     * @return array Array of media attachment IDs
     */
    private function find_all_post_media($post_id) {
        $media_ids = array();
        
        // 1. Get featured image
        $featured_image_id = get_post_thumbnail_id($post_id);
        if ($featured_image_id) {
            $media_ids[] = $featured_image_id;
        }
        
        // 2. Get directly attached media (post_parent relationship)
        $attached_media = get_attached_media('', $post_id);
        foreach ($attached_media as $attachment) {
            $media_ids[] = $attachment->ID;
        }
        
        // 3. Get post content and extract image IDs from it
        $post = get_post($post_id);
        if ($post && !empty($post->post_content)) {
            $content_media_ids = $this->extract_media_from_content($post->post_content);
            $media_ids = array_merge($media_ids, $content_media_ids);
        }
        
        // 4. Get media from post meta (custom fields, ACF, etc.)
        $meta_media_ids = $this->extract_media_from_meta($post_id);
        $media_ids = array_merge($media_ids, $meta_media_ids);
        
        // 5. For Voxel posts, get media from Voxel custom fields
        $voxel_media_ids = $this->extract_voxel_media($post_id);
        $media_ids = array_merge($media_ids, $voxel_media_ids);
        
        // Remove duplicates and ensure all are integers
        $media_ids = array_unique(array_filter(array_map('intval', $media_ids)));
        
        return $media_ids;
    }
    
    /**
     * Extract media IDs from post content
     * 
     * @param string $content Post content
     * @return array Array of media IDs
     */
    private function extract_media_from_content($content) {
        $media_ids = array();
        
        // Look for WordPress gallery shortcodes
        if (preg_match_all('/\[gallery[^\]]*ids=["\']([^"\']+)["\'][^\]]*\]/', $content, $matches)) {
            foreach ($matches[1] as $ids_string) {
                $ids = explode(',', $ids_string);
                $media_ids = array_merge($media_ids, array_map('trim', $ids));
            }
        }
        
        // Look for image shortcodes
        if (preg_match_all('/\[image[^\]]*id=["\']?(\d+)["\']?[^\]]*\]/', $content, $matches)) {
            $media_ids = array_merge($media_ids, $matches[1]);
        }
        
        // Look for attachment IDs in wp-image classes
        if (preg_match_all('/wp-image-(\d+)/', $content, $matches)) {
            $media_ids = array_merge($media_ids, $matches[1]);
        }
        
        // Look for WordPress attachment URLs and convert to IDs
        if (preg_match_all('/wp-content\/uploads\/[^\s"\']+\.(jpg|jpeg|png|gif|pdf|doc|docx|mp4|mp3|zip|svg)/i', $content, $matches)) {
            foreach ($matches[0] as $url) {
                $attachment_id = attachment_url_to_postid(home_url() . '/' . $url);
                if ($attachment_id) {
                    $media_ids[] = $attachment_id;
                }
            }
        }
        
        return array_map('intval', $media_ids);
    }
    
    /**
     * Extract media IDs from post meta
     * 
     * @param int $post_id Post ID
     * @return array Array of media IDs
     */
    private function extract_media_from_meta($post_id) {
        $media_ids = array();
        
        // Get all post meta
        $all_meta = get_post_meta($post_id);
        
        foreach ($all_meta as $meta_key => $meta_values) {
            foreach ($meta_values as $meta_value) {
                // Skip serialized data for now, handle it separately
                if (is_serialized($meta_value)) {
                    $unserialized = maybe_unserialize($meta_value);
                    $meta_media_ids = $this->extract_media_from_data($unserialized);
                    $media_ids = array_merge($media_ids, $meta_media_ids);
                } else {
                    // Check if meta value is a numeric ID of an attachment
                    if (is_numeric($meta_value) && wp_attachment_is_image($meta_value)) {
                        $media_ids[] = (int) $meta_value;
                    }
                    
                    // Check if meta value contains attachment URLs
                    if (is_string($meta_value) && preg_match_all('/wp-content\/uploads\/[^\s"\']+\.(jpg|jpeg|png|gif|pdf|doc|docx|mp4|mp3|zip|svg)/i', $meta_value, $matches)) {
                        foreach ($matches[0] as $url) {
                            $attachment_id = attachment_url_to_postid(home_url() . '/' . $url);
                            if ($attachment_id) {
                                $media_ids[] = $attachment_id;
                            }
                        }
                    }
                }
            }
        }
        
        return array_map('intval', array_filter($media_ids));
    }
    
    /**
     * Extract media from Voxel-specific fields
     * 
     * @param int $post_id Post ID
     * @return array Array of media IDs
     */
    private function extract_voxel_media($post_id) {
        $media_ids = array();
        
        // Check if Voxel is available
        if (!function_exists('\\Voxel\\Post')) {
            return $media_ids;
        }
        
        try {
            $voxel_post = \Voxel\Post::get($post_id);
            if (!$voxel_post) {
                return $media_ids;
            }
            
            $post_type = $voxel_post->post_type;
            if (!$post_type) {
                return $media_ids;
            }
            
            // Get all fields from the Voxel post type
            $fields = $post_type->get_fields();
            
            foreach ($fields as $field) {
                if (!$field) continue;
                
                // Check different field types that might contain media
                $field_type = $field->get_type();
                
                if (in_array($field_type, array('image', 'file', 'gallery'))) {
                    $field_value = $voxel_post->get_field($field->get_key());
                    
                    if ($field_type === 'gallery' && is_array($field_value)) {
                        foreach ($field_value as $item) {
                            if (isset($item['id']) && is_numeric($item['id'])) {
                                $media_ids[] = (int) $item['id'];
                            }
                        }
                    } elseif (($field_type === 'image' || $field_type === 'file') && is_array($field_value) && isset($field_value['id'])) {
                        $media_ids[] = (int) $field_value['id'];
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log('Voxel Toolkit: Error extracting Voxel media for post ' . $post_id . ': ' . $e->getMessage());
        }
        
        return array_map('intval', array_filter($media_ids));
    }
    
    /**
     * Recursively extract media IDs from arrays and objects
     * 
     * @param mixed $data Data to search
     * @return array Array of media IDs
     */
    private function extract_media_from_data($data) {
        $media_ids = array();
        
        if (is_array($data) || is_object($data)) {
            foreach ((array) $data as $key => $value) {
                if (is_numeric($value) && wp_attachment_is_image($value)) {
                    $media_ids[] = (int) $value;
                } elseif (is_string($value) && preg_match_all('/wp-content\/uploads\/[^\s"\']+\.(jpg|jpeg|png|gif|pdf|doc|docx|mp4|mp3|zip|svg)/i', $value, $matches)) {
                    foreach ($matches[0] as $url) {
                        $attachment_id = attachment_url_to_postid(home_url() . '/' . $url);
                        if ($attachment_id) {
                            $media_ids[] = $attachment_id;
                        }
                    }
                } elseif (is_array($value) || is_object($value)) {
                    $nested_media = $this->extract_media_from_data($value);
                    $media_ids = array_merge($media_ids, $nested_media);
                }
            }
        }
        
        return array_map('intval', array_filter($media_ids));
    }
    
    /**
     * Check if post type is enabled for media deletion
     * 
     * @param string $post_type Post type to check
     * @return bool Whether post type is enabled
     */
    private function is_post_type_enabled($post_type) {
        $function_settings = $this->settings->get_function_settings('delete_post_media', array(
            'enabled' => false,
            'post_types' => array()
        ));
        
        if (!$function_settings['enabled']) {
            return false;
        }
        
        // Ensure post_types is an array before checking
        $post_types = isset($function_settings['post_types']) && is_array($function_settings['post_types']) 
            ? $function_settings['post_types'] 
            : array();
        
        return in_array($post_type, $post_types);
    }
    
    /**
     * Handle settings updates
     * 
     * @param array $new_settings New settings
     * @param array $old_settings Old settings
     */
    public function on_settings_updated($new_settings, $old_settings) {
        // Reinitialize hooks
        $this->remove_hooks();
        $this->init_hooks();
    }
    
    /**
     * Remove hooks (for cleanup)
     */
    private function remove_hooks() {
        remove_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        remove_action('before_delete_post', array($this, 'delete_post_attachments'));
        remove_action('wp_ajax_voxel_toolkit_confirm_post_delete', array($this, 'ajax_confirm_post_delete'));
    }
    
    /**
     * Deinitialize (cleanup when function is disabled)
     */
    public function deinit() {
        $this->remove_hooks();
        remove_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10);
    }
}