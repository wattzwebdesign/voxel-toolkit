<?php
/**
 * Timeline Reply Summary Function
 *
 * Generates AI summaries of timeline post replies
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Timeline_Reply_Summary {

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
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'voxel_toolkit_reply_summaries';
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Create database table on plugin activation
        add_action('admin_init', array($this, 'maybe_create_table'));

        // Hook into reply stats update to regenerate summary
        add_action('voxel/post/timeline-reply-stats-updated', array($this, 'on_reply_stats_updated'), 10, 2);

        // Register AJAX endpoints
        add_action('voxel_ajax_vt_reply_summary.get', array($this, 'ajax_get_summary'));
        add_action('voxel_ajax_nopriv_vt_reply_summary.get', array($this, 'ajax_get_summary'));

        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Output config in footer
        add_action('wp_footer', array($this, 'output_config'), 5);
    }

    /**
     * Get settings
     */
    private function get_settings() {
        return Voxel_Toolkit_Settings::instance()->get_function_settings('timeline_reply_summary', array());
    }

    /**
     * Maybe create database table
     */
    public function maybe_create_table() {
        $table_version = '1.0';
        $current_version = get_option('vt_reply_summaries_table_version', '');

        if ($table_version === $current_version) {
            return;
        }

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `status_id` BIGINT(20) UNSIGNED NOT NULL,
            `summary` TEXT NOT NULL,
            `reply_count` INT NOT NULL,
            `generated_at` DATETIME NOT NULL,
            `ai_provider` VARCHAR(50) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `status_id` (`status_id`)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('vt_reply_summaries_table_version', $table_version);
    }

    /**
     * Handle reply stats update - regenerate summary if needed
     */
    public function on_reply_stats_updated($post_id, $stats) {
        $settings = $this->get_settings();

        // Check if feature is properly configured
        if (empty($settings['api_key'])) {
            return;
        }

        $threshold = isset($settings['reply_threshold']) ? absint($settings['reply_threshold']) : 3;
        $enabled_feeds = isset($settings['feeds']) ? $settings['feeds'] : array('post_reviews', 'post_wall', 'post_timeline');

        // Get timeline statuses for this post that have enough replies
        global $wpdb;
        $timeline_table = $wpdb->prefix . 'voxel_timeline';

        $feed_placeholders = implode(',', array_fill(0, count($enabled_feeds), '%s'));
        $params = array_merge(array($post_id, $threshold), $enabled_feeds);

        $statuses = $wpdb->get_results($wpdb->prepare(
            "SELECT id, reply_count, feed FROM {$timeline_table}
             WHERE post_id = %d AND reply_count >= %d AND feed IN ({$feed_placeholders}) AND moderation = 1",
            $params
        ), ARRAY_A);

        foreach ($statuses as $status) {
            $this->maybe_regenerate_summary($status['id'], $status['reply_count']);
        }
    }

    /**
     * Check if summary needs regeneration and regenerate if needed
     */
    private function maybe_regenerate_summary($status_id, $current_reply_count) {
        global $wpdb;

        // Check existing summary
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT reply_count FROM {$this->table_name} WHERE status_id = %d",
            $status_id
        ), ARRAY_A);

        // Only regenerate if reply count changed
        if ($existing && (int) $existing['reply_count'] === (int) $current_reply_count) {
            return;
        }

        // Generate new summary
        $this->generate_and_store_summary($status_id);
    }

    /**
     * Generate and store summary for a status
     */
    private function generate_and_store_summary($status_id) {
        $settings = $this->get_settings();

        if (empty($settings['api_key'])) {
            return false;
        }

        // Get replies for this status
        $replies = $this->get_replies_for_status($status_id);

        if (empty($replies)) {
            return false;
        }

        // Format replies for prompt
        $formatted_replies = $this->format_replies_for_prompt($replies);

        // Get prompt template
        $prompt_template = !empty($settings['prompt_template'])
            ? $settings['prompt_template']
            : Voxel_Toolkit_Functions::instance()->get_default_summary_prompt();

        $prompt = str_replace('{{replies}}', $formatted_replies, $prompt_template);

        // Generate summary with AI
        $provider = isset($settings['ai_provider']) ? $settings['ai_provider'] : 'openai';
        $max_tokens = isset($settings['max_summary_length']) ? absint($settings['max_summary_length']) : 300;

        $summary = $this->call_ai_api($prompt, $provider, $settings['api_key'], $max_tokens);

        if (!$summary) {
            return false;
        }

        // Store summary
        return $this->store_summary($status_id, $summary, count($replies), $provider);
    }

    /**
     * Get replies for a status
     */
    private function get_replies_for_status($status_id) {
        global $wpdb;
        $replies_table = $wpdb->prefix . 'voxel_timeline_replies';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT content FROM {$replies_table}
             WHERE status_id = %d AND moderation = 1
             ORDER BY created_at ASC",
            $status_id
        ), ARRAY_A);
    }

    /**
     * Format replies for AI prompt
     */
    private function format_replies_for_prompt($replies) {
        $formatted = array();
        foreach ($replies as $reply) {
            if (!empty($reply['content'])) {
                // Truncate very long replies
                $content = wp_trim_words($reply['content'], 100, '...');
                $formatted[] = '- "' . $content . '"';
            }
        }
        return implode("\n", $formatted);
    }

    /**
     * Call AI API to generate summary
     */
    private function call_ai_api($prompt, $provider, $api_key, $max_tokens) {
        if ($provider === 'anthropic') {
            return $this->call_anthropic_api($prompt, $api_key, $max_tokens);
        }
        return $this->call_openai_api($prompt, $api_key, $max_tokens);
    }

    /**
     * Call OpenAI API
     */
    private function call_openai_api($prompt, $api_key, $max_tokens) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model' => 'gpt-4o-mini',
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt),
                ),
                'max_tokens' => $max_tokens,
                'temperature' => 0.7,
            )),
        ));

        if (is_wp_error($response)) {
            error_log('VT Reply Summary - OpenAI Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['choices'][0]['message']['content'])) {
            return trim($body['choices'][0]['message']['content']);
        }

        if (isset($body['error']['message'])) {
            error_log('VT Reply Summary - OpenAI API Error: ' . $body['error']['message']);
        }

        return false;
    }

    /**
     * Call Anthropic API
     */
    private function call_anthropic_api($prompt, $api_key, $max_tokens) {
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'timeout' => 30,
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => $max_tokens,
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt),
                ),
            )),
        ));

        if (is_wp_error($response)) {
            error_log('VT Reply Summary - Anthropic Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['content'][0]['text'])) {
            return trim($body['content'][0]['text']);
        }

        if (isset($body['error']['message'])) {
            error_log('VT Reply Summary - Anthropic API Error: ' . $body['error']['message']);
        }

        return false;
    }

    /**
     * Store summary in database
     */
    private function store_summary($status_id, $summary, $reply_count, $provider) {
        global $wpdb;

        $data = array(
            'status_id' => $status_id,
            'summary' => $summary,
            'reply_count' => $reply_count,
            'generated_at' => current_time('mysql', true),
            'ai_provider' => $provider,
        );

        // Use REPLACE to handle both insert and update
        $result = $wpdb->replace($this->table_name, $data, array('%d', '%s', '%d', '%s', '%s'));

        return $result !== false;
    }

    /**
     * Get stored summary for a status
     */
    public function get_summary($status_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT summary, reply_count, generated_at, ai_provider FROM {$this->table_name} WHERE status_id = %d",
            $status_id
        ), ARRAY_A);
    }

    /**
     * AJAX handler to get summary
     */
    public function ajax_get_summary() {
        try {
            $status_id = isset($_REQUEST['status_id']) ? absint($_REQUEST['status_id']) : 0;
            $check_only = isset($_REQUEST['check_only']) && $_REQUEST['check_only'] == '1';

            if (!$status_id) {
                throw new \Exception(__('Invalid status ID.', 'voxel-toolkit'));
            }

            $settings = $this->get_settings();
            $threshold = isset($settings['reply_threshold']) ? absint($settings['reply_threshold']) : 3;

            // Check if status has enough replies
            global $wpdb;
            $timeline_table = $wpdb->prefix . 'voxel_timeline';
            $status = $wpdb->get_row($wpdb->prepare(
                "SELECT reply_count, feed FROM {$timeline_table} WHERE id = %d AND moderation = 1",
                $status_id
            ), ARRAY_A);

            if (!$status || (int) $status['reply_count'] < $threshold) {
                return wp_send_json(array(
                    'success' => true,
                    'eligible' => false,
                    'has_summary' => false,
                    'reason' => 'below_threshold',
                    'reply_count' => $status ? (int) $status['reply_count'] : 0,
                    'threshold' => $threshold,
                ));
            }

            // Check if feed is enabled (include user_timeline by default for newsfeed)
            $enabled_feeds = isset($settings['feeds']) ? $settings['feeds'] : array('post_reviews', 'post_wall', 'post_timeline', 'user_timeline');
            // Always allow user_timeline for newsfeed compatibility
            if (!in_array('user_timeline', $enabled_feeds, true)) {
                $enabled_feeds[] = 'user_timeline';
            }
            if (!in_array($status['feed'], $enabled_feeds, true)) {
                return wp_send_json(array(
                    'success' => true,
                    'eligible' => false,
                    'has_summary' => false,
                    'reason' => 'feed_not_enabled',
                    'feed' => $status['feed'],
                ));
            }

            // If just checking eligibility, return early with existing summary if available
            if ($check_only) {
                $summary = $this->get_summary($status_id);
                return wp_send_json(array(
                    'success' => true,
                    'eligible' => true,
                    'has_summary' => !empty($summary),
                    'summary' => $summary ? $summary['summary'] : null,
                    'reply_count' => (int) $status['reply_count'],
                ));
            }

            // Get existing summary
            $summary = $this->get_summary($status_id);

            // Generate if doesn't exist or reply count changed
            if (!$summary || (int) $summary['reply_count'] !== (int) $status['reply_count']) {
                $this->generate_and_store_summary($status_id);
                $summary = $this->get_summary($status_id);
            }

            if (!$summary) {
                return wp_send_json(array(
                    'success' => true,
                    'eligible' => true,
                    'has_summary' => false,
                ));
            }

            return wp_send_json(array(
                'success' => true,
                'eligible' => true,
                'has_summary' => true,
                'summary' => $summary['summary'],
                'generated_at' => $summary['generated_at'],
                'provider' => $summary['ai_provider'],
            ));

        } catch (\Exception $e) {
            return wp_send_json(array(
                'success' => false,
                'eligible' => false,
                'message' => $e->getMessage(),
            ));
        }
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        $settings = $this->get_settings();

        // Only enqueue if API key is configured
        if (empty($settings['api_key'])) {
            return;
        }

        $js_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/js/timeline-reply-summary.js';
        $css_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/css/timeline-reply-summary.css';

        wp_enqueue_script(
            'vt-timeline-reply-summary',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/timeline-reply-summary.js',
            array('jquery'),
            file_exists($js_file) ? filemtime($js_file) : VOXEL_TOOLKIT_VERSION,
            true
        );

        wp_enqueue_style(
            'vt-timeline-reply-summary',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/timeline-reply-summary.css',
            array(),
            file_exists($css_file) ? filemtime($css_file) : VOXEL_TOOLKIT_VERSION
        );

        // Localize script
        wp_localize_script('vt-timeline-reply-summary', 'vtReplySummary', array(
            'ajaxUrl' => home_url('/?vx=1&action=vt_reply_summary.get'),
            'threshold' => isset($settings['reply_threshold']) ? absint($settings['reply_threshold']) : 3,
            'label' => isset($settings['label_text']) && !empty($settings['label_text'])
                ? $settings['label_text']
                : __('TL;DR', 'voxel-toolkit'),
            'loadingText' => __('Generating summary...', 'voxel-toolkit'),
            'errorText' => __('Summary unavailable', 'voxel-toolkit'),
        ));
    }

    /**
     * Output config in footer
     */
    public function output_config() {
        $settings = $this->get_settings();

        if (empty($settings['api_key'])) {
            return;
        }

        $enabled_feeds = isset($settings['feeds']) ? $settings['feeds'] : array('post_reviews', 'post_wall', 'post_timeline');
        ?>
        <script type="text/javascript">
        window.vtReplySummaryConfig = {
            feeds: <?php echo wp_json_encode($enabled_feeds); ?>
        };
        </script>
        <?php
    }

    /**
     * Delete summary when status is deleted
     */
    public function delete_summary($status_id) {
        global $wpdb;
        return $wpdb->delete($this->table_name, array('status_id' => $status_id), array('%d'));
    }
}
