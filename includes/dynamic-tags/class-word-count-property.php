<?php
/**
 * Word Count Dynamic Property
 *
 * Counts total words in post content
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Word Count Property for Post Group
 */
class Voxel_Toolkit_Word_Count_Property {

    /**
     * Register the property
     */
    public static function register() {
        return [
            'type' => 'number',
            'label' => 'Word count',
            'callback' => function($post_group) {
                return self::calculate_word_count($post_group);
            },
        ];
    }

    /**
     * Calculate word count
     *
     * @param object $post_group The post group object
     * @return int Word count
     */
    private static function calculate_word_count($post_group) {
        // Get the post object
        $post = null;
        if (isset($post_group->post) && method_exists($post_group->post, 'get_wp_post')) {
            $post = $post_group->post->get_wp_post();
        } elseif (isset($post_group->post) && $post_group->post instanceof \WP_Post) {
            $post = $post_group->post;
        }

        if (!$post) {
            return 0;
        }

        // Get post content
        $content = $post->post_content;

        // Strip shortcodes and HTML tags
        $content = strip_shortcodes($content);
        $content = wp_strip_all_tags($content);

        // Count words
        $word_count = str_word_count($content);

        return $word_count;
    }
}
