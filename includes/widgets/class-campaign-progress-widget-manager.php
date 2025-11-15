<?php
/**
 * Campaign Progress Widget Manager
 *
 * Manages the Campaign Progress widget registration, assets, and functionality
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Campaign_Progress_Widget_Manager {

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
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));
        add_action('elementor/frontend/after_enqueue_styles', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Register Campaign Progress widget with Elementor
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/widgets/class-campaign-progress-widget.php';
        $widgets_manager->register(new \Voxel_Toolkit_Campaign_Progress_Widget());
    }

    /**
     * Enqueue widget styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'voxel-toolkit-campaign-progress',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/campaign-progress-widget.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
    }

    /**
     * Enqueue widget scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'voxel-toolkit-campaign-progress',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/campaign-progress-widget.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );
    }

    /**
     * Get campaign progress data
     *
     * @param int $post_id Campaign/Product post ID
     * @return array Progress data
     */
    public static function get_campaign_progress($post_id) {
        global $wpdb;

        // Cache key
        $cache_key = 'vt_campaign_progress_' . $post_id;

        // Check cache (5 minutes)
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        // Query completed orders
        $orders_query = "
            SELECT customer_id, details, created_at
            FROM {$wpdb->prefix}vx_orders
            WHERE status = 'completed'
            ORDER BY created_at DESC
        ";

        $orders = $wpdb->get_results($orders_query);

        $total_raised = 0;
        $recent_donors = array();
        $processed_users = array();

        foreach ($orders as $order) {
            $details = json_decode($order->details, true);

            if (!$details || !isset($details['cart']['items'])) {
                continue;
            }

            // Check if this order contains the campaign product
            $found_product = false;
            foreach ($details['cart']['items'] as $item) {
                if (isset($item['product']['post_id']) &&
                    intval($item['product']['post_id']) === intval($post_id)) {
                    $found_product = true;
                    break;
                }
            }

            if (!$found_product) {
                continue;
            }

            // Add to total
            $order_total = isset($details['pricing']['total']) ?
                           floatval($details['pricing']['total']) : 0;
            $total_raised += $order_total;

            // Get donor info
            if (!in_array($order->customer_id, $processed_users)) {
                $user = get_user_by('id', $order->customer_id);

                if ($user) {
                    // Get Voxel avatar
                    $avatar_id = get_user_meta($order->customer_id, 'voxel:avatar', true);
                    $avatar_url = $avatar_id ?
                        wp_get_attachment_image_url($avatar_id, 'thumbnail') :
                        get_avatar_url($order->customer_id, array('size' => 100));

                    $recent_donors[] = array(
                        'name' => $user->display_name,
                        'avatar_url' => $avatar_url,
                        'amount' => $order_total,
                        'date' => $order->created_at,
                        'currency' => isset($details['pricing']['currency']) ?
                                     $details['pricing']['currency'] : 'USD'
                    );

                    $processed_users[] = $order->customer_id;
                }
            }
        }

        $result = array(
            'total_raised' => $total_raised,
            'donation_count' => count($processed_users),
            'recent_donors' => $recent_donors
        );

        // Cache for 5 minutes
        set_transient($cache_key, $result, 300);

        return $result;
    }

    /**
     * Format currency amount
     *
     * @param float $amount Amount to format
     * @param string $currency Currency code
     * @param string $symbol Currency symbol
     * @return string Formatted amount
     */
    public static function format_currency($amount, $currency = 'USD', $symbol = '$') {
        return $symbol . number_format($amount, 2);
    }
}
