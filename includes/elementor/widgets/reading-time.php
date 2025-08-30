<?php
/**
 * Elementor Reading Time Widget
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Elementor_Reading_Time extends \Elementor\Widget_Base {
    
    /**
     * Class constructor
     */
    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);
        
        wp_register_style(
            'voxel-reading-time', 
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/reading-time.css',
            [],
            VOXEL_TOOLKIT_VERSION
        );
        
    }
    
    /**
     * Get style dependencies
     */
    public function get_style_depends() {
        return ['voxel-reading-time'];
    }
    
    /**
     * Get widget name
     */
    public function get_name() {
        return 'voxel-reading-time';
    }
    
    /**
     * Get widget title
     */
    public function get_title() {
        return __('Reading Time', 'voxel-toolkit');
    }
    
    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-clock-o';
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
        return 'https://codewattz.com/voxel-toolkit-plugin/';
    }
    
    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return ['reading', 'time', 'estimate', 'duration', 'voxel'];
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
        
        $this->add_control(
            'prefix_text',
            [
                'label' => __('Prefix Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Reading time: ',
                'placeholder' => __('Enter prefix text', 'voxel-toolkit'),
            ]
        );
        
        $this->add_control(
            'postfix_text',
            [
                'label' => __('Postfix Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => ' min',
                'placeholder' => __('Enter postfix text', 'voxel-toolkit'),
            ]
        );
        
        $this->add_control(
            'words_per_minute',
            [
                'label' => __('Words Per Minute', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 100,
                'max' => 500,
                'step' => 10,
                'default' => 300,
                'description' => __('Average reading speed in words per minute', 'voxel-toolkit'),
            ]
        );
        
        $this->add_responsive_control(
            'text_align',
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
                    '{{WRAPPER}} .voxel-reading-time' => 'justify-content: {{VALUE}};',
                ],
                'selectors_dictionary' => [
                    'left' => 'flex-start',
                    'center' => 'center',
                    'right' => 'flex-end',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Prefix
        $this->start_controls_section(
            'prefix_style_section',
            [
                'label' => __('Prefix Style', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'prefix_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .voxel-reading-time-prefix' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'prefix_typography',
                'selector' => '{{WRAPPER}} .voxel-reading-time-prefix',
            ]
        );
        
        $this->add_responsive_control(
            'prefix_spacing',
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
                    'size' => 5,
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-reading-time-prefix' => 'margin-right: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Time
        $this->start_controls_section(
            'time_style_section',
            [
                'label' => __('Time Style', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'time_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .voxel-reading-time-value' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'time_typography',
                'selector' => '{{WRAPPER}} .voxel-reading-time-value',
            ]
        );
        
        $this->add_responsive_control(
            'time_spacing',
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
                    'size' => 5,
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-reading-time-value' => 'margin-right: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Postfix
        $this->start_controls_section(
            'postfix_style_section',
            [
                'label' => __('Postfix Style', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'postfix_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .voxel-reading-time-postfix' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'postfix_typography',
                'selector' => '{{WRAPPER}} .voxel-reading-time-postfix',
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Container
        $this->start_controls_section(
            'container_style_section',
            [
                'label' => __('Container', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'container_background',
                'label' => __('Background', 'voxel-toolkit'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .voxel-reading-time',
            ]
        );
        
        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .voxel-reading-time' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'container_margin',
            [
                'label' => __('Margin', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .voxel-reading-time' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'label' => __('Border', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .voxel-reading-time',
            ]
        );
        
        $this->add_responsive_control(
            'container_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .voxel-reading-time' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'container_box_shadow',
                'label' => __('Box Shadow', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .voxel-reading-time',
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Render widget output on the frontend
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Get current post ID
        $post_id = get_the_ID();
        
        // Calculate reading time or use sample for editor
        if (!$post_id && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            $reading_time = 5; // Sample time for editor
        } elseif ($post_id) {
            $reading_time = Voxel_Toolkit_Reading_Time_Widget::calculate_reading_time($post_id, $settings['words_per_minute']);
        } else {
            return; // No post ID and not in editor
        }
        
        // Start output
        $this->add_render_attribute('wrapper', 'class', 'voxel-reading-time');
        
        // Add prefix render attributes
        $this->add_render_attribute('prefix', 'class', 'voxel-reading-time-prefix');
        $this->add_inline_editing_attributes('prefix_text', 'basic');
        
        // Add time render attributes
        $this->add_render_attribute('time', 'class', 'voxel-reading-time-value');
        
        // Add postfix render attributes
        $this->add_render_attribute('postfix', 'class', 'voxel-reading-time-postfix');
        $this->add_inline_editing_attributes('postfix_text', 'basic');
        
        ?>
        <div <?php echo $this->get_render_attribute_string('wrapper'); ?>>
            <?php if (!empty($settings['prefix_text'])) : ?>
                <span <?php echo $this->get_render_attribute_string('prefix'); ?>>
                    <?php echo esc_html($settings['prefix_text']); ?>
                </span>
            <?php endif; ?>
            
            <span <?php echo $this->get_render_attribute_string('time'); ?>>
                <?php echo esc_html($reading_time); ?>
            </span>
            
            <?php if (!empty($settings['postfix_text'])) : ?>
                <span <?php echo $this->get_render_attribute_string('postfix'); ?>>
                    <?php echo esc_html($settings['postfix_text']); ?>
                </span>
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
        var sampleTime = 5;
        #>
        <div class="voxel-reading-time">
            <# if (settings.prefix_text) { #>
                <span class="voxel-reading-time-prefix">
                    {{{ settings.prefix_text }}}
                </span>
            <# } #>
            
            <span class="voxel-reading-time-value">
                {{ sampleTime }}
            </span>
            
            <# if (settings.postfix_text) { #>
                <span class="voxel-reading-time-postfix">
                    {{{ settings.postfix_text }}}
                </span>
            <# } #>
        </div>
        <?php
    }
}