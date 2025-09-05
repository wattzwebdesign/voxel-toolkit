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

class Voxel_Toolkit_Admin_Notifications extends \Voxel\Controllers\Base_Controller {
    
    private $settings;
    private $enabled = false;

    /**
     * Constructor
     */
    public function __construct() {
        error_log('VT Admin Notifications: Constructor called');
        
        $this->settings = Voxel_Toolkit_Settings::instance();
        $this->load_settings();
        
        error_log('VT Admin Notifications: Enabled = ' . ($this->enabled ? 'true' : 'false'));
        
        // Force test the recipients config
        $test_recipients = $this->get_notification_recipients();
        error_log('VT Admin Notifications: Test recipients in constructor: ' . print_r($test_recipients, true));
        
        if ($this->enabled) {
            error_log('VT Admin Notifications: Calling parent::__construct()');
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
        
        error_log('VT Admin Notifications: Raw function settings: ' . print_r($function_settings, true));
        
        $this->enabled = isset($function_settings['enabled']) ? $function_settings['enabled'] : false;
        
        error_log('VT Admin Notifications: Final enabled status: ' . ($this->enabled ? 'true' : 'false'));
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
        error_log('VT Admin Notifications: hooks() method called');
        
        $events = \Voxel\Events\Base_Event::get_all();
        error_log('VT Admin Notifications: Found ' . count($events) . ' events');
        
        foreach ($events as $event) {
            $hook_name = sprintf('voxel/app-events/%s', $event->get_key());
            $this->on($hook_name, '@send_multiple_admins_notifications', 10, 1);
            error_log('VT Admin Notifications: Registered hook for ' . $hook_name);
        }
    }

    /**
     * Send notifications to multiple admin users
     * Processes all configured recipients for admin notifications
     */
    public function send_multiple_admins_notifications($event) {
        error_log('VT Admin Notifications: send_multiple_admins_notifications called for event: ' . $event->get_key());
        
        
        $recipients_config = $this->get_notification_recipients();
        error_log('VT Admin Notifications: Recipients config: ' . print_r($recipients_config, true));
        
        $voxel_default_admin = \Voxel\get('settings.notifications.admin_user');
        error_log('VT Admin Notifications: Voxel default admin ID: ' . $voxel_default_admin);

        foreach ($recipients_config as $recipient_config) {
            error_log('VT Admin Notifications: Processing recipient: ' . print_r($recipient_config, true));
            
            if (!$recipient_config['enabled']) {
                error_log('VT Admin Notifications: Recipient disabled, skipping');
                continue;
            }
            if ($recipient_config['id'] === $voxel_default_admin) {
                error_log('VT Admin Notifications: Recipient is default admin, skipping to avoid duplicate');
                continue;
            }
            
            $recipient = \Voxel\User::get($recipient_config['id']);
            if (!$recipient) {
                error_log('VT Admin Notifications: Could not get Voxel user for ID: ' . $recipient_config['id']);
                continue;
            }
            
            error_log('VT Admin Notifications: Sending notification to: ' . $recipient->get_email());
            $this->send_custom_email_notification($event, 'admin', $recipient);
        }
        
        error_log('VT Admin Notifications: Finished processing all recipients');
    }
    
    /**
     * Get notification recipients from configured settings
     */
    private function get_notification_recipients() {
        $function_settings = $this->settings->get_function_settings('admin_notifications', array());
        error_log('VT Admin Notifications: Function settings: ' . print_r($function_settings, true));
        
        $user_roles = $function_settings['user_roles'] ?? [];
        $user_accounts = $function_settings['selected_users'] ?? [];
        
        error_log('VT Admin Notifications: User roles from settings: ' . print_r($user_roles, true));
        error_log('VT Admin Notifications: Selected users from settings: ' . print_r($user_accounts, true));
        
        // Debug: Show all available WordPress roles
        global $wp_roles;
        $all_roles = $wp_roles->get_names();
        error_log('VT Admin Notifications: All available WordPress roles: ' . print_r($all_roles, true));

        $user_ids = [];
        
        // Get users from roles
        if (!empty($user_roles)) {
            foreach($user_roles as $role) {
                error_log('VT Admin Notifications: Processing role: ' . $role);
                $users = get_users(array('role' => $role));
                error_log('VT Admin Notifications: Found ' . count($users) . ' users for role: ' . $role);
                
                if (empty($users)) {
                    // Try with role__in for better compatibility
                    $users = get_users(array('role__in' => array($role)));
                    error_log('VT Admin Notifications: Retry with role__in found ' . count($users) . ' users for role: ' . $role);
                }
                
                foreach ($users as $user) {
                    $user_ids[] = $user->ID;
                    error_log('VT Admin Notifications: Added user from role - ID: ' . $user->ID . ', Email: ' . $user->user_email . ', Role: ' . $role);
                }
            }
        }

        // Add individual users
        if (!empty($user_accounts)) {
            foreach ($user_accounts as $user_id) {
                $user_ids[] = $user_id;
                $user = get_userdata($user_id);
                if ($user) {
                    error_log('VT Admin Notifications: Added individual user - ID: ' . $user_id . ', Email: ' . $user->user_email);
                }
            }
        }
        
        $user_ids = array_unique($user_ids);
        error_log('VT Admin Notifications: Final user IDs: ' . print_r($user_ids, true));
        
        // Convert to array of config objects for processing
        $recipients = [];
        foreach ($user_ids as $user_id) {
            $recipients[] = [
                'enabled' => true,
                'id' => $user_id
            ];
        }
        
        error_log('VT Admin Notifications: Final recipients config: ' . print_r($recipients, true));
        return $recipients;
    }
    
    /**
     * Test method to see if any Voxel events are firing
     */
    public function test_event_firing($event) {
        error_log('VT Admin Notifications: TEST EVENT FIRED! Event: ' . (is_object($event) ? get_class($event) : print_r($event, true)));
        if (is_object($event) && method_exists($event, 'get_key')) {
            error_log('VT Admin Notifications: Event key: ' . $event->get_key());
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
            error_log('VT Admin Notifications: No admin notification found for event ' . $event->get_key() . ', using fallback template: ' . $fallback_destination);
        }
        
        if (!$des_notification) {
            error_log('VT Admin Notifications: No notification template found for event: ' . $event->get_key());
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