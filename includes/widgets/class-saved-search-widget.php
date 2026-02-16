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
                    '{{WRAPPER}} .vtk-vault__grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
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
                    '{{WRAPPER}} .vtk-vault__grid' => 'gap: {{SIZE}}{{UNIT}};',
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
            'vt_ss_show_share_btn',
            [
                'label' => __('Show Share Button', 'voxel-toolkit'),
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

        $this->add_control('vt_ss_label_search', ['label' => __('Search', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Search', 'voxel-toolkit')]);
        $this->add_control('vt_ss_label_enable_notification', ['label' => __('Enable Notification', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Enable Notification', 'voxel-toolkit')]);
        $this->add_control('vt_ss_label_disable_notification', ['label' => __('Disable Notification', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Disable Notification', 'voxel-toolkit')]);
        $this->add_control('vt_ss_label_edit_title', ['label' => __('Edit Title', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Edit Title', 'voxel-toolkit')]);
        $this->add_control('vt_ss_label_reset', ['label' => __('Reset', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Reset', 'voxel-toolkit')]);
        $this->add_control('vt_ss_label_delete', ['label' => __('Delete', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Delete', 'voxel-toolkit')]);
        $this->add_control('vt_ss_label_share', ['label' => __('Share', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Share', 'voxel-toolkit')]);
        $this->add_control('vt_ss_label_share_success', ['label' => __('Share success message', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Link copied to clipboard!', 'voxel-toolkit')]);
        $this->add_control('vt_ss_label_no_result', ['label' => __('No results', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Nothing found!', 'voxel-toolkit')]);
        $this->add_control('vt_ss_label_no_filter', ['label' => __('No Filter', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('No filters found!', 'voxel-toolkit')]);
        $this->add_control('vt_ss_label_confirm_delete', ['label' => __('Delete Confirmation', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Are you sure you want to delete this search?', 'voxel-toolkit')]);

        $this->end_controls_section();

        // Icons Section
        $this->start_controls_section(
            'vt_ss_icons',
            [
                'label' => __('Icons', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control('vt_ss_icon_arrow_left', ['label' => __('Arrow left icon', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false]);
        $this->add_control('vt_ss_icon_arrow_right', ['label' => __('Arrow right icon', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false]);
        $this->add_control('vt_ss_icon_delete', ['label' => __('Delete icon', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false]);
        $this->add_control('vt_ss_icon_enable_notification', ['label' => __('Enable Notification', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false]);
        $this->add_control('vt_ss_icon_edit_title', ['label' => __('Edit title icon', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false]);
        $this->add_control('vt_ss_icon_disable_notification', ['label' => __('Disable Notification', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false]);
        $this->add_control('vt_ss_icon_search', ['label' => __('Search icon', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false]);
        $this->add_control('vt_ss_icon_share', ['label' => __('Share icon', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false]);

        $this->end_controls_section();

        // ─── STYLE: Search Card ───
        $this->start_controls_section('vt_ss_style_card', ['label' => __('Search Card', 'voxel-toolkit'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);

        $this->add_control('vt_ss_card_bg', ['label' => __('Background Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__card' => 'background: {{VALUE}}']]);
        $this->add_responsive_control('vt_ss_card_padding', ['label' => __('Body Padding', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', '%', 'em'], 'selectors' => ['{{WRAPPER}} .vtk-vault__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'vt_ss_card_border', 'label' => __('Border', 'voxel-toolkit'), 'selector' => '{{WRAPPER}} .vtk-vault__card']);
        $this->add_responsive_control('vt_ss_card_radius', ['label' => __('Border Radius', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 100, 'step' => 1]], 'default' => ['size' => 12, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .vtk-vault__card' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), ['name' => 'vt_ss_card_shadow', 'label' => __('Box Shadow', 'voxel-toolkit'), 'selector' => '{{WRAPPER}} .vtk-vault__card']);

        $this->end_controls_section();

        // ─── STYLE: Card Header ───
        $this->start_controls_section('vt_ss_style_header', ['label' => __('Card Header', 'voxel-toolkit'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);

        $this->add_control('vt_ss_header_bg', ['label' => __('Background Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__header' => 'background: {{VALUE}}']]);
        $this->add_responsive_control('vt_ss_header_padding', ['label' => __('Padding', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', '%', 'em'], 'selectors' => ['{{WRAPPER}} .vtk-vault__header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);

        $this->add_control('vt_ss_title_heading', ['label' => __('Title', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'vt_ss_title_typo', 'label' => __('Typography', 'voxel-toolkit'), 'selector' => '{{WRAPPER}} .vtk-vault__title']);
        $this->add_control('vt_ss_title_color', ['label' => __('Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__title' => 'color: {{VALUE}}']]);

        $this->add_control('vt_ss_badge_heading', ['label' => __('Post Type Badge', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_control('vt_ss_badge_bg', ['label' => __('Background Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__badge' => 'background: {{VALUE}}']]);
        $this->add_responsive_control('vt_ss_badge_size', ['label' => __('Size', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 24, 'max' => 60, 'step' => 1]], 'selectors' => ['{{WRAPPER}} .vtk-vault__badge' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; min-width: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('vt_ss_badge_radius', ['label' => __('Border Radius', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 30, 'step' => 1]], 'selectors' => ['{{WRAPPER}} .vtk-vault__badge' => 'border-radius: {{SIZE}}{{UNIT}};']]);

        $this->add_control('vt_ss_header_divider_heading', ['label' => __('Header Divider', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_control('vt_ss_header_divider_color', ['label' => __('Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__header' => 'border-bottom-color: {{VALUE}}']]);
        $this->add_responsive_control('vt_ss_header_divider_width', ['label' => __('Thickness', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 10, 'step' => 1]], 'selectors' => ['{{WRAPPER}} .vtk-vault__header' => 'border-bottom-width: {{SIZE}}{{UNIT}}; border-bottom-style: solid;']]);

        $this->end_controls_section();

        // ─── STYLE: Action Buttons ───
        $this->start_controls_section('vt_ss_style_actions', ['label' => __('Action Buttons', 'voxel-toolkit'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);

        $this->add_responsive_control('vt_ss_action_size', ['label' => __('Button Size', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 24, 'max' => 50, 'step' => 1]], 'default' => ['size' => 34, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .vtk-vault__action' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('vt_ss_action_icon_size', ['label' => __('Icon Size', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 12, 'max' => 32, 'step' => 1]], 'default' => ['size' => 16, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .vtk-vault__action i' => 'font-size: {{SIZE}}{{UNIT}};', '{{WRAPPER}} .vtk-vault__action svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('vt_ss_action_radius', ['label' => __('Border Radius', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 25, 'step' => 1]], 'selectors' => ['{{WRAPPER}} .vtk-vault__action' => 'border-radius: {{SIZE}}{{UNIT}};']]);

        $this->start_controls_tabs('vt_ss_action_tabs');

        $this->start_controls_tab('vt_ss_action_tab_normal', ['label' => __('Normal', 'voxel-toolkit')]);
        $this->add_control('vt_ss_action_bg', ['label' => __('Background Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__action' => 'background: {{VALUE}}']]);
        $this->add_control('vt_ss_action_icon_color', ['label' => __('Icon Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__action svg' => 'fill: {{VALUE}}', '{{WRAPPER}} .vtk-vault__action i' => 'color: {{VALUE}}']]);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'vt_ss_action_border', 'label' => __('Border', 'voxel-toolkit'), 'selector' => '{{WRAPPER}} .vtk-vault__action']);
        $this->end_controls_tab();

        $this->start_controls_tab('vt_ss_action_tab_hover', ['label' => __('Hover', 'voxel-toolkit')]);
        $this->add_control('vt_ss_action_bg_hover', ['label' => __('Background Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__action:hover' => 'background: {{VALUE}}']]);
        $this->add_control('vt_ss_action_icon_color_hover', ['label' => __('Icon Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__action:hover svg' => 'fill: {{VALUE}}', '{{WRAPPER}} .vtk-vault__action:hover i' => 'color: {{VALUE}}']]);
        $this->add_control('vt_ss_action_border_color_hover', ['label' => __('Border Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__action:hover' => 'border-color: {{VALUE}}']]);
        $this->end_controls_tab();

        $this->start_controls_tab('vt_ss_action_tab_active', ['label' => __('Active', 'voxel-toolkit')]);
        $this->add_control('vt_ss_action_bg_active', ['label' => __('Background Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__action:active, {{WRAPPER}} .vtk-vault__action--notif-on' => 'background: {{VALUE}}']]);
        $this->add_control('vt_ss_action_icon_color_active', ['label' => __('Icon Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__action:active svg, {{WRAPPER}} .vtk-vault__action--notif-on svg' => 'fill: {{VALUE}}', '{{WRAPPER}} .vtk-vault__action:active i, {{WRAPPER}} .vtk-vault__action--notif-on i' => 'color: {{VALUE}}']]);
        $this->add_control('vt_ss_action_border_color_active', ['label' => __('Border Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__action:active, {{WRAPPER}} .vtk-vault__action--notif-on' => 'border-color: {{VALUE}}']]);
        $this->end_controls_tab();

        $this->end_controls_tabs();
        $this->end_controls_section();

        // ─── STYLE: Filter Tags (Detailed) ───
        $this->start_controls_section('vt_ss_style_filters', ['label' => __('Filter Tags', 'voxel-toolkit'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE, 'condition' => ['vt_ss_template' => 'detailed']]);

        $this->add_responsive_control('vt_ss_filter_gap', ['label' => __('Gap', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 30, 'step' => 1]], 'default' => ['size' => 8, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .vtk-vault__criteria' => 'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_control('vt_ss_filter_bg', ['label' => __('Background Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__criterion' => 'background: {{VALUE}}']]);
        $this->add_responsive_control('vt_ss_filter_padding', ['label' => __('Padding', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', '%', 'em'], 'selectors' => ['{{WRAPPER}} .vtk-vault__criterion' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);

        $this->add_control('vt_ss_filter_border_heading', ['label' => __('Border', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_control('vt_ss_filter_border_style', ['label' => __('Border Style', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'none', 'options' => ['none' => __('None', 'voxel-toolkit'), 'solid' => __('Solid', 'voxel-toolkit'), 'dashed' => __('Dashed', 'voxel-toolkit'), 'dotted' => __('Dotted', 'voxel-toolkit')], 'selectors' => ['{{WRAPPER}} .vtk-vault__criterion' => 'border-style: {{VALUE}};']]);
        $this->add_responsive_control('vt_ss_filter_border_width', ['label' => __('Border Width', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px'], 'selectors' => ['{{WRAPPER}} .vtk-vault__criterion' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'], 'condition' => ['vt_ss_filter_border_style!' => 'none']]);
        $this->add_control('vt_ss_filter_border_color', ['label' => __('Border Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__criterion' => 'border-color: {{VALUE}}'], 'condition' => ['vt_ss_filter_border_style!' => 'none']]);
        $this->add_responsive_control('vt_ss_filter_radius', ['label' => __('Border Radius', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', '%'], 'selectors' => ['{{WRAPPER}} .vtk-vault__criterion' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), ['name' => 'vt_ss_filter_shadow', 'selector' => '{{WRAPPER}} .vtk-vault__criterion', 'separator' => 'before']);

        $this->add_control('vt_ss_filter_text_heading', ['label' => __('Text', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'vt_ss_filter_typo', 'label' => __('Typography', 'voxel-toolkit'), 'selector' => '{{WRAPPER}} .vtk-vault__criterion']);
        $this->add_control('vt_ss_filter_color', ['label' => __('Text Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__criterion, {{WRAPPER}} .vtk-vault__criterion-value' => 'color: {{VALUE}}']]);
        $this->add_control('vt_ss_filter_label_color', ['label' => __('Label Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__criterion-label' => 'color: {{VALUE}}']]);

        $this->add_control('vt_ss_filter_icon_heading', ['label' => __('Icon', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_control('vt_ss_filter_icon_color', ['label' => __('Icon Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__criterion-icon svg' => 'fill: {{VALUE}}']]);
        $this->add_responsive_control('vt_ss_filter_icon_size', ['label' => __('Icon Size', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 8, 'max' => 32, 'step' => 1]], 'selectors' => ['{{WRAPPER}} .vtk-vault__criterion-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};']]);

        $this->end_controls_section();

        // ─── STYLE: Filter Summary (Simple) ───
        $this->start_controls_section('vt_ss_style_summary', ['label' => __('Filter Summary', 'voxel-toolkit'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE, 'condition' => ['vt_ss_template' => 'simple']]);

        $this->add_control('vt_ss_summary_bg', ['label' => __('Background Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__summary' => 'background: {{VALUE}}']]);
        $this->add_responsive_control('vt_ss_summary_padding', ['label' => __('Padding', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', '%', 'em'], 'selectors' => ['{{WRAPPER}} .vtk-vault__summary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('vt_ss_summary_radius', ['label' => __('Border Radius', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', '%'], 'selectors' => ['{{WRAPPER}} .vtk-vault__summary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'vt_ss_summary_typo', 'label' => __('Typography', 'voxel-toolkit'), 'selector' => '{{WRAPPER}} .vtk-vault__summary']);
        $this->add_control('vt_ss_summary_color', ['label' => __('Text Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__summary' => 'color: {{VALUE}}']]);
        $this->add_control('vt_ss_summary_label_color', ['label' => __('Label Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__summary strong' => 'color: {{VALUE}}']]);

        $this->end_controls_section();

        // ─── STYLE: Card Footer ───
        $this->start_controls_section('vt_ss_style_footer', ['label' => __('Card Footer', 'voxel-toolkit'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);

        $this->add_control('vt_ss_footer_bg', ['label' => __('Background Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__footer' => 'background: {{VALUE}}']]);
        $this->add_responsive_control('vt_ss_footer_padding', ['label' => __('Padding', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', '%', 'em'], 'selectors' => ['{{WRAPPER}} .vtk-vault__footer' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'vt_ss_footer_typo', 'label' => __('Typography', 'voxel-toolkit'), 'selector' => '{{WRAPPER}} .vtk-vault__footer']);
        $this->add_control('vt_ss_footer_color', ['label' => __('Text Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__footer' => 'color: {{VALUE}}', '{{WRAPPER}} .vtk-vault__pt-label' => 'color: {{VALUE}}', '{{WRAPPER}} .vtk-vault__date' => 'color: {{VALUE}}']]);

        $this->add_control('vt_ss_footer_divider_heading', ['label' => __('Footer Divider', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_control('vt_ss_footer_divider_color', ['label' => __('Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .vtk-vault__footer' => 'border-top-color: {{VALUE}}']]);
        $this->add_responsive_control('vt_ss_footer_divider_width', ['label' => __('Thickness', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 10, 'step' => 1]], 'selectors' => ['{{WRAPPER}} .vtk-vault__footer' => 'border-top-width: {{SIZE}}{{UNIT}}; border-top-style: solid;']]);

        $this->end_controls_section();

        // ─── STYLE: Dialog (Modals) ───
        $this->start_controls_section('vt_ss_style_dialog', ['label' => __('Dialog / Modal', 'voxel-toolkit'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);

        $this->add_control('vt_ss_dialog_overlay_bg', ['label' => __('Overlay Background', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['.vtk-dialog__overlay' => 'background: {{VALUE}}']]);
        $this->add_control('vt_ss_dialog_panel_bg', ['label' => __('Panel Background', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['.vtk-dialog__panel' => 'background: {{VALUE}}']]);
        $this->add_responsive_control('vt_ss_dialog_panel_radius', ['label' => __('Panel Radius', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 30, 'step' => 1]], 'selectors' => ['.vtk-dialog__panel' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('vt_ss_dialog_panel_padding', ['label' => __('Panel Padding', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', '%', 'em'], 'selectors' => ['.vtk-dialog__panel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), ['name' => 'vt_ss_dialog_panel_shadow', 'selector' => '.vtk-dialog__panel']);

        $this->add_control('vt_ss_dialog_title_heading', ['label' => __('Title', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'vt_ss_dialog_title_typo', 'label' => __('Typography', 'voxel-toolkit'), 'selector' => '.vtk-dialog__title']);
        $this->add_control('vt_ss_dialog_title_color', ['label' => __('Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['.vtk-dialog__title' => 'color: {{VALUE}}']]);

        $this->add_control('vt_ss_dialog_btn_heading', ['label' => __('Buttons', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_control('vt_ss_dialog_btn_primary_bg', ['label' => __('Primary BG', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['.vtk-dialog__btn--primary' => 'background: {{VALUE}}']]);
        $this->add_control('vt_ss_dialog_btn_primary_color', ['label' => __('Primary Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['.vtk-dialog__btn--primary' => 'color: {{VALUE}}']]);
        $this->add_control('vt_ss_dialog_btn_danger_bg', ['label' => __('Danger BG', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['.vtk-dialog__btn--danger' => 'background: {{VALUE}}']]);
        $this->add_control('vt_ss_dialog_btn_danger_color', ['label' => __('Danger Color', 'voxel-toolkit'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['.vtk-dialog__btn--danger' => 'color: {{VALUE}}']]);

        $this->end_controls_section();
    }

    protected function render() {
        if (!is_user_logged_in()) {
            printf('<p class="ts-restricted">%s</p>', __('You must be logged in to view this content.', 'voxel-toolkit'));
            return;
        }

        $hide_when_empty = $this->get_settings_for_display('vt_ss_hide_when_empty') === 'yes';
        if ($hide_when_empty && !Voxel_Toolkit_Saved_Search::user_has_saved_searches()) {
            return;
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
                'share' => $this->get_settings_for_display('vt_ss_label_share') ?: __('Share', 'voxel-toolkit'),
                'shareSuccess' => $this->get_settings_for_display('vt_ss_label_share_success') ?: __('Link copied to clipboard!', 'voxel-toolkit'),
            ],
            'icons' => [
                'arrowLeft' => $this->get_icon_markup('vt_ss_icon_arrow_left'),
                'arrowRight' => $this->get_icon_markup('vt_ss_icon_arrow_right'),
                'delete' => $this->get_icon_markup('vt_ss_icon_delete'),
                'enableNotification' => $this->get_icon_markup('vt_ss_icon_enable_notification'),
                'editTitle' => $this->get_icon_markup('vt_ss_icon_edit_title'),
                'disableNotification' => $this->get_icon_markup('vt_ss_icon_disable_notification'),
                'search' => $this->get_icon_markup('vt_ss_icon_search'),
                'share' => $this->get_icon_markup('vt_ss_icon_share'),
            ],
            'defaultIcons' => [
                'editTitle' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M22.853,1.148a3.626,3.626,0,0,0-5.124,0L1.465,17.412A4.968,4.968,0,0,0,0,20.947V23a1,1,0,0,0,1,1H3.053a4.966,4.966,0,0,0,3.535-1.464L22.853,6.271A3.626,3.626,0,0,0,22.853,1.148ZM5.174,21.122A3.022,3.022,0,0,1,3.053,22H2V20.947a2.98,2.98,0,0,1,.879-2.121L15.222,6.483l2.3,2.3ZM21.438,4.857,18.932,7.364l-2.3-2.295,2.507-2.507a1.623,1.623,0,1,1,2.295,2.3Z"/></svg>',
                'search' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M23.707,22.293l-5.969-5.969a10.016,10.016,0,1,0-1.414,1.414l5.969,5.969a1,1,0,0,0,1.414-1.414ZM10,18a8,8,0,1,1,8-8A8.009,8.009,0,0,1,10,18Z"/></svg>',
                'delete' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M21,4H17.9A5.009,5.009,0,0,0,13,0H11A5.009,5.009,0,0,0,6.1,4H3A1,1,0,0,0,3,6H4V19a5.006,5.006,0,0,0,5,5h6a5.006,5.006,0,0,0,5-5V6h1a1,1,0,0,0,0-2ZM11,2h2a3.006,3.006,0,0,1,2.829,2H8.171A3.006,3.006,0,0,1,11,2Zm7,17a3,3,0,0,1-3,3H9a3,3,0,0,1-3-3V6H18Z"/><path d="M10,18a1,1,0,0,0,1-1V11a1,1,0,0,0-2,0v6A1,1,0,0,0,10,18Z"/><path d="M14,18a1,1,0,0,0,1-1V11a1,1,0,0,0-2,0v6A1,1,0,0,0,14,18Z"/></svg>',
                'notification' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M22.555,13.662l-1.9-6.836A9.321,9.321,0,0,0,2.576,7.3L1.105,13.915A5,5,0,0,0,5.986,20H7.1a5,5,0,0,0,9.8,0h.838a5,5,0,0,0,4.818-6.338ZM12,22a3,3,0,0,1-2.816-2h5.632A3,3,0,0,1,12,22Zm8.126-5.185A2.977,2.977,0,0,1,17.737,18H5.986a3,3,0,0,1-2.928-3.651l1.47-6.616a7.321,7.321,0,0,1,14.2-.372l1.9,6.836A2.977,2.977,0,0,1,20.126,16.815Z"/></svg>',
                'share' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M19.333,14.667a4.66,4.66,0,0,0-3.839,2.024L8.985,13.752a4.574,4.574,0,0,0,0-3.5l6.509-2.94A4.66,4.66,0,0,0,19.333,9.333,4.667,4.667,0,1,0,14.667,4.667a4.574,4.574,0,0,0,.182,1.235L8.34,8.842a4.667,4.667,0,1,0,0,6.316l6.509,2.94a4.574,4.574,0,0,0-.182,1.235,4.667,4.667,0,1,0,4.666-4.666Zm0-12.667a2.667,2.667,0,1,1-2.666,2.667A2.669,2.669,0,0,1,19.333,2ZM4.667,14.667A2.667,2.667,0,1,1,7.333,12,2.67,2.67,0,0,1,4.667,14.667ZM19.333,22a2.667,2.667,0,1,1,2.667-2.667A2.669,2.669,0,0,1,19.333,22Z"/></svg>',
            ],
        ];

        wp_enqueue_style('vtk-search-vault');
        wp_enqueue_script('vtk-search-vault');

        $template_path = VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/vault-widget.php';
        if (file_exists($template_path)) {
            include $template_path;
        }

        ?>
        <script type="text/javascript">
        (function() {
            function initVtkVault() {
                if (typeof window.render_vtk_search_vault === 'function') {
                    window.render_vtk_search_vault();
                } else {
                    setTimeout(initVtkVault, 100);
                }
            }
            if (document.readyState === 'complete') {
                initVtkVault();
            } else {
                window.addEventListener('load', initVtkVault);
            }
        })();
        </script>
        <?php
    }

    private function get_icon_markup($setting_key) {
        $icon = $this->get_settings_for_display($setting_key);
        if ($icon && !empty($icon['value']) && function_exists('\Voxel\get_icon_markup')) {
            return \Voxel\get_icon_markup($icon);
        }
        return '';
    }

    public function get_script_depends() {
        return ['vtk-search-vault'];
    }

    public function get_style_depends() {
        return ['vtk-search-vault'];
    }

    protected function content_template() {
    }

    public function render_plain_content($instance = []) {
    }
}
