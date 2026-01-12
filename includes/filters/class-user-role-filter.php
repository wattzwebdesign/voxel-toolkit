<?php
/**
 * User Role Filter
 *
 * Custom filter for filtering posts by author's user role
 *
 * @package Voxel_Toolkit
 */

namespace Voxel_Toolkit\Filters;

if (!defined('ABSPATH')) {
    exit;
}

// Only define the class if the parent class exists (Voxel theme is loaded)
if (!class_exists('\Voxel\Post_Types\Filters\Base_Filter')) {
    return;
}

class User_Role_Filter extends \Voxel\Post_Types\Filters\Base_Filter {

    protected $supported_conditions = ['text'];

    protected $props = [
        'type' => 'user-role',
        'label' => 'User Role (VT)',
        'placeholder' => '',
    ];

    public function get_models(): array {
        return [
            'label' => $this->get_model('label', ['classes' => 'x-col-12']),
            'placeholder' => $this->get_placeholder_model(),
            'key' => $this->get_model('key', ['classes' => 'x-col-6']),
            'icon' => $this->get_icon_model(),
        ];
    }

    public function query(\Voxel\Post_Types\Index_Query $query, array $args): void {
        $value = $this->parse_value($args[$this->get_key()] ?? null);
        if ($value === null) {
            return;
        }

        global $wpdb;
        $join_key = esc_sql($this->db_key());

        // Ensure $value is an array for multiple selection support
        if (!is_array($value)) {
            $value = [$value];
        }

        // Sanitize role keys
        $role_keys = array_map('esc_sql', $value);

        // Get escaped table name
        $table_name = $query->table->get_escaped_name();

        // Join with posts table to get author - no sprintf to avoid format specifier conflicts
        $query->join(
            "LEFT JOIN {$wpdb->posts} AS `{$join_key}_posts` ON `{$table_name}`.post_id = `{$join_key}_posts`.ID"
        );

        // Join with usermeta to get user capabilities (roles are stored here)
        $capabilities_meta_key = $wpdb->prefix . 'capabilities';
        $query->join(
            "LEFT JOIN {$wpdb->usermeta} AS `{$join_key}_caps` ON (
                `{$join_key}_posts`.post_author = `{$join_key}_caps`.user_id
                AND `{$join_key}_caps`.meta_key = '{$capabilities_meta_key}'
            )"
        );

        // Build WHERE clause - check if any of the selected roles exist in the serialized capabilities
        // Use CONCAT to avoid any quote-related sprintf issues in Voxel's adaptive filter code
        $role_conditions = [];
        foreach ($role_keys as $role_key) {
            $role_conditions[] = "`{$join_key}_caps`.meta_value LIKE CONCAT(" . $wpdb->prepare("'%%', CHAR(34), %s, CHAR(34), ';%%'", $role_key) . ")";
        }

        $where_sql = '(' . implode(' OR ', $role_conditions) . ')';
        $query->where($where_sql);
    }

    public function parse_value($value) {
        if (empty($value)) {
            return null;
        }

        // Handle both single value and comma-separated values
        if (is_string($value)) {
            $roles = explode(',', trim($value));
            $roles = array_filter(array_map('trim', $roles));
            return !empty($roles) ? $roles : null;
        }

        return null;
    }

    public function frontend_props() {
        return [
            'choices' => $this->_get_selected_choices(),
            'display_as' => ($this->elementor_config['display_as'] ?? null) === 'buttons' ? 'buttons' : 'popup',
            'placeholder' => $this->props['placeholder'] ?: $this->props['label'],
        ];
    }

    protected function _get_selected_choices() {
        $all_choices = $this->_get_choices();
        $choices = [];
        $selected = ($this->elementor_config['choices'] ?? null);
        if (is_array($selected) && !empty($selected)) {
            foreach ($selected as $choice_key) {
                if (isset($all_choices[$choice_key])) {
                    $choices[$choice_key] = $all_choices[$choice_key];
                }
            }
        }

        return !empty($choices) ? $choices : $all_choices;
    }

    protected function _get_choices() {
        $choices = [];

        if (function_exists('wp_roles')) {
            $wp_roles = wp_roles();
            foreach ($wp_roles->roles as $role_key => $role_info) {
                $choices[$role_key] = [
                    'key' => $role_key,
                    'label' => $role_info['name'],
                ];
            }
        }

        return $choices;
    }

    public function get_elementor_controls(): array {
        $choices = [];
        foreach ($this->_get_choices() as $choice) {
            $choices[$choice['key']] = $choice['label'];
        }

        return [
            'value' => [
                'label' => 'Default value',
                'type' => \Elementor\Controls_Manager::TEXT,
                'description' => 'Enter a comma-delimited list of role keys (e.g., subscriber,customer)',
            ],
            'display_as' => [
                'label' => 'Display as',
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'popup' => 'Popup',
                    'buttons' => 'Buttons',
                ],
                'conditional' => false,
            ],
            'choices' => [
                'label' => 'Choices',
                'description' => 'Leave blank to list all roles available',
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $choices,
                'conditional' => false,
            ],
        ];
    }
}
