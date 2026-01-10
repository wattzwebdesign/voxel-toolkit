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

        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'not_processed';
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 5;

        $settings = Voxel_Toolkit_Image_Optimization::instance()->get_settings();
        $max_width = intval($settings['max_width']);
        $max_height = intval($settings['max_height']);

        $args = $this->get_query_args($filter, $batch_size, $offset);
        $query = new WP_Query($args);
        $attachment_ids = $query->posts;

        $results = array();
        $total_saved = 0;
        $resized_count = 0;
        $skipped_count = 0;

        foreach ($attachment_ids as $attachment_id) {
            $result = $this->resize_image($attachment_id, $max_width, $max_height, $settings);
            $results[] = $result;

            if ($result['saved'] > 0) {
                $total_saved += $result['saved'];
                $resized_count++;
            } else {
                $skipped_count++;
            }
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
            'resized' => $resized_count,
            'skipped' => $skipped_count,
            'has_more' => $has_more,
            'next_offset' => $next_offset,
        ));
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

        if (!$needs_resize) {
            // Mark as processed but skipped
            update_post_meta($attachment_id, '_vt_bulk_resized', time());

            return array(
                'id' => $attachment_id,
                'filename' => $filename,
                'status' => 'skipped',
                'message' => sprintf(__('Already optimal (%dx%d)', 'voxel-toolkit'), $size['width'], $size['height']),
                'saved' => 0,
            );
        }

        // Resize
        $editor->resize($max_width, $max_height, false);
        $editor->set_quality(intval($settings['output_quality']));

        $result = $editor->save($file_path);

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

        // Regenerate thumbnails
        wp_update_attachment_metadata(
            $attachment_id,
            wp_generate_attachment_metadata($attachment_id, $file_path)
        );

        // Mark as processed
        update_post_meta($attachment_id, '_vt_bulk_resized', time());

        // Get new dimensions
        $new_editor = wp_get_image_editor($file_path);
        $new_dims = is_wp_error($new_editor) ? array('width' => 0, 'height' => 0) : $new_editor->get_size();

        return array(
            'id' => $attachment_id,
            'filename' => $filename,
            'status' => 'resized',
            'message' => sprintf(
                __('%dx%d -> %dx%d (saved %s)', 'voxel-toolkit'),
                $size['width'],
                $size['height'],
                $new_dims['width'],
                $new_dims['height'],
                $this->format_bytes($saved)
            ),
            'saved' => $saved,
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
        $deleted = $wpdb->delete(
            $wpdb->postmeta,
            array('meta_key' => '_vt_bulk_resized'),
            array('%s')
        );

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
}
