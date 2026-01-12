<?php
/**
 * RSVP Attendee List Widget
 *
 * Elementor widget for displaying RSVPs with admin approval and export
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_RSVP_Attendee_List_Widget extends \Elementor\Widget_Base {

    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);

        // Enqueue scripts and styles
        wp_enqueue_style('voxel-toolkit-rsvp');
        wp_enqueue_script('voxel-toolkit-rsvp');
    }

    public function get_name() {
        return 'voxel-rsvp-attendee-list';
    }

    public function get_title() {
        return __('RSVP Attendee List (VT)', 'voxel-toolkit');
    }

    public function get_icon() {
        return 'eicon-person';
    }

    public function get_categories() {
        return ['voxel-toolkit'];
    }

    protected function register_controls() {
        // Content Tab - List Settings
        $this->start_controls_section(
            'list_settings_section',
            [
                'label' => __('List Settings', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'list_title',
            [
                'label' => __('List Title', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Attendees', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'status_filter',
            [
                'label' => __('Show Statuses', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => [
                    'approved' => __('Approved', 'voxel-toolkit'),
                    'pending' => __('Pending', 'voxel-toolkit'),
                    'rejected' => __('Rejected', 'voxel-toolkit'),
                ],
                'default' => ['approved'],
            ]
        );

        $this->add_control(
            'items_per_page',
            [
                'label' => __('Items Per Page', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 10,
                'min' => 1,
                'max' => 100,
            ]
        );

        $this->add_control(
            'show_avatars',
            [
                'label' => __('Show Avatars', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_comments',
            [
                'label' => __('Show Comments', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_timestamps',
            [
                'label' => __('Show Timestamps', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_status_badge',
            [
                'label' => __('Show Status Badge', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_export_button',
            [
                'label' => __('Show Export Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Only visible to users who can edit posts', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'show_admin_actions',
            [
                'label' => __('Show Admin Actions', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Approve/Reject buttons for capable users', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // Content Tab - Labels
        $this->start_controls_section(
            'labels_section',
            [
                'label' => __('Labels', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'export_button_text',
            [
                'label' => __('Export Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Export CSV', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'approve_button_text',
            [
                'label' => __('Approve Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Approve', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'reject_button_text',
            [
                'label' => __('Reject Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Reject', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'delete_button_text',
            [
                'label' => __('Delete Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Delete', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'load_more_text',
            [
                'label' => __('Load More Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Load More', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'empty_message',
            [
                'label' => __('Empty Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('No RSVPs yet.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'status_approved_label',
            [
                'label' => __('Approved Status Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Approved', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'status_pending_label',
            [
                'label' => __('Pending Status Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Pending', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'status_rejected_label',
            [
                'label' => __('Rejected Status Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Rejected', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // Style Tab - Container
        $this->start_controls_section(
            'container_style_section',
            [
                'label' => __('Container', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'container_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-list-wrapper' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-list-wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .vt-attendee-list-wrapper',
            ]
        );

        $this->add_control(
            'container_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-list-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Title
        $this->start_controls_section(
            'title_style_section',
            [
                'label' => __('Title', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-list-title' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .vt-attendee-list-title',
            ]
        );

        $this->add_responsive_control(
            'title_margin',
            [
                'label' => __('Margin', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-list-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - List Items
        $this->start_controls_section(
            'list_item_style_section',
            [
                'label' => __('List Items', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'item_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-item' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'item_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'item_border',
                'selector' => '{{WRAPPER}} .vt-attendee-item',
            ]
        );

        $this->add_control(
            'item_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'item_gap',
            [
                'label' => __('Gap Between Items', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-list' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Avatar
        $this->start_controls_section(
            'avatar_style_section',
            [
                'label' => __('Avatar', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['show_avatars' => 'yes'],
            ]
        );

        $this->add_responsive_control(
            'avatar_size',
            [
                'label' => __('Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 20,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'size' => 40,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-avatar' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'avatar_border_radius',
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
                    'size' => 50,
                    'unit' => '%',
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-avatar' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Name
        $this->start_controls_section(
            'name_style_section',
            [
                'label' => __('Name', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'name_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-name' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'name_typography',
                'selector' => '{{WRAPPER}} .vt-attendee-name',
            ]
        );

        $this->end_controls_section();

        // Style Tab - Comment
        $this->start_controls_section(
            'comment_style_section',
            [
                'label' => __('Comment', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['show_comments' => 'yes'],
            ]
        );

        $this->add_control(
            'comment_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-comment' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'comment_typography',
                'selector' => '{{WRAPPER}} .vt-attendee-comment',
            ]
        );

        $this->end_controls_section();

        // Style Tab - Timestamp
        $this->start_controls_section(
            'timestamp_style_section',
            [
                'label' => __('Timestamp', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['show_timestamps' => 'yes'],
            ]
        );

        $this->add_control(
            'timestamp_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-timestamp' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'timestamp_typography',
                'selector' => '{{WRAPPER}} .vt-attendee-timestamp',
            ]
        );

        $this->end_controls_section();

        // Style Tab - Status Badges
        $this->start_controls_section(
            'status_badge_style_section',
            [
                'label' => __('Status Badges', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['show_status_badge' => 'yes'],
            ]
        );

        $this->add_control(
            'badge_approved_heading',
            [
                'label' => __('Approved Badge', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'badge_approved_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-status-badge.vt-status-approved' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'badge_approved_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#10b981',
                'selectors' => [
                    '{{WRAPPER}} .vt-status-badge.vt-status-approved' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'badge_pending_heading',
            [
                'label' => __('Pending Badge', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'badge_pending_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-status-badge.vt-status-pending' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'badge_pending_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f59e0b',
                'selectors' => [
                    '{{WRAPPER}} .vt-status-badge.vt-status-pending' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'badge_rejected_heading',
            [
                'label' => __('Rejected Badge', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'badge_rejected_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-status-badge.vt-status-rejected' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'badge_rejected_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ef4444',
                'selectors' => [
                    '{{WRAPPER}} .vt-status-badge.vt-status-rejected' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'badge_typography',
                'selector' => '{{WRAPPER}} .vt-status-badge',
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'badge_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-status-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'badge_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 20,
                    ],
                ],
                'default' => [
                    'size' => 4,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-status-badge' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Export Button
        $this->start_controls_section(
            'export_button_style_section',
            [
                'label' => __('Export Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['show_export_button' => 'yes'],
            ]
        );

        $this->start_controls_tabs('export_button_tabs');

        $this->start_controls_tab('export_button_normal_tab', ['label' => __('Normal', 'voxel-toolkit')]);

        $this->add_control(
            'export_button_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-export-btn' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'export_button_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-export-btn' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab('export_button_hover_tab', ['label' => __('Hover', 'voxel-toolkit')]);

        $this->add_control(
            'export_button_text_color_hover',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-export-btn:hover' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'export_button_background_hover',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-export-btn:hover' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'export_button_typography',
                'selector' => '{{WRAPPER}} .vt-export-btn',
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'export_button_border',
                'selector' => '{{WRAPPER}} .vt-export-btn',
            ]
        );

        $this->add_responsive_control(
            'export_button_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-export-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Admin Action Buttons
        $this->start_controls_section(
            'admin_buttons_style_section',
            [
                'label' => __('Admin Action Buttons', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['show_admin_actions' => 'yes'],
            ]
        );

        $this->add_control(
            'approve_btn_heading',
            [
                'label' => __('Approve Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'approve_btn_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-approve-btn' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'approve_btn_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#10b981',
                'selectors' => [
                    '{{WRAPPER}} .vt-approve-btn' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'reject_btn_heading',
            [
                'label' => __('Reject Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'reject_btn_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-reject-btn' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'reject_btn_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ef4444',
                'selectors' => [
                    '{{WRAPPER}} .vt-reject-btn' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'delete_btn_heading',
            [
                'label' => __('Delete Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'delete_btn_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#6b7280',
                'selectors' => [
                    '{{WRAPPER}} .vt-delete-btn' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'delete_btn_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f3f4f6',
                'selectors' => [
                    '{{WRAPPER}} .vt-delete-btn' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'admin_btn_typography',
                'selector' => '{{WRAPPER}} .vt-admin-actions button',
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'admin_btn_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-admin-actions button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'admin_btn_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 20,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-admin-actions button' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Load More Button
        $this->start_controls_section(
            'load_more_style_section',
            [
                'label' => __('Load More Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->start_controls_tabs('load_more_tabs');

        $this->start_controls_tab('load_more_normal_tab', ['label' => __('Normal', 'voxel-toolkit')]);

        $this->add_control(
            'load_more_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-load-more-btn' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'load_more_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-load-more-btn' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab('load_more_hover_tab', ['label' => __('Hover', 'voxel-toolkit')]);

        $this->add_control(
            'load_more_text_color_hover',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-load-more-btn:hover' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'load_more_background_hover',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-load-more-btn:hover' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'load_more_typography',
                'selector' => '{{WRAPPER}} .vt-load-more-btn',
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'load_more_border',
                'selector' => '{{WRAPPER}} .vt-load-more-btn',
            ]
        );

        $this->add_responsive_control(
            'load_more_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-load-more-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Empty State
        $this->start_controls_section(
            'empty_state_style_section',
            [
                'label' => __('Empty State', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'empty_state_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-attendee-empty' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'empty_state_typography',
                'selector' => '{{WRAPPER}} .vt-attendee-empty',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $post_id = get_the_ID();

        if (!$post_id) {
            return;
        }

        // Get RSVP instance
        $rsvp_instance = Voxel_Toolkit_RSVP::instance();

        // Get statuses to show
        $statuses = !empty($settings['status_filter']) ? $settings['status_filter'] : array('approved');
        $items_per_page = absint($settings['items_per_page']) ?: 10;

        // Get RSVPs
        $rsvps = $rsvp_instance->get_rsvps($post_id, $statuses, 1, $items_per_page);
        $total = $rsvp_instance->get_rsvp_count($post_id, $statuses);
        $has_more = $total > $items_per_page;

        // Check if user can manage RSVPs
        $can_manage = current_user_can('edit_posts');

        // Status labels
        $status_labels = array(
            'approved' => $settings['status_approved_label'],
            'pending' => $settings['status_pending_label'],
            'rejected' => $settings['status_rejected_label'],
        );

        // Export URL
        $export_url = add_query_arg(array(
            'action' => 'vt_rsvp_export',
            'post_id' => $post_id,
            'statuses' => $statuses,
            'nonce' => wp_create_nonce('vt_rsvp_nonce'),
        ), admin_url('admin-ajax.php'));

        ?>
        <div class="vt-attendee-list-wrapper"
             data-post-id="<?php echo esc_attr($post_id); ?>"
             data-statuses="<?php echo esc_attr(implode(',', $statuses)); ?>"
             data-per-page="<?php echo esc_attr($items_per_page); ?>"
             data-page="1"
             data-total="<?php echo esc_attr($total); ?>"
             data-ajax-refresh="true"
             data-show-avatars="<?php echo esc_attr($settings['show_avatars']); ?>"
             data-show-comments="<?php echo esc_attr($settings['show_comments']); ?>"
             data-show-timestamps="<?php echo esc_attr($settings['show_timestamps']); ?>"
             data-show-status-badge="<?php echo esc_attr($settings['show_status_badge']); ?>"
             data-empty-message="<?php echo esc_attr($settings['empty_message']); ?>"
             data-status-approved-label="<?php echo esc_attr($settings['status_approved_label']); ?>"
             data-status-pending-label="<?php echo esc_attr($settings['status_pending_label']); ?>"
             data-status-rejected-label="<?php echo esc_attr($settings['status_rejected_label']); ?>">

            <div class="vt-attendee-list-header">
                <?php if (!empty($settings['list_title'])) : ?>
                    <h3 class="vt-attendee-list-title"><?php echo esc_html($settings['list_title']); ?></h3>
                <?php endif; ?>

                <?php if ($settings['show_export_button'] === 'yes' && $can_manage && $total > 0) : ?>
                    <a href="<?php echo esc_url($export_url); ?>" class="vt-export-btn" target="_blank">
                        <?php echo esc_html($settings['export_button_text']); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (empty($rsvps)) : ?>
                <div class="vt-attendee-empty">
                    <p><?php echo esc_html($settings['empty_message']); ?></p>
                </div>
            <?php else : ?>
                <div class="vt-attendee-list">
                    <?php foreach ($rsvps as $rsvp) :
                        $avatar_url = '';
                        if ($rsvp->user_id) {
                            $avatar_id = get_user_meta($rsvp->user_id, 'voxel:avatar', true);
                            if ($avatar_id) {
                                $avatar_url = wp_get_attachment_image_url($avatar_id, 'thumbnail');
                            }
                            if (!$avatar_url) {
                                $avatar_url = get_avatar_url($rsvp->user_id, array('size' => 100));
                            }
                        } else {
                            $avatar_url = get_avatar_url($rsvp->user_email, array('size' => 100));
                        }
                        $time_ago = human_time_diff(strtotime($rsvp->created_at), current_time('timestamp')) . ' ' . __('ago', 'voxel-toolkit');
                        ?>
                        <div class="vt-attendee-item" data-rsvp-id="<?php echo esc_attr($rsvp->id); ?>">
                            <div class="vt-attendee-main">
                                <?php if ($settings['show_avatars'] === 'yes') : ?>
                                    <img src="<?php echo esc_url($avatar_url); ?>" alt="" class="vt-attendee-avatar">
                                <?php endif; ?>

                                <div class="vt-attendee-info">
                                    <div class="vt-attendee-name-row">
                                        <span class="vt-attendee-name"><?php echo esc_html($rsvp->user_name); ?></span>
                                        <?php if ($settings['show_status_badge'] === 'yes') : ?>
                                            <span class="vt-status-badge vt-status-<?php echo esc_attr($rsvp->status); ?>">
                                                <?php echo esc_html($status_labels[$rsvp->status] ?? ucfirst($rsvp->status)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($settings['show_comments'] === 'yes' && !empty($rsvp->comment)) : ?>
                                        <div class="vt-attendee-comment"><?php echo esc_html($rsvp->comment); ?></div>
                                    <?php endif; ?>

                                    <?php if ($settings['show_timestamps'] === 'yes') : ?>
                                        <div class="vt-attendee-timestamp"><?php echo esc_html($time_ago); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($settings['show_admin_actions'] === 'yes' && $can_manage) : ?>
                                <div class="vt-admin-actions">
                                    <?php if ($rsvp->status === 'pending') : ?>
                                        <button type="button" class="vt-approve-btn" data-rsvp-id="<?php echo esc_attr($rsvp->id); ?>">
                                            <?php echo esc_html($settings['approve_button_text']); ?>
                                        </button>
                                        <button type="button" class="vt-reject-btn" data-rsvp-id="<?php echo esc_attr($rsvp->id); ?>">
                                            <?php echo esc_html($settings['reject_button_text']); ?>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="vt-delete-btn" data-rsvp-id="<?php echo esc_attr($rsvp->id); ?>">
                                        <?php echo esc_html($settings['delete_button_text']); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($has_more) : ?>
                    <div class="vt-load-more-wrapper">
                        <button type="button" class="vt-load-more-btn">
                            <?php echo esc_html($settings['load_more_text']); ?>
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
