<?php
/**
 * Promotion Create Form
 *
 * Adds promotion package selection to Voxel's Create Form widget.
 * Users can optionally select a promotion package before submitting,
 * then get redirected to checkout after post creation.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Promotion_Create_Form {

    private static $instance = null;

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
     * Widgets with promotions enabled
     */
    private $promotion_widgets = array();

    /**
     * Constructor
     */
    private function __construct() {
        // Add Elementor controls to Create Post widget
        add_action('elementor/element/ts-create-post/ts_sf_post_types/after_section_end', array($this, 'add_widget_controls'), 10, 2);

        // Capture widget settings when rendered
        add_action('elementor/frontend/widget/before_render', array($this, 'capture_widget_settings'), 10, 1);

        // Render preview HTML directly after widget in Elementor editor
        add_action('elementor/frontend/widget/after_render', array($this, 'render_elementor_preview'), 10, 1);

        // Inject frontend JavaScript/Vue component
        add_action('wp_footer', array($this, 'render_frontend_script'), 20);

        // Auto-select package on cart-summary page
        add_action('wp_footer', array($this, 'render_cart_summary_autoselect'), 30);

        // Enqueue CSS
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));

        // Also enqueue CSS in Elementor editor preview
        add_action('elementor/preview/enqueue_styles', array($this, 'enqueue_styles'));

        // AJAX handler to get promotion packages for a post type
        add_action('wp_ajax_vt_get_promotion_packages', array($this, 'ajax_get_packages'));
        add_action('wp_ajax_nopriv_vt_get_promotion_packages', array($this, 'ajax_get_packages'));
    }

    /**
     * Capture widget settings when Create Post widget is rendered
     */
    public function capture_widget_settings($widget) {
        if ($widget->get_name() !== 'ts-create-post') {
            return;
        }

        $settings = $widget->get_settings_for_display();

        if (isset($settings['vt_enable_promotions']) && $settings['vt_enable_promotions'] === 'yes') {
            // Only enable preview mode when in Elementor editor, not on frontend
            $is_elementor_editor = (
                isset($_GET['elementor-preview']) ||
                (isset($_GET['action']) && $_GET['action'] === 'elementor') ||
                (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) ||
                (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->preview->is_preview_mode())
            );
            $preview_enabled = $is_elementor_editor && isset($settings['vt_promotions_preview']) && $settings['vt_promotions_preview'] === 'yes';

            $this->promotion_widgets[$widget->get_id()] = array(
                'enabled' => true,
                'title' => isset($settings['vt_promotions_title']) ? $settings['vt_promotions_title'] : __('Boost your listing', 'voxel-toolkit'),
                'description' => isset($settings['vt_promotions_description']) ? $settings['vt_promotions_description'] : __('Get more visibility with a promotion package (optional)', 'voxel-toolkit'),
                'skip_text' => isset($settings['vt_promotions_skip_text']) ? $settings['vt_promotions_skip_text'] : __('No thanks, just submit', 'voxel-toolkit'),
                'preview' => $preview_enabled,
            );
        }
    }

    /**
     * Render preview HTML directly in Elementor editor
     * Note: This hook doesn't fire for Voxel widgets, so preview is handled via JavaScript
     */
    public function render_elementor_preview($widget) {
        // Voxel's Create Post widget doesn't trigger standard Elementor hooks
        // Preview is handled via JavaScript in render_frontend_script() instead
        return;
    }

    /**
     * Add "Enable Promotions" controls to Create Post widget
     */
    public function add_widget_controls($element, $args) {
        $element->start_controls_section(
            'vt_promotions_section',
            array(
                'label' => __('Promotions (VT)', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $element->add_control(
            'vt_enable_promotions',
            array(
                'label' => __('Enable Promotions', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Show promotion packages at the end of the form before submit.', 'voxel-toolkit'),
            )
        );

        $element->add_control(
            'vt_promotions_title',
            array(
                'label' => __('Section Title', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Boost your listing', 'voxel-toolkit'),
                'condition' => array(
                    'vt_enable_promotions' => 'yes',
                ),
            )
        );

        $element->add_control(
            'vt_promotions_description',
            array(
                'label' => __('Section Description', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Get more visibility with a promotion package (optional)', 'voxel-toolkit'),
                'condition' => array(
                    'vt_enable_promotions' => 'yes',
                ),
            )
        );

        $element->add_control(
            'vt_promotions_skip_text',
            array(
                'label' => __('Skip Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('No thanks, just submit', 'voxel-toolkit'),
                'condition' => array(
                    'vt_enable_promotions' => 'yes',
                ),
            )
        );

        $element->add_control(
            'vt_promotions_preview',
            array(
                'label' => __('Preview Mode', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Show preview with dummy packages for styling in the editor.', 'voxel-toolkit'),
                'condition' => array(
                    'vt_enable_promotions' => 'yes',
                ),
            )
        );

        $element->end_controls_section();

        // Style Section
        $element->start_controls_section(
            'vt_promotions_style_section',
            array(
                'label' => __('Promotions Style (VT)', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => array(
                    'vt_enable_promotions' => 'yes',
                ),
            )
        );

        // Header Styles
        $element->add_control(
            'vt_promo_header_heading',
            array(
                'label' => __('Header', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
            )
        );

        $element->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'vt_promo_title_typography',
                'label' => __('Title Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-promotion-header-text h3',
            )
        );

        $element->add_control(
            'vt_promo_title_color',
            array(
                'label' => __('Title Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-promotion-header-text h3' => 'color: {{VALUE}};',
                ),
            )
        );

        $element->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'vt_promo_desc_typography',
                'label' => __('Description Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-promotion-header-text p',
            )
        );

        $element->add_control(
            'vt_promo_desc_color',
            array(
                'label' => __('Description Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-promotion-header-text p' => 'color: {{VALUE}};',
                ),
            )
        );

        $element->add_control(
            'vt_promo_icon_bg',
            array(
                'label' => __('Icon Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-promotion-icon-header' => 'background: {{VALUE}};',
                ),
            )
        );

        // Card Styles
        $element->add_control(
            'vt_promo_card_heading',
            array(
                'label' => __('Package Cards', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $element->add_control(
            'vt_promo_card_bg',
            array(
                'label' => __('Card Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-promotion-card' => 'background: {{VALUE}};',
                ),
            )
        );

        $element->add_control(
            'vt_promo_card_border_color',
            array(
                'label' => __('Card Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-promotion-card' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $element->add_control(
            'vt_promo_card_border_radius',
            array(
                'label' => __('Card Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 30,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-promotion-card' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $element->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'vt_promo_card_label_typography',
                'label' => __('Label Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-promotion-card-label',
            )
        );

        $element->add_control(
            'vt_promo_card_label_color',
            array(
                'label' => __('Label Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-promotion-card-label' => 'color: {{VALUE}};',
                ),
            )
        );

        $element->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'vt_promo_card_desc_typography',
                'label' => __('Card Description Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-promotion-card-desc',
            )
        );

        $element->add_control(
            'vt_promo_card_desc_color',
            array(
                'label' => __('Card Description Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-promotion-card-desc' => 'color: {{VALUE}};',
                ),
            )
        );

        $element->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'vt_promo_card_price_typography',
                'label' => __('Price Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-promotion-card-price',
            )
        );

        $element->add_control(
            'vt_promo_card_price_color',
            array(
                'label' => __('Price Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-promotion-card-price' => 'color: {{VALUE}};',
                ),
            )
        );

        $element->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'vt_promo_card_duration_typography',
                'label' => __('Duration Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-promotion-card-duration',
            )
        );

        // Skip Link
        $element->add_control(
            'vt_promo_skip_heading',
            array(
                'label' => __('Skip Link', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $element->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'vt_promo_skip_typography',
                'label' => __('Skip Link Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-promotion-skip',
            )
        );

        $element->add_control(
            'vt_promo_skip_color',
            array(
                'label' => __('Skip Link Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-promotion-skip' => 'color: {{VALUE}};',
                ),
            )
        );

        $element->add_control(
            'vt_promo_skip_hover_color',
            array(
                'label' => __('Skip Link Hover Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-promotion-skip:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $element->end_controls_section();
    }

    /**
     * Enqueue styles
     */
    public function enqueue_styles() {
        // Check if we're in Elementor editor
        $is_elementor = (
            (isset($_GET['action']) && $_GET['action'] === 'elementor') ||
            (isset($_GET['elementor-preview'])) ||
            (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) ||
            (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->preview->is_preview_mode())
        );

        // Only enqueue on pages that might have create-post widget or in Elementor
        if (!$is_elementor && !is_singular() && !is_page()) {
            return;
        }

        $css_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/assets/css/promotion-create-form.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'vt-promotion-create-form',
                VOXEL_TOOLKIT_PLUGIN_URL . 'includes/functions/assets/css/promotion-create-form.css',
                array(),
                VOXEL_TOOLKIT_VERSION
            );
        }
    }

    /**
     * Get promotion packages from Voxel settings
     */
    private function get_all_packages() {
        $paid_listings = get_option('voxel:paid_listings', '');

        // Voxel stores this as JSON string, not serialized PHP array
        if (is_string($paid_listings) && !empty($paid_listings)) {
            $paid_listings = json_decode($paid_listings, true);
        }

        if (empty($paid_listings) || !is_array($paid_listings)) {
            return array();
        }

        $settings = isset($paid_listings['settings']) ? $paid_listings['settings'] : array();
        $promotions = isset($settings['promotions']) ? $settings['promotions'] : array();
        $packages = isset($promotions['packages']) ? $promotions['packages'] : array();

        return $packages;
    }

    /**
     * Get packages filtered by post type
     */
    private function get_packages_for_post_type($post_type) {
        $all_packages = $this->get_all_packages();
        $filtered = array();

        foreach ($all_packages as $package) {
            // Check if package applies to this post type
            $package_post_types = isset($package['post_types']) ? $package['post_types'] : array();

            // If no post types specified, it applies to all
            if (empty($package_post_types) || in_array($post_type, $package_post_types)) {
                $filtered[] = array(
                    'key' => isset($package['key']) ? $package['key'] : '',
                    'label' => isset($package['ui']['label']) ? $package['ui']['label'] : __('Promotion', 'voxel-toolkit'),
                    'description' => isset($package['ui']['description']) ? $package['ui']['description'] : '',
                    'color' => isset($package['ui']['color']) ? $package['ui']['color'] : '#3b82f6',
                    'icon' => isset($package['ui']['icon']) ? $package['ui']['icon'] : '',
                    'price' => isset($package['price']['amount']) ? floatval($package['price']['amount']) : 0,
                    'currency' => $this->get_currency(),
                    'duration' => isset($package['duration']) ? $package['duration'] : array('type' => 'days', 'amount' => 7),
                    'priority' => isset($package['priority']) ? intval($package['priority']) : 1,
                );
            }
        }

        return $filtered;
    }

    /**
     * Get currency from Voxel settings
     */
    private function get_currency() {
        // Use Voxel's get_primary_currency function
        if (function_exists('\Voxel\get_primary_currency')) {
            $currency = \Voxel\get_primary_currency();
            if ($currency) {
                return $currency;
            }
        }

        // Fallback: try to read from voxel:payments option directly
        $payments = get_option('voxel:payments', '');
        if (is_string($payments) && !empty($payments)) {
            $payments = json_decode($payments, true);
        }
        if (is_array($payments) && isset($payments['stripe']['currency'])) {
            return $payments['stripe']['currency'];
        }

        // Ultimate fallback
        return 'USD';
    }

    /**
     * Get currency symbol
     */
    private function get_currency_symbol() {
        // Try Voxel's currency class first
        if (class_exists('\Voxel\Utils\Currency')) {
            $currency = $this->get_currency();
            $currency_data = \Voxel\Utils\Currency::get($currency);
            if ($currency_data && isset($currency_data['symbol'])) {
                return $currency_data['symbol'];
            }
        }

        // Fallback symbols
        $currency = $this->get_currency();
        $symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'JPY' => '¥',
            'CNY' => '¥',
            'CHF' => 'CHF',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr',
            'NZD' => 'NZ$',
            'SGD' => 'S$',
            'HKD' => 'HK$',
            'MXN' => 'MX$',
            'BRL' => 'R$',
            'INR' => '₹',
            'RUB' => '₽',
            'ZAR' => 'R',
            'PLN' => 'zł',
            'CZK' => 'Kč',
            'HUF' => 'Ft',
            'ILS' => '₪',
            'THB' => '฿',
            'MYR' => 'RM',
            'PHP' => '₱',
            'TWD' => 'NT$',
            'KRW' => '₩',
            'TRY' => '₺',
            'AED' => 'د.إ',
            'SAR' => '﷼',
        );

        if (isset($symbols[strtoupper($currency)])) {
            return $symbols[strtoupper($currency)];
        }

        // Return currency code as fallback
        return strtoupper($currency);
    }

    /**
     * Format price with currency
     */
    private function format_price($amount, $currency = null) {
        if ($currency === null) {
            $currency = $this->get_currency();
        }

        // Use Voxel's currency_format function (amount is NOT in cents)
        if (function_exists('\Voxel\currency_format')) {
            return \Voxel\currency_format($amount, $currency, false);
        }

        // Fallback: Check if zero-decimal currency
        $zero_decimal_currencies = array(
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
            'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'
        );
        $is_zero_decimal = in_array(strtoupper($currency), $zero_decimal_currencies);

        // Get symbol
        $symbol = $this->get_currency_symbol();

        // Format based on currency type
        if ($is_zero_decimal) {
            return $symbol . ' ' . number_format($amount, 0);
        }

        return $symbol . number_format($amount, 2);
    }

    /**
     * AJAX handler to get promotion packages for a post type
     */
    public function ajax_get_packages() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vt_promotion_packages')) {
            wp_send_json_error(array('message' => __('Invalid security token', 'voxel-toolkit')));
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';

        if (empty($post_type)) {
            wp_send_json_error(array('message' => __('Post type is required', 'voxel-toolkit')));
        }

        $packages = $this->get_packages_for_post_type($post_type);

        // Format prices
        foreach ($packages as &$package) {
            $package['formatted_price'] = $this->format_price($package['price'], $package['currency']);
        }

        wp_send_json_success(array(
            'packages' => $packages,
        ));
    }

    /**
     * Render frontend JavaScript
     */
    public function render_frontend_script() {
        // Only on frontend
        if (is_admin()) {
            return;
        }

        // Check if we're in Elementor editor
        $is_elementor = (
            isset($_GET['elementor-preview']) ||
            (isset($_GET['action']) && $_GET['action'] === 'elementor') ||
            (class_exists('\Elementor\Plugin') && (
                \Elementor\Plugin::$instance->editor->is_edit_mode() ||
                \Elementor\Plugin::$instance->preview->is_preview_mode()
            ))
        );

        // Don't output if no widgets with promotions enabled (unless in Elementor for debugging)
        if (empty($this->promotion_widgets) && !$is_elementor) {
            return;
        }

        $nonce = wp_create_nonce('vt_promotion_packages');
        $vx_checkout_nonce = wp_create_nonce('vx_checkout');
        $ajax_url = admin_url('admin-ajax.php');
        // Voxel uses its own AJAX system at /?vx=1
        $voxel_ajax_url = add_query_arg('vx', '1', home_url('/'));
        $widgets_config = wp_json_encode($this->promotion_widgets);
        $currency = $this->get_currency();
        $currency_symbol = $this->get_currency_symbol();
        $zero_decimal_currencies = array('BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF');
        $is_zero_decimal = in_array(strtoupper($currency), $zero_decimal_currencies);
        ?>
        <script>
        (function() {
            'use strict';

            // Widget configurations from PHP
            const vtPromotionWidgets = <?php echo $widgets_config; ?>;
            const vtCurrencySymbol = '<?php echo esc_js($currency_symbol); ?>';
            const vtIsZeroDecimal = <?php echo $is_zero_decimal ? 'true' : 'false'; ?>;

            // Store selected promotion globally
            window.vtSelectedPromotion = null;

            // Initialize when DOM is ready
            function initPromotionSelector() {
                // Check if we're in Elementor editor
                const isElementorEditor = window.location.href.includes('elementor-preview') ||
                                          window.location.href.includes('action=elementor') ||
                                          document.body.classList.contains('elementor-editor-active');

                // Get the correct document (may be in iframe for Elementor editor)
                let targetDoc = document;
                if (window.location.href.includes('action=elementor')) {
                    // We're in the main editor, look for preview iframe
                    const previewFrame = document.getElementById('elementor-preview-iframe');
                    if (previewFrame && previewFrame.contentDocument) {
                        targetDoc = previewFrame.contentDocument;
                    }
                }

                // Find all create-post widgets - try multiple selectors
                let widgets = targetDoc.querySelectorAll('.elementor-widget-ts-create-post');
                if (widgets.length === 0) {
                    widgets = targetDoc.querySelectorAll('[data-widget_type="ts-create-post.default"]');
                }
                if (widgets.length === 0) {
                    // Try finding by the form class
                    const forms = targetDoc.querySelectorAll('.ts-create-post');
                    widgets = [];
                    forms.forEach(form => {
                        const widget = form.closest('.elementor-widget');
                        if (widget) widgets.push(widget);
                    });
                }

                // Elementor editor preview mode - inject dummy preview for styling
                if (isElementorEditor && Object.keys(vtPromotionWidgets).length === 0) {
                    // Find widget containers in targetDoc
                    const widgetContainers = targetDoc.querySelectorAll('.elementor-widget-ts-create-post, [data-widget_type*="ts-create-post"]');

                    widgetContainers.forEach(container => {
                        const widgetId = container.dataset.id;

                        // Check if promotions AND preview mode are enabled in widget settings via Elementor API
                        let shouldShowPreview = false;
                        if (window.elementor && widgetId) {
                            try {
                                const widgetContainer = elementor.getContainer(widgetId);
                                if (widgetContainer && widgetContainer.settings) {
                                    const settings = widgetContainer.settings.attributes;
                                    const promotionsEnabled = settings.vt_enable_promotions === 'yes';
                                    const previewEnabled = settings.vt_promotions_preview === 'yes';
                                    shouldShowPreview = promotionsEnabled && previewEnabled;
                                }
                            } catch (e) {
                                // Could not get widget settings
                            }
                        }

                        // Only inject if both promotions AND preview mode are enabled
                        if (!shouldShowPreview) {
                            // Remove existing preview if preview was disabled
                            const existingPreview = container.querySelector('.vt-promotion-selector-container');
                            if (existingPreview) {
                                existingPreview.remove();
                            }
                            return;
                        }

                        // Don't inject if already exists
                        if (container.querySelector('.vt-promotion-selector-container')) {
                            return;
                        }

                        const previewConfig = {
                            title: '<?php echo esc_js(__('Boost your listing', 'voxel-toolkit')); ?>',
                            description: '<?php echo esc_js(__('Get more visibility with a promotion package (optional)', 'voxel-toolkit')); ?>',
                            skipText: '<?php echo esc_js(__('No thanks, just submit', 'voxel-toolkit')); ?>',
                            preview: true
                        };

                        const selector = createPromotionSelector(previewConfig, 'preview', targetDoc);
                        selector.style.display = 'block';

                        // Look for form inside and insert before footer
                        const innerForm = container.querySelector('.ts-create-post');
                        const footer = container.querySelector('.ts-form-footer');
                        if (footer) {
                            footer.parentNode.insertBefore(selector, footer);
                        } else if (innerForm) {
                            innerForm.appendChild(selector);
                        } else {
                            container.appendChild(selector);
                        }
                        loadPackages(selector, 'preview', true);
                    });

                    return;
                }

                widgets.forEach(widget => {
                    // Get the widget ID from the element
                    const widgetId = widget.dataset.id;
                    let vtSettings = vtPromotionWidgets[widgetId];

                    if (!vtSettings || !vtSettings.enabled) {
                        return;
                    }

                    // Configuration from PHP-captured settings
                    const vtConfig = {
                        title: vtSettings.title || '<?php echo esc_js(__('Boost your listing', 'voxel-toolkit')); ?>',
                        description: vtSettings.description || '<?php echo esc_js(__('Get more visibility with a promotion package (optional)', 'voxel-toolkit')); ?>',
                        skipText: vtSettings.skip_text || '<?php echo esc_js(__('No thanks, just submit', 'voxel-toolkit')); ?>',
                        preview: vtSettings.preview || false,
                    };

                    // Get the form element
                    const form = widget.querySelector('.ts-create-post');

                    // In preview mode without form, inject directly into widget
                    if (vtConfig.preview && !form) {
                        if (!widget.querySelector('.vt-promotion-selector-container')) {
                            const selector = createPromotionSelector(vtConfig, 'preview');
                            selector.style.display = 'block';
                            widget.appendChild(selector);
                            loadPackages(selector, 'preview', true);
                        }
                        return;
                    }

                    // In preview mode WITH form, still inject
                    if (vtConfig.preview) {
                        if (!widget.querySelector('.vt-promotion-selector-container')) {
                            const selector = createPromotionSelector(vtConfig, 'preview');
                            selector.style.display = 'block';
                            form.appendChild(selector);
                            loadPackages(selector, 'preview', true);
                        }
                        return;
                    }

                    if (!form) return;

                    // Get config from vxconfig script
                    const configScript = widget.querySelector('script.vxconfig');
                    let config = {};
                    if (configScript) {
                        try {
                            config = JSON.parse(configScript.textContent || '{}');
                        } catch (e) {}
                    }

                    const postType = config.post_type?.key || '';

                    // In preview mode, we don't need a real post type
                    if (!postType && !vtConfig.preview) return;

                    // Create and inject the promotion selector
                    const selector = createPromotionSelector(vtConfig, postType || 'preview');
                    const formFooter = form.querySelector('.ts-form-footer');

                    if (formFooter && !form.querySelector('.vt-promotion-selector-container')) {
                        formFooter.parentNode.insertBefore(selector, formFooter);

                        // Load packages (or dummy data in preview mode)
                        loadPackages(selector, postType, vtConfig.preview);

                        // In preview mode, always show the selector
                        if (vtConfig.preview) {
                            selector.style.display = 'block';
                        } else {
                            // Watch for step changes to show/hide
                            observeStepChanges(form, selector, config);
                        }
                    } else if (vtConfig.preview && !widget.querySelector('.vt-promotion-selector-container')) {
                        // Preview mode fallback - append to form if no footer found
                        selector.style.display = 'block';
                        form.appendChild(selector);
                        loadPackages(selector, 'preview', true);
                    }
                });
            }

            function createPromotionSelector(vtConfig, postType, targetDocument = document) {
                const container = targetDocument.createElement('div');
                container.className = 'vt-promotion-selector-container';
                container.style.display = 'none'; // Hidden by default until last step
                container.dataset.postType = postType;

                container.innerHTML = `
                    <div class="vt-promotion-selector">
                        <div class="vt-promotion-header">
                            <div class="vt-promotion-icon-header">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                                </svg>
                            </div>
                            <div class="vt-promotion-header-text">
                                <h3>${escapeHtml(vtConfig.title)}</h3>
                                <p>${escapeHtml(vtConfig.description)}</p>
                            </div>
                        </div>

                        <div class="vt-promotion-packages">
                            <div class="vt-promotion-loading">
                                <span class="vt-spinner"></span>
                                <span><?php echo esc_js(__('Loading packages...', 'voxel-toolkit')); ?></span>
                            </div>
                        </div>

                        <a href="#" class="vt-promotion-skip">${escapeHtml(vtConfig.skipText)}</a>
                    </div>
                `;

                // Skip button click handler
                container.querySelector('.vt-promotion-skip').addEventListener('click', function(e) {
                    e.preventDefault();
                    window.vtSelectedPromotion = null;
                    // Deselect all cards
                    container.querySelectorAll('.vt-promotion-card').forEach(card => {
                        card.classList.remove('is-selected');
                    });
                });

                return container;
            }

            function loadPackages(container, postType, isPreview) {
                const packagesContainer = container.querySelector('.vt-promotion-packages');

                // Preview mode - use dummy packages for styling
                if (isPreview) {
                    // Format price based on currency type
                    function formatPreviewPrice(amount) {
                        if (vtIsZeroDecimal) {
                            return vtCurrencySymbol + ' ' + Math.round(amount);
                        }
                        return vtCurrencySymbol + amount.toFixed(2);
                    }

                    const dummyPackages = [
                        {
                            key: 'preview-basic',
                            label: '<?php echo esc_js(__('Basic', 'voxel-toolkit')); ?>',
                            description: '<?php echo esc_js(__('Great for getting started', 'voxel-toolkit')); ?>',
                            duration: { type: 'days', amount: 7 },
                            formatted_price: formatPreviewPrice(10),
                            color: '#3b82f6'
                        },
                        {
                            key: 'preview-pro',
                            label: '<?php echo esc_js(__('Pro', 'voxel-toolkit')); ?>',
                            description: '<?php echo esc_js(__('Maximum visibility for your listing', 'voxel-toolkit')); ?>',
                            duration: { type: 'days', amount: 30 },
                            formatted_price: formatPreviewPrice(30),
                            color: '#8b5cf6'
                        },
                        {
                            key: 'preview-premium',
                            label: '<?php echo esc_js(__('Premium', 'voxel-toolkit')); ?>',
                            description: '<?php echo esc_js(__('Best value for serious sellers', 'voxel-toolkit')); ?>',
                            duration: { type: 'days', amount: 90 },
                            formatted_price: formatPreviewPrice(80),
                            color: '#f59e0b'
                        }
                    ];

                    packagesContainer.innerHTML = '';
                    dummyPackages.forEach(pkg => {
                        const card = createPackageCard(pkg);
                        packagesContainer.appendChild(card);
                    });
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'vt_get_promotion_packages');
                formData.append('post_type', postType);
                formData.append('nonce', '<?php echo esc_js($nonce); ?>');

                fetch('<?php echo esc_js($ajax_url); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.packages && data.data.packages.length > 0) {
                        packagesContainer.innerHTML = '';

                        data.data.packages.forEach(pkg => {
                            const card = createPackageCard(pkg);
                            packagesContainer.appendChild(card);
                        });
                    } else {
                        // No packages - hide the entire selector
                        container.style.display = 'none';
                        container.dataset.noPackages = 'true';
                    }
                })
                .catch(err => {
                    console.error('Error loading promotion packages:', err);
                    container.style.display = 'none';
                });
            }

            function createPackageCard(pkg) {
                const card = document.createElement('div');
                card.className = 'vt-promotion-card';
                card.dataset.packageKey = pkg.key;
                card.style.setProperty('--vt-accent-color', pkg.color || '#3b82f6');

                const duration = formatDuration(pkg.duration);

                card.innerHTML = `
                    <div class="vt-promotion-card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                    </div>
                    <div class="vt-promotion-card-info">
                        <div class="vt-promotion-card-label">${escapeHtml(pkg.label)}</div>
                        ${pkg.description ? `<div class="vt-promotion-card-desc">${escapeHtml(pkg.description)}</div>` : ''}
                        <div class="vt-promotion-card-duration">${duration}</div>
                    </div>
                    <div class="vt-promotion-card-price">${escapeHtml(pkg.formatted_price)}</div>
                    <div class="vt-promotion-card-check">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                `;

                card.addEventListener('click', function() {
                    const isSelected = card.classList.contains('is-selected');

                    // Deselect all cards
                    card.parentNode.querySelectorAll('.vt-promotion-card').forEach(c => {
                        c.classList.remove('is-selected');
                    });

                    if (isSelected) {
                        window.vtSelectedPromotion = null;
                    } else {
                        card.classList.add('is-selected');
                        window.vtSelectedPromotion = pkg;
                    }
                });

                return card;
            }

            function formatDuration(duration) {
                if (!duration) return '';
                const amount = duration.amount || 7;
                const type = duration.type || 'days';

                if (type === 'days') {
                    return amount === 1 ? '<?php echo esc_js(__('1 day', 'voxel-toolkit')); ?>' : amount + ' <?php echo esc_js(__('days', 'voxel-toolkit')); ?>';
                } else if (type === 'weeks') {
                    return amount === 1 ? '<?php echo esc_js(__('1 week', 'voxel-toolkit')); ?>' : amount + ' <?php echo esc_js(__('weeks', 'voxel-toolkit')); ?>';
                } else if (type === 'months') {
                    return amount === 1 ? '<?php echo esc_js(__('1 month', 'voxel-toolkit')); ?>' : amount + ' <?php echo esc_js(__('months', 'voxel-toolkit')); ?>';
                }
                return '';
            }

            function observeStepChanges(form, selector, config) {
                // Get total steps from progress indicators
                const updateVisibility = () => {
                    if (selector.dataset.noPackages === 'true') return;

                    const stepIndicators = form.querySelectorAll('.step-percentage li');
                    const doneSteps = form.querySelectorAll('.step-percentage li.step-done');

                    // Check if on last step - look for visible submit button
                    const submitBtn = form.querySelector('.ts-save-changes:not([style*="display: none"])');
                    const nextBtn = form.querySelector('.ts-next');
                    const isLastStep = submitBtn && (!nextBtn || nextBtn.classList.contains('disabled'));

                    // Also check by step count
                    const totalSteps = stepIndicators.length;
                    const currentStep = doneSteps.length;

                    if (isLastStep || currentStep >= totalSteps) {
                        selector.style.display = 'block';
                    } else {
                        selector.style.display = 'none';
                    }
                };

                // Initial check
                setTimeout(updateVisibility, 500);

                // Watch for DOM changes (step navigation)
                const observer = new MutationObserver(() => {
                    updateVisibility();
                });

                observer.observe(form, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class', 'style']
                });

                // Also listen for click events on navigation buttons
                form.addEventListener('click', function(e) {
                    if (e.target.closest('.ts-next, .ts-prev, .step-percentage li')) {
                        setTimeout(updateVisibility, 100);
                    }
                });
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Hook into form submission to redirect to checkout
            function setupSubmitInterception() {
                document.addEventListener('click', function(e) {
                    const submitBtn = e.target.closest('.ts-save-changes');
                    if (!submitBtn || !window.vtSelectedPromotion) return;

                    // Store the selected promotion to use after submission
                    const selectedPromotion = window.vtSelectedPromotion;

                    // Watch for success screen
                    const form = submitBtn.closest('.ts-create-post');
                    if (!form) return;

                    const observer = new MutationObserver((mutations, obs) => {
                        const successScreen = form.querySelector('.ts-edit-success');
                        if (successScreen) {
                            obs.disconnect();

                            // Get the post ID from the edit/back link (format: ?post_id=123)
                            const editLink = successScreen.querySelector('a[href*="post_id="]');
                            let postId = null;

                            if (editLink) {
                                const match = editLink.href.match(/[?&]post_id=(\d+)/);
                                if (match) postId = match[1];
                            }

                            if (postId && selectedPromotion) {
                                redirectToPromoCheckout(postId, selectedPromotion.key);
                            }
                        }
                    });

                    observer.observe(form, { childList: true, subtree: true });

                    // Disconnect after timeout
                    setTimeout(() => observer.disconnect(), 30000);
                });
            }

            function redirectToPromoCheckout(postId, promotionKey) {
                // Voxel uses its own AJAX system at /?vx=1&action=...
                const voxelAjaxUrl = '<?php echo esc_js($voxel_ajax_url); ?>';
                const checkoutUrl = voxelAjaxUrl + '&action=products.promotions.checkout&_wpnonce=<?php echo esc_js($vx_checkout_nonce); ?>';

                const formData = new FormData();
                formData.append('post_id', postId);
                formData.append('promotion_package', promotionKey);

                fetch(checkoutUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        // Fallback to cart-summary page with package param for auto-select
                        const cartUrl = new URL('<?php echo esc_js(home_url('/cart-summary/')); ?>');
                        cartUrl.searchParams.set('screen', 'promote');
                        cartUrl.searchParams.set('post_id', postId);
                        cartUrl.searchParams.set('vt_package', promotionKey);
                        window.location.href = cartUrl.toString();
                    }
                })
                .catch(() => {
                    // Fallback to cart-summary page with package param for auto-select
                    const cartUrl = new URL('<?php echo esc_js(home_url('/cart-summary/')); ?>');
                    cartUrl.searchParams.set('screen', 'promote');
                    cartUrl.searchParams.set('post_id', postId);
                    cartUrl.searchParams.set('vt_package', promotionKey);
                    window.location.href = cartUrl.toString();
                });
            }

            // Initialize with retries for Elementor editor
            let initAttempts = 0;
            const maxAttempts = 10;

            function tryInit() {
                initAttempts++;
                initPromotionSelector();

                // Check if we successfully injected (in Elementor editor mode)
                const isElementorEditor = window.location.href.includes('elementor-preview') ||
                                          document.body.classList.contains('elementor-editor-active');
                const hasInjected = document.querySelector('.vt-promotion-selector-container');

                if (isElementorEditor && !hasInjected && initAttempts < maxAttempts) {
                    // Retry with increasing delay
                    setTimeout(tryInit, 500 * initAttempts);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    tryInit();
                    setupSubmitInterception();
                });
            } else {
                tryInit();
                setupSubmitInterception();
            }

            // Also re-init on Elementor frontend init (for preview)
            document.addEventListener('elementor/init', initPromotionSelector);

            // Watch for DOM changes in Elementor editor
            if (window.location.href.includes('elementor-preview')) {
                const observer = new MutationObserver((mutations) => {
                    // Check if ts-create-post was added
                    const hasCreatePost = document.querySelector('.ts-create-post, .elementor-widget-ts-create-post');
                    const hasInjected = document.querySelector('.vt-promotion-selector-container');
                    if (hasCreatePost && !hasInjected) {
                        initPromotionSelector();
                    }
                });
                observer.observe(document.body, { childList: true, subtree: true });
            }

            // For Elementor main editor - watch iframe for content changes
            if (window.location.href.includes('action=elementor')) {
                // Wait for iframe to be ready and observe it
                const checkIframe = setInterval(() => {
                    const previewFrame = document.getElementById('elementor-preview-iframe');
                    if (previewFrame && previewFrame.contentDocument && previewFrame.contentDocument.body) {
                        clearInterval(checkIframe);
                        const iframeObserver = new MutationObserver((mutations) => {
                            const iframeDoc = previewFrame.contentDocument;
                            const hasCreatePost = iframeDoc.querySelector('.ts-create-post, .elementor-widget-ts-create-post');
                            const hasInjected = iframeDoc.querySelector('.vt-promotion-selector-container');
                            if (hasCreatePost && !hasInjected) {
                                initPromotionSelector();
                            }
                        });
                        iframeObserver.observe(previewFrame.contentDocument.body, { childList: true, subtree: true });
                        // Also run init once iframe is ready
                        initPromotionSelector();
                    }
                }, 500);
            }
        })();
        </script>
        <?php
    }

    /**
     * Auto-select package on cart-summary promotion screen
     * This runs when user is redirected to cart-summary with vt_package URL param
     */
    public function render_cart_summary_autoselect() {
        // Only on frontend
        if (is_admin()) {
            return;
        }

        // Check if we have the required URL parameters
        $screen = isset($_GET['screen']) ? sanitize_text_field($_GET['screen']) : '';
        $vt_package = isset($_GET['vt_package']) ? sanitize_text_field($_GET['vt_package']) : '';

        // Only output if on promote screen with our package parameter
        if ($screen !== 'promote' || empty($vt_package)) {
            return;
        }
        ?>
        <script>
        (function() {
            'use strict';

            const packageKey = '<?php echo esc_js($vt_package); ?>';
            let attempts = 0;
            const maxAttempts = 50; // 5 seconds max

            function autoSelectPackage() {
                attempts++;

                // Find the promotion checkout container
                const promotionEl = document.querySelector('.ts-checkout-promotion');
                if (!promotionEl) {
                    if (attempts < maxAttempts) {
                        setTimeout(autoSelectPackage, 100);
                    }
                    return;
                }

                // Find the vxconfig script with package data
                const configScript = promotionEl.closest('.elementor-element')?.querySelector('script.vxconfig');
                if (!configScript) {
                    if (attempts < maxAttempts) {
                        setTimeout(autoSelectPackage, 100);
                    }
                    return;
                }

                let config;
                try {
                    config = JSON.parse(configScript.textContent);
                } catch(e) {
                    return;
                }

                // Find the package cards (li elements in addon-cards)
                const packageCards = promotionEl.querySelectorAll('.addon-cards li');
                if (packageCards.length === 0) {
                    if (attempts < maxAttempts) {
                        setTimeout(autoSelectPackage, 100);
                    }
                    return;
                }

                // Find which index matches our package key
                const packages = config.packages || {};
                const packageKeys = Object.keys(packages);
                let targetIndex = -1;

                for (let i = 0; i < packageKeys.length; i++) {
                    if (packages[packageKeys[i]].key === packageKey) {
                        targetIndex = i;
                        break;
                    }
                }

                if (targetIndex >= 0 && packageCards[targetIndex]) {
                    // Click on the correct package card to select it
                    packageCards[targetIndex].click();
                }
            }

            // Start trying after DOM is ready and Vue has time to render
            function init() {
                // Wait a bit for Vue to mount and render
                setTimeout(autoSelectPackage, 300);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
        <?php
    }

    /**
     * Render settings section (for admin settings page)
     */
    public static function render_settings() {
        ?>
        <tr>
            <th scope="row">
                <label><?php _e('Promotion Create Form', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <p class="description">
                    <?php _e('This feature adds promotion package selection to the Create Form widget.', 'voxel-toolkit'); ?>
                </p>
                <ul style="list-style: disc; margin-left: 20px; margin-top: 10px;">
                    <li><?php _e('Enable promotions per widget in Elementor settings', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Packages are filtered by post type automatically', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Users are redirected to checkout after post submission', 'voxel-toolkit'); ?></li>
                </ul>
            </td>
        </tr>
        <?php
    }
}
