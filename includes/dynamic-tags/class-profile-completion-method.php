<?php
/**
 * Profile Completion Percentage Dynamic Tag Method
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only define the class if the parent class exists (Voxel theme is loaded)
if (!class_exists('\Voxel\Dynamic_Data\Modifiers\Group_Methods\Base_Group_Method')) {
    return;
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

        // Get the raw argument value before evaluation
        // Access the protected $args property directly
        $fields_string = isset($this->args[0]['content']) ? $this->args[0]['content'] : '';
        $field_keys = [];

        if (!empty($fields_string)) {
            // Extract field keys from Voxel dynamic tag syntax
            // Supports: @user(profile.field_name) or just field_name
            preg_match_all('/@user\(profile\.([a-zA-Z0-9_-]+)(?:\.[a-zA-Z0-9_-]+)?\)|([a-zA-Z0-9_-]+)/', $fields_string, $matches);

            // Combine matches from both patterns
            $field_keys = array_merge(
                array_filter($matches[1]), // Fields from @user(profile.xxx)
                array_filter($matches[2])  // Plain field names
            );

            // Remove duplicates and empty values
            $field_keys = array_unique(array_filter($field_keys));
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
