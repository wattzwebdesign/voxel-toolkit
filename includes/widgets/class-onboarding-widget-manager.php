<?php
/**
 * Onboarding Widget Manager
 *
 * Handles registration and initialization of the Onboarding widget
 *
 * @package Voxel_Toolkit
 */

namespace Voxel_Toolkit\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

class Onboarding_Widget_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('elementor/editor/before_enqueue_scripts', array($this, 'enqueue_editor_scripts'));
    }

    /**
     * Register the Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-onboarding-widget.php';
        $widgets_manager->register(new \Voxel_Toolkit\Widgets\Onboarding_Widget());
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on pages that have the onboarding widget
        if (!$this->page_has_onboarding_widget()) {
            return;
        }

        // Intro.js CSS
        wp_enqueue_style(
            'introjs',
            'https://cdn.jsdelivr.net/npm/intro.js@7.2.0/minified/introjs.min.css',
            array(),
            '7.2.0'
        );

        // Custom onboarding widget CSS
        $css_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/css/onboarding-widget.css';
        wp_enqueue_style(
            'voxel-onboarding-widget',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/onboarding-widget.css',
            array('introjs'),
            file_exists($css_file) ? filemtime($css_file) : VOXEL_TOOLKIT_VERSION
        );

        // Intro.js JavaScript
        wp_enqueue_script(
            'introjs',
            'https://cdn.jsdelivr.net/npm/intro.js@7.2.0/intro.min.js',
            array(),
            '7.2.0',
            true
        );

        // Custom onboarding widget JavaScript
        $js_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/js/onboarding-widget.js';
        wp_enqueue_script(
            'voxel-onboarding-widget',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/onboarding-widget.js',
            array('jquery', 'introjs'),
            file_exists($js_file) ? filemtime($js_file) : VOXEL_TOOLKIT_VERSION,
            true
        );
    }

    /**
     * Enqueue editor scripts
     */
    public function enqueue_editor_scripts() {
        $js_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/js/onboarding-widget-editor.js';
        wp_enqueue_script(
            'voxel-onboarding-widget-editor',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/onboarding-widget-editor.js',
            array('jquery', 'elementor-editor'),
            file_exists($js_file) ? filemtime($js_file) : VOXEL_TOOLKIT_VERSION,
            true
        );
    }

    /**
     * Check if current page has the onboarding widget
     */
    private function page_has_onboarding_widget() {
        global $post;

        if (!$post) {
            return false;
        }

        // Check if Elementor data contains our widget
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        return $elementor_data && strpos($elementor_data, 'voxel-onboarding') !== false;
    }
}
