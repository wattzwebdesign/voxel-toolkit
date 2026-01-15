<?php
/**
 * Voxel Toolkit - Bulk Resize
 *
 * Server-side bulk image optimization for media library.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Bulk_Resize {

    private static $instance = null;

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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_vt_bulk_resize_get_count', array($this, 'ajax_get_count'));
        add_action('wp_ajax_vt_bulk_resize_process', array($this, 'ajax_process_batch'));
        add_action('wp_ajax_vt_bulk_resize_reset', array($this, 'ajax_reset_processed'));

        // Media library column
        add_filter('manage_media_columns', array($this, 'add_media_column'));
        add_action('manage_media_custom_column', array($this, 'render_media_column'), 10, 2);
        add_action('admin_head', array($this, 'media_column_styles'));
    }

    /**
     * Add submenu under Media
     */
    public function add_admin_menu() {
        add_submenu_page(
            'upload.php',
            __('Bulk Resize (VT)', 'voxel-toolkit'),
            __('Bulk Resize (VT)', 'voxel-toolkit'),
            'manage_options',
            'vt-bulk-resize',
            array($this, 'render_page')
        );
    }

    /**
     * Enqueue scripts only on our page
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'media_page_vt-bulk-resize') {
            return;
        }

        wp_enqueue_style(
            'vt-bulk-resize',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/bulk-resize.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        wp_enqueue_script(
            'vt-bulk-resize',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/bulk-resize.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        $settings = Voxel_Toolkit_Image_Optimization::instance()->get_settings();

        wp_localize_script('vt-bulk-resize', 'VT_BulkResize', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_bulk_resize'),
            'max_width' => intval($settings['max_width']),
            'max_height' => intval($settings['max_height']),
            'quality' => intval($settings['output_quality']),
            'strings' => array(
                'processing' => __('Processing...', 'voxel-toolkit'),
                'complete' => __('Complete!', 'voxel-toolkit'),
                'error' => __('Error occurred', 'voxel-toolkit'),
                'stopped' => __('Stopped by user', 'voxel-toolkit'),
                'no_images' => __('No images to process', 'voxel-toolkit'),
            ),
        ));
    }

    /**
     * Render admin page
     */
    public function render_page() {
        $settings = Voxel_Toolkit_Image_Optimization::instance()->get_settings();
        ?>
        <div class="wrap vt-bulk-resize-wrap">
            <h1><?php _e('Bulk Resize (VT)', 'voxel-toolkit'); ?></h1>

            <div class="vt-bulk-resize-container">
                <!-- Settings Info -->
                <div class="vt-bulk-resize-card vt-bulk-resize-info">
                    <div class="vt-bulk-resize-card-header">
                        <span class="dashicons dashicons-info-outline"></span>
                        <?php _e('Current Settings', 'voxel-toolkit'); ?>
                    </div>
                    <div class="vt-bulk-resize-card-body">
                        <p><?php _e('Using settings from Image Optimization function:', 'voxel-toolkit'); ?></p>
                        <div class="vt-bulk-resize-settings-grid">
                            <div class="vt-bulk-resize-setting">
                                <span class="vt-bulk-resize-setting-label"><?php _e('Max Width', 'voxel-toolkit'); ?></span>
                                <span class="vt-bulk-resize-setting-value"><?php echo esc_html($settings['max_width']); ?>px</span>
                            </div>
                            <div class="vt-bulk-resize-setting">
                                <span class="vt-bulk-resize-setting-label"><?php _e('Max Height', 'voxel-toolkit'); ?></span>
                                <span class="vt-bulk-resize-setting-value"><?php echo esc_html($settings['max_height']); ?>px</span>
                            </div>
                            <div class="vt-bulk-resize-setting">
                                <span class="vt-bulk-resize-setting-label"><?php _e('Quality', 'voxel-toolkit'); ?></span>
                                <span class="vt-bulk-resize-setting-value"><?php echo esc_html($settings['output_quality']); ?>%</span>
                            </div>
                            <div class="vt-bulk-resize-setting">
                                <span class="vt-bulk-resize-setting-label"><?php _e('Format Mode', 'voxel-toolkit'); ?></span>
                                <span class="vt-bulk-resize-setting-value"><?php echo esc_html($this->get_mode_label($settings['optimization_mode'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="vt-bulk-resize-card">
                    <div class="vt-bulk-resize-card-header">
                        <span class="dashicons dashicons-filter"></span>
                        <?php _e('Filter Images', 'voxel-toolkit'); ?>
                    </div>
                    <div class="vt-bulk-resize-card-body">
                        <div class="vt-bulk-resize-filters">
                            <label class="vt-bulk-resize-radio">
                                <input type="radio" name="vt_filter" value="not_processed" checked>
                                <span><?php _e('Not Yet Processed', 'voxel-toolkit'); ?></span>
                            </label>
                            <label class="vt-bulk-resize-radio">
                                <input type="radio" name="vt_filter" value="oversized">
                                <span><?php _e('Oversized Only', 'voxel-toolkit'); ?></span>
                            </label>
                            <label class="vt-bulk-resize-radio">
                                <input type="radio" name="vt_filter" value="all">
                                <span><?php _e('All Images', 'voxel-toolkit'); ?></span>
                            </label>
                        </div>
                        <div class="vt-bulk-resize-count">
                            <span class="vt-bulk-resize-count-label"><?php _e('Found:', 'voxel-toolkit'); ?></span>
                            <span class="vt-bulk-resize-count-value" id="vt-image-count">—</span>
                            <span class="vt-bulk-resize-count-label"><?php _e('images', 'voxel-toolkit'); ?></span>
                            <button type="button" class="button button-small" id="vt-refresh-count">
                                <span class="dashicons dashicons-update"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Progress -->
                <div class="vt-bulk-resize-card vt-bulk-resize-progress-card" id="vt-progress-card" style="display: none;">
                    <div class="vt-bulk-resize-card-header">
                        <span class="dashicons dashicons-performance"></span>
                        <?php _e('Progress', 'voxel-toolkit'); ?>
                    </div>
                    <div class="vt-bulk-resize-card-body">
                        <div class="vt-bulk-resize-progress-bar">
                            <div class="vt-bulk-resize-progress-fill" id="vt-progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="vt-bulk-resize-progress-stats">
                            <div class="vt-bulk-resize-stat">
                                <span class="vt-bulk-resize-stat-value" id="vt-progress-percent">0%</span>
                            </div>
                            <div class="vt-bulk-resize-stat">
                                <span class="vt-bulk-resize-stat-label"><?php _e('Current:', 'voxel-toolkit'); ?></span>
                                <span class="vt-bulk-resize-stat-value" id="vt-current-image">—</span>
                            </div>
                            <div class="vt-bulk-resize-stat">
                                <span class="vt-bulk-resize-stat-label"><?php _e('Processed:', 'voxel-toolkit'); ?></span>
                                <span class="vt-bulk-resize-stat-value"><span id="vt-processed-count">0</span> / <span id="vt-total-count">0</span></span>
                            </div>
                            <div class="vt-bulk-resize-stat">
                                <span class="vt-bulk-resize-stat-label"><?php _e('Saved:', 'voxel-toolkit'); ?></span>
                                <span class="vt-bulk-resize-stat-value" id="vt-saved-size">0 B</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="vt-bulk-resize-actions">
                    <button type="button" class="button button-primary button-hero" id="vt-start-btn">
                        <span class="dashicons dashicons-controls-play"></span>
                        <?php _e('Start Processing', 'voxel-toolkit'); ?>
                    </button>
                    <button type="button" class="button button-secondary button-hero" id="vt-stop-btn" style="display: none;">
                        <span class="dashicons dashicons-controls-pause"></span>
                        <?php _e('Stop', 'voxel-toolkit'); ?>
                    </button>
                    <button type="button" class="button button-link" id="vt-reset-btn">
                        <?php _e('Reset processed status', 'voxel-toolkit'); ?>
                    </button>
                </div>

                <!-- Log -->
                <div class="vt-bulk-resize-card">
                    <div class="vt-bulk-resize-card-header">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php _e('Log', 'voxel-toolkit'); ?>
                    </div>
                    <div class="vt-bulk-resize-card-body">
                        <div class="vt-bulk-resize-log" id="vt-log">
                            <div class="vt-bulk-resize-log-empty"><?php _e('Processing log will appear here...', 'voxel-toolkit'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Summary -->
                <div class="vt-bulk-resize-card vt-bulk-resize-summary" id="vt-summary-card" style="display: none;">
                    <div class="vt-bulk-resize-card-header vt-bulk-resize-summary-header">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Processing Complete', 'voxel-toolkit'); ?>
                    </div>
                    <div class="vt-bulk-resize-card-body">
                        <div class="vt-bulk-resize-summary-grid">
                            <div class="vt-bulk-resize-summary-stat">
                                <span class="vt-bulk-resize-summary-value" id="vt-summary-processed">0</span>
                                <span class="vt-bulk-resize-summary-label"><?php _e('Images Processed', 'voxel-toolkit'); ?></span>
                            </div>
                            <div class="vt-bulk-resize-summary-stat">
                                <span class="vt-bulk-resize-summary-value" id="vt-summary-resized">0</span>
                                <span class="vt-bulk-resize-summary-label"><?php _e('Images Resized', 'voxel-toolkit'); ?></span>
                            </div>
                            <div class="vt-bulk-resize-summary-stat">
                                <span class="vt-bulk-resize-summary-value" id="vt-summary-skipped">0</span>
                                <span class="vt-bulk-resize-summary-label"><?php _e('Already Optimal', 'voxel-toolkit'); ?></span>
                            </div>
                            <div class="vt-bulk-resize-summary-stat vt-bulk-resize-summary-highlight">
                                <span class="vt-bulk-resize-summary-value" id="vt-summary-saved">0 B</span>
                                <span class="vt-bulk-resize-summary-label"><?php _e('Total Saved', 'voxel-toolkit'); ?></span>
                            </div>
                            <div class="vt-bulk-resize-summary-stat vt-bulk-resize-summary-highlight">
                                <span class="vt-bulk-resize-summary-value" id="vt-summary-percent">0%</span>
                                <span class="vt-bulk-resize-summary-label"><?php _e('Avg. Reduction', 'voxel-toolkit'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Get image count
     */
    public function ajax_get_count() {
        check_ajax_referer('vt_bulk_resize', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'not_processed';
        $count = $this->get_image_count($filter);

        wp_send_json_success(array('count' => $count));
    }

    /**
     * Get count of images matching filter
     */
    private function get_image_count($filter) {
        $args = $this->get_query_args($filter, -1, 0);
        $query = new WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Build query args based on filter
     */
    private function get_query_args($filter, $per_page = 5, $offset = 0) {
        $settings = Voxel_Toolkit_Image_Optimization::instance()->get_settings();
        $max_width = intval($settings['max_width']);
        $max_height = intval($settings['max_height']);

        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png', 'image/webp'),
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ids',
        );

        if ($filter === 'not_processed') {
            $args['meta_query'] = array(
                array(
                    'key' => '_vt_bulk_resized',
                    'compare' => 'NOT EXISTS',
                ),
            );
        } elseif ($filter === 'oversized') {
            // We need to check dimensions in PHP, but pre-filter by not processed
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => '_vt_bulk_resized',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => '_vt_bulk_resized',
                    'compare' => 'EXISTS',
                ),
            );
        }
        // 'all' has no meta_query

        return $args;
    }

    /**
     * AJAX: Process batch
     */
    public function ajax_process_batch() {
        check_ajax_referer('vt_bulk_resize', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Extend time limit for shared hosting
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'not_processed';
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 10;

        $settings = Voxel_Toolkit_Image_Optimization::instance()->get_settings();
        $max_width = intval($settings['max_width']);
        $max_height = intval($settings['max_height']);

        $args = $this->get_query_args($filter, $batch_size, $offset);
        $query = new WP_Query($args);
        $attachment_ids = $query->posts;

        // Free query memory
        $query = null;

        $results = array();
        $total_saved = 0;
        $total_original = 0;
        $resized_count = 0;
        $skipped_count = 0;

        foreach ($attachment_ids as $attachment_id) {
            $result = $this->resize_image($attachment_id, $max_width, $max_height, $settings);
            $results[] = $result;

            if ($result['saved'] > 0) {
                $total_saved += $result['saved'];
                $total_original += $result['original_size'];
                $resized_count++;
            } else {
                $skipped_count++;
            }

            // Clean up memory after each image
            $this->cleanup_memory();
        }

        // Check if there are more images
        $next_offset = $offset + $batch_size;
        $remaining_args = $this->get_query_args($filter, 1, $next_offset);
        $remaining_query = new WP_Query($remaining_args);
        $has_more = $remaining_query->found_posts > 0;

        wp_send_json_success(array(
            'processed' => count($results),
            'results' => $results,
            'saved_bytes' => $total_saved,
            'original_bytes' => $total_original,
            'resized' => $resized_count,
            'skipped' => $skipped_count,
            'has_more' => $has_more,
            'next_offset' => $next_offset,
        ));
    }

    /**
     * Clean up memory between image processing
     */
    private function cleanup_memory() {
        // Clear WordPress object cache
        global $wpdb, $wp_object_cache;

        if (is_object($wpdb)) {
            $wpdb->flush();
        }

        // Clear specific caches that grow during batch operations
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('posts');
            wp_cache_flush_group('post_meta');
        }

        // Trigger garbage collection if available
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Resize a single image
     */
    private function resize_image($attachment_id, $max_width, $max_height, $settings) {
        $file_path = get_attached_file($attachment_id);
        $filename = basename($file_path);

        if (!file_exists($file_path)) {
            return array(
                'id' => $attachment_id,
                'filename' => $filename,
                'status' => 'error',
                'message' => __('File not found', 'voxel-toolkit'),
                'saved' => 0,
            );
        }

        $original_size = filesize($file_path);
        $current_mime = get_post_mime_type($attachment_id);

        // Determine if format conversion is needed
        $optimization_mode = isset($settings['optimization_mode']) ? $settings['optimization_mode'] : 'all_webp';
        $convert_to_webp = $this->should_convert_to_webp($current_mime, $optimization_mode);

        // Check WebP support if conversion needed
        if ($convert_to_webp && !wp_image_editor_supports(array('mime_type' => 'image/webp'))) {
            $convert_to_webp = false;
        }

        // Get image editor
        $editor = wp_get_image_editor($file_path);
        if (is_wp_error($editor)) {
            return array(
                'id' => $attachment_id,
                'filename' => $filename,
                'status' => 'error',
                'message' => $editor->get_error_message(),
                'saved' => 0,
            );
        }

        $size = $editor->get_size();
        $needs_resize = ($size['width'] > $max_width || $size['height'] > $max_height);

        // If no resize AND no conversion needed, skip
        if (!$needs_resize && !$convert_to_webp) {
            update_post_meta($attachment_id, '_vt_bulk_resized', time());

            return array(
                'id' => $attachment_id,
                'filename' => $filename,
                'status' => 'skipped',
                'message' => sprintf(__('Already optimal (%dx%d)', 'voxel-toolkit'), $size['width'], $size['height']),
                'saved' => 0,
            );
        }

        // Resize if needed
        if ($needs_resize) {
            $editor->resize($max_width, $max_height, false);
        }
        $editor->set_quality(intval($settings['output_quality']));

        // Save with format conversion if needed
        $converted = false;
        $original_format = strtoupper(pathinfo($file_path, PATHINFO_EXTENSION));

        if ($convert_to_webp) {
            $new_file_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
            $result = $editor->save($new_file_path, 'image/webp');

            if (!is_wp_error($result)) {
                $converted = true;

                // Delete original file if it's a different path
                if ($new_file_path !== $file_path && file_exists($file_path)) {
                    @unlink($file_path);
                }

                // Update attachment metadata
                update_attached_file($attachment_id, $new_file_path);
                wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_mime_type' => 'image/webp',
                ));

                $file_path = $new_file_path;
                $filename = basename($new_file_path);
            }
        }

        // If not converted (or conversion failed), save normally
        if (!$converted) {
            $result = $editor->save($file_path);
        }

        if (is_wp_error($result)) {
            return array(
                'id' => $attachment_id,
                'filename' => $filename,
                'status' => 'error',
                'message' => $result->get_error_message(),
                'saved' => 0,
            );
        }

        // Get new size
        $new_size = filesize($file_path);
        $saved = $original_size - $new_size;
        $percent = $original_size > 0 ? round(($saved / $original_size) * 100, 1) : 0;

        // Regenerate thumbnails
        wp_update_attachment_metadata(
            $attachment_id,
            wp_generate_attachment_metadata($attachment_id, $file_path)
        );

        // Store optimization data
        update_post_meta($attachment_id, '_vt_bulk_resized', time());
        update_post_meta($attachment_id, '_vt_original_size', $original_size);
        update_post_meta($attachment_id, '_vt_saved_bytes', $saved);
        update_post_meta($attachment_id, '_vt_saved_percent', $percent);

        // Get new dimensions
        $new_editor = wp_get_image_editor($file_path);
        $new_dims = is_wp_error($new_editor) ? array('width' => 0, 'height' => 0) : $new_editor->get_size();

        // Build status message
        $status = $needs_resize ? 'resized' : 'converted';
        if ($needs_resize && $converted) {
            // Resized and converted
            $message = sprintf(
                __('%dx%d -> %dx%d WebP (saved %s, %s%%)', 'voxel-toolkit'),
                $size['width'],
                $size['height'],
                $new_dims['width'],
                $new_dims['height'],
                $this->format_bytes($saved),
                $percent
            );
        } elseif ($converted) {
            // Converted only (no resize)
            $message = sprintf(
                __('%dx%d %s -> WebP (saved %s, %s%%)', 'voxel-toolkit'),
                $size['width'],
                $size['height'],
                $original_format,
                $this->format_bytes($saved),
                $percent
            );
        } else {
            // Resized only (no conversion)
            $message = sprintf(
                __('%dx%d -> %dx%d (saved %s, %s%%)', 'voxel-toolkit'),
                $size['width'],
                $size['height'],
                $new_dims['width'],
                $new_dims['height'],
                $this->format_bytes($saved),
                $percent
            );
        }

        return array(
            'id' => $attachment_id,
            'filename' => $filename,
            'status' => $status,
            'message' => $message,
            'saved' => $saved,
            'original_size' => $original_size,
            'percent' => $percent,
            'converted' => $converted,
        );
    }

    /**
     * AJAX: Reset processed status
     */
    public function ajax_reset_processed() {
        check_ajax_referer('vt_bulk_resize', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;

        // Delete all VT bulk resize meta keys
        $meta_keys = array('_vt_bulk_resized', '_vt_original_size', '_vt_saved_bytes', '_vt_saved_percent');
        $deleted = 0;

        foreach ($meta_keys as $key) {
            $deleted += $wpdb->delete(
                $wpdb->postmeta,
                array('meta_key' => $key),
                array('%s')
            );
        }

        wp_send_json_success(array(
            'deleted' => $deleted,
        ));
    }

    /**
     * Format bytes to human readable
     */
    private function format_bytes($bytes) {
        if ($bytes === 0) return '0 B';
        if ($bytes < 0) return '-' . $this->format_bytes(abs($bytes));

        $k = 1024;
        $sizes = array('B', 'KB', 'MB', 'GB');
        $i = floor(log($bytes) / log($k));

        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    /**
     * Check if image should be converted to WebP based on optimization mode
     */
    private function should_convert_to_webp($mime_type, $mode) {
        // Already WebP, no conversion needed
        if ($mime_type === 'image/webp') {
            return false;
        }

        switch ($mode) {
            case 'all_webp':
                return in_array($mime_type, array('image/jpeg', 'image/png'));
            case 'only_jpg':
                return $mime_type === 'image/jpeg';
            case 'only_png':
                return $mime_type === 'image/png';
            case 'both_to_webp':
                return in_array($mime_type, array('image/jpeg', 'image/png'));
            case 'originals_only':
            default:
                return false;
        }
    }

    /**
     * Get human-readable label for optimization mode
     */
    private function get_mode_label($mode) {
        $labels = array(
            'all_webp' => __('Convert all to WebP', 'voxel-toolkit'),
            'only_jpg' => __('Only JPG to WebP', 'voxel-toolkit'),
            'only_png' => __('Only PNG to WebP', 'voxel-toolkit'),
            'both_to_webp' => __('JPG & PNG to WebP', 'voxel-toolkit'),
            'originals_only' => __('Keep original formats', 'voxel-toolkit'),
        );
        return isset($labels[$mode]) ? $labels[$mode] : $mode;
    }

    /**
     * Add optimization column to media library
     */
    public function add_media_column($columns) {
        $columns['vt_optimized'] = __('Optimized (VT)', 'voxel-toolkit');
        return $columns;
    }

    /**
     * Render optimization column content
     */
    public function render_media_column($column_name, $attachment_id) {
        if ($column_name !== 'vt_optimized') {
            return;
        }

        // Check if it's an image
        $mime_type = get_post_mime_type($attachment_id);
        if (!in_array($mime_type, array('image/jpeg', 'image/png', 'image/webp'))) {
            echo '<span class="vt-opt-na">—</span>';
            return;
        }

        $resized_time = get_post_meta($attachment_id, '_vt_bulk_resized', true);

        if (!$resized_time) {
            echo '<span class="vt-opt-no">' . __('No', 'voxel-toolkit') . '</span>';
            return;
        }

        $saved_bytes = get_post_meta($attachment_id, '_vt_saved_bytes', true);
        $saved_percent = get_post_meta($attachment_id, '_vt_saved_percent', true);

        if ($saved_bytes && $saved_percent) {
            echo '<span class="vt-opt-yes">';
            echo '<span class="vt-opt-badge">' . esc_html($saved_percent) . '%</span> ';
            echo '<span class="vt-opt-saved">' . esc_html($this->format_bytes($saved_bytes)) . '</span>';
            echo '</span>';
        } else {
            // Was processed but already optimal (no resize needed)
            echo '<span class="vt-opt-optimal">' . __('Optimal', 'voxel-toolkit') . '</span>';
        }
    }

    /**
     * Add styles for media column
     */
    public function media_column_styles() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'upload') {
            return;
        }
        ?>
        <style>
            .column-vt_optimized {
                width: 110px;
            }
            .vt-opt-na {
                color: #999;
            }
            .vt-opt-no {
                color: #999;
            }
            .vt-opt-yes {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }
            .vt-opt-badge {
                display: inline-block;
                background: #27ae60;
                color: #fff;
                font-size: 11px;
                font-weight: 600;
                padding: 2px 6px;
                border-radius: 3px;
            }
            .vt-opt-saved {
                color: #666;
                font-size: 12px;
            }
            .vt-opt-optimal {
                color: #1e3a5f;
                font-weight: 500;
            }
        </style>
        <?php
    }
}
