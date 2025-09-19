<?php
/**
 * Admin Taxonomy Search functionality
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Admin_Taxonomy_Search {
    
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
        // Check if admin taxonomy search is enabled
        if (!$this->is_enabled()) {
            return;
        }
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add search functionality to taxonomy metaboxes
        add_action('admin_footer-post.php', array($this, 'add_taxonomy_search_script'));
        add_action('admin_footer-post-new.php', array($this, 'add_taxonomy_search_script'));
    }
    
    /**
     * Check if admin taxonomy search is enabled
     */
    private function is_enabled() {
        $settings = Voxel_Toolkit_Settings::instance();
        $taxonomy_search_settings = $settings->get_function_settings('admin_taxonomy_search', array());
        
        return isset($taxonomy_search_settings['enabled']) && $taxonomy_search_settings['enabled'];
    }
    
    /**
     * Get enabled taxonomies
     */
    private function get_enabled_taxonomies() {
        $settings = Voxel_Toolkit_Settings::instance();
        $taxonomy_search_settings = $settings->get_function_settings('admin_taxonomy_search', array());
        
        return isset($taxonomy_search_settings['taxonomies']) ? $taxonomy_search_settings['taxonomies'] : array();
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on post edit pages
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        wp_enqueue_script(
            'voxel-toolkit-admin-taxonomy-search',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/admin-taxonomy-search.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );
        
        wp_localize_script('voxel-toolkit-admin-taxonomy-search', 'voxelTaxonomySearch', array(
            'searchPlaceholder' => __('Search terms...', 'voxel-toolkit'),
            'noResultsText' => __('No terms found', 'voxel-toolkit'),
            'enabledTaxonomies' => $this->get_enabled_taxonomies()
        ));
        
        wp_enqueue_style(
            'voxel-toolkit-admin-taxonomy-search',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/admin-taxonomy-search.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
    }
    
    /**
     * Add taxonomy search script to admin footer
     */
    public function add_taxonomy_search_script() {
        $enabled_taxonomies = $this->get_enabled_taxonomies();
        
        if (empty($enabled_taxonomies)) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            if (typeof window.VoxelTaxonomySearch !== 'undefined') {
                window.VoxelTaxonomySearch.init();
            }
        });
        </script>
        <?php
    }
}