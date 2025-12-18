<?php
/**
 * Helpful Votes Order By
 *
 * Custom order by type for sorting posts by article helpful votes
 * with support for different metrics (helpful, disputed, total)
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

class Helpful_Votes_Order extends \Voxel\Post_Types\Order_By\Base_Search_Order {

    protected $props = [
        'type' => 'helpful-votes',
        'order' => 'DESC',
        'sort_type' => 'helpful',
    ];

    /**
     * Get order by label
     */
    public function get_label(): string {
        return 'Helpful Votes';
    }

    /**
     * Get configuration models for admin
     */
    public function get_models(): array {
        return [
            'sort_type' => [
                'type' => \Voxel\Utils\Form_Models\Select_Model::class,
                'label' => 'Sort By',
                'classes' => 'x-col-6',
                'choices' => [
                    'helpful' => 'Most Helpful (Yes)',
                    'disputed' => 'Most Disputed (No)',
                    'total' => 'Total Votes (Yes + No)',
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

        $sort_type = $this->props['sort_type'] ?? 'helpful';
        $order = $this->props['order'] === 'ASC' ? 'ASC' : 'DESC';
        $table = $query->table->get_escaped_name();

        // Validate sort_type to prevent issues
        $valid_types = ['helpful', 'disputed', 'total'];
        if (!in_array($sort_type, $valid_types, true)) {
            $sort_type = 'helpful';
        }

        // Join the 'YES' votes (always needed)
        $query->join(sprintf(
            "LEFT JOIN {$wpdb->postmeta} AS vote_yes ON `%s`.post_id = vote_yes.post_id AND vote_yes.meta_key = '_article_helpful_yes'",
            $table
        ));

        // Join the 'NO' votes (needed for disputed and total)
        if (in_array($sort_type, ['disputed', 'total'], true)) {
            $query->join(sprintf(
                "LEFT JOIN {$wpdb->postmeta} AS vote_no ON `%s`.post_id = vote_no.post_id AND vote_no.meta_key = '_article_helpful_no'",
                $table
            ));
        }

        // Build the ORDER BY expression based on sort type
        switch ($sort_type) {
            case 'disputed':
                $sql_orderby = "COALESCE(CAST(vote_no.meta_value AS SIGNED), 0)";
                break;

            case 'total':
                $sql_orderby = "(COALESCE(CAST(vote_yes.meta_value AS SIGNED), 0) + COALESCE(CAST(vote_no.meta_value AS SIGNED), 0))";
                break;

            case 'helpful':
            default:
                $sql_orderby = "COALESCE(CAST(vote_yes.meta_value AS SIGNED), 0)";
                break;
        }

        // Apply the order
        $query->orderby("{$sql_orderby} {$order}");
    }

    /**
     * Get exports for frontend
     */
    public function exports(): array {
        return [
            'label' => $this->get_label(),
            'type' => $this->get_type(),
            'sort_type' => $this->props['sort_type'] ?? 'helpful',
            'order' => $this->props['order'] ?? 'DESC',
        ];
    }
}
