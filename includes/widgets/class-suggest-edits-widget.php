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
                'default' => [
                    'value' => 'fas fa-edit',
                    'library' => 'solid',
                ],
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

        $this->end_controls_section();

        // Style Tab
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
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
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-suggest-edit-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
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

        // Check if we're in Elementor editor
        if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
            // Try to get post type from current post
            $post_id = get_the_ID();
            if ($post_id) {
                $post_type = get_post_type($post_id);
            } else {
                return $fields;
            }
        } else {
            $post_id = get_the_ID();
            if (!$post_id) {
                return $fields;
            }
            $post_type = get_post_type($post_id);
        }

        // Get Voxel post type
        if (class_exists('\Voxel\Post_Type')) {
            $voxel_post_type = \Voxel\Post_Type::get($post_type);
            if ($voxel_post_type) {
                $post_fields = $voxel_post_type->get_fields();

                // Field types to exclude from suggestions
                $excluded_types = array(
                    'timezone',
                    'work-hours',
                    'switcher',
                    'file',
                    'repeater',
                    'recurring-date',
                    'post-relation',
                    'time',
                    'ui-step',
                    'ui-heading',
                    'ui-image',
                );

                // Specific field keys to exclude (logo, cover, gallery, event_date)
                $excluded_keys = array(
                    'logo',
                    'cover',
                    'gallery',
                    'event_date',
                );

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
        $settings = $this->get_settings_for_display();
        $post_id = get_the_ID();

        if (!$post_id) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p>' . __('Please view on a post page to see the Suggest Edits button.', 'voxel-toolkit') . '</p>';
            }
            return;
        }

        // Check if function is enabled for this post type
        $vt_settings = Voxel_Toolkit_Settings::instance();
        $se_config = $vt_settings->get_function_settings('suggest_edits');
        $enabled_post_types = isset($se_config['post_types']) ? $se_config['post_types'] : array();
        $current_post_type = get_post_type($post_id);

        if (!in_array($current_post_type, $enabled_post_types)) {
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

                        error_log('Voxel Toolkit: Field ' . $field_key . ' (type: ' . $field_type . ') value: ' . print_r($value, true));

                        $field_data = array(
                            'label' => $field->get_label(),
                            'value' => $value,
                            'type' => $field_type,
                        );

                        // For taxonomy fields, get all available terms
                        if ($field_type === 'taxonomy') {
                            $taxonomy = $field->get_prop('taxonomy');
                            $multiple = $field->get_prop('multiple');
                            error_log('Voxel Toolkit: Found taxonomy field: ' . $field_key . ', taxonomy: ' . $taxonomy . ', multiple: ' . ($multiple ? 'yes' : 'no'));

                            if ($taxonomy) {
                                $terms = get_terms(array(
                                    'taxonomy' => $taxonomy,
                                    'hide_empty' => false,
                                ));
                                error_log('Voxel Toolkit: Retrieved ' . count($terms) . ' terms for taxonomy: ' . $taxonomy);
                                if (!is_wp_error($terms)) {
                                    $field_data['options'] = $terms;
                                    $field_data['multiple'] = $multiple;
                                    error_log('Voxel Toolkit: Added terms to field_data');
                                } else {
                                    error_log('Voxel Toolkit: Error retrieving terms: ' . $terms->get_error_message());
                                }
                            }
                        }

                        // For select/multiselect fields, get choices
                        if ($field_type === 'select' || $field_type === 'multiselect') {
                            $choices = $field->get_prop('choices');
                            error_log('Voxel Toolkit: Found ' . $field_type . ' field: ' . $field_key . ', choices: ' . print_r($choices, true));
                            if (!empty($choices)) {
                                $field_data['options'] = $choices;
                                $field_data['multiple'] = ($field_type === 'multiselect');
                            }
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
                <?php if (!empty($settings['button_icon']['value'])): ?>
                    <i class="<?php echo esc_attr($settings['button_icon']['value']); ?>"></i>
                <?php endif; ?>
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
                                <div class="vt-field-item" data-field-key="<?php echo esc_attr($field_key); ?>">
                                    <div class="vt-field-header">
                                        <i class="eicon-edit"></i>
                                        <strong><?php echo esc_html($field_data['label']); ?></strong>
                                    </div>

                                    <div class="vt-field-current">
                                        <label><?php _e('Current value:', 'voxel-toolkit'); ?></label>
                                        <div class="vt-current-value">
                                            <?php echo esc_html($this->format_value($field_data['value'], $field_data['type'])); ?>
                                        </div>
                                    </div>

                                    <div class="vt-field-suggested">
                                        <label><?php _e('Suggested value:', 'voxel-toolkit'); ?></label>
                                        <?php if (!empty($field_data['options'])): ?>
                                            <?php
                                            $is_multiple = !empty($field_data['multiple']);
                                            $is_taxonomy = ($field_data['type'] === 'taxonomy');
                                            ?>
                                            <select class="vt-suggestion-input"
                                                data-field-key="<?php echo esc_attr($field_key); ?>"
                                                <?php echo $is_multiple ? 'multiple' : ''; ?>>
                                                <?php if (!$is_multiple): ?>
                                                    <option value=""><?php _e('Select...', 'voxel-toolkit'); ?></option>
                                                <?php endif; ?>
                                                <?php foreach ($field_data['options'] as $option): ?>
                                                    <?php if ($is_taxonomy): ?>
                                                        <option value="<?php echo esc_attr($option->term_id); ?>">
                                                            <?php echo esc_html($option->name); ?>
                                                        </option>
                                                    <?php else: ?>
                                                        <option value="<?php echo esc_attr($option['value']); ?>">
                                                            <?php echo esc_html($option['label']); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <input type="text"
                                                class="vt-suggestion-input"
                                                data-field-key="<?php echo esc_attr($field_key); ?>"
                                                placeholder="<?php _e('Enter new value...', 'voxel-toolkit'); ?>">
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($settings['show_incorrect_checkbox'] === 'yes'): ?>
                                        <div class="vt-field-incorrect">
                                            <label>
                                                <input type="checkbox" class="vt-incorrect-checkbox" data-field-key="<?php echo esc_attr($field_key); ?>">
                                                <?php _e("Don't know, but this is incorrect", 'voxel-toolkit'); ?>
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
                            <label><?php _e('Add Photos (proof of changes)', 'voxel-toolkit'); ?></label>
                            <div class="vt-photo-upload-area">
                                <button type="button" class="vt-upload-btn">
                                    <i class="eicon-upload"></i>
                                    <?php _e('Upload Photos', 'voxel-toolkit'); ?>
                                </button>
                                <div class="vt-uploaded-photos"></div>
                            </div>
                            <input type="hidden" class="vt-photo-ids" value="">
                        </div>
                    <?php endif; ?>

                    <div class="vt-form-messages"></div>
                </div>

                <div class="vt-suggest-modal-footer">
                    <button type="button" class="vt-modal-cancel">
                        <?php _e('Cancel', 'voxel-toolkit'); ?>
                    </button>
                    <button type="button" class="vt-modal-submit">
                        <?php echo esc_html($settings['submit_button_text'] ?? __('Submit', 'voxel-toolkit')); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Format field value for display
     */
    private function format_value($value, $type) {
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
}
