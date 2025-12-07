<?php
/**
 * Table of Contents Widget
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Table_Of_Contents_Widget {

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
        add_shortcode('voxel_table_of_contents', array($this, 'render_shortcode'));

        // Enqueue styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * Register Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/elementor/widgets/table-of-contents.php';
        $widgets_manager->register(new \Voxel_Toolkit_Elementor_Table_Of_Contents());
    }

    /**
     * Get post type steps from wp_options
     */
    public static function get_post_type_steps($post_type_key, $include_fields = false) {
        // Get the voxel:post_types option
        $post_types = get_option('voxel:post_types', array());

        // Handle serialized data
        if (is_string($post_types)) {
            $post_types = maybe_unserialize($post_types);
        }

        // Try JSON decode if it's a JSON string
        if (is_string($post_types)) {
            $decoded = json_decode($post_types, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $post_types = $decoded;
            }
        }

        if (empty($post_types) || !is_array($post_types)) {
            return array();
        }

        // Check if the post type exists
        if (!isset($post_types[$post_type_key])) {
            return array();
        }

        $post_type_data = $post_types[$post_type_key];

        // Get fields array
        if (!isset($post_type_data['fields']) || !is_array($post_type_data['fields'])) {
            return array();
        }

        $steps = array();
        $current_step_index = -1;

        // UI field types that should be excluded from the fields list
        $ui_field_types = array('ui-step', 'ui-heading', 'ui-image', 'ui-html');

        // Loop through fields and find ui-step types
        foreach ($post_type_data['fields'] as $field) {
            $field_type = isset($field['type']) ? $field['type'] : '';

            if ($field_type === 'ui-step') {
                $current_step_index++;
                $steps[] = array(
                    'key' => isset($field['key']) ? $field['key'] : '',
                    'label' => isset($field['label']) ? $field['label'] : '',
                    'fields' => array(),
                );
            } elseif ($include_fields && $current_step_index >= 0 && !in_array($field_type, $ui_field_types)) {
                // Add field to current step (exclude UI-only fields)
                $steps[$current_step_index]['fields'][] = array(
                    'key' => isset($field['key']) ? $field['key'] : '',
                    'label' => isset($field['label']) ? $field['label'] : ucfirst(str_replace(array('-', '_'), ' ', isset($field['key']) ? $field['key'] : '')),
                    'type' => $field_type,
                    'required' => isset($field['required']) ? $field['required'] : false,
                );
            }
        }

        return $steps;
    }

    /**
     * Get all available post types
     */
    public static function get_available_post_types() {
        $post_types = get_option('voxel:post_types', array());

        // Handle serialized data
        if (is_string($post_types)) {
            $post_types = maybe_unserialize($post_types);
        }

        // Try JSON decode if it's a JSON string
        if (is_string($post_types)) {
            $decoded = json_decode($post_types, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $post_types = $decoded;
            }
        }

        if (empty($post_types) || !is_array($post_types)) {
            return array('' => __('No post types found', 'voxel-toolkit'));
        }

        $available = array();

        foreach ($post_types as $key => $data) {
            if (is_array($data) && isset($data['settings'])) {
                $name = isset($data['settings']['singular']) ? $data['settings']['singular'] : $key;
                $available[$key] = $name;
            } else {
                $available[$key] = $key;
            }
        }

        if (empty($available)) {
            return array('' => __('No post types found', 'voxel-toolkit'));
        }

        return $available;
    }

    /**
     * Render table of contents
     */
    public static function render_table_of_contents($args = array()) {
        $defaults = array(
            'post_type' => '',
            'title' => 'Table of Contents',
            'show_title' => true,
            'title_tag' => 'h3',
            'list_style' => 'numbered',
            'alignment' => 'left',
            'container_class' => '',
            // Field display options
            'show_fields' => false,
            'indicator_empty_color' => '#e0e0e0',
            'indicator_filled_color' => '#22c55e',
            'indicator_size' => 12,
            'field_indent' => 20,
            // Style options
            'title_color' => '#333333',
            'title_typography' => array(),
            'item_color' => '#666666',
            'item_hover_color' => '#333333',
            'item_active_color' => '#333333',
            'item_typography' => array(),
            'background_color' => '#f9f9f9',
            'border_color' => '#e0e0e0',
            'border_width' => 1,
            'border_radius' => 8,
            'padding' => array(
                'top' => 20,
                'right' => 20,
                'bottom' => 20,
                'left' => 20,
            ),
            'item_spacing' => 10,
        );

        $args = wp_parse_args($args, $defaults);

        // Get steps for the selected post type (include fields if showing them)
        $steps = self::get_post_type_steps($args['post_type'], $args['show_fields']);

        if (empty($steps)) {
            return '';
        }

        // Get current step from URL
        $current_step = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : '';

        // If no step in URL, make first step active
        if (empty($current_step) && !empty($steps)) {
            $current_step = $steps[0]['key'];
        }

        // Build CSS - only add inline styles if not using Elementor
        $use_inline_styles = !isset($args['_elementor']);

        $container_styles = array();
        if ($use_inline_styles) {
            $container_styles[] = 'text-align: ' . esc_attr($args['alignment']);
            $container_styles[] = 'background-color: ' . esc_attr($args['background_color']);
            $container_styles[] = 'border: ' . absint($args['border_width']) . 'px solid ' . esc_attr($args['border_color']);
            $container_styles[] = 'border-radius: ' . absint($args['border_radius']) . 'px';
            $container_styles[] = 'padding: ' . absint($args['padding']['top']) . 'px ' . absint($args['padding']['right']) . 'px ' . absint($args['padding']['bottom']) . 'px ' . absint($args['padding']['left']) . 'px';
        } else {
            $container_styles[] = 'text-align: ' . esc_attr($args['alignment']);
        }

        $title_styles = array();
        if ($use_inline_styles) {
            $title_styles[] = 'color: ' . esc_attr($args['title_color']);
        }
        $title_styles[] = 'margin: 0 0 15px 0';

        $list_styles = array();
        $list_styles[] = 'margin: 0';
        $list_styles[] = 'padding-left: ' . ($args['list_style'] === 'numbered' ? '25px' : '20px');
        $list_styles[] = 'list-style-type: ' . ($args['list_style'] === 'numbered' ? 'decimal' : ($args['list_style'] === 'bullets' ? 'disc' : 'none'));

        $item_styles = array();
        if ($use_inline_styles) {
            $item_styles[] = 'color: ' . esc_attr($args['item_color']);
            $item_styles[] = 'margin-bottom: ' . absint($args['item_spacing']) . 'px';
        }
        $item_styles[] = 'line-height: 1.6';

        // Generate unique ID for hover styles
        $unique_id = 'voxel-toc-' . uniqid();

        // Start output
        ob_start();
        ?>

        <?php if ($use_inline_styles): ?>
        <style>
            .<?php echo esc_attr($unique_id); ?> .voxel-toc-item:hover {
                color: <?php echo esc_attr($args['item_hover_color']); ?>;
            }
            .<?php echo esc_attr($unique_id); ?> .voxel-toc-item.active {
                color: <?php echo esc_attr($args['item_active_color']); ?>;
                font-weight: 600;
            }
        </style>
        <?php else: ?>
        <style>
            .<?php echo esc_attr($unique_id); ?> .voxel-toc-item.active {
                font-weight: 600;
            }
        </style>
        <?php endif; ?>

        <div class="voxel-table-of-contents <?php echo esc_attr($unique_id); ?> <?php echo esc_attr($args['container_class']); ?><?php echo $args['show_fields'] ? ' vt-toc-with-fields' : ''; ?>"
             style="<?php echo esc_attr(implode('; ', $container_styles)); ?>"
             data-current-step="<?php echo esc_attr($current_step); ?>"
             data-show-fields="<?php echo $args['show_fields'] ? 'true' : 'false'; ?>">

            <?php if ($args['show_title'] && !empty($args['title'])): ?>
                <<?php echo esc_attr($args['title_tag']); ?> class="voxel-toc-title" style="<?php echo esc_attr(implode('; ', $title_styles)); ?>">
                    <?php echo esc_html($args['title']); ?>
                </<?php echo esc_attr($args['title_tag']); ?>>
            <?php endif; ?>

            <<?php echo $args['list_style'] === 'none' ? 'div' : 'ol'; ?> class="voxel-toc-list" style="<?php echo esc_attr(implode('; ', $list_styles)); ?>">
                <?php foreach ($steps as $step): ?>
                    <?php
                    $is_active = ($current_step === $step['key']);
                    $item_class = 'voxel-toc-item' . ($is_active ? ' active' : '');
                    $has_fields = $args['show_fields'] && !empty($step['fields']);
                    ?>
                    <li class="<?php echo esc_attr($item_class); ?><?php echo $has_fields ? ' vt-toc-has-fields' : ''; ?>"
                        style="<?php echo esc_attr(implode('; ', $item_styles)); ?>"
                        data-step-key="<?php echo esc_attr($step['key']); ?>">
                        <span class="vt-toc-step-label"><?php echo esc_html($step['label']); ?></span>
                        <?php if ($has_fields): ?>
                            <ul class="vt-toc-fields">
                                <?php foreach ($step['fields'] as $field): ?>
                                    <li class="vt-toc-field" data-field-key="<?php echo esc_attr($field['key']); ?>" data-field-type="<?php echo esc_attr($field['type']); ?>">
                                        <span class="vt-toc-field-indicator"></span>
                                        <span class="vt-toc-field-label"><?php echo esc_html($field['label']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </<?php echo $args['list_style'] === 'none' ? 'div' : 'ol'; ?>>

        </div>

        <?php
        return ob_get_clean();
    }

    /**
     * Render shortcode
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_type' => '',
            'title' => 'Table of Contents',
            'show_title' => 'yes',
            'alignment' => 'left',
        ), $atts);

        $atts['show_title'] = ($atts['show_title'] === 'yes');

        return self::render_table_of_contents($atts);
    }

    /**
     * Enqueue styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'voxel-toolkit-table-of-contents',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/table-of-contents.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        wp_enqueue_script(
            'voxel-toolkit-table-of-contents',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/table-of-contents.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );
    }
}
