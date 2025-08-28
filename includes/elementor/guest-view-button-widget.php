<?php
/**
 * Elementor Guest View Button Widget
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Guest_View_Button_Widget extends \Elementor\Widget_Base {
    
    /**
     * Get widget name
     */
    public function get_name() {
        return 'voxel_toolkit_guest_view_button';
    }
    
    /**
     * Get widget title
     */
    public function get_title() {
        return __('Guest View Button', 'voxel-toolkit');
    }
    
    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-eye';
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
    protected function _register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'button_text',
            [
                'label' => __('Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('View as Guest', 'voxel-toolkit'),
                'placeholder' => __('Enter button text', 'voxel-toolkit'),
            ]
        );
        
        $this->add_control(
            'button_icon',
            [
                'label' => __('Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-eye',
                    'library' => 'solid',
                ],
            ]
        );
        
        $this->add_control(
            'icon_position',
            [
                'label' => __('Icon Position', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'left',
                'options' => [
                    'left' => __('Before', 'voxel-toolkit'),
                    'right' => __('After', 'voxel-toolkit'),
                ],
            ]
        );
        
        $this->add_responsive_control(
            'align',
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
                    'justify' => [
                        'title' => __('Justified', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-justify',
                    ],
                ],
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}}' => 'text-align: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'hide_for_guests',
            [
                'label' => __('Hide for Guests', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Hide this button for users who are not logged in', 'voxel-toolkit'),
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Button
        $this->start_controls_section(
            'style_button',
            [
                'label' => __('Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'typography',
                'selector' => '{{WRAPPER}} .voxel-toolkit-guest-view-btn',
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Text_Shadow::get_type(),
            [
                'name' => 'text_shadow',
                'selector' => '{{WRAPPER}} .voxel-toolkit-guest-view-btn',
            ]
        );
        
        $this->start_controls_tabs('tabs_button_style');
        
        $this->start_controls_tab(
            'tab_button_normal',
            [
                'label' => __('Normal', 'voxel-toolkit'),
            ]
        );
        
        $this->add_control(
            'button_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .voxel-toolkit-guest-view-btn' => 'fill: {{VALUE}}; color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'background',
                'label' => __('Background', 'voxel-toolkit'),
                'types' => ['classic', 'gradient'],
                'exclude' => ['image'],
                'selector' => '{{WRAPPER}} .voxel-toolkit-guest-view-btn',
            ]
        );
        
        $this->end_controls_tab();
        
        $this->start_controls_tab(
            'tab_button_hover',
            [
                'label' => __('Hover', 'voxel-toolkit'),
            ]
        );
        
        $this->add_control(
            'hover_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .voxel-toolkit-guest-view-btn:hover, {{WRAPPER}} .voxel-toolkit-guest-view-btn:focus' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .voxel-toolkit-guest-view-btn:hover svg, {{WRAPPER}} .voxel-toolkit-guest-view-btn:focus svg' => 'fill: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'button_background_hover',
                'label' => __('Background', 'voxel-toolkit'),
                'types' => ['classic', 'gradient'],
                'exclude' => ['image'],
                'selector' => '{{WRAPPER}} .voxel-toolkit-guest-view-btn:hover, {{WRAPPER}} .voxel-toolkit-guest-view-btn:focus',
            ]
        );
        
        $this->add_control(
            'button_hover_border_color',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'border_border!' => '',
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-toolkit-guest-view-btn:hover, {{WRAPPER}} .voxel-toolkit-guest-view-btn:focus' => 'border-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'hover_animation',
            [
                'label' => __('Hover Animation', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HOVER_ANIMATION,
            ]
        );
        
        $this->end_controls_tab();
        
        $this->end_controls_tabs();
        
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'border',
                'selector' => '{{WRAPPER}} .voxel-toolkit-guest-view-btn',
                'separator' => 'before',
            ]
        );
        
        $this->add_responsive_control(
            'border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .voxel-toolkit-guest-view-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_box_shadow',
                'selector' => '{{WRAPPER}} .voxel-toolkit-guest-view-btn',
            ]
        );
        
        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .voxel-toolkit-guest-view-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'separator' => 'before',
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Icon
        $this->start_controls_section(
            'style_icon',
            [
                'label' => __('Icon', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'button_icon[value]!' => '',
                ],
            ]
        );
        
        $this->add_control(
            'icon_color',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .voxel-toolkit-guest-view-btn i' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .voxel-toolkit-guest-view-btn svg' => 'fill: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'icon_color_hover',
            [
                'label' => __('Icon Color Hover', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .voxel-toolkit-guest-view-btn:hover i' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .voxel-toolkit-guest-view-btn:hover svg' => 'fill: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'icon_size',
            [
                'label' => __('Icon Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 6,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-toolkit-guest-view-btn i' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .voxel-toolkit-guest-view-btn svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'icon_spacing',
            [
                'label' => __('Icon Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'default' => [
                    'size' => 8,
                ],
                'range' => [
                    'px' => [
                        'max' => 50,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-toolkit-guest-view-btn.icon-left i, {{WRAPPER}} .voxel-toolkit-guest-view-btn.icon-left svg' => 'margin-right: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .voxel-toolkit-guest-view-btn.icon-right i, {{WRAPPER}} .voxel-toolkit-guest-view-btn.icon-right svg' => 'margin-left: {{SIZE}}{{UNIT}};',
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
        
        // Check if we should hide for guests
        if ($settings['hide_for_guests'] === 'yes' && !is_user_logged_in()) {
            return;
        }
        
        // Check if already in guest view (simplified cookie check)
        $in_guest_view = isset($_COOKIE['voxel_toolkit_guest_view']) && $_COOKIE['voxel_toolkit_guest_view'] === 'active';
        
        $this->add_render_attribute('button', [
            'class' => [
                'voxel-toolkit-guest-view-btn',
                'elementor-button',
                'elementor-size-sm',
                'icon-' . $settings['icon_position'],
            ],
            'role' => 'button',
        ]);
        
        if (!empty($settings['hover_animation'])) {
            $this->add_render_attribute('button', 'class', 'elementor-animation-' . $settings['hover_animation']);
        }
        
        if ($in_guest_view) {
            $this->add_render_attribute('button', 'class', 'guest-view-active');
        }
        ?>
        <div class="voxel-toolkit-guest-view-widget">
            <a <?php echo $this->get_render_attribute_string('button'); ?> href="#" data-action="toggle-guest-view">
                <?php if (!empty($settings['button_icon']['value']) && $settings['icon_position'] === 'left') : ?>
                    <?php \Elementor\Icons_Manager::render_icon($settings['button_icon'], ['aria-hidden' => 'true']); ?>
                <?php endif; ?>
                
                <span class="button-text"><?php echo esc_html($settings['button_text']); ?></span>
                
                <?php if (!empty($settings['button_icon']['value']) && $settings['icon_position'] === 'right') : ?>
                    <?php \Elementor\Icons_Manager::render_icon($settings['button_icon'], ['aria-hidden' => 'true']); ?>
                <?php endif; ?>
            </a>
        </div>
        <style>
            .voxel-toolkit-guest-view-btn {
                display: inline-flex;
                align-items: center;
                text-decoration: none;
                transition: all 0.3s ease;
            }
            
            .voxel-toolkit-guest-view-btn.guest-view-active {
                opacity: 0.5;
                pointer-events: none;
            }
            
            .voxel-toolkit-guest-view-btn i,
            .voxel-toolkit-guest-view-btn svg {
                flex-shrink: 0;
            }
        </style>
        <?php
    }
}