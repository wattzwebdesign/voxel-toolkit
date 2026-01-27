<?php
/**
 * Address Part Method
 *
 * Extract specific parts from a Voxel address field with language support
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
 * Address Part Method
 *
 * Extracts specific components from Voxel address fields.
 * Supports localization by re-geocoding with the current site language.
 */
class Voxel_Toolkit_Address_Part_Method extends \Voxel\Dynamic_Data\Modifiers\Group_Methods\Base_Group_Method {

    /**
     * Cache for localized address data
     * @var array
     */
    private static $localized_cache = [];

    /**
     * Get method label
     */
    public function get_label(): string {
        return 'Address part';
    }

    /**
     * Get method key
     */
    public function get_key(): string {
        return 'address_part';
    }

    /**
     * Define method arguments
     */
    protected function define_args(): void {
        $this->define_arg([
            'type' => 'select',
            'label' => 'Address component',
            'description' => 'Select which part of the address to extract',
            'options' => [
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
     * Run the method
     */
    public function run($group) {

        // Get the part to extract from arguments
        $part = isset($this->args[0]) ? $this->args[0] : 'city';
        if (is_array($part) && isset($part['content'])) {
            $part = $part['content'];
        }


        // Try to get the value directly from the group
        $value = null;

        // Check if this is a property group with a value
        if (isset($group->_property)) {
            $property = $group->_property;
            if (method_exists($property, 'get_value')) {
                $value = $property->get_value();
            }
        }

        // Check if group has a get_value method
        if ($value === null && method_exists($group, 'get_value')) {
            $value = $group->get_value();
        }


        if (!is_array($value)) {
            return '';
        }

        // Get localized address data
        $localized_data = $this->get_localized_address_data($value);

        // Extract the requested part from localized data
        $result = $this->extract_address_part($localized_data, $part);
        return $result;
    }

    /**
     * Get localized address data by re-geocoding with current language
     *
     * @param array $address_data Original address data
     * @return array Localized address data (or original if localization fails)
     */
    private function get_localized_address_data($address_data) {
        // Get latitude and longitude
        $lat = isset($address_data['latitude']) ? $address_data['latitude'] : null;
        $lng = isset($address_data['longitude']) ? $address_data['longitude'] : null;

        // If no coordinates, return original data
        if (empty($lat) || empty($lng)) {
            return $address_data;
        }

        // Get current language
        $language = $this->get_current_language();

        // Create cache key
        $cache_key = md5($lat . ',' . $lng . ',' . $language);

        // Check memory cache first
        if (isset(self::$localized_cache[$cache_key])) {
            return self::$localized_cache[$cache_key];
        }

        // Check transient cache (persists across requests)
        $transient_key = 'vt_addr_' . $cache_key;
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            self::$localized_cache[$cache_key] = $cached;
            return $cached;
        }

        // Perform reverse geocoding with language
        $localized = $this->reverse_geocode_localized($lat, $lng, $language);

        if ($localized) {
            // Merge with original data (keep coordinates, update address components)
            $result = array_merge($address_data, ['address_components' => $localized]);

            // Cache the result
            self::$localized_cache[$cache_key] = $result;
            set_transient($transient_key, $result, DAY_IN_SECONDS); // Cache for 24 hours

            return $result;
        }

        // Fall back to original data
        return $address_data;
    }

    /**
     * Get current site/user language code
     *
     * @return string Language code (e.g., 'pl', 'en', 'de')
     */
    private function get_current_language() {
        // Check for WPML
        if (defined('ICL_LANGUAGE_CODE')) {
            return ICL_LANGUAGE_CODE;
        }

        // Check for Polylang
        if (function_exists('pll_current_language')) {
            $lang = pll_current_language('slug');
            if ($lang) {
                return $lang;
            }
        }

        // Check for TranslatePress
        global $TRP_LANGUAGE;
        if (!empty($TRP_LANGUAGE)) {
            // TranslatePress uses full locale, extract language code
            return substr($TRP_LANGUAGE, 0, 2);
        }

        // Fall back to WordPress locale
        $locale = get_locale();

        // Extract language code from locale (e.g., 'pl_PL' -> 'pl')
        $parts = explode('_', $locale);
        return $parts[0];
    }

    /**
     * Reverse geocode with language parameter
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param string $language Language code
     * @return array|null Address components or null on failure
     */
    private function reverse_geocode_localized($lat, $lng, $language) {
        // Try Google Maps first
        $result = $this->reverse_geocode_google($lat, $lng, $language);
        if ($result) {
            return $result;
        }

        // Try Mapbox as fallback
        $result = $this->reverse_geocode_mapbox($lat, $lng, $language);
        if ($result) {
            return $result;
        }

        return null;
    }

    /**
     * Reverse geocode using Google Maps API with language
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param string $language Language code
     * @return array|null Address components or null on failure
     */
    private function reverse_geocode_google($lat, $lng, $language) {
        $api_key = \Voxel\get('settings.maps.google_maps.api_key');

        if (empty($api_key)) {
            return null;
        }

        $url = sprintf(
            'https://maps.googleapis.com/maps/api/geocode/json?latlng=%s,%s&language=%s&key=%s',
            $lat,
            $lng,
            $language,
            $api_key
        );

        $response = wp_remote_get($url, [
            'timeout' => 5,
            'sslverify' => true,
        ]);

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
     * Reverse geocode using Mapbox API with language
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param string $language Language code
     * @return array|null Address components in Google format or null on failure
     */
    private function reverse_geocode_mapbox($lat, $lng, $language) {
        $api_key = \Voxel\get('settings.maps.mapbox.api_key');

        if (empty($api_key)) {
            return null;
        }

        $url = sprintf(
            'https://api.mapbox.com/geocoding/v5/mapbox.places/%s,%s.json?access_token=%s&language=%s&types=address,place,region,postcode,country',
            $lng,
            $lat,
            $api_key,
            $language
        );

        $response = wp_remote_get($url, [
            'timeout' => 5,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['features'])) {
            return null;
        }

        // Convert Mapbox format to Google format
        return $this->mapbox_to_google_format($data['features']);
    }

    /**
     * Convert Mapbox response to Google address_components format
     *
     * @param array $features Mapbox features array
     * @return array Address components in Google format
     */
    private function mapbox_to_google_format($features) {
        $components = [];

        foreach ($features as $feature) {
            if (empty($feature['place_type']) || empty($feature['text'])) {
                continue;
            }

            $type = $feature['place_type'][0];
            $value = $feature['text'];

            // Map Mapbox types to Google types
            $type_mapping = [
                'address' => 'route',
                'place' => 'locality',
                'region' => 'administrative_area_level_1',
                'postcode' => 'postal_code',
                'country' => 'country',
            ];

            if (isset($type_mapping[$type])) {
                $components[] = [
                    'long_name' => $value,
                    'short_name' => isset($feature['short_code']) ? strtoupper($feature['short_code']) : $value,
                    'types' => [$type_mapping[$type]],
                ];
            }

            // Extract street number from address if present
            if ($type === 'address' && !empty($feature['address'])) {
                $components[] = [
                    'long_name' => $feature['address'],
                    'short_name' => $feature['address'],
                    'types' => ['street_number'],
                ];
            }
        }

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

            default:
                return '';
        }
    }

    /**
     * Get component from address data
     *
     * @param array $address_data Address data array
     * @param string $component Component type to extract
     * @return string Component value
     */
    private function get_component($address_data, $component) {
        // Check if we have the raw Google Places format
        if (isset($address_data['address_components']) && is_array($address_data['address_components'])) {
            foreach ($address_data['address_components'] as $comp) {
                if (isset($comp['types']) && in_array($component, $comp['types'])) {
                    // Return long_name for better readability
                    return isset($comp['long_name']) ? $comp['long_name'] : '';
                }
            }
        }

        // Check if Voxel has already parsed it into a simpler format
        if (isset($address_data[$component])) {
            return $address_data[$component];
        }

        return '';
    }
}
