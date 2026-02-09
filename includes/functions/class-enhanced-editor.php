<?php
/**
 * Enhanced TinyMCE Editor
 *
 * Adds additional TinyMCE features to Voxel's WP Editor Advanced mode:
 * - Media upload button (images, videos, audio, files)
 * - Text color picker
 * - Background color picker
 * - Character map
 *
 * Security:
 * - Media button only shown to logged-in users (not guests)
 * - Content sanitized by Voxel via wp_kses_post() on save
 * - WordPress media library handles file type validation
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Enhanced_Editor {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter('voxel/texteditor-field/tinymce/config', array($this, 'enhance_editor_config'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_filter('user_has_cap', array($this, 'grant_upload_capability'), 10, 4);
    }

    /**
     * Dynamically grant upload_files capability to all logged-in users.
     *
     * WordPress's wp.media() requires the upload_files capability. Roles like
     * Voxel's "visitor" (mapped to subscriber) don't have it by default, so
     * media uploads silently fail. This filter grants it dynamically — it's
     * not stored in the DB and only runs while Enhanced Editor is active.
     *
     * @param array $allcaps All capabilities for the user.
     * @param array $caps    Required capabilities for the requested check.
     * @param array $args    Additional arguments passed to the capability check.
     * @param WP_User $user  The user object.
     * @return array Modified capabilities.
     */
    public function grant_upload_capability($allcaps, $caps, $args, $user) {
        if (is_user_logged_in() && in_array('upload_files', $caps, true)) {
            $allcaps['upload_files'] = true;
        }
        return $allcaps;
    }

    /**
     * Check if current user can use the media button
     *
     * Only logged-in users can use the media upload feature.
     * Guests are excluded for security.
     *
     * @return bool
     */
    private function user_can_upload() {
        return is_user_logged_in();
    }

    /**
     * Enqueue required scripts and styles
     */
    public function enqueue_scripts() {
        // Always enqueue frontend styles for alignment (needed for viewing content)
        wp_enqueue_style(
            'vt-enhanced-editor',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/enhanced-editor.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        // Only enqueue media scripts if user can upload
        if ($this->user_can_upload()) {
            wp_enqueue_media();

            wp_enqueue_script(
                'vt-enhanced-editor',
                VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/enhanced-editor.js',
                array('jquery'),
                VOXEL_TOOLKIT_VERSION,
                true
            );

            // Pass capability info to JavaScript
            wp_localize_script('vt-enhanced-editor', 'vtEnhancedEditor', array(
                'canUpload' => true,
                'nonce' => wp_create_nonce('vt_enhanced_editor'),
            ));
        }
    }

    /**
     * Enhance TinyMCE configuration for advanced editor mode
     *
     * @param array $config TinyMCE configuration array
     * @param object $field The texteditor field instance
     * @return array Modified configuration
     */
    public function enhance_editor_config($config, $field) {
        // Only modify if editor type is wp-editor-advanced
        if ($field->get_model_value('editor-type') !== 'wp-editor-advanced') {
            return $config;
        }

        // Base enhanced toolbar (without media button)
        // Original: formatselect,bold,italic,bullist,numlist,link,unlink,strikethrough,alignleft,aligncenter,alignright,underline,hr
        // Added: forecolor, backcolor, charmap
        $toolbar = 'formatselect,bold,italic,bullist,numlist,link,unlink,strikethrough,alignleft,aligncenter,alignright,underline,hr,forecolor,backcolor,charmap';

        // Add required plugins (only WordPress core TinyMCE plugins)
        // Original: lists,paste,tabfocus,wplink,wordpress,colorpicker,hr,wpautoresize
        // Added: textcolor (for forecolor/backcolor), charmap
        $config['tinymce']['plugins'] = 'lists,paste,tabfocus,wplink,wordpress,colorpicker,hr,wpautoresize,textcolor,charmap';

        // Only add media button if user has upload capability
        if ($this->user_can_upload()) {
            // Prepend media button to toolbar
            $toolbar = 'vt_media,' . $toolbar;

            // Register our custom media plugin as external
            if (!isset($config['tinymce']['external_plugins'])) {
                $config['tinymce']['external_plugins'] = array();
            }
            $config['tinymce']['external_plugins']['vt_media'] = VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/enhanced-editor.js';
        }

        $config['tinymce']['toolbar1'] = $toolbar;

        return $config;
    }
}
