<?php
/**
 * Article Helpful Widget Manager
 *
 * Handles registration and initialization of the Article Helpful widget
 *
 * @package Voxel_Toolkit
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Voxel_Toolkit_Article_Helpful_Widget_Manager
 *
 * Manages the Article Helpful widget registration and AJAX handlers
 */
class Voxel_Toolkit_Article_Helpful_Widget_Manager {

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
        // Register the Elementor widget
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));

        // Register AJAX handlers for vote submission
        add_action('wp_ajax_voxel_article_helpful_vote', array($this, 'handle_vote'));
        add_action('wp_ajax_nopriv_voxel_article_helpful_vote', array($this, 'handle_vote'));

        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Add admin columns - run immediately instead of on init
        $this->add_admin_columns_for_post_types();
    }

    /**
     * Register the Elementor widget
     *
     * @param object $widgets_manager Elementor widgets manager
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-article-helpful-widget.php';
        $widgets_manager->register(new \Voxel_Toolkit_Article_Helpful_Widget());
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        $css_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/css/article-helpful.css';
        $js_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/js/article-helpful.js';

        // Enqueue styles with file modification time for cache busting
        wp_enqueue_style(
            'voxel-article-helpful',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/article-helpful.css',
            array(),
            file_exists($css_file) ? filemtime($css_file) : VOXEL_TOOLKIT_VERSION
        );

        // Enqueue scripts with file modification time for cache busting
        wp_enqueue_script(
            'voxel-article-helpful',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/article-helpful.js',
            array('jquery'),
            file_exists($js_file) ? filemtime($js_file) : VOXEL_TOOLKIT_VERSION,
            true
        );

        // Localize script
        wp_localize_script('voxel-article-helpful', 'voxelArticleHelpful', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('voxel_article_helpful_nonce'),
        ));
    }

    /**
     * Handle AJAX vote submission
     */
    public function handle_vote() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'voxel_article_helpful_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'voxel-toolkit')));
            return;
        }

        // Get post ID and vote type
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $vote_type = isset($_POST['vote_type']) ? sanitize_text_field($_POST['vote_type']) : '';

        if (!$post_id || !in_array($vote_type, array('yes', 'no'))) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'voxel-toolkit')));
            return;
        }

        // Determine tracking method (user-based or cookie-based)
        $user_id = get_current_user_id();
        $previous_vote = null;
        $is_changing_vote = false;

        if ($user_id) {
            // User is logged in - use user meta
            $vote_meta_key = '_article_helpful_vote_' . $post_id;
            $previous_vote = get_user_meta($user_id, $vote_meta_key, true);

            if ($previous_vote && $previous_vote === $vote_type) {
                // User clicked the same vote again - no change
                // Note: Error message is now handled by widget settings via data attributes in JavaScript
                wp_send_json_error(array());
                return;
            }

            if ($previous_vote) {
                $is_changing_vote = true;
            }
        } else {
            // Guest user - use cookie
            $cookie_name = 'voxel_helpful_' . $post_id;
            if (isset($_COOKIE[$cookie_name])) {
                $previous_vote = $_COOKIE[$cookie_name];

                if ($previous_vote === $vote_type) {
                    // User clicked the same vote again - no change
                    // Note: Error message is now handled by widget settings via data attributes in JavaScript
                    wp_send_json_error(array());
                    return;
                }

                $is_changing_vote = true;
            }
        }

        // Get current vote counts
        $yes_count = get_post_meta($post_id, '_article_helpful_yes', true);
        $no_count = get_post_meta($post_id, '_article_helpful_no', true);

        $yes_count = $yes_count ? intval($yes_count) : 0;
        $no_count = $no_count ? intval($no_count) : 0;

        // If changing vote, decrease the old vote count
        if ($is_changing_vote && $previous_vote) {
            if ($previous_vote === 'yes') {
                $yes_count = max(0, $yes_count - 1);
            } else {
                $no_count = max(0, $no_count - 1);
            }
        }

        // Increase the new vote count
        if ($vote_type === 'yes') {
            $yes_count++;
        } else {
            $no_count++;
        }

        // Update vote counts
        update_post_meta($post_id, '_article_helpful_yes', $yes_count);
        update_post_meta($post_id, '_article_helpful_no', $no_count);

        // Save the vote
        if ($user_id) {
            // Save to user meta
            update_user_meta($user_id, $vote_meta_key, $vote_type);
        } else {
            // Save to cookie (expires in 30 days)
            setcookie($cookie_name, $vote_type, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);
        }

        // Send success response
        // Note: Messages are now handled by widget settings via data attributes in JavaScript
        wp_send_json_success(array(
            'yes_count' => $yes_count,
            'no_count' => $no_count,
            'vote_type' => $vote_type,
            'is_change' => $is_changing_vote,
        ));
    }

    /**
     * Add admin columns for all public post types
     */
    public function add_admin_columns_for_post_types() {
        // Get all public post types
        $post_types = get_post_types(array('public' => true), 'names');

        // Exclude certain post types if needed
        $exclude = array('attachment', 'elementor_library');
        $post_types = array_diff($post_types, $exclude);

        foreach ($post_types as $post_type) {
            add_filter("manage_{$post_type}_posts_columns", array($this, 'add_admin_column'));
            add_action("manage_{$post_type}_posts_custom_column", array($this, 'render_admin_column'), 10, 2);
            add_filter("manage_edit-{$post_type}_sortable_columns", array($this, 'make_column_sortable'));
        }

        // Handle sorting
        add_action('pre_get_posts', array($this, 'handle_column_sorting'));
    }

    /**
     * Add helpful stats column to admin
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_admin_column($columns) {
        // Add column after title
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['article_helpful'] = __('Helpful', 'voxel-toolkit');
            }
        }
        return $new_columns;
    }

    /**
     * Render the admin column content
     *
     * @param string $column Column name
     * @param int $post_id Post ID
     */
    public function render_admin_column($column, $post_id) {
        if ($column === 'article_helpful') {
            $yes_count = get_post_meta($post_id, '_article_helpful_yes', true);
            $no_count = get_post_meta($post_id, '_article_helpful_no', true);

            $yes_count = $yes_count ? intval($yes_count) : 0;
            $no_count = $no_count ? intval($no_count) : 0;
            $total = $yes_count + $no_count;

            if ($total > 0) {
                $percentage = round(($yes_count / $total) * 100);
                echo '<div class="voxel-helpful-stats">';
                echo '<span class="helpful-yes" style="color: #46b450; display: inline-flex; align-items: center; gap: 4px;" title="' . esc_attr__('Yes votes', 'voxel-toolkit') . '">';
                echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width: 14px; height: 14px; fill: currentColor;"><path d="M22.773,7.721A4.994,4.994,0,0,0,19,6H15.011l.336-2.041A3.037,3.037,0,0,0,9.626,2.122L7.712,6H5a5.006,5.006,0,0,0-5,5v5a5.006,5.006,0,0,0,5,5H18.3a5.024,5.024,0,0,0,4.951-4.3l.705-5A5,5,0,0,0,22.773,7.721ZM2,16V11A3,3,0,0,1,5,8H7V19H5A3,3,0,0,1,2,16Zm19.971-4.581-.706,5A3.012,3.012,0,0,1,18.3,19H9V7.734a1,1,0,0,0,.23-.292l2.189-4.435A1.07,1.07,0,0,1,13.141,2.8a1.024,1.024,0,0,1,.233.84l-.528,3.2A1,1,0,0,0,13.833,8H19a3,3,0,0,1,2.971,3.419Z"/></svg>';
                echo ' ' . esc_html($yes_count);
                echo '</span>';
                echo ' / ';
                echo '<span class="helpful-no" style="color: #dc3232; display: inline-flex; align-items: center; gap: 4px;" title="' . esc_attr__('No votes', 'voxel-toolkit') . '">';
                echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width: 14px; height: 14px; fill: currentColor;"><path d="M23.951,12.3l-.705-5A5.024,5.024,0,0,0,18.3,3H5A5.006,5.006,0,0,0,0,8v5a5.006,5.006,0,0,0,5,5H7.712l1.914,3.878a3.037,3.037,0,0,0,5.721-1.837L15.011,18H19a5,5,0,0,0,4.951-5.7ZM5,5H7V16H5a3,3,0,0,1-3-3V8A3,3,0,0,1,5,5Zm16.264,9.968A3,3,0,0,1,19,16H13.833a1,1,0,0,0-.987,1.162l.528,3.2a1.024,1.024,0,0,1-.233.84,1.07,1.07,0,0,1-1.722-.212L9.23,16.558A1,1,0,0,0,9,16.266V5h9.3a3.012,3.012,0,0,1,2.97,2.581l.706,5A3,3,0,0,1,21.264,14.968Z"/></svg>';
                echo ' ' . esc_html($no_count);
                echo '</span>';
                echo '<br><small>' . sprintf(esc_html__('%d%% helpful', 'voxel-toolkit'), $percentage) . '</small>';
                echo '</div>';
            } else {
                echo '<span style="color: #999;">—</span>';
            }
        }
    }

    /**
     * Make the column sortable
     *
     * @param array $columns Sortable columns
     * @return array Modified sortable columns
     */
    public function make_column_sortable($columns) {
        $columns['article_helpful'] = 'article_helpful';
        return $columns;
    }

    /**
     * Handle column sorting
     *
     * @param WP_Query $query The WordPress query
     */
    public function handle_column_sorting($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby === 'article_helpful') {
            $query->set('meta_key', '_article_helpful_yes');
            $query->set('orderby', 'meta_value_num');
        }
    }
}
