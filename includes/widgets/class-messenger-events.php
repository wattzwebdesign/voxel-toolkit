<?php
/**
 * Messenger Events Handler
 *
 * Handles Voxel messaging events for real-time updates
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Messenger_Events {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Disabled: Unthrottled event fires for every message causing performance issues
        // Real-time updates are handled by JavaScript polling (30-second intervals)
        // Uncomment below to re-enable for future WebSocket/real-time enhancements
        // add_action('voxel/app-events/messages/user:received_message_unthrottled', array($this, 'handle_message_received'), 10, 1);
    }

    /**
     * Handle message received event
     *
     * @param array $event Event data from Voxel
     */
    public function handle_message_received($event) {
        // This event fires when a user receives a new message
        // We can use this to trigger client-side notifications or updates

        // For now, we'll just ensure the event is logged for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('VT Messenger: New message received - ' . print_r($event, true));
        }

        // The actual real-time update happens via polling in JavaScript
        // This hook is here for future enhancements like WebSocket support
        // or server-sent events for true real-time messaging
    }
}

// Initialize
new Voxel_Toolkit_Messenger_Events();
