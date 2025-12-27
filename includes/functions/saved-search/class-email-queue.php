<?php
/**
 * Email Queue Class
 *
 * Handles queuing and batch processing of saved search email notifications.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Email_Queue {

    public static $table_version = '1.0';

    /**
     * Get table name
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'vt_email_queue';
    }

    /**
     * Setup database table
     */
    public static function setup_table() {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            recipient_email varchar(255) NOT NULL,
            recipient_id bigint(20) unsigned NOT NULL,
            post_id bigint(20) unsigned NOT NULL,
            saved_search_id bigint(20) unsigned NOT NULL,
            post_type varchar(100) NOT NULL,
            subject text NOT NULL,
            message longtext NOT NULL,
            status varchar(20) DEFAULT 'pending' NOT NULL,
            attempts tinyint(3) unsigned DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            processed_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at),
            KEY recipient_id (recipient_id),
            KEY post_search (post_id, saved_search_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('vt_email_queue_table_version', self::$table_version);
    }

    /**
     * Maybe setup table (version check)
     */
    public static function maybe_setup_table() {
        $current_version = get_option('vt_email_queue_table_version', '');
        if ($current_version !== self::$table_version) {
            self::setup_table();
        }
    }

    /**
     * Queue an email for batch processing
     *
     * @param array $data Email data
     * @return int|false Inserted ID or false on failure
     */
    public static function queue($data) {
        global $wpdb;

        // Check for duplicates first
        if (self::is_duplicate($data['recipient_id'], $data['post_id'], $data['saved_search_id'])) {
            return false;
        }

        $result = $wpdb->insert(
            self::get_table_name(),
            array(
                'recipient_email' => sanitize_email($data['recipient_email']),
                'recipient_id' => absint($data['recipient_id']),
                'post_id' => absint($data['post_id']),
                'saved_search_id' => absint($data['saved_search_id']),
                'post_type' => sanitize_key($data['post_type']),
                'subject' => sanitize_text_field($data['subject']),
                'message' => wp_kses_post($data['message']),
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Check if email is already queued (prevent duplicates)
     *
     * @param int $recipient_id User ID
     * @param int $post_id Post ID
     * @param int $saved_search_id Saved search ID
     * @return bool True if duplicate exists
     */
    public static function is_duplicate($recipient_id, $post_id, $saved_search_id) {
        global $wpdb;

        $table = self::get_table_name();

        // Check for pending/processing emails with same combination
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE recipient_id = %d
             AND post_id = %d
             AND saved_search_id = %d
             AND status IN ('pending', 'processing')",
            $recipient_id,
            $post_id,
            $saved_search_id
        ));

        return $count > 0;
    }

    /**
     * Get pending emails for batch processing
     *
     * @param int $limit Number of emails to retrieve
     * @return array Array of email records
     */
    public static function get_pending($limit = 25) {
        global $wpdb;

        $table = self::get_table_name();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table
             WHERE status = 'pending'
             ORDER BY created_at ASC
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Mark emails as processing (atomic lock)
     *
     * @param array $ids Array of email IDs
     * @return int Number of rows updated
     */
    public static function mark_processing($ids) {
        global $wpdb;

        if (empty($ids)) {
            return 0;
        }

        $table = self::get_table_name();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        return $wpdb->query($wpdb->prepare(
            "UPDATE $table
             SET status = 'processing'
             WHERE id IN ($placeholders)
             AND status = 'pending'",
            $ids
        ));
    }

    /**
     * Mark email as sent
     *
     * @param int $id Email ID
     * @return bool Success
     */
    public static function mark_sent($id) {
        global $wpdb;

        return $wpdb->update(
            self::get_table_name(),
            array(
                'status' => 'sent',
                'processed_at' => current_time('mysql'),
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        ) !== false;
    }

    /**
     * Mark email as failed with retry logic
     *
     * @param int $id Email ID
     * @param string $error Error message
     * @return bool Success
     */
    public static function mark_failed($id, $error = '') {
        global $wpdb;

        $table = self::get_table_name();

        // Get current attempts
        $attempts = $wpdb->get_var($wpdb->prepare(
            "SELECT attempts FROM $table WHERE id = %d",
            $id
        ));

        $new_attempts = intval($attempts) + 1;

        // If max attempts reached (3), mark as permanently failed
        $new_status = $new_attempts >= 3 ? 'failed' : 'pending';

        return $wpdb->update(
            $table,
            array(
                'status' => $new_status,
                'attempts' => $new_attempts,
                'error_message' => sanitize_text_field($error),
                'processed_at' => $new_status === 'failed' ? current_time('mysql') : null,
            ),
            array('id' => $id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        ) !== false;
    }

    /**
     * Cleanup old records (sent/failed older than 7 days)
     *
     * @return int Number of rows deleted
     */
    public static function cleanup_old_records() {
        global $wpdb;

        $table = self::get_table_name();

        return $wpdb->query(
            "DELETE FROM $table
             WHERE status IN ('sent', 'failed')
             AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
    }

    /**
     * Get queue statistics
     *
     * @return array Stats array
     */
    public static function get_stats() {
        global $wpdb;

        $table = self::get_table_name();

        $stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $table GROUP BY status",
            OBJECT_K
        );

        return array(
            'pending' => isset($stats['pending']) ? intval($stats['pending']->count) : 0,
            'processing' => isset($stats['processing']) ? intval($stats['processing']->count) : 0,
            'sent' => isset($stats['sent']) ? intval($stats['sent']->count) : 0,
            'failed' => isset($stats['failed']) ? intval($stats['failed']->count) : 0,
        );
    }

    /**
     * Reset stuck processing records (older than 10 minutes)
     * This handles cases where processing was interrupted
     */
    public static function reset_stuck_records() {
        global $wpdb;

        $table = self::get_table_name();

        return $wpdb->query(
            "UPDATE $table
             SET status = 'pending'
             WHERE status = 'processing'
             AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
        );
    }
}
