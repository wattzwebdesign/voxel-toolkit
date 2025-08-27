<?php
/**
 * Voxel Admin Light Mode
 * 
 * Adds light mode styling to the Voxel admin interface
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Light_Mode {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add light mode CSS with high priority
        add_action('admin_head', array($this, 'add_light_mode_styles'), 999);
        
        // Add body class for light mode
        add_filter('admin_body_class', array($this, 'add_body_class'));
    }
    
    /**
     * Check if current page should have light mode
     */
    private function should_apply_light_mode() {
        // Check if we're in admin
        if (!is_admin()) {
            return false;
        }
        
        // Exclude all Voxel Toolkit pages (main, settings, license)
        if (isset($_GET['page']) && (
            $_GET['page'] === 'voxel-toolkit' ||
            $_GET['page'] === 'voxel-toolkit-settings' || 
            $_GET['page'] === 'voxel-toolkit-manage-license'
        )) {
            return false;
        }
        
        // Check for Voxel pages (any page with 'voxel' in the URL, except voxel-toolkit)
        if (isset($_GET['page']) && strpos($_GET['page'], 'voxel') !== false) {
            return true;
        }
        
        // Check specifically for edit-post-type pages only
        if (isset($_GET['page']) && strpos($_GET['page'], 'edit-post-type-') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Add body class for light mode
     */
    public function add_body_class($classes) {
        if ($this->should_apply_light_mode()) {
            $classes .= ' voxel-toolkit-light-mode vx-dark-mode';
        }
        return $classes;
    }
    
    /**
     * Add light mode styles to admin
     */
    public function add_light_mode_styles() {
        // Only add styles on appropriate pages
        if (!$this->should_apply_light_mode()) {
            return;
        }
        ?>
        <style id="voxel-toolkit-light-mode">
        /* VOXEL ADMIN LIGHT MODE */
        
        /* Base styles */
        #wpbody {
            background-color: #fff !important;
            font-family: verdana !important;
        }
        
        #wpcontent, #wpfooter {
            background-color: #fff !important;
        }
        
        body {
            --accent-color: linear-gradient(-125deg, #444, #444 70%, #444) !important;
        }
        
        /* Sticky top */
        .sticky-top {
            background: rgb(243 244 245) !important;
        }
        
        /* Transparent buttons */
        .ts-button.ts-transparent {
            background: transparent;
            opacity: .5;
            outline: none;
            color: #444 !important;
            font-weight: 400;
            box-shadow: none;
        }
        
        /* Container text colors */
        .x-container h3 {
            color: #000 !important;
        }
        
        .x-container a {
            color: #000 !important;
        }
        
        .x-container li {
            color: #ccc !important;
        }
        
        .x-container p {
            color: #000 !important;
        }
        
        .x-container label {
            color: #000000 !important;
        }
        
        /* Dark mode overrides for headings and icons */
        .vx-dark-mode #wpcontent i,
        .vx-dark-mode #wpcontent h1, 
        .vx-dark-mode #wpcontent h2, 
        .vx-dark-mode #wpcontent h3, 
        .vx-dark-mode #wpcontent h4, 
        .vx-dark-mode #wpcontent h5, 
        .vx-dark-mode #wpcontent h6, 
        .vx-dark-mode .ts-field-modal h1, 
        .vx-dark-mode .ts-field-modal h2, 
        .vx-dark-mode .ts-field-modal h3, 
        .vx-dark-mode .ts-field-modal h4, 
        .vx-dark-mode .ts-field-modal h5, 
        .vx-dark-mode .ts-field-modal h6 {
            color: #444 !important;
        }
        
        /* Dark mode text colors */
        .vx-dark-mode #wpcontent p, 
        .vx-dark-mode #wpcontent span, 
        .vx-dark-mode #wpcontent ul, 
        .vx-dark-mode .ts-field-modal i, 
        .vx-dark-mode .ts-field-modal p, 
        .vx-dark-mode .ts-field-modal span, 
        .vx-dark-mode .ts-field-modal ul {
            color: #444 !important;
        }
        
        /* Vertical tabs */
        .inner-tabs.vertical-tabs li a:after {
            width: 3px;
            height: 0;
            background: linear-gradient(-125deg, #444, #444 70%, #444) !important;
        }
        
        /* Icon picker */
        .ts-icon-picker .icon-preview {
            background: #efeff0f7 !important;
        }
        
        /* Groups */
        .ts-group {
            padding: 20px;
            background: hsl(0deg 2.39% 84.67% / 14%) !important;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid hsla(0, 0%, 100%, .0509803922);
        }
        
        .ts-group-head {
            border-bottom: 1px solid hsl(0deg 5.05% 89.22% / 41%) !important;
        }
        
        /* Form inputs */
        .ts-form-group input, 
        .ts-form-group select, 
        .ts-form-group textarea {
            width: 100%;
            background: #fff !important;
            border: 1.5px solid hsl(0deg 0% 65.35% / 15%) !important;
            height: 45px;
            border-radius: 8px;
            color: #444 !important;
            transition: border-color .15s ease;
            max-width: none;
            padding: 0 14px;
            font-size: 14px;
        }
        
        .ts-form-group input:hover {
            border-color: #7e7d7d !important;
        }
        
        .ts-form-group input:disabled:hover {
            border-color: #7e7d7d !important;
        }
        
        .ts-form-group input:disabled {
            color: #444 !important;
        }
        
        /* Outline buttons */
        .ts-button.ts-outline {
            border: 1.5px solid hsl(0deg 0% 65.35% / 15%) !important;
            background: transparent;
            color: #444 !important;
            font-weight: 400;
        }
        
        /* Button hover */
        .ts-button:hover {
            border-color: #7e7d7d !important;
        }
        
        /* Save button */
        a.ts-button.ts-save-settings.btn-shadow {
            color: #fff !important;
        }
        
        .ts-button.ts-save-settings:hover {
            background: #626262 !important;
            transition: .0s ease;
        }
        
        /* Save button icon */
        .vx-dark-mode #wpcontent i.las.la-save.icon-sm {
            color: #fff !important;
        }
        
        /* Group text */
        .ts-group.vx-dark-mode {
            color: #444 !important;
        }
        
        /* Action icons */
        i.las.la-download.icon-sm,
        i.las.la-cloud-upload-alt.icon-sm,
        i.las.la-history.icon-sm,
        i.lar.la-trash-alt.icon-sm,
        i.las.la-grip-lines,
        i.las.la-pencil-ruler,
        i.las.la-filter {
            color: #444 !important;
        }
        
        /* Current nav item */
        .ts-nav .ts-nav-item.current-item a .item-icon {
            background: #f4f4f5 !important;
        }
        
        /* Toggle switches */
        .onoffswitch .onoffswitch-label {
            background: #e9e6e6;
        }
        
        /* Modals */
        .ts-field-modal .ts-modal-content {
            color: #fff;
            background: #fff !important;
        }
        
        /* Condition groups and tags */
        .condition-group, 
        .dtags-container, 
        .pick-tag {
            background: #fafbfb !important;
        }
        
        /* Code snippets */
        pre.ts-snippet {
            color: #444 !important;
            background: #fff !important;
        }
        
        /* FAQ */
        .x-faq-ui summary {
            font-size: 15px !important;
            color: #444 !important;
            opacity: 1 !important;
        }
        
        /* Templates and panels */
        .x-template,
        .vx-panel {
            background: #fafbfb !important;
        }
        
        .x-template:hover,
        .vx-panel:hover {
            border: 1px solid #e3e3e3;
        }
        
        /* Single fields */
        .single-field {
            background: #fafbfb !important;
            border: 1px solid #fafbfb;
        }
        
        .single-field:hover {
            border: 1px solid #e3e3e3;
        }
        
        .single-field .field-head .field-type {
            opacity: .6 !important;
            font-size: 12px;
        }
        
        /* List items */
        .x-container li {
            color: #444 !important;
        }
        
        .panel-info ul {
            opacity: 1 !important;
        }
        
        /* Form labels */
        .ts-form-group > label {
            opacity: 0.9 !important;
            color: #444 !important;
        }
        
        /* Links */
        .vx-dark-mode #wpcontent .ts-form-group ol a, 
        .vx-dark-mode #wpcontent .ts-form-group p a, 
        .vx-dark-mode .ts-field-modal .ts-form-group ol a, 
        .vx-dark-mode .ts-field-modal .ts-form-group p a,
        .ts-form-group label a {
            text-decoration: underline !important;
        }
        
        /* Checkboxes and radios */
        .container-checkbox .checkmark, 
        .container-radio .checkmark {
            background: #e9e7e7;
        }
        
        /* Placeholders */
        input::placeholder,
        textarea::placeholder {
            color: #444 !important;
        }
        
        /* Library items */
        .vx-dark-mode #wpcontent *.library-item .lib-content h3,
        .vx-dark-mode #wpcontent *.library-item .lib-content .lib-buttons a {
            color: #fff !important;
        }
        
        /* Term settings */
        #voxel-term-settings {
            background-color: #f9f9f9 !important;
        }
        
        /* Panel icons */
        .panel-icon {
            background: #efeff0f7 !important;
        }
        
        a.ts-button.edit-voxel.ts-outline img {
            background: #0c0909;
        }
        
        /* Elementor modal */
        .elementor-editor-active #dynamic-tags-modal .ts-modal-content {
            background: #fff !important;
        }
        
        /* Modal headers */
        .ts-field-modal .ts-modal-content .field-modal-head h2 {
            color: #444 !important;
        }
        </style>
        <?php
    }
    
    /**
     * Deinitialize function
     */
    public function deinit() {
        // Remove hooks when function is disabled
        remove_action('admin_head', array($this, 'add_light_mode_styles'), 999);
        remove_filter('admin_body_class', array($this, 'add_body_class'));
    }
}