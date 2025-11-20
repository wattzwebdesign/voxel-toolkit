<?php
/**
 * Show Field Description functionality
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Show_Field_Description {
    
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
        // This runs on both frontend AND Elementor preview (preview uses wp_footer too)
        add_action('wp_footer', array($this, 'add_field_description_script'));

        // Add controls to Voxel's create post widget - inject into existing style sections
        add_action('elementor/element/before_section_end', array($this, 'add_elementor_controls'), 10, 3);
    }
    
    /**
     * Get field descriptions from Voxel post type configuration
     *
     * @param string $post_type_key The post type key
     * @return array Array of field descriptions keyed by field key
     */
    private function get_voxel_field_descriptions($post_type_key) {
        $post_types = get_option('voxel:post_types', array());

        if (!isset($post_types[$post_type_key]['fields'])) {
            return array();
        }

        $descriptions = array();
        foreach ($post_types[$post_type_key]['fields'] as $field) {
            if (!empty($field['description']) && !empty($field['key'])) {
                $descriptions[$field['key']] = $field['description'];
            }
        }

        return $descriptions;
    }

    /**
     * Add field description script and styles to footer
     */
    public function add_field_description_script() {
        ?>
        <style>
            /* Hide all tooltip icons with dialogs */
            .create-post-form .vx-dialog {
                display: none !important;
            }

            /* Style for subtitle (text under label) - defaults, can be overridden by Elementor widget controls */
            .create-post-form .vx-subtitle {
                margin-top: -5px;
                margin-bottom: 10px;
                font-size: 0.9em;
                font-weight: 400;
                line-height: 1.5;
                color: #666666;
            }

            /* For switcher fields, the subtitle appears after the label */
            .create-post-form .switcher-label .vx-subtitle {
                display: block;
                margin-top: 5px;
                margin-bottom: 10px;
                margin-left: 0;
            }
        </style>
        <script>
            (function() {
                function processFieldDescriptions() {
                    const fields = document.querySelectorAll(".ts-form-group");

                    fields.forEach(function(field) {
                        // Skip if already processed
                        if (field.querySelector(".vx-subtitle")) {
                            return;
                        }

                        const dialogContent = field.querySelector(".vx-dialog-content");
                        const label = field.querySelector("label");

                        // If there's a description in the dialog content, convert it to subtitle
                        if (dialogContent && dialogContent.innerHTML.trim() && label) {
                            const subtitle = document.createElement("div");
                            subtitle.classList.add("vx-subtitle");
                            subtitle.innerHTML = dialogContent.innerHTML;

                            // For switcher fields, insert after the entire label element
                            if (field.classList.contains("switcher-label")) {
                                // Insert subtitle after the label, outside of it
                                label.insertAdjacentElement("afterend", subtitle);
                            } else {
                                // For regular fields, insert after label
                                label.insertAdjacentElement("afterend", subtitle);
                            }

                            // Hide the original tooltip icon
                            if (dialogContent.parentElement) {
                                dialogContent.parentElement.style.display = "none";
                            }
                        }
                    });
                }

                // Run on DOM load
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', processFieldDescriptions);
                } else {
                    processFieldDescriptions();
                }

                // Watch for Elementor frontend widget loads
                if (window.elementorFrontend) {
                    elementorFrontend.hooks.addAction('frontend/element_ready/widget', function() {
                        setTimeout(processFieldDescriptions, 100);
                    });
                }

                // Elementor Editor Preview Support
                const isElementorEditor = window.self !== window.top;
                if (isElementorEditor) {
                    // Poll every 500ms for up to 15 seconds to catch when Voxel loads the form
                    let pollCount = 0;
                    const maxPolls = 30;

                    const pollForForm = setInterval(function() {
                        pollCount++;
                        const fields = document.querySelectorAll(".ts-form-group");

                        if (fields.length > 0) {
                            processFieldDescriptions();
                            clearInterval(pollForForm);
                        } else if (pollCount >= maxPolls) {
                            clearInterval(pollForForm);
                        }
                    }, 500);
                }
            })();
        </script>
        <?php
    }

    /**
     * Add styling controls to Voxel's Create Post and Login/Register widgets
     *
     * @param object $section The section instance
     * @param string $section_id The section ID
     * @param array $args Section arguments
     */
    public function add_elementor_controls($section, $section_id, $args) {
        $widget_name = $section->get_name();

        // Only add controls to Voxel's create-post and login widgets
        if ($widget_name !== 'ts-create-post' && $widget_name !== 'ts-login') {
            return;
        }

        // Add to the fields general styling section for create-post widget
        if ($widget_name === 'ts-create-post' && $section_id !== 'ts_sf1_fields_general') {
            return;
        }

        // Add to the Form: Input & Textarea section for login widget (in Field style tab)
        if ($widget_name === 'ts-login' && $section_id !== 'ts_sf_intxt') {
            return;
        }

        // Add separator for our controls
        $section->add_control(
            'vt_field_desc_separator',
            array(
                'type' => \Elementor\Controls_Manager::DIVIDER,
            )
        );

        // Heading
        $section->add_control(
            'vt_field_desc_heading',
            array(
                'label' => __('Field Description Style (VT)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
            )
        );

        // Info notice
        $section->add_control(
            'vt_field_desc_notice',
            array(
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => __('<div style="padding: 10px; background: #e8f5e9; border-left: 3px solid #4caf50; margin-bottom: 15px; font-size: 12px;"><strong>✓ Field Descriptions Active:</strong> Descriptions from your Voxel post type configuration will appear below field labels. If a field has a description in the Voxel backend, it will show in both the editor preview and frontend.</div>', 'voxel-toolkit'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            )
        );

        // Color control
        $section->add_control(
            'vt_field_desc_color',
            array(
                'label' => __('Description Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vx-subtitle' => 'color: {{VALUE}};',
                ),
            )
        );

        // Typography group control
        $section->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'vt_field_desc_typography',
                'label' => __('Description Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vx-subtitle',
            )
        );

        // Margin top
        $section->add_responsive_control(
            'vt_field_desc_margin_top',
            array(
                'label' => __('Description Margin Top', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', 'em'),
                'range' => array(
                    'px' => array(
                        'min' => -50,
                        'max' => 50,
                    ),
                    'em' => array(
                        'min' => -5,
                        'max' => 5,
                        'step' => 0.1,
                    ),
                ),
                'default' => array(
                    'unit' => 'px',
                    'size' => -5,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vx-subtitle' => 'margin-top: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        // Margin bottom
        $section->add_responsive_control(
            'vt_field_desc_margin_bottom',
            array(
                'label' => __('Description Margin Bottom', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', 'em'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 50,
                    ),
                    'em' => array(
                        'min' => 0,
                        'max' => 5,
                        'step' => 0.1,
                    ),
                ),
                'default' => array(
                    'unit' => 'px',
                    'size' => 10,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vx-subtitle' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ),
            )
        );
    }
}