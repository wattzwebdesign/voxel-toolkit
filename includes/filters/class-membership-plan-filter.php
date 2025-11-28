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
     * Modify the search query to filter by membership plan
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

        // Sanitize plan keys
        $plan_keys = array_map('esc_sql', $value);
        $plan_keys_list = "'" . implode("','", $plan_keys) . "'";

        // Check if 'default' (Guest) is in the selected plans
        $include_guest = in_array('default', $plan_keys, true);

        // Join with posts table to get author
        $join_sql = sprintf(
            "LEFT JOIN {$wpdb->posts} AS `%s_posts` ON `%s`.post_id = `%s_posts`.ID",
            $join_key,
            $query->table->get_escaped_name(),
            $join_key
        );
        $query->join($join_sql);

        // Join with usermeta to get membership plan
        $join_plan_sql = sprintf(
            "LEFT JOIN {$wpdb->usermeta} AS `%s_plan` ON (
                `%s_posts`.post_author = `%s_plan`.user_id
                AND `%s_plan`.meta_key = 'voxel:plan'
            )",
            $join_key,
            $join_key,
            $join_key,
            $join_key
        );
        $query->join($join_plan_sql);

        // Build WHERE clause
        if ($include_guest) {
            // Include posts where plan is NULL or matches selected plans
            $where_sql = sprintf(
                "(
                    JSON_UNQUOTE(JSON_EXTRACT(`%s_plan`.meta_value, '$.plan')) IN (%s)
                    OR `%s_plan`.meta_value IS NULL
                )",
                $join_key,
                $plan_keys_list,
                $join_key
            );
        } else {
            // Only include posts where plan matches selected plans
            $where_sql = sprintf(
                "JSON_UNQUOTE(JSON_EXTRACT(`%s_plan`.meta_value, '$.plan')) IN (%s)",
                $join_key,
                $plan_keys_list
            );
        }
        $query->where($where_sql);
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
