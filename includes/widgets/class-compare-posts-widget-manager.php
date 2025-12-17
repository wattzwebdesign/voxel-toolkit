<?php
/**
 * Compare Posts Widget Manager
 *
 * Manages the Compare Posts functionality including:
 * - Compare Button widget registration
 * - Comparison Table widget registration
 * - Floating comparison bar
 * - AJAX handlers for comparison data
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Compare_Posts_Widget_Manager {

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
        // Register Elementor widgets
        add_action('elementor/widgets/register', array($this, 'register_elementor_widgets'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Add floating bar to footer
        add_action('wp_footer', array($this, 'render_floating_bar'));

        // AJAX handlers for getting comparison data
        add_action('wp_ajax_vt_get_comparison_posts', array($this, 'ajax_get_comparison_posts'));
        add_action('wp_ajax_nopriv_vt_get_comparison_posts', array($this, 'ajax_get_comparison_posts'));
    }

    /**
     * Register Elementor widgets
     *
     * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager
     */
    public function register_elementor_widgets($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-compare-posts-button-widget.php';
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-compare-posts-table-widget.php';

        $widgets_manager->register(new \Voxel_Toolkit_Compare_Posts_Button_Widget());
        $widgets_manager->register(new \Voxel_Toolkit_Compare_Posts_Table_Widget());
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_assets() {
        // Register and enqueue styles
        wp_enqueue_style(
            'voxel-toolkit-compare-posts',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/compare-posts.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        // Register and enqueue scripts
        wp_enqueue_script(
            'voxel-toolkit-compare-posts',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/compare-posts.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Get settings
        $settings = Voxel_Toolkit_Settings::instance();
        $compare_settings = $settings->get_function_settings('compare_posts', array());

        $comparison_pages = isset($compare_settings['comparison_pages']) ? (array) $compare_settings['comparison_pages'] : array();
        $bar_position = isset($compare_settings['bar_position']) ? $compare_settings['bar_position'] : 'bottom';
        $max_posts = isset($compare_settings['max_posts']) ? intval($compare_settings['max_posts']) : 4;

        // Build comparison page URLs per post type
        $comparison_page_urls = array();
        foreach ($comparison_pages as $pt_key => $page_id) {
            if ($page_id) {
                $comparison_page_urls[$pt_key] = get_permalink($page_id);
            }
        }

        // Localize script with configuration
        wp_localize_script('voxel-toolkit-compare-posts', 'voxelCompare', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_compare_posts'),
            'comparisonPageUrls' => $comparison_page_urls,
            'barPosition' => $bar_position,
            'maxPosts' => $max_posts,
            'i18n' => array(
                'compare' => __('Compare', 'voxel-toolkit'),
                'addedToComparison' => __('Added to Comparison', 'voxel-toolkit'),
                'viewComparison' => __('View Comparison', 'voxel-toolkit'),
                'clearAll' => __('Clear All', 'voxel-toolkit'),
                'remove' => __('Remove', 'voxel-toolkit'),
                'postsSelected' => __('posts selected', 'voxel-toolkit'),
                'maxReached' => __('Maximum posts reached', 'voxel-toolkit'),
                'differentPostType' => __('Can only compare posts of the same type', 'voxel-toolkit'),
                'minPosts' => __('Select at least 2 posts to compare', 'voxel-toolkit'),
                'noComparisonPage' => __('Comparison page not configured for this post type', 'voxel-toolkit'),
            ),
        ));
    }

    /**
     * Render floating bar container in footer
     */
    public function render_floating_bar() {
        // Don't render in Elementor editor
        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            return;
        }

        $settings = Voxel_Toolkit_Settings::instance();
        $compare_settings = $settings->get_function_settings('compare_posts', array());
        $bar_position = isset($compare_settings['bar_position']) ? $compare_settings['bar_position'] : 'bottom';
        ?>
        <div id="vt-compare-floating-bar"
             class="vt-compare-floating-bar vt-compare-bar-<?php echo esc_attr($bar_position); ?>"
             style="display: none;">
            <!-- Content populated by JavaScript -->
        </div>
        <?php
    }

    /**
     * AJAX handler for getting comparison posts data
     */
    public function ajax_get_comparison_posts() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vt_compare_posts')) {
            wp_send_json_error(array('message' => __('Security check failed', 'voxel-toolkit')));
        }

        $post_ids = isset($_POST['post_ids']) ? array_map('absint', (array)$_POST['post_ids']) : array();
        $fields = isset($_POST['fields']) ? array_map('sanitize_text_field', (array)$_POST['fields']) : array();

        if (empty($post_ids)) {
            wp_send_json_error(array('message' => __('No posts specified', 'voxel-toolkit')));
        }

        // Load the comparison renderer
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/class-comparison-renderer.php';
        $renderer = new Voxel_Toolkit_Comparison_Renderer();

        $posts_data = array();

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }

            $post_data = array(
                'id' => $post_id,
                'title' => get_the_title($post_id),
                'thumbnail' => get_the_post_thumbnail_url($post_id, 'thumbnail'),
                'permalink' => get_permalink($post_id),
                'fields' => array()
            );

            // Render each requested field
            foreach ($fields as $field_key) {
                $post_data['fields'][$field_key] = $renderer->render($field_key, $post_id);
            }

            $posts_data[] = $post_data;
        }

        wp_send_json_success($posts_data);
    }

    /**
     * Get all Voxel post types
     *
     * @return array Associative array of post_type_key => label
     */
    public static function get_voxel_post_types() {
        $post_types = get_option('voxel:post_types', array());

        if (is_string($post_types)) {
            $post_types = json_decode($post_types, true);
        }

        $result = array();
        if (is_array($post_types)) {
            foreach ($post_types as $key => $config) {
                $result[$key] = isset($config['settings']['singular']) ? $config['settings']['singular'] : ucfirst($key);
            }
        }

        return $result;
    }

    /**
     * Get fields for a specific post type
     *
     * @param string $post_type_key Post type key
     * @return array Associative array of field_key => field_label
     */
    public static function get_post_type_fields($post_type_key = '') {
        $post_types = get_option('voxel:post_types', array());

        if (is_string($post_types)) {
            $post_types = json_decode($post_types, true);
        }

        if (empty($post_type_key)) {
            return $post_types;
        }

        if (!isset($post_types[$post_type_key]['fields'])) {
            return array();
        }

        $fields = array();

        // Field types to skip (UI-only fields)
        $skip_types = array('ui-step', 'ui-heading', 'ui-html', 'ui-image');

        foreach ($post_types[$post_type_key]['fields'] as $field) {
            if (!isset($field['key'])) {
                continue;
            }

            // Skip internal/UI fields
            $type = isset($field['type']) ? $field['type'] : '';
            if (in_array($type, $skip_types)) {
                continue;
            }

            // Use label if set, otherwise format the key nicely
            $label = isset($field['label']) && !empty($field['label'])
                ? $field['label']
                : ucwords(str_replace(array('_', '-'), ' ', $field['key']));

            $fields[$field['key']] = $label;
        }

        return $fields;
    }
}
