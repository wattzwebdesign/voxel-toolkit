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
        // Filter is registered at top level in this file
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

// Register filter at the top level (before any theme hooks)
// Load the class file INSIDE the filter callback to ensure Voxel classes exist first
add_filter('voxel/orderby-types', function($orderby_types) {
    // Load view count order class NOW (when filter is called, Voxel classes exist)
    if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/order-by/class-view-count-order.php')) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/order-by/class-view-count-order.php';
    }

    $orderby_types['view-count'] = \Voxel_Toolkit\Order_By\View_Count_Order::class;

    return $orderby_types;
});

// Initialize the manager (for any future functionality)
Voxel_Toolkit_Order_By_Manager::instance();
