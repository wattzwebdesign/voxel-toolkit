<?php
/**
 * Tally Modifier
 *
 * Count published posts for a post type
 * Usage: @site(post_types.member.singular).tally
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tally Modifier - Count published posts for a post type
 */
class Voxel_Toolkit_Tally_Modifier extends \Voxel\Dynamic_Data\Modifiers\Base_Modifier {

    /**
     * Get modifier label
     */
    public function get_label(): string {
        return 'Post type count';
    }

    /**
     * Get modifier key
     */
    public function get_key(): string {
        return 'tally';
    }

    /**
     * Get modifier description
     */
    public function get_description(): string {
        return 'Count published posts in a post type. Use with @site(post_types.{type}.singular).tally or @site(post_types.{type}.plural).tally';
    }

    /**
     * Apply the modifier
     *
     * @param mixed $value The value being modified (could be post type name, etc.)
     * @return string Number of published posts
     */
    public function apply($value) {
        // Try to detect post type from the tag chain
        $post_type_key = $this->get_post_type_from_tag();

        if (!$post_type_key) {
            // Fallback: if value looks like a post type key, use it
            if (is_string($value) && post_type_exists($value)) {
                $post_type_key = $value;
            }
        }

        if (!$post_type_key) {
            return '0';
        }

        $count = $this->get_published_count($post_type_key);

        return (string) $count;
    }

    /**
     * Get post type key from the tag chain
     */
    private function get_post_type_from_tag() {
        if (!isset($this->tag) || !$this->tag) {
            return null;
        }

        // Try get_property_path() - returns array like ['post_types', 'member', 'singular']
        if (method_exists($this->tag, 'get_property_path')) {
            $property_path = $this->tag->get_property_path();
            if (is_array($property_path) && count($property_path) >= 2) {
                // Look for post_types in the path
                $post_types_index = array_search('post_types', $property_path);
                if ($post_types_index !== false && isset($property_path[$post_types_index + 1])) {
                    return $property_path[$post_types_index + 1];
                }
            }
        }

        return null;
    }

    /**
     * Parse post type from tag ID
     * Format: "site:post_types:member:singular"
     */
    private function parse_post_type_from_id($tag_string) {
        $parts = explode(':', $tag_string);

        // Look for post_types in the chain
        $post_types_index = array_search('post_types', $parts);

        if ($post_types_index !== false && isset($parts[$post_types_index + 1])) {
            return $parts[$post_types_index + 1];
        }

        return null;
    }

    /**
     * Parse post type from tag string representation
     * Format: "@site(post_types.member.singular)"
     */
    private function parse_post_type_from_string($tag_string) {
        // Try to match @site(post_types.{key}.something)
        if (preg_match('/@site\(post_types\.([^.)]+)/', $tag_string, $matches)) {
            return $matches[1];
        }

        // Try to match post_types.{key} pattern
        if (preg_match('/post_types\.([^.)]+)/', $tag_string, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get count of published posts for a post type
     *
     * @param string $post_type_key Post type key
     * @return int Number of published posts
     */
    private function get_published_count($post_type_key) {
        // Clean the post type key
        $post_type_key = sanitize_key($post_type_key);

        // Verify the post type exists
        if (!post_type_exists($post_type_key)) {
            return 0;
        }

        // Use wp_count_posts for efficient counting
        $counts = wp_count_posts($post_type_key);

        if (!$counts || !isset($counts->publish)) {
            return 0;
        }

        return (int) $counts->publish;
    }
}
