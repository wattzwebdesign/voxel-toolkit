<?php
/**
 * Saved Search Elementor Widget
 *
 * Displays and manages user's saved searches.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Saved_Search_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'vt-saved-search';
    }

    public function get_title() {
        return __('Saved Search (VT)', 'voxel-toolkit');
    }

    public function get_icon() {
        return 'eicon-search';
    }

    public function get_categories() {
        return ['voxel-toolkit', 'voxel', 'basic'];
    }

    public function get_keywords() {
        return ['saved', 'search', 'voxel', 'filter', 'toolkit'];
    }

    protected function register_controls() {
        // General Section
        $this->start_controls_section(
            'vt_ss_general',
            [
                'label' => __('General', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_responsive_control(
            'vt_ss_columns',
            [
                'label' => __('Columns', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '1',
                'tablet_default' => '1',
                'mobile_default' => '1',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-saved-searches-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_gap',
            [
                'label' => __('Gap', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 0, 'max' => 60, 'step' => 1]],
                'default' => ['size' => 20, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .vt-saved-searches-grid' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_template',
            [
                'label' => __('Card Template', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'detailed',
                'options' => [
                    'detailed' => __('Detailed (Filter Tags)', 'voxel-toolkit'),
                    'simple' => __('Simple (Filter Summary)', 'voxel-toolkit'),
                ],
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'vt_ss_show_filter_icons',
            [
                'label' => __('Show Filter Icons', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'no',
                'return_value' => 'yes',
                'condition' => [
                    'vt_ss_template' => 'detailed',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_show_title',
            [
                'label' => __('Show Title', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'vt_ss_show_edit_title',
            [
                'label' => __('Show Edit Title Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'vt_ss_show_post_type',
            [
                'label' => __('Show Post Type', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'vt_ss_show_created_date',
            [
                'label' => __('Show Created Date', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'vt_ss_show_search_btn',
            [
                'label' => __('Show Search Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'vt_ss_show_notification_btn',
            [
                'label' => __('Show Notification Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'vt_ss_show_delete_btn',
            [
                'label' => __('Show Delete Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'vt_ss_hide_when_empty',
            [
                'label' => __('Hide When Empty', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => '',
                'return_value' => 'yes',
                'separator' => 'before',
                'description' => __('Hide the widget completely if user has no saved searches', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // Labels Section
        $this->start_controls_section(
            'vt_ss_labels',
            [
                'label' => __('Labels & messages', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'vt_ss_label_search',
            [
                'label' => __('Search', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Search', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'vt_ss_label_enable_notification',
            [
                'label' => __('Enable Notification', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Enable Notification', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'vt_ss_label_disable_notification',
            [
                'label' => __('Disable Notification', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Disable Notification', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'vt_ss_label_edit_title',
            [
                'label' => __('Edit Title', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Edit Title', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'vt_ss_label_reset',
            [
                'label' => __('Reset', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Reset', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'vt_ss_label_delete',
            [
                'label' => __('Delete', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Delete', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'vt_ss_label_no_result',
            [
                'label' => __('No results', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Nothing found!', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'vt_ss_label_no_filter',
            [
                'label' => __('No Filter', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('No filters found!', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'vt_ss_label_confirm_delete',
            [
                'label' => __('Delete Confirmation', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Are you sure you want to delete this search?', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // Icons Section
        $this->start_controls_section(
            'vt_ss_icons',
            [
                'label' => __('Icons', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'vt_ss_icon_arrow_left',
            [
                'label' => __('Arrow left icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
            ]
        );

        $this->add_control(
            'vt_ss_icon_arrow_right',
            [
                'label' => __('Arrow right icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
            ]
        );

        $this->add_control(
            'vt_ss_icon_delete',
            [
                'label' => __('Delete icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
            ]
        );

        $this->add_control(
            'vt_ss_icon_enable_notification',
            [
                'label' => __('Enable Notification', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
            ]
        );

        $this->add_control(
            'vt_ss_icon_edit_title',
            [
                'label' => __('Edit title icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
            ]
        );

        $this->add_control(
            'vt_ss_icon_disable_notification',
            [
                'label' => __('Disable Notification', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
            ]
        );

        $this->add_control(
            'vt_ss_icon_search',
            [
                'label' => __('Search icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
            ]
        );

        $this->end_controls_section();

        // Style Section - Card
        $this->start_controls_section(
            'vt_ss_style_card',
            [
                'label' => __('Search Card', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'vt_ss_card_bg',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-search-card' => 'background: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_card_padding',
            [
                'label' => __('Body Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-search-card-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'vt_ss_card_border',
                'label' => __('Border', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-search-card',
            ]
        );

        $this->add_responsive_control(
            'vt_ss_card_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 0, 'max' => 100, 'step' => 1]],
                'default' => ['size' => 12, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .vt-search-card' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'vt_ss_card_shadow',
                'label' => __('Box Shadow', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-search-card',
            ]
        );

        $this->end_controls_section();

        // Style Section - Card Header
        $this->start_controls_section(
            'vt_ss_style_header',
            [
                'label' => __('Card Header', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'vt_ss_header_bg',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-search-card-header' => 'background: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_header_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-search-card-header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_title_heading',
            [
                'label' => __('Title', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'vt_ss_title_typo',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-search-card-title',
            ]
        );

        $this->add_control(
            'vt_ss_title_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-search-card-title' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_badge_heading',
            [
                'label' => __('Post Type Badge', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'vt_ss_badge_bg',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-search-post-type-badge' => 'background: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_badge_size',
            [
                'label' => __('Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 24, 'max' => 60, 'step' => 1]],
                'selectors' => [
                    '{{WRAPPER}} .vt-search-post-type-badge' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; min-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_badge_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 0, 'max' => 30, 'step' => 1]],
                'selectors' => [
                    '{{WRAPPER}} .vt-search-post-type-badge' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_header_divider_heading',
            [
                'label' => __('Header Divider', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'vt_ss_header_divider_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-search-card-header' => 'border-bottom-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_header_divider_width',
            [
                'label' => __('Thickness', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 0, 'max' => 10, 'step' => 1]],
                'selectors' => [
                    '{{WRAPPER}} .vt-search-card-header' => 'border-bottom-width: {{SIZE}}{{UNIT}}; border-bottom-style: solid;',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Action Buttons
        $this->start_controls_section(
            'vt_ss_style_actions',
            [
                'label' => __('Action Buttons', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'vt_ss_action_size',
            [
                'label' => __('Button Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 24, 'max' => 50, 'step' => 1]],
                'default' => ['size' => 34, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .vt-action-btn' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_action_icon_size',
            [
                'label' => __('Icon Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 12, 'max' => 32, 'step' => 1]],
                'default' => ['size' => 16, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .vt-action-btn i' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .vt-action-btn svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_action_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 0, 'max' => 25, 'step' => 1]],
                'selectors' => [
                    '{{WRAPPER}} .vt-action-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->start_controls_tabs('vt_ss_action_tabs');

        // Normal Tab
        $this->start_controls_tab(
            'vt_ss_action_tab_normal',
            [
                'label' => __('Normal', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'vt_ss_action_bg',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-action-btn' => 'background: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_action_icon_color',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-action-btn svg' => 'fill: {{VALUE}}',
                    '{{WRAPPER}} .vt-action-btn i' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'vt_ss_action_border',
                'label' => __('Border', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-action-btn',
            ]
        );

        $this->end_controls_tab();

        // Hover Tab
        $this->start_controls_tab(
            'vt_ss_action_tab_hover',
            [
                'label' => __('Hover', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'vt_ss_action_bg_hover',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-action-btn:hover' => 'background: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_action_icon_color_hover',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-action-btn:hover svg' => 'fill: {{VALUE}}',
                    '{{WRAPPER}} .vt-action-btn:hover i' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_action_border_color_hover',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-action-btn:hover' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        // Active Tab
        $this->start_controls_tab(
            'vt_ss_action_tab_active',
            [
                'label' => __('Active', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'vt_ss_action_bg_active',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-action-btn:active' => 'background: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_action_icon_color_active',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-action-btn:active svg' => 'fill: {{VALUE}}',
                    '{{WRAPPER}} .vt-action-btn:active i' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_action_border_color_active',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-action-btn:active' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();

        // Style Section - Filter Tags (Detailed Template)
        $this->start_controls_section(
            'vt_ss_style_filters',
            [
                'label' => __('Filter Tags', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'vt_ss_template' => 'detailed',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_filter_gap',
            [
                'label' => __('Gap', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 0, 'max' => 30, 'step' => 1]],
                'default' => ['size' => 8, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .vt-search-filters' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_filter_bg',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-filter-tag' => 'background: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_filter_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-filter-tag' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_filter_border_heading',
            [
                'label' => __('Border', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'vt_ss_filter_border_style',
            [
                'label' => __('Border Style', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'none',
                'options' => [
                    'none' => __('None', 'voxel-toolkit'),
                    'solid' => __('Solid', 'voxel-toolkit'),
                    'dashed' => __('Dashed', 'voxel-toolkit'),
                    'dotted' => __('Dotted', 'voxel-toolkit'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-filter-tag' => 'border-style: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_filter_border_width',
            [
                'label' => __('Border Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'selectors' => [
                    '{{WRAPPER}} .vt-filter-tag' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'condition' => [
                    'vt_ss_filter_border_style!' => 'none',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_filter_border_color',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-filter-tag' => 'border-color: {{VALUE}}',
                ],
                'condition' => [
                    'vt_ss_filter_border_style!' => 'none',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_filter_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-filter-tag' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'vt_ss_filter_shadow',
                'selector' => '{{WRAPPER}} .vt-filter-tag',
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'vt_ss_filter_text_heading',
            [
                'label' => __('Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'vt_ss_filter_typo',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-filter-tag',
            ]
        );

        $this->add_control(
            'vt_ss_filter_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-filter-tag' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_filter_label_color',
            [
                'label' => __('Label Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-filter-tag b, {{WRAPPER}} .vt-filter-tag strong, {{WRAPPER}} .vt-filter-tag label' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_filter_icon_heading',
            [
                'label' => __('Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'vt_ss_filter_icon_color',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-filter-tag .filter-icon svg' => 'fill: {{VALUE}}',
                    '{{WRAPPER}} .vt-filter-tag label svg' => 'fill: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_filter_icon_size',
            [
                'label' => __('Icon Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 8, 'max' => 32, 'step' => 1]],
                'selectors' => [
                    '{{WRAPPER}} .vt-filter-tag .filter-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .vt-filter-tag label svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Filter Summary (Simple Template)
        $this->start_controls_section(
            'vt_ss_style_summary',
            [
                'label' => __('Filter Summary', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'vt_ss_template' => 'simple',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_summary_bg',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-search-filters-summary' => 'background: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_summary_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-search-filters-summary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_summary_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-search-filters-summary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'vt_ss_summary_typo',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-search-filters-summary',
            ]
        );

        $this->add_control(
            'vt_ss_summary_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-search-filters-summary' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_summary_label_color',
            [
                'label' => __('Label Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-search-filters-summary strong' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Card Footer
        $this->start_controls_section(
            'vt_ss_style_footer',
            [
                'label' => __('Card Footer', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'vt_ss_footer_bg',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-search-card-footer' => 'background: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_footer_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-search-card-footer' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'vt_ss_footer_typo',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-search-card-footer',
            ]
        );

        $this->add_control(
            'vt_ss_footer_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-search-card-footer' => 'color: {{VALUE}}',
                    '{{WRAPPER}} .vt-search-post-type-label' => 'color: {{VALUE}}',
                    '{{WRAPPER}} .vt-search-date' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'vt_ss_footer_divider_heading',
            [
                'label' => __('Footer Divider', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'vt_ss_footer_divider_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-search-card-footer' => 'border-top-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_ss_footer_divider_width',
            [
                'label' => __('Thickness', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 0, 'max' => 10, 'step' => 1]],
                'selectors' => [
                    '{{WRAPPER}} .vt-search-card-footer' => 'border-top-width: {{SIZE}}{{UNIT}}; border-top-style: solid;',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!is_user_logged_in()) {
            printf('<p class="ts-restricted">%s</p>', __('You must be logged in to view this content.', 'voxel-toolkit'));
            return;
        }

        // Check if widget should be hidden when user has no saved searches
        $hide_when_empty = $this->get_settings_for_display('vt_ss_hide_when_empty') === 'yes';
        if ($hide_when_empty && !Voxel_Toolkit_Saved_Search::user_has_saved_searches()) {
            return; // Don't render anything
        }

        $config = [
            'template' => $this->get_settings_for_display('vt_ss_template') ?: 'detailed',
            'showPostType' => $this->get_settings_for_display('vt_ss_show_post_type'),
            'showFilterIcons' => $this->get_settings_for_display('vt_ss_show_filter_icons'),
            'showTitle' => $this->get_settings_for_display('vt_ss_show_title'),
            'showEditTitle' => $this->get_settings_for_display('vt_ss_show_edit_title'),
            'showCreatedDate' => $this->get_settings_for_display('vt_ss_show_created_date'),
            'widget_id' => $this->get_id(),
            'labels' => [
                'search' => $this->get_settings_for_display('vt_ss_label_search') ?: __('Search', 'voxel-toolkit'),
                'enableNotification' => $this->get_settings_for_display('vt_ss_label_enable_notification') ?: __('Enable Notification', 'voxel-toolkit'),
                'disableNotification' => $this->get_settings_for_display('vt_ss_label_disable_notification') ?: __('Disable Notification', 'voxel-toolkit'),
                'editTitle' => $this->get_settings_for_display('vt_ss_label_edit_title') ?: __('Edit Title', 'voxel-toolkit'),
                'reset' => $this->get_settings_for_display('vt_ss_label_reset') ?: __('Reset', 'voxel-toolkit'),
                'delete' => $this->get_settings_for_display('vt_ss_label_delete') ?: __('Delete', 'voxel-toolkit'),
                'noResult' => $this->get_settings_for_display('vt_ss_label_no_result') ?: __('Nothing found!', 'voxel-toolkit'),
                'noFilter' => $this->get_settings_for_display('vt_ss_label_no_filter') ?: __('No filters found!', 'voxel-toolkit'),
                'confirmDelete' => $this->get_settings_for_display('vt_ss_label_confirm_delete') ?: __('Are you sure you want to delete this search?', 'voxel-toolkit'),
            ],
            'icons' => [
                'arrowLeft' => $this->get_icon_markup('vt_ss_icon_arrow_left'),
                'arrowRight' => $this->get_icon_markup('vt_ss_icon_arrow_right'),
                'delete' => $this->get_icon_markup('vt_ss_icon_delete'),
                'enableNotification' => $this->get_icon_markup('vt_ss_icon_enable_notification'),
                'editTitle' => $this->get_icon_markup('vt_ss_icon_edit_title'),
                'disableNotification' => $this->get_icon_markup('vt_ss_icon_disable_notification'),
                'search' => $this->get_icon_markup('vt_ss_icon_search'),
            ],
            'defaultIcons' => [
                'editTitle' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M22.853,1.148a3.626,3.626,0,0,0-5.124,0L1.465,17.412A4.968,4.968,0,0,0,0,20.947V23a1,1,0,0,0,1,1H3.053a4.966,4.966,0,0,0,3.535-1.464L22.853,6.271A3.626,3.626,0,0,0,22.853,1.148ZM5.174,21.122A3.022,3.022,0,0,1,3.053,22H2V20.947a2.98,2.98,0,0,1,.879-2.121L15.222,6.483l2.3,2.3ZM21.438,4.857,18.932,7.364l-2.3-2.295,2.507-2.507a1.623,1.623,0,1,1,2.295,2.3Z"/></svg>',
                'search' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M23.707,22.293l-5.969-5.969a10.016,10.016,0,1,0-1.414,1.414l5.969,5.969a1,1,0,0,0,1.414-1.414ZM10,18a8,8,0,1,1,8-8A8.009,8.009,0,0,1,10,18Z"/></svg>',
                'delete' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M21,4H17.9A5.009,5.009,0,0,0,13,0H11A5.009,5.009,0,0,0,6.1,4H3A1,1,0,0,0,3,6H4V19a5.006,5.006,0,0,0,5,5h6a5.006,5.006,0,0,0,5-5V6h1a1,1,0,0,0,0-2ZM11,2h2a3.006,3.006,0,0,1,2.829,2H8.171A3.006,3.006,0,0,1,11,2Zm7,17a3,3,0,0,1-3,3H9a3,3,0,0,1-3-3V6H18Z"/><path d="M10,18a1,1,0,0,0,1-1V11a1,1,0,0,0-2,0v6A1,1,0,0,0,10,18Z"/><path d="M14,18a1,1,0,0,0,1-1V11a1,1,0,0,0-2,0v6A1,1,0,0,0,14,18Z"/></svg>',
                'notification' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M22.555,13.662l-1.9-6.836A9.321,9.321,0,0,0,2.576,7.3L1.105,13.915A5,5,0,0,0,5.986,20H7.1a5,5,0,0,0,9.8,0h.838a5,5,0,0,0,4.818-6.338ZM12,22a3,3,0,0,1-2.816-2h5.632A3,3,0,0,1,12,22Zm8.126-5.185A2.977,2.977,0,0,1,17.737,18H5.986a3,3,0,0,1-2.928-3.651l1.47-6.616a7.321,7.321,0,0,1,14.2-.372l1.9,6.836A2.977,2.977,0,0,1,20.126,16.815Z"/></svg>',
            ],
        ];

        // Enqueue styles
        wp_enqueue_style('vt-saved-search');

        // Enqueue scripts
        wp_enqueue_script('vt-saved-search');

        // Load template
        $template_path = VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/saved-search-widget.php';
        if (file_exists($template_path)) {
            include $template_path;
        }

        // Output config for Vue app
        ?>
        <script type="text/json" class="vtSavedSearchWidgetConfig">
            <?php echo wp_specialchars_decode(wp_json_encode($config)); ?>
        </script>
        <script type="text/javascript">
        (function() {
            function initVTSavedSearch() {
                if (typeof window.render_vt_saved_searches === 'function') {
                    window.render_vt_saved_searches();
                } else {
                    setTimeout(initVTSavedSearch, 100);
                }
            }
            if (document.readyState === 'complete') {
                initVTSavedSearch();
            } else {
                window.addEventListener('load', initVTSavedSearch);
            }
        })();
        </script>
        <?php
    }

    /**
     * Get icon markup from settings
     */
    private function get_icon_markup($setting_key) {
        $icon = $this->get_settings_for_display($setting_key);
        if ($icon && !empty($icon['value']) && function_exists('\Voxel\get_icon_markup')) {
            return \Voxel\get_icon_markup($icon);
        }
        return '';
    }

    public function get_script_depends() {
        return ['vt-saved-search'];
    }

    public function get_style_depends() {
        return ['vt-saved-search'];
    }

    protected function content_template() {
    }

    public function render_plain_content($instance = []) {
    }
}
