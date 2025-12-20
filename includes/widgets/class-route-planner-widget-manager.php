<?php
/**
 * Route Planner Widget Manager
 *
 * Manages the Route Planner functionality including:
 * - Elementor widget registration
 * - Asset enqueuing with map provider dependencies
 * - AJAX handlers for waypoint data retrieval
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Route_Planner_Widget_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register Elementor widget
        add_action('elementor/widgets/register', array($this, 'register_elementor_widgets'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // AJAX handlers for getting waypoint data
        add_action('wp_ajax_vt_get_route_waypoints', array($this, 'ajax_get_waypoints'));
        add_action('wp_ajax_nopriv_vt_get_route_waypoints', array($this, 'ajax_get_waypoints'));
    }

    /**
     * Register Elementor widgets
     *
     * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager
     */
    public function register_elementor_widgets($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-route-planner-widget.php';

        $widgets_manager->register(new \Voxel_Toolkit_Route_Planner_Widget());
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_assets() {
        // Only enqueue if Voxel is available
        if (!function_exists('\\Voxel\\get')) {
            return;
        }

        // Get map provider
        $map_provider = \Voxel\get('settings.maps.provider', 'google_maps');

        // Register styles
        wp_register_style(
            'voxel-toolkit-route-planner',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/route-planner.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        // Register script with jQuery dependency only (map scripts loaded via Voxel)
        wp_register_script(
            'voxel-toolkit-route-planner',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/route-planner.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Localize script with configuration
        wp_localize_script('voxel-toolkit-route-planner', 'voxelRoutePlanner', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_route_planner'),
            'mapProvider' => $map_provider,
            'googleMapsKey' => \Voxel\get('settings.maps.google_maps.api_key', ''),
            'mapboxKey' => \Voxel\get('settings.maps.mapbox.api_key', ''),
            'i18n' => array(
                'drive' => __('Drive', 'voxel-toolkit'),
                'walk' => __('Walk', 'voxel-toolkit'),
                'cycle' => __('Cycle', 'voxel-toolkit'),
                'transit' => __('Transit', 'voxel-toolkit'),
                'yourLocation' => __('Your Location', 'voxel-toolkit'),
                'start' => __('Start', 'voxel-toolkit'),
                'end' => __('End', 'voxel-toolkit'),
                'view' => __('View', 'voxel-toolkit'),
                'noWaypoints' => __('No waypoints configured', 'voxel-toolkit'),
                'routeFailed' => __('Could not calculate route', 'voxel-toolkit'),
                'locationDenied' => __('Location access denied', 'voxel-toolkit'),
                'loading' => __('Calculating route...', 'voxel-toolkit'),
            ),
        ));
    }

    /**
     * Enqueue map scripts when widget is rendered
     * Called from the widget's render method
     */
    public static function enqueue_map_scripts() {
        // Use Voxel's enqueue_maps function to load the correct map provider scripts
        if (function_exists('\\Voxel\\enqueue_maps')) {
            \Voxel\enqueue_maps();
        }

        // Enqueue our widget scripts
        wp_enqueue_style('voxel-toolkit-route-planner');
        wp_enqueue_script('voxel-toolkit-route-planner');
    }

    /**
     * AJAX handler for getting route waypoints
     */
    public function ajax_get_waypoints() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vt_route_planner')) {
            wp_send_json_error(array('message' => __('Security check failed', 'voxel-toolkit')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $data_source = isset($_POST['data_source']) ? sanitize_text_field($_POST['data_source']) : '';

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'voxel-toolkit')));
        }

        // Get the Voxel post
        if (!class_exists('\Voxel\Post')) {
            wp_send_json_error(array('message' => __('Voxel not available', 'voxel-toolkit')));
        }

        $post = \Voxel\Post::get($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found', 'voxel-toolkit')));
        }

        $waypoints = array();

        if ($data_source === 'repeater') {
            $repeater_key = isset($_POST['repeater_key']) ? sanitize_text_field($_POST['repeater_key']) : '';
            $location_key = isset($_POST['location_key']) ? sanitize_text_field($_POST['location_key']) : '';
            $label_key = isset($_POST['label_key']) ? sanitize_text_field($_POST['label_key']) : '';

            $waypoints = $this->get_waypoints_from_repeater($post, $repeater_key, $location_key, $label_key);
        } elseif ($data_source === 'post_relation') {
            $relation_key = isset($_POST['relation_key']) ? sanitize_text_field($_POST['relation_key']) : '';
            $location_key = isset($_POST['location_key']) ? sanitize_text_field($_POST['location_key']) : '';

            $waypoints = $this->get_waypoints_from_relations($post, $relation_key, $location_key);
        } elseif ($data_source === 'post_fields') {
            $post_fields_list = isset($_POST['post_fields_list']) ? $_POST['post_fields_list'] : array();

            $waypoints = $this->get_waypoints_from_post_fields($post, $post_fields_list);
        }

        wp_send_json_success(array('waypoints' => $waypoints));
    }

    /**
     * Extract waypoints from a repeater field
     *
     * @param \Voxel\Post $post Voxel post object
     * @param string $repeater_key Repeater field key
     * @param string $location_key Location sub-field key within repeater
     * @param string $label_key Optional label sub-field key within repeater
     * @return array Array of waypoint data
     */
    private function get_waypoints_from_repeater($post, $repeater_key, $location_key, $label_key = '') {
        if (empty($repeater_key) || empty($location_key)) {
            return array();
        }

        $field = $post->get_field($repeater_key);
        if (!$field) {
            return array();
        }

        $rows = $field->get_value();
        if (!is_array($rows)) {
            return array();
        }

        $waypoints = array();

        foreach ($rows as $index => $row) {
            if (!isset($row[$location_key]) || !is_array($row[$location_key])) {
                continue;
            }

            $loc = $row[$location_key];

            // Validate coordinates
            if (empty($loc['latitude']) || empty($loc['longitude'])) {
                continue;
            }

            $lat = floatval($loc['latitude']);
            $lng = floatval($loc['longitude']);

            // Skip invalid coordinates
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }

            // Get label from label field or address
            $label = '';
            if (!empty($label_key) && isset($row[$label_key])) {
                $label = is_string($row[$label_key]) ? $row[$label_key] : '';
            }
            if (empty($label) && !empty($loc['address'])) {
                $label = $loc['address'];
            }
            if (empty($label)) {
                $label = sprintf(__('Stop %d', 'voxel-toolkit'), $index + 1);
            }

            $waypoints[] = array(
                'lat' => $lat,
                'lng' => $lng,
                'address' => isset($loc['address']) ? $loc['address'] : '',
                'label' => $label,
                'index' => $index,
            );
        }

        return $waypoints;
    }

    /**
     * Extract waypoints from a post relation field
     *
     * @param \Voxel\Post $post Voxel post object
     * @param string $relation_key Post relation field key
     * @param string $location_key Location field key in related posts
     * @return array Array of waypoint data
     */
    private function get_waypoints_from_relations($post, $relation_key, $location_key) {
        if (empty($relation_key) || empty($location_key)) {
            return array();
        }

        $field = $post->get_field($relation_key);
        if (!$field) {
            return array();
        }

        $related_ids = $field->get_value();
        if (!is_array($related_ids) || empty($related_ids)) {
            return array();
        }

        $waypoints = array();

        foreach ($related_ids as $index => $related_id) {
            // Handle case where related_id might be an object or array
            if (is_object($related_id) && isset($related_id->id)) {
                $related_id = $related_id->id;
            } elseif (is_array($related_id) && isset($related_id['id'])) {
                $related_id = $related_id['id'];
            }
            $related_id = intval($related_id);

            $related_post = \Voxel\Post::get($related_id);
            if (!$related_post) {
                continue;
            }

            $loc_field = $related_post->get_field($location_key);
            if (!$loc_field) {
                continue;
            }

            $loc = $loc_field->get_value();
            if (!is_array($loc) || empty($loc['latitude']) || empty($loc['longitude'])) {
                continue;
            }

            $lat = floatval($loc['latitude']);
            $lng = floatval($loc['longitude']);

            // Skip invalid coordinates
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }

            $waypoints[] = array(
                'lat' => $lat,
                'lng' => $lng,
                'address' => isset($loc['address']) ? $loc['address'] : '',
                'label' => $related_post->get_title(),
                'post_id' => $related_id,
                'permalink' => $related_post->get_link(),
                'index' => $index,
            );
        }

        return $waypoints;
    }

    /**
     * Extract waypoints from individual post fields
     *
     * @param \Voxel\Post $post Voxel post object
     * @param array $fields_list Array of field definitions with field_key and optional label
     * @return array Array of waypoint data
     */
    private function get_waypoints_from_post_fields($post, $fields_list) {
        if (!is_array($fields_list) || empty($fields_list)) {
            return array();
        }

        $waypoints = array();

        foreach ($fields_list as $index => $field_def) {
            // Sanitize field definition
            $field_key = isset($field_def['field_key']) ? sanitize_text_field($field_def['field_key']) : '';
            $custom_label = isset($field_def['label']) ? sanitize_text_field($field_def['label']) : '';

            if (empty($field_key)) {
                continue;
            }

            $field = $post->get_field($field_key);
            if (!$field) {
                continue;
            }

            $loc = $field->get_value();
            if (!is_array($loc)) {
                continue;
            }

            // Validate coordinates
            if (empty($loc['latitude']) || empty($loc['longitude'])) {
                continue;
            }

            $lat = floatval($loc['latitude']);
            $lng = floatval($loc['longitude']);

            // Skip invalid coordinates
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }

            // Determine label: custom label > address > field key
            $label = '';
            if (!empty($custom_label)) {
                $label = $custom_label;
            } elseif (!empty($loc['address'])) {
                $label = $loc['address'];
            } else {
                $label = sprintf(__('Waypoint %d', 'voxel-toolkit'), $index + 1);
            }

            $waypoints[] = array(
                'lat' => $lat,
                'lng' => $lng,
                'address' => isset($loc['address']) ? $loc['address'] : '',
                'label' => $label,
                'index' => $index,
            );
        }

        return $waypoints;
    }
}
