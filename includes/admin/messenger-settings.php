<?php
/**
 * Messenger Settings Page
 *
 * Settings for controlling where the messenger widget appears
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

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $saved = false;

        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('voxel_toolkit_messenger_settings_nonce', 'voxel_toolkit_messenger_nonce')) {
            $input = isset($_POST['voxel_toolkit_messenger_settings']) ? $_POST['voxel_toolkit_messenger_settings'] : array();
            $sanitized = $this->sanitize_settings($input);
            update_option('voxel_toolkit_messenger_settings', $sanitized);
            $saved = true;
        }

        $settings = get_option('voxel_toolkit_messenger_settings', array());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if ($saved): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully.', 'voxel-toolkit'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('voxel_toolkit_messenger_settings_nonce', 'voxel_toolkit_messenger_nonce'); ?>

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
                                           name="voxel_toolkit_messenger_settings[enabled]"
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
                                           name="voxel_toolkit_messenger_settings[default_avatar]"
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
                                                   name="voxel_toolkit_messenger_settings[excluded_rules][]"
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
                                       name="voxel_toolkit_messenger_settings[excluded_post_ids]"
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
                                                   name="voxel_toolkit_messenger_settings[excluded_post_types][]"
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

                <?php submit_button(__('Save Settings', 'voxel-toolkit')); ?>
            </form>

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
        });
        </script>
        <?php
    }
}

// Initialize
Voxel_Toolkit_Messenger_Settings::instance();
