<?php
/**
 * Admin Notifications Function
 * 
 * Sends Voxel admin notifications to multiple users based on roles and individual selection
 * Extends Voxel Base_Controller for event handling
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if Voxel Base_Controller exists before defining class
// This prevents fatal errors during WP-CLI operations (cPanel, staging/live pushes, etc.)
if (!class_exists('\Voxel\Controllers\Base_Controller')) {
    return;
}

class Voxel_Toolkit_Admin_Notifications extends \Voxel\Controllers\Base_Controller {
    
    private $settings;
    private $enabled = false;

    /**
     * Constructor
     */
    public function __construct() {
        
        $this->settings = Voxel_Toolkit_Settings::instance();
        $this->load_settings();
        
        
        // Force test the recipients config
        $test_recipients = $this->get_notification_recipients();
        
        if ($this->enabled) {
            parent::__construct();
        }
        
        // Add a direct test hook to see if ANY Voxel events are firing
        add_action('voxel/app-events/user/post:created', array($this, 'test_event_firing'), 10, 1);
        add_action('voxel/app-events/admin/post:submitted', array($this, 'test_event_firing'), 10, 1);
        add_action('voxel/app-events/admin/post:approved', array($this, 'test_event_firing'), 10, 1);
        
        // Add a test for when someone logs in (common event)
        add_action('voxel/app-events/user/login', array($this, 'test_event_firing'), 10, 1);
        
        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }
    
    /**
     * Load settings
     */
    private function load_settings() {
        $function_settings = $this->settings->get_function_settings('admin_notifications', array(
            'enabled' => false,
            'user_roles' => array(),
            'selected_users' => array()
        ));
        
        
        $this->enabled = isset($function_settings['enabled']) ? $function_settings['enabled'] : false;
        
    }
    
    /**
     * Handle settings updates
     */
    public function on_settings_updated($old_settings, $new_settings) {
        $this->load_settings();
        
        // Re-initialize if we're enabled
        if ($this->enabled) {
            parent::__construct();
        }
    }

    /**
     * Hook registration for all Voxel events
     */
    protected function hooks() {
        
        $events = \Voxel\Events\Base_Event::get_all();
        
        foreach ($events as $event) {
            $hook_name = sprintf('voxel/app-events/%s', $event->get_key());
            $this->on($hook_name, '@send_multiple_admins_notifications', 10, 1);
        }
    }

    /**
     * Send notifications to multiple admin users
     * Processes all configured recipients for admin notifications
     */
    public function send_multiple_admins_notifications($event) {


        $recipients_config = $this->get_notification_recipients();

        $voxel_default_admin = (int) \Voxel\get('settings.notifications.admin_user');

        foreach ($recipients_config as $recipient_config) {

            if (!$recipient_config['enabled']) {
                continue;
            }
            // Skip Voxel's default admin to avoid duplicate notifications
            if ((int) $recipient_config['id'] === $voxel_default_admin) {
                continue;
            }

            $recipient = \Voxel\User::get($recipient_config['id']);
            if (!$recipient) {
                continue;
            }

            // Send email/in-app notification
            $this->send_custom_email_notification($event, 'admin', $recipient);

            // Send SMS notification if enabled
            $this->send_sms_notification_to_recipient($event, $recipient_config['id']);
        }

    }

    /**
     * Send SMS notification to a recipient
     *
     * @param \Voxel\Events\Base_Event $event Event instance
     * @param int $user_id User ID
     */
    private function send_sms_notification_to_recipient($event, $user_id) {
        // Check if SMS Notifications is available
        if (!class_exists('Voxel_Toolkit_SMS_Notifications')) {
            return;
        }

        $sms = Voxel_Toolkit_SMS_Notifications::instance();

        if (!$sms) {
            return;
        }

        // Send SMS for the admin destination
        $sms->send_event_sms_to_user($event, $user_id, 'admin');
    }
    
    /**
     * Get notification recipients from configured settings
     */
    private function get_notification_recipients() {
        $function_settings = $this->settings->get_function_settings('admin_notifications', array());
        
        $user_roles = $function_settings['user_roles'] ?? [];
        $user_accounts = $function_settings['selected_users'] ?? [];
        
        
        // Debug: Show all available WordPress roles
        global $wp_roles;
        $all_roles = $wp_roles->get_names();

        $user_ids = [];
        
        // Get users from roles
        if (!empty($user_roles)) {
            foreach($user_roles as $role) {
                $users = get_users(array('role' => $role));
                
                if (empty($users)) {
                    // Try with role__in for better compatibility
                    $users = get_users(array('role__in' => array($role)));
                }
                
                foreach ($users as $user) {
                    $user_ids[] = $user->ID;
                }
            }
        }

        // Add individual users (cast to int to ensure type consistency)
        if (!empty($user_accounts)) {
            foreach ($user_accounts as $user_id) {
                $user_ids[] = (int) $user_id;
            }
        }

        $user_ids = array_unique($user_ids, SORT_NUMERIC);

        // Convert to array of config objects for processing
        $recipients = [];
        foreach ($user_ids as $user_id) {
            $recipients[] = [
                'enabled' => true,
                'id' => (int) $user_id
            ];
        }
        
        return $recipients;
    }
    
    /**
     * Test method to see if any Voxel events are firing
     */
    public function test_event_firing($event) {
        if (is_object($event) && method_exists($event, 'get_key')) {
        }
    }
    
    /**
     * Send custom email notification to recipient
     * Handles email template processing and delivery
     */
    private function send_custom_email_notification($event, $destination, $recipient) {
        $emails = [];
        $email = null;

        if (!$recipient instanceof \Voxel\User) {
            if (isset($recipient['id']) || isset($recipient['email'])) {
                if (isset($recipient['id'])) {
                    $id = $recipient['id'];
                    $user = get_user_by('id', $id);
                } else {
                    $email = $recipient['email'];
                    $user = get_user_by('email', $email);
                }

                if (!$user) {
                    // Create a new instance of WP_User
                    $user = new \WP_User();

                    // Set only the email and display name
                    $user->ID = 0; // ID 0 indicates that the user is not in the database
                    $user->user_email = $email;
                    $user->display_name = $email;
                }
                $recipient = \Voxel\User::get($user);
            } else {
                return;
            }
        }

        $event->recipient = $recipient;
        $notifications = $event->get_notifications();

        $des_notification = $notifications[$destination] ?? null;
        
        // If no admin notification exists, try to use any available notification template
        if (!$des_notification && !empty($notifications)) {
            // Use the first available notification as a fallback
            $available_destinations = array_keys($notifications);
            $fallback_destination = $available_destinations[0];
            $des_notification = $notifications[$fallback_destination];
        }
        
        if (!$des_notification) {
            return;
        }

        if ($recipient->get_id() !== 0) {
            if ($des_notification['inapp']['enabled']) {
                $event->_inapp_sent_cache[$destination] = \Voxel\Notification::create([
                    'user_id' => $recipient->get_id(),
                    'type' => $event->get_key(),
                    'details' => array_merge(
                        $des_notification['inapp']['details']($event),
                        ['destination' => $destination]
                    ),
                ]);

                $recipient->update_notification_count();
            }
        }

        if ($des_notification['email']['enabled']) {
            $subject = \Voxel\render(
                $des_notification['email']['subject'] ?: $des_notification['email']['default_subject'],
                $event->get_dynamic_tags()
            );
            $message = \Voxel\render(
                $des_notification['email']['message'] ?: $des_notification['email']['default_message'],
                $event->get_dynamic_tags()
            );

            $emails[] = [
                'recipient' => $email ?? $recipient->get_email(),
                'subject' => $subject,
                'message' => $message,
                'headers' => [
                    'Content-type: text/html;',
                ],
            ];
        }

        if (!empty($emails)) {
            // Try Voxel's async email queue first, fallback to wp_mail if not available
            if (class_exists('\Voxel\Queues\Async_Email')) {
                \Voxel\Queues\Async_Email::instance()->data(['emails' => $emails])->dispatch();
            } else {
                // Fallback to standard wp_mail for each email
                foreach ($emails as $email_data) {
                    wp_mail(
                        $email_data['recipient'],
                        $email_data['subject'], 
                        $email_data['message'],
                        $email_data['headers']
                    );
                }
            }
        }
    }
}