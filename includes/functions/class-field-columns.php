<?php
/**
 * Field Columns
 *
 * Adds a column size picker to post field settings
 * Injects a dropdown next to the CSS Classes field
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Field_Columns {

    /**
     * Column class options (sorted by size, largest to smallest)
     */
    private $columns = [
        ''        => '-- Column Width --',
        'vx-1-1'  => '100%',
        'vx-3-4'  => '75%',
        'vx-2-3'  => '66%',
        'vx-1-2'  => '50%',
        'vx-1-3'  => '33%',
        'vx-1-4'  => '25%',
        'vx-1-6'  => '16%',
    ];

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Enqueue scripts and styles for the post-type editor
     */
    public function enqueue_scripts($hook) {
        // Only load on Voxel post-type editor pages
        // URL pattern: edit.php?post_type=X&page=edit-post-type-X&tab=fields
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

        // Check if we're on a Voxel post-type editor page (page starts with "edit-post-type-")
        $is_post_type_editor = (strpos($page, 'edit-post-type-') === 0);

        if (!$is_post_type_editor) {
            return;
        }

        // Enqueue the JavaScript
        wp_enqueue_script(
            'vt-field-columns',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/field-columns.js',
            [],
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Enqueue the CSS
        wp_enqueue_style(
            'vt-field-columns',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/field-columns.css',
            [],
            VOXEL_TOOLKIT_VERSION
        );

        // Pass column options to JavaScript
        wp_localize_script('vt-field-columns', 'vtFieldColumns', [
            'columns' => $this->columns,
        ]);
    }
}
