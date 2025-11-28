<?php
/**
 * Disable Gutenberg Function
 *
 * Disables the Gutenberg block editor site-wide and restores the classic editor.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Disable_Gutenberg {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize the feature
     */
    private function init() {
        // Disable block editor for all post types
        add_filter('use_block_editor_for_post_type', '__return_false', 100);
        add_filter('use_block_editor_for_post', '__return_false', 100);

        // Also disable for older Gutenberg plugin versions
        add_filter('gutenberg_can_edit_post_type', '__return_false', 100);
        add_filter('gutenberg_can_edit_post', '__return_false', 100);

        // Remove the "Try Gutenberg" dashboard widget
        remove_action('try_gutenberg_panel', 'wp_try_gutenberg_panel');

        // Remove Gutenberg plugin hooks if active
        add_action('plugins_loaded', array($this, 'remove_gutenberg_hooks'), 20);

        // Disable block-based widgets (restore classic widgets)
        add_filter('gutenberg_use_widgets_block_editor', '__return_false');
        add_filter('use_widgets_block_editor', '__return_false');
    }

    /**
     * Remove Gutenberg plugin hooks if the plugin is active
     */
    public function remove_gutenberg_hooks() {
        if (!function_exists('gutenberg_register_scripts_and_styles')) {
            return;
        }

        // Remove Gutenberg admin menu
        remove_action('admin_menu', 'gutenberg_menu');
        remove_action('admin_init', 'gutenberg_redirect_demo');

        // Remove Gutenberg scripts and styles
        remove_action('wp_enqueue_scripts', 'gutenberg_register_scripts_and_styles');
        remove_action('admin_enqueue_scripts', 'gutenberg_register_scripts_and_styles');
        remove_action('admin_notices', 'gutenberg_wordpress_version_notice');
        remove_action('rest_api_init', 'gutenberg_register_rest_widget_updater_routes');
        remove_action('admin_print_styles', 'gutenberg_block_editor_admin_print_styles');
        remove_action('admin_print_scripts', 'gutenberg_block_editor_admin_print_scripts');
        remove_action('admin_print_footer_scripts', 'gutenberg_block_editor_admin_print_footer_scripts');
        remove_action('admin_footer', 'gutenberg_block_editor_admin_footer');
        remove_action('admin_enqueue_scripts', 'gutenberg_widgets_init');
        remove_action('admin_notices', 'gutenberg_build_files_notice');

        // Remove Gutenberg filters
        remove_filter('load_script_translation_file', 'gutenberg_override_translation_file');
        remove_filter('block_editor_settings', 'gutenberg_extend_block_editor_styles');
        remove_filter('default_content', 'gutenberg_default_demo_content');
        remove_filter('default_title', 'gutenberg_default_demo_title');
        remove_filter('block_editor_settings', 'gutenberg_legacy_widget_settings');
        remove_filter('rest_request_after_callbacks', 'gutenberg_filter_oembed_result');

        // Remove older Gutenberg hooks
        remove_filter('wp_refresh_nonces', 'gutenberg_add_rest_nonce_to_heartbeat_response_headers');
        remove_filter('get_edit_post_link', 'gutenberg_revisions_link_to_editor');
        remove_filter('wp_prepare_revision_for_js', 'gutenberg_revisions_restore');
        remove_action('rest_api_init', 'gutenberg_register_rest_routes');
        remove_action('rest_api_init', 'gutenberg_add_taxonomy_visibility_field');
        remove_filter('registered_post_type', 'gutenberg_register_post_prepare_functions');
        remove_action('do_meta_boxes', 'gutenberg_meta_box_save');
        remove_action('submitpost_box', 'gutenberg_intercept_meta_box_render');
        remove_action('submitpage_box', 'gutenberg_intercept_meta_box_render');
        remove_action('edit_page_form', 'gutenberg_intercept_meta_box_render');
        remove_action('edit_form_advanced', 'gutenberg_intercept_meta_box_render');
        remove_filter('redirect_post_location', 'gutenberg_meta_box_save_redirect');
        remove_filter('filter_gutenberg_meta_boxes', 'gutenberg_filter_meta_boxes');
        remove_filter('body_class', 'gutenberg_add_responsive_body_class');
        remove_filter('admin_url', 'gutenberg_modify_add_new_button_url');
        remove_action('admin_enqueue_scripts', 'gutenberg_check_if_classic_needs_warning_about_blocks');
        remove_filter('register_post_type_args', 'gutenberg_filter_post_type_labels');
    }

    /**
     * Render settings for this function
     */
    public function render_settings($function_settings) {
        ?>
        <div class="voxel-toolkit-setting">
            <h3><?php _e('Disable Gutenberg', 'voxel-toolkit'); ?></h3>
            <p class="description">
                <?php _e('Disables the Gutenberg block editor site-wide and restores the classic WordPress editor.', 'voxel-toolkit'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Status', 'voxel-toolkit'); ?></th>
                    <td>
                        <span style="color: #46b450; font-weight: 600;">
                            <span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
                            <?php _e('Gutenberg is disabled', 'voxel-toolkit'); ?>
                        </span>
                        <p class="description" style="margin-top: 8px;">
                            <?php _e('The classic editor is now active for all post types.', 'voxel-toolkit'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('What this does', 'voxel-toolkit'); ?></th>
                    <td>
                        <ul style="margin: 0; list-style: disc; padding-left: 20px;">
                            <li><?php _e('Disables block editor for all posts and pages', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Restores classic widgets (disables block-based widgets)', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Removes Gutenberg plugin hooks if installed', 'voxel-toolkit'); ?></li>
                        </ul>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
}
