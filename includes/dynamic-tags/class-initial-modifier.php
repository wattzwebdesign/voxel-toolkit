<?php
/**
 * Initial with dot Modifier
 *
 * Returns the first letter of a string followed by a period.
 * Example: "John" → "J."
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\Voxel\Dynamic_Data\Modifiers\Base_Modifier')) {
    return;
}

class Voxel_Toolkit_Initial_Modifier extends \Voxel\Dynamic_Data\Modifiers\Base_Modifier {

    public function get_label(): string {
        return 'Initial with dot';
    }

    public function get_key(): string {
        return 'initial';
    }

    public function get_description(): string {
        return 'Returns the first letter followed by a period (e.g., "John" → "J.")';
    }

    protected function define_args(): void {
        // No arguments needed
    }

    public function apply($value) {
        $value = trim($value);

        if (empty($value)) {
            return '';
        }

        // Get first letter (multibyte safe for international characters)
        $first_letter = mb_substr($value, 0, 1, 'UTF-8');

        return $first_letter . '.';
    }
}
