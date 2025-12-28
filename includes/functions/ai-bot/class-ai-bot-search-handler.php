<?php
/**
 * AI Bot Search Handler
 *
 * Handles translating AI responses into Voxel searches and rendering results.
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_AI_Bot_Search_Handler {

    /**
     * Settings
     */
    private $settings;

    /**
     * Schema cache transient key
     */
    const SCHEMA_TRANSIENT = 'vt_ai_bot_schema';

    /**
     * Constructor
     */
    public function __construct($settings = array()) {
        $this->settings = $settings;
    }

    /**
     * Get enabled post types for searching
     */
    public function get_enabled_post_types() {
        // Get from settings or default to all Voxel post types
        $enabled = isset($this->settings['post_types']) ? (array) $this->settings['post_types'] : array();

        // Filter out empty values
        $enabled = array_filter($enabled);

        error_log('VT AI Bot Search - Settings post_types: ' . wp_json_encode($this->settings['post_types'] ?? 'not set'));

        if (empty($enabled)) {
            // Default: get all Voxel post types
            $enabled = $this->get_all_voxel_post_types();
        }

        return $enabled;
    }

    /**
     * Get all Voxel post types
     */
    private function get_all_voxel_post_types() {
        if (!class_exists('\Voxel\Post_Type')) {
            error_log('VT AI Bot Search - Voxel Post_Type class not found');
            return array();
        }

        $voxel_types = \Voxel\Post_Type::get_voxel_types();
        error_log('VT AI Bot Search - All Voxel types: ' . implode(', ', array_keys($voxel_types)));
        return array_keys($voxel_types);
    }

    /**
     * Get search schema for AI
     */
    public function get_search_schema() {
        // Try cache first
        $cached = get_transient(self::SCHEMA_TRANSIENT);
        if ($cached !== false) {
            return $cached;
        }

        $schema = array();
        $enabled_types = $this->get_enabled_post_types();

        foreach ($enabled_types as $post_type_key) {
            if (!class_exists('\Voxel\Post_Type')) {
                continue;
            }

            $post_type = \Voxel\Post_Type::get($post_type_key);
            if (!$post_type) {
                continue;
            }

            $schema[$post_type_key] = array(
                'label' => $post_type->get_label(),
                'singular' => $post_type->get_singular_name(),
                'filters' => $this->get_filter_schema($post_type),
            );
        }

        // Cache for 1 hour
        set_transient(self::SCHEMA_TRANSIENT, $schema, HOUR_IN_SECONDS);

        return $schema;
    }

    /**
     * Get filter schema for a post type (includes both filters and custom fields)
     */
    private function get_filter_schema($post_type) {
        $schema = array();

        // Track which fields have filters (for faster indexed search)
        $fields_with_filters = array();

        // First, add all configured filters (these use Voxel's indexed search - faster)
        foreach ($post_type->get_filters() as $filter) {
            $filter_key = $filter->get_key();
            $filter_data = array(
                'type' => $filter->get_type(),
                'label' => $filter->get_label(),
                'indexed' => true, // This filter uses Voxel's index table
            );

            // Track the source field
            $source_field = $filter->get_prop('source');
            if ($source_field) {
                $fields_with_filters[] = $source_field;
            }

            // Add options for taxonomy/select filters
            $type = $filter->get_type();
            if (in_array($type, array('terms', 'taxonomy'), true)) {
                $taxonomy = $filter->get_prop('taxonomy');
                if ($taxonomy) {
                    $terms = get_terms(array(
                        'taxonomy' => $taxonomy,
                        'hide_empty' => false,
                        'number' => 50, // Limit to prevent huge schemas
                    ));

                    if (!is_wp_error($terms)) {
                        $filter_data['options'] = array();
                        foreach ($terms as $term) {
                            $filter_data['options'][] = array(
                                'value' => $term->slug,
                                'label' => $term->name,
                            );
                        }
                    }
                }
            }

            // Add hint for location filters
            if ($type === 'location') {
                $radius_units = $filter->get_prop('radius_units');
                $units_label = ($radius_units === 'mi') ? 'miles' : 'kilometers';
                $filter_data['radius_units'] = $radius_units ?: 'km';
                $filter_data['hint'] = 'Use format: {"address": "city, state, country or full address", "radius": number_in_' . $units_label . '}. The address will be geocoded automatically.';
            }

            // Add hint for date filters
            if ($type === 'date') {
                $filter_data['hint'] = 'Use format: {"from": "YYYY-MM-DD", "to": "YYYY-MM-DD"}';
            }

            // Add hint for range filters
            if (in_array($type, array('range', 'stepper'), true)) {
                $filter_data['hint'] = 'Use format: {"min": number, "max": number} or just a number for exact match';
            }

            $schema[$filter_key] = $filter_data;
        }

        // Now add all custom fields that don't have filters (these use post meta - slower but flexible)
        foreach ($post_type->get_fields() as $field) {
            $field_key = $field->get_key();
            $field_type = $field->get_type();

            // Skip fields that already have filters, or system fields
            if (in_array($field_key, $fields_with_filters, true)) {
                continue;
            }

            // Skip certain field types that aren't useful for searching
            $skip_types = array('ui-step', 'ui-heading', 'ui-html', 'ui-image', 'file', 'image', 'profile-avatar', 'post-relation');
            if (in_array($field_type, $skip_types, true)) {
                continue;
            }

            $field_data = array(
                'type' => 'field:' . $field_type,
                'label' => $field->get_label(),
                'indexed' => false, // Uses post meta query
            );

            // Add hints based on field type
            switch ($field_type) {
                case 'text':
                case 'texteditor':
                case 'textarea':
                    $field_data['hint'] = 'Text search. Use _field:' . $field_key . ' with a text value to search.';
                    break;

                case 'number':
                    $field_data['hint'] = 'Use _field:' . $field_key . ' with {"min": X} or {"max": X} or {"equals": X}';
                    break;

                case 'switcher':
                    $field_data['hint'] = 'Use _field:' . $field_key . ' with true or false';
                    break;

                case 'select':
                    // Get select options
                    $choices = $field->get_prop('choices');
                    if (!empty($choices)) {
                        $field_data['options'] = array();
                        foreach ($choices as $choice) {
                            if (isset($choice['value']) && isset($choice['label'])) {
                                $field_data['options'][] = array(
                                    'value' => $choice['value'],
                                    'label' => $choice['label'],
                                );
                            }
                        }
                    }
                    $field_data['hint'] = 'Use _field:' . $field_key . ' with the option value';
                    break;

                case 'taxonomy':
                    $taxonomy = $field->get_prop('taxonomy');
                    if ($taxonomy) {
                        $terms = get_terms(array(
                            'taxonomy' => $taxonomy,
                            'hide_empty' => false,
                            'number' => 30,
                        ));
                        if (!is_wp_error($terms) && !empty($terms)) {
                            $field_data['options'] = array();
                            foreach ($terms as $term) {
                                $field_data['options'][] = array(
                                    'value' => $term->slug,
                                    'label' => $term->name,
                                );
                            }
                        }
                    }
                    $field_data['hint'] = 'Use _field:' . $field_key . ' with the term slug';
                    break;

                default:
                    $field_data['hint'] = 'Use _field:' . $field_key . ' with appropriate value';
            }

            // Add with _field: prefix to distinguish from regular filters
            $schema['_field:' . $field_key] = $field_data;
        }

        // Add special review/rating pseudo-filters
        $schema['_min_rating'] = array(
            'type' => 'special',
            'label' => 'Minimum Rating (Stars 1-5)',
            'hint' => 'Number from 1 to 5. Example: 4 means "4 stars and above". Internal scale is -2 to 2.',
            'indexed' => false,
        );

        $schema['_min_reviews'] = array(
            'type' => 'special',
            'label' => 'Minimum Review Count',
            'hint' => 'Number. Example: 1 means "at least 1 review", 5 means "at least 5 reviews"',
            'indexed' => false,
        );

        // Add content/post body search
        $schema['_content'] = array(
            'type' => 'special',
            'label' => 'Post Content Search',
            'hint' => 'Search within the post body/content text',
            'indexed' => false,
        );

        return $schema;
    }

    /**
     * Build system prompt for AI
     */
    public function build_system_prompt() {
        $schema = $this->get_search_schema();
        $site_name = get_bloginfo('name');
        $max_results = isset($this->settings['max_results']) ? absint($this->settings['max_results']) : 6;

        // Use custom prompt if provided
        $custom_prompt = isset($this->settings['system_prompt']) ? $this->settings['system_prompt'] : '';

        if (!empty($custom_prompt)) {
            // Replace placeholders in custom prompt
            $custom_prompt = str_replace(
                array('{{site_name}}', '{{schema}}', '{{max_results}}'),
                array($site_name, wp_json_encode($schema, JSON_PRETTY_PRINT), $max_results),
                $custom_prompt
            );
            return $custom_prompt;
        }

        // Build post type descriptions
        $type_descriptions = array();
        foreach ($schema as $key => $data) {
            $type_descriptions[] = $data['label'] . ' (' . $data['singular'] . ')';
        }

        $prompt = "You are a helpful search assistant for {$site_name}. You help users find content including: " . implode(', ', $type_descriptions) . ".

Available post types and their searchable filters:
" . wp_json_encode($schema, JSON_PRETTY_PRINT) . "

When a user asks a question:
1. Understand their intent and what they're looking for
2. Determine which post type(s) to search
3. Extract search parameters that match the available filters
4. ALWAYS respond with a JSON object in this exact format:

{
  \"explanation\": \"Brief 1-2 sentence explanation of what you're searching for\",
  \"searches\": [
    {
      \"post_type\": \"the_post_type_key\",
      \"filters\": {
        \"filter_key\": \"value\"
      },
      \"limit\": {$max_results}
    }
  ]
}

Important rules:
- Only use filter keys that exist in the schema above
- For keyword searches, use the 'keywords' filter if available
- The 'searches' array can contain multiple searches if needed
- Maximum {$max_results} results per search
- If you can't determine what to search, ask clarifying questions in the explanation and leave searches empty
- Always respond with ONLY the JSON object, no additional text before or after
- NEVER add comments in the JSON (no // or /* */ comments)
- The JSON must be valid and parseable

Special filters (prefixed with underscore):
- \"_min_rating\": Minimum star rating (1-5). Example: 4 means \"4 stars and above\"
- \"_min_reviews\": Minimum number of reviews. Example: 1 means \"has at least 1 review\"
- \"_content\": Search within post title/content/excerpt. Example: \"wood fired\"
- \"_field:field_key\": Search by any custom field value. Examples:
  - \"_field:price\": {\"min\": 10, \"max\": 50} for number ranges
  - \"_field:is_featured\": true for boolean/switcher fields
  - \"_field:cuisine_type\": \"italian\" for text/select fields

Filters marked as \"indexed: true\" in the schema use fast database queries.
Filters marked as \"indexed: false\" (special filters, custom fields) filter results after the main search.

Example user question: \"Find Italian restaurants near downtown\"
Example response:
{
  \"explanation\": \"Looking for Italian restaurants in the downtown area.\",
  \"searches\": [
    {
      \"post_type\": \"places\",
      \"filters\": {
        \"keywords\": \"Italian restaurant\",
        \"location\": {\"address\": \"downtown\", \"radius\": 5}
      },
      \"limit\": {$max_results}
    }
  ]
}";

        return $prompt;
    }

    /**
     * Execute searches based on AI response
     */
    public function execute_search($ai_parsed_response) {
        $results = array();

        if (!isset($ai_parsed_response['searches']) || !is_array($ai_parsed_response['searches'])) {
            error_log('VT AI Bot Search - No searches array in parsed response');
            return $results;
        }

        $max_results = isset($this->settings['max_results']) ? absint($this->settings['max_results']) : 6;

        foreach ($ai_parsed_response['searches'] as $search) {
            if (!isset($search['post_type'])) {
                error_log('VT AI Bot Search - Search missing post_type');
                continue;
            }

            $post_type_key = sanitize_text_field($search['post_type']);
            error_log('VT AI Bot Search - Processing post_type: ' . $post_type_key);

            // Verify post type is enabled
            $enabled_types = $this->get_enabled_post_types();
            error_log('VT AI Bot Search - Enabled types: ' . implode(', ', $enabled_types));
            if (!in_array($post_type_key, $enabled_types, true)) {
                error_log('VT AI Bot Search - Post type not enabled: ' . $post_type_key);
                continue;
            }

            // Check if Voxel search function exists
            if (!function_exists('Voxel\get_search_results')) {
                error_log('VT AI Bot Search - Voxel get_search_results function not found');
                continue;
            }

            // Extract special filters (reviews/ratings, custom fields, content)
            $special_filters = array();
            $regular_filters = array();

            if (isset($search['filters']) && is_array($search['filters'])) {
                foreach ($search['filters'] as $key => $value) {
                    // Check if this is a special filter (starts with underscore)
                    if (strpos($key, '_') === 0) {
                        // Special filter: _min_rating, _min_reviews, _field:*, _content
                        $special_filters[$key] = $value;
                    } else {
                        // Regular Voxel filter
                        $regular_filters[sanitize_text_field($key)] = $this->sanitize_filter_value($value);
                    }
                }
            }

            // Geocode location filters if needed
            $regular_filters = $this->process_location_filters($regular_filters);

            // Build request with regular filters only
            $request = array(
                'type' => $post_type_key,
            );
            $request = array_merge($request, $regular_filters);

            // If we have special filters, we need to fetch more results initially
            // then filter them down
            $fetch_limit = $max_results;
            if (!empty($special_filters)) {
                // Fetch many more to account for filtering (most posts may not have reviews)
                $fetch_limit = max(100, $max_results * 20);
            }

            $limit = isset($search['limit']) ? min(absint($search['limit']), $max_results) : $max_results;

            // Get template ID for this post type
            $template_id = $this->get_card_template_for_post_type($post_type_key);

            error_log('VT AI Bot Search - Request: ' . wp_json_encode($request));
            error_log('VT AI Bot Search - Fetch limit: ' . $fetch_limit . ', Final limit: ' . $limit);
            error_log('VT AI Bot Search - Special filters: ' . wp_json_encode($special_filters));
            error_log('VT AI Bot Search - Template ID: ' . ($template_id ?: 'default'));

            // Build search options
            $search_options = array(
                'limit' => $fetch_limit,
                'render' => empty($special_filters), // Only render if no special filtering needed
            );

            // Add custom template if specified
            if ($template_id) {
                $search_options['template_id'] = $template_id;
            }

            // Execute search with error handling
            try {
                $search_results = \Voxel\get_search_results($request, $search_options);
            } catch (\Exception $e) {
                error_log('VT AI Bot Search - Voxel search error: ' . $e->getMessage());
                continue; // Skip this search and try the next one
            } catch (\Error $e) {
                error_log('VT AI Bot Search - Voxel search fatal error: ' . $e->getMessage());
                continue;
            }

            if (!isset($search_results['ids']) || !is_array($search_results['ids'])) {
                error_log('VT AI Bot Search - Invalid search results format');
                continue;
            }

            error_log('VT AI Bot Search - Found ' . count($search_results['ids']) . ' initial results');

            $final_ids = $search_results['ids'];
            $final_html = isset($search_results['render']) ? $search_results['render'] : '';

            // Apply special filters if present
            if (!empty($special_filters) && !empty($final_ids)) {
                $final_ids = $this->apply_special_filters($final_ids, $special_filters, $post_type_key);
                error_log('VT AI Bot Search - After special filters: ' . count($final_ids) . ' results');

                // Limit to requested amount
                $final_ids = array_slice($final_ids, 0, $limit);

                // Now render the filtered results
                if (!empty($final_ids)) {
                    $final_html = $this->render_posts($final_ids, $post_type_key);
                }
            }

            // Get post type info
            $post_type = \Voxel\Post_Type::get($post_type_key);
            $label = $post_type ? $post_type->get_label() : $post_type_key;

            // Get archive URL for "see more" link
            $archive_url = '';
            if ($post_type) {
                $archive_url = get_post_type_archive_link($post_type_key);
                // If no archive, try to get from Voxel settings
                if (!$archive_url && method_exists($post_type, 'get_archive_link')) {
                    $archive_url = $post_type->get_archive_link();
                }
            }

            $results[] = array(
                'post_type' => $post_type_key,
                'post_type_label' => $label,
                'ids' => $final_ids,
                'html' => $final_html,
                'count' => count($final_ids),
                'has_more' => count($search_results['ids']) > count($final_ids),
                'archive_url' => $archive_url,
            );
        }

        return $results;
    }

    /**
     * Apply special filters to post IDs (reviews, custom fields, content)
     *
     * @param array $post_ids Array of post IDs
     * @param array $filters Special filters (_min_rating, _min_reviews, _field:*, _content)
     * @param string $post_type_key Post type key
     * @return array Filtered post IDs
     */
    private function apply_special_filters($post_ids, $filters, $post_type_key) {
        $filtered_ids = $post_ids;

        // Extract filter values
        $min_rating = isset($filters['_min_rating']) ? floatval($filters['_min_rating']) : 0;
        $min_reviews = isset($filters['_min_reviews']) ? intval($filters['_min_reviews']) : 0;
        $content_search = isset($filters['_content']) ? sanitize_text_field($filters['_content']) : '';

        // Collect custom field filters
        $field_filters = array();
        foreach ($filters as $key => $value) {
            if (strpos($key, '_field:') === 0) {
                $field_key = substr($key, 7); // Remove '_field:' prefix
                $field_filters[$field_key] = $value;
            }
        }

        error_log('VT AI Bot Search - Special filters: min_rating=' . $min_rating . ', min_reviews=' . $min_reviews . ', content=' . $content_search . ', field_filters=' . wp_json_encode($field_filters));

        // Apply filters to each post
        $result_ids = array();

        foreach ($filtered_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }

            $passes = true;

            // Check review/rating filters
            if ($min_rating > 0 || $min_reviews > 0) {
                $passes = $this->check_review_filter($post_id, $min_rating, $min_reviews);
            }

            // Check content filter
            if ($passes && !empty($content_search)) {
                $passes = $this->check_content_filter($post, $content_search);
            }

            // Check custom field filters
            if ($passes && !empty($field_filters)) {
                $passes = $this->check_field_filters($post_id, $field_filters, $post_type_key);
            }

            if ($passes) {
                $result_ids[] = $post_id;
            }
        }

        return $result_ids;
    }

    /**
     * Check if post passes review/rating filter
     */
    private function check_review_filter($post_id, $min_rating, $min_reviews) {
        $stats = get_post_meta($post_id, 'voxel:review_stats', true);

        if (empty($stats)) {
            // No reviews - fail if minimum reviews required
            return ($min_reviews <= 0 && $min_rating <= 0);
        }

        // Parse stats if it's a JSON string
        if (is_string($stats)) {
            $stats = json_decode($stats, true);
        }

        if (!is_array($stats)) {
            return false;
        }

        $review_count = isset($stats['total']) ? intval($stats['total']) : 0;
        $average_rating = isset($stats['average']) ? floatval($stats['average']) : -3;

        // Convert star rating (1-5) to internal scale (-2 to 2)
        $min_rating_internal = $min_rating > 0 ? $min_rating - 3 : -3;

        // Check minimum reviews
        if ($min_reviews > 0 && $review_count < $min_reviews) {
            return false;
        }

        // Check minimum rating
        if ($min_rating > 0 && $average_rating < $min_rating_internal) {
            return false;
        }

        return true;
    }

    /**
     * Check if post passes content filter
     */
    private function check_content_filter($post, $search_term) {
        $search_term = strtolower($search_term);

        // Search in title
        if (stripos($post->post_title, $search_term) !== false) {
            return true;
        }

        // Search in content
        if (stripos($post->post_content, $search_term) !== false) {
            return true;
        }

        // Search in excerpt
        if (stripos($post->post_excerpt, $search_term) !== false) {
            return true;
        }

        return false;
    }

    /**
     * Check if post passes custom field filters
     */
    private function check_field_filters($post_id, $field_filters, $post_type_key) {
        foreach ($field_filters as $field_key => $filter_value) {
            $field_value = get_post_meta($post_id, $field_key, true);

            // Handle JSON-encoded values
            if (is_string($field_value) && (strpos($field_value, '[') === 0 || strpos($field_value, '{') === 0)) {
                $decoded = json_decode($field_value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $field_value = $decoded;
                }
            }

            // Handle different filter value types
            if (is_array($filter_value)) {
                // Complex filter (min/max/equals)
                if (!$this->check_complex_field_filter($field_value, $filter_value)) {
                    return false;
                }
            } elseif (is_bool($filter_value) || $filter_value === 'true' || $filter_value === 'false') {
                // Boolean filter
                $expected = ($filter_value === true || $filter_value === 'true' || $filter_value === '1');
                $actual = ($field_value === '1' || $field_value === 1 || $field_value === true);
                if ($expected !== $actual) {
                    return false;
                }
            } elseif (is_array($field_value)) {
                // Field is array (multiselect, etc.) - check if filter value is in array
                if (!in_array($filter_value, $field_value, true) && !in_array(strval($filter_value), $field_value, true)) {
                    return false;
                }
            } else {
                // Simple text/value comparison
                $filter_str = strtolower(strval($filter_value));
                $field_str = strtolower(strval($field_value));

                // Check for exact match or contains
                if ($field_str !== $filter_str && stripos($field_str, $filter_str) === false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check complex field filter (min/max/equals)
     */
    private function check_complex_field_filter($field_value, $filter_config) {
        $field_num = is_numeric($field_value) ? floatval($field_value) : 0;

        if (isset($filter_config['equals'])) {
            return $field_num == floatval($filter_config['equals']);
        }

        if (isset($filter_config['min']) && $field_num < floatval($filter_config['min'])) {
            return false;
        }

        if (isset($filter_config['max']) && $field_num > floatval($filter_config['max'])) {
            return false;
        }

        return true;
    }

    /**
     * Render posts using Voxel's card templates
     *
     * @param array $post_ids Array of post IDs
     * @param string $post_type_key Post type key
     * @return string Rendered HTML
     */
    private function render_posts($post_ids, $post_type_key) {
        if (empty($post_ids)) {
            return '';
        }

        // Get template ID from settings
        $template_id = $this->get_card_template_for_post_type($post_type_key);

        // Use output buffering to capture rendered HTML
        ob_start();

        // Get post type for template info
        $post_type = \Voxel\Post_Type::get($post_type_key);

        // If no custom template specified, use default card template
        if (!$template_id && $post_type) {
            $templates = $post_type->get_templates();
            $template_id = isset($templates['card']) ? $templates['card'] : null;
        }

        // Validate custom template if specified
        if ($template_id && $post_type) {
            $custom_templates = $post_type->templates->get_custom_templates();
            $valid_custom_ids = isset($custom_templates['card']) ? array_column($custom_templates['card'], 'id') : array();

            // Check if it's a valid custom template or the default
            $default_template = $post_type->get_templates()['card'];
            if ($template_id !== $default_template && !in_array($template_id, $valid_custom_ids)) {
                // Invalid template, fall back to default
                $template_id = $default_template;
            }
        }

        if ($template_id) {
            // Enqueue template CSS
            if (function_exists('Voxel\enqueue_template_css')) {
                \Voxel\enqueue_template_css($template_id);
            }

            // Render each post with the template
            foreach ($post_ids as $post_id) {
                $post = \Voxel\Post::get($post_id);
                if (!$post) {
                    continue;
                }

                // Set current post context for dynamic tags
                if (function_exists('Voxel\set_current_post')) {
                    \Voxel\set_current_post($post);
                }

                echo '<div class="ts-preview" data-post-id="' . esc_attr($post_id) . '">';
                if (function_exists('Voxel\print_template')) {
                    \Voxel\print_template($template_id);
                }
                // Add action buttons
                echo $this->render_action_buttons($post);
                echo '</div>';
            }
        }

        return ob_get_clean();
    }

    /**
     * Render action buttons for a post (Directions, Call, View)
     *
     * @param \Voxel\Post $post The post object
     * @return string HTML for action buttons
     */
    private function render_action_buttons($post) {
        $html = '<div class="vt-ai-bot-card-actions">';
        $has_buttons = false;

        // Get Directions - if post has location field
        $location = $post->get_field('location');
        if ($location) {
            $value = $location->get_value();
            if (!empty($value['latitude']) && !empty($value['longitude'])) {
                $maps_url = 'https://www.google.com/maps/dir/?api=1&destination=' . $value['latitude'] . ',' . $value['longitude'];
                $html .= '<a href="' . esc_url($maps_url) . '" target="_blank" rel="noopener" class="vt-ai-bot-action-btn">';
                $html .= '<i class="las la-directions"></i> <span>Directions</span>';
                $html .= '</a>';
                $has_buttons = true;
            }
        }

        // Call - check common phone field names
        $phone_value = null;
        $phone_fields = array('phone', 'phone_number', 'telephone', 'tel', 'contact_phone');
        foreach ($phone_fields as $field_name) {
            $phone_field = $post->get_field($field_name);
            if ($phone_field) {
                $phone_value = $phone_field->get_value();
                if (!empty($phone_value)) {
                    break;
                }
            }
        }

        if (!empty($phone_value)) {
            // Clean phone number for tel: link
            $phone_clean = preg_replace('/[^0-9+]/', '', $phone_value);
            $html .= '<a href="tel:' . esc_attr($phone_clean) . '" class="vt-ai-bot-action-btn">';
            $html .= '<i class="las la-phone"></i> <span>Call</span>';
            $html .= '</a>';
            $has_buttons = true;
        }

        // View Details - always show
        $html .= '<a href="' . esc_url(get_permalink($post->get_id())) . '" class="vt-ai-bot-action-btn">';
        $html .= '<i class="las la-external-link-alt"></i> <span>View</span>';
        $html .= '</a>';
        $has_buttons = true;

        $html .= '</div>';

        return $has_buttons ? $html : '';
    }

    /**
     * Get card template ID for a post type from settings
     *
     * @param string $post_type_key Post type key
     * @return int|null Template ID or null for default
     */
    private function get_card_template_for_post_type($post_type_key) {
        $card_templates = isset($this->settings['card_templates']) ? $this->settings['card_templates'] : array();

        if (isset($card_templates[$post_type_key]) && !empty($card_templates[$post_type_key])) {
            return intval($card_templates[$post_type_key]);
        }

        return null;
    }

    /**
     * Sanitize filter value (recursive for arrays)
     */
    private function sanitize_filter_value($value) {
        if (is_array($value)) {
            $sanitized = array();
            foreach ($value as $k => $v) {
                $sanitized[sanitize_text_field($k)] = $this->sanitize_filter_value($v);
            }
            return $sanitized;
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value;
        }

        return sanitize_text_field($value);
    }

    /**
     * Clear schema cache
     */
    public static function clear_schema_cache() {
        delete_transient(self::SCHEMA_TRANSIENT);
    }

    /**
     * Process location filters - geocode addresses and format for Voxel
     *
     * Voxel expects location filter values in string format:
     * - Radius search: "address;lat,lng,radius"
     * - Area search: "address;swlat,swlng..nelat,nelng"
     *
     * @param array $filters Filter array
     * @return array Processed filters with formatted location strings
     */
    private function process_location_filters($filters) {
        // Check for common location filter keys
        $location_keys = array('location', 'address', 'nearby', 'geo');

        foreach ($location_keys as $key) {
            if (!isset($filters[$key])) {
                continue;
            }

            $location = $filters[$key];
            $address_text = '';
            $lat = null;
            $lng = null;
            $radius = 10; // Default radius

            // If it's just a string, treat it as an address to geocode
            if (is_string($location)) {
                $address_text = $location;
                $geocoded = $this->geocode_address($location);
                if ($geocoded) {
                    $lat = $geocoded['lat'];
                    $lng = $geocoded['lng'];
                    $address_text = $geocoded['address'];
                }
            }
            // If it's an array, extract components
            elseif (is_array($location)) {
                // Get address
                $address_text = isset($location['address']) ? $location['address'] : '';

                // Get radius if provided
                if (isset($location['radius'])) {
                    $radius = floatval($location['radius']);
                }

                // Check for existing coordinates
                if (isset($location['lat']) && isset($location['lng'])) {
                    $lat = floatval($location['lat']);
                    $lng = floatval($location['lng']);
                } elseif (isset($location['latitude']) && isset($location['longitude'])) {
                    $lat = floatval($location['latitude']);
                    $lng = floatval($location['longitude']);
                }
                // Geocode if we have address but no coordinates
                elseif (!empty($address_text)) {
                    $geocoded = $this->geocode_address($address_text);
                    if ($geocoded) {
                        $lat = $geocoded['lat'];
                        $lng = $geocoded['lng'];
                        $address_text = $geocoded['address'];
                    }
                }
            }

            // If we have valid coordinates, format for Voxel
            if ($lat !== null && $lng !== null) {
                // Voxel format for radius search: "address;lat,lng,radius"
                $formatted = sprintf('%s;%s,%s,%s', $address_text, $lat, $lng, $radius);
                $filters[$key] = $formatted;
                error_log('VT AI Bot Search - Formatted location filter: ' . $formatted);
            } else {
                // Remove invalid location filter
                unset($filters[$key]);
                error_log('VT AI Bot Search - Could not geocode location, removing filter');
            }
        }

        return $filters;
    }

    /**
     * Geocode an address using Google Geocoding API
     *
     * @param string $address Address to geocode
     * @return array|null Geocoded location with lat, lng, address, or null on failure
     */
    private function geocode_address($address) {
        if (empty($address)) {
            return null;
        }

        // Create cache key
        $cache_key = 'vt_ai_bot_geocode_' . md5($address);

        // Check cache first (24 hour expiration)
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            error_log('VT AI Bot Search - Using cached geocode for: ' . $address);
            return $cached;
        }

        // Get Google API key from Voxel settings
        $api_key = '';
        if (function_exists('Voxel\get')) {
            $api_key = \Voxel\get('settings.maps.google_maps.api_key');
        }

        if (empty($api_key)) {
            error_log('VT AI Bot Search - No Google Maps API key found');
            return null;
        }

        // Call Google Geocoding API
        $url = sprintf(
            'https://maps.googleapis.com/maps/api/geocode/json?address=%s&key=%s',
            urlencode($address),
            $api_key
        );

        error_log('VT AI Bot Search - Geocoding address: ' . $address);

        $response = wp_remote_get($url, array('timeout' => 10));

        if (is_wp_error($response)) {
            error_log('VT AI Bot Search - Geocoding API error: ' . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['results'][0]['geometry']['location'])) {
            error_log('VT AI Bot Search - Geocoding returned no results for: ' . $address);
            return null;
        }

        $location = $data['results'][0]['geometry']['location'];
        $formatted_address = isset($data['results'][0]['formatted_address']) ? $data['results'][0]['formatted_address'] : $address;

        $result = array(
            'address' => $formatted_address,
            'lat' => $location['lat'],
            'lng' => $location['lng'],
        );

        error_log('VT AI Bot Search - Geocoded: ' . $address . ' -> ' . $location['lat'] . ', ' . $location['lng']);

        // Cache for 24 hours
        set_transient($cache_key, $result, DAY_IN_SECONDS);

        return $result;
    }
}
