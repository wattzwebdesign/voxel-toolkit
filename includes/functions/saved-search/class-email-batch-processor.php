<?php
/**
 * Email Batch Processor Class
 *
 * Handles cron-based batch processing of queued email notifications.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Email_Batch_Processor {

    private static $instance = null;

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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add custom cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));

        // Schedule cron events
        add_action('admin_init', array($this, 'schedule_cron_events'));

        // Process batch hook
        add_action('vt_email_batch_process', array($this, 'process_batch'));

        // Cleanup hook
        add_action('vt_email_queue_cleanup', array($this, 'cleanup'));
    }

    /**
     * Add custom cron schedules based on settings
     */
    public function add_cron_schedules($schedules) {
        $settings = $this->get_settings();
        $interval = intval($settings['batch_interval']) * 60; // Convert minutes to seconds

        // Only add if interval is valid
        if ($interval > 0) {
            $schedules['vt_email_batch_interval'] = array(
                'interval' => $interval,
                'display' => sprintf(__('Every %d minutes', 'voxel-toolkit'), $settings['batch_interval']),
            );
        }

        return $schedules;
    }

    /**
     * Schedule cron events
     */
    public function schedule_cron_events() {
        $settings = $this->get_settings();

        // Only schedule if batching is enabled
        if (!$settings['enabled']) {
            // Unschedule if disabled
            $this->unschedule_cron_events();
            return;
        }

        // Schedule batch processing
        if (!wp_next_scheduled('vt_email_batch_process')) {
            wp_schedule_event(time(), 'vt_email_batch_interval', 'vt_email_batch_process');
        }

        // Schedule daily cleanup
        if (!wp_next_scheduled('vt_email_queue_cleanup')) {
            wp_schedule_event(time(), 'daily', 'vt_email_queue_cleanup');
        }
    }

    /**
     * Unschedule cron events
     */
    public function unschedule_cron_events() {
        $batch_timestamp = wp_next_scheduled('vt_email_batch_process');
        if ($batch_timestamp) {
            wp_unschedule_event($batch_timestamp, 'vt_email_batch_process');
        }

        $cleanup_timestamp = wp_next_scheduled('vt_email_queue_cleanup');
        if ($cleanup_timestamp) {
            wp_unschedule_event($cleanup_timestamp, 'vt_email_queue_cleanup');
        }
    }

    /**
     * Process a batch of queued emails
     */
    public function process_batch() {
        $settings = $this->get_settings();

        // Ensure batching is enabled
        if (!$settings['enabled']) {
            return;
        }

        $batch_size = intval($settings['batch_size']);

        // Reset any stuck processing records first
        Voxel_Toolkit_Email_Queue::reset_stuck_records();

        // Get pending emails
        $emails = Voxel_Toolkit_Email_Queue::get_pending($batch_size);

        if (empty($emails)) {
            return;
        }

        // Mark as processing (atomic lock to prevent race conditions)
        $ids = wp_list_pluck($emails, 'id');
        $locked = Voxel_Toolkit_Email_Queue::mark_processing($ids);

        if ($locked === 0) {
            // Another process already locked these, skip
            return;
        }

        // Process each email
        foreach ($emails as $email) {
            $this->send_email($email);
        }
    }

    /**
     * Send a single email
     *
     * @param object $email Email record from queue
     */
    private function send_email($email) {
        try {
            // Set up email headers
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
            );

            // Apply Voxel's styled email template if available, otherwise basic wrapper
            if (function_exists('\Voxel\email_template')) {
                $message = \Voxel\email_template($email->message);
            } else {
                $message = $this->wrap_email_content($email->message);
            }

            // Send the email
            $sent = wp_mail(
                $email->recipient_email,
                $email->subject,
                $message,
                $headers
            );

            if ($sent) {
                Voxel_Toolkit_Email_Queue::mark_sent($email->id);
            } else {
                // Get last PHP mail error if available
                $error = 'wp_mail returned false';
                if (isset($GLOBALS['phpmailer']) && is_object($GLOBALS['phpmailer'])) {
                    $error = $GLOBALS['phpmailer']->ErrorInfo ?: $error;
                }
                Voxel_Toolkit_Email_Queue::mark_failed($email->id, $error);
            }
        } catch (Exception $e) {
            Voxel_Toolkit_Email_Queue::mark_failed($email->id, $e->getMessage());
        }
    }

    /**
     * Wrap email content with basic HTML structure
     *
     * @param string $content Email body content
     * @return string Wrapped HTML
     */
    private function wrap_email_content($content) {
        // Check if content is already a complete HTML document
        if (stripos($content, '<html') !== false || stripos($content, '<!DOCTYPE') !== false) {
            return $content;
        }

        // Wrap in basic HTML structure
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    ' . $content . '
</body>
</html>';
    }

    /**
     * Cleanup old records
     */
    public function cleanup() {
        Voxel_Toolkit_Email_Queue::cleanup_old_records();
    }

    /**
     * Get batch processing settings
     *
     * @return array Settings
     */
    private function get_settings() {
        $options = get_option('voxel_toolkit_options', array());
        $saved_search = isset($options['saved_search']) ? $options['saved_search'] : array();

        return array(
            'enabled' => !empty($saved_search['email_batching_enabled']),
            'batch_size' => isset($saved_search['email_batch_size']) ? intval($saved_search['email_batch_size']) : 25,
            'batch_interval' => isset($saved_search['email_batch_interval']) ? intval($saved_search['email_batch_interval']) : 5,
        );
    }

    /**
     * Static method to deactivate cron (for plugin deactivation)
     */
    public static function deactivate() {
        $batch_timestamp = wp_next_scheduled('vt_email_batch_process');
        if ($batch_timestamp) {
            wp_unschedule_event($batch_timestamp, 'vt_email_batch_process');
        }

        $cleanup_timestamp = wp_next_scheduled('vt_email_queue_cleanup');
        if ($cleanup_timestamp) {
            wp_unschedule_event($cleanup_timestamp, 'vt_email_queue_cleanup');
        }
    }
}
