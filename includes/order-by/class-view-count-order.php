<?php
/**
 * View Count Order By
 *
 * Custom order by type for sorting posts by view counts
 * with support for different time periods
 *
 * @package Voxel_Toolkit
 */

namespace Voxel_Toolkit\Order_By;

if (!defined('ABSPATH')) {
    exit;
}

class View_Count_Order extends \Voxel\Post_Types\Order_By\Base_Search_Order {

    protected $props = [
        'type' => 'view-count',
        'order' => 'DESC',
        'period' => 'all',
    ];

    /**
     * Get order by label
     */
    public function get_label(): string {
        return 'View Count';
    }

    /**
     * Get configuration models for admin
     */
    public function get_models(): array {
        return [
            'period' => [
                'type' => \Voxel\Utils\Form_Models\Select_Model::class,
                'label' => 'Time Period',
                'classes' => 'x-col-6',
                'choices' => [
                    'all' => 'All time',
                    '30d' => 'Last 30 days',
                    '7d' => 'Last 7 days',
                    '1d' => 'Last 24 hours',
                ],
            ],
            'order' => $this->get_order_model(),
        ];
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

        $period = $this->props['period'] ?? 'all';
        $order = $this->props['order'] === 'ASC' ? 'ASC' : 'DESC';

        // Validate period to prevent SQL injection
        $valid_periods = ['all', '30d', '7d', '1d'];
        if (!in_array($period, $valid_periods, true)) {
            $period = 'all';
        }

        // Join with postmeta table to get view counts
        $query->join(sprintf(
            "LEFT JOIN {$wpdb->postmeta} AS view_counts_meta
            ON `%s`.post_id = view_counts_meta.post_id
            AND view_counts_meta.meta_key = 'voxel:view_counts'",
            $query->table->get_escaped_name()
        ));

        // Extract view count from JSON and select it
        // Format: {"views":{"all":21,"1d":2,"7d":7,"30d":21}}
        $query->select(sprintf(
            "COALESCE(
                CAST(
                    JSON_UNQUOTE(JSON_EXTRACT(view_counts_meta.meta_value, '$.views.\"%s\"'))
                    AS UNSIGNED
                ),
                0
            ) AS view_count",
            esc_sql($period)
        ));

        // Order by the extracted view count
        $query->orderby(sprintf('view_count %s', $order));
    }

    /**
     * Get exports for frontend
     */
    public function exports(): array {
        return [
            'label' => $this->get_label(),
            'type' => $this->get_type(),
            'period' => $this->props['period'] ?? 'all',
            'order' => $this->props['order'] ?? 'DESC',
        ];
    }
}
