<?php
/**
 * Filter Bar Class
 *
 * Handles the advanced filter bar UI and query modification for admin list tables.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Filter_Bar {

    /**
     * Filter type: 'posts' or 'users'
     */
    private $type;

    /**
     * Post type (for posts filter)
     */
    private $post_type;

    /**
     * Available filterable fields
     */
    private $fields = array();

    /**
     * Constructor
     */
    public function __construct($type, $post_type = '') {
        $this->type = $type;
        $this->post_type = $post_type;
    }

    /**
     * Set filterable fields
     */
    public function set_fields($fields) {
        $this->fields = $fields;
    }

    /**
     * Get filter type based on field
     */
    public function get_filter_type($field) {
        $type = isset($field['filter_type']) ? $field['filter_type'] : 'text';

        // Map field types to filter types
        $type_map = array(
            'text' => 'text',
            'texteditor' => 'text',
            'textarea' => 'text',
            'email' => 'text',
            'url' => 'text',
            'phone' => 'text',
            'number' => 'number',
            'date' => 'date',
            'recurring-date' => 'date',
            'event-date' => 'date',
            'select' => 'select',
            'switcher' => 'boolean',
            'taxonomy' => 'taxonomy',
            'post-relation' => 'select',
            'user' => 'select',
        );

        return isset($type_map[$type]) ? $type_map[$type] : 'text';
    }

    /**
     * Render the filter bar container
     */
    public function render() {
        // Get base URL (current page without filters)
        $base_url = remove_query_arg(array('vt_filter', 'paged'));

        // Prepare fields data for JS
        $fields_data = array();
        foreach ($this->fields as $field) {
            $field_data = array(
                'key' => $field['key'],
                'label' => $field['label'],
                'filter_type' => $this->get_filter_type($field),
            );

            // Add options for select-type fields
            if (isset($field['options'])) {
                $field_data['options'] = $field['options'];
            }

            $fields_data[] = $field_data;
        }

        // Localize the script with configuration
        $config = array(
            'fields' => $fields_data,
            'baseUrl' => $base_url,
            'i18n' => array(
                'advanced_filter' => __('Advanced Filter', 'voxel-toolkit'),
                'add_property' => __('Add', 'voxel-toolkit'),
                'add_filter' => __('Add Filter', 'voxel-toolkit'),
                'filter' => __('Filter', 'voxel-toolkit'),
                'clear' => __('Clear', 'voxel-toolkit'),
                'remove' => __('Remove', 'voxel-toolkit'),
                'select_field' => __('Select field...', 'voxel-toolkit'),
                'select_value' => __('Select...', 'voxel-toolkit'),
                'enter_value' => __('Enter value...', 'voxel-toolkit'),
                'equals' => __('equals', 'voxel-toolkit'),
                'not_equals' => __('does not equal', 'voxel-toolkit'),
                'contains' => __('contains', 'voxel-toolkit'),
                'not_contains' => __('does not contain', 'voxel-toolkit'),
                'starts_with' => __('starts with', 'voxel-toolkit'),
                'ends_with' => __('ends with', 'voxel-toolkit'),
                'greater_than' => __('greater than', 'voxel-toolkit'),
                'less_than' => __('less than', 'voxel-toolkit'),
                'greater_equal' => __('greater or equal', 'voxel-toolkit'),
                'less_equal' => __('less or equal', 'voxel-toolkit'),
                'is_empty' => __('is empty', 'voxel-toolkit'),
                'is_not_empty' => __('is not empty', 'voxel-toolkit'),
                'is' => __('is', 'voxel-toolkit'),
                'is_not' => __('is not', 'voxel-toolkit'),
                'before' => __('before', 'voxel-toolkit'),
                'after' => __('after', 'voxel-toolkit'),
                'match_all' => __('Match all', 'voxel-toolkit'),
                'match_any' => __('Match any', 'voxel-toolkit'),
            ),
        );

        ?>
        <div id="vt-filter-bar-container"></div>
        <script>
            var vtFilterBarConfig = <?php echo wp_json_encode($config); ?>;
        </script>
        <?php
    }

    /**
     * Parse filters from request
     */
    public static function parse_filters() {
        $filters = array();

        if (!isset($_GET['vt_filter']) || !is_array($_GET['vt_filter'])) {
            return $filters;
        }

        foreach ($_GET['vt_filter'] as $index => $filter) {
            if (empty($filter['field'])) {
                continue;
            }

            $filters[] = array(
                'field' => sanitize_text_field($filter['field']),
                'operator' => isset($filter['operator']) ? sanitize_text_field($filter['operator']) : 'equals',
                'value' => isset($filter['value']) ? sanitize_text_field($filter['value']) : '',
            );
        }

        return $filters;
    }

    /**
     * Get filter logic (and/or)
     */
    public static function get_filter_logic() {
        if (isset($_GET['vt_filter_logic']) && $_GET['vt_filter_logic'] === 'or') {
            return 'OR';
        }
        return 'AND';
    }

    /**
     * Build meta query from filters
     */
    public static function build_meta_query($filters, $field_config = array()) {
        $meta_query = array();

        foreach ($filters as $filter) {
            $field_key = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'];

            // Skip if no field
            if (empty($field_key)) {
                continue;
            }

            // Get the actual meta key (remove : prefix for special fields)
            $meta_key = ltrim($field_key, ':');

            // Handle different operators
            switch ($operator) {
                case 'equals':
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => $value,
                        'compare' => '=',
                    );
                    break;

                case 'not_equals':
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => $value,
                        'compare' => '!=',
                    );
                    break;

                case 'contains':
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => $value,
                        'compare' => 'LIKE',
                    );
                    break;

                case 'not_contains':
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => $value,
                        'compare' => 'NOT LIKE',
                    );
                    break;

                case 'starts_with':
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => $value . '%',
                        'compare' => 'LIKE',
                    );
                    break;

                case 'ends_with':
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => '%' . $value,
                        'compare' => 'LIKE',
                    );
                    break;

                case 'greater_than':
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => $value,
                        'compare' => '>',
                        'type' => is_numeric($value) ? 'NUMERIC' : 'CHAR',
                    );
                    break;

                case 'less_than':
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => $value,
                        'compare' => '<',
                        'type' => is_numeric($value) ? 'NUMERIC' : 'CHAR',
                    );
                    break;

                case 'greater_equal':
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => $value,
                        'compare' => '>=',
                        'type' => is_numeric($value) ? 'NUMERIC' : 'CHAR',
                    );
                    break;

                case 'less_equal':
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => $value,
                        'compare' => '<=',
                        'type' => is_numeric($value) ? 'NUMERIC' : 'CHAR',
                    );
                    break;

                case 'is_empty':
                    $meta_query[] = array(
                        'relation' => 'OR',
                        array(
                            'key' => $meta_key,
                            'compare' => 'NOT EXISTS',
                        ),
                        array(
                            'key' => $meta_key,
                            'value' => '',
                            'compare' => '=',
                        ),
                    );
                    break;

                case 'is_not_empty':
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'compare' => 'EXISTS',
                    );
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => '',
                        'compare' => '!=',
                    );
                    break;
            }
        }

        return $meta_query;
    }

    /**
     * Build taxonomy query from filters
     */
    public static function build_tax_query($filters, $field_config = array()) {
        $tax_query = array();

        foreach ($filters as $filter) {
            $field_key = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'];

            // Check if this is a taxonomy field
            if (!isset($field_config[$field_key]) || $field_config[$field_key]['filter_type'] !== 'taxonomy') {
                continue;
            }

            $taxonomy = $field_config[$field_key]['taxonomy'];

            switch ($operator) {
                case 'equals':
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field' => 'term_id',
                        'terms' => intval($value),
                    );
                    break;

                case 'not_equals':
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field' => 'term_id',
                        'terms' => intval($value),
                        'operator' => 'NOT IN',
                    );
                    break;

                case 'is_empty':
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'operator' => 'NOT EXISTS',
                    );
                    break;

                case 'is_not_empty':
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'operator' => 'EXISTS',
                    );
                    break;
            }
        }

        return $tax_query;
    }

    /**
     * Enqueue scripts and styles
     */
    public static function enqueue_assets() {
        wp_enqueue_style(
            'vt-admin-columns',
            VOXEL_TOOLKIT_PLUGIN_URL . 'includes/admin-columns/assets/css/admin-columns.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        wp_enqueue_script(
            'vt-admin-filter-bar',
            VOXEL_TOOLKIT_PLUGIN_URL . 'includes/admin-columns/assets/js/admin-filter-bar.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );
    }
}
