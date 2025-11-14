<?php
/**
 * Reading Time Dynamic Property
 *
 * Calculates estimated reading time for post content
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reading Time Property for Post Group
 */
class Voxel_Toolkit_Reading_Time_Property {

    /**
     * Register the property
     */
    public static function register() {
        return [
            'type' => 'string',
            'label' => 'Reading time',
            'callback' => function($post_group) {
                return self::calculate_reading_time($post_group);
            },
        ];
    }

    /**
     * Calculate reading time
     *
     * @param object $post_group The post group object
     * @return string Reading time text
     */
    private static function calculate_reading_time($post_group) {
        // Get the post object
        $post = null;
        if (isset($post_group->post) && method_exists($post_group->post, 'get_wp_post')) {
            $post = $post_group->post->get_wp_post();
        } elseif (isset($post_group->post) && $post_group->post instanceof \WP_Post) {
            $post = $post_group->post;
        }

        if (!$post) {
            return '0 min read';
        }

        // Get post content
        $content = $post->post_content;

        // Strip shortcodes and HTML tags
        $content = strip_shortcodes($content);
        $content = wp_strip_all_tags($content);

        // Count words
        $word_count = str_word_count($content);

        // Calculate reading time (average reading speed: 200 words per minute)
        $reading_time = ceil($word_count / 200);

        // Format output
        if ($reading_time < 1) {
            return '< 1 min read';
        } elseif ($reading_time === 1) {
            return '1 min read';
        } else {
            return $reading_time . ' min read';
        }
    }
}
