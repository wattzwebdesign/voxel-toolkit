<?php
/**
 * Messenger Widget - Facebook-style floating chat widget
 *
 * Displays a floating messenger button in bottom-right corner with multi-chat support
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Messenger_Widget extends \Elementor\Widget_Base {

    public function get_script_depends() {
        return ['voxel-toolkit-messenger'];
    }

    public function get_style_depends() {
        return ['voxel-toolkit-messenger'];
    }

    public function get_name() {
        return 'voxel-messenger';
    }

    public function get_title() {
        return __('Messenger (VT)', 'voxel-toolkit');
    }

    public function get_icon() {
        return 'eicon-comments';
    }

    public function get_categories() {
        return ['voxel-toolkit'];
    }

    protected function register_controls() {
        // Content Tab
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'position',
            [
                'label' => __('Position', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'bottom-right',
                'options' => [
                    'bottom-right' => __('Bottom Right', 'voxel-toolkit'),
                    'bottom-left' => __('Bottom Left', 'voxel-toolkit'),
                ],
            ]
        );

        $this->add_control(
            'show_unread_badge',
            [
                'label' => __('Show Unread Badge', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'max_open_chats',
            [
                'label' => __('Max Open Chats', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 3,
                'min' => 1,
                'max' => 5,
            ]
        );

        $this->add_control(
            'main_button_icon',
            [
                'label' => __('Main Button Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
                'description' => __('Leave empty to use default messages.svg icon', 'voxel-toolkit'),
                'recommended' => [
                    'fa-solid' => [
                        'comment',
                        'comment-dots',
                        'comments',
                        'envelope',
                        'message',
                    ],
                ],
            ]
        );

        $this->add_control(
            'send_button_icon',
            [
                'label' => __('Send Button Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
                'description' => __('Leave empty to use default send.svg icon', 'voxel-toolkit'),
                'recommended' => [
                    'fa-solid' => [
                        'paper-plane',
                        'arrow-right',
                        'chevron-right',
                        'location-arrow',
                    ],
                ],
            ]
        );

        $this->add_control(
            'upload_button_icon',
            [
                'label' => __('Upload Button Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
                'description' => __('Leave empty to use default upload.svg icon', 'voxel-toolkit'),
                'recommended' => [
                    'fa-solid' => [
                        'paperclip',
                        'image',
                        'camera',
                        'file',
                        'upload',
                    ],
                ],
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
                'description' => __('Show example messenger with sample chats in the editor for styling', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // Style Tab - Button
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Main Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'button_size',
            [
                'label' => __('Button Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 40,
                        'max' => 80,
                        'step' => 5,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 60,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-button' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'button_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#0084ff',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-button' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_hover_background',
            [
                'label' => __('Hover Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#006ce5',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-button:hover' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_icon_color',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-button i' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_icon_size',
            [
                'label' => __('Icon Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 16,
                        'max' => 48,
                        'step' => 2,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 28,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-button i' => 'font-size: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_shadow',
                'label' => __('Button Shadow', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-messenger-button',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'label' => __('Border', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-messenger-button',
            ]
        );

        $this->add_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'unit' => '%',
                    'top' => 50,
                    'right' => 50,
                    'bottom' => 50,
                    'left' => 50,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Badge
        $this->start_controls_section(
            'badge_style_section',
            [
                'label' => __('Badge', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'badge_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ff0000',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-badge' => 'background-color: {{VALUE}}',
                    '{{WRAPPER}} .vt-chat-avatar-badge' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'badge_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-badge' => 'color: {{VALUE}}',
                    '{{WRAPPER}} .vt-chat-avatar-badge' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'badge_font_size',
            [
                'label' => __('Font Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 8,
                        'max' => 16,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 11,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-badge' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .vt-chat-avatar-badge' => 'font-size: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'badge_size',
            [
                'label' => __('Badge Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 16,
                        'max' => 32,
                        'step' => 2,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-badge' => 'min-width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .vt-chat-avatar-badge' => 'min-width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'badge_border',
                'label' => __('Border', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-messenger-badge, {{WRAPPER}} .vt-chat-avatar-badge',
            ]
        );

        $this->end_controls_section();

        // Style Tab - Chat Circles/Avatars
        $this->start_controls_section(
            'chat_circles_style_section',
            [
                'label' => __('Chat Circles', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'chat_circle_size',
            [
                'label' => __('Circle Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 40,
                        'max' => 80,
                        'step' => 4,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 56,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-chat-item' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .vt-chat-avatar' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .vt-chat-avatar img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'chat_circle_gap',
            [
                'label' => __('Gap Between Circles', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 4,
                        'max' => 24,
                        'step' => 2,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 12,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-chat-list' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'chat_circle_border',
                'label' => __('Border', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-chat-avatar',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'chat_circle_shadow',
                'label' => __('Circle Shadow', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-chat-avatar',
            ]
        );

        $this->end_controls_section();

        // Style Tab - Tooltip
        $this->start_controls_section(
            'tooltip_style_section',
            [
                'label' => __('Tooltip', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'tooltip_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => 'rgba(0,0,0,0.95)',
                'selectors' => [
                    '{{WRAPPER}} .vt-chat-tooltip-floating' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'tooltip_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-chat-tooltip-floating' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'tooltip_width',
            [
                'label' => __('Tooltip Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 180,
                        'max' => 350,
                        'step' => 10,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 250,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-chat-tooltip-floating' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'tooltip_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 20,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-chat-tooltip-floating' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'tooltip_shadow',
                'label' => __('Tooltip Shadow', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-chat-tooltip-floating',
            ]
        );

        $this->end_controls_section();

        // Style Tab - Chat Windows
        $this->start_controls_section(
            'chat_style_section',
            [
                'label' => __('Chat Windows', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'chat_width',
            [
                'label' => __('Chat Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 280,
                        'max' => 400,
                        'step' => 10,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 320,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-chat-window' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'chat_height',
            [
                'label' => __('Chat Height', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 300,
                        'max' => 600,
                        'step' => 10,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 400,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-chat-window' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'chat_header_background',
            [
                'label' => __('Header Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#0084ff',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-chat-header' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'chat_body_background',
            [
                'label' => __('Body Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-chat-body' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'chat_window_border_radius',
            [
                'label' => __('Window Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 12,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-chat-window' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'chat_window_shadow',
                'label' => __('Window Shadow', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-messenger-chat-window',
            ]
        );

        $this->add_control(
            'chat_header_text_color',
            [
                'label' => __('Header Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-chat-header-name' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'chat_header_avatar_size',
            [
                'label' => __('Header Avatar Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 24,
                        'max' => 48,
                        'step' => 2,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 32,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-chat-header-avatar' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .vt-chat-header-avatar img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Messages
        $this->start_controls_section(
            'messages_style_section',
            [
                'label' => __('Messages', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'message_sent_heading',
            [
                'label' => __('Sent Messages', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'message_sent_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#0084ff',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-message.sent .vt-message-bubble' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'message_sent_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-message.sent .vt-message-bubble' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'message_sent_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 18,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-message.sent .vt-message-bubble' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'message_received_heading',
            [
                'label' => __('Received Messages', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'message_received_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e4e6eb',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-message.received .vt-message-bubble' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'message_received_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#050505',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-message.received .vt-message-bubble' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'message_received_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 18,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-message.received .vt-message-bubble' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'message_general_heading',
            [
                'label' => __('General', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'message_typography',
                'label' => __('Message Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-message-content',
            ]
        );

        $this->add_control(
            'message_time_color',
            [
                'label' => __('Time Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#65676b',
                'selectors' => [
                    '{{WRAPPER}} .vt-message-time' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'message_time_font_size',
            [
                'label' => __('Time Font Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 8,
                        'max' => 16,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 11,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-message-time' => 'font-size: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Input Area
        $this->start_controls_section(
            'input_style_section',
            [
                'label' => __('Input Area', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'footer_background',
            [
                'label' => __('Footer Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-chat-footer' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'input_background',
            [
                'label' => __('Input Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-input' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'input_border_color',
            [
                'label' => __('Input Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ced0d4',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-input' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'input_border_focus_color',
            [
                'label' => __('Input Border Focus Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#0084ff',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-input:focus' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'input_text_color',
            [
                'label' => __('Input Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-input' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'input_typography',
                'label' => __('Input Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-messenger-input',
            ]
        );

        $this->add_control(
            'send_button_heading',
            [
                'label' => __('Send Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'send_button_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#0084ff',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-send-btn' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'send_button_hover_background',
            [
                'label' => __('Hover Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#006ce5',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-send-btn:hover:not(:disabled)' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'send_button_icon_color',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-send-btn' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'send_button_size',
            [
                'label' => __('Button Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 28,
                        'max' => 48,
                        'step' => 2,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 36,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-send-btn' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'upload_button_heading',
            [
                'label' => __('Upload Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'upload_button_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => 'transparent',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-upload-btn' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'upload_button_hover_background',
            [
                'label' => __('Hover Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f0f2f5',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-upload-btn:hover' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'upload_button_icon_color',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#0084ff',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-upload-btn' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render icon with fallback to custom SVG
     */
    private function render_icon_with_fallback($icon_setting, $fallback_svg_name) {
        if (!empty($icon_setting['value'])) {
            // User has customized the icon
            \Elementor\Icons_Manager::render_icon($icon_setting, ['aria-hidden' => 'true']);
        } else {
            // Use custom SVG fallback
            $svg_path = VOXEL_TOOLKIT_PLUGIN_URL . 'assets/icons/' . $fallback_svg_name . '.svg';
            echo '<img src="' . esc_url($svg_path) . '" alt="" aria-hidden="true" />';
        }
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
        $preview_mode = isset($settings['preview_mode']) && $settings['preview_mode'] === 'yes';

        // Check if user is logged in (skip in editor preview mode)
        if (!is_user_logged_in() && !($is_editor && $preview_mode)) {
            if ($is_editor) {
                echo '<p>' . __('Messenger widget is only visible to logged-in users. Enable Preview Mode to see example content.', 'voxel-toolkit') . '</p>';
            }
            return;
        }

        // Check page rules from settings (skip in preview mode)
        if (!$preview_mode) {
            $messenger_settings = get_option('voxel_toolkit_messenger_settings', array());
            if (!$this->should_display_messenger($messenger_settings)) {
                return;
            }
        }

        $position_class = 'vt-messenger-position-' . $settings['position'];
        $max_chats = !empty($settings['max_open_chats']) ? $settings['max_open_chats'] : 3;
        ?>
        <div class="vt-messenger-container <?php echo esc_attr($position_class); ?> <?php echo ($is_editor && $preview_mode) ? 'vt-preview-mode' : ''; ?>"
             data-max-chats="<?php echo esc_attr($max_chats); ?>"
             data-show-badge="<?php echo esc_attr($settings['show_unread_badge']); ?>">

            <!-- Main Messenger Button -->
            <button class="vt-messenger-button" aria-label="<?php _e('Open messenger', 'voxel-toolkit'); ?>">
                <?php $this->render_icon_with_fallback($settings['main_button_icon'], 'messages'); ?>
                <?php if ($settings['show_unread_badge'] === 'yes'): ?>
                    <span class="vt-messenger-badge" <?php echo ($is_editor && $preview_mode) ? '' : 'style="display: none;"'; ?>>
                        <?php echo ($is_editor && $preview_mode) ? '3' : '0'; ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- Chat List Popup -->
            <div class="vt-messenger-popup" <?php echo ($is_editor && $preview_mode) ? '' : 'style="display: none;"'; ?>>
                <div class="vt-messenger-popup-header">
                    <h3><?php _e('Chats', 'voxel-toolkit'); ?></h3>
                    <button class="vt-messenger-close" aria-label="<?php _e('Close', 'voxel-toolkit'); ?>">
                        <i class="eicon-close"></i>
                    </button>
                </div>

                <div class="vt-messenger-search">
                    <input type="text"
                           class="vt-messenger-search-input"
                           placeholder="<?php echo esc_attr__('Search messages...', 'voxel-toolkit'); ?>">
                </div>

                <div class="vt-messenger-chat-list">
                    <?php if ($is_editor && $preview_mode): ?>
                        <!-- Preview Mode: Example Chat Circles -->
                        <div class="vt-messenger-chat-item" data-chat-key="preview-1">
                            <div class="vt-chat-avatar">
                                <img src="https://i.pravatar.cc/150?img=1" alt="John Smith">
                                <span class="vt-chat-avatar-badge">2</span>
                            </div>
                        </div>
                        <div class="vt-messenger-chat-item" data-chat-key="preview-2">
                            <div class="vt-chat-avatar">
                                <img src="https://i.pravatar.cc/150?img=5" alt="Sarah Johnson">
                                <span class="vt-chat-avatar-badge">1</span>
                            </div>
                        </div>
                        <div class="vt-messenger-chat-item" data-chat-key="preview-3">
                            <div class="vt-chat-avatar">
                                <img src="https://i.pravatar.cc/150?img=12" alt="Mike Wilson">
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="vt-messenger-loading">
                            <i class="eicon-loading eicon-animation-spin"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat Windows Container -->
            <div class="vt-messenger-chat-windows">
                <?php if ($is_editor && $preview_mode): ?>
                    <!-- Preview Mode: Example Chat Window -->
                    <div class="vt-messenger-chat-window" data-chat-key="preview-1">
                        <div class="vt-messenger-chat-header">
                            <div class="vt-chat-header-info">
                                <div class="vt-chat-header-avatar">
                                    <img src="https://i.pravatar.cc/150?img=1" alt="John Smith">
                                </div>
                                <div class="vt-chat-header-name">John Smith</div>
                            </div>
                            <div class="vt-chat-header-actions">
                                <button class="vt-messenger-chat-minimize" aria-label="<?php _e('Minimize', 'voxel-toolkit'); ?>">
                                    <span>−</span>
                                </button>
                                <button class="vt-messenger-chat-close" aria-label="<?php _e('Close', 'voxel-toolkit'); ?>">
                                    <span>×</span>
                                </button>
                            </div>
                        </div>
                        <div class="vt-messenger-chat-body">
                            <div class="vt-messenger-messages">
                                <!-- Preview: Received Message -->
                                <div class="vt-messenger-message received">
                                    <div class="vt-message-bubble">
                                        <div class="vt-message-content">
                                            <p>Hello! How can I help you today?</p>
                                        </div>
                                    </div>
                                    <div class="vt-message-time">10:30 AM</div>
                                </div>
                                <!-- Preview: Sent Message -->
                                <div class="vt-messenger-message sent">
                                    <div class="vt-message-bubble">
                                        <div class="vt-message-content">
                                            <p>Hi! I have a question about the widget.</p>
                                        </div>
                                    </div>
                                    <div class="vt-message-time">10:32 AM</div>
                                    <div class="vt-message-seen-badge">Seen</div>
                                </div>
                                <!-- Preview: Received Message -->
                                <div class="vt-messenger-message received">
                                    <div class="vt-message-bubble">
                                        <div class="vt-message-content">
                                            <p>Sure, what would you like to know?</p>
                                        </div>
                                    </div>
                                    <div class="vt-message-time">10:33 AM</div>
                                </div>
                            </div>
                        </div>
                        <div class="vt-messenger-chat-footer">
                            <textarea class="vt-messenger-input"
                                      placeholder="<?php echo esc_attr__('Type a message...', 'voxel-toolkit'); ?>"
                                      rows="1"></textarea>
                            <div class="vt-messenger-upload-buttons">
                                <button class="vt-messenger-upload-btn vt-upload-device" aria-label="<?php _e('Upload from device', 'voxel-toolkit'); ?>">
                                    <?php $this->render_icon_with_fallback($settings['upload_button_icon'], 'upload'); ?>
                                </button>
                            </div>
                            <button class="vt-messenger-send-btn" aria-label="<?php _e('Send', 'voxel-toolkit'); ?>">
                                <?php $this->render_icon_with_fallback($settings['send_button_icon'], 'send'); ?>
                            </button>
                            <input type="file" class="vt-messenger-file-input" style="display: none;" accept="image/*">
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Check if messenger should be displayed based on page rules
     */
    private function should_display_messenger($settings) {
        if (empty($settings['enabled'])) {
            return false;
        }

        $excluded_rules = !empty($settings['excluded_rules']) ? $settings['excluded_rules'] : array();
        $excluded_post_types = !empty($settings['excluded_post_types']) ? $settings['excluded_post_types'] : array();

        // Check excluded post IDs
        if (!empty($settings['excluded_post_ids'])) {
            $excluded_ids = array_map('trim', explode(',', $settings['excluded_post_ids']));
            if (in_array(get_the_ID(), $excluded_ids)) {
                return false;
            }
        }

        // Check excluded post types
        if (is_singular() && !empty($excluded_post_types)) {
            $current_post_type = get_post_type();
            if (in_array($current_post_type, $excluded_post_types)) {
                return false;
            }
        }

        // Check page type rules
        foreach ($excluded_rules as $rule) {
            switch ($rule) {
                case 'singular':
                    if (is_singular()) return false;
                    break;
                case 'archive':
                    if (is_archive()) return false;
                    break;
                case 'home':
                    if (is_front_page() || is_home()) return false;
                    break;
                case 'search':
                    if (is_search()) return false;
                    break;
                case '404':
                    if (is_404()) return false;
                    break;
            }
        }

        return true;
    }
}
