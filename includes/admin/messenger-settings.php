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
        add_action('admin_menu', array($this, 'add_settings_page'), 20); // Priority 20 to load after main menu
        add_action('admin_init', array($this, 'register_settings'));
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
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'voxel_toolkit_messenger_settings',
            'voxel_toolkit_messenger_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
            )
        );

        // General settings section
        add_settings_section(
            'voxel_toolkit_messenger_general',
            __('General Settings', 'voxel-toolkit'),
            array($this, 'render_general_section'),
            'voxel-toolkit-messenger'
        );

        add_settings_field(
            'enabled',
            __('Enable Messenger Widget', 'voxel-toolkit'),
            array($this, 'render_enabled_field'),
            'voxel-toolkit-messenger',
            'voxel_toolkit_messenger_general'
        );

        add_settings_field(
            'default_avatar',
            __('Default Avatar Image', 'voxel-toolkit'),
            array($this, 'render_default_avatar_field'),
            'voxel-toolkit-messenger',
            'voxel_toolkit_messenger_general'
        );

        // Page rules section
        add_settings_section(
            'voxel_toolkit_messenger_page_rules',
            __('Page Display Rules', 'voxel-toolkit'),
            array($this, 'render_page_rules_section'),
            'voxel-toolkit-messenger'
        );

        add_settings_field(
            'excluded_rules',
            __('Exclude From Page Types', 'voxel-toolkit'),
            array($this, 'render_excluded_rules_field'),
            'voxel-toolkit-messenger',
            'voxel_toolkit_messenger_page_rules'
        );

        add_settings_field(
            'excluded_post_ids',
            __('Exclude Specific Posts (IDs)', 'voxel-toolkit'),
            array($this, 'render_excluded_post_ids_field'),
            'voxel-toolkit-messenger',
            'voxel_toolkit_messenger_page_rules'
        );

        add_settings_field(
            'excluded_post_types',
            __('Exclude Post Types', 'voxel-toolkit'),
            array($this, 'render_excluded_post_types_field'),
            'voxel-toolkit-messenger',
            'voxel_toolkit_messenger_page_rules'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
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

        // Save settings
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'voxel_toolkit_messenger_messages',
                'voxel_toolkit_messenger_message',
                __('Settings saved successfully.', 'voxel-toolkit'),
                'updated'
            );
        }

        settings_errors('voxel_toolkit_messenger_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('voxel_toolkit_messenger_settings');
                do_settings_sections('voxel-toolkit-messenger');
                submit_button(__('Save Settings', 'voxel-toolkit'));
                ?>
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
        <?php
    }

    /**
     * Render general section description
     */
    public function render_general_section() {
        echo '<p>' . __('Control the messenger widget globally.', 'voxel-toolkit') . '</p>';
    }

    /**
     * Render enabled field
     */
    public function render_enabled_field() {
        $settings = get_option('voxel_toolkit_messenger_settings', array());
        $enabled = !empty($settings['enabled']) ? 1 : 0;
        ?>
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
        <?php
    }

    /**
     * Render default avatar field
     */
    public function render_default_avatar_field() {
        $settings = get_option('voxel_toolkit_messenger_settings', array());
        $default_avatar = !empty($settings['default_avatar']) ? $settings['default_avatar'] : '';
        ?>
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

    /**
     * Render page rules section description
     */
    public function render_page_rules_section() {
        echo '<p>' . __('Choose which pages should NOT display the messenger widget.', 'voxel-toolkit') . '</p>';
    }

    /**
     * Render excluded rules field
     */
    public function render_excluded_rules_field() {
        $settings = get_option('voxel_toolkit_messenger_settings', array());
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
        <?php
    }

    /**
     * Render excluded post IDs field
     */
    public function render_excluded_post_ids_field() {
        $settings = get_option('voxel_toolkit_messenger_settings', array());
        $excluded_post_ids = !empty($settings['excluded_post_ids']) ? $settings['excluded_post_ids'] : '';
        ?>
        <input type="text"
               name="voxel_toolkit_messenger_settings[excluded_post_ids]"
               value="<?php echo esc_attr($excluded_post_ids); ?>"
               class="regular-text"
               placeholder="123, 456, 789">
        <p class="description">
            <?php _e('Enter post/page IDs separated by commas. The messenger will not appear on these specific posts.', 'voxel-toolkit'); ?>
        </p>
        <?php
    }

    /**
     * Render excluded post types field
     */
    public function render_excluded_post_types_field() {
        $settings = get_option('voxel_toolkit_messenger_settings', array());
        $excluded_post_types = !empty($settings['excluded_post_types']) ? $settings['excluded_post_types'] : array();

        // Get all public post types
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
        <?php
    }
}

// Initialize
Voxel_Toolkit_Messenger_Settings::instance();
