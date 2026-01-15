<?php
/**
 * Suggest Edits Widget
 *
 * Elementor widget for suggesting edits to post fields
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Suggest_Edits_Widget extends \Elementor\Widget_Base {

    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);

        // Enqueue WordPress media uploader
        wp_enqueue_media();

        // Enqueue scripts and styles
        wp_enqueue_script('voxel-toolkit-suggest-edits', VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/suggest-edits-widget.js', array('jquery'), VOXEL_TOOLKIT_VERSION, true);
        wp_enqueue_style('voxel-toolkit-suggest-edits', VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/suggest-edits.css', array(), VOXEL_TOOLKIT_VERSION);

        wp_localize_script('voxel-toolkit-suggest-edits', 'vtSuggestEdits', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_suggest_edits'),
            'i18n' => array(
                'submitSuccess' => __('Thank you! Your suggestions have been submitted.', 'voxel-toolkit'),
                'submitError' => __('Failed to submit suggestions. Please try again.', 'voxel-toolkit'),
                'noChanges' => __('Please make at least one change before submitting.', 'voxel-toolkit'),
                'emailRequired' => __('Please provide your email address.', 'voxel-toolkit'),
            ),
        ));
    }

    public function get_name() {
        return 'voxel-suggest-edits';
    }

    public function get_title() {
        return __('Suggest Edits (VT)', 'voxel-toolkit');
    }

    public function get_icon() {
        return 'eicon-edit';
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
            'button_text',
            [
                'label' => __('Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Suggest an edit', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'button_icon',
            [
                'label' => __('Button Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
            ]
        );

        $this->add_control(
            'modal_title',
            [
                'label' => __('Modal Title', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Suggest an edit', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'input_placeholder',
            [
                'label' => __('Input Placeholder', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Enter new value...', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'fields_to_show',
            [
                'label' => __('Fields to Show', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $this->get_post_type_fields(),
                'description' => __('Select which fields users can suggest edits for', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'show_incorrect_checkbox',
            [
                'label' => __('Show "Don\'t know" Checkbox', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_permanently_closed',
            [
                'label' => __('Show "Permanently Closed" Option', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'enable_photo_upload',
            [
                'label' => __('Enable Photo Upload', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'max_photos',
            [
                'label' => __('Max Photos', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 5,
                'min' => 1,
                'max' => 20,
                'condition' => [
                    'enable_photo_upload' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'submit_button_text',
            [
                'label' => __('Submit Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Submit', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'show_empty_fields',
            [
                'label' => __('Show Fields Without Values', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Show fields even if they have no value, allowing users to suggest new content', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'inherit_current_values',
            [
                'label' => __('Pre-fill with Current Values', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Pre-populate multi-select fields with current values, allowing users to add or remove items without re-selecting everything', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'enable_comment_field',
            [
                'label' => __('Enable Comment Field', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Allow users to add a comment with their suggestions', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'comment_field_label',
            [
                'label' => __('Comment Field Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Additional Comments', 'voxel-toolkit'),
                'condition' => [
                    'enable_comment_field' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'comment_field_placeholder',
            [
                'label' => __('Comment Field Placeholder', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Add any additional context or notes...', 'voxel-toolkit'),
                'condition' => [
                    'enable_comment_field' => 'yes',
                ],
            ]
        );

        // Translation Section
        $this->add_control(
            'translations_heading',
            [
                'label' => __('Translations', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'label_current_value',
            [
                'label' => __('Current Value Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Current value:', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'label_suggested_value',
            [
                'label' => __('Suggested Value Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Suggested value:', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'label_incorrect_checkbox',
            [
                'label' => __('Incorrect Checkbox Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __("Don't know, but this is incorrect", 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'label_add_photos',
            [
                'label' => __('Add Photos Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Add Photos (proof of changes)', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'label_upload_photos',
            [
                'label' => __('Upload Photos Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Upload Photos', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'label_permanently_closed',
            [
                'label' => __('Permanently Closed Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Mark as Permanently Closed', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'label_no_value',
            [
                'label' => __('No Value Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('No value set', 'voxel-toolkit'),
                'description' => __('Shown when a field has no current value', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // Style Tab
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Suggest an Edit Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'button_icon_color',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-suggest-edit-btn i' => 'color: {{VALUE}}',
                    '{{WRAPPER}} .vt-suggest-edit-btn svg' => 'fill: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_icon_size',
            [
                'label' => __('Icon Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 8,
                        'max' => 50,
                    ],
                    'em' => [
                        'min' => 0.5,
                        'max' => 3,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-suggest-edit-btn i' => 'font-size: {{SIZE}}{{UNIT}}',
                    '{{WRAPPER}} .vt-suggest-edit-btn svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}',
                ],
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-suggest-edit-btn' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-suggest-edit-btn' => 'background-color: {{VALUE}}',
                    '{{WRAPPER}} .vt-modal-submit' => 'background-color: {{VALUE}} !important',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .vt-suggest-edit-btn',
            ]
        );

        $this->add_control(
            'button_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-suggest-edit-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-suggest-edit-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'button_border_color',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-suggest-edit-btn' => 'border: 1px solid {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_section();

        // Field Title Style
        $this->start_controls_section(
            'field_title_style_section',
            [
                'label' => __('Field Title', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'field_title_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-field-header strong' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'field_title_typography',
                'selector' => '{{WRAPPER}} .vt-field-header strong',
            ]
        );

        $this->end_controls_section();

        // Input Fields Style
        $this->start_controls_section(
            'input_style_section',
            [
                'label' => __('Input Fields', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'input_border_color',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-suggestion-input' => 'border-color: {{VALUE}}',
                    '{{WRAPPER}} .vt-form-field input[type="text"]' => 'border-color: {{VALUE}}',
                    '{{WRAPPER}} .vt-form-field input[type="email"]' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'input_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-suggestion-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .vt-form-field input[type="text"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .vt-form-field input[type="email"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Values Labels Style
        $this->start_controls_section(
            'values_labels_section',
            [
                'label' => __('Values Labels', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'value_label_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-field-current label' => 'color: {{VALUE}}',
                    '{{WRAPPER}} .vt-field-suggested label' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'value_label_typography',
                'selector' => '{{WRAPPER}} .vt-field-current label, {{WRAPPER}} .vt-field-suggested label',
            ]
        );

        $this->end_controls_section();

        // Upload Button Style
        $this->start_controls_section(
            'upload_button_style_section',
            [
                'label' => __('Upload Photos Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'upload_button_icon_color',
            [
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-upload-btn i' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'upload_button_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-upload-btn' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'upload_button_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-upload-btn' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'upload_button_border_color',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-upload-btn' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'upload_button_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-upload-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'upload_button_typography',
                'selector' => '{{WRAPPER}} .vt-upload-btn',
            ]
        );

        $this->end_controls_section();

        // Modal Style
        $this->start_controls_section(
            'modal_style_section',
            [
                'label' => __('Modal', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'modal_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '.vt-suggest-modal-content' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'modal_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '.vt-suggest-modal-content' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'modal_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'selectors' => [
                    '.vt-suggest-modal-content' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Get post type fields for current post
     */
    private function get_post_type_fields() {
        $fields = array();

        // Field types to exclude from suggestions
        $excluded_types = array(
            'timezone',
            'switcher',
            'file',
            'repeater',
            'recurring-date',
            'post-relation',
            'time',
            'ui-step',
            'ui-heading',
            'ui-image',
            'team-members-vt',
        );

        // Specific field keys to exclude (logo, cover, gallery, event_date)
        $excluded_keys = array(
            'logo',
            'cover',
            'gallery',
            'event_date',
        );

        // Check if Voxel is active
        if (!class_exists('\Voxel\Post_Type')) {
            return $fields;
        }

        // Get post type - try current post first
        $post_id = get_the_ID();
        $post_type = $post_id ? get_post_type($post_id) : null;

        // If we're editing an Elementor template, get ALL Voxel post types
        // This ensures fields show up when editing templates
        $is_template = ($post_type === 'elementor_library' || !$post_type);

        if ($is_template || \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            // Get all Voxel post types and their fields
            $all_post_types = \Voxel\Post_Type::get_all();

            foreach ($all_post_types as $voxel_post_type) {
                $post_type_label = $voxel_post_type->get_label();
                $post_fields = $voxel_post_type->get_fields();

                foreach ($post_fields as $field) {
                    $field_type = $field->get_type();
                    $field_key = $field->get_key();

                    // Skip excluded field types
                    if (in_array($field_type, $excluded_types)) {
                        continue;
                    }

                    // Skip excluded field keys
                    if (in_array($field_key, $excluded_keys)) {
                        continue;
                    }

                    // Use field key as the option key, label includes post type for clarity
                    // Only add if not already added (avoid duplicates across post types)
                    if (!isset($fields[$field_key])) {
                        $fields[$field_key] = $field->get_label();
                    }
                }
            }
        } else {
            // Frontend or preview - get fields for current post type only
            $voxel_post_type = \Voxel\Post_Type::get($post_type);
            if ($voxel_post_type) {
                $post_fields = $voxel_post_type->get_fields();

                foreach ($post_fields as $field) {
                    $field_type = $field->get_type();
                    $field_key = $field->get_key();

                    // Skip excluded field types
                    if (in_array($field_type, $excluded_types)) {
                        continue;
                    }

                    // Skip excluded field keys
                    if (in_array($field_key, $excluded_keys)) {
                        continue;
                    }

                    $fields[$field_key] = $field->get_label();
                }
            }
        }

        return $fields;
    }

    protected function render() {
        // Enqueue maps library for location field autocomplete
        if (function_exists('\Voxel\enqueue_maps')) {
            \Voxel\enqueue_maps();
        }

        $settings = $this->get_settings_for_display();
        $post_id = get_the_ID();

        if (!$post_id) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p>' . __('Please view on a post page to see the Suggest Edits button.', 'voxel-toolkit') . '</p>';
            }
            return;
        }

        // Check if function is enabled for this post type
        // If no specific post types are configured, allow all post types
        $vt_settings = Voxel_Toolkit_Settings::instance();
        $se_config = $vt_settings->get_function_settings('suggest_edits');
        $enabled_post_types = isset($se_config['post_types']) ? $se_config['post_types'] : array();
        $current_post_type = get_post_type($post_id);

        // Allow all post types if none are specifically configured
        if (!empty($enabled_post_types) && !in_array($current_post_type, $enabled_post_types)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p>' . __('Suggest Edits is not enabled for this post type.', 'voxel-toolkit') . '</p>';
            }
            return;
        }

        // Get current field values
        $field_values = array();
        if (!empty($settings['fields_to_show']) && class_exists('\Voxel\Post')) {
            $voxel_post = \Voxel\Post::get($post_id);
            if ($voxel_post) {
                $post_type_obj = $voxel_post->post_type;
                $all_fields = $post_type_obj->get_fields();

                foreach ($settings['fields_to_show'] as $field_key) {
                    if (isset($all_fields[$field_key])) {
                        $field = $all_fields[$field_key];
                        $field_type = $field->get_type();

                        // Get the field value properly - some fields return objects
                        $field_obj = $voxel_post->get_field($field_key);
                        if (is_object($field_obj) && method_exists($field_obj, 'get_value')) {
                            $value = $field_obj->get_value();
                        } else {
                            $value = $field_obj;
                        }

                        // Check if value is empty
                        $is_empty = false;
                        if (empty($value)) {
                            $is_empty = true;
                        } elseif (is_array($value)) {
                            // Check if array is empty or all values are empty
                            $is_empty = empty(array_filter($value, function($v) {
                                return !empty($v) || $v === 0 || $v === '0';
                            }));
                        }

                        // Skip empty fields unless show_empty_fields is enabled
                        if ($is_empty && $settings['show_empty_fields'] !== 'yes') {
                            continue;
                        }

                        $field_data = array(
                            'label' => $field->get_label(),
                            'value' => $value,
                            'type' => $field_type,
                            'field' => $field,
                            'is_empty' => $is_empty,
                        );

                        // For taxonomy fields, get all available terms with hierarchy
                        if ($field_type === 'taxonomy') {
                            $taxonomy = $field->get_prop('taxonomy');
                            $multiple = $field->get_prop('multiple');

                            if ($taxonomy) {
                                $terms = get_terms(array(
                                    'taxonomy' => $taxonomy,
                                    'hide_empty' => false,
                                    'orderby' => 'name',
                                    'order' => 'ASC',
                                ));
                                if (!is_wp_error($terms)) {
                                    // Build hierarchical structure with depth info
                                    $field_data['options'] = $this->build_hierarchical_terms($terms);
                                    $field_data['multiple'] = $multiple;
                                    $field_data['is_taxonomy'] = true;
                                }
                            }
                        }

                        // For select/multiselect fields, get choices
                        if ($field_type === 'select' || $field_type === 'multiselect') {
                            $choices = $field->get_prop('choices');
                            if (!empty($choices)) {
                                $field_data['options'] = $choices;
                                $field_data['multiple'] = ($field_type === 'multiselect');
                            }
                        }

                        // For work-hours field, format the schedule data
                        if ($field_type === 'work-hours') {

                            // Value is JSON array of schedule groups
                            if (is_string($value)) {
                                $schedule = json_decode($value, true);
                            } else {
                                $schedule = $value;
                            }

                            // Format for display
                            $formatted_schedule = array();
                            if (is_array($schedule)) {
                                foreach ($schedule as $group) {
                                    $days = isset($group['days']) ? $group['days'] : array();
                                    $status = isset($group['status']) ? $group['status'] : 'closed';
                                    $hours = isset($group['hours']) ? $group['hours'] : array();

                                    $formatted_schedule[] = array(
                                        'days' => $days,
                                        'status' => $status,
                                        'hours' => $hours,
                                    );
                                }
                            }

                            $field_data['schedule'] = $formatted_schedule;
                            $field_data['formatted_display'] = Voxel_Toolkit_Field_Formatters::format_work_hours_display($formatted_schedule);
                        }

                        $field_values[$field_key] = $field_data;
                    }
                }
            }
        }

        $button_text = !empty($settings['button_text']) ? $settings['button_text'] : __('Suggest an edit', 'voxel-toolkit');
        $modal_title = !empty($settings['modal_title']) ? $settings['modal_title'] : __('Suggest an edit', 'voxel-toolkit');
        ?>
        <div class="vt-suggest-edits-wrapper">
            <button type="button" class="vt-suggest-edit-btn" data-post-id="<?php echo esc_attr($post_id); ?>">
                <?php
                // Handle Elementor icon
                if (!empty($settings['button_icon']['value'])) {
                    if (is_string($settings['button_icon']['value'])) {
                        // Old format - just icon class
                        echo '<i class="' . esc_attr($settings['button_icon']['value']) . '"></i>';
                    } else {
                        // New format - render using Elementor
                        \Elementor\Icons_Manager::render_icon($settings['button_icon'], ['aria-hidden' => 'true']);
                    }
                }
                ?>
                <?php echo esc_html($button_text); ?>
            </button>
        </div>

        <!-- Modal -->
        <div class="vt-suggest-modal" id="vt-suggest-modal-<?php echo esc_attr($post_id); ?>" style="display: none;">
            <div class="vt-suggest-modal-overlay"></div>
            <div class="vt-suggest-modal-content">
                <div class="vt-suggest-modal-header">
                    <h3><?php echo esc_html($modal_title); ?></h3>
                    <button type="button" class="vt-modal-close">&times;</button>
                </div>

                <div class="vt-suggest-modal-body">
                    <?php if ($settings['show_permanently_closed'] === 'yes'): ?>
                    <!-- Permanently Closed Checkbox -->
                    <div class="vt-permanently-closed-wrapper">
                        <label class="vt-checkbox-label">
                            <input type="checkbox" class="vt-permanently-closed-checkbox" name="permanently_closed">
                            <span><?php echo esc_html(!empty($settings['label_permanently_closed']) ? $settings['label_permanently_closed'] : __('Mark as Permanently Closed', 'voxel-toolkit')); ?></span>
                        </label>
                    </div>
                    <?php endif; ?>

                    <?php if (!is_user_logged_in() && !empty($se_config['allow_guests'])): ?>
                        <div class="vt-guest-info">
                            <div class="vt-form-field">
                                <label><?php _e('Your Name', 'voxel-toolkit'); ?> <span class="required">*</span></label>
                                <input type="text" name="suggester_name" required>
                            </div>
                            <div class="vt-form-field">
                                <label><?php _e('Your Email', 'voxel-toolkit'); ?> <span class="required">*</span></label>
                                <input type="email" name="suggester_email" required>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($field_values)): ?>
                        <div class="vt-fields-list">
                            <?php foreach ($field_values as $field_key => $field_data): ?>
                                <div class="vt-field-item <?php echo ($field_data['type'] === 'work-hours') ? 'vt-work-hours-field' : ''; ?>" data-field-key="<?php echo esc_attr($field_key); ?>" data-field-type="<?php echo esc_attr($field_data['type']); ?>">
                                    <div class="vt-field-header">
                                        <strong><?php echo esc_html($field_data['label']); ?></strong>
                                    </div>

                                    <div class="vt-field-current">
                                        <label><?php echo esc_html(!empty($settings['label_current_value']) ? $settings['label_current_value'] : __('Current value:', 'voxel-toolkit')); ?></label>
                                        <div class="vt-current-value">
                                            <?php
                                            // Check if field is empty - show custom label
                                            if (!empty($field_data['is_empty'])) {
                                                $no_value_label = !empty($settings['label_no_value']) ? $settings['label_no_value'] : __('No value set', 'voxel-toolkit');
                                                echo '<span class="vt-no-value">' . esc_html($no_value_label) . '</span>';
                                            } else {
                                                // Check field type for special rendering
                                                $is_image_field = ($field_data['type'] === 'image');
                                                $is_work_hours = ($field_data['type'] === 'work-hours');

                                                if ($is_work_hours && !empty($field_data['formatted_display'])) {
                                                    // Display formatted work hours
                                                    echo '<div class="vt-wh-current-value">' . $field_data['formatted_display'] . '</div>';
                                                } elseif ($is_image_field && !empty($field_data['value'])) {
                                                    // Handle both single image ID and array of image IDs
                                                    $image_id = 0;
                                                    if (is_array($field_data['value']) && !empty($field_data['value'])) {
                                                        // Get first image from array
                                                        $first_item = reset($field_data['value']);
                                                        $image_id = is_numeric($first_item) ? intval($first_item) : 0;
                                                    } elseif (is_numeric($field_data['value'])) {
                                                        $image_id = intval($field_data['value']);
                                                    }

                                                    if ($image_id > 0) {
                                                        $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                                                        if ($image_url) {
                                                            echo '<img src="' . esc_url($image_url) . '" alt="" style="max-width: 100px; max-height: 100px; border-radius: 4px; display: block;">';
                                                        } else {
                                                            echo esc_html($this->format_value($field_data['value'], $field_data['type'], $field_data['field'] ?? null));
                                                        }
                                                    } else {
                                                        echo esc_html($this->format_value($field_data['value'], $field_data['type'], $field_data['field'] ?? null));
                                                    }
                                                } else {
                                                    $field_type = $field_data['type'] ?? '';
                                                    $formatted_value = $this->format_value($field_data['value'], $field_data['type'], $field_data['field'] ?? null);

                                                    // Allow safe HTML for rich text fields
                                                    if (in_array($field_type, ['texteditor', 'description'])) {
                                                        echo '<div class="vt-html-content">' . wp_kses_post($formatted_value) . '</div>';
                                                    } else {
                                                        echo esc_html($formatted_value);
                                                    }
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <div class="vt-field-suggested">
                                        <?php if ($field_data['type'] === 'work-hours'): ?>
                                            <!-- Work Hours - Show Button to Open Popup -->
                                            <button type="button" class="vt-wh-suggest-btn" data-field-key="<?php echo esc_attr($field_key); ?>">
                                                <i class="eicon-clock-o"></i>
                                                <?php _e('Suggest New Hours', 'voxel-toolkit'); ?>
                                            </button>

                                            <!-- Hidden data container for schedule -->
                                            <div class="vt-wh-data-container" style="display: none;" data-schedule='<?php echo esc_attr(json_encode($field_data['schedule'])); ?>'></div>

                                        <?php else: ?>
                                            <label><?php echo esc_html(!empty($settings['label_suggested_value']) ? $settings['label_suggested_value'] : __('Suggested value:', 'voxel-toolkit')); ?></label>

                                            <?php if (!empty($field_data['options'])): ?>
                                                <?php
                                                $is_multiple = !empty($field_data['multiple']);
                                                $is_taxonomy = !empty($field_data['is_taxonomy']);
                                                $inherit_values = ($settings['inherit_current_values'] === 'yes');

                                                // Get current values for pre-selection (if inherit enabled)
                                                $current_selected = array();
                                                if ($inherit_values && !empty($field_data['value'])) {
                                                    if ($is_taxonomy) {
                                                        // Extract term IDs from current value
                                                        $current_values = is_array($field_data['value']) ? $field_data['value'] : array($field_data['value']);
                                                        foreach ($current_values as $val) {
                                                            if (is_object($val) && isset($val->term_id)) {
                                                                $current_selected[] = $val->term_id;
                                                            } elseif (is_object($val) && method_exists($val, 'get_id')) {
                                                                $current_selected[] = $val->get_id();
                                                            } elseif (is_numeric($val)) {
                                                                $current_selected[] = intval($val);
                                                            }
                                                        }
                                                    } else {
                                                        // Regular select/multiselect values
                                                        $current_selected = is_array($field_data['value']) ? $field_data['value'] : array($field_data['value']);
                                                    }
                                                }
                                                ?>

                                                <?php if ($is_multiple): ?>
                                                    <!-- Checkbox List UI for Multi-select -->
                                                    <div class="vt-multicheck" data-field-key="<?php echo esc_attr($field_key); ?>">
                                                        <div class="vt-multicheck__scroll">
                                                            <?php foreach ($field_data['options'] as $option): ?>
                                                                <?php
                                                                if ($is_taxonomy) {
                                                                    $opt_value = $option['term_id'];
                                                                    $opt_label = $option['display_name'];
                                                                    $opt_name = $option['name'];
                                                                    $depth = $option['depth'];
                                                                } else {
                                                                    $opt_value = $option['value'];
                                                                    $opt_label = $option['label'];
                                                                    $opt_name = $option['label'];
                                                                    $depth = 0;
                                                                }
                                                                $is_selected = in_array($opt_value, $current_selected);
                                                                ?>
                                                                <div class="vt-multicheck__item <?php echo $is_selected ? 'vt-multicheck__item--checked' : ''; ?>"
                                                                     data-value="<?php echo esc_attr($opt_value); ?>"
                                                                     data-depth="<?php echo esc_attr($depth); ?>"
                                                                     style="<?php echo $depth > 0 ? 'padding-left: ' . (16 + ($depth * 20)) . 'px;' : ''; ?>">
                                                                    <span class="vt-multicheck__circle">
                                                                        <span class="vt-multicheck__checkmark"></span>
                                                                    </span>
                                                                    <span class="vt-multicheck__label"><?php echo esc_html($is_taxonomy ? $opt_name : $opt_label); ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <!-- Hidden select for form compatibility -->
                                                    <select class="vt-suggestion-input vt-multicheck__select"
                                                            data-field-key="<?php echo esc_attr($field_key); ?>"
                                                            multiple
                                                            style="display: none !important;">
                                                        <?php foreach ($field_data['options'] as $option): ?>
                                                            <?php
                                                            if ($is_taxonomy) {
                                                                $opt_value = $option['term_id'];
                                                                $opt_name = $option['name'];
                                                            } else {
                                                                $opt_value = $option['value'];
                                                                $opt_name = $option['label'];
                                                            }
                                                            $is_selected = in_array($opt_value, $current_selected);
                                                            ?>
                                                            <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($is_selected); ?>>
                                                                <?php echo esc_html($opt_name); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php else: ?>
                                                    <!-- Single Select Dropdown -->
                                                    <select class="vt-suggestion-input"
                                                            data-field-key="<?php echo esc_attr($field_key); ?>">
                                                        <option value=""><?php _e('Select...', 'voxel-toolkit'); ?></option>
                                                        <?php foreach ($field_data['options'] as $option): ?>
                                                            <?php
                                                            if ($is_taxonomy) {
                                                                $opt_value = $option['term_id'];
                                                                $opt_label = $option['display_name'];
                                                            } else {
                                                                $opt_value = $option['value'];
                                                                $opt_label = $option['label'];
                                                            }
                                                            ?>
                                                            <option value="<?php echo esc_attr($opt_value); ?>">
                                                                <?php echo esc_html($opt_label); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <input type="text"
                                                    class="vt-suggestion-input"
                                                    data-field-key="<?php echo esc_attr($field_key); ?>"
                                                    placeholder="<?php echo esc_attr($settings['input_placeholder']); ?>">
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($settings['show_incorrect_checkbox'] === 'yes'): ?>
                                        <div class="vt-field-incorrect">
                                            <label>
                                                <input type="checkbox" class="vt-incorrect-checkbox" data-field-key="<?php echo esc_attr($field_key); ?>">
                                                <?php echo esc_html(!empty($settings['label_incorrect_checkbox']) ? $settings['label_incorrect_checkbox'] : __("Don't know, but this is incorrect", 'voxel-toolkit')); ?>
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php _e('No fields available for editing.', 'voxel-toolkit'); ?></p>
                    <?php endif; ?>

                    <?php if ($settings['enable_photo_upload'] === 'yes'): ?>
                        <div class="vt-proof-photos">
                            <label><?php echo esc_html(!empty($settings['label_add_photos']) ? $settings['label_add_photos'] : __('Add Photos (proof of changes)', 'voxel-toolkit')); ?></label>
                            <div class="vt-photo-upload-area">
                                <button type="button" class="vt-upload-btn">
                                    <i class="eicon-upload"></i>
                                    <?php echo esc_html(!empty($settings['label_upload_photos']) ? $settings['label_upload_photos'] : __('Upload Photos', 'voxel-toolkit')); ?>
                                </button>
                                <div class="vt-uploaded-photos"></div>
                            </div>
                            <input type="hidden" class="vt-photo-ids" value="">
                        </div>

                        <!-- Upload Progress Bar -->
                        <div class="vt-upload-progress" style="display: none;">
                            <div class="vt-upload-progress-bar-container">
                                <div class="vt-upload-progress-bar"></div>
                            </div>
                            <span class="vt-upload-progress-text">Uploading...</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($settings['enable_comment_field'] === 'yes'): ?>
                        <div class="vt-suggester-comment">
                            <label><?php echo esc_html($settings['comment_field_label'] ?: __('Additional Comments', 'voxel-toolkit')); ?></label>
                            <textarea class="vt-comment-textarea" name="suggester_comment" rows="3" placeholder="<?php echo esc_attr($settings['comment_field_placeholder'] ?: __('Add any additional context or notes...', 'voxel-toolkit')); ?>"></textarea>
                        </div>
                    <?php endif; ?>

                    <div class="vt-form-messages"></div>
                </div>

                <div class="vt-suggest-modal-footer">
                    <button type="button" class="vt-modal-cancel">
                        <?php _e('Cancel', 'voxel-toolkit'); ?>
                    </button>
                    <button type="button" class="vt-modal-submit"<?php echo !empty($settings['button_background']) ? ' style="background-color: ' . esc_attr($settings['button_background']) . ' !important;"' : ''; ?>>
                        <?php echo esc_html($settings['submit_button_text'] ?? __('Submit', 'voxel-toolkit')); ?>
                    </button>
                </div>
            </div>

            <!-- Work Hours Popup Modal -->
            <div class="vt-wh-popup" style="display: none;">
                <div class="vt-wh-popup-overlay"></div>
                <div class="vt-wh-popup-content">
                    <div class="vt-wh-popup-header">
                        <h3><?php _e('Suggest New Hours', 'voxel-toolkit'); ?></h3>
                        <button type="button" class="vt-wh-popup-close">&times;</button>
                    </div>
                    <div class="vt-wh-popup-body">
                        <!-- Work Hours Editor -->
                        <div class="vt-wh-editor">
                            <div class="vt-wh-groups"></div>
                            <button type="button" class="vt-wh-add-group">+ <?php _e('Add schedule', 'voxel-toolkit'); ?></button>
                        </div>
                    </div>
                    <div class="vt-wh-popup-footer">
                        <button type="button" class="vt-wh-popup-cancel"><?php _e('Cancel', 'voxel-toolkit'); ?></button>
                        <button type="button" class="vt-wh-popup-save"><?php _e('Save Hours', 'voxel-toolkit'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Format field value for display
     */
    private function format_value($value, $type, $field = null) {
        // Handle location fields - extract address
        if ($type === 'location' && is_array($value) && isset($value['address'])) {
            return !empty($value['address']) ? $value['address'] : __('(empty)', 'voxel-toolkit');
        }

        // Handle taxonomy fields - convert term IDs to labels
        if ($type === 'taxonomy' && $field) {
            $taxonomy = $field->get_prop('taxonomy');
            if ($taxonomy) {
                $term_ids = is_array($value) ? $value : array($value);
                $term_labels = array();

                foreach ($term_ids as $item) {
                    if (is_object($item)) {
                        // Voxel Term object
                        if (method_exists($item, 'get_label')) {
                            $term_labels[] = $item->get_label();
                        } elseif (isset($item->name)) {
                            $term_labels[] = $item->name;
                        }
                    } elseif (is_numeric($item)) {
                        // Term ID - look up the term name
                        $term = get_term($item, $taxonomy);
                        if ($term && !is_wp_error($term)) {
                            $term_labels[] = $term->name;
                        }
                    } else {
                        $term_labels[] = $item;
                    }
                }

                return !empty($term_labels) ? implode(', ', $term_labels) : __('(empty)', 'voxel-toolkit');
            }
        }

        // Handle arrays (taxonomy terms, etc.) - check this BEFORE empty check
        if (is_array($value)) {
            if (empty($value)) {
                return __('(empty)', 'voxel-toolkit');
            }

            $formatted = array();
            foreach ($value as $item) {
                if (is_object($item)) {
                    // Voxel Term object
                    if (method_exists($item, 'get_label')) {
                        $formatted[] = $item->get_label();
                    } elseif (isset($item->name)) {
                        $formatted[] = $item->name;
                    }
                } else {
                    $formatted[] = $item;
                }
            }
            return !empty($formatted) ? implode(', ', $formatted) : __('(empty)', 'voxel-toolkit');
        }

        // Handle objects
        if (is_object($value)) {
            if (method_exists($value, 'get_label')) {
                return $value->get_label();
            } elseif (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return __('(object)', 'voxel-toolkit');
        }

        // Handle empty values
        if (empty($value)) {
            return __('(empty)', 'voxel-toolkit');
        }

        return $value;
    }

    /**
     * Format work hours schedule for display
     * @deprecated Use Voxel_Toolkit_Field_Formatters::format_work_hours_display() instead
     */
    private function format_work_hours_display($schedule) {
        return Voxel_Toolkit_Field_Formatters::format_work_hours_display($schedule);
    }

    /**
     * Build hierarchical term structure with depth info
     *
     * @param array $terms Array of WP_Term objects
     * @return array Terms with hierarchy data (depth, display_name with indentation)
     */
    private function build_hierarchical_terms($terms) {
        // Create lookup array by term_id
        $terms_by_id = array();
        foreach ($terms as $term) {
            $terms_by_id[$term->term_id] = $term;
        }

        // Calculate depth for each term
        $result = array();
        foreach ($terms as $term) {
            $depth = 0;
            $parent_id = $term->parent;

            // Walk up the parent chain to calculate depth
            while ($parent_id > 0 && isset($terms_by_id[$parent_id])) {
                $depth++;
                $parent_id = $terms_by_id[$parent_id]->parent;
            }

            $result[] = array(
                'term_id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'parent' => $term->parent,
                'depth' => $depth,
                'display_name' => str_repeat('— ', $depth) . $term->name,
            );
        }

        // Sort hierarchically: parents before children, alphabetically within same level
        usort($result, function($a, $b) use ($terms_by_id) {
            // Build ancestry paths for comparison
            $path_a = $this->get_term_ancestry_path($a, $terms_by_id);
            $path_b = $this->get_term_ancestry_path($b, $terms_by_id);
            return strcmp($path_a, $path_b);
        });

        return $result;
    }

    /**
     * Get ancestry path for sorting hierarchical terms
     *
     * @param array $term_data Term data array
     * @param array $terms_by_id Lookup array of terms
     * @return string Ancestry path for sorting
     */
    private function get_term_ancestry_path($term_data, $terms_by_id) {
        $path = array();
        $current_id = $term_data['term_id'];

        // Build path from term up to root
        while ($current_id > 0) {
            if (isset($terms_by_id[$current_id])) {
                array_unshift($path, $terms_by_id[$current_id]->name);
                $current_id = $terms_by_id[$current_id]->parent;
            } else {
                break;
            }
        }

        return implode('/', $path);
    }
}
