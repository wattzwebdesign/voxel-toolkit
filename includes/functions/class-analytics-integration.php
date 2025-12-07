<?php
/**
 * Analytics Integration Function
 *
 * Tracks e-commerce events (orders, memberships, paid listings) in Google Analytics.
 * Uses client-side gtag.js for GA4 E-commerce Events.
 * Provider-based architecture for future Umami/Fathom support.
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if Voxel Base_Controller exists before defining class
// This prevents fatal errors during WP-CLI operations (cPanel, staging/live pushes, etc.)
if (!class_exists('\Voxel\Controllers\Base_Controller')) {
    return;
}

class Voxel_Toolkit_Analytics_Integration extends \Voxel\Controllers\Base_Controller {

    /**
     * Singleton instance
     *
     * @var Voxel_Toolkit_Analytics_Integration|null
     */
    private static $instance = null;

    /**
     * Settings instance
     *
     * @var Voxel_Toolkit_Settings
     */
    private $settings;

    /**
     * Function enabled status
     *
     * @var bool
     */
    private $enabled = false;

    /**
     * Function settings
     *
     * @var array
     */
    private $function_settings = array();

    /**
     * Registered analytics providers
     *
     * @var array
     */
    private $providers = array();

    /**
     * Queued tracking events to output in footer
     *
     * @var array
     */
    private $queued_events = array();

    /**
     * Get singleton instance
     *
     * @return Voxel_Toolkit_Analytics_Integration
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
    public function __construct() {
        $this->settings = Voxel_Toolkit_Settings::instance();
        $this->load_settings();

        if ($this->enabled) {
            // Load provider classes
            $this->load_providers();

            // Initialize parent (registers Voxel hooks)
            parent::__construct();

            // Check for pending tracking on page load (handles AJAX-created events)
            add_action('wp_footer', array($this, 'maybe_track_pending_order'), 98);
            add_action('wp_footer', array($this, 'maybe_track_pending_membership'), 98);

            // Output tracking scripts in footer
            add_action('wp_footer', array($this, 'output_tracking_scripts'), 99);
        }

        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }

    /**
     * Load settings
     */
    private function load_settings() {
        $this->function_settings = $this->settings->get_function_settings('analytics_integration', array(
            'enabled' => false,
            'track_orders' => true,
            'track_memberships' => true,
            'track_listings' => true,
            'debug_mode' => false,
        ));

        $this->enabled = isset($this->function_settings['enabled']) ? (bool) $this->function_settings['enabled'] : false;
    }

    /**
     * Handle settings updates
     *
     * @param array $old_settings Old settings
     * @param array $new_settings New settings
     */
    public function on_settings_updated($old_settings, $new_settings) {
        $this->load_settings();

        if ($this->enabled) {
            $this->load_providers();
            parent::__construct();
        }
    }

    /**
     * Load analytics provider classes
     */
    private function load_providers() {
        // Load base provider
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/integrations/analytics/class-analytics-provider-base.php';

        // Load Google Analytics provider
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/integrations/analytics/class-google-analytics-provider.php';

        // Get GA4 Measurement ID from the "Google Analytics & Custom Tags" function
        $ga_measurement_id = self::get_shared_ga4_measurement_id();

        // Initialize providers with their settings
        $ga_settings = array(
            'measurement_id' => $ga_measurement_id,
            'debug_mode' => isset($this->function_settings['debug_mode']) ? $this->function_settings['debug_mode'] : false,
        );

        $this->providers['google_analytics'] = new Voxel_Toolkit_Google_Analytics_Provider($ga_settings);
    }

    /**
     * Hook registration for Voxel events
     *
     * Uses correct Voxel hooks discovered from fluent-affiliate integration:
     * - Orders: voxel/product-types/orders/order:updated (receives Order object)
     * - Memberships: voxel/app-events/membership/plan:activated (receives Event object)
     */
    protected function hooks() {
        // Track order status changes - fires when order transitions to 'completed'
        // Using Voxel's $this->on() method with @ prefix for method reference
        if ($this->should_track('orders')) {
            $this->on('voxel/product-types/orders/order:updated', '@handle_order_updated', 99, 1);
        }

        // Track membership activations
        if ($this->should_track('memberships')) {
            $this->on('voxel/app-events/membership/plan:activated', '@handle_membership_activated', 10, 1);
        }
    }

    /**
     * Check if we should track a specific event type
     *
     * @param string $type Event type (orders, memberships, listings)
     * @return bool
     */
    private function should_track($type) {
        $key = 'track_' . $type;
        return isset($this->function_settings[$key]) ? (bool) $this->function_settings[$key] : true;
    }

    /**
     * Handle order status updates from Voxel
     *
     * Fires when order status changes. We only track when status becomes 'completed'.
     * Hook: voxel/product-types/orders/order:updated
     *
     * @param \Voxel\Product_Types\Orders\Order $order Voxel Order object
     */
    protected function handle_order_updated($order) {
        if (!$this->enabled) {
            return;
        }

        // Only track when status changes to 'completed'
        $current_status = $order->get_status();
        $prev_status = $order->get_previous_status();

        if ($current_status !== 'completed') {
            return;
        }

        // Prevent duplicate tracking (status was already completed)
        if ($prev_status === 'completed') {
            return;
        }

        $user_id = $order->get_customer_id();
        if (!$user_id) {
            return;
        }

        $debug_mode = !empty($this->function_settings['debug_mode']);

        // Build purchase data using Voxel Order object methods
        try {
            // Note: Voxel order amounts are already in dollars (not cents like memberships)
            $items = array();
            $order_items = $order->get_items();

            foreach ($order_items as $item) {
                // Get item details safely
                $item_id = method_exists($item, 'get_id') ? $item->get_id() : 'unknown';
                $item_type = method_exists($item, 'get_type') ? $item->get_type() : 'Product';
                $item_subtotal = method_exists($item, 'get_subtotal') ? $item->get_subtotal() : 0;

                $items[] = array(
                    'item_id' => 'product_' . $item_id,
                    'item_name' => $item_type,
                    'item_category' => 'Orders',
                    'price' => floatval($item_subtotal),
                    'quantity' => 1,
                );
            }

            $purchase_data = array(
                'transaction_id' => 'order_' . $order->get_id(),
                'value' => floatval($order->get_total()),
                'currency' => $order->get_currency(),
                'items' => $items,
            );

            // Store pre-formatted purchase data in transient for output on next page load
            $transient_key = 'vt_analytics_order_' . $user_id;
            set_transient($transient_key, $purchase_data, 5 * MINUTE_IN_SECONDS);

            if ($debug_mode) {
                error_log('[Voxel Toolkit Analytics] Stored order #' . $order->get_id() . ' purchase data for user #' . $user_id);
                error_log('[Voxel Toolkit Analytics] Purchase data: ' . wp_json_encode($purchase_data));
            }

        } catch (\Exception $e) {
            error_log('[Voxel Toolkit Analytics] ERROR: Exception while processing order: ' . $e->getMessage());
        } catch (\Error $e) {
            error_log('[Voxel Toolkit Analytics] ERROR: Fatal error while processing order: ' . $e->getMessage());
        }
    }

    /**
     * Check for pending order tracking on page load
     *
     * Called in wp_footer to check if there's a pending order to track.
     * The transient now contains pre-formatted purchase data (not just order ID).
     */
    public function maybe_track_pending_order() {
        if (!$this->enabled) {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        // Check for pending order purchase data in transient
        $transient_key = 'vt_analytics_order_' . $user_id;
        $purchase_data = get_transient($transient_key);

        if (!$purchase_data || !is_array($purchase_data)) {
            return;
        }

        // Delete transient immediately to prevent duplicate tracking
        delete_transient($transient_key);

        if (!empty($this->function_settings['debug_mode'])) {
            error_log('[Voxel Toolkit Analytics] Found pending order purchase for user #' . $user_id);
            error_log('[Voxel Toolkit Analytics] Purchase data: ' . wp_json_encode($purchase_data));
        }

        // Queue the pre-formatted purchase data
        $this->queue_purchase_event($purchase_data);
    }

    /**
     * Handle membership plan activation from Voxel
     *
     * Hook: voxel/app-events/membership/plan:activated
     *
     * @param object $event Event object with user and membership properties
     */
    protected function handle_membership_activated($event) {
        if (!$this->enabled) {
            return;
        }

        // Get user ID from event
        $user_id = isset($event->user) && method_exists($event->user, 'get_id')
            ? $event->user->get_id()
            : 0;

        if (!$user_id) {
            return;
        }

        // Get membership from event
        $membership = isset($event->membership) ? $event->membership : null;

        if (!$membership) {
            return;
        }

        // Get amount in cents
        $amount = method_exists($membership, 'get_amount') ? $membership->get_amount() : 0;

        // Skip if free plan (no amount or zero)
        if (!$amount || $amount <= 0) {
            if (!empty($this->function_settings['debug_mode'])) {
                error_log('[Voxel Toolkit Analytics] Skipping free membership for user #' . $user_id);
            }
            return;
        }

        // Get currency
        $currency = method_exists($membership, 'get_currency') ? $membership->get_currency() : 'USD';

        // Get plan details
        $plan_key = isset($membership->plan) && method_exists($membership->plan, 'get_key')
            ? $membership->plan->get_key()
            : 'unknown';
        $plan_label = isset($membership->plan) && method_exists($membership->plan, 'get_label')
            ? $membership->plan->get_label()
            : 'Membership Plan';

        // Build purchase data (convert cents to dollars)
        $purchase_data = array(
            'transaction_id' => 'membership_' . $user_id . '_' . time(),
            'value' => round($amount / 100, 2), // cents to dollars
            'currency' => $currency,
            'items' => array(
                array(
                    'item_id' => 'plan_' . $plan_key,
                    'item_name' => $plan_label,
                    'item_category' => 'Memberships',
                    'price' => round($amount / 100, 2),
                    'quantity' => 1,
                ),
            ),
        );

        // Store pre-formatted purchase data in transient for output on next page load
        $transient_key = 'vt_analytics_membership_' . $user_id;
        set_transient($transient_key, $purchase_data, 5 * MINUTE_IN_SECONDS);

        if (!empty($this->function_settings['debug_mode'])) {
            error_log('[Voxel Toolkit Analytics] Stored membership purchase data for user #' . $user_id);
            error_log('[Voxel Toolkit Analytics] Purchase data: ' . wp_json_encode($purchase_data));
        }
    }

    /**
     * Check for pending membership tracking on page load
     */
    public function maybe_track_pending_membership() {
        if (!$this->enabled) {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        // Check for pending membership purchase data in transient
        $transient_key = 'vt_analytics_membership_' . $user_id;
        $purchase_data = get_transient($transient_key);

        if (!$purchase_data || !is_array($purchase_data)) {
            return;
        }

        // Delete transient immediately to prevent duplicate tracking
        delete_transient($transient_key);

        if (!empty($this->function_settings['debug_mode'])) {
            error_log('[Voxel Toolkit Analytics] Found pending membership purchase for user #' . $user_id);
        }

        // Queue the pre-formatted purchase data
        $this->queue_purchase_event($purchase_data);
    }

    /**
     * Queue a purchase event for output in footer
     *
     * @param array $data Purchase data
     */
    private function queue_purchase_event($data) {
        $this->queued_events[] = $data;
    }

    /**
     * Output tracking scripts in wp_footer
     */
    public function output_tracking_scripts() {
        if (empty($this->queued_events)) {
            return;
        }

        foreach ($this->queued_events as $event_data) {
            foreach ($this->providers as $provider) {
                if (!$provider->is_configured()) {
                    continue;
                }

                $script = $provider->get_purchase_script($event_data);

                if (!empty($script)) {
                    echo '<script>' . "\n";
                    echo '/* Voxel Toolkit Analytics - ' . esc_html($provider->get_label()) . ' */' . "\n";
                    echo $script . "\n";
                    echo '</script>' . "\n";
                }
            }
        }

        // Clear queued events
        $this->queued_events = array();
    }

    /**
     * Get available analytics providers
     *
     * @return array
     */
    public static function get_available_providers() {
        return array(
            'google_analytics' => array(
                'label' => __('Google Analytics 4', 'voxel-toolkit'),
                'available' => true,
            ),
            'umami' => array(
                'label' => __('Umami Analytics', 'voxel-toolkit'),
                'available' => false,
                'status' => __('Coming Soon', 'voxel-toolkit'),
            ),
            'fathom' => array(
                'label' => __('Fathom Analytics', 'voxel-toolkit'),
                'available' => false,
                'status' => __('Coming Soon', 'voxel-toolkit'),
            ),
        );
    }

    /**
     * Get the GA4 Measurement ID from the "Google Analytics & Custom Tags" function
     *
     * @return string GA4 Measurement ID or empty string
     */
    public static function get_shared_ga4_measurement_id() {
        $voxel_toolkit_options = get_option('voxel_toolkit_options', array());

        // Check if google_analytics function has a GA4 ID configured
        if (isset($voxel_toolkit_options['google_analytics']['ga4_measurement_id'])) {
            return $voxel_toolkit_options['google_analytics']['ga4_measurement_id'];
        }

        return '';
    }

    /**
     * Check if the "Google Analytics & Custom Tags" function is enabled and configured
     *
     * @return array Status info with 'enabled', 'configured', 'measurement_id'
     */
    public static function get_ga4_status() {
        $voxel_toolkit_options = get_option('voxel_toolkit_options', array());

        $ga_enabled = isset($voxel_toolkit_options['google_analytics']['enabled'])
            ? (bool) $voxel_toolkit_options['google_analytics']['enabled']
            : false;

        $measurement_id = isset($voxel_toolkit_options['google_analytics']['ga4_measurement_id'])
            ? $voxel_toolkit_options['google_analytics']['ga4_measurement_id']
            : '';

        $is_valid = !empty($measurement_id) && preg_match('/^G-[A-Z0-9]+$/', $measurement_id);

        return array(
            'enabled' => $ga_enabled,
            'configured' => $ga_enabled && $is_valid,
            'measurement_id' => $measurement_id,
            'is_valid_format' => $is_valid,
        );
    }
}
