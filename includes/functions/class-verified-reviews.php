<?php
/**
 * Verified Reviews Function
 *
 * - Restricts review submission to verified purchasers (per post type setting)
 * - Restricts author responses to users with active membership/listings
 * - Adds "Purchased" badge to verified buyer reviews
 *
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Verified_Reviews {

    private static $instance = null;
    private $settings = [];

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = get_option('voxel_toolkit_verified_reviews_settings', []);
        $this->add_hooks();
    }

    private function add_hooks() {
        // Hook into review submission validation
        add_filter('voxel/can-review-post', [$this, 'check_purchase_requirement'], 10, 2);

        // Hook into review reply validation (author responses)
        add_action('voxel/timeline/comment:before-create', [$this, 'check_author_response_permission'], 10, 2);

        // Add verified purchase badge to reviews
        add_filter('voxel/timeline/status:get-badges', [$this, 'add_purchase_badge'], 10, 2);

        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Add localized data for frontend
        add_filter('voxel/frontend-config', [$this, 'add_frontend_config']);
    }

    /**
     * Check if user meets purchase requirement to review
     */
    public function check_purchase_requirement($can_review, $post) {
        if (!$can_review) {
            return false;
        }

        $purchase_required_post_types = isset($this->settings['purchase_required_post_types'])
            ? $this->settings['purchase_required_post_types']
            : [];

        // Check if this post type requires purchase
        if (!in_array($post->post_type->get_key(), $purchase_required_post_types)) {
            return $can_review;
        }

        $current_user = \Voxel\current_user();
        if (!$current_user) {
            return false;
        }

        // Check if user has purchased from this post
        return $current_user->has_bought_product($post->get_id());
    }

    /**
     * Check if author can respond to reviews
     */
    public function check_author_response_permission($reply_data, $status) {
        // Only check for post_reviews feed
        if ($status->get_feed() !== 'post_reviews') {
            return;
        }

        $post = $status->get_post();
        if (!$post) {
            return;
        }

        $current_user = \Voxel\current_user();
        if (!$current_user) {
            return;
        }

        // Only check if replying user is the post author
        if ($post->get_author_id() !== $current_user->get_id()) {
            return;
        }

        // Check if author has EITHER active membership OR active listing
        $has_permission = false;

        // Check for active paid membership
        $membership = $current_user->get_membership();
        if ($membership && $membership->is_active()) {
            $plan = $membership->get_plan();
            if ($plan && $plan->get_key() !== 'default') {
                $has_permission = true;
            }
        }

        // Check for active paid listing (if membership check failed)
        if (!$has_permission) {
            $has_permission = $this->has_active_paid_listing($post);
        }

        if (!$has_permission) {
            $message = isset($this->settings['author_response_error'])
                ? $this->settings['author_response_error']
                : __('You need an active paid membership or listing to respond to reviews.', 'voxel-toolkit');
            throw new \Exception($message);
        }
    }

    /**
     * Check if post has active paid listing
     */
    private function has_active_paid_listing($post) {
        // Check if post has an active order associated with it
        global $wpdb;

        $order = $wpdb->get_var($wpdb->prepare(
            "SELECT orders.id
            FROM {$wpdb->prefix}vx_orders AS orders
            LEFT JOIN {$wpdb->prefix}vx_order_items AS order_items ON orders.id = order_items.order_id
            WHERE orders.vendor_id = %d
              AND orders.status IN ('completed','sub_active')
              AND order_items.post_id = %d
            LIMIT 1",
            $post->get_author_id(),
            $post->get_id()
        ));

        return !empty($order);
    }

    /**
     * Add verified purchase badge to reviews
     */
    public function add_purchase_badge($badges, $status) {
        // Only for post_reviews feed
        if ($status->get_feed() !== 'post_reviews') {
            return $badges;
        }

        $user = $status->get_user();
        $post = $status->get_post();

        if (!$user || !$post) {
            return $badges;
        }

        // Check if reviewer purchased from this post
        if ($user->has_bought_product($post->get_id())) {
            $badge_label = isset($this->settings['badge_label'])
                ? $this->settings['badge_label']
                : __('Purchased', 'voxel-toolkit');

            $badges[] = [
                'key' => 'verified_purchase',
                'label' => $badge_label,
                'type' => 'verified-purchase',
            ];
        }

        return $badges;
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (!is_singular()) {
            return;
        }

        wp_enqueue_script(
            'voxel-toolkit-verified-reviews',
            VOXEL_TOOLKIT_URL . 'assets/js/verified-reviews.js',
            ['jquery'],
            VOXEL_TOOLKIT_VERSION,
            true
        );

        wp_enqueue_style(
            'voxel-toolkit-verified-reviews',
            VOXEL_TOOLKIT_URL . 'assets/css/verified-reviews.css',
            [],
            VOXEL_TOOLKIT_VERSION
        );
    }

    /**
     * Add configuration to Voxel's frontend config
     */
    public function add_frontend_config($config) {
        $config['verifiedReviews'] = [
            'nonPurchaserMessage' => isset($this->settings['non_purchaser_message'])
                ? $this->settings['non_purchaser_message']
                : __('Only customers who have purchased can leave reviews.', 'voxel-toolkit'),
            'nonPurchaserAction' => isset($this->settings['non_purchaser_action'])
                ? $this->settings['non_purchaser_action']
                : 'show_message',
            'badgeColor' => isset($this->settings['badge_color'])
                ? $this->settings['badge_color']
                : '#10b981',
            'badgeIcon' => isset($this->settings['badge_icon'])
                ? $this->settings['badge_icon']
                : 'checkmark',
        ];

        return $config;
    }

    /**
     * Get current settings
     */
    public function get_settings() {
        return $this->settings;
    }
}
