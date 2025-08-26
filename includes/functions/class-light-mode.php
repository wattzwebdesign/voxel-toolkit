<?php
/**
 * Light Mode Function
 * 
 * Converts Voxel's dark admin interface to light mode with custom styling
 * Overrides CSS variables and adds light theme styling
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Light_Mode {
    
    private $settings;
    private $color_scheme = 'auto';
    private $custom_accent = '#2271b1';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = Voxel_Toolkit_Settings::instance();
        $this->load_settings();
        $this->init_hooks();
    }
    
    /**
     * Load settings
     */
    private function load_settings() {
        $function_settings = $this->settings->get_function_settings('light_mode', array(
            'enabled' => false,
            'color_scheme' => 'auto',
            'custom_accent' => '#2271b1'
        ));
        
        $this->color_scheme = isset($function_settings['color_scheme']) ? $function_settings['color_scheme'] : 'auto';
        $this->custom_accent = isset($function_settings['custom_accent']) ? $function_settings['custom_accent'] : '#2271b1';
        
        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Only add hooks if we're in admin and have Voxel
        if (!is_admin() || !$this->is_voxel_page()) {
            return;
        }
        
        // Add light mode CSS to admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_light_mode_styles'), 999);
        
        // Add body classes for targeting
        add_filter('admin_body_class', array($this, 'add_body_classes'));
        
        // Add inline styles for dynamic colors
        add_action('admin_head', array($this, 'add_custom_colors'), 999);
    }
    
    /**
     * Check if current page is a Voxel admin page
     */
    private function is_voxel_page() {
        global $pagenow;
        
        // Check for Voxel pages by URL parameters
        if (isset($_GET['page']) && strpos($_GET['page'], 'voxel') !== false) {
            return true;
        }
        
        // Check for Voxel post types
        if (isset($_GET['post_type']) && $this->is_voxel_post_type($_GET['post_type'])) {
            return true;
        }
        
        // Check for Voxel taxonomies
        if (isset($_GET['taxonomy']) && $this->is_voxel_taxonomy($_GET['taxonomy'])) {
            return true;
        }
        
        // Check current screen for Voxel content
        $screen = get_current_screen();
        if ($screen && (
            strpos($screen->id, 'voxel') !== false ||
            strpos($screen->base, 'voxel') !== false
        )) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if post type is from Voxel
     */
    private function is_voxel_post_type($post_type) {
        // Get Voxel post types if available
        if (function_exists('\Voxel\Post_Type::get_all')) {
            $voxel_post_types = \Voxel\Post_Type::get_all();
            return isset($voxel_post_types[$post_type]);
        }
        
        // Fallback: check common Voxel post types
        $common_voxel_types = array('place', 'event', 'job', 'profile', 'collection');
        return in_array($post_type, $common_voxel_types);
    }
    
    /**
     * Check if taxonomy is from Voxel
     */
    private function is_voxel_taxonomy($taxonomy) {
        // Get Voxel taxonomies if available
        if (function_exists('\Voxel\Taxonomy::get_all')) {
            $voxel_taxonomies = \Voxel\Taxonomy::get_all();
            return isset($voxel_taxonomies[$taxonomy]);
        }
        
        // Fallback: check if taxonomy belongs to Voxel post types
        $taxonomy_obj = get_taxonomy($taxonomy);
        if ($taxonomy_obj) {
            foreach ($taxonomy_obj->object_type as $post_type) {
                if ($this->is_voxel_post_type($post_type)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Enqueue light mode styles
     */
    public function enqueue_light_mode_styles($hook) {
        // Only proceed if light mode is enabled or auto
        if ($this->color_scheme === 'dark') {
            return;
        }
        
        // Create the CSS content
        $css_content = $this->generate_light_mode_css();
        
        // Add inline styles
        wp_add_inline_style('admin-bar', $css_content);
    }
    
    /**
     * Generate light mode CSS
     */
    private function generate_light_mode_css() {
        $should_apply_light = false;
        
        if ($this->color_scheme === 'light') {
            $should_apply_light = true;
        } elseif ($this->color_scheme === 'auto') {
            // Apply based on user's system preference
            $should_apply_light = true; // We'll handle this with CSS media query
        }
        
        if (!$should_apply_light) {
            return '';
        }
        
        $accent_color = $this->custom_accent;
        $accent_rgb = $this->hex_to_rgb($accent_color);
        $accent_light = $this->lighten_color($accent_color, 20);
        $accent_dark = $this->darken_color($accent_color, 20);
        
        $css = '';
        
        // Auto mode uses CSS media query
        if ($this->color_scheme === 'auto') {
            $css .= '@media (prefers-color-scheme: light) {';
        }
        
        $css .= "
        /* Light Mode Overrides for Voxel - Only target main content areas */
        .vx-dark-mode #wpcontent,
        .vx-dark-mode .ts-field-modal,
        .vx-dark-mode .ts-snippet,
        .nvx-editor {
            background: #f8f9fa !important;
            color: #2c3e50 !important;
        }
        
        /* Remove invert filters on edit mode images */
        .vx-dark-mode .edit-frontend img,
        .vx-dark-mode .edit-voxel img {
            filter: none !important;
        }
        
        /* Explicitly exclude WordPress admin bar from ALL styling */
        #wpadminbar,
        #wpadminbar *,
        #wpadminbar a,
        #wpadminbar i,
        #wpadminbar svg,
        #wpadminbar button {
            background: revert !important;
            color: revert !important;
            fill: revert !important;
            filter: revert !important;
        }
        
        /* Update CSS Variables */
        :root {
            --bg-color: #f8f9fa !important;
            --text-color: #2c3e50 !important;
            --accent-color: {$accent_color} !important;
            --accent-secondary: {$accent_light} !important;
            --accent-text: {$accent_dark} !important;
        }
        
        /* Text Colors */
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
        .vx-dark-mode .ts-field-modal h6,
        .vx-dark-mode #wpcontent i,
        .vx-dark-mode #wpcontent p,
        .vx-dark-mode #wpcontent span,
        .vx-dark-mode #wpcontent ul,
        .vx-dark-mode .ts-field-modal i,
        .vx-dark-mode .ts-field-modal p,
        .vx-dark-mode .ts-field-modal span,
        .vx-dark-mode .ts-field-modal ul,
        .nvx-editor {
            color: #2c3e50 !important;
        }
        
        /* Icons - Force all to black - ONLY in Voxel content areas */
        .vx-dark-mode #wpcontent i,
        .vx-dark-mode #wpcontent .dashicons,
        .vx-dark-mode #wpcontent .ts-icon,
        .vx-dark-mode #wpcontent .iconify,
        .vx-dark-mode #wpcontent [class*='icon'],
        .vx-dark-mode #wpcontent [class*='ts-icon'],
        .vx-dark-mode #wpcontent svg,
        .vx-dark-mode #wpcontent .vx-icon,
        .vx-dark-mode .ts-field-modal i,
        .vx-dark-mode .ts-field-modal .ts-icon,
        .vx-dark-mode .ts-field-modal .iconify,
        .vx-dark-mode .ts-field-modal svg,
        .vx-dark-mode .nvx-editor i,
        .vx-dark-mode .nvx-editor svg {
            color: #2c3e50 !important;
            fill: #2c3e50 !important;
        }
        
        /* Voxel-specific navigation and UI icons */
        .vx-dark-mode #wpcontent .ts-nav i,
        .vx-dark-mode #wpcontent .vx-nav i,
        .vx-dark-mode #wpcontent .field-head i,
        .vx-dark-mode #wpcontent .ts-form-group i,
        .vx-dark-mode #wpcontent .single-field i,
        .vx-dark-mode #wpcontent .ts-group i,
        .vx-dark-mode #wpcontent .vx-panel i,
        .vx-dark-mode #wpcontent .vx-panel svg,
        .vx-dark-mode #wpcontent .ts-button i,
        .vx-dark-mode #wpcontent .ts-tab-content i,
        .vx-dark-mode #wpcontent .vx-head i,
        .vx-dark-mode .vx-panel i,
        .vx-dark-mode .vx-panel svg,
        .vx-dark-mode .ts-snippet i,
        .vx-dark-mode .ts-snippet svg {
            color: #2c3e50 !important;
            fill: #2c3e50 !important;
        }
        
        /* Button Links - Keep white text (exclude from admin bar) */
        .vx-dark-mode #wpcontent a.ts-button,
        .vx-dark-mode #wpcontent .ts-button a,
        .vx-dark-mode #wpcontent .button a,
        .vx-dark-mode #wpcontent a.button,
        .vx-dark-mode .ts-field-modal a.ts-button,
        .vx-dark-mode .ts-field-modal .ts-button a,
        .vx-dark-mode .nvx-editor a.ts-button,
        .vx-dark-mode .nvx-editor .ts-button a {
            color: #fff !important;
            background: {$accent_color} !important;
        }
        
        .vx-dark-mode #wpcontent a.ts-button:hover,
        .vx-dark-mode #wpcontent .ts-button a:hover,
        .vx-dark-mode .ts-field-modal a.ts-button:hover,
        .vx-dark-mode .ts-field-modal .ts-button a:hover {
            background: {$accent_dark} !important;
            color: #fff !important;
        }
        
        /* Regular Links - Make them darker and visible - ONLY in Voxel content areas (exclude admin bar) */
        .vx-dark-mode #wpcontent a:not(.ts-button):not(.button),
        .vx-dark-mode .ts-field-modal a:not(.ts-button):not(.button),
        .vx-dark-mode .nvx-editor a:not(.ts-button):not(.button),
        .vx-dark-mode #wpcontent .ts-btn-link,
        .vx-dark-mode #wpcontent .link,
        .vx-dark-mode #wpcontent .ts-nav a,
        .vx-dark-mode #wpcontent .vx-nav a,
        .vx-dark-mode #wpcontent .ts-tab a,
        .vx-dark-mode #wpcontent .nav-link {
            color: #1a1a1a !important;
            text-decoration: underline !important;
        }
        
        .vx-dark-mode #wpcontent a:not(.ts-button):not(.button):hover,
        .vx-dark-mode .ts-field-modal a:not(.ts-button):not(.button):hover,
        .vx-dark-mode .nvx-editor a:not(.ts-button):not(.button):hover,
        .vx-dark-mode #wpcontent .ts-btn-link:hover,
        .vx-dark-mode #wpcontent .ts-nav a:hover,
        .vx-dark-mode #wpcontent .vx-nav a:hover,
        .vx-dark-mode #wpcontent .ts-tab a:hover {
            color: {$accent_color} !important;
            background-color: rgba(0,0,0,0.05) !important;
        }
        
        /* Active/current navigation links - ONLY in Voxel content areas */
        .vx-dark-mode #wpcontent .ts-nav a.current,
        .vx-dark-mode #wpcontent .ts-nav a.active,
        .vx-dark-mode #wpcontent .vx-nav a.current,
        .vx-dark-mode #wpcontent .vx-nav a.active,
        .vx-dark-mode #wpcontent .ts-tab.active a {
            color: {$accent_color} !important;
            background-color: rgba(0,0,0,0.1) !important;
        }
        
        /* Text that might be too light - ONLY in Voxel content areas */
        .vx-dark-mode #wpcontent .text-muted,
        .vx-dark-mode #wpcontent .muted,
        .vx-dark-mode #wpcontent .description,
        .vx-dark-mode #wpcontent .help-text,
        .vx-dark-mode .ts-field-modal .text-muted,
        .vx-dark-mode .ts-field-modal .muted,
        .vx-dark-mode .ts-field-modal .description,
        .vx-dark-mode .ts-field-modal .help-text {
            color: #666 !important;
        }
        
        /* Form Elements */
        .ts-form-group input,
        .ts-form-group select,
        .ts-form-group textarea {
            background: #ffffff !important;
            border: 1px solid #ddd !important;
            color: #2c3e50 !important;
        }
        
        .ts-form-group input:focus,
        .ts-form-group select:focus,
        .ts-form-group textarea:focus {
            border-color: {$accent_color} !important;
            box-shadow: 0 0 0 1px {$accent_color} !important;
        }
        
        .ts-form-group input:hover,
        .ts-form-group select:hover,
        .ts-form-group textarea:hover {
            border-color: #bbb !important;
        }
        
        .ts-form-group input::placeholder,
        .ts-form-group textarea::placeholder {
            color: #666 !important;
        }
        
        .ts-form-group > label {
            color: #2c3e50 !important;
            opacity: 0.8 !important;
        }
        
        .ts-form-group > p {
            color: #666 !important;
        }
        
        /* Buttons */
        .ts-button {
            background: {$accent_color} !important;
            color: #fff !important;
        }
        
        .ts-button:hover {
            background: {$accent_dark} !important;
        }
        
        /* CPT Header Buttons - Ensure white text */
        .vx-dark-mode .cpt-header-buttons,
        .vx-dark-mode .cpt-header-buttons a,
        .vx-dark-mode .cpt-header-buttons button,
        .vx-dark-mode .cpt-header-buttons .ts-button {
            color: #fff !important;
            background: {$accent_color} !important;
        }
        
        .vx-dark-mode .cpt-header-buttons a:hover,
        .vx-dark-mode .cpt-header-buttons button:hover,
        .vx-dark-mode .cpt-header-buttons .ts-button:hover {
            background: {$accent_dark} !important;
            color: #fff !important;
        }
        
        .ts-button.ts-outline {
            background: transparent !important;
            border: 1px solid #ddd !important;
            color: #2c3e50 !important;
        }
        
        .ts-button.ts-outline:hover {
            border-color: {$accent_color} !important;
            color: {$accent_color} !important;
        }
        
        .ts-button.ts-faded {
            background: #f8f9fa !important;
            color: #666 !important;
        }
        
        .ts-button.ts-faded:hover {
            background: #e9ecef !important;
            color: #2c3e50 !important;
        }
        
        /* Cards and Panels */
        .single-field,
        .ts-group,
        .vx-panel,
        .x-template {
            background: #ffffff !important;
            border: 1px solid #ddd !important;
            color: #2c3e50 !important;
        }
        
        .single-field:hover,
        .vx-panel:hover,
        .x-template:hover {
            background: #f8f9fa !important;
        }
        
        .single-field .field-head {
            background: #f8f9fa !important;
            border: 1px solid #e9ecef !important;
        }
        
        .single-field.open > .field-head {
            background: #ffffff !important;
        }
        
        .single-field .field-head:hover {
            background: #e9ecef !important;
        }
        
        /* Header and Navigation */
        .vx-head {
            border-bottom: 1px solid #ddd !important;
        }
        
        .inner-tabs li {
            color: #666 !important;
        }
        
        .inner-tabs li.current-item,
        .inner-tabs li:hover {
            color: #2c3e50 !important;
        }
        
        .inner-tabs li.current-item a:after {
            background: {$accent_color} !important;
        }
        
        /* Navigation Items */
        .ts-nav .ts-nav-item a {
            color: #666 !important;
        }
        
        .ts-nav .ts-nav-item.current-item a,
        .ts-nav .ts-nav-item a:hover {
            color: #2c3e50 !important;
        }
        
        .ts-nav .ts-nav-item a .item-icon {
            background: #f8f9fa !important;
            border: 1px solid #ddd !important;
        }
        
        .ts-nav .ts-nav-item.current-item a .item-icon {
            background: {$accent_color} !important;
        }
        
        /* Separators and Borders */
        .ts-separator {
            background: #ddd !important;
        }
        
        /* Switch Controls */
        .onoffswitch .onoffswitch-label {
            background: #ddd !important;
        }
        
        .onoffswitch .onoffswitch-checkbox:checked + .onoffswitch-label {
            background: {$accent_color} !important;
        }
        
        /* Checkboxes and Radio Buttons */
        .container-checkbox .checkmark,
        .container-radio .checkmark {
            background: #ddd !important;
        }
        
        .container-checkbox input:checked ~ .checkmark,
        .container-radio input:checked ~ .checkmark {
            background: {$accent_color} !important;
        }
        
        /* Field Actions */
        .single-field .field-head .field-action {
            border: 1px solid #ddd !important;
            background: transparent !important;
        }
        
        .single-field .field-head .field-action:hover {
            border-color: #bbb !important;
            background: #f8f9fa !important;
        }
        
        .single-field .field-head .field-action i {
            color: #666 !important;
        }
        
        .single-field .field-head .field-action:hover i {
            color: #2c3e50 !important;
        }
        
        /* Scrollbars */
        .min-scroll::-webkit-scrollbar-thumb {
            background-color: #ddd !important;
        }
        
        .min-scroll:hover::-webkit-scrollbar-thumb {
            background-color: #bbb !important;
        }
        
        /* Dynamic Tags and Modals */
        .pick-tag,
        .dtags-container,
        .condition-group {
            background: #ffffff !important;
            border: 1px solid #ddd !important;
        }
        
        .dtags-content .dynamic-editor {
            color: #2c3e50 !important;
        }
        
        .dtags-content .dynamic-editor .dtag {
            color: {$accent_color} !important;
        }
        
        .dtags-content textarea {
            color: #2c3e50 !important;
        }
        
        .dtags-content .editor-placeholder {
            color: #666 !important;
        }
        
        /* Icons and SVG */
        .panel-icon svg,
        .panel-icon i {
            fill: #666 !important;
            color: #666 !important;
        }
        
        /* Links */
        .vx-dark-mode #wpcontent .ts-form-group ol a,
        .vx-dark-mode #wpcontent .ts-form-group p a,
        .vx-dark-mode .ts-field-modal .ts-form-group ol a,
        .vx-dark-mode .ts-field-modal .ts-form-group p a {
            color: {$accent_color} !important;
        }
        
        /* Field Type Labels */
        .single-field .field-head .field-type {
            color: #666 !important;
            opacity: 0.7 !important;
        }
        
        /* Help Text */
        .ts-form-group > p,
        .description {
            color: #666 !important;
        }
        
        /* Notices */
        .ts-notice {
            color: #fff !important;
        }
        
        /* NVX Editor specific overrides */
        .nvx-editor {
            --nvx-dark-1: #f8f9fa !important;
            --nvx-light-1: #2c3e50 !important;
            --nvx-light-2: #666 !important;
            --nvx-light-3: #999 !important;
            --nvx-border-1: #ddd !important;
            --nvx-accent-1: {$accent_color} !important;
        }
        
        .nvx-editor .nvx-topbar {
            border-bottom: 1px solid #ddd !important;
        }
        
        .nvx-editor .nvx-right-sidebar,
        .nvx-editor .nvx-left-sidebar {
            border-color: #ddd !important;
        }
        
        .nvx-editor div.nvx-condition {
            border: 1px solid #ddd !important;
        }
        ";
        
        // Close media query for auto mode
        if ($this->color_scheme === 'auto') {
            $css .= '}';
        }
        
        return $css;
    }
    
    /**
     * Add body classes for targeting
     */
    public function add_body_classes($classes) {
        if ($this->color_scheme !== 'dark') {
            $classes .= ' voxel-toolkit-light-mode';
        }
        
        $classes .= ' voxel-toolkit-scheme-' . $this->color_scheme;
        
        return $classes;
    }
    
    /**
     * Add custom color CSS variables
     */
    public function add_custom_colors() {
        if ($this->color_scheme === 'dark') {
            return;
        }
        
        $accent_color = $this->custom_accent;
        $accent_light = $this->lighten_color($accent_color, 20);
        $accent_dark = $this->darken_color($accent_color, 20);
        
        echo '<style id="voxel-toolkit-custom-colors">';
        
        if ($this->color_scheme === 'auto') {
            echo '@media (prefers-color-scheme: light) {';
        }
        
        echo "
        :root {
            --voxel-toolkit-accent: {$accent_color};
            --voxel-toolkit-accent-light: {$accent_light};
            --voxel-toolkit-accent-dark: {$accent_dark};
        }
        ";
        
        if ($this->color_scheme === 'auto') {
            echo '}';
        }
        
        echo '</style>';
    }
    
    /**
     * Convert hex color to RGB
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        );
    }
    
    /**
     * Lighten a hex color
     */
    private function lighten_color($hex, $percent) {
        $rgb = $this->hex_to_rgb($hex);
        
        $rgb['r'] = min(255, $rgb['r'] + (255 - $rgb['r']) * $percent / 100);
        $rgb['g'] = min(255, $rgb['g'] + (255 - $rgb['g']) * $percent / 100);
        $rgb['b'] = min(255, $rgb['b'] + (255 - $rgb['b']) * $percent / 100);
        
        return sprintf('#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b']);
    }
    
    /**
     * Darken a hex color
     */
    private function darken_color($hex, $percent) {
        $rgb = $this->hex_to_rgb($hex);
        
        $rgb['r'] = max(0, $rgb['r'] - $rgb['r'] * $percent / 100);
        $rgb['g'] = max(0, $rgb['g'] - $rgb['g'] * $percent / 100);
        $rgb['b'] = max(0, $rgb['b'] - $rgb['b'] * $percent / 100);
        
        return sprintf('#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b']);
    }
    
    /**
     * Handle settings updates
     * 
     * @param array $new_settings New settings
     * @param array $old_settings Old settings
     */
    public function on_settings_updated($new_settings, $old_settings) {
        $function_settings = isset($new_settings['light_mode']) ? $new_settings['light_mode'] : array();
        $this->color_scheme = isset($function_settings['color_scheme']) ? $function_settings['color_scheme'] : 'auto';
        $this->custom_accent = isset($function_settings['custom_accent']) ? $function_settings['custom_accent'] : '#2271b1';
        
        // Reinitialize hooks with new settings
        $this->remove_hooks();
        $this->init_hooks();
    }
    
    /**
     * Remove hooks (for cleanup)
     */
    private function remove_hooks() {
        remove_action('admin_enqueue_scripts', array($this, 'enqueue_light_mode_styles'), 999);
        remove_filter('admin_body_class', array($this, 'add_body_classes'));
        remove_action('admin_head', array($this, 'add_custom_colors'), 999);
    }
    
    /**
     * Deinitialize (cleanup when function is disabled)
     */
    public function deinit() {
        $this->remove_hooks();
        remove_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10);
    }
}