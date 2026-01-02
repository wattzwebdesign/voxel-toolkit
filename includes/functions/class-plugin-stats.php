<?php
/**
 * Plugin Stats - Anonymous Usage Telemetry (Client Only)
 *
 * Sends anonymous feature usage data by default to help improve development.
 * Only tracks which functions/widgets/post fields are enabled.
 * No personal, site, domain, IP, or identifying information is collected.
 * Users can opt-out via the settings page.
 *
 * Note: The receiving/dashboard functionality is handled by a separate plugin
 * (VT Stats Receiver) installed on codewattz.com only.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Plugin_Stats {

    /**
     * Hardcoded endpoint - only codewattz.com receives stats
     */
    const STATS_ENDPOINT = 'https://codewattz.com/wp-json/voxel-toolkit/v1/stats';

    /**
     * Option key for tracking opt-out status (default is opted IN)
     */
    const OPT_OUT_OPTION = 'voxel_toolkit_stats_optout';

    /**
     * Singleton instance
     */
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
    public function __construct() {
        // Don't send stats FROM codewattz.com (it's the receiver)
        if ($this->is_codewattz()) {
            return;
        }

        // Send stats on settings changes (if not opted out)
        if (!$this->is_opted_out()) {
            add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);

            // Send initial stats on first load if not sent yet
            add_action('admin_init', array($this, 'maybe_send_initial_stats'));
        }

        // Add opt-out UI to settings page
        add_action('voxel_toolkit/settings_page_footer', array($this, 'render_optout_section'));
        add_action('wp_ajax_vt_stats_optout', array($this, 'handle_optout_ajax'));
        add_action('wp_ajax_vt_stats_send_now', array($this, 'handle_send_now_ajax'));
    }

    /**
     * Check if current site is codewattz.com
     */
    public function is_codewattz() {
        $host = parse_url(home_url(), PHP_URL_HOST);
        return in_array($host, array('codewattz.com', 'www.codewattz.com'));
    }

    /**
     * Check if user has opted out (default is opted IN)
     */
    public function is_opted_out() {
        return get_option(self::OPT_OUT_OPTION) === 'yes';
    }

    // =========================================
    // OPT-OUT UI (shown on settings page footer)
    // =========================================

    /**
     * Render the opt-out section on the settings page
     */
    public function render_optout_section() {
        $is_opted_out = $this->is_opted_out();
        $nonce = wp_create_nonce('vt_stats_optout');
        $last_sent = get_option('voxel_toolkit_stats_last_sent');
        $last_error = get_option('voxel_toolkit_stats_last_error');
        $site_key = $this->get_or_create_site_key();

        // Get example data
        $example_data = array(
            'enabled_functions' => array('Auto Verify Posts', 'Claim Listing', 'Quick Search'),
            'enabled_widgets' => array('Advanced Gallery', 'Business Hours'),
            'enabled_post_fields' => array('Poll Field'),
        );
        ?>
        <div class="vt-stats-optout-section">
            <div class="vt-stats-optout-inner">
                <div class="vt-stats-optout-icon">
                    <span class="dashicons dashicons-chart-bar"></span>
                </div>
                <div class="vt-stats-optout-content">
                    <p class="vt-stats-optout-text">
                        <?php _e('This plugin sends anonymous feature usage data to help improve development.', 'voxel-toolkit'); ?>
                        <strong><?php _e('No personal, site, domain, IP address, or any identifying information is collected.', 'voxel-toolkit'); ?></strong>
                    </p>
                    <details class="vt-stats-example">
                        <summary><?php _e('Example of data sent', 'voxel-toolkit'); ?></summary>
                        <pre><?php echo esc_html(json_encode($example_data, JSON_PRETTY_PRINT)); ?></pre>
                    </details>
                    <div class="vt-stats-optout-toggle">
                        <?php if ($is_opted_out) : ?>
                            <span class="vt-stats-status vt-stats-status-disabled"><?php _e('Data sharing disabled', 'voxel-toolkit'); ?></span>
                            <button type="button" class="button button-small vt-stats-toggle-btn" data-action="optin" data-nonce="<?php echo esc_attr($nonce); ?>">
                                <?php _e('Enable', 'voxel-toolkit'); ?>
                            </button>
                        <?php else : ?>
                            <span class="vt-stats-status vt-stats-status-enabled"><?php _e('Data sharing enabled', 'voxel-toolkit'); ?></span>
                            <button type="button" class="button button-small vt-stats-toggle-btn" data-action="optout" data-nonce="<?php echo esc_attr($nonce); ?>">
                                <?php _e('Opt out', 'voxel-toolkit'); ?>
                            </button>
                            <button type="button" class="button button-small vt-stats-send-btn" data-nonce="<?php echo esc_attr($nonce); ?>">
                                <?php _e('Send Now', 'voxel-toolkit'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_opted_out) : ?>
                        <div class="vt-stats-status-info">
                            <?php if ($last_sent) : ?>
                                <span class="vt-stats-last-sent"><?php printf(__('Last sent: %s ago', 'voxel-toolkit'), human_time_diff($last_sent)); ?></span>
                            <?php else : ?>
                                <span class="vt-stats-last-sent"><?php _e('Not yet sent', 'voxel-toolkit'); ?></span>
                            <?php endif; ?>
                            <span class="vt-stats-send-result"></span>
                            <?php if ($last_error) : ?>
                                <span class="vt-stats-error"><?php echo esc_html($last_error); ?></span>
                            <?php endif; ?>
                            <br><span class="vt-stats-site-key"><?php printf(__('Site ID: %s', 'voxel-toolkit'), substr($site_key, 0, 8) . '...'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
            .vt-stats-optout-section {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #dcdcde;
            }
            .vt-stats-optout-inner {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                max-width: 600px;
            }
            .vt-stats-optout-icon {
                flex-shrink: 0;
                width: 32px;
                height: 32px;
                background: #f0f0f1;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .vt-stats-optout-icon .dashicons {
                color: #787c82;
                font-size: 18px;
                width: 18px;
                height: 18px;
            }
            .vt-stats-optout-content {
                flex: 1;
            }
            .vt-stats-optout-text {
                margin: 0 0 8px 0;
                font-size: 12px;
                color: #646970;
                line-height: 1.5;
            }
            .vt-stats-optout-text strong {
                color: #50575e;
            }
            .vt-stats-example {
                margin-bottom: 10px;
                font-size: 12px;
            }
            .vt-stats-example summary {
                color: #2271b1;
                cursor: pointer;
                font-size: 11px;
            }
            .vt-stats-example summary:hover {
                color: #135e96;
            }
            .vt-stats-example pre {
                margin: 8px 0 0 0;
                padding: 10px;
                background: #f6f7f7;
                border: 1px solid #dcdcde;
                border-radius: 3px;
                font-size: 11px;
                line-height: 1.4;
                overflow-x: auto;
                color: #50575e;
            }
            .vt-stats-optout-toggle {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .vt-stats-status {
                font-size: 11px;
            }
            .vt-stats-status-enabled {
                color: #00a32a;
            }
            .vt-stats-status-disabled {
                color: #787c82;
            }
            .vt-stats-toggle-btn,
            .vt-stats-send-btn {
                font-size: 11px !important;
                min-height: 24px !important;
                line-height: 22px !important;
                padding: 0 8px !important;
            }
            .vt-stats-status-info {
                margin-top: 8px;
                font-size: 11px;
                color: #787c82;
            }
            .vt-stats-last-sent {
                display: inline-block;
            }
            .vt-stats-error {
                display: block;
                color: #d63638;
                margin-top: 4px;
            }
            .vt-stats-send-result {
                display: inline-block;
                margin-left: 8px;
            }
            .vt-stats-send-result.success {
                color: #00a32a;
            }
            .vt-stats-send-result.error {
                color: #d63638;
            }
            .vt-stats-site-key {
                font-family: monospace;
                font-size: 10px;
                color: #a0a0a0;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.vt-stats-toggle-btn').on('click', function() {
                var $btn = $(this);
                var action = $btn.data('action');
                var nonce = $btn.data('nonce');
                var $section = $btn.closest('.vt-stats-optout-section');

                $btn.prop('disabled', true).text('<?php esc_attr_e('Please wait...', 'voxel-toolkit'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vt_stats_optout',
                        toggle: action,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text(action === 'optout' ? '<?php esc_attr_e('Opt out', 'voxel-toolkit'); ?>' : '<?php esc_attr_e('Enable', 'voxel-toolkit'); ?>');
                    }
                });
            });

            // Send Now button
            $('.vt-stats-send-btn').on('click', function() {
                var $btn = $(this);
                var nonce = $btn.data('nonce');
                var $result = $('.vt-stats-send-result');

                $btn.prop('disabled', true).text('<?php esc_attr_e('Sending...', 'voxel-toolkit'); ?>');
                $result.removeClass('success error').text('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vt_stats_send_now',
                        nonce: nonce
                    },
                    success: function(response) {
                        console.log('VT Stats response:', response);
                        $btn.prop('disabled', false).text('<?php esc_attr_e('Send Now', 'voxel-toolkit'); ?>');
                        if (response && response.success) {
                            $result.addClass('success').text('<?php esc_attr_e('Sent successfully!', 'voxel-toolkit'); ?>');
                            // Update last sent text
                            $('.vt-stats-last-sent').text('<?php esc_attr_e('Last sent: just now', 'voxel-toolkit'); ?>');
                        } else {
                            var msg = (response && response.data && response.data.message) ? response.data.message : '<?php esc_attr_e('Failed to send', 'voxel-toolkit'); ?>';
                            $result.addClass('error').text(msg);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('VT Stats error:', status, error, xhr.responseText);
                        $btn.prop('disabled', false).text('<?php esc_attr_e('Send Now', 'voxel-toolkit'); ?>');
                        $result.addClass('error').text('<?php esc_attr_e('Request failed: ', 'voxel-toolkit'); ?>' + error);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Handle opt-out/opt-in AJAX
     */
    public function handle_optout_ajax() {
        check_ajax_referer('vt_stats_optout', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $toggle = isset($_POST['toggle']) ? sanitize_text_field($_POST['toggle']) : '';

        if ($toggle === 'optout') {
            // Delete data from remote server before opting out
            $this->delete_remote_stats();
            update_option(self::OPT_OUT_OPTION, 'yes');
        } else {
            delete_option(self::OPT_OUT_OPTION);
            // Send stats immediately when opting back in
            $this->send_stats();
        }

        wp_send_json_success(array('message' => 'Setting saved'));
    }

    /**
     * Delete stats from remote server (called when user opts out)
     */
    private function delete_remote_stats() {
        $site_key = get_option('voxel_toolkit_site_key');

        if (empty($site_key)) {
            return false;
        }

        $delete_endpoint = str_replace('/stats', '/stats/delete', self::STATS_ENDPOINT);

        $response = wp_remote_post($delete_endpoint, array(
            'body' => json_encode(array('site_key' => $site_key)),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
            'sslverify' => true,
        ));

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Handle send now AJAX request
     */
    public function handle_send_now_ajax() {
        check_ajax_referer('vt_stats_optout', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Clear any previous error
        delete_option('voxel_toolkit_stats_last_error');

        $result = $this->send_stats();

        if ($result) {
            wp_send_json_success(array('message' => 'Stats sent successfully'));
        } else {
            $error = get_option('voxel_toolkit_stats_last_error', 'Unknown error');
            wp_send_json_error(array('message' => $error));
        }
    }

    // =========================================
    // SENDING DATA
    // =========================================

    /**
     * Maybe send initial stats (first time)
     */
    public function maybe_send_initial_stats() {
        // Only run once
        if (get_option('voxel_toolkit_stats_initial_sent')) {
            return;
        }

        // Don't send if opted out
        if ($this->is_opted_out()) {
            return;
        }

        // Send stats
        if ($this->send_stats()) {
            update_option('voxel_toolkit_stats_initial_sent', '1');
        }
    }

    /**
     * Handle settings update - send stats if not opted out
     */
    public function on_settings_updated($new_value, $old_value) {
        // Don't send stats FROM codewattz.com
        if ($this->is_codewattz()) {
            return;
        }

        // Don't send if opted out
        if ($this->is_opted_out()) {
            return;
        }

        $this->send_stats();
    }

    /**
     * Send anonymous stats to codewattz.com
     * Only sends: site_key (for deduplication), enabled functions/widgets/post fields
     */
    public function send_stats() {
        $data = array(
            'site_key' => $this->get_or_create_site_key(),
            'enabled_functions' => $this->get_enabled_functions(),
            'enabled_widgets' => $this->get_enabled_widgets(),
            'enabled_post_fields' => $this->get_enabled_post_fields(),
        );

        $response = wp_remote_post(self::STATS_ENDPOINT, array(
            'body' => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
            'sslverify' => true,
        ));

        // Log errors for debugging
        if (is_wp_error($response)) {
            $error_message = 'Connection failed: ' . $response->get_error_message();
            error_log('Voxel Toolkit Stats: ' . $error_message);
            update_option('voxel_toolkit_stats_last_error', $error_message);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_message = 'Server returned ' . $response_code;
            if ($body) {
                $decoded = json_decode($body, true);
                if (isset($decoded['message'])) {
                    $error_message .= ': ' . $decoded['message'];
                }
            }
            error_log('Voxel Toolkit Stats: ' . $error_message);
            update_option('voxel_toolkit_stats_last_error', $error_message);
            return false;
        }

        // Clear any previous error on success
        delete_option('voxel_toolkit_stats_last_error');

        // Update last sent timestamp
        update_option('voxel_toolkit_stats_last_sent', time());

        return true;
    }

    /**
     * Get list of enabled functions with friendly names (excluding post fields and widgets)
     */
    private function get_enabled_functions() {
        $options = get_option('voxel_toolkit_options', array());
        $enabled = array();

        // Get function definitions for friendly names
        $function_names = $this->get_function_names();

        foreach ($options as $key => $value) {
            // Skip post fields - they're tracked separately
            if (strpos($key, 'post_field_') === 0) {
                continue;
            }
            // Skip widgets - they're tracked separately
            if (strpos($key, 'widget_') === 0) {
                continue;
            }
            if (is_array($value) && !empty($value['enabled'])) {
                $enabled[] = isset($function_names[$key]) ? $function_names[$key] : $key;
            }
        }

        return $enabled;
    }

    /**
     * Get list of enabled widgets with friendly names
     * Widgets are stored in voxel_toolkit_options with 'widget_' prefix
     */
    private function get_enabled_widgets() {
        $options = get_option('voxel_toolkit_options', array());
        $enabled = array();

        // Get widget definitions for friendly names
        $widget_names = $this->get_widget_names();

        foreach ($options as $key => $value) {
            // Only include widgets (keys starting with 'widget_')
            if (strpos($key, 'widget_') === 0) {
                if (is_array($value) && !empty($value['enabled'])) {
                    $widget_key = str_replace('widget_', '', $key);
                    $enabled[] = isset($widget_names[$widget_key]) ? $widget_names[$widget_key] : $widget_key;
                }
            }
        }

        return $enabled;
    }

    /**
     * Get list of enabled post fields with friendly names
     */
    private function get_enabled_post_fields() {
        $options = get_option('voxel_toolkit_options', array());
        $enabled = array();

        // Get post field definitions for friendly names
        $field_names = $this->get_post_field_names();

        foreach ($options as $key => $value) {
            // Only include post fields
            if (strpos($key, 'post_field_') === 0) {
                if (is_array($value) && !empty($value['enabled'])) {
                    $field_key = str_replace('post_field_', '', $key);
                    $enabled[] = isset($field_names[$field_key]) ? $field_names[$field_key] : $field_key;
                }
            }
        }

        return $enabled;
    }

    /**
     * Get function key => name mapping
     */
    private function get_function_names() {
        if (!class_exists('Voxel_Toolkit_Functions')) {
            return array();
        }

        $functions_manager = Voxel_Toolkit_Functions::instance();
        $available = $functions_manager->get_available_functions();
        $names = array();

        foreach ($available as $key => $data) {
            $names[$key] = isset($data['name']) ? $data['name'] : $key;
        }

        return $names;
    }

    /**
     * Get widget key => name mapping
     */
    private function get_widget_names() {
        if (!class_exists('Voxel_Toolkit_Functions')) {
            return array();
        }

        $functions_manager = Voxel_Toolkit_Functions::instance();
        $available = $functions_manager->get_available_widgets();
        $names = array();

        foreach ($available as $key => $data) {
            $names[$key] = isset($data['name']) ? $data['name'] : $key;
        }

        return $names;
    }

    /**
     * Get post field key => name mapping
     */
    private function get_post_field_names() {
        if (!class_exists('Voxel_Toolkit_Post_Fields')) {
            return array();
        }

        $fields_manager = Voxel_Toolkit_Post_Fields::instance();
        $available = $fields_manager->get_available_post_fields();
        $names = array();

        foreach ($available as $key => $data) {
            $names[$key] = isset($data['name']) ? $data['name'] : $key;
        }

        return $names;
    }

    /**
     * Get or create unique site key (anonymous identifier for deduplication only)
     */
    private function get_or_create_site_key() {
        $site_key = get_option('voxel_toolkit_site_key');

        if (empty($site_key)) {
            $site_key = wp_generate_uuid4();
            update_option('voxel_toolkit_site_key', $site_key);
        }

        return $site_key;
    }
}
