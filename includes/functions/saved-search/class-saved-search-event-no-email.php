<?php
/**
 * Saved Search Event (No Email) Class
 *
 * Modified event class that dispatches in-app and SMS notifications
 * but disables email (for use with email batching).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Saved_Search_Event_No_Email extends Voxel_Toolkit_Saved_Search_Event {

    /**
     * Define notifications (with email disabled)
     */
    public static function notifications(): array {
        $notifications = parent::notifications();

        // Disable email notification
        if (isset($notifications['notify-subscriber']['email'])) {
            $notifications['notify-subscriber']['email']['enabled'] = false;
        }

        return $notifications;
    }

    /**
     * Force email disabled after Voxel applies stored admin config.
     *
     * Voxel's Base_Event::get_notifications() overwrites the static
     * notifications() defaults with admin-stored config from the database,
     * which re-enables email. This override runs after that merge,
     * ensuring email stays disabled when batching handles it instead.
     */
    public function get_notifications(): array {
        $notifications = parent::get_notifications();

        foreach ($notifications as $destination => &$notification) {
            if (isset($notification['email'])) {
                $notification['email']['enabled'] = false;
            }
        }

        return $notifications;
    }
}
