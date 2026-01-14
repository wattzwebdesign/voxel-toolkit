<?php
/**
 * Column Renderer
 *
 * Handles rendering of column content for each field type.
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Column_Renderer {

    /**
     * Column types instance
     */
    private $column_types;

    /**
     * Maximum text length before truncation
     */
    private $max_text_length = 50;

    /**
     * Constructor
     */
    public function __construct($column_types) {
        $this->column_types = $column_types;
    }

    /**
     * Render a field value
     *
     * @param string $field_key Field key
     * @param int $post_id Post ID
     * @param array|null $column_config Optional column configuration for image sizing
     */
    public function render($field_key, $post_id, $column_config = null) {
        // Handle WordPress core fields (prefixed with :)
        if (strpos($field_key, ':') === 0) {
            return $this->render_wp_field($field_key, $post_id, $column_config);
        }

        // Check if Voxel is available
        if (!class_exists('\Voxel\Post')) {
            return $this->render_fallback($field_key, $post_id);
        }

        $post = \Voxel\Post::get($post_id);

        if (!$post) {
            return $this->empty_value();
        }

        $field = $post->get_field($field_key);

        if (!$field) {
            return $this->render_fallback($field_key, $post_id);
        }

        $value = $field->get_value();

        if ($this->is_empty($value)) {
            return $this->empty_value();
        }

        $type = $field->get_type();
        $method = 'render_' . str_replace('-', '_', $type);

        if (method_exists($this, $method)) {
            // Pass column config to fields that have display settings
            if (in_array($type, array('image', 'profile-avatar', 'product', 'work-hours', 'location', 'date', 'recurring-date', 'event-date', 'poll-vt', 'title', 'textarea', 'description', 'texteditor'))) {
                return $this->$method($value, $field, $post, $column_config);
            }
            return $this->$method($value, $field, $post);
        }

        return $this->render_default($value);
    }

    /**
     * Render WordPress core fields
     */
    private function render_wp_field($field_key, $post_id, $column_config = null) {
        $wp_post = get_post($post_id);

        if (!$wp_post) {
            return $this->empty_value();
        }

        switch ($field_key) {
            case ':date':
                return $this->render_wp_date($wp_post->post_date, $column_config);

            case ':author':
                $author = get_userdata($wp_post->post_author);
                if (!$author) {
                    return $this->empty_value();
                }
                $edit_link = get_edit_user_link($author->ID);
                return '<a href="' . esc_url($edit_link) . '">' . esc_html($author->display_name) . '</a>';

            case ':status':
                $status_obj = get_post_status_object($wp_post->post_status);
                $status_label = $status_obj ? $status_obj->label : $wp_post->post_status;
                $status_class = 'vt-ac-status-' . sanitize_html_class($wp_post->post_status);
                return '<span class="vt-ac-badge ' . $status_class . '">' . esc_html($status_label) . '</span>';

            case ':id':
                return '<span class="vt-ac-number">' . esc_html($post_id) . '</span>';

            case ':modified':
                return $this->render_wp_date($wp_post->post_modified, $column_config);

            case ':slug':
                return '<code class="vt-ac-slug">' . esc_html($wp_post->post_name) . '</code>';

            case ':excerpt':
                $excerpt = $wp_post->post_excerpt;
                if (empty($excerpt)) {
                    return $this->empty_value();
                }
                return $this->truncate(wp_strip_all_tags($excerpt), 80);

            case ':thumbnail':
                $thumbnail_id = get_post_thumbnail_id($post_id);
                if (!$thumbnail_id) {
                    return $this->empty_value();
                }
                // Get image settings from column config
                $img_width = 60;
                $img_height = 60;
                $wp_size = 'thumbnail';
                if (isset($column_config['image_settings'])) {
                    $img_width = isset($column_config['image_settings']['display_width']) ? $column_config['image_settings']['display_width'] : 60;
                    $img_height = isset($column_config['image_settings']['display_height']) ? $column_config['image_settings']['display_height'] : 60;
                    $wp_size = isset($column_config['image_settings']['wp_size']) ? $column_config['image_settings']['wp_size'] : 'thumbnail';
                }
                $url = wp_get_attachment_image_url($thumbnail_id, $wp_size);
                if (!$url) {
                    return $this->empty_value();
                }
                return '<img src="' . esc_url($url) . '" alt="" class="vt-ac-image" style="width:' . intval($img_width) . 'px;height:' . intval($img_height) . 'px;object-fit:cover;" />';

            case ':comments':
                $count = $wp_post->comment_count;
                if ($count == 0) {
                    return '<span class="vt-ac-number vt-ac-muted">0</span>';
                }
                return '<span class="vt-ac-number">' . number_format_i18n($count) . '</span>';

            case ':menu_order':
                return '<span class="vt-ac-number">' . esc_html($wp_post->menu_order) . '</span>';

            case ':parent':
                if (!$wp_post->post_parent) {
                    return $this->empty_value();
                }
                $parent = get_post($wp_post->post_parent);
                if (!$parent) {
                    return $this->empty_value();
                }
                $edit_link = get_edit_post_link($parent->ID);
                return '<a href="' . esc_url($edit_link) . '">' . esc_html($parent->post_title) . '</a>';

            case ':word_count':
                $content = $wp_post->post_content;
                $word_count = str_word_count(wp_strip_all_tags($content));
                return '<span class="vt-ac-number">' . number_format_i18n($word_count) . '</span>';

            case ':permalink':
                $permalink = get_permalink($post_id);
                if (!$permalink) {
                    return $this->empty_value();
                }
                return '<a href="' . esc_url($permalink) . '" target="_blank" class="vt-ac-permalink">' . esc_html($permalink) . '</a>';

            case ':view_counts':
                return $this->render_view_counts($post_id);

            case ':review_stats':
                return $this->render_review_stats($post_id);

            case ':listing_plan':
                return $this->render_listing_plan($post_id, $column_config);

            case ':article_helpful':
                return $this->render_article_helpful($post_id, $column_config);

            default:
                return $this->empty_value();
        }
    }

    /**
     * Render WordPress date fields (Date Published, Last Modified)
     */
    private function render_wp_date($date_string, $column_config = null) {
        $timestamp = strtotime($date_string);

        if ($timestamp === false) {
            return esc_html($date_string);
        }

        // Get display mode from column config
        $display_mode = 'datetime';
        if (isset($column_config['date_settings']['display'])) {
            $display_mode = $column_config['date_settings']['display'];
        }

        // Get date format from column config or use WordPress default
        $date_format = get_option('date_format');
        if (isset($column_config['date_settings']['date_format'])) {
            $format_setting = $column_config['date_settings']['date_format'];
            if ($format_setting === 'wordpress') {
                $date_format = get_option('date_format');
            } elseif ($format_setting === 'custom' && !empty($column_config['date_settings']['custom_date_format'])) {
                $date_format = $column_config['date_settings']['custom_date_format'];
            } else {
                // Preset formats
                $date_format = $format_setting;
            }
        }

        // Get time format from column config or use WordPress default
        $time_format = get_option('time_format');
        if (isset($column_config['date_settings']['time_format'])) {
            $format_setting = $column_config['date_settings']['time_format'];
            if ($format_setting === 'wordpress') {
                $time_format = get_option('time_format');
            } elseif ($format_setting === 'custom' && !empty($column_config['date_settings']['custom_time_format'])) {
                $time_format = $column_config['date_settings']['custom_time_format'];
            } else {
                // Preset formats
                $time_format = $format_setting;
            }
        }

        switch ($display_mode) {
            case 'date':
                $formatted = date_i18n($date_format, $timestamp);
                break;

            case 'datetime':
                $formatted = date_i18n($date_format . ' ' . $time_format, $timestamp);
                break;

            case 'relative':
                $formatted = $this->get_relative_date($timestamp);
                break;

            default:
                $formatted = date_i18n($date_format . ' ' . $time_format, $timestamp);
        }

        return '<span class="vt-ac-date" title="' . esc_attr($date_string) . '">' . esc_html($formatted) . '</span>';
    }

    /**
     * Render Voxel view counts
     */
    private function render_view_counts($post_id) {
        $view_data = get_post_meta($post_id, 'voxel:view_counts', true);

        // Get total views (all time)
        $total_views = 0;
        $views_30d = 0;

        if (!empty($view_data)) {
            // Decode if JSON string
            if (is_string($view_data)) {
                $view_data = json_decode($view_data, true);
            }

            if (is_array($view_data)) {
                if (isset($view_data['views']['all'])) {
                    $total_views = intval($view_data['views']['all']);
                }

                if (isset($view_data['views']['30d'])) {
                    $views_30d = intval($view_data['views']['30d']);
                }
            }
        }

        // Build output showing total and 30d
        $output = '<span class="vt-ac-view-counts">';
        $output .= '<span class="vt-ac-views-total' . ($total_views === 0 ? ' vt-ac-muted' : '') . '" title="' . esc_attr__('Total views', 'voxel-toolkit') . '">';
        $output .= number_format_i18n($total_views);
        $output .= '</span>';

        // Show 30d in parentheses if different from total
        if ($views_30d > 0 && $views_30d !== $total_views) {
            $output .= ' <span class="vt-ac-views-30d" title="' . esc_attr__('Last 30 days', 'voxel-toolkit') . '">(' . number_format_i18n($views_30d) . ')</span>';
        }

        $output .= '</span>';

        return $output;
    }

    /**
     * Render Voxel review stats
     */
    private function render_review_stats($post_id) {
        $review_data = get_post_meta($post_id, 'voxel:review_stats', true);

        if (empty($review_data)) {
            return $this->empty_value();
        }

        // Decode if JSON string
        if (is_string($review_data)) {
            $review_data = json_decode($review_data, true);
        }

        if (!is_array($review_data)) {
            return $this->empty_value();
        }

        $total = isset($review_data['total']) ? intval($review_data['total']) : 0;
        $average = isset($review_data['average']) ? floatval($review_data['average']) : 0;

        if ($total === 0) {
            return '<span class="vt-ac-reviews-empty">' . __('No reviews', 'voxel-toolkit') . '</span>';
        }

        // Convert Voxel scale (-2 to 2) to 5-star scale (1 to 5)
        // -2 = 1 star, -1 = 2 stars, 0 = 3 stars, 1 = 4 stars, 2 = 5 stars
        $stars = $average + 3;
        $stars = max(1, min(5, $stars)); // Clamp between 1 and 5

        // Round to nearest 0.5
        $stars_rounded = round($stars * 2) / 2;

        // Build star display
        $output = '<span class="vt-ac-review-stats">';
        $output .= '<span class="vt-ac-stars" title="' . esc_attr(sprintf(__('%.1f out of 5 stars', 'voxel-toolkit'), $stars)) . '">';

        // Render stars
        $output .= $this->render_stars($stars_rounded);

        $output .= '</span>';
        $output .= ' <span class="vt-ac-review-count">(' . number_format_i18n($total) . ')</span>';
        $output .= '</span>';

        return $output;
    }

    /**
     * Render star icons
     */
    private function render_stars($rating) {
        $output = '';
        $full_stars = floor($rating);
        $half_star = ($rating - $full_stars) >= 0.5;
        $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);

        // Full stars
        for ($i = 0; $i < $full_stars; $i++) {
            $output .= '<span class="vt-ac-star vt-ac-star-full">★</span>';
        }

        // Half star
        if ($half_star) {
            $output .= '<span class="vt-ac-star vt-ac-star-half">★</span>';
        }

        // Empty stars
        for ($i = 0; $i < $empty_stars; $i++) {
            $output .= '<span class="vt-ac-star vt-ac-star-empty">☆</span>';
        }

        return $output;
    }

    /**
     * Render Article Helpful stats (Voxel Toolkit)
     */
    private function render_article_helpful($post_id, $column_config = null) {
        $yes_count = get_post_meta($post_id, '_article_helpful_yes', true);
        $no_count = get_post_meta($post_id, '_article_helpful_no', true);

        $yes_count = $yes_count ? intval($yes_count) : 0;
        $no_count = $no_count ? intval($no_count) : 0;
        $total = $yes_count + $no_count;

        if ($total === 0) {
            return $this->empty_value();
        }

        $percentage = round(($yes_count / $total) * 100);

        // Get display mode from column config
        $display_mode = 'summary';
        if (isset($column_config['helpful_settings']['display'])) {
            $display_mode = $column_config['helpful_settings']['display'];
        }

        switch ($display_mode) {
            case 'yes_count':
                return '<span class="vt-ac-helpful-yes"><span class="dashicons dashicons-thumbs-up"></span> ' . number_format_i18n($yes_count) . '</span>';

            case 'no_count':
                return '<span class="vt-ac-helpful-no"><span class="dashicons dashicons-thumbs-down"></span> ' . number_format_i18n($no_count) . '</span>';

            case 'total':
                return '<span class="vt-ac-number">' . number_format_i18n($total) . ' ' . _n('vote', 'votes', $total, 'voxel-toolkit') . '</span>';

            case 'percentage':
                $class = $percentage >= 70 ? 'vt-ac-helpful-good' : ($percentage >= 40 ? 'vt-ac-helpful-neutral' : 'vt-ac-helpful-bad');
                return '<span class="vt-ac-badge ' . $class . '">' . $percentage . '%</span>';

            case 'summary':
            default:
                $output = '<span class="vt-ac-helpful-summary">';
                $output .= '<span class="vt-ac-helpful-yes" title="' . esc_attr__('Yes votes', 'voxel-toolkit') . '"><span class="dashicons dashicons-thumbs-up"></span> ' . number_format_i18n($yes_count) . '</span>';
                $output .= ' <span class="vt-ac-helpful-divider">/</span> ';
                $output .= '<span class="vt-ac-helpful-no" title="' . esc_attr__('No votes', 'voxel-toolkit') . '"><span class="dashicons dashicons-thumbs-down"></span> ' . number_format_i18n($no_count) . '</span>';
                $class = $percentage >= 70 ? 'vt-ac-helpful-good' : ($percentage >= 40 ? 'vt-ac-helpful-neutral' : 'vt-ac-helpful-bad');
                $output .= '<br><span class="vt-ac-badge ' . $class . '">' . $percentage . '% ' . __('helpful', 'voxel-toolkit') . '</span>';
                $output .= '</span>';
                return $output;
        }
    }

    /**
     * Render Voxel listing plan
     */
    private function render_listing_plan($post_id, $column_config = null) {
        $plan_data = get_post_meta($post_id, 'voxel:listing_plan', true);

        if (empty($plan_data)) {
            return $this->empty_value();
        }

        // Decode if JSON string
        if (is_string($plan_data)) {
            $plan_data = json_decode($plan_data, true);
        }

        if (!is_array($plan_data)) {
            return $this->empty_value();
        }

        // Get display mode from column config
        $display_mode = 'plan_name';
        if (isset($column_config['listing_plan_settings']['display'])) {
            $display_mode = $column_config['listing_plan_settings']['display'];
        }

        $plan_key = isset($plan_data['plan']) ? $plan_data['plan'] : '';
        $package_id = isset($plan_data['package']) ? intval($plan_data['package']) : 0;
        $purchase_time = isset($plan_data['time']) ? intval($plan_data['time']) : 0;

        switch ($display_mode) {
            case 'plan_name':
                return $this->render_listing_plan_name($plan_key);

            case 'amount':
                return $this->render_listing_plan_amount($package_id);

            case 'frequency':
                return $this->render_listing_plan_frequency($package_id);

            case 'purchase_date':
                if ($purchase_time > 0) {
                    $formatted = date_i18n(get_option('date_format'), $purchase_time);
                    return '<span class="vt-ac-date"><span class="dashicons dashicons-calendar-alt"></span> ' . esc_html($formatted) . '</span>';
                }
                return $this->empty_value();

            case 'expiration':
                return $this->render_listing_plan_expiration($package_id, $purchase_time);

            case 'summary':
                return $this->render_listing_plan_summary($plan_key, $package_id, $purchase_time);

            default:
                return $this->render_listing_plan_name($plan_key);
        }
    }

    /**
     * Get listing plan label from plan key
     */
    private function get_listing_plan_label($plan_key) {
        if (empty($plan_key)) {
            return null;
        }

        // Get paid listings settings from Voxel
        $paid_listings = get_option('voxel:paid_listings', '');

        if (empty($paid_listings)) {
            return null;
        }

        if (is_string($paid_listings)) {
            $paid_listings = json_decode($paid_listings, true);
        }

        if (!is_array($paid_listings) || !isset($paid_listings['plans'][$plan_key])) {
            return null;
        }

        $plan = $paid_listings['plans'][$plan_key];

        return isset($plan['label']) ? $plan['label'] : $plan_key;
    }

    /**
     * Get order item details from vx_order_items table
     */
    private function get_order_item_details($package_id) {
        if (empty($package_id)) {
            return null;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'vx_order_items';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return null;
        }

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $package_id
        ), ARRAY_A);

        if (!$item) {
            return null;
        }

        // Parse the details JSON
        if (isset($item['details']) && is_string($item['details'])) {
            $item['details_parsed'] = json_decode($item['details'], true);
        }

        return $item;
    }

    /**
     * Render listing plan name
     */
    private function render_listing_plan_name($plan_key) {
        $label = $this->get_listing_plan_label($plan_key);

        if ($label) {
            return '<span class="vt-ac-badge vt-ac-listing-plan"><span class="dashicons dashicons-awards"></span> ' . esc_html($label) . '</span>';
        }

        // Fallback to plan key formatted nicely
        if ($plan_key) {
            $formatted = ucwords(str_replace(array('-', '_'), ' ', $plan_key));
            return '<span class="vt-ac-badge vt-ac-listing-plan">' . esc_html($formatted) . '</span>';
        }

        return $this->empty_value();
    }

    /**
     * Render listing plan amount
     */
    private function render_listing_plan_amount($package_id) {
        $item = $this->get_order_item_details($package_id);

        if (!$item || !isset($item['details_parsed'])) {
            return $this->empty_value();
        }

        $details = $item['details_parsed'];

        // Get total amount from summary
        if (isset($details['summary']['total_amount'])) {
            $amount = floatval($details['summary']['total_amount']);
            $currency = isset($details['currency']) ? $details['currency'] : 'USD';
            return '<span class="vt-ac-price">' . $this->format_price_with_currency($amount, $currency) . '</span>';
        }

        return $this->empty_value();
    }

    /**
     * Format price with specific currency
     */
    private function format_price_with_currency($amount, $currency = 'USD') {
        $symbol = $this->get_currency_symbol(strtoupper($currency));
        return $symbol . number_format_i18n($amount, 2);
    }

    /**
     * Render listing plan frequency
     */
    private function render_listing_plan_frequency($package_id) {
        $item = $this->get_order_item_details($package_id);

        if (!$item || !isset($item['details_parsed'])) {
            return $this->empty_value();
        }

        $details = $item['details_parsed'];

        // Check for subscription info
        if (isset($details['subscription'])) {
            $frequency = isset($details['subscription']['frequency']) ? intval($details['subscription']['frequency']) : 1;
            $unit = isset($details['subscription']['unit']) ? $details['subscription']['unit'] : '';

            return '<span class="vt-ac-badge vt-ac-frequency">' . esc_html($this->format_billing_frequency($frequency, $unit)) . '</span>';
        }

        // One-time payment
        return '<span class="vt-ac-badge vt-ac-muted">' . __('One-time', 'voxel-toolkit') . '</span>';
    }

    /**
     * Format billing frequency to human readable
     */
    private function format_billing_frequency($frequency, $unit) {
        if (empty($unit)) {
            return __('One-time', 'voxel-toolkit');
        }

        switch ($unit) {
            case 'day':
                if ($frequency == 1) {
                    return __('Daily', 'voxel-toolkit');
                }
                return sprintf(__('Every %d days', 'voxel-toolkit'), $frequency);

            case 'week':
                if ($frequency == 1) {
                    return __('Weekly', 'voxel-toolkit');
                }
                return sprintf(__('Every %d weeks', 'voxel-toolkit'), $frequency);

            case 'month':
                if ($frequency == 1) {
                    return __('Monthly', 'voxel-toolkit');
                }
                return sprintf(__('Every %d months', 'voxel-toolkit'), $frequency);

            case 'year':
                if ($frequency == 1) {
                    return __('Yearly', 'voxel-toolkit');
                }
                return sprintf(__('Every %d years', 'voxel-toolkit'), $frequency);

            default:
                return __('Recurring', 'voxel-toolkit');
        }
    }

    /**
     * Render listing plan expiration
     */
    private function render_listing_plan_expiration($package_id, $purchase_time) {
        $item = $this->get_order_item_details($package_id);

        if (!$item || !isset($item['details_parsed'])) {
            return $this->empty_value();
        }

        $details = $item['details_parsed'];

        // Calculate expiration based on subscription
        if (isset($details['subscription']) && $purchase_time > 0) {
            $frequency = isset($details['subscription']['frequency']) ? intval($details['subscription']['frequency']) : 1;
            $unit = isset($details['subscription']['unit']) ? $details['subscription']['unit'] : '';

            $expiration_time = $this->calculate_expiration($purchase_time, $frequency, $unit);

            if ($expiration_time > 0) {
                $formatted = date_i18n(get_option('date_format'), $expiration_time);
                $now = current_time('timestamp');

                if ($expiration_time < $now) {
                    return '<span class="vt-ac-date vt-ac-expired"><span class="dashicons dashicons-warning"></span> ' . esc_html($formatted) . '</span>';
                }

                return '<span class="vt-ac-date"><span class="dashicons dashicons-calendar-alt"></span> ' . esc_html($formatted) . '</span>';
            }
        }

        return $this->empty_value();
    }

    /**
     * Calculate expiration timestamp
     */
    private function calculate_expiration($start_time, $frequency, $unit) {
        if (empty($unit) || $start_time <= 0) {
            return 0;
        }

        switch ($unit) {
            case 'day':
                return $start_time + ($frequency * DAY_IN_SECONDS);
            case 'week':
                return $start_time + ($frequency * WEEK_IN_SECONDS);
            case 'month':
                return $start_time + ($frequency * MONTH_IN_SECONDS);
            case 'year':
                return $start_time + ($frequency * YEAR_IN_SECONDS);
            default:
                return 0;
        }
    }

    /**
     * Render listing plan summary
     */
    private function render_listing_plan_summary($plan_key, $package_id, $purchase_time) {
        $parts = array();

        // Plan name
        $label = $this->get_listing_plan_label($plan_key);
        if ($label) {
            $parts[] = '<span class="vt-ac-badge vt-ac-listing-plan">' . esc_html($label) . '</span>';
        } elseif ($plan_key) {
            $formatted = ucwords(str_replace(array('-', '_'), ' ', $plan_key));
            $parts[] = '<span class="vt-ac-badge vt-ac-listing-plan">' . esc_html($formatted) . '</span>';
        }

        // Get order item details
        $item = $this->get_order_item_details($package_id);

        if ($item && isset($item['details_parsed'])) {
            $details = $item['details_parsed'];

            // Amount + frequency
            $amount_str = '';
            if (isset($details['summary']['total_amount'])) {
                $amount = floatval($details['summary']['total_amount']);
                $currency = isset($details['currency']) ? $details['currency'] : 'USD';
                $amount_str = $this->format_price_with_currency($amount, $currency);

                if (isset($details['subscription'])) {
                    $unit = isset($details['subscription']['unit']) ? $details['subscription']['unit'] : '';
                    if ($unit) {
                        $amount_str .= '/' . $this->get_unit_abbreviation($unit);
                    }
                }

                $parts[] = '<span class="vt-ac-price">' . esc_html($amount_str) . '</span>';
            }
        }

        if (empty($parts)) {
            return $this->empty_value();
        }

        return '<span class="vt-ac-listing-plan-summary">' . implode(' ', $parts) . '</span>';
    }

    /**
     * Get unit abbreviation
     */
    private function get_unit_abbreviation($unit) {
        switch ($unit) {
            case 'day':
                return __('day', 'voxel-toolkit');
            case 'week':
                return __('wk', 'voxel-toolkit');
            case 'month':
                return __('mo', 'voxel-toolkit');
            case 'year':
                return __('yr', 'voxel-toolkit');
            default:
                return $unit;
        }
    }

    /**
     * Fallback rendering using post meta
     */
    private function render_fallback($field_key, $post_id) {
        $value = get_post_meta($post_id, $field_key, true);

        if ($this->is_empty($value)) {
            return $this->empty_value();
        }

        return $this->render_default($value);
    }

    /**
     * Check if value is empty
     */
    private function is_empty($value) {
        if ($value === null || $value === '' || $value === array()) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        return false;
    }

    /**
     * Empty value display
     */
    private function empty_value() {
        return '<span class="vt-ac-empty">&mdash;</span>';
    }

    /**
     * Truncate text with tooltip
     */
    private function truncate($text, $max_length = null) {
        $max_length = $max_length ?: $this->max_text_length;

        if (mb_strlen($text) <= $max_length) {
            return esc_html($text);
        }

        $truncated = mb_substr($text, 0, $max_length) . '...';

        return '<span title="' . esc_attr($text) . '">' . esc_html($truncated) . '</span>';
    }

    // ==========================================
    // Field Type Renderers
    // ==========================================

    /**
     * Render title field with optional edit link and row actions
     */
    private function render_title($value, $field, $post, $column_config = null) {
        $post_id = $post->get_id();
        $wp_post = get_post($post_id);

        // Strip any HTML from the title value
        $value = wp_strip_all_tags($value);

        if (!$wp_post) {
            return $this->truncate($value);
        }

        // Get settings with defaults (both enabled by default)
        $show_link = true;
        $show_actions = true;

        if ($column_config && isset($column_config['title_settings'])) {
            $show_link = isset($column_config['title_settings']['show_link'])
                ? (bool) $column_config['title_settings']['show_link']
                : true;
            $show_actions = isset($column_config['title_settings']['show_actions'])
                ? (bool) $column_config['title_settings']['show_actions']
                : true;
        }

        $title = $this->truncate($value);
        $output = '';

        // Title with or without edit link
        // Note: $title is already escaped by truncate()
        if ($show_link && current_user_can('edit_post', $post_id)) {
            $edit_url = get_edit_post_link($post_id);
            $output .= '<strong><a class="row-title" href="' . esc_url($edit_url) . '">' . $title . '</a></strong>';
        } else {
            $output .= '<strong>' . $title . '</strong>';
        }

        // Row actions
        if ($show_actions) {
            $output .= $this->get_row_actions($wp_post);
        }

        // Add inline edit data for WordPress bulk/quick edit
        $output .= $this->get_inline_edit_data($wp_post);

        return $output;
    }

    /**
     * Generate hidden inline edit data for WordPress bulk/quick edit
     * This data is required for bulk edit to show post titles
     */
    private function get_inline_edit_data($post) {
        $post_type_object = get_post_type_object($post->post_type);

        if (!$post_type_object || !current_user_can('edit_post', $post->ID)) {
            return '';
        }

        $output = '<div class="hidden" id="inline_' . $post->ID . '">';
        $output .= '<div class="post_title">' . esc_html($post->post_title) . '</div>';
        $output .= '<div class="post_name">' . esc_html($post->post_name) . '</div>';
        $output .= '<div class="post_author">' . esc_html($post->post_author) . '</div>';
        $output .= '<div class="comment_status">' . esc_html($post->comment_status) . '</div>';
        $output .= '<div class="ping_status">' . esc_html($post->ping_status) . '</div>';
        $output .= '<div class="_status">' . esc_html($post->post_status) . '</div>';
        $output .= '<div class="jj">' . mysql2date('d', $post->post_date, false) . '</div>';
        $output .= '<div class="mm">' . mysql2date('m', $post->post_date, false) . '</div>';
        $output .= '<div class="aa">' . mysql2date('Y', $post->post_date, false) . '</div>';
        $output .= '<div class="hh">' . mysql2date('H', $post->post_date, false) . '</div>';
        $output .= '<div class="mn">' . mysql2date('i', $post->post_date, false) . '</div>';
        $output .= '<div class="ss">' . mysql2date('s', $post->post_date, false) . '</div>';
        $output .= '<div class="post_password">' . esc_html($post->post_password) . '</div>';

        if ($post_type_object->hierarchical) {
            $output .= '<div class="post_parent">' . $post->post_parent . '</div>';
        }

        if ($post->post_type === 'page') {
            $output .= '<div class="page_template">' . esc_html(get_post_meta($post->ID, '_wp_page_template', true)) . '</div>';
        }

        if (post_type_supports($post->post_type, 'page-attributes')) {
            $output .= '<div class="menu_order">' . $post->menu_order . '</div>';
        }

        // Taxonomies
        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $taxonomy_object = get_taxonomy($taxonomy);
            if ($taxonomy_object && $taxonomy_object->show_ui) {
                $terms = get_the_terms($post->ID, $taxonomy);
                $term_ids = $terms && !is_wp_error($terms) ? wp_list_pluck($terms, 'term_id') : array();
                $output .= '<div class="post_category" id="' . $taxonomy . '_' . $post->ID . '">' . implode(',', $term_ids) . '</div>';
            }
        }

        // Is sticky
        if (is_post_type_viewable($post_type_object)) {
            $output .= '<div class="sticky">' . (is_sticky($post->ID) ? 'sticky' : '') . '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Generate WordPress-style row actions for a post
     */
    private function get_row_actions($post) {
        $post_id = $post->ID;
        $post_type_object = get_post_type_object($post->post_type);
        $actions = array();

        // Edit action
        if (current_user_can('edit_post', $post_id)) {
            $actions['edit'] = sprintf(
                '<a href="%s" aria-label="%s">%s</a>',
                get_edit_post_link($post_id),
                esc_attr(sprintf(__('Edit &#8220;%s&#8221;'), $post->post_title)),
                __('Edit')
            );
        }

        // Quick Edit action
        if (current_user_can('edit_post', $post_id) && 'trash' !== $post->post_status) {
            $actions['inline hide-if-no-js'] = sprintf(
                '<button type="button" class="button-link editinline" aria-label="%s" aria-expanded="false">%s</button>',
                esc_attr(sprintf(__('Quick edit &#8220;%s&#8221; inline'), $post->post_title)),
                __('Quick&nbsp;Edit')
            );
        }

        // Trash/Delete action
        if (current_user_can('delete_post', $post_id)) {
            if ('trash' === $post->post_status) {
                $actions['untrash'] = sprintf(
                    '<a href="%s" aria-label="%s">%s</a>',
                    wp_nonce_url(admin_url("post.php?action=untrash&post=$post_id"), 'untrash-post_' . $post_id),
                    esc_attr(sprintf(__('Restore &#8220;%s&#8221; from the Trash'), $post->post_title)),
                    __('Restore')
                );
                $actions['delete'] = sprintf(
                    '<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
                    get_delete_post_link($post_id, '', true),
                    esc_attr(sprintf(__('Delete &#8220;%s&#8221; permanently'), $post->post_title)),
                    __('Delete Permanently')
                );
            } else {
                $actions['trash'] = sprintf(
                    '<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
                    get_delete_post_link($post_id),
                    esc_attr(sprintf(__('Move &#8220;%s&#8221; to the Trash'), $post->post_title)),
                    __('Trash')
                );
            }
        }

        // View action
        if ($post_type_object && $post_type_object->public) {
            if ('trash' !== $post->post_status) {
                if ('publish' === $post->post_status || 'attachment' === $post->post_type) {
                    $actions['view'] = sprintf(
                        '<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
                        get_permalink($post_id),
                        esc_attr(sprintf(__('View &#8220;%s&#8221;'), $post->post_title)),
                        __('View')
                    );
                } else {
                    $actions['view'] = sprintf(
                        '<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
                        get_preview_post_link($post_id),
                        esc_attr(sprintf(__('Preview &#8220;%s&#8221;'), $post->post_title)),
                        __('Preview')
                    );
                }
            }
        }

        if (empty($actions)) {
            return '';
        }

        // Build row actions HTML
        $output = '<div class="row-actions">';
        $i = 0;
        foreach ($actions as $action => $link) {
            $sep = ($i > 0) ? ' | ' : '';
            $output .= '<span class="' . esc_attr($action) . '">' . $sep . $link . '</span>';
            $i++;
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * Render text field
     */
    private function render_text($value, $field, $post) {
        return $this->truncate($value);
    }

    /**
     * Render textarea field
     */
    private function render_textarea($value, $field, $post, $column_config = null) {
        // Strip HTML
        $text = wp_strip_all_tags($value);

        // Get limit settings
        $limit_type = 'words';
        $limit_value = 20;

        if (isset($column_config['text_settings'])) {
            $limit_type = $column_config['text_settings']['limit_type'] ?? 'words';
            $limit_value = intval($column_config['text_settings']['limit_value'] ?? 20);
        }

        // Apply limit based on type
        switch ($limit_type) {
            case 'none':
                return esc_html($text);

            case 'characters':
                if ($limit_value > 0 && mb_strlen($text) > $limit_value) {
                    return esc_html(mb_substr($text, 0, $limit_value)) . '&hellip;';
                }
                return esc_html($text);

            case 'words':
            default:
                if ($limit_value > 0) {
                    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
                    if (count($words) > $limit_value) {
                        return esc_html(implode(' ', array_slice($words, 0, $limit_value))) . '&hellip;';
                    }
                }
                return esc_html($text);
        }
    }

    /**
     * Render description field
     */
    private function render_description($value, $field, $post, $column_config = null) {
        return $this->render_textarea($value, $field, $post, $column_config);
    }

    /**
     * Render text editor field
     */
    private function render_texteditor($value, $field, $post, $column_config = null) {
        return $this->render_textarea($value, $field, $post, $column_config);
    }

    /**
     * Render number field
     */
    private function render_number($value, $field, $post) {
        if (!is_numeric($value)) {
            return $this->empty_value();
        }

        return '<span class="vt-ac-number">' . number_format_i18n($value) . '</span>';
    }

    /**
     * Render email field
     */
    private function render_email($value, $field, $post) {
        return '<a href="mailto:' . esc_attr($value) . '" class="vt-ac-email">' . esc_html($value) . '</a>';
    }

    /**
     * Render phone field
     */
    private function render_phone($value, $field, $post) {
        $clean_phone = preg_replace('/[^0-9+]/', '', $value);
        return '<a href="tel:' . esc_attr($clean_phone) . '" class="vt-ac-phone">' . esc_html($value) . '</a>';
    }

    /**
     * Render URL field
     */
    private function render_url($value, $field, $post) {
        $parsed = parse_url($value);
        $display = isset($parsed['host']) ? $parsed['host'] : $value;

        // Remove www. for cleaner display
        $display = preg_replace('/^www\./', '', $display);

        return '<a href="' . esc_url($value) . '" target="_blank" class="vt-ac-url" title="' . esc_attr($value) . '">' . esc_html($display) . ' <span class="dashicons dashicons-external"></span></a>';
    }

    /**
     * Render date field
     */
    private function render_date($value, $field, $post, $column_config = null) {
        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return esc_html($value);
        }

        // Get display mode from column config
        $display_mode = 'date';
        if (isset($column_config['date_settings']['display'])) {
            $display_mode = $column_config['date_settings']['display'];
        }

        // Get date format from column config or use WordPress default
        $date_format = get_option('date_format');
        if (isset($column_config['date_settings']['date_format'])) {
            $format_setting = $column_config['date_settings']['date_format'];
            if ($format_setting === 'wordpress') {
                $date_format = get_option('date_format');
            } elseif ($format_setting === 'custom' && !empty($column_config['date_settings']['custom_date_format'])) {
                $date_format = $column_config['date_settings']['custom_date_format'];
            } else {
                // Preset formats
                $date_format = $format_setting;
            }
        }

        // Get time format from column config or use WordPress default
        $time_format = get_option('time_format');
        if (isset($column_config['date_settings']['time_format'])) {
            $format_setting = $column_config['date_settings']['time_format'];
            if ($format_setting === 'wordpress') {
                $time_format = get_option('time_format');
            } elseif ($format_setting === 'custom' && !empty($column_config['date_settings']['custom_time_format'])) {
                $time_format = $column_config['date_settings']['custom_time_format'];
            } else {
                // Preset formats
                $time_format = $format_setting;
            }
        }

        switch ($display_mode) {
            case 'date':
                $formatted = date_i18n($date_format, $timestamp);
                break;

            case 'datetime':
                $formatted = date_i18n($date_format . ' ' . $time_format, $timestamp);
                break;

            case 'relative':
                $formatted = $this->get_relative_date($timestamp);
                break;

            default:
                $formatted = date_i18n($date_format, $timestamp);
        }

        return '<span class="vt-ac-date" title="' . esc_attr($value) . '">' . esc_html($formatted) . '</span>';
    }

    /**
     * Get relative date string (e.g., "2 days ago", "in 3 weeks")
     */
    private function get_relative_date($timestamp) {
        $now = current_time('timestamp');
        $diff = $timestamp - $now;
        $abs_diff = abs($diff);

        $minute = 60;
        $hour = $minute * 60;
        $day = $hour * 24;
        $week = $day * 7;
        $month = $day * 30;
        $year = $day * 365;

        if ($abs_diff < $day) {
            if ($diff > 0) {
                return __('Today', 'voxel-toolkit');
            }
            return __('Today', 'voxel-toolkit');
        } elseif ($abs_diff < $day * 2) {
            return $diff > 0 ? __('Tomorrow', 'voxel-toolkit') : __('Yesterday', 'voxel-toolkit');
        } elseif ($abs_diff < $week) {
            $days = round($abs_diff / $day);
            return $diff > 0
                ? sprintf(_n('In %d day', 'In %d days', $days, 'voxel-toolkit'), $days)
                : sprintf(_n('%d day ago', '%d days ago', $days, 'voxel-toolkit'), $days);
        } elseif ($abs_diff < $month) {
            $weeks = round($abs_diff / $week);
            return $diff > 0
                ? sprintf(_n('In %d week', 'In %d weeks', $weeks, 'voxel-toolkit'), $weeks)
                : sprintf(_n('%d week ago', '%d weeks ago', $weeks, 'voxel-toolkit'), $weeks);
        } elseif ($abs_diff < $year) {
            $months = round($abs_diff / $month);
            return $diff > 0
                ? sprintf(_n('In %d month', 'In %d months', $months, 'voxel-toolkit'), $months)
                : sprintf(_n('%d month ago', '%d months ago', $months, 'voxel-toolkit'), $months);
        } else {
            $years = round($abs_diff / $year);
            return $diff > 0
                ? sprintf(_n('In %d year', 'In %d years', $years, 'voxel-toolkit'), $years)
                : sprintf(_n('%d year ago', '%d years ago', $years, 'voxel-toolkit'), $years);
        }
    }

    /**
     * Render time field
     */
    private function render_time($value, $field, $post) {
        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return esc_html($value);
        }

        $formatted = date_i18n(get_option('time_format'), $timestamp);

        return '<span class="vt-ac-time">' . esc_html($formatted) . '</span>';
    }

    /**
     * Render select field
     */
    private function render_select($value, $field, $post) {
        // Voxel stores select as "Label:value" or just the value
        if (is_string($value) && strpos($value, ':') !== false) {
            $parts = explode(':', $value, 2);
            $display = $parts[0]; // Use the label part
        } else {
            $display = $value;
        }

        return '<span class="vt-ac-select">' . esc_html($display) . '</span>';
    }

    /**
     * Render multiselect field
     */
    private function render_multiselect($value, $field, $post) {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value) || empty($value)) {
            return $this->empty_value();
        }

        // Extract labels if stored as "Label:value"
        $labels = array_map(function($item) {
            if (is_string($item) && strpos($item, ':') !== false) {
                $parts = explode(':', $item, 2);
                return $parts[0];
            }
            return $item;
        }, $value);

        $count = count($labels);

        if ($count <= 3) {
            return '<span class="vt-ac-multiselect">' . esc_html(implode(', ', $labels)) . '</span>';
        }

        $display = implode(', ', array_slice($labels, 0, 3));
        $remaining = $count - 3;

        return '<span class="vt-ac-multiselect" title="' . esc_attr(implode(', ', $labels)) . '">' . esc_html($display) . ' <span class="vt-ac-badge">+' . $remaining . '</span></span>';
    }

    /**
     * Render switcher field
     */
    private function render_switcher($value, $field, $post) {
        if ($value === '1' || $value === 1 || $value === true) {
            return '<span class="vt-ac-switcher vt-ac-switcher-yes" title="' . esc_attr__('Yes', 'voxel-toolkit') . '"><span class="dashicons dashicons-yes-alt"></span></span>';
        }

        return '<span class="vt-ac-switcher vt-ac-switcher-no" title="' . esc_attr__('No', 'voxel-toolkit') . '"><span class="dashicons dashicons-minus"></span></span>';
    }

    /**
     * Render color field
     */
    private function render_color($value, $field, $post) {
        // Validate hex color
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return esc_html($value);
        }

        return '<span class="vt-ac-color"><span class="vt-ac-color-swatch" style="background-color: ' . esc_attr($value) . '"></span> ' . esc_html($value) . '</span>';
    }

    /**
     * Render image field
     */
    private function render_image($value, $field, $post, $column_config = null) {
        // Get image settings from column config
        $img_width = 60;
        $img_height = 60;
        $wp_size = 'thumbnail';
        if (isset($column_config['image_settings'])) {
            $img_width = isset($column_config['image_settings']['display_width']) ? $column_config['image_settings']['display_width'] : 60;
            $img_height = isset($column_config['image_settings']['display_height']) ? $column_config['image_settings']['display_height'] : 60;
            $wp_size = isset($column_config['image_settings']['wp_size']) ? $column_config['image_settings']['wp_size'] : 'thumbnail';
        }

        // Handle single image (attachment ID)
        if (is_numeric($value)) {
            return $this->render_single_image($value, $img_width, $img_height, $wp_size);
        }

        // Handle gallery (comma-separated IDs or array)
        if (is_string($value) && strpos($value, ',') !== false) {
            $ids = explode(',', $value);
            return $this->render_image_gallery($ids, $img_width, $img_height, $wp_size);
        }

        if (is_array($value)) {
            return $this->render_image_gallery($value, $img_width, $img_height, $wp_size);
        }

        return $this->empty_value();
    }

    /**
     * Render a single image
     */
    private function render_single_image($attachment_id, $width = 60, $height = 60, $wp_size = 'thumbnail') {
        $url = wp_get_attachment_image_url($attachment_id, $wp_size);

        if (!$url) {
            return $this->empty_value();
        }

        return '<img src="' . esc_url($url) . '" alt="" class="vt-ac-image" style="width:' . intval($width) . 'px;height:' . intval($height) . 'px;object-fit:cover;" />';
    }

    /**
     * Render image gallery
     */
    private function render_image_gallery($ids, $width = 60, $height = 60, $wp_size = 'thumbnail') {
        $ids = array_filter(array_map('absint', $ids));

        if (empty($ids)) {
            return $this->empty_value();
        }

        $first_url = wp_get_attachment_image_url($ids[0], $wp_size);

        if (!$first_url) {
            return $this->empty_value();
        }

        $count = count($ids);
        $output = '<span class="vt-ac-gallery">';
        $output .= '<img src="' . esc_url($first_url) . '" alt="" class="vt-ac-image" style="width:' . intval($width) . 'px;height:' . intval($height) . 'px;object-fit:cover;" />';

        if ($count > 1) {
            $output .= '<span class="vt-ac-badge">+' . ($count - 1) . '</span>';
        }

        $output .= '</span>';

        return $output;
    }

    /**
     * Render file field
     */
    private function render_file($value, $field, $post) {
        // Handle multiple files (comma-separated or array)
        $file_ids = array();

        if (is_array($value)) {
            $file_ids = array_filter($value, 'is_numeric');
        } elseif (is_string($value) && strpos($value, ',') !== false) {
            $file_ids = array_filter(explode(',', $value), 'is_numeric');
        } elseif (is_numeric($value)) {
            $file_ids = array(intval($value));
        }

        if (empty($file_ids)) {
            return $this->empty_value();
        }

        $links = array();
        $max_display = 3;

        foreach (array_slice($file_ids, 0, $max_display) as $file_id) {
            $file_id = intval($file_id);
            $url = wp_get_attachment_url($file_id);

            if (!$url) {
                continue;
            }

            $filename = basename(get_attached_file($file_id));
            $mime_type = get_post_mime_type($file_id);
            $icon = $this->get_file_icon($mime_type);

            $links[] = '<a href="' . esc_url($url) . '" target="_blank" class="vt-ac-file" title="' . esc_attr($filename) . '"><span class="dashicons ' . esc_attr($icon) . '"></span> ' . esc_html($this->truncate_string($filename, 25)) . '</a>';
        }

        if (empty($links)) {
            return $this->empty_value();
        }

        $output = '<span class="vt-ac-files">' . implode(' ', $links);

        if (count($file_ids) > $max_display) {
            $output .= ' <span class="vt-ac-badge">+' . (count($file_ids) - $max_display) . '</span>';
        }

        $output .= '</span>';

        return $output;
    }

    /**
     * Get appropriate dashicon for file type
     */
    private function get_file_icon($mime_type) {
        if (empty($mime_type)) {
            return 'dashicons-media-default';
        }

        // Check by mime type
        if (strpos($mime_type, 'image/') === 0) {
            return 'dashicons-format-image';
        }
        if (strpos($mime_type, 'video/') === 0) {
            return 'dashicons-video-alt3';
        }
        if (strpos($mime_type, 'audio/') === 0) {
            return 'dashicons-format-audio';
        }
        if ($mime_type === 'application/pdf') {
            return 'dashicons-pdf';
        }
        if (in_array($mime_type, array('application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))) {
            return 'dashicons-media-document';
        }
        if (in_array($mime_type, array('application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))) {
            return 'dashicons-media-spreadsheet';
        }
        if (in_array($mime_type, array('application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed'))) {
            return 'dashicons-media-archive';
        }
        if (strpos($mime_type, 'text/') === 0) {
            return 'dashicons-media-text';
        }

        return 'dashicons-media-default';
    }

    /**
     * Render location field
     */
    private function render_location($value, $field, $post, $column_config = null) {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return $this->empty_value();
        }

        // Get display mode from column config
        $display_mode = 'address';
        if (isset($column_config['location_settings']['display'])) {
            $display_mode = $column_config['location_settings']['display'];
        }

        $has_address = isset($value['address']) && !empty($value['address']);
        $has_lat = isset($value['latitude']) && is_numeric($value['latitude']);
        $has_lng = isset($value['longitude']) && is_numeric($value['longitude']);

        switch ($display_mode) {
            case 'address':
                if ($has_address) {
                    return '<span class="vt-ac-location" title="' . esc_attr($value['address']) . '"><span class="dashicons dashicons-location"></span> ' . $this->truncate($value['address'], 40) . '</span>';
                }
                // Fall back to coordinates if no address
                if ($has_lat && $has_lng) {
                    $coords = round($value['latitude'], 4) . ', ' . round($value['longitude'], 4);
                    return '<span class="vt-ac-location"><span class="dashicons dashicons-location"></span> ' . esc_html($coords) . '</span>';
                }
                return $this->empty_value();

            case 'coordinates':
                if ($has_lat && $has_lng) {
                    $coords = round($value['latitude'], 6) . ', ' . round($value['longitude'], 6);
                    return '<span class="vt-ac-location"><span class="dashicons dashicons-location"></span> ' . esc_html($coords) . '</span>';
                }
                return $this->empty_value();

            case 'latitude':
                if ($has_lat) {
                    return '<span class="vt-ac-location-coord">' . esc_html(round($value['latitude'], 6)) . '</span>';
                }
                return $this->empty_value();

            case 'longitude':
                if ($has_lng) {
                    return '<span class="vt-ac-location-coord">' . esc_html(round($value['longitude'], 6)) . '</span>';
                }
                return $this->empty_value();

            case 'full':
                // Show address + coordinates
                $parts = array();
                if ($has_address) {
                    $parts[] = '<span class="vt-ac-location-address">' . $this->truncate($value['address'], 30) . '</span>';
                }
                if ($has_lat && $has_lng) {
                    $coords = round($value['latitude'], 4) . ', ' . round($value['longitude'], 4);
                    $parts[] = '<span class="vt-ac-location-coords vt-ac-muted">(' . esc_html($coords) . ')</span>';
                }
                if (empty($parts)) {
                    return $this->empty_value();
                }
                return '<span class="vt-ac-location"><span class="dashicons dashicons-location"></span> ' . implode(' ', $parts) . '</span>';

            default:
                // Default to address behavior
                if ($has_address) {
                    return '<span class="vt-ac-location" title="' . esc_attr($value['address']) . '"><span class="dashicons dashicons-location"></span> ' . $this->truncate($value['address'], 40) . '</span>';
                }
                if ($has_lat && $has_lng) {
                    $coords = round($value['latitude'], 4) . ', ' . round($value['longitude'], 4);
                    return '<span class="vt-ac-location"><span class="dashicons dashicons-location"></span> ' . esc_html($coords) . '</span>';
                }
                return $this->empty_value();
        }
    }

    /**
     * Render timezone field
     */
    private function render_timezone($value, $field, $post) {
        return '<span class="vt-ac-timezone"><span class="dashicons dashicons-clock"></span> ' . esc_html($value) . '</span>';
    }

    /**
     * Render work hours field
     */
    private function render_work_hours($value, $field, $post, $column_config = null) {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value) || empty($value)) {
            return $this->empty_value();
        }

        // DEBUG: Uncomment to see data structure
        // return '<pre style="font-size:10px;max-width:300px;overflow:auto;">' . esc_html(print_r($value, true)) . '</pre>';

        // Get display mode from column config
        $display_mode = 'status';
        if (isset($column_config['work_hours_settings']['display'])) {
            $display_mode = $column_config['work_hours_settings']['display'];
        }

        switch ($display_mode) {
            case 'status':
                return $this->render_work_hours_status($value);

            case 'today':
                return $this->render_work_hours_today($value);

            case 'badge':
            default:
                return '<span class="vt-ac-badge vt-ac-work-hours"><span class="dashicons dashicons-clock"></span> ' . __('Hours Set', 'voxel-toolkit') . '</span>';
        }
    }

    /**
     * Find the rule that applies to a given day
     */
    private function find_work_hours_rule_for_day($rules, $day_key) {
        foreach ($rules as $rule) {
            if (isset($rule['days']) && is_array($rule['days'])) {
                if (in_array($day_key, $rule['days'])) {
                    return $rule;
                }
            }
        }
        return null;
    }

    /**
     * Render work hours current status (Open/Closed)
     */
    private function render_work_hours_status($rules) {
        // Get current day and time using WordPress timezone
        $current_day = strtolower(current_time('l')); // monday, tuesday, etc.
        $current_time = current_time('H:i');

        // Day mapping for Voxel format
        $day_map = array(
            'sunday' => 'sun',
            'monday' => 'mon',
            'tuesday' => 'tue',
            'wednesday' => 'wed',
            'thursday' => 'thu',
            'friday' => 'fri',
            'saturday' => 'sat'
        );

        $day_key = isset($day_map[$current_day]) ? $day_map[$current_day] : $current_day;

        // Find the rule that applies to today
        $today_rule = $this->find_work_hours_rule_for_day($rules, $day_key);

        if (!$today_rule) {
            return '<span class="vt-ac-status-badge vt-ac-status-closed"><span class="dashicons dashicons-minus"></span> ' . __('Closed', 'voxel-toolkit') . '</span>';
        }

        $status = isset($today_rule['status']) ? $today_rule['status'] : '';

        // Handle different statuses
        switch ($status) {
            case 'open':
                // Open all day
                return '<span class="vt-ac-status-badge vt-ac-status-open"><span class="dashicons dashicons-yes"></span> ' . __('Open', 'voxel-toolkit') . '</span>';

            case 'closed':
                return '<span class="vt-ac-status-badge vt-ac-status-closed"><span class="dashicons dashicons-minus"></span> ' . __('Closed', 'voxel-toolkit') . '</span>';

            case 'appointments_only':
            case 'appointment':
            case 'by_appointment':
                return '<span class="vt-ac-status-badge vt-ac-status-appointment"><span class="dashicons dashicons-calendar-alt"></span> ' . __('By Appointment', 'voxel-toolkit') . '</span>';

            case 'hours':
                // Check specific hours
                if (isset($today_rule['hours']) && is_array($today_rule['hours'])) {
                    foreach ($today_rule['hours'] as $slot) {
                        $open_time = isset($slot['from']) ? $slot['from'] : (isset($slot['open']) ? $slot['open'] : null);
                        $close_time = isset($slot['to']) ? $slot['to'] : (isset($slot['close']) ? $slot['close'] : null);

                        if ($open_time && $close_time) {
                            $open_time = date('H:i', strtotime($open_time));
                            $close_time = date('H:i', strtotime($close_time));

                            if ($current_time >= $open_time && $current_time <= $close_time) {
                                return '<span class="vt-ac-status-badge vt-ac-status-open"><span class="dashicons dashicons-yes"></span> ' . __('Open', 'voxel-toolkit') . '</span>';
                            }
                        }
                    }
                }
                return '<span class="vt-ac-status-badge vt-ac-status-closed"><span class="dashicons dashicons-minus"></span> ' . __('Closed', 'voxel-toolkit') . '</span>';

            default:
                // Unknown status, check if there are hours defined
                if (!empty($today_rule['hours'])) {
                    foreach ($today_rule['hours'] as $slot) {
                        $open_time = isset($slot['from']) ? $slot['from'] : (isset($slot['open']) ? $slot['open'] : null);
                        $close_time = isset($slot['to']) ? $slot['to'] : (isset($slot['close']) ? $slot['close'] : null);

                        if ($open_time && $close_time) {
                            $open_time = date('H:i', strtotime($open_time));
                            $close_time = date('H:i', strtotime($close_time));

                            if ($current_time >= $open_time && $current_time <= $close_time) {
                                return '<span class="vt-ac-status-badge vt-ac-status-open"><span class="dashicons dashicons-yes"></span> ' . __('Open', 'voxel-toolkit') . '</span>';
                            }
                        }
                    }
                    return '<span class="vt-ac-status-badge vt-ac-status-closed"><span class="dashicons dashicons-minus"></span> ' . __('Closed', 'voxel-toolkit') . '</span>';
                }
                return '<span class="vt-ac-status-badge vt-ac-status-closed"><span class="dashicons dashicons-minus"></span> ' . __('Closed', 'voxel-toolkit') . '</span>';
        }
    }

    /**
     * Render today's work hours
     */
    private function render_work_hours_today($rules) {
        // Get current day using WordPress timezone
        $current_day = strtolower(current_time('l'));

        // Day mapping for Voxel format
        $day_map = array(
            'sunday' => 'sun',
            'monday' => 'mon',
            'tuesday' => 'tue',
            'wednesday' => 'wed',
            'thursday' => 'thu',
            'friday' => 'fri',
            'saturday' => 'sat'
        );

        $day_key = isset($day_map[$current_day]) ? $day_map[$current_day] : $current_day;

        // Find the rule that applies to today
        $today_rule = $this->find_work_hours_rule_for_day($rules, $day_key);

        if (!$today_rule) {
            return '<span class="vt-ac-hours-today vt-ac-muted">' . __('Closed today', 'voxel-toolkit') . '</span>';
        }

        $status = isset($today_rule['status']) ? $today_rule['status'] : '';

        // Handle different statuses
        switch ($status) {
            case 'open':
                return '<span class="vt-ac-hours-today"><span class="dashicons dashicons-clock"></span> ' . __('Open 24 Hours', 'voxel-toolkit') . '</span>';

            case 'closed':
                return '<span class="vt-ac-hours-today vt-ac-muted">' . __('Closed today', 'voxel-toolkit') . '</span>';

            case 'appointments_only':
            case 'appointment':
            case 'by_appointment':
                return '<span class="vt-ac-hours-today">' . __('By Appointment', 'voxel-toolkit') . '</span>';

            case 'hours':
            default:
                // Show specific hours
                if (isset($today_rule['hours']) && is_array($today_rule['hours']) && !empty($today_rule['hours'])) {
                    $time_strings = array();

                    foreach ($today_rule['hours'] as $slot) {
                        $open_time = isset($slot['from']) ? $slot['from'] : (isset($slot['open']) ? $slot['open'] : null);
                        $close_time = isset($slot['to']) ? $slot['to'] : (isset($slot['close']) ? $slot['close'] : null);

                        if ($open_time && $close_time) {
                            $open_formatted = date_i18n(get_option('time_format'), strtotime($open_time));
                            $close_formatted = date_i18n(get_option('time_format'), strtotime($close_time));
                            $time_strings[] = $open_formatted . ' - ' . $close_formatted;
                        }
                    }

                    if (!empty($time_strings)) {
                        return '<span class="vt-ac-hours-today"><span class="dashicons dashicons-clock"></span> ' . esc_html(implode(', ', $time_strings)) . '</span>';
                    }
                }

                // If status is 'open' with no hours, it's open all day
                if ($status === 'open' || empty($status)) {
                    return '<span class="vt-ac-hours-today"><span class="dashicons dashicons-clock"></span> ' . __('Open 24 Hours', 'voxel-toolkit') . '</span>';
                }

                return '<span class="vt-ac-hours-today vt-ac-muted">' . __('Closed today', 'voxel-toolkit') . '</span>';
        }
    }

    /**
     * Render recurring date field
     */
    private function render_recurring_date($value, $field, $post, $column_config = null) {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value) || empty($value)) {
            return $this->empty_value();
        }

        // Handle array of events - use first one
        $event = isset($value[0]) ? $value[0] : $value;

        // Get display mode from column config
        $display_mode = 'start_date';
        if (isset($column_config['recurring_date_settings']['display'])) {
            $display_mode = $column_config['recurring_date_settings']['display'];
        }

        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        // Extract event data
        $start = isset($event['start']) ? $event['start'] : null;
        $end = isset($event['end']) ? $event['end'] : null;
        $frequency = isset($event['frequency']) ? intval($event['frequency']) : 1;
        $unit = isset($event['unit']) ? $event['unit'] : '';
        $until = isset($event['until']) ? $event['until'] : null;
        $multiday = isset($event['multiday']) ? $event['multiday'] : false;
        $allday = isset($event['allday']) ? $event['allday'] : false;

        switch ($display_mode) {
            case 'start_date':
                if ($start) {
                    $timestamp = strtotime($start);
                    if ($timestamp !== false) {
                        $formatted = date_i18n($date_format, $timestamp);
                        return '<span class="vt-ac-recurring-date"><span class="dashicons dashicons-calendar-alt"></span> ' . esc_html($formatted) . '</span>';
                    }
                }
                return $this->empty_value();

            case 'start_datetime':
                if ($start) {
                    $timestamp = strtotime($start);
                    if ($timestamp !== false) {
                        $formatted = $allday
                            ? date_i18n($date_format, $timestamp) . ' (' . __('All day', 'voxel-toolkit') . ')'
                            : date_i18n($date_format . ' ' . $time_format, $timestamp);
                        return '<span class="vt-ac-recurring-date"><span class="dashicons dashicons-calendar-alt"></span> ' . esc_html($formatted) . '</span>';
                    }
                }
                return $this->empty_value();

            case 'end_date':
                if ($end) {
                    $timestamp = strtotime($end);
                    if ($timestamp !== false) {
                        $formatted = date_i18n($date_format, $timestamp);
                        return '<span class="vt-ac-recurring-date"><span class="dashicons dashicons-calendar-alt"></span> ' . esc_html($formatted) . '</span>';
                    }
                }
                return $this->empty_value();

            case 'end_datetime':
                if ($end) {
                    $timestamp = strtotime($end);
                    if ($timestamp !== false) {
                        $formatted = $allday
                            ? date_i18n($date_format, $timestamp) . ' (' . __('All day', 'voxel-toolkit') . ')'
                            : date_i18n($date_format . ' ' . $time_format, $timestamp);
                        return '<span class="vt-ac-recurring-date"><span class="dashicons dashicons-calendar-alt"></span> ' . esc_html($formatted) . '</span>';
                    }
                }
                return $this->empty_value();

            case 'date_range':
                if ($start && $end) {
                    $start_ts = strtotime($start);
                    $end_ts = strtotime($end);
                    if ($start_ts !== false && $end_ts !== false) {
                        $start_fmt = date_i18n($date_format, $start_ts);
                        $end_fmt = date_i18n($date_format, $end_ts);
                        if ($start_fmt === $end_fmt) {
                            return '<span class="vt-ac-recurring-date"><span class="dashicons dashicons-calendar-alt"></span> ' . esc_html($start_fmt) . '</span>';
                        }
                        return '<span class="vt-ac-recurring-date"><span class="dashicons dashicons-calendar-alt"></span> ' . esc_html($start_fmt . ' - ' . $end_fmt) . '</span>';
                    }
                }
                return $this->empty_value();

            case 'frequency':
                return $this->render_event_frequency($frequency, $unit, $until);

            case 'multiday':
                if ($multiday) {
                    return '<span class="vt-ac-badge vt-ac-event-multiday"><span class="dashicons dashicons-calendar"></span> ' . __('Multi-day', 'voxel-toolkit') . '</span>';
                }
                return '<span class="vt-ac-badge vt-ac-muted">' . __('Single day', 'voxel-toolkit') . '</span>';

            case 'allday':
                if ($allday) {
                    return '<span class="vt-ac-badge vt-ac-event-allday"><span class="dashicons dashicons-clock"></span> ' . __('All day', 'voxel-toolkit') . '</span>';
                }
                return '<span class="vt-ac-badge vt-ac-muted">' . __('Timed', 'voxel-toolkit') . '</span>';

            case 'summary':
                return $this->render_event_summary($event);

            default:
                if ($start) {
                    $timestamp = strtotime($start);
                    if ($timestamp !== false) {
                        $formatted = date_i18n($date_format, $timestamp);
                        return '<span class="vt-ac-recurring-date"><span class="dashicons dashicons-calendar-alt"></span> ' . esc_html($formatted) . '</span>';
                    }
                }
                return '<span class="vt-ac-badge"><span class="dashicons dashicons-calendar-alt"></span> ' . __('Scheduled', 'voxel-toolkit') . '</span>';
        }
    }

    /**
     * Render event frequency in natural language
     */
    private function render_event_frequency($frequency, $unit, $until) {
        if (empty($unit)) {
            return '<span class="vt-ac-badge vt-ac-muted">' . __('One-time', 'voxel-toolkit') . '</span>';
        }

        // Build frequency string
        $freq_str = '';

        switch ($unit) {
            case 'day':
                if ($frequency == 1) {
                    $freq_str = __('Daily', 'voxel-toolkit');
                } else {
                    $freq_str = sprintf(__('Every %d days', 'voxel-toolkit'), $frequency);
                }
                break;

            case 'week':
                if ($frequency == 1) {
                    $freq_str = __('Weekly', 'voxel-toolkit');
                } else {
                    $freq_str = sprintf(__('Every %d weeks', 'voxel-toolkit'), $frequency);
                }
                break;

            case 'month':
                if ($frequency == 1) {
                    $freq_str = __('Monthly', 'voxel-toolkit');
                } else {
                    $freq_str = sprintf(__('Every %d months', 'voxel-toolkit'), $frequency);
                }
                break;

            case 'year':
                if ($frequency == 1) {
                    $freq_str = __('Yearly', 'voxel-toolkit');
                } else {
                    $freq_str = sprintf(__('Every %d years', 'voxel-toolkit'), $frequency);
                }
                break;

            default:
                $freq_str = __('Recurring', 'voxel-toolkit');
        }

        // Add until date if present
        if ($until) {
            $until_ts = strtotime($until);
            if ($until_ts !== false) {
                $until_fmt = date_i18n(get_option('date_format'), $until_ts);
                $freq_str .= ' ' . sprintf(__('until %s', 'voxel-toolkit'), $until_fmt);
            }
        }

        return '<span class="vt-ac-event-frequency"><span class="dashicons dashicons-update"></span> ' . esc_html($freq_str) . '</span>';
    }

    /**
     * Render event summary (start + frequency)
     */
    private function render_event_summary($event) {
        $parts = array();
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        $start = isset($event['start']) ? $event['start'] : null;
        $allday = isset($event['allday']) ? $event['allday'] : false;
        $frequency = isset($event['frequency']) ? intval($event['frequency']) : 1;
        $unit = isset($event['unit']) ? $event['unit'] : '';
        $multiday = isset($event['multiday']) ? $event['multiday'] : false;

        // Start date/time
        if ($start) {
            $timestamp = strtotime($start);
            if ($timestamp !== false) {
                if ($allday) {
                    $parts[] = '<span class="vt-ac-event-start">' . date_i18n($date_format, $timestamp) . '</span>';
                } else {
                    $parts[] = '<span class="vt-ac-event-start">' . date_i18n($date_format . ' ' . $time_format, $timestamp) . '</span>';
                }
            }
        }

        // Badges
        $badges = array();

        if (!empty($unit)) {
            switch ($unit) {
                case 'day':
                    $badges[] = $frequency == 1 ? __('Daily', 'voxel-toolkit') : sprintf(__('Every %dd', 'voxel-toolkit'), $frequency);
                    break;
                case 'week':
                    $badges[] = $frequency == 1 ? __('Weekly', 'voxel-toolkit') : sprintf(__('Every %dw', 'voxel-toolkit'), $frequency);
                    break;
                case 'month':
                    $badges[] = $frequency == 1 ? __('Monthly', 'voxel-toolkit') : sprintf(__('Every %dm', 'voxel-toolkit'), $frequency);
                    break;
                case 'year':
                    $badges[] = $frequency == 1 ? __('Yearly', 'voxel-toolkit') : sprintf(__('Every %dy', 'voxel-toolkit'), $frequency);
                    break;
            }
        }

        if ($multiday) {
            $badges[] = __('Multi-day', 'voxel-toolkit');
        }

        if ($allday) {
            $badges[] = __('All day', 'voxel-toolkit');
        }

        if (!empty($badges)) {
            $parts[] = '<span class="vt-ac-badge">' . esc_html(implode(' · ', $badges)) . '</span>';
        }

        if (empty($parts)) {
            return '<span class="vt-ac-badge"><span class="dashicons dashicons-calendar-alt"></span> ' . __('Scheduled', 'voxel-toolkit') . '</span>';
        }

        return '<span class="vt-ac-event-summary">' . implode(' ', $parts) . '</span>';
    }

    /**
     * Render event-date field (alias for recurring-date)
     */
    private function render_event_date($value, $field, $post, $column_config = null) {
        return $this->render_recurring_date($value, $field, $post, $column_config);
    }

    /**
     * Render repeater field
     */
    private function render_repeater($value, $field, $post) {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return $this->empty_value();
        }

        $count = count($value);

        if ($count === 0) {
            return $this->empty_value();
        }

        // Try to show repeater content minimally
        $items = array();
        $max_items = 3; // Show up to 3 items
        $max_char_per_item = 25;

        foreach (array_slice($value, 0, $max_items) as $row) {
            if (!is_array($row)) {
                continue;
            }

            // Try to find a displayable value in order of preference
            $display_value = $this->extract_repeater_display_value($row, $max_char_per_item);
            if ($display_value) {
                $items[] = $display_value;
            }
        }

        // If we couldn't extract any meaningful values, fall back to row count
        if (empty($items)) {
            return '<span class="vt-ac-badge vt-ac-repeater">' . sprintf(_n('%d row', '%d rows', $count, 'voxel-toolkit'), $count) . '</span>';
        }

        $output = '<span class="vt-ac-repeater-list">';
        $output .= implode('<span class="vt-ac-repeater-sep">&bull;</span>', $items);

        if ($count > $max_items) {
            $output .= ' <span class="vt-ac-badge">+' . ($count - $max_items) . '</span>';
        }

        $output .= '</span>';

        return $output;
    }

    /**
     * Extract a displayable value from a repeater row
     */
    private function extract_repeater_display_value($row, $max_length = 25) {
        // Priority keys to look for (most likely to be the "title" or identifier)
        $priority_keys = array(
            'title', 'name', 'label', 'heading', 'text', 'item',
            'item_name', 'menu_item', 'option', 'value', 'content', 'description'
        );

        // First, check priority keys for string values
        foreach ($priority_keys as $key) {
            if (isset($row[$key]) && is_string($row[$key]) && !empty(trim($row[$key]))) {
                return '<span class="vt-ac-repeater-item">' . esc_html($this->truncate_string(trim($row[$key]), $max_length)) . '</span>';
            }
        }

        // Second, find the first non-empty string value
        foreach ($row as $key => $val) {
            // Skip certain keys that are unlikely to be display values
            if (in_array($key, array('id', 'key', 'type', 'order', 'sort', 'enabled', 'image', 'icon'))) {
                continue;
            }

            if (is_string($val) && !empty(trim($val))) {
                return '<span class="vt-ac-repeater-item">' . esc_html($this->truncate_string(trim($val), $max_length)) . '</span>';
            }
        }

        // Third, build a summary from numeric/simple values
        $summary_parts = array();
        foreach ($row as $key => $val) {
            // Skip non-displayable keys
            if (in_array($key, array('id', 'key', 'type', 'order', 'sort', 'enabled', 'image', 'icon'))) {
                continue;
            }

            if (is_numeric($val)) {
                // Format the key nicely and show with value
                $nice_key = ucfirst(str_replace(array('-', '_'), ' ', $key));
                $summary_parts[] = $nice_key . ': ' . number_format_i18n($val);
            } elseif (is_bool($val)) {
                $nice_key = ucfirst(str_replace(array('-', '_'), ' ', $key));
                $summary_parts[] = $nice_key . ': ' . ($val ? __('Yes', 'voxel-toolkit') : __('No', 'voxel-toolkit'));
            }

            // Limit to 2 summary items
            if (count($summary_parts) >= 2) {
                break;
            }
        }

        if (!empty($summary_parts)) {
            $summary = implode(', ', $summary_parts);
            return '<span class="vt-ac-repeater-item">' . esc_html($this->truncate_string($summary, $max_length + 10)) . '</span>';
        }

        // Fourth, if there's a price, show it
        if (isset($row['price']) && is_numeric($row['price'])) {
            return '<span class="vt-ac-repeater-item vt-ac-price">' . $this->format_price(floatval($row['price'])) . '</span>';
        }

        // Fifth, check if row has sub-items (like choices)
        if (isset($row['choices']) && is_array($row['choices'])) {
            $choice_count = count($row['choices']);
            if ($choice_count > 0) {
                // Get first choice name
                $first_key = array_key_first($row['choices']);
                if ($first_key && is_string($first_key)) {
                    $display = ucfirst(str_replace(array('-', '_'), ' ', $first_key));
                    if ($choice_count > 1) {
                        return '<span class="vt-ac-repeater-item">' . esc_html($this->truncate_string($display, $max_length)) . ' <span class="vt-ac-badge">+' . ($choice_count - 1) . '</span></span>';
                    }
                    return '<span class="vt-ac-repeater-item">' . esc_html($this->truncate_string($display, $max_length)) . '</span>';
                }
            }
        }

        // Last resort: show number of fields in the row
        $field_count = count($row);
        if ($field_count > 0) {
            return '<span class="vt-ac-repeater-item vt-ac-muted">' . sprintf(_n('%d field', '%d fields', $field_count, 'voxel-toolkit'), $field_count) . '</span>';
        }

        return null;
    }

    /**
     * Simple string truncation helper
     */
    private function truncate_string($text, $max_length) {
        if (mb_strlen($text) <= $max_length) {
            return $text;
        }
        return mb_substr($text, 0, $max_length) . '...';
    }

    /**
     * Render product field
     */
    private function render_product($value, $field, $post, $column_config = null) {
        // Try to get the product config from the field directly
        $product_config = null;

        // Method 1: Value is already an array
        if (is_array($value) && !empty($value)) {
            $product_config = $value;
        }
        // Method 2: Value is JSON string
        elseif (is_string($value) && !empty($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $product_config = $decoded;
            }
        }

        // Method 3: Try to get the field's props which contain product configuration
        if (empty($product_config) && $field) {
            // For Voxel product fields, the configuration is stored in field props
            $product_config = array();

            // Get all props from the field definition
            if (method_exists($field, 'get_props')) {
                $props = $field->get_props();
                if (is_array($props) && !empty($props)) {
                    $product_config = $props;
                }
            }

            // Also try individual prop access
            if (method_exists($field, 'get_prop')) {
                // Product mode/type
                foreach (array('product-type', 'product_type', 'mode') as $key) {
                    $val = $field->get_prop($key);
                    if ($val) {
                        $product_config['product_type'] = $val;
                        break;
                    }
                }

                // Calendar settings
                $calendar = $field->get_prop('calendar');
                if ($calendar) {
                    $product_config['calendar'] = $calendar;
                }

                // Base price
                $base_price = $field->get_prop('base-price');
                if ($base_price !== null) {
                    $product_config['base_price'] = $base_price;
                }
            }
        }

        // Method 4: Get from post meta directly with field key
        if (empty($product_config) && $post && $field) {
            $post_id = $post->get_id();
            $field_key = $field->get_key();

            if ($field_key) {
                $meta_value = get_post_meta($post_id, $field_key, true);
                if (is_array($meta_value) && !empty($meta_value)) {
                    $product_config = $meta_value;
                } elseif (is_string($meta_value) && !empty($meta_value)) {
                    $decoded = json_decode($meta_value, true);
                    if (is_array($decoded) && !empty($decoded)) {
                        $product_config = $decoded;
                    }
                }
            }
        }

        // Method 5: Try to get product data using Voxel's product API if available
        if (empty($product_config) && $post && class_exists('\Voxel\Product')) {
            try {
                $voxel_post = $post;
                if (method_exists($voxel_post, 'get_product')) {
                    $product = $voxel_post->get_product();
                    if ($product) {
                        $product_config = array();
                        if (method_exists($product, 'get_product_type')) {
                            $product_config['product_type'] = $product->get_product_type();
                        }
                        if (method_exists($product, 'get_base_price')) {
                            $product_config['base_price'] = $product->get_base_price();
                        }
                        if (method_exists($product, 'get_pricing')) {
                            $product_config['pricing'] = $product->get_pricing();
                        }
                    }
                }
            } catch (\Exception $e) {
                // Silently fail
            }
        }

        if (empty($product_config) || !is_array($product_config)) {
            return $this->empty_value();
        }

        // Get display mode from column config
        $display_mode = 'price';
        if (isset($column_config['product_settings']['display'])) {
            $display_mode = $column_config['product_settings']['display'];
        }

        switch ($display_mode) {
            case 'price':
                return $this->render_product_price($product_config);

            case 'discounted_price':
                return $this->render_product_discounted_price($product_config);

            case 'price_range':
                return $this->render_product_price_range($product_config);

            case 'product_type':
                $type = $this->get_product_type($product_config);
                if ($type) {
                    // Format nicely (restaurant-menu -> Restaurant Menu)
                    $formatted = ucwords(str_replace(array('-', '_'), ' ', $type));
                    return '<span class="vt-ac-badge vt-ac-product">' . esc_html($formatted) . '</span>';
                }
                return $this->empty_value();

            case 'booking_type':
                $booking_type = $this->get_product_booking_type($product_config);
                if ($booking_type) {
                    return '<span class="vt-ac-badge">' . esc_html($booking_type) . '</span>';
                }
                return $this->empty_value();

            case 'stock':
                return $this->render_product_stock($product_config);

            case 'calendar':
                $calendar_type = $this->get_product_calendar_type($product_config);
                if ($calendar_type) {
                    return '<span class="vt-ac-badge"><span class="dashicons dashicons-calendar-alt"></span> ' . esc_html($calendar_type) . '</span>';
                }
                return $this->empty_value();

            case 'deliverables':
                return $this->render_product_deliverables($product_config);

            case 'summary':
                return $this->render_product_summary($product_config);

            default:
                return $this->render_product_price($product_config);
        }
    }

    /**
     * Get product type from config
     */
    private function get_product_type($config) {
        // Check various possible keys
        $keys = array('product_type', 'type', 'mode', 'product_mode');
        foreach ($keys as $key) {
            if (isset($config[$key]) && !empty($config[$key])) {
                return $config[$key];
            }
        }
        return null;
    }

    /**
     * Get product booking type from config
     */
    private function get_product_booking_type($config) {
        if (isset($config['calendar']['type'])) {
            return ucfirst(str_replace('_', ' ', $config['calendar']['type']));
        }
        if (isset($config['booking_type'])) {
            return ucfirst(str_replace('_', ' ', $config['booking_type']));
        }
        if (isset($config['calendar_type'])) {
            return ucfirst(str_replace('_', ' ', $config['calendar_type']));
        }
        return null;
    }

    /**
     * Get product calendar type from config
     */
    private function get_product_calendar_type($config) {
        return $this->get_product_booking_type($config);
    }

    /**
     * Extract all prices from Voxel product config
     */
    private function extract_product_prices($value) {
        $prices = array();

        // Check for base_price as object with amount (Voxel format)
        if (isset($value['base_price'])) {
            if (is_array($value['base_price'])) {
                // base_price.amount
                if (isset($value['base_price']['amount']) && is_numeric($value['base_price']['amount'])) {
                    $prices[] = floatval($value['base_price']['amount']);
                }
                // base_price.discount_amount (sale price)
                if (isset($value['base_price']['discount_amount']) && is_numeric($value['base_price']['discount_amount'])) {
                    $prices[] = floatval($value['base_price']['discount_amount']);
                }
            } elseif (is_numeric($value['base_price'])) {
                $prices[] = floatval($value['base_price']);
            }
        }

        // Check Voxel variations structure: variations.variations.{id}.config.base_price.amount
        if (isset($value['variations']['variations']) && is_array($value['variations']['variations'])) {
            foreach ($value['variations']['variations'] as $variation) {
                if (isset($variation['config']['base_price']['amount']) && is_numeric($variation['config']['base_price']['amount'])) {
                    $prices[] = floatval($variation['config']['base_price']['amount']);
                }
            }
        }

        // Check Voxel addons structure: addons -> addon_key -> choices -> item_name -> price
        if (isset($value['addons']) && is_array($value['addons'])) {
            foreach ($value['addons'] as $addon) {
                if (isset($addon['choices']) && is_array($addon['choices'])) {
                    foreach ($addon['choices'] as $choice) {
                        if (isset($choice['price']) && is_numeric($choice['price'])) {
                            $prices[] = floatval($choice['price']);
                        }
                    }
                }
                // Direct price in addon
                if (isset($addon['price']) && is_numeric($addon['price'])) {
                    $prices[] = floatval($addon['price']);
                }
            }
        }

        // Check old additions structure
        if (isset($value['additions']) && is_array($value['additions'])) {
            foreach ($value['additions'] as $addition) {
                if (isset($addition['price']) && is_numeric($addition['price'])) {
                    $prices[] = floatval($addition['price']);
                }
            }
        }

        return $prices;
    }

    /**
     * Render product base price (original price, not discounted)
     */
    private function render_product_price($value) {
        // Get base price amount (not discount_amount)
        $base_price = null;

        if (isset($value['base_price'])) {
            if (is_array($value['base_price']) && isset($value['base_price']['amount'])) {
                $base_price = floatval($value['base_price']['amount']);
            } elseif (is_numeric($value['base_price'])) {
                $base_price = floatval($value['base_price']);
            }
        }

        if ($base_price !== null) {
            return '<span class="vt-ac-price">' . $this->format_price($base_price) . '</span>';
        }

        // Fall back to variations for other product structures
        $prices = array();
        if (isset($value['variations']['variations']) && is_array($value['variations']['variations'])) {
            foreach ($value['variations']['variations'] as $variation) {
                if (isset($variation['config']['base_price']['amount']) && is_numeric($variation['config']['base_price']['amount'])) {
                    $prices[] = floatval($variation['config']['base_price']['amount']);
                }
            }
        }

        if (!empty($prices)) {
            $min = min($prices);
            if (count($prices) > 1) {
                return '<span class="vt-ac-price">' . __('From', 'voxel-toolkit') . ' ' . $this->format_price($min) . '</span>';
            }
            return '<span class="vt-ac-price">' . $this->format_price($min) . '</span>';
        }

        return $this->empty_value();
    }

    /**
     * Render product price range
     */
    private function render_product_price_range($value) {
        $prices = $this->extract_product_prices($value);

        if (empty($prices)) {
            return $this->empty_value();
        }

        $min = min($prices);
        $max = max($prices);

        if ($min === $max) {
            return '<span class="vt-ac-price">' . $this->format_price($min) . '</span>';
        }

        return '<span class="vt-ac-price">' . $this->format_price($min) . ' - ' . $this->format_price($max) . '</span>';
    }

    /**
     * Render product discounted price
     */
    private function render_product_discounted_price($value) {
        $discounted_price = null;

        // Check for base_price with discount_amount
        if (isset($value['base_price']['discount_amount'])) {
            $discounted_price = floatval($value['base_price']['discount_amount']);
        }

        // If no discounted price, check additions
        if ($discounted_price === null && isset($value['additions']) && is_array($value['additions'])) {
            foreach ($value['additions'] as $addition) {
                if (isset($addition['price']['discount_amount'])) {
                    $discounted_price = floatval($addition['price']['discount_amount']);
                    break;
                }
            }
        }

        // Show discounted price
        if ($discounted_price !== null) {
            return '<span class="vt-ac-price vt-ac-price-sale">' . $this->format_price($discounted_price) . '</span>';
        }

        return $this->empty_value();
    }

    /**
     * Render product deliverables
     */
    private function render_product_deliverables($value) {
        $deliverables = array();

        // Check for deliverables in config
        if (isset($value['deliverables']) && is_array($value['deliverables'])) {
            foreach ($value['deliverables'] as $key => $deliverable_value) {
                if ($key === 'files' && !empty($deliverable_value)) {
                    // Files can be a comma-separated string of attachment IDs or an array
                    $file_ids = is_array($deliverable_value) ? $deliverable_value : explode(',', $deliverable_value);
                    $file_ids = array_filter(array_map('intval', $file_ids));
                    $file_count = count($file_ids);

                    if ($file_count === 1) {
                        // Single file - show title and link like file field
                        $attachment_id = reset($file_ids);
                        $attachment = get_post($attachment_id);
                        if ($attachment) {
                            $title = $attachment->post_title ?: basename(get_attached_file($attachment_id));
                            $url = wp_get_attachment_url($attachment_id);
                            $deliverables[] = '<a href="' . esc_url($url) . '" target="_blank" class="vt-ac-file-link">' .
                                              '<span class="dashicons dashicons-media-default"></span> ' .
                                              esc_html($title) .
                                              '</a>';
                        }
                    } elseif ($file_count > 1) {
                        // Multiple files - show count
                        $deliverables[] = '<span class="vt-ac-deliverable vt-ac-deliverable-files">' .
                                          '<span class="dashicons dashicons-media-default"></span> ' .
                                          sprintf(_n('%d file', '%d files', $file_count, 'voxel-toolkit'), $file_count) .
                                          '</span>';
                    }
                } elseif ($key === 'downloads' && !empty($deliverable_value)) {
                    $download_ids = is_array($deliverable_value) ? $deliverable_value : explode(',', $deliverable_value);
                    $download_ids = array_filter(array_map('intval', $download_ids));
                    $download_count = count($download_ids);

                    if ($download_count === 1) {
                        // Single download - show title and link
                        $attachment_id = reset($download_ids);
                        $attachment = get_post($attachment_id);
                        if ($attachment) {
                            $title = $attachment->post_title ?: basename(get_attached_file($attachment_id));
                            $url = wp_get_attachment_url($attachment_id);
                            $deliverables[] = '<a href="' . esc_url($url) . '" target="_blank" class="vt-ac-file-link">' .
                                              '<span class="dashicons dashicons-download"></span> ' .
                                              esc_html($title) .
                                              '</a>';
                        }
                    } elseif ($download_count > 1) {
                        // Multiple downloads - show count
                        $deliverables[] = '<span class="vt-ac-deliverable vt-ac-deliverable-downloads">' .
                                          '<span class="dashicons dashicons-download"></span> ' .
                                          sprintf(_n('%d download', '%d downloads', $download_count, 'voxel-toolkit'), $download_count) .
                                          '</span>';
                    }
                } elseif (!empty($deliverable_value) && is_string($deliverable_value)) {
                    // Generic deliverable
                    $formatted_key = ucwords(str_replace(array('-', '_'), ' ', $key));
                    $deliverables[] = '<span class="vt-ac-deliverable">' . esc_html($formatted_key) . '</span>';
                }
            }
        }

        if (empty($deliverables)) {
            return $this->empty_value();
        }

        return '<span class="vt-ac-deliverables">' . implode(' ', $deliverables) . '</span>';
    }

    /**
     * Render product stock status
     */
    private function render_product_stock($value) {
        // Check for inventory/stock settings
        if (isset($value['inventory']) && isset($value['inventory']['enabled']) && $value['inventory']['enabled']) {
            if (isset($value['inventory']['quantity'])) {
                $qty = intval($value['inventory']['quantity']);
                if ($qty > 0) {
                    return '<span class="vt-ac-badge vt-ac-stock-in">' . sprintf(__('%d in stock', 'voxel-toolkit'), $qty) . '</span>';
                } else {
                    return '<span class="vt-ac-badge vt-ac-stock-out">' . __('Out of stock', 'voxel-toolkit') . '</span>';
                }
            }
        }

        // Check for variations stock (sum all variation quantities)
        if (isset($value['variations']['variations']) && is_array($value['variations']['variations'])) {
            $total_stock = 0;
            $has_stock_tracking = false;

            foreach ($value['variations']['variations'] as $variation) {
                if (isset($variation['config']['stock']['enabled']) && $variation['config']['stock']['enabled']) {
                    $has_stock_tracking = true;
                    if (isset($variation['config']['stock']['quantity'])) {
                        $total_stock += intval($variation['config']['stock']['quantity']);
                    }
                }
            }

            if ($has_stock_tracking) {
                if ($total_stock > 0) {
                    return '<span class="vt-ac-badge vt-ac-stock-in">' . sprintf(__('%d in stock', 'voxel-toolkit'), $total_stock) . '</span>';
                } else {
                    return '<span class="vt-ac-badge vt-ac-stock-out">' . __('Out of stock', 'voxel-toolkit') . '</span>';
                }
            }
        }

        // If no inventory tracking, assume in stock
        return '<span class="vt-ac-badge vt-ac-stock-in">' . __('In stock', 'voxel-toolkit') . '</span>';
    }

    /**
     * Render product summary (type + price range)
     */
    private function render_product_summary($value) {
        $parts = array();

        // Product type
        $type = $this->get_product_type($value);
        if ($type) {
            $formatted = ucwords(str_replace(array('-', '_'), ' ', $type));
            $parts[] = '<span class="vt-ac-badge vt-ac-product">' . esc_html($formatted) . '</span>';
        }

        // Price range
        $prices = $this->extract_product_prices($value);
        if (!empty($prices)) {
            $min = min($prices);
            $max = max($prices);
            if ($min === $max) {
                $parts[] = '<span class="vt-ac-price">' . $this->format_price($min) . '</span>';
            } else {
                $parts[] = '<span class="vt-ac-price">' . $this->format_price($min) . '-' . $this->format_price($max) . '</span>';
            }
        }

        if (empty($parts)) {
            return $this->empty_value();
        }

        return implode(' ', $parts);
    }

    /**
     * Format price with currency
     */
    private function format_price($amount) {
        // Try to get Voxel currency settings
        if (function_exists('\Voxel\get') && \Voxel\get('settings.stripe.currency')) {
            $currency = strtoupper(\Voxel\get('settings.stripe.currency'));
            $symbol = $this->get_currency_symbol($currency);
            return $symbol . number_format_i18n($amount, 2);
        }

        // Fallback to USD
        return '$' . number_format_i18n($amount, 2);
    }

    /**
     * Get currency symbol
     */
    private function get_currency_symbol($currency) {
        $symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'CHF' => 'CHF ',
            'CNY' => '¥',
            'INR' => '₹',
            'MXN' => 'MX$',
            'BRL' => 'R$',
            'KRW' => '₩',
        );

        return isset($symbols[$currency]) ? $symbols[$currency] : $currency . ' ';
    }

    /**
     * Render taxonomy field
     */
    private function render_taxonomy($value, $field, $post) {
        // Try to get the actual taxonomy slug from the field
        $taxonomy = null;

        // Method 1: Try get_prop('taxonomy')
        if (method_exists($field, 'get_prop')) {
            $taxonomy = $field->get_prop('taxonomy');
        }

        // Method 2: Check if field has get_taxonomy method
        if (empty($taxonomy) && method_exists($field, 'get_taxonomy')) {
            $taxonomy = $field->get_taxonomy();
        }

        // Method 3: Fall back to field key
        if (empty($taxonomy)) {
            $taxonomy = $field->get_key();
        }

        // Check if taxonomy exists
        if (!taxonomy_exists($taxonomy)) {
            // Maybe the field key itself is the taxonomy
            if (taxonomy_exists($field->get_key())) {
                $taxonomy = $field->get_key();
            } else {
                return $this->empty_value();
            }
        }

        // Get terms for this post
        $terms = wp_get_post_terms($post->get_id(), $taxonomy);

        if (is_wp_error($terms) || empty($terms)) {
            // Try getting from the value if it contains term IDs
            if (!empty($value)) {
                $term_ids = array();

                if (is_array($value)) {
                    $term_ids = array_filter($value, 'is_numeric');
                } elseif (is_string($value)) {
                    // Could be comma-separated IDs or JSON
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $term_ids = array_filter($decoded, 'is_numeric');
                    } else {
                        $term_ids = array_filter(explode(',', $value), 'is_numeric');
                    }
                } elseif (is_numeric($value)) {
                    $term_ids = array(intval($value));
                }

                if (!empty($term_ids)) {
                    $terms = array();
                    foreach ($term_ids as $term_id) {
                        $term = get_term(intval($term_id), $taxonomy);
                        if ($term && !is_wp_error($term)) {
                            $terms[] = $term;
                        }
                    }
                }
            }

            if (empty($terms)) {
                return $this->empty_value();
            }
        }

        $links = array();
        $max_display = 3;

        foreach (array_slice($terms, 0, $max_display) as $term) {
            $edit_link = get_edit_term_link($term->term_id, $taxonomy);
            if ($edit_link) {
                $links[] = '<a href="' . esc_url($edit_link) . '">' . esc_html($term->name) . '</a>';
            } else {
                $links[] = esc_html($term->name);
            }
        }

        $output = '<span class="vt-ac-taxonomy">' . implode(', ', $links);

        if (count($terms) > $max_display) {
            $output .= ' <span class="vt-ac-badge">+' . (count($terms) - $max_display) . '</span>';
        }

        $output .= '</span>';

        return $output;
    }

    /**
     * Render post relation field
     */
    private function render_post_relation($value, $field, $post) {
        global $wpdb;

        $post_id = $post->get_id();
        $field_key = $field->get_key();

        // Query the voxel_relations table for related posts
        $table_name = $wpdb->prefix . 'voxel_relations';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return $this->empty_value();
        }

        // Determine relation type to query correctly
        // has_one/has_many: current post is parent, select children
        // belongs_to_one/belongs_to_many: current post is child, select parents
        $relation_type = '';
        if (method_exists($field, 'get_prop')) {
            $relation_type = $field->get_prop('relation_type');
        }

        $is_parent = in_array($relation_type, array('has_one', 'has_many'), true);

        if ($is_parent) {
            // Current post is parent, get child posts
            $related_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT child_id FROM $table_name WHERE parent_id = %d AND relation_key = %s ORDER BY `order` ASC",
                $post_id,
                $field_key
            ));
        } else {
            // Current post is child (belongs_to), get parent posts
            $related_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT parent_id FROM $table_name WHERE child_id = %d AND relation_key = %s ORDER BY `order` ASC",
                $post_id,
                $field_key
            ));
        }

        if (empty($related_ids)) {
            return $this->empty_value();
        }

        $max_display = 3;
        $total = count($related_ids);

        // Build visible links (first 3)
        $visible_links = array();
        foreach (array_slice($related_ids, 0, $max_display) as $related_id) {
            $link = $this->build_post_relation_link($related_id);
            if ($link) {
                $visible_links[] = $link;
            }
        }

        if (empty($visible_links)) {
            return $this->empty_value();
        }

        $output = '<span class="vt-ac-post-relations">';
        $output .= implode(', ', $visible_links);

        // If there are more, add dropdown
        if ($total > $max_display) {
            $remaining = $total - $max_display;

            // Build dropdown with ALL items
            $output .= ' <span class="vt-ac-relations-more">';
            $output .= '<span class="vt-ac-relations-toggle">+' . $remaining . ' ' . _n('more', 'more', $remaining, 'voxel-toolkit') . '</span>';
            $output .= '<span class="vt-ac-relations-dropdown">';

            foreach ($related_ids as $related_id) {
                $link = $this->build_post_relation_link($related_id, 40);
                if ($link) {
                    $output .= '<span class="vt-ac-relations-item">' . $link . '</span>';
                }
            }

            $output .= '</span>';
            $output .= '</span>';
        }

        $output .= '</span>';

        return $output;
    }

    /**
     * Build a single post relation link
     */
    private function build_post_relation_link($post_id, $max_length = 25) {
        $related_post = get_post($post_id);

        if (!$related_post) {
            return null;
        }

        $title = $related_post->post_title;
        if (empty($title)) {
            $title = sprintf(__('Post #%d', 'voxel-toolkit'), $post_id);
        }

        $edit_link = get_edit_post_link($post_id);
        if ($edit_link) {
            return '<a href="' . esc_url($edit_link) . '" target="_blank" title="' . esc_attr($title) . '">' . esc_html($this->truncate_string($title, $max_length)) . '</a>';
        }

        return '<span title="' . esc_attr($title) . '">' . esc_html($this->truncate_string($title, $max_length)) . '</span>';
    }

    /**
     * Render profile name field
     */
    private function render_profile_name($value, $field, $post) {
        return $this->truncate($value);
    }

    /**
     * Render profile avatar field (image type)
     */
    private function render_profile_avatar($value, $field, $post, $column_config = null) {
        // Profile avatar is an image field, reuse image rendering
        return $this->render_image($value, $field, $post, $column_config);
    }

    /**
     * Render Poll (VT) field
     */
    private function render_poll_vt($value, $field, $post, $column_config = null) {
        // Parse poll data
        $poll_data = $value;
        if (is_string($poll_data)) {
            $poll_data = json_decode($poll_data, true);
        }

        if (!is_array($poll_data) || empty($poll_data['options'])) {
            return $this->empty_value();
        }

        // Combine admin options and user-submitted options
        $all_options = $poll_data['options'];
        if (!empty($poll_data['user_submitted_options'])) {
            $all_options = array_merge($all_options, $poll_data['user_submitted_options']);
        }

        // Calculate total votes and votes per option
        $total_votes = 0;
        $option_votes = array();

        foreach ($all_options as $index => $option) {
            $vote_count = isset($option['votes']) && is_array($option['votes']) ? count($option['votes']) : 0;
            $option_votes[$index] = array(
                'label' => isset($option['label']) ? $option['label'] : '',
                'votes' => $vote_count,
            );
            $total_votes += $vote_count;
        }

        // Calculate percentages
        foreach ($option_votes as $index => &$opt) {
            $opt['percentage'] = $total_votes > 0 ? round(($opt['votes'] / $total_votes) * 100, 1) : 0;
        }

        // Get display mode from column config
        $display_mode = 'most_voted';
        if (isset($column_config['poll_settings']['display'])) {
            $display_mode = $column_config['poll_settings']['display'];
        }

        switch ($display_mode) {
            case 'most_voted':
                return $this->render_poll_most_voted($option_votes, 'count');

            case 'most_voted_percent':
                return $this->render_poll_most_voted($option_votes, 'percentage');

            case 'least_voted':
                return $this->render_poll_least_voted($option_votes, 'count');

            case 'least_voted_percent':
                return $this->render_poll_least_voted($option_votes, 'percentage');

            case 'total_votes':
                return '<span class="vt-ac-number">' . number_format_i18n($total_votes) . ' ' . _n('vote', 'votes', $total_votes, 'voxel-toolkit') . '</span>';

            case 'option_count':
                $count = count($all_options);
                return '<span class="vt-ac-number">' . number_format_i18n($count) . ' ' . _n('option', 'options', $count, 'voxel-toolkit') . '</span>';

            case 'summary':
                return $this->render_poll_summary($option_votes, $total_votes);

            default:
                return $this->render_poll_most_voted($option_votes, 'count');
        }
    }

    /**
     * Render poll most voted option
     */
    private function render_poll_most_voted($option_votes, $format = 'count') {
        if (empty($option_votes)) {
            return $this->empty_value();
        }

        // Find option with most votes
        usort($option_votes, function($a, $b) {
            return $b['votes'] - $a['votes'];
        });

        $winner = $option_votes[0];

        if ($winner['votes'] === 0) {
            return '<span class="vt-ac-muted">' . __('No votes yet', 'voxel-toolkit') . '</span>';
        }

        $label = esc_html($this->truncate_string($winner['label'], 30));

        if ($format === 'percentage') {
            return '<span class="vt-ac-poll-winner"><span class="vt-ac-poll-label">' . $label . '</span> <span class="vt-ac-badge vt-ac-poll-percent">' . $winner['percentage'] . '%</span></span>';
        }

        return '<span class="vt-ac-poll-winner"><span class="vt-ac-poll-label">' . $label . '</span> <span class="vt-ac-badge vt-ac-poll-count">' . number_format_i18n($winner['votes']) . '</span></span>';
    }

    /**
     * Render poll least voted option
     */
    private function render_poll_least_voted($option_votes, $format = 'count') {
        if (empty($option_votes)) {
            return $this->empty_value();
        }

        // Find option with least votes (excluding zero if there are other options with votes)
        usort($option_votes, function($a, $b) {
            return $a['votes'] - $b['votes'];
        });

        $loser = $option_votes[0];

        if ($loser['votes'] === 0 && count($option_votes) > 1) {
            // Find first option with no votes
            foreach ($option_votes as $opt) {
                if ($opt['votes'] === 0) {
                    $loser = $opt;
                    break;
                }
            }
        }

        $label = esc_html($this->truncate_string($loser['label'], 30));

        if ($format === 'percentage') {
            return '<span class="vt-ac-poll-loser"><span class="vt-ac-poll-label">' . $label . '</span> <span class="vt-ac-badge vt-ac-poll-percent">' . $loser['percentage'] . '%</span></span>';
        }

        return '<span class="vt-ac-poll-loser"><span class="vt-ac-poll-label">' . $label . '</span> <span class="vt-ac-badge vt-ac-poll-count">' . number_format_i18n($loser['votes']) . '</span></span>';
    }

    /**
     * Render poll summary (top 3 options)
     */
    private function render_poll_summary($option_votes, $total_votes) {
        if (empty($option_votes)) {
            return $this->empty_value();
        }

        // Sort by votes descending
        usort($option_votes, function($a, $b) {
            return $b['votes'] - $a['votes'];
        });

        // Take top 3
        $top = array_slice($option_votes, 0, 3);
        $parts = array();

        foreach ($top as $opt) {
            $label = esc_html($this->truncate_string($opt['label'], 15));
            $parts[] = '<span class="vt-ac-poll-item">' . $label . ' (' . $opt['percentage'] . '%)</span>';
        }

        $summary = implode(', ', $parts);

        if ($total_votes > 0) {
            $summary .= ' <span class="vt-ac-muted">(' . number_format_i18n($total_votes) . ' ' . _n('vote', 'votes', $total_votes, 'voxel-toolkit') . ')</span>';
        }

        return '<span class="vt-ac-poll-summary">' . $summary . '</span>';
    }

    /**
     * Default renderer for unknown types
     */
    private function render_default($value) {
        if (is_array($value)) {
            $count = count($value);
            return '<span class="vt-ac-badge">' . sprintf(_n('%d item', '%d items', $count, 'voxel-toolkit'), $count) . '</span>';
        }

        if (is_object($value)) {
            return '<span class="vt-ac-badge">' . __('Object', 'voxel-toolkit') . '</span>';
        }

        return $this->truncate((string) $value);
    }
}
