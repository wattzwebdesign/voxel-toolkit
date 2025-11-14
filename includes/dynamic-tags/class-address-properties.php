<?php
/**
 * Address Field Properties
 *
 * Extract specific parts from a Voxel address field
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register address field sub-properties
 */
class Voxel_Toolkit_Address_Properties {

    /**
     * Register address sub-properties
     */
    public static function register($properties, $group) {
        error_log('Address Properties: Registering address sub-properties');

        // We need to add sub-properties to location fields
        // This will be called when Voxel builds the property list

        return $properties;
    }

    /**
     * Get a specific component from address data
     */
    public static function get_component($address_data, $component) {
        error_log('Address Properties: get_component called with component = ' . $component);
        error_log('Address Properties: address_data = ' . print_r($address_data, true));

        if (!is_array($address_data)) {
            return '';
        }

        // Check if we have the raw Google Places format
        if (isset($address_data['address_components']) && is_array($address_data['address_components'])) {
            foreach ($address_data['address_components'] as $comp) {
                if (isset($comp['types']) && in_array($component, $comp['types'])) {
                    return isset($comp['long_name']) ? $comp['long_name'] : '';
                }
            }
        }

        // Check if Voxel has already parsed it
        if (isset($address_data[$component])) {
            return $address_data[$component];
        }

        return '';
    }
}
