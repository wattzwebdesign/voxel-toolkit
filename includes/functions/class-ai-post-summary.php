<?php
/**
 * AI Post Summary Function
 *
 * Automatically generates AI summaries for posts on publish/update.
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_AI_Post_Summary {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Meta keys for storing summary data
     */
    const META_KEY = '_vt_ai_post_summary';
    const META_HASH_KEY = '_vt_ai_post_summary_hash';

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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Auto-generate on post save
        add_action('save_post', array($this, 'on_post_save'), 20, 3);

        // Handle AJAX regeneration for single post
        add_action('wp_ajax_vt_regenerate_ai_summary', array($this, 'ajax_regenerate_summary'));

        // Note: Bulk generation AJAX is handled in class-admin.php for availability on settings page

        // Add admin scripts for settings page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_voxel-toolkit') {
            return;
        }

        wp_localize_script('jquery', 'vtAiPostSummary', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_ai_summary_nonce'),
        ));
    }

    /**
     * Get settings for this feature
     */
    private function get_settings() {
        return Voxel_Toolkit_Settings::instance()->get_function_settings('ai_post_summary', array());
    }

    /**
     * Check if auto-generation is enabled for a post type
     */
    private function is_enabled_for_post_type($post_type) {
        $settings = $this->get_settings();
        $enabled_types = isset($settings['post_types']) ? (array) $settings['post_types'] : array();
        return in_array($post_type, $enabled_types, true);
    }

    /**
     * Handle post save - auto-generate summary
     */
    public function on_post_save($post_id, $post, $update) {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Only on publish
        if ($post->post_status !== 'publish') {
            return;
        }

        // Check if enabled for this post type
        if (!$this->is_enabled_for_post_type($post->post_type)) {
            return;
        }

        // Check if AI is configured
        if (!class_exists('Voxel_Toolkit_AI_Settings') || !Voxel_Toolkit_AI_Settings::instance()->is_configured()) {
            return;
        }

        // Generate content hash
        $content_hash = $this->generate_content_hash($post_id);
        $stored_hash = get_post_meta($post_id, self::META_HASH_KEY, true);

        // Skip if content hasn't changed
        if ($content_hash === $stored_hash) {
            return;
        }

        // Generate and store summary
        $this->generate_and_store_summary($post_id);
    }

    /**
     * Generate content hash for change detection
     */
    private function generate_content_hash($post_id) {
        $aggregated = $this->aggregate_post_data($post_id);
        return md5($aggregated);
    }

    /**
     * Aggregate all post data for AI context
     */
    public function aggregate_post_data($post_id) {
        $parts = array();

        // Get WordPress post data
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }

        $parts[] = "Title: " . $post->post_title;

        if (!empty($post->post_content)) {
            $content = wp_strip_all_tags(strip_shortcodes($post->post_content));
            $parts[] = "Content: " . wp_trim_words($content, 500);
        }

        // Get Voxel post fields
        if (class_exists('\Voxel\Post')) {
            $voxel_post = \Voxel\Post::get($post_id);
            if ($voxel_post && $voxel_post->post_type) {
                $post_type = $voxel_post->post_type;
                $field_definitions = $post_type->get_fields();

                foreach ($field_definitions as $field_key => $field_def) {
                    // Get the field instance bound to this specific post
                    $field = $voxel_post->get_field($field_key);
                    if (!$field) {
                        continue;
                    }

                    $type = $field->get_type();

                    // Skip internal/system fields
                    if (in_array($type, array('ui-step', 'ui-heading', 'ui-html', 'ui-image', 'title'), true)) {
                        continue;
                    }

                    // Wrap in try-catch to handle any field errors gracefully
                    try {
                        $value = $field->get_value();
                    } catch (\Exception $e) {
                        continue;
                    } catch (\Error $e) {
                        continue;
                    }

                    if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                        continue;
                    }

                    $label = $field->get_label();

                    // Format value based on type
                    $formatted = $this->format_field_value($value, $type, $field);
                    if (!empty($formatted)) {
                        $parts[] = "{$label}: {$formatted}";
                    }
                }
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Format field value for AI prompt
     */
    private function format_field_value($value, $type, $field) {
        // Handle JSON-encoded values
        if (is_string($value) && in_array($type, array('location', 'work-hours', 'product', 'repeater', 'recurring-date'), true)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        switch ($type) {
            case 'title':
            case 'text':
            case 'textarea':
            case 'description':
            case 'email':
            case 'phone':
            case 'url':
                return is_string($value) ? wp_strip_all_tags($value) : '';

            case 'number':
            case 'numeric':
            case 'stepper':
                return is_numeric($value) ? number_format_i18n((float) $value) : '';

            case 'switcher':
                return (!empty($value) && $value !== 'no' && $value !== '0') ? 'Yes' : 'No';

            case 'select':
            case 'radio':
                // Try to get label from choices
                if (method_exists($field, 'get_prop')) {
                    $choices = $field->get_prop('choices');
                    if (is_array($choices)) {
                        foreach ($choices as $choice) {
                            if (isset($choice['value']) && $choice['value'] === $value && isset($choice['label'])) {
                                return sanitize_text_field($choice['label']);
                            }
                        }
                    }
                }
                return sanitize_text_field($value);

            case 'taxonomy':
            case 'terms-select':
                $terms = is_array($value) ? $value : array($value);
                $names = array();
                foreach ($terms as $term) {
                    if (is_object($term) && method_exists($term, 'get_label')) {
                        $names[] = $term->get_label();
                    } elseif (is_object($term) && isset($term->name)) {
                        $names[] = $term->name;
                    } elseif (is_numeric($term)) {
                        $term_obj = get_term($term);
                        if ($term_obj && !is_wp_error($term_obj)) {
                            $names[] = $term_obj->name;
                        }
                    }
                }
                return implode(', ', array_map('sanitize_text_field', $names));

            case 'location':
                if (is_array($value) && !empty($value['address'])) {
                    return sanitize_text_field($value['address']);
                }
                return '';

            case 'work-hours':
                return $this->format_work_hours($value);

            case 'product':
                if (is_array($value)) {
                    $parts = array();
                    if (isset($value['base_price']) || isset($value['price'])) {
                        $price = isset($value['base_price']) ? $value['base_price'] : $value['price'];
                        $parts[] = 'Price: $' . number_format_i18n((float) $price, 2);
                    }
                    if (isset($value['product_type'])) {
                        $parts[] = 'Type: ' . ucfirst(sanitize_text_field($value['product_type']));
                    }
                    return implode(', ', $parts);
                }
                return '';

            case 'repeater':
                if (is_array($value)) {
                    $items = array();
                    foreach ($value as $row) {
                        if (is_array($row)) {
                            $item_parts = array();
                            foreach ($row as $key => $val) {
                                if (!empty($val) && !is_array($val)) {
                                    $item_parts[] = ucwords(str_replace('_', ' ', $key)) . ': ' . sanitize_text_field($val);
                                }
                            }
                            if (!empty($item_parts)) {
                                $items[] = implode('; ', $item_parts);
                            }
                        }
                    }
                    return implode(' | ', $items);
                }
                return '';

            case 'post-relation':
                $ids = is_array($value) ? $value : array($value);
                $titles = array();
                foreach ($ids as $related_id) {
                    if (is_numeric($related_id)) {
                        $title = get_the_title($related_id);
                        if ($title) {
                            $titles[] = sanitize_text_field($title);
                        }
                    }
                }
                return implode(', ', $titles);

            case 'date':
            case 'date-time':
                if (!empty($value)) {
                    $timestamp = is_numeric($value) ? $value : strtotime($value);
                    if ($timestamp) {
                        return date_i18n(get_option('date_format'), $timestamp);
                    }
                }
                return '';

            default:
                if (is_array($value)) {
                    return wp_json_encode($value);
                }
                return sanitize_text_field((string) $value);
        }
    }

    /**
     * Format work hours for text summary
     */
    private function format_work_hours($value) {
        if (!is_array($value)) {
            return '';
        }

        $days_map = array(
            'mon' => 'Monday',
            'tue' => 'Tuesday',
            'wed' => 'Wednesday',
            'thu' => 'Thursday',
            'fri' => 'Friday',
            'sat' => 'Saturday',
            'sun' => 'Sunday',
        );

        $schedule = array();
        foreach ($value as $entry) {
            if (!is_array($entry) || empty($entry['days'])) {
                continue;
            }

            $status = isset($entry['status']) ? $entry['status'] : 'hours';
            $hours = isset($entry['hours']) ? $entry['hours'] : array();

            foreach ($entry['days'] as $day) {
                $day_name = isset($days_map[$day]) ? $days_map[$day] : $day;

                if ($status === 'closed') {
                    $schedule[] = "{$day_name}: Closed";
                } elseif ($status === 'open') {
                    $schedule[] = "{$day_name}: Open 24h";
                } elseif ($status === 'appointments_only') {
                    $schedule[] = "{$day_name}: By appointment";
                } elseif (!empty($hours)) {
                    $time_slots = array();
                    foreach ($hours as $slot) {
                        if (isset($slot['from']) && isset($slot['to'])) {
                            $time_slots[] = $slot['from'] . '-' . $slot['to'];
                        }
                    }
                    if (!empty($time_slots)) {
                        $schedule[] = "{$day_name}: " . implode(', ', $time_slots);
                    }
                }
            }
        }

        return implode('; ', $schedule);
    }

    /**
     * Generate and store summary
     */
    public function generate_and_store_summary($post_id) {
        if (!class_exists('Voxel_Toolkit_AI_Settings')) {
            return false;
        }

        $ai_settings = Voxel_Toolkit_AI_Settings::instance();

        if (!$ai_settings->is_configured()) {
            return false;
        }

        // Aggregate post data
        $aggregated_data = $this->aggregate_post_data($post_id);

        if (empty($aggregated_data)) {
            return false;
        }

        // Build prompt
        $settings = $this->get_settings();
        $prompt_template = !empty($settings['prompt_template'])
            ? $settings['prompt_template']
            : Voxel_Toolkit_Functions::instance()->get_default_ai_post_summary_prompt();

        $prompt = str_replace('{{post_data}}', $aggregated_data, $prompt_template);

        // Add language instruction if not English
        $language_name = $this->get_response_language_name();
        if ($language_name !== 'English') {
            $prompt .= "\n\nIMPORTANT: Write the summary in {$language_name}.";
        }

        // Generate summary
        $max_tokens = isset($settings['max_tokens']) ? absint($settings['max_tokens']) : 300;
        $max_tokens = max(50, min(5000, $max_tokens));

        $result = $ai_settings->generate_completion($prompt, $max_tokens);

        if (is_wp_error($result)) {
            error_log('VT AI Post Summary Error: ' . $result->get_error_message());
            return false;
        }

        // Sanitize the result
        $result = sanitize_textarea_field($result);

        // Store summary and hash
        update_post_meta($post_id, self::META_KEY, $result);
        update_post_meta($post_id, self::META_HASH_KEY, $this->generate_content_hash($post_id));

        return true;
    }

    /**
     * Get stored summary
     */
    public function get_summary($post_id) {
        return get_post_meta($post_id, self::META_KEY, true);
    }

    /**
     * AJAX handler for manual regeneration
     */
    public function ajax_regenerate_summary() {
        check_ajax_referer('vt_ai_summary_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Unauthorized', 'voxel-toolkit'));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID', 'voxel-toolkit'));
        }

        $result = $this->generate_and_store_summary($post_id);

        if ($result) {
            wp_send_json_success(array(
                'summary' => $this->get_summary($post_id),
            ));
        } else {
            wp_send_json_error(__('Failed to generate summary', 'voxel-toolkit'));
        }
    }

    /**
     * AJAX handler for bulk summary generation
     */
    public function ajax_bulk_generate_summaries() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vt_ai_summary_nonce')) {
            wp_send_json_error(__('Security check failed.', 'voxel-toolkit'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'voxel-toolkit'));
            return;
        }

        // Check if AI is configured
        if (!class_exists('Voxel_Toolkit_AI_Settings') || !Voxel_Toolkit_AI_Settings::instance()->is_configured()) {
            wp_send_json_error(__('AI not configured. Please add your API key in AI Settings.', 'voxel-toolkit'));
            return;
        }

        $settings = $this->get_settings();
        $enabled_types = isset($settings['post_types']) ? (array) $settings['post_types'] : array();

        if (empty($enabled_types)) {
            wp_send_json_error(__('No post types selected. Please select at least one post type.', 'voxel-toolkit'));
            return;
        }

        // Get posts without summaries (limit to 5 per request to avoid timeout)
        $args = array(
            'post_type' => $enabled_types,
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'meta_query' => array(
                array(
                    'key' => self::META_KEY,
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        $posts = get_posts($args);

        if (empty($posts)) {
            // Check total count
            $total_args = array(
                'post_type' => $enabled_types,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
            );
            $all_posts = get_posts($total_args);

            wp_send_json_success(array(
                'completed' => true,
                'message' => sprintf(__('All %d posts have summaries.', 'voxel-toolkit'), count($all_posts)),
                'processed' => 0,
                'remaining' => 0,
            ));
        }

        $processed = 0;
        $errors = array();

        foreach ($posts as $post) {
            $result = $this->generate_and_store_summary($post->ID);
            if ($result) {
                $processed++;
            } else {
                $errors[] = $post->ID;
            }
        }

        // Count remaining
        $remaining_args = array(
            'post_type' => $enabled_types,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => self::META_KEY,
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );
        $remaining = count(get_posts($remaining_args));

        wp_send_json_success(array(
            'completed' => $remaining === 0,
            'processed' => $processed,
            'remaining' => $remaining,
            'errors' => $errors,
            'message' => sprintf(__('Processed %d posts. %d remaining.', 'voxel-toolkit'), $processed, $remaining),
        ));
    }

    /**
     * Sanitize AI Post Summary settings input
     */
    public static function sanitize_settings($input) {
        $sanitized = array();

        // Post types
        $sanitized['post_types'] = isset($input['post_types'])
            ? array_map('sanitize_text_field', (array) $input['post_types'])
            : array();

        // Validate post types are actual Voxel post types
        if (class_exists('\Voxel\Post_Type')) {
            $valid_types = array_keys(\Voxel\Post_Type::get_voxel_types());
            $sanitized['post_types'] = array_intersect($sanitized['post_types'], $valid_types);
        }

        // Max tokens
        $sanitized['max_tokens'] = isset($input['max_tokens'])
            ? absint($input['max_tokens'])
            : 300;
        $sanitized['max_tokens'] = max(50, min(5000, $sanitized['max_tokens']));

        // Prompt template
        $sanitized['prompt_template'] = isset($input['prompt_template'])
            ? sanitize_textarea_field($input['prompt_template'])
            : '';

        return $sanitized;
    }

    /**
     * Get the response language name from AI Settings
     *
     * @return string Language name (e.g., "English", "Spanish", "French")
     */
    private function get_response_language_name() {
        // Get AI Settings
        if (!class_exists('Voxel_Toolkit_Settings')) {
            return 'English';
        }

        $settings = Voxel_Toolkit_Settings::instance();
        $ai_settings = $settings->get_function_settings('ai_settings', array());
        $language_code = isset($ai_settings['response_language']) ? $ai_settings['response_language'] : 'en';

        // Map language codes to names
        $languages = array(
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'ru' => 'Russian',
            'uk' => 'Ukrainian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'zh' => 'Chinese',
            'ar' => 'Arabic',
            'hi' => 'Hindi',
            'tr' => 'Turkish',
            'vi' => 'Vietnamese',
            'th' => 'Thai',
            'id' => 'Indonesian',
            'ms' => 'Malay',
            'sv' => 'Swedish',
            'da' => 'Danish',
            'no' => 'Norwegian',
            'fi' => 'Finnish',
            'el' => 'Greek',
            'cs' => 'Czech',
            'ro' => 'Romanian',
            'hu' => 'Hungarian',
            'he' => 'Hebrew',
        );

        return isset($languages[$language_code]) ? $languages[$language_code] : 'English';
    }
}
