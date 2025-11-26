<?php
/**
 * Suggest Edits Function
 *
 * Allow users to suggest edits to post fields
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Suggest_Edits {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Database table name
     */
    private $table_name;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'voxel_edit_suggestions';

        // Create table on activation
        add_action('admin_init', array($this, 'maybe_create_table'));

        // Handle form submissions
        add_action('template_redirect', array($this, 'handle_suggestion_submission'));

        // AJAX handlers
        add_action('wp_ajax_vt_accept_suggestion', array($this, 'ajax_accept_suggestion'));
        add_action('wp_ajax_vt_reject_suggestion', array($this, 'ajax_reject_suggestion'));
        add_action('wp_ajax_vt_save_accepted_suggestions', array($this, 'ajax_save_accepted_suggestions'));
        add_action('wp_ajax_vt_submit_suggestion', array($this, 'ajax_submit_suggestion'));
        add_action('wp_ajax_nopriv_vt_submit_suggestion', array($this, 'ajax_submit_suggestion'));
        add_action('wp_ajax_vt_bulk_action_suggestions', array($this, 'ajax_bulk_action_suggestions'));
        add_action('wp_ajax_vt_delete_post_suggestion', array($this, 'ajax_delete_post_suggestion'));

        // Register widgets
        add_action('elementor/widgets/register', array($this, 'register_widgets'));

        // Register app event
        add_filter('voxel/app-events/register', array($this, 'register_app_event'));
        add_filter('voxel/app-events/categories', array($this, 'register_event_category'));

        // Add admin menu pages (priority 999 to ensure it runs after Voxel's menu items)
        add_action('admin_menu', array($this, 'add_admin_menu_pages'), 999);
    }

    /**
     * Create database table if it doesn't exist
     */
    public function maybe_create_table() {
        global $wpdb;

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");

        if ($table_exists != $this->table_name) {
            $this->create_table();
        }
    }

    /**
     * Create suggestions table
     */
    private function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id bigint(20) UNSIGNED NOT NULL,
            field_key varchar(255) NOT NULL,
            current_value longtext,
            suggested_value longtext,
            suggester_user_id bigint(20) UNSIGNED DEFAULT 0,
            suggester_email varchar(255),
            suggester_name varchar(255),
            proof_images longtext,
            is_incorrect tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Register Elementor widgets
     */
    public function register_widgets($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-suggest-edits-widget.php';
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-pending-suggestions-widget.php';

        $widgets_manager->register(new \Voxel_Toolkit_Suggest_Edits_Widget());
        $widgets_manager->register(new \Voxel_Toolkit_Pending_Suggestions_Widget());
    }

    /**
     * Add admin menu pages under each enabled post type
     */
    public function add_admin_menu_pages() {
        global $submenu;

        $settings = Voxel_Toolkit_Settings::instance();
        $config = $settings->get_function_settings('suggest_edits');
        $enabled_post_types = $config['post_types'] ?? array();

        if (empty($enabled_post_types)) {
            return;
        }

        foreach ($enabled_post_types as $post_type_slug) {
            $post_type_obj = get_post_type_object($post_type_slug);
            if (!$post_type_obj) {
                continue;
            }

            // Get pending count for this post type
            $pending_count = $this->get_pending_count($post_type_slug);
            $menu_title = $pending_count > 0
                ? sprintf(__('Suggested Edits %s', 'voxel-toolkit'), '<span class="awaiting-mod">' . $pending_count . '</span>')
                : __('Suggested Edits', 'voxel-toolkit');

            add_submenu_page(
                'edit.php?post_type=' . $post_type_slug,
                __('Suggested Edits', 'voxel-toolkit'),
                $menu_title,
                'edit_posts',
                'vt-suggested-edits-' . $post_type_slug,
                array($this, 'render_admin_page')
            );

            // Reposition the menu item to appear right above "Edit Post Type"
            $parent_slug = 'edit.php?post_type=' . $post_type_slug;
            if (isset($submenu[$parent_slug])) {
                $suggested_edits_item = null;
                $suggested_edits_key = null;

                // Find our newly added menu item
                foreach ($submenu[$parent_slug] as $key => $item) {
                    if ($item[2] === 'vt-suggested-edits-' . $post_type_slug) {
                        $suggested_edits_item = $item;
                        $suggested_edits_key = $key;
                        break;
                    }
                }

                // Find the "Edit Post Type" menu item (it contains 'post_type=' in the URL)
                $edit_post_type_key = null;
                foreach ($submenu[$parent_slug] as $key => $item) {
                    // Look for Voxel's "Edit Post Type" menu item
                    if (strpos($item[2], 'admin.php?page=voxel-post-types&action=edit-post-type') !== false) {
                        $edit_post_type_key = $key;
                        break;
                    }
                }

                // If we found both items, reorder
                if ($suggested_edits_item !== null && $edit_post_type_key !== null && $suggested_edits_key !== null) {
                    // Remove our item from its current position
                    unset($submenu[$parent_slug][$suggested_edits_key]);

                    // Insert it right before "Edit Post Type"
                    $new_submenu = array();
                    foreach ($submenu[$parent_slug] as $key => $item) {
                        if ($key == $edit_post_type_key) {
                            // Insert our item before this one
                            $new_submenu[$edit_post_type_key - 0.5] = $suggested_edits_item;
                        }
                        $new_submenu[$key] = $item;
                    }

                    $submenu[$parent_slug] = $new_submenu;
                    ksort($submenu[$parent_slug]);
                }
            }
        }
    }

    /**
     * Render admin page for managing suggestions
     */
    public function render_admin_page() {
        global $wpdb;

        // Get current post type from URL
        $current_screen = get_current_screen();

        // Screen ID format is: {post_type}_page_vt-suggested-edits-{post_type}
        // Extract post type from the menu slug in URL
        if (isset($_GET['page']) && strpos($_GET['page'], 'vt-suggested-edits-') === 0) {
            $post_type = str_replace('vt-suggested-edits-', '', $_GET['page']);
        } else {
            $post_type = '';
        }

        if (!$post_type || !post_type_exists($post_type)) {
            echo '<div class="wrap"><h1>' . __('Suggested Edits', 'voxel-toolkit') . '</h1>';
            echo '<p>' . __('Invalid post type.', 'voxel-toolkit') . '</p></div>';
            return;
        }

        // Get filter parameters
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
        $order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // Build query
        $where = array("p.post_type = %s");
        $params = array($post_type);

        if ($status_filter !== 'all') {
            $where[] = "es.status = %s";
            $params[] = $status_filter;
        }

        if (!empty($search)) {
            $where[] = "(p.post_title LIKE %s OR es.field_key LIKE %s OR es.suggester_name LIKE %s OR es.suggester_email LIKE %s)";
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
        }

        $where_clause = implode(' AND ', $where);

        // Order by
        if ($orderby === 'status') {
            $order_clause = "es.status $order, es.created_at DESC";
        } elseif ($orderby === 'date') {
            $order_clause = "es.created_at $order";
        } else {
            $order_clause = "es.created_at DESC";
        }

        // Get suggestions
        $query = "SELECT es.*, p.post_title, p.post_type
            FROM {$this->table_name} es
            INNER JOIN {$wpdb->posts} p ON es.post_id = p.ID
            WHERE $where_clause
            ORDER BY $order_clause";

        $suggestions = $wpdb->get_results($wpdb->prepare($query, $params));

        // Enqueue admin styles and scripts
        wp_enqueue_style('vt-suggest-edits-admin', VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/suggest-edits-admin.css', array(), VOXEL_TOOLKIT_VERSION);
        wp_enqueue_script('vt-suggest-edits-admin', VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/suggest-edits-admin.js', array('jquery'), VOXEL_TOOLKIT_VERSION, true);
        wp_localize_script('vt-suggest-edits-admin', 'vtSuggestEdits', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_suggest_edits'),
        ));

        ?>
        <div class="wrap vt-suggest-edits-admin">
            <h1><?php echo esc_html(get_post_type_object($post_type)->labels->name); ?> - <?php _e('Suggested Edits', 'voxel-toolkit'); ?></h1>

            <!-- Filter and Search Controls -->
            <div class="vt-filters">
                <form method="get" action="">
                    <input type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">

                    <div class="vt-filter-group">
                        <label for="status-filter"><?php _e('Status:', 'voxel-toolkit'); ?></label>
                        <select name="status_filter" id="status-filter">
                            <option value="all" <?php selected($status_filter, 'all'); ?>><?php _e('All', 'voxel-toolkit'); ?></option>
                            <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'voxel-toolkit'); ?></option>
                            <option value="accepted" <?php selected($status_filter, 'accepted'); ?>><?php _e('Accepted', 'voxel-toolkit'); ?></option>
                            <option value="rejected" <?php selected($status_filter, 'rejected'); ?>><?php _e('Rejected', 'voxel-toolkit'); ?></option>
                        </select>
                    </div>

                    <div class="vt-filter-group">
                        <label for="orderby"><?php _e('Sort by:', 'voxel-toolkit'); ?></label>
                        <select name="orderby" id="orderby">
                            <option value="date" <?php selected($orderby, 'date'); ?>><?php _e('Date', 'voxel-toolkit'); ?></option>
                            <option value="status" <?php selected($orderby, 'status'); ?>><?php _e('Status', 'voxel-toolkit'); ?></option>
                        </select>
                    </div>

                    <div class="vt-filter-group">
                        <label for="order"><?php _e('Order:', 'voxel-toolkit'); ?></label>
                        <select name="order" id="order">
                            <option value="desc" <?php selected($order, 'DESC'); ?>><?php _e('Descending', 'voxel-toolkit'); ?></option>
                            <option value="asc" <?php selected($order, 'ASC'); ?>><?php _e('Ascending', 'voxel-toolkit'); ?></option>
                        </select>
                    </div>

                    <div class="vt-filter-group vt-search-group">
                        <label for="search-input"><?php _e('Search:', 'voxel-toolkit'); ?></label>
                        <input type="text" name="s" id="search-input" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search by post, field, or suggester...', 'voxel-toolkit'); ?>">
                    </div>

                    <button type="submit" class="button"><?php _e('Filter', 'voxel-toolkit'); ?></button>
                    <?php if ($status_filter !== 'all' || !empty($search) || $orderby !== 'date' || $order !== 'DESC'): ?>
                        <a href="<?php echo admin_url('edit.php?post_type=' . $post_type . '&page=' . $_GET['page']); ?>" class="button"><?php _e('Reset', 'voxel-toolkit'); ?></a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($suggestions)): ?>
                <p><?php _e('No suggestions found.', 'voxel-toolkit'); ?></p>
            <?php else: ?>
                <!-- Bulk Actions -->
                <div class="vt-bulk-actions">
                    <select id="bulk-action-selector">
                        <option value=""><?php _e('Bulk Actions', 'voxel-toolkit'); ?></option>
                        <option value="accept"><?php _e('Accept', 'voxel-toolkit'); ?></option>
                        <option value="reject"><?php _e('Reject', 'voxel-toolkit'); ?></option>
                        <option value="delete"><?php _e('Delete', 'voxel-toolkit'); ?></option>
                    </select>
                    <button type="button" id="bulk-action-apply" class="button"><?php _e('Apply', 'voxel-toolkit'); ?></button>
                    <span id="bulk-action-message"></span>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="check-column"><input type="checkbox" id="select-all-suggestions"></td>
                            <th><?php _e('Post', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Field', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Current Value', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Suggested Value', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Submitted By', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Date', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Images', 'voxel-toolkit'); ?></th>
                            <th><?php _e('Status', 'voxel-toolkit'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suggestions as $suggestion): ?>
                            <?php
                            // Prepare image URLs for data attribute and count
                            $image_urls = array();
                            if (!empty($suggestion->proof_images)) {
                                error_log('VT Backend: Raw proof_images: ' . $suggestion->proof_images);
                                $image_ids = json_decode($suggestion->proof_images, true);
                                error_log('VT Backend: Decoded IDs: ' . print_r($image_ids, true));
                                if (is_array($image_ids)) {
                                    foreach ($image_ids as $image_id) {
                                        $url = wp_get_attachment_url($image_id);
                                        error_log('VT Backend: Image ID ' . $image_id . ' -> URL: ' . ($url ?: 'NOT FOUND'));
                                        if ($url) {
                                            $image_urls[] = $url;
                                        }
                                    }
                                }
                                error_log('VT Backend: Final image URLs: ' . print_r($image_urls, true));
                            }

                            $data_attrs = sprintf(
                                'data-suggestion-id="%s" data-post-title="%s" data-field-key="%s" data-current-value="%s" data-suggested-value="%s" data-is-incorrect="%s" data-suggester-name="%s" data-suggester-email="%s" data-suggester-user-id="%s" data-proof-images="%s" data-post-id="%s" data-date="%s" data-status="%s"',
                                esc_attr($suggestion->id),
                                esc_attr($suggestion->post_title),
                                esc_attr($suggestion->field_key),
                                esc_attr($suggestion->current_value),
                                esc_attr($suggestion->suggested_value),
                                esc_attr($suggestion->is_incorrect),
                                esc_attr($suggestion->suggester_name),
                                esc_attr($suggestion->suggester_email),
                                esc_attr($suggestion->suggester_user_id),
                                esc_attr(json_encode($image_urls)),
                                esc_attr($suggestion->post_id),
                                esc_attr(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($suggestion->created_at))),
                                esc_attr($suggestion->status)
                            );
                            ?>
                            <tr data-suggestion-id="<?php echo esc_attr($suggestion->id); ?>" class="suggestion-row status-<?php echo esc_attr($suggestion->status); ?>">
                                <th scope="row" class="check-column">
                                    <input type="checkbox" class="suggestion-checkbox" value="<?php echo esc_attr($suggestion->id); ?>">
                                </th>
                                <td class="post column-post has-row-actions column-primary">
                                    <strong>
                                        <a href="<?php echo get_edit_post_link($suggestion->post_id); ?>" target="_blank" class="row-title">
                                            <?php echo esc_html($suggestion->post_title); ?>
                                        </a>
                                    </strong>
                                    <div class="row-actions">
                                        <span class="view">
                                            <a href="#" class="vt-view-suggestion" <?php echo $data_attrs; ?>>
                                                <?php _e('View', 'voxel-toolkit'); ?>
                                            </a>
                                        </span>
                                        <?php if ($suggestion->status === 'pending'): ?>
                                            <?php if ($suggestion->field_key === '_permanently_closed'): ?>
                                                | <span class="delete">
                                                    <a href="#" class="vt-delete-post"
                                                       data-suggestion-id="<?php echo esc_attr($suggestion->id); ?>"
                                                       data-post-id="<?php echo esc_attr($suggestion->post_id); ?>">
                                                        <?php _e('Delete Post', 'voxel-toolkit'); ?>
                                                    </a>
                                                </span>
                                            <?php else: ?>
                                                | <span class="accept">
                                                    <a href="#" class="vt-accept-suggestion"
                                                       data-suggestion-id="<?php echo esc_attr($suggestion->id); ?>"
                                                       style="color: #00a32a;">
                                                        <?php _e('Accept', 'voxel-toolkit'); ?>
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                            | <span class="reject">
                                                <a href="#" class="vt-reject-suggestion"
                                                   data-suggestion-id="<?php echo esc_attr($suggestion->id); ?>"
                                                   style="color: #d63638;">
                                                    <?php _e('Reject', 'voxel-toolkit'); ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    // Special handling for permanently closed field
                                    if ($suggestion->field_key === '_permanently_closed') {
                                        echo esc_html__('Permanently Closed?', 'voxel-toolkit');
                                    } else {
                                        echo esc_html($suggestion->field_key);
                                    }
                                    ?>
                                </td>
                                <td class="current-value">
                                    <?php
                                    if ($suggestion->is_incorrect && empty($suggestion->suggested_value)) {
                                        echo '<span class="incorrect-marker">' . __('Marked as incorrect', 'voxel-toolkit') . '</span>';
                                    } else {
                                        echo esc_html(wp_trim_words($suggestion->current_value, 10));
                                    }
                                    ?>
                                </td>
                                <td class="suggested-value">
                                    <?php
                                    if (!empty($suggestion->suggested_value)) {
                                        echo esc_html(wp_trim_words($suggestion->suggested_value, 10));
                                    } else {
                                        echo '<em>' . __('(Remove)', 'voxel-toolkit') . '</em>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($suggestion->suggester_user_id) {
                                        $user = get_userdata($suggestion->suggester_user_id);
                                        echo esc_html($user->display_name);
                                    } else {
                                        echo esc_html($suggestion->suggester_name) . ' <em>(Guest)</em>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($suggestion->created_at))); ?></td>
                                <td class="images column-images" style="text-align: center;">
                                    <?php if (!empty($image_urls) && count($image_urls) > 0): ?>
                                        <span class="dashicons dashicons-camera" style="color: #2271b1; font-size: 20px;" title="<?php echo esc_attr(sprintf(__('%d image(s) attached', 'voxel-toolkit'), count($image_urls))); ?>"></span>
                                        <br>
                                        <small><?php echo count($image_urls); ?></small>
                                    <?php else: ?>
                                        <span style="color: #dcdcde;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($suggestion->status); ?>">
                                        <?php echo esc_html(ucfirst($suggestion->status)); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- View Suggestion Modal -->
            <div id="vt-suggestion-modal" class="vt-modal" style="display: none;">
                <div class="vt-modal-overlay"></div>
                <div class="vt-modal-content">
                    <div class="vt-modal-header">
                        <h2><?php _e('View Suggestion', 'voxel-toolkit'); ?></h2>
                        <button class="vt-modal-close">&times;</button>
                    </div>
                    <div class="vt-modal-body">
                        <div class="vt-detail-row">
                            <strong><?php _e('Post:', 'voxel-toolkit'); ?></strong>
                            <span id="modal-post-title"></span>
                        </div>
                        <div class="vt-detail-row">
                            <strong><?php _e('Field:', 'voxel-toolkit'); ?></strong>
                            <span id="modal-field-key"></span>
                        </div>
                        <div class="vt-detail-row">
                            <strong><?php _e('Current Value:', 'voxel-toolkit'); ?></strong>
                            <div id="modal-current-value" class="vt-value-box"></div>
                        </div>
                        <div class="vt-detail-row">
                            <strong><?php _e('Suggested Value:', 'voxel-toolkit'); ?></strong>
                            <div id="modal-suggested-value" class="vt-value-box"></div>
                        </div>
                        <div class="vt-detail-row" id="modal-proof-images-row" style="display: none;">
                            <strong><?php _e('Proof Images:', 'voxel-toolkit'); ?></strong>
                            <div id="modal-proof-images" class="vt-proof-images"></div>
                        </div>
                        <div class="vt-detail-row">
                            <strong><?php _e('Submitted By:', 'voxel-toolkit'); ?></strong>
                            <span id="modal-suggester"></span>
                        </div>
                        <div class="vt-detail-row">
                            <strong><?php _e('Date:', 'voxel-toolkit'); ?></strong>
                            <span id="modal-date"></span>
                        </div>
                        <div class="vt-detail-row">
                            <strong><?php _e('Status:', 'voxel-toolkit'); ?></strong>
                            <span id="modal-status"></span>
                        </div>
                    </div>
                    <div class="vt-modal-footer">
                        <button class="button button-large vt-modal-close"><?php _e('Close', 'voxel-toolkit'); ?></button>
                        <div id="modal-actions"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle suggestion submission from frontend form
     */
    public function handle_suggestion_submission() {
        // Implementation will be in AJAX handler
    }

    /**
     * AJAX: Submit new suggestion
     */
    public function ajax_submit_suggestion() {
        check_ajax_referer('vt_suggest_edits', 'nonce');

        $post_id = absint($_POST['post_id']);

        // Handle JSON-encoded suggestions from FormData
        $suggestions = isset($_POST['suggestions']) ? json_decode(stripslashes($_POST['suggestions']), true) : array();
        if (!is_array($suggestions)) {
            $suggestions = array();
        }

        $permanently_closed = isset($_POST['permanently_closed']) && $_POST['permanently_closed'] == 1;

        // Handle file uploads
        $uploaded_image_ids = array();
        error_log('VT: Checking for proof image uploads...');
        error_log('VT: $_FILES: ' . print_r($_FILES, true));

        if (!empty($_FILES['proof_images'])) {
            error_log('VT: proof_images found in $_FILES');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $files = $_FILES['proof_images'];
            $file_count = count($files['name']);
            error_log('VT: File count: ' . $file_count);

            for ($i = 0; $i < $file_count; $i++) {
                error_log('VT: Processing file ' . $i . ' - Error code: ' . $files['error'][$i]);
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = array(
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i]
                    );

                    error_log('VT: Attempting to upload file: ' . $file['name']);
                    $upload = wp_handle_upload($file, array('test_form' => false));

                    if (!isset($upload['error'])) {
                        error_log('VT: Upload successful: ' . $upload['file']);
                        $attachment_id = wp_insert_attachment(array(
                            'post_mime_type' => $upload['type'],
                            'post_title'     => sanitize_file_name($file['name']),
                            'post_content'   => '',
                            'post_status'    => 'inherit'
                        ), $upload['file']);

                        if (!is_wp_error($attachment_id)) {
                            error_log('VT: Attachment created with ID: ' . $attachment_id);
                            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
                            $uploaded_image_ids[] = $attachment_id;
                        } else {
                            error_log('VT: Error creating attachment: ' . $attachment_id->get_error_message());
                        }
                    } else {
                        error_log('VT: Upload failed: ' . $upload['error']);
                    }
                } else {
                    error_log('VT: File ' . $i . ' has upload error code: ' . $files['error'][$i]);
                }
            }
        } else {
            error_log('VT: No proof_images in $_FILES');
        }

        error_log('VT: Total uploaded image IDs: ' . count($uploaded_image_ids));
        error_log('VT: Uploaded IDs array: ' . print_r($uploaded_image_ids, true));

        // Allow submission if either we have suggestions OR permanently closed is marked
        if (!$post_id || (empty($suggestions) && !$permanently_closed)) {
            wp_send_json_error(__('Invalid data provided', 'voxel-toolkit'));
        }

        // Get suggester info
        $user_id = get_current_user_id();
        $suggester_email = '';
        $suggester_name = '';

        if ($user_id) {
            $user = get_userdata($user_id);
            $suggester_email = $user->user_email;
            $suggester_name = $user->display_name;
        } else {
            $suggester_email = sanitize_email($_POST['suggester_email'] ?? '');
            $suggester_name = sanitize_text_field($_POST['suggester_name'] ?? 'Guest');
        }

        global $wpdb;
        $inserted_count = 0;

        // Encode uploaded images for storage (needed for both regular and permanently_closed suggestions)
        $proof_images_json = !empty($uploaded_image_ids) ? json_encode($uploaded_image_ids) : '';
        error_log('VT: Proof images JSON to be saved: ' . $proof_images_json);

        // If permanently closed is marked, create a special suggestion entry
        if ($permanently_closed) {
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'post_id' => $post_id,
                    'field_key' => '_permanently_closed',
                    'current_value' => 'No',
                    'suggested_value' => 'Yes',
                    'suggester_user_id' => $user_id,
                    'suggester_email' => $suggester_email,
                    'suggester_name' => $suggester_name,
                    'proof_images' => $proof_images_json,
                    'is_incorrect' => 0,
                    'status' => 'pending',
                ),
                array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s')
            );

            if ($result) {
                $inserted_count++;
            }

            // If permanently closed, we don't process other suggestions
            if ($inserted_count > 0) {
                // Dispatch app event
                try {
                    (new Voxel_Toolkit_Suggestion_Submitted_Event())->dispatch(
                        $post_id,
                        $user_id,
                        $suggester_email,
                        $suggester_name,
                        $inserted_count
                    );
                } catch (\Exception $e) {
                    error_log('Voxel Toolkit: Failed to dispatch suggestion event: ' . $e->getMessage());
                }

                wp_send_json_success(array(
                    'message' => __('Thank you! Your permanently closed suggestion has been submitted.', 'voxel-toolkit'),
                    'count' => $inserted_count,
                ));
            } else {
                wp_send_json_error(__('Failed to submit suggestion', 'voxel-toolkit'));
            }
            return;
        }

        // Process regular field suggestions
        foreach ($suggestions as $suggestion) {
            $field_key = sanitize_key($suggestion['field_key']);
            $suggested_value = wp_kses_post($suggestion['suggested_value'] ?? '');
            $is_incorrect = isset($suggestion['is_incorrect']) ? 1 : 0;

            // Special handling for work_hours and location fields - preserve JSON structure
            $is_work_hours = ($field_key === 'work_hours' || strpos($field_key, 'work-hours') !== false);
            $is_location = ($field_key === 'location');

            // Get current value from Voxel
            $current_value = '';
            if (class_exists('\\Voxel\\Post')) {
                $voxel_post = \Voxel\Post::get($post_id);
                if ($voxel_post) {
                    $field_obj = $voxel_post->get_field($field_key);
                    if (is_object($field_obj) && method_exists($field_obj, 'get_value')) {
                        $current_value = $field_obj->get_value();
                    } else {
                        $current_value = $field_obj;
                    }

                    // For work_hours and location, keep as JSON string
                    if ($is_work_hours || $is_location) {
                        if (is_string($current_value)) {
                            // Already JSON string
                            $current_value = $current_value;
                        } elseif (is_array($current_value)) {
                            $current_value = json_encode($current_value);
                        }
                    }
                    // Convert arrays/objects to string for storage
                    elseif (is_array($current_value)) {
                        $formatted = array();
                        foreach ($current_value as $item) {
                            if (is_object($item) && method_exists($item, 'get_label')) {
                                $formatted[] = $item->get_label();
                            } else {
                                $formatted[] = $item;
                            }
                        }
                        $current_value = implode(', ', $formatted);
                    } elseif (is_object($current_value)) {
                        if (method_exists($current_value, 'get_label')) {
                            $current_value = $current_value->get_label();
                        } else {
                            $current_value = '';
                        }
                    }
                }
            }

            // Insert suggestion
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'post_id' => $post_id,
                    'field_key' => $field_key,
                    'current_value' => $current_value,
                    'suggested_value' => $suggested_value,
                    'suggester_user_id' => $user_id,
                    'suggester_email' => $suggester_email,
                    'suggester_name' => $suggester_name,
                    'proof_images' => $proof_images_json,
                    'is_incorrect' => $is_incorrect,
                    'status' => 'pending',
                ),
                array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s')
            );

            if ($result) {
                $inserted_count++;
            }
        }

        if ($inserted_count > 0) {
            // Dispatch app event
            try {
                (new Voxel_Toolkit_Suggestion_Submitted_Event())->dispatch(
                    $post_id,
                    $user_id,
                    $suggester_email,
                    $suggester_name,
                    $inserted_count
                );
            } catch (\Exception $e) {
                error_log('Voxel Toolkit: Failed to dispatch suggestion event: ' . $e->getMessage());
            }

            wp_send_json_success(array(
                'message' => __('Thank you! Your suggestions have been submitted.', 'voxel-toolkit'),
                'count' => $inserted_count,
            ));
        } else {
            wp_send_json_error(__('Failed to submit suggestions', 'voxel-toolkit'));
        }
    }

    /**
     * AJAX: Accept suggestion (apply immediately)
     */
    public function ajax_accept_suggestion() {
        check_ajax_referer('vt_suggest_edits', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied', 'voxel-toolkit'));
        }

        $suggestion_id = absint($_POST['suggestion_id']);

        global $wpdb;

        // Get the suggestion
        $suggestion = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $suggestion_id
        ));

        if (!$suggestion) {
            wp_send_json_error(__('Suggestion not found', 'voxel-toolkit'));
        }

        $post_id = $suggestion->post_id;

        // Get Voxel post object
        if (!class_exists('\Voxel\Post')) {
            wp_send_json_error(__('Voxel not available', 'voxel-toolkit'));
        }

        $voxel_post = \Voxel\Post::get($post_id);
        if (!$voxel_post) {
            wp_send_json_error(__('Post not found', 'voxel-toolkit'));
        }

        $post_type = $voxel_post->post_type;

        // Special handling for permanently closed - delete the post instead of updating a field
        if ($suggestion->field_key === '_permanently_closed') {
            // Move post to trash
            $result = wp_trash_post($post_id);

            if ($result) {
                // Mark suggestion as accepted
                $wpdb->update(
                    $this->table_name,
                    array('status' => 'accepted'),
                    array('id' => $suggestion->id),
                    array('%s'),
                    array('%d')
                );

                wp_send_json_success(__('Post moved to trash successfully', 'voxel-toolkit'));
            } else {
                wp_send_json_error(__('Failed to move post to trash', 'voxel-toolkit'));
            }
            return;
        }

        // Skip if marked as incorrect with no suggested value
        if ($suggestion->is_incorrect && empty($suggestion->suggested_value)) {
            // Just mark as accepted without updating
            $wpdb->update(
                $this->table_name,
                array('status' => 'accepted'),
                array('id' => $suggestion->id),
                array('%s'),
                array('%d')
            );
            wp_send_json_success(__('Suggestion accepted', 'voxel-toolkit'));
            return;
        }

        // Get the field object
        $field = $post_type->get_field($suggestion->field_key);
        if (!$field) {
            wp_send_json_error(__('Field not found', 'voxel-toolkit'));
        }

        // Apply the suggestion
        try {
            $field_type = $field->get_type();

            // Handle based on field type
            if ($field_type === 'title') {
                wp_update_post([
                    'ID' => $post_id,
                    'post_title' => $suggestion->suggested_value,
                ]);
            } elseif ($field_type === 'description') {
                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $suggestion->suggested_value,
                ]);
            } elseif ($suggestion->field_key === '_thumbnail_id') {
                update_post_meta($post_id, '_thumbnail_id', $suggestion->suggested_value);
            } elseif ($field_type === 'taxonomy') {
                $taxonomy = $field->get_prop('taxonomy');
                if ($taxonomy) {
                    if (strpos($suggestion->suggested_value, ',') !== false) {
                        $term_ids = array_map('intval', explode(',', $suggestion->suggested_value));
                        wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
                    } else {
                        $term_id = intval($suggestion->suggested_value);
                        wp_set_object_terms($post_id, $term_id, $taxonomy, false);
                    }
                }
            } elseif ($field_type === 'select' || $field_type === 'multiselect') {
                $meta_key = 'voxel:' . $suggestion->field_key;
                if ($field_type === 'multiselect' && strpos($suggestion->suggested_value, ',') !== false) {
                    $values = explode(',', $suggestion->suggested_value);
                    update_post_meta($post_id, $meta_key, $values);
                } else {
                    update_post_meta($post_id, $meta_key, $suggestion->suggested_value);
                }
            } elseif ($field_type === 'work-hours') {
                $field->set_post($voxel_post);
                $schedule = json_decode($suggestion->suggested_value, true);
                if (is_array($schedule)) {
                    $field->update($schedule);
                }
            } elseif ($field_type === 'location') {
                $field->set_post($voxel_post);
                $location = json_decode($suggestion->suggested_value, true);
                if (!is_array($location)) {
                    $location = array(
                        'address' => $suggestion->suggested_value,
                        'map_picker' => false,
                        'latitude' => null,
                        'longitude' => null
                    );
                }
                $field->update($location);
            } else {
                $meta_key = 'voxel:' . $suggestion->field_key;
                update_post_meta($post_id, $meta_key, $suggestion->suggested_value);
            }

            // Trigger Voxel reindexing
            $table = $post_type->get_index_table();
            if ($table && method_exists($table, 'index')) {
                $table->index([$post_id]);
            }

            // Mark suggestion as accepted
            $wpdb->update(
                $this->table_name,
                array('status' => 'accepted'),
                array('id' => $suggestion->id),
                array('%s'),
                array('%d')
            );

            wp_send_json_success(__('Suggestion accepted and applied', 'voxel-toolkit'));
        } catch (\Exception $e) {
            wp_send_json_error(__('Failed to apply suggestion: ', 'voxel-toolkit') . $e->getMessage());
        }
    }

    /**
     * AJAX: Reject suggestion
     */
    public function ajax_reject_suggestion() {
        check_ajax_referer('vt_suggest_edits', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied', 'voxel-toolkit'));
        }

        $suggestion_id = absint($_POST['suggestion_id']);

        global $wpdb;
        $result = $wpdb->update(
            $this->table_name,
            array('status' => 'rejected'),
            array('id' => $suggestion_id),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success(__('Suggestion rejected', 'voxel-toolkit'));
        } else {
            wp_send_json_error(__('Failed to reject suggestion', 'voxel-toolkit'));
        }
    }

    /**
     * AJAX: Delete post (for permanently closed suggestions)
     */
    public function ajax_delete_post_suggestion() {
        check_ajax_referer('vt_suggest_edits', 'nonce');

        if (!current_user_can('delete_posts')) {
            wp_send_json_error(__('Permission denied', 'voxel-toolkit'));
        }

        $suggestion_id = absint($_POST['suggestion_id']);
        $post_id = absint($_POST['post_id']);

        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID', 'voxel-toolkit'));
        }

        global $wpdb;

        // Verify this is a permanently closed suggestion
        $suggestion = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND field_key = '_permanently_closed'",
            $suggestion_id
        ));

        if (!$suggestion) {
            wp_send_json_error(__('Invalid permanently closed suggestion', 'voxel-toolkit'));
        }

        // Delete the post
        $deleted = wp_delete_post($post_id, false); // false = move to trash

        if ($deleted) {
            // Mark suggestion as accepted
            $wpdb->update(
                $this->table_name,
                array('status' => 'accepted'),
                array('id' => $suggestion_id),
                array('%s'),
                array('%d')
            );

            wp_send_json_success(__('Post moved to trash', 'voxel-toolkit'));
        } else {
            wp_send_json_error(__('Failed to delete post', 'voxel-toolkit'));
        }
    }

    /**
     * AJAX: Save all queued suggestions
     */
    public function ajax_save_accepted_suggestions() {
        check_ajax_referer('vt_suggest_edits', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied', 'voxel-toolkit'));
        }

        $post_id = absint($_POST['post_id']);

        global $wpdb;

        // Get all queued suggestions for this post
        $suggestions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d AND status = 'queued'",
            $post_id
        ));

        if (empty($suggestions)) {
            wp_send_json_error(__('No queued suggestions to save', 'voxel-toolkit'));
        }

        $updated_count = 0;

        // Get Voxel post object
        if (!class_exists('\Voxel\Post')) {
            wp_send_json_error(__('Voxel not available', 'voxel-toolkit'));
        }

        $voxel_post = \Voxel\Post::get($post_id);
        if (!$voxel_post) {
            wp_send_json_error(__('Post not found', 'voxel-toolkit'));
        }

        $post_type = $voxel_post->post_type;

        // Prepare field updates array
        $field_updates = [];

        foreach ($suggestions as $suggestion) {
            // Skip if marked as incorrect with no suggested value
            if ($suggestion->is_incorrect && empty($suggestion->suggested_value)) {
                // Just mark as accepted without updating
                $wpdb->update(
                    $this->table_name,
                    array('status' => 'accepted'),
                    array('id' => $suggestion->id),
                    array('%s'),
                    array('%d')
                );
                continue;
            }

            // Get the field object
            $field = $post_type->get_field($suggestion->field_key);
            if (!$field) {
                continue;
            }

            // Add to updates array
            $field_updates[$suggestion->field_key] = [
                'value' => $suggestion->suggested_value,
                'suggestion_id' => $suggestion->id,
            ];
        }

        // Update all fields
        if (!empty($field_updates)) {
            foreach ($field_updates as $field_key => $data) {
                try {
                    error_log('Voxel Toolkit: Updating field ' . $field_key . ' with value: ' . $data['value']);

                    // Get the field object to check its type
                    $field = $post_type->get_field($field_key);
                    if (!$field) {
                        error_log('Voxel Toolkit: Field not found: ' . $field_key);
                        continue;
                    }

                    $field_type = $field->get_type();
                    error_log('Voxel Toolkit: Field type for ' . $field_key . ' is: ' . $field_type);

                    // Handle based on field type
                    if ($field_type === 'title') {
                        // Update post title
                        wp_update_post([
                            'ID' => $post_id,
                            'post_title' => $data['value'],
                        ]);
                        error_log('Voxel Toolkit: Updated post_title');
                    } elseif ($field_type === 'description') {
                        // Update post content
                        wp_update_post([
                            'ID' => $post_id,
                            'post_content' => $data['value'],
                        ]);
                        error_log('Voxel Toolkit: Updated post_content');
                    } elseif ($field_key === '_thumbnail_id') {
                        // Featured image - stored directly without prefix
                        update_post_meta($post_id, '_thumbnail_id', $data['value']);
                        error_log('Voxel Toolkit: Updated _thumbnail_id');
                    } elseif ($field_type === 'taxonomy') {
                        // Taxonomy fields - set term relationship
                        $taxonomy = $field->get_prop('taxonomy');
                        if ($taxonomy) {
                            // The value can be a single term ID or comma-separated IDs for multiple
                            if (strpos($data['value'], ',') !== false) {
                                // Multiple terms
                                $term_ids = array_map('intval', explode(',', $data['value']));
                                wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
                                error_log('Voxel Toolkit: Set terms ' . implode(', ', $term_ids) . ' for taxonomy ' . $taxonomy);
                            } else {
                                // Single term
                                $term_id = intval($data['value']);
                                wp_set_object_terms($post_id, $term_id, $taxonomy, false);
                                error_log('Voxel Toolkit: Set term ' . $term_id . ' for taxonomy ' . $taxonomy);
                            }
                        }
                    } elseif ($field_type === 'select' || $field_type === 'multiselect') {
                        // Select/multiselect fields
                        $meta_key = 'voxel:' . $field_key;
                        // For multiselect, value is comma-separated, convert to array
                        if ($field_type === 'multiselect' && strpos($data['value'], ',') !== false) {
                            $values = explode(',', $data['value']);
                            update_post_meta($post_id, $meta_key, $values);
                            error_log('Voxel Toolkit: Updated ' . $meta_key . ' with multiple values');
                        } else {
                            update_post_meta($post_id, $meta_key, $data['value']);
                            error_log('Voxel Toolkit: Updated ' . $meta_key);
                        }
                    } elseif ($field_type === 'work-hours') {
                        // Work hours field - use field's native update method
                        // This ensures proper postmeta storage AND work_hours table regeneration

                        $field = $post_type->get_field($field_key);
                        if (!$field) {
                            error_log('Voxel Toolkit: Work-hours field not found: ' . $field_key);
                            continue;
                        }

                        // Set post context for the field
                        $field->set_post($voxel_post);

                        // Decode JSON string to array (field expects array)
                        $schedule = json_decode($data['value'], true);

                        if (!is_array($schedule)) {
                            error_log('Voxel Toolkit: Invalid JSON for work_hours field');
                            continue;
                        }

                        // Call field's native update method
                        // This handles: postmeta + work_hours table regeneration
                        $field->update($schedule);
                        error_log('Voxel Toolkit: Updated work hours via field->update()');
                    } elseif ($field_type === 'location') {
                        // Location field - use field's native update method
                        // This ensures proper JSON structure with address, latitude, longitude

                        $field = $post_type->get_field($field_key);
                        if (!$field) {
                            error_log('Voxel Toolkit: Location field not found: ' . $field_key);
                            continue;
                        }

                        // Set post context for the field
                        $field->set_post($voxel_post);

                        // Decode JSON string to array (field expects array)
                        $location = json_decode($data['value'], true);

                        if (!is_array($location)) {
                            // If plain text received, structure it as location object
                            $location = array(
                                'address' => $data['value'],
                                'map_picker' => false,
                                'latitude' => null,
                                'longitude' => null
                            );
                            error_log('Voxel Toolkit: Location received as plain text, using as address: ' . $data['value']);
                        }

                        // Call field's native update method
                        // This handles proper postmeta storage with correct structure
                        $field->update($location);
                        error_log('Voxel Toolkit: Updated location via field->update()');
                    } else {
                        // All other Voxel fields use the 'voxel:' prefix
                        $meta_key = 'voxel:' . $field_key;
                        update_post_meta($post_id, $meta_key, $data['value']);
                        error_log('Voxel Toolkit: Updated ' . $meta_key);
                    }

                    $updated_count++;

                    // Mark suggestion as accepted
                    $wpdb->update(
                        $this->table_name,
                        array('status' => 'accepted'),
                        array('id' => $data['suggestion_id']),
                        array('%s'),
                        array('%d')
                    );

                    error_log('Voxel Toolkit: Successfully updated field ' . $field_key);
                } catch (\Exception $e) {
                    error_log('Voxel Toolkit: Failed to update field ' . $field_key . ': ' . $e->getMessage());
                }
            }
        }

        // Trigger Voxel reindexing
        $table = $post_type->get_index_table();
        if ($table && method_exists($table, 'index')) {
            $table->index([$post_id]);
        }

        wp_send_json_success(array(
            'message' => sprintf(__('%d suggestions applied successfully', 'voxel-toolkit'), $updated_count),
            'count' => $updated_count,
        ));
    }

    /**
     * Get suggestions for a post
     */
    public function get_suggestions_by_post($post_id, $status = 'pending') {
        global $wpdb;

        if ($status === 'all') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE post_id = %d ORDER BY created_at DESC",
                $post_id
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d AND status = %s ORDER BY created_at DESC",
            $post_id,
            $status
        ));
    }

    /**
     * Get pending suggestions count for a post type
     */
    public function get_pending_count($post_type = null) {
        global $wpdb;

        if ($post_type) {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} es
                INNER JOIN {$wpdb->posts} p ON es.post_id = p.ID
                WHERE p.post_type = %s AND es.status = 'pending'",
                $post_type
            );
        } else {
            $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending'";
        }

        return $wpdb->get_var($sql);
    }

    /**
     * Render settings page
     */
    public static function render_settings() {
        $settings = Voxel_Toolkit_Settings::instance();
        $current_settings = $settings->get_function_settings('suggest_edits');

        // Get all public post types
        $post_types = get_post_types(array('public' => true), 'objects');
        $enabled_post_types = isset($current_settings['post_types']) ? $current_settings['post_types'] : array();
        ?>
        <style>
            #wpfooter { display: none !important; }
        </style>

        <tr>
            <th scope="row">
                <label><?php _e('Enabled Post Types', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <?php foreach ($post_types as $post_type): ?>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox"
                            name="voxel_toolkit_options[suggest_edits][post_types][]"
                            value="<?php echo esc_attr($post_type->name); ?>"
                            <?php checked(in_array($post_type->name, $enabled_post_types)); ?>>
                        <?php echo esc_html($post_type->label); ?>
                    </label>
                <?php endforeach; ?>
                <p class="description"><?php _e('Select which post types should have the Suggest Edits feature enabled.', 'voxel-toolkit'); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label><?php _e('Permissions', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <label style="display: block; margin-bottom: 8px;">
                    <input type="checkbox"
                        name="voxel_toolkit_options[suggest_edits][allow_logged_in]"
                        value="1"
                        <?php checked(!empty($current_settings['allow_logged_in'])); ?>>
                    <?php _e('Allow logged-in users to suggest edits', 'voxel-toolkit'); ?>
                </label>
                <label style="display: block; margin-bottom: 8px;">
                    <input type="checkbox"
                        name="voxel_toolkit_options[suggest_edits][allow_guests]"
                        value="1"
                        <?php checked(!empty($current_settings['allow_guests'])); ?>>
                    <?php _e('Allow guests to suggest edits', 'voxel-toolkit'); ?>
                </label>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label><?php _e('Proof Images', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <label style="display: block; margin-bottom: 8px;">
                    <input type="checkbox"
                        name="voxel_toolkit_options[suggest_edits][require_proof]"
                        value="1"
                        <?php checked(!empty($current_settings['require_proof'])); ?>>
                    <?php _e('Require proof images', 'voxel-toolkit'); ?>
                </label>
                <label style="display: block; margin-bottom: 8px;">
                    <?php _e('Max images allowed:', 'voxel-toolkit'); ?>
                    <input type="number"
                        name="voxel_toolkit_options[suggest_edits][max_images]"
                        value="<?php echo esc_attr($current_settings['max_images'] ?? 5); ?>"
                        min="1"
                        max="20"
                        style="width: 80px;">
                </label>
            </td>
        </tr>
        <?php
    }

    /**
     * AJAX: Handle bulk actions
     */
    public function ajax_bulk_action_suggestions() {
        check_ajax_referer('vt_suggest_edits', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied', 'voxel-toolkit'));
        }

        $action = sanitize_text_field($_POST['bulk_action']);
        $suggestion_ids = isset($_POST['suggestion_ids']) ? array_map('absint', $_POST['suggestion_ids']) : array();

        if (empty($suggestion_ids)) {
            wp_send_json_error(__('No suggestions selected', 'voxel-toolkit'));
        }

        if (empty($action)) {
            wp_send_json_error(__('No action selected', 'voxel-toolkit'));
        }

        global $wpdb;
        $success_count = 0;
        $error_count = 0;

        foreach ($suggestion_ids as $suggestion_id) {
            if ($action === 'delete') {
                // Delete suggestion
                $result = $wpdb->delete(
                    $this->table_name,
                    array('id' => $suggestion_id),
                    array('%d')
                );

                if ($result !== false) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } elseif ($action === 'accept') {
                // Accept suggestion - apply the change
                $suggestion = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE id = %d",
                    $suggestion_id
                ));

                if ($suggestion && $suggestion->status === 'pending') {
                    // Apply the suggestion (reuse logic from ajax_accept_suggestion)
                    $applied = $this->apply_suggestion($suggestion);

                    if ($applied) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                } else {
                    $error_count++;
                }
            } elseif ($action === 'reject') {
                // Reject suggestion
                $result = $wpdb->update(
                    $this->table_name,
                    array('status' => 'rejected'),
                    array('id' => $suggestion_id),
                    array('%s'),
                    array('%d')
                );

                if ($result !== false) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }

        if ($success_count > 0) {
            /* translators: %d: number of suggestions processed */
            $message = sprintf(_n('%d suggestion processed successfully', '%d suggestions processed successfully', $success_count, 'voxel-toolkit'), $success_count);
            if ($error_count > 0) {
                /* translators: %d: number of suggestions that failed */
                $message .= ' ' . sprintf(_n('%d failed', '%d failed', $error_count, 'voxel-toolkit'), $error_count);
            }
            wp_send_json_success($message);
        } else {
            wp_send_json_error(__('Failed to process suggestions', 'voxel-toolkit'));
        }
    }

    /**
     * Apply a suggestion to a post
     *
     * @param object $suggestion The suggestion object from database
     * @return bool True on success, false on failure
     */
    private function apply_suggestion($suggestion) {
        $post_id = $suggestion->post_id;
        $field_key = $suggestion->field_key;
        $new_value = $suggestion->suggested_value;

        global $wpdb;

        // Special case: permanently closed - delete the post
        if ($field_key === '_permanently_closed') {
            // Move post to trash
            $result = wp_trash_post($post_id);

            if ($result) {
                // Mark suggestion as accepted
                $wpdb->update(
                    $this->table_name,
                    array('status' => 'accepted'),
                    array('id' => $suggestion->id),
                    array('%s'),
                    array('%d')
                );
                return true;
            } else {
                return false;
            }
        }

        // Get Voxel post object
        $post = \Voxel\Post::get($post_id);
        if (!$post) {
            return false;
        }

        $post_type = $post->get_post_type();
        if (!$post_type) {
            return false;
        }

        // Get field
        $field = $post_type->get_field($field_key);
        if (!$field) {
            // Handle special cases
            if ($field_key === '_thumbnail_id') {
                // Featured image
                update_post_meta($post_id, '_thumbnail_id', $new_value);
            } else {
                return false;
            }
        } else {
            $field_type = $field->get_type();

            // Apply change based on field type
            if ($field_type === 'title') {
                wp_update_post([
                    'ID' => $post_id,
                    'post_title' => $new_value,
                ]);
            } elseif ($field_type === 'description') {
                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $new_value,
                ]);
            } elseif ($field_type === 'work-hours' || $field_type === 'location') {
                // JSON fields
                $decoded_value = json_decode($new_value, true);
                if ($decoded_value !== null) {
                    update_post_meta($post_id, 'voxel:' . $field_key, $decoded_value);
                } else {
                    return false;
                }
            } elseif ($field_type === 'taxonomy') {
                // Taxonomy fields
                $taxonomy = $field->get_prop('taxonomy');
                if ($taxonomy) {
                    if (strpos($new_value, ',') !== false) {
                        $term_ids = array_map('intval', explode(',', $new_value));
                        wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
                    } else {
                        wp_set_object_terms($post_id, intval($new_value), $taxonomy, false);
                    }
                }
            } else {
                // Standard field
                update_post_meta($post_id, 'voxel:' . $field_key, $new_value);
            }
        }

        // Trigger reindexing
        $table = $post_type->get_index_table();
        if ($table) {
            $table->index([$post_id]);
        }

        // Mark as accepted
        $wpdb->update(
            $this->table_name,
            array('status' => 'accepted'),
            array('id' => $suggestion->id),
            array('%s'),
            array('%d')
        );

        return true;
    }

    /**
     * Register custom app event category
     */
    public function register_event_category($categories) {
        $categories['voxel_toolkit'] = [
            'key' => 'voxel_toolkit',
            'label' => 'Voxel Toolkit',
        ];

        return $categories;
    }

    /**
     * Register suggestion submitted app event
     */
    public function register_app_event($events) {
        // Check if Voxel Base_Event exists
        if (!class_exists('\\Voxel\\Events\\Base_Event')) {
            return $events;
        }

        // Create the event class
        $event = new Voxel_Toolkit_Suggestion_Submitted_Event();
        $events[$event->get_key()] = $event;

        return $events;
    }
}

/**
 * Suggestion Submitted Event
 */
class Voxel_Toolkit_Suggestion_Submitted_Event extends \Voxel\Events\Base_Event {

    public $post;
    public $suggester;
    public $suggestion_count = 0;
    public $is_guest = false;

    /**
     * Prepare event data
     */
    public function prepare($post_id, $suggester_user_id = 0, $suggester_email = '', $suggester_name = '', $suggestion_count = 0) {
        // Get post using force_get (like Voxel does)
        $post = \Voxel\Post::force_get($post_id);
        if (!($post && $post->get_author())) {
            throw new \Exception('Missing information.');
        }

        $this->post = $post;
        $this->suggestion_count = $suggestion_count;

        // Get suggester (user or guest)
        if ($suggester_user_id) {
            $suggester = \Voxel\User::get($suggester_user_id);
            if (!$suggester) {
                throw new \Exception('User not found.');
            }
            $this->suggester = $suggester;
            $this->is_guest = false;
        } else {
            // For guests, use the post author as suggester placeholder
            // (Voxel doesn't support non-user entities in dynamic tags)
            $this->suggester = $this->post->get_author();
            $this->is_guest = true;
        }
    }

    public function get_key(): string {
        return 'voxel_toolkit/suggestion:submitted';
    }

    public function get_label(): string {
        return 'Voxel Toolkit: Suggestion submitted';
    }

    public function get_category() {
        return 'voxel_toolkit';
    }

    /**
     * Configure notifications
     */
    public static function notifications(): array {
        return [
            'author' => [
                'label' => 'Notify post author',
                'recipient' => function($event) {
                    return $event->post->get_author();
                },
                'inapp' => [
                    'enabled' => true,
                    'subject' => 'New edit suggestion for @post(:title)',
                    'details' => function($event) {
                        return [
                            'post_id' => $event->post->get_id(),
                            'suggester_user_id' => $event->suggester->get_id(),
                            'suggester_email' => $event->suggester->get_email(),
                            'suggester_name' => $event->suggester->get_display_name(),
                            'suggestion_count' => $event->suggestion_count,
                        ];
                    },
                    'apply_details' => function($event, $details) {
                        $event->prepare(
                            $details['post_id'] ?? null,
                            $details['suggester_user_id'] ?? 0,
                            $details['suggester_email'] ?? '',
                            $details['suggester_name'] ?? '',
                            $details['suggestion_count'] ?? 0
                        );
                    },
                    'links_to' => function($event) {
                        return $event->post->get_link();
                    },
                    'image_id' => function($event) {
                        return $event->post->get_logo_id() ?: $event->post->get_thumbnail_id();
                    },
                ],
                'email' => [
                    'enabled' => false,
                    'subject' => 'New edit suggestion for @post(:title)',
                    'message' => <<<HTML
                    Hello @author(:display_name),

                    You have received new edit suggestion(s) for your post <strong>@post(:title)</strong>.

                    <a href="@post(:url)">View Post</a>

                    Thank you!
                    HTML,
                ],
            ],
            'admin' => [
                'label' => 'Notify admin',
                'recipient' => function($event) {
                    return \Voxel\User::get(\Voxel\get('settings.notifications.admin_user'));
                },
                'inapp' => [
                    'enabled' => false,
                    'subject' => 'New edit suggestion submitted for @post(:title)',
                    'details' => function($event) {
                        return [
                            'post_id' => $event->post->get_id(),
                            'suggester_user_id' => $event->suggester->get_id(),
                            'suggester_email' => $event->suggester->get_email(),
                            'suggester_name' => $event->suggester->get_display_name(),
                            'suggestion_count' => $event->suggestion_count,
                        ];
                    },
                    'apply_details' => function($event, $details) {
                        $event->prepare(
                            $details['post_id'] ?? null,
                            $details['suggester_user_id'] ?? 0,
                            $details['suggester_email'] ?? '',
                            $details['suggester_name'] ?? '',
                            $details['suggestion_count'] ?? 0
                        );
                    },
                    'links_to' => function($event) {
                        return $event->post->get_link();
                    },
                    'image_id' => function($event) {
                        return $event->post->get_logo_id() ?: $event->post->get_thumbnail_id();
                    },
                ],
                'email' => [
                    'enabled' => false,
                    'subject' => 'New edit suggestion submitted for @post(:title)',
                    'message' => <<<HTML
                    A new edit suggestion has been submitted for the post <strong>@post(:title)</strong>.

                    <a href="@post(:url)">View Post</a>
                    HTML,
                ],
            ],
        ];
    }

    public function set_mock_props() {
        $this->post = \Voxel\Post::mock();
        $this->suggester = \Voxel\User::mock();
        $this->suggestion_count = 3;
        $this->is_guest = false;
    }

    public function dynamic_tags(): array {
        $tags = [
            'post' => \Voxel\Dynamic_Data\Group::Post($this->post ?: \Voxel\Post::mock()),
        ];

        // Add author tag
        if ($this->post && $this->post->get_author()) {
            $tags['author'] = \Voxel\Dynamic_Data\Group::User($this->post->get_author());
        } else {
            $tags['author'] = \Voxel\Dynamic_Data\Group::User(\Voxel\User::mock());
        }

        // Only add suggester tag if we have a valid suggester
        if ($this->suggester && !$this->is_guest) {
            $tags['suggester'] = \Voxel\Dynamic_Data\Group::User($this->suggester);
        }

        return $tags;
    }
}
