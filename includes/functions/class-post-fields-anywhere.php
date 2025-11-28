<?php
/**
 * Post Fields Anywhere Function
 *
 * Enables dynamic tag to pull any post field and display it anywhere on the site.
 * Usage: @site().render_post_tag(post_id, @post(...))
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Post_Fields_Anywhere {

    /**
     * Singleton instance
     */
    private static $instance = null;

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
        // The dynamic tag is loaded and registered in class-dynamic-tags.php
        // This class only provides the settings UI
    }

    /**
     * Render settings for this function (optional)
     */
    public function render_settings($function_settings) {
        ?>
        <div class="voxel-toolkit-setting">
            <h3><?php _e('Post Fields Anywhere', 'voxel-toolkit'); ?></h3>
            <p class="description">
                <?php _e('Render any @post() dynamic tag in the context of a different post. This gives you full access to all Voxel dynamic tag features for any post on your site.', 'voxel-toolkit'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Usage', 'voxel-toolkit'); ?></th>
                    <td>
                        <code>@site().render_post_tag(post_id, @post(...))</code>
                        <p class="description" style="margin-top: 8px;">
                            <?php _e('Replace post_id with the post ID and use any @post() tag as the second argument.', 'voxel-toolkit'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Examples', 'voxel-toolkit'); ?></th>
                    <td>
                        <ul style="margin: 0; list-style: disc; padding-left: 20px;">
                            <li><code>@site().render_post_tag(123, @post(:title))</code> - <?php _e('Get post title', 'voxel-toolkit'); ?></li>
                            <li><code>@site().render_post_tag(123, @post(taxonomy))</code> - <?php _e('Get taxonomy label', 'voxel-toolkit'); ?></li>
                            <li><code>@site().render_post_tag(123, @post(taxonomy.slug))</code> - <?php _e('Get taxonomy slug', 'voxel-toolkit'); ?></li>
                            <li><code>@site().render_post_tag(123, @post(taxonomy.id))</code> - <?php _e('Get taxonomy ID', 'voxel-toolkit'); ?></li>
                            <li><code>@site().render_post_tag(123, @post(location.lng))</code> - <?php _e('Get longitude', 'voxel-toolkit'); ?></li>
                            <li><code>@site().render_post_tag(123, @post(location.lat))</code> - <?php _e('Get latitude', 'voxel-toolkit'); ?></li>
                            <li><code>@site().render_post_tag(123, @post(logo))</code> - <?php _e('Get logo/image', 'voxel-toolkit'); ?></li>
                        </ul>
                        <p class="description" style="margin-top: 12px;">
                            <strong><?php _e('With Modifiers:', 'voxel-toolkit'); ?></strong>
                        </p>
                        <ul style="margin: 4px 0 0; list-style: disc; padding-left: 20px;">
                            <li><code>@site().render_post_tag(123, @post(:title)|uppercase())</code></li>
                            <li><code>@site().render_post_tag(123, @post(logo)|default(@post(:logo)))</code></li>
                        </ul>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
}
