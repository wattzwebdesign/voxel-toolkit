<?php
/**
 * Address Part Modifier
 *
 * Extract specific parts from a Voxel address field
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Address Part Modifier
 *
 * Extracts specific components from Voxel address fields.
 * Works with international addresses.
 */
class Voxel_Toolkit_Address_Part_Modifier extends \Voxel\Dynamic_Data\Modifiers\Base_Modifier {

    /**
     * Get modifier label
     */
    public function get_label(): string {
        return 'Address part';
    }

    /**
     * Get modifier key
     */
    public function get_key(): string {
        return 'address_part';
    }

    /**
     * Define modifier arguments
     */
    protected function define_args(): void {
        $this->define_arg([
            'type' => 'select',
            'label' => 'Address component',
            'description' => 'Select which part of the address to extract',
            'choices' => [
                'number' => 'Street Number',
                'street' => 'Street Name',
                'city' => 'City',
                'state' => 'State/Province',
                'postal_code' => 'Postal Code/ZIP',
                'country' => 'Country',
            ],
        ]);
    }

    /**
     * Apply the modifier
     */
    public function apply($value) {
        // Get the part to extract from arguments
        $part = isset($this->args[0]) ? $this->args[0] : 'city';

        // Handle if it's an array with content key
        if (is_array($part) && isset($part['content'])) {
            $part = $part['content'];
        }

        // Value should be the formatted address string
        if (!is_string($value) || empty($value)) {
            return '';
        }

        // Get address components from the formatted address string
        $address_components = $this->get_address_components_from_address($value);

        if (!$address_components) {
            return '';
        }

        // Extract the requested part
        return $this->extract_address_part($address_components, $part);
    }

    /**
     * Get address components from formatted address string using Google Geocoding API
     *
     * @param string $address Formatted address string
     * @return array|null Address components or null on failure
     */
    private function get_address_components_from_address($address) {
        // Create cache key
        $cache_key = 'voxel_toolkit_geocode_addr_' . md5($address);

        // Check cache first (24 hour expiration)
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Get Google API key from Voxel settings
        $api_key = \Voxel\get('settings.maps.google_maps.api_key');

        if (empty($api_key)) {
            return null;
        }

        // Call Google Geocoding API with the address
        $url = sprintf(
            'https://maps.googleapis.com/maps/api/geocode/json?address=%s&key=%s',
            urlencode($address),
            $api_key
        );

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['results'][0]['address_components'])) {
            return null;
        }

        $components = $data['results'][0]['address_components'];

        // Cache for 24 hours
        set_transient($cache_key, $components, DAY_IN_SECONDS);

        return $components;
    }

    /**
     * Get address components from coordinates using Google Geocoding API
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return array|null Address components or null on failure
     */
    private function get_address_components_from_coords($lat, $lng) {
        // Create cache key
        $cache_key = 'voxel_toolkit_geocode_' . md5($lat . '_' . $lng);

        // Check cache first (24 hour expiration)
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Get Google API key from Voxel settings
        $api_key = \Voxel\get('settings.maps.google_maps.api_key');

        if (empty($api_key)) {
            return null;
        }

        // Call Google Geocoding API
        $url = sprintf(
            'https://maps.googleapis.com/maps/api/geocode/json?latlng=%s,%s&key=%s',
            $lat,
            $lng,
            $api_key
        );

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['results'][0]['address_components'])) {
            return null;
        }

        $components = $data['results'][0]['address_components'];

        // Cache for 24 hours
        set_transient($cache_key, $components, DAY_IN_SECONDS);

        return $components;
    }

    /**
     * Extract specific part from address data
     *
     * @param array $address_data Voxel address data
     * @param string $part Which part to extract
     * @return string Extracted address component
     */
    private function extract_address_part($address_data, $part) {
        // Voxel uses Google Places API format
        // Address components are stored in 'address' key

        switch ($part) {
            case 'number':
                return $this->get_component($address_data, 'street_number');

            case 'street':
                return $this->get_component($address_data, 'route');

            case 'city':
                // Try locality first, then postal_town, then administrative_area_level_2
                $city = $this->get_component($address_data, 'locality');
                if (empty($city)) {
                    $city = $this->get_component($address_data, 'postal_town');
                }
                if (empty($city)) {
                    $city = $this->get_component($address_data, 'administrative_area_level_2');
                }
                return $city;

            case 'state':
                // Try administrative_area_level_1 (works for most countries)
                return $this->get_component($address_data, 'administrative_area_level_1');

            case 'postal_code':
                return $this->get_component($address_data, 'postal_code');

            case 'country':
                return $this->get_component($address_data, 'country');

            case 'formatted':
            default:
                // Return the formatted address if available
                if (isset($address_data['address'])) {
                    return $address_data['address'];
                }
                return '';
        }
    }

    /**
     * Get component from address data
     *
     * @param array $address_data Address components array (already extracted from API)
     * @param string $component Component type to extract
     * @return string Component value
     */
    private function get_component($address_data, $component) {
        // The address_data is already the address_components array from the API
        // It's a numerically indexed array of component objects
        if (is_array($address_data)) {
            foreach ($address_data as $comp) {
                if (isset($comp['types']) && in_array($component, $comp['types'])) {
                    // Return long_name for better readability
                    return isset($comp['long_name']) ? $comp['long_name'] : '';
                }
            }
        }

        return '';
    }
}
