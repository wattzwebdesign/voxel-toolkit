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
}
