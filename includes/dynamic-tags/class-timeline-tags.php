<?php
/**
 * Timeline Dynamic Tags
 *
 * Extends Voxel's post dynamic tags with additional timeline/review properties
 * - reviews.latest.content, reviews.latest.score, reviews.latest.link
 * - reviews.oldest.*
 * - timeline.latest.content, timeline.latest.author, timeline.latest.link
 * - timeline.oldest.*
 * - wall.latest.*, wall.oldest.*
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Timeline_Tags {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cache for timeline queries to avoid repeated DB hits
     */
    private static $cache = array();

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        add_filter('voxel/dynamic-data/groups/post/properties', array($this, 'extend_post_properties'), 10, 2);
    }

    /**
     * Extend post properties with additional timeline/review tags
     *
     * @param array $properties Existing properties
     * @param object $data_group Post_Data_Group instance
     * @return array Modified properties
     */
    public function extend_post_properties($properties, $data_group) {
        if (!method_exists($data_group, 'get_post')) {
            return $properties;
        }

        $post = $data_group->get_post();
        if (!$post) {
            return $properties;
        }

        $post_id = $post->get_id();
        $instance = $this;

        // Extend reviews with additional properties
        if (isset($properties['reviews'])) {
            $properties['vt_reviews'] = $this->get_extended_review_tags($post_id, $instance);
        }

        // Extend timeline with additional properties
        if (isset($properties['timeline'])) {
            $properties['vt_timeline'] = $this->get_extended_timeline_tags($post_id, 'post_timeline', $instance);
        }

        // Extend wall with additional properties
        if (isset($properties['wall'])) {
            $properties['vt_wall'] = $this->get_extended_timeline_tags($post_id, 'post_wall', $instance);
        }

        return $properties;
    }

    /**
     * Get extended review tags
     *
     * @param int $post_id Post ID
     * @param object $instance This instance for closures
     * @return \Voxel\Dynamic_Data\Data_Types\Base_Data_Type
     */
    private function get_extended_review_tags($post_id, $instance) {
        return \Voxel\Dynamic_Data\Tag::Object('Reviews (Extended)')->properties(function() use ($post_id, $instance) {
            return [
                'latest' => \Voxel\Dynamic_Data\Tag::Object('Latest review')->properties(function() use ($post_id, $instance) {
                    return $instance->get_timeline_entry_properties($post_id, 'post_reviews', 'latest');
                }),
                'oldest' => \Voxel\Dynamic_Data\Tag::Object('Oldest review')->properties(function() use ($post_id, $instance) {
                    return $instance->get_timeline_entry_properties($post_id, 'post_reviews', 'oldest');
                }),
            ];
        });
    }

    /**
     * Get extended timeline/wall tags
     *
     * @param int $post_id Post ID
     * @param string $feed Feed type (post_timeline, post_wall)
     * @param object $instance This instance for closures
     * @return \Voxel\Dynamic_Data\Data_Types\Base_Data_Type
     */
    private function get_extended_timeline_tags($post_id, $feed, $instance) {
        $label = $feed === 'post_wall' ? 'Wall (Extended)' : 'Timeline (Extended)';

        return \Voxel\Dynamic_Data\Tag::Object($label)->properties(function() use ($post_id, $feed, $instance) {
            return [
                'latest' => \Voxel\Dynamic_Data\Tag::Object('Latest post')->properties(function() use ($post_id, $feed, $instance) {
                    return $instance->get_timeline_entry_properties($post_id, $feed, 'latest');
                }),
                'oldest' => \Voxel\Dynamic_Data\Tag::Object('Oldest post')->properties(function() use ($post_id, $feed, $instance) {
                    return $instance->get_timeline_entry_properties($post_id, $feed, 'oldest');
                }),
            ];
        });
    }

    /**
     * Get properties for a timeline entry (latest or oldest)
     *
     * @param int $post_id Post ID
     * @param string $feed Feed type (post_reviews, post_timeline, post_wall)
     * @param string $order 'latest' or 'oldest'
     * @return array Properties array
     */
    public function get_timeline_entry_properties($post_id, $feed, $order) {
        $instance = $this;

        $properties = [
            'content' => \Voxel\Dynamic_Data\Tag::String('Content')->render(function() use ($post_id, $feed, $order, $instance) {
                $entry = $instance->get_timeline_entry($post_id, $feed, $order);
                return $entry['content'] ?? '';
            }),
            'author' => \Voxel\Dynamic_Data\Tag::String('Author')->render(function() use ($post_id, $feed, $order, $instance) {
                $entry = $instance->get_timeline_entry($post_id, $feed, $order);
                if (empty($entry)) {
                    return '';
                }

                // Check if published_as (post) exists first
                if (!empty($entry['published_as'])) {
                    $post = \Voxel\Post::get($entry['published_as']);
                    if ($post) {
                        return $post->get_title();
                    }
                }

                // Fall back to user
                if (!empty($entry['user_id'])) {
                    $user = \Voxel\User::get($entry['user_id']);
                    if ($user) {
                        return $user->get_display_name();
                    }
                }

                return '';
            }),
            'date' => \Voxel\Dynamic_Data\Tag::Date('Date')->render(function() use ($post_id, $feed, $order, $instance) {
                $entry = $instance->get_timeline_entry($post_id, $feed, $order);
                return $entry['created_at'] ?? '';
            }),
            'link' => \Voxel\Dynamic_Data\Tag::URL('Link')->render(function() use ($post_id, $feed, $order, $instance) {
                $entry = $instance->get_timeline_entry($post_id, $feed, $order);
                if (empty($entry) || empty($entry['id'])) {
                    return '';
                }

                // Get the post this timeline entry belongs to
                $post = \Voxel\Post::get($post_id);
                if (!$post) {
                    return '';
                }

                // Build link to the timeline entry
                return add_query_arg('status_id', $entry['id'], $post->get_link());
            }),
        ];

        // Add score only for reviews
        if ($feed === 'post_reviews') {
            $properties['score'] = \Voxel\Dynamic_Data\Tag::Number('Score')->render(function() use ($post_id, $feed, $order, $instance) {
                $entry = $instance->get_timeline_entry($post_id, $feed, $order);
                if (empty($entry) || !isset($entry['review_score'])) {
                    return '';
                }

                // Convert from -2..2 scale to 1..5 scale
                $score = floatval($entry['review_score']);
                return round($score + 3, 1);
            });
        }

        return $properties;
    }

    /**
     * Get a timeline entry from the database
     *
     * @param int $post_id Post ID
     * @param string $feed Feed type (post_reviews, post_timeline, post_wall)
     * @param string $order 'latest' or 'oldest'
     * @return array|null Entry data or null
     */
    public function get_timeline_entry($post_id, $feed, $order) {
        $cache_key = "{$post_id}_{$feed}_{$order}";

        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'voxel_timeline';

        $order_direction = ($order === 'oldest') ? 'ASC' : 'DESC';

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, published_as, content, review_score, created_at
             FROM {$table}
             WHERE post_id = %d
               AND feed = %s
               AND moderation = 1
             ORDER BY created_at {$order_direction}, id {$order_direction}
             LIMIT 1",
            $post_id,
            $feed
        ), ARRAY_A);

        self::$cache[$cache_key] = $result;

        return $result;
    }

    /**
     * Clear cache (useful for testing or after updates)
     */
    public static function clear_cache() {
        self::$cache = array();
    }
}
