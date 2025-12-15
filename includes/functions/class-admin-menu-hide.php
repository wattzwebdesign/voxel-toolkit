<?php
/**
 * Admin Menu Hide Function
 *
 * Allows hiding specific admin menu items from the WordPress admin
 * Focuses on Voxel-specific menus
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Admin_Menu_Hide {

    private $settings;
    private $hidden_menus = array();

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
        $function_settings = $this->settings->get_function_settings('admin_menu_hide', array(
            'enabled' => false,
            'hidden_menus' => array()
        ));

        $this->hidden_menus = isset($function_settings['hidden_menus']) ? $function_settings['hidden_menus'] : array();

        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        if (empty($this->hidden_menus)) {
            return;
        }

        // Hook into admin_menu with high priority to remove menus after they're added
        add_action('admin_menu', array($this, 'hide_admin_menus'), 999);

        // Also hook into admin_init as a fallback
        add_action('admin_init', array($this, 'hide_admin_menus_fallback'), 999);

        // Hide menus via CSS as additional fallback
        add_action('admin_head', array($this, 'hide_menus_css'));

        // Log hidden menus for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('admin_footer', array($this, 'log_hidden_menus'));
        }
    }

    /**
     * Hide admin menus
     */
    public function hide_admin_menus() {
        global $menu, $submenu;

        foreach ($this->hidden_menus as $menu_key) {
            switch ($menu_key) {
                case 'voxel_settings':
                    $this->hide_voxel_settings_menu();
                    break;

                case 'voxel_post_types':
                    $this->hide_voxel_post_types_menu();
                    break;

                case 'voxel_templates':
                    $this->hide_voxel_templates_menu();
                    break;

                case 'voxel_users':
                    $this->hide_voxel_users_menu();
                    break;
            }
        }
    }

    /**
     * Hide Voxel Settings menu (main Voxel menu)
     */
    private function hide_voxel_settings_menu() {
        // Remove the main menu page
        remove_menu_page('voxel-settings');

        // Try to remove from submenu as well if it exists under another parent
        global $submenu;

        // Check common parent menus where it might be located
        $possible_parents = array('voxel', 'voxel-backend', 'admin.php?page=voxel');

        foreach ($possible_parents as $parent) {
            if (isset($submenu[$parent])) {
                foreach ($submenu[$parent] as $key => $item) {
                    if (isset($item[2]) && (
                        $item[2] === 'voxel-settings' ||
                        strpos($item[2], 'voxel-settings') !== false
                    )) {
                        unset($submenu[$parent][$key]);
                    }
                }
            }
        }

        // Remove by capability if the above doesn't work
        if (function_exists('remove_submenu_page')) {
            remove_submenu_page('admin.php', 'admin.php?page=voxel-settings');
            remove_submenu_page('voxel', 'voxel-settings');
            remove_submenu_page('voxel-backend', 'voxel-settings');
        }
    }

    /**
     * Hide Voxel Structure (Post Types) menu
     */
    private function hide_voxel_post_types_menu() {
        // Remove the main menu page
        remove_menu_page('voxel-post-types');

        // Try to remove from submenu as well if it exists under another parent
        global $submenu;

        // Check common parent menus where it might be located
        $possible_parents = array('voxel', 'voxel-backend', 'admin.php?page=voxel');

        foreach ($possible_parents as $parent) {
            if (isset($submenu[$parent])) {
                foreach ($submenu[$parent] as $key => $item) {
                    if (isset($item[2]) && (
                        $item[2] === 'voxel-post-types' ||
                        strpos($item[2], 'voxel-post-types') !== false
                    )) {
                        unset($submenu[$parent][$key]);
                    }
                }
            }
        }

        // Remove by capability if the above doesn't work
        if (function_exists('remove_submenu_page')) {
            remove_submenu_page('admin.php', 'admin.php?page=voxel-post-types');
            remove_submenu_page('voxel', 'voxel-post-types');
            remove_submenu_page('voxel-backend', 'voxel-post-types');
        }
    }

    /**
     * Hide Voxel Templates/Design menu
     */
    private function hide_voxel_templates_menu() {
        // Remove the main menu page
        remove_menu_page('voxel-templates');

        // Try to remove from submenu as well if it exists under another parent
        global $submenu;

        // Check common parent menus where it might be located
        $possible_parents = array('voxel', 'voxel-backend', 'admin.php?page=voxel');

        foreach ($possible_parents as $parent) {
            if (isset($submenu[$parent])) {
                foreach ($submenu[$parent] as $key => $item) {
                    if (isset($item[2]) && (
                        $item[2] === 'voxel-templates' ||
                        strpos($item[2], 'voxel-templates') !== false
                    )) {
                        unset($submenu[$parent][$key]);
                    }
                }
            }
        }

        // Remove by capability if the above doesn't work
        if (function_exists('remove_submenu_page')) {
            remove_submenu_page('admin.php', 'admin.php?page=voxel-templates');
            remove_submenu_page('voxel', 'voxel-templates');
            remove_submenu_page('voxel-backend', 'voxel-templates');
        }
    }

    /**
     * Hide Voxel Users submenu (under Users menu)
     */
    private function hide_voxel_users_menu() {
        // Remove the submenu page from Users menu
        remove_submenu_page('users.php', 'edit.php?post_type=profile');

        // Try to remove from submenu directly
        global $submenu;

        if (isset($submenu['users.php'])) {
            foreach ($submenu['users.php'] as $key => $item) {
                if (isset($item[2]) && (
                    $item[2] === 'edit.php?post_type=profile' ||
                    strpos($item[2], 'post_type=profile') !== false
                )) {
                    unset($submenu['users.php'][$key]);
                }
            }
        }
    }

    /**
     * Fallback method to hide menus via admin_init
     */
    public function hide_admin_menus_fallback() {
        // Additional attempt to hide menus if the first method didn't work
        $this->hide_admin_menus();
    }

    /**
     * Hide menus using CSS as final fallback
     */
    public function hide_menus_css() {
        if (empty($this->hidden_menus)) {
            return;
        }

        $css_selectors = array();

        foreach ($this->hidden_menus as $menu_key) {
            switch ($menu_key) {
                case 'voxel_settings':
                    $css_selectors[] = 'a[href="admin.php?page=voxel-settings"]';
                    $css_selectors[] = 'a[href*="voxel-settings"]';
                    break;

                case 'voxel_post_types':
                    $css_selectors[] = 'a[href="admin.php?page=voxel-post-types"]';
                    $css_selectors[] = 'a[href*="voxel-post-types"]';
                    break;

                case 'voxel_templates':
                    $css_selectors[] = 'a[href="admin.php?page=voxel-templates"]';
                    $css_selectors[] = 'a[href*="voxel-templates"]';
                    break;

                case 'voxel_users':
                    $css_selectors[] = 'a[href="edit.php?post_type=profile"]';
                    $css_selectors[] = 'a[href*="post_type=profile"]';
                    break;
            }
        }

        if (!empty($css_selectors)) {
            echo '<style type="text/css">';
            foreach ($css_selectors as $selector) {
                echo $selector . ', ';
                echo $selector . ' parent li { display: none !important; } ';
            }
            echo '</style>';
        }
    }

    /**
     * Log hidden menus for debugging
     */
    public function log_hidden_menus() {
        if (!empty($this->hidden_menus)) {
            echo '<!-- Voxel Toolkit: Hidden menus - ' . implode(', ', $this->hidden_menus) . ' -->';
        }
    }

    /**
     * Get available menus that can be hidden
     *
     * @return array Available menus
     */
    public function get_available_menus() {
        return array(
            'voxel_settings' => array(
                'name' => __('Voxel Menu', 'voxel-toolkit'),
                'description' => __('Hide the main Voxel settings menu', 'voxel-toolkit'),
                'url' => 'admin.php?page=voxel-settings'
            ),
            'voxel_post_types' => array(
                'name' => __('Structure', 'voxel-toolkit'),
                'description' => __('Hide the Voxel Structure (Post Types) menu', 'voxel-toolkit'),
                'url' => 'admin.php?page=voxel-post-types'
            ),
            'voxel_templates' => array(
                'name' => __('Design', 'voxel-toolkit'),
                'description' => __('Hide the Voxel Design/Templates menu', 'voxel-toolkit'),
                'url' => 'admin.php?page=voxel-templates'
            ),
            'voxel_users' => array(
                'name' => __('Users (Voxel)', 'voxel-toolkit'),
                'description' => __('Hide the Users (Voxel) submenu under Users', 'voxel-toolkit'),
                'url' => 'edit.php?post_type=profile'
            )
        );
    }

    /**
     * Check if a specific menu should be hidden
     *
     * @param string $menu_key Menu key
     * @return bool Whether menu should be hidden
     */
    public function is_menu_hidden($menu_key) {
        return in_array($menu_key, $this->hidden_menus);
    }

    /**
     * Hide a specific menu
     *
     * @param string $menu_key Menu key to hide
     * @return bool Whether the menu was hidden
     */
    public function hide_menu($menu_key) {
        if (!in_array($menu_key, $this->hidden_menus)) {
            $this->hidden_menus[] = $menu_key;
            return $this->update_hidden_menus();
        }
        return true;
    }

    /**
     * Show a specific menu (remove from hidden list)
     *
     * @param string $menu_key Menu key to show
     * @return bool Whether the menu was shown
     */
    public function show_menu($menu_key) {
        $key = array_search($menu_key, $this->hidden_menus);
        if ($key !== false) {
            unset($this->hidden_menus[$key]);
            $this->hidden_menus = array_values($this->hidden_menus); // Reindex array
            return $this->update_hidden_menus();
        }
        return true;
    }

    /**
     * Update hidden menus setting
     *
     * @return bool Whether settings were updated
     */
    private function update_hidden_menus() {
        return $this->settings->update_function_settings('admin_menu_hide', array(
            'enabled' => true,
            'hidden_menus' => $this->hidden_menus
        ));
    }

    /**
     * Handle settings updates
     *
     * @param array $new_settings New settings
     * @param array $old_settings Old settings
     */
    public function on_settings_updated($new_settings, $old_settings) {
        $function_settings = isset($new_settings['admin_menu_hide']) ? $new_settings['admin_menu_hide'] : array();
        $this->hidden_menus = isset($function_settings['hidden_menus']) ? $function_settings['hidden_menus'] : array();

        // Reinitialize hooks with new settings
        $this->remove_hooks();
        $this->init_hooks();
    }

    /**
     * Remove hooks (for cleanup)
     */
    private function remove_hooks() {
        remove_action('admin_menu', array($this, 'hide_admin_menus'), 999);
        remove_action('admin_init', array($this, 'hide_admin_menus_fallback'), 999);
        remove_action('admin_head', array($this, 'hide_menus_css'));
        remove_action('admin_footer', array($this, 'log_hidden_menus'));
    }

    /**
     * Deinitialize (cleanup when function is disabled)
     */
    public function deinit() {
        $this->remove_hooks();
        remove_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10);
    }
}
