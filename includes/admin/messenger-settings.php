<?php
/**
 * Messenger Settings Page
 *
 * Settings for controlling where the messenger widget appears
 * Uses AJAX to save settings to avoid caching/nonce issues on Cloudways
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Messenger_Settings {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'), 20);
        add_action('wp_ajax_vt_save_messenger_settings', array($this, 'ajax_save_settings'));
    }

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_submenu_page(
            'voxel-toolkit',
            __('Messenger Settings', 'voxel-toolkit'),
            __('Messenger', 'voxel-toolkit'),
            'manage_options',
            'voxel-toolkit-messenger',
            array($this, 'render_settings_page')
        );
    }

    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vt_messenger_settings_ajax')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        // Get and sanitize settings
        $input = isset($_POST['settings']) ? $_POST['settings'] : array();
        $sanitized = $this->sanitize_settings($input);

        // Save
        update_option('voxel_toolkit_messenger_settings', $sanitized);

        wp_send_json_success(array('message' => 'Settings saved successfully'));
    }

    /**
     * Sanitize settings
     */
    private function sanitize_settings($input) {
        $sanitized = array();

        $sanitized['enabled'] = !empty($input['enabled']) ? 1 : 0;

        if (!empty($input['default_avatar'])) {
            $sanitized['default_avatar'] = esc_url_raw($input['default_avatar']);
        }

        if (!empty($input['excluded_rules']) && is_array($input['excluded_rules'])) {
            $sanitized['excluded_rules'] = array_map('sanitize_text_field', $input['excluded_rules']);
        } else {
            $sanitized['excluded_rules'] = array();
        }

        if (!empty($input['excluded_post_ids'])) {
            $sanitized['excluded_post_ids'] = sanitize_text_field($input['excluded_post_ids']);
        }

        if (!empty($input['excluded_post_types']) && is_array($input['excluded_post_types'])) {
            $sanitized['excluded_post_types'] = array_map('sanitize_text_field', $input['excluded_post_types']);
        } else {
            $sanitized['excluded_post_types'] = array();
        }

        // Open new chats in window
        $sanitized['open_chats_in_window'] = !empty($input['open_chats_in_window']) ? 1 : 0;

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = get_option('voxel_toolkit_messenger_settings', array());
        $ajax_nonce = wp_create_nonce('vt_messenger_settings_ajax');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div id="vt-messenger-notice" style="display:none;" class="notice is-dismissible">
                <p></p>
            </div>

            <div id="vt-messenger-settings-form">
                <table class="form-table" role="presentation">
                    <tbody>
                        <!-- General Settings Section -->
                        <tr>
                            <th scope="row" colspan="2">
                                <h2 style="margin: 0;"><?php _e('General Settings', 'voxel-toolkit'); ?></h2>
                                <p class="description"><?php _e('Control the messenger widget globally.', 'voxel-toolkit'); ?></p>
                            </th>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Enable Messenger Widget', 'voxel-toolkit'); ?></th>
                            <td>
                                <?php $enabled = !empty($settings['enabled']) ? 1 : 0; ?>
                                <label>
                                    <input type="checkbox"
                                           name="enabled"
                                           value="1"
                                           <?php checked($enabled, 1); ?>>
                                    <?php _e('Enable the messenger widget site-wide', 'voxel-toolkit'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When enabled, the messenger widget will appear on pages unless excluded by rules below.', 'voxel-toolkit'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Default Avatar Image', 'voxel-toolkit'); ?></th>
                            <td>
                                <?php $default_avatar = !empty($settings['default_avatar']) ? $settings['default_avatar'] : ''; ?>
                                <div class="vt-messenger-avatar-upload">
                                    <input type="hidden"
                                           id="vt_default_avatar"
                                           name="default_avatar"
                                           value="<?php echo esc_attr($default_avatar); ?>">

                                    <button type="button" class="button vt-upload-avatar-btn">
                                        <?php _e('Upload/Select Image', 'voxel-toolkit'); ?>
                                    </button>

                                    <button type="button" class="button vt-remove-avatar-btn" style="<?php echo empty($default_avatar) ? 'display:none;' : ''; ?>">
                                        <?php _e('Remove Image', 'voxel-toolkit'); ?>
                                    </button>

                                    <div class="vt-avatar-preview" style="margin-top: 10px;">
                                        <?php if (!empty($default_avatar)): ?>
                                            <img src="<?php echo esc_url($default_avatar); ?>" style="max-width: 100px; height: auto; border-radius: 50%; border: 2px solid #ddd;">
                                        <?php endif; ?>
                                    </div>

                                    <p class="description">
                                        <?php _e('This image will be displayed in chat circles when a user has no avatar image.', 'voxel-toolkit'); ?>
                                    </p>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Open New Chats in Window', 'voxel-toolkit'); ?></th>
                            <td>
                                <?php $open_chats_in_window = !empty($settings['open_chats_in_window']) ? 1 : 0; ?>
                                <label>
                                    <input type="checkbox"
                                           name="open_chats_in_window"
                                           value="1"
                                           <?php checked($open_chats_in_window, 1); ?>>
                                    <?php _e('Open message actions in chat window instead of inbox', 'voxel-toolkit'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When enabled, clicking "Message" buttons on posts will open a chat window on the current page instead of navigating to the inbox page.', 'voxel-toolkit'); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Page Display Rules Section -->
                        <tr>
                            <th scope="row" colspan="2">
                                <h2 style="margin: 20px 0 0 0;"><?php _e('Page Display Rules', 'voxel-toolkit'); ?></h2>
                                <p class="description"><?php _e('Choose which pages should NOT display the messenger widget.', 'voxel-toolkit'); ?></p>
                            </th>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Exclude From Page Types', 'voxel-toolkit'); ?></th>
                            <td>
                                <?php
                                $excluded_rules = !empty($settings['excluded_rules']) ? $settings['excluded_rules'] : array();
                                $rules = array(
                                    'singular' => __('Singular Pages (Single Posts/Pages)', 'voxel-toolkit'),
                                    'archive' => __('Archive Pages', 'voxel-toolkit'),
                                    'home' => __('Home/Front Page', 'voxel-toolkit'),
                                    'search' => __('Search Results', 'voxel-toolkit'),
                                    '404' => __('404 Error Pages', 'voxel-toolkit'),
                                );
                                ?>
                                <fieldset>
                                    <?php foreach ($rules as $rule => $label): ?>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="checkbox"
                                                   name="excluded_rules[]"
                                                   value="<?php echo esc_attr($rule); ?>"
                                                   <?php checked(in_array($rule, $excluded_rules), true); ?>>
                                            <?php echo esc_html($label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                                <p class="description">
                                    <?php _e('The messenger will NOT appear on the selected page types.', 'voxel-toolkit'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Exclude Specific Posts (IDs)', 'voxel-toolkit'); ?></th>
                            <td>
                                <?php $excluded_post_ids = !empty($settings['excluded_post_ids']) ? $settings['excluded_post_ids'] : ''; ?>
                                <input type="text"
                                       name="excluded_post_ids"
                                       value="<?php echo esc_attr($excluded_post_ids); ?>"
                                       class="regular-text"
                                       placeholder="123, 456, 789">
                                <p class="description">
                                    <?php _e('Enter post/page IDs separated by commas. The messenger will not appear on these specific posts.', 'voxel-toolkit'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Exclude Post Types', 'voxel-toolkit'); ?></th>
                            <td>
                                <?php
                                $excluded_post_types = !empty($settings['excluded_post_types']) ? $settings['excluded_post_types'] : array();
                                $post_types = get_post_types(array('public' => true), 'objects');
                                ?>
                                <fieldset>
                                    <?php foreach ($post_types as $post_type): ?>
                                        <?php if ($post_type->name === 'attachment') continue; ?>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="checkbox"
                                                   name="excluded_post_types[]"
                                                   value="<?php echo esc_attr($post_type->name); ?>"
                                                   <?php checked(in_array($post_type->name, $excluded_post_types), true); ?>>
                                            <?php echo esc_html($post_type->label); ?>
                                            <span style="color: #666;">(<?php echo esc_html($post_type->name); ?>)</span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                                <p class="description">
                                    <?php _e('The messenger will NOT appear on singular pages of the selected post types.', 'voxel-toolkit'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="button" class="button button-primary" id="vt-save-messenger-settings">
                        <?php _e('Save Settings', 'voxel-toolkit'); ?>
                    </button>
                    <span class="spinner" style="float: none; margin-top: 0;"></span>
                </p>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 30px;">
                <h2><?php _e('Usage Instructions', 'voxel-toolkit'); ?></h2>
                <p><?php _e('To display the messenger widget on your site:', 'voxel-toolkit'); ?></p>
                <ol>
                    <li><?php _e('Enable the messenger widget above', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Add the "Messenger (VT)" widget to any page using Elementor', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Or use the shortcode:', 'voxel-toolkit'); ?> <code>[vt_messenger]</code></li>
                    <li><?php _e('Configure page exclusion rules to control where it appears', 'voxel-toolkit'); ?></li>
                </ol>

                <h3><?php _e('How It Works', 'voxel-toolkit'); ?></h3>
                <ul>
                    <li><?php _e('The messenger button appears in the bottom-right corner', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Users can open multiple chat windows simultaneously (like Facebook)', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Chat windows can be minimized to save space', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Real-time polling checks for new messages every second', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Mobile users see a scaled-down version optimized for touch', 'voxel-toolkit'); ?></li>
                </ul>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var mediaUploader;

            // Media uploader for avatar
            $('.vt-upload-avatar-btn').on('click', function(e) {
                e.preventDefault();

                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                mediaUploader = wp.media({
                    title: '<?php _e('Choose Default Avatar', 'voxel-toolkit'); ?>',
                    button: {
                        text: '<?php _e('Use This Image', 'voxel-toolkit'); ?>'
                    },
                    multiple: false
                });

                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#vt_default_avatar').val(attachment.url);
                    $('.vt-avatar-preview').html('<img src="' + attachment.url + '" style="max-width: 100px; height: auto; border-radius: 50%; border: 2px solid #ddd;">');
                    $('.vt-remove-avatar-btn').show();
                });

                mediaUploader.open();
            });

            $('.vt-remove-avatar-btn').on('click', function(e) {
                e.preventDefault();
                $('#vt_default_avatar').val('');
                $('.vt-avatar-preview').html('');
                $(this).hide();
            });

            // AJAX save on button click
            $('#vt-save-messenger-settings').on('click', function(e) {
                e.preventDefault();

                var $form = $('#vt-messenger-settings-form');
                var $button = $('#vt-save-messenger-settings');
                var $spinner = $form.find('.spinner');
                var $notice = $('#vt-messenger-notice');

                // Gather form data
                var settings = {
                    enabled: $form.find('input[name="enabled"]').is(':checked') ? 1 : 0,
                    default_avatar: $form.find('input[name="default_avatar"]').val(),
                    open_chats_in_window: $form.find('input[name="open_chats_in_window"]').is(':checked') ? 1 : 0,
                    excluded_rules: [],
                    excluded_post_ids: $form.find('input[name="excluded_post_ids"]').val(),
                    excluded_post_types: []
                };

                $form.find('input[name="excluded_rules[]"]:checked').each(function() {
                    settings.excluded_rules.push($(this).val());
                });

                $form.find('input[name="excluded_post_types[]"]:checked').each(function() {
                    settings.excluded_post_types.push($(this).val());
                });

                // Disable button and show spinner
                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                $notice.hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vt_save_messenger_settings',
                        nonce: '<?php echo $ajax_nonce; ?>',
                        settings: settings
                    },
                    success: function(response) {
                        $button.prop('disabled', false);
                        $spinner.removeClass('is-active');

                        if (response.success) {
                            $notice.removeClass('notice-error').addClass('notice-success');
                            $notice.find('p').text(response.data.message);
                        } else {
                            $notice.removeClass('notice-success').addClass('notice-error');
                            $notice.find('p').text(response.data.message || 'An error occurred');
                        }
                        $notice.show();

                        // Scroll to top to show notice
                        $('html, body').animate({ scrollTop: 0 }, 300);
                    },
                    error: function(xhr, status, error) {
                        $button.prop('disabled', false);
                        $spinner.removeClass('is-active');

                        $notice.removeClass('notice-success').addClass('notice-error');
                        $notice.find('p').text('Request failed: ' + error);
                        $notice.show();

                        $('html, body').animate({ scrollTop: 0 }, 300);
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Initialize
Voxel_Toolkit_Messenger_Settings::instance();
