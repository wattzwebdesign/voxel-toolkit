<?php
/**
 * Advanced Phone Input Enhancement
 *
 * Extends Voxel's native phone field with intl-tel-input configuration options:
 * - Default country
 * - Allowed countries (restriction)
 * - Show/hide dropdown
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Advanced_Phone_Input {

    /**
     * Settings instance
     */
    private $settings;

    /**
     * Flag to prevent double initialization
     */
    private static $initialized = false;

    /**
     * Constructor
     */
    public function __construct() {
        // Prevent double initialization
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        $this->settings = Voxel_Toolkit_Settings::instance();

        // Enqueue scripts on frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Save country codes on form submission
        add_action('voxel/frontend/post_updated', array($this, 'save_country_codes'), 10, 1);
        add_action('voxel/admin/save_post', array($this, 'admin_save_country_codes'), 10, 1);
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        // intl-tel-input CSS
        wp_enqueue_style(
            'intl-tel-input',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/css/intlTelInput.css',
            array(),
            '18.0.0'
        );

        // Fix flag sprite path - use local copies for reliability
        $flags_url = VOXEL_TOOLKIT_PLUGIN_URL . 'assets/img/flags.png';
        $flags_2x_url = VOXEL_TOOLKIT_PLUGIN_URL . 'assets/img/flags@2x.png';
        $flag_sprite_css = '
            .iti__flag {
                background-image: url("' . $flags_url . '") !important;
            }
            @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
                .iti__flag {
                    background-image: url("' . $flags_2x_url . '") !important;
                }
            }
        ';
        wp_add_inline_style('intl-tel-input', $flag_sprite_css);

        // intl-tel-input JS
        wp_enqueue_script(
            'intl-tel-input',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/intlTelInput.min.js',
            array(),
            '18.0.0',
            true
        );

        // Our custom handler
        wp_enqueue_script(
            'vt-advanced-phone-input',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/advanced-phone-input.js',
            array('intl-tel-input'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Collect phone field configs from all post types
        $field_configs = $this->get_all_phone_field_configs();

        // Localize script with config
        wp_localize_script('vt-advanced-phone-input', 'vt_advanced_phone', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_advanced_phone_nonce'),
            'utils_url' => 'https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/utils.js',
            'field_configs' => $field_configs,
        ));
    }

    /**
     * Get phone field configurations from all Voxel post types
     *
     * @return array Field key => config array
     */
    private function get_all_phone_field_configs() {
        $configs = [];

        if (!class_exists('\Voxel\Post_Type')) {
            return $configs;
        }

        $post_types = \Voxel\Post_Type::get_all();

        foreach ($post_types as $post_type) {
            $fields = $post_type->get_fields();

            foreach ($fields as $field) {
                if ($field->get_type() === 'phone') {
                    $field_key = $field->get_key();

                    // Get the VT config props
                    $initial_country = '';
                    $only_countries = [];
                    $allow_dropdown = true;

                    // Access props via reflection or get_prop if available
                    if (method_exists($field, 'get_prop')) {
                        $initial_country = $field->get_prop('vt_initial_country') ?: '';
                        $only_countries = $field->get_prop('vt_only_countries') ?: [];
                        $allow_dropdown = $field->get_prop('vt_allow_dropdown');
                        if ($allow_dropdown === null) {
                            $allow_dropdown = true;
                        }
                    }

                    // Parse only_countries if it's a string
                    if (is_string($only_countries) && !empty($only_countries)) {
                        $only_countries = array_map('trim', explode(',', $only_countries));
                        $only_countries = array_map('strtolower', $only_countries);
                        $only_countries = array_filter($only_countries);
                        $only_countries = array_values($only_countries);
                    } elseif (is_array($only_countries)) {
                        $only_countries = array_map('strtolower', $only_countries);
                        $only_countries = array_filter($only_countries);
                        $only_countries = array_values($only_countries);
                    } else {
                        $only_countries = [];
                    }

                    $configs[$field_key] = [
                        'initialCountry' => strtolower($initial_country),
                        'onlyCountries' => $only_countries,
                        'allowDropdown' => (bool) $allow_dropdown,
                    ];
                }
            }
        }

        return $configs;
    }

    /**
     * Save country codes when Voxel form is submitted (frontend)
     *
     * @param array $args Event arguments containing post object
     */
    public function save_country_codes($args) {
        if (!isset($args['post'])) {
            return;
        }

        $post = $args['post'];
        $post_id = $post->get_id();

        // Get all fields for this post type
        $fields = $post->get_fields();

        foreach ($fields as $field) {
            if ($field->get_type() === 'phone') {
                $field_key = $field->get_key();
                $country_key = $field_key . '_country_code';

                // Check if country code was submitted
                if (isset($_POST[$country_key])) {
                    $country_code = sanitize_text_field($_POST[$country_key]);
                    if (!empty($country_code)) {
                        update_post_meta($post_id, $country_key, $country_code);
                    }
                }
            }
        }
    }

    /**
     * Save country codes when post is saved in admin
     *
     * @param \Voxel\Post $post Post object
     */
    public function admin_save_country_codes($post) {
        $post_id = $post->get_id();
        $fields = $post->get_fields();

        foreach ($fields as $field) {
            if ($field->get_type() === 'phone') {
                $field_key = $field->get_key();
                $country_key = $field_key . '_country_code';

                if (isset($_POST[$country_key])) {
                    $country_code = sanitize_text_field($_POST[$country_key]);
                    if (!empty($country_code)) {
                        update_post_meta($post_id, $country_key, $country_code);
                    }
                }
            }
        }
    }

    /**
     * Get country code for a phone field
     *
     * @param int $post_id Post ID
     * @param string $phone_field_key Phone field key
     * @return string Country dial code (without +) or empty string
     */
    public static function get_country_code($post_id, $phone_field_key) {
        return get_post_meta($post_id, $phone_field_key . '_country_code', true);
    }

    /**
     * Get full E.164 formatted phone number
     *
     * @param int $post_id Post ID
     * @param string $phone_field_key Phone field key
     * @return string Full phone number in E.164 format (+1234567890) or empty string
     */
    public static function get_full_phone($post_id, $phone_field_key) {
        $phone = get_post_meta($post_id, $phone_field_key, true);
        $country_code = self::get_country_code($post_id, $phone_field_key);

        if (empty($phone)) {
            return '';
        }

        // Clean phone number - remove non-digits
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (empty($country_code)) {
            return $phone;
        }

        // Remove leading 0 if present (common in local formats)
        if (strpos($phone, '0') === 0) {
            $phone = substr($phone, 1);
        }

        return '+' . $country_code . $phone;
    }
}

/**
 * Extended Phone Field Type
 *
 * Extends Voxel's native Phone_Field to add intl-tel-input configuration options
 */
class Voxel_Toolkit_Extended_Phone_Field extends \Voxel\Post_Types\Fields\Phone_Field {

    protected $props = [
        'type' => 'phone',
        'label' => 'Phone',
        'placeholder' => '',
        'default' => null,
        // VT: intl-tel-input options
        'vt_initial_country' => '',
        'vt_only_countries' => [],
        'vt_allow_dropdown' => true,
    ];

    /**
     * Get field editor models
     *
     * @return array Field editor configuration
     */
    public function get_models(): array {
        $parent_models = parent::get_models();
        $countries = $this->get_country_choices();

        // Insert VT settings after description
        $new_models = [];
        foreach ($parent_models as $key => $model) {
            $new_models[$key] = $model;

            // Insert our fields after description
            if ($key === 'description') {
                $new_models['vt_allow_dropdown'] = [
                    'type' => \Voxel\Form_Models\Switcher_Model::class,
                    'label' => 'Country Selector Dropdown',
                    'description' => 'Show the country selection dropdown',
                    'classes' => 'x-col-12',
                ];

                $new_models['vt_initial_country'] = [
                    'type' => \Voxel\Form_Models\Select_Model::class,
                    'label' => 'Default Country',
                    'description' => 'Select the default country for this phone field.',
                    'classes' => 'x-col-6',
                    'choices' => array_merge(['' => '-- Auto-detect --'], $countries),
                ];

                $new_models['vt_only_countries'] = [
                    'type' => \Voxel\Form_Models\Checkboxes_Model::class,
                    'label' => 'Only Countries',
                    'description' => 'Restrict to specific countries. Leave empty for all.',
                    'classes' => 'x-col-12',
                    'columns' => 'two',
                    'choices' => $countries,
                ];
            }
        }

        return $new_models;
    }

    /**
     * Get list of countries for dropdown/checkboxes
     *
     * @return array Country code => Country name
     */
    private function get_country_choices() {
        return [
            'af' => 'Afghanistan',
            'al' => 'Albania',
            'dz' => 'Algeria',
            'ad' => 'Andorra',
            'ao' => 'Angola',
            'ar' => 'Argentina',
            'am' => 'Armenia',
            'au' => 'Australia',
            'at' => 'Austria',
            'az' => 'Azerbaijan',
            'bh' => 'Bahrain',
            'bd' => 'Bangladesh',
            'by' => 'Belarus',
            'be' => 'Belgium',
            'bz' => 'Belize',
            'bj' => 'Benin',
            'bt' => 'Bhutan',
            'bo' => 'Bolivia',
            'ba' => 'Bosnia',
            'bw' => 'Botswana',
            'br' => 'Brazil',
            'bn' => 'Brunei',
            'bg' => 'Bulgaria',
            'kh' => 'Cambodia',
            'cm' => 'Cameroon',
            'ca' => 'Canada',
            'cl' => 'Chile',
            'cn' => 'China',
            'co' => 'Colombia',
            'cr' => 'Costa Rica',
            'hr' => 'Croatia',
            'cu' => 'Cuba',
            'cy' => 'Cyprus',
            'cz' => 'Czech Republic',
            'dk' => 'Denmark',
            'ec' => 'Ecuador',
            'eg' => 'Egypt',
            'sv' => 'El Salvador',
            'ee' => 'Estonia',
            'et' => 'Ethiopia',
            'fi' => 'Finland',
            'fr' => 'France',
            'ge' => 'Georgia',
            'de' => 'Germany',
            'gh' => 'Ghana',
            'gr' => 'Greece',
            'gt' => 'Guatemala',
            'hn' => 'Honduras',
            'hk' => 'Hong Kong',
            'hu' => 'Hungary',
            'is' => 'Iceland',
            'in' => 'India',
            'id' => 'Indonesia',
            'ir' => 'Iran',
            'iq' => 'Iraq',
            'ie' => 'Ireland',
            'il' => 'Israel',
            'it' => 'Italy',
            'jm' => 'Jamaica',
            'jp' => 'Japan',
            'jo' => 'Jordan',
            'kz' => 'Kazakhstan',
            'ke' => 'Kenya',
            'kw' => 'Kuwait',
            'lv' => 'Latvia',
            'lb' => 'Lebanon',
            'ly' => 'Libya',
            'lt' => 'Lithuania',
            'lu' => 'Luxembourg',
            'mo' => 'Macau',
            'my' => 'Malaysia',
            'mv' => 'Maldives',
            'mt' => 'Malta',
            'mx' => 'Mexico',
            'md' => 'Moldova',
            'mc' => 'Monaco',
            'mn' => 'Mongolia',
            'me' => 'Montenegro',
            'ma' => 'Morocco',
            'mm' => 'Myanmar',
            'np' => 'Nepal',
            'nl' => 'Netherlands',
            'nz' => 'New Zealand',
            'ni' => 'Nicaragua',
            'ng' => 'Nigeria',
            'no' => 'Norway',
            'om' => 'Oman',
            'pk' => 'Pakistan',
            'pa' => 'Panama',
            'py' => 'Paraguay',
            'pe' => 'Peru',
            'ph' => 'Philippines',
            'pl' => 'Poland',
            'pt' => 'Portugal',
            'pr' => 'Puerto Rico',
            'qa' => 'Qatar',
            'ro' => 'Romania',
            'ru' => 'Russia',
            'sa' => 'Saudi Arabia',
            'rs' => 'Serbia',
            'sg' => 'Singapore',
            'sk' => 'Slovakia',
            'si' => 'Slovenia',
            'za' => 'South Africa',
            'kr' => 'South Korea',
            'es' => 'Spain',
            'lk' => 'Sri Lanka',
            'se' => 'Sweden',
            'ch' => 'Switzerland',
            'tw' => 'Taiwan',
            'th' => 'Thailand',
            'tn' => 'Tunisia',
            'tr' => 'Turkey',
            'ua' => 'Ukraine',
            'ae' => 'United Arab Emirates',
            'gb' => 'United Kingdom',
            'us' => 'United States',
            'uy' => 'Uruguay',
            'uz' => 'Uzbekistan',
            've' => 'Venezuela',
            'vn' => 'Vietnam',
            'ye' => 'Yemen',
            'zm' => 'Zambia',
            'zw' => 'Zimbabwe',
        ];
    }

    /**
     * Get frontend props (passed to JavaScript)
     *
     * @return array Frontend configuration
     */
    protected function frontend_props() {
        $props = parent::frontend_props();

        // Add intl-tel-input configuration
        $props['vt_phone_config'] = [
            'initialCountry' => $this->get_prop('vt_initial_country') ?: 'us',
            'onlyCountries' => $this->parse_countries($this->get_prop('vt_only_countries')),
            'allowDropdown' => (bool) $this->get_prop('vt_allow_dropdown'),
            'fieldKey' => $this->get_key(),
        ];

        return $props;
    }

    /**
     * Parse countries value into array
     *
     * @param mixed $countries Array from checkboxes or comma-separated string
     * @return array Array of country codes
     */
    private function parse_countries($countries) {
        if (empty($countries)) {
            return [];
        }

        // If already an array (from checkboxes), just filter and return
        if (is_array($countries)) {
            $countries = array_map('strtolower', $countries);
            $countries = array_filter($countries);
            return array_values($countries);
        }

        // Backwards compatibility: parse comma-separated string
        $countries = array_map('trim', explode(',', $countries));
        $countries = array_map('strtolower', $countries);
        $countries = array_filter($countries);

        return array_values($countries);
    }
}
