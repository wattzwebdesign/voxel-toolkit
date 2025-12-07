<?php
/**
 * Base Analytics Provider
 *
 * Abstract class defining interface for all analytics providers
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class Voxel_Toolkit_Analytics_Provider_Base {

    /**
     * Settings array for this provider
     *
     * @var array
     */
    protected $settings = array();

    /**
     * Constructor
     *
     * @param array $settings Provider settings
     */
    public function __construct($settings = array()) {
        $this->settings = $settings;
    }

    /**
     * Get provider key identifier
     *
     * @return string
     */
    abstract public function get_key();

    /**
     * Get provider display label
     *
     * @return string
     */
    abstract public function get_label();

    /**
     * Check if provider is properly configured
     *
     * @return bool
     */
    abstract public function is_configured();

    /**
     * Get the JavaScript code to track a purchase event
     *
     * @param array $data Purchase data with keys:
     *                    - transaction_id (string)
     *                    - value (float)
     *                    - currency (string)
     *                    - items (array of item arrays)
     * @return string JavaScript code
     */
    abstract public function get_purchase_script($data);

    /**
     * Render settings fields for this provider
     *
     * @param array $settings Current settings
     * @return void
     */
    abstract public function render_settings($settings);

    /**
     * Check if provider is available (future use for grayed out providers)
     *
     * @return bool
     */
    public function is_available() {
        return true;
    }

    /**
     * Get status message (e.g., "Coming Soon")
     *
     * @return string
     */
    public function get_status_message() {
        return '';
    }

    /**
     * Sanitize settings before saving
     *
     * @param array $input Raw input
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        return $input;
    }
}
