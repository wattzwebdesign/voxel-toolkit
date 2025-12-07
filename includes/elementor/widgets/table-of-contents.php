<?php
/**
 * Elementor Table of Contents Widget
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Elementor_Table_Of_Contents extends \Elementor\Widget_Base {

    /**
     * Get widget name
     */
    public function get_name() {
        return 'voxel-table-of-contents';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __('Table of Contents (VT)', 'voxel-toolkit');
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-bullet-list';
    }

    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['voxel-toolkit'];
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        // Post Type Selection
        $post_types = Voxel_Toolkit_Table_Of_Contents_Widget::get_available_post_types();

        $this->add_control(
            'post_type',
            [
                'label' => __('Post Type', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $post_types,
                'default' => !empty($post_types) ? array_key_first($post_types) : '',
                'description' => __('Select the post type to display steps from', 'voxel-toolkit'),
            ]
        );

        // Show Title
        $this->add_control(
            'show_title',
            [
                'label' => __('Show Title', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'voxel-toolkit'),
                'label_off' => __('Hide', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        // Title Text
        $this->add_control(
            'title',
            [
                'label' => __('Title', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Table of Contents', 'voxel-toolkit'),
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );

        // Title HTML Tag
        $this->add_control(
            'title_tag',
            [
                'label' => __('Title HTML Tag', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'h1' => 'H1',
                    'h2' => 'H2',
                    'h3' => 'H3',
                    'h4' => 'H4',
                    'h5' => 'H5',
                    'h6' => 'H6',
                    'div' => 'div',
                    'span' => 'span',
                    'p' => 'p',
                ],
                'default' => 'h3',
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );

        // List Style
        $this->add_control(
            'list_style',
            [
                'label' => __('List Style', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'numbered' => __('Numbered', 'voxel-toolkit'),
                    'bullets' => __('Bullets', 'voxel-toolkit'),
                    'none' => __('None', 'voxel-toolkit'),
                ],
                'default' => 'numbered',
            ]
        );

        // Alignment
        $this->add_control(
            'alignment',
            [
                'label' => __('Alignment', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __('Left', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Center', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => __('Right', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'left',
                'toggle' => true,
            ]
        );

        // Show Fields Toggle
        $this->add_control(
            'show_fields_heading',
            [
                'label' => __('Field Indicators', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'show_fields',
            [
                'label' => __('Show Fields Under Steps', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'voxel-toolkit'),
                'label_off' => __('Hide', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Display individual fields under each step with completion indicators', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // Title Style Section
        $this->start_controls_section(
            'title_style_section',
            [
                'label' => __('Title Style', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .voxel-toc-title' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .voxel-toc-title',
            ]
        );

        $this->add_responsive_control(
            'title_spacing',
            [
                'label' => __('Bottom Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'size' => 15,
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-toc-title' => 'margin-bottom: {{SIZE}}{{UNIT}}',
                ],
            ]
        );

        $this->end_controls_section();

        // Items Style Section
        $this->start_controls_section(
            'items_style_section',
            [
                'label' => __('Items Style', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'item_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#666666',
                'selectors' => [
                    '{{WRAPPER}} .voxel-toc-item' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'item_hover_color',
            [
                'label' => __('Hover Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .voxel-toc-item:hover' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'item_active_color',
            [
                'label' => __('Active Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .voxel-toc-item.active' => 'color: {{VALUE}}',
                ],
                'description' => __('Color for the currently active step (based on URL parameter)', 'voxel-toolkit'),
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'item_typography',
                'selector' => '{{WRAPPER}} .voxel-toc-item',
            ]
        );

        $this->add_responsive_control(
            'item_spacing',
            [
                'label' => __('Item Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-toc-item' => 'margin-bottom: {{SIZE}}{{UNIT}}',
                ],
            ]
        );

        $this->end_controls_section();

        // Container Style Section
        $this->start_controls_section(
            'container_style_section',
            [
                'label' => __('Container Style', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'background_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f9f9f9',
                'selectors' => [
                    '{{WRAPPER}} .voxel-table-of-contents' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'border',
                'selector' => '{{WRAPPER}} .voxel-table-of-contents',
            ]
        );

        $this->add_responsive_control(
            'border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .voxel-table-of-contents' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'default' => [
                    'top' => 20,
                    'right' => 20,
                    'bottom' => 20,
                    'left' => 20,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-table-of-contents' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'box_shadow',
                'selector' => '{{WRAPPER}} .voxel-table-of-contents',
            ]
        );

        $this->end_controls_section();

        // Field Indicators Style Section
        $this->start_controls_section(
            'fields_style_section',
            [
                'label' => __('Field Indicators', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_fields' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'indicator_empty_color',
            [
                'label' => __('Empty Indicator Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e0e0e0',
                'selectors' => [
                    '{{WRAPPER}} .vt-toc-field-indicator' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'indicator_filled_color',
            [
                'label' => __('Filled Indicator Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#22c55e',
                'selectors' => [
                    '{{WRAPPER}} .vt-toc-field.is-filled .vt-toc-field-indicator' => 'background-color: {{VALUE}}; border-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'indicator_size',
            [
                'label' => __('Indicator Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 8,
                        'max' => 24,
                    ],
                ],
                'default' => [
                    'size' => 12,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-toc-field-indicator' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}',
                ],
            ]
        );

        $this->add_control(
            'field_text_color',
            [
                'label' => __('Field Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#888888',
                'selectors' => [
                    '{{WRAPPER}} .vt-toc-field-label' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'field_filled_text_color',
            [
                'label' => __('Filled Field Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .vt-toc-field.is-filled .vt-toc-field-label' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'field_typography',
                'selector' => '{{WRAPPER}} .vt-toc-field-label',
            ]
        );

        $this->add_responsive_control(
            'field_indent',
            [
                'label' => __('Field Indent', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-toc-fields' => 'padding-left: {{SIZE}}{{UNIT}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'field_spacing',
            [
                'label' => __('Field Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 20,
                    ],
                ],
                'default' => [
                    'size' => 6,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-toc-field' => 'margin-bottom: {{SIZE}}{{UNIT}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'fields_top_spacing',
            [
                'label' => __('Fields Top Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                ],
                'default' => [
                    'size' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-toc-fields' => 'margin-top: {{SIZE}}{{UNIT}}',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        $args = array(
            'post_type' => $settings['post_type'],
            'title' => $settings['title'],
            'show_title' => ($settings['show_title'] === 'yes'),
            'title_tag' => $settings['title_tag'],
            'list_style' => $settings['list_style'],
            'alignment' => $settings['alignment'],
            'show_fields' => ($settings['show_fields'] === 'yes'),
            '_elementor' => true, // Flag to disable inline styles
        );

        echo Voxel_Toolkit_Table_Of_Contents_Widget::render_table_of_contents($args);
    }
}
