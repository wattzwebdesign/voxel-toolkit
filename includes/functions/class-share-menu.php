<?php
/**
 * Share Menu Function
 *
 * Adds additional share options to the Voxel share menu
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Share_Menu {

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
        add_filter('voxel/share-links', array($this, 'add_share_links'), 10);
    }

    /**
     * Add additional share links
     *
     * @param array $links Existing share links
     * @return array Modified share links
     */
    public function add_share_links($links) {
        // Pinterest
        $links['pinterest'] = array(
            'label' => 'Pinterest',
            'icon' => function() {
                return $this->get_icon('pinterest');
            },
            'link' => function($details) {
                return add_query_arg(array(
                    'url' => $details['link'],
                    'description' => $details['title'],
                ), 'https://pinterest.com/pin/create/button/');
            },
        );

        // Email
        $links['email'] = array(
            'label' => 'Email',
            'icon' => function() {
                return $this->get_icon('email');
            },
            'link' => function($details) {
                return 'mailto:?' . http_build_query(array(
                    'subject' => $details['title'],
                    'body' => $details['link'],
                ), '', '&');
            },
        );

        // Threads
        $links['threads'] = array(
            'label' => 'Threads',
            'icon' => function() {
                return $this->get_icon('threads');
            },
            'link' => function($details) {
                return add_query_arg(array(
                    'text' => $details['title'] . ' ' . $details['link'],
                ), 'https://www.threads.net/intent/post');
            },
        );

        // Bluesky
        $links['bluesky'] = array(
            'label' => 'Bluesky',
            'icon' => function() {
                return $this->get_icon('bluesky');
            },
            'link' => function($details) {
                return add_query_arg(array(
                    'text' => $details['title'] . ' ' . $details['link'],
                ), 'https://bsky.app/intent/compose');
            },
        );

        // SMS
        $links['sms'] = array(
            'label' => 'SMS',
            'icon' => function() {
                return $this->get_icon('sms');
            },
            'link' => function($details) {
                return 'sms:?body=' . rawurlencode($details['title'] . ' ' . $details['link']);
            },
        );

        // Line
        $links['line'] = array(
            'label' => 'Line',
            'icon' => function() {
                return $this->get_icon('line');
            },
            'link' => function($details) {
                return add_query_arg(array(
                    'url' => $details['link'],
                ), 'https://social-plugins.line.me/lineit/share');
            },
        );

        // Viber
        $links['viber'] = array(
            'label' => 'Viber',
            'icon' => function() {
                return $this->get_icon('viber');
            },
            'link' => function($details) {
                return 'viber://forward?text=' . rawurlencode($details['title'] . ' ' . $details['link']);
            },
        );

        // Snapchat
        $links['snapchat'] = array(
            'label' => 'Snapchat',
            'icon' => function() {
                return $this->get_icon('snapchat');
            },
            'link' => function($details) {
                return add_query_arg(array(
                    'url' => $details['link'],
                ), 'https://www.snapchat.com/share');
            },
        );

        // KakaoTalk
        $links['kakaotalk'] = array(
            'label' => 'KakaoTalk',
            'icon' => function() {
                return $this->get_icon('kakaotalk');
            },
            'link' => function($details) {
                return add_query_arg(array(
                    'url' => $details['link'],
                ), 'https://story.kakao.com/share');
            },
        );

        return $links;
    }

    /**
     * Get icon SVG for a share platform
     *
     * @param string $platform Platform key
     * @return string SVG icon markup
     */
    private function get_icon($platform) {
        // Email uses Voxel's envelope icon
        if ($platform === 'email' && function_exists('\Voxel\get_svg')) {
            return \Voxel\get_svg('envelope.svg');
        }

        // Map platform to toolkit icon file
        $icon_map = array(
            'pinterest' => 'pinterest.svg',
            'threads' => 'threads.svg',
            'bluesky' => 'bluesky.svg',
            'sms' => 'sms.svg',
            'line' => 'line.svg',
            'viber' => 'viber.svg',
            'snapchat' => 'snapchat.svg',
            'kakaotalk' => 'kakao-talk.svg',
        );

        if (isset($icon_map[$platform])) {
            $icon_path = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/icons/share/' . $icon_map[$platform];
            if (file_exists($icon_path)) {
                return file_get_contents($icon_path);
            }
        }

        // Fallback to Voxel's share icon
        if (function_exists('\Voxel\get_svg')) {
            return \Voxel\get_svg('share.svg');
        }

        return '';
    }
}
