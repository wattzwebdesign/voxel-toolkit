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

class User_Role_Filter extends \Voxel\Post_Types\Filters\Base_Filter {

    protected $props = [
        'type' => 'user-role',
        'label' => 'User Role',
        'placeholder' => 'Select user role(s)',
    ];

    /**
     * Get filter label
     */
    public function get_label(): string {
        return $this->props['label'] ?? 'User Role';
    }

    /**
     * Get admin configuration models
     */
    public function get_models(): array {
        return [
            'label' => $this->get_label_model(),
            'placeholder' => $this->get_placeholder_model(),
            'key' => $this->get_model('key', ['classes' => 'x-col-6']),
            'icon' => $this->get_icon_model(),
        ];
    }

    /**
     * Get user role choices
     */
    protected function _get_choices() {
        $choices = [];

        // Get all WordPress roles
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

    /**
     * Get frontend properties for the filter
     */
    public function frontend_props() {
        return [
            'choices' => $this->_get_choices(),
            'selected' => $this->_get_selected_roles() ?: ((object) []),
            'placeholder' => $this->props['placeholder'] ?: $this->props['label'],
            'display_as' => $this->elementor_config['display_as'] ?? 'popup',
        ];
    }

    /**
     * Get selected roles based on current filter value
     */
    protected function _get_selected_roles() {
        if (array_key_exists('selected_roles', $this->cache)) {
            return $this->cache['selected_roles'];
        }

        $value = $this->parse_value($this->get_value()) ?: [];
        if (empty($value)) {
            return null;
        }

        $all_roles = $this->_get_choices();
        $selected = [];
        foreach ($value as $role_key) {
            if (isset($all_roles[$role_key])) {
                $selected[$role_key] = $all_roles[$role_key];
            }
        }

        $this->cache['selected_roles'] = !empty($selected) ? $selected : null;
        return $this->cache['selected_roles'];
    }

    /**
     * Modify the search query to filter by user role
     */
    public function query(\Voxel\Post_Types\Index_Query $query, array $args): void {
        $value = $this->parse_value($args[$this->get_key()] ?? null);

        if (empty($value)) {
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

        // Join with posts table to get author
        $query->join(sprintf(
            "LEFT JOIN {$wpdb->posts} AS `%s_posts` ON `%s`.post_id = `%s_posts`.ID",
            $join_key,
            $query->table->get_escaped_name(),
            $join_key
        ));

        // Join with usermeta to get user capabilities (roles are stored here)
        $query->join(sprintf(
            "LEFT JOIN {$wpdb->usermeta} AS `%s_caps` ON (
                `%s_posts`.post_author = `%s_caps`.user_id
                AND `%s_caps`.meta_key = '{$wpdb->prefix}capabilities'
            )",
            $join_key,
            $join_key,
            $join_key,
            $join_key
        ));

        // Build WHERE clause - check if any of the selected roles exist in the serialized capabilities
        $role_conditions = [];
        foreach ($role_keys as $role_key) {
            $role_conditions[] = sprintf(
                "`%s_caps`.meta_value LIKE '%%\"%s\";%%'",
                $join_key,
                $role_key
            );
        }

        $where_sql = '(' . implode(' OR ', $role_conditions) . ')';
        $query->where($where_sql);
    }

    /**
     * Parse and validate filter value
     */
    public function parse_value($value) {
        if (!is_string($value) || empty($value)) {
            return null;
        }

        $roles = explode(',', trim($value));
        $roles = array_filter(array_map('trim', $roles));

        return !empty($roles) ? $roles : null;
    }

    /**
     * Get Elementor controls
     */
    public function get_elementor_controls(): array {
        return [
            'value' => [
                'label' => 'Default value',
                'type' => \Elementor\Controls_Manager::TEXT,
                'description' => 'Enter a comma-delimited list of role keys to be selected by default (e.g., subscriber,customer)',
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
        ];
    }

    /**
     * Exports for frontend (used by Voxel's search form system)
     */
    public function exports() {
        return [
            'type' => $this->props['type'],
            'label' => $this->get_label(),
            'placeholder' => $this->props['placeholder'] ?? 'Select user role(s)',
        ];
    }
}
