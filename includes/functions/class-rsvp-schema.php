<?php
/**
 * RSVP Schema Storage
 *
 * Stores and retrieves RSVP form field schemas per post type.
 * Schemas are automatically saved when RSVP form widgets render,
 * and used to generate dynamic tags in app events.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_RSVP_Schema {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Option key for storing schemas
     */
    private $option_key = 'voxel_toolkit_rsvp_field_schemas';

    /**
     * Cached schemas
     */
    private $schemas = null;

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
     * Private constructor
     */
    private function __construct() {
        // Load schemas on first use
    }

    /**
     * Get all schemas
     *
     * @return array All post type schemas
     */
    public function get_all_schemas() {
        if ($this->schemas === null) {
            $this->schemas = get_option($this->option_key, []);
            if (!is_array($this->schemas)) {
                $this->schemas = [];
            }
        }
        return $this->schemas;
    }

    /**
     * Get schema for a specific post type
     *
     * @param string $post_type Post type key
     * @return array Field definitions with keys: key, label, type, required
     */
    public function get_schema($post_type) {
        $schemas = $this->get_all_schemas();
        return isset($schemas[$post_type]) ? $schemas[$post_type] : [];
    }

    /**
     * Update schema for a post type
     *
     * Uses merge strategy: keeps all unique field keys from new + existing.
     * This handles cases where multiple widgets have different fields.
     *
     * @param string $post_type Post type key
     * @param array $fields Field definitions
     * @return bool Whether update was successful
     */
    public function update_schema($post_type, $fields) {
        if (empty($post_type) || !is_array($fields)) {
            return false;
        }

        $schemas = $this->get_all_schemas();

        // Get existing fields for this post type
        $existing = isset($schemas[$post_type]) ? $schemas[$post_type] : [];

        // Merge: new fields override existing with same key, but keep other existing fields
        $merged = array_merge($existing, $fields);

        $schemas[$post_type] = $merged;
        $this->schemas = $schemas;

        // Force save by deleting first, then adding
        delete_option($this->option_key);
        return add_option($this->option_key, $schemas, '', false);
    }

    /**
     * Replace schema for a post type (no merge)
     *
     * @param string $post_type Post type key
     * @param array $fields Field definitions
     * @return bool Whether update was successful
     */
    public function replace_schema($post_type, $fields) {
        if (empty($post_type) || !is_array($fields)) {
            return false;
        }

        $schemas = $this->get_all_schemas();
        $schemas[$post_type] = $fields;
        $this->schemas = $schemas;

        return update_option($this->option_key, $schemas);
    }

    /**
     * Clear schema for a post type
     *
     * @param string $post_type Post type key
     * @return bool Whether clear was successful
     */
    public function clear_schema($post_type) {
        $schemas = $this->get_all_schemas();

        if (isset($schemas[$post_type])) {
            unset($schemas[$post_type]);
            $this->schemas = $schemas;
            return update_option($this->option_key, $schemas);
        }

        return true;
    }

    /**
     * Clear all schemas
     *
     * @return bool Whether clear was successful
     */
    public function clear_all_schemas() {
        $this->schemas = [];
        return delete_option($this->option_key);
    }

    /**
     * Check if a post type has a schema
     *
     * @param string $post_type Post type key
     * @return bool Whether schema exists
     */
    public function has_schema($post_type) {
        $schemas = $this->get_all_schemas();
        return !empty($schemas[$post_type]);
    }
}
