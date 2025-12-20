<?php
/**
 * Route Planner Widget
 *
 * Elementor widget for displaying interactive routes with turn-by-turn directions
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Route_Planner_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name
     *
     * @return string Widget name
     */
    public function get_name() {
        return 'voxel-toolkit-route-planner';
    }

    /**
     * Get widget title
     *
     * @return string Widget title
     */
    public function get_title() {
        return __('Route Planner (VT)', 'voxel-toolkit');
    }

    /**
     * Get widget icon
     *
     * @return string Widget icon
     */
    public function get_icon() {
        return 'eicon-google-maps';
    }

    /**
     * Get widget categories
     *
     * @return array Widget categories
     */
    public function get_categories() {
        return array('voxel-toolkit');
    }

    /**
     * Get widget keywords
     *
     * @return array Widget keywords
     */
    public function get_keywords() {
        return array('route', 'map', 'directions', 'navigation', 'planner', 'waypoint', 'itinerary');
    }

    /**
     * Get script dependencies
     *
     * @return array Script dependencies
     */
    public function get_script_depends() {
        return array('voxel-toolkit-route-planner');
    }

    /**
     * Get style dependencies
     *
     * @return array Style dependencies
     */
    public function get_style_depends() {
        return array('voxel-toolkit-route-planner');
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    /**
     * Register content controls
     */
    private function register_content_controls() {
        // Data Source Section
        $this->start_controls_section(
            'section_data_source',
            array(
                'label' => __('Data Source', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'data_source',
            array(
                'label' => __('Data Source', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'repeater',
                'options' => array(
                    'repeater' => __('Repeater Field', 'voxel-toolkit'),
                    'post_relation' => __('Post Relation Field', 'voxel-toolkit'),
                    'post_fields' => __('Post Fields', 'voxel-toolkit'),
                ),
            )
        );

        // Repeater field controls
        $this->add_control(
            'repeater_field_key',
            array(
                'label' => __('Repeater Field Key', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'stops',
                'description' => __('The key of the repeater field containing route stops', 'voxel-toolkit'),
                'condition' => array('data_source' => 'repeater'),
            )
        );

        $this->add_control(
            'repeater_location_key',
            array(
                'label' => __('Location Sub-field Key', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'location',
                'description' => __('The key of the location field within each repeater row', 'voxel-toolkit'),
                'condition' => array('data_source' => 'repeater'),
            )
        );

        $this->add_control(
            'repeater_label_key',
            array(
                'label' => __('Label Sub-field Key (Optional)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'name',
                'description' => __('Optional field for stop names. Leave empty to use address.', 'voxel-toolkit'),
                'condition' => array('data_source' => 'repeater'),
            )
        );

        // Post relation field controls
        $this->add_control(
            'relation_field_key',
            array(
                'label' => __('Post Relation Field Key', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'venues',
                'description' => __('The key of the post relation field', 'voxel-toolkit'),
                'condition' => array('data_source' => 'post_relation'),
            )
        );

        $this->add_control(
            'relation_location_key',
            array(
                'label' => __('Location Field Key', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'location',
                'description' => __('The location field key in the related posts', 'voxel-toolkit'),
                'condition' => array('data_source' => 'post_relation'),
            )
        );

        // Post fields repeater control
        $post_fields_repeater = new \Elementor\Repeater();

        $post_fields_repeater->add_control(
            'field_key',
            array(
                'label' => __('Location Field Key', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'location',
                'label_block' => true,
            )
        );

        $post_fields_repeater->add_control(
            'label',
            array(
                'label' => __('Label (Optional)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('e.g., Starting Point', 'voxel-toolkit'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'post_fields_list',
            array(
                'label' => __('Location Fields', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => $post_fields_repeater->get_controls(),
                'default' => array(),
                'title_field' => '{{{ field_key || "Field" }}}',
                'condition' => array('data_source' => 'post_fields'),
            )
        );

        $this->end_controls_section();

        // Start Point Section
        $this->start_controls_section(
            'section_start_point',
            array(
                'label' => __('Start Point', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'start_point_mode',
            array(
                'label' => __('Start Point', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'first_stop',
                'options' => array(
                    'first_stop' => __('First Waypoint', 'voxel-toolkit'),
                    'user_location' => __('User GPS Location', 'voxel-toolkit'),
                    'custom' => __('Custom Address', 'voxel-toolkit'),
                ),
            )
        );

        $this->add_control(
            'custom_start_address',
            array(
                'label' => __('Start Address', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('Enter address...', 'voxel-toolkit'),
                'condition' => array('start_point_mode' => 'custom'),
            )
        );

        $this->add_control(
            'custom_start_lat',
            array(
                'label' => __('Latitude', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => -90,
                'max' => 90,
                'step' => 0.000001,
                'condition' => array('start_point_mode' => 'custom'),
            )
        );

        $this->add_control(
            'custom_start_lng',
            array(
                'label' => __('Longitude', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => -180,
                'max' => 180,
                'step' => 0.000001,
                'condition' => array('start_point_mode' => 'custom'),
            )
        );

        $this->end_controls_section();

        // Route Options Section
        $this->start_controls_section(
            'section_route_options',
            array(
                'label' => __('Route Options', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'travel_mode',
            array(
                'label' => __('Default Travel Mode', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'driving',
                'options' => array(
                    'driving' => __('Driving', 'voxel-toolkit'),
                    'walking' => __('Walking', 'voxel-toolkit'),
                    'cycling' => __('Cycling', 'voxel-toolkit'),
                    'transit' => __('Transit', 'voxel-toolkit'),
                ),
            )
        );

        $this->add_control(
            'allow_travel_mode_change',
            array(
                'label' => __('Show Travel Mode Buttons', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'default' => 'yes',
                'description' => __('Allow users to switch between travel modes', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'travel_label_driving',
            array(
                'label' => __('Driving Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Drive', 'voxel-toolkit'),
                'condition' => array('allow_travel_mode_change' => 'yes'),
            )
        );

        $this->add_control(
            'travel_icon_driving',
            array(
                'label' => __('Driving Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => array(
                    'value' => '',
                    'library' => '',
                ),
                'condition' => array('allow_travel_mode_change' => 'yes'),
            )
        );

        $this->add_control(
            'travel_label_walking',
            array(
                'label' => __('Walking Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Walk', 'voxel-toolkit'),
                'condition' => array('allow_travel_mode_change' => 'yes'),
            )
        );

        $this->add_control(
            'travel_icon_walking',
            array(
                'label' => __('Walking Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => array(
                    'value' => '',
                    'library' => '',
                ),
                'condition' => array('allow_travel_mode_change' => 'yes'),
            )
        );

        $this->add_control(
            'travel_label_cycling',
            array(
                'label' => __('Cycling Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Cycle', 'voxel-toolkit'),
                'condition' => array('allow_travel_mode_change' => 'yes'),
            )
        );

        $this->add_control(
            'travel_icon_cycling',
            array(
                'label' => __('Cycling Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => array(
                    'value' => '',
                    'library' => '',
                ),
                'condition' => array('allow_travel_mode_change' => 'yes'),
            )
        );

        $this->add_control(
            'travel_label_transit',
            array(
                'label' => __('Transit Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Transit', 'voxel-toolkit'),
                'condition' => array('allow_travel_mode_change' => 'yes'),
            )
        );

        $this->add_control(
            'travel_icon_transit',
            array(
                'label' => __('Transit Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => array(
                    'value' => '',
                    'library' => '',
                ),
                'condition' => array('allow_travel_mode_change' => 'yes'),
            )
        );

        $this->add_control(
            'optimize_route',
            array(
                'label' => __('Optimize Route Order', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'default' => '',
                'description' => __('Automatically reorder waypoints for the shortest route', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'allow_reorder',
            array(
                'label' => __('Allow Drag to Reorder', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'default' => '',
                'description' => __('Let users drag waypoints to reorder and recalculate the route', 'voxel-toolkit'),
                'condition' => array('optimize_route' => ''),
            )
        );

        $this->end_controls_section();

        // Display Options Section
        $this->start_controls_section(
            'section_display',
            array(
                'label' => __('Display Options', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'show_directions_panel',
            array(
                'label' => __('Show Directions Panel', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'default' => 'yes',
            )
        );

        $this->add_control(
            'directions_panel_position',
            array(
                'label' => __('Directions Panel Position', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'right',
                'options' => array(
                    'left' => __('Left', 'voxel-toolkit'),
                    'right' => __('Right', 'voxel-toolkit'),
                    'bottom' => __('Below Map', 'voxel-toolkit'),
                ),
                'condition' => array('show_directions_panel' => 'yes'),
            )
        );

        $this->add_control(
            'show_turn_by_turn',
            array(
                'label' => __('Show Turn-by-Turn Directions', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'default' => 'yes',
                'description' => __('Hide to show only the waypoints list', 'voxel-toolkit'),
                'condition' => array('show_directions_panel' => 'yes'),
            )
        );

        $this->add_control(
            'show_waypoint_address',
            array(
                'label' => __('Show Address Below Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'default' => '',
                'description' => __('Show the address below the waypoint label in the sidebar', 'voxel-toolkit'),
                'condition' => array('show_directions_panel' => 'yes'),
            )
        );

        $this->add_responsive_control(
            'map_height',
            array(
                'label' => __('Map Height', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', 'vh'),
                'range' => array(
                    'px' => array('min' => 200, 'max' => 1000, 'step' => 10),
                    'vh' => array('min' => 20, 'max' => 100, 'step' => 5),
                ),
                'default' => array('size' => 400, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-map' => 'height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'show_distance',
            array(
                'label' => __('Show Total Distance', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'default' => 'yes',
            )
        );

        $this->add_control(
            'distance_icon',
            array(
                'label' => __('Distance Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => array(
                    'value' => '',
                    'library' => '',
                ),
                'condition' => array('show_distance' => 'yes'),
            )
        );

        $this->add_control(
            'show_duration',
            array(
                'label' => __('Show Estimated Duration', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'default' => 'yes',
            )
        );

        $this->add_control(
            'duration_icon',
            array(
                'label' => __('Duration Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => array(
                    'value' => '',
                    'library' => '',
                ),
                'condition' => array('show_duration' => 'yes'),
            )
        );

        $this->add_control(
            'distance_unit',
            array(
                'label' => __('Distance Unit', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'metric',
                'options' => array(
                    'metric' => __('Kilometers', 'voxel-toolkit'),
                    'imperial' => __('Miles', 'voxel-toolkit'),
                ),
            )
        );

        $this->end_controls_section();

        // Map Options Section
        $this->start_controls_section(
            'section_map_options',
            array(
                'label' => __('Map Options', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'default_zoom',
            array(
                'label' => __('Default Zoom', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 1, 'max' => 20, 'step' => 1)),
                'default' => array('size' => 12),
            )
        );

        $this->add_control(
            'min_zoom',
            array(
                'label' => __('Minimum Zoom', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 1, 'max' => 20, 'step' => 1)),
                'default' => array('size' => 3),
            )
        );

        $this->add_control(
            'max_zoom',
            array(
                'label' => __('Maximum Zoom', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 1, 'max' => 20, 'step' => 1)),
                'default' => array('size' => 18),
            )
        );

        $this->end_controls_section();

        // Export Options Section
        $this->start_controls_section(
            'section_export_options',
            array(
                'label' => __('Export Options', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'show_google_maps_btn',
            array(
                'label' => __('Show Google Maps Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'default' => 'yes',
            )
        );

        $this->add_control(
            'google_maps_label',
            array(
                'label' => __('Google Maps Button Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Open in Google Maps', 'voxel-toolkit'),
                'condition' => array('show_google_maps_btn' => 'yes'),
            )
        );

        $this->add_control(
            'google_maps_icon',
            array(
                'label' => __('Google Maps Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => array(
                    'value' => '',
                    'library' => '',
                ),
                'condition' => array('show_google_maps_btn' => 'yes'),
            )
        );

        $this->add_control(
            'show_apple_maps_btn',
            array(
                'label' => __('Show Apple Maps Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'default' => 'yes',
            )
        );

        $this->add_control(
            'apple_maps_label',
            array(
                'label' => __('Apple Maps Button Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Open in Apple Maps', 'voxel-toolkit'),
                'condition' => array('show_apple_maps_btn' => 'yes'),
            )
        );

        $this->add_control(
            'apple_maps_icon',
            array(
                'label' => __('Apple Maps Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => array(
                    'value' => '',
                    'library' => '',
                ),
                'condition' => array('show_apple_maps_btn' => 'yes'),
            )
        );

        $this->add_control(
            'show_gpx_btn',
            array(
                'label' => __('Show Download GPX Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'default' => 'yes',
            )
        );

        $this->add_control(
            'gpx_label',
            array(
                'label' => __('GPX Button Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Download GPX', 'voxel-toolkit'),
                'condition' => array('show_gpx_btn' => 'yes'),
            )
        );

        $this->add_control(
            'gpx_icon',
            array(
                'label' => __('GPX Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => array(
                    'value' => '',
                    'library' => '',
                ),
                'condition' => array('show_gpx_btn' => 'yes'),
            )
        );

        $this->add_control(
            'gpx_filename',
            array(
                'label' => __('GPX Filename', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'route',
                'description' => __('Filename without .gpx extension', 'voxel-toolkit'),
                'condition' => array('show_gpx_btn' => 'yes'),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Register style controls
     */
    private function register_style_controls() {
        // Route Line Style
        $this->start_controls_section(
            'section_route_line_style',
            array(
                'label' => __('Route Line', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'route_line_color',
            array(
                'label' => __('Line Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#4285F4',
            )
        );

        $this->add_control(
            'route_line_weight',
            array(
                'label' => __('Line Thickness', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 1, 'max' => 10, 'step' => 1)),
                'default' => array('size' => 4),
            )
        );

        $this->add_control(
            'route_line_opacity',
            array(
                'label' => __('Line Opacity', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 0.1, 'max' => 1, 'step' => 0.1)),
                'default' => array('size' => 0.8),
            )
        );

        $this->end_controls_section();

        // Marker Style
        $this->start_controls_section(
            'section_marker_style',
            array(
                'label' => __('Markers', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'marker_style',
            array(
                'label' => __('Marker Style', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'numbered',
                'options' => array(
                    'numbered' => __('Numbered (1, 2, 3...)', 'voxel-toolkit'),
                    'lettered' => __('Lettered (A, B, C...)', 'voxel-toolkit'),
                ),
            )
        );

        $this->add_control(
            'start_marker_color',
            array(
                'label' => __('Start Marker Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#22c55e',
            )
        );

        $this->add_control(
            'waypoint_marker_color',
            array(
                'label' => __('Waypoint Marker Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#3b82f6',
            )
        );

        $this->add_control(
            'end_marker_color',
            array(
                'label' => __('End Marker Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ef4444',
            )
        );

        $this->add_control(
            'marker_size',
            array(
                'label' => __('Marker Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 24, 'max' => 48, 'step' => 2)),
                'default' => array('size' => 32),
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-marker' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'marker_text_color',
            array(
                'label' => __('Marker Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-marker' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'marker_border_color',
            array(
                'label' => __('Marker Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-marker' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'marker_border_width',
            array(
                'label' => __('Marker Border Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 0, 'max' => 6, 'step' => 1)),
                'default' => array('size' => 3),
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-marker' => 'border-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // Waypoint List Style
        $this->start_controls_section(
            'section_waypoint_list_style',
            array(
                'label' => __('Waypoint List', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => array('show_directions_panel' => 'yes'),
            )
        );

        $this->add_control(
            'waypoint_card_bg',
            array(
                'label' => __('Card Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f9fafb',
                'selectors' => array(
                    '{{WRAPPER}} .vt-waypoint-item' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'waypoint_card_hover_bg',
            array(
                'label' => __('Card Hover Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f3f4f6',
                'selectors' => array(
                    '{{WRAPPER}} .vt-waypoint-item:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'waypoint_card_radius',
            array(
                'label' => __('Card Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default' => array('top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-waypoint-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'waypoint_card_padding',
            array(
                'label' => __('Card Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default' => array('top' => 10, 'right' => 12, 'bottom' => 10, 'left' => 12, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-waypoint-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'waypoint_label_color',
            array(
                'label' => __('Label Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#1f2937',
                'selectors' => array(
                    '{{WRAPPER}} .vt-waypoint-label' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'waypoint_label_size',
            array(
                'label' => __('Label Font Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 10, 'max' => 24, 'step' => 1)),
                'default' => array('size' => 14),
                'selectors' => array(
                    '{{WRAPPER}} .vt-waypoint-label' => 'font-size: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'waypoint_address_color',
            array(
                'label' => __('Address Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#6b7280',
                'selectors' => array(
                    '{{WRAPPER}} .vt-waypoint-address' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'waypoint_address_size',
            array(
                'label' => __('Address Font Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 10, 'max' => 18, 'step' => 1)),
                'default' => array('size' => 12),
                'selectors' => array(
                    '{{WRAPPER}} .vt-waypoint-address' => 'font-size: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'waypoint_number_bg',
            array(
                'label' => __('Number Badge Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#3b82f6',
                'selectors' => array(
                    '{{WRAPPER}} .vt-waypoint-marker' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'waypoint_number_color',
            array(
                'label' => __('Number Badge Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-waypoint-marker' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // Direction Steps Style
        $this->start_controls_section(
            'section_steps_style',
            array(
                'label' => __('Direction Steps', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'conditions' => array(
                    'relation' => 'and',
                    'terms' => array(
                        array('name' => 'show_directions_panel', 'value' => 'yes'),
                        array('name' => 'show_turn_by_turn', 'value' => 'yes'),
                    ),
                ),
            )
        );

        $this->add_control(
            'step_border_color',
            array(
                'label' => __('Step Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e5e7eb',
                'selectors' => array(
                    '{{WRAPPER}} .vt-direction-step' => 'border-bottom-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'step_hover_bg',
            array(
                'label' => __('Step Hover Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f9fafb',
                'selectors' => array(
                    '{{WRAPPER}} .vt-direction-step:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'step_active_bg',
            array(
                'label' => __('Step Active Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#eff6ff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-direction-step.active' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'step_instruction_color',
            array(
                'label' => __('Instruction Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#1f2937',
                'selectors' => array(
                    '{{WRAPPER}} .vt-step-instruction' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'step_instruction_size',
            array(
                'label' => __('Instruction Font Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 10, 'max' => 20, 'step' => 1)),
                'default' => array('size' => 14),
                'selectors' => array(
                    '{{WRAPPER}} .vt-step-instruction' => 'font-size: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'step_meta_color',
            array(
                'label' => __('Distance/Duration Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#6b7280',
                'selectors' => array(
                    '{{WRAPPER}} .vt-step-meta' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'step_meta_size',
            array(
                'label' => __('Distance/Duration Font Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 10, 'max' => 16, 'step' => 1)),
                'default' => array('size' => 12),
                'selectors' => array(
                    '{{WRAPPER}} .vt-step-meta' => 'font-size: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'step_icon_color',
            array(
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#6b7280',
                'selectors' => array(
                    '{{WRAPPER}} .vt-step-icon' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // Directions Panel Style
        $this->start_controls_section(
            'section_directions_style',
            array(
                'label' => __('Directions Panel', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => array('show_directions_panel' => 'yes'),
            )
        );

        $this->add_responsive_control(
            'directions_panel_width',
            array(
                'label' => __('Panel Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 200, 'max' => 500, 'step' => 10),
                    '%' => array('min' => 20, 'max' => 50, 'step' => 5),
                ),
                'default' => array('size' => 320, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-directions-panel' => 'width: {{SIZE}}{{UNIT}}; min-width: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array('directions_panel_position!' => 'bottom'),
            )
        );

        $this->add_control(
            'directions_bg_color',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-directions-panel' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'directions_text_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#1f2937',
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-directions-panel' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .vt-step-instruction' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .vt-waypoint-label' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'directions_secondary_color',
            array(
                'label' => __('Secondary Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#6b7280',
                'selectors' => array(
                    '{{WRAPPER}} .vt-step-meta' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .vt-step-distance' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .vt-step-duration' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'directions_border_color',
            array(
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e5e7eb',
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-directions-panel' => 'border-color: {{VALUE}};',
                    '{{WRAPPER}} .vt-route-summary' => 'border-color: {{VALUE}};',
                    '{{WRAPPER}} .vt-direction-step' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'directions_typography',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-route-directions-panel',
            )
        );

        $this->add_control(
            'directions_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default' => array(
                    'top' => 8,
                    'right' => 8,
                    'bottom' => 8,
                    'left' => 8,
                    'unit' => 'px',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-directions-panel' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // Travel Mode Buttons Style
        $this->start_controls_section(
            'section_travel_mode_style',
            array(
                'label' => __('Travel Mode Buttons', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => array('allow_travel_mode_change' => 'yes'),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'travel_btn_typography',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-travel-mode-btn',
            )
        );

        $this->add_control(
            'travel_btn_icon_size',
            array(
                'label' => __('Icon Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 12, 'max' => 32, 'step' => 1)),
                'default' => array('size' => 18),
                'selectors' => array(
                    '{{WRAPPER}} .vt-travel-mode-btn svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'travel_btn_bg_color',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-travel-mode-btn' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'travel_btn_text_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#374151',
                'selectors' => array(
                    '{{WRAPPER}} .vt-travel-mode-btn' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'travel_btn_icon_color',
            array(
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => array(
                    '{{WRAPPER}} .vt-travel-mode-btn svg' => 'color: {{VALUE}}; stroke: {{VALUE}};',
                ),
                'description' => __('Leave empty to inherit text color', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'travel_btn_border_color',
            array(
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e5e7eb',
                'selectors' => array(
                    '{{WRAPPER}} .vt-travel-mode-btn' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'travel_btn_border_width',
            array(
                'label' => __('Border Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 0, 'max' => 5, 'step' => 1)),
                'default' => array('size' => 1),
                'selectors' => array(
                    '{{WRAPPER}} .vt-travel-mode-btn' => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;',
                ),
            )
        );

        $this->add_control(
            'travel_btn_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default' => array('top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-travel-mode-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'travel_btn_padding',
            array(
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default' => array('top' => 10, 'right' => 16, 'bottom' => 10, 'left' => 16, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-travel-mode-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'travel_btn_gap',
            array(
                'label' => __('Button Gap', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 0, 'max' => 20, 'step' => 1)),
                'default' => array('size' => 8),
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-travel-modes' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'travel_btn_active_heading',
            array(
                'label' => __('Active State', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_control(
            'travel_btn_active_bg',
            array(
                'label' => __('Active Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#3b82f6',
                'selectors' => array(
                    '{{WRAPPER}} .vt-travel-mode-btn.active' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'travel_btn_active_text',
            array(
                'label' => __('Active Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-travel-mode-btn.active' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .vt-travel-mode-btn.active svg' => 'color: {{VALUE}}; stroke: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'travel_btn_active_border_color',
            array(
                'label' => __('Active Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#3b82f6',
                'selectors' => array(
                    '{{WRAPPER}} .vt-travel-mode-btn.active' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'travel_btn_hover_heading',
            array(
                'label' => __('Hover State', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_control(
            'travel_btn_hover_bg',
            array(
                'label' => __('Hover Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
                'selectors' => array(
                    '{{WRAPPER}} .vt-travel-mode-btn:not(.active):hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'travel_btn_hover_text',
            array(
                'label' => __('Hover Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#3b82f6',
                'selectors' => array(
                    '{{WRAPPER}} .vt-travel-mode-btn:not(.active):hover' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .vt-travel-mode-btn:not(.active):hover svg' => 'color: {{VALUE}}; stroke: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'travel_btn_hover_border_color',
            array(
                'label' => __('Hover Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#3b82f6',
                'selectors' => array(
                    '{{WRAPPER}} .vt-travel-mode-btn:not(.active):hover' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // Summary Style
        $this->start_controls_section(
            'section_summary_style',
            array(
                'label' => __('Route Summary', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'summary_text_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#1f2937',
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-distance' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .vt-route-duration' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'summary_typography',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-route-summary',
            )
        );

        $this->end_controls_section();

        // Export Buttons Style
        $this->start_controls_section(
            'section_export_buttons_style',
            array(
                'label' => __('Export Buttons', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'export_btn_alignment',
            array(
                'label' => __('Alignment', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => array(
                    'flex-start' => array(
                        'title' => __('Left', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-left',
                    ),
                    'center' => array(
                        'title' => __('Center', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-center',
                    ),
                    'flex-end' => array(
                        'title' => __('Right', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-right',
                    ),
                ),
                'default' => 'flex-start',
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-export-buttons' => 'justify-content: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'export_btn_gap',
            array(
                'label' => __('Button Gap', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 0, 'max' => 30, 'step' => 1)),
                'default' => array('size' => 10),
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-export-buttons' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'export_btn_typography',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-route-export-btn',
            )
        );

        $this->add_control(
            'export_btn_icon_size',
            array(
                'label' => __('Icon Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 12, 'max' => 32, 'step' => 1)),
                'default' => array('size' => 18),
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-export-btn svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'export_btn_bg_color',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#3b82f6',
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-export-btn' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'export_btn_text_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-export-btn' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .vt-route-export-btn svg' => 'stroke: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'export_btn_border_color',
            array(
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#3b82f6',
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-export-btn' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'export_btn_border_width',
            array(
                'label' => __('Border Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array('px' => array('min' => 0, 'max' => 5, 'step' => 1)),
                'default' => array('size' => 0),
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-export-btn' => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;',
                ),
            )
        );

        $this->add_control(
            'export_btn_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default' => array('top' => 6, 'right' => 6, 'bottom' => 6, 'left' => 6, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-export-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'export_btn_padding',
            array(
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default' => array('top' => 10, 'right' => 16, 'bottom' => 10, 'left' => 16, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-export-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'export_btn_hover_heading',
            array(
                'label' => __('Hover State', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_control(
            'export_btn_hover_bg',
            array(
                'label' => __('Hover Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2563eb',
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-export-btn:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'export_btn_hover_text',
            array(
                'label' => __('Hover Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-export-btn:hover' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .vt-route-export-btn:hover svg' => 'stroke: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'export_btn_hover_border',
            array(
                'label' => __('Hover Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2563eb',
                'selectors' => array(
                    '{{WRAPPER}} .vt-route-export-btn:hover' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Render icon - either custom icon from settings or default SVG
     *
     * @param array  $icon_setting The icon setting from Elementor
     * @param string $default_svg  Default SVG markup to use if no custom icon
     * @param array  $attrs        Additional attributes for the icon wrapper
     * @return string
     */
    private function render_icon($icon_setting, $default_svg, $attrs = array()) {
        // Check if custom icon is set
        if (!empty($icon_setting['value'])) {
            ob_start();
            \Elementor\Icons_Manager::render_icon($icon_setting, array('aria-hidden' => 'true'));
            return ob_get_clean();
        }

        // Return default SVG
        return $default_svg;
    }

    /**
     * Render widget output on the frontend
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // Enqueue map scripts
        Voxel_Toolkit_Route_Planner_Widget_Manager::enqueue_map_scripts();

        // Get current post
        $post = null;
        if (function_exists('\Voxel\get_current_post')) {
            $post = \Voxel\get_current_post();
        }

        if (!$post) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="vt-route-planner-placeholder">';
                echo '<div class="vt-placeholder-icon"><i class="eicon-google-maps"></i></div>';
                echo '<div class="vt-placeholder-text">' . esc_html__('Route Planner', 'voxel-toolkit') . '</div>';
                echo '<div class="vt-placeholder-description">' . esc_html__('Configure field source in widget settings. This widget must be used on a single post template.', 'voxel-toolkit') . '</div>';
                echo '</div>';
            }
            return;
        }

        // Build configuration for JavaScript
        $config = array(
            'dataSource' => $settings['data_source'],
            'startPointMode' => $settings['start_point_mode'],
            'travelMode' => $settings['travel_mode'],
            'allowTravelModeChange' => $settings['allow_travel_mode_change'] === 'yes',
            'optimizeRoute' => $settings['optimize_route'] === 'yes',
            'allowReorder' => $settings['allow_reorder'] === 'yes' && $settings['optimize_route'] !== 'yes',
            'showDirections' => $settings['show_directions_panel'] === 'yes',
            'showTurnByTurn' => $settings['show_turn_by_turn'] === 'yes',
            'showWaypointAddress' => $settings['show_waypoint_address'] === 'yes',
            'showDistance' => $settings['show_distance'] === 'yes',
            'showDuration' => $settings['show_duration'] === 'yes',
            'distanceUnit' => $settings['distance_unit'],
            'zoom' => isset($settings['default_zoom']['size']) ? intval($settings['default_zoom']['size']) : 12,
            'minZoom' => isset($settings['min_zoom']['size']) ? intval($settings['min_zoom']['size']) : 3,
            'maxZoom' => isset($settings['max_zoom']['size']) ? intval($settings['max_zoom']['size']) : 18,
            'routeLineColor' => $settings['route_line_color'] ?? '#4285F4',
            'routeLineWeight' => isset($settings['route_line_weight']['size']) ? intval($settings['route_line_weight']['size']) : 4,
            'routeLineOpacity' => isset($settings['route_line_opacity']['size']) ? floatval($settings['route_line_opacity']['size']) : 0.8,
            'markerStyle' => $settings['marker_style'] ?? 'numbered',
            'startMarkerColor' => $settings['start_marker_color'] ?? '#22c55e',
            'waypointMarkerColor' => $settings['waypoint_marker_color'] ?? '#3b82f6',
            'endMarkerColor' => $settings['end_marker_color'] ?? '#ef4444',
            'postId' => $post->get_id(),
            // Export options
            'showGoogleMapsBtn' => $settings['show_google_maps_btn'] === 'yes',
            'showAppleMapsBtn' => $settings['show_apple_maps_btn'] === 'yes',
            'showGpxBtn' => $settings['show_gpx_btn'] === 'yes',
            'gpxFilename' => $settings['gpx_filename'] ?? 'route',
        );

        // Add field keys based on data source
        if ($settings['data_source'] === 'repeater') {
            $config['repeaterKey'] = $settings['repeater_field_key'] ?? '';
            $config['locationKey'] = $settings['repeater_location_key'] ?? '';
            $config['labelKey'] = $settings['repeater_label_key'] ?? '';
        } elseif ($settings['data_source'] === 'post_relation') {
            $config['relationKey'] = $settings['relation_field_key'] ?? '';
            $config['locationKey'] = $settings['relation_location_key'] ?? '';
        } elseif ($settings['data_source'] === 'post_fields') {
            $config['postFieldsList'] = $settings['post_fields_list'] ?? array();
        }

        // Custom start point
        if ($settings['start_point_mode'] === 'custom') {
            $config['customStart'] = array(
                'lat' => floatval($settings['custom_start_lat'] ?? 0),
                'lng' => floatval($settings['custom_start_lng'] ?? 0),
                'address' => $settings['custom_start_address'] ?? '',
            );
        }

        // Panel position class
        $panel_position = $settings['directions_panel_position'] ?? 'right';
        $wrapper_class = 'vt-route-planner-wrapper vt-panel-' . esc_attr($panel_position);
        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>" data-config="<?php echo esc_attr(wp_json_encode($config)); ?>">

            <?php if ($settings['allow_travel_mode_change'] === 'yes'): ?>
            <div class="vt-route-travel-modes">
                <button type="button" class="vt-travel-mode-btn <?php echo $settings['travel_mode'] === 'driving' ? 'active' : ''; ?>" data-mode="driving">
                    <?php echo $this->render_icon(
                        $settings['travel_icon_driving'] ?? array(),
                        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>'
                    ); ?>
                    <span><?php echo esc_html($settings['travel_label_driving'] ?: __('Drive', 'voxel-toolkit')); ?></span>
                </button>
                <button type="button" class="vt-travel-mode-btn <?php echo $settings['travel_mode'] === 'walking' ? 'active' : ''; ?>" data-mode="walking">
                    <?php echo $this->render_icon(
                        $settings['travel_icon_walking'] ?? array(),
                        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1"/><path d="m9 20 3-6 3 6"/><path d="m6 8 3 1 3-1 3 1 3-1"/><path d="M12 12v-2"/></svg>'
                    ); ?>
                    <span><?php echo esc_html($settings['travel_label_walking'] ?: __('Walk', 'voxel-toolkit')); ?></span>
                </button>
                <button type="button" class="vt-travel-mode-btn <?php echo $settings['travel_mode'] === 'cycling' ? 'active' : ''; ?>" data-mode="cycling">
                    <?php echo $this->render_icon(
                        $settings['travel_icon_cycling'] ?? array(),
                        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/></svg>'
                    ); ?>
                    <span><?php echo esc_html($settings['travel_label_cycling'] ?: __('Cycle', 'voxel-toolkit')); ?></span>
                </button>
                <button type="button" class="vt-travel-mode-btn <?php echo $settings['travel_mode'] === 'transit' ? 'active' : ''; ?>" data-mode="transit">
                    <?php echo $this->render_icon(
                        $settings['travel_icon_transit'] ?? array(),
                        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="16" x="4" y="3" rx="2"/><path d="M4 11h16"/><path d="M12 3v8"/><path d="m8 19-2 3"/><path d="m18 22-2-3"/><path d="M8 15h0"/><path d="M16 15h0"/></svg>'
                    ); ?>
                    <span><?php echo esc_html($settings['travel_label_transit'] ?: __('Transit', 'voxel-toolkit')); ?></span>
                </button>
            </div>
            <?php endif; ?>

            <div class="vt-route-content">
                <div class="vt-route-map"></div>

                <?php if ($settings['show_directions_panel'] === 'yes'): ?>
                <div class="vt-route-directions-panel">
                    <div class="vt-route-summary">
                        <?php if ($settings['show_distance'] === 'yes'): ?>
                        <span class="vt-route-distance">
                            <?php echo $this->render_icon(
                                $settings['distance_icon'] ?? array(),
                                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="M8 6h10v10"/></svg>'
                            ); ?>
                            <span class="vt-distance-value">--</span>
                        </span>
                        <?php endif; ?>
                        <?php if ($settings['show_duration'] === 'yes'): ?>
                        <span class="vt-route-duration">
                            <?php echo $this->render_icon(
                                $settings['duration_icon'] ?? array(),
                                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'
                            ); ?>
                            <span class="vt-duration-value">--</span>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="vt-route-waypoints-list"></div>
                    <div class="vt-route-steps"></div>
                </div>
                <?php endif; ?>
            </div>

            <?php
            $show_export_buttons = ($settings['show_google_maps_btn'] === 'yes' || $settings['show_apple_maps_btn'] === 'yes' || $settings['show_gpx_btn'] === 'yes');
            if ($show_export_buttons): ?>
            <div class="vt-route-export-buttons">
                <?php if ($settings['show_google_maps_btn'] === 'yes'): ?>
                <button type="button" class="vt-route-export-btn vt-export-google-maps">
                    <?php echo $this->render_icon(
                        $settings['google_maps_icon'] ?? array(),
                        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>'
                    ); ?>
                    <span><?php echo esc_html($settings['google_maps_label'] ?: __('Open in Google Maps', 'voxel-toolkit')); ?></span>
                </button>
                <?php endif; ?>
                <?php if ($settings['show_apple_maps_btn'] === 'yes'): ?>
                <button type="button" class="vt-route-export-btn vt-export-apple-maps">
                    <?php echo $this->render_icon(
                        $settings['apple_maps_icon'] ?? array(),
                        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>'
                    ); ?>
                    <span><?php echo esc_html($settings['apple_maps_label'] ?: __('Open in Apple Maps', 'voxel-toolkit')); ?></span>
                </button>
                <?php endif; ?>
                <?php if ($settings['show_gpx_btn'] === 'yes'): ?>
                <button type="button" class="vt-route-export-btn vt-export-gpx">
                    <?php echo $this->render_icon(
                        $settings['gpx_icon'] ?? array(),
                        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>'
                    ); ?>
                    <span><?php echo esc_html($settings['gpx_label'] ?: __('Download GPX', 'voxel-toolkit')); ?></span>
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="vt-route-loading" style="display: none;">
                <span class="vt-route-spinner"></span>
                <span class="vt-route-loading-text"><?php esc_html_e('Calculating route...', 'voxel-toolkit'); ?></span>
            </div>
        </div>
        <?php
    }
}
