<?php
/**
 * Profile Completion Percentage Dynamic Tag Method
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Profile Completion Method for User/Author Groups
 */
class Voxel_Toolkit_Profile_Completion_Method extends \Voxel\Dynamic_Data\Modifiers\Group_Methods\Base_Group_Method {

    /**
     * Get method label
     */
    public function get_label(): string {
        return 'Profile completion %';
    }

    /**
     * Get method key
     */
    public function get_key(): string {
        return 'profile_completion';
    }

    /**
     * Define method arguments
     */
    protected function define_args(): void {
        $this->define_arg([
            'type' => 'text',
            'label' => 'Field keys (comma-separated)',
            'description' => 'Enter profile field keys separated by commas (e.g., description,email,location,gallery)',
        ]);
    }

    /**
     * Run the method
     */
    public function run($group) {
        // Get user ID from the group
        $user_id = null;
        if (isset($group->user) && method_exists($group->user, 'get_id')) {
            $user_id = $group->user->get_id();
        }

        if (!$user_id) {
            return 0;
        }

        // Get field keys from argument (comma-separated string)
        $fields_string = $this->get_arg(0);
        $field_keys = [];

        if (!empty($fields_string)) {
            // Split by comma and trim whitespace
            $field_keys = array_map('trim', explode(',', $fields_string));
            // Remove empty values
            $field_keys = array_filter($field_keys);
        }

        // If no fields specified, return 0
        if (empty($field_keys)) {
            return 0;
        }

        // Use the Profile Progress Widget method to get field data
        if (class_exists('Voxel_Toolkit_Profile_Progress_Widget')) {
            $field_data = Voxel_Toolkit_Profile_Progress_Widget::get_user_profile_fields($user_id, $field_keys);
            $percentage = Voxel_Toolkit_Profile_Progress_Widget::calculate_progress($field_data);
            return $percentage;
        }

        return 0;
    }
}
