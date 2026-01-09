<?php
/**
 * Temporary Login Function
 *
 * Allows administrators to generate unique URLs for one-click temporary login access.
 * Uses secure selector/validator pattern for token storage.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Temporary_Login {

    /**
     * Database table names
     */
    private $tokens_table;
    private $logs_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->tokens_table = $wpdb->prefix . 'vt_temp_login_tokens';
        $this->logs_table = $wpdb->prefix . 'vt_temp_login_logs';

        $this->init_hooks();
        $this->maybe_create_tables();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Handle login via URL parameter
        add_action('init', array($this, 'handle_temp_login'), 1);

        // AJAX handlers
        add_action('wp_ajax_vt_create_temp_login', array($this, 'ajax_create_token'));
        add_action('wp_ajax_vt_delete_temp_login', array($this, 'ajax_delete_token'));
        add_action('wp_ajax_vt_toggle_temp_login', array($this, 'ajax_toggle_token'));
        add_action('wp_ajax_vt_get_temp_logins', array($this, 'ajax_get_tokens'));

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Cleanup expired tokens daily
        add_action('vt_cleanup_expired_temp_logins', array($this, 'cleanup_expired_tokens'));
        if (!wp_next_scheduled('vt_cleanup_expired_temp_logins')) {
            wp_schedule_event(time(), 'daily', 'vt_cleanup_expired_temp_logins');
        }
    }

    /**
     * Create database tables if they don't exist
     */
    private function maybe_create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Check if tables exist
        $tokens_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->tokens_table}'") === $this->tokens_table;
        $logs_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->logs_table}'") === $this->logs_table;

        if ($tokens_exists && $logs_exists) {
            return;
        }

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Tokens table
        if (!$tokens_exists) {
            $sql_tokens = "CREATE TABLE {$this->tokens_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                selector varchar(32) NOT NULL,
                validator_hash varchar(255) NOT NULL,
                token varchar(100) NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                created_by bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at datetime NOT NULL,
                redirect_url varchar(500) DEFAULT '',
                notes text DEFAULT '',
                status varchar(20) NOT NULL DEFAULT 'active',
                is_temp_user tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY selector (selector),
                KEY user_id (user_id),
                KEY status (status),
                KEY expires_at (expires_at)
            ) $charset_collate;";

            dbDelta($sql_tokens);
        }

        // Logs table
        if (!$logs_exists) {
            $sql_logs = "CREATE TABLE {$this->logs_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                token_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                ip_address varchar(45) NOT NULL,
                user_agent text DEFAULT '',
                logged_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                success tinyint(1) NOT NULL DEFAULT 1,
                failure_reason varchar(255) DEFAULT '',
                PRIMARY KEY (id),
                KEY token_id (token_id),
                KEY user_id (user_id),
                KEY logged_at (logged_at)
            ) $charset_collate;";

            dbDelta($sql_logs);
        }
    }

    /**
     * Add admin menu under Users
     */
    public function add_admin_menu() {
        add_users_page(
            __('Temp Logins', 'voxel-toolkit'),
            __('Temp Logins (VT)', 'voxel-toolkit'),
            'create_users',
            'vt-temp-logins',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'users_page_vt-temp-logins') {
            return;
        }

        wp_enqueue_style(
            'vt-temp-login-admin',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/temp-login-admin.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        wp_enqueue_script(
            'vt-temp-login-admin',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/temp-login-admin.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Get all editable roles
        $editable_roles = get_editable_roles();
        $roles = array();
        foreach ($editable_roles as $role_key => $role_data) {
            $roles[$role_key] = $role_data['name'];
        }

        wp_localize_script('vt-temp-login-admin', 'vtTempLogin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_temp_login_nonce'),
            'homeUrl' => home_url(),
            'adminUrl' => admin_url(),
            'roles' => $roles,
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this temporary login?', 'voxel-toolkit'),
                'confirm_delete_user' => __('This will also delete the temporary user account. Are you sure?', 'voxel-toolkit'),
                'copied' => __('Copied to clipboard!', 'voxel-toolkit'),
                'error' => __('An error occurred. Please try again.', 'voxel-toolkit'),
                'creating' => __('Creating...', 'voxel-toolkit'),
                'create' => __('Create Temporary Login', 'voxel-toolkit'),
            )
        ));
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Check capabilities
        if (!current_user_can('create_users')) {
            wp_die(__('You do not have permission to access this page.', 'voxel-toolkit'));
        }

        $tokens = $this->get_all_tokens();
        $users = get_users(array('orderby' => 'display_name'));

        ?>
        <div class="wrap vt-temp-login-wrap">
            <h1><?php _e('Temporary Logins', 'voxel-toolkit'); ?></h1>
            <p class="description"><?php _e('Generate secure one-click login URLs for temporary access to the site.', 'voxel-toolkit'); ?></p>

            <!-- Create New Token Section -->
            <div class="vt-temp-login-create-section">
                <h2><?php _e('Create New Temporary Login', 'voxel-toolkit'); ?></h2>

                <form id="vt-temp-login-form" class="vt-temp-login-form">
                    <div class="vt-form-row">
                        <div class="vt-form-group">
                            <label for="vt-user-type"><?php _e('User Type', 'voxel-toolkit'); ?></label>
                            <select id="vt-user-type" name="user_type">
                                <option value="existing"><?php _e('Existing User', 'voxel-toolkit'); ?></option>
                                <option value="new"><?php _e('Create New Temporary User', 'voxel-toolkit'); ?></option>
                            </select>
                        </div>

                        <div class="vt-form-group vt-existing-user-field">
                            <label for="vt-user-id"><?php _e('Select User', 'voxel-toolkit'); ?></label>
                            <select id="vt-user-id" name="user_id">
                                <option value=""><?php _e('-- Select User --', 'voxel-toolkit'); ?></option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo esc_attr($user->ID); ?>">
                                        <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="vt-form-group vt-new-user-field" style="display: none;">
                            <label for="vt-new-username"><?php _e('Username', 'voxel-toolkit'); ?></label>
                            <input type="text" id="vt-new-username" name="new_username" placeholder="<?php esc_attr_e('temp_user_123', 'voxel-toolkit'); ?>">
                        </div>

                        <div class="vt-form-group vt-new-user-field" style="display: none;">
                            <label for="vt-new-email"><?php _e('Email', 'voxel-toolkit'); ?></label>
                            <input type="email" id="vt-new-email" name="new_email" placeholder="<?php esc_attr_e('temp@example.com', 'voxel-toolkit'); ?>">
                        </div>

                        <div class="vt-form-group vt-new-user-field" style="display: none;">
                            <label for="vt-new-role"><?php _e('Role', 'voxel-toolkit'); ?></label>
                            <select id="vt-new-role" name="new_role">
                                <?php wp_dropdown_roles('subscriber'); ?>
                            </select>
                        </div>
                    </div>

                    <div class="vt-form-row">
                        <div class="vt-form-group">
                            <label for="vt-expiry-value"><?php _e('Expires After', 'voxel-toolkit'); ?></label>
                            <div class="vt-expiry-inputs">
                                <input type="number" id="vt-expiry-value" name="expiry_value" value="7" min="1" max="365">
                                <select id="vt-expiry-unit" name="expiry_unit">
                                    <option value="hours"><?php _e('Hours', 'voxel-toolkit'); ?></option>
                                    <option value="days" selected><?php _e('Days', 'voxel-toolkit'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="vt-form-group">
                            <label for="vt-redirect-url"><?php _e('Redirect After Login', 'voxel-toolkit'); ?></label>
                            <input type="url" id="vt-redirect-url" name="redirect_url" placeholder="<?php echo esc_attr(admin_url()); ?>">
                            <p class="description"><?php _e('Leave empty for admin dashboard', 'voxel-toolkit'); ?></p>
                        </div>
                    </div>

                    <div class="vt-form-row">
                        <div class="vt-form-group vt-form-group-full">
                            <label for="vt-notes"><?php _e('Notes (optional)', 'voxel-toolkit'); ?></label>
                            <textarea id="vt-notes" name="notes" rows="2" placeholder="<?php esc_attr_e('e.g., For developer John to debug issue #123', 'voxel-toolkit'); ?>"></textarea>
                        </div>
                    </div>

                    <div class="vt-form-actions">
                        <button type="submit" class="button button-primary" id="vt-create-btn">
                            <?php _e('Create Temporary Login', 'voxel-toolkit'); ?>
                        </button>
                    </div>
                </form>

                <!-- Generated URL Display -->
                <div id="vt-generated-url-section" class="vt-generated-url-section" style="display: none;">
                    <h3><?php _e('Temporary Login URL Created!', 'voxel-toolkit'); ?></h3>
                    <div class="vt-url-display">
                        <input type="text" id="vt-generated-url" readonly>
                        <button type="button" class="button" id="vt-copy-url">
                            <span class="dashicons dashicons-clipboard"></span>
                            <?php _e('Copy', 'voxel-toolkit'); ?>
                        </button>
                    </div>
                    <p class="description"><?php _e('Share this URL securely. Anyone with this link can log in to the selected account.', 'voxel-toolkit'); ?></p>
                </div>
            </div>

            <!-- Existing Tokens Table -->
            <div class="vt-temp-login-list-section">
                <h2><?php _e('Active Temporary Logins', 'voxel-toolkit'); ?></h2>

                <table class="wp-list-table widefat fixed striped" id="vt-temp-login-table">
                    <thead>
                        <tr>
                            <th class="column-user"><?php _e('User', 'voxel-toolkit'); ?></th>
                            <th class="column-created"><?php _e('Created', 'voxel-toolkit'); ?></th>
                            <th class="column-expires"><?php _e('Expires', 'voxel-toolkit'); ?></th>
                            <th class="column-status"><?php _e('Status', 'voxel-toolkit'); ?></th>
                            <th class="column-uses"><?php _e('Uses', 'voxel-toolkit'); ?></th>
                            <th class="column-notes"><?php _e('Notes', 'voxel-toolkit'); ?></th>
                            <th class="column-actions"><?php _e('Actions', 'voxel-toolkit'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="vt-tokens-tbody">
                        <?php if (empty($tokens)): ?>
                            <tr class="vt-no-tokens">
                                <td colspan="7"><?php _e('No temporary logins created yet.', 'voxel-toolkit'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tokens as $token): ?>
                                <?php $this->render_token_row($token); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Login Logs Section -->
            <div class="vt-temp-login-logs-section">
                <h2><?php _e('Login Activity Log', 'voxel-toolkit'); ?></h2>
                <?php $this->render_logs_table(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single token row
     */
    private function render_token_row($token) {
        $user = get_userdata($token->user_id);
        $creator = get_userdata($token->created_by);
        $is_expired = strtotime($token->expires_at) < time();
        $use_count = $this->get_token_use_count($token->id);

        $status_class = 'vt-status-' . $token->status;
        if ($is_expired) {
            $status_class = 'vt-status-expired';
        }

        ?>
        <tr data-token-id="<?php echo esc_attr($token->id); ?>" data-is-temp-user="<?php echo esc_attr($token->is_temp_user); ?>">
            <td class="column-user">
                <?php if ($user): ?>
                    <strong><?php echo esc_html($user->display_name); ?></strong>
                    <br><small><?php echo esc_html($user->user_email); ?></small>
                    <?php if ($token->is_temp_user): ?>
                        <br><span class="vt-temp-user-badge"><?php _e('Temp User', 'voxel-toolkit'); ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <em><?php _e('User deleted', 'voxel-toolkit'); ?></em>
                <?php endif; ?>
            </td>
            <td class="column-created">
                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($token->created_at))); ?>
                <?php if ($creator): ?>
                    <br><small><?php printf(__('by %s', 'voxel-toolkit'), esc_html($creator->display_name)); ?></small>
                <?php endif; ?>
            </td>
            <td class="column-expires">
                <?php
                echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($token->expires_at)));
                if ($is_expired) {
                    echo '<br><span class="vt-expired-label">' . __('Expired', 'voxel-toolkit') . '</span>';
                } else {
                    $time_left = human_time_diff(time(), strtotime($token->expires_at));
                    echo '<br><small>' . sprintf(__('%s left', 'voxel-toolkit'), $time_left) . '</small>';
                }
                ?>
            </td>
            <td class="column-status">
                <span class="vt-status-badge <?php echo esc_attr($status_class); ?>">
                    <?php
                    if ($is_expired) {
                        _e('Expired', 'voxel-toolkit');
                    } else {
                        echo esc_html(ucfirst($token->status));
                    }
                    ?>
                </span>
            </td>
            <td class="column-uses">
                <?php echo esc_html($use_count); ?>
            </td>
            <td class="column-notes">
                <?php echo esc_html($token->notes ?: '-'); ?>
            </td>
            <td class="column-actions">
                <div class="vt-token-actions">
                    <?php if (!$is_expired && $token->status === 'active'): ?>
                        <button type="button" class="button button-small vt-copy-token-url" data-token="<?php echo esc_attr($token->token); ?>" title="<?php esc_attr_e('Copy URL', 'voxel-toolkit'); ?>">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                        <button type="button" class="button button-small vt-toggle-token" data-action="disable" title="<?php esc_attr_e('Disable', 'voxel-toolkit'); ?>">
                            <span class="dashicons dashicons-hidden"></span>
                        </button>
                    <?php elseif (!$is_expired && $token->status === 'disabled'): ?>
                        <button type="button" class="button button-small vt-toggle-token" data-action="enable" title="<?php esc_attr_e('Enable', 'voxel-toolkit'); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="button button-small vt-delete-token" title="<?php esc_attr_e('Delete', 'voxel-toolkit'); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Render logs table
     */
    private function render_logs_table() {
        global $wpdb;

        $logs = $wpdb->get_results(
            "SELECT l.*, t.selector, t.user_id as token_user_id
             FROM {$this->logs_table} l
             LEFT JOIN {$this->tokens_table} t ON l.token_id = t.id
             ORDER BY l.logged_at DESC
             LIMIT 50"
        );

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date/Time', 'voxel-toolkit'); ?></th>
                    <th><?php _e('User', 'voxel-toolkit'); ?></th>
                    <th><?php _e('IP Address', 'voxel-toolkit'); ?></th>
                    <th><?php _e('User Agent', 'voxel-toolkit'); ?></th>
                    <th><?php _e('Status', 'voxel-toolkit'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5"><?php _e('No login activity recorded yet.', 'voxel-toolkit'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php $user = get_userdata($log->user_id); ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->logged_at))); ?></td>
                            <td>
                                <?php if ($user): ?>
                                    <?php echo esc_html($user->display_name); ?>
                                <?php else: ?>
                                    <?php _e('Unknown', 'voxel-toolkit'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($log->ip_address); ?></td>
                            <td class="vt-user-agent-cell" title="<?php echo esc_attr($log->user_agent); ?>">
                                <?php echo esc_html(wp_trim_words($log->user_agent, 10, '...')); ?>
                            </td>
                            <td>
                                <?php if ($log->success): ?>
                                    <span class="vt-status-badge vt-status-active"><?php _e('Success', 'voxel-toolkit'); ?></span>
                                <?php else: ?>
                                    <span class="vt-status-badge vt-status-expired"><?php _e('Failed', 'voxel-toolkit'); ?></span>
                                    <?php if ($log->failure_reason): ?>
                                        <br><small><?php echo esc_html($log->failure_reason); ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Get all tokens
     */
    private function get_all_tokens() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->tokens_table} ORDER BY created_at DESC"
        );
    }

    /**
     * Get token use count
     */
    private function get_token_use_count($token_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->logs_table} WHERE token_id = %d AND success = 1",
            $token_id
        ));
    }

    /**
     * Generate secure token using selector/validator pattern
     */
    private function generate_token() {
        // Generate cryptographically secure random bytes
        $selector = bin2hex(random_bytes(16)); // 32 chars
        $validator = bin2hex(random_bytes(32)); // 64 chars

        // Hash the validator for storage
        $validator_hash = password_hash($validator, PASSWORD_DEFAULT);

        return array(
            'selector' => $selector,
            'validator' => $validator,
            'validator_hash' => $validator_hash,
            'full_token' => $selector . '.' . $validator
        );
    }

    /**
     * Create a new temporary login token
     */
    public function create_token($user_id, $expiry_hours, $redirect_url = '', $notes = '', $is_temp_user = false) {
        global $wpdb;

        $token_data = $this->generate_token();

        $expires_at = date('Y-m-d H:i:s', time() + ($expiry_hours * 3600));

        $inserted = $wpdb->insert(
            $this->tokens_table,
            array(
                'selector' => $token_data['selector'],
                'validator_hash' => $token_data['validator_hash'],
                'token' => $token_data['full_token'],
                'user_id' => $user_id,
                'created_by' => get_current_user_id(),
                'expires_at' => $expires_at,
                'redirect_url' => $redirect_url,
                'notes' => $notes,
                'status' => 'active',
                'is_temp_user' => $is_temp_user ? 1 : 0
            ),
            array('%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d')
        );

        if (!$inserted) {
            return false;
        }

        return array(
            'id' => $wpdb->insert_id,
            'url' => $this->build_login_url($token_data['full_token']),
            'expires_at' => $expires_at
        );
    }

    /**
     * Build the login URL
     */
    private function build_login_url($token) {
        return add_query_arg('vt_temp_login', $token, home_url('/'));
    }

    /**
     * Handle temporary login via URL
     */
    public function handle_temp_login() {
        if (!isset($_GET['vt_temp_login']) || empty($_GET['vt_temp_login'])) {
            return;
        }

        // Already logged in
        if (is_user_logged_in()) {
            wp_safe_redirect(admin_url());
            exit;
        }

        $token = sanitize_text_field($_GET['vt_temp_login']);

        // Split token into selector and validator
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            $this->log_failed_attempt(0, 0, __('Invalid token format', 'voxel-toolkit'));
            wp_die(__('Invalid login link.', 'voxel-toolkit'), __('Login Failed', 'voxel-toolkit'), array('response' => 403));
        }

        $selector = $parts[0];
        $validator = $parts[1];

        // Validate token
        $result = $this->validate_token($selector, $validator);

        if (is_wp_error($result)) {
            wp_die($result->get_error_message(), __('Login Failed', 'voxel-toolkit'), array('response' => 403));
        }

        // Log successful login
        $this->log_login_attempt($result['token_id'], $result['user_id'], true);

        // Perform login
        $user = get_userdata($result['user_id']);
        if (!$user) {
            wp_die(__('User not found.', 'voxel-toolkit'), __('Login Failed', 'voxel-toolkit'), array('response' => 403));
        }

        // Set auth cookie
        wp_set_auth_cookie($user->ID, false);
        wp_set_current_user($user->ID);

        // Determine redirect URL
        $redirect_url = !empty($result['redirect_url']) ? $result['redirect_url'] : admin_url();

        // If redirect URL is relative, make it absolute
        if (!empty($redirect_url) && strpos($redirect_url, 'http') !== 0) {
            // Handle relative URLs (starting with /)
            if (strpos($redirect_url, '/') === 0) {
                $redirect_url = home_url($redirect_url);
            } else {
                // Assume it's a path without leading slash
                $redirect_url = home_url('/' . $redirect_url);
            }
        }

        // Validate redirect URL - allow same-site URLs
        $redirect_url = wp_validate_redirect($redirect_url, admin_url());

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Validate a token
     */
    private function validate_token($selector, $validator) {
        global $wpdb;

        // Get token by selector
        $token = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tokens_table} WHERE selector = %s",
            $selector
        ));

        if (!$token) {
            $this->log_failed_attempt(0, 0, __('Token not found', 'voxel-toolkit'));
            return new WP_Error('invalid_token', __('Invalid login link.', 'voxel-toolkit'));
        }

        // Check if token is active
        if ($token->status !== 'active') {
            $this->log_failed_attempt($token->id, $token->user_id, __('Token disabled', 'voxel-toolkit'));
            return new WP_Error('token_disabled', __('This login link has been disabled.', 'voxel-toolkit'));
        }

        // Check if token has expired
        if (strtotime($token->expires_at) < time()) {
            $this->log_failed_attempt($token->id, $token->user_id, __('Token expired', 'voxel-toolkit'));
            return new WP_Error('token_expired', __('This login link has expired.', 'voxel-toolkit'));
        }

        // Verify validator hash
        if (!password_verify($validator, $token->validator_hash)) {
            $this->log_failed_attempt($token->id, $token->user_id, __('Invalid validator', 'voxel-toolkit'));
            return new WP_Error('invalid_token', __('Invalid login link.', 'voxel-toolkit'));
        }

        // Check if user exists
        $user = get_userdata($token->user_id);
        if (!$user) {
            $this->log_failed_attempt($token->id, $token->user_id, __('User not found', 'voxel-toolkit'));
            return new WP_Error('user_not_found', __('The user for this login link no longer exists.', 'voxel-toolkit'));
        }

        return array(
            'token_id' => $token->id,
            'user_id' => $token->user_id,
            'redirect_url' => $token->redirect_url
        );
    }

    /**
     * Log a login attempt
     */
    private function log_login_attempt($token_id, $user_id, $success, $failure_reason = '') {
        global $wpdb;

        $wpdb->insert(
            $this->logs_table,
            array(
                'token_id' => $token_id,
                'user_id' => $user_id,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                'success' => $success ? 1 : 0,
                'failure_reason' => $failure_reason
            ),
            array('%d', '%d', '%s', '%s', '%d', '%s')
        );
    }

    /**
     * Log a failed attempt (when we don't have token info)
     */
    private function log_failed_attempt($token_id, $user_id, $reason) {
        $this->log_login_attempt($token_id, $user_id, false, $reason);
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Can be a comma-separated list
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }

    /**
     * AJAX: Create new token
     */
    public function ajax_create_token() {
        check_ajax_referer('vt_temp_login_nonce', 'nonce');

        if (!current_user_can('create_users')) {
            wp_send_json_error(__('Permission denied.', 'voxel-toolkit'));
        }

        $user_type = isset($_POST['user_type']) ? sanitize_text_field($_POST['user_type']) : 'existing';
        $expiry_value = isset($_POST['expiry_value']) ? absint($_POST['expiry_value']) : 7;
        $expiry_unit = isset($_POST['expiry_unit']) ? sanitize_text_field($_POST['expiry_unit']) : 'days';
        $redirect_url = isset($_POST['redirect_url']) ? esc_url_raw($_POST['redirect_url']) : '';
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';

        // Calculate expiry in hours
        $expiry_hours = $expiry_unit === 'days' ? $expiry_value * 24 : $expiry_value;

        // Validate expiry
        if ($expiry_hours < 1 || $expiry_hours > 8760) { // Max 1 year
            wp_send_json_error(__('Invalid expiry time. Must be between 1 hour and 365 days.', 'voxel-toolkit'));
        }

        $is_temp_user = false;

        if ($user_type === 'new') {
            // Create new temporary user
            $username = isset($_POST['new_username']) ? sanitize_user($_POST['new_username']) : '';
            $email = isset($_POST['new_email']) ? sanitize_email($_POST['new_email']) : '';
            $role = isset($_POST['new_role']) ? sanitize_text_field($_POST['new_role']) : 'subscriber';

            if (empty($username) || empty($email)) {
                wp_send_json_error(__('Username and email are required for new users.', 'voxel-toolkit'));
            }

            if (username_exists($username)) {
                wp_send_json_error(__('Username already exists.', 'voxel-toolkit'));
            }

            if (email_exists($email)) {
                wp_send_json_error(__('Email already exists.', 'voxel-toolkit'));
            }

            // Validate role
            $editable_roles = array_keys(get_editable_roles());
            if (!in_array($role, $editable_roles)) {
                wp_send_json_error(__('Invalid role selected.', 'voxel-toolkit'));
            }

            // Create user with random password
            $password = wp_generate_password(24, true, true);
            $user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                wp_send_json_error($user_id->get_error_message());
            }

            // Set role
            $user = get_userdata($user_id);
            $user->set_role($role);

            // Mark as temp user
            update_user_meta($user_id, '_vt_temp_login_user', 1);

            $is_temp_user = true;
        } else {
            // Use existing user
            $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

            if (!$user_id || !get_userdata($user_id)) {
                wp_send_json_error(__('Please select a valid user.', 'voxel-toolkit'));
            }
        }

        // Create token
        $result = $this->create_token($user_id, $expiry_hours, $redirect_url, $notes, $is_temp_user);

        if (!$result) {
            wp_send_json_error(__('Failed to create temporary login.', 'voxel-toolkit'));
        }

        wp_send_json_success(array(
            'url' => $result['url'],
            'expires_at' => $result['expires_at'],
            'message' => __('Temporary login created successfully!', 'voxel-toolkit')
        ));
    }

    /**
     * AJAX: Delete token
     */
    public function ajax_delete_token() {
        check_ajax_referer('vt_temp_login_nonce', 'nonce');

        if (!current_user_can('create_users')) {
            wp_send_json_error(__('Permission denied.', 'voxel-toolkit'));
        }

        $token_id = isset($_POST['token_id']) ? absint($_POST['token_id']) : 0;

        if (!$token_id) {
            wp_send_json_error(__('Invalid token ID.', 'voxel-toolkit'));
        }

        global $wpdb;

        // Get token info first
        $token = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tokens_table} WHERE id = %d",
            $token_id
        ));

        if (!$token) {
            wp_send_json_error(__('Token not found.', 'voxel-toolkit'));
        }

        // Check if we should delete the temp user too
        $delete_user = isset($_POST['delete_user']) && $_POST['delete_user'] === 'true';

        if ($delete_user && $token->is_temp_user) {
            // Check if this is the only token for this temp user
            $other_tokens = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tokens_table} WHERE user_id = %d AND id != %d",
                $token->user_id,
                $token_id
            ));

            if ($other_tokens == 0) {
                // Delete the temporary user
                require_once(ABSPATH . 'wp-admin/includes/user.php');
                wp_delete_user($token->user_id);
            }
        }

        // Delete logs for this token
        $wpdb->delete($this->logs_table, array('token_id' => $token_id), array('%d'));

        // Delete token
        $deleted = $wpdb->delete($this->tokens_table, array('id' => $token_id), array('%d'));

        if (!$deleted) {
            wp_send_json_error(__('Failed to delete token.', 'voxel-toolkit'));
        }

        wp_send_json_success(array(
            'message' => __('Temporary login deleted.', 'voxel-toolkit')
        ));
    }

    /**
     * AJAX: Toggle token status (enable/disable)
     */
    public function ajax_toggle_token() {
        check_ajax_referer('vt_temp_login_nonce', 'nonce');

        if (!current_user_can('create_users')) {
            wp_send_json_error(__('Permission denied.', 'voxel-toolkit'));
        }

        $token_id = isset($_POST['token_id']) ? absint($_POST['token_id']) : 0;
        $action = isset($_POST['toggle_action']) ? sanitize_text_field($_POST['toggle_action']) : '';

        if (!$token_id || !in_array($action, array('enable', 'disable'))) {
            wp_send_json_error(__('Invalid request.', 'voxel-toolkit'));
        }

        global $wpdb;

        $new_status = $action === 'enable' ? 'active' : 'disabled';

        $updated = $wpdb->update(
            $this->tokens_table,
            array('status' => $new_status),
            array('id' => $token_id),
            array('%s'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(__('Failed to update token status.', 'voxel-toolkit'));
        }

        wp_send_json_success(array(
            'message' => $action === 'enable'
                ? __('Temporary login enabled.', 'voxel-toolkit')
                : __('Temporary login disabled.', 'voxel-toolkit'),
            'new_status' => $new_status
        ));
    }

    /**
     * AJAX: Get all tokens (for refreshing the table)
     */
    public function ajax_get_tokens() {
        check_ajax_referer('vt_temp_login_nonce', 'nonce');

        if (!current_user_can('create_users')) {
            wp_send_json_error(__('Permission denied.', 'voxel-toolkit'));
        }

        $tokens = $this->get_all_tokens();

        ob_start();
        if (empty($tokens)) {
            ?>
            <tr class="vt-no-tokens">
                <td colspan="7"><?php _e('No temporary logins created yet.', 'voxel-toolkit'); ?></td>
            </tr>
            <?php
        } else {
            foreach ($tokens as $token) {
                $this->render_token_row($token);
            }
        }
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Cleanup expired tokens (scheduled task)
     */
    public function cleanup_expired_tokens() {
        global $wpdb;

        // Get tokens expired more than 30 days ago
        $old_date = date('Y-m-d H:i:s', strtotime('-30 days'));

        // Get temp users to potentially delete
        $temp_user_tokens = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$this->tokens_table}
             WHERE expires_at < %s AND is_temp_user = 1",
            $old_date
        ));

        // Delete old logs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->logs_table} WHERE logged_at < %s",
            $old_date
        ));

        // Delete old tokens
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tokens_table} WHERE expires_at < %s",
            $old_date
        ));

        // Clean up orphaned temp users
        if (!empty($temp_user_tokens)) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');

            foreach ($temp_user_tokens as $token) {
                // Check if user has any remaining tokens
                $remaining = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->tokens_table} WHERE user_id = %d",
                    $token->user_id
                ));

                if ($remaining == 0) {
                    // Check if this is actually a temp user
                    $is_temp = get_user_meta($token->user_id, '_vt_temp_login_user', true);
                    if ($is_temp) {
                        wp_delete_user($token->user_id);
                    }
                }
            }
        }
    }

    /**
     * Get login URL for a token by selector (used by JS for copy functionality)
     */
    public function get_login_url_by_selector($selector) {
        global $wpdb;

        $token = $wpdb->get_row($wpdb->prepare(
            "SELECT selector FROM {$this->tokens_table} WHERE selector = %s",
            $selector
        ));

        if (!$token) {
            return false;
        }

        // Note: We can't reconstruct the full URL since we don't store the validator
        // This is intentional for security - URLs are only shown once when created
        return false;
    }
}
