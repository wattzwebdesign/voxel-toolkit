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
        
        // Frontend AJAX handler
        add_action('wp_ajax_voxel_toolkit_duplicate_post_frontend', array($this, 'duplicate_post_frontend'));
        
        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // Register Elementor widget
        add_action('elementor/widgets/widgets_registered', array($this, 'register_elementor_widget'));
        
        // Add Elementor widget category
        add_action('elementor/elements/categories_registered', array($this, 'add_elementor_widget_category'));
        
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
     * Check if current user can duplicate posts based on role settings
     */
    private function can_user_duplicate() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $settings = Voxel_Toolkit_Settings::instance();
        $duplicate_settings = $settings->get_function_settings('duplicate_post', array());
        $allowed_roles = isset($duplicate_settings['allowed_roles']) ? $duplicate_settings['allowed_roles'] : array('contributor', 'author', 'editor', 'administrator');
        
        // If "all_roles" is selected, allow everyone
        if (in_array('all_roles', $allowed_roles)) {
            return true;
        }
        
        // Check if user has any of the allowed roles
        $user = wp_get_current_user();
        $user_roles = $user->roles;
        
        foreach ($user_roles as $role) {
            if (in_array($role, $allowed_roles)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add duplicate link to row actions
     */
    public function add_duplicate_link($actions, $post) {
        if (!$this->is_enabled_for_post_type($post->post_type)) {
            return $actions;
        }
        
        // Check if user can duplicate based on role settings
        if (!$this->can_user_duplicate()) {
            return $actions;
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
        
        // Check if user can duplicate based on role settings
        if (!$this->can_user_duplicate()) {
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
        
        // Check if user can duplicate based on role settings
        if (!$this->can_user_duplicate()) {
            wp_die(__('You do not have permission to duplicate posts.', 'voxel-toolkit'));
        }
        
        if (!$this->is_enabled_for_post_type($post->post_type)) {
            wp_die(__('Duplication is not enabled for this post type.', 'voxel-toolkit'));
        }
        
        // Create the duplicate (use default suffix for admin duplication)
        $new_post_id = $this->create_duplicate($post, ' (Copy)');
        
        if (!$new_post_id) {
            wp_die(__('Post duplication failed.', 'voxel-toolkit'));
        }
        
        // Determine where to redirect
        $redirect_to = isset($_GET['redirect_to']) ? sanitize_text_field($_GET['redirect_to']) : '';
        
        if ($redirect_to === 'edit') {
            // Redirect to edit the new post
            wp_safe_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
            exit;
        } else {
            // Redirect back to the list with success message
            $redirect_url = urldecode($redirect_to);
            if (empty($redirect_url)) {
                $redirect_url = admin_url('edit.php?post_type=' . $post->post_type);
            }
            $redirect_url = add_query_arg('duplicated', $new_post_id, $redirect_url);
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Create a duplicate of the post
     */
    private function create_duplicate($post, $title_suffix = ' (Copy)') {
        // Check if this is an Elementor page
        $is_elementor_page = get_post_meta($post->ID, '_elementor_data', true);
        
        // For Elementor pages, use original post status to avoid draft mode issues
        // For non-Elementor pages, use draft status
        $post_status = $is_elementor_page ? $post->post_status : 'draft';
        
        // Prepare post data - using exact duplicate-page approach
        $args = array(
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
            'post_author' => get_current_user_id(),
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_parent' => $post->post_parent,
            'post_password' => $post->post_password,
            'post_status' => $post_status,
            'post_title' => $post->post_title . $title_suffix,
            'post_type' => $post->post_type,
            'to_ping' => $post->to_ping,
            'menu_order' => $post->menu_order,
        );
        
        // Insert the new post
        $new_post_id = wp_insert_post($args);
        
        if (is_wp_error($new_post_id)) {
            return false;
        }
        
        // Duplicate taxonomies - using exact duplicate-page approach
        $taxonomies = array_map('sanitize_text_field', get_object_taxonomies($post->post_type));
        if (!empty($taxonomies) && is_array($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                $post_terms = wp_get_object_terms($post->ID, $taxonomy, array('fields' => 'slugs'));
                wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
            }
        }
        
        // Duplicate all post meta - using exact duplicate-page approach
        $post_meta_keys = get_post_custom_keys($post->ID);
        if (!empty($post_meta_keys)) {
            foreach ($post_meta_keys as $meta_key) {
                $meta_values = get_post_custom_values($meta_key, $post->ID);
                foreach ($meta_values as $meta_value) {
                    $meta_value = maybe_unserialize($meta_value);
                    update_post_meta($new_post_id, $meta_key, wp_slash($meta_value));
                }
            }
        }
        
        // Elementor compatibility - only for actual Elementor pages
        if ($is_elementor_page && is_plugin_active('elementor/elementor.php')) {
            // Ensure Elementor recognizes this as an Elementor page immediately
            update_post_meta($new_post_id, '_elementor_edit_mode', 'builder');
            if (defined('ELEMENTOR_VERSION')) {
                update_post_meta($new_post_id, '_elementor_version', ELEMENTOR_VERSION);
            }
            
            // Generate CSS
            $css = \Elementor\Core\Files\CSS\Post::create($new_post_id);
            $css->update();
        }
        
        return $new_post_id;
    }
    
    
    /**
     * Fix unicode encoding issues with umlauts and other special characters
     */
    private function fix_unicode_encoding($value) {
        if (empty($value) || !is_string($value)) {
            return $value;
        }
        
        // Check if the value contains unicode escape sequences
        if (strpos($value, '\u00') !== false) {
            // Convert unicode escape sequences back to proper UTF-8
            $value = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
                return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE');
            }, $value);
        }
        
        // Handle JSON data that might contain escaped unicode
        if ($this->is_json($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Re-encode with proper UTF-8 handling
                $value = wp_json_encode($decoded, JSON_UNESCAPED_UNICODE);
            }
        }
        
        return $value;
    }
    
    /**
     * Check if a string is valid JSON
     */
    private function is_json($string) {
        if (!is_string($string)) {
            return false;
        }
        
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
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
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        // Only enqueue for users who can duplicate
        if ($this->can_user_duplicate()) {
            wp_enqueue_script('jquery');
            wp_add_inline_script('jquery', $this->get_frontend_js());
        }
    }
    
    /**
     * Get frontend JavaScript
     */
    private function get_frontend_js() {
        return "
        jQuery(document).ready(function($) {
            $(document).on('click', '.voxel-toolkit-duplicate-btn', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var postId = button.data('post-id');
                var redirectType = button.data('redirect');
                var originalText = button.text();
                var copyingText = button.data('copying-text') || 'Duplicating...';
                var titleSuffix = button.data('title-suffix') || '(Copy)';
                
                // Disable button and show loading
                button.prop('disabled', true).text(copyingText);
                
                $.ajax({
                    url: '" . admin_url('admin-ajax.php') . "',
                    type: 'POST',
                    data: {
                        action: 'voxel_toolkit_duplicate_post_frontend',
                        post_id: postId,
                        redirect_type: redirectType,
                        title_suffix: titleSuffix,
                        nonce: '" . wp_create_nonce('voxel_toolkit_duplicate_frontend') . "'
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            alert('Error: ' + response.data.message);
                            button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        alert('Error: Could not duplicate post.');
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });
        });
        ";
    }
    
    /**
     * Handle frontend post duplication via AJAX
     */
    public function duplicate_post_frontend() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'voxel_toolkit_duplicate_frontend')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $redirect_type = isset($_POST['redirect_type']) ? sanitize_text_field($_POST['redirect_type']) : 'create_page';
        $title_suffix = isset($_POST['title_suffix']) ? sanitize_text_field($_POST['title_suffix']) : ' (Copy)';
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(array('message' => 'Post not found'));
        }
        
        if (!$this->is_enabled_for_post_type($post->post_type)) {
            wp_send_json_error(array('message' => 'Duplication not enabled for this post type'));
        }
        
        // Check if user can duplicate based on role settings
        if (!$this->can_user_duplicate()) {
            wp_send_json_error(array('message' => 'You do not have permission to duplicate posts'));
        }
        
        // Create the duplicate
        $new_post_id = $this->create_duplicate($post, $title_suffix);
        
        if (!$new_post_id) {
            wp_send_json_error(array('message' => 'Failed to duplicate post'));
        }
        
        // Determine redirect URL
        if ($redirect_type === 'create_page') {
            // Check if there's a custom redirect page configured for this post type
            $settings = Voxel_Toolkit_Settings::instance();
            $duplicate_settings = $settings->get_function_settings('duplicate_post', array());
            $redirect_pages = isset($duplicate_settings['redirect_pages']) ? $duplicate_settings['redirect_pages'] : array();
            
            if (!empty($redirect_pages[$post->post_type])) {
                // Use the configured page
                $page_id = $redirect_pages[$post->post_type];
                $redirect_url = get_permalink($page_id);
                if ($redirect_url) {
                    $redirect_url = add_query_arg('post_id', $new_post_id, $redirect_url);
                } else {
                    // Fallback if page doesn't exist
                    $redirect_url = home_url('/create-' . $post->post_type . '/?post_id=' . $new_post_id);
                }
            } else {
                // Use default URL pattern
                $redirect_url = home_url('/create-' . $post->post_type . '/?post_id=' . $new_post_id);
            }
        } else {
            $redirect_url = get_permalink($post_id) . '?duplicated=' . $new_post_id;
        }
        
        wp_send_json_success(array('redirect_url' => $redirect_url));
    }
    
    /**
     * Register Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        // Check if Elementor is active
        if (!class_exists('\Elementor\Widget_Base')) {
            return;
        }
        
        // Include widget file
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-duplicate-post-widget.php';
        
        // Register the widget
        $widgets_manager->register_widget_type(new Voxel_Toolkit_Duplicate_Post_Widget());
    }
    
    /**
     * Add Elementor widget category
     */
    public function add_elementor_widget_category($elements_manager) {
        $elements_manager->add_category(
            'voxel-toolkit',
            [
                'title' => __('Voxel Toolkit', 'voxel-toolkit'),
                'icon' => 'fa fa-toolbox',
            ]
        );
    }
}