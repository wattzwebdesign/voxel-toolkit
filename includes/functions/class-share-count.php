<?php
/**
 * Share Count Function
 *
 * Tracks the number of times a post has been shared via the share menu
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Share_Count {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register AJAX handlers for share tracking
        add_action('wp_ajax_vt_track_share', array($this, 'handle_track_share'));
        add_action('wp_ajax_nopriv_vt_track_share', array($this, 'handle_track_share'));

        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Register the dynamic tag
        add_filter('voxel/dynamic-data/groups/post/properties', array($this, 'register_dynamic_tag'), 10, 2);
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        $js_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/js/share-count.js';

        wp_enqueue_script(
            'vt-share-count',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/share-count.js',
            array(),
            file_exists($js_file) ? filemtime($js_file) : VOXEL_TOOLKIT_VERSION,
            true
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script('vt-share-count', 'vtShareCount', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_share_count_nonce'),
        ));
    }

    /**
     * Handle AJAX share tracking
     */
    public function handle_track_share() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vt_share_count_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'voxel-toolkit')));
            return;
        }

        // Get post ID and share network
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $network = isset($_POST['network']) ? sanitize_text_field($_POST['network']) : '';

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'voxel-toolkit')));
            return;
        }

        // Verify post exists
        if (!get_post($post_id)) {
            wp_send_json_error(array('message' => __('Post not found', 'voxel-toolkit')));
            return;
        }

        // Increment share count
        $current_count = get_post_meta($post_id, '_vt_share_count', true);
        $current_count = $current_count ? intval($current_count) : 0;
        $new_count = $current_count + 1;

        update_post_meta($post_id, '_vt_share_count', $new_count);

        // Optionally track by network
        if ($network) {
            $network_key = '_vt_share_count_' . sanitize_key($network);
            $network_count = get_post_meta($post_id, $network_key, true);
            $network_count = $network_count ? intval($network_count) : 0;
            update_post_meta($post_id, $network_key, $network_count + 1);
        }

        wp_send_json_success(array(
            'count' => $new_count,
            'network' => $network,
        ));
    }

    /**
     * Register the dynamic tag for share count
     *
     * @param array $properties Existing properties
     * @param object $group The property group
     * @return array Modified properties
     */
    public function register_dynamic_tag($properties, $group) {
        // Add share count as an object with total and per-network counts
        // Accessible as @post(share_count) for total, @post(share_count.facebook) for network-specific
        $properties['share_count'] = \Voxel\Dynamic_Data\Tag::Object('Share Count')->properties(function() use ($group) {
            $network_properties = [];

            // Total count - accessible as @post(share_count) or @post(share_count.total)
            $network_properties['total'] = \Voxel\Dynamic_Data\Tag::Number('Total Shares')
                ->render(function() use ($group) {
                    if (!$group->post || !$group->post->get_id()) {
                        return 0;
                    }
                    $count = get_post_meta($group->post->get_id(), '_vt_share_count', true);
                    return intval($count ? $count : 0);
                });

            // Define all trackable networks
            $networks = array(
                'facebook' => 'Facebook',
                'twitter' => 'X/Twitter',
                'linkedin' => 'LinkedIn',
                'reddit' => 'Reddit',
                'tumblr' => 'Tumblr',
                'whatsapp' => 'WhatsApp',
                'telegram' => 'Telegram',
                'pinterest' => 'Pinterest',
                'threads' => 'Threads',
                'bluesky' => 'Bluesky',
                'sms' => 'SMS',
                'line' => 'Line',
                'viber' => 'Viber',
                'snapchat' => 'Snapchat',
                'kakaotalk' => 'KakaoTalk',
                'email' => 'Email',
                'copy-link' => 'Copy Link',
                'native-share' => 'Native Share',
            );

            // Add each network as a property
            foreach ($networks as $key => $label) {
                $network_properties[$key] = \Voxel\Dynamic_Data\Tag::Number($label . ' Shares')
                    ->render(function() use ($group, $key) {
                        if (!$group->post || !$group->post->get_id()) {
                            return 0;
                        }
                        $meta_key = '_vt_share_count_' . sanitize_key($key);
                        $count = get_post_meta($group->post->get_id(), $meta_key, true);
                        return intval($count ? $count : 0);
                    });
            }

            return $network_properties;
        });

        return $properties;
    }

    /**
     * Get share count for a post
     *
     * @param int $post_id Post ID
     * @return int Share count
     */
    public static function get_share_count($post_id) {
        $count = get_post_meta($post_id, '_vt_share_count', true);
        return intval($count ? $count : 0);
    }

    /**
     * Get share count by network for a post
     *
     * @param int $post_id Post ID
     * @param string $network Network key (e.g., 'facebook', 'twitter')
     * @return int Share count for that network
     */
    public static function get_share_count_by_network($post_id, $network) {
        $network_key = '_vt_share_count_' . sanitize_key($network);
        $count = get_post_meta($post_id, $network_key, true);
        return intval($count ? $count : 0);
    }
}
