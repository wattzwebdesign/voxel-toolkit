<?php
/**
 * Active Filters Widget
 *
 * Display active search filters from URL parameters with remove functionality
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Active_Filters_Widget extends \Elementor\Widget_Base {

    /**
     * Hidden parameters that should never be displayed
     */
    private $always_hidden_params = array('pg', 'per_page', '_wpnonce', 'action');

    /**
     * Constructor
     */
    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);

        wp_register_style(
            'vt-active-filters',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/active-filters.css',
            [],
            VOXEL_TOOLKIT_VERSION
        );

        wp_register_script(
            'vt-active-filters',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/active-filters.js',
            [],
            VOXEL_TOOLKIT_VERSION,
            true
        );
    }

    /**
     * Get style dependencies
     */
    public function get_style_depends() {
        return ['vt-active-filters'];
    }

    /**
     * Get script dependencies
     */
    public function get_script_depends() {
        return ['vt-active-filters'];
    }

    /**
     * Get widget name
     */
    public function get_name() {
        return 'voxel-toolkit-active-filters';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __('Active Filters (VT)', 'voxel-toolkit');
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-filter';
    }

    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['voxel-toolkit', 'general'];
    }

    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return ['filter', 'filters', 'active', 'search', 'voxel', 'tags', 'clear'];
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    /**
     * Register content tab controls
     */
    private function register_content_controls() {
        // General Settings Section
        $this->start_controls_section(
            'general_section',
            [
                'label' => __('General Settings', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_preview',
            [
                'label' => __('Show Preview', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Show placeholder filters for styling in the editor', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'heading_text',
            [
                'label' => __('Heading', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('Active Filters', 'voxel-toolkit'),
                'description' => __('Optional heading above the filters', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'layout_direction',
            [
                'label' => __('Layout', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'horizontal',
                'options' => [
                    'horizontal' => __('Horizontal', 'voxel-toolkit'),
                    'vertical' => __('Vertical', 'voxel-toolkit'),
                ],
            ]
        );

        $this->add_control(
            'hide_when_empty',
            [
                'label' => __('Hide When No Filters', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'empty_message',
            [
                'label' => __('Empty State Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('No filters applied', 'voxel-toolkit'),
                'condition' => [
                    'hide_when_empty' => '',
                ],
            ]
        );

        $this->end_controls_section();

        // Filter Display Section
        $this->start_controls_section(
            'filter_section',
            [
                'label' => __('Filter Display', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'hide_type',
            [
                'label' => __('Hide Post Type Filter', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'hide_sort',
            [
                'label' => __('Hide Sort Filter', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
            ]
        );

        $this->add_control(
            'exclude_params',
            [
                'label' => __('Exclude Parameters', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('param1, param2', 'voxel-toolkit'),
                'description' => __('Comma-separated list of URL parameters to hide', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'remove_icon',
            [
                'label' => __('Remove Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'times',
                'options' => [
                    'times' => __('× (Times)', 'voxel-toolkit'),
                    'close' => __('✕ (Close)', 'voxel-toolkit'),
                    'x' => __('x (Letter)', 'voxel-toolkit'),
                    'none' => __('None', 'voxel-toolkit'),
                ],
            ]
        );

        $this->end_controls_section();

        // Clear All Section
        $this->start_controls_section(
            'clear_all_section',
            [
                'label' => __('Clear All Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_clear_all',
            [
                'label' => __('Show Clear All Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'clear_all_text',
            [
                'label' => __('Clear All Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Clear All', 'voxel-toolkit'),
                'condition' => [
                    'show_clear_all' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'clear_all_position',
            [
                'label' => __('Clear All Position', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'after',
                'options' => [
                    'before' => __('Before Filters', 'voxel-toolkit'),
                    'after' => __('After Filters', 'voxel-toolkit'),
                ],
                'condition' => [
                    'show_clear_all' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        // Labels Section
        $this->start_controls_section(
            'labels_section',
            [
                'label' => __('Filter Labels', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_filter_name',
            [
                'label' => __('Show Filter Name', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Display "Price: $0 - $300" vs "$0 - $300"', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'keywords_label',
            [
                'label' => __('Keywords Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Search', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'sort_label',
            [
                'label' => __('Sort Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Sort', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'range_separator',
            [
                'label' => __('Range Separator', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => ' - ',
                'description' => __('Text between min and max values', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Register style tab controls
     */
    private function register_style_controls() {
        // Heading Styling Section
        $this->start_controls_section(
            'heading_style_section',
            [
                'label' => __('Heading', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'heading_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .vt-active-filters-heading' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'heading_typography',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-active-filters-heading',
            ]
        );

        $this->add_responsive_control(
            'heading_spacing',
            [
                'label' => __('Bottom Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                    'em' => [
                        'min' => 0,
                        'max' => 3,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-active-filters-heading' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Tag Styling Section
        $this->start_controls_section(
            'tag_style_section',
            [
                'label' => __('Filter Tags', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'tag_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f0f0f0',
                'selectors' => [
                    '{{WRAPPER}} .vt-active-filter' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'tag_hover_background',
            [
                'label' => __('Hover Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e0e0e0',
                'selectors' => [
                    '{{WRAPPER}} .vt-active-filter:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'tag_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .vt-active-filter' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'tag_hover_text_color',
            [
                'label' => __('Hover Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .vt-active-filter:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'tag_typography',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-active-filter',
            ]
        );

        $this->add_responsive_control(
            'tag_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => [
                    'top' => '6',
                    'right' => '12',
                    'bottom' => '6',
                    'left' => '12',
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-active-filter' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'tag_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                    '%' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-active-filter' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'tag_border',
                'label' => __('Border', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-active-filter',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'tag_box_shadow',
                'label' => __('Box Shadow', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-active-filter',
            ]
        );

        $this->end_controls_section();

        // Remove Icon Styling Section
        $this->start_controls_section(
            'remove_icon_style_section',
            [
                'label' => __('Remove Icon', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'remove_icon_color',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .vt-filter-remove' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'remove_icon_hover_color',
            [
                'label' => __('Hover Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .vt-active-filter:hover .vt-filter-remove' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'remove_icon_size',
            [
                'label' => __('Icon Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 8,
                        'max' => 32,
                    ],
                    'em' => [
                        'min' => 0.5,
                        'max' => 2,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 16,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-filter-remove' => 'font-size: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'remove_icon_spacing',
            [
                'label' => __('Icon Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 20,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 6,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-filter-remove' => 'margin-left: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Clear All Styling Section
        $this->start_controls_section(
            'clear_all_style_section',
            [
                'label' => __('Clear All Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'clear_all_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#666666',
                'selectors' => [
                    '{{WRAPPER}} .vt-clear-all-filters' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'clear_all_hover_color',
            [
                'label' => __('Hover Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .vt-clear-all-filters:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'clear_all_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .vt-clear-all-filters' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'clear_all_hover_background',
            [
                'label' => __('Hover Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .vt-clear-all-filters:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'clear_all_typography',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-clear-all-filters',
            ]
        );

        $this->add_responsive_control(
            'clear_all_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-clear-all-filters' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'clear_all_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-clear-all-filters' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Layout Section
        $this->start_controls_section(
            'layout_section',
            [
                'label' => __('Layout', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'tag_gap',
            [
                'label' => __('Gap Between Tags', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                    'em' => [
                        'min' => 0,
                        'max' => 2,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-active-filters-list' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'alignment',
            [
                'label' => __('Alignment', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'flex-start' => [
                        'title' => __('Left', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Center', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'flex-end' => [
                        'title' => __('Right', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'flex-start',
                'selectors' => [
                    '{{WRAPPER}} .vt-active-filters-inner' => 'justify-content: {{VALUE}};',
                    '{{WRAPPER}} .vt-active-filters-list' => 'justify-content: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Container Styling Section
        $this->start_controls_section(
            'container_style_section',
            [
                'label' => __('Container', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'container_background',
                'label' => __('Background', 'voxel-toolkit'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .vt-active-filters-widget',
            ]
        );

        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-active-filters-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'container_margin',
            [
                'label' => __('Margin', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-active-filters-widget' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'label' => __('Border', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-active-filters-widget',
            ]
        );

        $this->add_responsive_control(
            'container_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-active-filters-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Parse URL filters from GET parameters
     *
     * @return array Array of filter data
     */
    private function parse_url_filters() {
        $settings = $this->get_settings_for_display();
        $filters = [];

        // Get hidden params from settings
        $hidden_params = $this->always_hidden_params;

        // Add type if hidden
        if ($settings['hide_type'] === 'yes') {
            $hidden_params[] = 'type';
        }

        // Add sort if hidden
        if ($settings['hide_sort'] === 'yes') {
            $hidden_params[] = 'sort';
        }

        // Add custom excluded params
        if (!empty($settings['exclude_params'])) {
            $custom_excludes = array_map('trim', explode(',', $settings['exclude_params']));
            $hidden_params = array_merge($hidden_params, $custom_excludes);
        }

        foreach ($_GET as $key => $value) {
            // Skip hidden params
            if (in_array($key, $hidden_params)) {
                continue;
            }

            // Skip empty values
            if ($value === '' || $value === null) {
                continue;
            }

            $filters[] = [
                'key' => $key,
                'value' => $value,
                'label' => $this->format_filter_label($key, $value),
                'remove_url' => $this->get_remove_url($key),
            ];
        }

        return $filters;
    }

    /**
     * Format filter value for human-readable display
     *
     * @param string $key Filter key
     * @param string $value Filter value
     * @return string Formatted label
     */
    private function format_filter_label($key, $value) {
        $settings = $this->get_settings_for_display();
        $show_name = $settings['show_filter_name'] === 'yes';
        $range_separator = !empty($settings['range_separator']) ? $settings['range_separator'] : ' - ';

        // Decode URL-encoded value
        $value = urldecode($value);

        // Format the value based on type
        $formatted_value = $value;

        // Range format: "0..300" → "0 - 300"
        if (strpos($value, '..') !== false) {
            list($min, $max) = explode('..', $value, 2);
            $formatted_value = $min . $range_separator . $max;
        }
        // Terms format: "slug1,slug2" → "Slug1, Slug2"
        elseif (strpos($value, ',') !== false) {
            $terms = array_map(function($term) {
                return ucfirst(str_replace('-', ' ', trim($term)));
            }, explode(',', $value));
            $formatted_value = implode(', ', $terms);
        }
        // Boolean/switcher: "1" → "Yes"
        elseif ($value === '1') {
            $formatted_value = __('Yes', 'voxel-toolkit');
        }
        elseif ($value === '0') {
            $formatted_value = __('No', 'voxel-toolkit');
        }
        // Default: Capitalize and replace dashes
        else {
            $formatted_value = ucfirst(str_replace('-', ' ', $value));
        }

        // Build label with or without filter name
        if ($show_name) {
            $label_prefix = $this->get_filter_label_prefix($key);
            return $label_prefix . ': ' . $formatted_value;
        }

        return $formatted_value;
    }

    /**
     * Get label prefix for a filter key
     *
     * @param string $key Filter key
     * @return string Label prefix
     */
    private function get_filter_label_prefix($key) {
        $settings = $this->get_settings_for_display();

        // Custom labels for specific keys
        $custom_labels = [
            'keywords' => !empty($settings['keywords_label']) ? $settings['keywords_label'] : __('Search', 'voxel-toolkit'),
            'sort' => !empty($settings['sort_label']) ? $settings['sort_label'] : __('Sort', 'voxel-toolkit'),
        ];

        if (isset($custom_labels[$key])) {
            return $custom_labels[$key];
        }

        // Default: capitalize and replace dashes/underscores
        return ucfirst(str_replace(['-', '_'], ' ', $key));
    }

    /**
     * Build URL without a specific filter parameter
     *
     * @param string $key_to_remove Parameter key to remove
     * @return string URL without the filter
     */
    private function get_remove_url($key_to_remove) {
        $params = $_GET;
        unset($params[$key_to_remove]);

        // Get base URL (without query string)
        $base_url = strtok($_SERVER['REQUEST_URI'], '?');

        if (empty($params)) {
            return $base_url;
        }

        return $base_url . '?' . http_build_query($params);
    }

    /**
     * Get clear all URL (base URL without any filters)
     *
     * @return string Base URL
     */
    private function get_clear_all_url() {
        return strtok($_SERVER['REQUEST_URI'], '?');
    }

    /**
     * Get preview/placeholder filters for editor styling
     *
     * @return array Array of placeholder filter data
     */
    private function get_preview_filters() {
        $settings = $this->get_settings_for_display();
        $show_name = $settings['show_filter_name'] === 'yes';
        $range_separator = !empty($settings['range_separator']) ? $settings['range_separator'] : ' - ';

        $filters = [];

        // Price range filter
        $price_label = $show_name ? __('Price', 'voxel-toolkit') . ': $0' . $range_separator . '$500' : '$0' . $range_separator . '$500';
        $filters[] = [
            'key' => 'price',
            'value' => '0..500',
            'label' => $price_label,
            'remove_url' => '#',
        ];

        // Category filter
        $category_label = $show_name ? __('Category', 'voxel-toolkit') . ': ' . __('Apartments, Houses', 'voxel-toolkit') : __('Apartments, Houses', 'voxel-toolkit');
        $filters[] = [
            'key' => 'category',
            'value' => 'apartments,houses',
            'label' => $category_label,
            'remove_url' => '#',
        ];

        // Keywords filter
        $keywords_label_prefix = !empty($settings['keywords_label']) ? $settings['keywords_label'] : __('Search', 'voxel-toolkit');
        $keywords_label = $show_name ? $keywords_label_prefix . ': ' . __('beach house', 'voxel-toolkit') : __('beach house', 'voxel-toolkit');
        $filters[] = [
            'key' => 'keywords',
            'value' => 'beach house',
            'label' => $keywords_label,
            'remove_url' => '#',
        ];

        // Featured filter
        $featured_label = $show_name ? __('Featured', 'voxel-toolkit') . ': ' . __('Yes', 'voxel-toolkit') : __('Yes', 'voxel-toolkit');
        $filters[] = [
            'key' => 'featured',
            'value' => '1',
            'label' => $featured_label,
            'remove_url' => '#',
        ];

        return $filters;
    }

    /**
     * Check if we should show preview
     *
     * @return bool
     */
    private function should_show_preview() {
        $settings = $this->get_settings_for_display();
        return $settings['show_preview'] === 'yes';
    }

    /**
     * Get remove icon character
     *
     * @return string Icon character
     */
    private function get_remove_icon() {
        $settings = $this->get_settings_for_display();

        switch ($settings['remove_icon']) {
            case 'times':
                return '&times;';
            case 'close':
                return '&#10005;';
            case 'x':
                return 'x';
            case 'none':
            default:
                return '';
        }
    }

    /**
     * Render the widget
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // Use preview filters if enabled, otherwise parse from URL
        $is_preview = $this->should_show_preview();
        $filters = $is_preview ? $this->get_preview_filters() : $this->parse_url_filters();

        // Handle empty state (only when not in preview mode)
        if (empty($filters) && !$is_preview) {
            if ($settings['hide_when_empty'] === 'yes') {
                return;
            }

            if (!empty($settings['empty_message'])) {
                echo '<div class="vt-active-filters-widget vt-active-filters-empty">';
                echo '<span class="vt-filters-empty-message">' . esc_html($settings['empty_message']) . '</span>';
                echo '</div>';
            }
            return;
        }

        $remove_icon = $this->get_remove_icon();
        $show_clear_all = $settings['show_clear_all'] === 'yes';
        $clear_all_position = $settings['clear_all_position'];
        $clear_all_text = !empty($settings['clear_all_text']) ? $settings['clear_all_text'] : __('Clear All', 'voxel-toolkit');
        $clear_all_url = $this->get_clear_all_url();
        $heading_text = !empty($settings['heading_text']) ? $settings['heading_text'] : '';
        $layout_direction = !empty($settings['layout_direction']) ? $settings['layout_direction'] : 'horizontal';
        $layout_class = $layout_direction === 'vertical' ? ' vt-layout-vertical' : '';

        ?>
        <div class="vt-active-filters-widget<?php echo esc_attr($layout_class); ?>">
            <?php if ($heading_text): ?>
                <div class="vt-active-filters-heading"><?php echo esc_html($heading_text); ?></div>
            <?php endif; ?>

            <div class="vt-active-filters-inner">
                <?php if ($show_clear_all && $clear_all_position === 'before'): ?>
                    <a href="<?php echo esc_url($clear_all_url); ?>" class="vt-clear-all-filters">
                        <?php echo esc_html($clear_all_text); ?>
                    </a>
                <?php endif; ?>

                <div class="vt-active-filters-list">
                    <?php foreach ($filters as $filter): ?>
                        <a href="<?php echo esc_url($filter['remove_url']); ?>" class="vt-active-filter" data-filter-key="<?php echo esc_attr($filter['key']); ?>">
                            <span class="vt-filter-label"><?php echo esc_html($filter['label']); ?></span>
                            <?php if ($remove_icon): ?>
                                <span class="vt-filter-remove"><?php echo $remove_icon; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($show_clear_all && $clear_all_position === 'after'): ?>
                    <a href="<?php echo esc_url($clear_all_url); ?>" class="vt-clear-all-filters">
                        <?php echo esc_html($clear_all_text); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
