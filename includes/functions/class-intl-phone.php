<?php
/**
 * International Phone Input Enhancement
 *
 * Adds intl-tel-input library to Voxel phone fields for country code selection
 * and auto-detection. Stores country code separately from phone number.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Intl_Phone {

    /**
     * Settings instance
     */
    private $settings;

    /**
     * Migration option key
     */
    const MIGRATION_OPTION = 'vt_intl_phone_migrated';

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = Voxel_Toolkit_Settings::instance();

        // Run migration on activation (if not already done)
        $this->maybe_run_migration();

        // Enqueue scripts on frontend pages
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Hook into Voxel form submission to save country code
        add_action('voxel/frontend/post_updated', array($this, 'save_country_codes'), 10, 1);

        // Also hook into admin post save
        add_action('voxel/admin/save_post', array($this, 'admin_save_country_codes'), 10, 1);
    }

    /**
     * Run migration if not already done
     */
    private function maybe_run_migration() {
        // Check if migration already ran
        if (get_option(self::MIGRATION_OPTION)) {
            return;
        }

        // Run the migration
        $this->migrate_existing_phone_numbers();

        // Mark as migrated
        update_option(self::MIGRATION_OPTION, time());
    }

    /**
     * Migrate existing phone numbers to include country code
     * Uses the default country code from SMS Notifications settings
     */
    private function migrate_existing_phone_numbers() {
        // Get default country code from SMS Notifications settings
        $sms_settings = $this->settings->get_function_settings('sms_notifications', array());
        $default_country_code = isset($sms_settings['country_code']) ? $sms_settings['country_code'] : '';

        // Clean country code (remove + if present)
        $default_country_code = preg_replace('/[^0-9]/', '', $default_country_code);

        if (empty($default_country_code)) {
            // No default country code configured, skip migration
            return;
        }

        // Get all Voxel post types
        if (!class_exists('\Voxel\Post_Type')) {
            return;
        }

        $post_types = \Voxel\Post_Type::get_all();
        $migrated_count = 0;

        foreach ($post_types as $post_type) {
            // Get phone fields for this post type
            $phone_fields = array();
            $fields = $post_type->get_fields();

            foreach ($fields as $field) {
                if ($field->get_type() === 'phone') {
                    $phone_fields[] = $field->get_key();
                }
            }

            if (empty($phone_fields)) {
                continue;
            }

            // Query all posts of this type
            $posts = get_posts(array(
                'post_type' => $post_type->get_key(),
                'posts_per_page' => -1,
                'post_status' => 'any',
                'fields' => 'ids',
            ));

            foreach ($posts as $post_id) {
                foreach ($phone_fields as $phone_field) {
                    // Check if phone has a value
                    $phone_value = get_post_meta($post_id, $phone_field, true);

                    if (empty($phone_value)) {
                        continue;
                    }

                    // Check if country code already exists
                    $country_key = $phone_field . '_country_code';
                    $existing_country = get_post_meta($post_id, $country_key, true);

                    if (!empty($existing_country)) {
                        // Already has country code, skip
                        continue;
                    }

                    // Add the default country code
                    update_post_meta($post_id, $country_key, $default_country_code);
                    $migrated_count++;
                }
            }
        }

    }

    /**
     * Reset migration flag (useful for re-running migration)
     */
    public static function reset_migration() {
        delete_option(self::MIGRATION_OPTION);
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        // Enqueue on all pages that might have a Voxel form
        // This includes profile edit pages, listing create/edit pages, etc.

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
            'vt-intl-phone',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/intl-phone-input.js',
            array('intl-tel-input'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Get phone field key from SMS Notifications settings
        $sms_settings = $this->settings->get_function_settings('sms_notifications', array());
        $phone_field_key = isset($sms_settings['phone_field']) ? $sms_settings['phone_field'] : 'phone';

        // Localize script with config
        wp_localize_script('vt-intl-phone', 'vt_intl_phone', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_intl_phone_nonce'),
            'utils_url' => 'https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/utils.js',
            'phone_field_key' => $phone_field_key,
        ));
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

                // Check if country code was submitted (via hidden field or POST data)
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
            // No country code stored, return phone as-is (might not be valid E.164)
            return $phone;
        }

        // Remove leading 0 if present (common in local formats)
        if (strpos($phone, '0') === 0) {
            $phone = substr($phone, 1);
        }

        return '+' . $country_code . $phone;
    }

    /**
     * Get phone number formatted for display (local format, no country code)
     *
     * @param int $post_id Post ID
     * @param string $phone_field_key Phone field key
     * @return string Phone number for display
     */
    public static function get_display_phone($post_id, $phone_field_key) {
        return get_post_meta($post_id, $phone_field_key, true);
    }
}
