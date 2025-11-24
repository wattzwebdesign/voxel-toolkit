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

        // Register widgets
        add_action('elementor/widgets/register', array($this, 'register_widgets'));

        // Register app event
        add_filter('voxel/app-events/register', array($this, 'register_app_event'));
        add_filter('voxel/app-events/categories', array($this, 'register_event_category'));
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
        if (!empty($_FILES['proof_images'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $files = $_FILES['proof_images'];
            $file_count = count($files['name']);

            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = array(
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i]
                    );

                    $upload = wp_handle_upload($file, array('test_form' => false));

                    if (!isset($upload['error'])) {
                        $attachment_id = wp_insert_attachment(array(
                            'post_mime_type' => $upload['type'],
                            'post_title'     => sanitize_file_name($file['name']),
                            'post_content'   => '',
                            'post_status'    => 'inherit'
                        ), $upload['file']);

                        if (!is_wp_error($attachment_id)) {
                            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
                            $uploaded_image_ids[] = $attachment_id;
                        }
                    }
                }
            }
        }

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
                    'proof_images' => '',
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
                $this->send_notification_email($post_id, $inserted_count);

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

        // Encode uploaded images for storage
        $proof_images_json = !empty($uploaded_image_ids) ? json_encode($uploaded_image_ids) : '';

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
            // Send notification email to post author
            $this->send_notification_email($post_id, $inserted_count);

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
     * AJAX: Accept suggestion (queue for save)
     */
    public function ajax_accept_suggestion() {
        check_ajax_referer('vt_suggest_edits', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied', 'voxel-toolkit'));
        }

        $suggestion_id = absint($_POST['suggestion_id']);

        global $wpdb;
        $result = $wpdb->update(
            $this->table_name,
            array('status' => 'queued'),
            array('id' => $suggestion_id),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success(__('Suggestion queued for save', 'voxel-toolkit'));
        } else {
            wp_send_json_error(__('Failed to accept suggestion', 'voxel-toolkit'));
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
     * Send notification email to post author
     */
    private function send_notification_email($post_id, $suggestion_count) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        // Get post author email
        $author_id = $post->post_author;
        $author = get_userdata($author_id);
        if (!$author) {
            return false;
        }

        // Get email template from settings
        $settings = Voxel_Toolkit_Settings::instance();
        $config = $settings->get_function_settings('suggest_edits');

        $subject = isset($config['email_subject']) && !empty($config['email_subject'])
            ? $config['email_subject']
            : __('New edit suggestions for your post', 'voxel-toolkit');

        $message = isset($config['email_message']) && !empty($config['email_message'])
            ? $config['email_message']
            : __('You have {suggestion_count} new edit suggestion(s) for your post "{post_title}". Please review them in your admin panel.', 'voxel-toolkit');

        // Replace dynamic tags
        $replacements = array(
            '{post_title}' => $post->post_title,
            '{post_url}' => get_permalink($post_id),
            '{suggestion_count}' => $suggestion_count,
            '{author_name}' => $author->display_name,
            '{edit_link}' => admin_url('edit.php?post_type=' . $post->post_type . '&page=vt-suggested-edits'),
        );

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
        $message = str_replace(array_keys($replacements), array_values($replacements), $message);
        $message = wpautop($message);

        // Send email
        $headers = array('Content-Type: text/html; charset=UTF-8');
        return wp_mail($author->user_email, $subject, $message, $headers);
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
                <label for="email_subject"><?php _e('Email Subject', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <input type="text"
                    id="email_subject"
                    name="voxel_toolkit_options[suggest_edits][email_subject]"
                    value="<?php echo esc_attr($current_settings['email_subject'] ?? ''); ?>"
                    class="regular-text">
                <p class="description">
                    <?php _e('Available tags: {post_title}, {suggestion_count}, {author_name}', 'voxel-toolkit'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="email_message"><?php _e('Email Message', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <textarea id="email_message"
                    name="voxel_toolkit_options[suggest_edits][email_message]"
                    rows="10"
                    class="large-text"><?php echo esc_textarea($current_settings['email_message'] ?? ''); ?></textarea>
                <p class="description">
                    <?php _e('Available tags: {post_title}, {post_url}, {suggestion_count}, {author_name}, {edit_link}', 'voxel-toolkit'); ?>
                </p>
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
    public $suggestion_count;
    public $is_guest;

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
                    'subject' => 'New edit suggestion for @post(title)',
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
                    'subject' => 'New edit suggestion for @post(title)',
                    'message' => <<<HTML
                    Hello @author(:display_name),

                    You have received new edit suggestion(s) for your post <strong>@post(title)</strong>.

                    <a href="@post(url)">View Post</a>

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
                    'subject' => 'New edit suggestion submitted for @post(title)',
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
                    'subject' => 'New edit suggestion submitted for @post(title)',
                    'message' => <<<HTML
                    A new edit suggestion has been submitted for the post <strong>@post(title)</strong>.

                    <a href="@post(url)">View Post</a>
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
            'author' => \Voxel\Dynamic_Data\Group::User(
                $this->post ? $this->post->get_author() : \Voxel\User::mock()
            ),
        ];

        // Only add suggester tag if it's not a guest (since guests use author as placeholder)
        if (!$this->is_guest && $this->suggester) {
            $tags['suggester'] = \Voxel\Dynamic_Data\Group::User($this->suggester);
        }

        return $tags;
    }
}
