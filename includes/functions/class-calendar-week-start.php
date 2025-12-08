<?php
/**
 * Calendar Week Start
 *
 * Makes Voxel date pickers respect WordPress "Week Starts On" setting.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Voxel_Toolkit_Calendar_Week_Start
 *
 * Overrides Pikaday's hardcoded firstDay setting to use WordPress setting.
 */
class Voxel_Toolkit_Calendar_Week_Start {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'override_pikaday_first_day'), 999);
    }

    /**
     * Override Pikaday's firstDay setting with WordPress start_of_week option
     */
    public function override_pikaday_first_day() {
        // Get WordPress "Week Starts On" setting (0 = Sunday, 1 = Monday, etc.)
        $start_of_week = (int) get_option('start_of_week', 0);

        // JavaScript to monkey-patch Pikaday constructor
        $inline_js = "
(function() {
    // Wait for Pikaday to be available
    var checkPikaday = function() {
        if (typeof window.Pikaday === 'undefined') {
            return;
        }

        var originalPikaday = window.Pikaday;

        // Replace Pikaday constructor with our wrapper
        window.Pikaday = function(options) {
            options = options || {};
            // Override firstDay if it's using the Voxel default (1 = Monday)
            // or if it's not explicitly set
            if (typeof options.firstDay === 'undefined' || options.firstDay === 1) {
                options.firstDay = {$start_of_week};
            }
            return new originalPikaday(options);
        };

        // Preserve prototype and static properties
        window.Pikaday.prototype = originalPikaday.prototype;
        Object.keys(originalPikaday).forEach(function(key) {
            window.Pikaday[key] = originalPikaday[key];
        });
    };

    // Run immediately if Pikaday is already loaded
    checkPikaday();

    // Also run on DOMContentLoaded in case Pikaday loads later
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkPikaday);
    }
})();
";

        // Add inline script after pikaday.js loads
        wp_add_inline_script('pikaday', $inline_js, 'after');
    }
}
