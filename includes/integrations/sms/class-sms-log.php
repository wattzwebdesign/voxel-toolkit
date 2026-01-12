<?php
/**
 * SMS Log Class
 *
 * Handles logging SMS send attempts for debugging and monitoring.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_SMS_Log {

    public static $table_version = '1.0';

    /**
     * Get table name
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'vt_sms_log';
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
            phone varchar(50) NOT NULL,
            message text NOT NULL,
            provider varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            message_id varchar(100) DEFAULT NULL,
            error_message text DEFAULT NULL,
            event_key varchar(255) DEFAULT NULL,
            recipient_user_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at),
            KEY provider (provider)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('vt_sms_log_table_version', self::$table_version);
    }

    /**
     * Maybe setup table (version check)
     */
    public static function maybe_setup_table() {
        $current_version = get_option('vt_sms_log_table_version', '');
        if ($current_version !== self::$table_version) {
            self::setup_table();
        }
    }

    /**
     * Log an SMS send attempt
     *
     * @param array $data Log data
     * @return int|false Inserted ID or false on failure
     */
    public static function log($data) {
        global $wpdb;

        // Ensure table exists
        self::maybe_setup_table();

        $result = $wpdb->insert(
            self::get_table_name(),
            array(
                'phone' => self::mask_phone($data['phone'] ?? ''),
                'message' => sanitize_textarea_field(self::truncate_message($data['message'] ?? '')),
                'provider' => sanitize_key($data['provider'] ?? ''),
                'status' => sanitize_key($data['status'] ?? 'unknown'),
                'message_id' => sanitize_text_field($data['message_id'] ?? ''),
                'error_message' => sanitize_text_field($data['error'] ?? ''),
                'event_key' => sanitize_text_field($data['event_key'] ?? ''),
                'recipient_user_id' => absint($data['user_id'] ?? 0) ?: null,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );

        // Cleanup old records periodically (1% chance per log)
        if (wp_rand(1, 100) === 1) {
            self::cleanup_old_records();
        }

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get recent log entries
     *
     * @param int $limit Number of entries to retrieve
     * @param int $offset Offset for pagination
     * @param string $status Filter by status (optional)
     * @return array Array of log records
     */
    public static function get_logs($limit = 50, $offset = 0, $status = '') {
        global $wpdb;

        // Ensure table exists
        self::maybe_setup_table();

        $table = self::get_table_name();

        $where = '';
        $prepare_args = array();

        if (!empty($status)) {
            $where = 'WHERE status = %s';
            $prepare_args[] = $status;
        }

        $prepare_args[] = $limit;
        $prepare_args[] = $offset;

        $query = "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT %d OFFSET %d";

        return $wpdb->get_results($wpdb->prepare($query, $prepare_args));
    }

    /**
     * Get total log count
     *
     * @param string $status Filter by status (optional)
     * @return int Total count
     */
    public static function get_count($status = '') {
        global $wpdb;

        $table = self::get_table_name();

        if (!empty($status)) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE status = %s",
                $status
            ));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    /**
     * Get log statistics
     *
     * @return array Stats array
     */
    public static function get_stats() {
        global $wpdb;

        // Ensure table exists
        self::maybe_setup_table();

        $table = self::get_table_name();

        $stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $table GROUP BY status",
            OBJECT_K
        );

        return array(
            'success' => isset($stats['success']) ? intval($stats['success']->count) : 0,
            'failed' => isset($stats['failed']) ? intval($stats['failed']->count) : 0,
            'total' => self::get_count(),
        );
    }

    /**
     * Clear all logs
     *
     * @return int Number of rows deleted
     */
    public static function clear_logs() {
        global $wpdb;

        $table = self::get_table_name();

        return $wpdb->query("TRUNCATE TABLE $table");
    }

    /**
     * Cleanup old records (older than 30 days)
     *
     * @return int Number of rows deleted
     */
    public static function cleanup_old_records() {
        global $wpdb;

        $table = self::get_table_name();

        return $wpdb->query(
            "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }

    /**
     * Mask phone number for privacy
     *
     * @param string $phone Phone number
     * @return string Masked phone number
     */
    private static function mask_phone($phone) {
        $length = strlen($phone);
        if ($length <= 7) {
            return $phone; // Don't mask short strings (like "DEBUG")
        }

        $mask_length = $length - 7;
        return substr($phone, 0, 4) . str_repeat('*', $mask_length) . substr($phone, -3);
    }

    /**
     * Truncate message for storage (first 200 chars)
     *
     * @param string $message Message content
     * @return string Truncated message
     */
    private static function truncate_message($message) {
        if (strlen($message) <= 200) {
            return $message;
        }
        return substr($message, 0, 197) . '...';
    }
}
