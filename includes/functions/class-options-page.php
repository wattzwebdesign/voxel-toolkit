<?php
/**
 * Site Options
 *
 * Create global site options accessible via dynamic tags
 *
 * @package Voxel_Toolkit
 * @version 2024-11-17-23:30
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Options_Page {

    /**
     * Maximum number of fields allowed
     */
    const MAX_FIELDS = 30;

    /**
     * Available field types
     */
    private $field_types = array(
        'text' => 'Text',
        'textarea' => 'Textarea',
        'number' => 'Number',
        'url' => 'URL',
        'image' => 'Image',
    );

    /**
     * Constructor
     */
    public function __construct() {
        // Hook for saving options page data
        add_action('admin_post_voxel_toolkit_save_site_options', array($this, 'handle_options_save'));
    }

    /**
     * Render settings UI for field configuration
     */
    public static function render_settings($function_settings) {
        $fields = isset($function_settings['fields']) ? $function_settings['fields'] : array();
        $instance = new self();
        ?>
        <div class="voxel-toolkit-options-page-settings">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php _e('Configured Fields', 'voxel-toolkit'); ?></label>
                    </th>
                    <td>
                        <p class="description">
                            <?php printf(__('Configure up to %d custom fields that will be available site-wide. Access them using dynamic tags like @site(options.field_name)', 'voxel-toolkit'), self::MAX_FIELDS); ?>
                        </p>

                        <div class="voxel-toolkit-fields-list" style="margin-top: 15px;">
                            <?php if (!empty($fields)): ?>
                                <table class="widefat striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Field Name', 'voxel-toolkit'); ?></th>
                                            <th><?php _e('Label', 'voxel-toolkit'); ?></th>
                                            <th><?php _e('Type', 'voxel-toolkit'); ?></th>
                                            <th><?php _e('Default Value', 'voxel-toolkit'); ?></th>
                                            <th><?php _e('Actions', 'voxel-toolkit'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($fields as $field_name => $field_config): ?>
                                            <tr class="field-row" data-field="<?php echo esc_attr($field_name); ?>">
                                                <td><code><?php echo esc_html($field_name); ?></code></td>
                                                <td><?php echo esc_html($field_config['label']); ?></td>
                                                <td><?php echo esc_html($instance->field_types[$field_config['type']]); ?></td>
                                                <td><?php echo esc_html($field_config['default']); ?></td>
                                                <td>
                                                    <button type="button" class="button button-small vt-delete-field" data-field="<?php echo esc_attr($field_name); ?>">
                                                        <?php _e('Delete', 'voxel-toolkit'); ?>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p><em><?php _e('No fields configured yet.', 'voxel-toolkit'); ?></em></p>
                            <?php endif; ?>
                        </div>

                        <?php if (count($fields) < self::MAX_FIELDS): ?>
                            <div class="voxel-toolkit-add-field" style="margin-top: 20px; padding: 20px; border: 1px solid #ddd; background: #f9f9f9;">
                                <h3><?php _e('Add New Field', 'voxel-toolkit'); ?></h3>
                                <p class="description"><?php _e('Add fields below and click "Save Settings" at the bottom to apply changes.', 'voxel-toolkit'); ?></p>

                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="new_field_name"><?php _e('Field Name', 'voxel-toolkit'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text"
                                                   id="new_field_name"
                                                   name="voxel_toolkit_options[options_page][new_field][name]"
                                                   class="regular-text"
                                                   placeholder="<?php esc_attr_e('e.g., company_phone', 'voxel-toolkit'); ?>" />
                                            <p class="description"><?php _e('Lowercase letters, numbers, and underscores only. Used in dynamic tags: @site(options.field_name)', 'voxel-toolkit'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="new_field_label"><?php _e('Label', 'voxel-toolkit'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text"
                                                   id="new_field_label"
                                                   name="voxel_toolkit_options[options_page][new_field][label]"
                                                   class="regular-text"
                                                   placeholder="<?php esc_attr_e('e.g., Company Phone', 'voxel-toolkit'); ?>" />
                                            <p class="description"><?php _e('Human-readable label shown in admin', 'voxel-toolkit'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="new_field_type"><?php _e('Field Type', 'voxel-toolkit'); ?></label>
                                        </th>
                                        <td>
                                            <select id="new_field_type" name="voxel_toolkit_options[options_page][new_field][type]">
                                                <?php foreach ($instance->field_types as $type => $label): ?>
                                                    <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="new_field_default"><?php _e('Default Value', 'voxel-toolkit'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text"
                                                   id="new_field_default"
                                                   name="voxel_toolkit_options[options_page][new_field][default]"
                                                   class="regular-text" />
                                            <p class="description"><?php _e('Optional default value', 'voxel-toolkit'); ?></p>
                                        </td>
                                    </tr>
                                </table>

                                <input type="hidden" name="voxel_toolkit_options[options_page][action]" value="add_field" />
                            </div>
                        <?php else: ?>
                            <p style="margin-top: 20px; color: #d63638;">
                                <?php printf(__('Maximum field limit (%d) reached. Delete fields to add new ones.', 'voxel-toolkit'), self::MAX_FIELDS); ?>
                            </p>
                        <?php endif; ?>

                        <!-- Hidden field to store fields marked for deletion -->
                        <input type="hidden" name="voxel_toolkit_options[options_page][delete_fields]" id="delete_fields" value="" />

                        <!-- Store existing fields -->
                        <input type="hidden" name="voxel_toolkit_options[options_page][fields]" value="<?php echo esc_attr(json_encode($fields)); ?>" />
                    </td>
                </tr>
            </table>

            <!-- OPTIONS PAGE JS VERSION: 2024-11-17-23:30 -->
            <script>
            jQuery(document).ready(function($) {
                console.log('OPTIONS PAGE: New delete functionality loaded');
                var fieldsToDelete = [];

                // Delete field - mark for deletion visually
                $('.vt-delete-field').on('click', function(e) {
                    e.preventDefault();
                    var fieldName = $(this).data('field');
                    var $row = $(this).closest('tr');

                    if (confirm('<?php _e('This field will be deleted when you save settings. Continue?', 'voxel-toolkit'); ?>')) {
                        // Mark field for deletion
                        fieldsToDelete.push(fieldName);

                        // Visual feedback
                        $row.addClass('vt-marked-for-deletion');
                        $row.css({
                            'opacity': '0.5',
                            'text-decoration': 'line-through',
                            'background-color': '#ffebee'
                        });

                        // Change button to "Undo"
                        $(this).text('<?php _e('Undo', 'voxel-toolkit'); ?>').removeClass('vt-delete-field').addClass('vt-undo-delete');
                    }
                });

                // Undo delete
                $(document).on('click', '.vt-undo-delete', function(e) {
                    e.preventDefault();
                    var fieldName = $(this).data('field');
                    var $row = $(this).closest('tr');

                    // Remove from delete list
                    fieldsToDelete = fieldsToDelete.filter(function(name) {
                        return name !== fieldName;
                    });

                    // Remove visual feedback
                    $row.removeClass('vt-marked-for-deletion');
                    $row.css({
                        'opacity': '1',
                        'text-decoration': 'none',
                        'background-color': ''
                    });

                    // Change button back to "Delete"
                    $(this).text('<?php _e('Delete', 'voxel-toolkit'); ?>').removeClass('vt-undo-delete').addClass('vt-delete-field');
                });

                // Before form submit, add all fields marked for deletion to hidden input
                $('form').on('submit', function() {
                    $('#delete_fields').val(fieldsToDelete.join(','));
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Render the Site Options page in admin
     */
    public function render_options_page() {
        $settings = Voxel_Toolkit_Settings::instance();
        $config = $settings->get_function_settings('options_page');
        $fields = isset($config['fields']) ? $config['fields'] : array();

        if (empty($fields)) {
            ?>
            <div class="wrap">
                <h1><?php _e('Site Options', 'voxel-toolkit'); ?></h1>
                <div class="notice notice-info">
                    <p><?php _e('No fields configured yet. Please configure fields in Settings > Options Page first.', 'voxel-toolkit'); ?></p>
                </div>
            </div>
            <?php
            return;
        }

        // Handle form submission
        if (isset($_POST['voxel_toolkit_options_nonce']) && wp_verify_nonce($_POST['voxel_toolkit_options_nonce'], 'voxel_toolkit_save_options')) {
            $this->handle_options_save();
        }

        ?>
        <div class="wrap">
            <h1><?php _e('Site Options', 'voxel-toolkit'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('voxel_toolkit_save_options', 'voxel_toolkit_options_nonce'); ?>

                <table class="form-table">
                    <?php foreach ($fields as $field_name => $field_config): ?>
                        <tr>
                            <th scope="row">
                                <label for="option_<?php echo esc_attr($field_name); ?>">
                                    <?php echo esc_html($field_config['label']); ?>
                                </label>
                            </th>
                            <td>
                                <?php $this->render_field($field_name, $field_config); ?>
                                <p class="description">
                                    <?php printf(__('Dynamic tag: %s', 'voxel-toolkit'), '<code>@site(options.' . esc_html($field_name) . ')</code>'); ?>
                                </p>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button(__('Save Options', 'voxel-toolkit')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render a single field based on its type
     */
    private function render_field($field_name, $field_config) {
        $option_name = 'voxel_options_' . $field_name;
        $value = get_option($option_name, $field_config['default']);
        $type = $field_config['type'];

        switch ($type) {
            case 'text':
                ?>
                <input type="text"
                       id="option_<?php echo esc_attr($field_name); ?>"
                       name="voxel_options[<?php echo esc_attr($field_name); ?>]"
                       value="<?php echo esc_attr($value); ?>"
                       class="regular-text" />
                <?php
                break;

            case 'textarea':
                ?>
                <textarea id="option_<?php echo esc_attr($field_name); ?>"
                          name="voxel_options[<?php echo esc_attr($field_name); ?>]"
                          rows="5"
                          class="large-text"><?php echo esc_textarea($value); ?></textarea>
                <?php
                break;

            case 'number':
                ?>
                <input type="number"
                       id="option_<?php echo esc_attr($field_name); ?>"
                       name="voxel_options[<?php echo esc_attr($field_name); ?>]"
                       value="<?php echo esc_attr($value); ?>"
                       class="regular-text" />
                <?php
                break;

            case 'url':
                ?>
                <input type="url"
                       id="option_<?php echo esc_attr($field_name); ?>"
                       name="voxel_options[<?php echo esc_attr($field_name); ?>]"
                       value="<?php echo esc_url($value); ?>"
                       class="regular-text"
                       placeholder="https://" />
                <?php
                break;

            case 'image':
                $image_id = intval($value);
                $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
                ?>
                <div class="voxel-toolkit-image-field">
                    <input type="hidden"
                           id="option_<?php echo esc_attr($field_name); ?>"
                           name="voxel_options[<?php echo esc_attr($field_name); ?>]"
                           value="<?php echo esc_attr($image_id); ?>"
                           class="voxel-toolkit-image-id" />

                    <div class="voxel-toolkit-image-preview" style="margin-bottom: 10px;">
                        <?php if ($image_url): ?>
                            <img src="<?php echo esc_url($image_url); ?>" style="max-width: 150px; height: auto; display: block;" />
                        <?php endif; ?>
                    </div>

                    <button type="button" class="button voxel-toolkit-select-image">
                        <?php echo $image_id ? __('Change Image', 'voxel-toolkit') : __('Select Image', 'voxel-toolkit'); ?>
                    </button>

                    <?php if ($image_id): ?>
                        <button type="button" class="button voxel-toolkit-remove-image">
                            <?php _e('Remove', 'voxel-toolkit'); ?>
                        </button>
                    <?php endif; ?>
                </div>
                <?php
                break;
        }
    }

    /**
     * Handle saving of option values
     */
    public function handle_options_save() {
        // Verify nonce
        if (!isset($_POST['voxel_toolkit_options_nonce']) || !wp_verify_nonce($_POST['voxel_toolkit_options_nonce'], 'voxel_toolkit_save_options')) {
            wp_die(__('Security check failed', 'voxel-toolkit'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', 'voxel-toolkit'));
        }

        // Get configured fields
        $settings = Voxel_Toolkit_Settings::instance();
        $config = $settings->get_function_settings('options_page');
        $fields = isset($config['fields']) ? $config['fields'] : array();

        if (empty($fields)) {
            return;
        }

        // Process each field value
        if (isset($_POST['voxel_options'])) {
            foreach ($fields as $field_name => $field_config) {
                $option_name = 'voxel_options_' . $field_name;
                $value = isset($_POST['voxel_options'][$field_name]) ? $_POST['voxel_options'][$field_name] : '';

                // Sanitize based on field type
                $sanitized_value = $this->sanitize_field_value($value, $field_config['type']);

                // Update option with autoload enabled
                update_option($option_name, $sanitized_value, true);
            }

            // Redirect with success message
            add_settings_error('voxel_toolkit_options', 'options_saved', __('Options saved successfully', 'voxel-toolkit'), 'success');
            set_transient('voxel_toolkit_options_saved', true, 30);
        }
    }

    /**
     * Sanitize field value based on type
     */
    private function sanitize_field_value($value, $type) {
        switch ($type) {
            case 'text':
                return sanitize_text_field($value);

            case 'textarea':
                return sanitize_textarea_field($value);

            case 'number':
                return intval($value);

            case 'url':
                return esc_url_raw($value);

            case 'image':
                return intval($value);

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Sanitize field name
     */
    public static function sanitize_field_name($name) {
        // Convert to lowercase
        $name = strtolower($name);

        // Replace spaces and dashes with underscores
        $name = str_replace(array(' ', '-'), '_', $name);

        // Remove any characters that aren't alphanumeric or underscore
        $name = preg_replace('/[^a-z0-9_]/', '', $name);

        // Remove leading/trailing underscores
        $name = trim($name, '_');

        return $name;
    }

    /**
     * Validate field type
     */
    public static function validate_field_type($type) {
        $allowed_types = array('text', 'textarea', 'number', 'url', 'image');
        return in_array($type, $allowed_types) ? $type : 'text';
    }

    /**
     * Get configured fields
     */
    public static function get_configured_fields() {
        $settings = Voxel_Toolkit_Settings::instance();
        $config = $settings->get_function_settings('options_page');
        return isset($config['fields']) ? $config['fields'] : array();
    }
}
