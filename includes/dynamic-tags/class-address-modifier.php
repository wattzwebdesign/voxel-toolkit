<?php
/**
 * Address Part Modifier
 *
 * Extract specific parts from a Voxel address field
 * Supports Google Maps, Mapbox, and OpenStreetMap providers
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only define the class if the parent class exists (Voxel theme is loaded)
if (!class_exists('\Voxel\Dynamic_Data\Modifiers\Base_Modifier')) {
    return;
}

/**
 * Address Part Modifier
 *
 * Extracts specific components from Voxel address fields.
 * Works with international addresses and multiple map providers.
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

        // Handle array value (Voxel address field data)
        if (is_array($value)) {
            // If we have address_components already, use them directly
            if (!empty($value['address_components'])) {
                return $this->extract_address_part($value['address_components'], $part);
            }

            // If we have coordinates, try reverse geocoding
            if (!empty($value['latitude']) && !empty($value['longitude'])) {
                $address_components = $this->get_address_components_from_coords(
                    $value['latitude'],
                    $value['longitude']
                );
                if ($address_components) {
                    return $this->extract_address_part($address_components, $part);
                }
            }

            // If we have an address string in the array, use that
            if (!empty($value['address'])) {
                $value = $value['address'];
            } else {
                return '';
            }
        }

        // Value should be the formatted address string
        if (!is_string($value) || empty($value)) {
            return '';
        }

        // Get address components from the formatted address string
        $address_components = $this->get_address_components_from_address($value);

        if ($address_components) {
            $result = $this->extract_address_part($address_components, $part);
            if (!empty($result)) {
                return $result;
            }
        }

        // Fallback: try to parse the address string directly (useful when geocoding fails)
        return $this->parse_address_string_fallback($value, $part);
    }

    /**
     * Fallback parser for when geocoding fails
     * Attempts to extract address parts directly from the formatted string
     *
     * @param string $address Formatted address string
     * @param string $part Which part to extract
     * @return string Extracted part or empty string
     */
    private function parse_address_string_fallback($address, $part) {
        // Split address by commas
        $parts = array_map('trim', explode(',', $address));

        if (empty($parts)) {
            return '';
        }

        switch ($part) {
            case 'country':
                // Country is typically the last part
                $last_part = end($parts);
                // Check if it looks like a country (not a postcode)
                if (!preg_match('/\d/', $last_part) || strlen($last_part) > 20) {
                    return $last_part;
                }
                return '';

            case 'postal_code':
                // Look for postal code patterns
                foreach ($parts as $p) {
                    // UK postcode pattern: AA9A 9AA, A9A 9AA, A9 9AA, A99 9AA, AA9 9AA, AA99 9AA
                    if (preg_match('/\b([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})\b/i', $p, $matches)) {
                        return strtoupper($matches[1]);
                    }
                    // US ZIP code pattern
                    if (preg_match('/\b(\d{5}(?:-\d{4})?)\b/', $p, $matches)) {
                        return $matches[1];
                    }
                    // Generic postal code (digits)
                    if (preg_match('/\b(\d{4,6})\b/', $p, $matches)) {
                        return $matches[1];
                    }
                }
                return '';

            case 'city':
                // For UK addresses: city is usually before the postcode
                // Pattern: "City POSTCODE" or "City, POSTCODE"
                foreach ($parts as $p) {
                    // Check if this part contains a UK postcode
                    if (preg_match('/^(.+?)\s+([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})$/i', $p, $matches)) {
                        return trim($matches[1]);
                    }
                }
                // If no postcode pattern found, city is often the second-to-last part
                if (count($parts) >= 2) {
                    $candidate = $parts[count($parts) - 2];
                    // Make sure it's not a street (doesn't start with a number)
                    if (!preg_match('/^\d/', $candidate)) {
                        // Remove any trailing postcode
                        $candidate = preg_replace('/\s+[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}$/i', '', $candidate);
                        return trim($candidate);
                    }
                }
                // Try the last part if it looks like a city
                if (count($parts) >= 1) {
                    $last = end($parts);
                    // Extract city from "City POSTCODE" format
                    if (preg_match('/^([A-Za-z\s]+?)(?:\s+[A-Z]{1,2}\d|$)/i', $last, $matches)) {
                        $city = trim($matches[1]);
                        if (!empty($city) && !preg_match('/^\d/', $city)) {
                            return $city;
                        }
                    }
                }
                return '';

            case 'street':
                // Street is typically the first part (may include number)
                if (!empty($parts[0])) {
                    $street = $parts[0];
                    // Remove leading street number
                    $street = preg_replace('/^\d+\s*/', '', $street);
                    return trim($street);
                }
                return '';

            case 'number':
                // Street number is at the beginning
                if (!empty($parts[0]) && preg_match('/^(\d+[A-Za-z]?)\s/', $parts[0], $matches)) {
                    return $matches[1];
                }
                return '';

            case 'state':
                // State/province is harder to extract without geocoding
                // For US addresses, it's typically a 2-letter code before ZIP
                foreach ($parts as $p) {
                    if (preg_match('/\b([A-Z]{2})\s+\d{5}\b/', $p, $matches)) {
                        return $matches[1];
                    }
                }
                return '';

            default:
                return '';
        }
    }

    /**
     * Get the active map provider from Voxel settings
     *
     * @return string Provider key: 'google_maps', 'mapbox', or 'openstreetmap'
     */
    private function get_map_provider() {
        return \Voxel\get('settings.maps.provider') ?: 'google_maps';
    }

    /**
     * Get the geocoding provider for OpenStreetMap
     *
     * @return string Geocoding provider: 'nominatim', 'google_maps', or 'mapbox'
     */
    private function get_osm_geocoding_provider() {
        return \Voxel\get('settings.maps.openstreetmap.geocoding_provider') ?: 'nominatim';
    }

    /**
     * Get address components from formatted address string
     * Routes to the appropriate geocoding API based on map provider settings
     *
     * @param string $address Formatted address string
     * @return array|null Address components (normalized to Google format) or null on failure
     */
    private function get_address_components_from_address($address) {
        // Create cache key (includes provider to avoid conflicts when switching)
        $provider = $this->get_map_provider();
        $cache_key = 'voxel_toolkit_geocode_addr_' . md5($address . '_' . $provider);

        // Check cache first (24 hour expiration)
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Route to appropriate geocoding service
        $components = null;

        switch ($provider) {
            case 'mapbox':
                $components = $this->geocode_with_mapbox($address);
                break;

            case 'openstreetmap':
                $osm_provider = $this->get_osm_geocoding_provider();
                switch ($osm_provider) {
                    case 'google_maps':
                        $components = $this->geocode_with_google($address);
                        break;
                    case 'mapbox':
                        $components = $this->geocode_with_mapbox($address);
                        break;
                    case 'nominatim':
                    default:
                        $components = $this->geocode_with_nominatim($address);
                        break;
                }
                break;

            case 'google_maps':
            default:
                $components = $this->geocode_with_google($address);
                break;
        }

        if ($components) {
            // Cache for 24 hours
            set_transient($cache_key, $components, DAY_IN_SECONDS);
        }

        return $components;
    }

    /**
     * Geocode address using Google Maps API
     *
     * @param string $address Address to geocode
     * @return array|null Normalized address components or null on failure
     */
    private function geocode_with_google($address) {
        $api_key = \Voxel\get('settings.maps.google_maps.api_key');

        if (empty($api_key)) {
            return null;
        }

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

        // Google format is already our target format
        return $data['results'][0]['address_components'];
    }

    /**
     * Geocode address using Mapbox API
     *
     * @param string $address Address to geocode
     * @return array|null Normalized address components (Google format) or null on failure
     */
    private function geocode_with_mapbox($address) {
        $api_key = \Voxel\get('settings.maps.mapbox.api_key');

        if (empty($api_key)) {
            return null;
        }

        $url = sprintf(
            'https://api.mapbox.com/geocoding/v5/mapbox.places/%s.json?access_token=%s&types=address,place,region,postcode,country',
            urlencode($address),
            $api_key
        );

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['features'][0])) {
            return null;
        }

        // Normalize Mapbox response to Google format
        return $this->normalize_mapbox_response($data['features'][0]);
    }

    /**
     * Geocode address using Nominatim (OpenStreetMap)
     *
     * @param string $address Address to geocode
     * @return array|null Normalized address components (Google format) or null on failure
     */
    private function geocode_with_nominatim($address) {
        $url = sprintf(
            'https://nominatim.openstreetmap.org/search?q=%s&format=json&addressdetails=1&limit=1',
            urlencode($address)
        );

        // Nominatim requires a User-Agent header
        $response = wp_remote_get($url, [
            'headers' => [
                'User-Agent' => 'VoxelToolkit/1.0 (WordPress Plugin)',
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data[0]['address'])) {
            return null;
        }

        // Normalize Nominatim response to Google format
        return $this->normalize_nominatim_response($data[0]['address']);
    }

    /**
     * Normalize Mapbox response to Google address_components format
     *
     * Mapbox returns data in a different structure with 'context' array
     * and the main feature containing the most specific match
     *
     * @param array $feature Mapbox feature object
     * @return array Normalized address components in Google format
     */
    private function normalize_mapbox_response($feature) {
        $components = [];

        // Extract from main feature text and place_type
        if (!empty($feature['text']) && !empty($feature['place_type'])) {
            $type = $feature['place_type'][0];
            $google_type = $this->mapbox_type_to_google($type);
            if ($google_type) {
                $components[] = [
                    'long_name' => $feature['text'],
                    'short_name' => $feature['text'],
                    'types' => [$google_type],
                ];
            }
        }

        // Extract address number if present
        if (!empty($feature['address'])) {
            $components[] = [
                'long_name' => $feature['address'],
                'short_name' => $feature['address'],
                'types' => ['street_number'],
            ];
        }

        // Extract from context array (contains place hierarchy)
        if (!empty($feature['context']) && is_array($feature['context'])) {
            foreach ($feature['context'] as $context_item) {
                if (empty($context_item['id']) || empty($context_item['text'])) {
                    continue;
                }

                // Extract type from id (e.g., "place.123456" -> "place")
                $id_parts = explode('.', $context_item['id']);
                $type = $id_parts[0];
                $google_type = $this->mapbox_type_to_google($type);

                if ($google_type) {
                    $short_name = isset($context_item['short_code'])
                        ? strtoupper($context_item['short_code'])
                        : $context_item['text'];

                    $components[] = [
                        'long_name' => $context_item['text'],
                        'short_name' => $short_name,
                        'types' => [$google_type],
                    ];
                }
            }
        }

        return $components;
    }

    /**
     * Map Mapbox place types to Google address component types
     *
     * @param string $mapbox_type Mapbox place type
     * @return string|null Google type or null if no mapping
     */
    private function mapbox_type_to_google($mapbox_type) {
        $mapping = [
            'address' => 'route',
            'place' => 'locality',
            'locality' => 'locality',
            'neighborhood' => 'neighborhood',
            'postcode' => 'postal_code',
            'district' => 'administrative_area_level_2',
            'region' => 'administrative_area_level_1',
            'country' => 'country',
        ];

        return isset($mapping[$mapbox_type]) ? $mapping[$mapbox_type] : null;
    }

    /**
     * Normalize Nominatim response to Google address_components format
     *
     * Nominatim returns a flat 'address' object with named keys
     *
     * @param array $address Nominatim address object
     * @return array Normalized address components in Google format
     */
    private function normalize_nominatim_response($address) {
        $components = [];

        // Street number
        if (!empty($address['house_number'])) {
            $components[] = [
                'long_name' => $address['house_number'],
                'short_name' => $address['house_number'],
                'types' => ['street_number'],
            ];
        }

        // Street name (route)
        if (!empty($address['road'])) {
            $components[] = [
                'long_name' => $address['road'],
                'short_name' => $address['road'],
                'types' => ['route'],
            ];
        }

        // City/locality - Nominatim uses various keys
        $city = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? null;
        if (!empty($city)) {
            $components[] = [
                'long_name' => $city,
                'short_name' => $city,
                'types' => ['locality'],
            ];
        }

        // Postal town (UK specific)
        if (!empty($address['postal_town'])) {
            $components[] = [
                'long_name' => $address['postal_town'],
                'short_name' => $address['postal_town'],
                'types' => ['postal_town'],
            ];
        }

        // County/administrative_area_level_2
        if (!empty($address['county'])) {
            $components[] = [
                'long_name' => $address['county'],
                'short_name' => $address['county'],
                'types' => ['administrative_area_level_2'],
            ];
        }

        // State/administrative_area_level_1
        $state = $address['state'] ?? $address['province'] ?? $address['region'] ?? null;
        if (!empty($state)) {
            $components[] = [
                'long_name' => $state,
                'short_name' => $state,
                'types' => ['administrative_area_level_1'],
            ];
        }

        // Postal code
        if (!empty($address['postcode'])) {
            $components[] = [
                'long_name' => $address['postcode'],
                'short_name' => $address['postcode'],
                'types' => ['postal_code'],
            ];
        }

        // Country
        if (!empty($address['country'])) {
            $country_code = $address['country_code'] ?? '';
            $components[] = [
                'long_name' => $address['country'],
                'short_name' => strtoupper($country_code),
                'types' => ['country'],
            ];
        }

        return $components;
    }

    /**
     * Get address components from coordinates using geocoding API
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return array|null Address components or null on failure
     */
    private function get_address_components_from_coords($lat, $lng) {
        // Create cache key
        $provider = $this->get_map_provider();
        $cache_key = 'voxel_toolkit_geocode_' . md5($lat . '_' . $lng . '_' . $provider);

        // Check cache first (24 hour expiration)
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $components = null;

        switch ($provider) {
            case 'mapbox':
                $components = $this->reverse_geocode_with_mapbox($lat, $lng);
                break;

            case 'openstreetmap':
                $osm_provider = $this->get_osm_geocoding_provider();
                switch ($osm_provider) {
                    case 'google_maps':
                        $components = $this->reverse_geocode_with_google($lat, $lng);
                        break;
                    case 'mapbox':
                        $components = $this->reverse_geocode_with_mapbox($lat, $lng);
                        break;
                    case 'nominatim':
                    default:
                        $components = $this->reverse_geocode_with_nominatim($lat, $lng);
                        break;
                }
                break;

            case 'google_maps':
            default:
                $components = $this->reverse_geocode_with_google($lat, $lng);
                break;
        }

        if ($components) {
            // Cache for 24 hours
            set_transient($cache_key, $components, DAY_IN_SECONDS);
        }

        return $components;
    }

    /**
     * Reverse geocode coordinates using Google Maps API
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return array|null Normalized address components or null on failure
     */
    private function reverse_geocode_with_google($lat, $lng) {
        $api_key = \Voxel\get('settings.maps.google_maps.api_key');

        if (empty($api_key)) {
            return null;
        }

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

        return $data['results'][0]['address_components'];
    }

    /**
     * Reverse geocode coordinates using Mapbox API
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return array|null Normalized address components or null on failure
     */
    private function reverse_geocode_with_mapbox($lat, $lng) {
        $api_key = \Voxel\get('settings.maps.mapbox.api_key');

        if (empty($api_key)) {
            return null;
        }

        $url = sprintf(
            'https://api.mapbox.com/geocoding/v5/mapbox.places/%s,%s.json?access_token=%s&types=address,place,region,postcode,country',
            $lng,
            $lat,
            $api_key
        );

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['features'][0])) {
            return null;
        }

        return $this->normalize_mapbox_response($data['features'][0]);
    }

    /**
     * Reverse geocode coordinates using Nominatim
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return array|null Normalized address components or null on failure
     */
    private function reverse_geocode_with_nominatim($lat, $lng) {
        $url = sprintf(
            'https://nominatim.openstreetmap.org/reverse?lat=%s&lon=%s&format=json&addressdetails=1',
            $lat,
            $lng
        );

        $response = wp_remote_get($url, [
            'headers' => [
                'User-Agent' => 'VoxelToolkit/1.0 (WordPress Plugin)',
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['address'])) {
            return null;
        }

        return $this->normalize_nominatim_response($data['address']);
    }

    /**
     * Extract specific part from address data
     *
     * @param array $address_data Normalized address components (Google format)
     * @param string $part Which part to extract
     * @return string Extracted address component
     */
    private function extract_address_part($address_data, $part) {
        switch ($part) {
            case 'number':
                return $this->get_component($address_data, 'street_number');

            case 'street':
                return $this->get_component($address_data, 'route');

            case 'city':
                // Try locality first (main city name)
                $city = $this->get_component($address_data, 'locality');
                if (empty($city)) {
                    $city = $this->get_component($address_data, 'postal_town'); // UK specific
                }
                if (empty($city)) {
                    $city = $this->get_component($address_data, 'sublocality'); // District/neighborhood
                }
                if (empty($city)) {
                    $city = $this->get_component($address_data, 'sublocality_level_1');
                }
                if (empty($city)) {
                    $city = $this->get_component($address_data, 'administrative_area_level_3'); // Municipality (gmina)
                }
                if (empty($city)) {
                    $city = $this->get_component($address_data, 'administrative_area_level_2'); // County (last resort)
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
     * @param array $address_data Address components array (normalized to Google format)
     * @param string $component Component type to extract
     * @return string Component value
     */
    private function get_component($address_data, $component) {
        // The address_data is the address_components array
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
