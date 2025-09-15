<?php
/**
 * Featured Posts Function
 * 
 * Adds featured functionality to posts by allowing admins to set Voxel Priority
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Featured_Posts {
    
    private static $instance = null;
    private $enabled_post_types = array();
    private $priority_values = array();
    
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
        error_log('Voxel Toolkit Featured Posts: Constructor called');
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'init'));
        
        // Try calling init directly to debug
        if (is_admin()) {
            error_log('Voxel Toolkit Featured Posts: Calling init directly from constructor');
            $this->init();
        }
    }
    
    /**
     * Initialize the function
     */
    public function init() {
        error_log('Voxel Toolkit Featured Posts: init() method called');
        
        // Get settings
        $settings = get_option('voxel_toolkit_options', array());
        error_log('Voxel Toolkit Featured Posts: All settings - ' . print_r($settings, true));
        
        $featured_settings = isset($settings['featured_posts']) ? $settings['featured_posts'] : array();
        error_log('Voxel Toolkit Featured Posts: Featured settings - ' . print_r($featured_settings, true));
        
        if (!isset($featured_settings['enabled']) || !$featured_settings['enabled']) {
            error_log('Voxel Toolkit Featured Posts: Function not enabled, exiting');
            return;
        }
        
        $this->enabled_post_types = isset($featured_settings['post_types']) ? $featured_settings['post_types'] : array();
        $this->priority_values = isset($featured_settings['priority_values']) ? $featured_settings['priority_values'] : array();
        
        if (empty($this->enabled_post_types)) {
            return;
        }
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        error_log('Featured Posts: init_hooks called with post types: ' . implode(', ', $this->enabled_post_types));
        
        // Add columns to post list tables
        foreach ($this->enabled_post_types as $post_type) {
            error_log('Featured Posts: Adding hooks for post type: ' . $post_type);
            add_filter("manage_{$post_type}_posts_columns", array($this, 'add_featured_column'));
            add_action("manage_{$post_type}_posts_custom_column", array($this, 'display_featured_column'), 10, 2);
        }
        
        // Make column sortable
        foreach ($this->enabled_post_types as $post_type) {
            add_filter("manage_edit-{$post_type}_sortable_columns", array($this, 'make_featured_column_sortable'));
        }
        
        // Handle sorting
        add_action('pre_get_posts', array($this, 'handle_featured_sorting'));
        
        // Add featured filter
        foreach ($this->enabled_post_types as $post_type) {
            add_action('restrict_manage_posts', array($this, 'add_featured_filter'));
        }
        
        // Handle featured filter
        add_action('pre_get_posts', array($this, 'handle_featured_filter'));
        
        // Handle AJAX toggle
        add_action('wp_ajax_toggle_featured_post', array($this, 'handle_ajax_toggle'));
        
        // Add bulk actions
        foreach ($this->enabled_post_types as $post_type) {
            add_filter("bulk_actions-edit-{$post_type}", array($this, 'add_bulk_actions'));
            add_filter("handle_bulk_actions-edit-{$post_type}", array($this, 'handle_bulk_actions'), 10, 3);
        }
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'display_bulk_action_notices'));
    }
    
    /**
     * Add featured column to post list
     */
    public function add_featured_column($columns) {
        global $typenow;
        
        error_log('Featured Posts: add_featured_column called for post type: ' . $typenow);
        error_log('Featured Posts: enabled post types: ' . print_r($this->enabled_post_types, true));
        
        if (!in_array($typenow, $this->enabled_post_types)) {
            error_log('Featured Posts: Post type not enabled, returning original columns');
            return $columns;
        }
        
        // Insert featured column after title
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['featured'] = __('Featured', 'voxel-toolkit');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Display featured column content
     */
    public function display_featured_column($column, $post_id) {
        if ($column !== 'featured') {
            return;
        }
        
        $priority = get_post_meta($post_id, 'voxel:priority', true);
        $post_type = get_post_type($post_id);
        $expected_priority = isset($this->priority_values[$post_type]) ? $this->priority_values[$post_type] : 10;
        
        $is_featured = ($priority == $expected_priority);
        
        $star_class = $is_featured ? 'dashicons-star-filled featured-active' : 'dashicons-star-empty';
        $nonce = wp_create_nonce('toggle_featured_' . $post_id);
        
        echo '<a href="#" class="toggle-featured" data-post-id="' . $post_id . '" data-nonce="' . $nonce . '" title="' . 
             ($is_featured ? __('Remove from featured', 'voxel-toolkit') : __('Make featured', 'voxel-toolkit')) . '">';
        echo '<span class="dashicons ' . $star_class . '"></span>';
        echo '</a>';
    }
    
    /**
     * Make featured column sortable
     */
    public function make_featured_column_sortable($columns) {
        $columns['featured'] = 'featured';
        return $columns;
    }
    
    /**
     * Handle featured column sorting
     */
    public function handle_featured_sorting($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        $orderby = $query->get('orderby');
        if ($orderby === 'featured') {
            $query->set('meta_key', 'voxel:priority');
            $query->set('orderby', 'meta_value_num');
            $query->set('meta_query', array(
                'relation' => 'OR',
                array(
                    'key' => 'voxel:priority',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'voxel:priority',
                    'compare' => 'NOT EXISTS'
                )
            ));
        }
    }
    
    /**
     * Add featured filter to post list
     */
    public function add_featured_filter() {
        global $typenow;
        
        if (!in_array($typenow, $this->enabled_post_types)) {
            return;
        }
        
        $featured = isset($_GET['featured']) ? $_GET['featured'] : '';
        
        echo '<select name="featured">';
        echo '<option value="">' . __('All posts', 'voxel-toolkit') . '</option>';
        echo '<option value="yes"' . selected($featured, 'yes', false) . '>' . __('Featured only', 'voxel-toolkit') . '</option>';
        echo '<option value="no"' . selected($featured, 'no', false) . '>' . __('Non-featured only', 'voxel-toolkit') . '</option>';
        echo '</select>';
    }
    
    /**
     * Handle featured filter
     */
    public function handle_featured_filter($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        $featured = isset($_GET['featured']) ? $_GET['featured'] : '';
        if (empty($featured)) {
            return;
        }
        
        global $typenow;
        if (!in_array($typenow, $this->enabled_post_types)) {
            return;
        }
        
        $expected_priority = isset($this->priority_values[$typenow]) ? $this->priority_values[$typenow] : 10;
        
        if ($featured === 'yes') {
            // Show only featured posts
            $query->set('meta_key', 'voxel:priority');
            $query->set('meta_value', $expected_priority);
        } elseif ($featured === 'no') {
            // Show only non-featured posts
            $query->set('meta_query', array(
                'relation' => 'OR',
                array(
                    'key' => 'voxel:priority',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => 'voxel:priority',
                    'value' => $expected_priority,
                    'compare' => '!='
                )
            ));
        }
    }
    
    /**
     * Handle AJAX toggle
     */
    public function handle_ajax_toggle() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        
        if (!$post_id || !wp_verify_nonce($nonce, 'toggle_featured_' . $post_id)) {
            wp_die(__('Security check failed', 'voxel-toolkit'));
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_die(__('You do not have permission to edit this post', 'voxel-toolkit'));
        }
        
        $post_type = get_post_type($post_id);
        if (!in_array($post_type, $this->enabled_post_types)) {
            wp_die(__('Featured posts not enabled for this post type', 'voxel-toolkit'));
        }
        
        $expected_priority = isset($this->priority_values[$post_type]) ? $this->priority_values[$post_type] : 10;
        $current_priority = get_post_meta($post_id, 'voxel:priority', true);
        
        if ($current_priority == $expected_priority) {
            // Remove featured status
            delete_post_meta($post_id, 'voxel:priority');
            $is_featured = false;
        } else {
            // Set as featured
            update_post_meta($post_id, 'voxel:priority', $expected_priority);
            $is_featured = true;
        }
        
        // Force post update to refresh caches and trigger Voxel's reindexing
        wp_update_post(array(
            'ID' => $post_id,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1)
        ));
        
        // Trigger Voxel indexing for this specific post
        $this->reindex_post($post_id);
        
        wp_send_json_success(array(
            'featured' => $is_featured,
            'message' => $is_featured ? __('Post marked as featured', 'voxel-toolkit') : __('Post removed from featured', 'voxel-toolkit')
        ));
    }
    
    /**
     * Add bulk actions
     */
    public function add_bulk_actions($actions) {
        $actions['make_featured'] = __('Make featured', 'voxel-toolkit');
        $actions['remove_featured'] = __('Remove from featured', 'voxel-toolkit');
        return $actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        if (!in_array($action, array('make_featured', 'remove_featured'))) {
            return $redirect_to;
        }
        
        global $typenow;
        if (!in_array($typenow, $this->enabled_post_types)) {
            return $redirect_to;
        }
        
        $expected_priority = isset($this->priority_values[$typenow]) ? $this->priority_values[$typenow] : 10;
        $count = 0;
        
        foreach ($post_ids as $post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                continue;
            }
            
            if ($action === 'make_featured') {
                update_post_meta($post_id, 'voxel:priority', $expected_priority);
                $count++;
            } elseif ($action === 'remove_featured') {
                delete_post_meta($post_id, 'voxel:priority');
                $count++;
            }
            
            // Force post update to refresh caches
            wp_update_post(array(
                'ID' => $post_id,
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', 1)
            ));
            
            // Trigger Voxel indexing for this specific post
            $this->reindex_post($post_id);
        }
        
        $redirect_to = add_query_arg('bulk_featured_posts', $count, $redirect_to);
        $redirect_to = add_query_arg('bulk_featured_action', $action, $redirect_to);
        
        return $redirect_to;
    }
    
    /**
     * Display bulk action notices
     */
    public function display_bulk_action_notices() {
        if (!isset($_GET['bulk_featured_posts'])) {
            return;
        }
        
        $count = intval($_GET['bulk_featured_posts']);
        $action = isset($_GET['bulk_featured_action']) ? $_GET['bulk_featured_action'] : '';
        
        if ($count === 0) {
            return;
        }
        
        $message = '';
        if ($action === 'make_featured') {
            $message = sprintf(
                _n('%s post marked as featured.', '%s posts marked as featured.', $count, 'voxel-toolkit'),
                $count
            );
        } elseif ($action === 'remove_featured') {
            $message = sprintf(
                _n('%s post removed from featured.', '%s posts removed from featured.', $count, 'voxel-toolkit'),
                $count
            );
        }
        
        if ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'edit.php') {
            return;
        }
        
        global $typenow;
        if (!in_array($typenow, $this->enabled_post_types)) {
            return;
        }
        
        wp_enqueue_script(
            'voxel-toolkit-featured-posts',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/featured-posts.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );
        
        wp_localize_script('voxel-toolkit-featured-posts', 'voxelFeaturedPosts', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'error_message' => __('An error occurred. Please try again.', 'voxel-toolkit')
        ));
        
        // Add CSS for featured stars
        // Add CSS directly to the page
        add_action('admin_head', function() {
            echo '<style>
                .column-featured { width: 125px; text-align: center; }
                .toggle-featured { text-decoration: none; }
                .toggle-featured:hover { text-decoration: none; }
                .dashicons-star-filled.featured-active { color: #ffb900; }
                .dashicons-star-empty { color: #ccd0d4; }
                .dashicons-star-empty:hover { color: #ffb900; }
                @keyframes rotation {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(359deg); }
                }
            </style>';
        });
    }
    
    /**
     * Reindex a specific post for Voxel search/listings
     * Based on Essential Addons for Voxel implementation
     */
    private function reindex_post($post_id) {
        try {
            // Check if Voxel is available
            if (!class_exists('\Voxel\Post_Type')) {
                return;
            }
            
            $post = get_post($post_id);
            if (!$post) {
                return;
            }
            
            // Get the Voxel post type
            $voxel_post_type = \Voxel\Post_Type::get($post->post_type);
            if (!$voxel_post_type) {
                return;
            }
            
            // Get the index table and reindex this specific post
            $table = $voxel_post_type->get_index_table();
            if ($table && method_exists($table, 'index')) {
                $table->index([$post_id]);
                error_log("Voxel Toolkit Featured Posts: Reindexed post {$post_id}");
            }
            
        } catch (Exception $e) {
            error_log("Voxel Toolkit Featured Posts: Error reindexing post {$post_id}: " . $e->getMessage());
        }
    }
}