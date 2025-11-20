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
        error_log('Voxel Toolkit: Poll Field __construct called');
        $this->init_hooks();
        error_log('Voxel Toolkit: Poll Field initialized, hooks registered');
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        error_log('Voxel Toolkit: Attempting to add voxel/field-types filter');
        // Register the custom field type - use priority 100 to ensure it runs after Voxel loads
        add_filter('voxel/field-types', array($this, 'register_field_type'), 100);
        error_log('Voxel Toolkit: voxel/field-types filter added');

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
        error_log('Voxel Toolkit: register_field_type called! Fields array keys: ' . implode(', ', array_keys($fields)));

        if (!class_exists('\Voxel\Post_Types\Fields\Base_Post_Field')) {
            error_log('Voxel Toolkit: Base_Post_Field class not found - CANNOT REGISTER');
            return $fields;
        }

        error_log('Voxel Toolkit: Base_Post_Field class exists! Registering poll-vt field type');
        $fields['poll-vt'] = '\Voxel_Toolkit_Poll_Field_Type';
        error_log('Voxel Toolkit: poll-vt added to fields array. New keys: ' . implode(', ', array_keys($fields)));
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
                                    title="Delete option"
                                >
                                    <svg width="16" height="16" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                                        <path d="m23.794 60.5h16.4121a7.61968 7.61968 0 0 0 7.59961-7.1709l1.99317-34.25586a1.50882 1.50882 0 0 0 -1.49707-1.58691h-32.60357a1.49982 1.49982 0 0 0 -1.49707 1.58691l1.99317 34.25586a7.61968 7.61968 0 0 0 7.59966 7.1709zm22.918-40.01367-1.90048 32.66894a4.61773 4.61773 0 0 1 -4.60547 4.34473h-16.41205a4.61773 4.61773 0 0 1 -4.60547-4.34473l-1.90044-32.66894z"/>
                                        <path d="m35.751 3.5h-7.502a5.25762 5.25762 0 0 0 -5.252 5.251v2.25195h-11.00384a1.50017 1.50017 0 0 0 .00007 3h40.01361a1.5 1.5 0 0 0 0-3h-11.00391v-2.25195a5.25762 5.25762 0 0 0 -5.25193-5.251zm2.252 7.50293h-12.00593v-2.25193a2.25372 2.25372 0 0 1 2.25193-2.251h7.502a2.25372 2.25372 0 0 1 2.252 2.251z"/>
                                        <path d="m27.169 51.60742a1.50127 1.50127 0 0 0 1.501-1.52929l-.38672-19.918a1.54491 1.54491 0 0 0 -1.52929-1.47071 1.50131 1.50131 0 0 0 -1.47071 1.5293l.38672 19.918a1.50048 1.50048 0 0 0 1.499 1.4707z"/>
                                        <path d="m36.80078 51.60742a1.50159 1.50159 0 0 0 1.5293-1.4707l.38672-19.918a1.50029 1.50029 0 1 0 -3-.05859l-.38672 19.918a1.50129 1.50129 0 0 0 1.4707 1.52929z"/>
                                    </svg>
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
                            <label class="ts-form-group switcher-label" style="display: flex; align-items: center; margin-bottom: 10px;">
                                <div class="switch-slider" style="margin-right: 10px;">
                                    <div class="onoffswitch">
                                        <input
                                            v-model="field.value.allow_user_options"
                                            type="checkbox"
                                            class="onoffswitch-checkbox"
                                            :id="'poll-user-options-' + field.id"
                                        >
                                        <label class="onoffswitch-label" :for="'poll-user-options-' + field.id"></label>
                                    </div>
                                </div>
                                <span>Allow users to add their own options</span>
                            </label>

                            <label class="ts-form-group switcher-label" style="display: flex; align-items: center; margin-bottom: 10px;">
                                <div class="switch-slider" style="margin-right: 10px;">
                                    <div class="onoffswitch">
                                        <input
                                            v-model="field.value.allow_multiple"
                                            type="checkbox"
                                            class="onoffswitch-checkbox"
                                            :id="'poll-multiple-' + field.id"
                                        >
                                        <label class="onoffswitch-label" :for="'poll-multiple-' + field.id"></label>
                                    </div>
                                </div>
                                <span>Allow users to choose multiple options</span>
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
                    },
                    validate() {
                        // Voxel requires this method for form validation
                        // Check if field is required
                        if (this.field.required) {
                            // Count valid options (non-empty labels)
                            const validOptions = this.field.value.options.filter(opt => opt.label && opt.label.trim()).length;
                            return validOptions >= 2;
                        }
                        return true;
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
            flex-direction: row;
        }

        /* Frontend poll display styles */
        .vt-poll-display {
            margin: 20px 0;
        }

        .vt-poll-options-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .vt-poll-option {
            display: block;
            position: relative;
            border: 2px solid #ccd0d5;
            border-radius: 8px;
            padding: 0;
            transition: all 0.2s ease;
            cursor: pointer;
            overflow: hidden;
            background: #fff;
        }

        .vt-poll-option:hover {
            border-color: #2271b1;
        }

        .vt-poll-option.voted {
            border-color: #2271b1;
        }

        .vt-poll-option-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            position: relative;
            z-index: 2;
            min-height: 48px;
        }

        .vt-poll-option-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }

        .vt-poll-option-text-wrap {
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex: 1;
            min-width: 0;
        }

        .vt-poll-input {
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid #d0d0d0;
            background: #fff;
            transition: all 0.2s ease;
            position: relative;
            flex-shrink: 0;
        }

        .vt-poll-input[type="radio"] {
            border-radius: 50%;
        }

        .vt-poll-input[type="checkbox"] {
            border-radius: 4px;
        }

        .vt-poll-input:hover {
            border-color: #2271b1;
        }

        .vt-poll-input:checked {
            background-color: #2271b1;
            border-color: #2271b1;
        }

        .vt-poll-input[type="radio"]:checked::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: white;
        }

        .vt-poll-input[type="checkbox"]:checked::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 12px;
            height: 12px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12"><path fill="white" d="M7.8,21.425A2.542,2.542,0,0,1,6,20.679L.439,15.121,2.561,13,7.8,18.239,21.439,4.6l2.122,2.121L9.6,20.679A2.542,2.542,0,0,1,7.8,21.425Z"/></svg>');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        .vt-poll-input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .vt-poll-option.disabled {
            opacity: 0.6;
        }

        .vt-poll-option-text {
            font-weight: 400;
            font-size: 15px;
            line-height: 1.4;
        }

        .vt-poll-option.voted .vt-poll-option-text {
            font-weight: 500;
        }

        .vt-poll-user-badge {
            font-size: 13px;
            color: #65676b;
        }

        .vt-poll-progress-bar {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background: transparent;
            z-index: 0;
            pointer-events: none;
            border-radius: 6px;
            overflow: hidden;
        }

        .vt-poll-progress-fill {
            width: 100%;
            height: 100%;
            background: #F2F4F4;
            transition: all 0.3s ease;
        }

        .vt-poll-option.voted .vt-poll-progress-fill {
            background: #E8EAEB;
        }

        .vt-poll-percentage {
            color: #050505;
            font-size: 15px;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .vt-poll-add-option {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .vt-poll-new-option {
            flex: 1;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }

        .vt-poll-submit-option {
            padding: 10px 20px;
            background: #2271b1;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }

        .vt-poll-submit-option:hover {
            background: #135e96;
        }

        .vt-poll-total {
            margin-top: 15px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        </style>

        <script>
        // Frontend poll voting functionality
        document.addEventListener('DOMContentLoaded', function() {
            const polls = document.querySelectorAll('.vt-poll-display');

            polls.forEach(poll => {
                const postId = poll.dataset.postId;
                const fieldKey = poll.dataset.fieldKey;
                const allowMultiple = poll.dataset.allowMultiple === '1';

                // Handle vote clicks
                poll.querySelectorAll('.vt-poll-input').forEach(input => {
                    input.addEventListener('change', function() {
                        const optionIndex = this.closest('.vt-poll-option').dataset.optionIndex;
                        handleVote(postId, fieldKey, optionIndex, poll);
                    });
                });

                // Handle add option
                const submitBtn = poll.querySelector('.vt-poll-submit-option');
                if (submitBtn) {
                    submitBtn.addEventListener('click', function() {
                        const input = poll.querySelector('.vt-poll-new-option');
                        const optionLabel = input.value.trim();

                        if (optionLabel) {
                            handleAddOption(postId, fieldKey, optionLabel, poll, input);
                        }
                    });
                }
            });

            function handleVote(postId, fieldKey, optionIndex, pollEl) {
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'vt_poll_vote',
                        post_id: postId,
                        field_key: fieldKey,
                        option_index: optionIndex,
                        nonce: '<?php echo wp_create_nonce('vt_poll_vote'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload the page to show updated results
                        location.reload();
                    } else {
                        alert(data.data.message || 'Error recording vote');
                    }
                })
                .catch(error => {
                    console.error('Vote error:', error);
                    alert('Error recording vote');
                });
            }

            function handleAddOption(postId, fieldKey, optionLabel, pollEl, inputEl) {
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'vt_poll_add_option',
                        post_id: postId,
                        field_key: fieldKey,
                        option_label: optionLabel,
                        nonce: '<?php echo wp_create_nonce('vt_poll_add_option'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        inputEl.value = '';
                        // Reload the page to show the new option
                        location.reload();
                    } else {
                        alert(data.data.message || 'Error adding option');
                    }
                })
                .catch(error => {
                    console.error('Add option error:', error);
                    alert('Error adding option');
                });
            }
        });
        </script>
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

        error_log('VT Poll Vote: post_id=' . $post_id . ', field_key=' . $field_key . ', option_index=' . $option_index);

        if (!$post_id || !$field_key) {
            wp_send_json_error(['message' => 'Invalid request']);
        }

        // Get current poll data
        $poll_data = get_post_meta($post_id, $field_key, true);

        // Voxel stores custom field data as JSON strings
        if (is_string($poll_data)) {
            $poll_data = json_decode($poll_data, true);
        }

        error_log('VT Poll Vote: poll_data=' . print_r($poll_data, true));

        if (!is_array($poll_data)) {
            wp_send_json_error(['message' => 'Invalid poll data']);
        }

        // Initialize user_submitted_options if not exists
        if (!isset($poll_data['user_submitted_options'])) {
            $poll_data['user_submitted_options'] = [];
        }

        // Combine admin options and user-submitted options to find the correct option
        $all_options = array_merge($poll_data['options'], $poll_data['user_submitted_options']);
        error_log('VT Poll Vote: all_options count=' . count($all_options) . ', checking index=' . $option_index);

        if (!isset($all_options[$option_index])) {
            error_log('VT Poll Vote: Option not found at index ' . $option_index);
            wp_send_json_error(['message' => 'Invalid option']);
        }

        // Determine if this is an admin option or user-submitted option
        $is_admin_option = $option_index < count($poll_data['options']);

        if ($is_admin_option) {
            $target_array = 'options';
            $target_index = $option_index;
        } else {
            $target_array = 'user_submitted_options';
            $target_index = $option_index - count($poll_data['options']);
        }

        // Initialize votes array if not exists
        if (!isset($poll_data[$target_array][$target_index]['votes'])) {
            $poll_data[$target_array][$target_index]['votes'] = [];
        }

        // Check if user already voted (for single-choice polls)
        if (!$poll_data['allow_multiple']) {
            // Remove votes from all admin options
            foreach ($poll_data['options'] as $idx => &$option) {
                if (isset($option['votes']) && in_array($user_id, $option['votes'])) {
                    $option['votes'] = array_diff($option['votes'], [$user_id]);
                    $option['votes'] = array_values($option['votes']);
                }
            }
            // Remove votes from all user-submitted options
            if (isset($poll_data['user_submitted_options'])) {
                foreach ($poll_data['user_submitted_options'] as $idx => &$option) {
                    if (isset($option['votes']) && in_array($user_id, $option['votes'])) {
                        $option['votes'] = array_diff($option['votes'], [$user_id]);
                        $option['votes'] = array_values($option['votes']);
                    }
                }
            }
        }

        // Toggle vote
        if (in_array($user_id, $poll_data[$target_array][$target_index]['votes'])) {
            // Remove vote
            $poll_data[$target_array][$target_index]['votes'] = array_diff(
                $poll_data[$target_array][$target_index]['votes'],
                [$user_id]
            );
        } else {
            // Add vote
            $poll_data[$target_array][$target_index]['votes'][] = $user_id;
        }

        // Re-index array
        $poll_data[$target_array][$target_index]['votes'] = array_values($poll_data[$target_array][$target_index]['votes']);

        // Update post meta (Voxel expects JSON)
        update_post_meta($post_id, $field_key, json_encode($poll_data));

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

        error_log('VT Poll Add Option: post_id=' . $post_id . ', field_key=' . $field_key . ', label=' . $option_label);

        if (!$post_id || !$field_key || !$option_label) {
            wp_send_json_error(['message' => 'Invalid request']);
        }

        // Get current poll data
        $poll_data = get_post_meta($post_id, $field_key, true);

        // Voxel stores custom field data as JSON strings
        if (is_string($poll_data)) {
            $poll_data = json_decode($poll_data, true);
        }

        error_log('VT Poll Add Option: poll_data=' . print_r($poll_data, true));

        if (!is_array($poll_data) || empty($poll_data['allow_user_options'])) {
            error_log('VT Poll Add Option: User options not allowed. allow_user_options=' . (isset($poll_data['allow_user_options']) ? var_export($poll_data['allow_user_options'], true) : 'not set'));
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

        // Update post meta (Voxel expects JSON)
        update_post_meta($post_id, $field_key, json_encode($poll_data));

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

    public function get_frontend_markup(): string {
        $value = $this->get_value();
        if (empty($value) || empty($value['options'])) {
            return '';
        }

        $post_id = $this->post->get_id();
        $field_key = $this->get_key();
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

        ob_start();
        ?>
        <div class="vt-poll-display" data-post-id="<?php echo esc_attr($post_id); ?>" data-field-key="<?php echo esc_attr($field_key); ?>" data-allow-multiple="<?php echo $allow_multiple ? '1' : '0'; ?>">
            <div class="vt-poll-options-list">
                <?php foreach ($all_options as $index => $option): ?>
                    <?php
                    $vote_count = isset($option['votes']) && is_array($option['votes']) ? count($option['votes']) : 0;
                    $percentage = $total_votes > 0 ? round(($vote_count / $total_votes) * 100) : 0;
                    $has_voted = $user_id && isset($option['votes']) && in_array($user_id, $option['votes']);
                    $is_user_submitted = isset($option['submitted_by']);
                    ?>
                    <div class="vt-poll-option <?php echo $has_voted ? 'voted' : ''; ?>" data-option-index="<?php echo esc_attr($index); ?>">
                        <div class="vt-poll-option-header">
                            <label class="vt-poll-option-label">
                                <input
                                    type="<?php echo $allow_multiple ? 'checkbox' : 'radio'; ?>"
                                    name="poll-<?php echo esc_attr($field_key); ?>"
                                    value="<?php echo esc_attr($index); ?>"
                                    <?php checked($has_voted); ?>
                                    class="vt-poll-input"
                                >
                                <span class="vt-poll-option-text">
                                    <?php echo esc_html($option['label']); ?>
                                    <?php if ($is_user_submitted): ?>
                                        <span class="vt-poll-user-badge" title="User submitted option">👤</span>
                                    <?php endif; ?>
                                </span>
                            </label>
                            <span class="vt-poll-vote-count"><?php echo $vote_count; ?> <?php echo $vote_count === 1 ? 'vote' : 'votes'; ?></span>
                        </div>
                        <div class="vt-poll-progress-bar">
                            <div class="vt-poll-progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <div class="vt-poll-percentage"><?php echo $percentage; ?>%</div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($allow_user_options && $user_id): ?>
                <div class="vt-poll-add-option">
                    <input type="text" class="vt-poll-new-option" placeholder="Add your own option..." />
                    <button type="button" class="vt-poll-submit-option">Add Option</button>
                </div>
            <?php endif; ?>

            <div class="vt-poll-total">Total votes: <?php echo $total_votes; ?></div>
        </div>
        <?php
        return ob_get_clean();
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
