<?php
/**
 * Checklist Field Elementor Styles
 *
 * Adds styling controls to the Create Post widget for checklist fields.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Checklist_Field_Elementor_Styles {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        if (self::$instance !== null) {
            return;
        }
        self::$instance = $this;

        add_action('elementor/element/ts-create-post/custom_popup/after_section_end', array($this, 'add_checklist_style_controls'), 10, 2);
    }

    /**
     * Add checklist styling controls to Create Post widget
     */
    public function add_checklist_style_controls($element, $args) {
        // Checklist Card Section
        $element->start_controls_section(
            'vt_checklist_card_style',
            [
                'label' => __('Checklist Card (VT)', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $element->add_control(
            'vt_checklist_card_bg',
            [
                'label' => __('Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item-row' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_card_border_color',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item-row' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_card_border_width',
            [
                'label' => __('Border Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 5,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item-row' => 'border-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_card_border_radius',
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
                    '{{WRAPPER}} .vt-checklist-item-row' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_card_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item-row' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_card_gap',
            [
                'label' => __('Gap Between Cards', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 40,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-items' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $element->end_controls_section();

        // Checklist Input Section
        $element->start_controls_section(
            'vt_checklist_input_style',
            [
                'label' => __('Checklist Input (VT)', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $element->add_control(
            'vt_checklist_input_bg',
            [
                'label' => __('Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item-content input.ts-filter, {{WRAPPER}} .vt-checklist-item-content textarea.ts-filter' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_input_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item-content input.ts-filter, {{WRAPPER}} .vt-checklist-item-content textarea.ts-filter' => 'color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_input_placeholder_color',
            [
                'label' => __('Placeholder Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item-content input.ts-filter::placeholder, {{WRAPPER}} .vt-checklist-item-content textarea.ts-filter::placeholder' => 'color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_input_border_color',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item-content input.ts-filter, {{WRAPPER}} .vt-checklist-item-content textarea.ts-filter' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_input_border_radius',
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
                    '{{WRAPPER}} .vt-checklist-item-content input.ts-filter, {{WRAPPER}} .vt-checklist-item-content textarea.ts-filter' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $element->end_controls_section();

        // Checklist Icons Section
        $element->start_controls_section(
            'vt_checklist_icons_style',
            [
                'label' => __('Checklist Icons (VT)', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $element->add_control(
            'vt_checklist_drag_heading',
            [
                'label' => __('Drag Handle', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
            ]
        );

        $element->add_control(
            'vt_checklist_drag_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-drag-handle' => 'color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_drag_hover_color',
            [
                'label' => __('Hover Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-drag-handle:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_delete_heading',
            [
                'label' => __('Delete Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $element->add_control(
            'vt_checklist_delete_bg',
            [
                'label' => __('Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item-actions .ts-icon-btn' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_delete_color',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item-actions .ts-icon-btn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_delete_border_color',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item-actions .ts-icon-btn' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_delete_hover_bg',
            [
                'label' => __('Hover Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item-actions .ts-icon-btn:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_delete_hover_color',
            [
                'label' => __('Hover Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item-actions .ts-icon-btn:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_delete_border_radius',
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
                    '{{WRAPPER}} .vt-checklist-item-actions .ts-icon-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_delete_size',
            [
                'label' => __('Button Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 30,
                        'max' => 60,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item-actions .ts-icon-btn' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $element->end_controls_section();

        // Add Item Button Section
        $element->start_controls_section(
            'vt_checklist_add_btn_style',
            [
                'label' => __('Checklist Add Button (VT)', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $element->add_control(
            'vt_checklist_add_btn_bg',
            [
                'label' => __('Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-field > .ts-form-group > .ts-btn' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_add_btn_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-field > .ts-form-group > .ts-btn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_add_btn_border_radius',
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
                    '{{WRAPPER}} .vt-checklist-field > .ts-form-group > .ts-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $element->add_control(
            'vt_checklist_add_btn_hover_bg',
            [
                'label' => __('Hover Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-field > .ts-form-group > .ts-btn:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $element->end_controls_section();
    }
}
