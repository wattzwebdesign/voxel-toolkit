<?php
/**
 * Compare Posts Button Widget
 *
 * Elementor widget for adding posts to comparison
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Compare_Posts_Button_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name
     *
     * @return string Widget name
     */
    public function get_name() {
        return 'voxel-toolkit-compare-button';
    }

    /**
     * Get widget title
     *
     * @return string Widget title
     */
    public function get_title() {
        return __('Compare Button (VT)', 'voxel-toolkit');
    }

    /**
     * Get widget icon
     *
     * @return string Widget icon
     */
    public function get_icon() {
        return 'eicon-exchange';
    }

    /**
     * Get widget categories
     *
     * @return array Widget categories
     */
    public function get_categories() {
        return array('voxel-toolkit');
    }

    /**
     * Get widget keywords
     *
     * @return array Widget keywords
     */
    public function get_keywords() {
        return array('compare', 'comparison', 'posts', 'voxel', 'button');
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    /**
     * Register content controls
     */
    private function register_content_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Content', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'button_text',
            array(
                'label' => __('Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Compare', 'voxel-toolkit'),
                'placeholder' => __('Compare', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'button_text_added',
            array(
                'label' => __('Button Text (Added)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Added to Comparison', 'voxel-toolkit'),
                'placeholder' => __('Added to Comparison', 'voxel-toolkit'),
                'description' => __('Text shown when post is already in comparison list', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'show_icon',
            array(
                'label' => __('Show Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'icon',
            array(
                'label' => __('Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => array(
                    'value' => 'fas fa-exchange-alt',
                    'library' => 'fa-solid',
                ),
                'condition' => array(
                    'show_icon' => 'yes',
                ),
            )
        );

        $this->add_control(
            'icon_added',
            array(
                'label' => __('Icon (Added State)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => array(
                    'value' => 'fas fa-check',
                    'library' => 'fa-solid',
                ),
                'condition' => array(
                    'show_icon' => 'yes',
                ),
            )
        );

        $this->add_control(
            'icon_position',
            array(
                'label' => __('Icon Position', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'left',
                'options' => array(
                    'left' => __('Left', 'voxel-toolkit'),
                    'right' => __('Right', 'voxel-toolkit'),
                ),
                'condition' => array(
                    'show_icon' => 'yes',
                ),
            )
        );

        $this->add_responsive_control(
            'align',
            array(
                'label' => __('Alignment', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => array(
                    'left' => array(
                        'title' => __('Left', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-left',
                    ),
                    'center' => array(
                        'title' => __('Center', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-center',
                    ),
                    'right' => array(
                        'title' => __('Right', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-right',
                    ),
                    'justify' => array(
                        'title' => __('Stretch', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-justify',
                    ),
                ),
                'default' => 'left',
                'selectors' => array(
                    '{{WRAPPER}}' => 'text-align: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'full_width',
            array(
                'label' => __('Full Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => '',
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-button' => 'width: 100%;',
                ),
            )
        );

        $this->end_controls_section();

        // Floating Bar Section
        $this->start_controls_section(
            'section_floating_bar',
            array(
                'label' => __('Floating Bar', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'floating_bar_info',
            array(
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => __('Style the floating comparison bar that appears when users add posts to compare. These styles apply globally.', 'voxel-toolkit'),
                'content_classes' => 'elementor-descriptor',
            )
        );

        $this->end_controls_section();
    }

    /**
     * Register style controls
     */
    private function register_style_controls() {
        // Button Style Section
        $this->start_controls_section(
            'section_button_style',
            array(
                'label' => __('Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .vt-compare-button',
            )
        );

        $this->add_responsive_control(
            'button_padding',
            array(
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'button_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        // Normal/Hover/Active Tabs
        $this->start_controls_tabs('button_style_tabs');

        // Normal Tab
        $this->start_controls_tab(
            'button_normal',
            array(
                'label' => __('Normal', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'button_text_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-button:not(.is-added)' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .vt-compare-button:not(.is-added) svg' => 'fill: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            array(
                'name' => 'button_background',
                'types' => array('classic', 'gradient'),
                'exclude' => array('image'),
                'selector' => '{{WRAPPER}} .vt-compare-button:not(.is-added)',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .vt-compare-button:not(.is-added)',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'button_box_shadow',
                'selector' => '{{WRAPPER}} .vt-compare-button:not(.is-added)',
            )
        );

        $this->end_controls_tab();

        // Hover Tab
        $this->start_controls_tab(
            'button_hover',
            array(
                'label' => __('Hover', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'button_text_color_hover',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-button:not(.is-added):hover' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .vt-compare-button:not(.is-added):hover svg' => 'fill: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            array(
                'name' => 'button_background_hover',
                'types' => array('classic', 'gradient'),
                'exclude' => array('image'),
                'selector' => '{{WRAPPER}} .vt-compare-button:not(.is-added):hover',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'button_border_hover',
                'selector' => '{{WRAPPER}} .vt-compare-button:not(.is-added):hover',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'button_box_shadow_hover',
                'selector' => '{{WRAPPER}} .vt-compare-button:not(.is-added):hover',
            )
        );

        $this->add_control(
            'hover_transition',
            array(
                'label' => __('Transition Duration', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 1000,
                        'step' => 50,
                    ),
                ),
                'default' => array(
                    'size' => 200,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-button' => 'transition: all {{SIZE}}ms ease;',
                ),
            )
        );

        $this->end_controls_tab();

        // Active Tab (Added state)
        $this->start_controls_tab(
            'button_active',
            array(
                'label' => __('Added', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'button_text_color_active',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-button.is-added' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .vt-compare-button.is-added svg' => 'fill: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            array(
                'name' => 'button_background_active',
                'types' => array('classic', 'gradient'),
                'exclude' => array('image'),
                'selector' => '{{WRAPPER}} .vt-compare-button.is-added',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'button_border_active',
                'selector' => '{{WRAPPER}} .vt-compare-button.is-added',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'button_box_shadow_active',
                'selector' => '{{WRAPPER}} .vt-compare-button.is-added',
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_control(
            'hr_icon',
            array(
                'type' => \Elementor\Controls_Manager::DIVIDER,
            )
        );

        // Icon Styling
        $this->add_control(
            'icon_heading',
            array(
                'label' => __('Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'condition' => array(
                    'show_icon' => 'yes',
                ),
            )
        );

        $this->add_responsive_control(
            'icon_size',
            array(
                'label' => __('Icon Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array(
                        'min' => 6,
                        'max' => 60,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-button .vt-compare-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .vt-compare-button .vt-compare-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array(
                    'show_icon' => 'yes',
                ),
            )
        );

        $this->add_responsive_control(
            'icon_spacing',
            array(
                'label' => __('Icon Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 50,
                    ),
                ),
                'default' => array(
                    'size' => 8,
                    'unit' => 'px',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-button .vt-compare-icon-left' => 'margin-right: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .vt-compare-button .vt-compare-icon-right' => 'margin-left: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array(
                    'show_icon' => 'yes',
                ),
            )
        );

        $this->end_controls_section();

        // Floating Bar Style Section
        $this->start_controls_section(
            'section_floating_bar_style',
            array(
                'label' => __('Floating Bar', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'bar_background_color',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    'body .vt-compare-floating-bar, body .vt-compare-bar-preview' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'bar_box_shadow',
                'selector' => 'body .vt-compare-floating-bar, body .vt-compare-bar-preview',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'bar_border',
                'selector' => 'body .vt-compare-floating-bar, body .vt-compare-bar-preview',
            )
        );

        $this->add_responsive_control(
            'bar_padding',
            array(
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'default' => array(
                    'top' => 15,
                    'right' => 20,
                    'bottom' => 15,
                    'left' => 20,
                    'unit' => 'px',
                ),
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-bar-content, body .vt-compare-bar-preview .vt-compare-bar-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'bar_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    'body .vt-compare-floating-bar, body .vt-compare-bar-preview' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'hr_bar_posts',
            array(
                'type' => \Elementor\Controls_Manager::DIVIDER,
            )
        );

        // Post Items
        $this->add_control(
            'bar_posts_heading',
            array(
                'label' => __('Post Items', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
            )
        );

        $this->add_control(
            'bar_post_bg_color',
            array(
                'label' => __('Item Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-bar-post, body .vt-compare-bar-preview .vt-compare-bar-post' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'bar_post_padding',
            array(
                'label' => __('Item Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-bar-post, body .vt-compare-bar-preview .vt-compare-bar-post' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'bar_post_border_radius',
            array(
                'label' => __('Item Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-bar-post, body .vt-compare-bar-preview .vt-compare-bar-post' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'bar_thumb_size',
            array(
                'label' => __('Thumbnail Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array(
                        'min' => 30,
                        'max' => 100,
                    ),
                ),
                'default' => array(
                    'size' => 50,
                    'unit' => 'px',
                ),
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-bar-post img, body .vt-compare-floating-bar .vt-compare-no-thumb, body .vt-compare-bar-preview .vt-compare-bar-post img, body .vt-compare-bar-preview .vt-compare-no-thumb' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'bar_thumb_border_radius',
            array(
                'label' => __('Thumbnail Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-bar-post img, body .vt-compare-floating-bar .vt-compare-no-thumb, body .vt-compare-bar-preview .vt-compare-bar-post img, body .vt-compare-bar-preview .vt-compare-no-thumb' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'bar_posts_gap',
            array(
                'label' => __('Gap Between Items', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 30,
                    ),
                ),
                'default' => array(
                    'size' => 10,
                    'unit' => 'px',
                ),
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-bar-posts, body .vt-compare-bar-preview .vt-compare-bar-posts' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'bar_post_title_color',
            array(
                'label' => __('Post Title Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-post-title, body .vt-compare-bar-preview .vt-compare-post-title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'bar_post_title_typography',
                'selector' => 'body .vt-compare-floating-bar .vt-compare-post-title, body .vt-compare-bar-preview .vt-compare-post-title',
            )
        );

        $this->add_control(
            'hr_bar_actions',
            array(
                'type' => \Elementor\Controls_Manager::DIVIDER,
            )
        );

        // View Button
        $this->add_control(
            'bar_view_btn_heading',
            array(
                'label' => __('View Comparison Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
            )
        );

        // View Button Tabs
        $this->start_controls_tabs('bar_view_btn_tabs');

        // Normal Tab
        $this->start_controls_tab(
            'bar_view_btn_normal',
            array(
                'label' => __('Normal', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'bar_view_btn_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-view-btn, body .vt-compare-bar-preview .vt-compare-view-btn' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'bar_view_btn_bg',
            array(
                'label' => __('Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-view-btn, body .vt-compare-bar-preview .vt-compare-view-btn' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'bar_view_btn_border',
                'selector' => 'body .vt-compare-floating-bar .vt-compare-view-btn, body .vt-compare-bar-preview .vt-compare-view-btn',
            )
        );

        $this->end_controls_tab();

        // Hover Tab
        $this->start_controls_tab(
            'bar_view_btn_hover',
            array(
                'label' => __('Hover', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'bar_view_btn_color_hover',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-view-btn:hover, body .vt-compare-bar-preview .vt-compare-view-btn:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'bar_view_btn_bg_hover',
            array(
                'label' => __('Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-view-btn:hover, body .vt-compare-bar-preview .vt-compare-view-btn:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'bar_view_btn_border_hover',
                'selector' => 'body .vt-compare-floating-bar .vt-compare-view-btn:hover, body .vt-compare-bar-preview .vt-compare-view-btn:hover',
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_control(
            'bar_view_btn_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'separator' => 'before',
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-view-btn, body .vt-compare-bar-preview .vt-compare-view-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'bar_view_btn_padding',
            array(
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-view-btn, body .vt-compare-bar-preview .vt-compare-view-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'hr_bar_clear_btn',
            array(
                'type' => \Elementor\Controls_Manager::DIVIDER,
            )
        );

        // Clear Button
        $this->add_control(
            'bar_clear_btn_heading',
            array(
                'label' => __('Clear All Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
            )
        );

        // Clear Button Tabs
        $this->start_controls_tabs('bar_clear_btn_tabs');

        // Normal Tab
        $this->start_controls_tab(
            'bar_clear_btn_normal',
            array(
                'label' => __('Normal', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'bar_clear_btn_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-clear-btn, body .vt-compare-bar-preview .vt-compare-clear-btn' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'bar_clear_btn_bg',
            array(
                'label' => __('Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-clear-btn, body .vt-compare-bar-preview .vt-compare-clear-btn' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'bar_clear_btn_border',
                'selector' => 'body .vt-compare-floating-bar .vt-compare-clear-btn, body .vt-compare-bar-preview .vt-compare-clear-btn',
            )
        );

        $this->end_controls_tab();

        // Hover Tab
        $this->start_controls_tab(
            'bar_clear_btn_hover',
            array(
                'label' => __('Hover', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'bar_clear_btn_color_hover',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-clear-btn:hover, body .vt-compare-bar-preview .vt-compare-clear-btn:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'bar_clear_btn_bg_hover',
            array(
                'label' => __('Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-clear-btn:hover, body .vt-compare-bar-preview .vt-compare-clear-btn:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'bar_clear_btn_border_hover',
                'selector' => 'body .vt-compare-floating-bar .vt-compare-clear-btn:hover, body .vt-compare-bar-preview .vt-compare-clear-btn:hover',
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_control(
            'bar_clear_btn_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'separator' => 'before',
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-clear-btn, body .vt-compare-bar-preview .vt-compare-clear-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'bar_clear_btn_padding',
            array(
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-clear-btn, body .vt-compare-bar-preview .vt-compare-clear-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'hr_bar_misc',
            array(
                'type' => \Elementor\Controls_Manager::DIVIDER,
            )
        );

        // Other
        $this->add_control(
            'bar_misc_heading',
            array(
                'label' => __('Other', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
            )
        );

        $this->add_control(
            'bar_count_color',
            array(
                'label' => __('Count Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-count, body .vt-compare-bar-preview .vt-compare-count' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'bar_remove_btn_color',
            array(
                'label' => __('Remove Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-remove-post, body .vt-compare-bar-preview .vt-compare-remove-post' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'bar_remove_btn_color_hover',
            array(
                'label' => __('Remove Icon Hover Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    'body .vt-compare-floating-bar .vt-compare-remove-post:hover, body .vt-compare-bar-preview .vt-compare-remove-post:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output
     */
    protected function render() {
        global $post;

        if (!$post) {
            return;
        }

        $settings = $this->get_settings_for_display();
        $post_id = $post->ID;
        $post_type = get_post_type($post);
        $post_title = get_the_title($post);
        $post_thumbnail = get_the_post_thumbnail_url($post, 'thumbnail');

        // Render normal icon
        $icon_html = '';
        if ($settings['show_icon'] === 'yes' && !empty($settings['icon']['value'])) {
            ob_start();
            \Elementor\Icons_Manager::render_icon($settings['icon'], array('aria-hidden' => 'true'));
            $icon_html = ob_get_clean();
        }

        // Render added state icon
        $icon_added_html = '';
        if ($settings['show_icon'] === 'yes' && !empty($settings['icon_added']['value'])) {
            ob_start();
            \Elementor\Icons_Manager::render_icon($settings['icon_added'], array('aria-hidden' => 'true'));
            $icon_added_html = ob_get_clean();
        }

        $icon_class = 'vt-compare-icon vt-compare-icon-' . $settings['icon_position'];
        ?>
        <button class="vt-compare-button"
                data-post-id="<?php echo esc_attr($post_id); ?>"
                data-post-type="<?php echo esc_attr($post_type); ?>"
                data-post-title="<?php echo esc_attr($post_title); ?>"
                data-post-thumbnail="<?php echo esc_attr($post_thumbnail); ?>"
                data-text-normal="<?php echo esc_attr($settings['button_text']); ?>"
                data-text-added="<?php echo esc_attr($settings['button_text_added']); ?>">
            <?php if ($settings['icon_position'] === 'left' && !empty($icon_html)): ?>
                <span class="<?php echo esc_attr($icon_class); ?> vt-compare-icon-normal"><?php echo $icon_html; ?></span>
                <span class="<?php echo esc_attr($icon_class); ?> vt-compare-icon-added" style="display:none;"><?php echo $icon_added_html; ?></span>
            <?php endif; ?>
            <span class="vt-compare-button-text"><?php echo esc_html($settings['button_text']); ?></span>
            <?php if ($settings['icon_position'] === 'right' && !empty($icon_html)): ?>
                <span class="<?php echo esc_attr($icon_class); ?> vt-compare-icon-normal"><?php echo $icon_html; ?></span>
                <span class="<?php echo esc_attr($icon_class); ?> vt-compare-icon-added" style="display:none;"><?php echo $icon_added_html; ?></span>
            <?php endif; ?>
        </button>
        <?php
    }
}
