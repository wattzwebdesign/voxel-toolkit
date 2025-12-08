<?php
/**
 * Auto Reply Field (VT)
 *
 * Custom field type for automatic message replies.
 * When a listing/profile receives a message, it automatically
 * sends the configured reply message.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Auto_Reply_Field {

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
        // Listen for incoming messages
        add_action('voxel/app-events/messages/user:received_message', array($this, 'handle_message_received'), 10, 1);

        // Add frontend Vue template for create/edit form
        add_action('wp_head', array($this, 'add_frontend_template'));
    }

    /**
     * Handle incoming message and send auto-reply if configured
     *
     * @param object $event The message event object
     */
    public function handle_message_received($event) {
        // The event has direct properties: $event->message, $event->sender, $event->receiver
        $receiver = $event->receiver;
        $sender = $event->sender;

        // Only handle if receiver is a post (listing or profile)
        if (!($receiver instanceof \Voxel\Post)) {
            return;
        }

        // Get auto-reply message from the post
        $auto_reply_message = $this->get_auto_reply_for_post($receiver);

        // If no auto-reply configured, do nothing
        if (empty($auto_reply_message)) {
            return;
        }

        // Process dynamic tags in the message
        $processed_message = $this->process_dynamic_tags($auto_reply_message, $event);

        // Send the auto-reply
        $this->send_auto_reply($receiver, $sender, $processed_message);
    }

    /**
     * Get auto-reply message from a post
     *
     * @param \Voxel\Post $post The post to get auto-reply from
     * @return string The auto-reply message or empty string
     */
    private function get_auto_reply_for_post($post) {
        $post_type = $post->post_type;

        if (!$post_type) {
            return '';
        }

        // Look for any field of type 'auto-reply-vt' on this post
        foreach ($post_type->get_fields() as $field) {
            if ($field->get_type() === 'auto-reply-vt') {
                // Get the value for this specific post
                $field->set_post($post);
                $value = $field->get_value();

                if (!empty($value)) {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * Process dynamic tags in the message
     *
     * @param string $message The message with potential dynamic tags
     * @param object $event The message event
     * @return string The processed message
     */
    private function process_dynamic_tags($message, $event) {
        $receiver = $event->receiver;
        $sender = $event->sender;

        // Replace @listing() tags
        if ($receiver instanceof \Voxel\Post) {
            // @listing(title)
            $message = str_replace('@listing(title)', $receiver->get_title(), $message);

            // @listing(field:key) - replace any field references
            if (preg_match_all('/@listing\(field:([^)]+)\)/', $message, $matches)) {
                foreach ($matches[1] as $index => $field_key) {
                    $field = $receiver->get_field($field_key);
                    $field_value = $field ? $field->get_value() : '';

                    // Convert to string if array
                    if (is_array($field_value)) {
                        $field_value = implode(', ', $field_value);
                    }

                    $message = str_replace($matches[0][$index], $field_value, $message);
                }
            }
        }

        // Replace @sender() tags
        if ($sender) {
            // @sender(display_name)
            $sender_name = method_exists($sender, 'get_display_name') ? $sender->get_display_name() : '';
            $message = str_replace('@sender(display_name)', $sender_name, $message);

            // @sender(name) - alias
            $message = str_replace('@sender(name)', $sender_name, $message);
        }

        return $message;
    }

    /**
     * Send the auto-reply message
     *
     * @param \Voxel\Post $sender_post The post sending the reply
     * @param mixed $receiver The original message sender (now the receiver)
     * @param string $message The message content
     */
    private function send_auto_reply($sender_post, $receiver, $message) {
        if (!class_exists('\Voxel\Modules\Direct_Messages\Message')) {
            return;
        }

        try {
            // Create the auto-reply message
            $new_message = \Voxel\Modules\Direct_Messages\Message::create([
                'sender_type' => 'post',
                'sender_id' => $sender_post->get_id(),
                'receiver_type' => $receiver->get_object_type(),
                'receiver_id' => $receiver->get_id(),
                'content' => $message,
                'details' => null,
                'seen' => 0,
            ]);

            // Update the chat record so it appears in conversation
            $new_message->update_chat();

            // Signal inbox activity to the receiver so they get real-time notification
            $receiver_user = null;
            if ($receiver instanceof \Voxel\Post) {
                $receiver_user = $receiver->get_author();
            } elseif ($receiver instanceof \Voxel\User) {
                $receiver_user = $receiver;
            }

            if ($receiver_user && method_exists($receiver_user, 'set_inbox_activity')) {
                $receiver_user->set_inbox_activity(true);
            }

        } catch (\Exception $e) {
            error_log('Voxel Toolkit Auto-Reply: Error sending message - ' . $e->getMessage());
        }
    }

    /**
     * Add frontend Vue template for create/edit post form
     */
    public function add_frontend_template() {
        ?>
        <script>
        document.addEventListener('voxel/create-post/init', e => {
            const { app, config, el } = e.detail;

            app.component('field-auto-reply-vt', {
                template: `
                    <div class="ts-form-group vt-auto-reply-field">
                        <label>
                            {{ field.label }}
                            <slot name="errors"></slot>
                        </label>
                        <p v-if="field.description" class="ts-description" style="margin-bottom: 10px; color: #666; font-size: 13px;">
                            {{ field.description }}
                        </p>
                        <textarea
                            v-model="field.value"
                            :placeholder="field.props.placeholder || 'Enter your auto-reply message...'"
                            class="ts-filter"
                            rows="4"
                            style="width: 100%; resize: vertical;"
                        ></textarea>
                    </div>
                `,
                props: {
                    field: Object
                },
                mounted() {
                    // Initialize with empty string if no value
                    if (this.field.value === null || this.field.value === undefined) {
                        this.field.value = '';
                    }
                },
                methods: {
                    validate() {
                        // Not required by default - empty means disabled
                        return true;
                    }
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render settings section for admin
     */
    public static function render_settings() {
        ?>
        <tr>
            <th scope="row">
                <label><?php _e('Auto Reply (VT)', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <p class="description">
                    <?php _e('Adds a custom "Auto Reply (VT)" field type for automatic message responses:', 'voxel-toolkit'); ?>
                </p>
                <ul style="list-style: disc; margin-left: 20px; margin-top: 10px;">
                    <li><?php _e('Automatic replies when listings receive messages', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Works with Profile post type for user-to-user messaging', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Supports dynamic tags: @listing(title), @sender(name)', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Respects Voxel\'s 15-minute throttle (one reply per conversation window)', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Leave empty to disable auto-reply', 'voxel-toolkit'); ?></li>
                </ul>
                <p class="description" style="margin-top: 10px;">
                    <strong><?php _e('Usage:', 'voxel-toolkit'); ?></strong> <?php _e('Add an "Auto Reply (VT)" field to any post type in Voxel Post Type settings.', 'voxel-toolkit'); ?>
                </p>
            </td>
        </tr>
        <?php
    }
}

// Only define the field type class if the parent class exists (Voxel theme is loaded)
if (!class_exists('\Voxel\Post_Types\Fields\Base_Post_Field')) {
    return;
}

/**
 * Auto Reply Field Type Class
 */
class Voxel_Toolkit_Auto_Reply_Field_Type extends \Voxel\Post_Types\Fields\Base_Post_Field {

    protected $supported_conditions = ['text'];

    protected $props = [
        'type' => 'auto-reply-vt',
        'label' => 'Auto Reply (VT)',
        'placeholder' => '',
        'default' => null,
    ];

    /**
     * Check if this field type is supported (enabled)
     */
    public function is_supported(): bool {
        $settings = Voxel_Toolkit_Settings::instance();
        return $settings->is_function_enabled('post_field_auto_reply_field');
    }

    /**
     * Get field configuration models for admin
     */
    public function get_models(): array {
        return [
            'label' => $this->get_label_model(),
            'key' => $this->get_key_model(),
            'placeholder' => $this->get_placeholder_model(),
            'description' => $this->get_description_model(),
            'required' => $this->get_required_model(),
            'css_class' => $this->get_css_class_model(),
            'default' => $this->get_default_value_model(),
            'hidden' => $this->get_hidden_model(),
        ];
    }

    /**
     * Sanitize the field value
     */
    public function sanitize($value) {
        return sanitize_textarea_field(trim($value));
    }

    /**
     * Validate the field value
     */
    public function validate($value): void {
        // No validation needed - empty is allowed (means auto-reply is disabled)
    }

    /**
     * Update/save the field value
     */
    public function update($value): void {
        if ($this->is_empty($value)) {
            delete_post_meta($this->post->get_id(), $this->get_key());
        } else {
            update_post_meta($this->post->get_id(), $this->get_key(), wp_slash($value));
        }
    }

    /**
     * Get value from post meta
     */
    public function get_value_from_post() {
        return get_post_meta($this->post->get_id(), $this->get_key(), true);
    }

    /**
     * Get editing value for create/edit form
     */
    protected function editing_value() {
        if ($this->is_new_post()) {
            return $this->get_default_value();
        } else {
            return $this->get_value();
        }
    }

    /**
     * Get default value
     */
    protected function get_default_value() {
        return $this->render_default_value($this->get_prop('default'));
    }

    /**
     * Get frontend props for Vue component
     */
    protected function frontend_props() {
        return [
            'placeholder' => $this->get_model_value('placeholder') ?: $this->props['label'],
        ];
    }

    /**
     * Get frontend markup (not displayed publicly)
     */
    public function get_frontend_markup(): string {
        // This field is internal only - no public display
        return '';
    }

    /**
     * Export field value as dynamic data for @post(field_key) tags
     *
     * @return \Voxel\Dynamic_Data\Data_Types\Base_Data_Type
     */
    public function dynamic_data() {
        return \Voxel\Dynamic_Data\Tag::String($this->get_label())->render(function() {
            return $this->get_value();
        });
    }
}
