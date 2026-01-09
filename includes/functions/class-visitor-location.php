<?php
/**
 * Visitor Location functionality for Voxel Toolkit
 *
 * Provides IP geolocation to detect visitor's city, state, and country
 * Uses Voxel's existing IP geolocation providers
 *
 * @package Voxel_Toolkit
 * @since 1.5.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Visitor_Location {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cache duration in seconds (default: 1 hour)
     */
    private $cache_duration = 3600;

    /**
     * Detection mode: 'ip' or 'browser'
     */
    private $detection_mode = 'ip';

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enqueue frontend JavaScript if browser mode is enabled
        if ($this->detection_mode === 'browser') {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 999);
        }
    }

    /**
     * Load settings from WordPress options
     */
    private function load_settings() {
        if (!class_exists('Voxel_Toolkit_Settings')) {
            return;
        }

        $settings = Voxel_Toolkit_Settings::instance();
        $function_settings = $settings->get_function_settings('visitor_location');

        // Load cache duration (default: 3600 seconds = 1 hour)
        if (isset($function_settings['visitor_location_cache_duration'])) {
            $this->cache_duration = absint($function_settings['visitor_location_cache_duration']);
        }

        // Load detection mode (default: 'ip')
        if (isset($function_settings['visitor_location_mode'])) {
            $this->detection_mode = sanitize_text_field($function_settings['visitor_location_mode']);
        }
    }

    /**
     * Enqueue frontend JavaScript for browser geolocation
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'voxel-toolkit-visitor-location',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/visitor-location.js',
            array(),
            '1.0.0',
            true
        );

        wp_localize_script('voxel-toolkit-visitor-location', 'voxelToolkitLocation', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_visitor_location'),
        ));
    }

    /**
     * Get full location string (City, State, Country)
     *
     * @return string Full location or empty string
     */
    public function get_location() {
        $geo_data = $this->get_geo_data();

        if (!$geo_data) {
            return '';
        }

        $city = isset($geo_data['city']) ? $geo_data['city'] : '';
        $state = isset($geo_data['state']) ? $geo_data['state'] : '';
        $country = isset($geo_data['country']) ? $geo_data['country'] : '';

        // Build location parts
        $parts = array();

        if ($city) {
            $parts[] = $city;
        }

        if ($state) {
            $parts[] = $state;
        }

        if ($country) {
            $parts[] = $country;
        }

        return implode(', ', $parts);
    }

    /**
     * Get city only
     *
     * @return string City name or empty string
     */
    public function get_city() {
        $geo_data = $this->get_geo_data();

        if (!$geo_data || empty($geo_data['city'])) {
            return '';
        }

        return $geo_data['city'];
    }

    /**
     * Get state only (US) or region
     *
     * @return string State/region name or empty string
     */
    public function get_state() {
        $geo_data = $this->get_geo_data();

        if (!$geo_data || empty($geo_data['state'])) {
            return '';
        }

        return $geo_data['state'];
    }

    /**
     * Get country only
     *
     * @return string Country name or empty string
     */
    public function get_country() {
        $geo_data = $this->get_geo_data();

        if (!$geo_data || empty($geo_data['country'])) {
            return '';
        }

        return $geo_data['country'];
    }

    /**
     * Get latitude
     *
     * @return string Latitude or empty string
     */
    public function get_latitude() {
        $geo_data = $this->get_geo_data();

        if (!$geo_data || !isset($geo_data['latitude']) || $geo_data['latitude'] === '') {
            return '';
        }

        return (string) $geo_data['latitude'];
    }

    /**
     * Get longitude
     *
     * @return string Longitude or empty string
     */
    public function get_longitude() {
        $geo_data = $this->get_geo_data();

        if (!$geo_data || !isset($geo_data['longitude']) || $geo_data['longitude'] === '') {
            return '';
        }

        return (string) $geo_data['longitude'];
    }

    /**
     * Get geolocation data for current visitor
     * Uses caching to reduce API calls
     *
     * @return array|false Geo data array or false on failure
     */
    private function get_geo_data() {
        // If browser mode is enabled, check cookie first
        if ($this->detection_mode === 'browser') {
            $cookie_data = $this->get_location_from_cookie();
            if ($cookie_data) {
                return $cookie_data;
            }
            // Fall through to IP geolocation if no cookie (user denied or hasn't been prompted yet)
        }

        // Get visitor IP using Voxel's Visitor class
        if (!class_exists('\Voxel\Visitor')) {
            return false;
        }

        $visitor = \Voxel\Visitor::get();
        $ip = $visitor->get_ip();

        if (!$ip || $ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
            // Local development - return false
            return false;
        }

        // Check cache first
        $cache_key = 'vt_visitor_location_' . md5($ip);
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        // Fetch from API
        $geo_data = $this->fetch_from_api($ip);

        if ($geo_data) {
            // Cache the result
            set_transient($cache_key, $geo_data, $this->cache_duration);
        }

        return $geo_data;
    }

    /**
     * Get location from browser geolocation cookie
     *
     * @return array|false Location data or false
     */
    private function get_location_from_cookie() {
        if (!isset($_COOKIE['vt_visitor_location'])) {
            return false;
        }

        $location_data = json_decode(stripslashes($_COOKIE['vt_visitor_location']), true);

        if (!$location_data || !isset($location_data['city'])) {
            return false;
        }

        return $location_data;
    }

    /**
     * Fetch geolocation data from multiple free services and pick best result
     *
     * @param string $ip IP address to look up
     * @return array|false Geo data array or false on failure
     */
    private function fetch_from_api($ip) {
        $results = [];

        // Service 1: geojs.io
        $geojs_data = $this->query_geojs($ip);
        if ($geojs_data) {
            $results[] = $geojs_data;
        }

        // Service 2: ipapi.co
        $ipapi_data = $this->query_ipapi($ip);
        if ($ipapi_data) {
            $results[] = $ipapi_data;
        }

        // Service 3: ip-api.com
        $ipapi_com_data = $this->query_ipapi_com($ip);
        if ($ipapi_com_data) {
            $results[] = $ipapi_com_data;
        }

        // If no results, return false
        if (empty($results)) {
            return false;
        }

        // Pick the best result based on consensus and data quality
        return $this->pick_best_result($results);
    }

    /**
     * Query geojs.io service
     *
     * @param string $ip IP address
     * @return array|false Geo data or false
     */
    private function query_geojs($ip) {
        $url = 'https://get.geojs.io/v1/ip/geo/' . $ip . '.json';

        $response = wp_remote_get($url, [
            'timeout' => 3,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!$data || empty($data['city'])) {
            return false;
        }

        return [
            'city' => sanitize_text_field($data['city']),
            'state' => isset($data['region']) ? sanitize_text_field($data['region']) : '',
            'country' => isset($data['country']) ? sanitize_text_field($data['country']) : '',
            'country_code' => isset($data['country_code']) ? strtoupper(sanitize_text_field($data['country_code'])) : '',
            'latitude' => isset($data['latitude']) ? floatval($data['latitude']) : '',
            'longitude' => isset($data['longitude']) ? floatval($data['longitude']) : '',
            'source' => 'geojs.io',
        ];
    }

    /**
     * Query ipapi.co service
     *
     * @param string $ip IP address
     * @return array|false Geo data or false
     */
    private function query_ipapi($ip) {
        $url = 'https://ipapi.co/' . $ip . '/json/';

        $response = wp_remote_get($url, [
            'timeout' => 3,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!$data || empty($data['city'])) {
            return false;
        }

        return [
            'city' => sanitize_text_field($data['city']),
            'state' => isset($data['region']) ? sanitize_text_field($data['region']) : '',
            'country' => isset($data['country_name']) ? sanitize_text_field($data['country_name']) : '',
            'country_code' => isset($data['country_code']) ? strtoupper(sanitize_text_field($data['country_code'])) : '',
            'latitude' => isset($data['latitude']) ? floatval($data['latitude']) : '',
            'longitude' => isset($data['longitude']) ? floatval($data['longitude']) : '',
            'source' => 'ipapi.co',
        ];
    }

    /**
     * Query ip-api.com service
     *
     * @param string $ip IP address
     * @return array|false Geo data or false
     */
    private function query_ipapi_com($ip) {
        $url = 'http://ip-api.com/json/' . $ip;

        $response = wp_remote_get($url, [
            'timeout' => 3,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!$data || $data['status'] !== 'success' || empty($data['city'])) {
            return false;
        }

        return [
            'city' => sanitize_text_field($data['city']),
            'state' => isset($data['regionName']) ? sanitize_text_field($data['regionName']) : '',
            'country' => isset($data['country']) ? sanitize_text_field($data['country']) : '',
            'country_code' => isset($data['countryCode']) ? strtoupper(sanitize_text_field($data['countryCode'])) : '',
            'latitude' => isset($data['lat']) ? floatval($data['lat']) : '',
            'longitude' => isset($data['lon']) ? floatval($data['lon']) : '',
            'source' => 'ip-api.com',
        ];
    }

    /**
     * Pick the best result from multiple sources
     * Prioritizes consensus and data completeness
     *
     * @param array $results Array of geo data results
     * @return array Best result
     */
    private function pick_best_result($results) {
        if (count($results) === 1) {
            return $results[0];
        }

        // Count city occurrences to find consensus
        $city_counts = [];
        foreach ($results as $result) {
            $city = $result['city'];
            if (!isset($city_counts[$city])) {
                $city_counts[$city] = ['count' => 0, 'data' => $result];
            }
            $city_counts[$city]['count']++;
        }

        // Sort by count (consensus)
        uasort($city_counts, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        // Get the most common city
        $best = reset($city_counts)['data'];

        // If there's a tie, pick the one with most complete data
        $max_count = reset($city_counts)['count'];
        $tied_results = array_filter($city_counts, function($item) use ($max_count) {
            return $item['count'] === $max_count;
        });

        if (count($tied_results) > 1) {
            $completeness_scores = [];
            foreach ($tied_results as $city => $item) {
                $data = $item['data'];
                $score = 0;
                if (!empty($data['city'])) $score++;
                if (!empty($data['state'])) $score++;
                if (!empty($data['country'])) $score++;
                if (!empty($data['country_code'])) $score++;
                $completeness_scores[$city] = ['score' => $score, 'data' => $data];
            }

            uasort($completeness_scores, function($a, $b) {
                return $b['score'] - $a['score'];
            });

            $best = reset($completeness_scores)['data'];
        }

        return $best;
    }

    /**
     * Clear cached location data for current visitor
     * Useful for testing or when IP changes
     */
    public function clear_cache() {
        if (!class_exists('\Voxel\Visitor')) {
            return;
        }

        $visitor = \Voxel\Visitor::get();
        $ip = $visitor->get_ip();

        if ($ip) {
            $cache_key = 'vt_visitor_location_' . md5($ip);
            delete_transient($cache_key);
        }
    }
}
