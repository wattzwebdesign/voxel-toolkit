<?php
/**
 * Membership Checkout Links
 *
 * Adds dynamic tags for generating membership plan checkout URLs
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Membership_Checkout_Links {

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
        // Register site properties for plans
        add_filter('voxel/dynamic-data/groups/site/properties', array($this, 'register_plans_properties'), 10, 2);

        // Enqueue Voxel's pricing JavaScript on frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue necessary scripts
     */
    public function enqueue_scripts() {
        // Only enqueue on frontend
        if (is_admin()) {
            return;
        }

        // Enqueue Voxel's pricing plans JavaScript which handles vx-pick-plan clicks
        wp_enqueue_script('vx:pricing-plans.js');
    }

    /**
     * Register plans properties for @site dynamic tags
     */
    public function register_plans_properties($properties, $group) {
        // Check if Paid Memberships module is available
        if (!class_exists('\Voxel\Modules\Paid_Memberships\Plan')) {
            return $properties;
        }

        // Add membership plans data
        $properties['plans'] = \Voxel\Dynamic_Data\Tag::Object('Membership Plans')->properties(function() {
            $plans = \Voxel\Modules\Paid_Memberships\Plan::all();
            $plans_properties = array();

            foreach ($plans as $plan) {
                $plan_key = $plan->get_key();
                $prices_config = $plan->config('prices');

                $plans_properties[$plan_key] = \Voxel\Dynamic_Data\Tag::Object($plan->get_label())->properties(function() use ($plan, $plan_key, $prices_config) {
                    $plan_props = array();

                    // Basic plan properties
                    $plan_props['key'] = \Voxel\Dynamic_Data\Tag::String('Plan Key')->render(function() use ($plan_key) {
                        return $plan_key;
                    });

                    $plan_props['label'] = \Voxel\Dynamic_Data\Tag::String('Plan Label')->render(function() use ($plan) {
                        return $plan->get_label();
                    });

                    $plan_props['description'] = \Voxel\Dynamic_Data\Tag::String('Plan Description')->render(function() use ($plan) {
                        return $plan->get_description();
                    });

                    // Checkout URL for the plan (uses first price if available)
                    $plan_props['checkout_url'] = \Voxel\Dynamic_Data\Tag::String('Checkout URL')->render(function() use ($plan_key, $prices_config) {
                        return self::generate_checkout_url($plan_key, $prices_config);
                    });

                    // Prices object
                    if (is_array($prices_config) && !empty($prices_config)) {
                        $plan_props['prices'] = \Voxel\Dynamic_Data\Tag::Object('Prices')->properties(function() use ($prices_config, $plan_key) {
                            $prices_properties = array();

                            foreach ($prices_config as $price_config) {
                                $price_key = $price_config['key'] ?? '';
                                if (!$price_key) {
                                    continue;
                                }

                                $prices_properties[$price_key] = \Voxel\Dynamic_Data\Tag::Object($price_config['label'] ?? $price_key)->properties(function() use ($price_config, $plan_key, $price_key) {
                                    $price_props = array();

                                    $price_props['key'] = \Voxel\Dynamic_Data\Tag::String('Price Key')->render(function() use ($price_key) {
                                        return $price_key;
                                    });

                                    $price_props['label'] = \Voxel\Dynamic_Data\Tag::String('Price Label')->render(function() use ($price_config) {
                                        return $price_config['label'] ?? '';
                                    });

                                    $price_props['amount'] = \Voxel\Dynamic_Data\Tag::Number('Amount')->render(function() use ($price_config) {
                                        return $price_config['amount'] ?? 0;
                                    });

                                    $price_props['currency'] = \Voxel\Dynamic_Data\Tag::String('Currency')->render(function() use ($price_config) {
                                        return $price_config['currency'] ?? '';
                                    });

                                    $price_props['interval'] = \Voxel\Dynamic_Data\Tag::String('Interval')->render(function() use ($price_config) {
                                        return $price_config['interval'] ?? '';
                                    });

                                    // Checkout URL for specific price
                                    $price_props['checkout_url'] = \Voxel\Dynamic_Data\Tag::String('Checkout URL')->render(function() use ($plan_key, $price_key) {
                                        return self::generate_checkout_url($plan_key, null, $price_key);
                                    });

                                    return $price_props;
                                });
                            }

                            return $prices_properties;
                        });
                    }

                    return $plan_props;
                });
            }

            return $plans_properties;
        });

        return $properties;
    }

    /**
     * Generate checkout URL
     *
     * @param string $plan_key Plan key
     * @param array|null $prices_config Prices configuration (to auto-select first price)
     * @param string|null $price_key Specific price key
     * @return string Checkout URL
     */
    private static function generate_checkout_url($plan_key, $prices_config = null, $price_key = null) {
        // If no specific price provided, try to use first available price
        if (!$price_key && is_array($prices_config) && !empty($prices_config)) {
            $first_price = reset($prices_config);
            $price_key = $first_price['key'] ?? null;
        }

        // Build checkout key
        if ($price_key) {
            $checkout_key = sprintf('%s@%s', $plan_key, $price_key);
        } else {
            $checkout_key = $plan_key;
        }

        // Generate URL in Voxel's format
        $url_args = array(
            'vx' => '1',
            'action' => 'paid_memberships.choose_plan',
            'plan' => $checkout_key,
            '_wpnonce' => wp_create_nonce('vx_choose_plan'),
        );

        return esc_url(add_query_arg($url_args, home_url('/')));
    }

    /**
     * Render settings section
     */
    public static function render_settings() {
        ?>
        <tr>
            <th scope="row">
                <label><?php _e('Membership Checkout Links', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <p class="description">
                    <?php _e('Adds dynamic tags for generating membership plan checkout URLs:', 'voxel-toolkit'); ?>
                </p>
                <ul style="list-style: disc; margin-left: 20px; margin-top: 10px;">
                    <li><code>@site(plans.PLAN_KEY.checkout_url)</code> - <?php _e('Get checkout URL for a plan', 'voxel-toolkit'); ?></li>
                    <li><code>@site(plans.PLAN_KEY.prices.PRICE_KEY.checkout_url)</code> - <?php _e('Get checkout URL for specific pricing', 'voxel-toolkit'); ?></li>
                </ul>
                <p class="description" style="margin-top: 10px;">
                    <strong><?php _e('Important:', 'voxel-toolkit'); ?></strong> <?php _e('These URLs must be used with buttons that have the "vx-pick-plan" CSS class for the AJAX checkout flow to work properly.', 'voxel-toolkit'); ?>
                </p>
            </td>
        </tr>
        <?php
    }
}
