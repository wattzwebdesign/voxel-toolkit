<?php
/**
 * Post Position Tracker
 *
 * Tracks post position within feed loops for dynamic tag usage.
 * Works by combining the post's index in the current feed with pagination offset.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Post_Position_Tracker {

    private static $instance = null;

    /**
     * Current feed offset (for pagination)
     * @var int
     */
    private static $current_offset = 0;

    /**
     * Whether we're in a valid feed render cycle
     * @var bool
     */
    private static $in_feed_render = false;

    /**
     * Track which post IDs we've already returned positions for in this render
     * @var array
     */
    private static $rendered_posts = [];

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
        // Hook before search results render to capture offset
        add_action('voxel/before_render_search_results', array($this, 'capture_feed_context'), 5);
        // Hook after search results render to reset state
        add_action('voxel/after_render_search_results', array($this, 'reset_feed_context'), 5);
    }

    /**
     * Capture feed context before rendering
     *
     * Extracts pagination offset from request parameters.
     * The offset is: limit * (page - 1)
     */
    public function capture_feed_context() {
        // Mark that we're in a valid feed render
        self::$in_feed_render = true;
        // Reset rendered posts for this new render cycle
        self::$rendered_posts = [];

        // Get page number from request
        $page = 1;
        if (isset($_REQUEST['pg']) && is_numeric($_REQUEST['pg'])) {
            $page = absint($_REQUEST['pg']);
        }

        // Get limit from request or use default
        $limit = 10;
        if (isset($_REQUEST['limit']) && is_numeric($_REQUEST['limit'])) {
            $limit = absint($_REQUEST['limit']);
        }

        // Calculate offset
        if ($page > 1) {
            self::$current_offset = $limit * ($page - 1);
        } else {
            self::$current_offset = 0;
        }
    }

    /**
     * Reset feed context after rendering
     */
    public function reset_feed_context() {
        self::$in_feed_render = false;
        self::$rendered_posts = [];
    }

    /**
     * Get the position of the current post in the feed
     *
     * Position is 1-indexed and accounts for pagination.
     *
     * @return int|null Position (1-indexed) or null if not in feed context
     */
    public static function get_current_position() {
        // Check if we're in a valid feed render cycle (hook fired)
        if (!self::$in_feed_render) {
            return null;
        }

        // Check if we're in Elementor preview/edit mode - return null to avoid stale data
        if (self::is_elementor_preview()) {
            return null;
        }

        // Check if we're in a feed context
        if (!isset($GLOBALS['vx_preview_card_current_ids']) ||
            !is_array($GLOBALS['vx_preview_card_current_ids']) ||
            empty($GLOBALS['vx_preview_card_current_ids'])) {
            return null;
        }

        // Check we're actually in an active render cycle (level > 0)
        if (!isset($GLOBALS['vx_preview_card_level']) || $GLOBALS['vx_preview_card_level'] < 1) {
            return null;
        }

        // Get current post
        if (!function_exists('\Voxel\get_current_post')) {
            return null;
        }

        $current_post = \Voxel\get_current_post();
        if (!$current_post || !method_exists($current_post, 'get_id')) {
            return null;
        }

        $post_id = (int) $current_post->get_id();
        $feed_ids = array_map('intval', $GLOBALS['vx_preview_card_current_ids']);

        // Find position in array (0-indexed)
        $index = array_search($post_id, $feed_ids, true);

        if ($index === false) {
            return null;
        }

        // Calculate absolute position (1-indexed)
        // Position = index + offset + 1
        $position = (int) $index + (int) self::$current_offset + 1;

        // Check if the expected position matches our render sequence
        // The next position should be count(rendered_posts) + 1
        $expected_position = count(self::$rendered_posts) + 1 + (int) self::$current_offset;

        if ($position !== $expected_position) {
            // Out of sequence render (likely from Elementor cache) - return null
            return null;
        }

        // Track this post as rendered
        self::$rendered_posts[] = $post_id;

        return $position;
    }

    /**
     * Check if we're in Elementor preview/edit mode
     *
     * @return bool True if in Elementor preview
     */
    private static function is_elementor_preview() {
        // Check for Elementor preview mode (including iframe preview)
        if (isset($_GET['elementor-preview']) || isset($_GET['elementor_library'])) {
            return true;
        }

        // Check for preview=true which Elementor iframe uses
        if (isset($_GET['preview']) && $_GET['preview'] === 'true') {
            return true;
        }

        // Check for Elementor AJAX actions (live preview updates)
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
            // Elementor uses these actions for live preview
            if (strpos($action, 'elementor') !== false) {
                return true;
            }
        }

        // Check if Elementor is in edit mode
        if (class_exists('\Elementor\Plugin')) {
            $elementor = \Elementor\Plugin::instance();
            if ($elementor->editor && $elementor->editor->is_edit_mode()) {
                return true;
            }
            if ($elementor->preview && $elementor->preview->is_preview_mode()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current offset
     *
     * @return int Current pagination offset
     */
    public static function get_current_offset() {
        return (int) self::$current_offset;
    }

    /**
     * Check if we're currently in a feed context
     *
     * @return bool True if in feed context
     */
    public static function is_in_feed() {
        return isset($GLOBALS['vx_preview_card_current_ids']) &&
               is_array($GLOBALS['vx_preview_card_current_ids']) &&
               !empty($GLOBALS['vx_preview_card_current_ids']);
    }
}
