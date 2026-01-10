<?php
/**
 * Voxel Toolkit - Image Optimization
 *
 * High-performance client-side image optimization with watermark support,
 * WebP conversion, resizing, and SEO metadata.
 *
 * @package Voxel_Toolkit
 * @version 5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Image_Optimization {

    private static $instance = null;

    /**
     * Default settings (v5.0)
     */
    private $defaults = array(
        'enabled' => true,
        'max_file_size' => 10,
        'max_width' => 1600,
        'max_height' => 1600,
        'output_quality' => 80,
        'optimization_mode' => 'all_webp',
        'rename_format' => 'post_title',
        'set_alt_text' => true,
        'alt_text_format' => 'title_counter_date',
        'disable_wp_scaling' => true,
        'wm_type' => 'none',
        'wm_text' => '',
        'wm_image_url' => '',
        'wm_pos' => 'bottom-right',
        'wm_scale' => 15,
    );

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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enqueue scripts on frontend and admin
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 1);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'), 1);

        // Auto-set alt text on attachment
        add_action('add_attachment', array($this, 'auto_set_image_metadata'));

        // Manage WordPress scaling
        add_filter('big_image_size_threshold', array($this, 'manage_wp_scaling'), 10, 1);
    }

    /**
     * Get settings
     */
    public function get_settings() {
        $toolkit_settings = get_option('voxel_toolkit_options', array());
        $settings = isset($toolkit_settings['image_optimization']) ? $toolkit_settings['image_optimization'] : array();
        return wp_parse_args($settings, $this->defaults);
    }

    /**
     * Manage WordPress scaling threshold
     */
    public function manage_wp_scaling($threshold) {
        $settings = $this->get_settings();
        if (!empty($settings['enabled']) && !empty($settings['disable_wp_scaling'])) {
            return false;
        }
        return $threshold;
    }

    /**
     * Auto-set alt text and title on image upload
     */
    public function auto_set_image_metadata($attachment_id) {
        $settings = $this->get_settings();

        if (empty($settings['set_alt_text'])) {
            return;
        }

        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return;
        }

        $parent_id = $attachment->post_parent;

        // International date format
        $date = date('F j, Y');

        if (!$parent_id) {
            // Use filename as base title if no parent
            $filename = pathinfo(get_attached_file($attachment_id), PATHINFO_FILENAME);
            $base_title = ucwords(str_replace(array('-', '_'), ' ', $filename));
        } else {
            $base_title = get_the_title($parent_id);
        }

        // Count existing attachments for counter
        $attachments = get_posts(array(
            'post_parent' => $parent_id,
            'post_type' => 'attachment',
            'numberposts' => -1,
            'post_status' => 'any',
        ));
        $counter = str_pad(count($attachments), 2, '0', STR_PAD_LEFT);

        // Generate alt text based on format
        $format = isset($settings['alt_text_format']) ? $settings['alt_text_format'] : 'title_counter_date';
        switch ($format) {
            case 'title_only':
                $alt_text = $base_title;
                break;
            case 'title_counter':
                $alt_text = $base_title . ' - Image ' . $counter;
                break;
            case 'title_date':
                $alt_text = $base_title . ' (' . $date . ')';
                break;
            case 'title_counter_date':
            default:
                $alt_text = $base_title . ' - Image ' . $counter . ' (' . $date . ')';
                break;
        }

        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));

        // Update post title if using post_title rename format
        if ($settings['rename_format'] === 'post_title') {
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_title' => $base_title . ' ' . $counter,
            ));
        }
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        $settings = $this->get_settings();

        if (empty($settings['enabled'])) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'voxel-toolkit-image-optimization',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/image-optimization.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        // Enqueue script
        wp_enqueue_script(
            'voxel-toolkit-image-optimization',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/image-optimization.js',
            array(),
            VOXEL_TOOLKIT_VERSION,
            false // Load in head for early interception
        );

        // Localize script with settings
        wp_localize_script('voxel-toolkit-image-optimization', 'VT_ImageOptimization', array(
            'maxFileSizeMB' => intval($settings['max_file_size']),
            'maxWidth' => intval($settings['max_width']),
            'maxHeight' => intval($settings['max_height']),
            'outputQuality' => intval($settings['output_quality']) / 100,
            'optimizationMode' => sanitize_text_field($settings['optimization_mode']),
            'renameFormat' => sanitize_text_field($settings['rename_format']),
            'wmType' => sanitize_text_field($settings['wm_type']),
            'wmText' => sanitize_text_field($settings['wm_text']),
            'wmImg' => esc_url($settings['wm_image_url']),
            'wmPos' => sanitize_text_field($settings['wm_pos']),
            'wmScale' => intval($settings['wm_scale']),
        ));
    }

    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input) {
        $sanitized = array();

        // Boolean settings
        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['set_alt_text'] = !empty($input['set_alt_text']);
        $sanitized['disable_wp_scaling'] = !empty($input['disable_wp_scaling']);

        // Integer settings with bounds
        $sanitized['max_file_size'] = isset($input['max_file_size']) ? max(1, min(100, intval($input['max_file_size']))) : 10;
        $sanitized['max_width'] = isset($input['max_width']) ? max(100, min(10000, intval($input['max_width']))) : 1600;
        $sanitized['max_height'] = isset($input['max_height']) ? max(100, min(10000, intval($input['max_height']))) : 1600;
        $sanitized['output_quality'] = isset($input['output_quality']) ? max(1, min(100, intval($input['output_quality']))) : 80;
        $sanitized['wm_scale'] = isset($input['wm_scale']) ? max(5, min(80, intval($input['wm_scale']))) : 15;

        // Optimization mode
        $allowed_modes = array('all_webp', 'only_jpg', 'only_png', 'both_to_webp', 'originals_only');
        $sanitized['optimization_mode'] = isset($input['optimization_mode']) && in_array($input['optimization_mode'], $allowed_modes)
            ? $input['optimization_mode']
            : 'all_webp';

        // Rename format
        $allowed_rename_formats = array('post_title', 'original');
        $sanitized['rename_format'] = isset($input['rename_format']) && in_array($input['rename_format'], $allowed_rename_formats)
            ? $input['rename_format']
            : 'post_title';

        // Alt text format
        $allowed_alt_formats = array('title_only', 'title_counter', 'title_date', 'title_counter_date');
        $sanitized['alt_text_format'] = isset($input['alt_text_format']) && in_array($input['alt_text_format'], $allowed_alt_formats)
            ? $input['alt_text_format']
            : 'title_counter_date';

        // Watermark type
        $allowed_wm_types = array('none', 'text', 'image');
        $sanitized['wm_type'] = isset($input['wm_type']) && in_array($input['wm_type'], $allowed_wm_types)
            ? $input['wm_type']
            : 'none';

        // Watermark position
        $allowed_wm_positions = array('center', 'top-left', 'top-right', 'bottom-left', 'bottom-right');
        $sanitized['wm_pos'] = isset($input['wm_pos']) && in_array($input['wm_pos'], $allowed_wm_positions)
            ? $input['wm_pos']
            : 'bottom-right';

        // Text fields
        $sanitized['wm_text'] = isset($input['wm_text']) ? sanitize_text_field($input['wm_text']) : '';

        // Watermark image URL - validate PNG extension
        $sanitized['wm_image_url'] = '';
        if (!empty($input['wm_image_url'])) {
            $url = esc_url_raw($input['wm_image_url']);
            $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
            if ($ext === 'png') {
                $sanitized['wm_image_url'] = $url;
            }
        }

        return $sanitized;
    }
}
