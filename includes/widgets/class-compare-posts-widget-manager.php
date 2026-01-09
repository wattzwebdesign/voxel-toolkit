<?php
/**
 * Compare Posts Widget Manager
 *
 * Manages the Compare Posts functionality including:
 * - Comparison Table widget registration
 * - Floating compare badge with popup
 * - Action (VX) widget integration
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

        // Voxel Action (VX) widget integration
        add_filter('voxel/advanced-list/actions', array($this, 'register_compare_action'));
        add_action('voxel/advanced-list/action:add_to_compare', array($this, 'render_compare_action'), 10, 2);

        // Add active state controls for compare action
        add_action('elementor/element/ts-advanced-list/ts_action_content/after_section_end', array($this, 'extend_action_controls'), 10, 2);
    }

    /**
     * Extend Advanced List action controls to include add_to_compare in active state conditions
     *
     * @param \Elementor\Widget_Base $element
     * @param array $args
     */
    public function extend_action_controls($element, $args) {
        $repeater_control = $element->get_controls('ts_actions');

        if (!$repeater_control || empty($repeater_control['fields'])) {
            return;
        }

        // Controls that need add_to_compare added to their conditions
        // Initial state controls (icon/text for normal state)
        // Active/Reveal state controls (icon/text for when action is active)
        $controls_to_update = array(
            'ts_acw_initial_icon',
            'ts_acw_initial_text',
            'ts_acw_reveal_heading',
            'ts_acw_reveal_text',
            'ts_acw_reveal_icon',
            'ts_acw_enable_tooltip',
            'ts_acw_tooltip_text',
        );

        $updated_fields = $repeater_control['fields'];
        $needs_update = false;

        foreach ($updated_fields as $field_key => &$field_data) {
            // Check if this is one of the controls we need to update
            if (in_array($field_key, $controls_to_update) && isset($field_data['condition']['ts_action_type'])) {
                // Add add_to_compare to the condition if not already present
                if (!in_array('add_to_compare', $field_data['condition']['ts_action_type'])) {
                    $field_data['condition']['ts_action_type'][] = 'add_to_compare';
                    $needs_update = true;
                }
            }
        }

        if ($needs_update) {
            $element->update_control(
                'ts_actions',
                array(
                    'fields' => $updated_fields,
                )
            );
        }
    }

    /**
     * Register Elementor widgets
     *
     * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager
     */
    public function register_elementor_widgets($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-compare-posts-table-widget.php';

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
        $max_posts = isset($compare_settings['max_posts']) ? intval($compare_settings['max_posts']) : 4;

        // Get custom text labels
        $badge_text = isset($compare_settings['badge_text']) && !empty($compare_settings['badge_text'])
            ? $compare_settings['badge_text']
            : __('Compare', 'voxel-toolkit');
        $popup_title = isset($compare_settings['popup_title']) && !empty($compare_settings['popup_title'])
            ? $compare_settings['popup_title']
            : __('Compare Posts', 'voxel-toolkit');
        $view_button_text = isset($compare_settings['view_button_text']) && !empty($compare_settings['view_button_text'])
            ? $compare_settings['view_button_text']
            : __('View Comparison', 'voxel-toolkit');
        $clear_button_text = isset($compare_settings['clear_button_text']) && !empty($compare_settings['clear_button_text'])
            ? $compare_settings['clear_button_text']
            : __('Clear All', 'voxel-toolkit');

        // Get custom notification messages
        $different_post_type_text = isset($compare_settings['different_post_type_text']) && !empty($compare_settings['different_post_type_text'])
            ? $compare_settings['different_post_type_text']
            : __('Can only compare posts of the same type', 'voxel-toolkit');
        $max_reached_text = isset($compare_settings['max_reached_text']) && !empty($compare_settings['max_reached_text'])
            ? $compare_settings['max_reached_text']
            : __('Maximum posts reached', 'voxel-toolkit');

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
            'maxPosts' => $max_posts,
            'labels' => array(
                'badge' => $badge_text,
                'popupTitle' => $popup_title,
                'viewButton' => $view_button_text,
                'clearButton' => $clear_button_text,
            ),
            'i18n' => array(
                'compare' => $badge_text,
                'addedToComparison' => __('Added to Comparison', 'voxel-toolkit'),
                'viewComparison' => $view_button_text,
                'clearAll' => $clear_button_text,
                'remove' => __('Remove', 'voxel-toolkit'),
                'postsSelected' => __('posts selected', 'voxel-toolkit'),
                'maxReached' => $max_reached_text,
                'differentPostType' => $different_post_type_text,
                'minPosts' => __('Select at least 2 posts to compare', 'voxel-toolkit'),
                'noComparisonPage' => __('Comparison page not configured for this post type', 'voxel-toolkit'),
            ),
        ));

        // Add dynamic styles based on settings
        $this->add_dynamic_styles($compare_settings);
    }

    /**
     * Add dynamic CSS styles based on settings
     *
     * @param array $settings Compare posts settings
     */
    private function add_dynamic_styles($settings) {
        // Get style settings with defaults
        $badge_bg = isset($settings['badge_bg_color']) ? $settings['badge_bg_color'] : '#3b82f6';
        $badge_text = isset($settings['badge_text_color']) ? $settings['badge_text_color'] : '#ffffff';
        $badge_radius = isset($settings['badge_border_radius']) ? intval($settings['badge_border_radius']) : 8;

        $popup_bg = isset($settings['popup_bg_color']) ? $settings['popup_bg_color'] : '#ffffff';
        $popup_text = isset($settings['popup_text_color']) ? $settings['popup_text_color'] : '#111827';
        $popup_radius = isset($settings['popup_border_radius']) ? intval($settings['popup_border_radius']) : 12;

        $button_bg = isset($settings['button_bg_color']) ? $settings['button_bg_color'] : '#3b82f6';
        $button_text = isset($settings['button_text_color']) ? $settings['button_text_color'] : '#ffffff';
        $button_radius = isset($settings['button_border_radius']) ? intval($settings['button_border_radius']) : 6;

        $secondary_bg = isset($settings['secondary_bg_color']) ? $settings['secondary_bg_color'] : '#f3f4f6';
        $secondary_text = isset($settings['secondary_text_color']) ? $settings['secondary_text_color'] : '#374151';

        $custom_css = "
            /* Compare Posts Dynamic Styles */
            .vt-compare-badge {
                background: {$badge_bg} !important;
                color: {$badge_text} !important;
                border-radius: {$badge_radius}px 0 0 {$badge_radius}px !important;
            }
            .vt-badge-count {
                background: {$badge_text} !important;
                color: {$badge_bg} !important;
            }
            .vt-compare-popup,
            .vt-compare-popup.ts-field-popup {
                background: {$popup_bg} !important;
                color: {$popup_text} !important;
                border-radius: {$popup_radius}px 0 0 {$popup_radius}px !important;
            }
            .vt-compare-popup .ts-popup-head,
            .vt-compare-popup .ts-popup-name {
                color: {$popup_text} !important;
            }
            .vt-compare-popup .vt-popup-post-title {
                color: {$popup_text} !important;
            }
            .vt-compare-popup .ts-popup-controller .ts-btn-1,
            .vt-compare-popup .vt-compare-popup-view {
                background: {$button_bg} !important;
                color: {$button_text} !important;
                border-radius: {$button_radius}px !important;
            }
            .vt-compare-popup .ts-popup-controller .ts-btn-4,
            .vt-compare-popup .vt-compare-popup-clear {
                background: {$secondary_bg} !important;
                color: {$secondary_text} !important;
                border-radius: {$button_radius}px !important;
            }
            @media (max-width: 768px) {
                .vt-compare-popup,
                .vt-compare-popup.ts-field-popup {
                    border-radius: {$popup_radius}px {$popup_radius}px 0 0 !important;
                }
            }
        ";

        wp_add_inline_style('voxel-toolkit-compare-posts', $custom_css);
    }

    /**
     * Render compare badge and popup in footer
     */
    public function render_floating_bar() {
        // Don't render in Elementor editor
        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            return;
        }

        // Get custom text labels from settings
        $settings = Voxel_Toolkit_Settings::instance();
        $compare_settings = $settings->get_function_settings('compare_posts', array());

        $badge_text = isset($compare_settings['badge_text']) && !empty($compare_settings['badge_text'])
            ? $compare_settings['badge_text']
            : __('Compare', 'voxel-toolkit');
        $popup_title = isset($compare_settings['popup_title']) && !empty($compare_settings['popup_title'])
            ? $compare_settings['popup_title']
            : __('Compare Posts', 'voxel-toolkit');
        $view_button_text = isset($compare_settings['view_button_text']) && !empty($compare_settings['view_button_text'])
            ? $compare_settings['view_button_text']
            : __('View Comparison', 'voxel-toolkit');
        $clear_button_text = isset($compare_settings['clear_button_text']) && !empty($compare_settings['clear_button_text'])
            ? $compare_settings['clear_button_text']
            : __('Clear All', 'voxel-toolkit');
        ?>
        <!-- Compare Badge (Vertical Side Badge) -->
        <div id="vt-compare-badge" class="vt-compare-badge" style="display: none;">
            <span class="vt-badge-text"><?php echo esc_html($badge_text); ?></span>
            <span class="vt-badge-count">0</span>
        </div>

        <!-- Compare Popup -->
        <div id="vt-compare-popup-overlay" class="vt-compare-popup-overlay" style="display: none;">
            <div class="ts-field-popup-container">
                <div class="ts-field-popup vt-compare-popup">
                    <div class="ts-popup-head flexify">
                        <div class="ts-popup-name flexify"><?php echo esc_html($popup_title); ?></div>
                        <a href="#" class="ts-icon-btn vt-compare-popup-close" aria-label="<?php esc_attr_e('Close', 'voxel-toolkit'); ?>">
                            <?php \Voxel\svg('close.svg'); ?>
                        </a>
                    </div>
                    <div class="ts-popup-content-wrapper min-scroll">
                        <ul class="vt-compare-popup-list simplify-ul ts-term-dropdown-list">
                            <!-- Content populated by JavaScript -->
                        </ul>
                    </div>
                    <div class="ts-popup-controller">
                        <ul class="flexify simplify-ul">
                            <li class="flexify">
                                <a href="#" class="ts-btn ts-btn-1 vt-compare-popup-view"><?php echo esc_html($view_button_text); ?></a>
                            </li>
                            <li class="flexify">
                                <a href="#" class="ts-btn ts-btn-4 vt-compare-popup-clear"><?php echo esc_html($clear_button_text); ?></a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Register "Add to Compare" action for Voxel Action (VX) widget
     *
     * @param array $actions Available actions
     * @return array Modified actions array
     */
    public function register_compare_action($actions) {
        $actions['add_to_compare'] = __('Add to Compare', 'voxel-toolkit');
        return $actions;
    }

    /**
     * Render "Add to Compare" action in Voxel Action (VX) widget
     *
     * @param object $widget Widget instance
     * @param array $action Action settings
     */
    public function render_compare_action($widget, $action) {
        $post = \Voxel\get_current_post();
        if (!$post) {
            return;
        }

        $post_id = $post->get_id();
        $post_type = $post->post_type->get_key();
        $post_title = $post->get_title();
        $post_thumbnail = get_the_post_thumbnail_url($post_id, 'thumbnail') ?: '';

        // Get icon settings from action config
        $initial_icon = !empty($action['ts_acw_initial_icon']['value']) ? $action['ts_acw_initial_icon'] : ['library' => 'fa-solid', 'value' => 'fas fa-exchange-alt'];
        // Use reveal icon if set, otherwise fall back to initial icon (same icon in both states)
        $reveal_icon = !empty($action['ts_acw_reveal_icon']['value']) ? $action['ts_acw_reveal_icon'] : $initial_icon;

        // Get text - allow empty string (no default fallback)
        $initial_text = isset($action['ts_acw_initial_text']) ? $action['ts_acw_initial_text'] : '';
        $reveal_text = isset($action['ts_acw_reveal_text']) ? $action['ts_acw_reveal_text'] : '';
        ?>
        <li class="elementor-repeater-item-<?php echo esc_attr($action['_id']); ?> flexify ts-action"
            <?php if (!empty($action['ts_enable_tooltip']) && $action['ts_enable_tooltip'] === 'yes' && !empty($action['ts_tooltip_text'])): ?>
                tooltip-inactive="<?php echo esc_attr($action['ts_tooltip_text']); ?>"
            <?php endif; ?>
            <?php if (!empty($action['ts_acw_enable_tooltip']) && $action['ts_acw_enable_tooltip'] === 'yes' && !empty($action['ts_acw_tooltip_text'])): ?>
                tooltip-active="<?php echo esc_attr($action['ts_acw_tooltip_text']); ?>"
            <?php endif; ?>
        >
            <a href="#"
               class="ts-action-con vt-compare-action-btn"
               role="button"
               data-post-id="<?php echo esc_attr($post_id); ?>"
               data-post-type="<?php echo esc_attr($post_type); ?>"
               data-post-title="<?php echo esc_attr($post_title); ?>"
               data-post-thumbnail="<?php echo esc_attr($post_thumbnail); ?>">
                <span class="ts-initial">
                    <div class="ts-action-icon"><?php \Voxel\render_icon($initial_icon); ?></div>
                    <?php if ($initial_text !== ''): ?>
                        <?php echo esc_html($initial_text); ?>
                    <?php endif; ?>
                </span>
                <span class="ts-reveal">
                    <div class="ts-action-icon"><?php \Voxel\render_icon($reveal_icon); ?></div>
                    <?php if ($reveal_text !== ''): ?>
                        <?php echo esc_html($reveal_text); ?>
                    <?php endif; ?>
                </span>
            </a>
        </li>
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
