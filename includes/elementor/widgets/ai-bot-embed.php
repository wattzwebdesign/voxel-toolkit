<?php
/**
 * Elementor AI Bot Embed Widget
 *
 * Embeds the AI chatbot directly on a page.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Elementor_AI_Bot_Embed extends \Elementor\Widget_Base {

    /**
     * Class constructor
     */
    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);

        wp_register_style(
            'voxel-ai-bot-embed',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/ai-bot-embed.css',
            [],
            VOXEL_TOOLKIT_VERSION
        );

        wp_register_script(
            'voxel-ai-bot-embed',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/ai-bot-embed.js',
            ['jquery'],
            VOXEL_TOOLKIT_VERSION,
            true
        );
    }

    /**
     * Get style dependencies
     */
    public function get_style_depends() {
        return ['voxel-ai-bot-embed'];
    }

    /**
     * Get script dependencies
     */
    public function get_script_depends() {
        return ['voxel-ai-bot-embed'];
    }

    /**
     * Get widget name
     */
    public function get_name() {
        return 'voxel-ai-bot-embed';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __('AI Bot Embed (VT)', 'voxel-toolkit');
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-comments';
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
        return ['ai', 'bot', 'chat', 'embed', 'assistant', 'voxel', 'search'];
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
            'show_header',
            [
                'label' => __('Show Header', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'header_title',
            [
                'label' => __('Header Title', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('Uses AI Bot panel title setting', 'voxel-toolkit'),
                'condition' => [
                    'show_header' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_suggested_queries',
            [
                'label' => __('Show Suggested Queries', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Uses queries from AI Bot settings', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'settings_notice',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => __('Welcome message, placeholder text, thinking text, and suggested queries are configured in Voxel Toolkit → AI Bot settings.', 'voxel-toolkit'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            ]
        );

        $this->end_controls_section();

        // Layout Section
        $this->start_controls_section(
            'layout_section',
            [
                'label' => __('Layout', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_responsive_control(
            'container_height',
            [
                'label' => __('Height', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'vh'],
                'range' => [
                    'px' => [
                        'min' => 300,
                        'max' => 1000,
                        'step' => 10,
                    ],
                    'vh' => [
                        'min' => 30,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 500,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style - Container Section
        $this->start_controls_section(
            'style_container_section',
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
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'container_border_radius',
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
                    'size' => 12,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .vt-ai-bot-embed',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'container_box_shadow',
                'selector' => '{{WRAPPER}} .vt-ai-bot-embed',
            ]
        );

        $this->end_controls_section();

        // Style - Header Section
        $this->start_controls_section(
            'style_header_section',
            [
                'label' => __('Header', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_header' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'header_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#0084ff',
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-header' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'header_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-header' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'header_typography',
                'selector' => '{{WRAPPER}} .vt-ai-bot-embed-header-title',
            ]
        );

        $this->add_responsive_control(
            'header_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'default' => [
                    'top' => 16,
                    'right' => 20,
                    'bottom' => 16,
                    'left' => 20,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style - Messages Section
        $this->start_controls_section(
            'style_messages_section',
            [
                'label' => __('Messages', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'user_bubble_heading',
            [
                'label' => __('User Messages', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'user_bubble_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#0084ff',
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-message-user .vt-ai-bot-embed-message-content' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'user_bubble_text',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-message-user .vt-ai-bot-embed-message-content' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'ai_bubble_heading',
            [
                'label' => __('AI Messages', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'ai_bubble_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f0f2f5',
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-message-ai .vt-ai-bot-embed-message-content' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'ai_bubble_text',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#050505',
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-message-ai .vt-ai-bot-embed-message-content' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'bubble_border_radius',
            [
                'label' => __('Bubble Border Radius', 'voxel-toolkit'),
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
                    '{{WRAPPER}} .vt-ai-bot-embed-message-user .vt-ai-bot-embed-message-content' => 'border-radius: {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 4px {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .vt-ai-bot-embed-message-ai .vt-ai-bot-embed-message-content' => 'border-radius: {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 4px;',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'message_typography',
                'selector' => '{{WRAPPER}} .vt-ai-bot-embed-message-content',
            ]
        );

        $this->end_controls_section();

        // Style - Input Section
        $this->start_controls_section(
            'style_input_section',
            [
                'label' => __('Input', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'input_background',
            [
                'label' => __('Input Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f0f2f5',
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-input' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_text_color',
            [
                'label' => __('Input Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#050505',
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-input' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_border_radius',
            [
                'label' => __('Input Border Radius', 'voxel-toolkit'),
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
                    'size' => 24,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-input' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'send_button_background',
            [
                'label' => __('Send Button Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#0084ff',
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-send' => 'background-color: {{VALUE}};',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'send_button_color',
            [
                'label' => __('Send Button Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-send' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style - Suggested Queries Section
        $this->start_controls_section(
            'style_suggested_section',
            [
                'label' => __('Suggested Queries', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_suggested_queries' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'suggested_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f0f2f5',
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-suggested-item' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'suggested_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#050505',
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-suggested-item' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'suggested_hover_background',
            [
                'label' => __('Hover Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e4e6e9',
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-suggested-item:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'suggested_border_radius',
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
                    'size' => 16,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-ai-bot-embed-suggested-item' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render the widget
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // Check if AI Bot is enabled
        if (!class_exists('Voxel_Toolkit_Settings')) {
            echo '<div class="vt-ai-bot-embed-error">' . esc_html__('AI Bot is not enabled.', 'voxel-toolkit') . '</div>';
            return;
        }

        $vt_settings = Voxel_Toolkit_Settings::instance();
        if (!$vt_settings->is_function_enabled('ai_bot')) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="vt-ai-bot-embed-error">' . esc_html__('Please enable AI Bot in Voxel Toolkit settings.', 'voxel-toolkit') . '</div>';
            }
            return;
        }

        // Get AI Bot settings
        $ai_settings = get_option('voxel_toolkit_options', array());
        $ai_bot_settings = isset($ai_settings['ai_bot']) ? $ai_settings['ai_bot'] : array();
        $access_control = isset($ai_bot_settings['access_control']) ? $ai_bot_settings['access_control'] : 'everyone';

        // Check access
        if ($access_control === 'logged_in' && !is_user_logged_in()) {
            echo '<div class="vt-ai-bot-embed-error">' . esc_html__('Please log in to use the AI assistant.', 'voxel-toolkit') . '</div>';
            return;
        }

        // Get content from AI Bot settings
        $welcome_message = isset($ai_bot_settings['welcome_message']) ? $ai_bot_settings['welcome_message'] : __('Hello! How can I help you today?', 'voxel-toolkit');
        $placeholder_text = isset($ai_bot_settings['placeholder_text']) ? $ai_bot_settings['placeholder_text'] : __('Type your question...', 'voxel-toolkit');
        $thinking_text = isset($ai_bot_settings['thinking_text']) ? $ai_bot_settings['thinking_text'] : __('AI is thinking', 'voxel-toolkit');
        $panel_title = isset($ai_bot_settings['panel_title']) ? $ai_bot_settings['panel_title'] : __('AI Assistant', 'voxel-toolkit');

        // Get suggested queries from AI Bot settings
        $suggested_queries = array();
        if ($settings['show_suggested_queries'] === 'yes' && isset($ai_bot_settings['suggested_queries']) && is_array($ai_bot_settings['suggested_queries'])) {
            $suggested_queries = array_filter($ai_bot_settings['suggested_queries']);
        }

        // Use widget header title if set, otherwise use AI Bot panel title
        $header_title = !empty($settings['header_title']) ? $settings['header_title'] : $panel_title;

        // Unique ID for this instance
        $widget_id = $this->get_id();

        // Localize script settings for this instance
        $instance_settings = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_ai_bot'),
            'welcomeMessage' => $welcome_message,
            'placeholderText' => $placeholder_text,
            'thinkingText' => $thinking_text,
            'suggestedQueries' => $suggested_queries,
            'isLoggedIn' => is_user_logged_in(),
            'i18n' => array(
                'error' => __('Sorry, something went wrong. Please try again.', 'voxel-toolkit'),
                'emptyMessage' => __('Please enter a message.', 'voxel-toolkit'),
                'loginRequired' => __('Please log in to use the AI assistant.', 'voxel-toolkit'),
            ),
        );

        ?>
        <div class="vt-ai-bot-embed" id="vt-ai-bot-embed-<?php echo esc_attr($widget_id); ?>" data-settings="<?php echo esc_attr(json_encode($instance_settings)); ?>">
            <?php if ($settings['show_header'] === 'yes') : ?>
            <div class="vt-ai-bot-embed-header">
                <h3 class="vt-ai-bot-embed-header-title"><?php echo esc_html($header_title); ?></h3>
            </div>
            <?php endif; ?>

            <div class="vt-ai-bot-embed-messages"></div>

            <div class="vt-ai-bot-embed-input-area">
                <form class="vt-ai-bot-embed-form">
                    <input type="text" class="vt-ai-bot-embed-input" placeholder="<?php echo esc_attr($placeholder_text); ?>" autocomplete="off">
                    <button type="submit" class="vt-ai-bot-embed-send">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Content template for editor preview
     */
    protected function content_template() {
        ?>
        <#
        var headerTitle = settings.header_title || '<?php echo esc_js(__('AI Assistant', 'voxel-toolkit')); ?>';
        #>
        <div class="vt-ai-bot-embed">
            <# if (settings.show_header === 'yes') { #>
            <div class="vt-ai-bot-embed-header">
                <h3 class="vt-ai-bot-embed-header-title">{{{ headerTitle }}}</h3>
            </div>
            <# } #>

            <div class="vt-ai-bot-embed-messages">
                <div class="vt-ai-bot-embed-message vt-ai-bot-embed-message-ai">
                    <div class="vt-ai-bot-embed-message-content"><?php echo esc_html__('Welcome message from AI Bot settings', 'voxel-toolkit'); ?></div>
                </div>

                <# if (settings.show_suggested_queries === 'yes') { #>
                <div class="vt-ai-bot-embed-suggested">
                    <button type="button" class="vt-ai-bot-embed-suggested-item"><?php echo esc_html__('Suggested query 1', 'voxel-toolkit'); ?></button>
                    <button type="button" class="vt-ai-bot-embed-suggested-item"><?php echo esc_html__('Suggested query 2', 'voxel-toolkit'); ?></button>
                </div>
                <# } #>
            </div>

            <div class="vt-ai-bot-embed-input-area">
                <form class="vt-ai-bot-embed-form">
                    <input type="text" class="vt-ai-bot-embed-input" placeholder="<?php echo esc_attr__('Type your question...', 'voxel-toolkit'); ?>" autocomplete="off">
                    <button type="submit" class="vt-ai-bot-embed-send">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
}
