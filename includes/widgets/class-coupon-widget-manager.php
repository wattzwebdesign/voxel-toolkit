<?php
/**
 * Coupon Widget Manager
 *
 * Handles registration and AJAX handlers for Stripe coupon management widget
 *
 * @package Voxel_Toolkit
 * @since 1.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Voxel_Toolkit_Coupon_Widget_Manager
 *
 * Manages the Coupon widget registration and AJAX handlers
 */
class Voxel_Toolkit_Coupon_Widget_Manager {

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
        // Register the Elementor widget
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));

        // Register AJAX handlers (logged in users only)
        add_action('wp_ajax_voxel_toolkit_create_coupon', array($this, 'handle_create_coupon'));
        add_action('wp_ajax_voxel_toolkit_get_user_coupons', array($this, 'handle_get_user_coupons'));
        add_action('wp_ajax_voxel_toolkit_delete_coupon', array($this, 'handle_delete_coupon'));

        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Register the Elementor widget
     *
     * @param object $widgets_manager Elementor widgets manager
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-coupon-widget.php';
        $widgets_manager->register(new \Voxel_Toolkit_Coupon_Widget());
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        $css_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/css/coupon-widget.css';
        $js_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/js/coupon-widget.js';

        // Enqueue Pikaday (from Voxel theme)
        wp_enqueue_script('pikaday');
        wp_enqueue_style('pikaday');

        // Enqueue styles
        wp_enqueue_style(
            'voxel-coupon-widget',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/coupon-widget.css',
            array('pikaday'),
            file_exists($css_file) ? filemtime($css_file) : VOXEL_TOOLKIT_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'voxel-coupon-widget',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/coupon-widget.js',
            array('jquery', 'pikaday'),
            file_exists($js_file) ? filemtime($js_file) : VOXEL_TOOLKIT_VERSION,
            true
        );

        // Get currency from Voxel settings
        $currency = 'USD';
        if (function_exists('\Voxel\get')) {
            $currency = \Voxel\get('payments.stripe.currency', 'USD');
        }

        // Localize script
        wp_localize_script('voxel-coupon-widget', 'voxelCouponWidget', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('voxel_coupon_widget_nonce'),
            'currency' => $currency,
            'i18n' => array(
                'confirmDelete' => __('Are you sure you want to delete this coupon?', 'voxel-toolkit'),
                'creating' => __('Creating...', 'voxel-toolkit'),
                'deleting' => __('Deleting...', 'voxel-toolkit'),
                'loading' => __('Loading...', 'voxel-toolkit'),
                'noCoupons' => __('No coupons created yet.', 'voxel-toolkit'),
                'success' => __('Coupon created successfully!', 'voxel-toolkit'),
                'deleted' => __('Coupon deleted successfully!', 'voxel-toolkit'),
                'error' => __('An error occurred. Please try again.', 'voxel-toolkit'),
            ),
        ));
    }

    /**
     * Check if Stripe is the enabled payment provider
     *
     * @return bool
     */
    private function is_stripe_enabled() {
        if (!function_exists('\Voxel\get')) {
            return false;
        }
        $provider = \Voxel\get('payments.provider', '');
        return $provider === 'stripe';
    }

    /**
     * Get Stripe client
     *
     * @return object|false Stripe client or false if not available
     */
    private function get_stripe_client() {
        if (!$this->is_stripe_enabled()) {
            return false;
        }
        if (!class_exists('\Voxel\Modules\Stripe_Payments\Stripe_Client')) {
            return false;
        }
        return \Voxel\Modules\Stripe_Payments\Stripe_Client::getClient();
    }

    /**
     * Handle create coupon AJAX request
     */
    public function handle_create_coupon() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'voxel_coupon_widget_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'voxel-toolkit')));
            return;
        }

        // Check user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to create coupons', 'voxel-toolkit')));
            return;
        }

        // Get Stripe client
        $stripe = $this->get_stripe_client();
        if (!$stripe) {
            wp_send_json_error(array('message' => __('Stripe payment gateway must be enabled to use this feature', 'voxel-toolkit')));
            return;
        }

        // Get and validate inputs
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $code = isset($_POST['code']) ? strtoupper(sanitize_text_field($_POST['code'])) : '';
        $discount_type = isset($_POST['discount_type']) ? sanitize_text_field($_POST['discount_type']) : 'percent';
        $percent_off = isset($_POST['percent_off']) ? floatval($_POST['percent_off']) : 0;
        $amount_off = isset($_POST['amount_off']) ? floatval($_POST['amount_off']) : 0;
        $duration = isset($_POST['duration']) ? sanitize_text_field($_POST['duration']) : 'once';
        $duration_months = isset($_POST['duration_months']) ? intval($_POST['duration_months']) : 0;
        $max_redemptions = isset($_POST['max_redemptions']) ? intval($_POST['max_redemptions']) : 0;
        $redeem_by = isset($_POST['redeem_by']) ? sanitize_text_field($_POST['redeem_by']) : '';
        $minimum_amount = isset($_POST['minimum_amount']) ? floatval($_POST['minimum_amount']) : 0;
        $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
        $first_time_only = isset($_POST['first_time_only']) && $_POST['first_time_only'] === 'true';

        // Validate required fields
        if (empty($name)) {
            wp_send_json_error(array('message' => __('Coupon name is required', 'voxel-toolkit')));
            return;
        }

        // Validate discount
        if ($discount_type === 'percent') {
            if ($percent_off <= 0 || $percent_off > 100) {
                wp_send_json_error(array('message' => __('Percent off must be between 1 and 100', 'voxel-toolkit')));
                return;
            }
        } else {
            if ($amount_off <= 0) {
                wp_send_json_error(array('message' => __('Amount off must be greater than 0', 'voxel-toolkit')));
                return;
            }
        }

        // Validate duration
        if (!in_array($duration, array('once', 'repeating', 'forever'))) {
            $duration = 'once';
        }

        if ($duration === 'repeating' && $duration_months <= 0) {
            wp_send_json_error(array('message' => __('Duration months is required for repeating coupons', 'voxel-toolkit')));
            return;
        }

        try {
            // Build coupon data
            $coupon_data = array(
                'name' => $name,
                'duration' => $duration,
                'metadata' => array(
                    'wp_user_id' => (string) get_current_user_id(),
                ),
            );

            // Add discount
            if ($discount_type === 'percent') {
                $coupon_data['percent_off'] = $percent_off;
            } else {
                $coupon_data['amount_off'] = intval($amount_off * 100); // Convert to cents
                $currency = 'USD';
                if (function_exists('\Voxel\get')) {
                    $currency = \Voxel\get('payments.stripe.currency', 'USD');
                }
                $coupon_data['currency'] = strtolower($currency);
            }

            // Add optional fields
            if ($duration === 'repeating' && $duration_months > 0) {
                $coupon_data['duration_in_months'] = $duration_months;
            }

            if ($max_redemptions > 0) {
                $coupon_data['max_redemptions'] = $max_redemptions;
            }

            if (!empty($redeem_by)) {
                $redeem_timestamp = strtotime($redeem_by);
                if ($redeem_timestamp && $redeem_timestamp > time()) {
                    $coupon_data['redeem_by'] = $redeem_timestamp;
                }
            }

            // Create coupon in Stripe
            $coupon = $stripe->coupons->create($coupon_data);

            // Determine if we need a promotion code
            $needs_promo_code = !empty($code) || $first_time_only || $minimum_amount > 0 || !empty($customer_email);

            // Create promotion code if needed
            $promo_code = null;
            if ($needs_promo_code) {
                $promo_data = array(
                    'coupon' => $coupon->id,
                    'code' => !empty($code) ? $code : strtoupper(wp_generate_password(8, false)),
                    'metadata' => array(
                        'wp_user_id' => (string) get_current_user_id(),
                    ),
                );

                // Build restrictions array
                $restrictions = array();

                if ($first_time_only) {
                    $restrictions['first_time_transaction'] = true;
                }

                if ($minimum_amount > 0) {
                    $restrictions['minimum_amount'] = intval($minimum_amount * 100); // Convert to cents
                    $currency = 'USD';
                    if (function_exists('\Voxel\get')) {
                        $currency = \Voxel\get('payments.stripe.currency', 'USD');
                    }
                    $restrictions['minimum_amount_currency'] = strtolower($currency);
                }

                if (!empty($restrictions)) {
                    $promo_data['restrictions'] = $restrictions;
                }

                // Limit to specific customer by email
                if (!empty($customer_email)) {
                    // Look up customer by email
                    $customers = $stripe->customers->all(array('email' => $customer_email, 'limit' => 1));
                    if (!empty($customers->data)) {
                        $promo_data['customer'] = $customers->data[0]->id;
                    } else {
                        // Customer not found - create one or return error
                        wp_send_json_error(array('message' => sprintf(__('No Stripe customer found with email: %s', 'voxel-toolkit'), $customer_email)));
                        return;
                    }
                }

                $promo_code = $stripe->promotionCodes->create($promo_data);
            }

            wp_send_json_success(array(
                'message' => __('Coupon created successfully!', 'voxel-toolkit'),
                'coupon' => array(
                    'id' => $coupon->id,
                    'name' => $coupon->name,
                    'percent_off' => $coupon->percent_off,
                    'amount_off' => $coupon->amount_off,
                    'currency' => $coupon->currency ?? null,
                    'duration' => $coupon->duration,
                    'duration_in_months' => $coupon->duration_in_months ?? null,
                    'max_redemptions' => $coupon->max_redemptions ?? null,
                    'times_redeemed' => $coupon->times_redeemed ?? 0,
                    'redeem_by' => $coupon->redeem_by ?? null,
                    'valid' => $coupon->valid,
                    'promo_code' => $promo_code ? $promo_code->code : null,
                ),
            ));

        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle get user coupons AJAX request
     */
    public function handle_get_user_coupons() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'voxel_coupon_widget_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'voxel-toolkit')));
            return;
        }

        // Check user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'voxel-toolkit')));
            return;
        }

        // Get Stripe client
        $stripe = $this->get_stripe_client();
        if (!$stripe) {
            wp_send_json_error(array('message' => __('Stripe payment gateway must be enabled to use this feature', 'voxel-toolkit')));
            return;
        }

        try {
            $current_user_id = (string) get_current_user_id();
            $user_coupons = array();

            // Get all coupons and filter by user
            $coupons = $stripe->coupons->all(array('limit' => 100));

            foreach ($coupons->data as $coupon) {
                $wp_user_id = $coupon->metadata['wp_user_id'] ?? '';
                if ($wp_user_id === $current_user_id) {
                    // Get promotion codes for this coupon
                    $promo_codes = $stripe->promotionCodes->all(array(
                        'coupon' => $coupon->id,
                        'limit' => 10,
                    ));

                    $codes = array();
                    foreach ($promo_codes->data as $promo) {
                        $promo_data = array(
                            'id' => $promo->id,
                            'code' => $promo->code,
                            'active' => $promo->active,
                            'first_time_transaction' => $promo->restrictions->first_time_transaction ?? false,
                            'minimum_amount' => $promo->restrictions->minimum_amount ?? null,
                            'minimum_amount_currency' => $promo->restrictions->minimum_amount_currency ?? null,
                        );

                        // Get customer email if restricted to specific customer
                        if (!empty($promo->customer)) {
                            try {
                                $customer = $stripe->customers->retrieve($promo->customer);
                                $promo_data['customer_email'] = $customer->email ?? null;
                            } catch (\Exception $e) {
                                $promo_data['customer_email'] = null;
                            }
                        } else {
                            $promo_data['customer_email'] = null;
                        }

                        $codes[] = $promo_data;
                    }

                    $user_coupons[] = array(
                        'id' => $coupon->id,
                        'name' => $coupon->name,
                        'percent_off' => $coupon->percent_off,
                        'amount_off' => $coupon->amount_off,
                        'currency' => $coupon->currency ?? null,
                        'duration' => $coupon->duration,
                        'duration_in_months' => $coupon->duration_in_months ?? null,
                        'max_redemptions' => $coupon->max_redemptions ?? null,
                        'times_redeemed' => $coupon->times_redeemed ?? 0,
                        'redeem_by' => $coupon->redeem_by ?? null,
                        'valid' => $coupon->valid,
                        'created' => $coupon->created,
                        'promo_codes' => $codes,
                    );
                }
            }

            // Sort by created date, newest first
            usort($user_coupons, function($a, $b) {
                return $b['created'] - $a['created'];
            });

            wp_send_json_success(array('coupons' => $user_coupons));

        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle delete coupon AJAX request
     */
    public function handle_delete_coupon() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'voxel_coupon_widget_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'voxel-toolkit')));
            return;
        }

        // Check user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'voxel-toolkit')));
            return;
        }

        // Get Stripe client
        $stripe = $this->get_stripe_client();
        if (!$stripe) {
            wp_send_json_error(array('message' => __('Stripe payment gateway must be enabled to use this feature', 'voxel-toolkit')));
            return;
        }

        $coupon_id = isset($_POST['coupon_id']) ? sanitize_text_field($_POST['coupon_id']) : '';

        if (empty($coupon_id)) {
            wp_send_json_error(array('message' => __('Coupon ID is required', 'voxel-toolkit')));
            return;
        }

        try {
            // Retrieve coupon to verify ownership
            $coupon = $stripe->coupons->retrieve($coupon_id);
            $wp_user_id = $coupon->metadata['wp_user_id'] ?? '';

            if ($wp_user_id !== (string) get_current_user_id()) {
                wp_send_json_error(array('message' => __('You do not have permission to delete this coupon', 'voxel-toolkit')));
                return;
            }

            // Delete the coupon
            $stripe->coupons->delete($coupon_id);

            wp_send_json_success(array('message' => __('Coupon deleted successfully!', 'voxel-toolkit')));

        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}
