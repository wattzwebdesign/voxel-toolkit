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
        add_action('wp_footer', array($this, 'add_field_description_script'));
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
     
            /* Style for subtitle (text under label) */
            .create-post-form .vx-subtitle {
                margin-top: -5px;
                margin-bottom: 10px;
                font-size: 0.9em;
                color: #666;
            }
        </style>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const form = document.querySelector(".create-post-form");
                if (!form) return; // Only works on the appropriate page
     
                form.querySelectorAll(".ts-form-group").forEach(function (field) {
                    // Check if subtitle already exists to prevent duplicates
                    if (field.querySelector(".vx-subtitle")) {
                        return; // Skip if subtitle already added
                    }
                    
                    const dialogContent = field.querySelector(".vx-dialog-content");
                    const label = field.querySelector("label");
     
                    if (dialogContent && label) {
                        // Create new element for subtitle
                        const subtitle = document.createElement("div");
                        subtitle.classList.add("vx-subtitle");
                        subtitle.innerHTML = dialogContent.innerHTML;
     
                        // Insert AFTER the label
                        label.insertAdjacentElement("afterend", subtitle);
     
                        // Hide the original dialog (with icon and tooltip)
                        dialogContent.parentElement.style.display = "none";
                    }
                });
            });
        </script>
        <?php
    }
}