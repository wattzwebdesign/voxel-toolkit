<?php
/**
 * Team Members Function
 *
 * Allows post authors to invite team members by email who can then edit the post.
 * Includes custom post field, invite system, and app event notifications.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Team_Members {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Meta key for storing team members
     */
    const META_KEY = '_vt_team_members';

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
        self::$instance = $this;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_vt_team_member_invite', array($this, 'ajax_invite_member'));
        add_action('wp_ajax_vt_team_member_remove', array($this, 'ajax_remove_member'));
        add_action('wp_ajax_vt_team_member_accept', array($this, 'ajax_accept_invite'));
        add_action('wp_ajax_nopriv_vt_team_member_accept', array($this, 'ajax_accept_invite'));
        add_action('wp_ajax_vt_team_member_decline', array($this, 'ajax_decline_invite'));
        add_action('wp_ajax_nopriv_vt_team_member_decline', array($this, 'ajax_decline_invite'));
        add_action('wp_ajax_vt_team_member_resend', array($this, 'ajax_resend_invite'));
        add_action('wp_ajax_vt_team_members_get', array($this, 'ajax_get_members'));

        // Add frontend Vue template for create/edit form
        add_action('wp_head', array($this, 'add_frontend_template'));

        // Add accept modal template to footer
        add_action('wp_footer', array($this, 'add_accept_modal'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Hook into WordPress capability system to grant edit permissions
        add_filter('user_has_cap', array($this, 'grant_team_member_capabilities'), 10, 4);

        // Block team members from deleting posts (runs early before Voxel's handler)
        add_action('voxel_ajax_user.posts.delete_post', array($this, 'block_team_member_delete'), 5);

        // Handle accept link
        add_action('template_redirect', array($this, 'handle_accept_link'));

        // Register app events
        add_filter('voxel/app-events/register', array($this, 'register_app_events'));
        add_filter('voxel/app-events/categories', array($this, 'register_event_category'));

        // Register visibility rule
        add_filter('voxel/dynamic-data/visibility-rules', array($this, 'register_visibility_rules'));
    }

    /**
     * Register custom visibility rules
     */
    public function register_visibility_rules($rules) {
        // Only register if both base class and our rule class exist
        if (class_exists('Voxel_Toolkit_User_Is_Team_Member_Rule')) {
            $rules['user:is_team_member'] = \Voxel_Toolkit_User_Is_Team_Member_Rule::class;
        }
        return $rules;
    }

    /**
     * Register event category
     */
    public function register_event_category($categories) {
        // Use shared Voxel Toolkit category
        if (!isset($categories['voxel_toolkit'])) {
            $categories['voxel_toolkit'] = [
                'key' => 'voxel_toolkit',
                'label' => 'Voxel Toolkit',
            ];
        }
        return $categories;
    }

    /**
     * Register app events
     */
    public function register_app_events($events) {
        if (!class_exists('\\Voxel\\Events\\Base_Event')) {
            return $events;
        }

        // Team Member Invited
        $invited = new Voxel_Toolkit_Team_Member_Invited_Event();
        $events[$invited->get_key()] = $invited;

        // Team Member Accepted
        $accepted = new Voxel_Toolkit_Team_Member_Accepted_Event();
        $events[$accepted->get_key()] = $accepted;

        // Team Member Declined
        $declined = new Voxel_Toolkit_Team_Member_Declined_Event();
        $events[$declined->get_key()] = $declined;

        return $events;
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style(
            'voxel-toolkit-team-members',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/team-members.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        wp_enqueue_script(
            'voxel-toolkit-team-members',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/team-members.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        wp_localize_script('voxel-toolkit-team-members', 'vtTeamMembers', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_team_members_nonce'),
            'i18n' => array(
                'inviteSent' => __('Invite sent successfully', 'voxel-toolkit'),
                'inviteError' => __('Failed to send invite', 'voxel-toolkit'),
                'removeConfirm' => __('Are you sure you want to remove this team member?', 'voxel-toolkit'),
                'removed' => __('Team member removed', 'voxel-toolkit'),
                'accepted' => __('You have been added as a team member', 'voxel-toolkit'),
                'expired' => __('This invite has expired', 'voxel-toolkit'),
                'invalidEmail' => __('The logged in email does not match the invite', 'voxel-toolkit'),
                'loginRequired' => __('Please log in to accept this invite', 'voxel-toolkit'),
                'alreadyInvited' => __('This email has already been invited', 'voxel-toolkit'),
                'acceptTitle' => __('Team Member Invite', 'voxel-toolkit'),
                'acceptMessage' => __('You have been invited to collaborate on this post. Accept to gain edit access.', 'voxel-toolkit'),
                'acceptButton' => __('Accept Invite', 'voxel-toolkit'),
                'declineButton' => __('Decline', 'voxel-toolkit'),
            ),
        ));
    }

    /**
     * Handle accept link on page load
     * Note: This method now only validates the token exists.
     * The modal reads GET params directly since cookies aren't available on the same request.
     */
    public function handle_accept_link() {
        // Validation is now handled in add_accept_modal() which reads GET params directly
    }

    /**
     * Get the login page URL from settings
     *
     * @param string $redirect_url URL to redirect to after login
     * @return string Login page URL
     */
    public function get_login_url($redirect_url = '') {
        // Get settings
        if (!class_exists('Voxel_Toolkit_Settings')) {
            require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/class-settings.php';
        }

        $settings = \Voxel_Toolkit_Settings::instance();
        $function_settings = $settings->get_function_settings('team_members', array());
        $login_page_id = isset($function_settings['login_page_id']) ? absint($function_settings['login_page_id']) : 0;

        if ($login_page_id) {
            $login_url = get_permalink($login_page_id);
            if ($login_url && $redirect_url) {
                $login_url = add_query_arg('redirect_to', urlencode($redirect_url), $login_url);
            }
            return $login_url;
        }

        // Fall back to WordPress login
        return wp_login_url($redirect_url);
    }

    /**
     * AJAX: Invite a team member
     */
    public function ajax_invite_member() {
        check_ajax_referer('vt_team_members_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (!$post_id || !$email) {
            wp_send_json_error(array('message' => __('Invalid request', 'voxel-toolkit')));
        }

        // Check if user can manage team members (must be author)
        if (!$this->can_manage_team_members($post_id)) {
            wp_send_json_error(array('message' => __('Permission denied', 'voxel-toolkit')));
        }

        // Validate email
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address', 'voxel-toolkit')));
        }

        // Check if already invited
        $members = $this->get_team_members($post_id);
        foreach ($members as $member) {
            if (strtolower($member['email']) === strtolower($email)) {
                wp_send_json_error(array('message' => __('This email has already been invited', 'voxel-toolkit')));
            }
        }

        // Generate unique token
        $token = wp_generate_password(32, false);

        // Get expiration from field settings
        $expiration_days = $this->get_field_expiration($post_id);
        $expires_at = time() + ($expiration_days * DAY_IN_SECONDS);

        // Add to team members
        $members[] = array(
            'email' => $email,
            'token' => $token,
            'status' => 'pending',
            'invited_at' => time(),
            'expires_at' => $expires_at,
            'accepted_at' => null,
            'user_id' => null,
        );

        $this->save_team_members($post_id, $members);

        // Send invite email (do this before responding so errors don't break the response)
        $this->fire_invite_event($post_id, $email, $token, $expires_at);

        // Send success response
        wp_send_json_success(array(
            'message' => __('Invite sent successfully', 'voxel-toolkit'),
            'member' => array(
                'email' => $email,
                'status' => 'pending',
                'invited_at' => time(),
                'expires_at' => $expires_at,
            ),
        ));
    }

    /**
     * AJAX: Remove a team member
     */
    public function ajax_remove_member() {
        check_ajax_referer('vt_team_members_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (!$post_id || !$email) {
            wp_send_json_error(array('message' => __('Invalid request', 'voxel-toolkit')));
        }

        // Check if user can manage team members (must be author)
        if (!$this->can_manage_team_members($post_id)) {
            wp_send_json_error(array('message' => __('Permission denied', 'voxel-toolkit')));
        }

        $members = $this->get_team_members($post_id);
        $new_members = array();

        foreach ($members as $member) {
            if (strtolower($member['email']) !== strtolower($email)) {
                $new_members[] = $member;
            }
        }

        $this->save_team_members($post_id, $new_members);

        wp_send_json_success(array('message' => __('Team member removed', 'voxel-toolkit')));
    }

    /**
     * AJAX: Accept an invite
     */
    public function ajax_accept_invite() {
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$token || !$post_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'voxel-toolkit')));
        }

        // Must be logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => __('Please log in to accept this invite', 'voxel-toolkit'),
                'require_login' => true,
            ));
        }

        $current_user = wp_get_current_user();
        $user_email = strtolower($current_user->user_email);

        $members = $this->get_team_members($post_id);
        $found = false;
        $updated_members = array();

        foreach ($members as $member) {
            if ($member['token'] === $token) {
                $found = true;

                // Check email matches
                if (strtolower($member['email']) !== $user_email) {
                    wp_send_json_error(array(
                        'message' => __('The logged in email does not match the invite', 'voxel-toolkit'),
                    ));
                }

                // Check expiration
                if (isset($member['expires_at']) && $member['expires_at'] < time()) {
                    wp_send_json_error(array(
                        'message' => __('This invite has expired', 'voxel-toolkit'),
                    ));
                }

                // Update status
                $member['status'] = 'accepted';
                $member['accepted_at'] = time();
                $member['user_id'] = $current_user->ID;
            }

            $updated_members[] = $member;
        }

        if (!$found) {
            wp_send_json_error(array('message' => __('Invalid invite token', 'voxel-toolkit')));
        }

        $this->save_team_members($post_id, $updated_members);

        // Fire accepted event
        $this->fire_accepted_event($post_id, $current_user->ID, $user_email);

        wp_send_json_success(array(
            'message' => __('You have been added as a team member', 'voxel-toolkit'),
        ));
    }

    /**
     * AJAX: Decline an invite
     */
    public function ajax_decline_invite() {
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$token || !$post_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'voxel-toolkit')));
        }

        $members = $this->get_team_members($post_id);
        $found = false;
        $declined_email = '';
        $updated_members = array();

        foreach ($members as $member) {
            if ($member['token'] === $token && $member['status'] === 'pending') {
                $found = true;
                $declined_email = $member['email'];
                // Remove declined member from list
                continue;
            }
            $updated_members[] = $member;
        }

        if (!$found) {
            wp_send_json_error(array('message' => __('Invalid invite token', 'voxel-toolkit')));
        }

        $this->save_team_members($post_id, $updated_members);

        // Fire declined event
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        $this->fire_declined_event($post_id, $user_id, $declined_email);

        wp_send_json_success(array(
            'message' => __('Invite declined', 'voxel-toolkit'),
        ));
    }

    /**
     * AJAX: Resend an invite
     */
    public function ajax_resend_invite() {
        check_ajax_referer('vt_team_members_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (!$post_id || !$email) {
            wp_send_json_error(array('message' => __('Invalid request', 'voxel-toolkit')));
        }

        // Check if user can manage team members (must be author)
        if (!$this->can_manage_team_members($post_id)) {
            wp_send_json_error(array('message' => __('Permission denied', 'voxel-toolkit')));
        }

        $members = $this->get_team_members($post_id);
        $found = false;

        foreach ($members as &$member) {
            if (strtolower($member['email']) === strtolower($email) && $member['status'] === 'pending') {
                $found = true;

                // Generate new token
                $member['token'] = wp_generate_password(32, false);

                // Reset expiration
                $expiration_days = $this->get_field_expiration($post_id);
                $member['expires_at'] = time() + ($expiration_days * DAY_IN_SECONDS);
                $member['invited_at'] = time();

                // Fire app event
                $this->fire_invite_event($post_id, $member['email'], $member['token'], $member['expires_at']);

                break;
            }
        }

        if (!$found) {
            wp_send_json_error(array('message' => __('Invite not found', 'voxel-toolkit')));
        }

        $this->save_team_members($post_id, $members);

        wp_send_json_success(array('message' => __('Invite resent successfully', 'voxel-toolkit')));
    }

    /**
     * AJAX: Get team members for a post
     */
    public function ajax_get_members() {
        check_ajax_referer('vt_team_members_nonce', 'nonce');

        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'voxel-toolkit')));
        }

        // Check if user can view team members
        if (!$this->can_manage_team_members($post_id) && !$this->is_team_member($post_id)) {
            wp_send_json_error(array('message' => __('Permission denied', 'voxel-toolkit')));
        }

        $members = $this->get_team_members($post_id);

        // Sanitize for output (remove tokens for security)
        $safe_members = array();
        foreach ($members as $member) {
            $safe_members[] = array(
                'email' => $member['email'],
                'status' => $member['status'],
                'invited_at' => $member['invited_at'],
                'expires_at' => isset($member['expires_at']) ? $member['expires_at'] : null,
                'accepted_at' => isset($member['accepted_at']) ? $member['accepted_at'] : null,
                'is_expired' => isset($member['expires_at']) && $member['expires_at'] < time() && $member['status'] === 'pending',
            );
        }

        wp_send_json_success(array('members' => $safe_members));
    }

    /**
     * Fire the team member invite app event
     */
    private function fire_invite_event($post_id, $email, $token, $expires_at) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        // Build accept URL
        $accept_url = add_query_arg(array(
            'vt_team_accept' => $post_id,
            'token' => $token,
        ), get_permalink($post_id));

        // Fire Voxel app event for hooks/admin notifications (but invitee email sent separately)
        if (class_exists('Voxel_Toolkit_Team_Member_Invited_Event') && class_exists('\\Voxel\\Events\\Base_Event')) {
            try {
                $event = new \Voxel_Toolkit_Team_Member_Invited_Event();
                $event->dispatch($post_id, $email, $accept_url, $expires_at);
            } catch (\Exception $e) {
                error_log('VT Team Members: Event dispatch failed: ' . $e->getMessage());
            }
        }

        // Always send invitee email directly (they might not have an account yet)
        $author = get_user_by('id', $post->post_author);
        $author_name = $author ? $author->display_name : __('The post author', 'voxel-toolkit');
        $expires_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expires_at);

        $subject = sprintf(__('You\'ve been invited to collaborate on %s', 'voxel-toolkit'), $post->post_title);

        $message = sprintf(
            __('Hello,

You have been invited to collaborate on <strong>%1$s</strong> by %2$s.

Click the link below to accept this invitation and gain edit access:

<a href="%3$s">Accept Invitation</a>

This invitation expires on %4$s.

If you don\'t have an account yet, please register first and then click the link above.

Thank you!', 'voxel-toolkit'),
            esc_html($post->post_title),
            esc_html($author_name),
            esc_url($accept_url),
            esc_html($expires_date)
        );

        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Fire the team member accepted app event
     */
    private function fire_accepted_event($post_id, $user_id, $email) {
        if (!class_exists('Voxel_Toolkit_Team_Member_Accepted_Event') || !class_exists('\\Voxel\\Events\\Base_Event')) {
            return;
        }

        try {
            $event = new \Voxel_Toolkit_Team_Member_Accepted_Event();
            $event->dispatch($post_id, $user_id, $email);
        } catch (\Exception $e) {
            error_log('VT Team Members: Accepted event dispatch failed: ' . $e->getMessage());
        }
    }

    /**
     * Fire the team member declined app event
     */
    private function fire_declined_event($post_id, $user_id, $email) {
        if (!class_exists('Voxel_Toolkit_Team_Member_Declined_Event') || !class_exists('\\Voxel\\Events\\Base_Event')) {
            return;
        }

        try {
            $event = new \Voxel_Toolkit_Team_Member_Declined_Event();
            $event->dispatch($post_id, $user_id, $email);
        } catch (\Exception $e) {
            error_log('VT Team Members: Declined event dispatch failed: ' . $e->getMessage());
        }
    }

    /**
     * Get field expiration setting for a post
     */
    private function get_field_expiration($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return 7; // Default 7 days
        }

        // Try to get from Voxel post type config
        if (function_exists('\Voxel\Post')) {
            $voxel_post = \Voxel\Post::get($post);
            if ($voxel_post && $voxel_post->post_type) {
                foreach ($voxel_post->post_type->get_fields() as $field) {
                    if ($field->get_type() === 'team-members-vt') {
                        $expiration = $field->get_prop('invite_expiration');
                        if ($expiration) {
                            return intval($expiration);
                        }
                    }
                }
            }
        }

        return 7; // Default 7 days
    }

    /**
     * Check if current user can manage team members for a post
     */
    public function can_manage_team_members($post_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        $current_user_id = get_current_user_id();

        // Admins can always manage team members
        if (current_user_can('manage_options')) {
            return true;
        }

        // Post author can manage team members
        return intval($post->post_author) === $current_user_id;
    }

    /**
     * Check if current user is a team member of the post
     */
    public function is_team_member($post_id, $user_id = null) {
        if ($user_id === null) {
            if (!is_user_logged_in()) {
                return false;
            }
            $user_id = get_current_user_id();
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $members = $this->get_team_members($post_id);
        $user_email = strtolower($user->user_email);

        foreach ($members as $member) {
            if (strtolower($member['email']) === $user_email && $member['status'] === 'accepted') {
                return true;
            }
        }

        return false;
    }

    /**
     * Grant team members edit capabilities for their posts
     *
     * @param array $allcaps All capabilities for the user
     * @param array $caps Required capabilities being checked
     * @param array $args Arguments: [0] = capability, [1] = user_id, [2] = post_id (optional)
     * @param WP_User $user User object
     * @return array Modified capabilities
     */
    public function grant_team_member_capabilities($allcaps, $caps, $args, $user) {
        $requested_cap = isset($args[0]) ? $args[0] : '';

        // Get post ID from args - must be explicitly provided
        $post_id = isset($args[2]) ? absint($args[2]) : 0;

        // If no post_id in args, don't process
        if (!$post_id) {
            return $allcaps;
        }

        // Use current logged-in user if filter user is 0 (fixes Elementor rendering context issues)
        $check_user_id = $user->ID;
        if ($check_user_id === 0 && is_user_logged_in()) {
            $check_user_id = get_current_user_id();
        }

        if ($check_user_id === 0) {
            return $allcaps;
        }

        // Check if this post type has a team members field
        if (!$this->post_has_team_members_field($post_id)) {
            return $allcaps;
        }

        // Check if user is an accepted team member (but not the author)
        $post = get_post($post_id);
        $is_author = $post && (int) $post->post_author === $check_user_id;
        $is_team_member = $this->is_team_member($post_id, $check_user_id);

        // If user is a team member but NOT the author, handle permissions carefully
        if ($is_team_member && !$is_author) {
            // Delete capabilities - ALWAYS DENY for non-author team members
            $delete_caps = array(
                'delete_post',
                'delete_posts',
                'delete_others_posts',
                'delete_published_posts',
                'delete_private_posts',
            );

            if (in_array($requested_cap, $delete_caps)) {
                // Explicitly deny delete capabilities for team members who are not authors
                foreach ($caps as $cap) {
                    $allcaps[$cap] = false;
                }
                return $allcaps;
            }

            // Edit capabilities - GRANT for team members
            $edit_caps = array(
                'edit_post',
                'edit_posts',
                'edit_others_posts',
                'edit_published_posts',
                'edit_private_posts',
            );

            if (in_array($requested_cap, $edit_caps)) {
                // Grant edit capabilities
                foreach ($caps as $cap) {
                    $allcaps[$cap] = true;
                }
            }
        }

        return $allcaps;
    }

    /**
     * Block team members from deleting posts they don't own
     * Runs early before Voxel's delete handler
     */
    public function block_team_member_delete() {
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        if (!$post_id) {
            return; // Let Voxel handle the error
        }

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return; // Let Voxel handle auth
        }

        // Check if user is a team member (but not the author)
        $post = get_post($post_id);
        if (!$post) {
            return; // Let Voxel handle the error
        }

        $is_author = (int) $post->post_author === $current_user_id;

        // If user is the author, allow deletion (Voxel will handle its own checks)
        if ($is_author) {
            return;
        }

        // If user is a team member but NOT the author, block deletion
        if ($this->is_team_member($post_id, $current_user_id)) {
            wp_send_json([
                'success' => false,
                'message' => __('Team members cannot delete posts. Only the post owner can delete this listing.', 'voxel-toolkit'),
            ]);
            exit;
        }
    }

    /**
     * Check if post type has team members field
     * Uses direct option lookup since user_has_cap runs before Voxel classes are loaded
     */
    private function post_has_team_members_field($post_id) {
        static $cache = array();

        if (isset($cache[$post_id])) {
            return $cache[$post_id];
        }

        // Get post type directly from WordPress
        $post_type = get_post_type($post_id);
        if (!$post_type) {
            $cache[$post_id] = false;
            return false;
        }

        // Check Voxel's post type configuration from options
        $post_types_option = get_option('voxel:post_types', '[]');
        $post_types_data = json_decode($post_types_option, true);

        if (!is_array($post_types_data)) {
            $cache[$post_id] = false;
            return false;
        }

        // Find the post type config
        foreach ($post_types_data as $pt_config) {
            if (!isset($pt_config['settings']['key']) || $pt_config['settings']['key'] !== $post_type) {
                continue;
            }

            // Check if this post type has team-members-vt field
            if (isset($pt_config['fields']) && is_array($pt_config['fields'])) {
                foreach ($pt_config['fields'] as $field) {
                    if (isset($field['type']) && $field['type'] === 'team-members-vt') {
                        $cache[$post_id] = true;
                        return true;
                    }
                }
            }

            break;
        }

        $cache[$post_id] = false;
        return false;
    }

    /**
     * Get team members for a post
     */
    public function get_team_members($post_id) {
        $members = get_post_meta($post_id, self::META_KEY, true);
        return is_array($members) ? $members : array();
    }

    /**
     * Save team members for a post
     */
    private function save_team_members($post_id, $members) {
        update_post_meta($post_id, self::META_KEY, $members);
    }

    /**
     * Add frontend Vue template for create/edit post form
     */
    public function add_frontend_template() {
        ?>
        <script>
        document.addEventListener('voxel/create-post/init', e => {
            const { app, config, el } = e.detail;

            app.component('field-team-members-vt', {
                template: `
                    <div class="ts-form-group vt-team-members-field">
                        <label>
                            {{ field.label }}
                            <slot name="errors"></slot>
                        </label>
                        <p v-if="field.description" class="ts-description" style="margin-bottom: 10px; color: #666; font-size: 13px;">
                            {{ field.description }}
                        </p>

                        <!-- Only show management UI if user is author -->
                        <div v-if="isAuthor" class="vt-team-members-manager">
                            <!-- Add member input -->
                            <div class="vt-team-add-member">
                                <input
                                    type="email"
                                    v-model="newEmail"
                                    :placeholder="field.props.placeholder || 'Enter email address...'"
                                    class="ts-filter"
                                    @keyup.enter="inviteMember"
                                    :disabled="isLoading"
                                />
                                <button
                                    type="button"
                                    class="ts-btn ts-btn-1"
                                    @click="inviteMember"
                                    :disabled="isLoading || !newEmail"
                                >
                                    {{ isLoading ? '...' : 'Invite' }}
                                </button>
                            </div>

                            <!-- Error/Success message -->
                            <div v-if="message" :class="['vt-team-message', messageType]">
                                {{ message }}
                            </div>

                            <!-- Members list -->
                            <div v-if="members.length" class="vt-team-members-list">
                                <div v-for="member in members" :key="member.email" class="vt-team-member">
                                    <div class="vt-team-member-info">
                                        <span class="vt-team-member-email">{{ member.email }}</span>
                                        <span :class="['vt-team-member-status', member.status, { expired: member.is_expired }]">
                                            {{ getStatusLabel(member) }}
                                        </span>
                                    </div>
                                    <div class="vt-team-member-actions">
                                        <button
                                            v-if="member.status === 'pending' && !member.is_expired"
                                            type="button"
                                            class="vt-team-action-btn resend"
                                            @click="resendInvite(member.email)"
                                            :disabled="isLoading"
                                            title="Resend Invite"
                                        >
                                            ↻
                                        </button>
                                        <button
                                            type="button"
                                            class="vt-team-action-btn remove"
                                            @click="removeMember(member.email)"
                                            :disabled="isLoading"
                                            title="Remove"
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <p v-else class="vt-team-no-members">
                                No team members yet. Invite someone by entering their email above.
                            </p>
                        </div>

                        <!-- View-only for team members -->
                        <div v-else class="vt-team-members-readonly">
                            <p class="vt-team-member-notice">
                                You are a team member of this post. Only the author can manage team members.
                            </p>
                        </div>
                    </div>
                `,
                props: {
                    field: Object
                },
                data() {
                    return {
                        newEmail: '',
                        members: [],
                        isLoading: false,
                        message: '',
                        messageType: 'success',
                        isAuthor: true,
                    };
                },
                mounted() {
                    this.loadMembers();
                    this.checkIsAuthor();
                },
                methods: {
                    checkIsAuthor() {
                        if (window.Voxel && window.Voxel.config && window.Voxel.config.post) {
                            const postAuthor = window.Voxel.config.post.author_id;
                            const currentUser = window.Voxel.config.user?.id;
                            this.isAuthor = postAuthor === currentUser;
                        }
                    },
                    async loadMembers() {
                        if (!this.field.props.post_id) return;

                        try {
                            const response = await fetch(vtTeamMembers.ajaxUrl + '?action=vt_team_members_get&post_id=' + this.field.props.post_id + '&nonce=' + vtTeamMembers.nonce);
                            const data = await response.json();
                            if (data.success) {
                                this.members = data.data.members;
                            }
                        } catch (error) {
                            console.error('Failed to load team members:', error);
                        }
                    },
                    async inviteMember() {
                        if (!this.newEmail || this.isLoading) return;

                        this.isLoading = true;
                        this.message = '';

                        try {
                            const formData = new FormData();
                            formData.append('action', 'vt_team_member_invite');
                            formData.append('nonce', vtTeamMembers.nonce);
                            formData.append('post_id', this.field.props.post_id);
                            formData.append('email', this.newEmail);

                            const response = await fetch(vtTeamMembers.ajaxUrl, {
                                method: 'POST',
                                body: formData,
                            });

                            const data = await response.json();

                            if (data.success) {
                                this.members.push(data.data.member);
                                this.newEmail = '';
                                this.showMessage(vtTeamMembers.i18n.inviteSent, 'success');
                            } else {
                                this.showMessage(data.data.message || vtTeamMembers.i18n.inviteError, 'error');
                            }
                        } catch (error) {
                            this.showMessage(vtTeamMembers.i18n.inviteError, 'error');
                        } finally {
                            this.isLoading = false;
                        }
                    },
                    async removeMember(email) {
                        if (!confirm(vtTeamMembers.i18n.removeConfirm)) return;

                        this.isLoading = true;

                        try {
                            const formData = new FormData();
                            formData.append('action', 'vt_team_member_remove');
                            formData.append('nonce', vtTeamMembers.nonce);
                            formData.append('post_id', this.field.props.post_id);
                            formData.append('email', email);

                            const response = await fetch(vtTeamMembers.ajaxUrl, {
                                method: 'POST',
                                body: formData,
                            });

                            const data = await response.json();

                            if (data.success) {
                                this.members = this.members.filter(m => m.email !== email);
                                this.showMessage(vtTeamMembers.i18n.removed, 'success');
                            } else {
                                this.showMessage(data.data.message, 'error');
                            }
                        } catch (error) {
                            this.showMessage('Failed to remove member', 'error');
                        } finally {
                            this.isLoading = false;
                        }
                    },
                    async resendInvite(email) {
                        this.isLoading = true;

                        try {
                            const formData = new FormData();
                            formData.append('action', 'vt_team_member_resend');
                            formData.append('nonce', vtTeamMembers.nonce);
                            formData.append('post_id', this.field.props.post_id);
                            formData.append('email', email);

                            const response = await fetch(vtTeamMembers.ajaxUrl, {
                                method: 'POST',
                                body: formData,
                            });

                            const data = await response.json();

                            if (data.success) {
                                this.showMessage('Invite resent', 'success');
                                this.loadMembers();
                            } else {
                                this.showMessage(data.data.message, 'error');
                            }
                        } catch (error) {
                            this.showMessage('Failed to resend invite', 'error');
                        } finally {
                            this.isLoading = false;
                        }
                    },
                    getStatusLabel(member) {
                        if (member.status === 'accepted') {
                            return 'Accepted';
                        }
                        if (member.is_expired) {
                            return 'Expired';
                        }
                        return 'Pending';
                    },
                    showMessage(msg, type) {
                        this.message = msg;
                        this.messageType = type;
                        setTimeout(() => {
                            this.message = '';
                        }, 4000);
                    },
                    validate() {
                        return true;
                    }
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Add accept modal to footer
     */
    public function add_accept_modal() {
        // Read GET parameters directly (cookies aren't available on the same request they're set)
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $post_id = isset($_GET['vt_team_accept']) ? absint($_GET['vt_team_accept']) : 0;

        if (!$token || !$post_id) {
            return;
        }

        // Verify the token exists and is valid for this post
        $members = $this->get_team_members($post_id);
        $valid_token = false;
        $invite_email = '';

        foreach ($members as $member) {
            if ($member['token'] === $token) {
                $valid_token = true;
                $invite_email = $member['email'];
                break;
            }
        }

        if (!$valid_token) {
            return;
        }

        $is_logged_in = is_user_logged_in();
        $return_url = add_query_arg(array(
            'vt_team_accept' => $post_id,
            'token' => $token,
        ), get_permalink($post_id));
        $login_url = $this->get_login_url($return_url);
        ?>
        <div id="vt-team-accept-modal" class="vt-team-modal" style="display: none;">
            <div class="vt-team-modal-backdrop"></div>
            <div class="vt-team-modal-content">
                <h3><?php _e('Team Member Invite', 'voxel-toolkit'); ?></h3>
                <?php if ($is_logged_in): ?>
                    <p><?php _e('You have been invited to collaborate on this post. Accept to gain edit access.', 'voxel-toolkit'); ?></p>
                    <div class="vt-team-modal-actions">
                        <button type="button" class="ts-btn ts-btn-1" id="vt-team-accept-btn">
                            <?php _e('Accept Invite', 'voxel-toolkit'); ?>
                        </button>
                        <button type="button" class="ts-btn ts-btn-2" id="vt-team-decline-btn">
                            <?php _e('Decline', 'voxel-toolkit'); ?>
                        </button>
                    </div>
                <?php else: ?>
                    <p><?php _e('You have been invited to collaborate on this post. Please log in or create an account to accept this invite.', 'voxel-toolkit'); ?></p>
                    <div class="vt-team-modal-actions">
                        <a href="<?php echo esc_url($login_url); ?>" class="ts-btn ts-btn-1" id="vt-team-login-btn">
                            <?php _e('Log In / Register', 'voxel-toolkit'); ?>
                        </a>
                        <button type="button" class="ts-btn ts-btn-2" id="vt-team-decline-btn">
                            <?php _e('Decline', 'voxel-toolkit'); ?>
                        </button>
                    </div>
                <?php endif; ?>
                <div id="vt-team-modal-message" class="vt-team-message" style="display: none;"></div>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('vt-team-accept-modal');
            const acceptBtn = document.getElementById('vt-team-accept-btn');
            const declineBtn = document.getElementById('vt-team-decline-btn');
            const messageEl = document.getElementById('vt-team-modal-message');

            if (!modal) return;

            modal.style.display = 'flex';

            <?php if ($is_logged_in): ?>
            acceptBtn.addEventListener('click', async function() {
                acceptBtn.disabled = true;
                acceptBtn.textContent = '...';

                try {
                    const formData = new FormData();
                    formData.append('action', 'vt_team_member_accept');
                    formData.append('token', '<?php echo esc_js($token); ?>');
                    formData.append('post_id', '<?php echo esc_js($post_id); ?>');

                    const response = await fetch(vtTeamMembers.ajaxUrl, {
                        method: 'POST',
                        body: formData,
                    });

                    const data = await response.json();

                    if (data.success) {
                        messageEl.className = 'vt-team-message success';
                        messageEl.textContent = data.data.message;
                        messageEl.style.display = 'block';
                        setTimeout(() => {
                            modal.style.display = 'none';
                            // Remove URL parameters and reload
                            const url = new URL(window.location);
                            url.searchParams.delete('vt_team_accept');
                            url.searchParams.delete('token');
                            window.location.href = url.toString();
                        }, 1500);
                    } else {
                        messageEl.className = 'vt-team-message error';
                        messageEl.textContent = data.data.message;
                        messageEl.style.display = 'block';
                        acceptBtn.disabled = false;
                        acceptBtn.textContent = '<?php _e('Accept Invite', 'voxel-toolkit'); ?>';
                    }
                } catch (error) {
                    messageEl.className = 'vt-team-message error';
                    messageEl.textContent = 'An error occurred';
                    messageEl.style.display = 'block';
                    acceptBtn.disabled = false;
                    acceptBtn.textContent = '<?php _e('Accept Invite', 'voxel-toolkit'); ?>';
                }
            });
            <?php endif; ?>

            declineBtn.addEventListener('click', async function() {
                declineBtn.disabled = true;
                declineBtn.textContent = '...';

                try {
                    const formData = new FormData();
                    formData.append('action', 'vt_team_member_decline');
                    formData.append('token', '<?php echo esc_js($token); ?>');
                    formData.append('post_id', '<?php echo esc_js($post_id); ?>');

                    const response = await fetch(vtTeamMembers.ajaxUrl, {
                        method: 'POST',
                        body: formData,
                    });

                    const data = await response.json();

                    messageEl.className = data.success ? 'vt-team-message success' : 'vt-team-message error';
                    messageEl.textContent = data.data?.message || (data.success ? 'Invite declined' : 'Error');
                    messageEl.style.display = 'block';

                    setTimeout(() => {
                        modal.style.display = 'none';
                        // Remove URL parameters
                        const url = new URL(window.location);
                        url.searchParams.delete('vt_team_accept');
                        url.searchParams.delete('token');
                        window.history.replaceState({}, '', url);
                    }, 1000);
                } catch (error) {
                    // Just close the modal on error
                    modal.style.display = 'none';
                    const url = new URL(window.location);
                    url.searchParams.delete('vt_team_accept');
                    url.searchParams.delete('token');
                    window.history.replaceState({}, '', url);
                }
            });

            modal.querySelector('.vt-team-modal-backdrop').addEventListener('click', function() {
                modal.style.display = 'none';
                // Remove URL parameters
                const url = new URL(window.location);
                url.searchParams.delete('vt_team_accept');
                url.searchParams.delete('token');
                window.history.replaceState({}, '', url);
            });
        });
        </script>
        <?php
    }
}

// Only define the field type class if the parent class exists (Voxel theme is loaded)
if (!class_exists('\Voxel\Post_Types\Fields\Base_Post_Field')) {
    return;
}

/**
 * Team Members Field Type Class
 */
class Voxel_Toolkit_Team_Members_Field_Type extends \Voxel\Post_Types\Fields\Base_Post_Field {

    protected $supported_conditions = [];

    protected $props = [
        'type' => 'team-members-vt',
        'label' => 'Team Members (VT)',
        'placeholder' => '',
        'invite_expiration' => 7,
    ];

    public function get_models(): array {
        return [
            'label' => $this->get_label_model(),
            'key' => $this->get_key_model(),
            'placeholder' => $this->get_placeholder_model(),
            'description' => $this->get_description_model(),
            'invite_expiration' => [
                'type' => \Voxel\Form_Models\Number_Model::class,
                'label' => 'Invite expiration (days)',
                'description' => 'Number of days before an invite expires',
                'width' => '1/2',
            ],
            'css_class' => $this->get_css_class_model(),
            'hidden' => $this->get_hidden_model(),
        ];
    }

    public function sanitize($value) {
        return '';
    }

    public function validate($value): void {
    }

    public function update($value): void {
    }

    public function get_value_from_post() {
        return '';
    }

    protected function editing_value() {
        return '';
    }

    protected function frontend_props() {
        $post_id = 0;
        if ($this->post) {
            $post_id = $this->post->get_id();
        }

        return [
            'placeholder' => $this->get_model_value('placeholder') ?: __('Enter email address...', 'voxel-toolkit'),
            'post_id' => $post_id,
            'invite_expiration' => $this->get_prop('invite_expiration'),
        ];
    }

    public function get_frontend_markup(): string {
        return '';
    }
}

/**
 * Team Member Events
 */
if (class_exists('\\Voxel\\Events\\Base_Event')) {

    /**
     * Custom Data Group for Team Member Invitee
     */
    class Voxel_Toolkit_Invitee_Data_Group extends \Voxel\Dynamic_Data\Data_Groups\Base_Data_Group {

        protected $email;
        protected $accept_url;
        protected $expires_at;

        public function __construct($email = '', $accept_url = '', $expires_at = 0) {
            $this->email = $email;
            $this->accept_url = $accept_url;
            $this->expires_at = $expires_at;
        }

        public function get_type(): string {
            return 'vt_invitee';
        }

        protected function properties(): array {
            return [
                'email' => \Voxel\Dynamic_Data\Tag::Email('Invitee email')->render(function() {
                    return $this->email;
                }),
                'accept_url' => \Voxel\Dynamic_Data\Tag::URL('Accept invitation URL')->render(function() {
                    return $this->accept_url;
                }),
                'expires_date' => \Voxel\Dynamic_Data\Tag::String('Expiration date')->render(function() {
                    return $this->expires_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $this->expires_at) : '';
                }),
            ];
        }

        public static function mock(): \Voxel\Dynamic_Data\Data_Groups\Base_Data_Group {
            return new static('invitee@example.com', home_url('/accept-invite'), time() + (7 * DAY_IN_SECONDS));
        }
    }

    /**
     * Custom Data Group for Team Member
     */
    class Voxel_Toolkit_Member_Data_Group extends \Voxel\Dynamic_Data\Data_Groups\Base_Data_Group {

        protected $user;
        protected $email;

        public function __construct($user = null, $email = '') {
            $this->user = $user;
            $this->email = $email;
        }

        public function get_type(): string {
            return 'vt_member';
        }

        protected function properties(): array {
            return [
                'display_name' => \Voxel\Dynamic_Data\Tag::String('Display name')->render(function() {
                    return $this->user ? $this->user->get_display_name() : $this->email;
                }),
                'email' => \Voxel\Dynamic_Data\Tag::Email('Email address')->render(function() {
                    return $this->email;
                }),
            ];
        }

        public static function mock(): \Voxel\Dynamic_Data\Data_Groups\Base_Data_Group {
            return new static(\Voxel\User::mock(), 'member@example.com');
        }
    }

    /**
     * Team Member Invited Event
     */
    class Voxel_Toolkit_Team_Member_Invited_Event extends \Voxel\Events\Base_Event {

        public $post;
        public $invitee_email;
        public $accept_url;
        public $expires_at;

        public function get_key(): string {
            return 'voxel_toolkit/team-member:invited';
        }

        public function get_label(): string {
            return 'Team member invited';
        }

        public function get_category() {
            return 'voxel_toolkit';
        }

        public function prepare($post_id, $email = '', $accept_url = '', $expires_at = 0) {
            $this->post = $post_id ? \Voxel\Post::force_get($post_id) : null;
            $this->invitee_email = $email ?: '';
            $this->accept_url = $accept_url ?: '';
            $this->expires_at = $expires_at ?: 0;
        }

        public static function notifications(): array {
            return [
                'admin' => [
                    'label' => 'Notify admin',
                    'recipient' => function($event) {
                        // Get admin user from Voxel settings, fall back to WordPress admin
                        $admin_id = \Voxel\get('settings.notifications.admin_user');
                        if ($admin_id) {
                            return \Voxel\User::get($admin_id);
                        }
                        // Fallback to first admin user
                        $admins = get_users(['role' => 'administrator', 'number' => 1]);
                        return !empty($admins) ? \Voxel\User::get($admins[0]->ID) : null;
                    },
                    'inapp' => [
                        'enabled' => false,
                        'subject' => "Team member invited to @post(title)",
                        'details' => function($event) {
                            return [
                                'post_id' => $event->post ? $event->post->get_id() : 0,
                                'email' => $event->invitee_email ?: '',
                                'accept_url' => $event->accept_url ?: '',
                                'expires_at' => $event->expires_at ?: 0,
                            ];
                        },
                        'apply_details' => function($event, $details) {
                            $event->prepare(
                                $details['post_id'] ?? 0,
                                $details['email'] ?? '',
                                $details['accept_url'] ?? '',
                                $details['expires_at'] ?? 0
                            );
                        },
                        'links_to' => function($event) {
                            return $event->post ? $event->post->get_link() : home_url();
                        },
                    ],
                    'email' => [
                        'enabled' => false,
                        'subject' => "Team member invited to @post(title)",
                        'message' => "Hello,\n\n@post(author.display_name) has invited @invitee(email) to collaborate on <strong>@post(title)</strong>.\n\nThis invitation expires on @invitee(expires_date).",
                    ],
                ],
            ];
        }

        public function set_mock_props() {
            $this->post = \Voxel\Post::mock();
            $this->invitee_email = 'invitee@example.com';
            $this->accept_url = home_url('/accept-invite');
            $this->expires_at = time() + (7 * DAY_IN_SECONDS);
        }

        public function dynamic_tags(): array {
            $tags = [
                'invitee' => new Voxel_Toolkit_Invitee_Data_Group(
                    $this->invitee_email ?: '',
                    $this->accept_url ?: '',
                    $this->expires_at ?: 0
                ),
            ];
            if ($this->post) {
                $tags['post'] = \Voxel\Dynamic_Data\Group::Post($this->post);
            }
            return $tags;
        }
    }

    /**
     * Team Member Accepted Event
     */
    class Voxel_Toolkit_Team_Member_Accepted_Event extends \Voxel\Events\Base_Event {

        public $post;
        public $member;
        public $member_email;

        public function get_key(): string {
            return 'voxel_toolkit/team-member:accepted';
        }

        public function get_label(): string {
            return 'Team member accepted';
        }

        public function get_category() {
            return 'voxel_toolkit';
        }

        public function prepare($post_id, $user_id = null, $email = '') {
            $this->post = $post_id ? \Voxel\Post::force_get($post_id) : null;
            $this->member = $user_id ? \Voxel\User::get($user_id) : null;
            $this->member_email = $email ?: '';
        }

        public static function notifications(): array {
            return [
                'author' => [
                    'label' => 'Notify post author',
                    'recipient' => function($event) {
                        return $event->post ? $event->post->get_author() : null;
                    },
                    'inapp' => [
                        'enabled' => true,
                        'subject' => '@member(display_name) accepted your team invite for @post(title)',
                        'details' => function($event) {
                            return [
                                'post_id' => $event->post ? $event->post->get_id() : 0,
                                'user_id' => $event->member ? $event->member->get_id() : 0,
                                'email' => $event->member_email ?: '',
                            ];
                        },
                        'apply_details' => function($event, $details) {
                            $event->prepare(
                                $details['post_id'] ?? 0,
                                $details['user_id'] ?? 0,
                                $details['email'] ?? ''
                            );
                        },
                        'links_to' => function($event) {
                            return $event->post ? $event->post->get_link() : home_url();
                        },
                        'image_id' => function($event) {
                            return $event->member ? $event->member->get_avatar_id() : null;
                        },
                    ],
                    'email' => [
                        'enabled' => false,
                        'subject' => '@member(display_name) accepted your team invite',
                        'message' => "Hello,\n\n@member(display_name) has accepted your invitation to collaborate on <strong>@post(title)</strong>.\n\nThey now have edit access to this post.",
                    ],
                ],
                'admin' => [
                    'label' => 'Notify admin',
                    'recipient' => function($event) {
                        $admin_id = \Voxel\get('settings.notifications.admin_user');
                        if ($admin_id) {
                            return \Voxel\User::get($admin_id);
                        }
                        $admins = get_users(['role' => 'administrator', 'number' => 1]);
                        return !empty($admins) ? \Voxel\User::get($admins[0]->ID) : null;
                    },
                    'inapp' => [
                        'enabled' => false,
                        'subject' => "Team member accepted invite for @post(title)",
                        'details' => function($event) {
                            return [
                                'post_id' => $event->post ? $event->post->get_id() : 0,
                                'user_id' => $event->member ? $event->member->get_id() : 0,
                                'email' => $event->member_email ?: '',
                            ];
                        },
                        'apply_details' => function($event, $details) {
                            $event->prepare(
                                $details['post_id'] ?? 0,
                                $details['user_id'] ?? 0,
                                $details['email'] ?? ''
                            );
                        },
                        'links_to' => function($event) {
                            return $event->post ? $event->post->get_link() : home_url();
                        },
                    ],
                    'email' => [
                        'enabled' => false,
                        'subject' => "Team member accepted invite for @post(title)",
                        'message' => "Hello,\n\n@member(display_name) (@member(email)) has accepted the invitation to collaborate on <strong>@post(title)</strong>.\n\nThey now have edit access to this post.",
                    ],
                ],
            ];
        }

        public function set_mock_props() {
            $this->post = \Voxel\Post::mock();
            $this->member = \Voxel\User::mock();
            $this->member_email = 'member@example.com';
        }

        public function dynamic_tags(): array {
            $tags = [
                'member' => new Voxel_Toolkit_Member_Data_Group($this->member, $this->member_email ?: ''),
            ];
            if ($this->post) {
                $tags['post'] = \Voxel\Dynamic_Data\Group::Post($this->post);
            }
            return $tags;
        }
    }

    /**
     * Team Member Declined Event
     */
    class Voxel_Toolkit_Team_Member_Declined_Event extends \Voxel\Events\Base_Event {

        public $post;
        public $member;
        public $member_email;

        public function get_key(): string {
            return 'voxel_toolkit/team-member:declined';
        }

        public function get_label(): string {
            return 'Team member declined';
        }

        public function get_category() {
            return 'voxel_toolkit';
        }

        public function prepare($post_id, $user_id = null, $email = '') {
            $this->post = $post_id ? \Voxel\Post::force_get($post_id) : null;
            $this->member = $user_id ? \Voxel\User::get($user_id) : null;
            $this->member_email = $email ?: '';
        }

        public static function notifications(): array {
            return [
                'author' => [
                    'label' => 'Notify post author',
                    'recipient' => function($event) {
                        return $event->post ? $event->post->get_author() : null;
                    },
                    'inapp' => [
                        'enabled' => true,
                        'subject' => '@member(display_name) declined your team invite for @post(title)',
                        'details' => function($event) {
                            return [
                                'post_id' => $event->post ? $event->post->get_id() : 0,
                                'user_id' => $event->member ? $event->member->get_id() : 0,
                                'email' => $event->member_email ?: '',
                            ];
                        },
                        'apply_details' => function($event, $details) {
                            $event->prepare(
                                $details['post_id'] ?? 0,
                                $details['user_id'] ?? 0,
                                $details['email'] ?? ''
                            );
                        },
                        'links_to' => function($event) {
                            return $event->post ? $event->post->get_link() : home_url();
                        },
                        'image_id' => function($event) {
                            return $event->member ? $event->member->get_avatar_id() : null;
                        },
                    ],
                    'email' => [
                        'enabled' => false,
                        'subject' => '@member(display_name) declined your team invite',
                        'message' => "Hello,\n\n@member(display_name) has declined your invitation to collaborate on <strong>@post(title)</strong>.",
                    ],
                ],
                'admin' => [
                    'label' => 'Notify admin',
                    'recipient' => function($event) {
                        $admin_id = \Voxel\get('settings.notifications.admin_user');
                        if ($admin_id) {
                            return \Voxel\User::get($admin_id);
                        }
                        $admins = get_users(['role' => 'administrator', 'number' => 1]);
                        return !empty($admins) ? \Voxel\User::get($admins[0]->ID) : null;
                    },
                    'inapp' => [
                        'enabled' => false,
                        'subject' => "Team member declined invite for @post(title)",
                        'details' => function($event) {
                            return [
                                'post_id' => $event->post ? $event->post->get_id() : 0,
                                'user_id' => $event->member ? $event->member->get_id() : 0,
                                'email' => $event->member_email ?: '',
                            ];
                        },
                        'apply_details' => function($event, $details) {
                            $event->prepare(
                                $details['post_id'] ?? 0,
                                $details['user_id'] ?? 0,
                                $details['email'] ?? ''
                            );
                        },
                        'links_to' => function($event) {
                            return $event->post ? $event->post->get_link() : home_url();
                        },
                    ],
                    'email' => [
                        'enabled' => false,
                        'subject' => "Team member declined invite for @post(title)",
                        'message' => "Hello,\n\n@member(display_name) (@member(email)) has declined the invitation to collaborate on <strong>@post(title)</strong>.",
                    ],
                ],
            ];
        }

        public function set_mock_props() {
            $this->post = \Voxel\Post::mock();
            $this->member = \Voxel\User::mock();
            $this->member_email = 'member@example.com';
        }

        public function dynamic_tags(): array {
            $tags = [
                'member' => new Voxel_Toolkit_Member_Data_Group($this->member, $this->member_email ?: ''),
            ];
            if ($this->post) {
                $tags['post'] = \Voxel\Dynamic_Data\Group::Post($this->post);
            }
            return $tags;
        }
    }
}

/**
 * User Is Team Member Visibility Rule
 *
 * Returns true if the current user is a team member of the current post
 */
if (class_exists('\Voxel\Dynamic_Data\Visibility_Rules\Base_Visibility_Rule')) {
    class Voxel_Toolkit_User_Is_Team_Member_Rule extends \Voxel\Dynamic_Data\Visibility_Rules\Base_Visibility_Rule {

        public function get_type(): string {
            return 'user:is_team_member';
        }

        public function get_label(): string {
            return _x('User is team member of current post', 'visibility rules', 'voxel-toolkit');
        }

        public function evaluate(): bool {
            // Must be logged in
            if (!is_user_logged_in()) {
                return false;
            }

            // Get current post
            $post = \Voxel\get_current_post();
            if (!$post) {
                return false;
            }

            $post_id = $post->get_id();
            $user_id = get_current_user_id();

            // Use the main class's is_team_member method for consistency
            $team_members_instance = \Voxel_Toolkit_Team_Members::instance();
            if ($team_members_instance) {
                return $team_members_instance->is_team_member($post_id, $user_id);
            }

            return false;
        }
    }
}
