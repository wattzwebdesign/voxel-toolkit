<?php
/**
 * Column Types Definition
 *
 * Defines supported field types and their properties for admin columns.
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Column_Types {

    /**
     * Field type definitions
     */
    private $types = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->register_types();
    }

    /**
     * Register all field types
     */
    private function register_types() {
        $this->types = array(
            // Simple text types
            'title' => array(
                'label' => __('Title', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-editor-textcolor',
            ),
            'text' => array(
                'label' => __('Text', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-editor-textcolor',
            ),
            'textarea' => array(
                'label' => __('Textarea', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-editor-paragraph',
            ),
            'description' => array(
                'label' => __('Description', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-editor-paragraph',
            ),

            // Numeric
            'number' => array(
                'label' => __('Number', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
                'numeric' => true,
                'icon' => 'dashicons-editor-ol',
            ),

            // Contact fields
            'email' => array(
                'label' => __('Email', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-email',
            ),
            'phone' => array(
                'label' => __('Phone', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-phone',
            ),
            'url' => array(
                'label' => __('URL', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-admin-links',
            ),

            // Date/Time
            'date' => array(
                'label' => __('Date', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-calendar',
            ),
            'time' => array(
                'label' => __('Time', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-clock',
            ),

            // Selections
            'select' => array(
                'label' => __('Select', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => true,
                'numeric' => false,
                'icon' => 'dashicons-arrow-down-alt2',
            ),
            'multiselect' => array(
                'label' => __('Multi-Select', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-list-view',
            ),

            // Toggle/Switch
            'switcher' => array(
                'label' => __('Switcher', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => true,
                'numeric' => false,
                'icon' => 'dashicons-yes-alt',
            ),

            // Color
            'color' => array(
                'label' => __('Color', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-art',
            ),

            // Media
            'image' => array(
                'label' => __('Image', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-format-image',
            ),
            'file' => array(
                'label' => __('File', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-media-default',
            ),

            // Location
            'location' => array(
                'label' => __('Location', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-location',
            ),

            // Timezone
            'timezone' => array(
                'label' => __('Timezone', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-clock',
            ),

            // Complex types
            'work-hours' => array(
                'label' => __('Work Hours', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-clock',
            ),
            'recurring-date' => array(
                'label' => __('Recurring Date', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-calendar-alt',
            ),
            'repeater' => array(
                'label' => __('Repeater', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-list-view',
            ),
            'product' => array(
                'label' => __('Product', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
                'numeric' => true,
                'icon' => 'dashicons-cart',
            ),
            'poll-vt' => array(
                'label' => __('Poll (VT)', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
                'numeric' => true,
                'icon' => 'dashicons-chart-bar',
            ),
            // Alias for Voxel's internal type name
            'poll' => array(
                'label' => __('Poll (VT)', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
                'numeric' => true,
                'icon' => 'dashicons-chart-bar',
            ),

            // Taxonomy
            'taxonomy' => array(
                'label' => __('Taxonomy', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => true,
                'numeric' => false,
                'icon' => 'dashicons-tag',
            ),

            // Relations
            'post-relation' => array(
                'label' => __('Post Relation', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-admin-links',
            ),

            // Profile fields
            'profile-name' => array(
                'label' => __('Profile Name', 'voxel-toolkit'),
                'sortable' => true,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-admin-users',
            ),

            // Texteditor
            'texteditor' => array(
                'label' => __('Text Editor', 'voxel-toolkit'),
                'sortable' => false,
                'filterable' => false,
                'numeric' => false,
                'icon' => 'dashicons-editor-paragraph',
            ),
        );
    }

    /**
     * Get type information
     */
    public function get_type_info($type) {
        if (isset($this->types[$type])) {
            return $this->types[$type];
        }

        // Default for unknown types
        return array(
            'label' => ucfirst(str_replace('-', ' ', $type)),
            'sortable' => false,
            'filterable' => false,
            'numeric' => false,
            'icon' => 'dashicons-admin-generic',
        );
    }

    /**
     * Get all field type info for JavaScript
     */
    public function get_field_type_info() {
        return $this->types;
    }

    /**
     * Check if field type is sortable
     */
    public function is_sortable($type) {
        $info = $this->get_type_info($type);
        return $info['sortable'];
    }

    /**
     * Check if field type is filterable
     */
    public function is_filterable($type) {
        $info = $this->get_type_info($type);
        return $info['filterable'];
    }

    /**
     * Check if field type uses numeric sorting
     */
    public function is_numeric($type) {
        $info = $this->get_type_info($type);
        return $info['numeric'];
    }

    /**
     * Get icon for field type
     */
    public function get_icon($type) {
        $info = $this->get_type_info($type);
        return $info['icon'];
    }
}
