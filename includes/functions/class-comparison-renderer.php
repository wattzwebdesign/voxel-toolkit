<?php
/**
 * Comparison Renderer
 *
 * Renders Voxel field values for the comparison table with full detail display.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Comparison_Renderer {

    /**
     * Render a field value for display
     *
     * @param string $field_key Field key
     * @param int $post_id Post ID
     * @return string Rendered HTML
     */
    public function render($field_key, $post_id) {
        // Check for Voxel Post class
        if (!class_exists('\Voxel\Post')) {
            return $this->render_wp_field($field_key, $post_id);
        }

        $post = \Voxel\Post::get($post_id);
        if (!$post) {
            return $this->render_wp_field($field_key, $post_id);
        }

        $field = $post->get_field($field_key);
        if (!$field) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        $value = $field->get_value();
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        $type = $field->get_type();

        // Dispatch to type-specific renderer
        $method = 'render_' . str_replace('-', '_', $type);
        if (method_exists($this, $method)) {
            return $this->$method($value, $field, $post);
        }

        return $this->render_default($value);
    }

    /**
     * Render WordPress core field
     *
     * @param string $field_key Field key
     * @param int $post_id Post ID
     * @return string Rendered HTML
     */
    private function render_wp_field($field_key, $post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        switch ($field_key) {
            case 'title':
                return esc_html(get_the_title($post_id));
            case 'content':
                return wp_trim_words(wp_strip_all_tags($post->post_content), 50);
            case 'excerpt':
                return esc_html($post->post_excerpt);
            case 'date':
                return get_the_date('', $post_id);
            case 'author':
                return esc_html(get_the_author_meta('display_name', $post->post_author));
            default:
                $meta = get_post_meta($post_id, $field_key, true);
                if ($meta) {
                    return is_array($meta) ? esc_html(json_encode($meta)) : esc_html($meta);
                }
                return '<span class="vt-compare-empty-value">—</span>';
        }
    }

    /**
     * Default renderer for unknown types
     *
     * @param mixed $value Field value
     * @return string Rendered HTML
     */
    private function render_default($value) {
        if (is_array($value)) {
            return esc_html(json_encode($value, JSON_PRETTY_PRINT));
        }
        return esc_html((string)$value);
    }

    /**
     * Render title field
     */
    private function render_title($value, $field, $post) {
        return esc_html($value);
    }

    /**
     * Render text field
     */
    private function render_text($value, $field, $post) {
        return esc_html($value);
    }

    /**
     * Render textarea field
     */
    private function render_textarea($value, $field, $post) {
        return nl2br(esc_html($value));
    }

    /**
     * Render description field
     */
    private function render_description($value, $field, $post) {
        return nl2br(esc_html($value));
    }

    /**
     * Render number field
     */
    private function render_number($value, $field, $post) {
        if (is_numeric($value)) {
            return number_format_i18n($value);
        }
        return esc_html($value);
    }

    /**
     * Render date field
     */
    private function render_date($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return esc_html($value);
        }

        return date_i18n(get_option('date_format'), $timestamp);
    }

    /**
     * Render email field
     */
    private function render_email($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }
        return '<a href="mailto:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
    }

    /**
     * Render phone field
     */
    private function render_phone($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }
        $clean = preg_replace('/[^0-9+]/', '', $value);
        return '<a href="tel:' . esc_attr($clean) . '">' . esc_html($value) . '</a>';
    }

    /**
     * Render URL field
     */
    private function render_url($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }
        $display = preg_replace('#^https?://#', '', $value);
        return '<a href="' . esc_url($value) . '" target="_blank" rel="noopener">' . esc_html($display) . '</a>';
    }

    /**
     * Render switcher field
     */
    private function render_switcher($value, $field, $post) {
        $yes_label = __('Yes', 'voxel-toolkit');
        $no_label = __('No', 'voxel-toolkit');

        // Try to get custom labels from field config
        if (method_exists($field, 'get_prop')) {
            $custom_yes = $field->get_prop('label_on');
            $custom_no = $field->get_prop('label_off');
            if ($custom_yes) $yes_label = $custom_yes;
            if ($custom_no) $no_label = $custom_no;
        }

        $is_checked = !empty($value) && $value !== 'no' && $value !== '0';
        $class = $is_checked ? 'vt-compare-yes' : 'vt-compare-no';

        return '<span class="' . $class . '">' . esc_html($is_checked ? $yes_label : $no_label) . '</span>';
    }

    /**
     * Render select field
     */
    private function render_select($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        // Try to get label from choices
        if (method_exists($field, 'get_prop')) {
            $choices = $field->get_prop('choices');
            if (is_array($choices)) {
                foreach ($choices as $choice) {
                    if (isset($choice['value']) && $choice['value'] === $value && isset($choice['label'])) {
                        return esc_html($choice['label']);
                    }
                }
            }
        }

        return esc_html($value);
    }

    /**
     * Render image field (single)
     */
    private function render_image($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        $image_ids = is_array($value) ? $value : array($value);
        $output = '<div class="vt-compare-images">';

        foreach ($image_ids as $image_id) {
            if (is_numeric($image_id)) {
                $url = wp_get_attachment_image_url($image_id, 'medium');
                if ($url) {
                    $output .= '<img src="' . esc_url($url) . '" alt="" class="vt-compare-image">';
                }
            }
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render file field
     */
    private function render_file($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        $file_ids = is_array($value) ? $value : array($value);
        $output = '<div class="vt-compare-files">';

        foreach ($file_ids as $file_id) {
            if (is_numeric($file_id)) {
                $url = wp_get_attachment_url($file_id);
                $filename = basename(get_attached_file($file_id));
                if ($url) {
                    $output .= '<a href="' . esc_url($url) . '" target="_blank" class="vt-compare-file">';
                    $output .= '<span class="dashicons dashicons-media-default"></span> ';
                    $output .= esc_html($filename);
                    $output .= '</a>';
                }
            }
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render taxonomy field
     */
    private function render_taxonomy($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        // Handle Voxel Term objects array
        $terms_array = is_array($value) ? $value : array($value);
        $term_names = array();

        foreach ($terms_array as $term_item) {
            // Handle Voxel Term object
            if (is_object($term_item) && method_exists($term_item, 'get_label')) {
                $term_names[] = esc_html($term_item->get_label());
            } elseif (is_object($term_item) && isset($term_item->name)) {
                $term_names[] = esc_html($term_item->name);
            } elseif (is_numeric($term_item)) {
                // Get taxonomy from field
                $taxonomy = '';
                if (method_exists($field, 'get_prop')) {
                    $tax_obj = $field->get_prop('taxonomy');
                    // Handle Voxel Taxonomy object
                    if (is_object($tax_obj) && method_exists($tax_obj, 'get_key')) {
                        $taxonomy = $tax_obj->get_key();
                    } elseif (is_string($tax_obj)) {
                        $taxonomy = $tax_obj;
                    }
                }

                if (!empty($taxonomy)) {
                    $term = get_term($term_item, $taxonomy);
                    if ($term && !is_wp_error($term)) {
                        $term_names[] = esc_html($term->name);
                    }
                }
            }
        }

        if (empty($term_names)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        return implode(', ', $term_names);
    }

    /**
     * Render location field with full detail
     */
    private function render_location($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        // Decode if string
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        $output = '<div class="vt-compare-location">';

        // Address
        if (!empty($value['address'])) {
            $output .= '<div class="vt-loc-address">';
            $output .= '<span class="dashicons dashicons-location"></span> ';
            $output .= esc_html($value['address']);
            $output .= '</div>';
        }

        // Coordinates
        if (!empty($value['latitude']) && !empty($value['longitude'])) {
            $lat = round($value['latitude'], 6);
            $lng = round($value['longitude'], 6);
            $output .= '<div class="vt-loc-coords">';
            $output .= '<small>' . esc_html($lat) . ', ' . esc_html($lng) . '</small>';
            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render work hours field with full schedule
     */
    private function render_work_hours($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        // Decode if string
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        $days_map = array(
            'mon' => __('Monday', 'voxel-toolkit'),
            'tue' => __('Tuesday', 'voxel-toolkit'),
            'wed' => __('Wednesday', 'voxel-toolkit'),
            'thu' => __('Thursday', 'voxel-toolkit'),
            'fri' => __('Friday', 'voxel-toolkit'),
            'sat' => __('Saturday', 'voxel-toolkit'),
            'sun' => __('Sunday', 'voxel-toolkit'),
        );

        // Build schedule by day
        $schedule = array();
        foreach ($days_map as $day_key => $day_name) {
            $schedule[$day_key] = array(
                'name' => $day_name,
                'status' => 'closed',
                'hours' => array(),
            );
        }

        // Process work hours entries
        foreach ($value as $entry) {
            if (!is_array($entry) || empty($entry['days'])) {
                continue;
            }

            $status = isset($entry['status']) ? $entry['status'] : 'hours';
            $hours = isset($entry['hours']) ? $entry['hours'] : array();

            foreach ($entry['days'] as $day) {
                if (isset($schedule[$day])) {
                    $schedule[$day]['status'] = $status;
                    if ($status === 'hours' && !empty($hours)) {
                        $schedule[$day]['hours'] = $hours;
                    }
                }
            }
        }

        // Build output
        $output = '<div class="vt-compare-work-hours">';

        foreach ($schedule as $day_key => $day_data) {
            $status_class = $day_data['status'] === 'closed' ? 'vt-wh-closed' : 'vt-wh-open';

            $output .= '<div class="vt-wh-day ' . $status_class . '">';
            $output .= '<span class="vt-wh-day-name">' . esc_html($day_data['name']) . '</span>';
            $output .= '<span class="vt-wh-day-hours">';

            if ($day_data['status'] === 'closed') {
                $output .= __('Closed', 'voxel-toolkit');
            } elseif ($day_data['status'] === 'appointments_only') {
                $output .= __('By appointment', 'voxel-toolkit');
            } elseif ($day_data['status'] === 'open') {
                $output .= __('Open 24h', 'voxel-toolkit');
            } elseif (!empty($day_data['hours'])) {
                $hours_strings = array();
                $time_format = get_option('time_format');
                foreach ($day_data['hours'] as $slot) {
                    if (isset($slot['from']) && isset($slot['to'])) {
                        $from_formatted = $this->format_time_string($slot['from'], $time_format);
                        $to_formatted = $this->format_time_string($slot['to'], $time_format);
                        $hours_strings[] = esc_html($from_formatted) . ' - ' . esc_html($to_formatted);
                    }
                }
                $output .= implode(', ', $hours_strings);
            }

            $output .= '</span>';
            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render repeater field with all items and all fields
     */
    private function render_repeater($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        // Decode if string
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        $output = '<div class="vt-compare-repeater">';

        foreach ($value as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $output .= '<div class="vt-rep-item">';
            $output .= '<div class="vt-rep-item-header">' . __('Item', 'voxel-toolkit') . ' ' . ($index + 1) . '</div>';
            $output .= '<div class="vt-rep-item-fields">';

            // Show ALL fields in the row
            foreach ($row as $field_key => $field_value) {
                if ($field_value === null || $field_value === '') {
                    continue;
                }

                $output .= '<div class="vt-rep-field">';
                $output .= '<span class="vt-rep-field-label">' . esc_html(ucwords(str_replace(array('_', '-'), ' ', $field_key))) . ':</span> ';

                // Format the value based on type
                if (is_array($field_value)) {
                    $output .= '<span class="vt-rep-field-value">' . esc_html(implode(', ', array_map('strval', $field_value))) . '</span>';
                } elseif (is_bool($field_value)) {
                    $output .= '<span class="vt-rep-field-value">' . ($field_value ? __('Yes', 'voxel-toolkit') : __('No', 'voxel-toolkit')) . '</span>';
                } else {
                    $output .= '<span class="vt-rep-field-value">' . esc_html($field_value) . '</span>';
                }

                $output .= '</div>';
            }

            $output .= '</div>';
            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render product field
     */
    private function render_product($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        // Decode if string
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $output = '<div class="vt-compare-product">';

        // Try to get pricing information
        if (is_array($value)) {
            // Base price
            if (isset($value['base_price']) || isset($value['price'])) {
                $price = isset($value['base_price']) ? $value['base_price'] : $value['price'];
                $output .= '<div class="vt-prod-price">';
                $output .= '<strong>' . __('Price:', 'voxel-toolkit') . '</strong> ';
                $output .= $this->format_price($price);
                $output .= '</div>';
            }

            // Product type
            if (isset($value['product_type'])) {
                $output .= '<div class="vt-prod-type">';
                $output .= '<strong>' . __('Type:', 'voxel-toolkit') . '</strong> ';
                $output .= esc_html(ucfirst($value['product_type']));
                $output .= '</div>';
            }
        }

        // Try Voxel Product API if available
        if (class_exists('\Voxel\Product') && $post && method_exists($post, 'get_product')) {
            try {
                $product = $post->get_product();
                if ($product && method_exists($product, 'get_minimum_price')) {
                    $min_price = $product->get_minimum_price();
                    if ($min_price !== null) {
                        $output .= '<div class="vt-prod-min-price">';
                        $output .= __('From', 'voxel-toolkit') . ' ' . $this->format_price($min_price);
                        $output .= '</div>';
                    }
                }
            } catch (Exception $e) {
                // Silently fail
            }
        }

        if ($output === '<div class="vt-compare-product">') {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Format price with currency
     */
    private function format_price($price) {
        if (function_exists('wc_price')) {
            return wc_price($price);
        }
        // Fallback formatting
        return '$' . number_format_i18n((float)$price, 2);
    }

    /**
     * Format time string (e.g., "09:00" or "17:30") using WordPress time format
     *
     * @param string $time_string Time in H:i format (e.g., "09:00", "17:30")
     * @param string $format Optional format, defaults to WordPress time_format
     * @return string Formatted time
     */
    private function format_time_string($time_string, $format = '') {
        if (empty($time_string)) {
            return '';
        }

        if (empty($format)) {
            $format = get_option('time_format');
        }

        // Parse the time string (handles "09:00" or "9:00" formats)
        $timestamp = strtotime($time_string);
        if ($timestamp === false) {
            // Try prepending a date if strtotime fails
            $timestamp = strtotime('1970-01-01 ' . $time_string);
        }

        if ($timestamp === false) {
            return $time_string; // Return original if parsing fails
        }

        return date_i18n($format, $timestamp);
    }

    /**
     * Render time field
     */
    private function render_time($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        return esc_html($this->format_time_string($value));
    }

    /**
     * Render post relation field
     */
    private function render_post_relation($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        $post_ids = is_array($value) ? $value : array($value);
        $titles = array();

        foreach ($post_ids as $related_id) {
            if (is_numeric($related_id)) {
                $title = get_the_title($related_id);
                if ($title) {
                    $link = get_permalink($related_id);
                    $titles[] = '<a href="' . esc_url($link) . '">' . esc_html($title) . '</a>';
                }
            }
        }

        if (empty($titles)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        return implode(', ', $titles);
    }

    /**
     * Render profile name field
     */
    private function render_profile_name($value, $field, $post) {
        return esc_html($value);
    }

    /**
     * Render profile avatar field
     */
    private function render_profile_avatar($value, $field, $post) {
        return $this->render_image($value, $field, $post);
    }

    /**
     * Render color field
     */
    private function render_color($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        return '<span class="vt-compare-color" style="background-color: ' . esc_attr($value) . ';">' . esc_html($value) . '</span>';
    }

    /**
     * Render texteditor field
     */
    private function render_texteditor($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        return wp_kses_post($value);
    }

    /**
     * Render recurring-date field with full detail (handles arrays of events)
     */
    private function render_recurring_date($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        // Decode if string
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return esc_html($value);
        }

        // Check if this is an array of events or a single event
        // If the first key is numeric, it's an array of events
        $events = isset($value[0]) ? $value : array($value);

        $output = '<div class="vt-compare-recurring-date">';

        foreach ($events as $index => $event) {
            if (!is_array($event)) {
                continue;
            }

            if (count($events) > 1) {
                $output .= '<div class="vt-rd-event">';
                $output .= '<div class="vt-rd-event-header">' . __('Event', 'voxel-toolkit') . ' ' . ($index + 1) . '</div>';
            }

            // Start date/time
            if (!empty($event['start'])) {
                $output .= '<div class="vt-rd-start">';
                $output .= '<strong>' . __('Start:', 'voxel-toolkit') . '</strong> ';
                $timestamp = strtotime($event['start']);
                $output .= $timestamp ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : esc_html($event['start']);
                $output .= '</div>';
            }

            // End date/time
            if (!empty($event['end'])) {
                $output .= '<div class="vt-rd-end">';
                $output .= '<strong>' . __('End:', 'voxel-toolkit') . '</strong> ';
                $timestamp = strtotime($event['end']);
                $output .= $timestamp ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : esc_html($event['end']);
                $output .= '</div>';
            }

            // Frequency and unit (e.g., "Every 1 week")
            if (!empty($event['frequency']) && !empty($event['unit'])) {
                $output .= '<div class="vt-rd-freq">';
                $output .= '<strong>' . __('Repeats:', 'voxel-toolkit') . '</strong> ';
                $output .= sprintf(__('Every %d %s', 'voxel-toolkit'), intval($event['frequency']), esc_html($event['unit']));
                $output .= '</div>';
            } elseif (!empty($event['frequency'])) {
                $output .= '<div class="vt-rd-freq">';
                $output .= '<strong>' . __('Frequency:', 'voxel-toolkit') . '</strong> ';
                $output .= esc_html($event['frequency']);
                $output .= '</div>';
            }

            // Until date
            if (!empty($event['until'])) {
                $output .= '<div class="vt-rd-until">';
                $output .= '<strong>' . __('Until:', 'voxel-toolkit') . '</strong> ';
                $timestamp = strtotime($event['until']);
                $output .= $timestamp ? date_i18n(get_option('date_format'), $timestamp) : esc_html($event['until']);
                $output .= '</div>';
            }

            // Multiday flag
            if (isset($event['multiday'])) {
                $output .= '<div class="vt-rd-multiday">';
                $output .= '<strong>' . __('Multi-day:', 'voxel-toolkit') . '</strong> ';
                $output .= $event['multiday'] ? __('Yes', 'voxel-toolkit') : __('No', 'voxel-toolkit');
                $output .= '</div>';
            }

            // All day flag
            if (isset($event['allday'])) {
                $output .= '<div class="vt-rd-allday">';
                $output .= '<strong>' . __('All day:', 'voxel-toolkit') . '</strong> ';
                $output .= $event['allday'] ? __('Yes', 'voxel-toolkit') : __('No', 'voxel-toolkit');
                $output .= '</div>';
            }

            if (count($events) > 1) {
                $output .= '</div>';
            }
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render event-date field
     */
    private function render_event_date($value, $field, $post) {
        return $this->render_recurring_date($value, $field, $post);
    }

    /**
     * Render timezone field
     */
    private function render_timezone($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }
        return esc_html($value);
    }

    /**
     * Render terms-select field (taxonomy selection)
     */
    private function render_terms_select($value, $field, $post) {
        return $this->render_taxonomy($value, $field, $post);
    }

    /**
     * Render media-library field
     */
    private function render_media_library($value, $field, $post) {
        return $this->render_image($value, $field, $post);
    }

    /**
     * Render numeric field
     */
    private function render_numeric($value, $field, $post) {
        return $this->render_number($value, $field, $post);
    }

    /**
     * Render stepper field (numeric stepper)
     */
    private function render_stepper($value, $field, $post) {
        return $this->render_number($value, $field, $post);
    }

    /**
     * Render gallery field
     */
    private function render_gallery($value, $field, $post) {
        return $this->render_image($value, $field, $post);
    }

    /**
     * Render links/social field
     */
    private function render_links($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        // Decode if string
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return esc_html($value);
        }

        $output = '<div class="vt-compare-links">';

        foreach ($value as $link) {
            if (is_array($link) && !empty($link['url'])) {
                $label = !empty($link['label']) ? $link['label'] : (!empty($link['network']) ? ucfirst($link['network']) : preg_replace('#^https?://#', '', $link['url']));
                $output .= '<a href="' . esc_url($link['url']) . '" target="_blank" rel="noopener" class="vt-compare-link">';
                $output .= esc_html($label);
                $output .= '</a> ';
            } elseif (is_string($link) && filter_var($link, FILTER_VALIDATE_URL)) {
                $output .= '<a href="' . esc_url($link) . '" target="_blank" rel="noopener" class="vt-compare-link">';
                $output .= esc_html(preg_replace('#^https?://#', '', $link));
                $output .= '</a> ';
            }
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render social-links field
     */
    private function render_social_links($value, $field, $post) {
        return $this->render_links($value, $field, $post);
    }

    /**
     * Render date-range field
     */
    private function render_date_range($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        // Decode if string
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return esc_html($value);
        }

        $output = '';

        if (!empty($value['start'])) {
            $timestamp = strtotime($value['start']);
            $output .= $timestamp ? date_i18n(get_option('date_format'), $timestamp) : esc_html($value['start']);
        }

        if (!empty($value['end'])) {
            $output .= ' — ';
            $timestamp = strtotime($value['end']);
            $output .= $timestamp ? date_i18n(get_option('date_format'), $timestamp) : esc_html($value['end']);
        }

        return $output ?: '<span class="vt-compare-empty-value">—</span>';
    }

    /**
     * Render multiselect field
     */
    private function render_multiselect($value, $field, $post) {
        if (empty($value)) {
            return '<span class="vt-compare-empty-value">—</span>';
        }

        $values = is_array($value) ? $value : array($value);
        $labels = array();

        // Try to get labels from choices
        if (method_exists($field, 'get_prop')) {
            $choices = $field->get_prop('choices');
            if (is_array($choices)) {
                foreach ($values as $val) {
                    $found = false;
                    foreach ($choices as $choice) {
                        if (isset($choice['value']) && $choice['value'] === $val && isset($choice['label'])) {
                            $labels[] = esc_html($choice['label']);
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $labels[] = esc_html($val);
                    }
                }
            } else {
                $labels = array_map('esc_html', $values);
            }
        } else {
            $labels = array_map('esc_html', $values);
        }

        return implode(', ', $labels);
    }

    /**
     * Render checkbox field
     */
    private function render_checkbox($value, $field, $post) {
        return $this->render_multiselect($value, $field, $post);
    }

    /**
     * Render radio field
     */
    private function render_radio($value, $field, $post) {
        return $this->render_select($value, $field, $post);
    }

    /**
     * Render wpeditor field (WordPress editor)
     */
    private function render_wpeditor($value, $field, $post) {
        return $this->render_texteditor($value, $field, $post);
    }

    /**
     * Render ui-image field (if not skipped)
     */
    private function render_ui_image($value, $field, $post) {
        return $this->render_image($value, $field, $post);
    }
}
