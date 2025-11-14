<?php
/**
 * Profile Completion Settings
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render profile completion settings section
 */
function voxel_toolkit_render_profile_completion_settings() {
    // Get available profile fields
    $available_fields = array();
    if (class_exists('Voxel_Toolkit_Profile_Progress_Widget')) {
        $available_fields = Voxel_Toolkit_Profile_Progress_Widget::get_available_profile_fields();
    }

    // Get currently selected fields
    $selected_fields = get_option('voxel_toolkit_profile_completion_fields', array());

    ?>
    <div class="vt-settings-section">
        <h2><?php _e('Profile Completion Settings', 'voxel-toolkit'); ?></h2>
        <p class="description">
            <?php _e('Select which profile fields to track for the profile completion percentage. These fields will be used by the dynamic tag @user().profile_completion() and the Profile Progress Widget.', 'voxel-toolkit'); ?>
        </p>

        <form method="post" action="">
            <?php wp_nonce_field('voxel_toolkit_profile_completion', 'voxel_toolkit_profile_completion_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php _e('Profile Fields to Track', 'voxel-toolkit'); ?></label>
                    </th>
                    <td>
                        <?php if (empty($available_fields) || (count($available_fields) === 1 && isset($available_fields['']))): ?>
                            <p class="description" style="color: #d63638;">
                                <?php _e('No profile fields found. Make sure you have a "profile" post type configured in Voxel.', 'voxel-toolkit'); ?>
                            </p>
                        <?php else: ?>
                            <select name="voxel_toolkit_profile_fields[]" multiple size="10" style="width: 100%; max-width: 400px;">
                                <?php foreach ($available_fields as $key => $label): ?>
                                    <?php if ($key !== ''): // Skip empty key ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php echo in_array($key, $selected_fields) ? 'selected' : ''; ?>>
                                            <?php echo esc_html($label); ?> (<?php echo esc_html($key); ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Hold Ctrl (Cmd on Mac) to select multiple fields. Currently selected: ', 'voxel-toolkit'); ?>
                                <strong><?php echo count($selected_fields); ?> field(s)</strong>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <?php submit_button(__('Save Profile Completion Settings', 'voxel-toolkit'), 'primary', 'submit', false); ?>
                &nbsp;
                <button type="submit" name="voxel_toolkit_reset_profile_completion" value="1" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to reset the profile completion fields? This will clear all selected fields.', 'voxel-toolkit')); ?>');">
                    <?php _e('Reset Fields', 'voxel-toolkit'); ?>
                </button>
            </p>
        </form>
    </div>

    <style>
        .vt-settings-section {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .vt-settings-section h2 {
            margin-top: 0;
        }
        .vt-settings-section select[multiple] {
            padding: 5px;
        }
        .vt-settings-section select[multiple] option {
            padding: 5px;
        }
    </style>
    <?php
}

/**
 * Handle profile completion settings save
 */
function voxel_toolkit_save_profile_completion_settings() {
    // Handle reset
    if (isset($_POST['voxel_toolkit_reset_profile_completion']) &&
        isset($_POST['voxel_toolkit_profile_completion_nonce']) &&
        wp_verify_nonce($_POST['voxel_toolkit_profile_completion_nonce'], 'voxel_toolkit_profile_completion') &&
        current_user_can('manage_options')) {

        delete_option('voxel_toolkit_profile_completion_fields');

        add_settings_error(
            'voxel_toolkit_messages',
            'voxel_toolkit_message',
            __('Profile completion fields have been reset.', 'voxel-toolkit'),
            'success'
        );
        return;
    }

    // Handle save
    if (!isset($_POST['voxel_toolkit_profile_completion_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['voxel_toolkit_profile_completion_nonce'], 'voxel_toolkit_profile_completion')) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['submit'])) {
        return;
    }

    $selected_fields = isset($_POST['voxel_toolkit_profile_fields']) && is_array($_POST['voxel_toolkit_profile_fields'])
        ? array_map('sanitize_text_field', $_POST['voxel_toolkit_profile_fields'])
        : array();

    update_option('voxel_toolkit_profile_completion_fields', $selected_fields, false);

    add_settings_error(
        'voxel_toolkit_messages',
        'voxel_toolkit_message',
        __('Profile completion settings saved.', 'voxel-toolkit'),
        'success'
    );
}

// Hook to save settings
add_action('admin_init', 'voxel_toolkit_save_profile_completion_settings');
