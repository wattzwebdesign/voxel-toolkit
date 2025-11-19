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

        // Enqueue scripts and styles
        wp_enqueue_script('voxel-toolkit-pending-suggestions', VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/pending-suggestions.js', array('jquery'), VOXEL_TOOLKIT_VERSION, true);
        wp_enqueue_style('voxel-toolkit-suggest-edits', VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/suggest-edits.css', array(), VOXEL_TOOLKIT_VERSION);

        wp_localize_script('voxel-toolkit-pending-suggestions', 'vtPendingSuggestions', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_suggest_edits'),
            'i18n' => array(
                'acceptSuccess' => __('Suggestion queued for save', 'voxel-toolkit'),
                'rejectSuccess' => __('Suggestion rejected', 'voxel-toolkit'),
                'saveSuccess' => __('Changes saved successfully', 'voxel-toolkit'),
                'error' => __('An error occurred. Please try again.', 'voxel-toolkit'),
                'confirmSave' => __('Apply all accepted suggestions?', 'voxel-toolkit'),
            ),
        ));
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

        // Style Tab
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Style', 'voxel-toolkit'),
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

        $this->add_control(
            'suggestion_background',
            [
                'label' => __('Suggestion Background', 'voxel-toolkit'),
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
                <div class="vt-suggestions-list">
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
                                        <label><?php _e('Current:', 'voxel-toolkit'); ?></label>
                                        <div class="vt-value">
                                            <?php
                                            if (!empty($suggestion->current_value)) {
                                                echo esc_html($suggestion->current_value);
                                            } else {
                                                echo '<em style="color: #999;">' . __('(not captured)', 'voxel-toolkit') . '</em>';
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($suggestion->suggested_value)): ?>
                                        <div class="vt-arrow">→</div>
                                        <div class="vt-suggested-value-box">
                                            <label><?php _e('Suggested:', 'voxel-toolkit'); ?></label>
                                            <div class="vt-value vt-suggested">
                                                <?php echo esc_html($suggestion->suggested_value); ?>
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
                                            <label><?php _e('Suggested:', 'voxel-toolkit'); ?></label>
                                            <div class="vt-value">
                                                <em style="color: #999;"><?php _e('(empty)', 'voxel-toolkit'); ?></em>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($suggestion->proof_images)): ?>
                                    <div class="vt-proof-images-display">
                                        <label><?php _e('Proof Images:', 'voxel-toolkit'); ?></label>
                                        <div class="vt-images-grid">
                                            <?php
                                            $image_ids = json_decode($suggestion->proof_images, true);
                                            if (is_array($image_ids)) {
                                                foreach ($image_ids as $image_id) {
                                                    $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                                                    if ($image_url) {
                                                        echo '<img src="' . esc_url($image_url) . '" alt="">';
                                                    }
                                                }
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
                                        <?php _e('Accept', 'voxel-toolkit'); ?>
                                    </button>
                                    <button type="button"
                                        class="vt-reject-btn"
                                        data-suggestion-id="<?php echo esc_attr($suggestion->id); ?>">
                                        <i class="eicon-close"></i>
                                        <?php _e('Reject', 'voxel-toolkit'); ?>
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
}
