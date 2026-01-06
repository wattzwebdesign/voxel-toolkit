<?php
/**
 * Checklist Display Widget
 *
 * Displays a checklist field from the current post with full styling controls.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Checklist_Display_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'vt-checklist-display';
    }

    public function get_title() {
        return __('Checklist Display (VT)', 'voxel-toolkit');
    }

    public function get_icon() {
        return 'eicon-checkbox';
    }

    public function get_categories() {
        return ['voxel', 'basic'];
    }

    public function get_script_depends() {
        return ['vt-checklist-display'];
    }

    public function get_style_depends() {
        return ['vt-checklist-display'];
    }

    protected function register_controls() {
        // Checklist Settings
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Checklist Settings', 'voxel-toolkit'),
            ]
        );

        // Get all post types with checklist fields
        $checklist_fields = [];
        $post_types = \Voxel\Post_Type::get_voxel_types();

        foreach ($post_types as $post_type) {
            foreach ($post_type->get_fields() as $field) {
                if ($field->get_type() === 'checklist-vt') {
                    $checklist_fields[$field->get_key()] = sprintf(
                        '%s: %s',
                        $post_type->get_label(),
                        $field->get_label()
                    );
                }
            }
        }

        $this->add_control(
            'checklist_field',
            [
                'label' => __('Select Checklist Field', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $checklist_fields,
                'default' => !empty($checklist_fields) ? array_key_first($checklist_fields) : '',
            ]
        );

        $this->add_control(
            'hide_if_empty',
            [
                'label' => __('Hide if No Items', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
            ]
        );

        $this->add_control(
            'show_progress_bar',
            [
                'label' => __('Show Progress Bar', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_percentage',
            [
                'label' => __('Show Percentage Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_checked_timestamp',
            [
                'label' => __('Show Checked Timestamp', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
            ]
        );

        $this->add_control(
            'hide_checked_items',
            [
                'label' => __('Hide Checked Items', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Hide items that have been checked off.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'hide_unchecked_items',
            [
                'label' => __('Hide Unchecked Items', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Hide items that have not been checked.', 'voxel-toolkit'),
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
            'empty_text',
            [
                'label' => __('Empty Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('No checklist items.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'progress_text',
            [
                'label' => __('Progress Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '{checked} of {total} completed ({percentage}%)',
                'description' => __('Use {checked}, {total}, {percentage} placeholders.', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // Checkbox Style
        $this->start_controls_section(
            'section_checkbox_style',
            [
                'label' => __('Checkbox', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'checkbox_size',
            [
                'label' => __('Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 14,
                        'max' => 40,
                    ],
                ],
                'default' => [
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-checkbox' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'checkbox_border_color',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#d0d0d0',
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-checkbox' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'checkbox_border_width',
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
                    '{{WRAPPER}} .vt-checklist-checkbox' => 'border-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'checkbox_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 20,
                    ],
                ],
                'default' => [
                    'size' => 4,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-checkbox' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'checkbox_checked_bg',
            [
                'label' => __('Checked Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-checkbox.is-checked' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'checkbox_checkmark_color',
            [
                'label' => __('Checkmark Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
            ]
        );

        $this->add_control(
            'checkbox_hover_border_color',
            [
                'label' => __('Hover Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item:hover .vt-checklist-checkbox' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Item Title Style
        $this->start_controls_section(
            'section_title_style',
            [
                'label' => __('Item Title', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .vt-checklist-title',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'title_checked_color',
            [
                'label' => __('Checked Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#888888',
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item.is-checked .vt-checklist-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'title_checked_decoration',
            [
                'label' => __('Checked Text Decoration', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'none' => __('None', 'voxel-toolkit'),
                    'line-through' => __('Strikethrough', 'voxel-toolkit'),
                ],
                'default' => 'line-through',
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item.is-checked .vt-checklist-title' => 'text-decoration: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Item Description Style
        $this->start_controls_section(
            'section_description_style',
            [
                'label' => __('Item Description', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography',
                'selector' => '{{WRAPPER}} .vt-checklist-description',
            ]
        );

        $this->add_control(
            'description_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#666666',
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-description' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'description_checked_color',
            [
                'label' => __('Checked Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#999999',
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item.is-checked .vt-checklist-description' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'description_spacing',
            [
                'label' => __('Top Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                ],
                'default' => [
                    'size' => 4,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-description' => 'margin-top: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Item Box Style
        $this->start_controls_section(
            'section_item_style',
            [
                'label' => __('Item Box', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'item_bg',
            [
                'label' => __('Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'item_checked_bg',
            [
                'label' => __('Checked Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item.is-checked' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'item_border_color',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e0e0e0',
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'item_border_width',
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
                'default' => [
                    'size' => 1,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item' => 'border-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'item_border_radius',
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
                    '{{WRAPPER}} .vt-checklist-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'item_padding',
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
                    '{{WRAPPER}} .vt-checklist-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'item_spacing',
            [
                'label' => __('Spacing Between Items', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 40,
                    ],
                ],
                'default' => [
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-item + .vt-checklist-item' => 'margin-top: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Progress Bar Style
        $this->start_controls_section(
            'section_progress_style',
            [
                'label' => __('Progress Bar', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_progress_bar' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'progress_height',
            [
                'label' => __('Height', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 4,
                        'max' => 30,
                    ],
                ],
                'default' => [
                    'size' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-progress-bar' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'progress_bg',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e0e0e0',
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-progress-bar' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'progress_fill',
            [
                'label' => __('Fill Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-progress-fill' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'progress_border_radius',
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
                'default' => [
                    'size' => 4,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-progress-bar, {{WRAPPER}} .vt-checklist-progress-fill' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'progress_margin',
            [
                'label' => __('Margin Bottom', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 40,
                    ],
                ],
                'default' => [
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-progress' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Progress Text Style
        $this->start_controls_section(
            'section_progress_text_style',
            [
                'label' => __('Progress Text', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_percentage' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'progress_text_typography',
                'selector' => '{{WRAPPER}} .vt-checklist-progress-text',
            ]
        );

        $this->add_control(
            'progress_text_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#666666',
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-progress-text' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'progress_text_spacing',
            [
                'label' => __('Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                ],
                'default' => [
                    'size' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-progress-text' => 'margin-top: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Timestamp Style
        $this->start_controls_section(
            'section_timestamp_style',
            [
                'label' => __('Timestamp', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_checked_timestamp' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'timestamp_typography',
                'selector' => '{{WRAPPER}} .vt-checklist-timestamp',
            ]
        );

        $this->add_control(
            'timestamp_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#999999',
                'selectors' => [
                    '{{WRAPPER}} .vt-checklist-timestamp' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $field_key = $settings['checklist_field'];

        if (empty($field_key)) {
            echo '<p>' . __('Please select a checklist field in the widget settings.', 'voxel-toolkit') . '</p>';
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
        if (!$field || $field->get_type() !== 'checklist-vt') {
            echo '<p>' . __('Checklist field not found.', 'voxel-toolkit') . '</p>';
            return;
        }

        // Get field props and settings
        $props = $field->get_props();
        $check_scope = isset($props['check_scope']) ? $props['check_scope'] : 'global';
        $check_permission = isset($props['check_permission']) ? $props['check_permission'] : 'author';

        // Get items with state
        $post_id = $post->get_id();
        $user_id = get_current_user_id();
        $checklist_manager = Voxel_Toolkit_Checklist_Field::instance();
        $items = $checklist_manager->get_items_with_state($post_id, $field_key, $check_scope, $user_id);

        if (empty($items)) {
            if ($settings['hide_if_empty'] === 'yes') {
                return;
            }
            echo '<p>' . esc_html($settings['empty_text']) . '</p>';
            return;
        }

        // Calculate progress
        $progress = $checklist_manager->calculate_progress($post_id, $field_key, ['items' => $items], $check_scope, $user_id);

        // Check if user can interact
        $can_check = false;
        if ($user_id) {
            if ($check_permission === 'author') {
                $can_check = ($user_id === $post->get_author_id());
            } elseif ($check_permission === 'logged_in') {
                $can_check = true;
            }
        }

        // Get checkmark color for SVG
        $checkmark_color = !empty($settings['checkbox_checkmark_color']) ? $settings['checkbox_checkmark_color'] : '#ffffff';
        $checkmark_color_encoded = str_replace('#', '%23', $checkmark_color);

        $widget_id = $this->get_id();
        ?>
        <style>
            .elementor-element-<?php echo esc_attr($widget_id); ?> .vt-checklist-checkbox.is-checked::after {
                background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12"><path fill="<?php echo esc_attr($checkmark_color_encoded); ?>" d="M7.8,21.425A2.542,2.542,0,0,1,6,20.679L.439,15.121,2.561,13,7.8,18.239,21.439,4.6l2.122,2.121L9.6,20.679A2.542,2.542,0,0,1,7.8,21.425Z"/></svg>');
            }
        </style>
        <div class="vt-checklist-display"
             data-post-id="<?php echo esc_attr($post_id); ?>"
             data-field-key="<?php echo esc_attr($field_key); ?>"
             data-can-check="<?php echo $can_check ? '1' : '0'; ?>"
             data-nonce="<?php echo esc_attr(wp_create_nonce('vt_checklist_nonce')); ?>">

            <?php if ($settings['show_progress_bar'] === 'yes' || $settings['show_percentage'] === 'yes'): ?>
                <div class="vt-checklist-progress">
                    <?php if ($settings['show_progress_bar'] === 'yes'): ?>
                        <div class="vt-checklist-progress-bar">
                            <div class="vt-checklist-progress-fill" style="width: <?php echo esc_attr($progress['percentage']); ?>%;"></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($settings['show_percentage'] === 'yes'): ?>
                        <div class="vt-checklist-progress-text">
                            <?php
                            $progress_text = $settings['progress_text'];
                            $progress_text = str_replace('{checked}', $progress['checked'], $progress_text);
                            $progress_text = str_replace('{total}', $progress['total'], $progress_text);
                            $progress_text = str_replace('{percentage}', $progress['percentage'], $progress_text);
                            echo esc_html($progress_text);
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="vt-checklist-items">
                <?php foreach ($items as $index => $item):
                    $is_checked = !empty($item['checked']);

                    // Skip based on visibility settings
                    if ($is_checked && $settings['hide_checked_items'] === 'yes') {
                        continue;
                    }
                    if (!$is_checked && $settings['hide_unchecked_items'] === 'yes') {
                        continue;
                    }

                    $has_title = !empty($item['title']);
                    $has_description = !empty($item['description']);
                    ?>
                    <div class="vt-checklist-item <?php echo $is_checked ? 'is-checked' : ''; ?> <?php echo $can_check ? 'can-check' : ''; ?>"
                         data-index="<?php echo esc_attr($index); ?>">
                        <div class="vt-checklist-checkbox <?php echo $is_checked ? 'is-checked' : ''; ?>"></div>
                        <div class="vt-checklist-content">
                            <?php if ($has_title): ?>
                                <div class="vt-checklist-title"><?php echo esc_html($item['title']); ?></div>
                            <?php endif; ?>
                            <?php if ($has_description): ?>
                                <div class="vt-checklist-description"><?php echo esc_html($item['description']); ?></div>
                            <?php endif; ?>
                            <?php if ($settings['show_checked_timestamp'] === 'yes' && $is_checked && !empty($item['checked_timestamp'])): ?>
                                <div class="vt-checklist-timestamp">
                                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $item['checked_timestamp'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
