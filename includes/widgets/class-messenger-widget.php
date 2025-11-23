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
            'enable_sound',
            [
                'label' => __('Enable Sound Notifications', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'no',
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
            'badge_background',
            [
                'label' => __('Badge Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ff0000',
                'selectors' => [
                    '{{WRAPPER}} .vt-messenger-badge' => 'background-color: {{VALUE}}',
                ],
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

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Check if user is logged in
        if (!is_user_logged_in()) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p>' . __('Messenger widget is only visible to logged-in users.', 'voxel-toolkit') . '</p>';
            }
            return;
        }

        // Check page rules from settings
        $messenger_settings = get_option('voxel_toolkit_messenger_settings', array());
        if (!$this->should_display_messenger($messenger_settings)) {
            return;
        }

        $position_class = 'vt-messenger-position-' . $settings['position'];
        $max_chats = !empty($settings['max_open_chats']) ? $settings['max_open_chats'] : 3;
        ?>
        <div class="vt-messenger-container <?php echo esc_attr($position_class); ?>"
             data-max-chats="<?php echo esc_attr($max_chats); ?>"
             data-show-badge="<?php echo esc_attr($settings['show_unread_badge']); ?>"
             data-enable-sound="<?php echo esc_attr($settings['enable_sound']); ?>">

            <!-- Main Messenger Button -->
            <button class="vt-messenger-button" aria-label="<?php _e('Open messenger', 'voxel-toolkit'); ?>">
                <i class="eicon-comments"></i>
                <?php if ($settings['show_unread_badge'] === 'yes'): ?>
                    <span class="vt-messenger-badge" style="display: none;">0</span>
                <?php endif; ?>
            </button>

            <!-- Chat List Popup -->
            <div class="vt-messenger-popup" style="display: none;">
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
                    <div class="vt-messenger-loading">
                        <i class="eicon-loading eicon-animation-spin"></i>
                    </div>
                </div>
            </div>

            <!-- Chat Windows Container -->
            <div class="vt-messenger-chat-windows"></div>
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
