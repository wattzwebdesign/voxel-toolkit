<?php
/**
 * Pending Suggestions Widget
 *
 * Display and manage pending edit suggestions for post authors
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Pending_Suggestions_Widget extends \Elementor\Widget_Base {

    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);

        // Enqueue styles only (no JS needed - backend handles Accept/Reject)
        wp_enqueue_style('voxel-toolkit-suggest-edits', VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/suggest-edits.css', array(), VOXEL_TOOLKIT_VERSION);
    }

    public function get_name() {
        return 'voxel-pending-suggestions';
    }

    public function get_title() {
        return __('Pending Suggestions (VT)', 'voxel-toolkit');
    }

    public function get_icon() {
        return 'eicon-time-line';
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
            'widget_title',
            [
                'label' => __('Widget Title', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Pending Edit Suggestions', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'no_suggestions_text',
            [
                'label' => __('No Suggestions Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('No pending suggestions', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'save_button_text',
            [
                'label' => __('Save Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Save Changes', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'show_status_filter',
            [
                'label' => __('Show Status Filter', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Labels Section
        $this->start_controls_section(
            'labels_section',
            [
                'label' => __('Labels', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'label_current',
            [
                'label' => __('Current Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Current:', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'label_suggested',
            [
                'label' => __('Suggested Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Suggested:', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'label_proof_images',
            [
                'label' => __('Proof Images Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Proof Images:', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'button_accept_text',
            [
                'label' => __('Accept Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Accept', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'button_reject_text',
            [
                'label' => __('Reject Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Reject', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // Confirmation Messages Section
        $this->start_controls_section(
            'confirmation_messages_section',
            [
                'label' => __('Confirmation Messages', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'confirm_accept_message',
            [
                'label' => __('Accept Confirmation (Regular)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Are you sure you want to accept this suggestion?', 'voxel-toolkit'),
                'description' => __('Message shown when accepting regular suggestions', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'confirm_delete_first',
            [
                'label' => __('Delete Confirmation (First Warning)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'rows' => 5,
                'default' => __("WARNING: Accepting this will PERMANENTLY DELETE the post. This action CANNOT be undone!\n\nThe post will be moved to trash and cannot be recovered.\n\nAre you absolutely sure you want to proceed?", 'voxel-toolkit'),
                'description' => __('First warning for permanently closed suggestions', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'confirm_delete_second',
            [
                'label' => __('Delete Confirmation (Final Warning)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'rows' => 3,
                'default' => __("FINAL WARNING: You are about to delete this post permanently.\n\nClick OK to confirm deletion, or Cancel to stop.", 'voxel-toolkit'),
                'description' => __('Final warning for permanently closed suggestions', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'confirm_reject_message',
            [
                'label' => __('Reject Confirmation', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Are you sure you want to reject this suggestion?', 'voxel-toolkit'),
                'description' => __('Message shown when rejecting suggestions', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // Style Tab - General
        $this->start_controls_section(
            'style_general_section',
            [
                'label' => __('General', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Title Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-pending-suggestions-title' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .vt-pending-suggestions-title',
            ]
        );

        $this->end_controls_section();

        // Style Tab - Suggestion Cards
        $this->start_controls_section(
            'style_cards_section',
            [
                'label' => __('Suggestion Cards', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'suggestion_background',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-suggestion-item' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'border_color',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-suggestion-item' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'border_width',
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
                'selectors' => [
                    '{{WRAPPER}} .vt-suggestion-item' => 'border-width: {{SIZE}}{{UNIT}}',
                ],
            ]
        );

        $this->add_control(
            'border_radius',
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
                    '{{WRAPPER}} .vt-suggestion-item' => 'border-radius: {{SIZE}}{{UNIT}}',
                ],
            ]
        );

        $this->add_control(
            'card_spacing',
            [
                'label' => __('Space Between Cards', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-suggestion-item' => 'margin-bottom: {{SIZE}}{{UNIT}}',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Labels
        $this->start_controls_section(
            'style_labels_section',
            [
                'label' => __('Labels', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label' => __('Label Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-current-value-box label' => 'color: {{VALUE}}',
                    '{{WRAPPER}} .vt-suggested-value-box label' => 'color: {{VALUE}}',
                    '{{WRAPPER}} .vt-proof-images-display label' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'label_typography',
                'selector' => '{{WRAPPER}} .vt-current-value-box label, {{WRAPPER}} .vt-suggested-value-box label, {{WRAPPER}} .vt-proof-images-display label',
            ]
        );

        $this->end_controls_section();

        // Style Tab - Values
        $this->start_controls_section(
            'style_values_section',
            [
                'label' => __('Values', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'current_value_color',
            [
                'label' => __('Current Value Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-current-value-box .vt-value' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'current_value_bg',
            [
                'label' => __('Current Value Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-current-value-box' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'suggested_value_color',
            [
                'label' => __('Suggested Value Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-suggested-value-box .vt-value' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'suggested_value_bg',
            [
                'label' => __('Suggested Value Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-suggested-value-box' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Buttons
        $this->start_controls_section(
            'style_buttons_section',
            [
                'label' => __('Buttons', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'accept_button_color',
            [
                'label' => __('Accept Button Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-accept-btn' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'accept_button_text_color',
            [
                'label' => __('Accept Button Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-accept-btn' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'reject_button_color',
            [
                'label' => __('Reject Button Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-reject-btn' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'reject_button_text_color',
            [
                'label' => __('Reject Button Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-reject-btn' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .vt-accept-btn, {{WRAPPER}} .vt-reject-btn',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $post_id = get_the_ID();

        if (!$post_id) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p>' . __('Please view on a post page to see pending suggestions.', 'voxel-toolkit') . '</p>';
            }
            return;
        }

        // Check if current user is the post author
        $current_user_id = get_current_user_id();
        $post_author_id = get_post_field('post_author', $post_id);

        if ($current_user_id != $post_author_id && !current_user_can('edit_others_posts')) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p>' . __('Only the post author can see pending suggestions.', 'voxel-toolkit') . '</p>';
            }
            return;
        }

        // Get suggestions
        $suggest_edits = Voxel_Toolkit_Suggest_Edits::instance();
        $pending_suggestions = $suggest_edits->get_suggestions_by_post($post_id, 'pending');
        $queued_suggestions = $suggest_edits->get_suggestions_by_post($post_id, 'queued');
        $all_suggestions = array_merge($pending_suggestions, $queued_suggestions);

        $widget_title = !empty($settings['widget_title']) ? $settings['widget_title'] : __('Pending Edit Suggestions', 'voxel-toolkit');
        ?>
        <div class="vt-pending-suggestions-wrapper" data-post-id="<?php echo esc_attr($post_id); ?>">
            <div class="vt-pending-suggestions-header">
                <h3 class="vt-pending-suggestions-title"><?php echo esc_html($widget_title); ?></h3>

                <?php if ($settings['show_status_filter'] === 'yes' && !empty($all_suggestions)): ?>
                    <div class="vt-status-filter">
                        <select class="vt-filter-select">
                            <option value="all"><?php _e('All', 'voxel-toolkit'); ?></option>
                            <option value="pending" selected><?php _e('Pending', 'voxel-toolkit'); ?></option>
                            <option value="queued"><?php _e('Queued', 'voxel-toolkit'); ?></option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <div class="vt-form-messages"></div>

            <?php if (empty($all_suggestions)): ?>
                <div class="vt-no-suggestions">
                    <p><?php echo esc_html($settings['no_suggestions_text'] ?? __('No pending suggestions', 'voxel-toolkit')); ?></p>
                </div>
            <?php else: ?>
                <div class="vt-suggestions-list"
                     data-confirm-accept="<?php echo esc_attr($settings['confirm_accept_message'] ?: __('Are you sure you want to accept this suggestion?', 'voxel-toolkit')); ?>"
                     data-confirm-delete-first="<?php echo esc_attr($settings['confirm_delete_first'] ?: __("WARNING: Accepting this will PERMANENTLY DELETE the post. This action CANNOT be undone!\n\nThe post will be moved to trash and cannot be recovered.\n\nAre you absolutely sure you want to proceed?", 'voxel-toolkit')); ?>"
                     data-confirm-delete-second="<?php echo esc_attr($settings['confirm_delete_second'] ?: __("FINAL WARNING: You are about to delete this post permanently.\n\nClick OK to confirm deletion, or Cancel to stop.", 'voxel-toolkit')); ?>"
                     data-confirm-reject="<?php echo esc_attr($settings['confirm_reject_message'] ?: __('Are you sure you want to reject this suggestion?', 'voxel-toolkit')); ?>">
                    <?php foreach ($all_suggestions as $suggestion): ?>
                        <?php
                        $field_label = $this->get_field_label($post_id, $suggestion->field_key);
                        $status_class = 'vt-status-' . $suggestion->status;
                        ?>
                        <div class="vt-suggestion-item <?php echo esc_attr($status_class); ?>"
                            data-suggestion-id="<?php echo esc_attr($suggestion->id); ?>"
                            data-status="<?php echo esc_attr($suggestion->status); ?>">

                            <div class="vt-suggestion-header">
                                <div class="vt-field-info">
                                    <strong class="vt-field-name"><?php echo esc_html($field_label); ?></strong>
                                    <span class="vt-suggester">
                                        <?php printf(__('by %s', 'voxel-toolkit'), esc_html($suggestion->suggester_name)); ?>
                                    </span>
                                    <span class="vt-suggestion-date">
                                        <?php echo human_time_diff(strtotime($suggestion->created_at), time()) . ' ' . __('ago', 'voxel-toolkit'); ?>
                                    </span>
                                </div>
                                <div class="vt-suggestion-status">
                                    <?php if ($suggestion->status === 'queued'): ?>
                                        <span class="vt-status-badge vt-status-queued"><?php _e('Queued', 'voxel-toolkit'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="vt-suggestion-body">
                                <div class="vt-value-comparison">
                                    <div class="vt-current-value-box">
                                        <label><?php echo esc_html($settings['label_current'] ?: __('Current:', 'voxel-toolkit')); ?></label>
                                        <div class="vt-value">
                                            <?php
                                            if (!empty($suggestion->current_value)) {
                                                echo $this->format_suggestion_value($suggestion->current_value, $suggestion->field_key, $suggestion->post_id);
                                            } else {
                                                echo '<em style="color: #999;">' . __('(not captured)', 'voxel-toolkit') . '</em>';
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($suggestion->suggested_value)): ?>
                                        <div class="vt-arrow">→</div>
                                        <div class="vt-suggested-value-box">
                                            <label><?php echo esc_html($settings['label_suggested'] ?: __('Suggested:', 'voxel-toolkit')); ?></label>
                                            <div class="vt-value vt-suggested">
                                                <?php echo $this->format_suggestion_value($suggestion->suggested_value, $suggestion->field_key, $suggestion->post_id); ?>
                                                <?php if ($suggestion->is_incorrect): ?>
                                                    <br><em style="color: #d63638; font-size: 12px; margin-top: 5px; display: inline-block;">
                                                        <i class="eicon-warning"></i>
                                                        <?php _e('Also marked as incorrect', 'voxel-toolkit'); ?>
                                                    </em>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php elseif ($suggestion->is_incorrect): ?>
                                        <div class="vt-incorrect-notice">
                                            <i class="eicon-warning"></i>
                                            <?php _e('Marked as incorrect (no suggested value)', 'voxel-toolkit'); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="vt-arrow">→</div>
                                        <div class="vt-suggested-value-box">
                                            <label><?php echo esc_html($settings['label_suggested'] ?: __('Suggested:', 'voxel-toolkit')); ?></label>
                                            <div class="vt-value">
                                                <em style="color: #999;"><?php _e('(empty)', 'voxel-toolkit'); ?></em>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($suggestion->proof_images)): ?>
                                    <div class="vt-proof-images-display">
                                        <label><?php echo esc_html($settings['label_proof_images'] ?: __('Proof Images:', 'voxel-toolkit')); ?></label>
                                        <div class="vt-images-grid">
                                            <?php
                                            // Debug: show what's in proof_images
                                            error_log('VT: Proof images raw: ' . $suggestion->proof_images);
                                            $image_ids = json_decode($suggestion->proof_images, true);
                                            error_log('VT: Decoded image IDs: ' . print_r($image_ids, true));

                                            if (is_array($image_ids) && !empty($image_ids)) {
                                                foreach ($image_ids as $image_id) {
                                                    error_log('VT: Processing image ID: ' . $image_id);
                                                    $image_url = wp_get_attachment_url($image_id);
                                                    $thumbnail_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                                                    error_log('VT: Image URL: ' . $image_url . ', Thumbnail URL: ' . $thumbnail_url);

                                                    if ($image_url) {
                                                        echo '<a href="' . esc_url($image_url) . '" target="_blank" class="vt-proof-image-link">';
                                                        echo '<img src="' . esc_url($thumbnail_url ?: $image_url) . '" alt="' . esc_attr__('Proof image', 'voxel-toolkit') . '">';
                                                        echo '</a>';
                                                    } else {
                                                        error_log('VT: No URL found for image ID: ' . $image_id);
                                                    }
                                                }
                                            } else {
                                                error_log('VT: Image IDs not an array or empty');
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($suggestion->status === 'pending'): ?>
                                <div class="vt-suggestion-actions">
                                    <button type="button"
                                        class="vt-accept-btn"
                                        data-suggestion-id="<?php echo esc_attr($suggestion->id); ?>">
                                        <i class="eicon-check"></i>
                                        <?php echo esc_html($settings['button_accept_text'] ?: __('Accept', 'voxel-toolkit')); ?>
                                    </button>
                                    <button type="button"
                                        class="vt-reject-btn"
                                        data-suggestion-id="<?php echo esc_attr($suggestion->id); ?>">
                                        <i class="eicon-close"></i>
                                        <?php echo esc_html($settings['button_reject_text'] ?: __('Reject', 'voxel-toolkit')); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($queued_suggestions)): ?>
                    <div class="vt-save-actions">
                        <button type="button" class="vt-save-all-btn">
                            <i class="eicon-save"></i>
                            <?php echo esc_html($settings['save_button_text'] ?? __('Save Changes', 'voxel-toolkit')); ?>
                            <span class="vt-queued-count">(<?php echo count($queued_suggestions); ?>)</span>
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get field label from post type
     */
    private function get_field_label($post_id, $field_key) {
        // Special handling for permanently closed
        if ($field_key === '_permanently_closed') {
            return __('Permanently Closed?', 'voxel-toolkit');
        }

        if (class_exists('\Voxel\Post')) {
            $voxel_post = \Voxel\Post::get($post_id);
            if ($voxel_post) {
                $post_type = $voxel_post->post_type;
                $fields = $post_type->get_fields();
                if (isset($fields[$field_key])) {
                    return $fields[$field_key]->get_label();
                }
            }
        }

        return $field_key;
    }

    /**
     * Format suggestion value for display based on field type
     */
    private function format_suggestion_value($value, $field_key, $post_id) {
        if (empty($value)) {
            return '';
        }

        // Get field type
        $field_type = $this->get_field_type($post_id, $field_key);

        // Handle work-hours field specially
        if ($field_type === 'work-hours') {
            $schedule = is_string($value) ? json_decode($value, true) : $value;
            if (is_array($schedule)) {
                return Voxel_Toolkit_Field_Formatters::format_work_hours_display($schedule);
            }
        }

        // Handle location field specially
        if ($field_type === 'location') {
            return Voxel_Toolkit_Field_Formatters::format_location_display($value);
        }

        // Default formatting
        return esc_html($value);
    }

    /**
     * Get field type from post
     */
    private function get_field_type($post_id, $field_key) {
        if (class_exists('\Voxel\Post')) {
            $voxel_post = \Voxel\Post::get($post_id);
            if ($voxel_post) {
                $post_type = $voxel_post->post_type;
                $field = $post_type->get_field($field_key);
                if ($field) {
                    return $field->get_type();
                }
            }
        }

        return '';
    }
}
