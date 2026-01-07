<?php
/**
 * Link Management Function
 *
 * Shows warning modals when users click external links.
 * Similar to government websites that warn users when leaving the site.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Link_Management {

    /**
     * Settings instance
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = Voxel_Toolkit_Settings::instance();

        // Enqueue scripts on frontend only
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Don't load in admin or for logged-in admins if they prefer
        if (is_admin()) {
            return;
        }

        // Get settings
        $function_settings = $this->settings->get_function_settings('link_management', array());

        // Enqueue CSS
        wp_enqueue_style(
            'vt-link-management',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/link-management.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'vt-link-management',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/link-management.js',
            array(),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Parse whitelist domains
        $whitelist_raw = isset($function_settings['whitelist']) ? $function_settings['whitelist'] : '';
        $whitelist = array_filter(array_map('trim', explode("\n", $whitelist_raw)));

        // Parse exclusion selectors
        $exclusions_raw = isset($function_settings['exclusion_selectors']) ? $function_settings['exclusion_selectors'] : '';
        $exclusion_selectors = array_filter(array_map('trim', explode("\n", $exclusions_raw)));

        // Get site name for default title
        $site_name = get_bloginfo('name');

        // Default values
        $default_title = sprintf(__("You're leaving %s", 'voxel-toolkit'), $site_name);
        $default_message = __('You are about to leave this website and visit an external site. We are not responsible for the content or privacy practices of external sites.', 'voxel-toolkit');

        // Default colors and styles
        $styles = array(
            'modal_bg' => isset($function_settings['modal_bg']) ? $function_settings['modal_bg'] : '#ffffff',
            'title_color' => isset($function_settings['title_color']) ? $function_settings['title_color'] : '#1e293b',
            'message_color' => isset($function_settings['message_color']) ? $function_settings['message_color'] : '#64748b',
            'icon_bg' => isset($function_settings['icon_bg']) ? $function_settings['icon_bg'] : '#fef3c7',
            'icon_color' => isset($function_settings['icon_color']) ? $function_settings['icon_color'] : '#d97706',
            'continue_bg' => isset($function_settings['continue_bg']) ? $function_settings['continue_bg'] : '#3b82f6',
            'continue_text_color' => isset($function_settings['continue_text_color']) ? $function_settings['continue_text_color'] : '#ffffff',
            'continue_border_color' => isset($function_settings['continue_border_color']) && !empty($function_settings['continue_border_color']) ? $function_settings['continue_border_color'] : 'transparent',
            'cancel_bg' => isset($function_settings['cancel_bg']) ? $function_settings['cancel_bg'] : '#f1f5f9',
            'cancel_text_color' => isset($function_settings['cancel_text_color']) ? $function_settings['cancel_text_color'] : '#475569',
            'cancel_border_color' => isset($function_settings['cancel_border_color']) && !empty($function_settings['cancel_border_color']) ? $function_settings['cancel_border_color'] : 'transparent',
            'button_border_radius' => isset($function_settings['button_border_radius']) ? $function_settings['button_border_radius'] : '8',
            'button_border_width' => isset($function_settings['button_border_width']) ? $function_settings['button_border_width'] : '0',
        );

        // Add inline CSS for custom colors and styles
        $custom_css = ':root {
            --vt-lm-modal-bg: ' . esc_attr($styles['modal_bg']) . ';
            --vt-lm-title-color: ' . esc_attr($styles['title_color']) . ';
            --vt-lm-message-color: ' . esc_attr($styles['message_color']) . ';
            --vt-lm-icon-bg: ' . esc_attr($styles['icon_bg']) . ';
            --vt-lm-icon-color: ' . esc_attr($styles['icon_color']) . ';
            --vt-lm-continue-bg: ' . esc_attr($styles['continue_bg']) . ';
            --vt-lm-continue-text: ' . esc_attr($styles['continue_text_color']) . ';
            --vt-lm-continue-border-color: ' . esc_attr($styles['continue_border_color']) . ';
            --vt-lm-cancel-bg: ' . esc_attr($styles['cancel_bg']) . ';
            --vt-lm-cancel-text: ' . esc_attr($styles['cancel_text_color']) . ';
            --vt-lm-cancel-border-color: ' . esc_attr($styles['cancel_border_color']) . ';
            --vt-lm-button-border-radius: ' . esc_attr($styles['button_border_radius']) . 'px;
            --vt-lm-button-border-width: ' . esc_attr($styles['button_border_width']) . 'px;
        }';
        wp_add_inline_style('vt-link-management', $custom_css);

        // Localize script with settings
        wp_localize_script('vt-link-management', 'vt_link_management', array(
            'title' => isset($function_settings['title']) && !empty($function_settings['title'])
                ? $function_settings['title']
                : $default_title,
            'message' => isset($function_settings['message']) && !empty($function_settings['message'])
                ? $function_settings['message']
                : $default_message,
            'continue_text' => isset($function_settings['continue_text']) && !empty($function_settings['continue_text'])
                ? $function_settings['continue_text']
                : __('Continue', 'voxel-toolkit'),
            'cancel_text' => isset($function_settings['cancel_text']) && !empty($function_settings['cancel_text'])
                ? $function_settings['cancel_text']
                : __('Go Back', 'voxel-toolkit'),
            'show_url' => !empty($function_settings['show_url']),
            'whitelist' => $whitelist,
            'exclusion_selectors' => $exclusion_selectors,
            'current_host' => wp_parse_url(home_url(), PHP_URL_HOST),
        ));
    }
}
