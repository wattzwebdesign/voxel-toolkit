<?php
/**
 * Timeline Photos Widget Manager
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Timeline_Photos_Widget {
    
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
        // Register Elementor widget
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));
        
        // Add shortcode
        add_shortcode('timeline_photos', array($this, 'timeline_photos_shortcode'));
        
        // Enqueue styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        
        // Add Elementor frontend styles
        add_action('elementor/frontend/after_enqueue_styles', array($this, 'enqueue_frontend_styles'));
    }
    
    /**
     * Register Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/elementor/widgets/timeline-photos.php';
        $widgets_manager->register(new \Voxel_Toolkit_Elementor_Timeline_Photos());
    }
    
    /**
     * Enqueue styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'voxel-timeline-photos',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/timeline-photos.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
    }
    
    /**
     * Enqueue frontend styles for Elementor
     */
    public function enqueue_frontend_styles() {
        wp_enqueue_style(
            'voxel-timeline-photos',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/timeline-photos.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
    }
    
    /**
     * Debug timeline data for a post
     */
    public static function debug_timeline_data($post_id) {
        global $wpdb;
        
        if (!$post_id) {
            return 'No post ID provided';
        }
        
        $table_name = $wpdb->prefix . 'voxel_timeline';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        if (!$table_exists) {
            return "Table {$table_name} does not exist";
        }
        
        // Get all timeline entries for this post
        $all_entries = $wpdb->get_results($wpdb->prepare(
            "SELECT id, feed, details, created_at FROM {$table_name} WHERE post_id = %d ORDER BY created_at DESC",
            $post_id
        ));
        
        $debug_info = [];
        $debug_info[] = "Post ID: {$post_id}";
        $debug_info[] = "Total timeline entries: " . count($all_entries);
        
        if (empty($all_entries)) {
            $debug_info[] = "No timeline entries found for this post";
            
            // Check if there are any timeline entries at all
            $total_entries = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $debug_info[] = "Total entries in timeline table: {$total_entries}";
            
            if ($total_entries > 0) {
                // Show available feeds
                $types = $wpdb->get_results("SELECT DISTINCT feed, COUNT(*) as count FROM {$table_name} GROUP BY feed");
                $debug_info[] = "Available timeline feeds:";
                foreach ($types as $type) {
                    $debug_info[] = "  - {$type->feed}: {$type->count} entries";
                }
            }
        } else {
            $debug_info[] = "\nTimeline entries by feed:";
            $types_count = [];
            foreach ($all_entries as $entry) {
                $types_count[$entry->feed] = ($types_count[$entry->feed] ?? 0) + 1;
            }
            
            foreach ($types_count as $type => $count) {
                $debug_info[] = "  - {$type}: {$count} entries";
            }
            
            // Show first few entries with details
            $debug_info[] = "\nFirst 3 timeline entries:";
            for ($i = 0; $i < min(3, count($all_entries)); $i++) {
                $entry = $all_entries[$i];
                $debug_info[] = "  Entry " . ($i + 1) . ":";
                $debug_info[] = "    ID: {$entry->id}";
                $debug_info[] = "    Feed: {$entry->feed}";
                $debug_info[] = "    Created: {$entry->created_at}";
                $debug_info[] = "    Details: " . substr($entry->details, 0, 200) . (strlen($entry->details) > 200 ? '...' : '');
                
                // Parse JSON if possible
                $details = json_decode($entry->details, true);
                if ($details) {
                    $debug_info[] = "    Parsed details keys: " . implode(', ', array_keys($details));
                    if (isset($details['files'])) {
                        $debug_info[] = "    Files found: " . print_r($details['files'], true);
                    }
                }
                $debug_info[] = "";
            }
        }
        
        return implode("\n", $debug_info);
    }
    
    /**
     * Get timeline photos for a post (static method for external use)
     */
    public static function get_post_timeline_photos($post_id, $debug_mode = false, $timeline_source = 'post_reviews') {
        global $wpdb;

        if (!$post_id) {
            return [];
        }

        // If debug mode is enabled, return debug info instead
        if ($debug_mode) {
            return self::debug_timeline_data($post_id);
        }

        $table_name = $wpdb->prefix . 'voxel_timeline';

        // Handle author_timeline differently - query by user_id
        if ($timeline_source === 'author_timeline') {
            $post = get_post($post_id);
            if (!$post || !$post->post_author) {
                return [];
            }
            $author_id = $post->post_author;
            $query = $wpdb->prepare(
                "SELECT details FROM {$table_name} WHERE user_id = %d AND feed = 'user_timeline' AND moderation = 1",
                $author_id
            );
        } else {
            // Query by post_id for post_reviews, post_wall, post_timeline
            $query = $wpdb->prepare(
                "SELECT details FROM {$table_name} WHERE post_id = %d AND feed = %s AND moderation = 1",
                $post_id,
                $timeline_source
            );
        }

        $results = $wpdb->get_results($query);
        
        if (empty($results)) {
            return [];
        }
        
        $file_ids = [];
        
        foreach ($results as $result) {
            $details = json_decode($result->details, true);
            
            if (isset($details['files']) && !empty($details['files'])) {
                // Handle both single file ID and array of file IDs
                if (is_array($details['files'])) {
                    $file_ids = array_merge($file_ids, $details['files']);
                } else {
                    // Handle comma-separated string of IDs
                    if (is_string($details['files']) && strpos($details['files'], ',') !== false) {
                        $ids = explode(',', $details['files']);
                        $file_ids = array_merge($file_ids, array_map('trim', $ids));
                    } else {
                        $file_ids[] = $details['files'];
                    }
                }
            }
        }
        
        // Remove duplicates and filter out empty values
        $file_ids = array_filter(array_unique($file_ids));
        
        if (empty($file_ids)) {
            return [];
        }
        
        // Get attachment data for each file ID
        $photos = [];
        foreach ($file_ids as $file_id) {
            $file_id = intval($file_id);
            if ($file_id <= 0) continue;
            
            $attachment = get_post($file_id);
            if ($attachment && $attachment->post_type === 'attachment' && wp_attachment_is_image($file_id)) {
                $photos[] = [
                    'id' => $file_id,
                    'url' => wp_get_attachment_url($file_id),
                    'title' => get_the_title($file_id),
                    'alt' => get_post_meta($file_id, '_wp_attachment_image_alt', true),
                    'caption' => wp_get_attachment_caption($file_id),
                    'description' => get_post_field('post_content', $file_id),
                ];
            }
        }
        
        return $photos;
    }
    
    /**
     * Get timeline photos count for a post
     */
    public static function get_post_timeline_photos_count($post_id) {
        $photos = self::get_post_timeline_photos($post_id);
        return count($photos);
    }
    
    /**
     * Shortcode for timeline photos
     */
    public function timeline_photos_shortcode($atts) {
        global $post;

        $atts = shortcode_atts(array(
            'post_id' => '',
            'source' => 'post_reviews',
            'columns' => 3,
            'layout' => 'masonry',
            'image_size' => 'medium_large',
            'lightbox' => 'yes',
            'empty_message' => 'No photos found',
            'show_empty' => 'yes',
            'debug' => 'no',
        ), $atts);

        $post_id = !empty($atts['post_id']) ? intval($atts['post_id']) : (isset($post->ID) ? $post->ID : 0);

        if (!$post_id) {
            return '';
        }

        // Validate timeline source
        $valid_sources = array('post_reviews', 'post_wall', 'post_timeline', 'author_timeline');
        $timeline_source = in_array($atts['source'], $valid_sources) ? $atts['source'] : 'post_reviews';

        // If debug mode is enabled, show debug information
        if ($atts['debug'] === 'yes') {
            $debug_info = self::get_post_timeline_photos($post_id, true, $timeline_source);
            return '<div class="timeline-photos-debug"><pre>' . esc_html($debug_info) . '</pre></div>';
        }

        $photos = self::get_post_timeline_photos($post_id, false, $timeline_source);
        
        if (empty($photos)) {
            return $atts['show_empty'] === 'yes' ? '<div class="timeline-photos-empty">' . esc_html($atts['empty_message']) . '</div>' : '';
        }
        
        $gallery_classes = [
            'voxel-timeline-photos',
            'layout-' . esc_attr($atts['layout']),
        ];
        
        $lightbox_attr = $atts['lightbox'] === 'yes' ? 'data-lightbox="timeline-photos"' : '';
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $gallery_classes)); ?>" style="--columns: <?php echo esc_attr($atts['columns']); ?>;">
            <?php foreach ($photos as $photo): ?>
                <div class="timeline-photo-item">
                    <a href="<?php echo esc_url($photo['url']); ?>" 
                       <?php echo $lightbox_attr; ?>
                       title="<?php echo esc_attr($photo['title']); ?>">
                        <?php
                        echo wp_get_attachment_image(
                            $photo['id'], 
                            $atts['image_size'], 
                            false, 
                            [
                                'alt' => $photo['alt'],
                                'title' => $photo['title'],
                            ]
                        );
                        ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
}