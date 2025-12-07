<?php
/**
 * Google Analytics Provider
 *
 * GA4 E-commerce tracking implementation
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Google_Analytics_Provider extends Voxel_Toolkit_Analytics_Provider_Base {

    /**
     * Get provider key identifier
     *
     * @return string
     */
    public function get_key() {
        return 'google_analytics';
    }

    /**
     * Get provider display label
     *
     * @return string
     */
    public function get_label() {
        return __('Google Analytics 4', 'voxel-toolkit');
    }

    /**
     * Check if provider is properly configured
     *
     * @return bool
     */
    public function is_configured() {
        $measurement_id = isset($this->settings['measurement_id']) ? $this->settings['measurement_id'] : '';
        return !empty($measurement_id) && preg_match('/^G-[A-Z0-9]+$/', $measurement_id);
    }

    /**
     * Get the JavaScript code to track a purchase event
     *
     * @param array $data Purchase data
     * @return string JavaScript code
     */
    public function get_purchase_script($data) {
        if (!$this->is_configured()) {
            return '';
        }

        $debug_mode = !empty($this->settings['debug_mode']);

        // Build GA4 e-commerce event data
        $event_data = array(
            'transaction_id' => isset($data['transaction_id']) ? $data['transaction_id'] : '',
            'value' => isset($data['value']) ? floatval($data['value']) : 0,
            'currency' => isset($data['currency']) ? strtoupper($data['currency']) : 'USD',
            'items' => isset($data['items']) ? $data['items'] : array(),
        );

        $json_data = wp_json_encode($event_data, JSON_UNESCAPED_UNICODE);

        $script = '';

        if ($debug_mode) {
            $script .= 'console.log("[Voxel Toolkit Analytics] GA4 Purchase Event:", ' . $json_data . ');' . "\n";
        }

        $script .= 'if (typeof gtag === "function") {' . "\n";
        $script .= '    gtag("event", "purchase", ' . $json_data . ');' . "\n";

        if ($debug_mode) {
            $script .= '    console.log("[Voxel Toolkit Analytics] GA4 purchase event sent successfully");' . "\n";
        }

        $script .= '} else {' . "\n";

        if ($debug_mode) {
            $script .= '    console.warn("[Voxel Toolkit Analytics] gtag not found. Make sure Google Analytics is installed.");' . "\n";
        }

        $script .= '}';

        return $script;
    }

    /**
     * Render settings fields for this provider
     *
     * @param array $settings Current settings
     * @return void
     */
    public function render_settings($settings) {
        $measurement_id = isset($settings['measurement_id']) ? $settings['measurement_id'] : '';
        $is_valid = !empty($measurement_id) && preg_match('/^G-[A-Z0-9]+$/', $measurement_id);
        ?>
        <div class="vt-ga-settings" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 8px;">
            <h4 style="margin: 0 0 15px 0; display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#F9AB00">
                    <path d="M22.84 2.998c-.766-.63-1.898-.52-2.528.247l-9.058 11.06-4.783-3.922c-.746-.612-1.877-.503-2.527.244-.65.747-.55 1.858.198 2.489l6.232 5.103c.344.28.763.424 1.184.424.486 0 .972-.195 1.323-.581l10.206-12.455c.63-.767.52-1.878-.247-2.609z"/>
                    <circle cx="12" cy="12" r="10" fill="none" stroke="#F9AB00" stroke-width="2"/>
                </svg>
                <?php _e('Google Analytics 4 Settings', 'voxel-toolkit'); ?>
            </h4>

            <table class="form-table" style="margin: 0;">
                <tr>
                    <th scope="row" style="padding: 10px 0;">
                        <label for="ga_measurement_id"><?php _e('Measurement ID', 'voxel-toolkit'); ?></label>
                    </th>
                    <td style="padding: 10px 0;">
                        <input
                            type="text"
                            id="ga_measurement_id"
                            name="voxel_toolkit_functions[analytics_integration][google_analytics][measurement_id]"
                            value="<?php echo esc_attr($measurement_id); ?>"
                            placeholder="G-XXXXXXXXXX"
                            class="regular-text"
                            style="font-family: monospace;"
                        />
                        <?php if (!empty($measurement_id)): ?>
                            <?php if ($is_valid): ?>
                                <span style="color: #46b450; margin-left: 10px;">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Valid format', 'voxel-toolkit'); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #dc3232; margin-left: 10px;">
                                    <span class="dashicons dashicons-warning"></span>
                                    <?php _e('Invalid format. Should be G-XXXXXXXXXX', 'voxel-toolkit'); ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <p class="description" style="margin-top: 8px;">
                            <?php _e('Find this in Google Analytics > Admin > Data Streams > Web stream details', 'voxel-toolkit'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Sanitize settings before saving
     *
     * @param array $input Raw input
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['measurement_id'])) {
            // Clean and uppercase the measurement ID
            $id = strtoupper(sanitize_text_field($input['measurement_id']));
            // Remove any spaces
            $id = str_replace(' ', '', $id);
            $sanitized['measurement_id'] = $id;
        }

        return $sanitized;
    }
}
