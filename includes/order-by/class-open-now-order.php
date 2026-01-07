<?php
/**
 * Open Now Order By
 *
 * Custom order by type for sorting posts by open/closed status,
 * showing open listings first (or closed first if reversed)
 *
 * @package Voxel_Toolkit
 */

namespace Voxel_Toolkit\Order_By;

if (!defined('ABSPATH')) {
    exit;
}

// Only define the class if the parent class exists (Voxel theme is loaded)
if (!class_exists('\Voxel\Post_Types\Order_By\Base_Search_Order')) {
    return;
}

class Open_Now_Order extends \Voxel\Post_Types\Order_By\Base_Search_Order {

    protected $props = [
        'type' => 'open-now',
        'order' => 'DESC',          // DESC = Open first, ASC = Closed first
        'field' => '',              // Work hours field key
        'timezone_mode' => 'site',  // 'site' or 'post'
    ];

    /**
     * Get order by label
     */
    public function get_label(): string {
        return 'Open Now';
    }

    /**
     * Get configuration models for admin
     */
    public function get_models(): array {
        return [
            'field' => [
                'type' => \Voxel\Utils\Form_Models\Select_Model::class,
                'label' => 'Work Hours Field',
                'classes' => 'x-col-6',
                'choices' => $this->get_work_hours_field_choices(),
            ],
            'timezone_mode' => [
                'type' => \Voxel\Utils\Form_Models\Select_Model::class,
                'label' => 'Timezone',
                'classes' => 'x-col-6',
                'choices' => [
                    'site' => 'Site timezone',
                    'post' => 'Post timezone',
                ],
            ],
            'order' => $this->get_order_model(),
        ];
    }

    /**
     * Get work hours field choices for the current post type
     */
    protected function get_work_hours_field_choices(): array {
        $choices = ['' => 'Select field'];

        // Get the post type from the parent context
        if (isset($this->post_type) && $this->post_type) {
            $fields = $this->post_type->get_fields();
            foreach ($fields as $field) {
                if ($field->get_type() === 'work-hours') {
                    $choices[$field->get_key()] = $field->get_label();
                }
            }
        }

        return $choices;
    }

    /**
     * Setup index table columns (for post timezone mode)
     */
    public function setup(\Voxel\Post_Types\Index_Table $table): void {
        if ($this->props['timezone_mode'] === 'post') {
            // Ensure timezone column exists for post timezone mode
            // This is typically already added by the Open Now filter
            // but we check to be safe
        }
    }

    /**
     * Index data (for post timezone mode)
     */
    public function index(\Voxel\Post $post): array {
        if ($this->props['timezone_mode'] === 'post') {
            return [
                'timezone' => sprintf('\'%s\'', esc_sql($post->get_timezone()->getName())),
            ];
        }
        return [];
    }

    /**
     * Apply order to query
     *
     * @param \Voxel\Post_Types\Index_Query $query Query object
     * @param array $args Query arguments
     * @param array $clause_args Clause arguments
     */
    public function query(\Voxel\Post_Types\Index_Query $query, array $args, array $clause_args): void {
        global $wpdb;

        $field_key = $this->props['field'] ?? '';
        if (empty($field_key)) {
            return;
        }

        $order = $this->props['order'] === 'ASC' ? 'ASC' : 'DESC';
        $post_type_key = esc_sql($query->post_type->get_key());
        $field_key_escaped = esc_sql($field_key);
        $join_key = 'open_now_sort_' . esc_sql($field_key);
        $table_name = $query->table->get_escaped_name();

        // Calculate minute of week based on timezone mode
        if ($this->props['timezone_mode'] === 'site') {
            $minute_of_week = \Voxel\get_minute_of_week(new \DateTime('now', wp_timezone()));
            $start_offset = "`{$join_key}`.`start`";
            $end_offset = "`{$join_key}`.`end`";
        } else {
            // Post timezone mode - calculate offset dynamically
            $minute_of_week = \Voxel\get_minute_of_week(\Voxel\utc());
            $start_offset = "(`{$join_key}`.`start` - TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(),
                CONVERT_TZ(UTC_TIMESTAMP(), \"UTC\", `{$table_name}`.timezone)
            ))";
            $end_offset = "(`{$join_key}`.`end` - TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(),
                CONVERT_TZ(UTC_TIMESTAMP(), \"UTC\", `{$table_name}`.timezone)
            ))";
        }

        // LEFT JOIN to work hours table to check if currently open
        // Using LEFT JOIN so we get all posts (open and closed)
        $query->join(sprintf(
            "LEFT JOIN {$wpdb->prefix}voxel_work_hours AS `%s` ON (
                `%s`.post_id = `%s`.post_id
                AND `%s`.post_type = '%s'
                AND `%s`.field_key = '%s'
                AND %d BETWEEN %s AND %s
            )",
            $join_key,
            $table_name, $join_key,
            $join_key, $post_type_key,
            $join_key, $field_key_escaped,
            $minute_of_week, $start_offset, $end_offset
        ));

        // Order by open status: 1 = open (has matching row), 0 = closed (no match)
        // DESC = open first, ASC = closed first
        $query->orderby(sprintf(
            "CASE WHEN `%s`.id IS NOT NULL THEN 1 ELSE 0 END %s",
            $join_key,
            $order
        ));
    }

    /**
     * Get exports for frontend
     */
    public function exports(): array {
        return [
            'label' => $this->get_label(),
            'type' => $this->get_type(),
            'field' => $this->props['field'] ?? '',
            'timezone_mode' => $this->props['timezone_mode'] ?? 'site',
            'order' => $this->props['order'] ?? 'DESC',
        ];
    }
}
