<?php
/**
 * Membership Expiration Date Dynamic Property
 *
 * Retrieves and formats the user's membership expiration date from Voxel plan meta
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Membership Expiration Property for User/Author Groups
 */
class Voxel_Toolkit_Membership_Expiration_Property {

    /**
     * Register the property
     */
    public static function register() {
        return [
            'type' => 'string',
            'label' => 'Membership expiration date',
            'callback' => function($user_group) {
                return self::get_expiration_date($user_group);
            },
        ];
    }

    /**
     * Get membership expiration date
     *
     * @param object $user_group The user group object
     * @return string Formatted expiration date or empty string
     */
    private static function get_expiration_date($user_group) {
        // Get user ID from the group
        $user_id = null;
        if (isset($user_group->user) && method_exists($user_group->user, 'get_id')) {
            $user_id = $user_group->user->get_id();
        } elseif (isset($user_group->user) && $user_group->user instanceof \WP_User) {
            $user_id = $user_group->user->ID;
        }

        if (!$user_id) {
            return '';
        }

        // Get the voxel:plan meta
        $plan_meta = get_user_meta($user_id, 'voxel:plan', true);

        if (empty($plan_meta)) {
            return '';
        }

        // Decode JSON if it's a string
        if (is_string($plan_meta)) {
            $plan_data = json_decode($plan_meta, true);
        } else {
            $plan_data = $plan_meta;
        }

        // Validate plan data structure
        if (!is_array($plan_data) || !isset($plan_data['billing']['current_period']['end'])) {
            return '';
        }

        // Get the end date
        $end_date = $plan_data['billing']['current_period']['end'];

        if (empty($end_date)) {
            return '';
        }

        // Parse the date
        try {
            $timestamp = strtotime($end_date);
            if ($timestamp === false) {
                return '';
            }

            // Format according to WordPress date settings
            $date_format = get_option('date_format');
            $formatted_date = date_i18n($date_format, $timestamp);

            return $formatted_date;
        } catch (Exception $e) {
            return '';
        }
    }
}
