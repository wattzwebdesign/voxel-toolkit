<?php
/**
 * Enhanced Post Relation Function
 *
 * Customizes the display of posts in Post Relation field selectors
 * using dynamic tag templates per post type.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Enhanced_Post_Relation {

    private $settings;
    private $options = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = Voxel_Toolkit_Settings::instance();
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * Load settings
     */
    private function load_settings() {
        $this->options = $this->settings->get_function_settings('enhanced_post_relation', array(
            'enabled' => false,
            'post_type_settings' => array()
        ));

        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }

    /**
     * Handle settings update
     */
    public function on_settings_updated($old_settings, $new_settings) {
        if (isset($new_settings['enhanced_post_relation'])) {
            $this->options = $new_settings['enhanced_post_relation'];
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Hook early into Voxel's custom AJAX action for relations
        // Voxel uses custom endpoint ?vx=1 with voxel_ajax_{action} hooks
        add_action('voxel_ajax_create_post.relations.get_posts', array($this, 'intercept_relations_ajax'), 1);
        add_action('voxel_ajax_nopriv_create_post.relations.get_posts', array($this, 'intercept_relations_ajax'), 1);
    }

    /**
     * Intercept the relations AJAX request
     */
    public function intercept_relations_ajax() {
        // Check if any post type has enhanced display enabled
        $has_enabled_post_type = false;
        if (!empty($this->options['post_type_settings'])) {
            foreach ($this->options['post_type_settings'] as $pt_key => $pt_settings) {
                if (!empty($pt_settings['enabled']) && !empty($pt_settings['display_template'])) {
                    $has_enabled_post_type = true;
                    break;
                }
            }
        }

        if (!$has_enabled_post_type) {
            return;
        }

        // Start output buffering with callback
        ob_start(array($this, 'modify_json_response'));
    }

    /**
     * Modify the JSON response from Voxel's relations endpoint
     */
    public function modify_json_response($output) {
        // Try to decode as JSON
        $data = json_decode($output, true);

        // If not valid JSON or no data array, return original
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['data']) || !is_array($data['data'])) {
            return $output;
        }

        // Process each post in the response
        $modified = false;
        foreach ($data['data'] as &$post_data) {
            $post_id = isset($post_data['id']) ? absint($post_data['id']) : 0;

            if (!$post_id) {
                continue;
            }

            // Get the actual post type from the post
            $wp_post = get_post($post_id);
            if (!$wp_post) {
                continue;
            }

            $post_type = $wp_post->post_type;

            // Check if we have settings for this post type
            if (empty($this->options['post_type_settings'][$post_type]['enabled'])) {
                continue;
            }

            $template = isset($this->options['post_type_settings'][$post_type]['display_template'])
                ? $this->options['post_type_settings'][$post_type]['display_template']
                : '';

            if (empty($template)) {
                continue;
            }

            // Render the template for this post
            $rendered = $this->render_template_for_post($post_id, $template);

            if (!empty($rendered)) {
                $post_data['title'] = $rendered;
                $modified = true;
            }
        }

        // Return modified JSON if we made changes
        if ($modified) {
            return wp_json_encode($data);
        }

        return $output;
    }

    /**
     * Render dynamic tag template in context of specific post
     */
    private function render_template_for_post($post_id, $template) {
        // Check if Voxel classes exist
        if (!class_exists('\Voxel\Post')) {
            return '';
        }

        $post = \Voxel\Post::get($post_id);
        if (!$post) {
            return '';
        }

        // Check if VoxelScript Renderer exists
        if (!class_exists('\Voxel\Dynamic_Data\VoxelScript\Renderer') || !class_exists('\Voxel\Dynamic_Data\Group')) {
            // Fall back to simple render function if available
            if (function_exists('\Voxel\render')) {
                return \Voxel\render($template);
            }
            return '';
        }

        // Build the groups array with our target post context
        $post_group = \Voxel\Dynamic_Data\Group::Post($post);
        $author_group = $post->get_author()
            ? \Voxel\Dynamic_Data\Group::User($post->get_author())
            : null;

        $groups = array(
            'post' => $post_group,
            'site' => \Voxel\Dynamic_Data\Group::Site(),
        );

        if ($author_group) {
            $groups['author'] = $author_group;
        }

        // Add current user context if available
        if (function_exists('\Voxel\current_user') && \Voxel\current_user()) {
            $groups['user'] = \Voxel\Dynamic_Data\Group::User(\Voxel\current_user());
        }

        // Create renderer with post context
        $renderer = new \Voxel\Dynamic_Data\VoxelScript\Renderer($groups);

        // Render the template
        try {
            $result = $renderer->render($template);
        } catch (\Exception $e) {
            return '';
        }

        // Strip any HTML tags for cleaner display in dropdown
        $result = wp_strip_all_tags($result);

        // Trim whitespace
        $result = trim($result);

        return $result;
    }

    /**
     * Get available post types for settings
     */
    public static function get_available_post_types() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $available = array();

        foreach ($post_types as $post_type) {
            if (in_array($post_type->name, array('attachment', 'page'))) {
                continue;
            }
            $available[$post_type->name] = $post_type->labels->name;
        }

        return $available;
    }
}
