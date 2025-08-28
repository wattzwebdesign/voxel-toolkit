<?php
/**
 * Duplicate Post/Page functionality
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Duplicate_Post {
    
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
        // Add duplicate link to row actions
        add_filter('post_row_actions', array($this, 'add_duplicate_link'), 10, 2);
        add_filter('page_row_actions', array($this, 'add_duplicate_link'), 10, 2);
        
        // Add duplicate button to post edit screen
        add_action('post_submitbox_misc_actions', array($this, 'add_duplicate_button'));
        
        // Handle duplication
        add_action('admin_action_voxel_toolkit_duplicate_post', array($this, 'duplicate_post'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'duplication_admin_notice'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Check if duplication is enabled for a post type
     */
    private function is_enabled_for_post_type($post_type) {
        $settings = Voxel_Toolkit_Settings::instance();
        $duplicate_settings = $settings->get_function_settings('duplicate_post', array());
        
        if (!isset($duplicate_settings['enabled']) || !$duplicate_settings['enabled']) {
            return false;
        }
        
        $enabled_post_types = isset($duplicate_settings['post_types']) ? $duplicate_settings['post_types'] : array();
        return in_array($post_type, $enabled_post_types);
    }
    
    /**
     * Add duplicate link to row actions
     */
    public function add_duplicate_link($actions, $post) {
        if (!$this->is_enabled_for_post_type($post->post_type)) {
            return $actions;
        }
        
        if (!current_user_can('edit_posts') || !current_user_can('edit_post', $post->ID)) {
            return $actions;
        }
        
        $duplicate_url = wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'voxel_toolkit_duplicate_post',
                    'post' => $post->ID,
                    'redirect_to' => urlencode($_SERVER['REQUEST_URI'])
                ),
                admin_url('admin.php')
            ),
            'voxel_toolkit_duplicate_post_' . $post->ID
        );
        
        $actions['duplicate'] = '<a href="' . esc_url($duplicate_url) . '" title="' . esc_attr__('Duplicate this item', 'voxel-toolkit') . '">' . __('Duplicate', 'voxel-toolkit') . '</a>';
        
        return $actions;
    }
    
    /**
     * Add duplicate button to post edit screen
     */
    public function add_duplicate_button() {
        global $post;
        
        if (!$post || !$post->ID || !$this->is_enabled_for_post_type($post->post_type)) {
            return;
        }
        
        // Only show for existing posts (not new posts)
        if ($post->post_status === 'auto-draft') {
            return;
        }
        
        if (!current_user_can('edit_posts') || !current_user_can('edit_post', $post->ID)) {
            return;
        }
        
        $duplicate_url = wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'voxel_toolkit_duplicate_post',
                    'post' => $post->ID,
                    'redirect_to' => 'edit'
                ),
                admin_url('admin.php')
            ),
            'voxel_toolkit_duplicate_post_' . $post->ID
        );
        ?>
        <div class="misc-pub-section voxel-toolkit-duplicate">
            <span class="dashicons dashicons-admin-page" style="color: #82878c; margin-right: 3px; vertical-align: text-bottom;"></span>
            <a href="<?php echo esc_url($duplicate_url); ?>" class="voxel-toolkit-duplicate-link" style="text-decoration: none;">
                <?php _e('Duplicate This', 'voxel-toolkit'); ?>
            </a>
        </div>
        <?php
    }
    
    /**
     * Handle post duplication
     */
    public function duplicate_post() {
        // Check nonce and permissions
        if (!isset($_GET['post'])) {
            wp_die(__('No post to duplicate has been specified!', 'voxel-toolkit'));
        }
        
        $post_id = intval($_GET['post']);
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'voxel_toolkit_duplicate_post_' . $post_id)) {
            wp_die(__('Security check failed', 'voxel-toolkit'));
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_die(__('Post not found!', 'voxel-toolkit'));
        }
        
        if (!current_user_can('edit_posts') || !current_user_can('edit_post', $post_id)) {
            wp_die(__('You do not have permission to duplicate this post.', 'voxel-toolkit'));
        }
        
        if (!$this->is_enabled_for_post_type($post->post_type)) {
            wp_die(__('Duplication is not enabled for this post type.', 'voxel-toolkit'));
        }
        
        // Create the duplicate
        $new_post_id = $this->create_duplicate($post);
        
        if (!$new_post_id) {
            wp_die(__('Post duplication failed.', 'voxel-toolkit'));
        }
        
        // Determine where to redirect
        $redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : '';
        
        if ($redirect_to === 'edit') {
            // Redirect to edit the new post
            wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
        } else {
            // Redirect back to the list with success message
            $redirect_url = urldecode($redirect_to);
            if (empty($redirect_url)) {
                $redirect_url = admin_url('edit.php?post_type=' . $post->post_type);
            }
            $redirect_url = add_query_arg('duplicated', $new_post_id, $redirect_url);
            wp_redirect($redirect_url);
        }
        
        exit;
    }
    
    /**
     * Create a duplicate of the post
     */
    private function create_duplicate($post) {
        // Prepare post data
        $new_post = array(
            'post_title'    => $post->post_title . ' (Copy)',
            'post_content'  => $post->post_content,
            'post_excerpt'  => $post->post_excerpt,
            'post_status'   => 'draft',
            'post_type'     => $post->post_type,
            'post_author'   => get_current_user_id(),
            'post_parent'   => $post->post_parent,
            'menu_order'    => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status'   => $post->ping_status,
            'post_password' => $post->post_password,
        );
        
        // Insert the new post
        $new_post_id = wp_insert_post($new_post);
        
        if (!$new_post_id || is_wp_error($new_post_id)) {
            return false;
        }
        
        // Duplicate post meta
        $this->duplicate_post_meta($post->ID, $new_post_id);
        
        // Duplicate taxonomies
        $this->duplicate_post_taxonomies($post->ID, $new_post_id, $post->post_type);
        
        return $new_post_id;
    }
    
    /**
     * Duplicate post meta
     */
    private function duplicate_post_meta($source_id, $target_id) {
        global $wpdb;
        
        // Get all post meta
        $post_meta = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
                $source_id
            )
        );
        
        if (empty($post_meta)) {
            return;
        }
        
        // Skip certain meta keys
        $skip_keys = array('_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date');
        
        foreach ($post_meta as $meta) {
            if (in_array($meta->meta_key, $skip_keys)) {
                continue;
            }
            
            // Add meta to new post
            add_post_meta($target_id, $meta->meta_key, maybe_unserialize($meta->meta_value));
        }
    }
    
    /**
     * Duplicate post taxonomies
     */
    private function duplicate_post_taxonomies($source_id, $target_id, $post_type) {
        // Get all taxonomies for this post type
        $taxonomies = get_object_taxonomies($post_type);
        
        if (empty($taxonomies)) {
            return;
        }
        
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($source_id, $taxonomy, array('fields' => 'ids'));
            
            if (!empty($terms)) {
                wp_set_object_terms($target_id, $terms, $taxonomy);
            }
        }
    }
    
    /**
     * Display admin notice after duplication
     */
    public function duplication_admin_notice() {
        if (!isset($_GET['duplicated'])) {
            return;
        }
        
        $duplicated_id = intval($_GET['duplicated']);
        $edit_link = admin_url('post.php?action=edit&post=' . $duplicated_id);
        
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php _e('Post duplicated successfully.', 'voxel-toolkit'); ?>
                <a href="<?php echo esc_url($edit_link); ?>"><?php _e('Edit duplicated post', 'voxel-toolkit'); ?></a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        global $post;
        
        // Only add styles if we have a post and duplication is enabled for this post type
        if (!$post || !$this->is_enabled_for_post_type($post->post_type)) {
            return;
        }
        
        ?>
        <style>
            .voxel-toolkit-duplicate {
                padding: 6px 10px 8px;
            }
            .voxel-toolkit-duplicate-link:hover {
                text-decoration: underline;
            }
        </style>
        <?php
    }
}