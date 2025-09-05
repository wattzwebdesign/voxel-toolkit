<?php
/**
 * Export Orders Function Class
 * 
 * Adds export functionality to Voxel orders page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Export_Orders {
    
    private $enabled = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Check if enabled via Voxel Toolkit settings
        $toolkit_settings = Voxel_Toolkit_Settings::instance();
        $this->enabled = $toolkit_settings->is_function_enabled('export_orders');
        
        if ($this->enabled) {
            $this->init();
        }
    }
    
    /**
     * Initialize the function
     */
    private function init() {
        add_action('admin_init', array($this, 'handle_export'));
        add_action('admin_notices', array($this, 'add_export_button'));
        add_action('admin_head', array($this, 'add_export_button_styles'));
    }
    
    /**
     * Enable the function
     */
    public function enable() {
        $this->enabled = true;
        $this->init();
    }
    
    /**
     * Disable the function
     */
    public function disable() {
        $this->enabled = false;
        remove_action('admin_init', array($this, 'handle_export'));
        remove_action('admin_notices', array($this, 'add_export_button'));
        remove_action('admin_head', array($this, 'add_export_button_styles'));
    }
    
    /**
     * Check if function is enabled
     */
    public function is_enabled() {
        return $this->enabled;
    }
    
    /**
     * Add export button styles
     */
    public function add_export_button_styles() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'voxel-orders') {
            return;
        }
        ?>
        <style>
        .voxel-export-container {
            margin: 20px 0;
            padding: 0;
            background: none;
            border: none;
            box-shadow: none;
        }
        .voxel-export-button {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #555;
            cursor: pointer;
            display: inline-block;
            font-size: 13px;
            font-weight: 400;
            line-height: 1;
            margin: 0;
            padding: 8px 16px;
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .voxel-export-button:hover {
            background: #f8f9fa;
            border-color: #aaa;
            color: #333;
            text-decoration: none;
        }
        .voxel-export-button:active {
            background: #f1f3f4;
            border-color: #999;
        }
        </style>
        <?php
    }
    
    /**
     * Add export button to orders page
     */
    public function add_export_button() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'voxel-orders') {
            return;
        }
        
        // Only show on the main orders page (no additional parameters)
        if (count($_GET) !== 1) {
            return;
        }
        
        $export_url = wp_nonce_url(admin_url('admin.php?page=voxel-orders&action=export_orders'), 'export_voxel_orders');
        
        echo '<div class="voxel-export-container">';
        echo '<a href="' . esc_url($export_url) . '" class="voxel-export-button">Export Orders to CSV</a>';
        echo '</div>';
    }
    
    /**
     * Handle the export request
     */
    public function handle_export() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'export_orders') {
            return;
        }
        
        // Check for orders page with same logic as button display
        $current_page = isset($_GET['page']) ? $_GET['page'] : 'none';
        $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'none';
        
        $is_orders_page = (
            ($current_page === 'voxel-orders') ||
            ($current_page === 'voxel' && $current_tab === 'orders') ||
            ($current_page === 'voxel' && isset($_GET['action']) && $_GET['action'] === 'orders') ||
            (strpos($current_page, 'voxel') !== false && (strpos($_SERVER['REQUEST_URI'], 'orders') !== false || strpos($_SERVER['REQUEST_URI'], 'ecommerce') !== false))
        );
        
        if (!$is_orders_page) {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'export_voxel_orders')) {
            wp_die('Security check failed');
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to export orders');
        }
        
        // Generate CSV
        $this->generate_csv();
    }
    
    /**
     * Generate and download CSV file
     */
    private function generate_csv() {
        global $wpdb;
        
        // Get all orders with customer and vendor info
        $orders = $wpdb->get_results("
            SELECT 
                o.*,
                c.display_name as customer_name,
                c.user_email as customer_email,
                v.display_name as vendor_name
            FROM {$wpdb->prefix}vx_orders o
            LEFT JOIN {$wpdb->users} c ON o.customer_id = c.ID
            LEFT JOIN {$wpdb->users} v ON o.vendor_id = v.ID
            ORDER BY o.created_at DESC
        ");
        
        // Get order items
        $order_items = $wpdb->get_results("
            SELECT oi.*, p.post_title
            FROM {$wpdb->prefix}vx_order_items oi
            LEFT JOIN {$wpdb->posts} p ON oi.post_id = p.ID
        ");
        
        // Group items by order
        $items_by_order = array();
        foreach ($order_items as $item) {
            if (!isset($items_by_order[$item->order_id])) {
                $items_by_order[$item->order_id] = array();
            }
            $items_by_order[$item->order_id][] = $item;
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="voxel-orders-export-' . date('Y-m-d-His') . '.csv"');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Build CSV headers - one row per line item
        $headers = array(
            'Order ID',
            'User',
            'Email',
            'Order Value',
            'Currency',
            'Order Status',
            'Payment Method',
            'Transaction ID',
            'Created At',
            'Item Name',
            'Item Quantity',
            'Item Price',
            'Item Total',
            'Checkout Customer Name',
            'Checkout Email',
            'Checkout Phone',
            'Address Line 1',
            'Address Line 2',
            'City',
            'State',
            'Postal Code',
            'Country'
        );
        
        // Write headers
        fputcsv($output, $headers);
        
        // Process each order
        foreach ($orders as $order) {
            $details = json_decode($order->details, true);
            
            // Basic order data
            $basic_order_data = array(
                $order->id,
                $order->customer_name,
                $order->customer_email,
                isset($details['pricing']['total']) ? $details['pricing']['total'] : '0',
                isset($details['pricing']['currency']) ? $details['pricing']['currency'] : 'USD',
                $order->status,
                $order->payment_method,
                $order->transaction_id,
                $order->created_at
            );
            
            // Checkout details
            $checkout = isset($details['checkout']['session_details']['customer_details']) 
                ? $details['checkout']['session_details']['customer_details'] 
                : array();
            
            $checkout_data = array(
                isset($checkout['name']) ? $checkout['name'] : '',
                isset($checkout['email']) ? $checkout['email'] : '',
                isset($checkout['phone']) ? $checkout['phone'] : '',
                isset($checkout['address']['line1']) ? $checkout['address']['line1'] : '',
                isset($checkout['address']['line2']) ? $checkout['address']['line2'] : '',
                isset($checkout['address']['city']) ? $checkout['address']['city'] : '',
                isset($checkout['address']['state']) ? $checkout['address']['state'] : '',
                isset($checkout['address']['postal_code']) ? $checkout['address']['postal_code'] : '',
                isset($checkout['address']['country']) ? $checkout['address']['country'] : ''
            );
            
            // Get order items
            $order_id = $order->id;
            $items = isset($items_by_order[$order_id]) ? $items_by_order[$order_id] : array();
            
            if (empty($items)) {
                // If no items, create one row with empty item data
                $row = array_merge($basic_order_data, array('', '', '', ''), $checkout_data);
                fputcsv($output, $row);
            } else {
                // Create one row for each item
                foreach ($items as $item) {
                    $item_details = json_decode($item->details, true);
                    
                    // Get item name
                    $item_name = $item->post_title;
                    if (empty($item_name) && isset($item_details['product']['label'])) {
                        $item_name = $item_details['product']['label'];
                    }
                    
                    // Get variation info if available
                    if (isset($item_details['variation']['attributes'])) {
                        $variations = array();
                        foreach ($item_details['variation']['attributes'] as $attr) {
                            if (isset($attr['attribute']['label']) && isset($attr['value']['label'])) {
                                $variations[] = $attr['attribute']['label'] . ': ' . $attr['value']['label'];
                            }
                        }
                        if (!empty($variations)) {
                            $item_name .= ' (' . implode(', ', $variations) . ')';
                        }
                    }
                    
                    // Get quantity and price
                    $quantity = isset($item_details['summary']['quantity']) ? $item_details['summary']['quantity'] : 1;
                    $unit_price = isset($item_details['summary']['amount_per_unit']) ? $item_details['summary']['amount_per_unit'] : 0;
                    $item_total = isset($item_details['summary']['total_amount']) ? $item_details['summary']['total_amount'] : 0;
                    
                    // Item data
                    $item_data = array(
                        $item_name,
                        $quantity,
                        $unit_price,
                        $item_total
                    );
                    
                    // Combine all data for this row
                    $row = array_merge($basic_order_data, $item_data, $checkout_data);
                    fputcsv($output, $row);
                }
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get function info
     */
    public static function get_info() {
        return array(
            'title' => __('Export Orders', 'voxel-toolkit'),
            'description' => __('Add an export button to the Voxel orders page to export all orders to CSV format with comprehensive details.', 'voxel-toolkit'),
            'category' => 'orders'
        );
    }
}