<?php
/**
 * File Size and Extension Modifiers
 *
 * Get file size and extension from file ID
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only define the classes if the parent class exists (Voxel theme is loaded)
if (!class_exists('\Voxel\Dynamic_Data\Modifiers\Base_Modifier')) {
    return;
}

/**
 * File Size Modifier
 */
class Voxel_Toolkit_File_Size_Modifier extends \Voxel\Dynamic_Data\Modifiers\Base_Modifier {

    /**
     * Get modifier label
     */
    public function get_label(): string {
        return 'File size';
    }

    /**
     * Get modifier key
     */
    public function get_key(): string {
        return 'file_size';
    }

    /**
     * Apply the modifier
     */
    public function apply($value) {
        $file_id = $value;

        if (!$file_id || !is_numeric($file_id)) {
            return '';
        }

        $file_id = intval($file_id);

        // Get file path from attachment ID
        $file_path = get_attached_file($file_id);

        if (!$file_path || !file_exists($file_path)) {
            return '';
        }

        return $this->get_file_size($file_path);
    }

    /**
     * Get formatted file size
     *
     * @param string $file_path Path to file
     * @return string Formatted file size (e.g., "2.5 MB")
     */
    private function get_file_size($file_path) {
        $size_bytes = filesize($file_path);

        if ($size_bytes === false) {
            return '';
        }

        // Convert to appropriate unit
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit_index = 0;
        $size = $size_bytes;

        while ($size >= 1024 && $unit_index < count($units) - 1) {
            $size /= 1024;
            $unit_index++;
        }

        // Format with appropriate decimal places
        if ($unit_index === 0) {
            // Bytes - no decimals
            return number_format($size, 0) . ' ' . $units[$unit_index];
        } else {
            // KB, MB, GB, TB - 2 decimal places
            return number_format($size, 2) . ' ' . $units[$unit_index];
        }
    }
}

/**
 * File Extension Modifier
 */
class Voxel_Toolkit_File_Extension_Modifier extends \Voxel\Dynamic_Data\Modifiers\Base_Modifier {

    /**
     * Get modifier label
     */
    public function get_label(): string {
        return 'File extension';
    }

    /**
     * Get modifier key
     */
    public function get_key(): string {
        return 'file_extension';
    }

    /**
     * Apply the modifier
     */
    public function apply($value) {
        $file_id = $value;

        if (!$file_id || !is_numeric($file_id)) {
            return '';
        }

        $file_id = intval($file_id);

        // Get file path from attachment ID
        $file_path = get_attached_file($file_id);

        if (!$file_path || !file_exists($file_path)) {
            return '';
        }

        return $this->get_file_extension($file_path);
    }

    /**
     * Get file extension
     *
     * @param string $file_path Path to file
     * @return string File extension (e.g., "zip", "png")
     */
    private function get_file_extension($file_path) {
        $extension = pathinfo($file_path, PATHINFO_EXTENSION);
        return strtolower($extension);
    }
}
