<?php
/**
 * Weather Elementor Widget
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Weather_Widget extends \Elementor\Widget_Base {
    
    /**
     * Get widget name
     */
    public function get_name() {
        return 'voxel-toolkit-weather';
    }
    
    /**
     * Get widget title
     */
    public function get_title() {
        return __('Weather (VT)', 'voxel-toolkit');
    }
    
    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-flash';
    }
    
    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['voxel-toolkit'];
    }
    
    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return ['weather', 'forecast', 'temperature', 'climate'];
    }
    
    /**
     * Register widget controls
     */
    protected function _register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Weather Settings', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'latitude',
            [
                'label' => __('Latitude', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '40.7128',
                'placeholder' => __('e.g., 40.7128', 'voxel-toolkit'),
            ]
        );
        
        $this->add_control(
            'longitude',
            [
                'label' => __('Longitude', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '-74.0060',
                'placeholder' => __('e.g., -74.0060', 'voxel-toolkit'),
            ]
        );
        
        $this->add_control(
            'api_key',
            [
                'label' => __('API Key', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('Enter your OpenWeatherMap API key', 'voxel-toolkit'),
                'input_type' => 'password',
            ]
        );
        
        $this->add_control(
            'view_type',
            [
                'label' => __('View Type', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'current',
                'options' => [
                    'current' => __('Current Weather', 'voxel-toolkit'),
                    'forecast_3' => __('3-Day Forecast', 'voxel-toolkit'),
                    'forecast_5' => __('5-Day Forecast', 'voxel-toolkit'),
                ],
            ]
        );
        
        $this->add_control(
            'units',
            [
                'label' => __('Temperature Units', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'metric',
                'options' => [
                    'metric' => __('Celsius (°C)', 'voxel-toolkit'),
                    'imperial' => __('Fahrenheit (°F)', 'voxel-toolkit'),
                    'kelvin' => __('Kelvin (K)', 'voxel-toolkit'),
                ],
            ]
        );
        
        $this->add_control(
            'language',
            [
                'label' => __('Language', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => $this->get_default_language(),
                'options' => $this->get_language_options(),
                'description' => __('Select the language for weather descriptions and location names.', 'voxel-toolkit'),
            ]
        );
        
        $this->add_control(
            'show_description',
            [
                'label' => __('Show Weather Description', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'voxel-toolkit'),
                'label_off' => __('Hide', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'show_humidity',
            [
                'label' => __('Show Humidity', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'voxel-toolkit'),
                'label_off' => __('Hide', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'show_wind',
            [
                'label' => __('Show Wind Speed', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'voxel-toolkit'),
                'label_off' => __('Hide', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'show_icons',
            [
                'label' => __('Show Weather Icons', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'voxel-toolkit'),
                'label_off' => __('Hide', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Container
        $this->start_controls_section(
            'style_container',
            [
                'label' => __('Container Style', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'container_background',
                'label' => __('Background', 'voxel-toolkit'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .voxel-weather-widget',
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .voxel-weather-widget',
            ]
        );
        
        $this->add_control(
            'container_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .voxel-weather-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .voxel-weather-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'container_box_shadow',
                'selector' => '{{WRAPPER}} .voxel-weather-widget',
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Typography
        $this->start_controls_section(
            'style_typography',
            [
                'label' => __('Typography', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'location_typography',
                'label' => __('Location Text', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .voxel-weather-location',
            ]
        );
        
        $this->add_control(
            'location_color',
            [
                'label' => __('Location Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .voxel-weather-location' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'temperature_typography',
                'label' => __('Temperature Text', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .voxel-weather-temperature',
            ]
        );
        
        $this->add_control(
            'temperature_color',
            [
                'label' => __('Temperature Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .voxel-weather-temperature' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography',
                'label' => __('Description Text', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .voxel-weather-description, {{WRAPPER}} .voxel-weather-details',
            ]
        );
        
        $this->add_control(
            'description_color',
            [
                'label' => __('Description Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .voxel-weather-description, {{WRAPPER}} .voxel-weather-details' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Get language options for dropdown
     */
    private function get_language_options() {
        return [
            'en' => __('English', 'voxel-toolkit'),
            'af' => __('Afrikaans', 'voxel-toolkit'),
            'sq' => __('Albanian', 'voxel-toolkit'),
            'ar' => __('Arabic', 'voxel-toolkit'),
            'az' => __('Azerbaijani', 'voxel-toolkit'),
            'eu' => __('Basque', 'voxel-toolkit'),
            'be' => __('Belarusian', 'voxel-toolkit'),
            'bg' => __('Bulgarian', 'voxel-toolkit'),
            'ca' => __('Catalan', 'voxel-toolkit'),
            'zh_cn' => __('Chinese Simplified', 'voxel-toolkit'),
            'zh_tw' => __('Chinese Traditional', 'voxel-toolkit'),
            'hr' => __('Croatian', 'voxel-toolkit'),
            'cz' => __('Czech', 'voxel-toolkit'),
            'da' => __('Danish', 'voxel-toolkit'),
            'nl' => __('Dutch', 'voxel-toolkit'),
            'fi' => __('Finnish', 'voxel-toolkit'),
            'fr' => __('French', 'voxel-toolkit'),
            'gl' => __('Galician', 'voxel-toolkit'),
            'de' => __('German', 'voxel-toolkit'),
            'el' => __('Greek', 'voxel-toolkit'),
            'he' => __('Hebrew', 'voxel-toolkit'),
            'hi' => __('Hindi', 'voxel-toolkit'),
            'hu' => __('Hungarian', 'voxel-toolkit'),
            'is' => __('Icelandic', 'voxel-toolkit'),
            'id' => __('Indonesian', 'voxel-toolkit'),
            'it' => __('Italian', 'voxel-toolkit'),
            'ja' => __('Japanese', 'voxel-toolkit'),
            'kr' => __('Korean', 'voxel-toolkit'),
            'ku' => __('Kurmanji (Kurdish)', 'voxel-toolkit'),
            'la' => __('Latvian', 'voxel-toolkit'),
            'lt' => __('Lithuanian', 'voxel-toolkit'),
            'mk' => __('Macedonian', 'voxel-toolkit'),
            'no' => __('Norwegian', 'voxel-toolkit'),
            'fa' => __('Persian (Farsi)', 'voxel-toolkit'),
            'pl' => __('Polish', 'voxel-toolkit'),
            'pt' => __('Portuguese', 'voxel-toolkit'),
            'pt_br' => __('Portuguese Brasil', 'voxel-toolkit'),
            'ro' => __('Romanian', 'voxel-toolkit'),
            'ru' => __('Russian', 'voxel-toolkit'),
            'sr' => __('Serbian', 'voxel-toolkit'),
            'sk' => __('Slovak', 'voxel-toolkit'),
            'sl' => __('Slovenian', 'voxel-toolkit'),
            'es' => __('Spanish', 'voxel-toolkit'),
            'sv' => __('Swedish', 'voxel-toolkit'),
            'th' => __('Thai', 'voxel-toolkit'),
            'tr' => __('Turkish', 'voxel-toolkit'),
            'uk' => __('Ukrainian', 'voxel-toolkit'),
            'vi' => __('Vietnamese', 'voxel-toolkit'),
        ];
    }
    
    /**
     * Get default language - always English
     */
    private function get_default_language() {
        return 'en';
    }
    
    /**
     * Render the widget
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Check if required fields are filled
        if (empty($settings['api_key']) || empty($settings['latitude']) || empty($settings['longitude'])) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="voxel-weather-widget" style="padding: 20px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; text-align: center;">';
                echo '<p style="margin: 0; color: #999;">Please enter your API key, latitude, and longitude in the widget settings.</p>';
                echo '</div>';
            }
            return;
        }
        
        // Get weather data
        $weather_data = $this->get_weather_data($settings);
        
        if (!$weather_data) {
            echo '<div class="voxel-weather-widget" style="padding: 20px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; text-align: center;">';
            echo '<p style="margin: 0; color: #e74c3c;">Error loading weather data. Please check your API key and location.</p>';
            echo '</div>';
            return;
        }
        
        $this->render_weather($weather_data, $settings);
    }
    
    /**
     * Get weather data from OpenWeatherMap API
     */
    private function get_weather_data($settings) {
        $api_key = $settings['api_key'];
        $latitude = $settings['latitude'];
        $longitude = $settings['longitude'];
        $units = $settings['units'];
        $view_type = $settings['view_type'];
        $language = !empty($settings['language']) ? $settings['language'] : $this->get_default_language();
        
        // Create cache key
        $cache_key = 'voxel_weather_' . md5($api_key . $latitude . $longitude . $units . $view_type . $language);
        
        // Check cache first (5 minutes)
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Map units to API-compatible values (OpenWeatherMap uses 'standard' for Kelvin)
        $api_units = ($units === 'kelvin') ? 'standard' : $units;

        // Determine API endpoint using lat/lng
        if ($view_type === 'current') {
            $api_url = "https://api.openweathermap.org/data/2.5/weather?lat={$latitude}&lon={$longitude}&appid={$api_key}&units={$api_units}&lang={$language}";
        } else {
            $api_url = "https://api.openweathermap.org/data/2.5/forecast?lat={$latitude}&lon={$longitude}&appid={$api_key}&units={$api_units}&lang={$language}";
        }
        
        // Make API request
        $response = wp_remote_get($api_url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || (isset($data['cod']) && $data['cod'] != 200)) {
            return false;
        }
        
        // Cache the result for 5 minutes
        set_transient($cache_key, $data, 300);
        
        return $data;
    }
    
    /**
     * Render weather display
     */
    private function render_weather($data, $settings) {
        ?>
        <style>
        .voxel-weather-widget {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .voxel-weather-main {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 10px 0;
        }
        .voxel-weather-icon .weather-icon-img {
            width: 64px;
            height: 64px;
            object-fit: contain;
        }
        .voxel-weather-forecast-days {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .voxel-weather-forecast-day {
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            min-width: 120px;
        }
        .voxel-weather-forecast-day .voxel-weather-icon .weather-icon-img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            margin: 5px 0;
        }
        .voxel-weather-details {
            margin-top: 15px;
        }
        .voxel-weather-details span {
            display: inline-block;
            margin-right: 20px;
            font-size: 14px;
        }
        </style>
        <div class="voxel-weather-widget">
            <?php if ($settings['view_type'] === 'current'): ?>
                <?php $this->render_current_weather($data, $settings); ?>
            <?php else: ?>
                <?php $this->render_forecast_weather($data, $settings); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render current weather
     */
    private function render_current_weather($data, $settings) {
        $temp_unit = $this->get_temp_unit($settings['units']);
        $wind_unit = $settings['units'] === 'imperial' ? 'mph' : 'm/s';
        $weather_icon = $settings['show_icons'] === 'yes' ? $this->get_weather_icon($data['weather'][0]['icon']) : '';
        ?>
        <div class="voxel-weather-current">
            <div class="voxel-weather-location"><?php echo esc_html($data['name'] . ', ' . $data['sys']['country']); ?></div>
            
            <div class="voxel-weather-main">
                <?php if ($settings['show_icons'] === 'yes' && $weather_icon): ?>
                    <div class="voxel-weather-icon"><?php echo $weather_icon; ?></div>
                <?php endif; ?>
                <div class="voxel-weather-temperature"><?php echo round($data['main']['temp']); ?><?php echo $temp_unit; ?></div>
            </div>
            
            <?php if ($settings['show_description'] === 'yes'): ?>
                <div class="voxel-weather-description"><?php echo ucwords($data['weather'][0]['description']); ?></div>
            <?php endif; ?>
            
            <div class="voxel-weather-details">
                <?php if ($settings['show_humidity'] === 'yes'): ?>
                    <span class="voxel-weather-humidity">Humidity: <?php echo $data['main']['humidity']; ?>%</span>
                <?php endif; ?>
                
                <?php if ($settings['show_wind'] === 'yes'): ?>
                    <span class="voxel-weather-wind">Wind: <?php echo round($data['wind']['speed']); ?> <?php echo $wind_unit; ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render forecast weather
     */
    private function render_forecast_weather($data, $settings) {
        $temp_unit = $this->get_temp_unit($settings['units']);
        $days_to_show = $settings['view_type'] === 'forecast_3' ? 3 : 5;
        
        // Group forecasts by day
        $daily_forecasts = array();
        foreach ($data['list'] as $forecast) {
            $date = date('Y-m-d', $forecast['dt']);
            if (!isset($daily_forecasts[$date])) {
                $daily_forecasts[$date] = $forecast;
            }
        }
        
        $daily_forecasts = array_slice($daily_forecasts, 0, $days_to_show, true);
        ?>
        <div class="voxel-weather-forecast">
            <div class="voxel-weather-location"><?php echo esc_html($data['city']['name'] . ', ' . $data['city']['country']); ?></div>
            
            <div class="voxel-weather-forecast-days">
                <?php foreach ($daily_forecasts as $date => $forecast): ?>
                    <div class="voxel-weather-forecast-day">
                        <div class="voxel-weather-forecast-date"><?php echo date('M j', $forecast['dt']); ?></div>
                        
                        <?php if ($settings['show_icons'] === 'yes'): ?>
                            <div class="voxel-weather-icon"><?php echo $this->get_weather_icon($forecast['weather'][0]['icon']); ?></div>
                        <?php endif; ?>
                        
                        <div class="voxel-weather-temperature"><?php echo round($forecast['main']['temp']); ?><?php echo $temp_unit; ?></div>
                        <?php if ($settings['show_description'] === 'yes'): ?>
                            <div class="voxel-weather-description"><?php echo ucwords($forecast['weather'][0]['description']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get temperature unit symbol
     */
    private function get_temp_unit($units) {
        switch ($units) {
            case 'imperial':
                return '°F';
            case 'kelvin':
                return 'K';
            default:
                return '°C';
        }
    }
    
    /**
     * Get weather icon HTML based on OpenWeatherMap icon code
     */
    private function get_weather_icon($icon_code) {
        if (empty($icon_code)) {
            return '';
        }
        
        $icon_url = "https://openweathermap.org/img/wn/{$icon_code}@2x.png";
        $alt_text = $this->get_weather_icon_alt($icon_code);
        
        return '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr($alt_text) . '" class="weather-icon-img" />';
    }
    
    /**
     * Get alt text for weather icons
     */
    private function get_weather_icon_alt($icon_code) {
        $alt_map = [
            '01d' => 'Clear sky',
            '01n' => 'Clear sky',
            '02d' => 'Few clouds',
            '02n' => 'Few clouds',
            '03d' => 'Scattered clouds',
            '03n' => 'Scattered clouds',
            '04d' => 'Broken clouds',
            '04n' => 'Broken clouds',
            '09d' => 'Shower rain',
            '09n' => 'Shower rain',
            '10d' => 'Rain',
            '10n' => 'Rain',
            '11d' => 'Thunderstorm',
            '11n' => 'Thunderstorm',
            '13d' => 'Snow',
            '13n' => 'Snow',
            '50d' => 'Mist',
            '50n' => 'Mist',
        ];
        
        return isset($alt_map[$icon_code]) ? $alt_map[$icon_code] : 'Weather icon';
    }
}