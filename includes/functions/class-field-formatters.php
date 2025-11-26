<?php
/**
 * Field Formatters - Shared utility class for formatting field values for display
 *
 * @package Voxel_Toolkit
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Field_Formatters {

    /**
     * Format work hours schedule for display
     *
     * @param array $schedule Work hours schedule array
     * @return string Formatted HTML string
     */
    public static function format_work_hours_display($schedule) {
        if (empty($schedule)) {
            return __('No hours set', 'voxel-toolkit');
        }

        $day_names = array(
            'mon' => __('Mon', 'voxel-toolkit'),
            'tue' => __('Tue', 'voxel-toolkit'),
            'wed' => __('Wed', 'voxel-toolkit'),
            'thu' => __('Thu', 'voxel-toolkit'),
            'fri' => __('Fri', 'voxel-toolkit'),
            'sat' => __('Sat', 'voxel-toolkit'),
            'sun' => __('Sun', 'voxel-toolkit'),
        );

        $formatted_lines = array();

        foreach ($schedule as $group) {
            $days = isset($group['days']) ? $group['days'] : array();
            $status = isset($group['status']) ? $group['status'] : 'closed';
            $hours = isset($group['hours']) ? $group['hours'] : array();

            if (empty($days)) {
                continue;
            }

            // Format days list
            $day_labels = array();
            foreach ($days as $day) {
                if (isset($day_names[$day])) {
                    $day_labels[] = $day_names[$day];
                }
            }
            $days_text = implode(', ', $day_labels);

            // Format status/hours
            if ($status === 'closed') {
                $status_text = __('Closed', 'voxel-toolkit');
            } elseif ($status === 'open') {
                $status_text = __('Open 24 hours', 'voxel-toolkit');
            } elseif ($status === 'appointments_only') {
                $status_text = __('By appointment only', 'voxel-toolkit');
            } elseif ($status === 'hours' && !empty($hours)) {
                $time_ranges = array();
                foreach ($hours as $time_slot) {
                    if (isset($time_slot['from']) && isset($time_slot['to'])) {
                        $time_ranges[] = $time_slot['from'] . ' - ' . $time_slot['to'];
                    }
                }
                $status_text = implode(', ', $time_ranges);
            } else {
                $status_text = __('No hours', 'voxel-toolkit');
            }

            $formatted_lines[] = '<strong>' . esc_html($days_text) . ':</strong> ' . esc_html($status_text);
        }

        return implode('<br>', $formatted_lines);
    }

    /**
     * Format location data for display (address only)
     *
     * @param mixed $location Location data (JSON string or array)
     * @return string Formatted address
     */
    public static function format_location_display($location) {
        // If it's a JSON string, decode it
        if (is_string($location)) {
            $location = json_decode($location, true);
        }

        // If it's an array with an address key, return the address
        if (is_array($location) && isset($location['address'])) {
            return esc_html($location['address']);
        }

        // Fallback: return as-is if it's already a plain string
        if (is_string($location)) {
            return esc_html($location);
        }

        return __('No location set', 'voxel-toolkit');
    }
}
