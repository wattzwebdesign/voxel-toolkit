<?php
/**
 * Onboarding Widget
 *
 * Interactive onboarding tours using intro.js
 *
 * @package Voxel_Toolkit
 */

namespace Voxel_Toolkit\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

class Onboarding_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name
     */
    public function get_name() {
        return 'voxel-onboarding';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __('Onboarding (VT)', 'voxel-toolkit');
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-navigator';
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
        // Content Tab - Tour Behavior Section
        $this->start_controls_section(
            'section_tour_behavior',
            [
                'label' => __('Tour Behavior', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'preview_mode',
            [
                'label' => __('Preview Mode', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('On', 'voxel-toolkit'),
                'label_off' => __('Off', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => __('Enable this while editing to prevent auto-start. Disable before publishing.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'auto_start',
            [
                'label' => __('Auto-start Tour', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => __('Automatically start the tour when the page loads (only shows once per session).', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'auto_start_delay',
            [
                'label' => __('Auto-start Delay (seconds)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['s'],
                'range' => [
                    's' => [
                        'min' => 0,
                        'max' => 5,
                        'step' => 0.5,
                    ],
                ],
                'default' => [
                    'unit' => 's',
                    'size' => 0.5,
                ],
                'condition' => [
                    'auto_start' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_start_button',
            [
                'label' => __('Show Start Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Display a button to manually start the tour.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'start_button_text',
            [
                'label' => __('Start Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Start Tour', 'voxel-toolkit'),
                'condition' => [
                    'show_start_button' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'reset_tour_divider',
            [
                'type' => \Elementor\Controls_Manager::DIVIDER,
            ]
        );

        $this->add_control(
            'reset_tour_version',
            [
                'label' => __('Tour Version', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HIDDEN,
                'default' => 1,
            ]
        );

        $this->add_control(
            'reset_tour_button',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => __('<div style="margin-bottom: 10px;"><strong>Current Tour Version:</strong> <span class="voxel-tour-version-display">1</span></div><button type="button" class="elementor-button elementor-button-default voxel-reset-tour-btn" style="width: 100%;">Reset Tour for All Users</button><div style="margin-top: 10px; font-size: 12px; color: #7a7a7a;">Click this button to increment the tour version and make the tour appear again for all users, even if they\'ve already completed it.</div>', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // Content Tab - Tour Options Section
        $this->start_controls_section(
            'section_tour_options',
            [
                'label' => __('Tour Options', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_progress',
            [
                'label' => __('Show Progress Bar', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Display a progress indicator showing current step.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'show_bullets',
            [
                'label' => __('Show Step Bullets', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Display bullet points for each tour step.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'exit_on_overlay',
            [
                'label' => __('Exit on Overlay Click', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => __('Allow users to exit tour by clicking the overlay.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'show_step_numbers',
            [
                'label' => __('Show Step Numbers', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Display step numbers in tooltips.', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // Content Tab - Tour Steps Section
        $this->start_controls_section(
            'section_tour_steps',
            [
                'label' => __('Tour Steps', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'steps_help',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => __('<strong>How to target elements:</strong><br>
                    1. Assign a CSS ID to any Elementor widget in Advanced &gt; CSS ID (e.g., "my-element")<br>
                    2. Enter "#my-element" in the Target Selector field below<br>
                    3. Or use existing class names like ".my-class"', 'voxel-toolkit'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            ]
        );

        $repeater = new \Elementor\Repeater();

        $repeater->add_control(
            'step_title',
            [
                'label' => __('Step Title', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Step Title', 'voxel-toolkit'),
                'label_block' => true,
            ]
        );

        $repeater->add_control(
            'step_content',
            [
                'label' => __('Step Content', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::WYSIWYG,
                'default' => __('Describe this step...', 'voxel-toolkit'),
            ]
        );

        $repeater->add_control(
            'target_selector',
            [
                'label' => __('Target Element (CSS Selector)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'label_block' => true,
                'description' => __('Enter CSS selector (e.g., #my-element or .my-class). Leave empty for full-page overlay.', 'voxel-toolkit'),
            ]
        );

        $repeater->add_control(
            'tooltip_position',
            [
                'label' => __('Tooltip Position', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'bottom',
                'options' => [
                    'top' => __('Top', 'voxel-toolkit'),
                    'bottom' => __('Bottom', 'voxel-toolkit'),
                    'left' => __('Left', 'voxel-toolkit'),
                    'right' => __('Right', 'voxel-toolkit'),
                    'auto' => __('Auto', 'voxel-toolkit'),
                ],
            ]
        );

        $this->add_control(
            'tour_steps',
            [
                'label' => __('Steps', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => [
                    [
                        'step_title' => __('Welcome!', 'voxel-toolkit'),
                        'step_content' => __('Welcome to our site. Let us show you around!', 'voxel-toolkit'),
                        'target_selector' => '',
                        'tooltip_position' => 'bottom',
                    ],
                ],
                'title_field' => '{{{ step_title }}}',
            ]
        );

        $this->end_controls_section();

        // Style Tab - Start Button Section
        $this->start_controls_section(
            'section_button_style',
            [
                'label' => __('Start Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_start_button' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .voxel-tour-start-btn',
            ]
        );

        $this->start_controls_tabs('button_style_tabs');

        $this->start_controls_tab(
            'button_normal_tab',
            [
                'label' => __('Normal', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .voxel-tour-start-btn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_background_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#1e3a5f',
                'selectors' => [
                    '{{WRAPPER}} .voxel-tour-start-btn' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'button_hover_tab',
            [
                'label' => __('Hover', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'button_hover_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .voxel-tour-start-btn:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_hover_background_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2a5080',
                'selectors' => [
                    '{{WRAPPER}} .voxel-tour-start-btn:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .voxel-tour-start-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => '12',
                    'right' => '24',
                    'bottom' => '12',
                    'left' => '24',
                    'unit' => 'px',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'button_border_radius',
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
                'default' => [
                    'unit' => 'px',
                    'size' => 4,
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-tour-start-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'label' => __('Border', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .voxel-tour-start-btn',
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_box_shadow',
                'label' => __('Box Shadow', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .voxel-tour-start-btn',
            ]
        );

        $this->end_controls_section();

        // Style Tab - Tooltip Section
        $this->start_controls_section(
            'section_tooltip_style',
            [
                'label' => __('Tooltip', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'tooltip_background_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '.introjs-tooltip' => 'background-color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'tooltip_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#000000',
                'selectors' => [
                    '.introjs-tooltip' => 'color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'tooltip_typography',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '.introjs-tooltip',
            ]
        );

        $this->add_control(
            'tooltip_border_radius',
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
                'default' => [
                    'unit' => 'px',
                    'size' => 5,
                ],
                'selectors' => [
                    '.introjs-tooltip' => 'border-radius: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->add_responsive_control(
            'tooltip_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '.introjs-tooltip' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'tooltip_box_shadow',
                'label' => __('Box Shadow', 'voxel-toolkit'),
                'selector' => '.introjs-tooltip',
            ]
        );

        $this->add_control(
            'tooltip_max_width',
            [
                'label' => __('Max Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 200,
                        'max' => 800,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 400,
                ],
                'selectors' => [
                    '.introjs-tooltip' => 'max-width: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->add_control(
            'heading_tooltip_title',
            [
                'label' => __('Title', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'tooltip_title_color',
            [
                'label' => __('Title Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#000000',
                'selectors' => [
                    '.introjs-tooltip-title' => 'color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'tooltip_title_typography',
                'label' => __('Title Typography', 'voxel-toolkit'),
                'selector' => '.introjs-tooltip-title',
            ]
        );

        $this->add_control(
            'heading_tooltip_arrow',
            [
                'label' => __('Arrow', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'tooltip_arrow_color',
            [
                'label' => __('Arrow Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '.introjs-arrow.top' => 'border-bottom-color: {{VALUE}} !important;',
                    '.introjs-arrow.bottom' => 'border-top-color: {{VALUE}} !important;',
                    '.introjs-arrow.left' => 'border-right-color: {{VALUE}} !important;',
                    '.introjs-arrow.right' => 'border-left-color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Navigation Buttons Section
        $this->start_controls_section(
            'section_nav_buttons_style',
            [
                'label' => __('Navigation Buttons', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'nav_button_typography',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '.introjs-button',
            ]
        );

        $this->start_controls_tabs('nav_button_tabs');

        $this->start_controls_tab(
            'nav_button_normal',
            [
                'label' => __('Normal', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'nav_button_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '.introjs-button' => 'color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'nav_button_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#1e3a5f',
                'selectors' => [
                    '.introjs-button' => 'background-color: {{VALUE}} !important; background-image: none !important;',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'nav_button_hover',
            [
                'label' => __('Hover', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'nav_button_hover_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '.introjs-button:hover, .introjs-button:focus' => 'color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'nav_button_hover_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2a5080',
                'selectors' => [
                    '.introjs-button:hover, .introjs-button:focus' => 'background-color: {{VALUE}} !important; background-image: none !important;',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control(
            'nav_button_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '.introjs-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'nav_button_border_radius',
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
                'default' => [
                    'unit' => 'px',
                    'size' => 3,
                ],
                'selectors' => [
                    '.introjs-button' => 'border-radius: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'nav_button_border',
                'label' => __('Border', 'voxel-toolkit'),
                'selector' => '.introjs-button',
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'nav_button_box_shadow',
                'label' => __('Box Shadow', 'voxel-toolkit'),
                'selector' => '.introjs-button',
            ]
        );

        $this->end_controls_section();

        // Style Tab - Skip Button Section
        $this->start_controls_section(
            'section_skip_button_style',
            [
                'label' => __('Skip Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'skip_button_typography',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '.introjs-skipbutton',
            ]
        );

        $this->start_controls_tabs('skip_button_tabs');

        $this->start_controls_tab(
            'skip_button_normal',
            [
                'label' => __('Normal', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'skip_button_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#7a7a7a',
                'selectors' => [
                    '.introjs-skipbutton' => 'color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'skip_button_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '.introjs-skipbutton' => 'background-color: {{VALUE}} !important; background-image: none !important;',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'skip_button_hover',
            [
                'label' => __('Hover', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'skip_button_hover_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#000000',
                'selectors' => [
                    '.introjs-skipbutton:hover, .introjs-skipbutton:focus' => 'color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'skip_button_hover_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '.introjs-skipbutton:hover, .introjs-skipbutton:focus' => 'background-color: {{VALUE}} !important; background-image: none !important;',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control(
            'skip_button_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '.introjs-skipbutton' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'skip_button_border_radius',
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
                'default' => [
                    'unit' => 'px',
                    'size' => 3,
                ],
                'selectors' => [
                    '.introjs-skipbutton' => 'border-radius: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'skip_button_border',
                'label' => __('Border', 'voxel-toolkit'),
                'selector' => '.introjs-skipbutton',
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'skip_button_box_shadow',
                'label' => __('Box Shadow', 'voxel-toolkit'),
                'selector' => '.introjs-skipbutton',
            ]
        );

        $this->end_controls_section();

        // Style Tab - Progress Bar Section
        $this->start_controls_section(
            'section_progress_style',
            [
                'label' => __('Progress Bar', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_progress' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'progress_bar_color',
            [
                'label' => __('Progress Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#1e3a5f',
                'selectors' => [
                    '.introjs-progressbar' => 'background-color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'progress_bar_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e0e0e0',
                'selectors' => [
                    '.introjs-progress' => 'background-color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'progress_bar_height',
            [
                'label' => __('Height', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 1,
                        'max' => 20,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 2,
                ],
                'selectors' => [
                    '.introjs-progress' => 'height: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Bullets Section
        $this->start_controls_section(
            'section_bullets_style',
            [
                'label' => __('Bullets', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_bullets' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'bullet_active_color',
            [
                'label' => __('Active Bullet Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#1e3a5f',
                'selectors' => [
                    '.introjs-bullets ul li a.active' => 'background-color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'bullet_inactive_color',
            [
                'label' => __('Inactive Bullet Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#cccccc',
                'selectors' => [
                    '.introjs-bullets ul li a' => 'background-color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'bullet_size',
            [
                'label' => __('Bullet Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 4,
                        'max' => 20,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '.introjs-bullets ul li a' => 'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        $widget_id = $this->get_id();
        $page_id = get_the_ID();

        // Build tour steps for JavaScript
        $tour_steps = array();
        if (!empty($settings['tour_steps'])) {
            foreach ($settings['tour_steps'] as $step) {
                $tour_steps[] = array(
                    'title' => $step['step_title'],
                    'intro' => $step['step_content'],
                    'element' => !empty($step['target_selector']) ? $step['target_selector'] : null,
                    'position' => $step['tooltip_position'] ?? 'bottom',
                );
            }
        }

        // Output widget container
        ?>
        <div
            class="voxel-onboarding-tour-widget"
            data-widget-id="<?php echo esc_attr($widget_id); ?>"
            data-page-id="<?php echo esc_attr($page_id); ?>"
            data-tour-version="<?php echo esc_attr($settings['reset_tour_version'] ?? 1); ?>"
            data-preview-mode="<?php echo esc_attr($settings['preview_mode'] ?? 'no'); ?>"
            data-tour-steps="<?php echo esc_attr(wp_json_encode($tour_steps)); ?>"
            data-auto-start="<?php echo esc_attr($settings['auto_start'] ?? 'no'); ?>"
            data-auto-start-delay="<?php echo esc_attr(($settings['auto_start_delay']['size'] ?? 0.5) * 1000); ?>"
            data-show-progress="<?php echo esc_attr($settings['show_progress'] ?? 'yes'); ?>"
            data-show-bullets="<?php echo esc_attr($settings['show_bullets'] ?? 'yes'); ?>"
            data-exit-on-overlay="<?php echo esc_attr($settings['exit_on_overlay'] ?? 'no'); ?>"
            data-show-step-numbers="<?php echo esc_attr($settings['show_step_numbers'] ?? 'yes'); ?>"
        >
            <?php if ($settings['show_start_button'] === 'yes') : ?>
                <button class="voxel-tour-start-btn">
                    <?php echo esc_html($settings['start_button_text'] ?? 'Start Tour'); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php
    }
}
