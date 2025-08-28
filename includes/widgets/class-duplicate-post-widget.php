<?php
/**
 * Duplicate Post Elementor Widget
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Duplicate_Post_Widget extends \Elementor\Widget_Base {
    
    /**
     * Get widget name
     */
    public function get_name() {
        return 'voxel-toolkit-duplicate-post';
    }
    
    /**
     * Get widget title
     */
    public function get_title() {
        return __('Duplicate Post', 'voxel-toolkit');
    }
    
    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-copy';
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
                'default' => __('Duplicate Post', 'voxel-toolkit'),
                'placeholder' => __('Enter button text', 'voxel-toolkit'),
            ]
        );
        
        $this->add_control(
            'redirect_type',
            [
                'label' => __('Redirect After Duplicate', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'create_page',
                'options' => [
                    'create_page' => __('Create/Edit Page', 'voxel-toolkit'),
                    'current_page' => __('Current Page', 'voxel-toolkit'),
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Button Style Section
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Button Style', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .voxel-toolkit-duplicate-btn',
            ]
        );
        
        $this->start_controls_tabs('button_style_tabs');
        
        // Normal State
        $this->start_controls_tab(
            'button_normal',
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
                    '{{WRAPPER}} .voxel-toolkit-duplicate-btn' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'button_background',
                'label' => __('Background', 'voxel-toolkit'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .voxel-toolkit-duplicate-btn',
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .voxel-toolkit-duplicate-btn',
            ]
        );
        
        $this->add_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .voxel-toolkit-duplicate-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_box_shadow',
                'selector' => '{{WRAPPER}} .voxel-toolkit-duplicate-btn',
            ]
        );
        
        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .voxel-toolkit-duplicate-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_tab();
        
        // Hover State
        $this->start_controls_tab(
            'button_hover',
            [
                'label' => __('Hover', 'voxel-toolkit'),
            ]
        );
        
        $this->add_control(
            'button_text_color_hover',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .voxel-toolkit-duplicate-btn:hover' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'button_background_hover',
                'label' => __('Background', 'voxel-toolkit'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .voxel-toolkit-duplicate-btn:hover',
            ]
        );
        
        $this->add_control(
            'button_border_color_hover',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .voxel-toolkit-duplicate-btn:hover' => 'border-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_box_shadow_hover',
                'selector' => '{{WRAPPER}} .voxel-toolkit-duplicate-btn:hover',
            ]
        );
        
        $this->add_control(
            'button_hover_animation',
            [
                'label' => __('Hover Animation', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HOVER_ANIMATION,
            ]
        );
        
        $this->end_controls_tab();
        
        $this->end_controls_tabs();
        
        $this->add_responsive_control(
            'button_align',
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
                'default' => 'left',
                'selectors' => [
                    '{{WRAPPER}} .voxel-toolkit-duplicate-wrapper' => 'text-align: {{VALUE}};',
                ],
                'separator' => 'before',
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Render the widget
     */
    protected function render() {
        global $post;
        
        if (!$post) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="voxel-toolkit-duplicate-wrapper">';
                echo '<button class="voxel-toolkit-duplicate-btn elementor-button">Preview Mode: Duplicate Button</button>';
                echo '</div>';
            }
            return;
        }
        
        $settings = $this->get_settings_for_display();
        $button_text = !empty($settings['button_text']) ? $settings['button_text'] : __('Duplicate Post', 'voxel-toolkit');
        $redirect_type = $settings['redirect_type'];
        
        // Check if user can edit posts
        if (!current_user_can('edit_posts')) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="voxel-toolkit-duplicate-wrapper">';
                echo '<button class="voxel-toolkit-duplicate-btn elementor-button" disabled>Login Required</button>';
                echo '</div>';
            }
            return;
        }
        
        $hover_class = '';
        if (!empty($settings['button_hover_animation'])) {
            $hover_class = 'elementor-animation-' . $settings['button_hover_animation'];
        }
        
        ?>
        <div class="voxel-toolkit-duplicate-wrapper">
            <button class="voxel-toolkit-duplicate-btn elementor-button <?php echo esc_attr($hover_class); ?>" 
                    data-post-id="<?php echo esc_attr($post->ID); ?>"
                    data-redirect="<?php echo esc_attr($redirect_type); ?>">
                <?php echo esc_html($button_text); ?>
            </button>
        </div>
        <?php
    }
}