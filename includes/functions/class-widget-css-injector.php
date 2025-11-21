<?php
/**
 * Widget CSS Injector
 *
 * Add CSS Class and ID fields to Voxel widgets
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Widget_CSS_Injector {

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
    private function __construct() {
        // Hook into Elementor to register additional controls
        add_action('elementor/element/after_section_end', array($this, 'inject_css_controls'), 10, 3);

        // Make CSS data available globally for templates
        add_action('elementor/frontend/widget/before_render', array($this, 'prepare_css_data'));

        // Add inline script to inject classes/IDs via JavaScript
        add_action('elementor/frontend/after_enqueue_scripts', array($this, 'enqueue_injector_script'));
    }

    /**
     * Inject CSS Class and ID controls into Voxel widgets
     */
    public function inject_css_controls($element, $section_id, $args) {
        // Get the widget name
        $widget_name = $element->get_name();

        // Define which widgets to target
        $target_widgets = array(
            'ts-navbar',
            'ts-user-bar',
            'ts-advanced-list',
        );

        if (!in_array($widget_name, $target_widgets)) {
            return;
        }

        // For Navbar widget - inject after the repeater items
        if ($widget_name === 'ts-navbar' && $section_id === 'ts_navbar_content') {
            $this->inject_navbar_controls($element);
        }

        // For User Bar widget - inject after appropriate section
        if ($widget_name === 'ts-user-bar' && $section_id === 'user_area_repeater') {
            $this->inject_userbar_controls($element);
        }

        // For Advanced List widget - inject after appropriate section
        if ($widget_name === 'ts-advanced-list' && $section_id === 'ts_action_content') {
            $this->inject_advanced_list_controls($element);
        }
    }

    /**
     * Inject controls for Navbar widget
     */
    private function inject_navbar_controls($element) {
        // We need to modify the existing repeater to add CSS class and ID fields
        // This requires using update_control to add fields to the existing repeater

        $repeater_control = $element->get_controls('ts_navbar_items');

        if (!$repeater_control) {
            return;
        }

        // Get the repeater instance
        $repeater = new \Elementor\Repeater();

        // Add all existing controls first
        foreach ($repeater_control['fields'] as $field_key => $field_data) {
            $repeater->add_control($field_key, $field_data);
        }

        // Add CSS Class control
        $repeater->add_control(
            'vt_item_css_class',
            array(
                'label' => __('CSS Class (VT)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('my-custom-class', 'voxel-toolkit'),
                'description' => __('Add custom CSS class to this navbar item', 'voxel-toolkit'),
                'separator' => 'before',
            )
        );

        // Add CSS ID control
        $repeater->add_control(
            'vt_item_css_id',
            array(
                'label' => __('CSS ID (VT)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('my-custom-id', 'voxel-toolkit'),
                'description' => __('Add custom CSS ID to this navbar item', 'voxel-toolkit'),
            )
        );

        // Update the repeater control
        $element->update_control(
            'ts_navbar_items',
            array(
                'fields' => $repeater->get_controls(),
            )
        );
    }

    /**
     * Inject controls for User Bar widget
     */
    private function inject_userbar_controls($element) {
        $repeater_control = $element->get_controls('ts_userbar_items');

        if (!$repeater_control) {
            return;
        }

        $repeater = new \Elementor\Repeater();

        foreach ($repeater_control['fields'] as $field_key => $field_data) {
            $repeater->add_control($field_key, $field_data);
        }

        $repeater->add_control(
            'vt_item_css_class',
            array(
                'label' => __('CSS Class (VT)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('my-custom-class', 'voxel-toolkit'),
                'description' => __('Add custom CSS class to this user bar item', 'voxel-toolkit'),
                'separator' => 'before',
            )
        );

        $repeater->add_control(
            'vt_item_css_id',
            array(
                'label' => __('CSS ID (VT)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('my-custom-id', 'voxel-toolkit'),
                'description' => __('Add custom CSS ID to this user bar item', 'voxel-toolkit'),
            )
        );

        $element->update_control(
            'ts_userbar_items',
            array(
                'fields' => $repeater->get_controls(),
            )
        );
    }

    /**
     * Inject controls for Advanced List widget
     */
    private function inject_advanced_list_controls($element) {
        $repeater_control = $element->get_controls('ts_actions');

        if (!$repeater_control) {
            return;
        }

        $repeater = new \Elementor\Repeater();

        foreach ($repeater_control['fields'] as $field_key => $field_data) {
            $repeater->add_control($field_key, $field_data);
        }

        $repeater->add_control(
            'vt_item_css_class',
            array(
                'label' => __('CSS Class (VT)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('my-custom-class', 'voxel-toolkit'),
                'description' => __('Add custom CSS class to this action item', 'voxel-toolkit'),
                'separator' => 'before',
            )
        );

        $repeater->add_control(
            'vt_item_css_id',
            array(
                'label' => __('CSS ID (VT)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('my-custom-id', 'voxel-toolkit'),
                'description' => __('Add custom CSS ID to this action item', 'voxel-toolkit'),
            )
        );

        $element->update_control(
            'ts_actions',
            array(
                'fields' => $repeater->get_controls(),
            )
        );
    }

    /**
     * Prepare CSS data and add as data attribute to widget
     */
    public function prepare_css_data($element) {
        $widget_name = $element->get_name();
        $settings = $element->get_settings_for_display();

        // Define which widgets to target and their repeater fields
        $widget_config = array(
            'ts-navbar' => 'ts_navbar_items',
            'ts-user-bar' => 'ts_userbar_items',
            'ts-advanced-list' => 'ts_actions',
        );

        if (!isset($widget_config[$widget_name])) {
            return;
        }

        $repeater_key = $widget_config[$widget_name];

        // Collect CSS data from repeater items
        if (!empty($settings[$repeater_key])) {
            $css_data = array();

            foreach ($settings[$repeater_key] as $index => $item) {
                if (!empty($item['vt_item_css_class']) || !empty($item['vt_item_css_id'])) {
                    $css_data[] = array(
                        'index' => $index,
                        'class' => !empty($item['vt_item_css_class']) ? esc_attr($item['vt_item_css_class']) : '',
                        'id' => !empty($item['vt_item_css_id']) ? esc_attr($item['vt_item_css_id']) : '',
                    );
                }
            }

            if (!empty($css_data)) {
                // Add data attribute to widget wrapper
                $element->add_render_attribute('_wrapper', 'data-vt-css', wp_json_encode($css_data));
            }
        }
    }

    /**
     * Enqueue JavaScript to inject CSS classes and IDs
     */
    public function enqueue_injector_script() {
        ?>
        <script>
        (function() {
            'use strict';

            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initCSSInjector);
            } else {
                initCSSInjector();
            }

            function initCSSInjector() {
                // Find all widgets with CSS data
                var widgets = document.querySelectorAll('[data-vt-css]');

                widgets.forEach(function(widget) {
                    var cssData = JSON.parse(widget.getAttribute('data-vt-css'));
                    if (!cssData || !cssData.length) return;

                    // Find the appropriate items based on widget type
                    var items = null;

                    // Navbar widget - select all direct li children of .ts-nav
                    if (widget.classList.contains('elementor-widget-ts-navbar')) {
                        var navContainer = widget.querySelector('.ts-nav');
                        if (navContainer) {
                            items = navContainer.querySelectorAll(':scope > li');
                        }
                    }
                    // User Bar widget - select all direct li children of .user-area-menu
                    else if (widget.classList.contains('elementor-widget-ts-user-bar')) {
                        var userMenu = widget.querySelector('.user-area-menu');
                        if (userMenu) {
                            items = userMenu.querySelectorAll(':scope > li');
                        }
                    }
                    // Advanced List widget - select all direct li children of .ts-advanced-list
                    else if (widget.classList.contains('elementor-widget-ts-advanced-list')) {
                        var actionList = widget.querySelector('.ts-advanced-list');
                        if (actionList) {
                            items = actionList.querySelectorAll(':scope > li');
                        }
                    }

                    if (!items || items.length === 0) return;

                    // Apply CSS classes and IDs to matching items
                    cssData.forEach(function(data) {
                        if (items[data.index]) {
                            if (data.class) {
                                var classes = data.class.split(' ');
                                classes.forEach(function(cls) {
                                    if (cls.trim()) {
                                        items[data.index].classList.add(cls.trim());
                                    }
                                });
                            }
                            if (data.id) {
                                items[data.index].id = data.id;
                            }
                        }
                    });
                });
            }
        })();
        </script>
        <?php
    }

    /**
     * Render settings section
     */
    public static function render_settings() {
        ?>
        <tr>
            <th scope="row">
                <label><?php _e('CSS Class & ID Injection', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <p class="description">
                    <?php _e('This feature adds CSS Class and ID fields to the following Voxel widgets:', 'voxel-toolkit'); ?>
                </p>
                <ul style="list-style: disc; margin-left: 20px; margin-top: 10px;">
                    <li><?php _e('Navbar (VX) - Adds fields to each navbar item', 'voxel-toolkit'); ?></li>
                    <li><?php _e('User Bar (VX) - Adds fields to each user bar item', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Advanced List (VX) - Adds fields to each action item', 'voxel-toolkit'); ?></li>
                </ul>
                <p class="description" style="margin-top: 10px;">
                    <?php _e('These fields allow you to add custom CSS classes and IDs to individual items within these widgets for advanced styling and targeting.', 'voxel-toolkit'); ?>
                </p>
            </td>
        </tr>
        <?php
    }
}
