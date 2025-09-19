<?php
/**
 * Media Paste functionality
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Media_Paste {
    
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
        // Check if media paste is enabled
        if (!$this->is_enabled()) {
            return;
        }
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers for image upload
        add_action('wp_ajax_voxel_toolkit_paste_image', array($this, 'handle_paste_upload'));
        
        // Elementor specific hooks - DISABLED temporarily, will revisit later
        // add_action('elementor/editor/after_enqueue_scripts', array($this, 'enqueue_elementor_scripts'));
    }
    
    /**
     * Check if media paste is enabled
     */
    private function is_enabled() {
        $settings = Voxel_Toolkit_Settings::instance();
        $media_paste_settings = $settings->get_function_settings('media_paste', array());
        
        return isset($media_paste_settings['enabled']) && $media_paste_settings['enabled'];
    }
    
    /**
     * Check if current user can paste images
     */
    private function can_user_paste() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $settings = Voxel_Toolkit_Settings::instance();
        $media_paste_settings = $settings->get_function_settings('media_paste', array());
        $allowed_roles = isset($media_paste_settings['allowed_roles']) ? $media_paste_settings['allowed_roles'] : array('administrator', 'editor');
        
        // If "all_roles" is selected, allow everyone
        if (in_array('all_roles', $allowed_roles)) {
            return true;
        }
        
        // Check if user has any of the allowed roles
        $user = wp_get_current_user();
        $user_roles = $user->roles;
        
        foreach ($user_roles as $role) {
            if (in_array($role, $allowed_roles)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on specific admin pages
        $allowed_pages = array(
            'post.php',
            'post-new.php',
            'upload.php',
            'media-upload.php',
            'admin.php'
        );
        
        if (!in_array($hook, $allowed_pages)) {
            return;
        }
        
        // Check if user can paste
        if (!$this->can_user_paste()) {
            return;
        }
        
        wp_enqueue_script(
            'voxel-toolkit-media-paste',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/media-paste.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );
        
        wp_localize_script('voxel-toolkit-media-paste', 'voxelMediaPaste', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('voxel_toolkit_paste_image'),
            'uploadingText' => __('Uploading pasted image...', 'voxel-toolkit'),
            'errorText' => __('Error uploading image. Please try again.', 'voxel-toolkit'),
            'successText' => __('Image uploaded successfully!', 'voxel-toolkit'),
            'maxFileSize' => wp_max_upload_size(),
            'allowedTypes' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp')
        ));
        
        wp_enqueue_style(
            'voxel-toolkit-media-paste',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/media-paste.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
    }
    
    /**
     * Enqueue Elementor scripts
     */
    public function enqueue_elementor_scripts() {
        // Check if user can paste
        if (!$this->can_user_paste()) {
            return;
        }
        
        wp_enqueue_script(
            'voxel-toolkit-media-paste-elementor',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/media-paste-elementor.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );
        
        wp_localize_script('voxel-toolkit-media-paste-elementor', 'voxelMediaPaste', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('voxel_toolkit_paste_image'),
            'uploadingText' => __('Uploading pasted image...', 'voxel-toolkit'),
            'errorText' => __('Error uploading image. Please try again.', 'voxel-toolkit'),
            'successText' => __('Image uploaded successfully!', 'voxel-toolkit'),
            'maxFileSize' => wp_max_upload_size(),
            'allowedTypes' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp')
        ));
    }
    
    /**
     * Handle pasted image upload
     */
    public function handle_paste_upload() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'voxel_toolkit_paste_image')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check if user can paste
        if (!$this->can_user_paste()) {
            wp_send_json_error(array('message' => 'You do not have permission to upload images'));
        }
        
        // Check if image data is provided
        if (!isset($_POST['image_data']) || empty($_POST['image_data'])) {
            wp_send_json_error(array('message' => 'No image data provided'));
        }
        
        $image_data = $_POST['image_data'];
        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : 'pasted-image.png';
        
        // Validate and process base64 image data
        $upload_result = $this->process_base64_image($image_data, $filename);
        
        if (is_wp_error($upload_result)) {
            wp_send_json_error(array('message' => $upload_result->get_error_message()));
        }
        
        wp_send_json_success($upload_result);
    }
    
    /**
     * Process base64 image data and create WordPress attachment
     */
    private function process_base64_image($base64_data, $filename) {
        // Remove data URL prefix if present
        $base64_data = preg_replace('/^data:image\/[^;]+;base64,/', '', $base64_data);
        
        // Decode base64 data
        $image_data = base64_decode($base64_data);
        
        if ($image_data === false) {
            return new WP_Error('invalid_image', 'Invalid image data');
        }
        
        // Check file size
        $file_size = strlen($image_data);
        $max_size = wp_max_upload_size();
        
        if ($file_size > $max_size) {
            return new WP_Error('file_too_large', sprintf('File size exceeds maximum allowed size of %s', size_format($max_size)));
        }
        
        // Create temporary file
        $upload_dir = wp_upload_dir();
        $temp_file = wp_tempnam($filename, $upload_dir['tmp']);
        
        if (!$temp_file) {
            return new WP_Error('temp_file_failed', 'Failed to create temporary file');
        }
        
        // Write image data to temporary file
        if (file_put_contents($temp_file, $image_data) === false) {
            return new WP_Error('write_failed', 'Failed to write image data');
        }
        
        // Validate image
        $image_info = getimagesize($temp_file);
        if (!$image_info) {
            unlink($temp_file);
            return new WP_Error('invalid_image', 'Invalid image file');
        }
        
        // Check allowed image types
        $allowed_types = array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP);
        if (!in_array($image_info[2], $allowed_types)) {
            unlink($temp_file);
            return new WP_Error('invalid_type', 'Image type not allowed');
        }
        
        // Generate unique filename
        $extension = image_type_to_extension($image_info[2]);
        $unique_filename = wp_unique_filename($upload_dir['path'], 'pasted-image-' . time() . $extension);
        $target_file = $upload_dir['path'] . '/' . $unique_filename;
        
        // Move temp file to uploads directory
        if (!rename($temp_file, $target_file)) {
            unlink($temp_file);
            return new WP_Error('move_failed', 'Failed to move uploaded file');
        }
        
        // Create WordPress attachment
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $unique_filename,
            'post_mime_type' => $image_info['mime'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $unique_filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $target_file);
        
        if (is_wp_error($attach_id)) {
            unlink($target_file);
            return $attach_id;
        }
        
        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $target_file);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        // Return attachment data
        return array(
            'id' => $attach_id,
            'url' => wp_get_attachment_url($attach_id),
            'title' => get_the_title($attach_id),
            'filename' => $unique_filename,
            'filesize' => size_format(filesize($target_file)),
            'dimensions' => $image_info[0] . ' × ' . $image_info[1]
        );
    }
}