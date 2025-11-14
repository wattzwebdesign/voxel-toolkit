<?php
/**
 * Elementor Profile Progress Widget
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Elementor_Profile_Progress extends \Elementor\Widget_Base {
    
    /**
     * Class constructor
     */
    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);
        
        wp_register_style(
            'voxel-profile-progress', 
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/profile-progress.css',
            [],
            VOXEL_TOOLKIT_VERSION
        );
        
    }
    
    /**
     * Get style dependencies
     */
    public function get_style_depends() {
        return ['voxel-profile-progress'];
    }
    
    /**
     * Get widget name
     */
    public function get_name() {
        return 'voxel-profile-progress';
    }
    
    /**
     * Get widget title
     */
    public function get_title() {
        return __('Profile Progress (VT)', 'voxel-toolkit');
    }
    
    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-progress-tracker';
    }
    
    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['voxel-toolkit', 'general'];
    }
    
    /**
     * Get help URL
     */
    public function get_custom_help_url() {
        return 'https://codewattz.com/doc';
    }
    
    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return ['profile', 'progress', 'completion', 'fields', 'voxel'];
    }
    
    /**
     * Whether the reload preview is required
     */
    public function is_reload_preview_required() {
        return false;
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
        
        // Progress Display Type
        $this->add_control(
            'progress_type',
            [
                'label' => __('Progress Type', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'horizontal',
                'options' => [
                    'horizontal' => __('Horizontal Bar', 'voxel-toolkit'),
                    'circular' => __('Circular Progress', 'voxel-toolkit'),
                ],
            ]
        );
        
        // Profile Fields Repeater
        $repeater = new \Elementor\Repeater();

        // Get available profile fields
        $profile_fields = Voxel_Toolkit_Profile_Progress_Widget::get_available_profile_fields();

        $repeater->add_control(
            'field_key',
            [
                'label' => __('Profile Field', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $profile_fields,
                'default' => !empty($profile_fields) ? array_key_first($profile_fields) : '',
                'label_block' => true,
                'render_type' => 'none',
            ]
        );
        
        $repeater->add_control(
            'field_label',
            [
                'label' => __('Field Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('Enter field display name', 'voxel-toolkit'),
                'label_block' => true,
                'render_type' => 'none',
            ]
        );
        
        $repeater->add_control(
            'field_icon',
            [
                'label' => __('Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-star',
                    'library' => 'fa-solid',
                ],
            ]
        );
        
        // Build default fields
        $default_fields = [];
        if (!empty($profile_fields) && is_array($profile_fields)) {
            $field_keys = array_keys($profile_fields);
            $count = 0;
            foreach ($field_keys as $key) {
                if ($count >= 2) break; // Only add first 2 fields as defaults
                if (!empty($key)) {
                    $default_fields[] = [
                        'field_key' => $key,
                        'field_label' => $profile_fields[$key],
                    ];
                    $count++;
                }
            }
        }

        // Fallback if no profile fields found
        if (empty($default_fields)) {
            $default_fields = [
                [
                    'field_key' => '',
                    'field_label' => __('No fields available', 'voxel-toolkit'),
                ],
            ];
        }

        $this->add_control(
            'profile_fields',
            [
                'label' => __('Profile Fields', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => $default_fields,
                'title_field' => '{{{ field_label }}} ({{{ field_key }}})',
            ]
        );
        
        $this->add_control(
            'show_percentage',
            [
                'label' => __('Show Percentage', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'voxel-toolkit'),
                'label_off' => __('Hide', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'percentage_before_text',
            [
                'label' => __('Before Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('Enter text before percentage', 'voxel-toolkit'),
                'condition' => [
                    'show_percentage' => 'yes',
                ],
                'render_type' => 'template',
                'prefix_class' => '',
                'selectors' => [],
            ]
        );
        
        $this->add_control(
            'percentage_after_text',
            [
                'label' => __('After Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('Enter text after percentage', 'voxel-toolkit'),
                'condition' => [
                    'show_percentage' => 'yes',
                ],
                'render_type' => 'template',
                'prefix_class' => '',
                'selectors' => [],
            ]
        );
        
        $this->add_control(
            'show_field_list',
            [
                'label' => __('Show Field List', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'voxel-toolkit'),
                'label_off' => __('Hide', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => __('Display list of completed/incomplete fields below progress', 'voxel-toolkit'),
            ]
        );
        
        $this->add_responsive_control(
            'progress_align',
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
                'default' => 'center',
                'selectors' => [
                    '{{WRAPPER}} .voxel-profile-progress' => 'text-align: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'show_edit_profile_link',
            [
                'label' => __('Show Edit Profile Link', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'voxel-toolkit'),
                'label_off' => __('Hide', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'no',
                'separator' => 'before',
            ]
        );
        
        $this->add_control(
            'edit_profile_button_text',
            [
                'label' => __('Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Edit Profile', 'voxel-toolkit'),
                'placeholder' => __('Enter button text', 'voxel-toolkit'),
                'condition' => [
                    'show_edit_profile_link' => 'yes',
                ],
                'render_type' => 'none',
            ]
        );
        
        $this->add_control(
            'edit_profile_button_url',
            [
                'label' => __('Button URL', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::URL,
                'placeholder' => __('https://your-link.com', 'voxel-toolkit'),
                'condition' => [
                    'show_edit_profile_link' => 'yes',
                ],
                'render_type' => 'none',
            ]
        );
        
        $this->end_controls_section();
        
        // Progress Bar Style
        $this->start_controls_section(
            'progress_bar_style',
            [
                'label' => __('Progress Bar', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'progress_type' => 'horizontal',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'progress_bar_height',
            [
                'label' => __('Height', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 5,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-progress-bar' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'progress_bar_bg_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e0e0e0',
                'selectors' => [
                    '{{WRAPPER}} .voxel-progress-bar' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'progress_bar_fill_color',
            [
                'label' => __('Fill Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#4CAF50',
                'selectors' => [
                    '{{WRAPPER}} .voxel-progress-fill' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'progress_bar_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .voxel-progress-bar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .voxel-progress-fill' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Circular Progress Style
        $this->start_controls_section(
            'circular_progress_style',
            [
                'label' => __('Circular Progress', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'progress_type' => 'circular',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'circle_size',
            [
                'label' => __('Circle Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 50,
                        'max' => 300,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 120,
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-circular-progress' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'circle_stroke_width',
            [
                'label' => __('Stroke Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 2,
                        'max' => 20,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-circular-progress circle' => 'stroke-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'circle_bg_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e0e0e0',
                'selectors' => [
                    '{{WRAPPER}} .voxel-circular-progress .circle-bg' => 'stroke: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'circle_fill_color',
            [
                'label' => __('Fill Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#4CAF50',
                'selectors' => [
                    '{{WRAPPER}} .voxel-circular-progress .circle-progress' => 'stroke: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Percentage Text Style
        $this->start_controls_section(
            'percentage_style',
            [
                'label' => __('Percentage Text', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_percentage' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'percentage_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .voxel-progress-percentage' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'percentage_value_color',
            [
                'label' => __('Number Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .voxel-percentage-value' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'percentage_typography',
                'selector' => '{{WRAPPER}} .voxel-progress-percentage',
            ]
        );
        
        $this->add_responsive_control(
            'percentage_spacing',
            [
                'label' => __('Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-progress-percentage' => 'margin-top: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Field List Layout
        $this->start_controls_section(
            'field_list_layout',
            [
                'label' => __('Field List Layout', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_field_list' => 'yes',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'field_list_columns',
            [
                'label' => __('Columns', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 6,
                'default' => 1,
                'tablet_default' => 1,
                'mobile_default' => 1,
                'selectors' => [
                    '{{WRAPPER}} .voxel-field-list' => 'columns: {{VALUE}}; column-gap: 15px;',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'field_list_column_gap',
            [
                'label' => __('Column Gap', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 15,
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-field-list' => 'column-gap: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'field_list_columns!' => '1',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'field_list_row_gap',
            [
                'label' => __('Row Gap', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-field-item' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'field_list_typography',
                'selector' => '{{WRAPPER}} .voxel-field-item',
            ]
        );
        
        $this->end_controls_section();
        
        // Completed Fields Style
        $this->start_controls_section(
            'completed_fields_style',
            [
                'label' => __('Completed Fields', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_field_list' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'field_completed_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#4CAF50',
                'selectors' => [
                    '{{WRAPPER}} .voxel-field-item.completed' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'field_completed_bg_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => 'rgba(76, 175, 80, 0.1)',
                'selectors' => [
                    '{{WRAPPER}} .voxel-field-item.completed' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'field_completed_icon_color',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#4CAF50',
                'selectors' => [
                    '{{WRAPPER}} .voxel-field-item.completed .field-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .voxel-field-item.completed .field-icon svg' => 'fill: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Incomplete Fields Style
        $this->start_controls_section(
            'incomplete_fields_style',
            [
                'label' => __('Incomplete Fields', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_field_list' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'field_incomplete_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f44336',
                'selectors' => [
                    '{{WRAPPER}} .voxel-field-item.incomplete' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'field_incomplete_bg_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => 'rgba(244, 67, 54, 0.1)',
                'selectors' => [
                    '{{WRAPPER}} .voxel-field-item.incomplete' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'field_incomplete_icon_color',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f44336',
                'selectors' => [
                    '{{WRAPPER}} .voxel-field-item.incomplete .field-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .voxel-field-item.incomplete .field-icon svg' => 'fill: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Edit Profile Button Style
        $this->start_controls_section(
            'edit_profile_button_style',
            [
                'label' => __('Edit Profile Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_edit_profile_link' => 'yes',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .voxel-edit-profile-button',
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
                    '{{WRAPPER}} .voxel-edit-profile-button' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_background_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#4CAF50',
                'selectors' => [
                    '{{WRAPPER}} .voxel-edit-profile-button' => 'background-color: {{VALUE}};',
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
                'selectors' => [
                    '{{WRAPPER}} .voxel-edit-profile-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_hover_background_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .voxel-edit-profile-button:hover' => 'background-color: {{VALUE}};',
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
                'default' => [
                    'top' => 12,
                    'right' => 24,
                    'bottom' => 12,
                    'left' => 24,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-edit-profile-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'separator' => 'before',
            ]
        );
        
        $this->add_responsive_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 5,
                    'right' => 5,
                    'bottom' => 5,
                    'left' => 5,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-edit-profile-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'button_spacing',
            [
                'label' => __('Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 15,
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-edit-profile-button' => 'margin-top: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Get user profile field data
     */
    private function get_user_profile_fields($user_id, $field_keys) {
        global $wpdb;
        
        if (!$user_id || empty($field_keys)) {
            return [];
        }
        
        // Step 1: Get user's profile ID from wp_usermeta
        $profile_id = get_user_meta($user_id, 'voxel:profile_id', true);
        
        if (!$profile_id) {
            return [];
        }
        
        $field_data = [];
        
        foreach ($field_keys as $field_key) {
            // Special case: voxel:avatar is stored in wp_usermeta, not wp_postmeta
            if ($field_key === 'voxel:avatar') {
                $meta_value = get_user_meta($user_id, 'voxel:avatar', true);
            }
            // Special case: description is stored in wp_posts.post_content
            elseif ($field_key === 'description') {
                $post = get_post($profile_id);
                $meta_value = $post ? $post->post_content : '';
            }
            else {
                // Step 2: Check if field exists in wp_postmeta using the profile_id as post_id
                $meta_value = get_post_meta($profile_id, $field_key, true);
            }
            
            // More comprehensive check for field completion
            $is_completed = false;
            
            if ($meta_value !== '' && $meta_value !== false && $meta_value !== null) {
                // For arrays/objects, check if they have meaningful content
                if (is_array($meta_value) || is_object($meta_value)) {
                    $is_completed = !empty($meta_value);
                } 
                // For strings, check if not just whitespace
                elseif (is_string($meta_value)) {
                    $is_completed = trim($meta_value) !== '';
                }
                // For numbers, including 0
                elseif (is_numeric($meta_value)) {
                    $is_completed = true;
                }
                // For other types
                else {
                    $is_completed = !empty($meta_value);
                }
            }
            
            $field_data[$field_key] = [
                'exists' => $is_completed,
                'value' => $meta_value
            ];
        }
        
        return $field_data;
    }
    
    /**
     * Calculate progress percentage
     */
    private function calculate_progress($field_data) {
        if (empty($field_data)) {
            return 0;
        }
        
        $total_fields = count($field_data);
        $completed_fields = 0;
        
        foreach ($field_data as $field) {
            if ($field['exists']) {
                $completed_fields++;
            }
        }
        
        return round(($completed_fields / $total_fields) * 100);
    }
    
    /**
     * Render widget output on the frontend
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Get current user ID
        $user_id = get_current_user_id();
        
        // Extract field keys from repeater
        $field_keys = [];
        if (!empty($settings['profile_fields'])) {
            foreach ($settings['profile_fields'] as $field) {
                if (!empty($field['field_key'])) {
                    $field_keys[] = $field['field_key'];
                }
            }
        }
        
        // Get field data or use sample for editor
        if (!$user_id && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            // Sample data for editor
            $progress_percentage = 65;
            $field_data = [
                'first_name' => ['exists' => true, 'value' => 'John'],
                'last_name' => ['exists' => true, 'value' => 'Doe'],
                'bio' => ['exists' => false, 'value' => ''],
            ];
        } elseif ($user_id && !empty($field_keys)) {
            $field_data = $this->get_user_profile_fields($user_id, $field_keys);
            $progress_percentage = $this->calculate_progress($field_data);
        } else {
            return; // No user ID and not in editor
        }
        
        // Start output
        $this->add_render_attribute('wrapper', 'class', 'voxel-profile-progress');
        
        ?>
        <div <?php echo $this->get_render_attribute_string('wrapper'); ?>>
            <?php if ($settings['progress_type'] === 'horizontal') : ?>
                <div class="voxel-progress-bar">
                    <div class="voxel-progress-fill" style="width: <?php echo esc_attr($progress_percentage) . "%"; ?>;"></div>
                </div>
                <?php if ($settings['show_percentage'] === 'yes') : ?>
                    <div class="voxel-progress-percentage">
                        <?php if (!empty($settings['percentage_before_text'])) : ?>
                            <span class="voxel-percentage-before"><?php echo esc_html($settings['percentage_before_text']); ?></span>
                        <?php endif; ?>
                        <span class="voxel-percentage-value"><?php echo esc_html($progress_percentage) . '%'; ?></span>
                        <?php if (!empty($settings['percentage_after_text'])) : ?>
                            <span class="voxel-percentage-after"><?php echo esc_html($settings['percentage_after_text']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="voxel-circular-progress-container">
                    <div class="voxel-circular-progress">
                        <svg viewBox="0 0 120 120">
                            <circle class="circle-bg" cx="60" cy="60" r="54" fill="none" />
                            <circle class="circle-progress" cx="60" cy="60" r="54" fill="none" 
                                    stroke-dasharray="<?php echo esc_attr(2 * pi() * 54); ?>" 
                                    stroke-dashoffset="<?php echo esc_attr(2 * pi() * 54 * (1 - $progress_percentage / 100)); ?>" />
                        </svg>
                        <?php if ($settings['show_percentage'] === 'yes') : ?>
                            <div class="voxel-progress-percentage">
                                <?php if (!empty($settings['percentage_before_text'])) : ?>
                                    <span class="voxel-percentage-before"><?php echo esc_html($settings['percentage_before_text']); ?></span>
                                <?php endif; ?>
                                <span class="voxel-percentage-value"><?php echo esc_html($progress_percentage) . '%'; ?></span>
                                <?php if (!empty($settings['percentage_after_text'])) : ?>
                                    <span class="voxel-percentage-after"><?php echo esc_html($settings['percentage_after_text']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($settings['show_field_list'] === 'yes' && !empty($settings['profile_fields'])) : ?>
                <div class="voxel-field-list">
                    <?php foreach ($settings['profile_fields'] as $field) : 
                        $field_key = $field['field_key'];
                        $field_label = $field['field_label'] ?: $field_key;
                        $is_completed = isset($field_data[$field_key]) && $field_data[$field_key]['exists'];
                        $status_class = $is_completed ? 'completed' : 'incomplete';
                        $status_icon = $is_completed ? '✓' : '✗';
                    ?>
                        <div class="voxel-field-item <?php echo esc_attr($status_class); ?>">
                            <?php if (!empty($field['field_icon']['value'])) : ?>
                                <span class="field-icon">
                                    <?php \Elementor\Icons_Manager::render_icon($field['field_icon'], ['aria-hidden' => 'true']); ?>
                                </span>
                            <?php else : ?>
                                <span class="field-icon"><?php echo esc_html($status_icon); ?></span>
                            <?php endif; ?>
                            <span class="field-label"><?php echo esc_html($field_label); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($settings['show_edit_profile_link'] === 'yes' && !empty($settings['edit_profile_button_text'])) : 
                $button_url = '';
                if (!empty($settings['edit_profile_button_url']['url'])) {
                    $button_url = $settings['edit_profile_button_url']['url'];
                    $target = $settings['edit_profile_button_url']['is_external'] ? ' target="_blank"' : '';
                    $nofollow = $settings['edit_profile_button_url']['nofollow'] ? ' rel="nofollow"' : '';
                } else {
                    $target = '';
                    $nofollow = '';
                }
            ?>
                <div class="voxel-edit-profile-link">
                    <?php if (!empty($button_url)) : ?>
                        <a href="<?php echo esc_url($button_url); ?>" class="voxel-edit-profile-button"<?php echo $target . $nofollow; ?>>
                            <?php echo esc_html($settings['edit_profile_button_text']); ?>
                        </a>
                    <?php else : ?>
                        <span class="voxel-edit-profile-button">
                            <?php echo esc_html($settings['edit_profile_button_text']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render widget output in the editor
     */
    protected function content_template() {
        ?>
        <#
        // Static sample data for consistent preview
        var sampleProgress = 73; // Fixed percentage for consistent styling preview
        #>
        <div class="voxel-profile-progress">
            <# if (settings.progress_type === 'horizontal') { #>
                <div class="voxel-progress-bar">
                    <div class="voxel-progress-fill" style="width: {{ sampleProgress }}%;"></div>
                </div>
                <# if (settings.show_percentage === 'yes') { #>
                    <div class="voxel-progress-percentage">
                        <# if (settings.percentage_before_text) { #>
                            <span class="voxel-percentage-before">{{{ settings.percentage_before_text }}}</span>
                        <# } #>
                        <span class="voxel-percentage-value">{{ sampleProgress }}%</span>
                        <# if (settings.percentage_after_text) { #>
                            <span class="voxel-percentage-after">{{{ settings.percentage_after_text }}}</span>
                        <# } #>
                    </div>
                <# } #>
            <# } else { #>
                <div class="voxel-circular-progress-container">
                    <div class="voxel-circular-progress">
                        <svg viewBox="0 0 120 120">
                            <circle class="circle-bg" cx="60" cy="60" r="54" fill="none" />
                            <circle class="circle-progress" cx="60" cy="60" r="54" fill="none" 
                                    stroke-dasharray="339.292" 
                                    stroke-dashoffset="{{ 339.292 * (1 - sampleProgress / 100) }}" />
                        </svg>
                        <# if (settings.show_percentage === 'yes') { #>
                            <div class="voxel-progress-percentage">
                                <# if (settings.percentage_before_text) { #>
                                    <span class="voxel-percentage-before">{{{ settings.percentage_before_text }}}</span>
                                <# } #>
                                <span class="voxel-percentage-value">{{ sampleProgress }}%</span>
                                <# if (settings.percentage_after_text) { #>
                                    <span class="voxel-percentage-after">{{{ settings.percentage_after_text }}}</span>
                                <# } #>
                            </div>
                        <# } #>
                    </div>
                </div>
            <# } #>
            
            <# if (settings.show_field_list === 'yes' && settings.profile_fields && settings.profile_fields.length) { #>
                <div class="voxel-field-list">
                    <# _.each(settings.profile_fields, function(field, index) { 
                        // Predictable completion pattern for consistent preview
                        var completed = (index % 3 !== 2); // 2 out of every 3 fields are completed
                        var statusClass = completed ? 'completed' : 'incomplete';
                        var statusIcon = completed ? '✓' : '✗';
                        var fieldLabel = field.field_label || field.field_key || 'Field ' + (index + 1);
                    #>
                        <div class="voxel-field-item {{ statusClass }}">
                            <# if (field.field_icon && field.field_icon.value) { #>
                                <span class="field-icon">
                                    <# if (field.field_icon.library === 'svg') { #>
                                        <img src="{{ field.field_icon.value.url }}" alt="">
                                    <# } else { #>
                                        <i class="{{ field.field_icon.value }}" aria-hidden="true"></i>
                                    <# } #>
                                </span>
                            <# } else { #>
                                <span class="field-icon">{{ statusIcon }}</span>
                            <# } #>
                            <span class="field-label">{{ fieldLabel }}</span>
                        </div>
                    <# }); #>
                </div>
            <# } else if (settings.show_field_list === 'yes') { #>
                <div class="voxel-field-list">
                    <div class="elementor-alert elementor-alert-info">
                        <span class="elementor-alert-title">Add Profile Fields</span>
                        <span class="elementor-alert-description">Add profile fields above to see the preview.</span>
                    </div>
                </div>
            <# } #>
            
            <# if (settings.show_edit_profile_link === 'yes' && settings.edit_profile_button_text) { #>
                <div class="voxel-edit-profile-link">
                    <# if (settings.edit_profile_button_url && settings.edit_profile_button_url.url) { #>
                        <a href="{{ settings.edit_profile_button_url.url }}" class="voxel-edit-profile-button"
                           <# if (settings.edit_profile_button_url.is_external) { #> target="_blank"<# } #>
                           <# if (settings.edit_profile_button_url.nofollow) { #> rel="nofollow"<# } #>>
                            {{{ settings.edit_profile_button_text }}}
                        </a>
                    <# } else { #>
                        <span class="voxel-edit-profile-button">
                            {{{ settings.edit_profile_button_text }}}
                        </span>
                    <# } #>
                </div>
            <# } #>
        </div>
        <?php
    }
}