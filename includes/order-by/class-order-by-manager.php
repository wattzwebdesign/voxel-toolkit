<?php
/**
 * Order By Manager
 *
 * Registers custom order by types with Voxel
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Order_By_Manager {

    /**
     * Singleton instance
     */
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
     * Constructor
     */
    private function __construct() {
        // Register early, before Voxel initializes post types
        add_action('after_setup_theme', array($this, 'register_order_by_types'), 5);
    }

    /**
     * Register custom order by types
     */
    public function register_order_by_types() {
        // Load the view count order class
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/order-by/class-view-count-order.php';

        // Register with Voxel
        add_filter('voxel/orderby-types', array($this, 'add_view_count_order'));
    }

    /**
     * Add view count order to Voxel's order by types
     *
     * @param array $orderby_types Existing order by types
     * @return array Modified order by types
     */
    public function add_view_count_order($orderby_types) {
        $orderby_types['view-count'] = \Voxel_Toolkit\Order_By\View_Count_Order::class;
        return $orderby_types;
    }
}

// Initialize the manager
Voxel_Toolkit_Order_By_Manager::instance();
