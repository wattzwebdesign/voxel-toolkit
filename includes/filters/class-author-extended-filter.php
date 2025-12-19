<?php
/**
 * Extended Author Filter
 *
 * Extends Voxel's Author filter to also include posts where user is a team member
 * With option to show only team member posts
 *
 * @package Voxel_Toolkit
 */

namespace Voxel_Toolkit\Filters;

if (!defined('ABSPATH')) {
    exit;
}

// Only define the class if the parent class exists (Voxel theme is loaded)
if (!class_exists('\Voxel\Post_Types\Filters\User_Filter')) {
    return;
}

class Author_Extended_Filter extends \Voxel\Post_Types\Filters\User_Filter {

    protected $props = [
        'type' => 'user',
        'label' => 'Author',
    ];

    /**
     * Override get_elementor_controls to add team_only option
     */
    public function get_elementor_controls(): array {
        return [
            'value' => [
                'label' => _x('Default value', 'author filter', 'voxel-backend'),
                'type' => \Elementor\Controls_Manager::NUMBER,
            ],
            'team_only' => [
                'label' => _x('Only show team member posts', 'author filter', 'voxel-toolkit'),
                'description' => _x('When enabled, only shows posts where the user is an assigned team member (not their authored posts)', 'author filter', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => 'yes',
            ],
        ];
    }

    /**
     * Override the query method to include team membership
     */
    public function query(\Voxel\Post_Types\Index_Query $query, array $args): void {
        $value = $this->parse_value($args[$this->get_key()] ?? null);
        if ($value === null) {
            return;
        }

        $user_id = absint($value);
        $user = get_userdata($user_id);

        if (!$user) {
            return;
        }

        global $wpdb;

        $join_key = esc_sql($this->db_key());
        $table_name = $query->table->get_escaped_name();
        $meta_key = '_vt_team_members';
        $escaped_email = $user->user_email ? esc_sql($user->user_email) : '';
        $team_only = ($this->elementor_config['team_only'] ?? null) === 'yes';

        if ($team_only) {
            // Only show posts where user is a team member (not author)
            if (!$escaped_email) {
                $query->where('1 = 0');
                return;
            }

            $query->join(<<<SQL
                INNER JOIN {$wpdb->postmeta} AS `{$join_key}_team` ON (
                    `{$table_name}`.post_id = `{$join_key}_team`.post_id
                    AND `{$join_key}_team`.meta_key = '{$meta_key}'
                    AND `{$join_key}_team`.meta_value LIKE '%{$escaped_email}%'
                    AND `{$join_key}_team`.meta_value LIKE '%"status";s:8:"accepted"%'
                )
            SQL);
        } else {
            // Show posts where user is author OR team member
            // Join with posts table to check author
            $query->join(<<<SQL
                LEFT JOIN {$wpdb->posts} AS `{$join_key}` ON (
                    `{$table_name}`.post_id = `{$join_key}`.ID
                )
            SQL);

            // Left join with postmeta to check team membership
            $query->join(<<<SQL
                LEFT JOIN {$wpdb->postmeta} AS `{$join_key}_team` ON (
                    `{$table_name}`.post_id = `{$join_key}_team`.post_id
                    AND `{$join_key}_team`.meta_key = '{$meta_key}'
                )
            SQL);

            // WHERE: user is author OR user is accepted team member
            if ($escaped_email) {
                $query->where(<<<SQL
                    (
                        `{$join_key}`.post_author = {$user_id}
                        OR (
                            `{$join_key}_team`.meta_value LIKE '%{$escaped_email}%'
                            AND `{$join_key}_team`.meta_value LIKE '%"status";s:8:"accepted"%'
                        )
                    )
                SQL);
            } else {
                // No email, just check author
                $query->where("`{$join_key}`.post_author = {$user_id}");
            }
        }
    }
}
