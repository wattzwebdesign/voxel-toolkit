<?php
/**
 * Sold Modifier
 *
 * Count total sold quantity for a product/post from vx_order_items table
 * Usage: @post(id).sold()
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sold Modifier - Count total quantity sold for a product
 */
class Voxel_Toolkit_Sold_Modifier extends \Voxel\Dynamic_Data\Modifiers\Base_Modifier {

    /**
     * Get modifier label
     */
    public function get_label(): string {
        return 'Total Sold';
    }

    /**
     * Get modifier key
     */
    public function get_key(): string {
        return 'sold';
    }

    /**
     * Get modifier description
     */
    public function get_description(): string {
        return 'Count total quantity sold for a product. Use with @post(id).sold()';
    }

    /**
     * Apply the modifier
     *
     * @param mixed $value The post ID
     * @return string Total quantity sold
     */
    public function apply($value) {
        // The value should be the post ID
        $post_id = absint($value);

        if (!$post_id) {
            return '0';
        }

        $total_quantity = $this->get_total_sold($post_id);

        return (string) $total_quantity;
    }

    /**
     * Get total quantity sold for a product from vx_order_items table
     *
     * @param int $post_id Product post ID
     * @return int Total quantity sold
     */
    private function get_total_sold($post_id) {
        global $wpdb;

        // Get the vx_order_items table name
        $table_name = $wpdb->prefix . 'vx_order_items';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return 0;
        }

        // Query to get all order items where field_key = 'product'
        // The details JSON contains: {"type":"variable","product":{"label":"..."},"summary":{"quantity":1,...},...}
        $query = $wpdb->prepare(
            "SELECT details FROM $table_name WHERE field_key = %s",
            'product'
        );

        $results = $wpdb->get_col($query);

        if (empty($results)) {
            return 0;
        }

        $total_quantity = 0;

        // Loop through each order item
        foreach ($results as $details_json) {
            $details = json_decode($details_json, true);

            if (!$details || !is_array($details)) {
                continue;
            }

            // Check if this item belongs to our post_id
            // We need to check the product label or variation_id against the post
            // Since we don't have direct post_id in the details, we'll need to match by product title
            $product_label = isset($details['product']['label']) ? $details['product']['label'] : '';

            if (empty($product_label)) {
                continue;
            }

            // Get the post title to match
            $post_title = get_the_title($post_id);

            // If the product label matches the post title, count the quantity
            if ($product_label === $post_title) {
                $quantity = isset($details['summary']['quantity']) ? absint($details['summary']['quantity']) : 0;
                $total_quantity += $quantity;
            }
        }

        return $total_quantity;
    }
}
