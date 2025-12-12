<?php
/**
 * Membership Plan Filter
 *
 * Custom filter for filtering posts by author's membership plan
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

class Membership_Plan_Filter extends \Voxel\Post_Types\Filters\Base_Filter {

    protected $props = [
        'type' => 'membership-plan',
        'label' => 'Membership Plan',
        'placeholder' => 'Select membership plan(s)',
    ];

    /**
     * Get filter label
     */
    public function get_label(): string {
        return $this->props['label'] ?? 'Membership Plan';
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
     * Get membership plan choices
     */
    protected function _get_choices() {
        $choices = [];

        // Add Guest plan first
        $choices['default'] = [
            'key' => 'default',
            'label' => 'Guest',
        ];

        // Get all active (non-archived) membership plans
        if (class_exists('\Voxel\Modules\Paid_Memberships\Plan')) {
            $plans = \Voxel\Modules\Paid_Memberships\Plan::active();

            foreach ($plans as $plan) {
                $choices[$plan->get_key()] = [
                    'key' => $plan->get_key(),
                    'label' => $plan->get_label(),
                ];
            }
        }

        return $choices;
    }

    /**
     * Get selected choices for frontend
     */
    protected function _get_selected_choices() {
        $all_choices = $this->_get_choices();
        $selected = (array) ($this->props['choices'] ?? []);

        if (empty($selected)) {
            return $all_choices;
        }

        $choices = [];
        foreach ($selected as $choice_key) {
            if (isset($all_choices[$choice_key])) {
                $choices[$choice_key] = $all_choices[$choice_key];
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
            'selected' => $this->_get_selected_plans() ?: ((object) []),
            'placeholder' => $this->props['placeholder'] ?: $this->props['label'],
            'display_as' => $this->elementor_config['display_as'] ?? 'popup',
        ];
    }

    /**
     * Get selected plans based on current filter value
     */
    protected function _get_selected_plans() {
        if (array_key_exists('selected_plans', $this->cache)) {
            return $this->cache['selected_plans'];
        }

        $value = $this->parse_value($this->get_value()) ?: [];
        if (empty($value)) {
            return null;
        }

        $all_plans = $this->_get_choices();
        $selected = [];
        foreach ($value as $plan_key) {
            if (isset($all_plans[$plan_key])) {
                $selected[$plan_key] = $all_plans[$plan_key];
            }
        }

        $this->cache['selected_plans'] = !empty($selected) ? $selected : null;
        return $this->cache['selected_plans'];
    }

    /**
     * Get the correct meta key for membership plan (handles test mode and multisite)
     */
    protected function _get_membership_meta_key(): string {
        // Determine base meta key based on test mode
        $base_key = 'voxel:plan';
        if (function_exists('\Voxel\is_test_mode') && \Voxel\is_test_mode()) {
            $base_key = 'voxel:test_plan';
        }

        // Handle multisite - use Voxel's helper if available
        if (function_exists('\Voxel\get_site_specific_user_meta_key')) {
            return \Voxel\get_site_specific_user_meta_key($base_key);
        }

        return $base_key;
    }

    /**
     * Modify the search query to filter by membership plan
     */
    public function query(\Voxel\Post_Types\Index_Query $query, array $args): void {

        $value = $this->parse_value($args[$this->get_key()] ?? null);

        // Debug logging
        error_log('Membership Plan Filter - query() called');
        error_log('  Filter key: ' . $this->get_key());
        error_log('  Args: ' . print_r($args, true));
        error_log('  Parsed value: ' . print_r($value, true));

        if ($value === null) {
            error_log('  Value is null, returning early');
            return;
        }

        global $wpdb;
        $join_key = esc_sql($this->db_key());

        // Ensure $value is an array for multiple selection support
        if (!is_array($value)) {
            $value = [$value];
        }

        // Sanitize plan keys
        $plan_keys = array_map('esc_sql', $value);

        // Check if 'default' (Guest/Free) is in the selected plans
        $include_default = in_array('default', $plan_keys, true);

        // Remove 'default' from plan keys for the IN clause (it's handled separately)
        $plan_keys_without_default = array_values(array_filter($plan_keys, function($key) {
            return $key !== 'default';
        }));

        // Get escaped table name
        $table_name = $query->table->get_escaped_name();

        // Get the correct meta key (handles test mode and multisite)
        $meta_key = esc_sql($this->_get_membership_meta_key());

        // Join with posts table to get author
        $query->join(
            "LEFT JOIN {$wpdb->posts} AS `{$join_key}_posts` ON `{$table_name}`.post_id = `{$join_key}_posts`.ID"
        );

        // Join with usermeta to get membership plan
        $query->join(
            "LEFT JOIN {$wpdb->usermeta} AS `{$join_key}_plan` ON (
                `{$join_key}_posts`.post_author = `{$join_key}_plan`.user_id
                AND `{$join_key}_plan`.meta_key = '{$meta_key}'
            )"
        );

        // Build WHERE clause based on selected plans
        $conditions = [];

        // If 'default' is selected, include users with default plan OR no plan meta
        if ($include_default) {
            $conditions[] = "(
                `{$join_key}_plan`.meta_value IS NULL
                OR `{$join_key}_plan`.meta_value = ''
                OR (
                    `{$join_key}_plan`.meta_value IS NOT NULL
                    AND JSON_VALID(`{$join_key}_plan`.meta_value)
                    AND JSON_UNQUOTE(JSON_EXTRACT(`{$join_key}_plan`.meta_value, '$.plan')) = 'default'
                )
            )";
        }

        // If other plans are selected, include those
        if (!empty($plan_keys_without_default)) {
            $plan_keys_list = "'" . implode("','", $plan_keys_without_default) . "'";
            $conditions[] = "(
                `{$join_key}_plan`.meta_value IS NOT NULL
                AND `{$join_key}_plan`.meta_value != ''
                AND JSON_VALID(`{$join_key}_plan`.meta_value)
                AND JSON_UNQUOTE(JSON_EXTRACT(`{$join_key}_plan`.meta_value, '$.plan')) IN ({$plan_keys_list})
            )";
        }

        // Combine conditions with OR
        if (!empty($conditions)) {
            $where_sql = '(' . implode(' OR ', $conditions) . ')';
            error_log('  WHERE SQL: ' . $where_sql);
            $query->where($where_sql);
        }

        error_log('  Query complete');
    }

    /**
     * Parse and validate filter value
     */
    public function parse_value($value) {
        if (!is_string($value) || empty($value)) {
            return null;
        }

        $plans = explode(',', trim($value));
        $plans = array_filter(array_map('trim', $plans));

        return !empty($plans) ? $plans : null;
    }

    /**
     * Get Elementor controls
     */
    public function get_elementor_controls(): array {
        return [
            'value' => [
                'label' => 'Default value',
                'type' => \Elementor\Controls_Manager::TEXT,
                'description' => 'Enter a comma-delimited list of plan keys to be selected by default (e.g., default,plan-1)',
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
            'placeholder' => $this->props['placeholder'] ?? 'Select membership plan(s)',
        ];
    }
}
