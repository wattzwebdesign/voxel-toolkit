<?php
/**
 * RSVP Form Widget
 *
 * Elementor widget for submitting RSVPs to posts/events
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_RSVP_Form_Widget extends \Elementor\Widget_Base {

    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);

        // Enqueue scripts and styles
        wp_enqueue_style('voxel-toolkit-rsvp');
        wp_enqueue_script('voxel-toolkit-rsvp');
    }

    public function get_name() {
        return 'voxel-rsvp-form';
    }

    public function get_title() {
        return __('RSVP Form (VT)', 'voxel-toolkit');
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    public function get_categories() {
        return ['voxel-toolkit'];
    }

    protected function register_controls() {
        // Content Tab - Form Settings
        $this->start_controls_section(
            'form_settings_section',
            [
                'label' => __('Form Settings', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        // Post type selector for schema registration
        $post_type_options = ['' => __('— Select Post Type —', 'voxel-toolkit')];
        if (class_exists('\\Voxel\\Post_Type')) {
            foreach (\Voxel\Post_Type::get_voxel_types() as $post_type) {
                $post_type_options[$post_type->get_key()] = $post_type->get_label();
            }
        }

        $this->add_control(
            'target_post_type',
            [
                'label' => __('Post Type', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $post_type_options,
                'default' => '',
                'description' => __('Required. Select the post type this RSVP form is for.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'form_description',
            [
                'label' => __('Form Description', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('RSVP to this event to secure your spot.', 'voxel-toolkit'),
                'rows' => 3,
            ]
        );

        $this->add_control(
            'allow_guest_rsvp',
            [
                'label' => __('Allow Guest RSVPs', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Allow non-logged-in users to RSVP', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'login_required_message',
            [
                'label' => __('Login Required Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Please log in to RSVP to this event.', 'voxel-toolkit'),
                'condition' => ['allow_guest_rsvp' => ''],
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __('Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('RSVP', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'cancel_button_text',
            [
                'label' => __('Cancel Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Cancel RSVP', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'require_approval',
            [
                'label' => __('Require Approval', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('RSVPs will be pending until approved', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'max_attendees',
            [
                'label' => __('Max Attendees', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
                'min' => 0,
                'description' => __('0 = unlimited', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'show_count',
            [
                'label' => __('Show RSVP Count', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Content Tab - Labels & Messages
        $this->start_controls_section(
            'labels_section',
            [
                'label' => __('Messages', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'success_message',
            [
                'label' => __('Success Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Thank you! Your RSVP has been submitted.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'pending_message',
            [
                'label' => __('Pending Approval Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Your RSVP is pending approval.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'closed_message',
            [
                'label' => __('RSVP Closed Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('RSVP is closed - maximum capacity reached.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'count_format',
            [
                'label' => __('Count Format', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('{current} / {max} attending', 'voxel-toolkit'),
                'description' => __('Use {current} and {max} placeholders', 'voxel-toolkit'),
                'condition' => ['show_count' => 'yes'],
            ]
        );

        $this->add_control(
            'count_format_unlimited',
            [
                'label' => __('Count Format (Unlimited)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('{current} attending', 'voxel-toolkit'),
                'description' => __('Use {current} placeholder', 'voxel-toolkit'),
                'condition' => ['show_count' => 'yes'],
            ]
        );

        $this->end_controls_section();

        // Content Tab - Guest Fields
        $this->start_controls_section(
            'guest_fields_section',
            [
                'label' => __('Guest Fields', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                'condition' => ['allow_guest_rsvp' => 'yes'],
            ]
        );

        $repeater = new \Elementor\Repeater();

        $repeater->add_control(
            'field_label',
            [
                'label' => __('Field Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Field', 'voxel-toolkit'),
            ]
        );

        $repeater->add_control(
            'field_key',
            [
                'label' => __('Field Key', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'field',
                'description' => __('Unique identifier (no spaces, lowercase)', 'voxel-toolkit'),
            ]
        );

        $repeater->add_control(
            'field_type',
            [
                'label' => __('Field Type', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'text',
                'options' => [
                    'text' => __('Text', 'voxel-toolkit'),
                    'email' => __('Email', 'voxel-toolkit'),
                    'textarea' => __('Textarea', 'voxel-toolkit'),
                    'select' => __('Dropdown', 'voxel-toolkit'),
                    'number' => __('Number', 'voxel-toolkit'),
                ],
            ]
        );

        $repeater->add_control(
            'field_placeholder',
            [
                'label' => __('Placeholder', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'condition' => [
                    'field_type!' => 'select',
                ],
            ]
        );

        $repeater->add_control(
            'field_options',
            [
                'label' => __('Options', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'description' => __('One option per line', 'voxel-toolkit'),
                'condition' => [
                    'field_type' => 'select',
                ],
            ]
        );

        $repeater->add_control(
            'field_required',
            [
                'label' => __('Required', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
            ]
        );

        $this->add_control(
            'guest_fields',
            [
                'label' => __('Fields', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => [
                    [
                        'field_label' => __('Your Name', 'voxel-toolkit'),
                        'field_key' => 'user_name',
                        'field_type' => 'text',
                        'field_placeholder' => '',
                        'field_required' => 'yes',
                    ],
                    [
                        'field_label' => __('Your Email', 'voxel-toolkit'),
                        'field_key' => 'user_email',
                        'field_type' => 'email',
                        'field_placeholder' => '',
                        'field_required' => 'yes',
                    ],
                    [
                        'field_label' => __('Comment', 'voxel-toolkit'),
                        'field_key' => 'comment',
                        'field_type' => 'textarea',
                        'field_placeholder' => __('Any dietary requirements or notes...', 'voxel-toolkit'),
                        'field_required' => '',
                    ],
                ],
                'title_field' => '{{{ field_label }}}',
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
                    '{{WRAPPER}} .vt-rsvp-form-wrapper' => 'background-color: {{VALUE}}',
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
                    '{{WRAPPER}} .vt-rsvp-form-wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .vt-rsvp-form-wrapper',
            ]
        );

        $this->add_control(
            'container_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-form-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Description
        $this->start_controls_section(
            'description_style_section',
            [
                'label' => __('Description', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'description_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-description' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography',
                'selector' => '{{WRAPPER}} .vt-rsvp-description',
            ]
        );

        $this->add_responsive_control(
            'description_margin',
            [
                'label' => __('Margin', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-description' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Labels
        $this->start_controls_section(
            'labels_style_section',
            [
                'label' => __('Labels', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-label' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'label_typography',
                'selector' => '{{WRAPPER}} .vt-rsvp-label',
            ]
        );

        $this->end_controls_section();

        // Style Tab - Input Fields
        $this->start_controls_section(
            'input_style_section',
            [
                'label' => __('Input Fields', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'input_typography',
                'selector' => '{{WRAPPER}} .vt-rsvp-input, {{WRAPPER}} .vt-rsvp-textarea, {{WRAPPER}} .vt-rsvp-select',
            ]
        );

        $this->add_control(
            'input_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-input, {{WRAPPER}} .vt-rsvp-textarea, {{WRAPPER}} .vt-rsvp-select' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'input_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-input, {{WRAPPER}} .vt-rsvp-textarea, {{WRAPPER}} .vt-rsvp-select' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'input_border',
                'selector' => '{{WRAPPER}} .vt-rsvp-input, {{WRAPPER}} .vt-rsvp-textarea, {{WRAPPER}} .vt-rsvp-select',
            ]
        );

        $this->add_control(
            'input_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-input, {{WRAPPER}} .vt-rsvp-textarea, {{WRAPPER}} .vt-rsvp-select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'input_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-input, {{WRAPPER}} .vt-rsvp-textarea, {{WRAPPER}} .vt-rsvp-select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Button
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Submit Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->start_controls_tabs('button_tabs');

        $this->start_controls_tab('button_normal_tab', ['label' => __('Normal', 'voxel-toolkit')]);

        $this->add_control(
            'button_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-submit-btn' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_background_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-submit-btn' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab('button_hover_tab', ['label' => __('Hover', 'voxel-toolkit')]);

        $this->add_control(
            'button_text_color_hover',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-submit-btn:hover' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_background_color_hover',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-submit-btn:hover' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .vt-rsvp-submit-btn',
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .vt-rsvp-submit-btn',
            ]
        );

        $this->add_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-submit-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-submit-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Cancel Button
        $this->start_controls_section(
            'cancel_button_style_section',
            [
                'label' => __('Cancel Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->start_controls_tabs('cancel_button_tabs');

        $this->start_controls_tab('cancel_button_normal_tab', ['label' => __('Normal', 'voxel-toolkit')]);

        $this->add_control(
            'cancel_button_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-cancel-btn' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'cancel_button_background_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-cancel-btn' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab('cancel_button_hover_tab', ['label' => __('Hover', 'voxel-toolkit')]);

        $this->add_control(
            'cancel_button_text_color_hover',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-cancel-btn:hover' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'cancel_button_background_color_hover',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-cancel-btn:hover' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'cancel_button_typography',
                'selector' => '{{WRAPPER}} .vt-rsvp-cancel-btn',
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'cancel_button_border',
                'selector' => '{{WRAPPER}} .vt-rsvp-cancel-btn',
            ]
        );

        $this->add_control(
            'cancel_button_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-cancel-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'cancel_button_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-cancel-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Count Display
        $this->start_controls_section(
            'count_style_section',
            [
                'label' => __('Count Display', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['show_count' => 'yes'],
            ]
        );

        $this->add_control(
            'count_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-count' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'count_typography',
                'selector' => '{{WRAPPER}} .vt-rsvp-count',
            ]
        );

        $this->end_controls_section();

        // Style Tab - Status Messages
        $this->start_controls_section(
            'messages_style_section',
            [
                'label' => __('Status Messages', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'success_message_color',
            [
                'label' => __('Success Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#10b981',
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-message.vt-rsvp-success' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'error_message_color',
            [
                'label' => __('Error Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ef4444',
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-message.vt-rsvp-error' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'pending_message_color',
            [
                'label' => __('Pending Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f59e0b',
                'selectors' => [
                    '{{WRAPPER}} .vt-rsvp-message.vt-rsvp-pending' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'message_typography',
                'selector' => '{{WRAPPER}} .vt-rsvp-message',
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

        // Save field schema for this post type (for dynamic tags in app events)
        $target_post_type = $settings['target_post_type'] ?? '';
        if ($target_post_type && !empty($settings['guest_fields'])) {
            $this->save_field_schema($target_post_type, $settings['guest_fields']);
        }

        // Get RSVP instance
        $rsvp_instance = Voxel_Toolkit_RSVP::instance();

        // Get current user info
        $is_logged_in = is_user_logged_in();
        $current_user_email = '';
        $current_user_name = '';

        if ($is_logged_in) {
            $user = wp_get_current_user();
            $current_user_email = $user->user_email;
            $current_user_name = $user->display_name;
        }

        // Check if user already has RSVP
        $user_rsvp = null;
        if ($is_logged_in) {
            $user_rsvp = $rsvp_instance->get_user_rsvp($post_id, $current_user_email);
        }

        // Get current count
        $max_attendees = absint($settings['max_attendees']);
        $current_count = $rsvp_instance->get_rsvp_count($post_id, array('approved'));
        $is_closed = ($max_attendees > 0 && $current_count >= $max_attendees && !$user_rsvp);

        // Format count display
        $count_display = '';
        if ($settings['show_count'] === 'yes') {
            if ($max_attendees > 0) {
                $count_display = str_replace(
                    array('{current}', '{max}'),
                    array($current_count, $max_attendees),
                    $settings['count_format']
                );
            } else {
                $count_display = str_replace(
                    '{current}',
                    $current_count,
                    $settings['count_format_unlimited']
                );
            }
        }

        ?>
        <div class="vt-rsvp-form-wrapper"
             data-post-id="<?php echo esc_attr($post_id); ?>"
             data-require-approval="<?php echo esc_attr($settings['require_approval']); ?>"
             data-max-attendees="<?php echo esc_attr($max_attendees); ?>"
             data-show-count="<?php echo esc_attr($settings['show_count']); ?>"
             data-count-format="<?php echo esc_attr($settings['count_format'] ?? '{current} / {max} attending'); ?>"
             data-count-format-unlimited="<?php echo esc_attr($settings['count_format_unlimited'] ?? '{current} attending'); ?>"
             data-ajax-refresh="true">

            <?php if (!empty($settings['form_description'])) : ?>
                <div class="vt-rsvp-description"><?php echo esc_html($settings['form_description']); ?></div>
            <?php endif; ?>

            <?php if ($settings['show_count'] === 'yes' && $count_display) : ?>
                <div class="vt-rsvp-count"><?php echo esc_html($count_display); ?></div>
            <?php endif; ?>

            <?php
            // Check if guest RSVPs are allowed
            $allow_guest_rsvp = $settings['allow_guest_rsvp'] === 'yes';
            $show_login_message = !$is_logged_in && !$allow_guest_rsvp;
            ?>

            <?php if ($show_login_message) : ?>
                <div class="vt-rsvp-login-required">
                    <p class="vt-rsvp-message vt-rsvp-pending"><?php echo esc_html($settings['login_required_message']); ?></p>
                </div>
            <?php elseif ($is_closed) : ?>
                <div class="vt-rsvp-closed">
                    <p class="vt-rsvp-message vt-rsvp-error"><?php echo esc_html($settings['closed_message']); ?></p>
                </div>
            <?php elseif ($user_rsvp) : ?>
                <!-- User already RSVP'd - show status and cancel option -->
                <div class="vt-rsvp-status">
                    <?php if ($user_rsvp->status === 'pending') : ?>
                        <p class="vt-rsvp-message vt-rsvp-pending"><?php echo esc_html($settings['pending_message']); ?></p>
                    <?php else : ?>
                        <p class="vt-rsvp-message vt-rsvp-success"><?php echo esc_html($settings['success_message']); ?></p>
                    <?php endif; ?>
                </div>
                <button type="button" class="vt-rsvp-cancel-btn" data-email="<?php echo esc_attr($current_user_email); ?>">
                    <?php echo esc_html($settings['cancel_button_text']); ?>
                </button>
            <?php else : ?>
                <!-- RSVP Form -->
                <form class="vt-rsvp-form">
                    <?php
                    // Render guest fields
                    // Special keys: user_name, user_email map to top-level form fields
                    // comment maps to the comment field
                    // Other keys are custom fields stored in JSON
                    $guest_fields = !empty($settings['guest_fields']) ? $settings['guest_fields'] : array();
                    $special_keys = array('user_name', 'user_email', 'comment');

                    foreach ($guest_fields as $index => $field) :
                        $field_key = sanitize_key($field['field_key']);
                        $field_required = $field['field_required'] === 'yes';
                        $field_id = 'vt-rsvp-field-' . $index . '-' . $this->get_id();

                        // Skip name/email fields for logged-in users
                        if ($is_logged_in && in_array($field_key, array('user_name', 'user_email'))) {
                            continue;
                        }

                        // Determine the input name
                        if (in_array($field_key, $special_keys)) {
                            $input_name = $field_key;
                        } else {
                            $input_name = 'custom_fields[' . $field_key . ']';
                        }
                        ?>
                        <div class="vt-rsvp-field">
                            <label class="vt-rsvp-label" for="<?php echo esc_attr($field_id); ?>">
                                <?php echo esc_html($field['field_label']); ?>
                                <?php if ($field_required) : ?><span class="required">*</span><?php endif; ?>
                            </label>
                            <?php if ($field['field_type'] === 'textarea') : ?>
                                <textarea id="<?php echo esc_attr($field_id); ?>"
                                          name="<?php echo esc_attr($input_name); ?>"
                                          class="vt-rsvp-textarea"
                                          placeholder="<?php echo esc_attr($field['field_placeholder'] ?? ''); ?>"
                                          <?php echo $field_required ? 'required' : ''; ?>></textarea>
                            <?php elseif ($field['field_type'] === 'select') : ?>
                                <select id="<?php echo esc_attr($field_id); ?>"
                                        name="<?php echo esc_attr($input_name); ?>"
                                        class="vt-rsvp-select"
                                        <?php echo $field_required ? 'required' : ''; ?>>
                                    <option value=""><?php _e('Select...', 'voxel-toolkit'); ?></option>
                                    <?php
                                    $options = array_filter(array_map('trim', explode("\n", $field['field_options'] ?? '')));
                                    foreach ($options as $option) :
                                        ?>
                                        <option value="<?php echo esc_attr($option); ?>"><?php echo esc_html($option); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($field['field_type'] === 'number') : ?>
                                <input type="number"
                                       id="<?php echo esc_attr($field_id); ?>"
                                       name="<?php echo esc_attr($input_name); ?>"
                                       class="vt-rsvp-input"
                                       placeholder="<?php echo esc_attr($field['field_placeholder'] ?? ''); ?>"
                                       <?php echo $field_required ? 'required' : ''; ?>>
                            <?php elseif ($field['field_type'] === 'email') : ?>
                                <input type="email"
                                       id="<?php echo esc_attr($field_id); ?>"
                                       name="<?php echo esc_attr($input_name); ?>"
                                       class="vt-rsvp-input"
                                       placeholder="<?php echo esc_attr($field['field_placeholder'] ?? ''); ?>"
                                       <?php echo $field_required ? 'required' : ''; ?>>
                            <?php else : ?>
                                <input type="text"
                                       id="<?php echo esc_attr($field_id); ?>"
                                       name="<?php echo esc_attr($input_name); ?>"
                                       class="vt-rsvp-input"
                                       placeholder="<?php echo esc_attr($field['field_placeholder'] ?? ''); ?>"
                                       <?php echo $field_required ? 'required' : ''; ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="vt-rsvp-message-container"></div>

                    <button type="submit" class="vt-rsvp-submit-btn">
                        <?php echo esc_html($settings['button_text']); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save field schema for dynamic tag generation in app events
     *
     * @param string $post_type Post type key
     * @param array $guest_fields Fields from widget settings
     */
    private function save_field_schema($post_type, $guest_fields) {
        // Load schema class if not already loaded
        if (!class_exists('Voxel_Toolkit_RSVP_Schema')) {
            $schema_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/class-rsvp-schema.php';
            if (file_exists($schema_file)) {
                require_once $schema_file;
            } else {
                error_log('VT RSVP: Schema file not found at ' . $schema_file);
                return;
            }
        }

        $schema = Voxel_Toolkit_RSVP_Schema::instance();
        $fields = [];

        foreach ($guest_fields as $field) {
            $key = sanitize_key($field['field_key'] ?? '');
            if (empty($key)) {
                continue;
            }

            $fields[$key] = [
                'key' => $key,
                'label' => $field['field_label'] ?? ucwords(str_replace('_', ' ', $key)),
                'type' => $field['field_type'] ?? 'text',
                'required' => ($field['field_required'] ?? '') === 'yes',
            ];
        }

        if (!empty($fields)) {
            // Use replace (not merge) so re-saving clears old/stale fields
            $schema->replace_schema($post_type, $fields);
        }
    }
}
