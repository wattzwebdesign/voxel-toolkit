<?php
/**
 * Order Summary Dynamic Tag Method
 *
 * Generates an email-friendly HTML table of order items
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order Summary Method for Order Group
 */
class Voxel_Toolkit_Order_Summary_Method extends \Voxel\Dynamic_Data\Modifiers\Group_Methods\Base_Group_Method {

    /**
     * Get method label
     */
    public function get_label(): string {
        return 'Order Summary';
    }

    /**
     * Get method key
     */
    public function get_key(): string {
        return 'summary';
    }

    /**
     * Define method arguments
     */
    protected function define_args(): void {
        // No arguments needed - will use order ID from context
    }

    /**
     * Run the method
     */
    public function run($group) {
        // Get order ID from the group
        $order_id = null;
        if (isset($group->order) && method_exists($group->order, 'get_id')) {
            $order_id = $group->order->get_id();
        }

        if (!$order_id) {
            return '';
        }

        return $this->generate_order_summary_table($order_id);
    }

    /**
     * Generate HTML table for order summary
     */
    private function generate_order_summary_table($order_id) {
        global $wpdb;

        // Get order items from database
        $table_name = $wpdb->prefix . 'vx_order_items';
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE order_id = %d",
            $order_id
        ));

        if (empty($items)) {
            return '<p>No items found for this order.</p>';
        }

        // Start building HTML table
        $html = '<table style="width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; margin: 20px 0;">';
        $html .= '<thead>';
        $html .= '<tr style="background-color: #f5f5f5;">';
        $html .= '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600; color: #333;">ITEM</th>';
        $html .= '<th style="padding: 12px; text-align: right; border-bottom: 2px solid #ddd; font-weight: 600; color: #333;">TOTAL</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        $grand_total = 0;

        foreach ($items as $item) {
            $details = json_decode($item->details, true);
            if (!$details) {
                continue;
            }

            $product_label = $details['product']['label'] ?? 'Unknown Product';
            $currency = $details['currency'] ?? 'USD';
            $total_amount = $details['summary']['total_amount'] ?? 0;
            $grand_total += $total_amount;

            // Item row
            $html .= '<tr>';
            $html .= '<td style="padding: 15px 12px; border-bottom: 1px solid #eee;">';
            $html .= '<div style="font-weight: 600; color: #1a1a1a; margin-bottom: 5px;">' . esc_html($product_label) . '</div>';

            // Add details based on type
            if ($details['type'] === 'booking') {
                $html .= $this->format_booking_details($details);
            } else {
                $html .= $this->format_regular_product_details($details);
            }

            $html .= '</td>';
            $html .= '<td style="padding: 15px 12px; text-align: right; border-bottom: 1px solid #eee; font-weight: 600; color: #1a1a1a;">';
            $html .= $this->format_currency($total_amount, $currency);
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';

        // Total row
        $html .= '<tfoot>';
        $html .= '<tr style="background-color: #f9f9f9;">';
        $html .= '<td style="padding: 15px 12px; text-align: right; font-weight: 700; font-size: 16px; color: #1a1a1a;">Total</td>';
        $html .= '<td style="padding: 15px 12px; text-align: right; font-weight: 700; font-size: 16px; color: #1a1a1a;">';
        $html .= $this->format_currency($grand_total, $currency ?? 'USD');
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</tfoot>';

        $html .= '</table>';

        return $html;
    }

    /**
     * Format regular product details
     */
    private function format_regular_product_details($details) {
        $html = '';

        if (isset($details['summary']['quantity'])) {
            $quantity = $details['summary']['quantity'];
            $amount_per_unit = $details['summary']['amount_per_unit'] ?? 0;
            $currency = $details['currency'] ?? 'USD';

            $html .= '<div style="color: #666; font-size: 14px;">';
            $html .= '- ' . $quantity . ' × ' . $this->format_currency($amount_per_unit, $currency);
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Format booking details
     */
    private function format_booking_details($details) {
        $html = '<div style="color: #666; font-size: 14px; margin-top: 5px;">';

        // Date range
        if (isset($details['booking'])) {
            $booking = $details['booking'];
            if (isset($booking['start_date']) && isset($booking['end_date'])) {
                $start = date('M j, Y', strtotime($booking['start_date']));
                $end = date('M j, Y', strtotime($booking['end_date']));
                $count_mode = $booking['count_mode'] ?? 'nights';

                // Calculate nights/days
                $start_time = strtotime($booking['start_date']);
                $end_time = strtotime($booking['end_date']);
                $diff = ($end_time - $start_time) / (60 * 60 * 24);

                $html .= '<div style="margin-bottom: 3px;">';
                $html .= '<strong>' . $start . ' - ' . $end . '</strong>';
                $html .= ' (' . $diff . ' ' . $count_mode . ')';
                $html .= '</div>';
            }
        }

        // Addons
        if (isset($details['summary']['summary'])) {
            foreach ($details['summary']['summary'] as $summary_item) {
                if ($summary_item['key'] === 'addons' && isset($summary_item['summary'])) {
                    foreach ($summary_item['summary'] as $addon) {
                        $html .= $this->format_addon($addon, $details['currency'] ?? 'USD');
                    }
                }
            }
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Format addon details
     */
    private function format_addon($addon, $currency) {
        $html = '<div style="margin-top: 5px; padding-left: 10px;">';

        if ($addon['type'] === 'numeric') {
            $label = $addon['label'] ?? '';
            $quantity = $addon['quantity'] ?? 0;
            $amount = $addon['amount'] ?? 0;
            $repeat = $addon['repeat'] ?? null;

            $html .= '• ' . esc_html($label) . ': ' . $quantity;

            if ($repeat) {
                $length = $repeat['length'] ?? 0;
                $mode = $repeat['mode'] ?? '';
                $html .= ' × ' . $length . ' ' . $mode;
            }

            $html .= ' - ' . $this->format_currency($amount, $currency);
        } elseif ($addon['type'] === 'custom-multiselect') {
            $label = $addon['label'] ?? '';
            $html .= '• ' . esc_html($label) . ':';

            if (isset($addon['summary']) && is_array($addon['summary'])) {
                foreach ($addon['summary'] as $selection) {
                    $selection_label = $selection['label'] ?? '';
                    $selection_amount = $selection['amount'] ?? 0;
                    $repeat = $addon['repeat'] ?? null;

                    $html .= '<div style="margin-left: 15px; margin-top: 2px;">';
                    $html .= '- ' . esc_html($selection_label);

                    if ($repeat) {
                        $length = $repeat['length'] ?? 0;
                        $mode = $repeat['mode'] ?? '';
                        $html .= ' × ' . $length . ' ' . $mode;
                    }

                    $html .= ' - ' . $this->format_currency($selection_amount, $currency);
                    $html .= '</div>';
                }
            }
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Format currency amount
     */
    private function format_currency($amount, $currency = 'USD') {
        $symbol = '$'; // Default to USD

        // Map common currencies
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
        ];

        if (isset($symbols[$currency])) {
            $symbol = $symbols[$currency];
        }

        return $symbol . number_format($amount, 2);
    }
}
