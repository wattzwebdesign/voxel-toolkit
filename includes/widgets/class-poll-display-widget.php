<?php
/**
 * Poll Display Widget
 *
 * Displays a poll field from the current post
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Poll_Display_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'vt-poll-display';
    }

    public function get_title() {
        return __('Poll Display (VT)', 'voxel-toolkit');
    }

    public function get_icon() {
        return 'eicon-checkbox';
    }

    public function get_categories() {
        return ['voxel', 'basic'];
    }

    protected function register_controls() {
        // Poll Settings
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Poll Settings', 'voxel-toolkit'),
            ]
        );

        // Get all post types with poll fields
        $poll_fields = [];
        $post_types = \Voxel\Post_Type::get_voxel_types();

        foreach ($post_types as $post_type) {
            foreach ($post_type->get_fields() as $field) {
                if ($field->get_type() === 'poll-vt') {
                    $poll_fields[$field->get_key()] = sprintf(
                        '%s: %s',
                        $post_type->get_label(),
                        $field->get_label()
                    );
                }
            }
        }

        $this->add_control(
            'poll_field',
            [
                'label' => __('Select Poll Field', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $poll_fields,
                'default' => !empty($poll_fields) ? array_key_first($poll_fields) : '',
            ]
        );

        $this->add_control(
            'hide_if_empty',
            [
                'label' => __('Hide Element if No Poll Options Exist', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
            ]
        );

        $this->end_controls_section();

        // Text Settings
        $this->start_controls_section(
            'section_text',
            [
                'label' => __('Text Settings', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'vote_singular',
            [
                'label' => __('Vote (Singular)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'vote',
            ]
        );

        $this->add_control(
            'vote_plural',
            [
                'label' => __('Votes (Plural)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'votes',
            ]
        );

        $this->add_control(
            'add_option_placeholder',
            [
                'label' => __('Add Option Placeholder', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Add your own option...',
            ]
        );

        $this->add_control(
            'add_option_button',
            [
                'label' => __('Add Option Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Add Option',
            ]
        );

        $this->add_control(
            'total_votes_text',
            [
                'label' => __('Total Votes Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Total votes:',
            ]
        );

        $this->end_controls_section();

        // Radio/Checkbox Style
        $this->start_controls_section(
            'section_input_style',
            [
                'label' => __('Radio/Checkbox', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'input_size',
            [
                'label' => __('Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 12,
                        'max' => 40,
                    ],
                ],
                'default' => [
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-input' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'input_border_color',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#d0d0d0',
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-input' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_border_width',
            [
                'label' => __('Border Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 1,
                        'max' => 5,
                    ],
                ],
                'default' => [
                    'size' => 2,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-input' => 'border-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'input_checked_color',
            [
                'label' => __('Checked Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-input:checked' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_checkmark_color',
            [
                'label' => __('Checkmark Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-input[type="radio"]:checked::after' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_hover_border_color',
            [
                'label' => __('Hover Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-input:hover' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Option Name Typography - Not Voted
        $this->start_controls_section(
            'section_option_typography_not_voted',
            [
                'label' => __('Option Name - Not Voted', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'option_name_typography_not_voted',
                'selector' => '{{WRAPPER}} .vt-poll-option:not(.voted) .vt-poll-option-text',
            ]
        );

        $this->add_control(
            'option_name_color_not_voted',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-option:not(.voted) .vt-poll-option-text' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Option Name Typography - Voted
        $this->start_controls_section(
            'section_option_typography_voted',
            [
                'label' => __('Option Name - Voted', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'option_name_typography_voted',
                'selector' => '{{WRAPPER}} .vt-poll-option.voted .vt-poll-option-text',
            ]
        );

        $this->add_control(
            'option_name_color_voted',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-option.voted .vt-poll-option-text' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Option Box Style
        $this->start_controls_section(
            'section_option_box',
            [
                'label' => __('Option Box', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'option_border_color',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e0e0e0',
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-option' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'option_border_width',
            [
                'label' => __('Border Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 10,
                    ],
                ],
                'default' => [
                    'size' => 1,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-option' => 'border-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'option_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 8,
                    'right' => 8,
                    'bottom' => 8,
                    'left' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-option' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'option_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 12,
                    'right' => 16,
                    'bottom' => 12,
                    'left' => 16,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-option-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'option_not_voted_bg',
            [
                'label' => __('Not Voted Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-option:not(.voted)' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'option_hover_border_color',
            [
                'label' => __('Hover Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-option:hover' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'option_voted_bg',
            [
                'label' => __('Voted Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-option.voted' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Progress Bar
        $this->start_controls_section(
            'section_progress_bar',
            [
                'label' => __('Progress Bar', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'progress_bar_bg',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f0f0f0',
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-progress-bar' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'progress_bar_fill',
            [
                'label' => __('Fill Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#F2F4F4',
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-progress-fill' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'progress_bar_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 3,
                    'right' => 3,
                    'bottom' => 3,
                    'left' => 3,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-progress-bar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .vt-poll-progress-fill' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Vote Count Typography
        $this->start_controls_section(
            'section_vote_count',
            [
                'label' => __('Vote Count', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'vote_count_typography',
                'selector' => '{{WRAPPER}} .vt-poll-vote-count',
            ]
        );

        $this->add_control(
            'vote_count_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#666',
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-vote-count' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Percentage Typography
        $this->start_controls_section(
            'section_percentage',
            [
                'label' => __('Percentage', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'percentage_typography',
                'selector' => '{{WRAPPER}} .vt-poll-percentage',
            ]
        );

        $this->add_control(
            'percentage_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#999',
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-percentage' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Add Option Input
        $this->start_controls_section(
            'section_add_option_input',
            [
                'label' => __('Add Option Input', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'add_option_input_typography',
                'selector' => '{{WRAPPER}} .vt-poll-new-option',
            ]
        );

        $this->add_control(
            'add_option_input_bg',
            [
                'label' => __('Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-new-option' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'add_option_input_border',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-new-option' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'add_option_input_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-new-option' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'add_option_input_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-new-option' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Add Option Button
        $this->start_controls_section(
            'section_add_option_button',
            [
                'label' => __('Add Option Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'add_option_button_typography',
                'selector' => '{{WRAPPER}} .vt-poll-submit-option',
            ]
        );

        $this->add_control(
            'add_option_button_bg',
            [
                'label' => __('Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-submit-option' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'add_option_button_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#fff',
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-submit-option' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'add_option_button_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-submit-option' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'add_option_button_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-poll-submit-option' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $field_key = $settings['poll_field'];

        // Get checkmark color for SVG
        $checkmark_color = !empty($settings['input_checkmark_color']) ? $settings['input_checkmark_color'] : '#ffffff';
        // Convert hex to URL-encoded format for SVG
        $checkmark_color_encoded = str_replace('#', '%23', $checkmark_color);

        if (empty($field_key)) {
            echo '<p>' . __('Please select a poll field in the widget settings.', 'voxel-toolkit') . '</p>';
            return;
        }

        // Get current post
        $post = \Voxel\get_current_post();
        if (!$post) {
            echo '<p>' . __('No post found.', 'voxel-toolkit') . '</p>';
            return;
        }

        // Get the field
        $field = $post->get_field($field_key);
        if (!$field || $field->get_type() !== 'poll-vt') {
            echo '<p>' . __('Poll field not found.', 'voxel-toolkit') . '</p>';
            return;
        }

        // Get the poll field type instance to render
        $value = $field->get_value();
        if (empty($value) || empty($value['options'])) {
            // Check if we should hide the widget when empty
            if ($settings['hide_if_empty'] === 'yes') {
                return; // Hide completely
            }
            echo '<p>' . __('No poll data available.', 'voxel-toolkit') . '</p>';
            return;
        }

        $post_id = $post->get_id();
        $user_id = get_current_user_id();
        $allow_multiple = !empty($value['allow_multiple']);
        $allow_user_options = !empty($value['allow_user_options']);

        // Combine admin options and user-submitted options
        $all_options = $value['options'];
        if (!empty($value['user_submitted_options'])) {
            $all_options = array_merge($all_options, $value['user_submitted_options']);
        }

        // Calculate total votes
        $total_votes = 0;
        foreach ($all_options as $option) {
            if (isset($option['votes']) && is_array($option['votes'])) {
                $total_votes += count($option['votes']);
            }
        }

        // Generate unique ID for this widget instance
        $widget_id = $this->get_id();
        ?>
        <style>
            .elementor-element-<?php echo esc_attr($widget_id); ?> .vt-poll-input[type="checkbox"]:checked::after {
                background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12"><path fill="<?php echo esc_attr($checkmark_color_encoded); ?>" d="M7.8,21.425A2.542,2.542,0,0,1,6,20.679L.439,15.121,2.561,13,7.8,18.239,21.439,4.6l2.122,2.121L9.6,20.679A2.542,2.542,0,0,1,7.8,21.425Z"/></svg>');
            }
        </style>
        <div class="vt-poll-display" data-post-id="<?php echo esc_attr($post_id); ?>" data-field-key="<?php echo esc_attr($field_key); ?>" data-allow-multiple="<?php echo $allow_multiple ? '1' : '0'; ?>">
            <div class="vt-poll-options-list">
                <?php foreach ($all_options as $index => $option): ?>
                    <?php
                    $vote_count = isset($option['votes']) && is_array($option['votes']) ? count($option['votes']) : 0;
                    $percentage = $total_votes > 0 ? round(($vote_count / $total_votes) * 100) : 0;
                    $has_voted = $user_id && isset($option['votes']) && in_array($user_id, $option['votes']);
                    $is_user_submitted = isset($option['submitted_by']);
                    $submitted_by_username = '';
                    if ($is_user_submitted && !empty($option['submitted_by'])) {
                        $submitted_user = get_userdata($option['submitted_by']);
                        if ($submitted_user) {
                            $submitted_by_username = $submitted_user->user_login;
                        }
                    }
                    ?>
                    <label class="vt-poll-option <?php echo $has_voted ? 'voted' : ''; ?> <?php echo !$user_id ? 'disabled' : ''; ?>" data-option-index="<?php echo esc_attr($index); ?>">
                        <div class="vt-poll-option-content">
                            <div class="vt-poll-option-left">
                                <input
                                    type="<?php echo $allow_multiple ? 'checkbox' : 'radio'; ?>"
                                    name="poll-<?php echo esc_attr($field_key); ?>"
                                    value="<?php echo esc_attr($index); ?>"
                                    <?php checked($has_voted); ?>
                                    <?php disabled(!$user_id); ?>
                                    class="vt-poll-input"
                                >
                                <div class="vt-poll-option-text-wrap">
                                    <span class="vt-poll-option-text"><?php echo esc_html($option['label']); ?></span>
                                    <?php if ($is_user_submitted && $submitted_by_username): ?>
                                        <span class="vt-poll-user-badge">(Submitted by <?php echo esc_html($submitted_by_username); ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="vt-poll-percentage"><?php echo $percentage; ?>%</span>
                        </div>
                        <div class="vt-poll-progress-bar" style="width: <?php echo $percentage; ?>%">
                            <div class="vt-poll-progress-fill"></div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <?php if ($allow_user_options && $user_id): ?>
                <div class="vt-poll-add-option">
                    <input type="text" class="vt-poll-new-option" placeholder="<?php echo esc_attr($settings['add_option_placeholder']); ?>" />
                    <button type="button" class="vt-poll-submit-option"><?php echo esc_html($settings['add_option_button']); ?></button>
                </div>
            <?php endif; ?>

            <div class="vt-poll-total"><?php echo esc_html($settings['total_votes_text']); ?> <?php echo $total_votes; ?></div>
        </div>
        <?php
    }
}
