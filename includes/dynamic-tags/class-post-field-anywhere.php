<?php
/**
 * Post Field Anywhere Dynamic Tag
 *
 * Registers render_post_tag method on the @site() group to render any @post() tag
 * in the context of a different post.
 *
 * Usage: @site().render_post_tag(post_id, @post(...))
 * Example: @site().render_post_tag(123, @post(taxonomy.slug))
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only define the method class if the parent class exists (Voxel theme is loaded)
if (!class_exists('\Voxel\Dynamic_Data\Modifiers\Group_Methods\Base_Group_Method')) {
    return;
}

/**
 * Render Post Tag Method for Site Group
 *
 * Renders any dynamic tag expression in the context of a specific post.
 *
 * Usage: @site().render_post_tag(123, @post(taxonomy.slug))
 *        @site().render_post_tag(123, @post(location.lng))
 */
class Voxel_Toolkit_Render_Post_Tag_Method extends \Voxel\Dynamic_Data\Modifiers\Group_Methods\Base_Group_Method {

    /**
     * Get method label
     */
    public function get_label(): string {
        return 'Render Post Tag';
    }

    /**
     * Get method key
     */
    public function get_key(): string {
        return 'render_post_tag';
    }

    /**
     * Define method arguments
     */
    protected function define_args(): void {
        $this->define_arg([
            'type' => 'text',
            'label' => 'Post ID',
            'description' => 'The ID of the post to use as context',
        ]);

        $this->define_arg([
            'type' => 'text',
            'label' => 'Tag expression',
            'description' => 'The dynamic tag to render, e.g., @post(taxonomy.slug), @post(location.lng)',
        ]);
    }

    /**
     * Run the method
     */
    public function run($group) {
        // Get post ID from first argument
        $post_id = absint($this->get_arg(0));

        // Get the raw tag expression (NOT resolved - we want to render it ourselves)
        $tag_expression = isset($this->args[1]['content']) ? $this->args[1]['content'] : '';

        if (empty($post_id) || empty($tag_expression)) {
            return '';
        }

        // Get the Voxel post
        if (!class_exists('\Voxel\Post')) {
            return '';
        }

        $post = \Voxel\Post::get($post_id);
        if (!$post) {
            return '';
        }

        // Create a renderer with this post as the context
        if (!class_exists('\Voxel\Dynamic_Data\VoxelScript\Renderer')) {
            return '';
        }

        // Build the groups array with our target post
        $post_group = \Voxel\Dynamic_Data\Group::Post($post);
        $author_group = $post->get_author() ? \Voxel\Dynamic_Data\Group::User($post->get_author()) : null;

        $renderer = new \Voxel\Dynamic_Data\VoxelScript\Renderer([
            'post' => $post_group,
            'author' => $author_group,
            'site' => \Voxel\Dynamic_Data\Group::Site(),
            'user' => \Voxel\current_user() ? \Voxel\Dynamic_Data\Group::User(\Voxel\current_user()) : null,
        ]);

        // Render the tag expression in the context of the target post
        return $renderer->render($tag_expression);
    }
}
