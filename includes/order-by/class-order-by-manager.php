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
        // Add hook to inject view count preset via JavaScript
        add_action('admin_footer', array($this, 'inject_view_count_preset_js'));
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

    /**
     * Inject JavaScript to add order by presets to Post_Type_Options
     */
    public function inject_view_count_preset_js() {
        // Only run on admin pages
        if (!is_admin()) {
            return;
        }

        ?>
        <script type="text/javascript">
        (function() {
            if (typeof window.Post_Type_Options !== 'undefined' && window.Post_Type_Options.orderby_presets) {
                // Add "View Count" preset
                window.Post_Type_Options.orderby_presets['view-count-preset'] = {
                    key: 'view-count-preset',
                    label: 'View Count',
                    clauses: [{
                        type: 'view-count',
                        order: 'DESC',
                        period: 'all'
                    }]
                };

                // Add "Helpful Votes" preset
                window.Post_Type_Options.orderby_presets['helpful-votes-preset'] = {
                    key: 'helpful-votes-preset',
                    label: 'Helpful Votes',
                    clauses: [{
                        type: 'helpful-votes',
                        order: 'DESC',
                        sort_type: 'helpful'
                    }]
                };
            }
        })();
        </script>
        <?php
    }
}

// Register filter at the top level (before any theme hooks)
// Load the class files INSIDE the filter callback to ensure Voxel classes exist first
add_filter('voxel/orderby-types', function($orderby_types) {
    // Load view count order class
    if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/order-by/class-view-count-order.php')) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/order-by/class-view-count-order.php';
    }
    $orderby_types['view-count'] = \Voxel_Toolkit\Order_By\View_Count_Order::class;

    // Load helpful votes order class
    if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/order-by/class-helpful-votes-order.php')) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/order-by/class-helpful-votes-order.php';
    }
    $orderby_types['helpful-votes'] = \Voxel_Toolkit\Order_By\Helpful_Votes_Order::class;

    return $orderby_types;
});

// Initialize the manager (for any future functionality)
Voxel_Toolkit_Order_By_Manager::instance();
