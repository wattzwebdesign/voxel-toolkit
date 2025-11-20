<?php
/**
 * Poll Field (VT)
 *
 * Custom field type for creating polls with multiple options,
 * user voting, and optional user-submitted options
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Poll_Field {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register the custom field type
        add_filter('voxel/field-types', array($this, 'register_field_type'));

        // Add frontend template
        add_action('wp_head', array($this, 'add_frontend_template'));

        // Add admin styles for field configuration
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handler for voting
        add_action('wp_ajax_vt_poll_vote', array($this, 'handle_vote'));
        add_action('wp_ajax_nopriv_vt_poll_vote', array($this, 'handle_vote'));

        // AJAX handler for adding user option
        add_action('wp_ajax_vt_poll_add_option', array($this, 'handle_add_option'));
        add_action('wp_ajax_nopriv_vt_poll_add_option', array($this, 'handle_add_option'));
    }

    /**
     * Register the poll field type
     */
    public function register_field_type($fields) {
        if (!class_exists('\Voxel\Post_Types\Fields\Base_Post_Field')) {
            return $fields;
        }

        $fields['poll-vt'] = '\Voxel_Toolkit_Poll_Field_Type';
        return $fields;
    }

    /**
     * Add frontend Vue template
     */
    public function add_frontend_template() {
        ?>
        <script>
        document.addEventListener('voxel/create-post/init', e => {
            const { app, config, el } = e.detail;

            app.component('field-poll-vt', {
                template: `
                    <div class="ts-form-group vt-poll-field">
                        <label>
                            {{ field.label }}
                            <slot name="errors"></slot>
                        </label>

                        <div class="vt-poll-options">
                            <div v-for="(option, index) in field.value.options" :key="index" class="vt-poll-option-item">
                                <input
                                    type="text"
                                    v-model="option.label"
                                    :placeholder="'Option ' + (index + 1)"
                                    class="ts-filter"
                                >
                                <button
                                    type="button"
                                    @click="removeOption(index)"
                                    class="ts-icon-btn ts-smaller"
                                    v-if="field.value.options.length > 2"
                                >
                                    <i class="las la-trash"></i>
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="addOption"
                            class="ts-btn ts-btn-2 ts-btn-small"
                            style="margin-top: 10px;"
                        >
                            <i class="las la-plus"></i> Add Option
                        </button>

                        <div class="vt-poll-settings" style="margin-top: 15px;">
                            <label class="ts-form-group switcher-label">
                                <div class="switch-slider">
                                    <div class="onoffswitch">
                                        <input
                                            v-model="field.value.allow_user_options"
                                            type="checkbox"
                                            class="onoffswitch-checkbox"
                                        >
                                        <label class="onoffswitch-label"></label>
                                    </div>
                                </div>
                                Allow users to add their own options
                            </label>

                            <label class="ts-form-group switcher-label">
                                <div class="switch-slider">
                                    <div class="onoffswitch">
                                        <input
                                            v-model="field.value.allow_multiple"
                                            type="checkbox"
                                            class="onoffswitch-checkbox"
                                        >
                                        <label class="onoffswitch-label"></label>
                                    </div>
                                </div>
                                Allow users to choose multiple options
                            </label>
                        </div>
                    </div>
                `,
                props: {
                    field: Object
                },
                mounted() {
                    // Initialize field value structure if empty
                    if (!this.field.value || typeof this.field.value !== 'object') {
                        this.field.value = {
                            options: [
                                { label: '', votes: [] },
                                { label: '', votes: [] }
                            ],
                            allow_user_options: false,
                            allow_multiple: false,
                            user_submitted_options: []
                        };
                    }

                    // Ensure minimum structure
                    if (!this.field.value.options || this.field.value.options.length < 2) {
                        this.field.value.options = [
                            { label: '', votes: [] },
                            { label: '', votes: [] }
                        ];
                    }
                },
                methods: {
                    addOption() {
                        this.field.value.options.push({ label: '', votes: [] });
                    },
                    removeOption(index) {
                        if (this.field.value.options.length > 2) {
                            this.field.value.options.splice(index, 1);
                        }
                    }
                }
            });
        });
        </script>

        <style>
        .vt-poll-field .vt-poll-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .vt-poll-field .vt-poll-option-item {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .vt-poll-field .vt-poll-option-item input {
            flex: 1;
        }

        .vt-poll-field .vt-poll-settings {
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .vt-poll-field .vt-poll-settings .switcher-label {
            margin-bottom: 10px;
        }
        </style>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Add any admin-specific scripts here if needed
    }

    /**
     * Handle vote submission
     */
    public function handle_vote() {
        check_ajax_referer('vt_poll_vote', 'nonce');

        $post_id = absint($_POST['post_id'] ?? 0);
        $field_key = sanitize_key($_POST['field_key'] ?? '');
        $option_index = absint($_POST['option_index'] ?? 0);
        $user_id = get_current_user_id();

        if (!$post_id || !$field_key) {
            wp_send_json_error(['message' => 'Invalid request']);
        }

        // Get current poll data
        $poll_data = get_post_meta($post_id, $field_key, true);

        if (!is_array($poll_data) || !isset($poll_data['options'][$option_index])) {
            wp_send_json_error(['message' => 'Invalid option']);
        }

        // Initialize votes array if not exists
        if (!isset($poll_data['options'][$option_index]['votes'])) {
            $poll_data['options'][$option_index]['votes'] = [];
        }

        // Check if user already voted (for single-choice polls)
        if (!$poll_data['allow_multiple']) {
            foreach ($poll_data['options'] as $idx => &$option) {
                if (isset($option['votes']) && in_array($user_id, $option['votes'])) {
                    // Remove previous vote
                    $option['votes'] = array_diff($option['votes'], [$user_id]);
                    $option['votes'] = array_values($option['votes']);
                }
            }
        }

        // Toggle vote
        if (in_array($user_id, $poll_data['options'][$option_index]['votes'])) {
            // Remove vote
            $poll_data['options'][$option_index]['votes'] = array_diff(
                $poll_data['options'][$option_index]['votes'],
                [$user_id]
            );
        } else {
            // Add vote
            $poll_data['options'][$option_index]['votes'][] = $user_id;
        }

        // Re-index array
        $poll_data['options'][$option_index]['votes'] = array_values($poll_data['options'][$option_index]['votes']);

        // Update post meta
        update_post_meta($post_id, $field_key, $poll_data);

        wp_send_json_success([
            'poll_data' => $poll_data,
            'message' => 'Vote recorded'
        ]);
    }

    /**
     * Handle user-submitted option
     */
    public function handle_add_option() {
        check_ajax_referer('vt_poll_add_option', 'nonce');

        $post_id = absint($_POST['post_id'] ?? 0);
        $field_key = sanitize_key($_POST['field_key'] ?? '');
        $option_label = sanitize_text_field($_POST['option_label'] ?? '');
        $user_id = get_current_user_id();

        if (!$post_id || !$field_key || !$option_label) {
            wp_send_json_error(['message' => 'Invalid request']);
        }

        // Get current poll data
        $poll_data = get_post_meta($post_id, $field_key, true);

        if (!is_array($poll_data) || !$poll_data['allow_user_options']) {
            wp_send_json_error(['message' => 'User options not allowed']);
        }

        // Initialize user_submitted_options if not exists
        if (!isset($poll_data['user_submitted_options'])) {
            $poll_data['user_submitted_options'] = [];
        }

        // Add new option
        $new_option = [
            'label' => $option_label,
            'votes' => [$user_id], // Auto-vote for the option they created
            'submitted_by' => $user_id,
            'submitted_at' => current_time('mysql')
        ];

        $poll_data['user_submitted_options'][] = $new_option;

        // Update post meta
        update_post_meta($post_id, $field_key, $poll_data);

        wp_send_json_success([
            'poll_data' => $poll_data,
            'message' => 'Option added'
        ]);
    }

    /**
     * Render settings section
     */
    public static function render_settings() {
        ?>
        <tr>
            <th scope="row">
                <label><?php _e('Poll Field (VT)', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <p class="description">
                    <?php _e('Adds a custom "Poll (VT)" field type to Voxel post types. Create interactive polls with:', 'voxel-toolkit'); ?>
                </p>
                <ul style="list-style: disc; margin-left: 20px; margin-top: 10px;">
                    <li><?php _e('Multiple admin-defined poll options', 'voxel-toolkit'); ?></li>
                    <li><?php _e('User voting with visual results', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Optional user-submitted options (with author attribution)', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Single or multiple choice voting', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Vote tracking per user', 'voxel-toolkit'); ?></li>
                </ul>
                <p class="description" style="margin-top: 10px;">
                    <strong><?php _e('Usage:', 'voxel-toolkit'); ?></strong> <?php _e('Add a "Poll (VT)" field to any post type in Voxel Post Type settings.', 'voxel-toolkit'); ?>
                </p>
            </td>
        </tr>
        <?php
    }
}

/**
 * Poll Field Type Class
 */
class Voxel_Toolkit_Poll_Field_Type extends \Voxel\Post_Types\Fields\Base_Post_Field {

    protected $props = [
        'type' => 'poll-vt',
        'label' => 'Poll (VT)',
    ];

    public function get_models(): array {
        return [
            'label' => $this->get_label_model(),
            'key' => $this->get_key_model(),
            'description' => $this->get_description_model(),
            'required' => $this->get_required_model(),
            'css_class' => $this->get_css_class_model(),
        ];
    }

    public function sanitize($value) {
        if (!is_array($value)) {
            return [
                'options' => [],
                'allow_user_options' => false,
                'allow_multiple' => false,
                'user_submitted_options' => []
            ];
        }

        // Sanitize options
        if (isset($value['options']) && is_array($value['options'])) {
            foreach ($value['options'] as &$option) {
                if (isset($option['label'])) {
                    $option['label'] = sanitize_text_field($option['label']);
                }
                if (!isset($option['votes']) || !is_array($option['votes'])) {
                    $option['votes'] = [];
                }
            }
        }

        // Sanitize settings
        $value['allow_user_options'] = !empty($value['allow_user_options']);
        $value['allow_multiple'] = !empty($value['allow_multiple']);

        // Initialize user_submitted_options
        if (!isset($value['user_submitted_options']) || !is_array($value['user_submitted_options'])) {
            $value['user_submitted_options'] = [];
        }

        return $value;
    }

    public function validate($value): void {
        // Require at least 2 options with labels
        if (!is_array($value) || !isset($value['options']) || count($value['options']) < 2) {
            $this->validation->add_error('Please provide at least 2 poll options.');
            return;
        }

        $valid_options = 0;
        foreach ($value['options'] as $option) {
            if (!empty($option['label'])) {
                $valid_options++;
            }
        }

        if ($valid_options < 2) {
            $this->validation->add_error('Please provide at least 2 poll options with labels.');
        }
    }

    public function update($value): void {
        if ($this->is_empty($value)) {
            delete_post_meta($this->post->get_id(), $this->get_key());
        } else {
            update_post_meta($this->post->get_id(), $this->get_key(), wp_slash(wp_json_encode($value)));
        }
    }

    public function get_value_from_post() {
        $value = get_post_meta($this->post->get_id(), $this->get_key(), true);

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return [
                'options' => [],
                'allow_user_options' => false,
                'allow_multiple' => false,
                'user_submitted_options' => []
            ];
        }

        return $value;
    }
}
