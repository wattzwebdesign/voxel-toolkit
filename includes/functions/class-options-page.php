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
     * Singleton instance
     */
    private static $instance = null;

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
     * Get singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Hook for saving options page data
        add_action('admin_post_voxel_toolkit_save_site_options', array($this, 'handle_options_save'));

        // Register Elementor widget
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));

        // Handle frontend form submissions immediately - we're already past init
        $this->handle_frontend_form_submission();
    }

    /**
     * Handle frontend form submission
     */
    public function handle_frontend_form_submission() {
        if (isset($_POST['vt_site_options_nonce']) && wp_verify_nonce($_POST['vt_site_options_nonce'], 'vt_save_site_options')) {

            // Get configured fields
            $settings = Voxel_Toolkit_Settings::instance();
            $config = $settings->get_function_settings('options_page');
            $fields = isset($config['fields']) ? $config['fields'] : array();

            if (!empty($fields) && isset($_POST['vt_options'])) {
                foreach ($fields as $field_name => $field_config) {
                    $option_name = 'voxel_options_' . $field_name;
                    $value = isset($_POST['vt_options'][$field_name]) ? $_POST['vt_options'][$field_name] : '';

                    // Sanitize based on field type
                    $sanitized_value = $this->sanitize_field_value($value, $field_config['type']);

                    // Update option
                    update_option($option_name, $sanitized_value, true);
                }

                // Get clean URL without query params
                $clean_url = strtok($_SERVER['REQUEST_URI'], '?');

                // Redirect to same page with success parameter
                wp_safe_redirect(add_query_arg('vt_saved', '1', $clean_url));
                exit;
            }
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
     * Register Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-site-options-form.php';
        $widgets_manager->register(new \Voxel_Toolkit_Site_Options_Form());
    }

    /**
     * Render settings UI for field configuration
     */
    public static function render_settings($function_settings) {
        $fields = isset($function_settings['fields']) ? $function_settings['fields'] : array();
        ?>
        <div class="voxel-toolkit-options-page-settings">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php _e('Field Configuration', 'voxel-toolkit'); ?></label>
                    </th>
                    <td>
                        <p class="description">
                            <?php _e('Configure custom fields on the dedicated configuration page.', 'voxel-toolkit'); ?>
                        </p>
                        <p style="margin-top: 15px;">
                            <a href="<?php echo admin_url('admin.php?page=voxel-toolkit-configure-fields'); ?>" class="button button-primary">
                                <?php _e('Configure Fields', 'voxel-toolkit'); ?>
                            </a>
                        </p>

                        <?php if (!empty($fields)): ?>
                            <div class="voxel-toolkit-fields-list" style="margin-top: 20px;">
                                <p><strong><?php echo count($fields); ?></strong> <?php _e('field(s) configured', 'voxel-toolkit'); ?></p>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * OLD SETTINGS UI - REMOVED
     * Now using dedicated configure fields page
     */
    private static function old_render_settings_removed() {
        /* OLD CODE REMOVED - See configure fields page instead
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
                                <h3><?php _e('Add New Fields', 'voxel-toolkit'); ?></h3>
                                <p class="description"><?php _e('Add multiple fields below, then click "Save Settings" at the bottom to apply all changes.', 'voxel-toolkit'); ?></p>

                                <!-- Pending fields list -->
                                <div id="pending-fields-list" style="margin-bottom: 20px; display: none;">
                                    <h4><?php _e('Fields to be added:', 'voxel-toolkit'); ?></h4>
                                    <table class="widefat striped">
                                        <thead>
                                            <tr>
                                                <th><?php _e('Field Name', 'voxel-toolkit'); ?></th>
                                                <th><?php _e('Label', 'voxel-toolkit'); ?></th>
                                                <th><?php _e('Type', 'voxel-toolkit'); ?></th>
                                                <th><?php _e('Default', 'voxel-toolkit'); ?></th>
                                                <th><?php _e('Actions', 'voxel-toolkit'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="pending-fields-tbody"></tbody>
                                    </table>
                                </div>

                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="new_field_name"><?php _e('Field Name', 'voxel-toolkit'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text"
                                                   id="new_field_name"
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
                                            <select id="new_field_type">
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
                                                   class="regular-text" />
                                            <p class="description"><?php _e('Optional default value', 'voxel-toolkit'); ?></p>
                                        </td>
                                    </tr>
                                </table>

                                <p>
                                    <button type="button" class="button button-primary" id="add-field-to-queue">
                                        <?php _e('Add Another Field', 'voxel-toolkit'); ?>
                                    </button>
                                </p>
                            </div>
                        <?php else: ?>
                            <p style="margin-top: 20px; color: #d63638;">
                                <?php printf(__('Maximum field limit (%d) reached. Delete fields to add new ones.', 'voxel-toolkit'), self::MAX_FIELDS); ?>
                            </p>
                        <?php endif; ?>

                        <!-- Hidden field to store fields marked for deletion -->
                        <input type="hidden" name="voxel_toolkit_options[options_page][delete_fields]" id="delete_fields" value="" />

                        <!-- Hidden field to store new fields to be added -->
                        <input type="hidden" name="voxel_toolkit_options[options_page][new_fields]" id="new_fields" value="" />

                        <!-- Store existing fields -->
                        <input type="hidden" name="voxel_toolkit_options[options_page][fields]" value="<?php echo esc_attr(json_encode($fields)); ?>" />
                    </td>
                </tr>
            </table>

            <!-- OPTIONS PAGE JS VERSION: 2024-11-18-00:15 -->
            <script>
            jQuery(document).ready(function($) {
                console.log('OPTIONS PAGE: Multi-field add functionality loaded');
                var fieldsToDelete = [];
                var fieldsToAdd = [];

                // Auto-format field name as user types
                $('#new_field_name').on('input', function() {
                    var value = $(this).val();
                    // Convert to lowercase and replace spaces/hyphens with underscores
                    var formatted = value.toLowerCase().replace(/[\s-]+/g, '_').replace(/[^a-z0-9_]/g, '');
                    $(this).val(formatted);
                });

                // Add field to queue
                $('#add-field-to-queue').on('click', function(e) {
                    e.preventDefault();

                    var fieldName = $('#new_field_name').val().trim();
                    var fieldLabel = $('#new_field_label').val().trim();
                    var fieldType = $('#new_field_type').val();
                    var fieldDefault = $('#new_field_default').val().trim();

                    if (!fieldName) {
                        alert('<?php _e('Please enter a field name', 'voxel-toolkit'); ?>');
                        return;
                    }

                    // Check if field already exists in queue
                    var exists = fieldsToAdd.some(function(field) {
                        return field.name === fieldName;
                    });

                    if (exists) {
                        alert('<?php _e('A field with this name is already in the queue', 'voxel-toolkit'); ?>');
                        return;
                    }

                    // Auto-generate label if empty
                    if (!fieldLabel) {
                        fieldLabel = fieldName.split('_').map(function(word) {
                            return word.charAt(0).toUpperCase() + word.slice(1);
                        }).join(' ');
                    }

                    // Add to queue
                    fieldsToAdd.push({
                        name: fieldName,
                        label: fieldLabel,
                        type: fieldType,
                        default: fieldDefault
                    });

                    // Update display
                    updatePendingFieldsList();

                    // Clear form
                    $('#new_field_name').val('');
                    $('#new_field_label').val('');
                    $('#new_field_type').val('text');
                    $('#new_field_default').val('');
                    $('#new_field_name').focus();
                });

                // Update pending fields list
                function updatePendingFieldsList() {
                    var $tbody = $('#pending-fields-tbody');
                    $tbody.empty();

                    if (fieldsToAdd.length === 0) {
                        $('#pending-fields-list').hide();
                        return;
                    }

                    $('#pending-fields-list').show();

                    fieldsToAdd.forEach(function(field, index) {
                        var $row = $('<tr>');
                        $row.append('<td><code>' + field.name + '</code></td>');
                        $row.append('<td>' + field.label + '</td>');
                        $row.append('<td>' + field.type + '</td>');
                        $row.append('<td>' + (field.default || '<em>none</em>') + '</td>');
                        $row.append('<td><button type="button" class="button button-small remove-pending-field" data-index="' + index + '">Remove</button></td>');
                        $tbody.append($row);
                    });
                }

                // Remove field from queue
                $(document).on('click', '.remove-pending-field', function(e) {
                    e.preventDefault();
                    var index = $(this).data('index');
                    fieldsToAdd.splice(index, 1);
                    updatePendingFieldsList();
                });

                // Delete field - mark for deletion visually (using delegated events)
                $(document).on('click', '.vt-delete-field', function(e) {
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

                // Before form submit, add all fields to hidden inputs
                $('form').on('submit', function() {
                    console.log('Form submitting with fields to add:', fieldsToAdd);
                    $('#delete_fields').val(fieldsToDelete.join(','));
                    $('#new_fields').val(JSON.stringify(fieldsToAdd));
                    console.log('Hidden field value:', $('#new_fields').val());
                });
            });
            </script>
        </div>
        */
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
                    <p><?php _e('No fields configured yet. Please configure fields first.', 'voxel-toolkit'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=voxel-toolkit-configure-fields'); ?>" class="button button-primary"><?php _e('Configure Fields', 'voxel-toolkit'); ?></a></p>
                </div>
            </div>
            <?php
            return;
        }

        // Handle form submission
        if (isset($_POST['voxel_toolkit_options_nonce']) && wp_verify_nonce($_POST['voxel_toolkit_options_nonce'], 'voxel_toolkit_save_options')) {
            $this->handle_options_save();
        }

        // Check for success message
        $show_success = get_transient('voxel_toolkit_options_saved');
        if ($show_success) {
            delete_transient('voxel_toolkit_options_saved');
        }

        ?>
        <div class="wrap voxel-toolkit-edit-options-page">
            <h1><?php _e('Edit Site Options', 'voxel-toolkit'); ?></h1>

            <div class="voxel-toolkit-intro">
                <p><?php _e('Update your global site settings below. These values are accessible throughout your site via dynamic tags.', 'voxel-toolkit'); ?></p>
            </div>

            <?php if ($show_success): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Options saved successfully!', 'voxel-toolkit'); ?></p>
                </div>
            <?php endif; ?>

            <style>
                .voxel-toolkit-edit-options-page .voxel-toolkit-intro {
                    margin-bottom: 20px;
                }
                .vt-options-container {
                    background: #fff;
                    border: 1px solid #e1e5e9;
                    border-radius: 12px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
                    margin-top: 20px;
                    overflow: hidden;
                }
                .vt-option-row {
                    padding: 25px 30px;
                    border-bottom: 1px solid #e1e5e9;
                    transition: background-color 0.2s ease;
                }
                .vt-option-row:last-child {
                    border-bottom: none;
                }
                .vt-option-row:hover {
                    background-color: #f8f9fa;
                }
                .vt-option-header {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin-bottom: 12px;
                }
                .vt-option-label {
                    font-weight: 600;
                    font-size: 15px;
                    color: #1d2327;
                    margin: 0;
                }
                .vt-option-type {
                    display: inline-block;
                    background: #e8f4f8;
                    color: #0c5d8c;
                    padding: 4px 12px;
                    border-radius: 12px;
                    font-size: 11px;
                    text-transform: uppercase;
                    font-weight: 600;
                    letter-spacing: 0.5px;
                }
                .vt-option-field {
                    margin-bottom: 10px;
                }
                .vt-option-field input[type="text"],
                .vt-option-field input[type="url"],
                .vt-option-field input[type="number"],
                .vt-option-field textarea {
                    width: 100%;
                    max-width: 600px;
                    padding: 8px 12px;
                    border: 1px solid #e1e5e9;
                    border-radius: 6px;
                    font-size: 14px;
                    transition: border-color 0.2s ease;
                }
                .vt-option-field input[type="text"]:focus,
                .vt-option-field input[type="url"]:focus,
                .vt-option-field input[type="number"]:focus,
                .vt-option-field textarea:focus {
                    border-color: #1e3a5f;
                    outline: none;
                    box-shadow: 0 0 0 1px #1e3a5f;
                }
                .vt-option-field textarea {
                    min-height: 100px;
                    resize: vertical;
                }
                .vt-option-tag {
                    font-size: 12px;
                    color: #646970;
                    margin-top: 8px;
                }
                .vt-option-tag code {
                    background: #f8f9fa;
                    padding: 3px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    border: 1px solid #e1e5e9;
                    font-family: monospace;
                    color: #1e3a5f;
                }
                .vt-save-button-wrapper {
                    margin-top: 20px;
                }
            </style>

            <form method="post" action="">
                <?php wp_nonce_field('voxel_toolkit_save_options', 'voxel_toolkit_options_nonce'); ?>

                <div class="vt-options-container">
                    <?php foreach ($fields as $field_name => $field_config): ?>
                        <div class="vt-option-row">
                            <div class="vt-option-header">
                                <label for="option_<?php echo esc_attr($field_name); ?>" class="vt-option-label">
                                    <?php echo esc_html($field_config['label']); ?>
                                </label>
                                <span class="vt-option-type"><?php echo esc_html(ucfirst($field_config['type'])); ?></span>
                            </div>
                            <div class="vt-option-field">
                                <?php $this->render_field($field_name, $field_config); ?>
                            </div>
                            <div class="vt-option-tag">
                                <?php printf(__('Tag: %s', 'voxel-toolkit'), '<code>@site(options.' . esc_html($field_name) . ')</code>'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="vt-save-button-wrapper">
                    <?php submit_button(__('Save Options', 'voxel-toolkit'), 'primary', 'submit', false); ?>
                </div>
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
